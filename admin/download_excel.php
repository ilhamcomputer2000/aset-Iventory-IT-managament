<?php
// Download daftar asset IT (admin) as XLSX.

// Buffer output early to avoid accidental whitespace breaking XLSX headers.
if (ob_get_level() === 0) {
    ob_start();
}

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

require_once __DIR__ . '/../koneksi.php';

// Determine role (hosting-safe): session role can be empty, so fallback to DB.
$userRole = strtolower(trim((string) ($_SESSION['role'] ?? '')));
if ($userRole === '' && isset($_SESSION['user_id'])) {
    $stmt = $kon->prepare('SELECT role FROM users WHERE id = ? LIMIT 1');
    if ($stmt) {
        $uid = (int) $_SESSION['user_id'];
        $stmt->bind_param('i', $uid);
        if ($stmt->execute()) {
            $roleDb = null;
            $stmt->bind_result($roleDb);
            if ($stmt->fetch() && !empty($roleDb)) {
                $_SESSION['role'] = (string) $roleDb;
                $userRole = strtolower(trim((string) $roleDb));
            }
        }
        $stmt->close();
    }
}

if ($userRole !== 'super_admin') {
    header('Location: ../user/view.php');
    exit();
}

// Release session lock ASAP (prevents downloads stuck in loading)
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

@set_time_limit(300);
@ini_set('memory_limit', '512M');

// Polyfill for hosts without ext-fileinfo (mime_content_type).
// PhpSpreadsheet calls mime_content_type() while embedding images into XLSX.
if (!function_exists('mime_content_type')) {
    function mime_content_type($filename)
    {
        $filename = (string) $filename;
        if ($filename === '' || !is_file($filename)) {
            return false;
        }

        if (function_exists('getimagesize')) {
            $info = @getimagesize($filename);
            if (is_array($info) && !empty($info['mime'])) {
                return (string) $info['mime'];
            }
        }

        $ext = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
        switch ($ext) {
            case 'jpg':
            case 'jpeg':
                return 'image/jpeg';
            case 'png':
                return 'image/png';
            case 'gif':
                return 'image/gif';
            default:
                return 'application/octet-stream';
        }
    }
}

// PhpSpreadsheet bootstrap (hosting-safe)
$autoloadCandidates = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/vendor/autoload.php',
];

$autoloadPath = '';
foreach ($autoloadCandidates as $path) {
    if (is_file($path)) {
        $autoloadPath = $path;
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
    echo "Gagal download XLSX: ekstensi PHP 'zip' (ZipArchive) belum aktif.";
    exit();
}

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

function export_safe_cell($value): string
{
    if ($value === null) {
        return '';
    }
    return (string) $value;
}

function export_decode_riwayat($raw): string
{
    $riwayatData = html_entity_decode((string) $raw, ENT_QUOTES, 'UTF-8');
    $riwayatData = trim($riwayatData);
    if ($riwayatData === '') {
        return '';
    }

    $riwayatArray = json_decode($riwayatData, true);
    if (!is_array($riwayatArray) || count($riwayatArray) === 0) {
        return $riwayatData;
    }

    $out = '';
    foreach ($riwayatArray as $idx => $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $out .= 'Entry ' . ($idx + 1) . ":\n";
        $out .= '  Nama: ' . ($entry['nama'] ?? '-') . "\n";
        $out .= '  Jabatan: ' . ($entry['jabatan'] ?? '-') . "\n";
        $out .= '  Employee ID: ' . ($entry['empleId'] ?? ($entry['emplId'] ?? '-')) . "\n";
        $out .= '  Lokasi: ' . ($entry['lokasi'] ?? '-') . "\n";
        $out .= '  Tgl Serah: ' . ($entry['tgl_serah_terima'] ?? '-') . "\n";
        $out .= '  Tgl Kembali: ' . ($entry['tgl_pengembalian'] ?? '-') . "\n";
        $out .= '  Catatan: ' . ($entry['catatan'] ?? '-') . "\n\n";
    }

    return trim($out);
}

function export_attach_image_if_exists($sheet, string $absPath, string $coord, string $name): void
{
    if (!is_file($absPath)) {
        return;
    }

    $ext = strtolower(pathinfo($absPath, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif'], true)) {
        return;
    }

    $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
    $drawing->setName($name);
    $drawing->setDescription($name);
    $drawing->setPath($absPath);
    $drawing->setWidth(80);
    $drawing->setHeight(80);
    $drawing->setCoordinates($coord);
    $drawing->setWorksheet($sheet);
}

function export_normalize_photo_value($value): string
{
    if ($value === null) return '';
    $v = trim((string) $value);
    if ($v === '') return '';

    // Jika tersimpan sebagai JSON array, ambil elemen pertama.
    if ($v !== '' && ($v[0] ?? '') === '[') {
        $decoded = json_decode($v, true);
        if (is_array($decoded) && isset($decoded[0]) && is_string($decoded[0])) {
            $v = trim($decoded[0]);
        }
    }

    return $v;
}

function export_resolve_image_path(string $value, array $uploadsBases): string
{
    $v = trim($value);
    if ($v === '') return '';

    // Drop querystring (kalau value berupa URL).
    if (strpos($v, '?') !== false) {
        $v = (string) strtok($v, '?');
    }
    $v = rawurldecode($v);

    // Jika URL penuh, ambil path-nya.
    if (preg_match('#^https?://#i', $v)) {
        $path = (string) (parse_url($v, PHP_URL_PATH) ?? '');
        $v = $path !== '' ? $path : $v;
    }

    $v = str_replace('\\', '/', $v);
    $v = ltrim($v, '/');

    // Jika mengandung "uploads/", ambil bagian setelahnya.
    $pos = stripos($v, 'uploads/');
    if ($pos !== false) {
        $v = substr($v, $pos + strlen('uploads/'));
    }

    // Kandidat: path relatif apa adanya dan basename.
    $candidates = [];
    $candidates[] = $v;
    $base = basename($v);
    if ($base !== '' && $base !== $v) {
        $candidates[] = $base;
    }

    // Jika ternyata sudah absolute path di server.
    if (is_file($v)) {
        return (string) (realpath($v) ?: $v);
    }

    foreach ($uploadsBases as $uploadsDir) {
        if (!is_string($uploadsDir) || $uploadsDir === '') continue;
        foreach ($candidates as $cand) {
            $cand = trim((string) $cand);
            if ($cand === '') continue;
            $candFs = str_replace('/', DIRECTORY_SEPARATOR, $cand);
            $abs = rtrim($uploadsDir, '/\\') . DIRECTORY_SEPARATOR . $candFs;
            if (is_file($abs)) {
                return (string) (realpath($abs) ?: $abs);
            }
        }
    }

    return '';
}

try {
    $start_date = '';
    $end_date = '';
    $end_date_exclusive = '';

    $start_date_raw = isset($_GET['start_date']) ? trim((string)$_GET['start_date']) : '';
    $end_date_raw = isset($_GET['end_date']) ? trim((string)$_GET['end_date']) : '';

    if ($start_date_raw !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date_raw)) {
        $dt = DateTime::createFromFormat('Y-m-d', $start_date_raw);
        if ($dt && $dt->format('Y-m-d') === $start_date_raw) {
            $start_date = $start_date_raw;
        }
    }
    if ($end_date_raw !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date_raw)) {
        $dt = DateTime::createFromFormat('Y-m-d', $end_date_raw);
        if ($dt && $dt->format('Y-m-d') === $end_date_raw) {
            $end_date = $end_date_raw;
            $dt->modify('+1 day');
            $end_date_exclusive = $dt->format('Y-m-d');
        }
    }
    if ($start_date !== '' && $end_date !== '' && $start_date > $end_date) {
        [$start_date, $end_date] = [$end_date, $start_date];
        $dt = DateTime::createFromFormat('Y-m-d', $end_date);
        if ($dt) {
            $dt->modify('+1 day');
            $end_date_exclusive = $dt->format('Y-m-d');
        }
    }

    $where = ' WHERE 1=1';
    if ($start_date !== '') {
        $where .= " AND Waktu >= '" . $kon->real_escape_string($start_date) . " 00:00:00'";
    }
    if ($end_date_exclusive !== '') {
        $where .= " AND Waktu < '" . $kon->real_escape_string($end_date_exclusive) . " 00:00:00'";
    }

    $sql = "SELECT * FROM peserta" . $where . " ORDER BY id_peserta DESC";
    $result = $kon->query($sql);
    if (!$result) {
        throw new RuntimeException('Query gagal: ' . $kon->error);
    }

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    $columns = [
        'A1' => 'No',
        'B1' => 'Waktu',
        'C1' => 'Nama Barang',
        'D1' => 'Merek',
        'E1' => 'Type',
        'F1' => 'Serial Number',
        'G1' => 'Spesifikasi',
        'H1' => 'Kelengkapan Barang',
        'I1' => 'Kondisi Barang',
        'J1' => 'Riwayat Barang',
        'K1' => 'User yang Menggunakan Perangkat',
        'L1' => 'Jenis Barang',
        'M1' => 'Lokasi',
        'N1' => 'ID Karyawan',
        'O1' => 'Jabatan',
        'P1' => 'Status Barang',
        'Q1' => 'Status LOP',
        'R1' => 'Status Kelayakan Barang',
        'S1' => 'Photo Barang',
        'T1' => 'Photo Depan',
        'U1' => 'Photo Belakang',
        'V1' => 'Photo SN',
    ];
    foreach ($columns as $cell => $header) {
        $sheet->setCellValue($cell, $header);
    }

    $uploadsBases = [];
    $uploadsBases[] = (string) (realpath(__DIR__ . '/../uploads') ?: (__DIR__ . '/../uploads'));
    $uploadsBases[] = (string) (realpath(__DIR__ . '/uploads') ?: (__DIR__ . '/uploads'));
    if (!empty($_SERVER['DOCUMENT_ROOT'])) {
        $uploadsBases[] = (string) (realpath(rtrim((string) $_SERVER['DOCUMENT_ROOT'], '/\\') . '/uploads') ?: '');
    }
    $uploadsBases = array_values(array_filter(array_unique($uploadsBases)));

    $rowNum = 2;
    $no = 1;
    while ($row = $result->fetch_assoc()) {
        $sheet->setCellValue('A' . $rowNum, $no);
        $sheet->setCellValue('B' . $rowNum, export_safe_cell($row['Waktu'] ?? ''));
        $sheet->setCellValue('C' . $rowNum, export_safe_cell($row['Nama_Barang'] ?? ''));
        $sheet->setCellValue('D' . $rowNum, export_safe_cell($row['Merek'] ?? ''));
        $sheet->setCellValue('E' . $rowNum, export_safe_cell($row['Type'] ?? ''));
        $sheet->setCellValue('F' . $rowNum, export_safe_cell($row['Serial_Number'] ?? ''));
        $sheet->setCellValue('G' . $rowNum, export_safe_cell($row['Spesifikasi'] ?? ''));
        $sheet->setCellValue('H' . $rowNum, export_safe_cell($row['Kelengkapan_Barang'] ?? ''));
        $sheet->setCellValue('I' . $rowNum, export_safe_cell($row['Kondisi_Barang'] ?? ''));
        $sheet->setCellValue('J' . $rowNum, export_decode_riwayat($row['Riwayat_Barang'] ?? ''));
        $sheet->setCellValue('K' . $rowNum, export_safe_cell($row['User_Perangkat'] ?? ''));
        $sheet->setCellValue('L' . $rowNum, export_safe_cell($row['Jenis_Barang'] ?? ''));
        $sheet->setCellValue('M' . $rowNum, export_safe_cell($row['Lokasi'] ?? ''));
        $sheet->setCellValue('N' . $rowNum, export_safe_cell($row['Id_Karyawan'] ?? ''));
        $sheet->setCellValue('O' . $rowNum, export_safe_cell($row['Jabatan'] ?? ''));
        $sheet->setCellValue('P' . $rowNum, export_safe_cell($row['Status_Barang'] ?? ''));
        $sheet->setCellValue('Q' . $rowNum, export_safe_cell($row['Status_LOP'] ?? ''));
        $sheet->setCellValue('R' . $rowNum, export_safe_cell($row['Status_Kelayakan_Barang'] ?? ''));

        $sheet->getRowDimension($rowNum)->setRowHeight(80);

        $photoBarang = export_normalize_photo_value($row['Photo_Barang'] ?? '');
        if ($photoBarang !== '') {
            $abs = export_resolve_image_path($photoBarang, $uploadsBases);
            if ($abs !== '') {
                export_attach_image_if_exists($sheet, $abs, 'S' . $rowNum, 'PhotoBarang');
            }
        }
        $photoDepan = export_normalize_photo_value($row['Photo_Depan'] ?? '');
        if ($photoDepan !== '') {
            $abs = export_resolve_image_path($photoDepan, $uploadsBases);
            if ($abs !== '') {
                export_attach_image_if_exists($sheet, $abs, 'T' . $rowNum, 'PhotoDepan');
            }
        }
        $photoBelakang = export_normalize_photo_value($row['Photo_Belakang'] ?? '');
        if ($photoBelakang !== '') {
            $abs = export_resolve_image_path($photoBelakang, $uploadsBases);
            if ($abs !== '') {
                export_attach_image_if_exists($sheet, $abs, 'U' . $rowNum, 'PhotoBelakang');
            }
        }
        $photoSn = export_normalize_photo_value($row['Photo_SN'] ?? '');
        if ($photoSn !== '') {
            $abs = export_resolve_image_path($photoSn, $uploadsBases);
            if ($abs !== '') {
                export_attach_image_if_exists($sheet, $abs, 'V' . $rowNum, 'PhotoSN');
            }
        }

        $rowNum++;
        $no++;
    }
    $result->free();

    foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R'] as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    foreach (['S', 'T', 'U', 'V'] as $col) {
        $sheet->getColumnDimension($col)->setWidth(14);
    }

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $filename = 'Daftar_Asset_IT_' . date('Y-m-d_H-i-s') . '.xlsx';

    while (ob_get_level() > 0) {
        @ob_end_clean();
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('X-Content-Type-Options: nosniff');

    $writer->save('php://output');
    exit();
} catch (Throwable $e) {
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo 'Gagal download XLSX: ' . $e->getMessage();
    exit();
}
