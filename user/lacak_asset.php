<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Mulai session
session_start();
// Hosting hardening: jangan fatal jika app_url.php belum ter-upload.
$__appUrlPath = __DIR__ . '/../app_url.php';
if (is_file($__appUrlPath)) {
    require_once $__appUrlPath;
} else {
    if (!function_exists('app_base_path_from_docroot')) {
        function app_base_path_from_docroot(): string
        {
            $scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? ($_SERVER['PHP_SELF'] ?? ''));
            if ($scriptName === '')
                return '';
            $scriptWebDir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
            if ($scriptWebDir === '' || $scriptWebDir === '.' || $scriptWebDir === '/')
                $scriptWebDir = '';
            if ($scriptWebDir !== '' && preg_match('#/(user|admin)$#', $scriptWebDir)) {
                $scriptWebDir = (string) preg_replace('#/(user|admin)$#', '', $scriptWebDir);
                $scriptWebDir = rtrim($scriptWebDir, '/');
                if ($scriptWebDir === '' || $scriptWebDir === '/')
                    $scriptWebDir = '';
            }
            return $scriptWebDir;
        }
    }
    if (!function_exists('app_abs_path')) {
        function app_abs_path(string $path): string
        {
            $base = app_base_path_from_docroot();
            $p = '/' . ltrim($path, '/');
            return $base . $p;
        }
    }
    if (!function_exists('app_base_url')) {
        function app_base_url(): string
        {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
            if ($host === '')
                return app_abs_path('');
            return $scheme . '://' . $host . app_abs_path('');
        }
    }
}
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . app_abs_path('login'));
    exit();
}

// Role Check
$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : 'user';
if ($user_role !== 'user') {
    header('Location: ' . app_abs_path('dashboard_admin'));
    exit();
}

// Include koneksi database
include "../koneksi.php";

// Flash message (PRG)
$flashSuccess = null;
$flashError = null;
if (isset($_SESSION['flash_success'])) {
    $flashSuccess = (string) $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}
if (isset($_SESSION['flash_error'])) {
    $flashError = (string) $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

function lacak_asset_redirect_self(): void
{
    $path = strtok($_SERVER['REQUEST_URI'] ?? 'lacak_asset.php', '?');
    header('Location: ' . $path);
    exit();
}

// Handle change password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    try {
        $newPassword = isset($_POST['new_password']) ? (string) $_POST['new_password'] : '';
        $confirmPassword = isset($_POST['confirm_password']) ? (string) $_POST['confirm_password'] : '';

        $newPassword = trim($newPassword);
        $confirmPassword = trim($confirmPassword);

        if ($newPassword === '' || $confirmPassword === '') {
            throw new Exception('Password baru dan konfirmasi wajib diisi.');
        }
        if ($newPassword !== $confirmPassword) {
            throw new Exception('Konfirmasi password tidak sama.');
        }
        if (strlen($newPassword) < 6) {
            throw new Exception('Password minimal 6 karakter.');
        }

        $uid = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
        if ($uid <= 0) {
            throw new Exception('Session user tidak valid. Silakan login ulang.');
        }

        $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
        if ($hashed === false) {
            throw new Exception('Gagal memproses password.');
        }

        $stmtUp = $kon->prepare('UPDATE `users` SET `password` = ? WHERE `id` = ? LIMIT 1');
        if (!$stmtUp) {
            throw new Exception('Prepare gagal: ' . $kon->error);
        }
        $stmtUp->bind_param('si', $hashed, $uid);
        if (!$stmtUp->execute()) {
            throw new Exception('Update password gagal: ' . $stmtUp->error);
        }
        $stmtUp->close();

        $_SESSION['flash_success'] = 'Password berhasil diganti.';
        lacak_asset_redirect_self();
    } catch (Throwable $e) {
        $_SESSION['flash_error'] = 'Gagal ganti password. ' . $e->getMessage();
        error_log('Change password error (user/lacak_asset.php): ' . $e->getMessage());
        lacak_asset_redirect_self();
    }
}

// Handle pengajuan pinjaman aset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_pinjaman') {
    try {
        $id_aset = isset($_POST['id_aset']) ? (int) $_POST['id_aset'] : 0;
        $tgl_pinjam = isset($_POST['tgl_pinjam']) ? trim($_POST['tgl_pinjam']) : '';
        $catatan = isset($_POST['catatan']) ? trim($_POST['catatan']) : '';
        $id_karyawan = isset($_SESSION['username']) ? $_SESSION['username'] : '';

        if ($id_aset <= 0 || $tgl_pinjam === '') {
            throw new Exception('Aset dan Tanggal Pinjam wajib diisi.');
        }

        $stmtPinjam = $kon->prepare('INSERT INTO `request_pinjaman` (`id_karyawan`, `id_aset`, `tgl_pinjam`, `catatan`) VALUES (?, ?, ?, ?)');
        if (!$stmtPinjam) {
            throw new Exception('Prepare gagal: ' . $kon->error);
        }
        $stmtPinjam->bind_param('siss', $id_karyawan, $id_aset, $tgl_pinjam, $catatan);
        if (!$stmtPinjam->execute()) {
            throw new Exception('Gagal mengajukan pinjaman: ' . $stmtPinjam->error);
        }
        $stmtPinjam->close();

        // Notification: notify admin about new pinjaman request
        try {
            // Get asset name for the notification message
            $assetLabel = 'Aset #' . $id_aset;
            $stmtAssetName = $kon->prepare("SELECT CONCAT(Nama_Barang, ' ', Merek, ' ', Type) AS label FROM peserta WHERE id_peserta = ? LIMIT 1");
            if ($stmtAssetName) {
                $stmtAssetName->bind_param('i', $id_aset);
                if ($stmtAssetName->execute()) {
                    $assetRes = $stmtAssetName->get_result();
                    $assetRow = $assetRes ? $assetRes->fetch_assoc() : null;
                    if ($assetRow && !empty($assetRow['label'])) {
                        $assetLabel = trim($assetRow['label']);
                    }
                }
                $stmtAssetName->close();
            }
            $namaUser = isset($_SESSION['Nama_Lengkap']) ? $_SESSION['Nama_Lengkap'] : $id_karyawan;
            $notifTitle = 'Request Pinjaman Aset Baru';
            $notifMsg = $namaUser . ' mengajukan pinjaman: ' . $assetLabel . ' (Tgl: ' . $tgl_pinjam . ')';
            $notifType = 'pinjaman_request';
            $lastId = $kon->insert_id;
            $stmtNotif = $kon->prepare("INSERT INTO `notifications` (`target_role`, `target_user_id`, `type`, `title`, `message`, `reference_id`) VALUES ('admin', NULL, ?, ?, ?, ?)");
            if ($stmtNotif) {
                $stmtNotif->bind_param('sssi', $notifType, $notifTitle, $notifMsg, $lastId);
                @$stmtNotif->execute();
                @$stmtNotif->close();
            }
        } catch (Throwable $ne) {
            error_log('Notification insert error (user/lacak_asset.php pinjaman): ' . $ne->getMessage());
        }

        $_SESSION['flash_success'] = 'Request Pinjaman berhasil diajukan dan sedang menunggu persetujuan Admin.';
        lacak_asset_redirect_self();
    } catch (Throwable $e) {
        $_SESSION['flash_error'] = 'Gagal mengajukan pinjaman. ' . $e->getMessage();
        error_log('Request pinjaman error: ' . $e->getMessage());
        lacak_asset_redirect_self();
    }
}

// Session variables
$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'User';
$Nama_Lengkap = isset($_SESSION['Nama_Lengkap']) ? $_SESSION['Nama_Lengkap'] : $username;
$Jabatan_Level = isset($_SESSION['Jabatan_Level']) ? trim((string) $_SESSION['Jabatan_Level']) : '';

// Fallback: jika session Jabatan_Level kosong, ambil dari DB berdasarkan user_id
if ($Jabatan_Level === '' && isset($_SESSION['user_id'])) {
    $stmtMeta = $kon->prepare("SELECT Jabatan_Level, Nama_Lengkap, role FROM users WHERE id = ? LIMIT 1");
    if ($stmtMeta) {
        $uid = (int) $_SESSION['user_id'];
        $stmtMeta->bind_param('i', $uid);
        if ($stmtMeta->execute()) {
            $jabDb = null;
            $namaDb = null;
            $roleDb = null;
            $stmtMeta->bind_result($jabDb, $namaDb, $roleDb);
            if ($stmtMeta->fetch()) {
                $jab = trim((string) ($jabDb ?? ''));
                if ($jab !== '') {
                    $Jabatan_Level = $jab;
                    $_SESSION['Jabatan_Level'] = $jab;
                }

                if (empty($_SESSION['Nama_Lengkap']) && !empty($namaDb)) {
                    $_SESSION['Nama_Lengkap'] = (string) $namaDb;
                    $Nama_Lengkap = $_SESSION['Nama_Lengkap'];
                }
                if (empty($_SESSION['role']) && !empty($roleDb)) {
                    $_SESSION['role'] = (string) $roleDb;
                }
            }
        }
        $stmtMeta->close();
    } else {
        error_log('Failed to prepare user meta query (lacak_asset.php): ' . mysqli_error($kon));
    }
}

// Set timezone
date_default_timezone_set('Asia/Jakarta');

// Pagination
$limit = 12;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1)
    $page = 1;
$start_from = ($page - 1) * $limit;

// Query aset
$current_user = mysqli_real_escape_string($kon, $_SESSION['username']);
$stmt = $kon->prepare("SELECT * FROM peserta WHERE Id_Karyawan = ? ORDER BY Waktu DESC LIMIT ?, ?");
$stmt->bind_param("sii", $current_user, $start_from, $limit);
$stmt->execute();
$hasil = $stmt->get_result();

// Pagination for request history
$reqLimit = 10;
$reqPage = isset($_GET['req_page']) && is_numeric($_GET['req_page']) ? (int)$_GET['req_page'] : 1;
if ($reqPage < 1) $reqPage = 1;
$reqOffset = ($reqPage - 1) * $reqLimit;

// Count total requests for this user
$stmtReqCount = $kon->prepare("SELECT COUNT(*) as total FROM request_pinjaman WHERE id_karyawan = ?");
$stmtReqCount->bind_param("s", $current_user);
$stmtReqCount->execute();
$reqCountResult = $stmtReqCount->get_result();
$totalRequests = $reqCountResult->fetch_assoc()['total'];
$reqTotalPages = ceil($totalRequests / $reqLimit);
$stmtReqCount->close();

// Get history of requests for this user (paginated)
$stmtReq = $kon->prepare("
    SELECT r.*, p.Nama_Barang, p.Merek, p.Type, p.Serial_Number, p.Status_Barang 
    FROM request_pinjaman r 
    JOIN peserta p ON r.id_aset = p.id_peserta 
    WHERE r.id_karyawan = ? 
    ORDER BY r.created_at DESC
    LIMIT ?, ?
");
$stmtReq->bind_param("sii", $current_user, $reqOffset, $reqLimit);
$stmtReq->execute();
$reqResult = $stmtReq->get_result();
$requestList = [];
while ($rr = $reqResult->fetch_assoc()) {
    $requestList[] = $rr;
}
$stmtReq->close();

// ========== AJAX HANDLER FOR REQUEST HISTORY ==========
if (isset($_GET['action']) && $_GET['action'] === 'ajax_get_req_history') {
    header('Content-Type: application/json; charset=utf-8');

    // Build cards HTML
    $cards_html = '';
    if (count($requestList) > 0) {
        foreach ($requestList as $req) {
            $statusHtml = '';
            $reqStatus = trim($req['status'] ?? '');
            // Treat empty status as APPROVED (legacy data)
            if ($reqStatus === '') $reqStatus = 'APPROVED';

            if ($reqStatus === 'PENDING') {
                $statusHtml = '<span class="inline-block px-3 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800 border border-yellow-200"><i class="fas fa-clock mr-1"></i> Menunggu Persetujuan</span>';
            } elseif ($reqStatus === 'APPROVED') {
                $assetStatus = trim($req['Status_Barang'] ?? 'IN USE');
                if ($assetStatus === 'IN USE') {
                    $statusHtml = '<span class="inline-block px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800 border border-green-200"><i class="fas fa-check-circle mr-1"></i> Sedang Digunakan</span>';
                } else {
                    $statusHtml = '<span class="inline-block px-3 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800 border border-blue-200"><i class="fas fa-undo mr-1"></i> Sudah Dikembalikan</span>';
                }
            } elseif ($reqStatus === 'RETURNED') {
                $statusHtml = '<span class="inline-block px-3 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800 border border-blue-200"><i class="fas fa-undo mr-1"></i> Sudah Dikembalikan</span>';
            } elseif ($reqStatus === 'TRANSFERRED') {
                $statusHtml = '<span class="inline-block px-3 py-1 rounded-full text-xs font-semibold bg-purple-100 text-purple-800 border border-purple-200"><i class="fas fa-exchange-alt mr-1"></i> Ditransfer</span>';
            } elseif ($reqStatus === 'REJECTED') {
                $statusHtml = '<span class="inline-block px-3 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-800 border border-red-200"><i class="fas fa-times-circle mr-1"></i> Ditolak</span>';
                if (!empty($req['alasan_reject'])) {
                    $statusHtml .= '<p class="mt-2 text-xs text-red-600 bg-red-50 p-2 rounded border border-red-100"><strong>Alasan:</strong> ' . htmlspecialchars($req['alasan_reject']) . '</p>';
                }
            }

            $cards_html .= '<div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                <h3 class="text-base font-bold text-gray-900 truncate mb-2">' . htmlspecialchars($req['Nama_Barang'] . ' ' . $req['Merek'] . ' ' . $req['Type']) . '</h3>
                <p class="text-xs text-gray-500 mb-4">SN: ' . htmlspecialchars($req['Serial_Number']) . '</p>
                <p class="text-sm text-gray-700 mb-1"><strong>Tgl Pinjam:</strong> ' . htmlspecialchars($req['tgl_pinjam']) . '</p>
                <p class="text-sm text-gray-700 mb-3 line-clamp-2" title="' . htmlspecialchars($req['catatan']) . '"><strong>Catatan:</strong> ' . htmlspecialchars($req['catatan'] ?: '-') . '</p>
                <div class="pt-3 border-t border-gray-100 mt-auto">' . $statusHtml . '</div>
            </div>';
        }
    } else {
        $cards_html = '<div class="col-span-full text-center py-10 bg-white rounded-2xl shadow-sm border border-gray-100"><p class="text-gray-500">Belum ada riwayat pengajuan pinjaman aset.</p></div>';
    }

    // Build pagination HTML (index.php style)
    $pag_html = '';
    if ($reqTotalPages > 1) {
        $pag_html .= '<div class="mt-6 flex flex-col items-center"><div class="w-full max-w-4xl flex flex-col items-center space-y-2">';
        $pag_html .= '<div class="text-sm text-gray-600 text-center">Showing ' . min($reqOffset + 1, $totalRequests) . ' to ' . min($reqOffset + $reqLimit, $totalRequests) . ' of ' . $totalRequests . ' results</div>';
        $pag_html .= '<nav class="inline-flex rounded-md shadow-sm -space-x-px justify-center">';

        if ($reqPage > 1) {
            $pag_html .= '<a href="#" data-page="' . ($reqPage - 1) . '" class="req-pagination-link relative inline-flex items-center px-3 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50"><i class="fas fa-chevron-left"></i><span class="ml-1">Prev</span></a>';
        }
        $pStart = max(1, $reqPage - 2);
        $pEnd = min($reqTotalPages, $reqPage + 2);
        if ($pStart > 1) {
            $pag_html .= '<a href="#" data-page="1" class="req-pagination-link relative inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">1</a>';
            if ($pStart > 2) $pag_html .= '<span class="relative inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-500">...</span>';
        }
        for ($i = $pStart; $i <= $pEnd; $i++) {
            if ($i == $reqPage) {
                $pag_html .= '<span class="relative z-10 inline-flex items-center px-3 py-2 border border-orange-500 bg-orange-50 text-sm font-medium text-orange-600">' . $i . '</span>';
            } else {
                $pag_html .= '<a href="#" data-page="' . $i . '" class="req-pagination-link relative inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">' . $i . '</a>';
            }
        }
        if ($pEnd < $reqTotalPages) {
            if ($pEnd < $reqTotalPages - 1) $pag_html .= '<span class="relative inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-500">...</span>';
            $pag_html .= '<a href="#" data-page="' . $reqTotalPages . '" class="req-pagination-link relative inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">' . $reqTotalPages . '</a>';
        }
        if ($reqPage < $reqTotalPages) {
            $pag_html .= '<a href="#" data-page="' . ($reqPage + 1) . '" class="req-pagination-link relative inline-flex items-center px-3 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50"><span class="mr-1">Next</span><i class="fas fa-chevron-right"></i></a>';
        }
        $pag_html .= '</nav></div></div>';
    }

    echo json_encode([
        'cards_html' => $cards_html,
        'pagination_html' => $pag_html,
        'current_page' => $reqPage,
        'total_pages' => $reqTotalPages,
        'total_records' => (int)$totalRequests
    ]);
    exit();
}
// ========== END AJAX HANDLER ==========

// Get list of ALL assets for the dropdown
$stmtAssets = $kon->query("SELECT * FROM peserta ORDER BY Nama_Barang ASC");
$allAssets = [];
$totalReady = 0;
if ($stmtAssets) {
    while ($ra = $stmtAssets->fetch_assoc()) {
        $allAssets[] = $ra;
        if (strtoupper($ra['Status_Barang']) === 'READY') {
            $totalReady++;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ASSET IT CITRATEL</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Custom animations for loading */
        @keyframes wave {

            0%,
            100% {
                height: 1rem;
            }

            50% {
                height: 3rem;
            }
        }

        @keyframes progress {
            0% {
                width: 0%;
            }

            100% {
                width: 100%;
            }
        }

        .loading-bar {
            animation: wave 1s ease-in-out infinite;
        }

        .loading-bar:nth-child(1) {
            animation-delay: 0s;
        }

        .loading-bar:nth-child(2) {
            animation-delay: 0.1s;
        }

        .loading-bar:nth-child(3) {
            animation-delay: 0.2s;
        }

        .loading-progress-bar {
            animation: progress 2s ease-in-out forwards;
        }

        /* Modal animation */
        .cp-modal.hidden {
            display: none;
        }

        .cp-modal .cp-overlay {
            opacity: 0;
            transition: opacity 200ms ease;
        }

        .cp-modal .cp-panel {
            opacity: 0;
            transform: scale(0.95);
            transition: opacity 200ms ease, transform 200ms ease;
        }

        .cp-modal.cp-open .cp-overlay {
            opacity: 1;
        }

        .cp-modal.cp-open .cp-panel {
            opacity: 1;
            transform: scale(1);
        }
    </style>
</head>

<body class="bg-gray-50 overflow-x-hidden">

    <!-- Loading Animation -->
    <div id="loadingOverlay"
        class="fixed inset-0 bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 z-[100] flex items-center justify-center transition-opacity duration-500">
        <div class="text-center">
            <div class="mb-6 flex items-center justify-center">
                <div
                    class="w-24 h-24 bg-white rounded-full flex items-center justify-center shadow-2xl animate-pulse overflow-hidden border-4 border-orange-400">
                    <!-- Gambar logo dengan border lingkaran polos -->
                    <img src="logo_form/logo ckt fix.png" alt="Logo PT CIPTA KARYA TECHNOLOGY"
                        class="w-full h-full object-cover rounded-full">
                </div>
            </div>
            <h1 class="text-2xl md:text-3xl font-bold mb-2 text-white">PT CIPTA KARYA TECHNOLOGY</h1>
            <p class="text-gray-300 mb-6">Loading Sistem ASSET...</p>
            <div class="flex items-end justify-center space-x-2 h-16 mb-4">
                <div class="loading-bar w-2 bg-orange-400 rounded-full"></div>
                <div class="loading-bar w-2 bg-orange-400 rounded-full"></div>
                <div class="loading-bar w-2 bg-orange-400 rounded-full"></div>
            </div>
            <div class="w-64 h-2 bg-slate-700 rounded-full overflow-hidden mx-auto">
                <div class="loading-progress-bar h-full bg-gradient-to-r from-orange-400 to-orange-600 rounded-full">
                </div>
            </div>
        </div>
    </div>

    <!-- Overlay untuk mobile -->
    <div id="overlay"
        class="fixed inset-0 bg-black/60 backdrop-blur-sm z-40 lg:hidden transition-opacity duration-300 opacity-0 pointer-events-none">
    </div>

    <!-- Sidebar -->
    <aside id="sidebar"
        class="fixed top-0 left-0 h-screen w-60 bg-white border-r border-gray-200 z-50 transform -translate-x-full lg:translate-x-0 transition-transform duration-300 ease-in-out shadow-lg lg:shadow-none overflow-y-auto">
        <!-- Close button (mobile only) -->
        <button id="close-sidebar"
            class="absolute top-3 right-3 p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-all duration-200 z-10 lg:hidden">
            <i class="fas fa-times text-sm"></i>
        </button>

        <!-- Sidebar Header -->
        <div class="p-4 pt-5 border-b border-gray-200">
            <div class="flex items-center space-x-3">
                <div
                    class="w-10 h-10 bg-gradient-to-br from-orange-400 to-orange-600 rounded-xl flex items-center justify-center shadow-sm flex-shrink-0">
                    <i class="fas fa-building text-white text-sm"></i>
                </div>
                <div class="min-w-0">
                    <h2 class="text-sm font-bold text-gray-900 leading-tight truncate">Asset Management</h2>
                    <p class="text-[11px] text-gray-500 truncate">PT CIPTA KARYA TECHNOLOGY</p>
                </div>
            </div>
        </div>

        <!-- Profile - compact -->
        <div class="px-4 py-3 border-b border-gray-200">
            <div class="flex items-center space-x-3">
                <div
                    class="w-8 h-8 bg-gradient-to-br from-blue-400 to-purple-500 rounded-lg flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-user text-white text-xs"></i>
                </div>
                <div class="min-w-0">
                    <span
                        class="text-sm font-semibold text-gray-900 block truncate"><?php echo htmlspecialchars($Nama_Lengkap); ?></span>
                    <span
                        class="text-[11px] text-gray-500 truncate block"><?php echo htmlspecialchars($Jabatan_Level !== '' ? $Jabatan_Level : '-'); ?></span>
                </div>
            </div>
        </div>

        <!-- Navigation Menu -->
        <nav class="mt-2 px-3 pb-20">
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider px-3 mb-2">MENU</p>

            <a href="dashboard_user.php"
                class="flex items-center space-x-3 py-2.5 px-3 rounded-lg mb-1 transition-all duration-200 text-gray-600 hover:bg-gray-100 hover:text-gray-900">
                <i class="fas fa-tachometer-alt text-base w-5 text-center text-gray-400"></i>
                <span class="text-sm font-medium">Dashboard</span>
            </a>

            <a href="view.php"
                class="flex items-center space-x-3 py-2.5 px-3 rounded-lg mb-1 transition-all duration-200 text-gray-600 hover:bg-gray-100 hover:text-gray-900">
                <i class="fas fa-cogs text-base w-5 text-center text-gray-400"></i>
                <span class="text-sm font-medium">Asset IT</span>
            </a>

            <a href="lacak_asset.php"
                class="flex items-center space-x-3 py-2.5 px-3 rounded-lg mb-1 transition-all duration-200 bg-orange-50 text-orange-700 border-l-[3px] border-orange-500">
                <i class="fas fa-search-location text-base w-5 text-center text-orange-500"></i>
                <span class="text-sm font-semibold">Lacak Asset</span>
            </a>

            <a href="ticket.php"
                class="flex items-center space-x-3 py-2.5 px-3 rounded-lg mb-1 transition-all duration-200 text-gray-600 hover:bg-gray-100 hover:text-gray-900">
                <i class="fas fa-ticket-alt text-base w-5 text-center text-gray-400"></i>
                <span class="text-sm font-medium">Ticket</span>
            </a>

            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider px-3 mt-4 mb-2">SETTINGS</p>

            <!-- Settings Dropdown -->
            <div class="relative">
                <button id="settings-toggle"
                    class="w-full flex items-center space-x-3 py-2.5 px-3 rounded-lg mb-1 transition-all duration-200 text-gray-600 hover:bg-gray-100 hover:text-gray-900">
                    <i class="fas fa-cog text-base w-5 text-center text-gray-400"></i>
                    <span class="text-sm font-medium flex-1 text-left">Settings</span>
                    <i id="settings-arrow"
                        class="fas fa-chevron-down text-xs text-gray-400 transition-transform duration-300"></i>
                </button>

                <!-- Submenu -->
                <ul id="settings-submenu" class="overflow-hidden transition-all duration-300 ease-in-out"
                    style="max-height: 0; opacity: 0;">
                    <li>
                        <button id="btn-open-change-password" type="button"
                            class="w-full flex items-center space-x-3 py-2 px-3 pl-11 text-sm text-gray-500 hover:bg-gray-100 hover:text-gray-900 transition-all duration-200 rounded-lg">
                            <i class="fas fa-key text-xs"></i>
                            <span>Ganti Password</span>
                        </button>
                    </li>
                </ul>
            </div>

            <a href="<?php echo htmlspecialchars(app_abs_path('logout.php')); ?>"
                class="flex items-center space-x-3 py-2.5 px-3 rounded-lg mb-1 transition-all duration-200 text-red-500 hover:bg-red-50 hover:text-red-600">
                <i class="fas fa-sign-out-alt text-base w-5 text-center"></i>
                <span class="text-sm font-medium">Logout</span>
            </a>
        </nav>
    </aside>

    <!-- Navbar collapsed/expanded styles -->
    <style>
        #lacak-navbar {
            transition: width 0.3s ease, margin-left 0.3s ease;
        }

        @media (min-width: 1024px) {
            body.sidebar-collapsed #lacak-navbar {
                width: 100% !important;
                margin-left: 0 !important;
            }

            body.sidebar-collapsed #sidebar {
                transform: translateX(-100%) !important;
            }
        }
    </style>

    <!-- Navbar -->
    <nav id="lacak-navbar"
        class="bg-gradient-to-r from-orange-500 to-orange-600 shadow-lg fixed w-full lg:w-[calc(100%-15rem)] lg:ml-60 z-30 top-0">
        <div class="px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-14">
                <div class="flex items-center space-x-4">
                    <button id="hamburger-btn"
                        class="p-3 rounded-lg bg-white/20 backdrop-blur-sm text-white shadow-md hover:bg-white/30 transition-all duration-300 hover:scale-105 flex items-center justify-center min-w-[44px] min-h-[44px]">
                        <i id="hamburger-icon" class="fas fa-bars text-lg"></i>
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main id="main-content"
        class="pt-20 px-4 sm:px-6 lg:px-8 py-6 ml-0 lg:ml-60 transition-all duration-300 ease-in-out overflow-x-hidden">
        <div class="w-full mx-auto">
            <?php if ($flashSuccess): ?>
                <div class="mb-4 bg-green-50 border border-green-200 text-green-800 rounded-lg p-4">
                    <?php echo htmlspecialchars($flashSuccess); ?>
                </div>
            <?php endif; ?>
            <?php if ($flashError): ?>
                <div class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-lg p-4">
                    <?php echo htmlspecialchars($flashError); ?>
                </div>
            <?php endif; ?>

            <!-- Header -->
            <div class="mb-8 flex flex-col md:flex-row md:items-center justify-between text-center md:text-left gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 mb-2">Asset PT CIPTA KARYA TECHNOLOGY</h1>
                    <p class="text-gray-600">Daftar Asset IT dan Riwayat Pengajuan</p>
                </div>
                <div>
                    <button id="btn-ajukan-baru"
                        class="bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white px-6 py-2.5 rounded-xl font-semibold transition-all duration-300 transform hover:-translate-y-1 shadow-md">
                        <i class="fas fa-file-alt mr-2"></i>Ajukan Pinjaman Aset Baru
                    </button>
                </div>
            </div>

            <!-- Asset Grid -->
            <?php if (mysqli_num_rows($hasil) > 0): ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.5rem;">
                    <?php while ($row = mysqli_fetch_assoc($hasil)): ?>
                        <?php
                        $nama_barang = htmlspecialchars($row['Nama_Barang'] ?? '');
                        $merek = htmlspecialchars($row['Merek'] ?? '');
                        $type = htmlspecialchars($row['Type'] ?? '');
                        $jabatan = htmlspecialchars($row['Jabatan'] ?? '');
                        $photo_barang = htmlspecialchars($row['Photo_Barang'] ?? '');
                        $label = trim("$nama_barang $merek $type");
                        ?>
                        <div
                            class="bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow duration-300 border border-gray-200">
                            <h3 class="text-lg font-bold text-gray-900 truncate mb-4"><?= $label ?></h3>

                            <div class="space-y-2 mb-4">
                                <p class="text-sm text-gray-700">
                                    <strong>SN:</strong> <span
                                        class="text-gray-600"><?= htmlspecialchars($row['Serial_Number'] ?? '') ?></span>
                                </p>
                                <p class="text-sm text-gray-700">
                                    <strong>Lokasi:</strong> <span
                                        class="text-gray-600"><?= htmlspecialchars($row['Lokasi'] ?? '') ?></span>
                                </p>
                                <p class="text-sm text-gray-700">
                                    <strong>Jabatan:</strong> <span class="text-gray-600"><?= $jabatan ?></span>
                                </p>
                            </div>

                            <!-- Photo Asset -->
                            <?php if (!empty($photo_barang) && file_exists("../uploads/" . $photo_barang)): ?>
                                <div class="mt-4">
                                    <strong class="text-sm text-gray-700">Photo Asset:</strong>
                                    <img src="../uploads/<?= $photo_barang ?>" alt="Foto <?= $label ?>"
                                        class="mt-2 w-32 h-auto rounded border border-gray-300 shadow-sm">
                                </div>
                            <?php else: ?>
                                <p class="mt-4 text-sm">
                                    <strong class="text-gray-700">Photo Asset:</strong>
                                    <span class="text-gray-500">Tidak tersedia</span>
                                </p>
                            <?php endif; ?>

                            <p class="text-sm text-gray-500 mt-4 pt-4 border-t border-gray-200">
                                Digunakan oleh: <?= htmlspecialchars($row['User_Perangkat'] ?? '') ?>
                                (<?= htmlspecialchars($row['Id_Karyawan'] ?? '') ?>)
                            </p>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-20 bg-white rounded-2xl shadow-sm border border-gray-100">
                    <div class="mb-6">
                        <i class="fas fa-box-open text-6xl text-gray-300"></i>
                    </div>
                    <h2 class="text-2xl font-semibold text-gray-700 mb-4">Belum Ada Asset yang Anda Pegang</h2>
                    <p class="text-gray-500 text-lg mb-6">Anda belum memegang aset apa pun saat ini.</p>
                </div>
            <?php endif; ?>

            <!-- Riwayat Pengajuan Pinjaman -->
            <div class="mt-12 mb-6">
                <h2 class="text-xl font-bold text-gray-900 border-b pb-2">Riwayat Pengajuan Pinjaman</h2>
            </div>

            <?php if (count($requestList) > 0): ?>
                <div id="req-cards-container" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.5rem;">
                    <?php foreach ($requestList as $req): ?>
                        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                            <h3 class="text-base font-bold text-gray-900 truncate mb-2">
                                <?= htmlspecialchars($req['Nama_Barang'] . ' ' . $req['Merek'] . ' ' . $req['Type']) ?>
                            </h3>
                            <p class="text-xs text-gray-500 mb-4">SN: <?= htmlspecialchars($req['Serial_Number']) ?></p>

                            <p class="text-sm text-gray-700 mb-1">
                                <strong>Tgl Pinjam:</strong> <?= htmlspecialchars($req['tgl_pinjam']) ?>
                            </p>
                            <p class="text-sm text-gray-700 mb-3 line-clamp-2" title="<?= htmlspecialchars($req['catatan']) ?>">
                                <strong>Catatan:</strong> <?= htmlspecialchars($req['catatan'] ?: '-') ?>
                            </p>

                            <div class="pt-3 border-t border-gray-100 mt-auto">
                                <?php
                                    $reqStatus = trim($req['status'] ?? '');
                                    if ($reqStatus === '') $reqStatus = 'APPROVED';
                                ?>
                                <?php if ($reqStatus === 'PENDING'): ?>
                                    <span class="inline-block px-3 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800 border border-yellow-200">
                                        <i class="fas fa-clock mr-1"></i> Menunggu Persetujuan
                                    </span>
                                <?php elseif ($reqStatus === 'APPROVED'): ?>
                                    <?php $assetStatus = trim($req['Status_Barang'] ?? 'IN USE'); ?>
                                    <?php if ($assetStatus === 'IN USE'): ?>
                                        <span class="inline-block px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800 border border-green-200">
                                            <i class="fas fa-check-circle mr-1"></i> Sedang Digunakan
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-block px-3 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800 border border-blue-200">
                                            <i class="fas fa-undo mr-1"></i> Sudah Dikembalikan
                                        </span>
                                    <?php endif; ?>
                                <?php elseif ($reqStatus === 'RETURNED'): ?>
                                    <span class="inline-block px-3 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800 border border-blue-200">
                                        <i class="fas fa-undo mr-1"></i> Sudah Dikembalikan
                                    </span>
                                <?php elseif ($reqStatus === 'TRANSFERRED'): ?>
                                    <span class="inline-block px-3 py-1 rounded-full text-xs font-semibold bg-purple-100 text-purple-800 border border-purple-200">
                                        <i class="fas fa-exchange-alt mr-1"></i> Ditransfer
                                    </span>
                                <?php elseif ($reqStatus === 'REJECTED'): ?>
                                    <span class="inline-block px-3 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-800 border border-red-200">
                                        <i class="fas fa-times-circle mr-1"></i> Ditolak
                                    </span>
                                    <?php if (!empty($req['alasan_reject'])): ?>
                                        <p class="mt-2 text-xs text-red-600 bg-red-50 p-2 rounded border border-red-100">
                                            <strong>Alasan:</strong> <?= htmlspecialchars($req['alasan_reject']) ?>
                                        </p>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div id="req-cards-container" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.5rem;">
                    <div class="col-span-full text-center py-10 bg-white rounded-2xl shadow-sm border border-gray-100">
                        <p class="text-gray-500">Belum ada riwayat pengajuan pinjaman aset.</p>
                    </div>
                </div>
            <?php endif; ?>

                <!-- Request History Pagination (AJAX, index.php style) -->
                <div id="req-pagination-container">
                <?php if ($reqTotalPages > 1): ?>
                    <div class="mt-6 flex flex-col items-center">
                        <div class="w-full max-w-4xl flex flex-col items-center space-y-2">
                            <div class="text-sm text-gray-600 text-center">Showing <?= min($reqOffset + 1, $totalRequests) ?> to <?= min($reqOffset + $reqLimit, $totalRequests) ?> of <?= $totalRequests ?> results</div>
                            <nav class="inline-flex rounded-md shadow-sm -space-x-px justify-center">
                                <?php if ($reqPage > 1): ?>
                                    <a href="#" data-page="<?= $reqPage - 1 ?>" class="req-pagination-link relative inline-flex items-center px-3 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50"><i class="fas fa-chevron-left"></i><span class="ml-1">Prev</span></a>
                                <?php endif; ?>
                                <?php
                                $rStart = max(1, $reqPage - 2); $rEnd = min($reqTotalPages, $reqPage + 2);
                                if ($rStart > 1) {
                                    echo '<a href="#" data-page="1" class="req-pagination-link relative inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">1</a>';
                                    if ($rStart > 2) echo '<span class="relative inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-500">...</span>';
                                }
                                for ($ri = $rStart; $ri <= $rEnd; $ri++) {
                                    if ($ri == $reqPage) echo '<span class="relative z-10 inline-flex items-center px-3 py-2 border border-orange-500 bg-orange-50 text-sm font-medium text-orange-600">' . $ri . '</span>';
                                    else echo '<a href="#" data-page="' . $ri . '" class="req-pagination-link relative inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">' . $ri . '</a>';
                                }
                                if ($rEnd < $reqTotalPages) {
                                    if ($rEnd < $reqTotalPages - 1) echo '<span class="relative inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-500">...</span>';
                                    echo '<a href="#" data-page="' . $reqTotalPages . '" class="req-pagination-link relative inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">' . $reqTotalPages . '</a>';
                                }
                                ?>
                                <?php if ($reqPage < $reqTotalPages): ?>
                                    <a href="#" data-page="<?= $reqPage + 1 ?>" class="req-pagination-link relative inline-flex items-center px-3 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50"><span class="mr-1">Next</span><i class="fas fa-chevron-right"></i></a>
                                <?php endif; ?>
                            </nav>
                        </div>
                    </div>
                <?php endif; ?>
                </div>

        </div>
    </main>

    <!-- Modal Form Request Pinjaman Aset -->
    <div id="pinjaman-modal" class="cp-modal hidden fixed inset-0 z-[110] flex items-center justify-center p-4">
        <div class="cp-overlay absolute inset-0 bg-black/60 backdrop-blur-sm" data-cp-close="1"></div>
        <div
            class="cp-panel relative w-full max-w-2xl bg-white rounded-2xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">

            <div class="flex items-center justify-between px-6 py-4 border-b bg-gray-50">
                <div>
                    <h3 class="text-lg font-bold text-gray-900">Form Pengajuan Pinjaman Aset IT</h3>
                    <p class="text-sm text-gray-600">Pilih aset yang tersedia untuk dipinjam.</p>
                </div>
                <button type="button" class="text-gray-500 hover:text-gray-800 transition-colors" data-cp-close="1">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <form method="POST" class="p-6 space-y-5 overflow-y-auto">
                <input type="hidden" name="action" value="request_pinjaman" />

                <div class="bg-blue-50 border border-blue-100 rounded-xl p-4 flex items-start gap-4 mb-2">
                    <div class="bg-blue-100 p-2 rounded-lg text-blue-600">
                        <i class="fas fa-info-circle text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm text-blue-900 font-medium">Informasi Pemohon</p>
                        <p class="text-xs text-blue-700 mt-1">
                            Anda mengajukan atas nama <strong><?= htmlspecialchars($Nama_Lengkap) ?></strong>
                            (<?= htmlspecialchars($username) ?>) - Jabatan: <?= htmlspecialchars($Jabatan_Level) ?>
                        </p>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Pilih Asset <span
                            class="text-gray-500 font-normal">(Tersedia: <?= $totalReady ?> unit READY)</span> <span
                            class="text-red-500">*</span></label>
                    <div class="relative" id="custom-select-wrapper">
                        <!-- Native select hidden visually but kept for form submission & validation -->
                        <select id="select_id_aset" name="id_aset" required class="sr-only">
                            <option value="">-- Pilih Aset IT --</option>
                            <?php foreach ($allAssets as $a): ?>
                                <?php
                                $isReady = strtoupper($a['Status_Barang']) === 'READY';
                                $statusLabel = $isReady ? 'READY' : strtoupper($a['Status_Barang']);
                                ?>
                                <option value="<?= htmlspecialchars($a['id_peserta']) ?>" <?= $isReady ? '' : 'disabled' ?>
                                    data-label="<?= htmlspecialchars($a['Nama_Barang'] . ' ' . $a['Merek'] . ' ' . $a['Type'] . ' (SN: ' . $a['Serial_Number'] . ') - Status: ' . $statusLabel) ?>">
                                    <?= htmlspecialchars($a['Nama_Barang'] . ' ' . $a['Merek'] . ' ' . $a['Type'] . ' (SN: ' . $a['Serial_Number'] . ') - Status: ' . $statusLabel) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <!-- Custom Dropdown Button -->
                        <button type="button" id="custom-select-button"
                            class="w-full flex items-center justify-between bg-white pl-10 pr-3 py-2.5 rounded-xl border border-gray-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm focus:outline-none transition-all">
                            <i class="fas fa-laptop absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                            <span id="custom-select-text" class="text-gray-500 truncate flex-1 text-left">-- Pilih Aset
                                IT --</span>
                            <i class="fas fa-chevron-down text-gray-400 ml-2 transition-transform duration-200"
                                id="custom-select-icon"></i>
                        </button>

                        <!-- Dropdown Menu -->
                        <div id="custom-select-dropdown"
                            class="absolute z-50 w-full mt-1 bg-white border border-gray-200 rounded-xl shadow-lg hidden flex-col max-h-80 overflow-hidden origin-top transition duration-200">
                            <!-- Search Box -->
                            <div class="p-2 border-b border-gray-100 bg-gray-50">
                                <div class="relative">
                                    <i
                                        class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                                    <input type="text" id="custom-select-search"
                                        class="w-full pl-8 pr-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent bg-white shadow-sm"
                                        placeholder="Cari aset, type, SN, status, kelayakan...">
                                </div>
                            </div>

                            <!-- Options List -->
                            <ul id="custom-select-options" class="flex-1 overflow-y-auto py-1 max-h-60">
                                <?php foreach ($allAssets as $a): ?>
                                    <?php
                                    $isReady = strtoupper($a['Status_Barang']) === 'READY';
                                    $statusLabel = $isReady ? 'READY' : strtoupper($a['Status_Barang']);
                                    $nameBrandType = htmlspecialchars($a['Nama_Barang'] . ' ' . $a['Merek'] . ' ' . $a['Type']);
                                    $kondisi = isset($a['Status_Kelayakan_Barang']) ? $a['Status_Kelayakan_Barang'] : '';
                                    $searchStr = strtolower($a['Nama_Barang'] . ' ' . $a['Merek'] . ' ' . $a['Type'] . ' ' . $a['Serial_Number'] . ' ' . $a['Status_Barang'] . ' ' . $kondisi);
                                    ?>
                                    <li class="custom-option px-4 py-2.5 text-sm <?= $isReady ? 'hover:bg-indigo-50 cursor-pointer text-gray-700' : 'text-gray-400 cursor-not-allowed bg-gray-50' ?>"
                                        data-value="<?= htmlspecialchars($a['id_peserta']) ?>"
                                        data-search="<?= htmlspecialchars($searchStr) ?>"
                                        data-ready="<?= $isReady ? 'true' : 'false' ?>">
                                        <div class="flex flex-col">
                                            <span
                                                class="font-medium custom-option-title <?= $isReady ? 'text-gray-900' : 'text-gray-500' ?>"><?= $nameBrandType ?></span>
                                            <div class="flex items-center justify-between mt-1">
                                                <span class="text-xs text-gray-500">SN:
                                                    <?= htmlspecialchars($a['Serial_Number']) ?></span>
                                                <div class="flex items-center gap-1.5">
                                                    <?php if ($kondisi !== ''): ?>
                                                        <?php
                                                        $isLayak = (strtoupper($kondisi) === 'LAYAK' || strtoupper($kondisi) === 'LAYAK PAKAI');
                                                        $kondisiColor = $isLayak ? 'bg-blue-100 text-blue-700' : 'bg-orange-100 text-orange-700';
                                                        ?>
                                                        <span
                                                            class="text-[10px] px-2 py-0.5 rounded-md font-semibold <?= $kondisiColor ?>"><?= htmlspecialchars($kondisi) ?></span>
                                                    <?php endif; ?>
                                                    <span
                                                        class="text-[10px] px-2 py-0.5 rounded-md font-semibold <?= $isReady ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>"><?= $statusLabel ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                                <li id="custom-select-no-results"
                                    class="hidden px-4 py-3 text-sm text-gray-500 text-center">Aset tidak ditemukan.
                                </li>
                            </ul>
                        </div>
                    </div>
                    <?php if ($totalReady === 0): ?>
                        <p class="mt-1 text-xs text-red-500">Saat ini tidak ada aset yang berstatus READY di gudang.</p>
                    <?php endif; ?>

                    <!-- Container Detail Aset -->
                    <div id="asset-details-container"
                        class="hidden mt-3 bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
                        <h4 class="text-xs font-bold text-gray-800 uppercase tracking-wider mb-2 border-b pb-1">Detail
                            Spesifikasi Aset</h4>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-y-2 gap-x-4 text-sm" id="asset-details-grid">
                            <!-- JS will populate this -->
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Tanggal Rencana Pinjam <span
                            class="text-red-500">*</span></label>
                    <div class="relative">
                        <i class="fas fa-calendar-alt absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                        <input type="date" name="tgl_pinjam" required min="<?= date('Y-m-d') ?>"
                            class="w-full pl-10 pr-3 py-2.5 rounded-xl border border-gray-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Catatan Kebutuhan (Opsional)</label>
                    <div class="relative">
                        <i class="fas fa-comment-dots absolute left-3 top-3 text-gray-400"></i>
                        <textarea name="catatan" rows="3" placeholder="Contoh: Digunakan untuk project X di lapangan..."
                            class="w-full pl-10 pr-3 py-2.5 rounded-xl border border-gray-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm"></textarea>
                    </div>
                </div>

                <div class="pt-4 flex justify-end gap-3 border-t">
                    <button type="button"
                        class="px-5 py-2.5 border border-gray-300 rounded-xl text-gray-700 hover:bg-gray-50 font-medium transition"
                        data-cp-close="1">Batal</button>
                    <button type="submit"
                        class="px-5 py-2.5 bg-indigo-600 text-white rounded-xl hover:bg-indigo-700 transition font-medium flex items-center gap-2"
                        <?= $totalReady === 0 ? 'disabled title="Harap tunggu sampai ada stok"' : '' ?>>
                        <i class="fas fa-paper-plane"></i> Kirim Pengajuan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Ganti Password (Global) -->
    <?php require_once __DIR__ . '/modal_change_password.html'; ?>

    <script>
        // State management
        let mobileSidebarOpen = false;
        const STORAGE_KEY = 'sidebarCollapsed';

        // Elements
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');
        const hamburgerBtn = document.getElementById('hamburger-btn');
        const closeSidebarBtn = document.getElementById('close-sidebar');
        const mainContent = document.getElementById('main-content');
        const lacakNavbar = document.getElementById('lacak-navbar');
        const hamburgerIcon = document.getElementById('hamburger-icon');
        const settingsToggle = document.getElementById('settings-toggle');
        const settingsSubmenu = document.getElementById('settings-submenu');
        const settingsArrow = document.getElementById('settings-arrow');

        let settingsOpen = false;

        function isDesktop() { return window.innerWidth >= 1024; }

        // Apply desktop collapsed/expanded state
        function applyDesktopState() {
            var collapsed = localStorage.getItem(STORAGE_KEY) === '1';
            if (collapsed) {
                document.body.classList.add('sidebar-collapsed');
                if (mainContent) mainContent.style.marginLeft = '0';
            } else {
                document.body.classList.remove('sidebar-collapsed');
                if (mainContent) mainContent.style.marginLeft = '';
            }
            updateHamburgerIcon();
        }

        // Update hamburger icon
        function updateHamburgerIcon() {
            if (!hamburgerIcon) return;
            // Always show bars icon
            hamburgerIcon.classList.add('fa-bars');
            hamburgerIcon.classList.remove('fa-times');
        }

        // Initialize
        if (isDesktop()) {
            sidebar.classList.remove('-translate-x-full');
            sidebar.classList.add('translate-x-0');
            applyDesktopState();
        } else {
            mobileSidebarOpen = false;
            sidebar.classList.add('-translate-x-full');
            sidebar.classList.remove('translate-x-0');
            updateHamburgerIcon();
        }

        // --- Mobile sidebar ---
        function openMobileSidebar() {
            mobileSidebarOpen = true;
            sidebar.classList.remove('-translate-x-full');
            sidebar.classList.add('translate-x-0');
            if (overlay) {
                overlay.classList.remove('opacity-0', 'pointer-events-none');
                overlay.classList.add('opacity-100');
            }
            updateHamburgerIcon();
        }

        function closeMobileSidebar() {
            mobileSidebarOpen = false;
            sidebar.classList.add('-translate-x-full');
            sidebar.classList.remove('translate-x-0');
            if (overlay) {
                overlay.classList.add('opacity-0', 'pointer-events-none');
                overlay.classList.remove('opacity-100');
            }
            updateHamburgerIcon();
        }

        // --- Hamburger click: works for both mobile and desktop ---
        hamburgerBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (isDesktop()) {
                var nowCollapsed = !document.body.classList.contains('sidebar-collapsed');
                localStorage.setItem(STORAGE_KEY, nowCollapsed ? '1' : '0');
                applyDesktopState();
            } else {
                if (mobileSidebarOpen) closeMobileSidebar();
                else openMobileSidebar();
            }
        });

        // Close sidebar (mobile only)
        closeSidebarBtn.addEventListener('click', function () {
            closeMobileSidebar();
        });

        // Close sidebar when clicking overlay
        overlay.addEventListener('click', function () {
            closeMobileSidebar();
        });

        // Settings dropdown toggle
        settingsToggle.addEventListener('click', function (e) {
            e.preventDefault();
            settingsOpen = !settingsOpen;

            if (settingsOpen) {
                settingsSubmenu.style.maxHeight = '200px';
                settingsSubmenu.style.opacity = '1';
                settingsArrow.style.transform = 'rotate(180deg)';
            } else {
                settingsSubmenu.style.maxHeight = '0';
                settingsSubmenu.style.opacity = '0';
                settingsArrow.style.transform = 'rotate(0deg)';
            }
        });

        // Handle window resize
        window.addEventListener('resize', function () {
            if (isDesktop()) {
                mobileSidebarOpen = false;
                sidebar.classList.remove('-translate-x-full');
                sidebar.classList.add('translate-x-0');
                if (overlay) {
                    overlay.classList.add('opacity-0', 'pointer-events-none');
                    overlay.classList.remove('opacity-100');
                }
                applyDesktopState();
            } else {
                document.body.classList.remove('sidebar-collapsed');
                if (mainContent) mainContent.style.marginLeft = '';
                if (!mobileSidebarOpen) {
                    sidebar.classList.add('-translate-x-full');
                    sidebar.classList.remove('translate-x-0');
                }
                updateHamburgerIcon();
            }
        });

        // Custom Select Dropdown Logic
        const customSelectContainer = document.getElementById('custom-select-wrapper');
        const customSelectBtn = document.getElementById('custom-select-button');
        const customSelectText = document.getElementById('custom-select-text');
        const customSelectIcon = document.getElementById('custom-select-icon');
        const customSelectDropdown = document.getElementById('custom-select-dropdown');
        const customSelectSearch = document.getElementById('custom-select-search');
        const customSelectOptions = document.querySelectorAll('.custom-option');
        const customSelectNoResults = document.getElementById('custom-select-no-results');
        const hiddenSelectNative = document.getElementById('select_id_aset');

        let isCustomSelectOpen = false;

        function toggleCustomSelect() {
            isCustomSelectOpen = !isCustomSelectOpen;
            if (isCustomSelectOpen) {
                customSelectDropdown.classList.remove('hidden');
                setTimeout(() => {
                    customSelectSearch.focus();
                }, 10);
                customSelectIcon.classList.add('rotate-180');
                customSelectBtn.classList.add('border-indigo-500', 'ring-2', 'ring-indigo-500');
            } else {
                customSelectDropdown.classList.add('hidden');
                customSelectIcon.classList.remove('rotate-180');
                customSelectBtn.classList.remove('border-indigo-500', 'ring-2', 'ring-indigo-500');
                customSelectSearch.value = '';
                filterCustomOptions('');
            }
        }

        if (customSelectBtn) {
            customSelectBtn.addEventListener('click', toggleCustomSelect);
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function (e) {
            if (isCustomSelectOpen && customSelectContainer && !customSelectContainer.contains(e.target)) {
                toggleCustomSelect();
            }
        });

        // Search filtering
        if (customSelectSearch) {
            customSelectSearch.addEventListener('input', function (e) {
                filterCustomOptions(e.target.value);
            });
        }

        function filterCustomOptions(query) {
            const queryWords = query.toLowerCase().split(' ').filter(word => word.trim() !== '');
            let hasVisible = false;

            customSelectOptions.forEach(option => {
                const searchStr = option.getAttribute('data-search');

                // Cek apakah semua kata kunci pencarian ada di dalam string pencarian aset
                const isMatch = queryWords.every(word => searchStr.includes(word));

                if (isMatch || queryWords.length === 0) {
                    option.style.display = 'block';
                    hasVisible = true;
                } else {
                    option.style.display = 'none';
                }
            });

            if (hasVisible) {
                customSelectNoResults.classList.add('hidden');
            } else {
                customSelectNoResults.classList.remove('hidden');
            }
        }

        // Option selection
        customSelectOptions.forEach(option => {
            option.addEventListener('click', function () {
                if (this.getAttribute('data-ready') !== 'true') return;

                const value = this.getAttribute('data-value');
                // Extract just the name/brand/type for the button text
                const titleEl = this.querySelector('.custom-option-title');
                const nameBrandType = titleEl ? titleEl.textContent : this.textContent.trim();

                // Update native select
                hiddenSelectNative.value = value;

                // Update button text
                customSelectText.textContent = nameBrandType;
                customSelectText.classList.remove('text-gray-500');
                customSelectText.classList.add('text-gray-900', 'font-semibold');

                toggleCustomSelect();

                // Trigger change event for the native select so the detail view updates
                hiddenSelectNative.dispatchEvent(new Event('change'));
            });
        });

        // Dynamic Asset Details Display
        const assetsData = <?= json_encode($allAssets) ?>;
        const selectAset = document.getElementById('select_id_aset');
        const assetDetailsContainer = document.getElementById('asset-details-container');
        const assetDetailsGrid = document.getElementById('asset-details-grid');

        if (selectAset) {
            selectAset.addEventListener('change', function () {
                const selectedId = this.value;
                if (!selectedId) {
                    assetDetailsContainer.classList.add('hidden');
                    return;
                }

                const asset = assetsData.find(a => a.id_peserta == selectedId);
                if (asset) {
                    assetDetailsContainer.classList.remove('hidden');

                    // Format output
                    const textDetails = [
                        { label: 'Nomor Aset', value: asset.Nomor_Aset },
                        { label: 'Kategori', value: asset.Category },
                        { label: 'Jenis Barang', value: asset.Jenis_Barang },
                        { label: 'Nama Barang', value: asset.Nama_Barang },
                        { label: 'Merek', value: asset.Merek },
                        { label: 'Tipe', value: asset.Type },
                        { label: 'Serial Number', value: asset.Serial_Number },
                        { label: 'Spesifikasi', value: asset.Spesifikasi },
                        { label: 'Kelengkapan Barang', value: asset.Kelengkapan_Barang },
                        { label: 'Kondisi Barang', value: asset.Kondisi_Barang },
                        { label: 'Lokasi', value: asset.Lokasi },
                        { label: 'ID Karyawan', value: asset.Id_Karyawan },
                        { label: 'User Perangkat', value: asset.User_Perangkat },
                        { label: 'Jabatan', value: asset.Jabatan },
                        { label: 'Status Barang', value: asset.Status_Barang },
                        { label: 'Status LOP', value: asset.Status_LOP },
                        { label: 'Status Kelayakan', value: asset.Status_Kelayakan_Barang },
                        { label: 'Harga Barang', value: asset.Harga_Barang },
                        { label: 'Tahun Rilis', value: asset.Tahun_Rilis },
                        { label: 'Detail Pembelian', type: 'header' },
                        { label: 'Waktu Pembelian', value: asset.Waktu_Pembelian },
                        { label: 'Nama Toko', value: asset.Nama_Toko_Pembelian },
                        { label: 'Kategori Pembelian', value: asset.Kategori_Pembelian },
                        { label: 'Link Pembelian', value: asset.Link_Pembelian, isLink: true },
                        { label: 'Log System', type: 'header' },
                        { label: 'Waktu Input', value: asset.Waktu },
                        { label: 'Create By', value: asset.Create_By }
                    ];

                    const photoDetails = [
                        { label: 'Photo Barang', value: asset.Photo_Barang },
                        { label: 'Photo Depan', value: asset.Photo_Depan },
                        { label: 'Photo Belakang', value: asset.Photo_Belakang },
                        { label: 'Photo SN', value: asset.Photo_SN },
                        { label: 'Photo Invoice', value: asset.Photo_Invoice }
                    ];

                    let html = '<div class="col-span-1 sm:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-y-2 gap-x-4">';

                    textDetails.forEach(item => {
                        if (item.type === 'header') {
                            html += `</div>
                                <h5 class="text-[11px] font-bold text-gray-700 uppercase tracking-wide mt-3 mb-1 col-span-1 sm:col-span-2 border-b pb-1">${item.label}</h5>
                                <div class="col-span-1 sm:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-y-2 gap-x-4">`;
                            return;
                        }

                        if (item.value && item.value.trim() !== '') {
                            let displayValue = item.value;
                            if (item.isLink) {
                                let url = item.value.startsWith('http') ? item.value : 'http://' + item.value;
                                displayValue = `<a href="${url}" target="_blank" class="text-blue-600 hover:underline truncate block" title="${item.value}">${item.value}</a>`;
                            } else {
                                // Coba tangani line breaks atau escape tags
                                displayValue = item.value.replace(/</g, "&lt;").replace(/>/g, "&gt;");
                            }

                            html += `
                                <div class="flex flex-col mb-1 border-b border-gray-100 pb-1 overflow-hidden">
                                    <span class="text-gray-500 text-[11px]">${item.label}</span>
                                    <span class="text-gray-900 font-medium text-xs truncate" title="${item.value.replace(/"/g, '&quot;')}">${displayValue}</span>
                                </div>
                            `;
                        }
                    });
                    html += '</div>';

                    // Tambahkan grid untuk foto-foto
                    let photoHtml = '';
                    photoDetails.forEach(img => {
                        if (img.value && img.value.trim() !== '') {
                            photoHtml += `
                                <div class="flex flex-col items-center p-2 border border-gray-100 rounded-lg bg-gray-50">
                                    <span class="text-[10px] text-gray-500 mb-1">${img.label}</span>
                                    <a href="../uploads/${img.value}" target="_blank" class="block">
                                        <img src="../uploads/${img.value}" alt="${img.label}" class="w-16 h-16 object-cover rounded-md border border-gray-200 hover:scale-105 transition-transform" onerror="this.onerror=null; this.parentElement.parentElement.style.display='none'">
                                    </a>
                                </div>
                            `;
                        }
                    });

                    if (photoHtml !== '') {
                        html += `
                            <h5 class="text-[11px] font-bold text-gray-700 uppercase tracking-wide mt-4 mb-2 border-b pb-1">Dokumentasi Foto</h5>
                            <div class="grid grid-cols-3 sm:grid-cols-5 gap-3">
                                ${photoHtml}
                            </div>
                        `;
                    }

                    // === Riwayat Barang Section ===
                    let riwayatRaw = asset.Riwayat_Barang;
                    if (riwayatRaw && riwayatRaw.trim() !== '') {
                        let riwayatItems = [];
                        try {
                            riwayatItems = JSON.parse(riwayatRaw);
                            if (!Array.isArray(riwayatItems)) riwayatItems = [riwayatItems];
                        } catch (e) {
                            // Bukan JSON valid, tampilkan sebagai teks biasa
                            riwayatItems = null;
                        }

                        html += `<h5 class="text-[11px] font-bold text-gray-700 uppercase tracking-wide mt-4 mb-2 border-b pb-1">Riwayat Barang</h5>`;

                        if (riwayatItems && riwayatItems.length > 0) {
                            html += `<div class="space-y-2 max-h-48 overflow-y-auto pr-1">`;
                            riwayatItems.forEach((entry, idx) => {
                                let nama = entry.nama || entry.Nama_User || '-';
                                let jabatan = entry.jabatan || entry.Divisi_Jabatan || '-';
                                let lokasi = entry.lokasi || entry.Lokasi_User || '-';
                                let empId = entry.emploid || entry.emplid || entry.id_krywn || '';
                                let tglSerah = entry.tgl_serah_terima || entry.tgl_serah || entry.Tgl_serah || '-';
                                let tglKembali = entry.tgl_pengembalian || entry.Tgl_pengembalian || '';
                                let catatan = entry.catatan || entry.Catatan || '';
                                let aksi = entry.aksi || entry.Status || '';
                                let createdAt = entry.created_at || '';
                                let createdBy = entry.created_by || '';

                                let aksiClass = 'bg-gray-100 text-gray-700';
                                if (aksi.toUpperCase().includes('TRANSFER')) aksiClass = 'bg-blue-100 text-blue-700';
                                else if (aksi.toUpperCase().includes('IN USE')) aksiClass = 'bg-green-100 text-green-700';
                                else if (aksi.toUpperCase().includes('READY')) aksiClass = 'bg-emerald-100 text-emerald-700';
                                else if (aksi.toUpperCase().includes('RETURN') || aksi.toUpperCase().includes('KEMBALI')) aksiClass = 'bg-orange-100 text-orange-700';

                                html += `
                                <div class="bg-gray-50 border border-gray-200 rounded-lg p-3 text-xs">
                                    <div class="flex items-center justify-between mb-1.5">
                                        <span class="font-semibold text-gray-900">${idx + 1}. ${nama}</span>
                                        ${aksi ? `<span class="text-[10px] px-2 py-0.5 rounded-md font-semibold ${aksiClass}">${aksi}</span>` : ''}
                                    </div>
                                    <div class="grid grid-cols-2 gap-x-3 gap-y-1 text-[11px] text-gray-600">
                                        ${jabatan !== '-' ? `<div><i class="fas fa-briefcase text-gray-400 w-3"></i> ${jabatan}</div>` : ''}
                                        ${empId ? `<div><i class="fas fa-id-card text-gray-400 w-3"></i> ${empId}</div>` : ''}
                                        ${lokasi !== '-' ? `<div><i class="fas fa-map-marker-alt text-gray-400 w-3"></i> ${lokasi}</div>` : ''}
                                        ${tglSerah !== '-' ? `<div><i class="fas fa-calendar text-gray-400 w-3"></i> Serah: ${tglSerah}</div>` : ''}
                                        ${tglKembali ? `<div><i class="fas fa-calendar-check text-gray-400 w-3"></i> Kembali: ${tglKembali}</div>` : ''}
                                        ${createdBy ? `<div><i class="fas fa-user-edit text-gray-400 w-3"></i> Oleh: ${createdBy}</div>` : ''}
                                    </div>
                                    ${catatan ? `<div class="mt-1.5 text-[11px] text-gray-500 italic border-t border-gray-100 pt-1"><i class="fas fa-sticky-note text-gray-400 mr-1"></i>${catatan}</div>` : ''}
                                </div>`;
                            });
                            html += `</div>`;
                        } else {
                            // Fallback: tampilkan sebagai plain text yang di-wrap
                            html += `<div class="bg-gray-50 border border-gray-200 rounded-lg p-3 text-xs text-gray-700 whitespace-pre-wrap break-words max-h-48 overflow-y-auto">${riwayatRaw.replace(/</g, "&lt;").replace(/>/g, "&gt;")}</div>`;
                        }
                    }

                    assetDetailsGrid.innerHTML = html;
                } else {
                    assetDetailsContainer.classList.add('hidden');
                }
            });
        }
        // Loading animation
        window.addEventListener('load', function () {
            setTimeout(function () {
                const loadingOverlay = document.getElementById('loadingOverlay');
                loadingOverlay.style.opacity = '0';
                setTimeout(function () {
                    loadingOverlay.style.display = 'none';
                }, 500);
            }, 2000);
        });

        // Sidebar is already initialized inline above

        // Change password: tombol di sidebar sudah dihandle oleh modal_change_password.html (global)
        // Saat tombol diklik, tutup submenu settings
        const btnOpenChangePasswordLacak = document.getElementById('btn-open-change-password');
        if (btnOpenChangePasswordLacak) {
            btnOpenChangePasswordLacak.addEventListener('click', () => {
                settingsOpen = false;
                if (settingsSubmenu) { settingsSubmenu.style.maxHeight = '0'; settingsSubmenu.style.opacity = '0'; }
                if (settingsArrow) settingsArrow.style.transform = 'rotate(0deg)';
            });
        }


        const btnAjukanBaru = document.getElementById('btn-ajukan-baru');
        const pinjamanModal = document.getElementById('pinjaman-modal');

        function openPinjamanModal() {
            if (!pinjamanModal) return;
            pinjamanModal.classList.remove('hidden');
            requestAnimationFrame(() => {
                pinjamanModal.classList.add('cp-open');
            });
        }

        function closePinjamanModal() {
            if (!pinjamanModal) return;
            pinjamanModal.classList.remove('cp-open');
            setTimeout(() => {
                pinjamanModal.classList.add('hidden');
            }, 200);
        }

        btnAjukanBaru?.addEventListener('click', openPinjamanModal);

        pinjamanModal?.querySelectorAll('[data-cp-close="1"]').forEach((el) => {
            el.addEventListener('click', closePinjamanModal);
        });

        // Close modal on Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                if (changePasswordModal && !changePasswordModal.classList.contains('hidden')) {
                    closeChangePasswordModal();
                }
                if (pinjamanModal && !pinjamanModal.classList.contains('hidden')) {
                    closePinjamanModal();
                }
            }
        });

        // ========== AJAX Pagination for Request History ==========
        function bindReqPaginationLinks() {
            document.querySelectorAll('.req-pagination-link').forEach(function(link) {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    var page = this.getAttribute('data-page');
                    if (!page) return;
                    fetchReqHistoryPage(parseInt(page));
                });
            });
        }

        function fetchReqHistoryPage(page) {
            var cardsContainer = document.getElementById('req-cards-container');
            var paginationContainer = document.getElementById('req-pagination-container');

            // Show loading state
            if (cardsContainer) {
                cardsContainer.innerHTML = '<div class="col-span-full text-center py-10"><i class="fas fa-spinner fa-spin text-3xl text-orange-500"></i><p class="mt-3 text-gray-500">Memuat data...</p></div>';
            }

            var url = 'lacak_asset.php?action=ajax_get_req_history&req_page=' + page;

            fetch(url)
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    if (cardsContainer && data.cards_html) {
                        cardsContainer.innerHTML = data.cards_html;
                    }
                    if (paginationContainer && data.pagination_html !== undefined) {
                        paginationContainer.innerHTML = data.pagination_html;
                    }
                    // Re-bind pagination links after DOM update
                    bindReqPaginationLinks();

                    // Scroll to the request history section
                    var heading = document.querySelector('.mt-12.mb-6');
                    if (heading) {
                        heading.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                })
                .catch(function(err) {
                    console.error('Error fetching request history:', err);
                    if (cardsContainer) {
                        cardsContainer.innerHTML = '<div class="col-span-full text-center py-10 text-red-500"><i class="fas fa-exclamation-circle text-3xl"></i><p class="mt-3">Gagal memuat data. Silakan coba lagi.</p></div>';
                    }
                });
        }

        // Bind pagination links on initial page load
        bindReqPaginationLinks();

    </script>
</body>

</html>