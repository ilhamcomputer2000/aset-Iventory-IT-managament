<?php
// Ticket page (user)
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$rawRole = isset($_SESSION['role']) ? (string) $_SESSION['role'] : 'user';
$role = strtolower(trim($rawRole));

// Admin yang nyasar ke halaman user diarahkan ke halaman admin.
if ($role === 'super_admin' || $role === 'admin') {
    header('Location: ../admin/ticket.php');
    exit();
}

// Default: user.
if ($role !== 'user') {
    $role = 'user';
}

$username = isset($_SESSION['username']) ? (string) $_SESSION['username'] : 'User';
$Id_Karyawan = isset($_SESSION['Id_Karyawan']) && (string) $_SESSION['Id_Karyawan'] !== ''
    ? (string) $_SESSION['Id_Karyawan']
    : $username;

$Nama_Lengkap = isset($_SESSION['Nama_Lengkap']) && (string) $_SESSION['Nama_Lengkap'] !== ''
    ? (string) $_SESSION['Nama_Lengkap']
    : $username;

$Jabatan_Level = isset($_SESSION['Jabatan_Level']) ? trim((string) $_SESSION['Jabatan_Level']) : '';

require_once __DIR__ . '/../koneksi.php';

$flashSuccess = null;
$flashError = null;

// Flash message (PRG) untuk mencegah resubmit POST saat refresh
if (isset($_SESSION['flash_success'])) {
    $flashSuccess = (string) $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}
if (isset($_SESSION['flash_error'])) {
    $flashError = (string) $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

function ticket_redirect_self(): void
{
    $self = isset($_SERVER['PHP_SELF']) ? (string) $_SERVER['PHP_SELF'] : 'ticket.php';
    header('Location: ' . $self);
    exit();
}

function ticket_audit_ensure_table(mysqli $kon): void
{
    $sql = "CREATE TABLE IF NOT EXISTS `ticket_status_history` (\n"
        . "  `id` INT NOT NULL AUTO_INCREMENT,\n"
        . "  `Ticket_code` INT NOT NULL,\n"
        . "  `status_from` VARCHAR(50) NULL,\n"
        . "  `status_to` VARCHAR(50) NOT NULL,\n"
        . "  `changed_at` DATETIME NOT NULL,\n"
        . "  `changed_by` VARCHAR(100) NULL,\n"
        . "  `changed_by_role` VARCHAR(50) NULL,\n"
        . "  `note` TEXT NULL,\n"
        . "  PRIMARY KEY (`id`),\n"
        . "  KEY `idx_ticket_code_changed_at` (`Ticket_code`, `changed_at`)\n"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $ok = $kon->query($sql);
    if ($ok === false) {
        error_log('Failed to ensure ticket_status_history table (user/ticket.php): ' . $kon->error);
    }
}

function ticket_audit_insert(
    mysqli $kon,
    int $ticketCode,
    ?string $statusFrom,
    string $statusTo,
    ?string $changedBy,
    ?string $changedByRole,
    ?string $note
): void {
    if ($ticketCode <= 0 || trim($statusTo) === '') {
        return;
    }

    ticket_audit_ensure_table($kon);

    $stmt = $kon->prepare(
        'INSERT INTO `ticket_status_history` (`Ticket_code`, `status_from`, `status_to`, `changed_at`, `changed_by`, `changed_by_role`, `note`) '
        . 'VALUES (?,?,?,NOW(),?,?,?)'
    );
    if (!$stmt) {
        return;
    }
    $stmt->bind_param(
        'isssss',
        $ticketCode,
        $statusFrom,
        $statusTo,
        $changedBy,
        $changedByRole,
        $note
    );
    @$stmt->execute();
    @$stmt->close();
}

// Tarik data user dari tabel users untuk autofill Jabatan & Divisi (sesuai request)
$Jabatan_Auto = '';
$Divisi_Auto = '';
$Region_Auto = '';
try {
    $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
    if ($userId > 0) {
        $stmtU = $kon->prepare('SELECT `Jabatan_Level`, `Divisi`, `Region` FROM `users` WHERE `id` = ? LIMIT 1');
        if ($stmtU) {
            $stmtU->bind_param('i', $userId);
            if ($stmtU->execute()) {
                $resU = $stmtU->get_result();
                if ($resU) {
                    $rowU = $resU->fetch_assoc();
                    if ($rowU) {
                        if (isset($rowU['Jabatan_Level'])) {
                            $Jabatan_Auto = trim((string) $rowU['Jabatan_Level']);
                        }
                        if (isset($rowU['Divisi'])) {
                            $Divisi_Auto = trim((string) $rowU['Divisi']);
                        }
                        if (isset($rowU['Region'])) {
                            $Region_Auto = trim((string) $rowU['Region']);
                        }
                    }
                }
            }
            $stmtU->close();
        }
    }
} catch (Throwable $e) {
    // Jangan gagalkan halaman kalau gagal baca metadata user
    error_log('Fetch user metadata (Jabatan_Level/Divisi) failed (user/ticket.php): ' . $e->getMessage());
}

function ticket_user_format_code(int $ticketCode, ?string $createUser): string
{
    $year = (string) date('Y');
    if ($createUser) {
        $ts = strtotime($createUser);
        if ($ts !== false) {
            $year = date('Y', $ts);
        }
    }
    return 'ITCKT-' . $year . '-' . str_pad((string) $ticketCode, 6, '0', STR_PAD_LEFT);
}

function ticket_user_status_list(): array
{
    return ['Open', 'In Progress', 'Review', 'Done', 'Reject', 'Closed'];
}

function ticket_user_is_allowed_status(string $status): bool
{
    return in_array($status, ticket_user_status_list(), true);
}

function ticket_trim_max(string $value, int $maxLen): string
{
    $value = trim($value);
    if ($maxLen <= 0) {
        return $value;
    }
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $maxLen);
    }
    return substr($value, 0, $maxLen);
}

function ticket_badge_priority_class(string $priority): string
{
    $key = strtolower(trim($priority));
    $key = str_replace(['_', '-'], ' ', $key);
    switch ($key) {
        case 'low':
            return 'bg-gray-100 text-gray-700 border-gray-200';
        case 'medium':
            return 'bg-yellow-50 text-yellow-800 border-yellow-200';
        case 'high':
            return 'bg-orange-50 text-orange-700 border-orange-200';
        case 'urgent':
            return 'bg-red-50 text-red-700 border-red-200';
        default:
            return 'bg-gray-50 text-gray-700 border-gray-200';
    }
}

function ticket_badge_status_class(string $status): string
{
    $key = strtolower(trim($status));
    $key = str_replace(['_', '-'], ' ', $key);
    $key = preg_replace('/\s+/', ' ', $key);

    switch ($key) {
        case 'open':
            return 'bg-blue-50 text-blue-700 border-blue-200';
        case 'in progress':
            return 'bg-orange-50 text-orange-700 border-orange-200';
        case 'review':
            return 'bg-yellow-50 text-yellow-800 border-yellow-200';
        case 'done':
            return 'bg-green-50 text-green-700 border-green-200';
        case 'reject':
        case 'rejected':
            return 'bg-red-50 text-red-700 border-red-200';
        case 'closed':
            return 'bg-gray-100 text-gray-700 border-gray-200';
        default:
            return 'bg-gray-50 text-gray-700 border-gray-200';
    }
}

function ticket_badge_type_pekerjaan_class(string $type): string
{
    $key = strtolower(trim($type));
    $key = str_replace(['_', '-'], ' ', $key);
    $key = preg_replace('/\s+/', ' ', $key);

    switch ($key) {
        case 'remote':
            return 'bg-blue-50 text-blue-700 border-blue-200';
        case 'onsite':
            return 'bg-purple-50 text-purple-700 border-purple-200';
        default:
            return 'bg-slate-50 text-slate-700 border-slate-200';
    }
}

function ticket_save_upload(array $file, string $destDir, string $prefix, array $allowedExts = []): string
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return '';
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Upload file gagal (kode: ' . (int) $file['error'] . ').');
    }

    $name = isset($file['name']) ? (string) $file['name'] : '';
    $tmp = isset($file['tmp_name']) ? (string) $file['tmp_name'] : '';
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new Exception('Upload file tidak valid.');
    }

    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if ($ext === '') {
        $ext = 'bin';
    }
    if (!empty($allowedExts) && !in_array($ext, $allowedExts, true)) {
        throw new Exception('Tipe file tidak diizinkan: .' . $ext);
    }

    if (!is_dir($destDir)) {
        if (!@mkdir($destDir, 0775, true) && !is_dir($destDir)) {
            throw new Exception('Gagal membuat folder upload.');
        }
    }

    $safe = $prefix . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destPath = rtrim($destDir, '/\\') . DIRECTORY_SEPARATOR . $safe;

    // Resize/compress images before saving (best-effort to <=100KB)
    $isImageExt = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
    $targetBytes = 100 * 1024;
    $maxDim = 1600;

    // If it's already small enough, keep as-is to avoid quality loss
    $tmpSize = @filesize($tmp);
    if ($isImageExt && is_int($tmpSize) && $tmpSize > 0 && $tmpSize <= $targetBytes) {
        if (!@move_uploaded_file($tmp, $destPath)) {
            throw new Exception('Gagal menyimpan file upload.');
        }
        return $safe;
    }

    $saved = false;
    if ($isImageExt && function_exists('getimagesize')) {
        $imgInfo = @getimagesize($tmp);
        if (is_array($imgInfo) && isset($imgInfo[0], $imgInfo[1], $imgInfo[2])) {
            $srcW = (int) $imgInfo[0];
            $srcH = (int) $imgInfo[1];
            $imgType = (int) $imgInfo[2];

            if ($srcW > 0 && $srcH > 0) {
                $scale = min(1, $maxDim / max($srcW, $srcH));
                $dstW = (int) max(1, floor($srcW * $scale));
                $dstH = (int) max(1, floor($srcH * $scale));

                $srcImg = null;
                switch ($imgType) {
                    case IMAGETYPE_JPEG:
                        if (function_exists('imagecreatefromjpeg'))
                            $srcImg = @imagecreatefromjpeg($tmp);
                        break;
                    case IMAGETYPE_PNG:
                        if (function_exists('imagecreatefrompng'))
                            $srcImg = @imagecreatefrompng($tmp);
                        break;
                    case IMAGETYPE_GIF:
                        if (function_exists('imagecreatefromgif'))
                            $srcImg = @imagecreatefromgif($tmp);
                        break;
                    case IMAGETYPE_WEBP:
                        if (function_exists('imagecreatefromwebp'))
                            $srcImg = @imagecreatefromwebp($tmp);
                        break;
                    default:
                        $srcImg = null;
                }

                if ($srcImg) {
                    $dstImg = ($dstW === $srcW && $dstH === $srcH) ? $srcImg : imagecreatetruecolor($dstW, $dstH);
                    if ($dstImg !== $srcImg) {
                        if ($imgType === IMAGETYPE_PNG || $imgType === IMAGETYPE_WEBP) {
                            imagealphablending($dstImg, false);
                            imagesavealpha($dstImg, true);
                            $transparent = imagecolorallocatealpha($dstImg, 0, 0, 0, 127);
                            imagefilledrectangle($dstImg, 0, 0, $dstW, $dstH, $transparent);
                        }
                        @imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
                    }

                    // Try to hit targetBytes for JPEG/WebP by iterating quality
                    if ($imgType === IMAGETYPE_JPEG && function_exists('imagejpeg')) {
                        for ($q = 85; $q >= 50; $q -= 5) {
                            if (@imagejpeg($dstImg, $destPath, $q)) {
                                $sz = @filesize($destPath);
                                if (is_int($sz) && $sz > 0 && $sz <= $targetBytes) {
                                    $saved = true;
                                    break;
                                }
                                $saved = true; // at least wrote something
                            }
                        }
                    } elseif ($imgType === IMAGETYPE_WEBP && function_exists('imagewebp')) {
                        for ($q = 85; $q >= 50; $q -= 5) {
                            if (@imagewebp($dstImg, $destPath, $q)) {
                                $sz = @filesize($destPath);
                                if (is_int($sz) && $sz > 0 && $sz <= $targetBytes) {
                                    $saved = true;
                                    break;
                                }
                                $saved = true;
                            }
                        }
                    } elseif ($imgType === IMAGETYPE_PNG && function_exists('imagepng')) {
                        // PNG: increase compression level (0-9). Not guaranteed to reach 100KB.
                        for ($lvl = 9; $lvl >= 6; $lvl--) {
                            if (@imagepng($dstImg, $destPath, $lvl)) {
                                $sz = @filesize($destPath);
                                if (is_int($sz) && $sz > 0 && $sz <= $targetBytes) {
                                    $saved = true;
                                    break;
                                }
                                $saved = true;
                            }
                        }
                    } elseif ($imgType === IMAGETYPE_GIF && function_exists('imagegif')) {
                        $saved = @imagegif($dstImg, $destPath);
                    }

                    if ($dstImg !== $srcImg) {
                        imagedestroy($dstImg);
                        imagedestroy($srcImg);
                    } else {
                        imagedestroy($srcImg);
                    }
                }
            }
        }
    }

    // Fallback: save original file
    if (!$saved) {
        if (!@move_uploaded_file($tmp, $destPath)) {
            throw new Exception('Gagal menyimpan file upload.');
        }
    }

    return $safe;
}

function ticket_safe_unlink(string $baseDir, string $filename): void
{
    $filename = trim($filename);
    if ($filename === '') {
        return;
    }
    $safe = basename($filename);
    $path = rtrim($baseDir, '/\\') . DIRECTORY_SEPARATOR . $safe;
    if (is_file($path)) {
        @unlink($path);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_ticket') {
    try {
        if ($Id_Karyawan === '' || $Nama_Lengkap === '') {
            throw new Exception('Data user belum lengkap (Id Karyawan / Nama).');
        }

        // Divisi_User wajib: diambil dari tabel users kolom Divisi
        $divisi = ticket_trim_max($Divisi_Auto, 200);
        // Region wajib: diambil dari tabel users kolom Region
        $region = ticket_trim_max($Region_Auto, 250);
        $subject = ticket_trim_max((string) ($_POST['Subject'] ?? ''), 250);
        $kategori = ticket_trim_max((string) ($_POST['Kategori_Masalah'] ?? ''), 2050);
        $priority = ticket_trim_max((string) ($_POST['Priority'] ?? ''), 50);
        $deskripsi = ticket_trim_max((string) ($_POST['Deskripsi_Masalah'] ?? ''), 255);

        if ($divisi === '' || $region === '' || $subject === '' || $kategori === '' || $priority === '' || $deskripsi === '') {
            throw new Exception('Mohon lengkapi semua field yang wajib.');
        }

        $status = 'Open';
        $createBy = ticket_trim_max($username, 100);
        // Jabatan_User wajib: diambil dari tabel users kolom Jabatan_Level
        $jabatanSource = $Jabatan_Auto !== '' ? $Jabatan_Auto : ($Jabatan_Level !== '' ? $Jabatan_Level : 'User');
        $jabatanUser = ticket_trim_max($jabatanSource, 100);

        // Generate nomor urut (karena kolom tidak AUTO_INCREMENT).
        $nextNo = 1;
        $nextId = 1;
        $nextTicketCode = 1;

        @$kon->begin_transaction();

        $maxRes = $kon->query('SELECT COALESCE(MAX(`No`),0)+1 AS nextNo, COALESCE(MAX(`ID`),0)+1 AS nextId, COALESCE(MAX(`Ticket_code`),0)+1 AS nextTicketCode FROM `ticket`');
        if ($maxRes) {
            $m = $maxRes->fetch_assoc();
            if ($m) {
                $nextNo = (int) $m['nextNo'];
                $nextId = (int) $m['nextId'];
                $nextTicketCode = (int) $m['nextTicketCode'];
            }
            $maxRes->free();
        }

        // Upload (opsional). Simpan filename saja (kolom max 100).
        $uploadDir = __DIR__ . '/../uploads/ticket';
        $fotoName = '';
        $docName = '';
        if (isset($_FILES['Foto_Ticket'])) {
            $fotoName = ticket_save_upload($_FILES['Foto_Ticket'], $uploadDir, 'foto_' . $nextTicketCode, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
        }
        if (isset($_FILES['Document'])) {
            $docName = ticket_save_upload($_FILES['Document'], $uploadDir, 'doc_' . $nextTicketCode, ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'png', 'jpg', 'jpeg']);
        }

        $stmt = $kon->prepare(
            'INSERT INTO `ticket` (`No`, `ID`, `Ticket_code`, `Id_Karyawan`, `Nama_User`, `Divisi_User`, `Jabatan_User`, `Region`, `Subject`, `Kategori_Masalah`, `Priority`, `Status_Request`, `Type_Pekerjaan`, `Create_By_User`, `Deskripsi_Masalah`, `Foto_Ticket`, `Document`, `Jawaban_IT`, `Photo_IT`) '
            . 'VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        if (!$stmt) {
            throw new Exception('Prepare insert gagal: ' . $kon->error);
        }

        $jawabanItDefault = '';
        $photoItDefault = '';
        // Type_Pekerjaan harus diisi oleh IT/admin. Untuk user, simpan kosong agar tampil '-' sampai IT mengupdate.
        // Tetap pakai empty string (bukan NULL) untuk aman jika kolom DB NOT NULL.
        $typePekerjaanDefault = '';

        // 19 params total: 3 integers + 16 strings
        $insertTypes = 'iii' . str_repeat('s', 16);

        $stmt->bind_param(
            $insertTypes,
            $nextNo,
            $nextId,
            $nextTicketCode,
            $Id_Karyawan,
            $Nama_Lengkap,
            $divisi,
            $jabatanUser,
            $region,
            $subject,
            $kategori,
            $priority,
            $status,
            $typePekerjaanDefault,
            $createBy,
            $deskripsi,
            $fotoName,
            $docName,
            $jawabanItDefault,
            $photoItDefault
        );

        if (!$stmt->execute()) {
            throw new Exception('Insert ticket gagal: ' . $stmt->error);
        }
        $stmt->close();

        // Audit: initial status
        ticket_audit_insert($kon, $nextTicketCode, null, $status, $Nama_Lengkap, 'user', 'Ticket dibuat');

        @$kon->commit();

        // Notification: notify admin about new ticket
        try {
            $notifTitle = 'Ticket Baru Dibuat';
            $notifMsg = $Nama_Lengkap . ' membuat ticket baru: "' . $subject . '" (Priority: ' . $priority . ')';
            $notifType = 'ticket_created';
            $stmtNotif = $kon->prepare("INSERT INTO `notifications` (`target_role`, `target_user_id`, `type`, `title`, `message`, `reference_id`) VALUES ('admin', NULL, ?, ?, ?, ?)");
            if ($stmtNotif) {
                $stmtNotif->bind_param('sssi', $notifType, $notifTitle, $notifMsg, $nextTicketCode);
                @$stmtNotif->execute();
                @$stmtNotif->close();
            }
        } catch (Throwable $ne) {
            error_log('Notification insert error (user/ticket.php create): ' . $ne->getMessage());
        }

        $_SESSION['flash_success'] = 'Ticket berhasil dibuat. Ticket Code: ' . $nextTicketCode;
        ticket_redirect_self();
    } catch (Throwable $e) {
        @$kon->rollback();
        $_SESSION['flash_error'] = 'Gagal membuat ticket. ' . $e->getMessage();
        error_log('Create ticket error (user/ticket.php): ' . $e->getMessage());
        ticket_redirect_self();
    }
}

// Update ticket milik user (inline edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_ticket') {
    try {
        if ($Id_Karyawan === '' || $username === '') {
            throw new Exception('Data user belum lengkap.');
        }

        $ticketCode = isset($_POST['Ticket_code']) ? (int) $_POST['Ticket_code'] : 0;
        if ($ticketCode <= 0) {
            throw new Exception('Ticket code tidak valid.');
        }

        $subject = ticket_trim_max((string) ($_POST['Subject'] ?? ''), 250);
        $kategori = ticket_trim_max((string) ($_POST['Kategori_Masalah'] ?? ''), 2050);
        $priority = ticket_trim_max((string) ($_POST['Priority'] ?? ''), 50);
        $deskripsi = ticket_trim_max((string) ($_POST['Deskripsi_Masalah'] ?? ''), 255);

        if ($subject === '' || $kategori === '' || $priority === '' || $deskripsi === '') {
            throw new Exception('Mohon lengkapi field yang wajib.');
        }

        // Ambil data ticket existing (ownership check + status)
        $stmtGet = $kon->prepare('SELECT `Status_Request`, `Foto_Ticket`, `Document` FROM `ticket` WHERE `Ticket_code` = ? AND `Id_Karyawan` = ? AND `Create_By_User` = ? LIMIT 1');
        if (!$stmtGet) {
            throw new Exception('Prepare gagal: ' . $kon->error);
        }
        $stmtGet->bind_param('iss', $ticketCode, $Id_Karyawan, $username);
        if (!$stmtGet->execute()) {
            throw new Exception('Gagal mengambil ticket: ' . $stmtGet->error);
        }
        $resGet = $stmtGet->get_result();
        $existing = $resGet ? $resGet->fetch_assoc() : null;
        $stmtGet->close();

        if (!$existing) {
            throw new Exception('Ticket tidak ditemukan atau bukan milik akun ini.');
        }

        $statusNow = isset($existing['Status_Request']) ? (string) $existing['Status_Request'] : '';
        if ($statusNow !== 'Open') {
            throw new Exception('Ticket hanya bisa diedit saat status Open.');
        }

        $fotoName = isset($existing['Foto_Ticket']) ? (string) $existing['Foto_Ticket'] : '';
        $docName = isset($existing['Document']) ? (string) $existing['Document'] : '';

        $uploadDir = __DIR__ . '/../uploads/ticket';
        if (isset($_FILES['Foto_Ticket']) && isset($_FILES['Foto_Ticket']['error']) && $_FILES['Foto_Ticket']['error'] !== UPLOAD_ERR_NO_FILE) {
            $fotoName = ticket_save_upload($_FILES['Foto_Ticket'], $uploadDir, 'foto_' . $ticketCode, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
        }
        if (isset($_FILES['Document']) && isset($_FILES['Document']['error']) && $_FILES['Document']['error'] !== UPLOAD_ERR_NO_FILE) {
            $docName = ticket_save_upload($_FILES['Document'], $uploadDir, 'doc_' . $ticketCode, ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'png', 'jpg', 'jpeg']);
        }

        $stmtUp = $kon->prepare('UPDATE `ticket` SET `Subject` = ?, `Kategori_Masalah` = ?, `Priority` = ?, `Deskripsi_Masalah` = ?, `Foto_Ticket` = ?, `Document` = ? WHERE `Ticket_code` = ? AND `Id_Karyawan` = ? AND `Create_By_User` = ?');
        if (!$stmtUp) {
            throw new Exception('Prepare update gagal: ' . $kon->error);
        }
        $stmtUp->bind_param('ssssssiss', $subject, $kategori, $priority, $deskripsi, $fotoName, $docName, $ticketCode, $Id_Karyawan, $username);
        if (!$stmtUp->execute()) {
            throw new Exception('Update ticket gagal: ' . $stmtUp->error);
        }
        $stmtUp->close();

        $_SESSION['flash_success'] = 'Ticket berhasil diupdate.';
        ticket_redirect_self();
    } catch (Throwable $e) {
        $_SESSION['flash_error'] = 'Gagal update ticket. ' . $e->getMessage();
        error_log('Update ticket error (user/ticket.php): ' . $e->getMessage());
        ticket_redirect_self();
    }
}

// Delete ticket milik user (hanya saat status Open)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_ticket') {
    try {
        if ($Id_Karyawan === '' || $username === '') {
            throw new Exception('Data user belum lengkap.');
        }

        $ticketCode = isset($_POST['Ticket_code']) ? (int) $_POST['Ticket_code'] : 0;
        if ($ticketCode <= 0) {
            throw new Exception('Ticket code tidak valid.');
        }

        // Ambil ticket untuk validasi kepemilikan + status + file terkait
        $stmtGet = $kon->prepare('SELECT `Status_Request`, `Foto_Ticket`, `Document` FROM `ticket` WHERE `Ticket_code` = ? AND `Id_Karyawan` = ? AND `Create_By_User` = ? LIMIT 1');
        if (!$stmtGet) {
            throw new Exception('Prepare gagal: ' . $kon->error);
        }
        $stmtGet->bind_param('iss', $ticketCode, $Id_Karyawan, $username);
        if (!$stmtGet->execute()) {
            throw new Exception('Gagal mengambil ticket: ' . $stmtGet->error);
        }
        $resGet = $stmtGet->get_result();
        $existing = $resGet ? $resGet->fetch_assoc() : null;
        $stmtGet->close();

        if (!$existing) {
            throw new Exception('Ticket tidak ditemukan atau bukan milik akun ini.');
        }

        $statusNow = isset($existing['Status_Request']) ? (string) $existing['Status_Request'] : '';
        if ($statusNow !== 'Open') {
            throw new Exception('Ticket hanya bisa dihapus saat status Open.');
        }

        $stmtDel = $kon->prepare('DELETE FROM `ticket` WHERE `Ticket_code` = ? AND `Id_Karyawan` = ? AND `Create_By_User` = ?');
        if (!$stmtDel) {
            throw new Exception('Prepare delete gagal: ' . $kon->error);
        }
        $stmtDel->bind_param('iss', $ticketCode, $Id_Karyawan, $username);
        if (!$stmtDel->execute()) {
            throw new Exception('Hapus ticket gagal: ' . $stmtDel->error);
        }
        $stmtDel->close();

        // Best-effort hapus file yang terkait (kalau ada)
        $uploadDir = __DIR__ . '/../uploads/ticket';
        ticket_safe_unlink($uploadDir, (string) ($existing['Foto_Ticket'] ?? ''));
        ticket_safe_unlink($uploadDir, (string) ($existing['Document'] ?? ''));

        $_SESSION['flash_success'] = 'Ticket berhasil dihapus.';
        ticket_redirect_self();
    } catch (Throwable $e) {
        $_SESSION['flash_error'] = 'Gagal hapus ticket. ' . $e->getMessage();
        error_log('Delete ticket error (user/ticket.php): ' . $e->getMessage());
        ticket_redirect_self();
    }
}

// User approval: Done -> Closed
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'approve_close') {
    try {
        if ($Id_Karyawan === '' || $username === '' || $Nama_Lengkap === '') {
            throw new Exception('Data user belum lengkap.');
        }

        $ticketCode = isset($_POST['Ticket_code']) ? (int) $_POST['Ticket_code'] : 0;
        if ($ticketCode <= 0) {
            throw new Exception('Ticket code tidak valid.');
        }

        // Ownership check + current status
        $stmtGet = $kon->prepare('SELECT `Status_Request` FROM `ticket` WHERE `Ticket_code` = ? AND `Id_Karyawan` = ? AND `Create_By_User` = ? LIMIT 1');
        if (!$stmtGet) {
            throw new Exception('Prepare gagal: ' . $kon->error);
        }
        $stmtGet->bind_param('iss', $ticketCode, $Id_Karyawan, $username);
        if (!$stmtGet->execute()) {
            throw new Exception('Gagal mengambil ticket: ' . $stmtGet->error);
        }
        $resGet = $stmtGet->get_result();
        $existing = $resGet ? $resGet->fetch_assoc() : null;
        $stmtGet->close();

        if (!$existing) {
            throw new Exception('Ticket tidak ditemukan atau bukan milik akun ini.');
        }

        $statusNow = isset($existing['Status_Request']) ? (string) $existing['Status_Request'] : '';
        if ($statusNow !== 'Done') {
            throw new Exception('Approval Close hanya bisa dilakukan saat status Done.');
        }

        @$kon->begin_transaction();

        $newStatus = 'Closed';
        $stmtUp = $kon->prepare('UPDATE `ticket` SET `Status_Request` = ? WHERE `Ticket_code` = ? AND `Id_Karyawan` = ? AND `Create_By_User` = ? AND `Status_Request` = ?');
        if (!$stmtUp) {
            throw new Exception('Prepare update gagal: ' . $kon->error);
        }
        $stmtUp->bind_param('sisss', $newStatus, $ticketCode, $Id_Karyawan, $username, $statusNow);
        if (!$stmtUp->execute()) {
            throw new Exception('Update status gagal: ' . $stmtUp->error);
        }
        $affected = $stmtUp->affected_rows;
        $stmtUp->close();

        if ($affected <= 0) {
            throw new Exception('Gagal approval close: status ticket sudah berubah atau tidak valid.');
        }

        ticket_audit_insert($kon, $ticketCode, $statusNow, $newStatus, $Nama_Lengkap, 'user', 'Kamu menyetujui penutupan ticket');

        @$kon->commit();

        // Notification: notify admin about ticket closed by user
        try {
            $notifTitle = 'Ticket Di-Closed';
            $notifMsg = $Nama_Lengkap . ' menyetujui penutupan ticket #' . $ticketCode;
            $notifType = 'ticket_closed';
            $stmtNotif = $kon->prepare("INSERT INTO `notifications` (`target_role`, `target_user_id`, `type`, `title`, `message`, `reference_id`) VALUES ('admin', NULL, ?, ?, ?, ?)");
            if ($stmtNotif) {
                $stmtNotif->bind_param('sssi', $notifType, $notifTitle, $notifMsg, $ticketCode);
                @$stmtNotif->execute();
                @$stmtNotif->close();
            }
        } catch (Throwable $ne) {
            error_log('Notification insert error (user/ticket.php close): ' . $ne->getMessage());
        }

        $_SESSION['flash_success'] = 'Ticket berhasil di-Closed (approval user).';
        ticket_redirect_self();
    } catch (Throwable $e) {
        @$kon->rollback();
        $_SESSION['flash_error'] = 'Gagal approval close. ' . $e->getMessage();
        error_log('Approve close error (user/ticket.php): ' . $e->getMessage());
        ticket_redirect_self();
    }
}

// Pagination setup (template: user/dashboard_user.php)
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$page = max(1, $page);
$limit = 10;
$offset = ($page - 1) * $limit;

// Status filter (tabs)
$statusFilter = '';
if (isset($_GET['status'])) {
    $requestedStatus = trim((string) $_GET['status']);
    if ($requestedStatus !== '' && strcasecmp($requestedStatus, 'all') !== 0 && ticket_user_is_allowed_status($requestedStatus)) {
        $statusFilter = $requestedStatus;
    }
}

// Search query (progressive enhancement: works with non-AJAX submit, realtime with AJAX)
$searchQuery = '';
if (isset($_GET['q'])) {
    $searchQuery = ticket_trim_max((string) $_GET['q'], 120);
}
$searchQuery = trim($searchQuery);
$hasSearch = ($searchQuery !== '');

// Support searching by displayed code: ITCKT-YYYY-000123 (or legacy TCK-YYYY-000123)
$searchTicketCodeTerm = $searchQuery;
if ($searchQuery !== '') {
    if (preg_match('/^(?:ITCKT|TCK)-\\d{4}-0*(\\d{1,10})$/i', $searchQuery, $m)) {
        $searchTicketCodeTerm = $m[1];
    } elseif (preg_match('/^0*(\\d{1,10})$/', $searchQuery, $m)) {
        // Pure numeric search should match Ticket_code too
        $searchTicketCodeTerm = $m[1];
    }
}

$searchLikeText = '%' . $searchQuery . '%';
$searchLikeTicketCode = '%' . $searchTicketCodeTerm . '%';

// 8 placeholders (keep in sync with bind_param below)
$ticketUserSearchWhereSql = '('
    . 'CAST(`Ticket_code` AS CHAR) LIKE ? '
    . 'OR `Subject` LIKE ? '
    . 'OR `Kategori_Masalah` LIKE ? '
    . 'OR `Priority` LIKE ? '
    . 'OR `Status_Request` LIKE ? '
    . 'OR `Type_Pekerjaan` LIKE ? '
    . 'OR `Deskripsi_Masalah` LIKE ? '
    . 'OR `Create_User` LIKE ?'
    . ')';

function ticket_build_pagination_html(int $page, int $totalPages, int $totalRecords, int $offset, int $limit, array $baseParams = []): string
{
    $paginationHtml = '';
    if ($totalPages <= 1) {
        return $paginationHtml;
    }

    $paginationHtml .= '<div class="mt-6 flex items-center justify-between">'
        . '<div class="text-sm text-gray-600">'
        . 'Showing ' . min($offset + 1, $totalRecords)
        . ' to ' . min($offset + $limit, $totalRecords)
        . ' of ' . $totalRecords . ' results'
        . '</div>'
        . '<nav class="inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">';

    // Previous
    if ($page > 1) {
        $prevParams = array_merge($baseParams, ['page' => $page - 1]);
        $prevUrl = '?' . http_build_query($prevParams);
        $paginationHtml .= '<a href="' . htmlspecialchars($prevUrl) . '" class="pagination-link relative inline-flex items-center px-3 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">'
            . '<i class="fas fa-chevron-left"></i><span class="ml-1">Prev</span></a>';
    }

    $start = max(1, $page - 2);
    $end = min($totalPages, $page + 2);

    if ($start > 1) {
        $firstParams = array_merge($baseParams, ['page' => 1]);
        $paginationHtml .= '<a href="?' . htmlspecialchars(http_build_query($firstParams)) . '" class="pagination-link relative inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">1</a>';
        if ($start > 2) {
            $paginationHtml .= '<span class="relative inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-500">...</span>';
        }
    }

    for ($i = $start; $i <= $end; $i++) {
        if ($i === $page) {
            $paginationHtml .= '<span class="relative z-10 inline-flex items-center px-3 py-2 border border-orange-500 bg-orange-50 text-sm font-medium text-orange-600">' . $i . '</span>';
        } else {
            $pageParams = array_merge($baseParams, ['page' => $i]);
            $paginationHtml .= '<a href="?' . htmlspecialchars(http_build_query($pageParams)) . '" class="pagination-link relative inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">' . $i . '</a>';
        }
    }

    if ($end < $totalPages) {
        if ($end < $totalPages - 1) {
            $paginationHtml .= '<span class="relative inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-500">...</span>';
        }
        $lastParams = array_merge($baseParams, ['page' => $totalPages]);
        $paginationHtml .= '<a href="?' . htmlspecialchars(http_build_query($lastParams)) . '" class="pagination-link relative inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">' . $totalPages . '</a>';
    }

    // Next
    if ($page < $totalPages) {
        $nextParams = array_merge($baseParams, ['page' => $page + 1]);
        $nextUrl = '?' . http_build_query($nextParams);
        $paginationHtml .= '<a href="' . htmlspecialchars($nextUrl) . '" class="pagination-link relative inline-flex items-center px-3 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">'
            . '<span class="mr-1">Next</span><i class="fas fa-chevron-right"></i></a>';
    }

    $paginationHtml .= '</nav></div>';
    return $paginationHtml;
}

// Handle AJAX request untuk pagination (template: user/dashboard_user.php)
if (isset($_GET['action']) && $_GET['action'] === 'ajax_get_tickets') {
    header('Content-Type: application/json; charset=utf-8');
    // Release session lock so concurrent requests (SSE/polling) don't block us
    session_write_close();

    try {
        // Build base params (preserve other query params except action)
        $baseParams = $_GET;
        unset($baseParams['action']);
        unset($baseParams['page']);

        // Counts per status for tabs (user scope; include search if any)
        $ajaxStatusCounts = array_fill_keys(ticket_user_status_list(), 0);
        $ajaxTotalAllRecords = 0;
        if ($hasSearch) {
            $stmtStatusCounts = $kon->prepare('SELECT `Status_Request`, COUNT(*) AS total FROM `ticket` WHERE `Id_Karyawan` = ? AND `Create_By_User` = ? AND ' . $ticketUserSearchWhereSql . ' GROUP BY `Status_Request`');
        } else {
            $stmtStatusCounts = $kon->prepare('SELECT `Status_Request`, COUNT(*) AS total FROM `ticket` WHERE `Id_Karyawan` = ? AND `Create_By_User` = ? GROUP BY `Status_Request`');
        }
        if ($stmtStatusCounts) {
            if ($hasSearch) {
                $stmtStatusCounts->bind_param(
                    'ssssssssss',
                    $Id_Karyawan,
                    $username,
                    $searchLikeTicketCode,
                    $searchLikeText,
                    $searchLikeText,
                    $searchLikeText,
                    $searchLikeText,
                    $searchLikeText,
                    $searchLikeText,
                    $searchLikeText
                );
            } else {
                $stmtStatusCounts->bind_param('ss', $Id_Karyawan, $username);
            }
            if ($stmtStatusCounts->execute()) {
                $resStatus = $stmtStatusCounts->get_result();
                if ($resStatus) {
                    while ($r = $resStatus->fetch_assoc()) {
                        $st = isset($r['Status_Request']) ? (string) $r['Status_Request'] : '';
                        $cnt = (int) ($r['total'] ?? 0);
                        $ajaxTotalAllRecords += $cnt;
                        if ($st !== '' && ticket_user_is_allowed_status($st)) {
                            $ajaxStatusCounts[$st] = $cnt;
                        }
                    }
                }
            }
            $stmtStatusCounts->close();
        }

        // Count
        $totalRecords = 0;
        if ($statusFilter !== '' && $hasSearch) {
            $stmtCount = $kon->prepare('SELECT COUNT(*) AS total FROM `ticket` WHERE `Id_Karyawan` = ? AND `Create_By_User` = ? AND `Status_Request` = ? AND ' . $ticketUserSearchWhereSql);
        } elseif ($statusFilter !== '') {
            $stmtCount = $kon->prepare('SELECT COUNT(*) AS total FROM `ticket` WHERE `Id_Karyawan` = ? AND `Create_By_User` = ? AND `Status_Request` = ?');
        } elseif ($hasSearch) {
            $stmtCount = $kon->prepare('SELECT COUNT(*) AS total FROM `ticket` WHERE `Id_Karyawan` = ? AND `Create_By_User` = ? AND ' . $ticketUserSearchWhereSql);
        } else {
            $stmtCount = $kon->prepare('SELECT COUNT(*) AS total FROM `ticket` WHERE `Id_Karyawan` = ? AND `Create_By_User` = ?');
        }
        if (!$stmtCount) {
            throw new Exception('Prepare count gagal: ' . $kon->error);
        }

        if ($statusFilter !== '' && $hasSearch) {
            $stmtCount->bind_param(
                'sssssssssss',
                $Id_Karyawan,
                $username,
                $statusFilter,
                $searchLikeTicketCode,
                $searchLikeText,
                $searchLikeText,
                $searchLikeText,
                $searchLikeText,
                $searchLikeText,
                $searchLikeText,
                $searchLikeText
            );
        } elseif ($statusFilter !== '') {
            $stmtCount->bind_param('sss', $Id_Karyawan, $username, $statusFilter);
        } elseif ($hasSearch) {
            $stmtCount->bind_param(
                'ssssssssss',
                $Id_Karyawan,
                $username,
                $searchLikeTicketCode,
                $searchLikeText,
                $searchLikeText,
                $searchLikeText,
                $searchLikeText,
                $searchLikeText,
                $searchLikeText,
                $searchLikeText
            );
        } else {
            $stmtCount->bind_param('ss', $Id_Karyawan, $username);
        }
        if (!$stmtCount->execute()) {
            throw new Exception('Count gagal: ' . $stmtCount->error);
        }
        $resCount = $stmtCount->get_result();
        $rowCount = $resCount ? $resCount->fetch_assoc() : null;
        $totalRecords = $rowCount ? (int) ($rowCount['total'] ?? 0) : 0;
        $stmtCount->close();

        $totalPages = $totalRecords > 0 ? (int) ceil($totalRecords / $limit) : 1;
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $limit;

        // Data
        $tickets = [];
        if ($statusFilter !== '' && $hasSearch) {
            $stmtList = $kon->prepare('SELECT `Ticket_code`, `Subject`, `Kategori_Masalah`, `Priority`, `Status_Request`, `Type_Pekerjaan`, `Create_User`, `Divisi_User`, `Jabatan_User`, `Region`, `Deskripsi_Masalah`, `Foto_Ticket`, `Document`, `Jawaban_IT`, `Photo_IT`, `assigned_to` FROM `ticket` WHERE `Id_Karyawan` = ? AND `Create_By_User` = ? AND `Status_Request` = ? AND ' . $ticketUserSearchWhereSql . ' ORDER BY `Ticket_code` DESC LIMIT ? OFFSET ?');
        } elseif ($statusFilter !== '') {
            $stmtList = $kon->prepare('SELECT `Ticket_code`, `Subject`, `Kategori_Masalah`, `Priority`, `Status_Request`, `Type_Pekerjaan`, `Create_User`, `Divisi_User`, `Jabatan_User`, `Region`, `Deskripsi_Masalah`, `Foto_Ticket`, `Document`, `Jawaban_IT`, `Photo_IT`, `assigned_to` FROM `ticket` WHERE `Id_Karyawan` = ? AND `Create_By_User` = ? AND `Status_Request` = ? ORDER BY `Ticket_code` DESC LIMIT ? OFFSET ?');
        } elseif ($hasSearch) {
            $stmtList = $kon->prepare('SELECT `Ticket_code`, `Subject`, `Kategori_Masalah`, `Priority`, `Status_Request`, `Type_Pekerjaan`, `Create_User`, `Divisi_User`, `Jabatan_User`, `Region`, `Deskripsi_Masalah`, `Foto_Ticket`, `Document`, `Jawaban_IT`, `Photo_IT`, `assigned_to` FROM `ticket` WHERE `Id_Karyawan` = ? AND `Create_By_User` = ? AND ' . $ticketUserSearchWhereSql . ' ORDER BY `Ticket_code` DESC LIMIT ? OFFSET ?');
        } else {
            $stmtList = $kon->prepare('SELECT `Ticket_code`, `Subject`, `Kategori_Masalah`, `Priority`, `Status_Request`, `Type_Pekerjaan`, `Create_User`, `Divisi_User`, `Jabatan_User`, `Region`, `Deskripsi_Masalah`, `Foto_Ticket`, `Document`, `Jawaban_IT`, `Photo_IT`, `assigned_to` FROM `ticket` WHERE `Id_Karyawan` = ? AND `Create_By_User` = ? ORDER BY `Ticket_code` DESC LIMIT ? OFFSET ?');
        }
        if (!$stmtList) {
            throw new Exception('Prepare list gagal: ' . $kon->error);
        }

        if ($statusFilter !== '' && $hasSearch) {
            $stmtList->bind_param(
                'sssssssssssii',
                $Id_Karyawan,
                $username,
                $statusFilter,
                $searchLikeTicketCode,
                $searchLikeText,
                $searchLikeText,
                $searchLikeText,
                $searchLikeText,
                $searchLikeText,
                $searchLikeText,
                $searchLikeText,
                $limit,
                $offset
            );
        } elseif ($statusFilter !== '') {
            $stmtList->bind_param('sssii', $Id_Karyawan, $username, $statusFilter, $limit, $offset);
        } elseif ($hasSearch) {
            $stmtList->bind_param(
                'ssssssssssii',
                $Id_Karyawan,
                $username,
                $searchLikeTicketCode,
                $searchLikeText,
                $searchLikeText,
                $searchLikeText,
                $searchLikeText,
                $searchLikeText,
                $searchLikeText,
                $searchLikeText,
                $limit,
                $offset
            );
        } else {
            $stmtList->bind_param('ssii', $Id_Karyawan, $username, $limit, $offset);
        }
        if (!$stmtList->execute()) {
            throw new Exception('Query list gagal: ' . $stmtList->error);
        }
        $resList = $stmtList->get_result();
        if ($resList) {
            while ($row = $resList->fetch_assoc()) {
                $tickets[] = $row;
            }
        }
        $stmtList->close();

        // Build table HTML (match current table structure)
        $tableHtml = '<thead class="bg-gray-50">'
            . '<tr>'
            . '<th class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider px-4 py-3 border-b">No</th>'
            . '<th class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider px-4 py-3 border-b">Ticket Code</th>'
            . '<th class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider px-4 py-3 border-b">Subject</th>'
            . '<th class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider px-4 py-3 border-b">Kategori</th>'
            . '<th class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider px-4 py-3 border-b">Priority</th>'
            . '<th class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider px-4 py-3 border-b">Status</th>'
            . '<th class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider px-4 py-3 border-b">Type Pekerjaan</th>'
            . '<th class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider px-4 py-3 border-b">Created</th>'
            . '<th class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider px-4 py-3 border-b">File User</th>'
            . '<th class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider px-4 py-3 border-b">Respon IT</th>'
            . '<th class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider px-4 py-3 border-b">File IT</th>'
            . '<th class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider px-4 py-3 border-b">Aksi</th>'
            . '</tr>'
            . '</thead>'
            . '<tbody class="bg-white divide-y divide-gray-100">';

        $rowNo = $offset;
        if (count($tickets) === 0) {
            $msg = ($statusFilter !== '')
                ? ('Tidak ada ticket untuk status: ' . $statusFilter . '.')
                : 'Belum ada ticket untuk user ini.';
            $tableHtml .= '<tr>'
                . '<td colspan="12" class="px-4 py-6 text-center text-sm text-gray-500">' . htmlspecialchars($msg) . '</td>'
                . '</tr>';
        } else {
            foreach ($tickets as $t) {
                $rowNo++;
                $codeInt = (int) ($t['Ticket_code'] ?? 0);
                $codeDisplay = ticket_user_format_code($codeInt, isset($t['Create_User']) ? (string) $t['Create_User'] : null);
                $hasFoto = isset($t['Foto_Ticket']) && (string) $t['Foto_Ticket'] !== '';
                $hasDoc = isset($t['Document']) && (string) $t['Document'] !== '';
                $canEdit = isset($t['Status_Request']) && (string) $t['Status_Request'] === 'Open';
                $canApproveClose = isset($t['Status_Request']) && (string) $t['Status_Request'] === 'Done';

                $jawabanItText = isset($t['Jawaban_IT']) ? trim((string) $t['Jawaban_IT']) : '';
                $jawabanItDisplay = ($jawabanItText !== '') ? $jawabanItText : 'Menunggu respon IT';

                $itFileName = isset($t['Photo_IT']) ? trim((string) $t['Photo_IT']) : '';
                $itFileDisplay = ($itFileName !== '') ? $itFileName : 'Menunggu update dari IT';

                $priorityText = (string) ($t['Priority'] ?? '');
                $statusText = (string) ($t['Status_Request'] ?? '');
                $typePekerjaanText = trim((string) ($t['Type_Pekerjaan'] ?? ''));

                $priorityClass = ticket_badge_priority_class($priorityText);
                $statusClass = ticket_badge_status_class($statusText);
                $typeClass = ticket_badge_type_pekerjaan_class($typePekerjaanText);

                $assignedToUser = trim((string) ($t['assigned_to'] ?? ''));
                $assignedBadge  = $assignedToUser !== '' ? (
                    '<div class="mt-1.5 inline-flex items-center gap-1.5 px-2 py-1 rounded-lg text-[11px] font-medium bg-emerald-50 text-emerald-700 border border-emerald-200 w-full">'
                    . '<span class="relative flex h-2 w-2 flex-shrink-0"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span><span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span></span>'
                    . '<span class="leading-tight">Sedang ditangani<br><span class="font-semibold">' . htmlspecialchars($assignedToUser) . '</span></span>'
                    . '</div>'
                ) : '';

                $tableHtml .= '<tr class="hover:bg-orange-50/40 transition-colors">'
                    . '<td class="px-4 py-3 text-sm text-gray-800 whitespace-nowrap">' . htmlspecialchars((string) $rowNo) . '</td>'
                    . '<td class="px-4 py-3 text-sm text-gray-800 whitespace-nowrap">'
                    . '<div class="font-semibold text-gray-900">' . htmlspecialchars($codeDisplay) . '</div>'
                    . '<div class="text-xs text-gray-500">#' . htmlspecialchars((string) $codeInt) . '</div>'
                    . '</td>'
                    . '<td class="px-4 py-3 text-sm text-gray-800">' . htmlspecialchars((string) ($t['Subject'] ?? '')) . '</td>'
                    . '<td class="px-4 py-3 text-sm text-gray-800">' . htmlspecialchars((string) ($t['Kategori_Masalah'] ?? '')) . '</td>'
                    . '<td class="px-4 py-3 text-sm text-gray-800 whitespace-nowrap">'
                    . '<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold border ' . htmlspecialchars($priorityClass) . '">' . htmlspecialchars($priorityText) . '</span>'
                    . '</td>'
                    . '<td class="px-4 py-3 text-sm text-gray-800 whitespace-nowrap">'
                    . '<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold border ' . htmlspecialchars($statusClass) . '">' . htmlspecialchars($statusText) . '</span>'
                    . $assignedBadge
                    . '</td>'
                    . '<td class="px-4 py-3 text-sm text-gray-800 whitespace-nowrap">'
                    . ($typePekerjaanText !== ''
                        ? '<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold border ' . htmlspecialchars($typeClass) . '">' . htmlspecialchars($typePekerjaanText) . '</span>'
                        : '<span class="text-gray-400">-</span>')
                    . '</td>'
                    . '<td class="px-4 py-3 text-sm text-gray-800 whitespace-nowrap">' . htmlspecialchars((string) ($t['Create_User'] ?? '')) . '</td>'
                    . '<td class="px-4 py-3 text-sm text-gray-800 whitespace-nowrap">';

                if ($hasFoto) {
                    $tableHtml .= '<a class="text-orange-700 hover:underline" href="../uploads/ticket/' . rawurlencode((string) $t['Foto_Ticket']) . '" target="_blank" rel="noopener">Foto</a>';
                }
                if ($hasFoto && $hasDoc) {
                    $tableHtml .= '<span class="text-gray-400">|</span>';
                }
                if ($hasDoc) {
                    $tableHtml .= '<a class="text-orange-700 hover:underline" href="../uploads/ticket/' . rawurlencode((string) $t['Document']) . '" target="_blank" rel="noopener">Doc</a>';
                }
                if (!$hasFoto && !$hasDoc) {
                    $tableHtml .= '<span class="text-gray-400">-</span>';
                }

                $tableHtml .= '</td>'
                    . '<td class="px-4 py-3 text-sm text-gray-800 min-w-[220px]">' . htmlspecialchars((string) $jawabanItDisplay) . '</td>'
                    . '<td class="px-4 py-3 text-sm text-gray-800 whitespace-nowrap">'
                    . ($itFileName !== ''
                        ? '<a class="text-orange-700 hover:underline" href="../uploads/ticket/' . rawurlencode((string) $itFileName) . '" target="_blank" rel="noopener">File IT</a>'
                        : '<span class="text-gray-500">' . htmlspecialchars((string) $itFileDisplay) . '</span>')
                    . '</td>'
                    . '<td class="px-4 py-3 text-sm whitespace-nowrap">'
                    . '<div class="flex items-center gap-2">'
                    . '<button type="button" class="inline-flex items-center gap-2 px-3 py-2 rounded-full text-xs font-semibold border border-blue-200 bg-blue-50 text-blue-700 hover:bg-blue-100 transition-all duration-150 transform hover:scale-105 active:scale-95" '
                    . 'data-action="auditTicket" '
                    . 'data-ticket-code="' . htmlspecialchars((string) $codeInt, ENT_QUOTES) . '" '
                    . 'data-code-display="' . htmlspecialchars($codeDisplay, ENT_QUOTES) . '">'
                    . '<i class="fas fa-clock-rotate-left" aria-hidden="true"></i>Audit</button>'
                    . ($canApproveClose
                        ? '<form method="POST" class="inline" data-action="approveClose">'
                        . '<input type="hidden" name="action" value="approve_close" />'
                        . '<input type="hidden" name="Ticket_code" value="' . htmlspecialchars((string) $codeInt, ENT_QUOTES) . '" />'
                        . '<button type="submit" class="inline-flex items-center gap-2 px-3 py-2 rounded-full text-xs font-semibold border border-green-200 bg-green-50 text-green-700 hover:bg-green-100 transition-all duration-150 transform hover:scale-105 active:scale-95">'
                        . '<i class="fas fa-circle-check" aria-hidden="true"></i>Approve Close</button>'
                        . '</form>'
                        : '')
                    . '<button type="button" class="inline-flex items-center gap-2 px-3 py-2 rounded-full text-xs font-semibold border border-gray-200 bg-gray-100 text-gray-800 hover:bg-gray-200 transition-all duration-150 transform hover:scale-105 active:scale-95" '
                    . 'data-action="viewTicket" '
                    . 'data-ticket-code="' . htmlspecialchars((string) $codeInt, ENT_QUOTES) . '" '
                    . 'data-code-display="' . htmlspecialchars($codeDisplay, ENT_QUOTES) . '" '
                    . 'data-created="' . htmlspecialchars((string) ($t['Create_User'] ?? ''), ENT_QUOTES) . '" '
                    . 'data-status="' . htmlspecialchars((string) ($t['Status_Request'] ?? ''), ENT_QUOTES) . '" '
                    . 'data-subject="' . htmlspecialchars((string) ($t['Subject'] ?? ''), ENT_QUOTES) . '" '
                    . 'data-kategori="' . htmlspecialchars((string) ($t['Kategori_Masalah'] ?? ''), ENT_QUOTES) . '" '
                    . 'data-priority="' . htmlspecialchars((string) ($t['Priority'] ?? ''), ENT_QUOTES) . '" '
                    . 'data-type-pekerjaan="' . htmlspecialchars((string) ($t['Type_Pekerjaan'] ?? ''), ENT_QUOTES) . '" '
                    . 'data-divisi="' . htmlspecialchars((string) ($t['Divisi_User'] ?? ''), ENT_QUOTES) . '" '
                    . 'data-jabatan="' . htmlspecialchars((string) ($t['Jabatan_User'] ?? ''), ENT_QUOTES) . '" '
                    . 'data-region="' . htmlspecialchars((string) ($t['Region'] ?? ''), ENT_QUOTES) . '" '
                    . 'data-deskripsi="' . htmlspecialchars((string) ($t['Deskripsi_Masalah'] ?? ''), ENT_QUOTES) . '" '
                    . 'data-foto="' . htmlspecialchars((string) ($t['Foto_Ticket'] ?? ''), ENT_QUOTES) . '" '
                    . 'data-doc="' . htmlspecialchars((string) ($t['Document'] ?? ''), ENT_QUOTES) . '" '
                    . 'data-jawaban-it="' . htmlspecialchars((string) ($t['Jawaban_IT'] ?? ''), ENT_QUOTES) . '" '
                    . 'data-file-it="' . htmlspecialchars((string) ($t['Photo_IT'] ?? ''), ENT_QUOTES) . '">'
                    . '<i class="fas fa-eye" aria-hidden="true"></i>View</button>';

                $editExtra = $canEdit ? 'hover:bg-orange-100 hover:scale-105 active:scale-95' : 'opacity-50 cursor-not-allowed';
                $disabledAttr = $canEdit ? '' : 'disabled';
                $tableHtml .= '<button type="button" class="inline-flex items-center gap-2 px-3 py-2 rounded-full text-xs font-semibold border border-orange-200 bg-orange-50 text-orange-800 transition-all duration-150 transform ' . $editExtra . '" '
                    . 'data-action="editTicket" '
                    . 'data-ticket-code="' . htmlspecialchars((string) $codeInt, ENT_QUOTES) . '" '
                    . 'data-code-display="' . htmlspecialchars($codeDisplay, ENT_QUOTES) . '" '
                    . 'data-status="' . htmlspecialchars((string) ($t['Status_Request'] ?? ''), ENT_QUOTES) . '" '
                    . 'data-subject="' . htmlspecialchars((string) ($t['Subject'] ?? ''), ENT_QUOTES) . '" '
                    . 'data-kategori="' . htmlspecialchars((string) ($t['Kategori_Masalah'] ?? ''), ENT_QUOTES) . '" '
                    . 'data-priority="' . htmlspecialchars((string) ($t['Priority'] ?? ''), ENT_QUOTES) . '" '
                    . 'data-deskripsi="' . htmlspecialchars((string) ($t['Deskripsi_Masalah'] ?? ''), ENT_QUOTES) . '" '
                    . 'data-foto="' . htmlspecialchars((string) ($t['Foto_Ticket'] ?? ''), ENT_QUOTES) . '" '
                    . 'data-doc="' . htmlspecialchars((string) ($t['Document'] ?? ''), ENT_QUOTES) . '" '
                    . $disabledAttr . '>'
                    . '<i class="fas fa-pen" aria-hidden="true"></i>Edit</button>';

                $deleteExtra = $canEdit ? 'hover:bg-red-100 hover:scale-105 active:scale-95' : 'opacity-50 cursor-not-allowed';
                $delDisabled = $canEdit ? '' : 'disabled';
                $tableHtml .= '<form method="POST" class="inline" data-action="deleteTicket">'
                    . '<input type="hidden" name="action" value="delete_ticket" />'
                    . '<input type="hidden" name="Ticket_code" value="' . htmlspecialchars((string) $codeInt, ENT_QUOTES) . '" />'
                    . '<button type="submit" class="inline-flex items-center gap-2 px-3 py-2 rounded-full text-xs font-semibold border border-red-200 bg-red-50 text-red-700 transition-all duration-150 transform ' . $deleteExtra . '" ' . $delDisabled . '>'
                    . '<i class="fas fa-trash" aria-hidden="true"></i>Hapus</button>'
                    . '</form>'
                    . '</div>'
                    . (!$canEdit ? '<div class="text-xs text-gray-500 mt-1">Edit hanya saat status Open</div>' : '')
                    . '</td>'
                    . '</tr>';
            }
        }

        $tableHtml .= '</tbody>';
        $paginationHtml = ticket_build_pagination_html($page, $totalPages, $totalRecords, $offset, $limit, $baseParams);

        echo json_encode([
            'table_html' => $tableHtml,
            'pagination_html' => $paginationHtml,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_records' => $totalRecords,
            'status_counts' => $ajaxStatusCounts,
            'total_all_records' => $ajaxTotalAllRecords,
        ]);
        exit();
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
        exit();
    }
}

// AJAX: audit timeline for a ticket (user-owned only)
if (isset($_GET['action']) && $_GET['action'] === 'ajax_get_ticket_audit') {
    header('Content-Type: application/json; charset=utf-8');

    try {
        $ticketCode = isset($_GET['ticket_code']) ? (int) $_GET['ticket_code'] : 0;
        if ($ticketCode <= 0) {
            throw new Exception('Ticket code tidak valid.');
        }

        $normalizeStatus = static function (?string $status): string {
            $key = strtolower(trim((string) $status));
            $key = str_replace(['_', '-'], ' ', $key);
            $key = preg_replace('/\s+/', ' ', $key);
            return trim((string) $key);
        };

        $formatDuration = static function (int $seconds): string {
            $seconds = max(0, $seconds);
            $days = intdiv($seconds, 86400);
            $seconds = $seconds % 86400;
            $hours = intdiv($seconds, 3600);
            $seconds = $seconds % 3600;
            $mins = intdiv($seconds, 60);

            $parts = [];
            if ($days > 0)
                $parts[] = $days . ' hari';
            if ($hours > 0)
                $parts[] = $hours . ' jam';
            $parts[] = $mins . ' menit';
            return implode(' ', $parts);
        };

        // Ownership check + pull IT response/file for audit display
        $stmtOwn = $kon->prepare('SELECT `Ticket_code`, `Status_Request`, `Create_User`, `Jawaban_IT`, `Photo_IT`, `Type_Pekerjaan` FROM `ticket` WHERE `Ticket_code` = ? AND `Id_Karyawan` = ? AND `Create_By_User` = ? LIMIT 1');
        if (!$stmtOwn) {
            throw new Exception('Prepare gagal: ' . $kon->error);
        }
        $stmtOwn->bind_param('iss', $ticketCode, $Id_Karyawan, $username);
        if (!$stmtOwn->execute()) {
            throw new Exception('Query gagal: ' . $stmtOwn->error);
        }
        $ownRes = $stmtOwn->get_result();
        $ownRow = $ownRes ? $ownRes->fetch_assoc() : null;
        $stmtOwn->close();
        if (!$ownRow) {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden']);
            exit();
        }

        ticket_audit_ensure_table($kon);

        $items = [];
        $slaStartTs = null;
        $doneTs = null;
        $closedTs = null;
        $itJawaban = isset($ownRow['Jawaban_IT']) ? trim((string) $ownRow['Jawaban_IT']) : '';
        $itFile = isset($ownRow['Photo_IT']) ? trim((string) $ownRow['Photo_IT']) : '';

        $createdTs = null;
        $createdAt = isset($ownRow['Create_User']) ? (string) $ownRow['Create_User'] : '';
        if ($createdAt !== '') {
            $ts = strtotime($createdAt);
            if ($ts !== false) {
                $createdTs = (int) $ts;
            }
        }

        $stmtH = $kon->prepare('SELECT `status_from`, `status_to`, `changed_at`, `changed_by`, `changed_by_role`, `note` FROM `ticket_status_history` WHERE `Ticket_code` = ? ORDER BY `changed_at` ASC, `id` ASC');
        if ($stmtH) {
            $stmtH->bind_param('i', $ticketCode);
            if ($stmtH->execute()) {
                $resH = $stmtH->get_result();
                if ($resH) {
                    while ($row = $resH->fetch_assoc()) {
                        $statusTo = isset($row['status_to']) ? (string) $row['status_to'] : '';
                        $statusToNorm = $normalizeStatus($statusTo);
                        $changedAt = isset($row['changed_at']) ? (string) $row['changed_at'] : '';

                        if ($slaStartTs === null && $statusToNorm === 'in progress') {
                            $ts = strtotime($changedAt);
                            if ($ts !== false) {
                                $slaStartTs = (int) $ts;
                            }
                        }
                        if ($statusToNorm === 'closed') {
                            $ts = strtotime($changedAt);
                            if ($ts !== false) {
                                $closedTs = (int) $ts;
                            }
                        }

                        if ($doneTs === null && $statusToNorm === 'done') {
                            $ts = strtotime($changedAt);
                            if ($ts !== false) {
                                $doneTs = (int) $ts;
                            }
                        }

                        $items[] = [
                            'status_from' => $row['status_from'],
                            'status_to' => $statusTo,
                            'changed_at' => $changedAt,
                            'changed_by' => $row['changed_by'],
                            'changed_by_role' => $row['changed_by_role'],
                            'note' => $row['note'],
                        ];
                    }
                }
            }
            $stmtH->close();
        }

        // Add IT response + IT file on Done event
        foreach ($items as $idx => $it) {
            $statusToNorm = $normalizeStatus(isset($it['status_to']) ? (string) $it['status_to'] : '');
            if ($statusToNorm === 'done') {
                $items[$idx]['it_response'] = $itJawaban;
                $items[$idx]['it_file'] = $itFile;
            }
        }

        // Add SLA up to Done (from first In Progress -> Done, fallback to Create_User)
        if ($doneTs !== null) {
            $slaStartResolved = $slaStartTs;
            if ($slaStartResolved === null) {
                $slaStartResolved = $createdTs;
            }
            if ($slaStartResolved !== null && $doneTs >= $slaStartResolved) {
                $slaDoneSeconds = (int) ($doneTs - $slaStartResolved);
                $slaDoneText = $formatDuration($slaDoneSeconds);
                foreach ($items as $idx => $it) {
                    $statusToNorm = $normalizeStatus(isset($it['status_to']) ? (string) $it['status_to'] : '');
                    if ($statusToNorm === 'done') {
                        $items[$idx]['sla_done_seconds'] = $slaDoneSeconds;
                        $items[$idx]['sla_done_text'] = $slaDoneText;
                    }
                }
            }
        }

        // Add SLA on Closed event (from first In Progress -> Closed, fallback to Create_User)
        if ($closedTs !== null) {
            if ($slaStartTs === null) {
                $slaStartTs = $createdTs;
            }

            if ($slaStartTs !== null && $closedTs >= $slaStartTs) {
                $slaSeconds = (int) ($closedTs - $slaStartTs);
                $slaText = $formatDuration($slaSeconds);
                foreach ($items as $idx => $it) {
                    $statusToNorm = $normalizeStatus(isset($it['status_to']) ? (string) $it['status_to'] : '');
                    if ($statusToNorm === 'closed') {
                        $items[$idx]['sla_seconds'] = $slaSeconds;
                        $items[$idx]['sla_text'] = $slaText;
                    }
                }
            }
        }

        // Backward compatibility: tickets that existed before audit logging
        if (count($items) === 0) {
            $currentStatus = isset($ownRow['Status_Request']) ? (string) $ownRow['Status_Request'] : 'Open';
            $items[] = [
                'status_from' => null,
                'status_to' => $currentStatus,
                'changed_at' => isset($ownRow['Create_User']) ? (string) $ownRow['Create_User'] : date('Y-m-d H:i:s'),
                'changed_by' => $Nama_Lengkap,
                'changed_by_role' => 'user',
                'note' => 'Ticket dibuat',
            ];

            $norm = $normalizeStatus($currentStatus);
            if ($norm === 'done') {
                $items[0]['it_response'] = $itJawaban;
                $items[0]['it_file'] = $itFile;
            }
        }

        echo json_encode([
            'ok' => true,
            'ticket_code' => $ticketCode,
            'items' => $items,
        ]);
        exit();
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
        exit();
    }
}

// Tampilkan ticket milik user saja.
$userTickets = [];
$ticketListError = null;
$totalRecords = 0;
$totalPages = 1;
$paginationHtml = '';

// Defaults for tab rendering (avoid undefined vars if query fails)
$statusCounts = array_fill_keys(ticket_user_status_list(), 0);
$totalAllRecords = 0;
$tabBaseParams = [];
try {
    // Counts per status for tabs (user scope)
    if ($hasSearch) {
        $stmtStatusCounts = $kon->prepare('SELECT `Status_Request`, COUNT(*) AS total FROM `ticket` WHERE `Id_Karyawan` = ? AND `Create_By_User` = ? AND ' . $ticketUserSearchWhereSql . ' GROUP BY `Status_Request`');
    } else {
        $stmtStatusCounts = $kon->prepare('SELECT `Status_Request`, COUNT(*) AS total FROM `ticket` WHERE `Id_Karyawan` = ? AND `Create_By_User` = ? GROUP BY `Status_Request`');
    }
    if ($stmtStatusCounts) {
        if ($hasSearch) {
            $stmtStatusCounts->bind_param(
                'ssssssssss',
                $Id_Karyawan,
                $username,
                $searchLikeTicketCode,
                $searchLikeText,
                $searchLikeText,
                $searchLikeText,
                $searchLikeText,
                $searchLikeText,
                $searchLikeText,
                $searchLikeText
            );
        } else {
            $stmtStatusCounts->bind_param('ss', $Id_Karyawan, $username);
        }
        if ($stmtStatusCounts->execute()) {
            $resStatus = $stmtStatusCounts->get_result();
            if ($resStatus) {
                while ($r = $resStatus->fetch_assoc()) {
                    $st = isset($r['Status_Request']) ? (string) $r['Status_Request'] : '';
                    $cnt = (int) ($r['total'] ?? 0);
                    $totalAllRecords += $cnt;
                    if ($st !== '' && ticket_user_is_allowed_status($st)) {
                        $statusCounts[$st] = $cnt;
                    }
                }
            }
        }
        $stmtStatusCounts->close();
    }

    // Count total records
    if ($statusFilter !== '' && $hasSearch) {
        $stmtCount = $kon->prepare('SELECT COUNT(*) AS total FROM `ticket` WHERE `Id_Karyawan` = ? AND `Create_By_User` = ? AND `Status_Request` = ? AND ' . $ticketUserSearchWhereSql);
    } elseif ($statusFilter !== '') {
        $stmtCount = $kon->prepare('SELECT COUNT(*) AS total FROM `ticket` WHERE `Id_Karyawan` = ? AND `Create_By_User` = ? AND `Status_Request` = ?');
    } elseif ($hasSearch) {
        $stmtCount = $kon->prepare('SELECT COUNT(*) AS total FROM `ticket` WHERE `Id_Karyawan` = ? AND `Create_By_User` = ? AND ' . $ticketUserSearchWhereSql);
    } else {
        $stmtCount = $kon->prepare('SELECT COUNT(*) AS total FROM `ticket` WHERE `Id_Karyawan` = ? AND `Create_By_User` = ?');
    }
    if (!$stmtCount) {
        throw new Exception($kon->error);
    }

    if ($statusFilter !== '' && $hasSearch) {
        $stmtCount->bind_param(
            'sssssssssss',
            $Id_Karyawan,
            $username,
            $statusFilter,
            $searchLikeTicketCode,
            $searchLikeText,
            $searchLikeText,
            $searchLikeText,
            $searchLikeText,
            $searchLikeText,
            $searchLikeText,
            $searchLikeText
        );
    } elseif ($statusFilter !== '') {
        $stmtCount->bind_param('sss', $Id_Karyawan, $username, $statusFilter);
    } elseif ($hasSearch) {
        $stmtCount->bind_param(
            'ssssssssss',
            $Id_Karyawan,
            $username,
            $searchLikeTicketCode,
            $searchLikeText,
            $searchLikeText,
            $searchLikeText,
            $searchLikeText,
            $searchLikeText,
            $searchLikeText,
            $searchLikeText
        );
    } else {
        $stmtCount->bind_param('ss', $Id_Karyawan, $username);
    }
    if ($stmtCount->execute()) {
        $resCount = $stmtCount->get_result();
        $rowCount = $resCount ? $resCount->fetch_assoc() : null;
        $totalRecords = $rowCount ? (int) ($rowCount['total'] ?? 0) : 0;
    }
    $stmtCount->close();

    // Fallback for All count if statusCounts query didn't run
    if ($totalAllRecords === 0 && $statusFilter !== '') {
        if ($hasSearch) {
            $stmtAll = $kon->prepare('SELECT COUNT(*) AS total FROM `ticket` WHERE `Id_Karyawan` = ? AND `Create_By_User` = ? AND ' . $ticketUserSearchWhereSql);
        } else {
            $stmtAll = $kon->prepare('SELECT COUNT(*) AS total FROM `ticket` WHERE `Id_Karyawan` = ? AND `Create_By_User` = ?');
        }
        if ($stmtAll) {
            if ($hasSearch) {
                $stmtAll->bind_param(
                    'ssssssssss',
                    $Id_Karyawan,
                    $username,
                    $searchLikeTicketCode,
                    $searchLikeText,
                    $searchLikeText,
                    $searchLikeText,
                    $searchLikeText,
                    $searchLikeText,
                    $searchLikeText,
                    $searchLikeText
                );
            } else {
                $stmtAll->bind_param('ss', $Id_Karyawan, $username);
            }
            if ($stmtAll->execute()) {
                $resAll = $stmtAll->get_result();
                $rowAll = $resAll ? $resAll->fetch_assoc() : null;
                $totalAllRecords = $rowAll ? (int) ($rowAll['total'] ?? 0) : 0;
            }
            $stmtAll->close();
        }
    }

    if ($statusFilter === '') {
        $totalAllRecords = $totalRecords;
    }

    $totalPages = $totalRecords > 0 ? (int) ceil($totalRecords / $limit) : 1;
    if ($page > $totalPages) {
        $page = $totalPages;
        $offset = ($page - 1) * $limit;
    }

    if ($statusFilter !== '' && $hasSearch) {
        $stmtList = $kon->prepare('SELECT `Ticket_code`, `Subject`, `Kategori_Masalah`, `Priority`, `Status_Request`, `Type_Pekerjaan`, `Create_User`, `Divisi_User`, `Jabatan_User`, `Region`, `Deskripsi_Masalah`, `Foto_Ticket`, `Document`, `Jawaban_IT`, `Photo_IT` FROM `ticket` WHERE `Id_Karyawan` = ? AND `Create_By_User` = ? AND `Status_Request` = ? AND ' . $ticketUserSearchWhereSql . ' ORDER BY `Ticket_code` DESC LIMIT ? OFFSET ?');
    } elseif ($statusFilter !== '') {
        $stmtList = $kon->prepare('SELECT `Ticket_code`, `Subject`, `Kategori_Masalah`, `Priority`, `Status_Request`, `Type_Pekerjaan`, `Create_User`, `Divisi_User`, `Jabatan_User`, `Region`, `Deskripsi_Masalah`, `Foto_Ticket`, `Document`, `Jawaban_IT`, `Photo_IT` FROM `ticket` WHERE `Id_Karyawan` = ? AND `Create_By_User` = ? AND `Status_Request` = ? ORDER BY `Ticket_code` DESC LIMIT ? OFFSET ?');
    } elseif ($hasSearch) {
        $stmtList = $kon->prepare('SELECT `Ticket_code`, `Subject`, `Kategori_Masalah`, `Priority`, `Status_Request`, `Type_Pekerjaan`, `Create_User`, `Divisi_User`, `Jabatan_User`, `Region`, `Deskripsi_Masalah`, `Foto_Ticket`, `Document`, `Jawaban_IT`, `Photo_IT` FROM `ticket` WHERE `Id_Karyawan` = ? AND `Create_By_User` = ? AND ' . $ticketUserSearchWhereSql . ' ORDER BY `Ticket_code` DESC LIMIT ? OFFSET ?');
    } else {
        $stmtList = $kon->prepare('SELECT `Ticket_code`, `Subject`, `Kategori_Masalah`, `Priority`, `Status_Request`, `Type_Pekerjaan`, `Create_User`, `Divisi_User`, `Jabatan_User`, `Region`, `Deskripsi_Masalah`, `Foto_Ticket`, `Document`, `Jawaban_IT`, `Photo_IT` FROM `ticket` WHERE `Id_Karyawan` = ? AND `Create_By_User` = ? ORDER BY `Ticket_code` DESC LIMIT ? OFFSET ?');
    }
    if (!$stmtList) {
        throw new Exception($kon->error);
    }
    if ($statusFilter !== '' && $hasSearch) {
        $stmtList->bind_param(
            'sssssssssssii',
            $Id_Karyawan,
            $username,
            $statusFilter,
            $searchLikeTicketCode,
            $searchLikeText,
            $searchLikeText,
            $searchLikeText,
            $searchLikeText,
            $searchLikeText,
            $searchLikeText,
            $searchLikeText,
            $limit,
            $offset
        );
    } elseif ($statusFilter !== '') {
        $stmtList->bind_param('sssii', $Id_Karyawan, $username, $statusFilter, $limit, $offset);
    } elseif ($hasSearch) {
        $stmtList->bind_param(
            'ssssssssssii',
            $Id_Karyawan,
            $username,
            $searchLikeTicketCode,
            $searchLikeText,
            $searchLikeText,
            $searchLikeText,
            $searchLikeText,
            $searchLikeText,
            $searchLikeText,
            $searchLikeText,
            $limit,
            $offset
        );
    } else {
        $stmtList->bind_param('ssii', $Id_Karyawan, $username, $limit, $offset);
    }
    if ($stmtList->execute()) {
        $resList = $stmtList->get_result();
        if ($resList) {
            while ($row = $resList->fetch_assoc()) {
                $userTickets[] = $row;
            }
        }
    }
    $stmtList->close();

    // Build base params for pagination links (preserve existing GET params except page)
    $baseParams = $_GET;
    unset($baseParams['page']);
    unset($baseParams['action']);
    $paginationHtml = ticket_build_pagination_html($page, $totalPages, $totalRecords, $offset, $limit, $baseParams);

    // Base params for tabs (reset page; preserve other params)
    $tabBaseParams = $_GET;
    unset($tabBaseParams['page']);
    unset($tabBaseParams['action']);
    unset($tabBaseParams['status']);
} catch (Throwable $e) {
    $ticketListError = 'Gagal mengambil daftar ticket.';
    error_log('Ticket list error (user/ticket.php): ' . $e->getMessage());
}

// ===== Ticket Dashboard (User) - based on user's tickets =====
$dashTotalTickets = 0;
$dashStatusCounts = array_fill_keys(ticket_user_status_list(), 0);
$dashPriorityCounts = ['Low' => 0, 'Medium' => 0, 'High' => 0, 'Urgent' => 0];
$dashRecentTickets = [];
$dashAvgResponseTime = '-';

// Status counts + total
try {
    $stmtDashStatus = $kon->prepare('SELECT `Status_Request`, COUNT(*) AS total FROM `ticket` WHERE `Id_Karyawan` = ? AND `Create_By_User` = ? GROUP BY `Status_Request`');
    if ($stmtDashStatus) {
        $stmtDashStatus->bind_param('ss', $Id_Karyawan, $username);
        if ($stmtDashStatus->execute()) {
            $resDashStatus = $stmtDashStatus->get_result();
            if ($resDashStatus) {
                while ($r = $resDashStatus->fetch_assoc()) {
                    $st = isset($r['Status_Request']) ? (string) $r['Status_Request'] : '';
                    $cnt = (int) ($r['total'] ?? 0);
                    $dashTotalTickets += $cnt;
                    if ($st !== '' && ticket_user_is_allowed_status($st)) {
                        $dashStatusCounts[$st] = $cnt;
                    }
                }
            }
        }
        $stmtDashStatus->close();
    }
} catch (Throwable $e) {
    // best-effort
}

// Priority distribution
try {
    $stmtDashPr = $kon->prepare('SELECT `Priority`, COUNT(*) AS total FROM `ticket` WHERE `Id_Karyawan` = ? AND `Create_By_User` = ? GROUP BY `Priority`');
    if ($stmtDashPr) {
        $stmtDashPr->bind_param('ss', $Id_Karyawan, $username);
        if ($stmtDashPr->execute()) {
            $resDashPr = $stmtDashPr->get_result();
            if ($resDashPr) {
                while ($r = $resDashPr->fetch_assoc()) {
                    $p = isset($r['Priority']) ? strtolower(trim((string) $r['Priority'])) : '';
                    $cnt = (int) ($r['total'] ?? 0);
                    if ($p === 'low')
                        $dashPriorityCounts['Low'] += $cnt;
                    elseif ($p === 'medium')
                        $dashPriorityCounts['Medium'] += $cnt;
                    elseif ($p === 'high')
                        $dashPriorityCounts['High'] += $cnt;
                    elseif ($p === 'urgent')
                        $dashPriorityCounts['Urgent'] += $cnt;
                }
            }
        }
        $stmtDashPr->close();
    }
} catch (Throwable $e) {
    // best-effort
}

// Recent tickets
try {
    $stmtRecent = $kon->prepare('SELECT `Ticket_code`, `Nama_User`, `Divisi_User`, `Subject`, `Kategori_Masalah`, `Priority`, `Status_Request`, `Create_User` FROM `ticket` WHERE `Id_Karyawan` = ? AND `Create_By_User` = ? ORDER BY `Ticket_code` DESC LIMIT 5');
    if ($stmtRecent) {
        $stmtRecent->bind_param('ss', $Id_Karyawan, $username);
        if ($stmtRecent->execute()) {
            $resRecent = $stmtRecent->get_result();
            if ($resRecent) {
                while ($row = $resRecent->fetch_assoc()) {
                    $dashRecentTickets[] = $row;
                }
            }
        }
        $stmtRecent->close();
    }
} catch (Throwable $e) {
    // best-effort
}

// Avg response time (based on first status change in ticket_status_history)
try {
    $hasHistoryTable = false;
    $resTbl = $kon->query("SHOW TABLES LIKE 'ticket_status_history'");
    if ($resTbl) {
        $hasHistoryTable = ($resTbl->num_rows > 0);
        $resTbl->free();
    }

    if ($hasHistoryTable) {
        $sqlAvg = "SELECT AVG(TIMESTAMPDIFF(SECOND, t.Create_User, h.first_changed_at)) AS avg_sec\n"
            . "FROM ticket t\n"
            . "JOIN (SELECT Ticket_code, MIN(changed_at) AS first_changed_at FROM ticket_status_history GROUP BY Ticket_code) h\n"
            . "  ON h.Ticket_code = t.Ticket_code\n"
            . "WHERE t.Id_Karyawan = ? AND t.Create_By_User = ? AND t.Create_User IS NOT NULL AND h.first_changed_at IS NOT NULL";

        $stmtAvg = $kon->prepare($sqlAvg);
        if ($stmtAvg) {
            $stmtAvg->bind_param('ss', $Id_Karyawan, $username);
            if ($stmtAvg->execute()) {
                $resAvg = $stmtAvg->get_result();
                if ($resAvg) {
                    $avgRow = $resAvg->fetch_assoc();
                    $avgSec = isset($avgRow['avg_sec']) ? (float) $avgRow['avg_sec'] : 0.0;
                    if ($avgSec > 0) {
                        if ($avgSec >= 3600) {
                            $dashAvgResponseTime = number_format($avgSec / 3600, 1) . 'h';
                        } elseif ($avgSec >= 60) {
                            $dashAvgResponseTime = (string) round($avgSec / 60) . 'm';
                        } else {
                            $dashAvgResponseTime = (string) round($avgSec) . 's';
                        }
                    }
                }
            }
            $stmtAvg->close();
        }
    }
} catch (Throwable $e) {
    // best-effort
}

$dashOpenTickets = (int) ($dashStatusCounts['Open'] ?? 0);
$dashInProgressTickets = (int) ($dashStatusCounts['In Progress'] ?? 0);
$dashReviewTickets = (int) ($dashStatusCounts['Review'] ?? 0);
$dashDoneTickets = (int) ($dashStatusCounts['Done'] ?? 0);
$dashRejectedTickets = (int) ($dashStatusCounts['Reject'] ?? 0);
$dashClosedTickets = (int) ($dashStatusCounts['Closed'] ?? 0);

$dashStatusData = [];
foreach (ticket_user_status_list() as $st) {
    $dashStatusData[] = ['name' => $st, 'value' => (int) ($dashStatusCounts[$st] ?? 0)];
}

$dashPriorityData = [
    ['name' => 'Low', 'value' => (int) ($dashPriorityCounts['Low'] ?? 0)],
    ['name' => 'Medium', 'value' => (int) ($dashPriorityCounts['Medium'] ?? 0)],
    ['name' => 'High', 'value' => (int) ($dashPriorityCounts['High'] ?? 0)],
    ['name' => 'Urgent', 'value' => (int) ($dashPriorityCounts['Urgent'] ?? 0)],
];
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket - User</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../global_dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script
        src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>

    <style>
        @keyframes spin { to { transform: rotate(360deg); } }
        /* Modal animation helpers (no extra dependencies) */
        .modal-overlay {
            opacity: 0;
            transition: opacity 180ms ease;
        }

        .modal-panel {
            opacity: 0;
            transform: scale(0.96);
            transition: opacity 180ms ease, transform 180ms ease;
            will-change: transform, opacity;
        }

        .modal-open .modal-overlay {
            opacity: 1;
        }

        .modal-open .modal-panel {
            opacity: 1;
            transform: scale(1);
        }

        @media (prefers-reduced-motion: reduce) {

            .modal-overlay,
            .modal-panel {
                transition: none !important;
            }
        }
    </style>
</head>

<body class="bg-gray-50">
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

    <?php $activePage = 'ticket';
    require_once __DIR__ . '/sidebar_user_include.php'; ?>

    <div id="main-content-wrapper" class="lg:ml-60 transition-all duration-300">
        <script>
            (function () {
                var el = document.getElementById('main-content-wrapper');
                if (!el) return;
                function apply(collapsed) {
                    if (collapsed) { el.style.marginLeft = '0'; }
                    else { el.style.marginLeft = ''; }
                }
                if (window.innerWidth >= 1024 && localStorage.getItem('sidebarCollapsed') === '1') { apply(true); }
                window.addEventListener('sidebarToggled', function (e) { if (window.innerWidth >= 1024) apply(e.detail.collapsed); });
                window.addEventListener('resize', function () {
                    if (window.innerWidth >= 1024) { apply(localStorage.getItem('sidebarCollapsed') === '1'); }
                    else { apply(false); }
                });
            })();
        </script>
        <main class="p-6 lg:p-8">
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900 mb-2 mt-16">Ticket</h1>
                <p class="text-gray-600">Buat request ticket IT Support.</p>
            </div>

            <?php if ($flashSuccess): ?>
                <div data-flash="1"
                    class="mb-6 bg-green-50 border border-green-200 text-green-700 rounded-lg p-4 transition-opacity duration-300">
                    <?php echo htmlspecialchars($flashSuccess); ?>
                </div>
            <?php endif; ?>
            <?php if ($flashError): ?>
                <div data-flash="1"
                    class="mb-6 bg-red-50 border border-red-200 text-red-700 rounded-lg p-4 transition-opacity duration-300">
                    <?php echo htmlspecialchars($flashError); ?>
                </div>
            <?php endif; ?>

            <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                <div class="flex items-start gap-4">
                    <div class="w-10 h-10 rounded-lg bg-orange-100 flex items-center justify-center">
                        <i class="fas fa-ticket-alt text-orange-600"></i>
                    </div>
                    <div class="flex-1">
                        <h2 class="font-semibold text-gray-900">Buat Ticket</h2>
                        <p class="text-gray-700 mt-1">Ticket akan tercatat dengan ID Karyawan & nama user.</p>

                        <div class="mt-4">
                            <button type="button"
                                class="inline-flex items-center gap-2 px-5 py-3 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-all duration-200 font-medium transform hover:scale-105 active:scale-95"
                                data-action="openCreateTicket">
                                <i class="fas fa-plus" aria-hidden="true"></i>
                                Buat Ticket
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Create Ticket Modal -->
            <div id="createTicketModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="modal-overlay absolute inset-0 bg-black/50" data-modal-close="create"></div>
                <div
                    class="modal-panel relative w-full max-w-3xl bg-white rounded-xl shadow-lg overflow-hidden max-h-[calc(100vh-2rem)] flex flex-col">
                    <div class="flex items-center justify-between px-6 py-4 border-b">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">Buat Ticket</h3>
                            <p class="text-sm text-gray-600">Lengkapi form request IT Support</p>
                        </div>
                        <button type="button" class="text-gray-500 hover:text-gray-800" data-modal-close="create">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <div class="p-6 overflow-y-auto flex-1">
                        <form id="createTicketForm" method="POST" enctype="multipart/form-data" class="space-y-4">
                            <input type="hidden" name="action" value="create_ticket" />

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">ID Karyawan</label>
                                    <input type="text" value="<?php echo htmlspecialchars($Id_Karyawan); ?>" readonly
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-50" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Nama</label>
                                    <input type="text" value="<?php echo htmlspecialchars($Nama_Lengkap); ?>" readonly
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-50" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Jabatan</label>
                                    <input type="text"
                                        value="<?php echo htmlspecialchars($Jabatan_Auto !== '' ? $Jabatan_Auto : ($Jabatan_Level !== '' ? $Jabatan_Level : 'User')); ?>"
                                        readonly
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-50" />
                                    <p class="text-xs text-gray-500 mt-1">Otomatis dari data akun</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Divisi</label>
                                    <input name="Divisi_User" type="text"
                                        value="<?php echo htmlspecialchars($Divisi_Auto); ?>" readonly
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-50" />
                                    <p class="text-xs text-gray-500 mt-1">Otomatis dari data akun</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Region</label>
                                    <input name="Region" type="text"
                                        value="<?php echo htmlspecialchars($Region_Auto); ?>" readonly
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-50" />
                                    <p class="text-xs text-gray-500 mt-1">Otomatis dari data akun</p>
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                                    <input name="Subject" type="text" required maxlength="250"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg"
                                        placeholder="Judul singkat masalah" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Kategori Masalah</label>
                                    <select name="Kategori_Masalah" required
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg">
                                        <option value="">Pilih kategori</option>
                                        <option value="Aplikasi">Aplikasi</option>
                                        <option value="Email">Email</option>
                                        <option value="Jaringan">Jaringan</option>
                                        <option value="Hardware">Hardware</option>
                                        <option value="Akun/Access">Akun/Access</option>
                                        <option value="Lainnya">Lainnya</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
                                    <select name="Priority" required
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg">
                                        <option value="">Pilih priority</option>
                                        <option value="Low">Low</option>
                                        <option value="Medium">Medium</option>
                                        <option value="High">High</option>
                                        <option value="Urgent">Urgent</option>
                                    </select>
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi
                                        Masalah</label>
                                    <textarea name="Deskripsi_Masalah" required maxlength="255" rows="4"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg"
                                        placeholder="Jelaskan masalah secara singkat (maks 255 karakter)"></textarea>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Foto Evidence
                                        (opsional)</label>
                                    <input id="Foto_Ticket_Create" name="Foto_Ticket" type="file" accept="image/*"
                                        capture="environment" class="w-full text-sm js-ticket-photo-input" />
                                    <div class="mt-2">
                                        <button type="button"
                                            class="inline-flex items-center gap-2 px-3 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 text-sm js-ticket-camera-btn"
                                            data-target-input="Foto_Ticket_Create">
                                            <i class="fas fa-camera" aria-hidden="true"></i>
                                            Gunakan Kamera
                                        </button>
                                    </div>
                                    <div id="ticketCameraBox_Create"
                                        class="mt-3 hidden p-3 bg-orange-50 rounded-lg border border-orange-200">
                                        <video id="ticketCameraVideo_Create" playsinline autoplay
                                            class="w-full max-w-sm rounded-lg shadow-md mx-auto"></video>
                                        <div id="ticketCameraGeo_Create"
                                            class="mt-2 text-[11px] text-gray-700 text-center">
                                            Menunggu lokasi...
                                        </div>
                                        <div class="mt-3 flex gap-2 justify-center">
                                            <button type="button"
                                                class="px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white rounded-lg text-sm js-ticket-camera-capture"
                                                data-camera-scope="Create" data-target-input="Foto_Ticket_Create">
                                                <i class="fas fa-circle-dot mr-2" aria-hidden="true"></i>Capture
                                            </button>
                                            <button type="button"
                                                class="px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg text-sm js-ticket-camera-close"
                                                data-camera-scope="Create">
                                                <i class="fas fa-xmark mr-2" aria-hidden="true"></i>Tutup
                                            </button>
                                        </div>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1">JPG/PNG/GIF/WEBP</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Document
                                        (opsional)</label>
                                    <input name="Document" type="file" class="w-full text-sm" />
                                    <p class="text-xs text-gray-500 mt-1">PDF/DOC/DOCX/XLS/XLSX</p>
                                </div>
                            </div>

                            <div class="pt-2 flex justify-end gap-2">
                                <button type="button"
                                    class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50"
                                    data-modal-close="create">Batal</button>
                                <button id="createTicketSubmitBtn" type="submit"
                                    class="px-5 py-3 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-all duration-200 font-medium inline-flex items-center gap-2 transform hover:scale-105 active:scale-95">
                                    <i class="fas fa-paper-plane" aria-hidden="true"></i>
                                    Kirim Request
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Dashboard Ticket (User) -->
            <div class="mb-8">
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center justify-between gap-4 mb-5">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg bg-orange-100 flex items-center justify-center">
                                <i class="fas fa-chart-pie text-orange-600" aria-hidden="true"></i>
                            </div>
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900">Dashboard Ticket</h2>
                                <p class="text-sm text-gray-600">Ringkasan ticket dan insight cepat</p>
                            </div>
                        </div>
                        <span id="urt-live-badge"
                            class="flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-50 border border-amber-200 text-amber-700"
                            title="Menghubungkan ke real-time..." style="transition:all 0.4s ease;">
                            <span id="urt-live-dot" class="inline-block w-2 h-2 rounded-full bg-amber-400"
                                style="transition:background-color 0.4s ease, transform 0.3s ease;"></span>
                            LIVE
                        </span>
                    </div>

                    <!-- Stats Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                        <div tabindex="0"
                            class="pressable bg-white rounded-lg border border-gray-200 p-5 shadow-sm cursor-pointer transition-all duration-200 ease-out hover:-translate-y-0.5 hover:shadow-lg hover:border-orange-200 hover:bg-orange-50/30 active:scale-[0.99] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-orange-500/40 focus-visible:ring-offset-2">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-sm text-gray-600 mb-1">Total Tickets</p>
                                    <div id="urt-total" class="text-3xl font-semibold text-gray-900"
                                        style="transition:color 0.3s;">
                                        <?php echo htmlspecialchars((string) $dashTotalTickets); ?></div>
                                    <p class="text-xs text-gray-500 mt-1">All time</p>
                                </div>
                                <div class="p-3 rounded-lg bg-blue-100 text-blue-600">
                                    <i class="fas fa-ticket-alt" aria-hidden="true"></i>
                                </div>
                            </div>
                        </div>

                        <div tabindex="0"
                            class="pressable bg-white rounded-lg border border-gray-200 p-5 shadow-sm cursor-pointer transition-all duration-200 ease-out hover:-translate-y-0.5 hover:shadow-lg hover:border-orange-200 hover:bg-orange-50/30 active:scale-[0.99] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-orange-500/40 focus-visible:ring-offset-2">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-sm text-gray-600 mb-1">Open Tickets</p>
                                    <div id="urt-open" class="text-3xl font-semibold text-gray-900"
                                        style="transition:color 0.3s;">
                                        <?php echo htmlspecialchars((string) $dashOpenTickets); ?></div>
                                    <p class="text-xs text-gray-500 mt-1">Need action</p>
                                </div>
                                <div class="p-3 rounded-lg bg-orange-100 text-orange-600">
                                    <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                                </div>
                            </div>
                        </div>

                        <div tabindex="0"
                            class="pressable bg-white rounded-lg border border-gray-200 p-5 shadow-sm cursor-pointer transition-all duration-200 ease-out hover:-translate-y-0.5 hover:shadow-lg hover:border-orange-200 hover:bg-orange-50/30 active:scale-[0.99] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-orange-500/40 focus-visible:ring-offset-2">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-sm text-gray-600 mb-1">In Progress</p>
                                    <div id="urt-inprogress" class="text-3xl font-semibold text-gray-900"
                                        style="transition:color 0.3s;">
                                        <?php echo htmlspecialchars((string) $dashInProgressTickets); ?></div>
                                    <p class="text-xs text-gray-500 mt-1">Being processed</p>
                                </div>
                                <div class="p-3 rounded-lg bg-yellow-100 text-yellow-700">
                                    <i class="fas fa-clock" aria-hidden="true"></i>
                                </div>
                            </div>
                        </div>

                        <div tabindex="0"
                            class="pressable bg-white rounded-lg border border-gray-200 p-5 shadow-sm cursor-pointer transition-all duration-200 ease-out hover:-translate-y-0.5 hover:shadow-lg hover:border-orange-200 hover:bg-orange-50/30 active:scale-[0.99] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-orange-500/40 focus-visible:ring-offset-2">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-sm text-gray-600 mb-1">Completed (Done)</p>
                                    <div id="urt-done" class="text-3xl font-semibold text-gray-900"
                                        style="transition:color 0.3s;">
                                        <?php echo htmlspecialchars((string) $dashDoneTickets); ?></div>
                                    <p class="text-xs text-gray-500 mt-1">Resolved</p>
                                </div>
                                <div class="p-3 rounded-lg bg-green-100 text-green-600">
                                    <i class="fas fa-check-circle" aria-hidden="true"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Additional Stats -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                        <div tabindex="0"
                            class="pressable bg-white rounded-lg border border-gray-200 p-5 shadow-sm cursor-pointer transition-all duration-200 ease-out hover:-translate-y-0.5 hover:shadow-lg hover:border-orange-200 hover:bg-orange-50/30 active:scale-[0.99] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-orange-500/40 focus-visible:ring-offset-2">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-sm text-gray-600 mb-1">Review</p>
                                    <div id="urt-review" class="text-3xl font-semibold text-gray-900"
                                        style="transition:color 0.3s;">
                                        <?php echo htmlspecialchars((string) $dashReviewTickets); ?></div>
                                    <p class="text-xs text-gray-500 mt-1">Waiting validation</p>
                                </div>
                                <div class="p-3 rounded-lg bg-yellow-100 text-yellow-700">
                                    <i class="fas fa-clipboard-check" aria-hidden="true"></i>
                                </div>
                            </div>
                        </div>

                        <div tabindex="0"
                            class="pressable bg-white rounded-lg border border-gray-200 p-5 shadow-sm cursor-pointer transition-all duration-200 ease-out hover:-translate-y-0.5 hover:shadow-lg hover:border-orange-200 hover:bg-orange-50/30 active:scale-[0.99] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-orange-500/40 focus-visible:ring-offset-2">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-sm text-gray-600 mb-1">Closed</p>
                                    <div id="urt-closed" class="text-3xl font-semibold text-gray-900"
                                        style="transition:color 0.3s;">
                                        <?php echo htmlspecialchars((string) $dashClosedTickets); ?></div>
                                    <p class="text-xs text-gray-500 mt-1">Finalized by user</p>
                                </div>
                                <div class="p-3 rounded-lg bg-gray-200 text-gray-600">
                                    <i class="fas fa-lock" aria-hidden="true"></i>
                                </div>
                            </div>
                        </div>

                        <div tabindex="0"
                            class="pressable bg-white rounded-lg border border-gray-200 p-5 shadow-sm cursor-pointer transition-all duration-200 ease-out hover:-translate-y-0.5 hover:shadow-lg hover:border-orange-200 hover:bg-orange-50/30 active:scale-[0.99] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-orange-500/40 focus-visible:ring-offset-2">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-sm text-gray-600 mb-1">Rejected</p>
                                    <div id="urt-rejected" class="text-3xl font-semibold text-gray-900"
                                        style="transition:color 0.3s;">
                                        <?php echo htmlspecialchars((string) $dashRejectedTickets); ?></div>
                                    <p class="text-xs text-gray-500 mt-1">Need review</p>
                                </div>
                                <div class="p-3 rounded-lg bg-red-100 text-red-600">
                                    <i class="fas fa-times-circle" aria-hidden="true"></i>
                                </div>
                            </div>
                        </div>

                        <div tabindex="0"
                            class="pressable bg-white rounded-lg border border-gray-200 p-5 shadow-sm cursor-pointer transition-all duration-200 ease-out hover:-translate-y-0.5 hover:shadow-lg hover:border-orange-200 hover:bg-orange-50/30 active:scale-[0.99] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-orange-500/40 focus-visible:ring-offset-2">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-sm text-gray-600 mb-1">Average Response Time</p>
                                    <div id="urt-avgtime" class="text-3xl font-semibold text-gray-900"
                                        style="transition:color 0.3s;">
                                        <?php echo htmlspecialchars((string) $dashAvgResponseTime); ?></div>
                                    <p class="text-xs text-gray-500 mt-1">Based on first status change</p>
                                </div>
                                <div class="p-3 rounded-lg bg-gray-200 text-gray-600">
                                    <i class="fas fa-chart-line" aria-hidden="true"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Charts -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
                        <div tabindex="0"
                            class="pressable bg-white rounded-lg border border-gray-200 p-5 cursor-pointer transition-all duration-200 ease-out hover:-translate-y-0.5 hover:shadow-lg hover:border-orange-200 hover:bg-orange-50/20 active:scale-[0.99] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-orange-500/40 focus-visible:ring-offset-2">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Ticket Status Distribution</h3>
                            <div class="relative" style="height: 300px;">
                                <canvas id="userTicketStatusChart"></canvas>
                            </div>
                        </div>
                        <div tabindex="0"
                            class="pressable bg-white rounded-lg border border-gray-200 p-5 cursor-pointer transition-all duration-200 ease-out hover:-translate-y-0.5 hover:shadow-lg hover:border-orange-200 hover:bg-orange-50/20 active:scale-[0.99] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-orange-500/40 focus-visible:ring-offset-2">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Tickets by Priority</h3>
                            <div class="relative" style="height: 300px;">
                                <canvas id="userTicketPriorityChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Tickets -->
                    <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-semibold text-gray-900">Recent Tickets</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th
                                            class="px-5 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            Ticket Code</th>
                                        <th
                                            class="px-5 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            User</th>
                                        <th
                                            class="px-5 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            Divisi</th>
                                        <th
                                            class="px-5 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            Subject</th>
                                        <th
                                            class="px-5 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            Category</th>
                                        <th
                                            class="px-5 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            Priority</th>
                                        <th
                                            class="px-5 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            Status</th>
                                        <th
                                            class="px-5 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            Created</th>
                                    </tr>
                                </thead>
                                <tbody id="user-dash-recent-tbody" class="bg-white divide-y divide-gray-100">
                                    <?php if (count($dashRecentTickets) === 0): ?>
                                        <tr>
                                            <td class="px-5 py-4 text-sm text-gray-600" colspan="8">Belum ada data ticket.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($dashRecentTickets as $rt): ?>
                                            <?php
                                            $rtCodeInt = (int) ($rt['Ticket_code'] ?? 0);
                                            $rtCodeDisplay = ticket_user_format_code($rtCodeInt, isset($rt['Create_User']) ? (string) $rt['Create_User'] : null);
                                            $rtStatus = isset($rt['Status_Request']) ? (string) $rt['Status_Request'] : '';
                                            $rtStatusClass = ticket_badge_status_class($rtStatus);
                                            $rtPriority = isset($rt['Priority']) ? (string) $rt['Priority'] : '';
                                            $rtPriorityClass = ticket_badge_priority_class($rtPriority);
                                            ?>
                                            <tr class="hover:bg-orange-50/40 transition-colors">
                                                <td class="px-5 py-4 whitespace-nowrap">
                                                    <div class="text-sm font-semibold text-gray-900">
                                                        <?php echo htmlspecialchars($rtCodeDisplay); ?></div>
                                                    <div class="text-xs text-gray-500">
                                                        #<?php echo htmlspecialchars((string) $rtCodeInt); ?></div>
                                                </td>
                                                <td class="px-5 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <?php echo htmlspecialchars((string) ($rt['Nama_User'] ?? $Nama_Lengkap)); ?>
                                                </td>
                                                <td class="px-5 py-4 whitespace-nowrap text-sm text-gray-600">
                                                    <?php echo htmlspecialchars((string) ($rt['Divisi_User'] ?? '')); ?></td>
                                                <td class="px-5 py-4 text-sm text-gray-900">
                                                    <?php echo htmlspecialchars((string) ($rt['Subject'] ?? '')); ?></td>
                                                <td class="px-5 py-4 whitespace-nowrap text-sm text-gray-600">
                                                    <?php echo htmlspecialchars((string) ($rt['Kategori_Masalah'] ?? '')); ?>
                                                </td>
                                                <td class="px-5 py-4 whitespace-nowrap">
                                                    <span
                                                        class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold border <?php echo htmlspecialchars($rtPriorityClass); ?>"><?php echo htmlspecialchars($rtPriority); ?></span>
                                                </td>
                                                <td class="px-5 py-4 whitespace-nowrap">
                                                    <span
                                                        class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold border <?php echo htmlspecialchars($rtStatusClass); ?>"><?php echo htmlspecialchars($rtStatus); ?></span>
                                                </td>
                                                <td class="px-5 py-4 whitespace-nowrap text-sm text-gray-600">
                                                    <?php echo htmlspecialchars((string) ($rt['Create_User'] ?? '')); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ===== IT TEAM STATUS BANNER ===== -->
            <div id="it-status-banner" class="mb-4 rounded-xl border transition-all duration-500"
                style="display:none; overflow:hidden;">
                <div class="flex items-center gap-4 px-5 py-3.5">
                    <!-- Pulsing icon -->
                    <div id="it-status-icon" class="flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center text-lg">
                        <span id="it-status-emoji">⚙️</span>
                    </div>
                    <!-- Text content -->
                    <div class="flex-1 min-w-0">
                        <p id="it-status-title" class="text-sm font-semibold leading-tight"></p>
                        <p id="it-status-sub" class="text-xs mt-0.5 opacity-80"></p>
                    </div>
                    <!-- Stats pills -->
                    <div id="it-status-pills" class="hidden sm:flex items-center gap-2 flex-shrink-0"></div>
                    <!-- Live dot -->
                    <div class="flex-shrink-0 flex items-center gap-1.5">
                        <span class="relative flex h-2 w-2">
                            <span id="it-live-ping" class="animate-ping absolute inline-flex h-full w-full rounded-full opacity-75"></span>
                            <span id="it-live-dot" class="relative inline-flex rounded-full h-2 w-2"></span>
                        </span>
                        <span class="text-[10px] font-medium opacity-60">LIVE</span>
                    </div>
                </div>
            </div>
            <!-- ===== END IT TEAM STATUS BANNER ===== -->

            <div class="bg-white rounded-xl shadow-lg p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Ticket Saya</h2>

                <form id="ticketSearchForm" method="GET" class="mb-4">
                    <?php if ($statusFilter !== ''): ?>
                        <input type="hidden" name="status"
                            value="<?php echo htmlspecialchars($statusFilter, ENT_QUOTES); ?>" />
                    <?php endif; ?>
                    <?php
                    $downloadParams = [];
                    if ($statusFilter !== '') {
                        $downloadParams['status'] = $statusFilter;
                    }
                    if ($searchQuery !== '') {
                        $downloadParams['q'] = $searchQuery;
                    }
                    $downloadUrl = 'download_ticket_report.php';
                    if (!empty($downloadParams)) {
                        $downloadUrl .= '?' . http_build_query($downloadParams);
                    }
                    ?>
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                        <div class="max-w-md w-full">
                            <div class="relative">
                                <div
                                    class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                                    <i class="fas fa-search" aria-hidden="true"></i>
                                </div>
                                <input id="ticketSearchInput" name="q" type="text"
                                    value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="Search ticket..."
                                    class="w-full pl-10 pr-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-orange-500/40 focus:border-orange-400" />
                            </div>
                        </div>

                        <div
                            class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full md:w-auto mt-2 md:mt-0">
                            <?php $approvalCloseCount = isset($statusCounts['Done']) ? (int) $statusCounts['Done'] : 0; ?>
                            <button id="ticketApprovalCloseBtn" type="button"
                                class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg text-sm font-semibold whitespace-nowrap <?php echo ($approvalCloseCount > 0) ? 'bg-green-600 text-white hover:bg-green-700' : 'bg-gray-200 text-gray-500 cursor-not-allowed'; ?>"
                                <?php echo ($approvalCloseCount > 0) ? '' : 'disabled'; ?>>
                                <i class="fas fa-circle-check" aria-hidden="true"></i>
                                Approval Close
                                <span id="ticketApprovalCloseCount"
                                    class="inline-flex items-center justify-center min-w-[1.5rem] h-6 px-2 rounded-full text-xs font-bold <?php echo ($approvalCloseCount > 0) ? 'bg-white/20 text-white' : 'bg-white text-gray-600'; ?>">
                                    <?php echo htmlspecialchars((string) $approvalCloseCount); ?>
                                </span>
                            </button>

                            <a id="ticketDownloadReportBtn" href="<?php echo htmlspecialchars($downloadUrl); ?>"
                                class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-slate-700 text-white rounded-lg hover:bg-slate-800 text-sm font-semibold whitespace-nowrap">
                                <i class="fas fa-download" aria-hidden="true"></i>
                                Download Report
                            </a>
                        </div>
                    </div>
                </form>

                <!-- Status Tabs (filter) -->
                <?php
                $tabClassBase = 'status-tab inline-flex items-center gap-2 px-4 py-2 rounded-lg border text-sm font-semibold transition-all duration-200 ease-out transform active:scale-95';
                $tabClassActive = $tabClassBase . ' bg-orange-600 border-orange-600 text-white';
                $tabClassInactive = $tabClassBase . ' bg-white border-gray-200 text-gray-700 hover:bg-orange-50 hover:border-orange-200';

                $countClassBase = 'tab-count inline-flex items-center justify-center px-2 py-0.5 rounded-full text-xs font-semibold';
                $countClassActive = $countClassBase . ' bg-white/20 text-white';
                $countClassInactive = $countClassBase . ' bg-gray-100 text-gray-700';

                $activeTabStatus = $statusFilter;
                $allTabUrl = '?' . http_build_query($tabBaseParams ?? []);
                ?>
                <div class="mb-5">
                    <div class="flex flex-wrap items-center gap-2">
                        <a href="<?php echo htmlspecialchars($allTabUrl); ?>"
                            class="<?php echo ($activeTabStatus === '') ? $tabClassActive : $tabClassInactive; ?>"
                            data-status=""
                            data-class-active="<?php echo htmlspecialchars($tabClassActive, ENT_QUOTES); ?>"
                            data-class-inactive="<?php echo htmlspecialchars($tabClassInactive, ENT_QUOTES); ?>">
                            <span>All</span>
                            <span
                                class="<?php echo ($activeTabStatus === '') ? $countClassActive : $countClassInactive; ?>"
                                data-class-active="<?php echo htmlspecialchars($countClassActive, ENT_QUOTES); ?>"
                                data-class-inactive="<?php echo htmlspecialchars($countClassInactive, ENT_QUOTES); ?>">
                                <?php echo htmlspecialchars((string) ($totalAllRecords ?? 0)); ?>
                            </span>
                        </a>

                        <?php foreach (ticket_user_status_list() as $st): ?>
                            <?php
                            $isActive = ($activeTabStatus === $st);
                            $tabUrl = '?' . http_build_query(array_merge($tabBaseParams ?? [], ['status' => $st]));
                            $countVal = isset($statusCounts[$st]) ? (int) $statusCounts[$st] : 0;
                            ?>
                            <a href="<?php echo htmlspecialchars($tabUrl); ?>"
                                class="<?php echo $isActive ? $tabClassActive : $tabClassInactive; ?>"
                                data-status="<?php echo htmlspecialchars($st, ENT_QUOTES); ?>"
                                data-class-active="<?php echo htmlspecialchars($tabClassActive, ENT_QUOTES); ?>"
                                data-class-inactive="<?php echo htmlspecialchars($tabClassInactive, ENT_QUOTES); ?>">
                                <span><?php echo htmlspecialchars($st); ?></span>
                                <span class="<?php echo $isActive ? $countClassActive : $countClassInactive; ?>"
                                    data-class-active="<?php echo htmlspecialchars($countClassActive, ENT_QUOTES); ?>"
                                    data-class-inactive="<?php echo htmlspecialchars($countClassInactive, ENT_QUOTES); ?>">
                                    <?php echo htmlspecialchars((string) $countVal); ?>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div id="tickets-table-container" class="overflow-x-auto">
                    <?php if ($ticketListError): ?>
                        <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-4">
                            <?php echo htmlspecialchars($ticketListError); ?>
                        </div>
                    <?php elseif (count($userTickets) === 0): ?>
                        <div class="bg-yellow-50 border border-yellow-200 text-yellow-700 rounded-lg p-4">
                            <?php if ($statusFilter !== ''): ?>
                                Tidak ada ticket untuk status: <span
                                    class="font-semibold"><?php echo htmlspecialchars($statusFilter); ?></span>.
                            <?php else: ?>
                                Belum ada ticket untuk user ini.
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <table class="min-w-full border border-gray-200 rounded-lg overflow-hidden">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th
                                        class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider px-4 py-3 border-b">
                                        No</th>
                                    <th
                                        class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider px-4 py-3 border-b">
                                        Ticket Code</th>
                                    <th
                                        class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider px-4 py-3 border-b">
                                        Subject</th>
                                    <th
                                        class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider px-4 py-3 border-b">
                                        Kategori</th>
                                    <th
                                        class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider px-4 py-3 border-b">
                                        Priority</th>
                                    <th
                                        class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider px-4 py-3 border-b">
                                        Status</th>
                                    <th
                                        class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider px-4 py-3 border-b">
                                        Type Pekerjaan</th>
                                    <th
                                        class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider px-4 py-3 border-b">
                                        Created</th>
                                    <th
                                        class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider px-4 py-3 border-b">
                                        File User</th>
                                    <th
                                        class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider px-4 py-3 border-b">
                                        Respon IT</th>
                                    <th
                                        class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider px-4 py-3 border-b">
                                        File IT</th>
                                    <th
                                        class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider px-4 py-3 border-b">
                                        Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                <?php $rowNo = $offset;
                                foreach ($userTickets as $t):
                                    $rowNo++; ?>
                                    <?php
                                    $codeInt = (int) $t['Ticket_code'];
                                    $codeDisplay = ticket_user_format_code($codeInt, isset($t['Create_User']) ? (string) $t['Create_User'] : null);
                                    $hasFoto = isset($t['Foto_Ticket']) && (string) $t['Foto_Ticket'] !== '';
                                    $hasDoc = isset($t['Document']) && (string) $t['Document'] !== '';
                                    $canEdit = isset($t['Status_Request']) && (string) $t['Status_Request'] === 'Open';
                                    $canApproveClose = isset($t['Status_Request']) && (string) $t['Status_Request'] === 'Done';
                                    ?>
                                    <tr class="hover:bg-orange-50/40 transition-colors">
                                        <td class="px-4 py-3 text-sm text-gray-800 whitespace-nowrap">
                                            <?php echo htmlspecialchars((string) $rowNo); ?></td>
                                        <td class="px-4 py-3 text-sm text-gray-800 whitespace-nowrap">
                                            <div class="font-semibold text-gray-900">
                                                <?php echo htmlspecialchars($codeDisplay); ?></div>
                                            <div class="text-xs text-gray-500">
                                                #<?php echo htmlspecialchars((string) $codeInt); ?></div>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-800">
                                            <?php echo htmlspecialchars((string) $t['Subject']); ?></td>
                                        <td class="px-4 py-3 text-sm text-gray-800">
                                            <?php echo htmlspecialchars((string) $t['Kategori_Masalah']); ?></td>
                                        <td class="px-4 py-3 text-sm text-gray-800 whitespace-nowrap">
                                            <?php $priorityText = (string) ($t['Priority'] ?? ''); ?>
                                            <span
                                                class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold border <?php echo ticket_badge_priority_class($priorityText); ?>">
                                                <?php echo htmlspecialchars($priorityText); ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-800 whitespace-nowrap">
                                            <?php $statusText = (string) ($t['Status_Request'] ?? ''); ?>
                                            <span
                                                class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold border <?php echo ticket_badge_status_class($statusText); ?>">
                                                <?php echo htmlspecialchars($statusText); ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-800 whitespace-nowrap">
                                            <?php $typePekerjaanText = trim((string) ($t['Type_Pekerjaan'] ?? '')); ?>
                                            <?php if ($typePekerjaanText !== ''): ?>
                                                <span
                                                    class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold border <?php echo ticket_badge_type_pekerjaan_class($typePekerjaanText); ?>">
                                                    <?php echo htmlspecialchars($typePekerjaanText); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-gray-400">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-800 whitespace-nowrap">
                                            <?php echo htmlspecialchars((string) ($t['Create_User'] ?? '')); ?></td>
                                        <td class="px-4 py-3 text-sm text-gray-800 whitespace-nowrap">
                                            <?php
                                            // (sudah dihitung di atas)
                                            ?>
                                            <?php if ($hasFoto): ?>
                                                <a class="text-orange-700 hover:underline"
                                                    href="../uploads/ticket/<?php echo rawurlencode((string) $t['Foto_Ticket']); ?>"
                                                    target="_blank" rel="noopener">Foto</a>
                                            <?php endif; ?>
                                            <?php if ($hasFoto && $hasDoc): ?>
                                                <span class="text-gray-400">|</span>
                                            <?php endif; ?>
                                            <?php if ($hasDoc): ?>
                                                <a class="text-orange-700 hover:underline"
                                                    href="../uploads/ticket/<?php echo rawurlencode((string) $t['Document']); ?>"
                                                    target="_blank" rel="noopener">Doc</a>
                                            <?php endif; ?>
                                            <?php if (!$hasFoto && !$hasDoc): ?>
                                                <span class="text-gray-400">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-800 min-w-[220px]">
                                            <?php
                                            $jawabanItText = isset($t['Jawaban_IT']) ? trim((string) $t['Jawaban_IT']) : '';
                                            $jawabanItDisplay = ($jawabanItText !== '') ? $jawabanItText : 'Menunggu respon IT';
                                            echo htmlspecialchars((string) $jawabanItDisplay);
                                            ?>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-800 whitespace-nowrap">
                                            <?php
                                            $itFileName = isset($t['Photo_IT']) ? trim((string) $t['Photo_IT']) : '';
                                            if ($itFileName !== '') {
                                                echo '<a class="text-orange-700 hover:underline" href="../uploads/ticket/' . rawurlencode((string) $itFileName) . '" target="_blank" rel="noopener">File IT</a>';
                                            } else {
                                                echo '<span class="text-gray-500">Menunggu update dari IT</span>';
                                            }
                                            ?>
                                        </td>
                                        <td class="px-4 py-3 text-sm whitespace-nowrap">
                                            <div class="flex items-center gap-2">
                                                <?php if ($canApproveClose): ?>
                                                    <form method="POST" class="inline" data-action="approveClose">
                                                        <input type="hidden" name="action" value="approve_close" />
                                                        <input type="hidden" name="Ticket_code"
                                                            value="<?php echo htmlspecialchars((string) $codeInt, ENT_QUOTES); ?>" />
                                                        <button type="submit"
                                                            class="inline-flex items-center gap-2 px-3 py-2 rounded-full text-xs font-semibold border border-green-200 bg-green-50 text-green-700 hover:bg-green-100 transition-all duration-150 transform hover:scale-105 active:scale-95">
                                                            <i class="fas fa-circle-check" aria-hidden="true"></i>
                                                            Approve Close
                                                        </button>
                                                    </form>
                                                <?php endif; ?>

                                                <button type="button"
                                                    class="inline-flex items-center gap-2 px-3 py-2 rounded-full text-xs font-semibold border border-blue-200 bg-blue-50 text-blue-700 hover:bg-blue-100 transition-all duration-150 transform hover:scale-105 active:scale-95"
                                                    data-action="auditTicket"
                                                    data-ticket-code="<?php echo htmlspecialchars((string) $codeInt, ENT_QUOTES); ?>"
                                                    data-code-display="<?php echo htmlspecialchars($codeDisplay, ENT_QUOTES); ?>">
                                                    <i class="fas fa-clock-rotate-left" aria-hidden="true"></i>
                                                    Audit
                                                </button>

                                                <button type="button"
                                                    class="inline-flex items-center gap-2 px-3 py-2 rounded-full text-xs font-semibold border border-gray-200 bg-gray-100 text-gray-800 hover:bg-gray-200 transition-all duration-150 transform hover:scale-105 active:scale-95"
                                                    data-action="viewTicket"
                                                    data-ticket-code="<?php echo htmlspecialchars((string) $codeInt, ENT_QUOTES); ?>"
                                                    data-code-display="<?php echo htmlspecialchars($codeDisplay, ENT_QUOTES); ?>"
                                                    data-created="<?php echo htmlspecialchars((string) ($t['Create_User'] ?? ''), ENT_QUOTES); ?>"
                                                    data-status="<?php echo htmlspecialchars((string) ($t['Status_Request'] ?? ''), ENT_QUOTES); ?>"
                                                    data-subject="<?php echo htmlspecialchars((string) ($t['Subject'] ?? ''), ENT_QUOTES); ?>"
                                                    data-kategori="<?php echo htmlspecialchars((string) ($t['Kategori_Masalah'] ?? ''), ENT_QUOTES); ?>"
                                                    data-priority="<?php echo htmlspecialchars((string) ($t['Priority'] ?? ''), ENT_QUOTES); ?>"
                                                    data-type-pekerjaan="<?php echo htmlspecialchars((string) ($t['Type_Pekerjaan'] ?? ''), ENT_QUOTES); ?>"
                                                    data-divisi="<?php echo htmlspecialchars((string) ($t['Divisi_User'] ?? ''), ENT_QUOTES); ?>"
                                                    data-jabatan="<?php echo htmlspecialchars((string) ($t['Jabatan_User'] ?? ''), ENT_QUOTES); ?>"
                                                    data-region="<?php echo htmlspecialchars((string) ($t['Region'] ?? ''), ENT_QUOTES); ?>"
                                                    data-deskripsi="<?php echo htmlspecialchars((string) ($t['Deskripsi_Masalah'] ?? ''), ENT_QUOTES); ?>"
                                                    data-foto="<?php echo htmlspecialchars((string) ($t['Foto_Ticket'] ?? ''), ENT_QUOTES); ?>"
                                                    data-doc="<?php echo htmlspecialchars((string) ($t['Document'] ?? ''), ENT_QUOTES); ?>"
                                                    data-jawaban-it="<?php echo htmlspecialchars((string) ($t['Jawaban_IT'] ?? ''), ENT_QUOTES); ?>"
                                                    data-file-it="<?php echo htmlspecialchars((string) ($t['Photo_IT'] ?? ''), ENT_QUOTES); ?>">
                                                    <i class="fas fa-eye" aria-hidden="true"></i>
                                                    View
                                                </button>

                                                <button type="button"
                                                    class="inline-flex items-center gap-2 px-3 py-2 rounded-full text-xs font-semibold border border-orange-200 bg-orange-50 text-orange-800 transition-all duration-150 transform <?php echo $canEdit ? 'hover:bg-orange-100 hover:scale-105 active:scale-95' : 'opacity-50 cursor-not-allowed'; ?>"
                                                    data-action="editTicket"
                                                    data-ticket-code="<?php echo htmlspecialchars((string) $codeInt, ENT_QUOTES); ?>"
                                                    data-code-display="<?php echo htmlspecialchars($codeDisplay, ENT_QUOTES); ?>"
                                                    data-status="<?php echo htmlspecialchars((string) ($t['Status_Request'] ?? ''), ENT_QUOTES); ?>"
                                                    data-subject="<?php echo htmlspecialchars((string) ($t['Subject'] ?? ''), ENT_QUOTES); ?>"
                                                    data-kategori="<?php echo htmlspecialchars((string) ($t['Kategori_Masalah'] ?? ''), ENT_QUOTES); ?>"
                                                    data-priority="<?php echo htmlspecialchars((string) ($t['Priority'] ?? ''), ENT_QUOTES); ?>"
                                                    data-deskripsi="<?php echo htmlspecialchars((string) ($t['Deskripsi_Masalah'] ?? ''), ENT_QUOTES); ?>"
                                                    data-foto="<?php echo htmlspecialchars((string) ($t['Foto_Ticket'] ?? ''), ENT_QUOTES); ?>"
                                                    data-doc="<?php echo htmlspecialchars((string) ($t['Document'] ?? ''), ENT_QUOTES); ?>"
                                                    <?php echo $canEdit ? '' : 'disabled'; ?>>
                                                    <i class="fas fa-pen" aria-hidden="true"></i>
                                                    Edit
                                                </button>

                                                <form method="POST" class="inline" data-action="deleteTicket">
                                                    <input type="hidden" name="action" value="delete_ticket" />
                                                    <input type="hidden" name="Ticket_code"
                                                        value="<?php echo htmlspecialchars((string) $codeInt, ENT_QUOTES); ?>" />
                                                    <button type="submit"
                                                        class="inline-flex items-center gap-2 px-3 py-2 rounded-full text-xs font-semibold border border-red-200 bg-red-50 text-red-700 transition-all duration-150 transform <?php echo $canEdit ? 'hover:bg-red-100 hover:scale-105 active:scale-95' : 'opacity-50 cursor-not-allowed'; ?>"
                                                        <?php echo $canEdit ? '' : 'disabled'; ?>>
                                                        <i class="fas fa-trash" aria-hidden="true"></i>
                                                        Hapus
                                                    </button>
                                                </form>
                                            </div>
                                            <?php if (!$canEdit): ?>
                                                <div class="text-xs text-gray-500 mt-1">Edit hanya saat status Open</div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <!-- Pagination -->
                <div id="pagination-container">
                    <?php echo $paginationHtml; ?>
                </div>

                <!-- View Modal -->
                <div id="viewTicketModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="modal-overlay absolute inset-0 bg-black/50" data-modal-close="view"></div>
                    <div
                        class="modal-panel relative w-full max-w-3xl bg-white rounded-xl shadow-lg overflow-hidden max-h-[calc(100vh-2rem)] flex flex-col">
                        <div class="h-1 bg-gradient-to-r from-orange-500 to-orange-300"></div>
                        <div
                            class="flex items-start justify-between gap-4 px-6 py-4 border-b bg-gradient-to-r from-orange-50 to-white">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">Detail Ticket</h3>
                                <p class="text-sm text-gray-600 font-mono" id="viewTicketCode"></p>
                                <p class="text-sm text-gray-700 mt-1" id="viewSubject"></p>
                            </div>
                            <button type="button" class="text-gray-500 hover:text-gray-800" data-modal-close="view">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="p-6 overflow-y-auto flex-1">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="md:col-span-2 space-y-4">
                                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                            <div>
                                                <div
                                                    class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide">
                                                    Created</div>
                                                <div id="viewCreated" class="text-sm font-medium text-gray-900"></div>
                                            </div>
                                            <div>
                                                <div
                                                    class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide">
                                                    Kategori Masalah</div>
                                                <div id="viewKategori" class="text-sm font-medium text-gray-900"></div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="rounded-xl border border-gray-200 bg-white p-4">
                                        <div
                                            class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                            Deskripsi Masalah</div>
                                        <div id="viewDeskripsi" class="text-sm text-gray-800 whitespace-pre-wrap"></div>
                                    </div>

                                    <div class="rounded-xl border border-gray-200 bg-white p-4">
                                        <div
                                            class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-3">
                                            Lampiran</div>
                                        <div class="space-y-3">
                                            <div class="flex items-start justify-between gap-4">
                                                <div class="text-sm font-medium text-gray-700">File User</div>
                                                <div id="viewFilesUser" class="text-sm text-gray-800 text-right"></div>
                                            </div>
                                            <div class="flex items-start justify-between gap-4">
                                                <div class="text-sm font-medium text-gray-700">File IT</div>
                                                <div id="viewFilesIt" class="text-sm text-gray-800 text-right"></div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="rounded-xl border border-gray-200 bg-white p-4">
                                        <div
                                            class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                            Respon IT</div>
                                        <div id="viewJawabanIt" class="text-sm text-gray-800 whitespace-pre-wrap"></div>
                                    </div>
                                </div>

                                <div class="space-y-4">
                                    <div class="rounded-xl border border-gray-200 bg-white p-4">
                                        <div
                                            class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-3">
                                            Ringkasan</div>

                                        <div class="space-y-3">
                                            <div>
                                                <div
                                                    class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide">
                                                    Status</div>
                                                <span id="viewStatus"
                                                    class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold border bg-gray-50 text-gray-700 border-gray-200">-</span>
                                            </div>

                                            <div>
                                                <div
                                                    class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide">
                                                    Priority</div>
                                                <span id="viewPriority"
                                                    class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold border bg-gray-50 text-gray-700 border-gray-200">-</span>
                                            </div>

                                            <div>
                                                <div
                                                    class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide">
                                                    Type Pekerjaan</div>
                                                <span id="viewTypePekerjaan"
                                                    class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold border bg-gray-50 text-gray-700 border-gray-200">-</span>
                                            </div>

                                            <div class="pt-2 border-t border-gray-100"></div>

                                            <div>
                                                <div
                                                    class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide">
                                                    Divisi</div>
                                                <div id="viewDivisi" class="text-sm font-medium text-gray-900">-</div>
                                            </div>
                                            <div>
                                                <div
                                                    class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide">
                                                    Jabatan</div>
                                                <div id="viewJabatan" class="text-sm font-medium text-gray-900">-</div>
                                            </div>
                                            <div>
                                                <div
                                                    class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide">
                                                    Region</div>
                                                <div id="viewRegion" class="text-sm font-medium text-gray-900">-</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="px-6 py-4 border-t flex justify-end">
                            <button type="button"
                                class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50"
                                data-modal-close="view">Tutup</button>
                        </div>
                    </div>
                </div>

                <!-- Audit Modal -->
                <div id="auditTicketModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="modal-overlay absolute inset-0 bg-black/50" data-modal-close="audit"></div>
                    <div
                        class="modal-panel relative w-full max-w-3xl bg-white rounded-xl shadow-lg overflow-hidden max-h-[calc(100vh-2rem)] flex flex-col">
                        <div class="h-1 bg-gradient-to-r from-blue-600 to-blue-300"></div>
                        <div
                            class="flex items-start justify-between gap-4 px-6 py-4 border-b bg-gradient-to-r from-blue-50 to-white">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">Audit Status Ticket</h3>
                                <p class="text-sm text-gray-600 font-mono" id="auditTicketCode"></p>
                            </div>
                            <button type="button" class="text-gray-500 hover:text-gray-800" data-modal-close="audit">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="p-6 overflow-y-auto flex-1">
                            <div id="auditTimelineContainer" class="space-y-4"></div>
                        </div>
                        <div class="px-6 py-4 border-t bg-gray-50 flex items-center justify-end">
                            <button type="button"
                                class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50"
                                data-modal-close="audit">Tutup</button>
                        </div>
                    </div>
                </div>

                <!-- Edit Modal -->
                <div id="editTicketModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="modal-overlay absolute inset-0 bg-black/50" data-modal-close="edit"></div>
                    <div
                        class="modal-panel relative w-full max-w-3xl bg-white rounded-xl shadow-lg overflow-hidden max-h-[calc(100vh-2rem)] flex flex-col">
                        <div class="flex items-center justify-between px-6 py-4 border-b">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">Edit Ticket</h3>
                                <p class="text-sm text-gray-600" id="editTicketCode"></p>
                            </div>
                            <button type="button" class="text-gray-500 hover:text-gray-800" data-modal-close="edit">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>

                        <form id="editTicketForm" method="POST" enctype="multipart/form-data"
                            class="p-6 space-y-3 overflow-y-auto flex-1">
                            <input type="hidden" name="action" value="update_ticket" />
                            <input type="hidden" name="Ticket_code" id="editTicketCodeValue" value="" />

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                                <input name="Subject" id="editSubject" type="text" required maxlength="250"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg" />
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Kategori Masalah</label>
                                    <select name="Kategori_Masalah" id="editKategori" required
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg">
                                        <option value="">Pilih kategori</option>
                                        <option value="Aplikasi">Aplikasi</option>
                                        <option value="Email">Email</option>
                                        <option value="Jaringan">Jaringan</option>
                                        <option value="Hardware">Hardware</option>
                                        <option value="Akun/Access">Akun/Access</option>
                                        <option value="Lainnya">Lainnya</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
                                    <select name="Priority" id="editPriority" required
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg">
                                        <option value="">Pilih priority</option>
                                        <option value="Low">Low</option>
                                        <option value="Medium">Medium</option>
                                        <option value="High">High</option>
                                        <option value="Urgent">Urgent</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi Masalah</label>
                                <textarea name="Deskripsi_Masalah" id="editDeskripsi" required maxlength="255" rows="4"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg"></textarea>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Foto Ticket
                                        (opsional)</label>
                                    <input id="Foto_Ticket_Edit" name="Foto_Ticket" type="file" accept="image/*"
                                        capture="environment" class="w-full text-sm js-ticket-photo-input" />
                                    <div class="mt-2">
                                        <button type="button"
                                            class="inline-flex items-center gap-2 px-3 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 text-sm js-ticket-camera-btn"
                                            data-target-input="Foto_Ticket_Edit">
                                            <i class="fas fa-camera" aria-hidden="true"></i>
                                            Gunakan Kamera
                                        </button>
                                    </div>
                                    <div id="ticketCameraBox_Edit"
                                        class="mt-3 hidden p-3 bg-orange-50 rounded-lg border border-orange-200">
                                        <video id="ticketCameraVideo_Edit" playsinline autoplay
                                            class="w-full max-w-sm rounded-lg shadow-md mx-auto"></video>
                                        <div id="ticketCameraGeo_Edit"
                                            class="mt-2 text-[11px] text-gray-700 text-center">
                                            Menunggu lokasi...
                                        </div>
                                        <div class="mt-3 flex gap-2 justify-center">
                                            <button type="button"
                                                class="px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white rounded-lg text-sm js-ticket-camera-capture"
                                                data-camera-scope="Edit" data-target-input="Foto_Ticket_Edit">
                                                <i class="fas fa-circle-dot mr-2" aria-hidden="true"></i>Capture
                                            </button>
                                            <button type="button"
                                                class="px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg text-sm js-ticket-camera-close"
                                                data-camera-scope="Edit">
                                                <i class="fas fa-xmark mr-2" aria-hidden="true"></i>Tutup
                                            </button>
                                        </div>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1" id="editFotoInfo"></p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Document
                                        (opsional)</label>
                                    <input name="Document" type="file" class="w-full text-sm" />
                                    <p class="text-xs text-gray-500 mt-1" id="editDocInfo"></p>
                                </div>
                            </div>

                            <div class="text-xs text-gray-500">Edit hanya bisa saat status Open.</div>

                            <div class="pt-2 flex justify-end gap-2">
                                <button type="button"
                                    class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50"
                                    data-modal-close="edit">Batal</button>
                                <button type="submit"
                                    class="px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700">Simpan</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Ticket dashboard charts
            if (typeof Chart !== 'undefined') {
                if (typeof ChartDataLabels !== 'undefined') {
                    try {
                        Chart.register(ChartDataLabels);
                    } catch (e) {
                        // ignore
                    }
                }

                const statusEl = document.getElementById('userTicketStatusChart');
                const prEl = document.getElementById('userTicketPriorityChart');
                if (statusEl && prEl) {
                    const statusLabels = <?php echo json_encode(array_column($dashStatusData, 'name')); ?>;
                    const statusValues = <?php echo json_encode(array_map('intval', array_column($dashStatusData, 'value'))); ?>;
                    const prLabels = <?php echo json_encode(array_column($dashPriorityData, 'name')); ?>;
                    const prValues = <?php echo json_encode(array_map('intval', array_column($dashPriorityData, 'value'))); ?>;

                    const statusCtx = statusEl.getContext('2d');
                    const prCtx = prEl.getContext('2d');

                    // Status Chart (Pie)
                    new Chart(statusCtx, {
                        type: 'pie',
                        data: {
                            labels: statusLabels,
                            datasets: [{
                                data: statusValues,
                                backgroundColor: [
                                    '#3B82F6', // Open
                                    '#F97316', // In Progress
                                    '#EAB308', // Review
                                    '#22C55E', // Done
                                    '#EF4444', // Reject
                                    '#6B7280', // Closed
                                ],
                                borderWidth: 2,
                                borderColor: '#ffffff'
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: { padding: 12, font: { size: 12 } }
                                },
                                datalabels: {
                                    color: '#111827',
                                    font: { weight: '600', size: 11 },
                                    formatter: function (value, ctx) {
                                        var v = Number(value || 0);
                                        if (!v) return '';
                                        var dataArr = (ctx && ctx.chart && ctx.chart.data && ctx.chart.data.datasets && ctx.chart.data.datasets[0])
                                            ? (ctx.chart.data.datasets[0].data || [])
                                            : [];
                                        var total = 0;
                                        for (var i = 0; i < dataArr.length; i++) total += Number(dataArr[i] || 0);
                                        var pct = total ? (v / total) * 100 : 0;
                                        return v + ' (' + pct.toFixed(1) + '%)';
                                    }
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function (ctx) {
                                            return (ctx.label || '') + ': ' + (ctx.parsed || 0) + ' tickets';
                                        }
                                    }
                                }
                            }
                        }
                    });

                    // Priority Chart (Bar)
                    new Chart(prCtx, {
                        type: 'bar',
                        data: {
                            labels: prLabels,
                            datasets: [{
                                label: 'Tickets',
                                data: prValues,
                                backgroundColor: '#F97316',
                                borderColor: '#EA580C',
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            layout: { padding: { top: 22 } },
                            plugins: {
                                legend: { display: false },
                                datalabels: {
                                    anchor: 'end',
                                    align: 'end',
                                    offset: 2,
                                    clamp: true,
                                    clip: false,
                                    color: '#111827',
                                    font: { weight: '600', size: 11 },
                                    formatter: function (value, ctx) {
                                        var v = Number(value || 0);
                                        if (!v) return '';
                                        var dataArr = (ctx && ctx.chart && ctx.chart.data && ctx.chart.data.datasets && ctx.chart.data.datasets[0])
                                            ? (ctx.chart.data.datasets[0].data || [])
                                            : [];
                                        var total = 0;
                                        for (var i = 0; i < dataArr.length; i++) total += Number(dataArr[i] || 0);
                                        var pct = total ? (v / total) * 100 : 0;
                                        return v + ' (' + pct.toFixed(1) + '%)';
                                    }
                                }
                            },
                            scales: {
                                y: { beginAtZero: true, grace: '25%', ticks: { precision: 0 } }
                            }
                        }
                    });
                }
            }

            const loadingOverlay = document.getElementById('loadingOverlay');
            if (loadingOverlay) {
                setTimeout(function () {
                    loadingOverlay.style.display = 'none';
                }, 300);
            }

            // Auto-hide flash notifications after 5 seconds
            const flashEls = document.querySelectorAll('[data-flash="1"]');
            if (flashEls && flashEls.length > 0) {
                setTimeout(() => {
                    flashEls.forEach((el) => {
                        el.classList.add('opacity-0');
                        setTimeout(() => {
                            el.remove();
                        }, 350);
                    });
                }, 5000);
            }

            const hamburgerBtn = document.getElementById('hamburger-btn');
            const closeSidebarBtn = document.getElementById('close-sidebar');
            const sidebar = document.getElementById('sidebar');
            const mobileOverlay = document.getElementById('mobile-overlay');
            const hamburgerIcon = document.getElementById('hamburger-icon');

            let sidebarOpen = false;

            function updateSidebar() {
                if (!sidebar || !mobileOverlay || !hamburgerIcon) return;
                if (sidebarOpen) {
                    sidebar.classList.add('open');
                    mobileOverlay.classList.add('active');
                    hamburgerIcon.classList.remove('fa-bars');
                    hamburgerIcon.classList.add('fa-times');
                } else {
                    sidebar.classList.remove('open');
                    mobileOverlay.classList.remove('active');
                    hamburgerIcon.classList.remove('fa-times');
                    hamburgerIcon.classList.add('fa-bars');
                }
            }

            function toggleSidebar() {
                sidebarOpen = !sidebarOpen;
                updateSidebar();
            }

            function closeSidebar() {
                sidebarOpen = false;
                updateSidebar();
            }

            if (hamburgerBtn) {
                hamburgerBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    toggleSidebar();
                });
            }

            if (closeSidebarBtn) {
                closeSidebarBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    closeSidebar();
                });
            }

            if (mobileOverlay) {
                mobileOverlay.addEventListener('click', function (e) {
                    e.preventDefault();
                    closeSidebar();
                });
            }

            window.addEventListener('resize', function () {
                if (window.innerWidth >= 1024) {
                    closeSidebar();
                }
            });

            // Modal handlers (Create / View / Edit)
            const createModal = document.getElementById('createTicketModal');
            const viewModal = document.getElementById('viewTicketModal');
            const editModal = document.getElementById('editTicketModal');
            const auditModal = document.getElementById('auditTicketModal');

            function openModal(el) {
                if (!el) return;
                el.classList.remove('hidden');
                // trigger animation
                requestAnimationFrame(() => {
                    el.classList.add('modal-open');
                });
            }

            function closeModal(el) {
                if (!el) return;
                el.classList.remove('modal-open');
                // wait for transition before hiding
                window.setTimeout(() => {
                    el.classList.add('hidden');
                }, 200);
            }

            document.querySelectorAll('[data-modal-close="create"]').forEach((btn) => {
                btn.addEventListener('click', () => closeModal(createModal));
            });

            document.querySelectorAll('[data-modal-close="view"]').forEach((btn) => {
                btn.addEventListener('click', () => closeModal(viewModal));
            });
            document.querySelectorAll('[data-modal-close="edit"]').forEach((btn) => {
                btn.addEventListener('click', () => closeModal(editModal));
            });
            document.querySelectorAll('[data-modal-close="audit"]').forEach((btn) => {
                btn.addEventListener('click', () => closeModal(auditModal));
            });

            const openCreateBtn = document.querySelector('button[data-action="openCreateTicket"]');
            if (openCreateBtn) {
                openCreateBtn.addEventListener('click', () => openModal(createModal));
            }

            function attachTicketActionListeners() {
                function ticketBadgePriorityClass(priority) {
                    const key = String(priority || '').trim().toLowerCase().replace(/[_-]+/g, ' ');
                    switch (key) {
                        case 'low':
                            return 'bg-gray-100 text-gray-700 border-gray-200';
                        case 'medium':
                            return 'bg-yellow-50 text-yellow-800 border-yellow-200';
                        case 'high':
                            return 'bg-orange-50 text-orange-700 border-orange-200';
                        case 'urgent':
                            return 'bg-red-50 text-red-700 border-red-200';
                        default:
                            return 'bg-gray-50 text-gray-700 border-gray-200';
                    }
                }

                function ticketBadgeStatusClass(status) {
                    const key = String(status || '').trim().toLowerCase().replace(/[_-]+/g, ' ').replace(/\s+/g, ' ');
                    switch (key) {
                        case 'open':
                            return 'bg-blue-50 text-blue-700 border-blue-200';
                        case 'in progress':
                            return 'bg-orange-50 text-orange-700 border-orange-200';
                        case 'review':
                            return 'bg-yellow-50 text-yellow-800 border-yellow-200';
                        case 'done':
                            return 'bg-green-50 text-green-700 border-green-200';
                        case 'reject':
                        case 'rejected':
                            return 'bg-red-50 text-red-700 border-red-200';
                        case 'closed':
                            return 'bg-gray-100 text-gray-700 border-gray-200';
                        default:
                            return 'bg-gray-50 text-gray-700 border-gray-200';
                    }
                }

                function ticketBadgeTypeClass(typePekerjaan) {
                    const key = String(typePekerjaan || '').trim().toLowerCase();
                    switch (key) {
                        case 'remote':
                            return 'bg-purple-50 text-purple-700 border-purple-200';
                        case 'onsite':
                            return 'bg-indigo-50 text-indigo-700 border-indigo-200';
                        default:
                            return 'bg-gray-50 text-gray-700 border-gray-200';
                    }
                }

                function escapeHtml(s) {
                    return String(s || '')
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;')
                        .replace(/'/g, '&#039;');
                }

                function formatAuditDateTime(dt) {
                    const raw = String(dt || '').trim();
                    if (!raw) return '';
                    const iso = raw.includes('T') ? raw : raw.replace(' ', 'T');
                    const d = new Date(iso);
                    if (Number.isNaN(d.getTime())) return raw;
                    return d.toLocaleString('id-ID', {
                        year: 'numeric',
                        month: 'short',
                        day: '2-digit',
                        hour: '2-digit',
                        minute: '2-digit',
                    });
                }

                function formatAuditDateOnly(dt) {
                    const raw = String(dt || '').trim();
                    if (!raw) return '';
                    const iso = raw.includes('T') ? raw : raw.replace(' ', 'T');
                    const d = new Date(iso);
                    if (Number.isNaN(d.getTime())) return raw;
                    return d.toLocaleDateString('id-ID', { year: 'numeric', month: 'long', day: '2-digit' });
                }

                function renderAuditTimeline(items) {
                    const container = document.getElementById('auditTimelineContainer');
                    if (!container) return;

                    const list = Array.isArray(items) ? items : [];
                    if (list.length === 0) {
                        container.innerHTML = '<div class="text-sm text-gray-500">Belum ada riwayat status.</div>';
                        return;
                    }

                    let html = '';
                    let lastDate = '';
                    list.forEach((it, idx) => {
                        const changedAt = it && it.changed_at ? String(it.changed_at) : '';
                        const dateOnly = formatAuditDateOnly(changedAt);
                        if (dateOnly && dateOnly !== lastDate) {
                            html += '<div class="pt-2">'
                                + '<div class="inline-flex items-center gap-2 px-3 py-1 rounded-full border border-gray-200 bg-gray-50 text-xs font-semibold text-gray-700">'
                                + '<i class="fas fa-calendar" aria-hidden="true"></i>'
                                + '<span>' + escapeHtml(dateOnly) + '</span>'
                                + '</div>'
                                + '</div>';
                            lastDate = dateOnly;
                        }

                        const statusFrom = it && it.status_from ? String(it.status_from) : '';
                        const statusTo = it && it.status_to ? String(it.status_to) : '';
                        const actor = it && it.changed_by ? String(it.changed_by) : 'System';
                        const role = it && it.changed_by_role ? String(it.changed_by_role) : '';
                        const note = it && it.note ? String(it.note) : '';

                        const norm = String(statusTo || '').trim().toLowerCase().replace(/[_-]+/g, ' ').replace(/\s+/g, ' ');
                        const itResponse = it && it.it_response ? String(it.it_response) : '';
                        const itFile = it && it.it_file ? String(it.it_file) : '';
                        const slaText = it && it.sla_text ? String(it.sla_text) : '';
                        const slaDoneText = it && it.sla_done_text ? String(it.sla_done_text) : '';

                        const statusMarker = (() => {
                            switch (norm) {
                                case 'open':
                                    return { bg: 'bg-blue-600', ring: 'ring-blue-100', icon: 'fas fa-folder-open' };
                                case 'in progress':
                                    return { bg: 'bg-orange-600', ring: 'ring-orange-100', icon: 'fas fa-spinner' };
                                case 'review':
                                    return { bg: 'bg-yellow-500', ring: 'ring-yellow-100', icon: 'fas fa-magnifying-glass' };
                                case 'done':
                                    return { bg: 'bg-green-600', ring: 'ring-green-100', icon: 'fas fa-circle-check' };
                                case 'reject':
                                case 'rejected':
                                    return { bg: 'bg-red-600', ring: 'ring-red-100', icon: 'fas fa-circle-xmark' };
                                case 'closed':
                                    return { bg: 'bg-gray-600', ring: 'ring-gray-200', icon: 'fas fa-lock' };
                                default:
                                    return { bg: 'bg-blue-600', ring: 'ring-blue-100', icon: 'fas fa-circle' };
                            }
                        })();

                        let extraHtml = '';
                        if (norm === 'done') {
                            const respDisplay = itResponse.trim() ? escapeHtml(itResponse) : '<span class="text-gray-400">-</span>';
                            const fileDisplay = itFile.trim()
                                ? ('<a class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-orange-200 bg-orange-50 text-orange-700 hover:bg-orange-100 transition-colors" '
                                    + 'href="../uploads/ticket/' + encodeURIComponent(itFile) + '" target="_blank" rel="noopener">'
                                    + '<i class="fas fa-file-arrow-down" aria-hidden="true"></i><span>File IT</span></a>')
                                : '<span class="text-gray-400">-</span>';

                            extraHtml += '<div class="mt-2 grid grid-cols-1 md:grid-cols-2 gap-3">'
                                + '<div class="rounded-lg border border-gray-200 bg-gray-50 p-3">'
                                + '<div class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide">Respon IT</div>'
                                + '<div class="mt-1 text-sm text-gray-800 whitespace-pre-wrap">' + respDisplay + '</div>'
                                + '</div>'
                                + '<div class="rounded-lg border border-gray-200 bg-gray-50 p-3">'
                                + '<div class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide">File IT</div>'
                                + '<div class="mt-1 text-sm text-gray-800">' + fileDisplay + '</div>'
                                + '</div>'
                                + '</div>';

                            if (slaDoneText.trim()) {
                                extraHtml += '<div class="mt-2 rounded-lg border border-blue-200 bg-blue-50 p-3">'
                                    + '<div class="text-[11px] font-semibold text-blue-700 uppercase tracking-wide">Total SLA sampai Done</div>'
                                    + '<div class="mt-1 text-sm font-semibold text-blue-800">' + escapeHtml(slaDoneText) + '</div>'
                                    + '</div>';
                            }
                        }

                        if (norm === 'closed' && slaText.trim()) {
                            extraHtml += '<div class="mt-2 rounded-lg border border-blue-200 bg-blue-50 p-3">'
                                + '<div class="text-[11px] font-semibold text-blue-700 uppercase tracking-wide">SLA Approved (Closed oleh User)</div>'
                                + '<div class="mt-1 text-sm font-semibold text-blue-800">' + escapeHtml(slaText) + '</div>'
                                + '</div>';
                        }

                        const isLast = idx === list.length - 1;
                        html += '<div class="relative pl-10 ' + (isLast ? '' : 'pb-6') + '">'
                            + '<span class="absolute left-1.5 top-0.5 w-7 h-7 ' + statusMarker.bg + ' rounded-full ring-4 ' + statusMarker.ring + ' flex items-center justify-center text-white">'
                            + '<i class="' + statusMarker.icon + '" aria-hidden="true"></i>'
                            + '</span>'
                            + (isLast ? '' : '<span class="absolute left-5 top-8 bottom-0 w-px bg-gray-200"></span>')
                            + '<div class="flex items-center justify-between gap-3">'
                            + '<div class="text-xs text-gray-500">' + escapeHtml(formatAuditDateTime(changedAt)) + '</div>'
                            + '<div class="text-[11px] font-semibold px-2 py-0.5 rounded-full border border-blue-200 bg-blue-50 text-blue-700">' + escapeHtml(role || 'system') + '</div>'
                            + '</div>'
                            + '<div class="mt-1 text-sm font-semibold text-gray-900">'
                            + (statusFrom ? ('Status: ' + escapeHtml(statusFrom) + ' \u2192 ' + escapeHtml(statusTo)) : ('Status: ' + escapeHtml(statusTo)))
                            + '</div>'
                            + '<div class="mt-0.5 text-sm text-gray-700">oleh: ' + escapeHtml(actor) + '</div>'
                            + (note.trim() ? ('<div class="mt-2 text-sm text-gray-700 bg-gray-50 border border-gray-200 rounded-lg p-3">' + escapeHtml(note) + '</div>') : '')
                            + (extraHtml ? extraHtml : '')
                            + '</div>';
                    });

                    container.innerHTML = html;
                }

                function loadAuditTimeline(ticketCode) {
                    const container = document.getElementById('auditTimelineContainer');
                    if (container) {
                        container.innerHTML = '<div class="text-sm text-gray-500">Memuat riwayat...</div>';
                    }

                    const url = new URL(window.location.href);
                    url.searchParams.set('action', 'ajax_get_ticket_audit');
                    url.searchParams.set('ticket_code', String(ticketCode || ''));
                    url.searchParams.delete('page');

                    fetch(url.toString())
                        .then((r) => {
                            if (!r.ok) throw new Error('HTTP ' + r.status);
                            return r.json();
                        })
                        .then((data) => {
                            if (data && data.error) {
                                throw new Error(data.error);
                            }
                            renderAuditTimeline((data && data.items) ? data.items : []);
                        })
                        .catch((err) => {
                            console.error(err);
                            if (container) {
                                container.innerHTML = '<div class="text-sm text-red-600">Gagal memuat audit. ' + escapeHtml(err.message || err) + '</div>';
                            }
                        });
                }

                // Audit modal
                document.querySelectorAll('button[data-action="auditTicket"]').forEach((btn) => {
                    if (btn.dataset.boundAudit === '1') return;
                    btn.dataset.boundAudit = '1';

                    btn.addEventListener('click', () => {
                        const d = btn.dataset;
                        const ticketCode = d.ticketCode || '';
                        const codeDisplay = d.codeDisplay || '';
                        const codeEl = document.getElementById('auditTicketCode');
                        if (codeEl) {
                            codeEl.textContent = (codeDisplay ? codeDisplay : '') + (ticketCode ? ('  (#' + ticketCode + ')') : '');
                        }

                        openModal(auditModal);
                        loadAuditTimeline(ticketCode);
                    });
                });

                // View modal populate
                document.querySelectorAll('button[data-action="viewTicket"]').forEach((btn) => {
                    if (btn.dataset.boundView === '1') return;
                    btn.dataset.boundView = '1';

                    btn.addEventListener('click', () => {
                        const d = btn.dataset;
                        const foto = d.foto || '';
                        const doc = d.doc || '';

                        document.getElementById('viewTicketCode').textContent = (d.codeDisplay || '') + (d.ticketCode ? ('  (#' + d.ticketCode + ')') : '');
                        document.getElementById('viewCreated').textContent = d.created || '';
                        document.getElementById('viewSubject').textContent = d.subject || '';
                        document.getElementById('viewKategori').textContent = d.kategori || '';
                        const statusEl = document.getElementById('viewStatus');
                        if (statusEl) {
                            const statusText = d.status || '-';
                            statusEl.textContent = statusText;
                            statusEl.className = 'inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold border ' + ticketBadgeStatusClass(statusText);
                        }

                        const priorityEl = document.getElementById('viewPriority');
                        if (priorityEl) {
                            const priorityText = d.priority || '-';
                            priorityEl.textContent = priorityText;
                            priorityEl.className = 'inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold border ' + ticketBadgePriorityClass(priorityText);
                        }

                        const typeEl = document.getElementById('viewTypePekerjaan');
                        if (typeEl) {
                            const typeText = (d.typePekerjaan || '').trim();
                            const displayText = typeText !== '' ? typeText : '-';
                            typeEl.textContent = displayText;
                            typeEl.className = 'inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold border ' + ticketBadgeTypeClass(typeText);
                        }
                        document.getElementById('viewDivisi').textContent = d.divisi || '';
                        document.getElementById('viewJabatan').textContent = d.jabatan || '';
                        document.getElementById('viewRegion').textContent = d.region || '';
                        document.getElementById('viewDeskripsi').textContent = d.deskripsi || '';

                        const jawabanText = (d.jawabanIt || '').trim();
                        const jawabanEl = document.getElementById('viewJawabanIt');
                        if (jawabanEl) {
                            jawabanEl.textContent = jawabanText !== '' ? jawabanText : 'Menunggu respon IT';
                        }

                        const filesEl = document.getElementById('viewFilesUser');
                        if (filesEl) {
                            filesEl.innerHTML = '';
                        }
                        const parts = [];
                        if (foto) {
                            const a = document.createElement('a');
                            a.href = '../uploads/ticket/' + encodeURIComponent(foto);
                            a.target = '_blank';
                            a.rel = 'noopener';
                            a.className = 'inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-orange-200 bg-orange-50 text-orange-700 hover:bg-orange-100 transition-colors';
                            a.innerHTML = '<i class="fas fa-image" aria-hidden="true"></i><span>Foto</span>';
                            parts.push(a);
                        }
                        if (doc) {
                            const a = document.createElement('a');
                            a.href = '../uploads/ticket/' + encodeURIComponent(doc);
                            a.target = '_blank';
                            a.rel = 'noopener';
                            a.className = 'inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-orange-200 bg-orange-50 text-orange-700 hover:bg-orange-100 transition-colors';
                            a.innerHTML = '<i class="fas fa-file-lines" aria-hidden="true"></i><span>Doc</span>';
                            parts.push(a);
                        }
                        if (parts.length === 0) {
                            if (filesEl) filesEl.textContent = '-';
                        } else {
                            parts.forEach((p, idx) => {
                                if (idx > 0) {
                                    const spacer = document.createElement('span');
                                    spacer.className = 'inline-block w-2';
                                    spacer.textContent = '';
                                    if (filesEl) filesEl.appendChild(spacer);
                                }
                                if (filesEl) filesEl.appendChild(p);
                            });
                        }

                        const itEl = document.getElementById('viewFilesIt');
                        const itFile = (d.fileIt || '').trim();
                        if (itEl) {
                            itEl.innerHTML = '';
                            if (itFile === '') {
                                itEl.textContent = 'Menunggu update dari IT';
                            } else {
                                const a = document.createElement('a');
                                a.href = '../uploads/ticket/' + encodeURIComponent(itFile);
                                a.target = '_blank';
                                a.rel = 'noopener';
                                a.className = 'inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-orange-200 bg-orange-50 text-orange-700 hover:bg-orange-100 transition-colors';
                                a.innerHTML = '<i class="fas fa-file-arrow-down" aria-hidden="true"></i><span>File IT</span>';
                                itEl.appendChild(a);
                            }
                        }

                        openModal(viewModal);
                    });
                });

                // Edit modal populate
                document.querySelectorAll('button[data-action="editTicket"]').forEach((btn) => {
                    if (btn.dataset.boundEdit === '1') return;
                    btn.dataset.boundEdit = '1';

                    btn.addEventListener('click', () => {
                        if (btn.disabled) return;
                        const d = btn.dataset;

                        document.getElementById('editTicketCode').textContent = (d.codeDisplay || '') + (d.ticketCode ? ('  (#' + d.ticketCode + ')') : '');
                        document.getElementById('editTicketCodeValue').value = d.ticketCode || '';
                        document.getElementById('editSubject').value = d.subject || '';
                        document.getElementById('editKategori').value = d.kategori || '';
                        document.getElementById('editPriority').value = d.priority || '';
                        document.getElementById('editDeskripsi').value = d.deskripsi || '';

                        document.getElementById('editFotoInfo').textContent = d.foto ? ('Saat ini: ' + d.foto) : 'Saat ini: -';
                        document.getElementById('editDocInfo').textContent = d.doc ? ('Saat ini: ' + d.doc) : 'Saat ini: -';

                        openModal(editModal);
                    });
                });

                // Konfirmasi hapus
                document.querySelectorAll('form[data-action="deleteTicket"]').forEach((form) => {
                    if (form.dataset.boundDelete === '1') return;
                    form.dataset.boundDelete = '1';

                    form.addEventListener('submit', (e) => {
                        const btn = form.querySelector('button[type="submit"]');
                        if (btn && btn.disabled) {
                            e.preventDefault();
                            return;
                        }
                        if (!confirm('Yakin ingin menghapus ticket ini? (Hanya bisa saat status Open)')) {
                            e.preventDefault();
                        }
                    });
                });

                // Konfirmasi approval close
                document.querySelectorAll('form[data-action="approveClose"]').forEach((form) => {
                    if (form.dataset.boundApproveClose === '1') return;
                    form.dataset.boundApproveClose = '1';

                    form.addEventListener('submit', (e) => {
                        if (!confirm('Setujui penutupan ticket ini? Status akan menjadi Closed..')) {
                            e.preventDefault();
                        }
                    });
                });
            }

            // Create ticket: prevent double click + close modal on submit
            const createForm = document.getElementById('createTicketForm');
            if (createForm) {
                createForm.addEventListener('submit', () => {
                    const submitBtn = document.getElementById('createTicketSubmitBtn');
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.classList.add('opacity-75', 'cursor-not-allowed');
                    }
                    closeModal(createModal);
                });
            }

            function setActiveTab(statusValue) {
                const tabs = document.querySelectorAll('a.status-tab');
                tabs.forEach((tab) => {
                    const tabStatus = tab.dataset.status || '';
                    const isActive = tabStatus === (statusValue || '');
                    tab.className = isActive ? (tab.dataset.classActive || tab.className) : (tab.dataset.classInactive || tab.className);

                    const countEl = tab.querySelector('.tab-count');
                    if (countEl) {
                        countEl.className = isActive ? (countEl.dataset.classActive || countEl.className) : (countEl.dataset.classInactive || countEl.className);
                    }
                });
            }

            function updateTabCounts(statusCounts, totalAll) {
                const tabs = document.querySelectorAll('a.status-tab');
                tabs.forEach((tab) => {
                    const tabStatus = tab.dataset.status || '';
                    const countEl = tab.querySelector('.tab-count');
                    if (!countEl) return;
                    if (tabStatus === '') {
                        countEl.textContent = String(totalAll || 0);
                        return;
                    }
                    const v = (statusCounts && Object.prototype.hasOwnProperty.call(statusCounts, tabStatus)) ? statusCounts[tabStatus] : 0;
                    countEl.textContent = String(v || 0);
                });
            }

            function updateApprovalCloseButton(statusCounts) {
                const btn = document.getElementById('ticketApprovalCloseBtn');
                const countEl = document.getElementById('ticketApprovalCloseCount');
                if (!btn || !countEl) return;

                let doneCount = 0;
                if (statusCounts && Object.prototype.hasOwnProperty.call(statusCounts, 'Done')) {
                    doneCount = parseInt(statusCounts['Done'], 10) || 0;
                } else {
                    doneCount = parseInt(String(countEl.textContent || '0'), 10) || 0;
                }

                countEl.textContent = String(doneCount);

                if (doneCount > 0) {
                    btn.disabled = false;
                    btn.className = 'inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg text-sm font-semibold whitespace-nowrap bg-green-600 text-white hover:bg-green-700';
                    countEl.className = 'inline-flex items-center justify-center min-w-[1.5rem] h-6 px-2 rounded-full text-xs font-bold bg-white/20 text-white';
                } else {
                    btn.disabled = true;
                    btn.className = 'inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg text-sm font-semibold whitespace-nowrap bg-gray-200 text-gray-500 cursor-not-allowed';
                    countEl.className = 'inline-flex items-center justify-center min-w-[1.5rem] h-6 px-2 rounded-full text-xs font-bold bg-white text-gray-600';
                }
            }

            function updateDownloadLinkFromUrl(url) {
                const btn = document.getElementById('ticketDownloadReportBtn');
                if (!btn || !url) return;
                const params = new URLSearchParams(url.searchParams);
                params.delete('page');
                params.delete('action');

                // Only keep status + q for the report
                const reportParams = new URLSearchParams();
                const st = params.get('status');
                const q = params.get('q');
                if (st) reportParams.set('status', st);
                if (q) reportParams.set('q', q);

                const qs = reportParams.toString();
                btn.setAttribute('href', 'download_ticket_report.php' + (qs ? ('?' + qs) : ''));
            }

            // AJAX pagination (progressive enhancement)
            function showTableLoading() {
                const container = document.getElementById('tickets-table-container');
                if (!container) return;
                container.style.opacity = '0.4';
                container.style.pointerEvents = 'none';
                container.style.transition = 'opacity 0.15s ease';
                let spinner = document.getElementById('tickets-table-spinner');
                if (!spinner) {
                    spinner = document.createElement('div');
                    spinner.id = 'tickets-table-spinner';
                    spinner.style.cssText = 'position:absolute;inset:0;display:flex;align-items:center;justify-content:center;z-index:10;pointer-events:none;';
                    spinner.innerHTML = '<div style="display:flex;align-items:center;gap:10px;background:rgba(255,255,255,0.9);border-radius:10px;padding:12px 20px;box-shadow:0 2px 12px rgba(0,0,0,0.12);font-size:14px;color:#374151;font-weight:500;">'
                        + '<svg style="animation:spin 0.8s linear infinite;width:20px;height:20px;color:#ea580c;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle style="opacity:.25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path style="opacity:.75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>'
                        + 'Memuat data...</div>';
                    const wrap = container.parentElement;
                    if (wrap) {
                        const pos = window.getComputedStyle(wrap).position;
                        if (pos === 'static') wrap.style.position = 'relative';
                        wrap.appendChild(spinner);
                    }
                }
                spinner.style.display = 'flex';
            }

            function hideTableLoading() {
                const container = document.getElementById('tickets-table-container');
                if (container) {
                    container.style.opacity = '';
                    container.style.pointerEvents = '';
                }
                const spinner = document.getElementById('tickets-table-spinner');
                if (spinner) spinner.style.display = 'none';
            }

            function loadPage(pageNum, statusValue) {
                const url = new URL(window.location.href);

                // Apply current search query
                const searchInput = document.getElementById('ticketSearchInput');
                const q = searchInput ? String(searchInput.value || '').trim() : '';
                if (q) {
                    url.searchParams.set('q', q);
                } else {
                    url.searchParams.delete('q');
                }

                if (typeof statusValue !== 'undefined') {
                    if (statusValue) {
                        url.searchParams.set('status', String(statusValue));
                    } else {
                        url.searchParams.delete('status');
                    }
                    url.searchParams.delete('page');
                }
                url.searchParams.set('page', String(pageNum));

                const fetchParams = new URLSearchParams(url.searchParams);
                fetchParams.set('action', 'ajax_get_tickets');
                const fetchUrl = `${window.location.pathname}?${fetchParams.toString()}`;

                showTableLoading();

                fetch(fetchUrl)
                    .then((response) => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok: ' + response.status);
                        }
                        return response.json();
                    })
                    .then((data) => {
                        hideTableLoading();
                        if (data.error) {
                            alert('Error: ' + data.error);
                            return;
                        }

                        const tableContainer = document.getElementById('tickets-table-container');
                        const paginationContainer = document.getElementById('pagination-container');
                        if (!tableContainer || !paginationContainer) return;

                        const tableClass = 'min-w-full border border-gray-200 rounded-lg overflow-hidden';
                        tableContainer.innerHTML = '<table class="' + tableClass + '">' + (data.table_html || '') + '</table>';
                        paginationContainer.innerHTML = data.pagination_html || '';

                        // Update URL (without ajax action)
                        history.pushState({}, '', url.toString());

                        setActiveTab(url.searchParams.get('status') || '');

                        updateDownloadLinkFromUrl(url);

                        if (data.status_counts && typeof data.total_all_records !== 'undefined') {
                            updateTabCounts(data.status_counts, data.total_all_records);
                            updateApprovalCloseButton(data.status_counts);
                        }

                        attachPaginationListeners();
                        attachTicketActionListeners();

                        tableContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    })
                    .catch((err) => {
                        hideTableLoading();
                        console.error('Fetch error:', err);
                        // fallback: allow normal navigation if needed
                    });
            }


            function attachTabListeners() {
                const tabs = document.querySelectorAll('a.status-tab');
                tabs.forEach((tab) => {
                    if (tab.dataset.boundTab === '1') return;
                    tab.dataset.boundTab = '1';

                    tab.addEventListener('click', function (e) {
                        e.preventDefault();
                        const statusValue = this.dataset.status || '';
                        setActiveTab(statusValue);
                        loadPage(1, statusValue);
                    });
                });
            }

            function attachPaginationListeners() {
                const paginationContainer = document.getElementById('pagination-container');
                if (!paginationContainer) return;

                const links = paginationContainer.querySelectorAll('a.pagination-link');
                links.forEach((link) => {
                    if (link.dataset.boundPagination === '1') return;
                    link.dataset.boundPagination = '1';

                    link.addEventListener('click', function (e) {
                        e.preventDefault();
                        const href = this.getAttribute('href') || '';
                        const qPos = href.indexOf('?');
                        if (qPos < 0) return;
                        const urlParams = new URLSearchParams(href.substring(qPos + 1));
                        const p = urlParams.get('page');
                        if (p) {
                            loadPage(parseInt(p, 10));
                        }
                    });
                });
            }

            // initial bindings
            attachTicketActionListeners();
            attachPaginationListeners();
            attachTabListeners();
            setActiveTab((new URL(window.location.href)).searchParams.get('status') || '');
            updateDownloadLinkFromUrl(new URL(window.location.href));

            // Approval close notification button
            (function attachApprovalCloseButton() {
                const btn = document.getElementById('ticketApprovalCloseBtn');
                if (!btn || btn.dataset.boundApprovalBtn === '1') return;
                btn.dataset.boundApprovalBtn = '1';

                btn.addEventListener('click', () => {
                    if (btn.disabled) return;
                    setActiveTab('Done');
                    loadPage(1, 'Done');
                });
            })();

            updateApprovalCloseButton(null);

            // Expose loadPage globally so the real-time module can refresh the ticket list
            window.urtLoadPage = loadPage;

            // ===== IT TEAM STATUS BANNER (v2 — contextual personalized) =====
            (function itStatusBanner() {
                const banner = document.getElementById('it-status-banner');
                const icon   = document.getElementById('it-status-icon');
                const emoji  = document.getElementById('it-status-emoji');
                const title  = document.getElementById('it-status-title');
                const sub    = document.getElementById('it-status-sub');
                const pills  = document.getElementById('it-status-pills');
                const ping   = document.getElementById('it-live-ping');
                const dot    = document.getElementById('it-live-dot');
                if (!banner) return;

                const THEMES = {
                    mine: {
                        banner: 'bg-emerald-50 border-emerald-300',
                        icon:   'bg-emerald-100 text-emerald-700',
                        title:  'text-emerald-900',
                        sub:    'text-emerald-700',
                        dot:    'bg-emerald-500',
                        ping:   'bg-emerald-400',
                        pill:   'bg-emerald-100 text-emerald-800',
                        emoji:  '🛠️',
                    },
                    active: {
                        banner: 'bg-emerald-50 border-emerald-200',
                        icon:   'bg-emerald-100 text-emerald-700',
                        title:  'text-emerald-900',
                        sub:    'text-emerald-700',
                        dot:    'bg-emerald-500',
                        ping:   'bg-emerald-400',
                        pill:   'bg-emerald-100 text-emerald-800',
                        emoji:  '⚙️',
                    },
                    queued: {
                        banner: 'bg-amber-50 border-amber-200',
                        icon:   'bg-amber-100 text-amber-700',
                        title:  'text-amber-900',
                        sub:    'text-amber-700',
                        dot:    'bg-amber-500',
                        ping:   'bg-amber-400',
                        pill:   'bg-amber-100 text-amber-800',
                        emoji:  '📋',
                    },
                    idle: {
                        banner: 'bg-blue-50 border-blue-200',
                        icon:   'bg-blue-100 text-blue-700',
                        title:  'text-blue-900',
                        sub:    'text-blue-700',
                        dot:    'bg-blue-400',
                        ping:   'bg-blue-300',
                        pill:   'bg-blue-100 text-blue-800',
                        emoji:  '✅',
                    },
                };

                function applyTheme(level) {
                    const t = THEMES[level] || THEMES.idle;
                    Object.keys(THEMES).forEach(l => {
                        const th = THEMES[l];
                        banner.classList.remove(...th.banner.split(' '));
                        icon.classList.remove(...th.icon.split(' '));
                        title.classList.remove(...th.title.split(' '));
                        sub.classList.remove(...th.sub.split(' '));
                    });
                    banner.classList.add(...t.banner.split(' '));
                    icon.classList.add(...t.icon.split(' '));
                    title.classList.add(...t.title.split(' '));
                    sub.classList.add(...t.sub.split(' '));
                    dot.className  = 'relative inline-flex rounded-full h-2 w-2 ' + t.dot;
                    ping.className = 'animate-ping absolute inline-flex h-full w-full rounded-full opacity-75 ' + t.ping;
                    emoji.textContent = t.emoji;
                    return t;
                }

                function pill(text, cls) {
                    return `<span class="px-2.5 py-1 rounded-full text-[11px] font-semibold ${cls}">${text}</span>`;
                }

                function updateBanner(data) {
                    if (!data || !data.ok) return;
                    const level = data.status_level || 'idle';

                    if (level === 'mine') {
                        // Tiket user ini sedang dikerjakan — banner disembunyikan,
                        // badge "Sedang ditangani" di baris tiket sudah cukup informatif.
                        banner.style.display = 'none';
                        return;
                    }

                    // Untuk level selain 'mine', terapkan tema dan tampilkan banner
                    const t = applyTheme(level);

                    if (level === 'active') {
                        // ── GENERAL: ada tiket lain yang dikerjakan ──

                        title.textContent = `Tim IT sedang aktif mengerjakan ${data.active_count} tiket`;
                        const parts = [];
                        if (data.open_count > 0) parts.push(`${data.open_count} tiket menunggu`);
                        if (data.avg_response)   parts.push(`Rata-rata respons: ${data.avg_response}`);
                        sub.textContent = parts.join(' · ') || 'Tim IT siap membantu Anda';
                        let p = '';
                        if (data.active_count > 0) p += pill(`🔧 ${data.active_count} dikerjakan`, t.pill);
                        if (data.open_count > 0)   p += pill(`📋 ${data.open_count} antrian`, t.pill);
                        if (data.avg_response)     p += pill(`⏱ ${data.avg_response}`, t.pill);
                        pills.innerHTML = p;

                    } else if (level === 'queued') {
                        // ── Ada antrian tapi belum ada yang diambil admin ──
                        title.textContent = `${data.open_count} tiket sedang menunggu diproses`;
                        sub.textContent   = data.avg_response
                            ? `Rata-rata respons: ${data.avg_response} · Tim IT akan segera merespons`
                            : 'Tim IT akan segera merespons tiket Anda';
                        let p = pill(`📋 ${data.open_count} antrian`, t.pill);
                        if (data.avg_response) p += pill(`⏱ ${data.avg_response}`, t.pill);
                        pills.innerHTML = p;

                    } else {
                        // ── IDLE: tidak ada tiket aktif ──
                        title.textContent = 'Tim IT siap melayani';
                        sub.textContent   = data.avg_response
                            ? `Rata-rata respons: ${data.avg_response} · Tidak ada antrian`
                            : 'Tidak ada antrian saat ini';
                        pills.innerHTML   = data.avg_response
                            ? pill(`⏱ ${data.avg_response}`, t.pill) : '';
                    }

                    banner.style.display = '';
                    banner.style.opacity = '1';
                }

                function fetchStatus() {
                    if (document.hidden) return;
                    fetch('api_it_status.php?_=' + Date.now(), { credentials: 'same-origin' })
                        .then(r => r.ok ? r.json() : null)
                        .then(data => { if (data) updateBanner(data); })
                        .catch(() => {});
                }

                fetchStatus();
                setInterval(fetchStatus, 30000);
            })();
            // ===== END IT TEAM STATUS BANNER =====



            // Realtime search (debounced). Non-AJAX still works by submitting the form.
            (function attachSearchListeners() {
                const form = document.getElementById('ticketSearchForm');
                const input = document.getElementById('ticketSearchInput');
                if (!form || !input) return;

                let t = null;
                const trigger = () => {
                    if (t) window.clearTimeout(t);
                    t = window.setTimeout(() => {
                        loadPage(1);
                    }, 300);
                };

                input.addEventListener('input', trigger);
                form.addEventListener('submit', function (e) {
                    e.preventDefault();
                    loadPage(1);
                });
            })();

            // Kamera + GPS stamp untuk Foto Ticket (best-effort)
            const TICKET_IMAGE_TARGET_BYTES = 100 * 1024; // 100KB
            const TICKET_IMAGE_MAX_DIM = 1600; // px
            let ticketGeoWatchId = null;
            let ticketGeoLast = null;
            const ticketCameraState = { stream: null, scope: null };

            function ticketStartGeoWatch() {
                if (!navigator.geolocation) {
                    ticketGeoLast = { error: 'Geolocation tidak didukung browser.' };
                    return;
                }
                if (ticketGeoWatchId !== null) return;
                ticketGeoWatchId = navigator.geolocation.watchPosition(
                    (pos) => {
                        ticketGeoLast = {
                            lat: pos.coords.latitude,
                            lng: pos.coords.longitude,
                            acc: pos.coords.accuracy,
                            ts: pos.timestamp
                        };
                    },
                    (err) => {
                        ticketGeoLast = { error: (err && err.message) ? err.message : 'Gagal ambil lokasi.' };
                    },
                    { enableHighAccuracy: true, maximumAge: 5000, timeout: 15000 }
                );
            }

            function ticketStopGeoWatchSoon() {
                if (ticketGeoWatchId === null) return;
                window.setTimeout(() => {
                    try { navigator.geolocation.clearWatch(ticketGeoWatchId); } catch (e) { }
                    ticketGeoWatchId = null;
                }, 30000);
            }

            function ticketFormatDateTimeLocal(d) {
                const pad = (n) => String(n).padStart(2, '0');
                return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) +
                    ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
            }

            function ticketReplaceInputFile(fileInput, newFile) {
                if (!fileInput || !newFile) return;
                try {
                    const dt = new DataTransfer();
                    dt.items.add(newFile);
                    fileInput.files = dt.files;
                } catch (e) {
                    // ignore
                }
            }

            function ticketDelay(ms) {
                return new Promise((resolve) => setTimeout(resolve, ms));
            }

            async function ticketWaitGeoSnapshot(timeoutMs) {
                ticketStartGeoWatch();
                ticketStopGeoWatchSoon();
                const start = Date.now();
                while (Date.now() - start < timeoutMs) {
                    if (ticketGeoLast && (ticketGeoLast.lat || ticketGeoLast.error)) {
                        break;
                    }
                    await ticketDelay(120);
                }
                return ticketGeoLast;
            }

            async function ticketCompressImageToTarget(file, targetBytes, maxDim) {
                if (!file || !file.type || !file.type.startsWith('image/')) return file;
                if (file.type === 'image/gif') return file;

                const createBitmap = (window.createImageBitmap)
                    ? (blob) => window.createImageBitmap(blob)
                    : (blob) => new Promise((resolve, reject) => {
                        const img = new Image();
                        img.onload = () => resolve(img);
                        img.onerror = reject;
                        img.src = URL.createObjectURL(blob);
                    });

                let bitmap;
                try {
                    bitmap = await createBitmap(file);
                } catch (e) {
                    return file;
                }

                const originalWidth = bitmap.width;
                const originalHeight = bitmap.height;
                if (!originalWidth || !originalHeight) return file;

                const scale = Math.min(1, maxDim / Math.max(originalWidth, originalHeight));
                let targetW = Math.max(1, Math.round(originalWidth * scale));
                let targetH = Math.max(1, Math.round(originalHeight * scale));

                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d', { alpha: false });
                if (!ctx) return file;

                const render = (w, h) => {
                    canvas.width = w;
                    canvas.height = h;
                    ctx.fillStyle = '#ffffff';
                    ctx.fillRect(0, 0, w, h);
                    ctx.drawImage(bitmap, 0, 0, w, h);
                };

                const toJpegBlob = (quality) => new Promise((resolve) => {
                    canvas.toBlob((b) => resolve(b), 'image/jpeg', quality);
                });

                render(targetW, targetH);

                let bestBlob = null;
                let quality = 0.88;
                let attempts = 0;
                let dimAttempts = 0;
                while (attempts < 10) {
                    const blob = await toJpegBlob(quality);
                    if (!blob) break;
                    bestBlob = blob;
                    if (blob.size <= targetBytes) break;

                    if (quality > 0.55) {
                        quality = Math.max(0.55, quality - 0.07);
                    } else {
                        if (dimAttempts >= 3) break;
                        dimAttempts += 1;
                        targetW = Math.max(1, Math.round(targetW * 0.9));
                        targetH = Math.max(1, Math.round(targetH * 0.9));
                        render(targetW, targetH);
                    }
                    attempts += 1;
                }

                if (!(bitmap instanceof ImageBitmap) && bitmap && bitmap.src && bitmap.src.startsWith('blob:')) {
                    try { URL.revokeObjectURL(bitmap.src); } catch (e) { }
                }

                if (!bestBlob) return file;
                if (bestBlob.size >= file.size) return file;

                const baseName = (file.name || 'image').replace(/\.[^/.]+$/, '');
                const newName = baseName + '.jpg';
                return new File([bestBlob], newName, { type: 'image/jpeg', lastModified: Date.now() });
            }

            async function ticketStampGeoOnImage(file) {
                if (!file || !file.type || !file.type.startsWith('image/')) return file;
                if (file.type === 'image/gif') return file;

                const geo = await ticketWaitGeoSnapshot(2500);
                const nowText = ticketFormatDateTimeLocal(new Date());
                const coordsLine = (geo && geo.lat)
                    ? `Lat:${geo.lat.toFixed(6)} Lng:${geo.lng.toFixed(6)} Acc:±${Math.round(geo.acc || 0)}m | ${nowText}`
                    : `Lokasi: tidak tersedia | ${nowText}`;

                const createBitmap = (window.createImageBitmap)
                    ? (blob) => window.createImageBitmap(blob)
                    : (blob) => new Promise((resolve, reject) => {
                        const img = new Image();
                        img.onload = () => resolve(img);
                        img.onerror = reject;
                        img.src = URL.createObjectURL(blob);
                    });

                let bitmap;
                try {
                    bitmap = await createBitmap(file);
                } catch (e) {
                    return file;
                }

                const originalWidth = bitmap.width;
                const originalHeight = bitmap.height;
                if (!originalWidth || !originalHeight) return file;

                const scale = Math.min(1, TICKET_IMAGE_MAX_DIM / Math.max(originalWidth, originalHeight));
                const w = Math.max(1, Math.round(originalWidth * scale));
                const h = Math.max(1, Math.round(originalHeight * scale));

                const canvas = document.createElement('canvas');
                canvas.width = w;
                canvas.height = h;
                const ctx = canvas.getContext('2d', { alpha: false });
                if (!ctx) return file;

                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, w, h);
                ctx.drawImage(bitmap, 0, 0, w, h);

                // stamp text
                ctx.font = '14px sans-serif';
                const padding = 10;
                const boxH = 28;
                ctx.fillStyle = 'rgba(0,0,0,0.45)';
                ctx.fillRect(padding, h - boxH - padding, Math.min(w - padding * 2, 720), boxH);
                ctx.fillStyle = '#ffffff';
                ctx.fillText(coordsLine, padding + 8, h - padding - 10);

                const stampedBlob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.92));

                if (!(bitmap instanceof ImageBitmap) && bitmap && bitmap.src && bitmap.src.startsWith('blob:')) {
                    try { URL.revokeObjectURL(bitmap.src); } catch (e) { }
                }

                if (!stampedBlob) return file;
                const baseName = (file.name || 'foto_ticket').replace(/\.[^/.]+$/, '');
                const stampedFile = new File([stampedBlob], baseName + '.jpg', { type: 'image/jpeg', lastModified: Date.now() });

                // compress best-effort to <=100KB
                return await ticketCompressImageToTarget(stampedFile, TICKET_IMAGE_TARGET_BYTES, TICKET_IMAGE_MAX_DIM);
            }

            function ticketRenderGeoText(targetEl) {
                if (!targetEl) return;
                if (ticketGeoLast && ticketGeoLast.lat) {
                    targetEl.textContent = `Lat: ${ticketGeoLast.lat.toFixed(6)}, Lng: ${ticketGeoLast.lng.toFixed(6)} (±${Math.round(ticketGeoLast.acc || 0)}m)`;
                } else if (ticketGeoLast && ticketGeoLast.error) {
                    targetEl.textContent = `Lokasi tidak tersedia: ${ticketGeoLast.error}`;
                } else {
                    targetEl.textContent = 'Menunggu lokasi...';
                }
            }

            function ticketGetElsByScope(scope) {
                const box = document.getElementById('ticketCameraBox_' + scope);
                const video = document.getElementById('ticketCameraVideo_' + scope);
                const geoEl = document.getElementById('ticketCameraGeo_' + scope);
                return { box, video, geoEl };
            }

            async function ticketOpenCamera(scope) {
                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    alert('Browser tidak mendukung akses kamera.');
                    return;
                }
                // secure context check
                if (!window.isSecureContext && location.hostname !== 'localhost') {
                    alert('Akses kamera/GPS butuh HTTPS atau localhost.');
                    return;
                }

                // close other scope if open
                if (ticketCameraState.stream) {
                    await ticketCloseCamera(ticketCameraState.scope);
                }

                const { box, video, geoEl } = ticketGetElsByScope(scope);
                if (!box || !video) return;

                ticketStartGeoWatch();
                ticketStopGeoWatchSoon();
                ticketRenderGeoText(geoEl);

                try {
                    const stream = await navigator.mediaDevices.getUserMedia({
                        video: { facingMode: { ideal: 'environment' } },
                        audio: false
                    });
                    video.srcObject = stream;
                    await video.play();
                    box.classList.remove('hidden');
                    ticketCameraState.stream = stream;
                    ticketCameraState.scope = scope;
                } catch (err) {
                    alert('Kamera tidak bisa dibuka. Pastikan izin kamera diaktifkan.');
                }
            }

            async function ticketCloseCamera(scope) {
                const { box, video } = ticketGetElsByScope(scope);
                if (box) box.classList.add('hidden');
                if (video) {
                    try { video.pause(); } catch (e) { }
                    video.srcObject = null;
                }
                if (ticketCameraState.stream) {
                    try { ticketCameraState.stream.getTracks().forEach((t) => t.stop()); } catch (e) { }
                }
                ticketCameraState.stream = null;
                ticketCameraState.scope = null;
            }

            async function ticketCapture(scope, targetInputId) {
                const { video } = ticketGetElsByScope(scope);
                const input = targetInputId ? document.getElementById(targetInputId) : null;
                if (!video || !input) return;
                if (!video.videoWidth || !video.videoHeight) {
                    alert('Kamera belum siap. Tunggu sebentar.');
                    return;
                }

                // refresh a quick geo snapshot
                const geo = await ticketWaitGeoSnapshot(1500);
                const nowText = ticketFormatDateTimeLocal(new Date());
                const coordsLine = (geo && geo.lat)
                    ? `Lat:${geo.lat.toFixed(6)} Lng:${geo.lng.toFixed(6)} Acc:±${Math.round(geo.acc || 0)}m | ${nowText}`
                    : `Lokasi: tidak tersedia | ${nowText}`;

                const scale = Math.min(1, TICKET_IMAGE_MAX_DIM / Math.max(video.videoWidth, video.videoHeight));
                const w = Math.max(1, Math.round(video.videoWidth * scale));
                const h = Math.max(1, Math.round(video.videoHeight * scale));

                const canvas = document.createElement('canvas');
                canvas.width = w;
                canvas.height = h;
                const ctx = canvas.getContext('2d', { alpha: false });
                if (!ctx) return;

                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, w, h);
                ctx.drawImage(video, 0, 0, w, h);

                // stamp text
                ctx.font = '14px sans-serif';
                const padding = 10;
                const boxH = 28;
                ctx.fillStyle = 'rgba(0,0,0,0.45)';
                ctx.fillRect(padding, h - boxH - padding, Math.min(w - padding * 2, 720), boxH);
                ctx.fillStyle = '#ffffff';
                ctx.fillText(coordsLine, padding + 8, h - padding - 10);

                const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.92));
                if (!blob) {
                    alert('Gagal capture gambar.');
                    return;
                }

                const fileName = `foto_ticket_${Date.now()}.jpg`;
                let capturedFile = new File([blob], fileName, { type: 'image/jpeg', lastModified: Date.now() });
                capturedFile = await ticketCompressImageToTarget(capturedFile, TICKET_IMAGE_TARGET_BYTES, TICKET_IMAGE_MAX_DIM);

                // prevent double stamp by change handler
                input.dataset.processing = '1';
                ticketReplaceInputFile(input, capturedFile);
                input.dataset.processing = '0';

                await ticketCloseCamera(scope);
            }

            function initTicketCameraButtons() {
                document.querySelectorAll('button.js-ticket-camera-btn').forEach((btn) => {
                    if (btn.dataset.boundTicketCamBtn === '1') return;
                    btn.dataset.boundTicketCamBtn = '1';
                    btn.addEventListener('click', () => {
                        const targetId = btn.dataset.targetInput || '';
                        // map target input to scope
                        const scope = (targetId === 'Foto_Ticket_Edit') ? 'Edit' : 'Create';
                        ticketOpenCamera(scope);
                    });
                });

                document.querySelectorAll('button.js-ticket-camera-close').forEach((btn) => {
                    if (btn.dataset.boundTicketCamClose === '1') return;
                    btn.dataset.boundTicketCamClose = '1';
                    btn.addEventListener('click', () => {
                        const scope = btn.dataset.cameraScope || '';
                        if (scope) ticketCloseCamera(scope);
                    });
                });

                document.querySelectorAll('button.js-ticket-camera-capture').forEach((btn) => {
                    if (btn.dataset.boundTicketCamCap === '1') return;
                    btn.dataset.boundTicketCamCap = '1';
                    btn.addEventListener('click', async () => {
                        const scope = btn.dataset.cameraScope || '';
                        const targetId = btn.dataset.targetInput || '';
                        if (!scope || !targetId) return;
                        await ticketCapture(scope, targetId);
                    });
                });
            }

            function initTicketPhotoInputs() {
                document.querySelectorAll('input.js-ticket-photo-input').forEach((input) => {
                    if (input.dataset.boundTicketPhoto === '1') return;
                    input.dataset.boundTicketPhoto = '1';
                    input.addEventListener('change', async () => {
                        if (input.dataset.processing === '1') return;
                        const file = input.files && input.files[0] ? input.files[0] : null;
                        if (!file) return;
                        if (!file.type || !file.type.startsWith('image/')) return;
                        input.dataset.processing = '1';
                        try {
                            const stamped = await ticketStampGeoOnImage(file);
                            if (stamped && stamped !== file) {
                                ticketReplaceInputFile(input, stamped);
                            }
                        } catch (e) {
                            // ignore
                        } finally {
                            input.dataset.processing = '0';
                        }
                    });
                });
            }

            initTicketCameraButtons();
            initTicketPhotoInputs();
        });
    </script>

    <!-- ===== Real-Time Dashboard (User) ===== -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            'use strict';

            var STATS_API = 'api_ticket_stats.php';
            var STREAM_URL = 'ticket_stream.php';
            var POLL_MS = 20000;

            var _sse = null;
            var _sseOk = false;
            var _pollTimer = null;

            // --- Live indicator ---
            function setLive(ok) {
                var badge = document.getElementById('urt-live-badge');
                var dot = document.getElementById('urt-live-dot');
                if (badge) {
                    badge.style.backgroundColor = ok ? '#f0fdf4' : '#fffbeb';
                    badge.style.borderColor = ok ? '#bbf7d0' : '#fde68a';
                    badge.style.color = ok ? '#15803d' : '#b45309';
                    badge.title = ok ? 'Real-time aktif (SSE)' : 'Polling mode (20s)';
                }
                if (dot) {
                    dot.style.backgroundColor = ok ? '#22c55e' : '#fbbf24';
                }
                _sseOk = ok;
            }

            // --- Animated number counter ---
            function animateNum(el, target) {
                if (!el) return;
                var from = parseInt(el.textContent, 10) || 0;
                target = parseInt(target, 10) || 0;
                if (from === target) return;
                var dur = 650;
                var diff = target - from;
                var t0 = performance.now();
                function step(ts) {
                    var p = Math.min((ts - t0) / dur, 1);
                    var ease = 1 - Math.pow(1 - p, 3);
                    el.textContent = Math.round(from + diff * ease);
                    if (p < 1) requestAnimationFrame(step);
                    else el.textContent = target;
                }
                requestAnimationFrame(step);
            }

            // --- Ticket code formatter (mirrors PHP) ---
            function fmtCode(code, createUser) {
                var year = new Date().getFullYear().toString();
                if (createUser) {
                    var d = new Date(createUser);
                    if (!isNaN(d.getTime())) year = d.getFullYear().toString();
                }
                var pad = ('000000' + code).slice(-6);
                return 'ITCKT-' + year + '-' + pad;
            }

            // --- HTML escape ---
            function escH(s) {
                return (s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            }

            // --- Status badge class (mirrors PHP) ---
            function statusBadgeClass(s) {
                var k = (s || '').toLowerCase().trim().replace(/[-_]/g, ' ');
                if (k === 'open') return 'bg-blue-50 text-blue-700 border-blue-200';
                if (k === 'in progress') return 'bg-orange-50 text-orange-700 border-orange-200';
                if (k === 'review') return 'bg-yellow-50 text-yellow-800 border-yellow-200';
                if (k === 'done') return 'bg-green-50 text-green-700 border-green-200';
                if (k === 'reject' || k === 'rejected') return 'bg-red-50 text-red-700 border-red-200';
                if (k === 'closed') return 'bg-gray-100 text-gray-700 border-gray-200';
                return 'bg-gray-50 text-gray-700 border-gray-200';
            }

            // --- Priority badge class (mirrors PHP) ---
            function priorityBadgeClass(p) {
                var k = (p || '').toLowerCase().trim();
                if (k === 'low') return 'bg-gray-100 text-gray-700 border-gray-200';
                if (k === 'medium') return 'bg-yellow-50 text-yellow-800 border-yellow-200';
                if (k === 'high') return 'bg-orange-50 text-orange-700 border-orange-200';
                if (k === 'urgent') return 'bg-red-50 text-red-700 border-red-200';
                return 'bg-gray-50 text-gray-700 border-gray-200';
            }

            // --- Update Chart.js charts ---
            function updateCharts(data) {
                if (!data || !data.ok) return;
                var sc = data.status_counts || {};
                var pc = data.priority_counts || {};

                var statusOrder = ['Open', 'In Progress', 'Review', 'Done', 'Reject', 'Closed'];
                var statusValues = statusOrder.map(function (s) { return sc[s] || 0; });
                var priorityOrder = ['Low', 'Medium', 'High', 'Urgent'];
                var priorityValues = priorityOrder.map(function (p) { return pc[p] || 0; });

                // Status chart
                var chartSt = (typeof Chart !== 'undefined' && typeof Chart.getChart === 'function')
                    ? Chart.getChart('userTicketStatusChart') : null;
                if (chartSt && chartSt.data && chartSt.data.datasets && chartSt.data.datasets[0]) {
                    chartSt.data.datasets[0].data = statusValues;
                    chartSt.update('active');
                }

                // Priority chart
                var chartPr = (typeof Chart !== 'undefined' && typeof Chart.getChart === 'function')
                    ? Chart.getChart('userTicketPriorityChart') : null;
                if (chartPr && chartPr.data && chartPr.data.datasets && chartPr.data.datasets[0]) {
                    chartPr.data.datasets[0].data = priorityValues;
                    chartPr.update('active');
                }
            }

            // --- Apply fetched stats to DOM ---
            function applyData(data) {
                if (!data || !data.ok) return;
                var sc = data.status_counts || {};
                var pc = data.priority_counts || {};

                animateNum(document.getElementById('urt-total'), data.total || 0);
                animateNum(document.getElementById('urt-open'), sc['Open'] || 0);
                animateNum(document.getElementById('urt-inprogress'), sc['In Progress'] || 0);
                animateNum(document.getElementById('urt-done'), sc['Done'] || 0);
                animateNum(document.getElementById('urt-review'), sc['Review'] || 0);
                animateNum(document.getElementById('urt-closed'), sc['Closed'] || 0);
                animateNum(document.getElementById('urt-rejected'), sc['Reject'] || 0);

                var avgEl = document.getElementById('urt-avgtime');
                if (avgEl && data.avg_response_time && avgEl.textContent !== data.avg_response_time) {
                    avgEl.textContent = data.avg_response_time;
                }

                // Charts
                updateCharts(data);

                // Recent tickets table
                var tbody = document.getElementById('user-dash-recent-tbody');
                if (!tbody) return;
                var rows = data.recent_tickets || [];
                if (rows.length === 0) {
                    tbody.innerHTML = '<tr><td class="px-5 py-4 text-sm text-gray-600" colspan="8">Belum ada data ticket.</td></tr>';
                } else {
                    var html = '';
                    for (var i = 0; i < rows.length; i++) {
                        var rt = rows[i];
                        var code = parseInt(rt.Ticket_code, 10) || 0;
                        var cd = fmtCode(code, rt.Create_User || '');
                        var st = rt.Status_Request || '';
                        var pr = rt.Priority || '';
                        html +=
                            '<tr class="hover:bg-orange-50/40 transition-colors">' +
                            '<td class="px-5 py-4 whitespace-nowrap"><div class="text-sm font-semibold text-gray-900">' + escH(cd) + '</div><div class="text-xs text-gray-500">#' + code + '</div></td>' +
                            '<td class="px-5 py-4 whitespace-nowrap text-sm text-gray-900">' + escH(rt.Nama_User || '') + '</td>' +
                            '<td class="px-5 py-4 whitespace-nowrap text-sm text-gray-600">' + escH(rt.Divisi_User || '') + '</td>' +
                            '<td class="px-5 py-4 text-sm text-gray-900">' + escH(rt.Subject || '') + '</td>' +
                            '<td class="px-5 py-4 whitespace-nowrap text-sm text-gray-600">' + escH(rt.Kategori_Masalah || '') + '</td>' +
                            '<td class="px-5 py-4 whitespace-nowrap"><span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold border ' + priorityBadgeClass(pr) + '">' + escH(pr) + '</span></td>' +
                            '<td class="px-5 py-4 whitespace-nowrap"><span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold border ' + statusBadgeClass(st) + '">' + escH(st) + '</span></td>' +
                            '<td class="px-5 py-4 whitespace-nowrap text-sm text-gray-600">' + escH(rt.Create_User || '') + '</td>' +
                            '</tr>';
                    }
                    tbody.innerHTML = html;
                }
            }

            // --- Debounced ticket list reload ---
            var _listReloadTimer = null;
            function scheduleListReload() {
                if (_listReloadTimer) clearTimeout(_listReloadTimer);
                _listReloadTimer = setTimeout(function () {
                    _listReloadTimer = null;
                    if (typeof window.urtLoadPage === 'function') {
                        // Reload current page (preserve active tab & search)
                        var url = new URL(window.location.href);
                        var pageNum = parseInt(url.searchParams.get('page') || '1', 10) || 1;
                        window.urtLoadPage(pageNum);
                    }
                }, 600);
            }

            // --- Fetch stats from API ---
            function fetchStats() {
                fetch(STATS_API + '?_t=' + Date.now(), { credentials: 'same-origin' })
                    .then(function (r) { return r.ok ? r.json() : null; })
                    .then(function (data) {
                        if (data) {
                            applyData(data);
                            scheduleListReload();
                        }
                    })
                    .catch(function () { });
            }

            // --- Polling fallback ---
            function startPolling() {
                if (_pollTimer) clearInterval(_pollTimer);
                _pollTimer = setInterval(fetchStats, POLL_MS);
            }

            // --- SSE connection ---
            function connectSSE() {
                if (_sse) {
                    try { _sse.close(); } catch (e) { }
                    _sse = null;
                }
                if (typeof EventSource === 'undefined') {
                    setLive(false);
                    startPolling();
                    return;
                }

                _sse = new EventSource(STREAM_URL);

                _sse.addEventListener('connected', function () {
                    setLive(true);
                    if (_pollTimer) { clearInterval(_pollTimer); _pollTimer = null; }
                    fetchStats();
                });

                _sse.addEventListener('dashboard_update', function () {
                    fetchStats();
                });

                _sse.addEventListener('ping', function () {
                    // Server timed out, reconnect
                    if (_sse) { try { _sse.close(); } catch (e) { } _sse = null; }
                    setTimeout(connectSSE, 500);
                });

                _sse.onerror = function () {
                    setLive(false);
                    if (_sse) { try { _sse.close(); } catch (e) { } _sse = null; }
                    startPolling();
                    setTimeout(connectSSE, 8000);
                };
            }

            // Boot
            fetchStats();
            connectSSE();
        });
    </script>
</body>

</html>