<?php
// Download ticket report (admin) as CSV
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$rawRole = isset($_SESSION['role']) ? (string)$_SESSION['role'] : 'user';
$user_role = strtolower(trim($rawRole));
if ($user_role !== 'super_admin') {
    header('Location: ../user/ticket.php');
    exit();
}

// Release session lock ASAP (prevents downloads stuck in loading)
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

require_once __DIR__ . '/../koneksi.php';

// Best-effort runtime tuning for shared hosting
@set_time_limit(300);
@ini_set('memory_limit', '512M');

// PhpSpreadsheet bootstrap (hosting-safe)
$autoloadCandidates = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/vendor/autoload.php',
];
$autoloadPath = '';
foreach ($autoloadCandidates as $p) {
    if (is_file($p)) {
        $autoloadPath = $p;
        break;
    }
}
if ($autoloadPath === '') {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Gagal download XLSX: composer vendor belum ter-upload (autoload.php tidak ditemukan).";
    exit();
}
require_once $autoloadPath;

if (!class_exists('ZipArchive')) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Gagal download XLSX: ekstensi PHP 'zip' (ZipArchive) belum aktif di hosting.";
    exit();
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Make hosting errors visible (avoid silent corrupt downloads)
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

function ticket_admin_status_list(): array {
    return ['Open', 'In Progress', 'Review', 'Done', 'Reject', 'Closed'];
}

function ticket_admin_is_allowed_status(string $status): bool {
    return in_array($status, ticket_admin_status_list(), true);
}

function ticket_admin_format_code(int $ticketCode, ?string $createUser): string {
    $year = (string)date('Y');
    if ($createUser) {
        $ts = strtotime($createUser);
        if ($ts !== false) {
            $year = date('Y', $ts);
        }
    }
    return 'ITCKT-' . $year . '-' . str_pad((string)$ticketCode, 6, '0', STR_PAD_LEFT);
}

// Filters
$statusFilter = '';
if (isset($_GET['status'])) {
    $requestedStatus = trim((string)$_GET['status']);
    if ($requestedStatus !== '' && strcasecmp($requestedStatus, 'all') !== 0 && ticket_admin_is_allowed_status($requestedStatus)) {
        $statusFilter = $requestedStatus;
    }
}

$searchQuery = '';
if (isset($_GET['q'])) {
    $searchQuery = trim((string)$_GET['q']);
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
        $searchTicketCodeTerm = $m[1];
    }
}

$searchLikeText = '%' . $searchQuery . '%';
$searchLikeTicketCode = '%' . $searchTicketCodeTerm . '%';

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

// Photo_IT column may not exist on older DB
$hasPhotoItColumn = false;
$resCol = $kon->query("SHOW COLUMNS FROM `ticket` LIKE 'Photo_IT'");
if ($resCol && $resCol->num_rows > 0) {
    $hasPhotoItColumn = true;
}
if ($resCol) {
    $resCol->free();
}

$sql = 'SELECT `Ticket_code`, `Id_Karyawan`, `Nama_User`, `Divisi_User`, `Jabatan_User`, `Region`, `Subject`, `Kategori_Masalah`, `Priority`, `Status_Request`, `Type_Pekerjaan`, `Create_User`, `Create_By_User`, `Deskripsi_Masalah`, `Foto_Ticket`, `Document`, `Jawaban_IT`'
    . ($hasPhotoItColumn ? ', `Photo_IT`' : '')
    . ' FROM `ticket` WHERE 1=1';

$types = '';
$params = [];

if ($statusFilter !== '') {
    $sql .= ' AND `Status_Request` = ?';
    $types .= 's';
    $params[] = $statusFilter;
}

if ($hasSearch) {
    $sql .= ' AND ' . $ticketAdminSearchWhereSql;
    $types .= 'ssssssssssss';
    $params[] = $searchLikeTicketCode;
    for ($i = 0; $i < 11; $i++) {
        $params[] = $searchLikeText;
    }
}

$sql .= ' ORDER BY `Ticket_code` DESC';

$stmt = $kon->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo 'Prepare gagal.';
    exit();
}

if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}

if (!$stmt->execute()) {
    http_response_code(500);
    echo 'Query gagal.';
    exit();
}

$res = $stmt->get_result();

// Session already closed above.

try {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

$headers = [
    'Ticket Code',
    'Ticket Number',
    'Created',
    'Create_By_User',
    'ID Karyawan',
    'Nama User',
    'Divisi',
    'Jabatan',
    'Region',
    'Subject',
    'Kategori',
    'Priority',
    'Status',
    'Type Pekerjaan',
    'Deskripsi',
    'Foto_Ticket',
    'Document',
    'Jawaban_IT',
];
if ($hasPhotoItColumn) {
    $headers[] = 'Photo_IT';
}

$sheet->fromArray([$headers], null, 'A1', true);
$sheet->getStyle('1:1')->getFont()->setBold(true);

$rowNum = 2;
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $codeInt = (int)($row['Ticket_code'] ?? 0);
        $created = isset($row['Create_User']) ? (string)$row['Create_User'] : '';
        $codeDisplay = ticket_admin_format_code($codeInt, $created);

        $values = [
            $codeDisplay,
            (string)$codeInt,
            $created,
            (string)($row['Create_By_User'] ?? ''),
            (string)($row['Id_Karyawan'] ?? ''),
            (string)($row['Nama_User'] ?? ''),
            (string)($row['Divisi_User'] ?? ''),
            (string)($row['Jabatan_User'] ?? ''),
            (string)($row['Region'] ?? ''),
            (string)($row['Subject'] ?? ''),
            (string)($row['Kategori_Masalah'] ?? ''),
            (string)($row['Priority'] ?? ''),
            (string)($row['Status_Request'] ?? ''),
            (string)($row['Type_Pekerjaan'] ?? ''),
            (string)($row['Deskripsi_Masalah'] ?? ''),
            (string)($row['Foto_Ticket'] ?? ''),
            (string)($row['Document'] ?? ''),
            (string)($row['Jawaban_IT'] ?? ''),
        ];

        if ($hasPhotoItColumn) {
            $values[] = (string)($row['Photo_IT'] ?? '');
        }

        $sheet->fromArray([$values], null, 'A' . $rowNum, true);
        $rowNum++;
    }
}

// NOTE: Auto-size can be extremely slow on shared hosting.

$filename = 'Ticket_Report_Admin_' . date('Y-m-d_H-i-s') . '.xlsx';

// Prevent corrupted XLSX due to buffered output
while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->setPreCalculateFormulas(false);
$tmpDir = sys_get_temp_dir();
if (is_dir($tmpDir) && is_writable($tmpDir)) {
    $writer->setUseDiskCaching(true, $tmpDir);
}
$writer->save('php://output');

    $stmt->close();
    exit();
} catch (Throwable $e) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Gagal download XLSX: ' . $e->getMessage();
    exit();
} finally {
    restore_error_handler();
}
