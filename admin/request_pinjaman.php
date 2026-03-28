<?php
session_start();
require_once __DIR__ . '/../koneksi.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] === 'user') {
    header("Location: ../index.php");
    exit();
}

$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'Admin';
$Nama_Lengkap = isset($_SESSION['Nama_Lengkap']) ? $_SESSION['Nama_Lengkap'] : 'Admin User';
$Jabatan_Level = isset($_SESSION['Jabatan_Level']) ? $_SESSION['Jabatan_Level'] : 'Administrator';

// ========== Auto-create tables if missing (hosting compatibility) ==========
$kon->query("CREATE TABLE IF NOT EXISTS `Riwayat_Barang` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `Id_Barang` INT NOT NULL,
    `Status` VARCHAR(50) DEFAULT NULL,
    `Diperbarui_Oleh` VARCHAR(100) DEFAULT NULL,
    `Tanggal_Diperbarui` DATE DEFAULT NULL,
    `Catatan` TEXT DEFAULT NULL,
    `Nama_User` VARCHAR(200) DEFAULT NULL,
    `Divisi_Jabatan` VARCHAR(200) DEFAULT NULL,
    `id_krywn` VARCHAR(100) DEFAULT NULL,
    `Tgl_serah` DATE DEFAULT NULL,
    `Lokasi_User` VARCHAR(200) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$kon->query("CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `target_role` ENUM('admin','user') NOT NULL,
    `target_user_id` INT NULL,
    `type` VARCHAR(50) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT NULL,
    `reference_id` INT NULL,
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_target_role` (`target_role`, `is_read`, `created_at`),
    KEY `idx_target_user` (`target_user_id`, `is_read`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Also ensure request_pinjaman has the extra status values
$kon->query("ALTER TABLE `request_pinjaman` MODIFY COLUMN `status` VARCHAR(20) DEFAULT 'PENDING'");
// ========== End auto-create ==========

// Helper: append entry to Riwayat_Barang JSON column in peserta table
function appendRiwayatJson($kon, $id_aset, $entry) {
    $stmtGet = $kon->prepare('SELECT Riwayat_Barang FROM peserta WHERE id_peserta = ? LIMIT 1');
    $stmtGet->bind_param('i', $id_aset);
    $stmtGet->execute();
    $resGet = $stmtGet->get_result();
    $rowGet = $resGet ? $resGet->fetch_assoc() : null;
    $stmtGet->close();

    $riwayatList = [];
    if ($rowGet && !empty($rowGet['Riwayat_Barang'])) {
        $raw = html_entity_decode($rowGet['Riwayat_Barang'], ENT_QUOTES, 'UTF-8');
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) $riwayatList = $decoded;
    }

    if ($entry !== null) {
        if (isset($entry['_return_mode']) && $entry['_return_mode'] === true) {
            // RETURN: update last entry's tgl_pengembalian + append catatan
            unset($entry['_return_mode']);
            if (count($riwayatList) > 0) {
                $lastIdx = count($riwayatList) - 1;
                $riwayatList[$lastIdx]['tgl_pengembalian'] = $entry['tgl_pengembalian'] ?? date('Y-m-d');
                if (!empty($entry['catatan'])) {
                    $existing = trim($riwayatList[$lastIdx]['catatan'] ?? '');
                    $riwayatList[$lastIdx]['catatan'] = $existing !== '' ? $existing . ' | Return: ' . $entry['catatan'] : $entry['catatan'];
                }
            } else {
                $riwayatList[] = $entry;
            }
        } else {
            $riwayatList[] = $entry;
        }
    }

    $json = json_encode($riwayatList, JSON_UNESCAPED_UNICODE);
    $stmtUpd = $kon->prepare('UPDATE peserta SET Riwayat_Barang = ? WHERE id_peserta = ?');
    $stmtUpd->bind_param('si', $json, $id_aset);
    $stmtUpd->execute();
    $stmtUpd->close();
}

// Handling Approve and Reject POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  try {
    // Enable exceptions so prepare/execute failures are caught
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action'];

    if ($action === 'approve') {
        // Fetch request info
        $stmtReq = $kon->prepare("SELECT id_karyawan, id_aset FROM request_pinjaman WHERE id = ?");
        $stmtReq->bind_param("i", $request_id);
        $stmtReq->execute();
        $resReq = $stmtReq->get_result();
        
        if ($resReq->num_rows > 0) {
            $reqData = $resReq->fetch_assoc();
            $id_karyawan = $reqData['id_karyawan'];
            $id_aset = $reqData['id_aset'];
            
            // Get user details
            $stmtUser = $kon->prepare("SELECT id, Nama_Lengkap, Jabatan_Level, Region FROM users WHERE username = ?");
            $stmtUser->bind_param("s", $id_karyawan);
            $stmtUser->execute();
            $resUser = $stmtUser->get_result();
            if ($resUser->num_rows > 0) {
                $userData = $resUser->fetch_assoc();
                $nama_user = $userData['Nama_Lengkap'];
                $jabatan_user = $userData['Jabatan_Level'];
                $lokasi_user = $userData['Region'];
                $target_user_id_notif = (int)$userData['id'];
                
                // Update peserta: Status_Barang='IN USE', give it to user
                $stmtUpdate = $kon->prepare("UPDATE peserta SET Status_Barang = 'IN USE', User_Perangkat = ?, Jabatan = ?, Id_Karyawan = ?, Lokasi = ? WHERE id_peserta = ?");
                $stmtUpdate->bind_param("ssssi", $nama_user, $jabatan_user, $id_karyawan, $lokasi_user, $id_aset);
                $stmtUpdate->execute();
                
                // Add to Riwayat_Barang (History)
                $tanggal_sekarang = date('Y-m-d');
                $catatan = "Approved dari Request Pinjaman";
                $admin_name = $_SESSION['username'];
                $stmtHistory = $kon->prepare("INSERT INTO Riwayat_Barang (Id_Barang, Status, Diperbarui_Oleh, Tanggal_Diperbarui, Catatan, Nama_User, Divisi_Jabatan, id_krywn, Tgl_serah, Lokasi_User) VALUES (?, 'IN USE', ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmtHistory->bind_param("issssssss", $id_aset, $admin_name, $tanggal_sekarang, $catatan, $nama_user, $jabatan_user, $id_karyawan, $tanggal_sekarang, $lokasi_user);
                $stmtHistory->execute();

                // Also update Riwayat_Barang JSON column in peserta
                appendRiwayatJson($kon, $id_aset, [
                    'nama' => $nama_user,
                    'jabatan' => $jabatan_user,
                    'empleId' => $id_karyawan,
                    'lokasi' => $lokasi_user,
                    'tgl_serah_terima' => $tanggal_sekarang,
                    'tgl_pengembalian' => '',
                    'catatan' => $catatan,
                    'aksi' => 'TRANSFER',
                    'created_by' => $admin_name,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);

                // Notification: notify user about approval
                try {
                    $assetLabel = 'Aset #' . $id_aset;
                    $stmtAn = $kon->prepare("SELECT CONCAT(Nama_Barang, ' ', Merek, ' ', Type) AS label FROM peserta WHERE id_peserta = ? LIMIT 1");
                    if ($stmtAn) {
                        $stmtAn->bind_param('i', $id_aset);
                        if ($stmtAn->execute()) {
                            $anRes = $stmtAn->get_result();
                            $anRow = $anRes ? $anRes->fetch_assoc() : null;
                            if ($anRow && !empty($anRow['label'])) $assetLabel = trim($anRow['label']);
                        }
                        $stmtAn->close();
                    }
                    $notifTitle = 'Pinjaman Aset Disetujui';
                    $notifMsg = 'Request pinjaman "' . $assetLabel . '" telah disetujui oleh Admin.';
                    $notifType = 'pinjaman_approved';
                    $stmtNotif = $kon->prepare("INSERT INTO `notifications` (`target_role`, `target_user_id`, `type`, `title`, `message`, `reference_id`) VALUES ('user', ?, ?, ?, ?, ?)");
                    if ($stmtNotif) {
                        $stmtNotif->bind_param('isssi', $target_user_id_notif, $notifType, $notifTitle, $notifMsg, $request_id);
                        @$stmtNotif->execute();
                        @$stmtNotif->close();
                    }
                } catch (Throwable $ne) {
                    error_log('Notification insert error (approve pinjaman): ' . $ne->getMessage());
                }
            }
            
            // Update request status
            $stmtApprove = $kon->prepare("UPDATE request_pinjaman SET status = 'APPROVED' WHERE id = ?");
            $stmtApprove->bind_param("i", $request_id);
            $stmtApprove->execute();
            
            $_SESSION['flash_success'] = 'Request berhasil disetujui.';
        }
    } elseif ($action === 'reject') {
        $alasan = isset($_POST['alasan_reject']) ? trim($_POST['alasan_reject']) : '';
        $stmtReject = $kon->prepare("UPDATE request_pinjaman SET status = 'REJECTED', alasan_reject = ? WHERE id = ?");
        $stmtReject->bind_param("si", $alasan, $request_id);
        $stmtReject->execute();

        // Notification: notify user about rejection
        try {
            $stmtReqInfo = $kon->prepare("SELECT r.id_karyawan, r.id_aset, u.id AS user_id FROM request_pinjaman r LEFT JOIN users u ON r.id_karyawan = u.username WHERE r.id = ? LIMIT 1");
            if ($stmtReqInfo) {
                $stmtReqInfo->bind_param('i', $request_id);
                if ($stmtReqInfo->execute()) {
                    $reqInfoRes = $stmtReqInfo->get_result();
                    $reqInfoRow = $reqInfoRes ? $reqInfoRes->fetch_assoc() : null;
                    if ($reqInfoRow && !empty($reqInfoRow['user_id'])) {
                        $assetLabel = 'Aset #' . ($reqInfoRow['id_aset'] ?? '');
                        $stmtAn2 = $kon->prepare("SELECT CONCAT(Nama_Barang, ' ', Merek, ' ', Type) AS label FROM peserta WHERE id_peserta = ? LIMIT 1");
                        if ($stmtAn2) {
                            $stmtAn2->bind_param('i', $reqInfoRow['id_aset']);
                            if ($stmtAn2->execute()) {
                                $an2Res = $stmtAn2->get_result();
                                $an2Row = $an2Res ? $an2Res->fetch_assoc() : null;
                                if ($an2Row && !empty($an2Row['label'])) $assetLabel = trim($an2Row['label']);
                            }
                            $stmtAn2->close();
                        }
                        $targetUid = (int)$reqInfoRow['user_id'];
                        $notifTitle = 'Pinjaman Aset Ditolak';
                        $notifMsg = 'Request pinjaman "' . $assetLabel . '" ditolak.' . ($alasan !== '' ? ' Alasan: ' . $alasan : '');
                        $notifType = 'pinjaman_rejected';
                        $stmtNotif = $kon->prepare("INSERT INTO `notifications` (`target_role`, `target_user_id`, `type`, `title`, `message`, `reference_id`) VALUES ('user', ?, ?, ?, ?, ?)");
                        if ($stmtNotif) {
                            $stmtNotif->bind_param('isssi', $targetUid, $notifType, $notifTitle, $notifMsg, $request_id);
                            @$stmtNotif->execute();
                            @$stmtNotif->close();
                        }
                    }
                }
                $stmtReqInfo->close();
            }
        } catch (Throwable $ne) {
            error_log('Notification insert error (reject pinjaman): ' . $ne->getMessage());
        }

        $_SESSION['flash_success'] = 'Request berhasil ditolak.';
    } elseif ($action === 'return_asset') {
        // ========== RETURN ASSET ==========
        $stmtReq = $kon->prepare("SELECT id_karyawan, id_aset FROM request_pinjaman WHERE id = ? AND status = 'APPROVED'");
        $stmtReq->bind_param("i", $request_id);
        $stmtReq->execute();
        $resReq = $stmtReq->get_result();

        if ($resReq->num_rows > 0) {
            $reqData = $resReq->fetch_assoc();
            $id_aset = $reqData['id_aset'];
            $id_karyawan = $reqData['id_karyawan'];

            // Get asset info for notification
            $assetLabel = 'Aset #' . $id_aset;
            $stmtAn = $kon->prepare("SELECT CONCAT(Nama_Barang, ' ', Merek, ' ', Type) AS label FROM peserta WHERE id_peserta = ? LIMIT 1");
            if ($stmtAn) {
                $stmtAn->bind_param('i', $id_aset);
                if ($stmtAn->execute()) {
                    $anRes = $stmtAn->get_result();
                    $anRow = $anRes ? $anRes->fetch_assoc() : null;
                    if ($anRow && !empty($anRow['label'])) $assetLabel = trim($anRow['label']);
                }
                $stmtAn->close();
            }

            // Update peserta: Status_Barang='SPARE', clear user assignment
            $stmtUpdate = $kon->prepare("UPDATE peserta SET Status_Barang = 'READY', User_Perangkat = '', Jabatan = '', Id_Karyawan = '', Lokasi = '' WHERE id_peserta = ?");
            $stmtUpdate->bind_param("i", $id_aset);
            $stmtUpdate->execute();

            // Add to Riwayat_Barang
            $tanggal_sekarang = date('Y-m-d');
            $catatan = "Return aset dari pinjaman - dikembalikan oleh Admin";
            $admin_name = $_SESSION['username'];
            $stmtHistory = $kon->prepare("INSERT INTO Riwayat_Barang (Id_Barang, Status, Diperbarui_Oleh, Tanggal_Diperbarui, Catatan) VALUES (?, 'READY', ?, ?, ?)");
            $stmtHistory->bind_param("isss", $id_aset, $admin_name, $tanggal_sekarang, $catatan);
            $stmtHistory->execute();

            // Also update Riwayat_Barang JSON column in peserta (return mode)
            appendRiwayatJson($kon, $id_aset, [
                '_return_mode' => true,
                'tgl_pengembalian' => $tanggal_sekarang,
                'catatan' => $catatan,
                'aksi' => 'RETURN',
                'created_by' => $admin_name,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            // Update request status
            $stmtReturn = $kon->prepare("UPDATE request_pinjaman SET status = 'RETURNED' WHERE id = ?");
            $stmtReturn->bind_param("i", $request_id);
            $stmtReturn->execute();

            // Notify user
            try {
                $stmtUser = $kon->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
                if ($stmtUser) {
                    $stmtUser->bind_param('s', $id_karyawan);
                    $stmtUser->execute();
                    $userRes = $stmtUser->get_result();
                    $userRow = $userRes ? $userRes->fetch_assoc() : null;
                    if ($userRow) {
                        $targetUid = (int)$userRow['id'];
                        $notifTitle = 'Aset Dikembalikan (Return)';
                        $notifMsg = 'Aset "' . $assetLabel . '" telah dikembalikan (return) oleh Admin.';
                        $notifType = 'pinjaman_returned';
                        $stmtNotif = $kon->prepare("INSERT INTO `notifications` (`target_role`, `target_user_id`, `type`, `title`, `message`, `reference_id`) VALUES ('user', ?, ?, ?, ?, ?)");
                        if ($stmtNotif) {
                            $stmtNotif->bind_param('isssi', $targetUid, $notifType, $notifTitle, $notifMsg, $request_id);
                            @$stmtNotif->execute();
                            @$stmtNotif->close();
                        }
                    }
                    $stmtUser->close();
                }
            } catch (Throwable $ne) {
                error_log('Notification insert error (return pinjaman): ' . $ne->getMessage());
            }

            $_SESSION['flash_success'] = 'Aset berhasil di-return. Status: READY.';
        } else {
            $_SESSION['flash_error'] = 'Request tidak ditemukan atau belum disetujui.';
        }
        $stmtReq->close();

    } elseif ($action === 'transfer_asset') {
        // ========== TRANSFER ASSET ==========
        $transfer_to = isset($_POST['transfer_to']) ? trim($_POST['transfer_to']) : '';
        
        $stmtReq = $kon->prepare("SELECT id_karyawan, id_aset FROM request_pinjaman WHERE id = ? AND status = 'APPROVED'");
        $stmtReq->bind_param("i", $request_id);
        $stmtReq->execute();
        $resReq = $stmtReq->get_result();

        if ($resReq->num_rows > 0 && $transfer_to !== '') {
            $reqData = $resReq->fetch_assoc();
            $id_aset = $reqData['id_aset'];
            $old_karyawan = $reqData['id_karyawan'];

            // Get new user details
            $stmtNewUser = $kon->prepare("SELECT Nama_Lengkap, Jabatan_Level, Region FROM users WHERE username = ?");
            $stmtNewUser->bind_param("s", $transfer_to);
            $stmtNewUser->execute();
            $resNewUser = $stmtNewUser->get_result();

            if ($resNewUser->num_rows > 0) {
                $newUserData = $resNewUser->fetch_assoc();

                // Get asset info for notification
                $assetLabel = 'Aset #' . $id_aset;
                $stmtAn = $kon->prepare("SELECT CONCAT(Nama_Barang, ' ', Merek, ' ', Type) AS label FROM peserta WHERE id_peserta = ? LIMIT 1");
                if ($stmtAn) {
                    $stmtAn->bind_param('i', $id_aset);
                    if ($stmtAn->execute()) {
                        $anRes = $stmtAn->get_result();
                        $anRow = $anRes ? $anRes->fetch_assoc() : null;
                        if ($anRow && !empty($anRow['label'])) $assetLabel = trim($anRow['label']);
                    }
                    $stmtAn->close();
                }

                // Update peserta: transfer to new user
                $stmtUpdate = $kon->prepare("UPDATE peserta SET User_Perangkat = ?, Jabatan = ?, Id_Karyawan = ?, Lokasi = ? WHERE id_peserta = ?");
                $stmtUpdate->bind_param("ssssi", $newUserData['Nama_Lengkap'], $newUserData['Jabatan_Level'], $transfer_to, $newUserData['Region'], $id_aset);
                $stmtUpdate->execute();

                // Add to Riwayat_Barang
                $tanggal_sekarang = date('Y-m-d');
                $catatan = "Transfer aset dari " . $old_karyawan . " ke " . $transfer_to;
                $admin_name = $_SESSION['username'];
                $stmtHistory = $kon->prepare("INSERT INTO Riwayat_Barang (Id_Barang, Status, Diperbarui_Oleh, Tanggal_Diperbarui, Catatan, Nama_User, Divisi_Jabatan, id_krywn, Tgl_serah, Lokasi_User) VALUES (?, 'IN USE', ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmtHistory->bind_param("issssssss", $id_aset, $admin_name, $tanggal_sekarang, $catatan, $newUserData['Nama_Lengkap'], $newUserData['Jabatan_Level'], $transfer_to, $tanggal_sekarang, $newUserData['Region']);
                $stmtHistory->execute();

                // Also update Riwayat_Barang JSON column in peserta
                appendRiwayatJson($kon, $id_aset, [
                    'nama' => $newUserData['Nama_Lengkap'],
                    'jabatan' => $newUserData['Jabatan_Level'],
                    'empleId' => $transfer_to,
                    'lokasi' => $newUserData['Region'],
                    'tgl_serah_terima' => $tanggal_sekarang,
                    'tgl_pengembalian' => '',
                    'catatan' => $catatan,
                    'aksi' => 'TRANSFER',
                    'created_by' => $admin_name,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);

                // Update original request status
                $stmtTransfer = $kon->prepare("UPDATE request_pinjaman SET status = 'TRANSFERRED' WHERE id = ?");
                $stmtTransfer->bind_param("i", $request_id);
                $stmtTransfer->execute();

                // Notify original user about transfer
                try {
                    $stmtUser = $kon->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
                    if ($stmtUser) {
                        $stmtUser->bind_param('s', $old_karyawan);
                        $stmtUser->execute();
                        $userRes = $stmtUser->get_result();
                        $userRow = $userRes ? $userRes->fetch_assoc() : null;
                        if ($userRow) {
                            $targetUid = (int)$userRow['id'];
                            $notifTitle = 'Aset Ditransfer';
                            $notifMsg = 'Aset "' . $assetLabel . '" telah ditransfer ke ' . $newUserData['Nama_Lengkap'] . ' oleh Admin.';
                            $notifType = 'pinjaman_transferred';
                            $stmtNotif = $kon->prepare("INSERT INTO `notifications` (`target_role`, `target_user_id`, `type`, `title`, `message`, `reference_id`) VALUES ('user', ?, ?, ?, ?, ?)");
                            if ($stmtNotif) {
                                $stmtNotif->bind_param('isssi', $targetUid, $notifType, $notifTitle, $notifMsg, $request_id);
                                @$stmtNotif->execute();
                                @$stmtNotif->close();
                            }
                        }
                        $stmtUser->close();
                    }
                } catch (Throwable $ne) {
                    error_log('Notification insert error (transfer pinjaman): ' . $ne->getMessage());
                }

                $_SESSION['flash_success'] = 'Aset berhasil ditransfer ke ' . htmlspecialchars($newUserData['Nama_Lengkap']) . '.';
            } else {
                $_SESSION['flash_error'] = 'User tujuan transfer tidak ditemukan.';
            }
            $stmtNewUser->close();
        } else {
            $_SESSION['flash_error'] = 'Request tidak valid atau user tujuan belum diisi.';
        }
        $stmtReq->close();
    }

  } catch (Throwable $e) {
    // Log the real error for debugging
    error_log('request_pinjaman POST error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    $_SESSION['flash_error'] = 'Terjadi kesalahan server: ' . $e->getMessage();
  } finally {
    // Restore default reporting
    mysqli_report(MYSQLI_REPORT_OFF);
  }

    header("Location: request_pinjaman.php");
    exit();
}

// ========== AJAX HANDLER ==========
if (isset($_GET['action']) && $_GET['action'] === 'ajax_get_requests') {
    header('Content-Type: application/json; charset=utf-8');

    $ajax_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $ajax_limit = 10;

    $countQuery = $kon->query("SELECT COUNT(*) as total FROM request_pinjaman");
    $ajax_total = $countQuery->fetch_assoc()['total'];
    $ajax_total_pages = max(1, ceil($ajax_total / $ajax_limit));
    $ajax_page = min($ajax_page, $ajax_total_pages);
    $ajax_offset = ($ajax_page - 1) * $ajax_limit;

    $query = "
        SELECT r.*, p.Nama_Barang, p.Merek, p.Type, p.Serial_Number, u.Nama_Lengkap, u.Jabatan_Level
        FROM request_pinjaman r
        JOIN peserta p ON r.id_aset = p.id_peserta
        LEFT JOIN users u ON r.id_karyawan = u.username
        ORDER BY r.created_at DESC
        LIMIT ?, ?
    ";
    $stmt = $kon->prepare($query);
    $stmt->bind_param("ii", $ajax_offset, $ajax_limit);
    $stmt->execute();
    $requests = $stmt->get_result();

    // Build table HTML
    $table_html = '';
    if ($requests->num_rows > 0) {
        while ($row = $requests->fetch_assoc()) {
            $statusBadge = '';
            if ($row['status'] === 'PENDING') {
                $statusBadge = '<span class="inline-flex items-center px-2.5 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-lg border border-yellow-200"><i class="fas fa-clock mr-1.5"></i> Pending</span>';
            } elseif ($row['status'] === 'APPROVED') {
                $statusBadge = '<span class="inline-flex items-center px-2.5 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-lg border border-green-200"><i class="fas fa-check-circle mr-1.5"></i> In Use</span>';
            } elseif ($row['status'] === 'RETURNED') {
                $statusBadge = '<span class="inline-flex items-center px-2.5 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded-lg border border-blue-200"><i class="fas fa-undo mr-1.5"></i> Returned</span>';
            } elseif ($row['status'] === 'TRANSFERRED') {
                $statusBadge = '<span class="inline-flex items-center px-2.5 py-1 text-xs font-medium bg-purple-100 text-purple-800 rounded-lg border border-purple-200"><i class="fas fa-exchange-alt mr-1.5"></i> Transferred</span>';
            } elseif ($row['status'] === 'REJECTED') {
                $statusBadge = '<span class="inline-flex items-center px-2.5 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-lg border border-red-200"><i class="fas fa-times-circle mr-1.5"></i> Rejected</span>';
            }

            $actionHtml = '';
            if ($row['status'] === 'PENDING') {
                $actionHtml = '<div class="flex items-center justify-center gap-2">
                    <button type="button" onclick="openApproveModal(' . (int)$row['id'] . ')" class="p-2 bg-green-50 text-green-600 hover:bg-green-100 hover:text-green-700 rounded-lg transition-colors border border-green-200" title="Setujui"><i class="fas fa-check"></i></button>
                    <button type="button" onclick="openRejectModal(' . (int)$row['id'] . ')" class="p-2 bg-red-50 text-red-600 hover:bg-red-100 hover:text-red-700 rounded-lg transition-colors border border-red-200" title="Tolak"><i class="fas fa-times"></i></button>
                </div>';
            } elseif ($row['status'] === 'APPROVED') {
                $actionHtml = '<div class="flex items-center justify-center gap-2">
                    <button type="button" onclick="openReturnModal(' . (int)$row['id'] . ')" class="p-1.5 bg-blue-50 text-blue-600 hover:bg-blue-100 hover:text-blue-700 rounded-lg transition-colors border border-blue-200 text-xs px-2" title="Return"><i class="fas fa-undo mr-1"></i>Return</button>
                    <button type="button" onclick="openTransferModal(' . (int)$row['id'] . ')" class="p-1.5 bg-purple-50 text-purple-600 hover:bg-purple-100 hover:text-purple-700 rounded-lg transition-colors border border-purple-200 text-xs px-2" title="Transfer"><i class="fas fa-exchange-alt mr-1"></i>Transfer</button>
                </div>';
            } else {
                $actionHtml = '<span class="text-gray-400 text-sm"><i class="fas fa-minus"></i></span>';
            }

            $table_html .= '<tr class="hover:bg-slate-50/50 transition-colors">
                <td class="px-6 py-4 text-sm text-gray-600">
                    <div class="font-medium text-gray-900">' . htmlspecialchars($row['tgl_pinjam']) . '</div>
                    <div class="text-xs text-gray-400">Diajukan: ' . date('d M Y H:i', strtotime($row['created_at'])) . '</div>
                </td>
                <td class="px-6 py-4">
                    <div class="font-semibold text-gray-900">' . htmlspecialchars($row['Nama_Lengkap'] ?? $row['id_karyawan']) . '</div>
                    <div class="text-xs text-gray-500 border border-gray-200 bg-gray-50 inline-block px-2 py-0.5 rounded mt-1">' . htmlspecialchars($row['id_karyawan']) . ' &bull; ' . htmlspecialchars($row['Jabatan_Level'] ?? '-') . '</div>
                </td>
                <td class="px-6 py-4">
                    <div class="font-medium text-gray-900">' . htmlspecialchars($row['Nama_Barang'].' '.$row['Merek'].' '.$row['Type']) . '</div>
                    <div class="text-xs font-mono text-gray-500 mt-1">SN: ' . htmlspecialchars($row['Serial_Number']) . '</div>
                </td>
                <td class="px-6 py-4 text-sm text-gray-600 max-w-xs truncate" title="' . htmlspecialchars($row['catatan'] ?? '-') . '">' . htmlspecialchars($row['catatan'] ?: '-') . '</td>
                <td class="px-6 py-4">' . $statusBadge . '</td>
                <td class="px-6 py-4 text-center">' . $actionHtml . '</td>
            </tr>';
        }
    } else {
        $table_html = '<tr><td colspan="6" class="px-6 py-12 text-center text-gray-500 bg-gray-50/50"><div class="mb-3 text-gray-300"><i class="fas fa-inbox text-4xl"></i></div>Belum ada request pinjaman yang masuk.</td></tr>';
    }

    // Build pagination HTML (same style as index.php)
    $pag_html = '';
    $pag_html .= '<div class="mt-6 flex flex-col items-center"><div class="w-full max-w-4xl flex flex-col items-center space-y-2">';
    $pag_html .= '<div class="text-sm text-gray-600 text-center">Total Data: <strong>' . $ajax_total . '</strong> &mdash; Showing ' . min($ajax_offset + 1, $ajax_total) . ' to ' . min($ajax_offset + $ajax_limit, $ajax_total) . ' of ' . $ajax_total . ' results</div>';
    if ($ajax_total_pages > 1) {
        $pag_html .= '<nav class="inline-flex rounded-md shadow-sm -space-x-px justify-center">';

        if ($ajax_page > 1) {
            $pag_html .= '<a href="#" data-page="' . ($ajax_page - 1) . '" class="pagination-link relative inline-flex items-center px-3 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50"><i class="fas fa-chevron-left"></i><span class="ml-1">Prev</span></a>';
        }

        $pStart = max(1, $ajax_page - 2);
        $pEnd = min($ajax_total_pages, $ajax_page + 2);
        if ($pStart > 1) {
            $pag_html .= '<a href="#" data-page="1" class="pagination-link relative inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">1</a>';
            if ($pStart > 2) $pag_html .= '<span class="relative inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-500">...</span>';
        }
        for ($i = $pStart; $i <= $pEnd; $i++) {
            if ($i == $ajax_page) {
                $pag_html .= '<span class="relative z-10 inline-flex items-center px-3 py-2 border border-orange-500 bg-orange-50 text-sm font-medium text-orange-600">' . $i . '</span>';
            } else {
                $pag_html .= '<a href="#" data-page="' . $i . '" class="pagination-link relative inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">' . $i . '</a>';
            }
        }
        if ($pEnd < $ajax_total_pages) {
            if ($pEnd < $ajax_total_pages - 1) $pag_html .= '<span class="relative inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-500">...</span>';
            $pag_html .= '<a href="#" data-page="' . $ajax_total_pages . '" class="pagination-link relative inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">' . $ajax_total_pages . '</a>';
        }

        if ($ajax_page < $ajax_total_pages) {
            $pag_html .= '<a href="#" data-page="' . ($ajax_page + 1) . '" class="pagination-link relative inline-flex items-center px-3 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50"><span class="mr-1">Next</span><i class="fas fa-chevron-right"></i></a>';
        }

        $pag_html .= '</nav>';
    }
    $pag_html .= '</div></div>';

    echo json_encode([
        'table_html' => $table_html,
        'pagination_html' => $pag_html,
        'current_page' => $ajax_page,
        'total_pages' => $ajax_total_pages,
        'total_records' => (int)$ajax_total
    ]);
    exit();
}
// ========== END AJAX HANDLER ==========

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Fetch requests
$query = "
    SELECT r.*, p.Nama_Barang, p.Merek, p.Type, p.Serial_Number, u.Nama_Lengkap, u.Jabatan_Level
    FROM request_pinjaman r
    JOIN peserta p ON r.id_aset = p.id_peserta
    LEFT JOIN users u ON r.id_karyawan = u.username
    ORDER BY r.created_at DESC
    LIMIT ?, ?
";
$stmt = $kon->prepare($query);
$stmt->bind_param("ii", $offset, $limit);
$stmt->execute();
$requests = $stmt->get_result();

$countQuery = $kon->query("SELECT COUNT(*) as total FROM request_pinjaman");
$totalData = $countQuery->fetch_assoc()['total'];
$totalPages = ceil($totalData / $limit);

// Fetch user list for transfer dropdown
$userList = [];
$userQuery = $kon->query("SELECT username, Nama_Lengkap, Jabatan_Level FROM users WHERE Status_Akun = 'Aktif' ORDER BY Nama_Lengkap ASC");
if ($userQuery) {
    while ($uRow = $userQuery->fetch_assoc()) $userList[] = $uRow;
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Pinjaman - Asset IT</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../global_dashboard.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
    </style>
</head>
<body class="bg-slate-50">

    <!-- Loading Animation -->
    <div id="loadingOverlay" class="loading-overlay">
        <div class="loading-content">
            <div class="loading-logo">
                <img src="logo_form/logo ckt fix.png" alt="Logo Perusahaan" class="logo-image">
            </div>
            <h1 class="text-2xl md:text-3xl font-bold mb-2">PT CIPTA KARYA TECHNOLOGY</h1>
            <p class="text-gray-300 mb-4">Loading Sistem ASSET...</p>
            <div class="loading-bars">
                <div class="loading-bar"></div>
                <div class="loading-bar"></div>
                <div class="loading-bar"></div>
            </div>
            <div class="loading-progress">
                <div class="loading-progress-bar"></div>
            </div>
        </div>
    </div>

    <?php
    // Include sidebar + navbar admin
    $activePage = 'request_pinjaman';
    require_once __DIR__ . '/sidebar_admin.php';
    ?>

    <!-- Flash message component -->
    <?php if (isset($_SESSION['flash_success'])): ?>
        <div id="flash-success" class="fixed top-5 right-5 z-[9999] bg-green-500 text-white px-6 py-4 rounded-xl shadow-lg flex items-center gap-3 transform transition-all duration-500 translate-y-0 opacity-100">
            <i class="fas fa-check-circle text-xl"></i>
            <span class="font-medium"><?= htmlspecialchars($_SESSION['flash_success']) ?></span>
            <button onclick="document.getElementById('flash-success').remove()" class="ml-4 hover:text-green-200">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <?php unset($_SESSION['flash_success']); ?>
        <script>setTimeout(() => { const el = document.getElementById('flash-success'); if(el) el.remove(); }, 3000);</script>
    <?php endif; ?>

    <?php if (isset($_SESSION['flash_error'])): ?>
        <div id="flash-error" class="fixed top-5 right-5 z-[9999] bg-red-500 text-white px-6 py-4 rounded-xl shadow-lg flex items-center gap-3 transform transition-all duration-500 translate-y-0 opacity-100 max-w-lg">
            <i class="fas fa-exclamation-triangle text-xl"></i>
            <span class="font-medium text-sm"><?= htmlspecialchars($_SESSION['flash_error']) ?></span>
            <button onclick="document.getElementById('flash-error').remove()" class="ml-4 hover:text-red-200 flex-shrink-0">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <?php unset($_SESSION['flash_error']); ?>
        <script>setTimeout(() => { const el = document.getElementById('flash-error'); if(el) el.remove(); }, 8000);</script>
    <?php endif; ?>

    <div id="main-content-wrapper" class="lg:ml-60 transition-all duration-300 ease-in-out">
    <script>
        (function() {
            var wrapper = document.getElementById('main-content-wrapper');
            if (!wrapper) return;
            function applyState() {
                if (window.innerWidth >= 1024) {
                    var collapsed = localStorage.getItem('sidebarCollapsed') === '1';
                    wrapper.style.marginLeft = collapsed ? '0' : '';
                } else {
                    wrapper.style.marginLeft = '0';
                }
            }
            applyState();
            window.addEventListener('sidebarToggled', function(e) { applyState(); });
            window.addEventListener('resize', function() { applyState(); });
        })();
    </script>
    <main class="mt-16 min-h-screen bg-slate-50 p-6 pt-8">
        <div class="max-w-7xl mx-auto">
                
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Request Pinjaman</h1>
                    <p class="text-gray-500">Persetujuan pengajuan pinjaman aset IT dari Karyawan</p>
                </div>
            </div>

                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-50 border-b border-gray-200">
                                    <th class="px-6 py-4 text-sm font-semibold text-gray-700">Tanggal</th>
                                    <th class="px-6 py-4 text-sm font-semibold text-gray-700">Pemohon</th>
                                    <th class="px-6 py-4 text-sm font-semibold text-gray-700">Aset</th>
                                    <th class="px-6 py-4 text-sm font-semibold text-gray-700">Catatan</th>
                                    <th class="px-6 py-4 text-sm font-semibold text-gray-700">Status</th>
                                    <th class="px-6 py-4 text-sm font-semibold text-gray-700 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="request-tbody" class="divide-y divide-gray-100">
                                <?php if ($requests->num_rows > 0): ?>
                                    <?php while ($row = $requests->fetch_assoc()): ?>
                                        <tr class="hover:bg-slate-50/50 transition-colors">
                                            <td class="px-6 py-4 text-sm text-gray-600">
                                                <div class="font-medium text-gray-900"><?= htmlspecialchars($row['tgl_pinjam']) ?></div>
                                                <div class="text-xs text-gray-400">Diajukan: <?= date('d M Y H:i', strtotime($row['created_at'])) ?></div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="font-semibold text-gray-900"><?= htmlspecialchars($row['Nama_Lengkap'] ?? $row['id_karyawan']) ?></div>
                                                <div class="text-xs text-gray-500 border border-gray-200 bg-gray-50 inline-block px-2 py-0.5 rounded mt-1">
                                                    <?= htmlspecialchars($row['id_karyawan']) ?> • <?= htmlspecialchars($row['Jabatan_Level'] ?? '-') ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="font-medium text-gray-900"><?= htmlspecialchars($row['Nama_Barang'].' '.$row['Merek'].' '.$row['Type']) ?></div>
                                                <div class="text-xs font-mono text-gray-500 mt-1">SN: <?= htmlspecialchars($row['Serial_Number']) ?></div>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-600 max-w-xs truncate" title="<?= htmlspecialchars($row['catatan'] ?? '-') ?>">
                                                <?= htmlspecialchars($row['catatan'] ?: '-') ?>
                                            </td>
                                            <td class="px-6 py-4">
                                                <?php if ($row['status'] === 'PENDING'): ?>
                                                    <span class="inline-flex items-center px-2.5 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-lg border border-yellow-200">
                                                        <i class="fas fa-clock mr-1.5"></i> Pending
                                                    </span>
                                                <?php elseif ($row['status'] === 'APPROVED'): ?>
                                                    <span class="inline-flex items-center px-2.5 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-lg border border-green-200">
                                                        <i class="fas fa-check-circle mr-1.5"></i> In Use
                                                    </span>
                                                <?php elseif ($row['status'] === 'RETURNED'): ?>
                                                    <span class="inline-flex items-center px-2.5 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded-lg border border-blue-200">
                                                        <i class="fas fa-undo mr-1.5"></i> Returned
                                                    </span>
                                                <?php elseif ($row['status'] === 'TRANSFERRED'): ?>
                                                    <span class="inline-flex items-center px-2.5 py-1 text-xs font-medium bg-purple-100 text-purple-800 rounded-lg border border-purple-200">
                                                        <i class="fas fa-exchange-alt mr-1.5"></i> Transferred
                                                    </span>
                                                <?php elseif ($row['status'] === 'REJECTED'): ?>
                                                    <span class="inline-flex items-center px-2.5 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-lg border border-red-200">
                                                        <i class="fas fa-times-circle mr-1.5"></i> Rejected
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 text-center">
                                                <?php if ($row['status'] === 'PENDING'): ?>
                                                    <div class="flex items-center justify-center gap-2">
                                                        <button type="button" onclick="openApproveModal(<?= $row['id'] ?>)" class="p-2 bg-green-50 text-green-600 hover:bg-green-100 hover:text-green-700 rounded-lg transition-colors border border-green-200" title="Setujui">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                        <button type="button" onclick="openRejectModal(<?= $row['id'] ?>)" class="p-2 bg-red-50 text-red-600 hover:bg-red-100 hover:text-red-700 rounded-lg transition-colors border border-red-200" title="Tolak">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    </div>
                                                <?php elseif ($row['status'] === 'APPROVED'): ?>
                                                    <div class="flex items-center justify-center gap-2">
                                                        <button type="button" onclick="openReturnModal(<?= $row['id'] ?>)" class="p-1.5 bg-blue-50 text-blue-600 hover:bg-blue-100 hover:text-blue-700 rounded-lg transition-colors border border-blue-200 text-xs px-2" title="Return">
                                                            <i class="fas fa-undo mr-1"></i>Return
                                                        </button>
                                                        <button type="button" onclick="openTransferModal(<?= $row['id'] ?>)" class="p-1.5 bg-purple-50 text-purple-600 hover:bg-purple-100 hover:text-purple-700 rounded-lg transition-colors border border-purple-200 text-xs px-2" title="Transfer">
                                                            <i class="fas fa-exchange-alt mr-1"></i>Transfer
                                                        </button>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-gray-400 text-sm"><i class="fas fa-minus"></i></span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="px-6 py-12 text-center text-gray-500 bg-gray-50/50">
                                            <div class="mb-3 text-gray-300"><i class="fas fa-inbox text-4xl"></i></div>
                                            Belum ada request pinjaman yang masuk.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination (AJAX-powered, index.php style) -->
                    <div id="pagination-container">
                        <div class="mt-6 flex flex-col items-center">
                            <div class="w-full max-w-4xl flex flex-col items-center space-y-2">
                                <div class="text-sm text-gray-600 text-center">Total Data: <strong><?= $totalData ?></strong> &mdash; Showing <?= min($offset + 1, $totalData) ?> to <?= min($offset + $limit, $totalData) ?> of <?= $totalData ?> results</div>
                    <?php if ($totalPages > 1): ?>

                                <nav class="inline-flex rounded-md shadow-sm -space-x-px justify-center">
                                    <?php if ($page > 1): ?>
                                        <a href="#" data-page="<?= $page - 1 ?>" class="pagination-link relative inline-flex items-center px-3 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50"><i class="fas fa-chevron-left"></i><span class="ml-1">Prev</span></a>
                                    <?php endif; ?>
                                    <?php
                                    $start = max(1, $page - 2); $end = min($totalPages, $page + 2);
                                    if ($start > 1) {
                                        echo '<a href="#" data-page="1" class="pagination-link relative inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">1</a>';
                                        if ($start > 2) echo '<span class="relative inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-500">...</span>';
                                    }
                                    for ($i = $start; $i <= $end; $i++) {
                                        if ($i == $page) echo '<span class="relative z-10 inline-flex items-center px-3 py-2 border border-orange-500 bg-orange-50 text-sm font-medium text-orange-600">' . $i . '</span>';
                                        else echo '<a href="#" data-page="' . $i . '" class="pagination-link relative inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">' . $i . '</a>';
                                    }
                                    if ($end < $totalPages) {
                                        if ($end < $totalPages - 1) echo '<span class="relative inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-500">...</span>';
                                        echo '<a href="#" data-page="' . $totalPages . '" class="pagination-link relative inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">' . $totalPages . '</a>';
                                    }
                                    ?>
                                    <?php if ($page < $totalPages): ?>
                                        <a href="#" data-page="<?= $page + 1 ?>" class="pagination-link relative inline-flex items-center px-3 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50"><span class="mr-1">Next</span><i class="fas fa-chevron-right"></i></a>
                                    <?php endif; ?>
                                </nav>
                    <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

        </div>
    </main>
    </div><!-- /main-content-wrapper -->

    <!-- Reject Modal -->
    <div id="reject-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeRejectModal()"></div>
        <div class="relative w-full max-w-md bg-white rounded-2xl shadow-2xl overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 border-b bg-red-50">
                <div class="flex items-center gap-3 text-red-700">
                    <i class="fas fa-exclamation-triangle text-xl"></i>
                    <h3 class="text-lg font-bold">Tolak Request Pinjaman</h3>
                </div>
                <button type="button" class="text-red-400 hover:text-red-700 transition-colors" onclick="closeRejectModal()">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form method="POST" class="p-6">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" id="reject_request_id" name="request_id" value="">
                
                <div class="mb-6">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Alasan Penolakan <span class="text-red-500">*</span></label>
                    <textarea name="alasan_reject" required rows="3" placeholder="Misal: Aset sedang di-maintenance..." class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:ring-2 focus:ring-red-500 focus:border-red-500 text-sm resize-none"></textarea>
                    <p class="mt-2 text-xs text-gray-500">Alasan ini akan dapat dilihat oleh pemohon di riwayat mereka.</p>
                </div>
                
                <div class="flex justify-end gap-3">
                    <button type="button" class="px-5 py-2.5 text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-xl font-medium transition-colors" onclick="closeRejectModal()">Batal</button>
                    <button type="submit" class="px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-xl font-medium transition-colors flex items-center gap-2">
                        <i class="fas fa-times-circle"></i> Konfirmasi Tolak
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Hide loading overlay
        window.addEventListener('load', function() {
            const overlay = document.getElementById('loadingOverlay');
            if (overlay) {
                overlay.style.pointerEvents = 'none';
                overlay.style.opacity = '0';
                setTimeout(function() { overlay.style.display = 'none'; }, 500);
            }
        });

        function openApproveModal(id) {
            document.getElementById('approve_request_id').value = id;
            document.getElementById('approve-modal').classList.remove('hidden');
        }
        function closeApproveModal() {
            document.getElementById('approve-modal').classList.add('hidden');
        }
        function openRejectModal(id) {
            document.getElementById('reject_request_id').value = id;
            document.getElementById('reject-modal').classList.remove('hidden');
        }
        function closeRejectModal() {
            document.getElementById('reject-modal').classList.add('hidden');
        }

        // ========== AJAX PAGINATION ==========
        let currentPage = <?= $page ?>;

        function fetchRequests(page) {
            const tbody = document.getElementById('request-tbody');
            const pagContainer = document.getElementById('pagination-container');

            // Show loading state
            tbody.style.opacity = '0.5';
            tbody.style.pointerEvents = 'none';

            fetch('?action=ajax_get_requests&page=' + page)
                .then(r => r.json())
                .then(data => {
                    tbody.innerHTML = data.table_html;
                    pagContainer.innerHTML = data.pagination_html;
                    currentPage = data.current_page;

                    // Smooth scroll to table top
                    tbody.closest('.bg-white')?.scrollIntoView({ behavior: 'smooth', block: 'start' });

                    // Re-bind pagination links
                    bindPaginationLinks();

                    tbody.style.opacity = '1';
                    tbody.style.pointerEvents = '';
                })
                .catch(err => {
                    console.error('AJAX error:', err);
                    tbody.style.opacity = '1';
                    tbody.style.pointerEvents = '';
                });
        }

        function bindPaginationLinks() {
            document.querySelectorAll('#pagination-container .pagination-link').forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const page = this.getAttribute('data-page');
                    if (page) fetchRequests(parseInt(page));
                });
            });
        }

        // Bind on initial load
        document.addEventListener('DOMContentLoaded', bindPaginationLinks);
    </script>

    <!-- Approve Modal -->
    <div id="approve-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeApproveModal()"></div>
        <div class="relative w-full max-w-md bg-white rounded-2xl shadow-2xl overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 border-b bg-green-50">
                <div class="flex items-center gap-3 text-green-700">
                    <i class="fas fa-check-circle text-xl"></i>
                    <h3 class="text-lg font-bold">Setujui Request Pinjaman</h3>
                </div>
                <button type="button" class="text-green-400 hover:text-green-700 transition-colors" onclick="closeApproveModal()">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <form method="POST" class="p-6">
                <input type="hidden" name="action" value="approve">
                <input type="hidden" id="approve_request_id" name="request_id" value="">
                <div class="mb-6">
                    <div class="flex items-center gap-3 p-4 bg-green-50 rounded-xl border border-green-200">
                        <i class="fas fa-info-circle text-green-600"></i>
                        <p class="text-sm text-green-800">Status aset akan otomatis berubah menjadi <strong>IN USE</strong> dan data peminjam akan diperbarui.</p>
                    </div>
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" class="px-5 py-2.5 text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-xl font-medium transition-colors" onclick="closeApproveModal()">Batal</button>
                    <button type="submit" class="px-5 py-2.5 bg-green-600 hover:bg-green-700 text-white rounded-xl font-medium transition-colors flex items-center gap-2">
                        <i class="fas fa-check-circle"></i> Konfirmasi Setuju
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Return Modal -->
    <div id="return-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeReturnModal()"></div>
        <div class="relative w-full max-w-md bg-white rounded-2xl shadow-2xl overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 border-b bg-blue-50">
                <div class="flex items-center gap-3 text-blue-700">
                    <i class="fas fa-undo text-xl"></i>
                    <h3 class="text-lg font-bold">Return Aset</h3>
                </div>
                <button type="button" class="text-blue-400 hover:text-blue-700 transition-colors" onclick="closeReturnModal()">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <form method="POST" class="p-6">
                <input type="hidden" name="action" value="return_asset">
                <input type="hidden" id="return_request_id" name="request_id" value="">
                <div class="mb-6">
                    <div class="flex items-center gap-3 p-4 bg-blue-50 rounded-xl border border-blue-200">
                        <i class="fas fa-info-circle text-blue-600"></i>
                        <p class="text-sm text-blue-800">Aset akan dikembalikan ke status <strong>SPARE</strong> dan data peminjam akan dihapus dari aset.</p>
                    </div>
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" class="px-5 py-2.5 text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-xl font-medium transition-colors" onclick="closeReturnModal()">Batal</button>
                    <button type="submit" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-medium transition-colors flex items-center gap-2">
                        <i class="fas fa-undo"></i> Konfirmasi Return
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Transfer Modal -->
    <div id="transfer-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeTransferModal()"></div>
        <div class="relative w-full max-w-md bg-white rounded-2xl shadow-2xl overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 border-b bg-purple-50">
                <div class="flex items-center gap-3 text-purple-700">
                    <i class="fas fa-exchange-alt text-xl"></i>
                    <h3 class="text-lg font-bold">Transfer Aset</h3>
                </div>
                <button type="button" class="text-purple-400 hover:text-purple-700 transition-colors" onclick="closeTransferModal()">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <form method="POST" class="p-6">
                <input type="hidden" name="action" value="transfer_asset">
                <input type="hidden" id="transfer_request_id" name="request_id" value="">
                <div class="mb-4">
                    <div class="flex items-center gap-3 p-4 bg-purple-50 rounded-xl border border-purple-200 mb-4">
                        <i class="fas fa-info-circle text-purple-600"></i>
                        <p class="text-sm text-purple-800">Aset akan dipindahkan ke user baru. Status aset tetap <strong>IN USE</strong> dengan pemegang baru.</p>
                    </div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Transfer ke User <span class="text-red-500">*</span></label>
                    <select name="transfer_to" required class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:ring-2 focus:ring-purple-500 focus:border-purple-500 text-sm">
                        <option value="">-- Pilih User Tujuan --</option>
                        <?php foreach ($userList as $u): ?>
                            <option value="<?= htmlspecialchars($u['username']) ?>"><?= htmlspecialchars($u['Nama_Lengkap']) ?> (<?= htmlspecialchars($u['username']) ?> - <?= htmlspecialchars($u['Jabatan_Level']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" class="px-5 py-2.5 text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-xl font-medium transition-colors" onclick="closeTransferModal()">Batal</button>
                    <button type="submit" class="px-5 py-2.5 bg-purple-600 hover:bg-purple-700 text-white rounded-xl font-medium transition-colors flex items-center gap-2">
                        <i class="fas fa-exchange-alt"></i> Konfirmasi Transfer
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openReturnModal(id) {
            document.getElementById('return_request_id').value = id;
            document.getElementById('return-modal').classList.remove('hidden');
        }
        function closeReturnModal() {
            document.getElementById('return-modal').classList.add('hidden');
        }
        function openTransferModal(id) {
            document.getElementById('transfer_request_id').value = id;
            document.getElementById('transfer-modal').classList.remove('hidden');
        }
        function closeTransferModal() {
            document.getElementById('transfer-modal').classList.add('hidden');
        }
    </script>

</body>
</html>
