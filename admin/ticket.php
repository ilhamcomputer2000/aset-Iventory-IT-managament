<?php
// Ticket page (admin)
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$user_role = isset($_SESSION['role']) ? (string) $_SESSION['role'] : 'user';
if ($user_role !== 'super_admin') {
    header('Location: ../user/ticket.php');
    exit();
}

$username = isset($_SESSION['username']) ? (string) $_SESSION['username'] : 'Admin';
$Nama_Lengkap = isset($_SESSION['Nama_Lengkap']) && (string) $_SESSION['Nama_Lengkap'] !== ''
    ? (string) $_SESSION['Nama_Lengkap']
    : $username;
$Jabatan_Level = isset($_SESSION['Jabatan_Level']) && (string) $_SESSION['Jabatan_Level'] !== ''
    ? (string) $_SESSION['Jabatan_Level']
    : '-';

require_once __DIR__ . '/../koneksi.php';

function ticket_admin_redirect_self(): void
{
    $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
    if ($uri === '' || strpos($uri, "\n") !== false || strpos($uri, "\r") !== false) {
        $uri = 'ticket.php';
    }
    header('Location: ' . $uri);
    exit();
}

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
        error_log('Failed to ensure ticket_status_history table (admin/ticket.php): ' . $kon->error);
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

function ticket_admin_format_code(int $ticketCode, ?string $createUser): string
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

function ticket_admin_status_list(): array
{
    return ['Open', 'In Progress', 'Review', 'Done', 'Reject', 'Closed'];
}

function ticket_admin_is_allowed_status(string $status): bool
{
    return in_array($status, ticket_admin_status_list(), true);
}

function ticket_admin_normalize_status(string $status): string
{
    $key = strtolower(trim($status));
    $key = str_replace(['_', '-'], ' ', $key);
    $key = preg_replace('/\s+/', ' ', $key);
    return trim((string) $key);
}

function ticket_admin_status_rank(string $status): int
{
    $k = ticket_admin_normalize_status($status);
    switch ($k) {
        case 'open':
            return 10;
        case 'reject':
        case 'rejected':
            return 20;
        case 'review':
            return 30;
        case 'in progress':
            return 40;
        case 'done':
            return 50;
        case 'closed':
            return 60;
        default:
            return 0;
    }
}

function ticket_admin_can_transition_status(string $fromStatus, string $toStatus): bool
{
    $from = ticket_admin_normalize_status($fromStatus);
    $to = ticket_admin_normalize_status($toStatus);

    if ($to === '' || $from === '') {
        return true;
    }
    if ($from === $to) {
        return true;
    }

    // Closed must be approved by user (not settable from IT/Admin)
    if ($to === 'closed') {
        return false;
    }

    // Terminal statuses
    if ($from === 'closed' || $from === 'done' || $from === 'reject' || $from === 'rejected') {
        return false;
    }

    // Never allow moving back to Open
    if ($to === 'open') {
        return false;
    }

    $fromRank = ticket_admin_status_rank($from);
    $toRank = ticket_admin_status_rank($to);
    if ($fromRank === 0 || $toRank === 0) {
        return false;
    }
    return $toRank >= $fromRank;
}

function ticket_admin_is_locked_for_it_edits(string $status): bool
{
    $k = ticket_admin_normalize_status($status);
    return in_array($k, ['done', 'closed', 'reject', 'rejected'], true);
}

function ticket_admin_badge_status_class(string $status): string
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

function ticket_admin_badge_priority_class(string $priority): string
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

function ticket_admin_is_allowed_type_pekerjaan(string $type): bool
{
    $allowed = ['Remote', 'Onsite'];
    return in_array($type, $allowed, true);
}

function ticket_admin_save_upload(array $file, string $destDir, string $prefix, array $allowedExts = []): string
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
    if ($isImageExt && function_exists('getimagesize') && function_exists('imagecreatetruecolor')) {
        $info = @getimagesize($tmp);
        if (is_array($info) && isset($info[0], $info[1], $info[2])) {
            $srcW = (int) $info[0];
            $srcH = (int) $info[1];
            $imgType = (int) $info[2];

            if ($srcW > 0 && $srcH > 0) {
                $scale = min(1.0, $maxDim / max($srcW, $srcH));
                $dstW = max(1, (int) round($srcW * $scale));
                $dstH = max(1, (int) round($srcH * $scale));

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

                    if ($imgType === IMAGETYPE_JPEG && function_exists('imagejpeg')) {
                        for ($q = 85; $q >= 50; $q -= 5) {
                            if (@imagejpeg($dstImg, $destPath, $q)) {
                                $sz = @filesize($destPath);
                                if (is_int($sz) && $sz > 0 && $sz <= $targetBytes) {
                                    $saved = true;
                                    break;
                                }
                                $saved = true;
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

function ticket_admin_column_exists(mysqli $kon, string $columnName): bool
{
    $col = $kon->real_escape_string($columnName);
    $res = $kon->query("SHOW COLUMNS FROM `ticket` LIKE '{$col}'");
    if ($res === false) {
        return false;
    }
    $exists = $res->num_rows > 0;
    $res->free();
    return $exists;
}

$hasPhotoItColumn = ticket_admin_column_exists($kon, 'Photo_IT');

// === AUTO-MIGRATE: tambah kolom assigned_to dan assigned_at jika belum ada ===
$hasAssignedColumn = ticket_admin_column_exists($kon, 'assigned_to');
if (!$hasAssignedColumn) {
    $kon->query("ALTER TABLE `ticket`
        ADD COLUMN `assigned_to`  VARCHAR(150) NULL DEFAULT NULL COMMENT 'Nama IT staff yg sedang mengerjakan',
        ADD COLUMN `assigned_at`  DATETIME     NULL DEFAULT NULL COMMENT 'Waktu admin ambil ticket'");
    $hasAssignedColumn = ($kon->errno === 0);
}

// === AJAX: assign_ticket (admin ambil / lepas ticket) ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign_ticket') {
    header('Content-Type: application/json; charset=utf-8');
    session_write_close();
    try {
        if (!$hasAssignedColumn)
            throw new Exception('Kolom assigned_to belum tersedia.');
        $ticketCode = isset($_POST['ticket_code']) ? (int) $_POST['ticket_code'] : 0;
        if ($ticketCode <= 0)
            throw new Exception('Ticket code tidak valid.');
        $mode = isset($_POST['mode']) ? trim($_POST['mode']) : 'assign'; // 'assign' or 'unassign'

        if ($mode === 'unassign') {
            $stmt = $kon->prepare('UPDATE `ticket` SET `assigned_to` = NULL, `assigned_at` = NULL WHERE `Ticket_code` = ?');
            $stmt->bind_param('i', $ticketCode);
            $stmt->execute();
            $stmt->close();
            echo json_encode(['ok' => true, 'mode' => 'unassign', 'assigned_to' => null]);
        } else {
            // assign ke admin saat ini
            $adminName = $Nama_Lengkap !== '' ? $Nama_Lengkap : $username;
            $now = date('Y-m-d H:i:s');
            $stmt = $kon->prepare('UPDATE `ticket` SET `assigned_to` = ?, `assigned_at` = ? WHERE `Ticket_code` = ?');
            $stmt->bind_param('ssi', $adminName, $now, $ticketCode);
            $stmt->execute();
            $stmt->close();
            echo json_encode(['ok' => true, 'mode' => 'assign', 'assigned_to' => $adminName]);
        }
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
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
    if ($requestedStatus !== '' && strcasecmp($requestedStatus, 'all') !== 0 && ticket_admin_is_allowed_status($requestedStatus)) {
        $statusFilter = $requestedStatus;
    }
}

// Search query (progressive enhancement: works with non-AJAX submit, realtime with AJAX)
$searchQuery = '';
if (isset($_GET['q'])) {
    $searchQuery = trim((string) $_GET['q']);
    if ($searchQuery !== '') {
        if (function_exists('mb_substr')) {
            $searchQuery = mb_substr($searchQuery, 0, 120);
        } else {
            $searchQuery = substr($searchQuery, 0, 120);
        }
    }
}
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

function ticket_admin_parse_ymd($raw): string
{
    $raw = trim((string) $raw);
    if ($raw === '') {
        return '';
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
        return '';
    }
    $y = (int) substr($raw, 0, 4);
    $m = (int) substr($raw, 5, 2);
    $d = (int) substr($raw, 8, 2);
    if (!checkdate($m, $d, $y)) {
        return '';
    }
    return sprintf('%04d-%02d-%02d', $y, $m, $d);
}

function ticket_admin_stmt_bind(mysqli_stmt $stmt, string $types, array $params): void
{
    if ($types === '') {
        return;
    }
    $bindArgs = [];
    $bindArgs[] = $types;
    foreach (array_values($params) as $i => $v) {
        $params[$i] = $v;
        $bindArgs[] = &$params[$i];
    }
    @call_user_func_array([$stmt, 'bind_param'], $bindArgs);
}

function ticket_admin_build_ticket_where(
    string $statusFilter,
    bool $hasSearch,
    string $ticketAdminSearchWhereSql,
    string $fromDateTime,
    string $toDateTime,
    string $searchLikeTicketCode,
    string $searchLikeText
): array {
    $whereParts = [];
    $types = '';
    $params = [];

    if ($statusFilter !== '') {
        $whereParts[] = '`Status_Request` = ?';
        $types .= 's';
        $params[] = $statusFilter;
    }

    if ($hasSearch) {
        $whereParts[] = $ticketAdminSearchWhereSql;
        $types .= str_repeat('s', 12);
        $params[] = $searchLikeTicketCode;
        for ($i = 0; $i < 11; $i++) {
            $params[] = $searchLikeText;
        }
    }

    if ($fromDateTime !== '') {
        $whereParts[] = '`Create_User` >= ?';
        $types .= 's';
        $params[] = $fromDateTime;
    }

    if ($toDateTime !== '') {
        $whereParts[] = '`Create_User` <= ?';
        $types .= 's';
        $params[] = $toDateTime;
    }

    $whereSql = '';
    if (!empty($whereParts)) {
        $whereSql = ' WHERE ' . implode(' AND ', $whereParts);
    }
    return [$whereSql, $types, $params];
}

// Date range filters (kalender): date_from (YYYY-MM-DD) s/d date_to (YYYY-MM-DD)
$filterDateFrom = '';
$filterDateTo = '';
$rawFrom = $_GET['date_from'] ?? ($_GET['from'] ?? '');
$rawTo = $_GET['date_to'] ?? ($_GET['to'] ?? '');
if ($rawFrom !== '') {
    $filterDateFrom = ticket_admin_parse_ymd($rawFrom);
}
if ($rawTo !== '') {
    $filterDateTo = ticket_admin_parse_ymd($rawTo);
}
if ($filterDateFrom !== '' && $filterDateTo !== '' && strcmp($filterDateFrom, $filterDateTo) > 0) {
    $tmp = $filterDateFrom;
    $filterDateFrom = $filterDateTo;
    $filterDateTo = $tmp;
}

$filterFromDateTime = $filterDateFrom !== '' ? ($filterDateFrom . ' 00:00:00') : '';
$filterToDateTime = $filterDateTo !== '' ? ($filterDateTo . ' 23:59:59') : '';
$hasDateFilter = ($filterFromDateTime !== '' || $filterToDateTime !== '');

// 12 placeholders (keep in sync with bind_param below)
$ticketAdminSearchWhereSql = '('
    . 'CAST(`Ticket_code` AS CHAR) LIKE ? '
    . 'OR `Id_Karyawan` LIKE ? '
    . 'OR `Nama_User` LIKE ? '
    . 'OR `Divisi_User` LIKE ? '
    . 'OR `Jabatan_User` LIKE ? '
    . 'OR `Region` LIKE ? '
    . 'OR `Subject` LIKE ? '
    . 'OR `Kategori_Masalah` LIKE ? '
    . 'OR `Priority` LIKE ? '
    . 'OR `Status_Request` LIKE ? '
    . 'OR `Type_Pekerjaan` LIKE ? '
    . 'OR `Deskripsi_Masalah` LIKE ?'
    . ')';

function ticket_admin_build_pagination_html(int $page, int $totalPages, int $totalRecords, int $offset, int $limit, array $baseParams = []): string
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

// Handle update status (respon ticket)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    try {
        $ticketCode = isset($_POST['Ticket_code']) ? (int) $_POST['Ticket_code'] : 0;
        $newStatus = isset($_POST['Status_Request']) ? trim((string) $_POST['Status_Request']) : '';

        if ($ticketCode <= 0) {
            throw new Exception('Ticket code tidak valid.');
        }
        if (!ticket_admin_is_allowed_status($newStatus)) {
            throw new Exception('Status tidak valid.');
        }

        // Fetch current status + IT fields for validation
        $oldStatus = '';
        $currentType = '';
        $currentJawaban = '';
        $currentPhotoIt = '';
        $photoItSelect = $hasPhotoItColumn ? ', `Photo_IT`' : '';

        $stmtGet = $kon->prepare('SELECT `Status_Request`, `Type_Pekerjaan`, `Jawaban_IT`' . $photoItSelect . ' FROM `ticket` WHERE `Ticket_code` = ? LIMIT 1');
        if (!$stmtGet) {
            throw new Exception('Prepare gagal: ' . $kon->error);
        }
        $stmtGet->bind_param('i', $ticketCode);
        if (!$stmtGet->execute()) {
            throw new Exception('Query gagal: ' . $stmtGet->error);
        }
        $resGet = $stmtGet->get_result();
        $rowGet = $resGet ? $resGet->fetch_assoc() : null;
        $stmtGet->close();
        if (!$rowGet) {
            throw new Exception('Ticket tidak ditemukan.');
        }

        $oldStatus = isset($rowGet['Status_Request']) ? (string) $rowGet['Status_Request'] : '';
        $currentType = isset($rowGet['Type_Pekerjaan']) ? trim((string) $rowGet['Type_Pekerjaan']) : '';
        $currentJawaban = isset($rowGet['Jawaban_IT']) ? trim((string) $rowGet['Jawaban_IT']) : '';
        if ($hasPhotoItColumn && isset($rowGet['Photo_IT'])) {
            $currentPhotoIt = trim((string) $rowGet['Photo_IT']);
        }

        // Closed must be approved by user
        if (ticket_admin_normalize_status($newStatus) === 'closed') {
            throw new Exception('Status Closed hanya bisa disetujui oleh User (approval close).');
        }

        // Prevent backward status changes (anti-manipulation)
        if (!ticket_admin_can_transition_status($oldStatus, $newStatus)) {
            throw new Exception('Status tidak boleh diubah mundur/kembali untuk mencegah manipulasi audit.');
        }

        // When marking Done, IT must fill all IT inputs
        if (strcasecmp($newStatus, 'Done') === 0) {
            if ($currentType === '' || !ticket_admin_is_allowed_type_pekerjaan($currentType)) {
                throw new Exception('Gagal update: untuk status Done, wajib isi Type Pekerjaan (Remote/Onsite).');
            }
            if ($currentJawaban === '') {
                throw new Exception('Gagal update: untuk status Done, wajib isi Respon IT terlebih dahulu.');
            }
            if ($hasPhotoItColumn && $currentPhotoIt === '') {
                throw new Exception('Gagal update: untuk status Done, wajib upload File IT terlebih dahulu.');
            }
        }

        $stmtUp = $kon->prepare('UPDATE `ticket` SET `Status_Request` = ? WHERE `Ticket_code` = ?');
        if (!$stmtUp) {
            throw new Exception('Prepare update gagal: ' . $kon->error);
        }
        $stmtUp->bind_param('si', $newStatus, $ticketCode);
        if (!$stmtUp->execute()) {
            throw new Exception('Update status gagal: ' . $stmtUp->error);
        }
        $stmtUp->close();

        if ($oldStatus !== $newStatus) {
            ticket_audit_insert($kon, $ticketCode, $oldStatus, $newStatus, $Nama_Lengkap, $user_role, null);

            // Notification: notify ticket owner about status change
            try {
                $stmtOwner = $kon->prepare("SELECT u.id, t.Nama_User, t.Subject FROM `ticket` t LEFT JOIN `users` u ON t.Create_By_User = u.username WHERE t.Ticket_code = ? LIMIT 1");
                if ($stmtOwner) {
                    $stmtOwner->bind_param('i', $ticketCode);
                    if ($stmtOwner->execute()) {
                        $ownerRes = $stmtOwner->get_result();
                        $ownerRow = $ownerRes ? $ownerRes->fetch_assoc() : null;
                        if ($ownerRow && !empty($ownerRow['id'])) {
                            $targetUserId = (int) $ownerRow['id'];
                            $ticketSubject = $ownerRow['Subject'] ?? '#' . $ticketCode;

                            // Special notification for Done status - needs user approval to close
                            if (strcasecmp($newStatus, 'Done') === 0) {
                                $notifTitle = 'Approval Closed Diperlukan';
                                $notifMsg = 'Ticket "' . $ticketSubject . '" sudah Done. Silakan approval closed ticket Anda.';
                                $notifType = 'ticket_approval_needed';
                            } else {
                                $notifTitle = 'Status Ticket Diubah';
                                $notifMsg = 'Ticket "' . $ticketSubject . '" berubah dari ' . $oldStatus . ' → ' . $newStatus;
                                $notifType = 'ticket_status_changed';
                            }

                            $stmtNotif = $kon->prepare("INSERT INTO `notifications` (`target_role`, `target_user_id`, `type`, `title`, `message`, `reference_id`) VALUES ('user', ?, ?, ?, ?, ?)");
                            if ($stmtNotif) {
                                $stmtNotif->bind_param('isssi', $targetUserId, $notifType, $notifTitle, $notifMsg, $ticketCode);
                                @$stmtNotif->execute();
                                @$stmtNotif->close();
                            }
                        }
                    }
                    $stmtOwner->close();
                }
            } catch (Throwable $ne) {
                error_log('Notification insert error (admin/ticket.php): ' . $ne->getMessage());
            }
        }

        $_SESSION['flash_success'] = 'Status ticket berhasil diupdate.';
        ticket_admin_redirect_self();
    } catch (Throwable $e) {
        $_SESSION['flash_error'] = 'Gagal update status. ' . $e->getMessage();
        error_log('Update status ticket error (admin/ticket.php): ' . $e->getMessage());
        ticket_admin_redirect_self();
    }
}

// Handle update type pekerjaan (Remote/Onsite)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_type_pekerjaan') {
    try {
        $ticketCode = isset($_POST['Ticket_code']) ? (int) $_POST['Ticket_code'] : 0;
        $newType = isset($_POST['Type_Pekerjaan']) ? trim((string) $_POST['Type_Pekerjaan']) : '';

        if ($ticketCode <= 0) {
            throw new Exception('Ticket code tidak valid.');
        }
        if (!ticket_admin_is_allowed_type_pekerjaan($newType)) {
            throw new Exception('Type pekerjaan tidak valid.');
        }

        // Lock edits after final statuses
        $stmtLock = $kon->prepare('SELECT `Status_Request` FROM `ticket` WHERE `Ticket_code` = ? LIMIT 1');
        if ($stmtLock) {
            $stmtLock->bind_param('i', $ticketCode);
            if ($stmtLock->execute()) {
                $resLock = $stmtLock->get_result();
                $rowLock = $resLock ? $resLock->fetch_assoc() : null;
                $curStatus = $rowLock && isset($rowLock['Status_Request']) ? (string) $rowLock['Status_Request'] : '';
                if ($curStatus !== '' && ticket_admin_is_locked_for_it_edits($curStatus)) {
                    $stmtLock->close();
                    throw new Exception('Tidak bisa mengubah Type Pekerjaan setelah status Done/Closed/Reject.');
                }
            }
            $stmtLock->close();
        }

        $stmtUp = $kon->prepare('UPDATE `ticket` SET `Type_Pekerjaan` = ? WHERE `Ticket_code` = ?');
        if (!$stmtUp) {
            throw new Exception('Prepare update gagal: ' . $kon->error);
        }
        $stmtUp->bind_param('si', $newType, $ticketCode);
        if (!$stmtUp->execute()) {
            throw new Exception('Update type pekerjaan gagal: ' . $stmtUp->error);
        }
        $stmtUp->close();

        $_SESSION['flash_success'] = 'Type pekerjaan berhasil diupdate.';
        ticket_admin_redirect_self();
    } catch (Throwable $e) {
        $_SESSION['flash_error'] = 'Gagal update type pekerjaan. ' . $e->getMessage();
        error_log('Update type pekerjaan error (admin/ticket.php): ' . $e->getMessage());
        ticket_admin_redirect_self();
    }
}

// Handle update jawaban IT (Jawaban_IT)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_jawaban_it') {
    try {
        $ticketCode = isset($_POST['Ticket_code']) ? (int) $_POST['Ticket_code'] : 0;
        $jawabanIt = isset($_POST['Jawaban_IT']) ? trim((string) $_POST['Jawaban_IT']) : '';

        if ($ticketCode <= 0) {
            throw new Exception('Ticket code tidak valid.');
        }

        // Lock edits after final statuses
        $stmtLock = $kon->prepare('SELECT `Status_Request` FROM `ticket` WHERE `Ticket_code` = ? LIMIT 1');
        if ($stmtLock) {
            $stmtLock->bind_param('i', $ticketCode);
            if ($stmtLock->execute()) {
                $resLock = $stmtLock->get_result();
                $rowLock = $resLock ? $resLock->fetch_assoc() : null;
                $curStatus = $rowLock && isset($rowLock['Status_Request']) ? (string) $rowLock['Status_Request'] : '';
                if ($curStatus !== '' && ticket_admin_is_locked_for_it_edits($curStatus)) {
                    $stmtLock->close();
                    throw new Exception('Tidak bisa mengubah Respon IT setelah status Done/Closed/Reject.');
                }
            }
            $stmtLock->close();
        }

        $stmtUp = $kon->prepare('UPDATE `ticket` SET `Jawaban_IT` = ? WHERE `Ticket_code` = ?');
        if (!$stmtUp) {
            throw new Exception('Prepare update gagal: ' . $kon->error);
        }
        $stmtUp->bind_param('si', $jawabanIt, $ticketCode);
        if (!$stmtUp->execute()) {
            throw new Exception('Update jawaban IT gagal: ' . $stmtUp->error);
        }
        $stmtUp->close();

        $_SESSION['flash_success'] = 'Jawaban IT berhasil disimpan.';
        ticket_admin_redirect_self();
    } catch (Throwable $e) {
        $_SESSION['flash_error'] = 'Gagal simpan jawaban IT. ' . $e->getMessage();
        error_log('Update Jawaban_IT error (admin/ticket.php): ' . $e->getMessage());
        ticket_admin_redirect_self();
    }
}

// Handle upload File IT (Photo_IT)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_file_it') {
    try {
        if (!$hasPhotoItColumn) {
            throw new Exception('Kolom Photo_IT belum tersedia di tabel ticket.');
        }

        $ticketCode = isset($_POST['Ticket_code']) ? (int) $_POST['Ticket_code'] : 0;
        if ($ticketCode <= 0) {
            throw new Exception('Ticket code tidak valid.');
        }

        // Lock edits after final statuses
        $stmtLock = $kon->prepare('SELECT `Status_Request` FROM `ticket` WHERE `Ticket_code` = ? LIMIT 1');
        if ($stmtLock) {
            $stmtLock->bind_param('i', $ticketCode);
            if ($stmtLock->execute()) {
                $resLock = $stmtLock->get_result();
                $rowLock = $resLock ? $resLock->fetch_assoc() : null;
                $curStatus = $rowLock && isset($rowLock['Status_Request']) ? (string) $rowLock['Status_Request'] : '';
                if ($curStatus !== '' && ticket_admin_is_locked_for_it_edits($curStatus)) {
                    $stmtLock->close();
                    throw new Exception('Tidak bisa upload File IT setelah status Done/Closed/Reject.');
                }
            }
            $stmtLock->close();
        }

        $uploadDir = __DIR__ . '/../uploads/ticket';
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx'];
        $fileName = '';
        if (isset($_FILES['Photo_IT'])) {
            $fileName = ticket_admin_save_upload($_FILES['Photo_IT'], $uploadDir, 'it_' . $ticketCode, $allowed);
        }
        if ($fileName === '') {
            throw new Exception('Tidak ada file yang diupload.');
        }

        $stmtUp = $kon->prepare('UPDATE `ticket` SET `Photo_IT` = ? WHERE `Ticket_code` = ?');
        if (!$stmtUp) {
            throw new Exception('Prepare update gagal: ' . $kon->error);
        }
        $stmtUp->bind_param('si', $fileName, $ticketCode);
        if (!$stmtUp->execute()) {
            throw new Exception('Update file IT gagal: ' . $stmtUp->error);
        }
        $stmtUp->close();

        $_SESSION['flash_success'] = 'File IT berhasil diupload.';
        ticket_admin_redirect_self();
    } catch (Throwable $e) {
        $_SESSION['flash_error'] = 'Gagal upload File IT. ' . $e->getMessage();
        error_log('Upload Photo_IT error (admin/ticket.php): ' . $e->getMessage());
        ticket_admin_redirect_self();
    }
}

$tickets = [];
$ticketQueryError = null;
$totalRecords = 0;
$totalPages = 1;
$paginationHtml = '';

// Defaults for tab rendering (avoid undefined vars if query fails)
$statusCounts = array_fill_keys(ticket_admin_status_list(), 0);
$totalAllRecords = 0;
$tabBaseParams = [];

// Handle AJAX request untuk pagination (template: user/dashboard_user.php)
if (isset($_GET['action']) && $_GET['action'] === 'ajax_get_admin_tickets') {
    header('Content-Type: application/json; charset=utf-8');
    // Release session lock so concurrent requests (SSE/polling) don't block us
    session_write_close();

    try {
        // Build base params (preserve other query params except action)
        $baseParams = $_GET;
        unset($baseParams['action']);
        unset($baseParams['page']);

        // Counts per status for tabs (include search + date range if any)
        $ajaxStatusCounts = array_fill_keys(ticket_admin_status_list(), 0);
        $ajaxTotalAllRecords = 0;
        [$whereTabs, $typesTabs, $paramsTabs] = ticket_admin_build_ticket_where(
            '',
            $hasSearch,
            $ticketAdminSearchWhereSql,
            $filterFromDateTime,
            $filterToDateTime,
            $searchLikeTicketCode,
            $searchLikeText
        );
        $stmtStatusCounts = $kon->prepare('SELECT `Status_Request`, COUNT(*) AS total FROM `ticket`' . $whereTabs . ' GROUP BY `Status_Request`');
        if ($stmtStatusCounts) {
            ticket_admin_stmt_bind($stmtStatusCounts, $typesTabs, $paramsTabs);
            if ($stmtStatusCounts->execute()) {
                $resStatus = $stmtStatusCounts->get_result();
                if ($resStatus) {
                    while ($r = $resStatus->fetch_assoc()) {
                        $st = isset($r['Status_Request']) ? (string) $r['Status_Request'] : '';
                        $cnt = (int) ($r['total'] ?? 0);
                        $ajaxTotalAllRecords += $cnt;
                        if ($st !== '' && ticket_admin_is_allowed_status($st)) {
                            $ajaxStatusCounts[$st] = $cnt;
                        }
                    }
                }
            }
            $stmtStatusCounts->close();
        }

        // Count (with optional status filter, plus search + date)
        [$whereCount, $typesCount, $paramsCount] = ticket_admin_build_ticket_where(
            $statusFilter,
            $hasSearch,
            $ticketAdminSearchWhereSql,
            $filterFromDateTime,
            $filterToDateTime,
            $searchLikeTicketCode,
            $searchLikeText
        );
        $stmtCount = $kon->prepare('SELECT COUNT(*) AS total FROM `ticket`' . $whereCount);
        if (!$stmtCount) {
            throw new Exception('Prepare count gagal: ' . $kon->error);
        }

        ticket_admin_stmt_bind($stmtCount, $typesCount, $paramsCount);
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
        $photoItSelect = $hasPhotoItColumn ? ', `Photo_IT`' : '';
        $assignedSelect = $hasAssignedColumn ? ', `assigned_to`, `assigned_at`' : '';
        $selectCols = '`No`, `Ticket_code`, `Id_Karyawan`, `Nama_User`, `Divisi_User`, `Jabatan_User`, `Region`, `Subject`, `Kategori_Masalah`, `Priority`, `Status_Request`, `Type_Pekerjaan`, `Create_User`, `Create_By_User`, `Deskripsi_Masalah`, `Foto_Ticket`, `Document`, `Jawaban_IT`' . $photoItSelect . $assignedSelect;

        [$whereList, $typesListBase, $paramsListBase] = ticket_admin_build_ticket_where(
            $statusFilter,
            $hasSearch,
            $ticketAdminSearchWhereSql,
            $filterFromDateTime,
            $filterToDateTime,
            $searchLikeTicketCode,
            $searchLikeText
        );
        $stmtList = $kon->prepare('SELECT ' . $selectCols . ' FROM `ticket`' . $whereList . ' ORDER BY `Ticket_code` DESC LIMIT ? OFFSET ?');
        if (!$stmtList) {
            throw new Exception('Prepare list gagal: ' . $kon->error);
        }

        $typesList = $typesListBase . 'ii';
        $paramsList = $paramsListBase;
        $paramsList[] = $limit;
        $paramsList[] = $offset;
        ticket_admin_stmt_bind($stmtList, $typesList, $paramsList);
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
            . ($hasAssignedColumn ? '<th class="text-left text-xs font-semibold text-orange-600 uppercase tracking-wider px-3 py-3 border-b">Kerjakan</th>' : '')
            . '<th class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider px-4 py-3 border-b">Ticket Code</th>'
            . '<th class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider px-4 py-3 border-b">Created</th>'
            . '<th class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider px-4 py-3 border-b">ID Karyawan</th>'
            . '<th class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider px-4 py-3 border-b">Nama User</th>'
            . '<th class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider px-4 py-3 border-b">Divisi</th>'
            . '<th class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider px-4 py-3 border-b">Region</th>'
            . '<th class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider px-4 py-3 border-b">Subject</th>'
            . '<th class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider px-4 py-3 border-b">Kategori</th>'
            . '<th class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider px-4 py-3 border-b">Priority</th>'
            . '<th class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider px-4 py-3 border-b">Status</th>'
            . '<th class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider px-4 py-3 border-b">Type Pekerjaan</th>'
            . '<th class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider px-4 py-3 border-b">Deskripsi</th>'
            . '<th class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider px-4 py-3 border-b">File</th>'
            . '<th class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider px-4 py-3 border-b">File IT</th>'
            . '<th class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider px-4 py-3 border-b">Jawaban IT</th>'
            . '<th class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider px-4 py-3 border-b">Respon</th>'
            . '</tr>'
            . '</thead>'
            . '<tbody class="bg-white divide-y divide-gray-100">';

        $rowNo = $offset;
        foreach ($tickets as $t) {
            $rowNo++;
            $codeInt = (int) ($t['Ticket_code'] ?? 0);
            $codeDisplay = ticket_admin_format_code($codeInt, isset($t['Create_User']) ? (string) $t['Create_User'] : null);
            $hasFoto = isset($t['Foto_Ticket']) && (string) $t['Foto_Ticket'] !== '';
            $hasDoc = isset($t['Document']) && (string) $t['Document'] !== '';
            $itFile = ($hasPhotoItColumn && isset($t['Photo_IT'])) ? trim((string) $t['Photo_IT']) : '';
            $currentStatus = isset($t['Status_Request']) ? (string) $t['Status_Request'] : '';
            $statusClass = ticket_admin_badge_status_class($currentStatus);
            $priorityText = (string) ($t['Priority'] ?? '');
            $priorityClass = ticket_admin_badge_priority_class($priorityText);
            $currentType = isset($t['Type_Pekerjaan']) ? trim((string) $t['Type_Pekerjaan']) : '';
            if (!ticket_admin_is_allowed_type_pekerjaan($currentType)) {
                $currentType = '';
            }

            $assignedToAjax = ($hasAssignedColumn && isset($t['assigned_to'])) ? trim((string) $t['assigned_to']) : '';
            $adminNameAjax = $Nama_Lengkap !== '' ? $Nama_Lengkap : $username;
            $isMineAjax = $assignedToAjax !== '' && $assignedToAjax === $adminNameAjax;
            $isOthersAjax = $assignedToAjax !== '' && !$isMineAjax;

            if ($hasAssignedColumn) {
                if ($isMineAjax) {
                    $assignedTd = '<td class="px-3 py-3 whitespace-nowrap">'
                        . '<button onclick="assignTicket(' . $codeInt . ',\'unassign\',this)" title="Klik untuk lepas"'
                        . ' class="assign-btn mine inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-green-50 text-green-700 border border-green-200 hover:bg-red-50 hover:text-red-600 hover:border-red-200 transition-all group">'
                        . '<span class="relative flex h-2 w-2"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span><span class="relative inline-flex rounded-full h-2 w-2 bg-green-500"></span></span>'
                        . '<span class="group-hover:hidden">Saya</span><span class="hidden group-hover:inline">Lepas</span>'
                        . '</button></td>';
                } elseif ($isOthersAjax) {
                    $assignedTd = '<td class="px-3 py-3 whitespace-nowrap">'
                        . '<span class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-medium bg-blue-50 text-blue-700 border border-blue-200">'
                        . '<span class="relative flex h-2 w-2"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-75"></span><span class="relative inline-flex rounded-full h-2 w-2 bg-blue-500"></span></span>'
                        . htmlspecialchars($assignedToAjax) . '</span></td>';
                } else {
                    $assignedTd = '<td class="px-3 py-3 whitespace-nowrap">'
                        . '<button onclick="assignTicket(' . $codeInt . ',\'assign\',this)"'
                        . ' class="assign-btn inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-medium bg-gray-50 text-gray-500 border border-gray-200 hover:bg-orange-50 hover:text-orange-700 hover:border-orange-300 transition-all">'
                        . '<i class="fas fa-hand-pointer text-[10px]"></i> Kerjakan</button></td>';
                }
            } else {
                $assignedTd = '';
            }

            $tableHtml .= '<tr class="hover:bg-orange-50/40 transition-colors" data-ticket-code="' . htmlspecialchars((string) $codeInt, ENT_QUOTES) . '">'
                . '<td class="px-4 py-3 text-sm text-gray-800 whitespace-nowrap">' . htmlspecialchars((string) $rowNo) . '</td>'
                . $assignedTd
                . '<td class="px-4 py-3 text-sm text-gray-800 whitespace-nowrap">'
                . '<div class="font-semibold text-gray-900">' . htmlspecialchars($codeDisplay) . '</div>'
                . '<div class="text-xs text-gray-500">#' . htmlspecialchars((string) $codeInt) . '</div>'
                . '</td>'
                . '<td class="px-4 py-3 text-sm text-gray-800 whitespace-nowrap">' . htmlspecialchars((string) ($t['Create_User'] ?? '')) . '</td>'
                . '<td class="px-4 py-3 text-sm text-gray-800 whitespace-nowrap">' . htmlspecialchars((string) ($t['Id_Karyawan'] ?? '')) . '</td>'
                . '<td class="px-4 py-3 text-sm text-gray-800">' . htmlspecialchars((string) ($t['Nama_User'] ?? '')) . '</td>'
                . '<td class="px-4 py-3 text-sm text-gray-800">' . htmlspecialchars((string) ($t['Divisi_User'] ?? '')) . '</td>'
                . '<td class="px-4 py-3 text-sm text-gray-800">' . htmlspecialchars((string) ($t['Region'] ?? '')) . '</td>'
                . '<td class="px-4 py-3 text-sm text-gray-800">' . htmlspecialchars((string) ($t['Subject'] ?? '')) . '</td>'
                . '<td class="px-4 py-3 text-sm text-gray-800">' . htmlspecialchars((string) ($t['Kategori_Masalah'] ?? '')) . '</td>'
                . '<td class="px-4 py-3 text-sm text-gray-800 whitespace-nowrap">'
                . '<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold border ' . htmlspecialchars($priorityClass) . '">' . htmlspecialchars($priorityText) . '</span>'
                . '</td>'
                . '<td class="px-4 py-3 text-sm text-gray-800 whitespace-nowrap">'
                . '<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold border ' . htmlspecialchars($statusClass) . '">' . htmlspecialchars((string) $currentStatus) . '</span>'
                . '</td>'
                . '<td class="px-4 py-3 text-sm text-gray-800 whitespace-nowrap">'
                . '<form method="POST" class="flex items-center gap-2">'
                . '<input type="hidden" name="action" value="update_type_pekerjaan" />'
                . '<input type="hidden" name="Ticket_code" value="' . htmlspecialchars((string) $codeInt, ENT_QUOTES) . '" />'
                . '<select name="Type_Pekerjaan" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">';

            $typeOpts = ['Remote', 'Onsite'];
            foreach ($typeOpts as $opt) {
                $sel = ($opt === $currentType) ? 'selected' : '';
                $tableHtml .= '<option value="' . htmlspecialchars($opt, ENT_QUOTES) . '" ' . $sel . '>' . htmlspecialchars($opt) . '</option>';
            }

            $tableHtml .= '</select>'
                . '<button type="submit" class="px-3 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 text-sm">Set</button>'
                . '</form>'
                . '</td>'
                . '<td class="px-4 py-3 text-sm text-gray-800 min-w-[220px]">' . htmlspecialchars((string) ($t['Deskripsi_Masalah'] ?? '')) . '</td>'
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

            $jawabanItVal = isset($t['Jawaban_IT']) ? trim((string) $t['Jawaban_IT']) : '';
            $photoItInputId = 'Photo_IT_' . $codeInt;

            $tableHtml .= '</td>'
                . '<td class="px-4 py-3 text-sm text-gray-800 whitespace-nowrap">'
                . '<div class="flex items-center gap-2">'
                . ($itFile !== ''
                    ? '<a class="text-orange-700 hover:underline" href="../uploads/ticket/' . rawurlencode((string) $itFile) . '" target="_blank" rel="noopener">File IT</a>'
                    : '<span class="text-gray-400">-</span>')
                . '</div>'
                . '<form method="POST" enctype="multipart/form-data" class="mt-2 flex items-center gap-2">'
                . '<input type="hidden" name="action" value="update_file_it" />'
                . '<input type="hidden" name="Ticket_code" value="' . htmlspecialchars((string) $codeInt, ENT_QUOTES) . '" />'
                . '<input type="file" id="' . htmlspecialchars($photoItInputId, ENT_QUOTES) . '" name="Photo_IT" accept="image/*,application/pdf,.pdf,.doc,.docx,.xls,.xlsx" capture="environment" class="text-sm js-it-photo-input" />'
                . '<button type="button" class="px-2.5 py-2 border border-gray-300 rounded-lg text-xs text-gray-700 hover:bg-gray-50 js-it-camera-btn whitespace-nowrap" data-target-input="' . htmlspecialchars($photoItInputId, ENT_QUOTES) . '"><i class="fas fa-camera"></i> Kamera</button>'
                . '<button type="submit" class="px-3 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 text-sm whitespace-nowrap">Upload</button>'
                . '</form>'
                . '</td>'
                . '<td class="px-4 py-3 text-sm text-gray-800 min-w-[260px]">'
                . '<form method="POST" class="flex items-start gap-2">'
                . '<input type="hidden" name="action" value="update_jawaban_it" />'
                . '<input type="hidden" name="Ticket_code" value="' . htmlspecialchars((string) $codeInt, ENT_QUOTES) . '" />'
                . '<textarea name="Jawaban_IT" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="Tulis jawaban IT...">' . htmlspecialchars((string) $jawabanItVal) . '</textarea>'
                . '<button type="submit" class="px-3 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 text-sm whitespace-nowrap">Simpan</button>'
                . '</form>'
                . '</td>'
                . '<td class="px-4 py-3 text-sm text-gray-800 whitespace-nowrap">'
                . '<form method="POST" class="flex items-center gap-2">'
                . '<input type="hidden" name="action" value="update_status" />'
                . '<input type="hidden" name="Ticket_code" value="' . htmlspecialchars((string) $codeInt, ENT_QUOTES) . '" />'
                . '<select name="Status_Request" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">';

            $opts = ['Open', 'Reject', 'Review', 'In Progress', 'Done'];
            foreach ($opts as $opt) {
                $sel = ($opt === $currentStatus) ? 'selected' : '';
                $disabled = ($sel === '') && !ticket_admin_can_transition_status((string) $currentStatus, (string) $opt);
                $label = ($disabled ? '🔒 ' : '') . $opt;
                $tableHtml .= '<option value="' . htmlspecialchars($opt, ENT_QUOTES) . '" ' . $sel . ' ' . ($disabled ? 'disabled' : '') . '>' . htmlspecialchars($label) . '</option>';
            }

            $tableHtml .= '</select>'
                . '<button type="submit" class="px-3 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 text-sm">Update</button>'
                . '</form>'
                . '<div class="mt-1 text-[11px] text-red-600">Wajib isi Type Pekerjaan + Respon IT' . ($hasPhotoItColumn ? ' + File IT' : '') . ' sebelum pilih Done.</div>'
                . '</td>'
                . '</tr>';
        }

        $tableHtml .= '</tbody>';
        $paginationHtml = ticket_admin_build_pagination_html($page, $totalPages, $totalRecords, $offset, $limit, $baseParams);

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

try {
    // Counts per status for tabs (global counts)
    [$whereTabs, $typesTabs, $paramsTabs] = ticket_admin_build_ticket_where(
        '',
        $hasSearch,
        $ticketAdminSearchWhereSql,
        $filterFromDateTime,
        $filterToDateTime,
        $searchLikeTicketCode,
        $searchLikeText
    );
    $stmtStatusCounts = $kon->prepare('SELECT `Status_Request`, COUNT(*) AS total FROM `ticket`' . $whereTabs . ' GROUP BY `Status_Request`');
    if ($stmtStatusCounts) {
        ticket_admin_stmt_bind($stmtStatusCounts, $typesTabs, $paramsTabs);
        if ($stmtStatusCounts->execute()) {
            $resStatus = $stmtStatusCounts->get_result();
            if ($resStatus) {
                while ($r = $resStatus->fetch_assoc()) {
                    $st = isset($r['Status_Request']) ? (string) $r['Status_Request'] : '';
                    $cnt = (int) ($r['total'] ?? 0);
                    $totalAllRecords += $cnt;
                    if ($st !== '' && ticket_admin_is_allowed_status($st)) {
                        $statusCounts[$st] = $cnt;
                    }
                }
            }
        }
    }
    if ($stmtStatusCounts) {
        $stmtStatusCounts->close();
    }

    // Count total records
    [$whereCount, $typesCount, $paramsCount] = ticket_admin_build_ticket_where(
        $statusFilter,
        $hasSearch,
        $ticketAdminSearchWhereSql,
        $filterFromDateTime,
        $filterToDateTime,
        $searchLikeTicketCode,
        $searchLikeText
    );
    $stmtCount = $kon->prepare('SELECT COUNT(*) AS total FROM `ticket`' . $whereCount);
    if (!$stmtCount) {
        throw new Exception($kon->error);
    }

    ticket_admin_stmt_bind($stmtCount, $typesCount, $paramsCount);
    if ($stmtCount->execute()) {
        $resCount = $stmtCount->get_result();
        $rowCount = $resCount ? $resCount->fetch_assoc() : null;
        $totalRecords = $rowCount ? (int) ($rowCount['total'] ?? 0) : 0;
    }
    $stmtCount->close();

    if ($statusFilter === '') {
        $totalAllRecords = $totalRecords;
    }

    $totalPages = $totalRecords > 0 ? (int) ceil($totalRecords / $limit) : 1;
    if ($page > $totalPages) {
        $page = $totalPages;
        $offset = ($page - 1) * $limit;
    }

    // Data
    $photoItSelect = $hasPhotoItColumn ? ', `Photo_IT`' : '';
    $assignedSelect = $hasAssignedColumn ? ', `assigned_to`, `assigned_at`' : '';
    $selectCols = '`No`, `Ticket_code`, `Id_Karyawan`, `Nama_User`, `Divisi_User`, `Jabatan_User`, `Region`, `Subject`, `Kategori_Masalah`, `Priority`, `Status_Request`, `Type_Pekerjaan`, `Create_User`, `Create_By_User`, `Deskripsi_Masalah`, `Foto_Ticket`, `Document`, `Jawaban_IT`' . $photoItSelect . $assignedSelect;
    [$whereList, $typesListBase, $paramsListBase] = ticket_admin_build_ticket_where(
        $statusFilter,
        $hasSearch,
        $ticketAdminSearchWhereSql,
        $filterFromDateTime,
        $filterToDateTime,
        $searchLikeTicketCode,
        $searchLikeText
    );
    $stmtList = $kon->prepare('SELECT ' . $selectCols . ' FROM `ticket`' . $whereList . ' ORDER BY `Ticket_code` DESC LIMIT ? OFFSET ?');
    if (!$stmtList) {
        throw new Exception('Prepare list gagal: ' . $kon->error);
    }

    $typesList = $typesListBase . 'ii';
    $paramsList = $paramsListBase;
    $paramsList[] = $limit;
    $paramsList[] = $offset;
    ticket_admin_stmt_bind($stmtList, $typesList, $paramsList);
    if (!$stmtList->execute()) {
        throw new Exception('Query list gagal: ' . $stmtList->error);
    }
    $res = $stmtList->get_result();
    if ($res === false) {
        $ticketQueryError = 'Gagal mengambil data ticket.';
        error_log('Ticket query failed: ' . $kon->error);
    } else {
        while ($row = $res->fetch_assoc()) {
            $tickets[] = $row;
        }
    }
    $stmtList->close();

    $baseParams = $_GET;
    unset($baseParams['page']);
    unset($baseParams['action']);
    $paginationHtml = ticket_admin_build_pagination_html($page, $totalPages, $totalRecords, $offset, $limit, $baseParams);

    // Base params for tabs (reset page; preserve other params)
    $tabBaseParams = $_GET;
    unset($tabBaseParams['page']);
    unset($tabBaseParams['action']);
    unset($tabBaseParams['status']);
} catch (Throwable $e) {
    $ticketQueryError = 'Terjadi error saat mengambil data ticket.';
    error_log('Ticket query exception: ' . $e->getMessage());
}

// ===== Ticket Dashboard (Admin) - real data (affected by date range filter) =====
$dashTotalTickets = 0;
$dashStatusCounts = array_fill_keys(ticket_admin_status_list(), 0);
$dashPriorityCounts = ['Low' => 0, 'Medium' => 0, 'High' => 0, 'Urgent' => 0];
$dashRecentTickets = [];
$dashAvgResponseTime = '-';

$dashRangeLabel = 'All time';
if ($hasDateFilter) {
    if ($filterDateFrom !== '' && $filterDateTo !== '') {
        $dashRangeLabel = $filterDateFrom . ' s/d ' . $filterDateTo;
    } elseif ($filterDateFrom !== '') {
        $dashRangeLabel = 'Dari ' . $filterDateFrom;
    } elseif ($filterDateTo !== '') {
        $dashRangeLabel = 'Sampai ' . $filterDateTo;
    }
}

$dashWhereParts = [];
$dashTypes = '';
$dashParams = [];
if ($filterFromDateTime !== '') {
    $dashWhereParts[] = '`Create_User` >= ?';
    $dashTypes .= 's';
    $dashParams[] = $filterFromDateTime;
}
if ($filterToDateTime !== '') {
    $dashWhereParts[] = '`Create_User` <= ?';
    $dashTypes .= 's';
    $dashParams[] = $filterToDateTime;
}
$dashWhereSql = !empty($dashWhereParts) ? (' WHERE ' . implode(' AND ', $dashWhereParts)) : '';

// Status counts + total
try {
    $stmtDashStatus = $kon->prepare('SELECT `Status_Request`, COUNT(*) AS total FROM `ticket`' . $dashWhereSql . ' GROUP BY `Status_Request`');
    if ($stmtDashStatus) {
        ticket_admin_stmt_bind($stmtDashStatus, $dashTypes, $dashParams);
    }
    if ($stmtDashStatus && $stmtDashStatus->execute()) {
        $resDashStatus = $stmtDashStatus->get_result();
        if ($resDashStatus) {
            while ($r = $resDashStatus->fetch_assoc()) {
                $st = isset($r['Status_Request']) ? (string) $r['Status_Request'] : '';
                $cnt = (int) ($r['total'] ?? 0);
                $dashTotalTickets += $cnt;
                if ($st !== '' && ticket_admin_is_allowed_status($st)) {
                    $dashStatusCounts[$st] = $cnt;
                }
            }
        }
    }
    if ($stmtDashStatus) {
        $stmtDashStatus->close();
    }
} catch (Throwable $e) {
    // best-effort
}

// Priority distribution
try {
    $stmtDashPr = $kon->prepare('SELECT `Priority`, COUNT(*) AS total FROM `ticket`' . $dashWhereSql . ' GROUP BY `Priority`');
    if ($stmtDashPr) {
        ticket_admin_stmt_bind($stmtDashPr, $dashTypes, $dashParams);
    }
    if ($stmtDashPr && $stmtDashPr->execute()) {
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
    if ($stmtDashPr) {
        $stmtDashPr->close();
    }
} catch (Throwable $e) {
    // best-effort
}

// Recent tickets
try {
    $stmtRecent = $kon->prepare('SELECT `Ticket_code`, `Nama_User`, `Divisi_User`, `Subject`, `Kategori_Masalah`, `Priority`, `Status_Request`, `Create_User` FROM `ticket`' . $dashWhereSql . ' ORDER BY `Ticket_code` DESC LIMIT 5');
    if ($stmtRecent) {
        ticket_admin_stmt_bind($stmtRecent, $dashTypes, $dashParams);
    }
    if ($stmtRecent && $stmtRecent->execute()) {
        $resRecent = $stmtRecent->get_result();
        if ($resRecent) {
            while ($row = $resRecent->fetch_assoc()) {
                $dashRecentTickets[] = $row;
            }
        }
    }
    if ($stmtRecent) {
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
            . "WHERE t.Create_User IS NOT NULL AND h.first_changed_at IS NOT NULL";

        $avgTypes = '';
        $avgParams = [];
        if ($filterFromDateTime !== '') {
            $sqlAvg .= " AND t.Create_User >= ?";
            $avgTypes .= 's';
            $avgParams[] = $filterFromDateTime;
        }
        if ($filterToDateTime !== '') {
            $sqlAvg .= " AND t.Create_User <= ?";
            $avgTypes .= 's';
            $avgParams[] = $filterToDateTime;
        }

        $stmtAvg = $kon->prepare($sqlAvg);
        if ($stmtAvg) {
            ticket_admin_stmt_bind($stmtAvg, $avgTypes, $avgParams);
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
foreach (ticket_admin_status_list() as $st) {
    $dashStatusData[] = ['name' => $st, 'value' => (int) ($dashStatusCounts[$st] ?? 0)];
}

$dashPriorityData = [];
foreach (['Low', 'Medium', 'High', 'Urgent'] as $p) {
    $dashPriorityData[] = ['name' => $p, 'value' => (int) ($dashPriorityCounts[$p] ?? 0)];
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../global_dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script
        src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>
    <style>
        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* ===== MOBILE RESPONSIF - Admin Ticket ===== */

        /* Main content padding lebih kecil di mobile */
        @media (max-width: 767px) {
            main.p-6 {
                padding: 0.75rem !important;
            }

            /* Page title lebih compact di mobile */
            h1.text-3xl {
                font-size: 1.5rem !important;
                margin-top: 3.5rem !important;
            }

            /* Dashboard header: stack vertikal di mobile */
            .bg-white.rounded-xl.shadow-lg .flex.items-center.justify-between.gap-4.mb-5 {
                flex-direction: column !important;
                align-items: flex-start !important;
                gap: 0.75rem !important;
            }

            /* Filter form date range: stack ke bawah di mobile */
            .bg-white.rounded-xl.shadow-lg form.flex.flex-wrap {
                flex-direction: column !important;
                align-items: stretch !important;
                width: 100% !important;
            }

            .bg-white.rounded-xl.shadow-lg form.flex.flex-wrap div,
            .bg-white.rounded-xl.shadow-lg form.flex.flex-wrap input[type="date"] {
                width: 100% !important;
            }

            .bg-white.rounded-xl.shadow-lg form.flex.flex-wrap .flex.items-center.gap-2 {
                flex-direction: row !important;
                width: 100% !important;
            }

            .bg-white.rounded-xl.shadow-lg form.flex.flex-wrap button[type="submit"],
            .bg-white.rounded-xl.shadow-lg form.flex.flex-wrap a {
                flex: 1;
                text-align: center;
                justify-content: center;
            }

            /* Stats cards: 2 kolom di mobile */
            .grid.grid-cols-1.md\:grid-cols-2.lg\:grid-cols-4 {
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 0.75rem !important;
            }

            /* Stats card padding lebih kecil */
            .grid.grid-cols-1.md\:grid-cols-2.lg\:grid-cols-4>div {
                padding: 0.875rem !important;
            }

            .grid.grid-cols-1.md\:grid-cols-2.lg\:grid-cols-4 .text-3xl {
                font-size: 1.5rem !important;
            }

            /* Table section: padding lebih kecil */
            .bg-white.rounded-xl.shadow-lg.p-6 {
                padding: 0.75rem !important;
            }

            /* Table horizontal scroll dengan hint */
            #tickets-table-container {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                border-radius: 0.5rem;
                position: relative;
            }

            #tickets-table-container::after {
                content: '← Geser untuk melihat lebih →';
                display: block;
                text-align: center;
                font-size: 0.7rem;
                color: #9ca3af;
                padding: 0.35rem 0;
                border-top: 1px solid #f3f4f6;
            }

            /* Search + download: full width di mobile */
            #ticketSearchForm .flex.flex-col {
                gap: 0.5rem !important;
            }

            #ticketSearchForm #ticketDownloadReportBtn {
                width: 100%;
                justify-content: center;
            }

            /* Status tabs: wrappable dan ukuran kecil */
            .status-tab {
                padding: 0.375rem 0.75rem !important;
                font-size: 0.75rem !important;
            }

            /* Form in card: lebih compact */
            .bg-white.rounded-xl.shadow-lg.p-6 input[type="date"],
            .bg-white.rounded-xl.shadow-lg.p-6 input[type="text"],
            .bg-white.rounded-xl.shadow-lg.p-6 textarea,
            .bg-white.rounded-xl.shadow-lg.p-6 select {
                padding: 0.5rem 0.75rem !important;
            }

            /* Recent tickets table: horizontal scroll */
            .overflow-x-auto {
                -webkit-overflow-scrolling: touch;
            }

            /* Tabel assign button lebih compact */
            .assign-btn {
                padding: 0.25rem 0.5rem !important;
                font-size: 0.7rem !important;
            }

            /* Charts: height lebih kecil di mobile */
            [style*="height: 300px"] {
                height: 220px !important;
            }
        }

        /* Small mobile (< 480px) */
        @media (max-width: 479px) {
            .grid.grid-cols-1.md\:grid-cols-2.lg\:grid-cols-4 {
                grid-template-columns: 1fr 1fr !important;
            }

            .grid.grid-cols-1.md\:grid-cols-2.lg\:grid-cols-4 .text-3xl {
                font-size: 1.25rem !important;
            }

            h1.text-3xl {
                font-size: 1.25rem !important;
            }
        }

        /* Tablet (768px - 1023px) */
        @media (min-width: 768px) and (max-width: 1023px) {
            main.p-6 {
                padding: 1.25rem !important;
            }

            .grid.grid-cols-1.md\:grid-cols-2.lg\:grid-cols-4 {
                grid-template-columns: repeat(2, 1fr) !important;
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

    <?php
    // Include sidebar + navbar admin
    $activePage = 'ticket';
    require_once __DIR__ . '/sidebar_admin.php';
    ?>

    <div id="main-content-wrapper" class="lg:ml-60 transition-all duration-300 ease-in-out">
        <script>
            (function () {
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
                window.addEventListener('sidebarToggled', function (e) { applyState(); });
                window.addEventListener('resize', function () { applyState(); });
            })();
        </script>
        <main class="p-3 sm:p-6 lg:p-8">
            <div class="mb-6 sm:mb-8">
                <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-2 mt-14 sm:mt-16">Ticket</h1>
                <p class="text-gray-600">Tabel request ticket IT Support (input dari user).</p>
            </div>

            <!-- Dashboard Ticket (Admin) -->
            <div class="mb-8">
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex flex-wrap items-center justify-between gap-3 mb-4 sm:mb-5">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg bg-orange-100 flex items-center justify-center">
                                <i class="fas fa-chart-pie text-orange-600" aria-hidden="true"></i>
                            </div>
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900">Dashboard Ticket</h2>
                                <p class="text-sm text-gray-600">Ringkasan ticket dan insight cepat</p>
                            </div>
                            <span id="rt-live-badge"
                                class="flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-50 border border-amber-200 text-amber-700"
                                title="Menghubungkan ke real-time..." style="transition:all 0.4s ease;">
                                <span id="rt-live-dot" class="inline-block w-2 h-2 rounded-full bg-amber-400"
                                    style="transition:background-color 0.4s ease, transform 0.3s ease;"></span>
                                LIVE
                            </span>
                        </div>

                        <form method="GET" class="flex flex-wrap items-end gap-2 w-full sm:w-auto mt-2 sm:mt-0">
                            <?php if ($statusFilter !== ''): ?>
                                <input type="hidden" name="status"
                                    value="<?php echo htmlspecialchars($statusFilter, ENT_QUOTES); ?>" />
                            <?php endif; ?>
                            <?php if ($searchQuery !== ''): ?>
                                <input type="hidden" name="q"
                                    value="<?php echo htmlspecialchars($searchQuery, ENT_QUOTES); ?>" />
                            <?php endif; ?>

                            <div class="flex-1 min-w-[130px]">
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Dari</label>
                                <input type="date" name="date_from"
                                    value="<?php echo htmlspecialchars($filterDateFrom, ENT_QUOTES); ?>"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" />
                            </div>
                            <div class="flex-1 min-w-[130px]">
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Sampai</label>
                                <input type="date" name="date_to"
                                    value="<?php echo htmlspecialchars($filterDateTo, ENT_QUOTES); ?>"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" />
                            </div>
                            <div class="flex items-center gap-2 pt-5">
                                <button type="submit"
                                    class="px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 text-sm font-semibold whitespace-nowrap">Filter</button>
                                <?php if ($hasDateFilter): ?>
                                    <?php
                                    $resetParams = $_GET;
                                    unset($resetParams['date_from'], $resetParams['date_to'], $resetParams['from'], $resetParams['to'], $resetParams['page'], $resetParams['action']);
                                    $resetUrl = '?' . http_build_query($resetParams);
                                    ?>
                                    <a href="<?php echo htmlspecialchars($resetUrl); ?>"
                                        class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-semibold text-gray-700 hover:bg-gray-50 whitespace-nowrap">Reset</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>

                    <!-- Stats Cards -->
                    <div class="grid grid-cols-2 md:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-4 sm:mb-6">
                        <div tabindex="0"
                            class="pressable bg-white rounded-lg border border-gray-200 p-5 shadow-sm cursor-pointer transition-all duration-200 ease-out hover:-translate-y-0.5 hover:shadow-lg hover:border-orange-200 hover:bg-orange-50/30 active:scale-[0.99] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-orange-500/40 focus-visible:ring-offset-2">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-sm text-gray-600 mb-1">Total Tickets</p>
                                    <div id="rt-total" class="text-3xl font-semibold text-gray-900"
                                        style="transition:color 0.3s;">
                                        <?php echo htmlspecialchars((string) $dashTotalTickets); ?>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1">
                                        <?php echo htmlspecialchars($dashRangeLabel); ?>
                                    </p>
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
                                    <div id="rt-open" class="text-3xl font-semibold text-gray-900"
                                        style="transition:color 0.3s;">
                                        <?php echo htmlspecialchars((string) $dashOpenTickets); ?>
                                    </div>
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
                                    <div id="rt-inprogress" class="text-3xl font-semibold text-gray-900"
                                        style="transition:color 0.3s;">
                                        <?php echo htmlspecialchars((string) $dashInProgressTickets); ?>
                                    </div>
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
                                    <div id="rt-done" class="text-3xl font-semibold text-gray-900"
                                        style="transition:color 0.3s;">
                                        <?php echo htmlspecialchars((string) $dashDoneTickets); ?>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1">Resolved</p>
                                </div>
                                <div class="p-3 rounded-lg bg-green-100 text-green-600">
                                    <i class="fas fa-check-circle" aria-hidden="true"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Additional Stats -->
                    <div class="grid grid-cols-2 md:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-4 sm:mb-6">
                        <div tabindex="0"
                            class="pressable bg-white rounded-lg border border-gray-200 p-5 shadow-sm cursor-pointer transition-all duration-200 ease-out hover:-translate-y-0.5 hover:shadow-lg hover:border-orange-200 hover:bg-orange-50/30 active:scale-[0.99] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-orange-500/40 focus-visible:ring-offset-2">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-sm text-gray-600 mb-1">Review</p>
                                    <div id="rt-review" class="text-3xl font-semibold text-gray-900"
                                        style="transition:color 0.3s;">
                                        <?php echo htmlspecialchars((string) $dashReviewTickets); ?>
                                    </div>
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
                                    <div id="rt-closed" class="text-3xl font-semibold text-gray-900"
                                        style="transition:color 0.3s;">
                                        <?php echo htmlspecialchars((string) $dashClosedTickets); ?>
                                    </div>
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
                                    <div id="rt-rejected" class="text-3xl font-semibold text-gray-900"
                                        style="transition:color 0.3s;">
                                        <?php echo htmlspecialchars((string) $dashRejectedTickets); ?>
                                    </div>
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
                                    <div id="rt-avgtime" class="text-3xl font-semibold text-gray-900"
                                        style="transition:color 0.3s;">
                                        <?php echo htmlspecialchars((string) $dashAvgResponseTime); ?>
                                    </div>
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
                                <canvas id="adminTicketStatusChart"></canvas>
                            </div>
                        </div>
                        <div tabindex="0"
                            class="pressable bg-white rounded-lg border border-gray-200 p-5 cursor-pointer transition-all duration-200 ease-out hover:-translate-y-0.5 hover:shadow-lg hover:border-orange-200 hover:bg-orange-50/20 active:scale-[0.99] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-orange-500/40 focus-visible:ring-offset-2">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Tickets by Priority</h3>
                            <div class="relative" style="height: 300px;">
                                <canvas id="adminTicketPriorityChart"></canvas>
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
                                <tbody id="dash-recent-tbody" class="bg-white divide-y divide-gray-100">
                                    <?php if (count($dashRecentTickets) === 0): ?>
                                        <tr>
                                            <td class="px-5 py-4 text-sm text-gray-600" colspan="8">Belum ada data ticket.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($dashRecentTickets as $rt): ?>
                                            <?php
                                            $rtCodeInt = (int) ($rt['Ticket_code'] ?? 0);
                                            $rtCodeDisplay = ticket_admin_format_code($rtCodeInt, isset($rt['Create_User']) ? (string) $rt['Create_User'] : null);
                                            $rtStatus = isset($rt['Status_Request']) ? (string) $rt['Status_Request'] : '';
                                            $rtStatusClass = ticket_admin_badge_status_class($rtStatus);
                                            $rtPriority = isset($rt['Priority']) ? (string) $rt['Priority'] : '';
                                            $rtPriorityClass = ticket_admin_badge_priority_class($rtPriority);
                                            ?>
                                            <tr class="hover:bg-orange-50/40 transition-colors">
                                                <td class="px-5 py-4 whitespace-nowrap">
                                                    <div class="text-sm font-semibold text-gray-900">
                                                        <?php echo htmlspecialchars($rtCodeDisplay); ?>
                                                    </div>
                                                    <div class="text-xs text-gray-500">
                                                        #<?php echo htmlspecialchars((string) $rtCodeInt); ?></div>
                                                </td>
                                                <td class="px-5 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <?php echo htmlspecialchars((string) ($rt['Nama_User'] ?? '')); ?>
                                                </td>
                                                <td class="px-5 py-4 whitespace-nowrap text-sm text-gray-600">
                                                    <?php echo htmlspecialchars((string) ($rt['Divisi_User'] ?? '')); ?>
                                                </td>
                                                <td class="px-5 py-4 text-sm text-gray-900">
                                                    <?php echo htmlspecialchars((string) ($rt['Subject'] ?? '')); ?>
                                                </td>
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
                                                    <?php echo htmlspecialchars((string) ($rt['Create_User'] ?? '')); ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center justify-between gap-4 mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-orange-100 flex items-center justify-center">
                            <i class="fas fa-headset text-orange-600"></i>
                        </div>
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">Request Ticket IT Support</h2>
                            <p class="text-sm text-gray-600">Menampilkan data ticket dengan pagination</p>
                        </div>
                    </div>
                </div>

                <form id="ticketSearchForm" method="GET" class="mb-4">
                    <?php if ($statusFilter !== ''): ?>
                        <input type="hidden" name="status"
                            value="<?php echo htmlspecialchars($statusFilter, ENT_QUOTES); ?>" />
                    <?php endif; ?>
                    <?php if ($filterDateFrom !== ''): ?>
                        <input type="hidden" name="date_from"
                            value="<?php echo htmlspecialchars($filterDateFrom, ENT_QUOTES); ?>" />
                    <?php endif; ?>
                    <?php if ($filterDateTo !== ''): ?>
                        <input type="hidden" name="date_to"
                            value="<?php echo htmlspecialchars($filterDateTo, ENT_QUOTES); ?>" />
                    <?php endif; ?>
                    <?php
                    $downloadParams = [];
                    if ($statusFilter !== '') {
                        $downloadParams['status'] = $statusFilter;
                    }
                    if ($searchQuery !== '') {
                        $downloadParams['q'] = $searchQuery;
                    }
                    if ($filterDateFrom !== '') {
                        $downloadParams['date_from'] = $filterDateFrom;
                    }
                    if ($filterDateTo !== '') {
                        $downloadParams['date_to'] = $filterDateTo;
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

                        <a id="ticketDownloadReportBtn" href="<?php echo htmlspecialchars($downloadUrl); ?>"
                            class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-slate-700 text-white rounded-lg hover:bg-slate-800 text-sm font-semibold whitespace-nowrap">
                            <i class="fas fa-download" aria-hidden="true"></i>
                            Download Report
                        </a>
                    </div>
                </form>

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

                        <?php foreach (ticket_admin_status_list() as $st): ?>
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
                    <?php if ($ticketQueryError): ?>
                        <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-4">
                            <?php echo htmlspecialchars($ticketQueryError); ?>
                        </div>
                    <?php elseif (count($tickets) === 0): ?>
                        <div class="bg-yellow-50 border border-yellow-200 text-yellow-700 rounded-lg p-4">
                            <?php if ($statusFilter !== ''): ?>
                                Tidak ada ticket untuk status: <span
                                    class="font-semibold"><?php echo htmlspecialchars($statusFilter); ?></span>.
                            <?php else: ?>
                                Belum ada request ticket dari user.
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <table class="min-w-full border border-gray-200 rounded-lg overflow-hidden">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th
                                        class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider px-4 py-3 border-b">
                                        No</th>
                                    <?php if ($hasAssignedColumn): ?>
                                        <th
                                            class="text-left text-xs font-semibold text-orange-600 uppercase tracking-wider px-3 py-3 border-b">
                                            Kerjakan</th>
                                    <?php endif; ?>
                                    <th
                                        class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider px-4 py-3 border-b">
                                        Ticket Code</th>
                                    <th
                                        class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider px-4 py-3 border-b">
                                        Created</th>
                                    <th
                                        class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider px-4 py-3 border-b">
                                        ID Karyawan</th>
                                    <th
                                        class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider px-4 py-3 border-b">
                                        Nama User</th>
                                    <th
                                        class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider px-4 py-3 border-b">
                                        Divisi</th>
                                    <th
                                        class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider px-4 py-3 border-b">
                                        Region</th>
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
                                        Deskripsi</th>
                                    <th
                                        class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider px-4 py-3 border-b">
                                        File</th>
                                    <th
                                        class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider px-4 py-3 border-b">
                                        File IT</th>
                                    <th
                                        class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider px-4 py-3 border-b">
                                        Jawaban IT</th>
                                    <th
                                        class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider px-4 py-3 border-b">
                                        Respon</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                <?php $rowNo = $offset;
                                foreach ($tickets as $t):
                                    $rowNo++; ?>
                                    <?php
                                    $codeInt = (int) $t['Ticket_code'];
                                    $codeDisplay = ticket_admin_format_code($codeInt, isset($t['Create_User']) ? (string) $t['Create_User'] : null);
                                    $hasFoto = isset($t['Foto_Ticket']) && (string) $t['Foto_Ticket'] !== '';
                                    $hasDoc = isset($t['Document']) && (string) $t['Document'] !== '';
                                    $itFile = ($hasPhotoItColumn && isset($t['Photo_IT'])) ? trim((string) $t['Photo_IT']) : '';
                                    $currentStatus = isset($t['Status_Request']) ? (string) $t['Status_Request'] : '';
                                    $statusClass = ticket_admin_badge_status_class($currentStatus);
                                    $currentType = isset($t['Type_Pekerjaan']) ? trim((string) $t['Type_Pekerjaan']) : '';
                                    if (!ticket_admin_is_allowed_type_pekerjaan($currentType)) {
                                        $currentType = '';
                                    }
                                    $priorityText = (string) ($t['Priority'] ?? '');
                                    $priorityClass = ticket_admin_badge_priority_class($priorityText);
                                    $jawabanItVal = isset($t['Jawaban_IT']) ? trim((string) $t['Jawaban_IT']) : '';
                                    $photoItInputId = 'Photo_IT_' . $codeInt;
                                    $assignedTo = ($hasAssignedColumn && isset($t['assigned_to'])) ? trim((string) $t['assigned_to']) : '';
                                    $adminDisplayName = $Nama_Lengkap !== '' ? $Nama_Lengkap : $username;
                                    $isMine = $assignedTo !== '' && $assignedTo === $adminDisplayName;
                                    $isOthers = $assignedTo !== '' && !$isMine;
                                    ?>
                                    <tr class="hover:bg-orange-50/40 transition-colors"
                                        data-ticket-code="<?php echo htmlspecialchars((string) $codeInt, ENT_QUOTES); ?>">
                                        <td class="px-4 py-3 text-sm text-gray-800 whitespace-nowrap">
                                            <?php echo htmlspecialchars((string) $rowNo); ?>
                                        </td>
                                        <?php if ($hasAssignedColumn): ?>
                                            <td class="px-3 py-3 whitespace-nowrap">
                                                <?php if ($isMine): ?>
                                                    <button onclick="assignTicket(<?php echo $codeInt; ?>, 'unassign', this)"
                                                        class="assign-btn mine inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-green-50 text-green-700 border border-green-200 hover:bg-red-50 hover:text-red-600 hover:border-red-200 transition-all group"
                                                        title="Klik untuk lepas">
                                                        <span class="relative flex h-2 w-2"><span
                                                                class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span><span
                                                                class="relative inline-flex rounded-full h-2 w-2 bg-green-500"></span></span>
                                                        <span class="group-hover:hidden">Saya</span>
                                                        <span class="hidden group-hover:inline">Lepas</span>
                                                    </button>
                                                <?php elseif ($isOthers): ?>
                                                    <span
                                                        class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-medium bg-blue-50 text-blue-700 border border-blue-200">
                                                        <span class="relative flex h-2 w-2"><span
                                                                class="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-75"></span><span
                                                                class="relative inline-flex rounded-full h-2 w-2 bg-blue-500"></span></span>
                                                        <?php echo htmlspecialchars($assignedTo); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <button onclick="assignTicket(<?php echo $codeInt; ?>, 'assign', this)"
                                                        class="assign-btn inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-medium bg-gray-50 text-gray-500 border border-gray-200 hover:bg-orange-50 hover:text-orange-700 hover:border-orange-300 transition-all">
                                                        <i class="fas fa-hand-pointer text-[10px]"></i> Kerjakan
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        <?php endif; ?>
                                        <td class="px-4 py-3 text-sm text-gray-800 whitespace-nowrap">
                                            <div class="font-semibold text-gray-900">
                                                <?php echo htmlspecialchars($codeDisplay); ?>
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                #<?php echo htmlspecialchars((string) $codeInt); ?></div>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-800 whitespace-nowrap">
                                            <?php echo htmlspecialchars((string) ($t['Create_User'] ?? '')); ?>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-800 whitespace-nowrap">
                                            <?php echo htmlspecialchars((string) $t['Id_Karyawan']); ?>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-800">
                                            <?php echo htmlspecialchars((string) $t['Nama_User']); ?>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-800">
                                            <?php echo htmlspecialchars((string) $t['Divisi_User']); ?>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-800">
                                            <?php echo htmlspecialchars((string) $t['Region']); ?>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-800">
                                            <?php echo htmlspecialchars((string) $t['Subject']); ?>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-800">
                                            <?php echo htmlspecialchars((string) $t['Kategori_Masalah']); ?>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-800 whitespace-nowrap">
                                            <span
                                                class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold border <?php echo htmlspecialchars($priorityClass); ?>">
                                                <?php echo htmlspecialchars($priorityText); ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-800 whitespace-nowrap">
                                            <span
                                                class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold border <?php echo htmlspecialchars($statusClass); ?>">
                                                <?php echo htmlspecialchars((string) $currentStatus); ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-800 whitespace-nowrap">
                                            <form method="POST" class="flex items-center gap-2">
                                                <input type="hidden" name="action" value="update_type_pekerjaan" />
                                                <input type="hidden" name="Ticket_code"
                                                    value="<?php echo htmlspecialchars((string) $codeInt); ?>" />
                                                <select name="Type_Pekerjaan"
                                                    class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                                                    <?php
                                                    $typeOpts = ['Remote', 'Onsite'];
                                                    foreach ($typeOpts as $opt) {
                                                        $sel = ($opt === $currentType) ? 'selected' : '';
                                                        echo '<option value="' . htmlspecialchars($opt) . '" ' . $sel . '>' . htmlspecialchars($opt) . '</option>';
                                                    }
                                                    ?>
                                                </select>
                                                <button type="submit"
                                                    class="px-3 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 text-sm">Set</button>
                                            </form>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-800 min-w-[220px]">
                                            <?php echo htmlspecialchars((string) ($t['Deskripsi_Masalah'] ?? '')); ?>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-800 whitespace-nowrap">
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
                                        <td class="px-4 py-3 text-sm text-gray-800 whitespace-nowrap">
                                            <div class="flex items-center gap-2">
                                                <?php if ($itFile !== ''): ?>
                                                    <a class="text-orange-700 hover:underline"
                                                        href="../uploads/ticket/<?php echo rawurlencode((string) $itFile); ?>"
                                                        target="_blank" rel="noopener">File IT</a>
                                                <?php else: ?>
                                                    <span class="text-gray-400">-</span>
                                                <?php endif; ?>
                                            </div>
                                            <form method="POST" enctype="multipart/form-data"
                                                class="mt-2 flex items-center gap-2">
                                                <input type="hidden" name="action" value="update_file_it" />
                                                <input type="hidden" name="Ticket_code"
                                                    value="<?php echo htmlspecialchars((string) $codeInt); ?>" />
                                                <input type="file"
                                                    id="<?php echo htmlspecialchars($photoItInputId, ENT_QUOTES); ?>"
                                                    name="Photo_IT" accept="image/*,application/pdf,.pdf,.doc,.docx,.xls,.xlsx"
                                                    capture="environment" class="text-sm js-it-photo-input" />
                                                <button type="button"
                                                    class="px-2.5 py-2 border border-gray-300 rounded-lg text-xs text-gray-700 hover:bg-gray-50 js-it-camera-btn whitespace-nowrap"
                                                    data-target-input="<?php echo htmlspecialchars($photoItInputId, ENT_QUOTES); ?>"><i
                                                        class="fas fa-camera"></i> Kamera</button>
                                                <button type="submit"
                                                    class="px-3 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 text-sm whitespace-nowrap">Upload</button>
                                            </form>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-800 min-w-[260px]">
                                            <form method="POST" class="flex items-start gap-2">
                                                <input type="hidden" name="action" value="update_jawaban_it" />
                                                <input type="hidden" name="Ticket_code"
                                                    value="<?php echo htmlspecialchars((string) $codeInt); ?>" />
                                                <textarea name="Jawaban_IT" rows="2"
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"
                                                    placeholder="Tulis jawaban IT..."><?php echo htmlspecialchars((string) $jawabanItVal); ?></textarea>
                                                <button type="submit"
                                                    class="px-3 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 text-sm whitespace-nowrap">Simpan</button>
                                            </form>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-800 whitespace-nowrap">
                                            <form method="POST" class="flex items-center gap-2">
                                                <input type="hidden" name="action" value="update_status" />
                                                <input type="hidden" name="Ticket_code"
                                                    value="<?php echo htmlspecialchars((string) $codeInt); ?>" />
                                                <select name="Status_Request"
                                                    class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                                                    <?php
                                                    $opts = ['Open', 'Reject', 'Review', 'In Progress', 'Done'];
                                                    foreach ($opts as $opt) {
                                                        $sel = ($opt === $currentStatus) ? 'selected' : '';
                                                        $disabled = ($sel === '') && !ticket_admin_can_transition_status((string) $currentStatus, (string) $opt);
                                                        $label = ($disabled ? '🔒 ' : '') . $opt;
                                                        echo '<option value="' . htmlspecialchars($opt) . '" ' . $sel . ' ' . ($disabled ? 'disabled' : '') . '>' . htmlspecialchars($label) . '</option>';
                                                    }
                                                    ?>
                                                </select>
                                                <button type="submit"
                                                    class="px-3 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 text-sm">Update</button>
                                            </form>
                                            <div class="mt-1 text-[11px] text-red-600">Wajib isi Type Pekerjaan + Respon
                                                IT<?php echo $hasPhotoItColumn ? ' + File IT' : ''; ?> sebelum pilih Done.</div>
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
            </div>

            <!-- Kamera Modal (untuk Photo_IT) -->
            <div id="itCameraModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/50" data-it-camera-close="1"></div>
                <div class="relative w-full max-w-lg bg-white rounded-xl shadow-lg overflow-hidden">
                    <div class="flex items-center justify-between px-5 py-4 border-b">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">Kamera</h3>
                            <p class="text-xs text-gray-600">Capture foto + GPS (otomatis)</p>
                        </div>
                        <button type="button" class="text-gray-500 hover:text-gray-800" data-it-camera-close="1">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="p-5">
                        <video id="itCameraVideo" playsinline autoplay class="w-full rounded-lg bg-black"></video>
                        <div id="itCameraGeo" class="mt-2 text-[11px] text-gray-700 text-center">Menunggu lokasi...
                        </div>
                        <div class="mt-4 flex gap-2 justify-end">
                            <button type="button"
                                class="px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg text-sm"
                                data-it-camera-close="1">Tutup</button>
                            <button type="button" id="itCameraCaptureBtn"
                                class="px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white rounded-lg text-sm">
                                <i class="fas fa-circle-dot mr-2" aria-hidden="true"></i>Capture
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Ticket dashboard charts
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof Chart === 'undefined') return;

            if (typeof ChartDataLabels !== 'undefined') {
                try {
                    Chart.register(ChartDataLabels);
                } catch (e) {
                    // ignore
                }
            }

            const statusEl = document.getElementById('adminTicketStatusChart');
            const prEl = document.getElementById('adminTicketPriorityChart');
            if (!statusEl || !prEl) return;

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
        });

        // ====== REALTIME TICKET DASHBOARD ======
        // Polls api_ticket_stats.php every 20s (fallback) and uses SSE via
        // ticket_stream.php for instant notification of any change.
        document.addEventListener('DOMContentLoaded', function () {
            'use strict';

            var STATS_API = 'api_ticket_stats.php';
            var STREAM_URL = 'ticket_stream.php';
            var POLL_MS = 20000;   // polling fallback interval

            var _sse = null;
            var _sseOk = false;
            var _pollTimer = null;

            // --- Live indicator ---
            function setLiveStatus(ok) {
                var badge = document.getElementById('rt-live-badge');
                var dot = document.getElementById('rt-live-dot');
                if (badge) {
                    badge.style.backgroundColor = ok ? '#f0fdf4' : '#fffbeb';
                    badge.style.borderColor = ok ? '#bbf7d0' : '#fde68a';
                    badge.style.color = ok ? '#15803d' : '#b45309';
                    badge.title = ok ? 'Real-time aktif (SSE)' : 'Polling mode (20s)';
                }
                if (dot) {
                    dot.style.backgroundColor = ok ? '#22c55e' : '#f59e0b';
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
                    var ease = 1 - Math.pow(1 - p, 3); // cubic ease-out
                    el.textContent = Math.round(from + diff * ease);
                    if (p < 1) requestAnimationFrame(step);
                    else el.textContent = target;
                }
                requestAnimationFrame(step);
            }

            // --- Flash a card to indicate update ---
            function flashEl(el) {
                if (!el) return;
                el.style.transition = 'background-color 0.3s ease';
                el.style.backgroundColor = 'rgba(251,146,60,0.18)';
                setTimeout(function () { el.style.backgroundColor = ''; }, 1100);
            }

            // --- HTML escaping ---
            function escH(s) {
                return (s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            }

            // --- Badge helpers (mirrors PHP functions) ---
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
            function priorityBadgeClass(p) {
                var k = (p || '').toLowerCase().trim();
                if (k === 'low') return 'bg-gray-100 text-gray-700 border-gray-200';
                if (k === 'medium') return 'bg-yellow-50 text-yellow-800 border-yellow-200';
                if (k === 'high') return 'bg-orange-50 text-orange-700 border-orange-200';
                if (k === 'urgent') return 'bg-red-50 text-red-700 border-red-200';
                return 'bg-gray-50 text-gray-700 border-gray-200';
            }
            function fmtCode(code, createUser) {
                var y = (createUser && createUser.length >= 4) ? createUser.substring(0, 4) : new Date().getFullYear().toString();
                return 'ITCKT-' + y + '-' + String(parseInt(code, 10) || 0).padStart(6, '0');
            }

            // --- Apply fetched stats to DOM ---
            function applyData(data) {
                if (!data || !data.ok) return;
                var sc = data.status_counts || {};
                var pc = data.priority_counts || {};

                // Stat cards
                animateNum(document.getElementById('rt-total'), data.total || 0);
                animateNum(document.getElementById('rt-open'), sc['Open'] || 0);
                animateNum(document.getElementById('rt-inprogress'), sc['In Progress'] || 0);
                animateNum(document.getElementById('rt-done'), sc['Done'] || 0);
                animateNum(document.getElementById('rt-review'), sc['Review'] || 0);
                animateNum(document.getElementById('rt-closed'), sc['Closed'] || 0);
                animateNum(document.getElementById('rt-rejected'), sc['Reject'] || 0);

                var avgEl = document.getElementById('rt-avgtime');
                if (avgEl && data.avg_response_time && avgEl.textContent !== data.avg_response_time) {
                    avgEl.textContent = data.avg_response_time;
                }

                // Update Chart.js charts (Chart.getChart requires Chart.js 3+)
                if (typeof Chart !== 'undefined') {
                    try {
                        var sOrder = ['Open', 'In Progress', 'Review', 'Done', 'Reject', 'Closed'];
                        var sCh = Chart.getChart('adminTicketStatusChart');
                        if (sCh) {
                            sCh.data.datasets[0].data = sOrder.map(function (s) { return sc[s] || 0; });
                            sCh.update('active');
                        }
                        var pOrder = ['Low', 'Medium', 'High', 'Urgent'];
                        var pCh = Chart.getChart('adminTicketPriorityChart');
                        if (pCh) {
                            pCh.data.datasets[0].data = pOrder.map(function (p) { return pc[p] || 0; });
                            pCh.update('active');
                        }
                    } catch (e) { /* best-effort */ }
                }

                // Recent tickets table
                var tbody = document.getElementById('dash-recent-tbody');
                if (tbody && Array.isArray(data.recent_tickets)) {
                    var rows = data.recent_tickets;
                    if (!rows.length) {
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
                        flashEl(tbody.closest('div') || tbody);
                    }
                }
            }

            // --- Fetch fresh stats from API ---
            function refresh() {
                fetch(STATS_API + '?_=' + Date.now(), { credentials: 'same-origin' })
                    .then(function (r) { return r.ok ? r.json() : null; })
                    .then(function (d) {
                        if (d) {
                            applyData(d);
                            setLiveStatus(_sseOk);
                        }
                    })
                    .catch(function () { /* silent */ });
            }

            // --- SSE connection ---
            function connectSSE() {
                if (_sse) {
                    try { _sse.close(); } catch (e) { }
                    _sse = null;
                }
                if (typeof EventSource === 'undefined') return;

                try {
                    var es = new EventSource(STREAM_URL);
                    _sse = es;

                    es.addEventListener('hello', function () {
                        setLiveStatus(true);
                    });

                    es.addEventListener('dashboard_update', function () {
                        // Something changed → get fresh stats immediately
                        refresh();
                        // Pulse the live dot
                        var dot = document.getElementById('rt-live-dot');
                        if (dot) {
                            dot.style.transform = 'scale(2)';
                            setTimeout(function () { dot.style.transform = ''; }, 350);
                        }
                        // Also refresh the main ticket table
                        if (typeof window._refreshMainTicketTable === 'function') {
                            window._refreshMainTicketTable();
                        }
                    });

                    // 'timeout' → stream ended normally; browser auto-reconnects via EventSource
                    es.addEventListener('timeout', function () {
                        // Nothing to do — EventSource will reconnect automatically
                    });

                    es.onerror = function () {
                        setLiveStatus(false);
                        // Browser will auto-retry after ~3s; don't close explicitly
                    };
                } catch (e) {
                    setLiveStatus(false);
                }
            }

            // --- Boot ---
            refresh();           // immediate data load
            connectSSE();        // SSE for instant change detection
            _pollTimer = setInterval(refresh, POLL_MS);  // polling fallback untuk stat cards
        });

        // ====== REALTIME MAIN TICKET TABLE (auto-refresh tabel utama) ======
        // Dipisah dari DOMContentLoaded di atas agar loadPage sudah terdefinisi
        document.addEventListener('DOMContentLoaded', function () {

            // Expose fungsi refresh tabel utama ke scope global
            // agar bisa dipanggil dari SSE handler maupun polling
            // silent=true → tidak ada loading overlay / scroll (tidak terasa seperti refresh)
            window._refreshMainTicketTable = function () {
                if (typeof window.loadPage === 'function') {
                    var url = new URL(window.location.href);
                    var currentPage = parseInt(url.searchParams.get('page') || '1', 10);
                    var currentStatus = url.searchParams.get('status') || '';
                    window.loadPage(currentPage, currentStatus, true /* silent */);
                }
            };

            // Polling fallback setiap 15 detik untuk tabel utama (silent background refresh)
            setInterval(function () {
                if (!document.hidden) {
                    window._refreshMainTicketTable();
                }
            }, 15000);
        });

        // Hide loading overlay (prevent stuck overlay)
        document.addEventListener('DOMContentLoaded', function () {
            const loadingOverlay = document.getElementById('loadingOverlay');
            if (!loadingOverlay) return;
            setTimeout(function () {
                loadingOverlay.style.display = 'none';
            }, 300);
        });

        // AJAX pagination (progressive enhancement)
        document.addEventListener('DOMContentLoaded', function () {
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

            function updateDownloadLinkFromUrl(url) {
                const btn = document.getElementById('ticketDownloadReportBtn');
                if (!btn || !url) return;
                const params = new URLSearchParams(url.searchParams);
                params.delete('page');
                params.delete('action');

                // Keep status + q + date range for the report
                const reportParams = new URLSearchParams();
                const st = params.get('status');
                const q = params.get('q');
                const df = params.get('date_from') || params.get('from');
                const dt = params.get('date_to') || params.get('to');
                if (st) reportParams.set('status', st);
                if (q) reportParams.set('q', q);
                if (df) reportParams.set('date_from', df);
                if (dt) reportParams.set('date_to', dt);

                const qs = reportParams.toString();
                btn.setAttribute('href', 'download_ticket_report.php' + (qs ? ('?' + qs) : ''));
            }

            function showTableLoading() {
                const container = document.getElementById('tickets-table-container');
                if (!container) return;
                container.style.opacity = '0.4';
                container.style.pointerEvents = 'none';
                container.style.transition = 'opacity 0.15s ease';
                // Show spinner overlay
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

            // silent=true → auto-refresh background, tidak ada loading overlay / scroll
            function loadPage(pageNum, statusValue, silent) {
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
                fetchParams.set('action', 'ajax_get_admin_tickets');
                const fetchUrl = `${window.location.pathname}?${fetchParams.toString()}`;

                // Hanya tampilkan loading overlay jika bukan silent (auto-refresh)
                if (!silent) showTableLoading();

                fetch(fetchUrl)
                    .then((response) => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok: ' + response.status);
                        }
                        return response.json();
                    })
                    .then((data) => {
                        if (!silent) hideTableLoading();
                        if (data.error) {
                            if (!silent) alert('Error: ' + data.error);
                            return;
                        }

                        const tableContainer = document.getElementById('tickets-table-container');
                        const paginationContainer = document.getElementById('pagination-container');
                        if (!tableContainer || !paginationContainer) return;

                        const tableClass = 'min-w-full border border-gray-200 rounded-lg overflow-hidden';

                        if (silent) {
                            // ── SMART SILENT REFRESH ──────────────────────────────────────────────
                            // Sebelum replace DOM, simpan state semua input per Ticket_code
                            // sehingga ketikan / file yang sudah dipilih admin tidak hilang.

                            // 1) Kumpulkan state dari baris yang sedang aktif
                            const savedStates = {}; // key: ticket_code (string)
                            const activeEl = document.activeElement;

                            tableContainer.querySelectorAll('tr[data-ticket-code]').forEach(tr => {
                                const code = tr.dataset.ticketCode;
                                if (!code) return;

                                const jawabanTa = tr.querySelector('textarea[name="Jawaban_IT"]');
                                const statusSel = tr.querySelector('select[name="Status_Request"]');
                                const typeSel = tr.querySelector('select[name="Type_Pekerjaan"]');
                                const fileInput = tr.querySelector('input[type="file"][name="Photo_IT"]');

                                // Apakah baris ini sedang "aktif" (ada fokus / teks yang diubah / file staged)?
                                const isFocused = activeEl && tr.contains(activeEl);
                                const hasJawaban = jawabanTa && jawabanTa.value.trim() !== '';
                                const hasFile = fileInput && fileInput.files && fileInput.files.length > 0;

                                const isDirty = isFocused || hasJawaban || hasFile;

                                savedStates[code] = {
                                    isDirty,
                                    jawaban: jawabanTa ? jawabanTa.value : null,
                                    status: statusSel ? statusSel.value : null,
                                    type: typeSel ? typeSel.value : null,
                                    // File tidak bisa diklon, cukup catat nama agar tahu sudah dipilih
                                    hasFile,
                                    fileName: (fileInput && fileInput.files && fileInput.files[0]) ? fileInput.files[0].name : null,
                                };
                            });

                            // 2) Cek apakah ADA baris yang sedang dirty (admin sedang mengetik/upload)
                            const anyDirty = Object.values(savedStates).some(s => s.isDirty);

                            // 3) Parse HTML baru ke DOM sementara
                            const tempWrap = document.createElement('div');
                            tempWrap.innerHTML = '<table class="' + tableClass + '">' + (data.table_html || '') + '</table>';
                            const newTable = tempWrap.querySelector('table');

                            if (anyDirty) {
                                // Ada baris dirty: update baris secara selektif (row-by-row diffing)
                                const newRows = newTable
                                    ? Array.from(newTable.querySelectorAll('tr[data-ticket-code]'))
                                    : [];

                                newRows.forEach(newTr => {
                                    const code = newTr.dataset.ticketCode;
                                    const state = savedStates[code];

                                    if (state && state.isDirty) {
                                        // Jangan sentuh baris ini — admin sedang menggunakannya
                                        return;
                                    }

                                    // Update atau tambahkan baris ini ke tabel yang sudah ada
                                    const existingTr = tableContainer.querySelector('tr[data-ticket-code="' + code + '"]');
                                    if (existingTr) {
                                        // Restore state baris yang tidak dirty (select value mungkin berubah di server)
                                        if (state && state.status !== null) {
                                            const newStatusSel = newTr.querySelector('select[name="Status_Request"]');
                                            if (newStatusSel && state.status) newStatusSel.value = state.status;
                                        }
                                        existingTr.replaceWith(newTr);
                                    }
                                });

                                // Update header (thead) kalau ada perubahan
                                const existingThead = tableContainer.querySelector('thead');
                                const newThead = newTable ? newTable.querySelector('thead') : null;
                                if (existingThead && newThead) existingThead.replaceWith(newThead);

                            } else {
                                // Tidak ada yang dirty: aman untuk replace penuh dengan efek fade
                                tableContainer.style.transition = 'opacity 0.2s ease';
                                tableContainer.style.opacity = '0.7';
                                setTimeout(() => {
                                    tableContainer.innerHTML = '<table class="' + tableClass + '">' + (data.table_html || '') + '</table>';
                                    paginationContainer.innerHTML = data.pagination_html || '';
                                    tableContainer.style.opacity = '1';
                                    attachPaginationListeners();
                                }, 150);
                                return; // keluar dari blok .then, sudah di-handle
                            }

                            // Jika dirty-mode: update pagination tetap boleh
                            paginationContainer.innerHTML = data.pagination_html || '';
                            tableContainer.style.opacity = '1';
                            attachPaginationListeners();
                        } else {
                            tableContainer.innerHTML = '<table class="' + tableClass + '">' + (data.table_html || '') + '</table>';
                            paginationContainer.innerHTML = data.pagination_html || '';
                            // Scroll ke tabel hanya jika user memicu (bukan auto-refresh)
                            tableContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
                            attachPaginationListeners();
                        }

                        // Update URL, tab, dan download link
                        history.pushState({}, '', url.toString());
                        setActiveTab(url.searchParams.get('status') || '');
                        updateDownloadLinkFromUrl(url);

                        if (data.status_counts && typeof data.total_all_records !== 'undefined') {
                            updateTabCounts(data.status_counts, data.total_all_records);
                        }
                    })
                    .catch((err) => {
                        if (!silent) {
                            hideTableLoading();
                            console.error('Fetch error:', err);
                        }
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

            attachPaginationListeners();
            attachTabListeners();
            setActiveTab((new URL(window.location.href)).searchParams.get('status') || '');
            updateDownloadLinkFromUrl(new URL(window.location.href));

            // Expose loadPage untuk diakses SSE handler (didefinisikan di scope ini)
            window.loadPage = loadPage;

            // ===== ASSIGN TICKET FUNCTION =====
            window.assignTicket = function (ticketCode, mode, btnEl) {
                const fd = new FormData();
                fd.append('action', 'assign_ticket');
                fd.append('ticket_code', ticketCode);
                fd.append('mode', mode);

                const td = btnEl ? btnEl.closest('td') : null;
                if (btnEl) {
                    btnEl.disabled = true;
                    btnEl.style.opacity = '0.6';
                }

                fetch(window.location.pathname, { method: 'POST', body: fd, credentials: 'same-origin' })
                    .then(r => r.json())
                    .then(data => {
                        if (!data.ok) { alert('Gagal: ' + (data.error || 'Unknown error')); return; }
                        // Refresh tabel agar tombol update ke state terbaru
                        if (typeof window._refreshMainTicketTable === 'function') {
                            window._refreshMainTicketTable();
                        }
                    })
                    .catch(() => { if (btnEl) { btnEl.disabled = false; btnEl.style.opacity = ''; } });
            };

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
        });

        // Kamera + GPS stamp untuk upload Photo_IT (best-effort, support AJAX table)
        document.addEventListener('DOMContentLoaded', function () {
            const IT_IMAGE_TARGET_BYTES = 100 * 1024; // 100KB
            const IT_IMAGE_MAX_DIM = 1600; // px
            let itGeoWatchId = null;
            let itGeoLast = null;
            let itCameraStream = null;
            let itCameraTargetInputId = '';

            function itStartGeoWatch() {
                if (!navigator.geolocation) {
                    itGeoLast = { error: 'Geolocation tidak didukung browser.' };
                    return;
                }
                if (itGeoWatchId !== null) return;
                itGeoWatchId = navigator.geolocation.watchPosition(
                    (pos) => {
                        itGeoLast = {
                            lat: pos.coords.latitude,
                            lng: pos.coords.longitude,
                            acc: pos.coords.accuracy,
                            ts: pos.timestamp
                        };
                    },
                    (err) => {
                        itGeoLast = { error: (err && err.message) ? err.message : 'Gagal ambil lokasi.' };
                    },
                    { enableHighAccuracy: true, maximumAge: 5000, timeout: 15000 }
                );
            }

            function itStopGeoWatchSoon() {
                if (itGeoWatchId === null) return;
                window.setTimeout(() => {
                    try { navigator.geolocation.clearWatch(itGeoWatchId); } catch (e) { }
                    itGeoWatchId = null;
                }, 30000);
            }

            function itFormatDateTimeLocal(d) {
                const pad = (n) => String(n).padStart(2, '0');
                return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) +
                    ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
            }

            function itReplaceInputFile(fileInput, newFile) {
                if (!fileInput || !newFile) return;
                try {
                    const dt = new DataTransfer();
                    dt.items.add(newFile);
                    fileInput.files = dt.files;
                } catch (e) {
                    // ignore
                }
            }

            function itDelay(ms) {
                return new Promise((resolve) => setTimeout(resolve, ms));
            }

            async function itWaitGeoSnapshot(timeoutMs) {
                itStartGeoWatch();
                itStopGeoWatchSoon();
                const start = Date.now();
                while (Date.now() - start < timeoutMs) {
                    if (itGeoLast && (itGeoLast.lat || itGeoLast.error)) break;
                    await itDelay(120);
                }
                return itGeoLast;
            }

            async function itCompressImageToTarget(file, targetBytes, maxDim) {
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

            async function itStampGeoOnImage(file) {
                if (!file || !file.type || !file.type.startsWith('image/')) return file;
                if (file.type === 'image/gif') return file;

                const geo = await itWaitGeoSnapshot(2500);
                const nowText = itFormatDateTimeLocal(new Date());
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

                const scale = Math.min(1, IT_IMAGE_MAX_DIM / Math.max(originalWidth, originalHeight));
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
                const baseName = (file.name || 'photo_it').replace(/\.[^/.]+$/, '');
                const stampedFile = new File([stampedBlob], baseName + '.jpg', { type: 'image/jpeg', lastModified: Date.now() });
                return await itCompressImageToTarget(stampedFile, IT_IMAGE_TARGET_BYTES, IT_IMAGE_MAX_DIM);
            }

            function itRenderGeoText(targetEl) {
                if (!targetEl) return;
                if (itGeoLast && itGeoLast.lat) {
                    targetEl.textContent = `Lat: ${itGeoLast.lat.toFixed(6)}, Lng: ${itGeoLast.lng.toFixed(6)} (±${Math.round(itGeoLast.acc || 0)}m)`;
                } else if (itGeoLast && itGeoLast.error) {
                    targetEl.textContent = `Lokasi tidak tersedia: ${itGeoLast.error}`;
                } else {
                    targetEl.textContent = 'Menunggu lokasi...';
                }
            }

            async function itOpenCameraModal(targetInputId) {
                const modal = document.getElementById('itCameraModal');
                const video = document.getElementById('itCameraVideo');
                const geoEl = document.getElementById('itCameraGeo');
                if (!modal || !video) return;

                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    alert('Browser tidak mendukung akses kamera.');
                    return;
                }
                if (!window.isSecureContext && location.hostname !== 'localhost') {
                    alert('Akses kamera/GPS butuh HTTPS atau localhost.');
                    return;
                }

                // Close any existing
                await itCloseCameraModal();

                itCameraTargetInputId = String(targetInputId || '');
                itStartGeoWatch();
                itStopGeoWatchSoon();
                itRenderGeoText(geoEl);

                try {
                    const stream = await navigator.mediaDevices.getUserMedia({
                        video: { facingMode: { ideal: 'environment' } },
                        audio: false
                    });
                    itCameraStream = stream;
                    video.srcObject = stream;
                    await video.play();
                    modal.classList.remove('hidden');
                } catch (err) {
                    itCameraTargetInputId = '';
                    alert('Kamera tidak bisa dibuka. Pastikan izin kamera diaktifkan.');
                }
            }

            async function itCloseCameraModal() {
                const modal = document.getElementById('itCameraModal');
                const video = document.getElementById('itCameraVideo');
                if (modal) modal.classList.add('hidden');
                if (video) {
                    try { video.pause(); } catch (e) { }
                    video.srcObject = null;
                }
                if (itCameraStream) {
                    try { itCameraStream.getTracks().forEach((t) => t.stop()); } catch (e) { }
                }
                itCameraStream = null;
            }

            async function itCaptureFromModal() {
                const video = document.getElementById('itCameraVideo');
                const targetId = itCameraTargetInputId;
                const input = targetId ? document.getElementById(targetId) : null;
                if (!video || !input) return;
                if (!video.videoWidth || !video.videoHeight) {
                    alert('Kamera belum siap. Tunggu sebentar.');
                    return;
                }

                const geo = await itWaitGeoSnapshot(1500);
                const nowText = itFormatDateTimeLocal(new Date());
                const coordsLine = (geo && geo.lat)
                    ? `Lat:${geo.lat.toFixed(6)} Lng:${geo.lng.toFixed(6)} Acc:±${Math.round(geo.acc || 0)}m | ${nowText}`
                    : `Lokasi: tidak tersedia | ${nowText}`;

                const scale = Math.min(1, IT_IMAGE_MAX_DIM / Math.max(video.videoWidth, video.videoHeight));
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

                let capturedFile = new File([blob], `photo_it_${Date.now()}.jpg`, { type: 'image/jpeg', lastModified: Date.now() });
                capturedFile = await itCompressImageToTarget(capturedFile, IT_IMAGE_TARGET_BYTES, IT_IMAGE_MAX_DIM);

                input.dataset.processing = '1';
                itReplaceInputFile(input, capturedFile);
                input.dataset.processing = '0';

                await itCloseCameraModal();
                itCameraTargetInputId = '';
            }

            const tableContainer = document.getElementById('tickets-table-container');
            if (!tableContainer) return;

            // Close modal handlers
            document.querySelectorAll('[data-it-camera-close="1"]').forEach((el) => {
                el.addEventListener('click', (e) => {
                    e.preventDefault();
                    itCloseCameraModal();
                    itCameraTargetInputId = '';
                });
            });
            const capBtn = document.getElementById('itCameraCaptureBtn');
            if (capBtn) {
                capBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    itCaptureFromModal();
                });
            }

            tableContainer.addEventListener('click', (e) => {
                const btn = e.target && e.target.closest ? e.target.closest('button.js-it-camera-btn') : null;
                if (!btn) return;
                e.preventDefault();
                const targetId = btn.dataset.targetInput || '';
                itOpenCameraModal(targetId);
            });

            tableContainer.addEventListener('change', async (e) => {
                const input = e.target;
                if (!input || !input.classList || !input.classList.contains('js-it-photo-input')) return;
                if (input.dataset.processing === '1') return;

                const file = input.files && input.files[0] ? input.files[0] : null;
                if (!file) return;
                if (!file.type || !file.type.startsWith('image/')) return; // docs: skip

                input.dataset.processing = '1';
                try {
                    const stamped = await itStampGeoOnImage(file);
                    if (stamped && stamped !== file) {
                        itReplaceInputFile(input, stamped);
                    }
                } catch (err) {
                    // ignore
                } finally {
                    input.dataset.processing = '0';
                }
            });
        });
    </script>
</body>

</html>