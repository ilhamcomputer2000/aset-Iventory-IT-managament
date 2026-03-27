<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// [SEMUA BACKEND LOGIC PHP TETAP SAMA - JANGAN DIUBAH]
// Hosting hardening: jangan fatal jika app_url.php belum ter-upload.
$__appUrlPath = __DIR__ . '/../app_url.php';
if (is_file($__appUrlPath)) {
    require_once $__appUrlPath;
} else {
    if (!function_exists('app_base_path_from_docroot')) {
        function app_base_path_from_docroot(): string {
            $scriptName = (string)($_SERVER['SCRIPT_NAME'] ?? ($_SERVER['PHP_SELF'] ?? ''));
            if ($scriptName === '') return '';

            $scriptWebDir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
            if ($scriptWebDir === '' || $scriptWebDir === '.' || $scriptWebDir === '/') {
                $scriptWebDir = '';
            }

            // Proyek ini biasanya punya folder /admin dan /user.
            // Jika script berada di salah satunya, base path harus naik 1 level.
            if ($scriptWebDir !== '' && preg_match('#/(user|admin)$#', $scriptWebDir)) {
                $scriptWebDir = (string)preg_replace('#/(user|admin)$#', '', $scriptWebDir);
                $scriptWebDir = rtrim($scriptWebDir, '/');
                if ($scriptWebDir === '' || $scriptWebDir === '/') {
                    $scriptWebDir = '';
                }
            }

            return $scriptWebDir;
        }
    }
    if (!function_exists('app_abs_path')) {
        function app_abs_path(string $path): string {
            $base = app_base_path_from_docroot();
            $p = '/' . ltrim($path, '/');
            return $base . $p;
        }
    }
    if (!function_exists('app_base_url')) {
        function app_base_url(): string {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = (string)($_SERVER['HTTP_HOST'] ?? '');
            if ($host === '') {
                return app_abs_path('');
            }
            return $scheme . '://' . $host . app_abs_path('');
        }
    }
}

// Hosting hardening: autoload best-effort (QR akan nonaktif jika vendor tidak ada).
$__autoloadLoaded = false;
$__autoloadCandidates = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/vendor/autoload.php',
];
foreach ($__autoloadCandidates as $__autoloadPath) {
    if (is_file($__autoloadPath)) {
        require_once $__autoloadPath;
        $__autoloadLoaded = true;
        break;
    }
}

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

function generateQrCode($data) {
    // Jika vendor/autoload.php tidak ada di hosting, jangan bikin fatal.
    if (!class_exists(QrCode::class) || !class_exists(PngWriter::class)) {
        return '';
    }
    $qrCode = new QrCode($data);
    $qrCode->setSize(150);
    $qrCode->setMargin(10);
    $writer = new PngWriter();
    $result = $writer->write($qrCode);
    $tempDir = __DIR__ . '/../temp';
    if (!is_dir($tempDir)) {
        @mkdir($tempDir, 0777, true);
    }
    $fileName = uniqid('', true) . '.png';
    $fsPath = $tempDir . '/' . $fileName;
    file_put_contents($fsPath, $result->getString());
    return app_abs_path('temp/' . $fileName);
}

// Mulai session
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . app_abs_path('login'));
    exit();
}

// FIX: Role Check - Admin only untuk index.php, user redirect ke view.php
$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : 'user';  // Default 'user' jika tidak ada role
if ($user_role !== 'user') {
    header('Location: ' . app_abs_path('dashboard_admin'));
    exit();
}

// Include koneksi database
include "../koneksi.php";

// NOTE: Halaman user ini view-only. Aksi create/update/delete sengaja dimatikan.

// PERBAIKAN: Hapus $conn (gunakan $kon konsisten dari koneksi.php, hindari konflik)
if (mysqli_connect_error()) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

// Handle AJAX request untuk pagination - MUST BE BEFORE ANY HTML OUTPUT
if (isset($_GET['action']) && $_GET['action'] === 'ajax_get_assets') {
    header('Content-Type: application/json; charset=utf-8');
    
    $ajax_status_filter = isset($_GET['status_filter']) ? trim($_GET['status_filter']) : '';
    $ajax_category_filter = isset($_GET['kategori']) ? trim($_GET['kategori']) : '';
    $ajax_search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
    $ajax_brand_filter = isset($_GET['brand_filter']) ? trim($_GET['brand_filter']) : '';
    $ajax_status_lop_filter = isset($_GET['status_lop_filter']) ? trim($_GET['status_lop_filter']) : '';
    $ajax_status_kelayakan_filter = isset($_GET['status_kelayakan_filter']) ? trim($_GET['status_kelayakan_filter']) : '';
    $ajax_start_date = '';
    $ajax_end_date = '';
    $ajax_end_date_exclusive = '';

    $ajax_start_date_raw = isset($_GET['start_date']) ? trim((string)$_GET['start_date']) : '';
    $ajax_end_date_raw = isset($_GET['end_date']) ? trim((string)$_GET['end_date']) : '';

    if ($ajax_start_date_raw !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $ajax_start_date_raw)) {
        $dt = DateTime::createFromFormat('Y-m-d', $ajax_start_date_raw);
        if ($dt && $dt->format('Y-m-d') === $ajax_start_date_raw) {
            $ajax_start_date = $ajax_start_date_raw;
        }
    }
    if ($ajax_end_date_raw !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $ajax_end_date_raw)) {
        $dt = DateTime::createFromFormat('Y-m-d', $ajax_end_date_raw);
        if ($dt && $dt->format('Y-m-d') === $ajax_end_date_raw) {
            $ajax_end_date = $ajax_end_date_raw;
            $dt->modify('+1 day');
            $ajax_end_date_exclusive = $dt->format('Y-m-d');
        }
    }
    if ($ajax_start_date !== '' && $ajax_end_date !== '' && $ajax_start_date > $ajax_end_date) {
        [$ajax_start_date, $ajax_end_date] = [$ajax_end_date, $ajax_start_date];
        $dt = DateTime::createFromFormat('Y-m-d', $ajax_end_date);
        if ($dt) {
            $dt->modify('+1 day');
            $ajax_end_date_exclusive = $dt->format('Y-m-d');
        }
    }
    $ajax_view_mode = isset($_GET['view_mode']) ? trim($_GET['view_mode']) : 'table';
    if (!in_array($ajax_view_mode, ['table', 'grid'], true)) {
        $ajax_view_mode = 'table';
    }
    
    // Items per page (AJAX) - allowed: 5, 10, 20
    $ajax_per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 10;
    if (!in_array($ajax_per_page, [5, 10, 20])) {
        $ajax_per_page = 10;
    }
    $ajax_page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $ajax_page = max(1, $ajax_page);
    
    $ajax_limit = $ajax_per_page;
    $ajax_offset = ($ajax_page - 1) * $ajax_limit;
    
    // Count query
    $ajax_count_sql = "SELECT COUNT(id_peserta) FROM peserta WHERE 1=1";
    if (!empty($ajax_category_filter)) {
        $ajax_count_sql .= " AND Nama_Barang = '" . mysqli_real_escape_string($kon, $ajax_category_filter) . "'";
    }
    if (!empty($ajax_search_query)) {
        $ajax_count_sql .= " AND (Nama_Barang LIKE '%" . mysqli_real_escape_string($kon, $ajax_search_query) . "%' OR Merek LIKE '%" . mysqli_real_escape_string($kon, $ajax_search_query) . "%' OR Type LIKE '%" . mysqli_real_escape_string($kon, $ajax_search_query) . "%' OR Serial_Number LIKE '%" . mysqli_real_escape_string($kon, $ajax_search_query) . "%')";
    }
    if (!empty($ajax_status_filter)) {
        $ajax_count_sql .= " AND Status_Barang = '" . mysqli_real_escape_string($kon, $ajax_status_filter) . "'";
    }
    if (!empty($ajax_brand_filter)) {
        $ajax_count_sql .= " AND Merek = '" . mysqli_real_escape_string($kon, $ajax_brand_filter) . "'";
    }
    if (!empty($ajax_status_lop_filter)) {
        $ajax_count_sql .= " AND Status_LOP = '" . mysqli_real_escape_string($kon, $ajax_status_lop_filter) . "'";
    }
    if (!empty($ajax_status_kelayakan_filter)) {
        $ajax_count_sql .= " AND Status_Kelayakan_Barang = '" . mysqli_real_escape_string($kon, $ajax_status_kelayakan_filter) . "'";
    }

    if (!empty($ajax_start_date)) {
        $ajax_count_sql .= " AND Waktu >= '" . mysqli_real_escape_string($kon, $ajax_start_date) . " 00:00:00'";
    }
    if (!empty($ajax_end_date_exclusive)) {
        $ajax_count_sql .= " AND Waktu < '" . mysqli_real_escape_string($kon, $ajax_end_date_exclusive) . " 00:00:00'";
    }
    
    $ajax_count_result = mysqli_query($kon, $ajax_count_sql);
    $ajax_count_row = mysqli_fetch_row($ajax_count_result);
    $ajax_total_filtered = $ajax_count_row[0] ?? 0;
    $ajax_total_pages = $ajax_total_filtered > 0 ? ceil($ajax_total_filtered / $ajax_limit) : 1;
    $ajax_page = min($ajax_page, $ajax_total_pages);
    $ajax_offset = ($ajax_page - 1) * $ajax_limit;
    
    // Data query
    $ajax_sql = "SELECT * FROM peserta WHERE 1=1";
    if (!empty($ajax_category_filter)) {
        $ajax_sql .= " AND Nama_Barang = '" . mysqli_real_escape_string($kon, $ajax_category_filter) . "'";
    }
    if (!empty($ajax_search_query)) {
        $ajax_sql .= " AND (Nama_Barang LIKE '%" . mysqli_real_escape_string($kon, $ajax_search_query) . "%' OR Merek LIKE '%" . mysqli_real_escape_string($kon, $ajax_search_query) . "%' OR Type LIKE '%" . mysqli_real_escape_string($kon, $ajax_search_query) . "%' OR Serial_Number LIKE '%" . mysqli_real_escape_string($kon, $ajax_search_query) . "%')";
    }
    if (!empty($ajax_status_filter)) {
        $ajax_sql .= " AND Status_Barang = '" . mysqli_real_escape_string($kon, $ajax_status_filter) . "'";
    }
    if (!empty($ajax_brand_filter)) {
        $ajax_sql .= " AND Merek = '" . mysqli_real_escape_string($kon, $ajax_brand_filter) . "'";
    }
    if (!empty($ajax_status_lop_filter)) {
        $ajax_sql .= " AND Status_LOP = '" . mysqli_real_escape_string($kon, $ajax_status_lop_filter) . "'";
    }
    if (!empty($ajax_status_kelayakan_filter)) {
        $ajax_sql .= " AND Status_Kelayakan_Barang = '" . mysqli_real_escape_string($kon, $ajax_status_kelayakan_filter) . "'";
    }

    if (!empty($ajax_start_date)) {
        $ajax_sql .= " AND Waktu >= '" . mysqli_real_escape_string($kon, $ajax_start_date) . " 00:00:00'";
    }
    if (!empty($ajax_end_date_exclusive)) {
        $ajax_sql .= " AND Waktu < '" . mysqli_real_escape_string($kon, $ajax_end_date_exclusive) . " 00:00:00'";
    }
    $ajax_sql .= " ORDER BY id_peserta DESC LIMIT $ajax_limit OFFSET $ajax_offset";
    
    $ajax_result = mysqli_query($kon, $ajax_sql);
    if (!$ajax_result) {
        http_response_code(500);
        echo json_encode(['error' => 'Query failed: ' . mysqli_error($kon)]);
        exit();
    }
    
    // Build HTML for current view mode
    $ajax_table_html = '';
    $ajax_grid_html = '';

    if ($ajax_view_mode === 'table') {
        $ajax_table_html = '<thead class="bg-gradient-to-r from-gray-800 to-gray-900 text-white">
                            <tr>
                                <th class="px-4 py-4 text-left font-semibold sticky-col col-no">No</th>
                                <th class="px-4 py-4 text-left font-semibold sticky-col col-waktu">Waktu</th>
                                <th class="px-4 py-4 text-left font-semibold sticky-col col-createby">Create By</th>
                                <th class="px-4 py-4 text-left font-semibold sticky-col col-namabarang">Nama Barang</th>
                                <th class="px-4 py-4 text-left font-semibold col-nomoraset">Nomor Aset</th>
                                <th class="px-4 py-4 text-left font-semibold">Merek</th>
                                <th class="px-4 py-4 text-left font-semibold">Type</th>
                                <th class="px-4 py-4 text-left font-semibold">Serial Number</th>
                                <th class="px-4 py-4 text-left font-semibold">Spesifikasi</th>
                                <th class="px-4 py-4 text-left font-semibold">User Perangkat</th>
                                <th class="px-4 py-4 text-left font-semibold">Lokasi</th>
                                <th class="px-4 py-4 text-left font-semibold">Employee ID</th>
                                <th class="px-4 py-4 text-left font-semibold">Jabatan</th>
                                <th class="px-4 py-4 text-left font-semibold">Jenis Barang</th>
                                <th class="px-4 py-4 text-left font-semibold">Status Barang</th>
                                <th class="px-4 py-4 text-left font-semibold">Status LOP</th>
                                <th class="px-4 py-4 text-left font-semibold">Layak/Tidak</th>
                                <th class="px-4 py-4 text-left font-semibold">Photo Barang Lengkap</th>
                                <th class="px-4 py-4 text-left font-semibold">Photo Depan</th>
                                <th class="px-4 py-4 text-left font-semibold">Photo Belakang</th>
                                <th class="px-4 py-4 text-left font-semibold">Photo SN</th>
                                <th class="px-4 py-4 text-left font-semibold">Harga Barang</th>
                                <th class="px-4 py-4 text-left font-semibold">Tahun Rilis</th>
                                <th class="px-4 py-4 text-left font-semibold">Waktu Pembelian</th>
                                <th class="px-4 py-4 text-left font-semibold">Nama Vendor</th>
                                <th class="px-4 py-4 text-left font-semibold">Kategori Pembelian</th>
                                <th class="px-4 py-4 text-left font-semibold">Link Pembelian</th>
                                <th class="px-4 py-4 text-center font-semibold sticky-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>';

        $ajax_no = $ajax_offset + 1;
        while ($ajax_data = mysqli_fetch_array($ajax_result)) {
            $ajax_rowClass = ($ajax_no % 2 == 0) ? 'bg-white' : 'bg-gray-50/50';

            $ajax_kategori_raw = trim((string)($ajax_data['Kategori_Pembelian'] ?? ''));
            $ajax_link_raw = trim((string)($ajax_data['Link_Pembelian'] ?? ''));
            if (strcasecmp($ajax_kategori_raw, 'Online') !== 0) {
                $ajax_link_cell = '-';
            } elseif ($ajax_link_raw === '') {
                $ajax_link_cell = '-';
            } elseif (preg_match('~^https?://~i', $ajax_link_raw)) {
                $ajax_link_href = htmlspecialchars($ajax_link_raw, ENT_QUOTES);
                $ajax_link_title = htmlspecialchars($ajax_link_raw, ENT_QUOTES);
                $ajax_link_cell = '<a href="' . $ajax_link_href . '" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline" title="' . $ajax_link_title . '"><i class="fas fa-link"></i></a>';
            } else {
                $ajax_link_cell = '-';
            }

            $ajax_table_html .= '<tr class="table-row border-b border-gray-100 ' . $ajax_rowClass . ' hover:bg-gradient-to-r hover:from-blue-50 hover:to-purple-50">
                                <td class="px-4 py-4 font-medium text-gray-900 sticky-col col-no">' . $ajax_no . '</td>
                                <td class="px-4 py-4 text-gray-700 sticky-col col-waktu">' . htmlspecialchars($ajax_data["Waktu"] ?? '') . '</td>
                                <td class="px-4 py-4 text-gray-700 sticky-col col-createby">' . htmlspecialchars((isset($ajax_data['Create_By']) && trim((string)$ajax_data['Create_By']) !== '') ? $ajax_data['Create_By'] : '-') . '</td>
                                <td class="px-4 py-4 font-medium text-gray-900 max-w-xs sticky-col col-namabarang">' . htmlspecialchars($ajax_data["Nama_Barang"] ?? '') . '</td>
                                <td class="px-4 py-4 text-gray-700 font-mono text-sm col-nomoraset">' . htmlspecialchars($ajax_data["Nomor_Aset"] ?? '') . '</td>
                                <td class="px-4 py-4 text-gray-700">' . htmlspecialchars($ajax_data["Merek"] ?? '') . '</td>
                                <td class="px-4 py-4 text-gray-700">' . htmlspecialchars($ajax_data["Type"] ?? '') . '</td>
                                <td class="px-4 py-4 text-gray-700 font-mono text-sm">' . htmlspecialchars($ajax_data["Serial_Number"] ?? '') . '</td>
                                <td class="px-4 py-4 text-gray-700 max-w-xs">
                                    <div class="line-clamp-2 overflow-hidden" title="' . htmlspecialchars($ajax_data["Spesifikasi"] ?? '') . '">
                                        ' . htmlspecialchars($ajax_data["Spesifikasi"] ?? '') . '
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-gray-700 max-w-xs">
                                    <div class="line-clamp-2 overflow-hidden" title="' . htmlspecialchars($ajax_data["User_Perangkat"] ?? '') . '">
                                        ' . htmlspecialchars($ajax_data["User_Perangkat"] ?? '') . '
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-gray-700 max-w-xs">
                                    <div class="line-clamp-2 overflow-hidden" title="' . htmlspecialchars($ajax_data["Lokasi"] ?? '') . '">
                                        ' . htmlspecialchars($ajax_data["Lokasi"] ?? '') . '
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-gray-700">' . htmlspecialchars($ajax_data["Id_Karyawan"] ?? '') . '</td>
                                <td class="px-4 py-4 text-gray-700">' . htmlspecialchars($ajax_data["Jabatan"] ?? '') . '</td>
                                <td class="px-4 py-4">
                                    <span class="status-badge ' . getStatusBadgeClass($ajax_data['Jenis_Barang'] ?? '') . '">
                                        ' . htmlspecialchars($ajax_data['Jenis_Barang'] ?? '') . '
                                    </span>
                                </td>
                                <td class="px-4 py-4">
                                    <span class="status-badge ' . getStatusBadgeClass($ajax_data['Status_Barang'] ?? '') . '">
                                        ' . htmlspecialchars($ajax_data['Status_Barang'] ?? '') . '
                                    </span>
                                </td>
                                <td class="px-4 py-4">
                                    <span class="status-badge ' . getStatusBadgeClass($ajax_data['Status_LOP'] ?? '') . '">
                                        ' . htmlspecialchars($ajax_data['Status_LOP'] ?? '') . '
                                    </span>
                                </td>
                                <td class="px-4 py-4">
                                    <span class="status-badge ' . getStatusBadgeClass($ajax_data['Status_Kelayakan_Barang'] ?? '') . '">
                                        ' . htmlspecialchars($ajax_data['Status_Kelayakan_Barang'] ?? '') . '
                                    </span>
                                </td>
                                <td class="px-4 py-4">
                                    ' . (!empty($ajax_data['Photo_Barang']) ? '<div class="w-16 h-16 rounded-lg overflow-hidden shadow-md"><img src="../uploads/' . htmlspecialchars($ajax_data['Photo_Barang']) . '" class="w-full h-full object-cover"></div>' : '<div class="w-16 h-16 bg-gray-200 rounded-lg flex items-center justify-center"><i class="fas fa-image text-gray-400"></i></div>') . '
                                </td>
                                <td class="px-4 py-4">
                                    ' . (!empty($ajax_data['Photo_Depan']) ? '<div class="w-16 h-16 rounded-lg overflow-hidden shadow-md"><img src="../uploads/' . htmlspecialchars($ajax_data['Photo_Depan']) . '" class="w-full h-full object-cover"></div>' : '<div class="w-16 h-16 bg-gray-200 rounded-lg flex items-center justify-center"><i class="fas fa-image text-gray-400"></i></div>') . '
                                </td>
                                <td class="px-4 py-4">
                                    ' . (!empty($ajax_data['Photo_Belakang']) ? '<div class="w-16 h-16 rounded-lg overflow-hidden shadow-md"><img src="../uploads/' . htmlspecialchars($ajax_data['Photo_Belakang']) . '" class="w-full h-full object-cover"></div>' : '<div class="w-16 h-16 bg-gray-200 rounded-lg flex items-center justify-center"><i class="fas fa-image text-gray-400"></i></div>') . '
                                </td>
                                <td class="px-4 py-4">
                                    ' . (!empty($ajax_data['Photo_SN']) ? '<div class="w-16 h-16 rounded-lg overflow-hidden shadow-md"><img src="../uploads/' . htmlspecialchars($ajax_data['Photo_SN']) . '" class="w-full h-full object-cover"></div>' : '<div class="w-16 h-16 bg-gray-200 rounded-lg flex items-center justify-center"><i class="fas fa-image text-gray-400"></i></div>') . '
                                </td>
                                <td class="px-4 py-4 text-gray-700 font-medium">' . htmlspecialchars(formatRupiah($ajax_data["Harga_Barang"] ?? '')) . '</td>
                                <td class="px-4 py-4 text-gray-700">' . htmlspecialchars($ajax_data["Tahun_Rilis"] ?? '-') . '</td>
                                <td class="px-4 py-4 text-gray-700">' . htmlspecialchars($ajax_data["Waktu_Pembelian"] ?? '-') . '</td>
                                <td class="px-4 py-4 text-gray-700">' . htmlspecialchars($ajax_data["Nama_Toko_Pembelian"] ?? '-') . '</td>
                                <td class="px-4 py-4 text-gray-700">' . htmlspecialchars($ajax_data["Kategori_Pembelian"] ?? '-') . '</td>
                                <td class="px-4 py-4 text-gray-700">' . $ajax_link_cell . '</td>
                                <td class="px-4 py-4 sticky-right">
                                    <div class="flex items-center justify-center space-x-2">
                                        <a href="viewer.php?id_peserta=' . htmlspecialchars($ajax_data['id_peserta'] ?? '') . '" class="p-2 text-blue-600"><i class="fas fa-eye"></i></a>
                                        <a href="../qr_print.php?id_peserta=' . htmlspecialchars($ajax_data['id_peserta'] ?? '') . '" class="p-2 text-purple-600"><i class="fas fa-qrcode"></i></a>
                                    </div>
                                </td>
                            </tr>';
            $ajax_no++;
        }
        $ajax_table_html .= '</tbody>';
    } else {
        // Grid items only (inner HTML untuk container .asset-grid)
        while ($ajax_data = mysqli_fetch_array($ajax_result)) {
            $photoBarang = $ajax_data['Photo_Barang'] ?? '';
            $namaBarang = $ajax_data['Nama_Barang'] ?? '';
            $statusBarang = $ajax_data['Status_Barang'] ?? '';
            $merek = $ajax_data['Merek'] ?? '';
            $type = $ajax_data['Type'] ?? '';
            $userPerangkat = $ajax_data['User_Perangkat'] ?? '';
            $jabatan = $ajax_data['Jabatan'] ?? '';
            $idKaryawan = $ajax_data['Id_Karyawan'] ?? '';
            $spesifikasi = $ajax_data['Spesifikasi'] ?? '';
            $lokasi = $ajax_data['Lokasi'] ?? '';
            $waktu = $ajax_data['Waktu'] ?? '';
            $serialNumber = $ajax_data['Serial_Number'] ?? '';
            $jenisBarang = $ajax_data['Jenis_Barang'] ?? '';
            $statusKelayakan = $ajax_data['Status_Kelayakan_Barang'] ?? '';
            $idPeserta = $ajax_data['id_peserta'] ?? '';

            $userPerangkatDisplay = !empty($userPerangkat) ? $userPerangkat : '-';
            $jabatanDisplay = !empty($jabatan) ? $jabatan : '-';
            $idKaryawanDisplay = !empty($idKaryawan) ? $idKaryawan : '-';
            $spesifikasiDisplay = !empty($spesifikasi) ? $spesifikasi : '-';
            $specToggleHtml = '';
            $specDetailHtml = '';
            if (!empty($spesifikasi)) {
                $specToggleHtml = '<button type="button" class="grid-spec-toggle text-gray-500 hover:text-gray-700 px-1" aria-expanded="false" aria-label="Lihat detail spesifikasi"><i class="fas fa-chevron-down grid-spec-chevron text-[9px] transition-transform duration-200"></i></button>';
                $specLines = preg_split("/\r\n|\r|\n/", trim((string)$spesifikasi));
                if (count($specLines) === 1) {
                    $single = trim($specLines[0]);
                    if (strpos($single, ';') !== false) {
                        $specLines = array_map('trim', explode(';', $single));
                    }
                }
                $specItemsHtml = '';
                foreach ($specLines as $line) {
                    $line = trim((string)$line);
                    if ($line === '') continue;
                    $specItemsHtml .= '<div class="flex items-start gap-1">'
                        . '<span class="text-gray-400 leading-none">•</span>'
                        . '<span class="truncate whitespace-nowrap min-w-0 flex-1">' . htmlspecialchars($line) . '</span>'
                        . '</div>';
                }
                $specDetailHtml = '<div class="grid-spec-detail hidden mt-0.5 text-[8px] text-gray-700">' . $specItemsHtml . '</div>';
            }

            $gridPhotos = [];
            if (!empty($ajax_data['Photo_Barang'])) $gridPhotos[] = '../uploads/' . $ajax_data['Photo_Barang'];
            if (!empty($ajax_data['Photo_Depan'])) $gridPhotos[] = '../uploads/' . $ajax_data['Photo_Depan'];
            if (!empty($ajax_data['Photo_Belakang'])) $gridPhotos[] = '../uploads/' . $ajax_data['Photo_Belakang'];
            if (!empty($ajax_data['Photo_SN'])) $gridPhotos[] = '../uploads/' . $ajax_data['Photo_SN'];
            $gridPhotosJson = htmlspecialchars(json_encode($gridPhotos), ENT_QUOTES, 'UTF-8');
            $gridHasMultiPhotos = count($gridPhotos) > 1;
            $gridPrimaryPhoto = $gridPhotos[0] ?? '';

            $tgl = '-';
            if (!empty($waktu)) {
                $ts = strtotime($waktu);
                if ($ts !== false) {
                    $tgl = date('d M Y', $ts);
                }
            }

            $ajax_grid_html .= '<div class="bg-white/90 backdrop-blur-sm rounded-lg shadow-md hover:shadow-xl transition-all duration-300 hover:-translate-y-1 border border-gray-200 overflow-hidden group flex flex-col h-full" data-photos="' . $gridPhotosJson . '" data-photo-index="0">
                                    <div class="relative h-32 bg-gradient-to-br from-gray-100 to-gray-200 overflow-hidden">'
                                        . (!empty($gridPrimaryPhoto)
                                            ? '<img src="' . htmlspecialchars($gridPrimaryPhoto) . '" alt="' . htmlspecialchars($namaBarang) . '" class="grid-card-image w-full h-full object-cover cursor-pointer transition-transform duration-300 group-hover:scale-110" onclick="openImageModal(\'' . htmlspecialchars($gridPrimaryPhoto) . '\')" />'
                                            : '<div class="w-full h-full flex items-center justify-center"><i class="fas fa-image text-gray-400 text-2xl"></i></div>')
                                        . ($gridHasMultiPhotos
                                            ? '<button type="button" class="grid-photo-prev grid-photo-nav absolute left-1 top-1/2 -translate-y-1/2 w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-white/80 backdrop-blur border border-gray-200 text-gray-700 flex items-center justify-center hover:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 opacity-0 pointer-events-none transition-opacity duration-200 group-hover:opacity-100 group-hover:pointer-events-auto group-focus-within:opacity-100 group-focus-within:pointer-events-auto" aria-label="Foto sebelumnya"><i class="fas fa-chevron-left text-[10px] sm:text-xs"></i></button>
                                               <button type="button" class="grid-photo-next grid-photo-nav absolute right-1 top-1/2 -translate-y-1/2 w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-white/80 backdrop-blur border border-gray-200 text-gray-700 flex items-center justify-center hover:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 opacity-0 pointer-events-none transition-opacity duration-200 group-hover:opacity-100 group-hover:pointer-events-auto group-focus-within:opacity-100 group-focus-within:pointer-events-auto" aria-label="Foto berikutnya"><i class="fas fa-chevron-right text-[10px] sm:text-xs"></i></button>'
                                            : '')
                                        . '<div class="absolute top-1 left-1">
                                            <span class="inline-block px-1.5 py-0.5 rounded-full text-xs font-semibold border ' . getStatusBadgeClass($statusBarang) . '">
                                                ' . htmlspecialchars($statusBarang) . '
                                            </span>
                                        </div>
                                    </div>

                                    <div class="p-2 flex flex-col flex-grow">
                                        <h3 class="font-semibold text-[10px] text-gray-900 line-clamp-2 mb-0.5" title="' . htmlspecialchars($namaBarang) . '">
                                            ' . htmlspecialchars($namaBarang) . '
                                        </h3>
                                        <p class="text-[9px] text-gray-600 mb-0.5 truncate">' . htmlspecialchars($merek) . '</p>
                                        <div class="mb-1">
                                            <span class="inline-block px-1 py-0.5 rounded text-[8px] font-semibold bg-blue-100 text-blue-700">
                                                ' . htmlspecialchars($type) . '
                                            </span>
                                        </div>

                                        <div class="text-[8px] text-gray-600 space-y-0.5 mb-1">
                                            <div class="truncate">
                                                <i class="fas fa-user text-gray-400 w-3"></i>
                                                <span class="truncate inline-block max-w-[75%]">' . htmlspecialchars($userPerangkatDisplay) . '</span>
                                            </div>
                                            <div class="truncate">
                                                <i class="fas fa-briefcase text-gray-400 w-3"></i>
                                                <span class="truncate inline-block max-w-[75%]">' . htmlspecialchars($jabatanDisplay) . '</span>
                                            </div>
                                            <div class="truncate">
                                                <i class="fas fa-id-card text-gray-400 w-3"></i>
                                                <span class="truncate inline-block max-w-[75%]">' . htmlspecialchars($idKaryawanDisplay) . '</span>
                                            </div>
                                            <div>
                                                <div class="flex items-start gap-1">
                                                    <i class="fas fa-clipboard-list text-gray-400 w-3 mt-[1px]"></i>
                                                    <div class="min-w-0 flex-1">
                                                        <div class="flex items-start justify-between gap-1">
                                                            <span class="truncate inline-block max-w-[75%]" title="' . htmlspecialchars($spesifikasi) . '">
                                                                ' . htmlspecialchars($spesifikasiDisplay) . '
                                                            </span>
                                                            ' . $specToggleHtml . '
                                                        </div>
                                                        ' . $specDetailHtml . '
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="truncate">
                                                <i class="fas fa-map-marker-alt text-gray-400 w-3"></i>
                                                <span class="truncate inline-block max-w-[75%]">' . htmlspecialchars($lokasi) . '</span>
                                            </div>
                                            <div class="truncate">
                                                <i class="fas fa-calendar text-gray-400 w-3"></i>
                                                <span class="text-[8px]">' . htmlspecialchars($tgl) . '</span>
                                            </div>
                                        </div>

                                        <div class="mb-1">
                                            <span class="text-[7px] font-mono bg-gray-100 px-1 py-0.5 rounded text-gray-700 block truncate">' . htmlspecialchars($serialNumber) . '</span>
                                        </div>

                                        <div class="flex flex-wrap gap-0.5 mb-1">
                                            <span class="inline-block px-1 py-0.5 rounded text-[7px] font-medium border ' . getStatusBadgeClass($jenisBarang) . '">
                                                ' . htmlspecialchars(substr($jenisBarang, 0, 5)) . '
                                            </span>
                                            <span class="inline-block px-1 py-0.5 rounded text-[7px] font-medium border ' . getStatusBadgeClass($statusKelayakan) . '">
                                                ' . htmlspecialchars($statusKelayakan) . '
                                            </span>
                                        </div>

                                        <div class="flex gap-0.5 pt-1 border-t border-gray-100 mt-auto">
                                            <a href="viewer.php?id_peserta=' . htmlspecialchars($idPeserta) . '" class="flex-1 p-0.5 text-blue-600 hover:bg-blue-50 rounded border border-blue-200 hover:border-blue-300 transition-all text-center" title="View Details">
                                                <i class="fas fa-eye text-[9px]"></i>
                                            </a>
                                            <a href="../qr_print.php?id_peserta=' . htmlspecialchars($idPeserta) . '" class="flex-1 p-0.5 text-purple-600 hover:bg-purple-50 rounded border border-purple-200 hover:border-purple-300 transition-all text-center" title="QR Code">
                                                <i class="fas fa-qrcode text-[9px]"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>';
        }
    }
    
    // Build pagination HTML
    $ajax_pagination_html = '';
        if ($ajax_total_pages > 1) {
                $ajax_pagination_html .= '<div class="mt-6 flex flex-col items-center">
                                                                    <div class="w-full max-w-4xl flex flex-col items-center space-y-2">
                                                                        <div class="text-sm text-gray-600 text-center">
                                                                            Showing ' . min($ajax_offset + 1, $ajax_total_filtered) . ' to ' . min($ajax_offset + $ajax_limit, $ajax_total_filtered) . ' of ' . $ajax_total_filtered . ' results
                                                                        </div>
                                                                        <nav class="inline-flex rounded-md shadow-sm -space-x-px justify-center">';
        
        $ajax_base_params = [];
        if (!empty($ajax_status_filter)) $ajax_base_params['status_filter'] = $ajax_status_filter;
        if (!empty($ajax_category_filter)) $ajax_base_params['kategori'] = $ajax_category_filter;
        if (!empty($ajax_search_query)) $ajax_base_params['search'] = $ajax_search_query;
        if (!empty($ajax_brand_filter)) $ajax_base_params['brand_filter'] = $ajax_brand_filter;
        if (!empty($ajax_status_lop_filter)) $ajax_base_params['status_lop_filter'] = $ajax_status_lop_filter;
        if (!empty($ajax_status_kelayakan_filter)) $ajax_base_params['status_kelayakan_filter'] = $ajax_status_kelayakan_filter;
        if (!empty($ajax_start_date)) $ajax_base_params['start_date'] = $ajax_start_date;
        if (!empty($ajax_end_date)) $ajax_base_params['end_date'] = $ajax_end_date;
        if (!empty($ajax_view_mode)) $ajax_base_params['view_mode'] = $ajax_view_mode;
        $ajax_base_params['per_page'] = $ajax_limit;
        
        // Previous
        if ($ajax_page > 1) {
            $ajax_prev_params = array_merge($ajax_base_params, ['page' => $ajax_page - 1]);
            $ajax_pagination_html .= '<a href="?' . http_build_query($ajax_prev_params) . '" class="pagination-link relative inline-flex items-center px-3 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50"><i class="fas fa-chevron-left"></i><span class="ml-1">Prev</span></a>';
        }
        
        // Pages
        $ajax_start = max(1, $ajax_page - 2);
        $ajax_end = min($ajax_total_pages, $ajax_page + 2);
        
        if ($ajax_start > 1) {
            $ajax_first_params = array_merge($ajax_base_params, ['page' => 1]);
            $ajax_pagination_html .= '<a href="?' . http_build_query($ajax_first_params) . '" class="pagination-link relative inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">1</a>';
            if ($ajax_start > 2) {
                $ajax_pagination_html .= '<span class="relative inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-500">...</span>';
            }
        }
        
        for ($i = $ajax_start; $i <= $ajax_end; $i++) {
            if ($i == $ajax_page) {
                $ajax_pagination_html .= '<span class="relative z-10 inline-flex items-center px-3 py-2 border border-orange-500 bg-orange-50 text-sm font-medium text-orange-600">' . $i . '</span>';
            } else {
                $ajax_page_params = array_merge($ajax_base_params, ['page' => $i]);
                $ajax_pagination_html .= '<a href="?' . http_build_query($ajax_page_params) . '" class="pagination-link relative inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">' . $i . '</a>';
            }
        }
        
        if ($ajax_end < $ajax_total_pages) {
            if ($ajax_end < $ajax_total_pages - 1) {
                $ajax_pagination_html .= '<span class="relative inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-500">...</span>';
            }
            $ajax_last_params = array_merge($ajax_base_params, ['page' => $ajax_total_pages]);
            $ajax_pagination_html .= '<a href="?' . http_build_query($ajax_last_params) . '" class="pagination-link relative inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">' . $ajax_total_pages . '</a>';
        }
        
        // Next
        if ($ajax_page < $ajax_total_pages) {
            $ajax_next_params = array_merge($ajax_base_params, ['page' => $ajax_page + 1]);
            $ajax_pagination_html .= '<a href="?' . http_build_query($ajax_next_params) . '" class="pagination-link relative inline-flex items-center px-3 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50"><span class="mr-1">Next</span><i class="fas fa-chevron-right"></i></a>';
        }
        
        $ajax_pagination_html .= '</nav></div>';
    }
    
    echo json_encode([
        'table_html' => $ajax_table_html,
        'grid_html' => $ajax_grid_html,
        'pagination_html' => $ajax_pagination_html,
        'current_page' => $ajax_page,
        'total_pages' => $ajax_total_pages,
        'total_records' => $ajax_total_filtered,
        'view_mode' => $ajax_view_mode
    ]);
    exit();
}

// Query untuk menghitung total data (sudah pakai $kon)
$total_query = "SELECT COUNT(*) as total FROM peserta";
$total_result = mysqli_query($kon, $total_query);
if ($total_result && mysqli_num_rows($total_result) > 0) {
    $total_row = mysqli_fetch_assoc($total_result);
    $total_data = $total_row['total'];
} else {
    $total_data = 0;
    error_log("Error total query: " . mysqli_error($kon));
}

// PERBAIKAN: Query jumlah status pakai $kon (bukan $conn), dan tutup koneksi di akhir jika perlu
$sql = "
    SELECT 
        SUM(CASE WHEN Status_Barang = 'READY' THEN 1 ELSE 0 END) AS total_ready,
        SUM(CASE WHEN Status_Barang = 'KOSONG' THEN 1 ELSE 0 END) AS total_kosong,
        SUM(CASE WHEN Status_Barang = 'IN USE' THEN 1 ELSE 0 END) AS total_inuse,
        SUM(CASE WHEN Status_Barang = 'REPAIR' THEN 1 ELSE 0 END) AS total_repair,
        SUM(CASE WHEN Status_Barang = 'TEMPORARY' THEN 1 ELSE 0 END) AS total_temporary,
        SUM(CASE WHEN Status_Barang = 'RUSAK' THEN 1 ELSE 0 END) AS total_rusak FROM peserta";
$result = mysqli_query($kon, $sql);  // PERBAIKAN: Ganti $conn->query ke mysqli_query($kon, ...

if ($result && mysqli_num_rows($result) > 0) {  // PERBAIKAN: Tambah check $result
    $row = mysqli_fetch_assoc($result);
    $jumlah_ready = $row['total_ready'];
    $jumlah_kosong = $row['total_kosong'];
    $jumlah_repair = $row['total_repair'];
    $jumlah_temporary = $row['total_temporary'];
    $jumlah_rusak = $row['total_rusak'];
} else {
    $jumlah_ready = 0;
    $jumlah_kosong = 0;
    $jumlah_repair = 0;
    $jumlah_temporary = 0;
    $jumlah_rusak = 0;
    error_log("Error status query: " . mysqli_error($kon));  // PERBAIKAN: Tambah log error
}

// Pastikan session username sudah diatur
$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'User';

// FIX: Ambil nama lengkap dari session (fallback ke username jika kosong)
$Nama_Lengkap = isset($_SESSION['Nama_Lengkap']) ? $_SESSION['Nama_Lengkap'] : $username;
$Jabatan_Level = isset($_SESSION['Jabatan_Level']) ? trim((string)$_SESSION['Jabatan_Level']) : '';

// Fallback: jika session Jabatan_Level kosong, ambil dari DB berdasarkan user_id
if ($Jabatan_Level === '' && isset($_SESSION['user_id'])) {
    $stmtMeta = $kon->prepare("SELECT Jabatan_Level, Nama_Lengkap, role FROM users WHERE id = ? LIMIT 1");
    if ($stmtMeta) {
        $uid = (int)$_SESSION['user_id'];
        $stmtMeta->bind_param('i', $uid);
        if ($stmtMeta->execute()) {
            $jabDb = null;
            $namaDb = null;
            $roleDb = null;
            $stmtMeta->bind_result($jabDb, $namaDb, $roleDb);
            if ($stmtMeta->fetch()) {
                $jab = trim((string)($jabDb ?? ''));
                if ($jab !== '') {
                    $Jabatan_Level = $jab;
                    $_SESSION['Jabatan_Level'] = $jab;
                }

                if (empty($_SESSION['Nama_Lengkap']) && !empty($namaDb)) {
                    $_SESSION['Nama_Lengkap'] = (string)$namaDb;
                    $Nama_Lengkap = $_SESSION['Nama_Lengkap'];
                }
                if (empty($_SESSION['role']) && !empty($roleDb)) {
                    $_SESSION['role'] = (string)$roleDb;
                }
            }
        }
        $stmtMeta->close();
    } else {
        error_log('Failed to prepare user meta query (view.php): ' . mysqli_error($kon));
    }
}

// DEBUG SEMENTARA: Cek session (lihat di source code Ctrl+U; hapus setelah test)
echo "<!-- DEBUG: Username = '$username' | Nama_Lengkap = '$Nama_Lengkap' -->";

// PERBAIKAN: Inisialisasi array kosong dulu untuk hindari 
$kategori_options = [];

// Ambil nilai dropdown dan search query
$kategori = isset($_GET['kategori']) ? mysqli_real_escape_string($kon, $_GET['kategori']) : '';
$search_query = isset($_GET['search']) ? mysqli_real_escape_string($kon, $_GET['search']) : '';
$status_filter = isset($_GET['status_filter']) ? mysqli_real_escape_string($kon, $_GET['status_filter']) : '';
$brand_filter = isset($_GET['brand_filter']) ? mysqli_real_escape_string($kon, $_GET['brand_filter']) : '';
$status_lop_filter = isset($_GET['status_lop_filter']) ? mysqli_real_escape_string($kon, $_GET['status_lop_filter']) : '';  // TAMBAHAN: Filter Status LOP
$status_kelayakan_filter = isset($_GET['status_kelayakan_filter']) ? mysqli_real_escape_string($kon, $_GET['status_kelayakan_filter']) : '';  // TAMBAHAN: Filter Status Kelayakan
$view_mode = isset($_GET['view_mode']) ? $_GET['view_mode'] : 'table';

// TAMBAHAN: Filter kalender (Start Date - End Date) berdasarkan kolom Waktu
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
    // Jika user kebalik input tanggal, tukar agar tetap bekerja
    [$start_date, $end_date] = [$end_date, $start_date];
    $dt = DateTime::createFromFormat('Y-m-d', $end_date);
    if ($dt) {
        $dt->modify('+1 day');
        $end_date_exclusive = $dt->format('Y-m-d');
    }
}

$download_excel_params = [];
if ($start_date !== '') $download_excel_params['start_date'] = $start_date;
if ($end_date !== '') $download_excel_params['end_date'] = $end_date;
$download_excel_href = 'download_excel.php' . (!empty($download_excel_params) ? ('?' . http_build_query($download_excel_params)) : '');


// FIX: Debug current GET params (hapus setelah test)
error_log("GET Params: " . print_r($_GET, true));  // Cek di PHP error log
echo "<!-- DEBUG: Loaded Filters - Kategori: '$kategori' | Status: '$status_filter' | Brand: '$brand_filter' | LOP: '$status_lop_filter' | Search: '$search_query' | Start: '$start_date' | End: '$end_date' -->";


// PERBAIKAN: Ambil opsi unik untuk filter dropdown dari database (batasi 100 opsi agar tidak overload)
$kategori_query = "SELECT DISTINCT Nama_Barang FROM peserta WHERE Nama_Barang != '' AND Nama_Barang IS NOT NULL ORDER BY Nama_Barang ASC LIMIT 100";
$kategori_result = mysqli_query($kon, $kategori_query);
if ($kategori_result && mysqli_num_rows($kategori_result) > 0) {
    while ($row = mysqli_fetch_array($kategori_result)) {
        $kategori_options[] = trim($row['Nama_Barang']);  // Trim spasi ekstra
    }
} else {
    error_log("Error query Nama_Barang: " . mysqli_error($kon));
    // Jika gagal, fallback ke opsi kosong
}

// PERBAIKAN: Query opsi Status LOP - Tambah fallback SELALU (gabung dengan query result), tambah debug
$status_lop_options = ['LUNAS', 'BELUM LUNAS', 'TIDAK LOP'];  // PERBAIKAN: Selalu mulai dengan hardcoded
$status_lop_query = "SELECT DISTINCT Status_LOP FROM peserta WHERE Status_LOP != '' AND Status_LOP IS NOT NULL ORDER BY Status_LOP ASC LIMIT 50";
$status_lop_result = mysqli_query($kon, $status_lop_query);
if ($status_lop_result && mysqli_num_rows($status_lop_result) > 0) {
    $temp_options = [];
    while ($row = mysqli_fetch_array($status_lop_result)) {
        $opt = trim($row['Status_LOP']);
        if (!in_array($opt, $temp_options)) {  // Hindari duplikat
            $temp_options[] = $opt;
        }
    }
    // Gabung dengan hardcoded (unique)
    $status_lop_options = array_unique(array_merge($status_lop_options, $temp_options));
    sort($status_lop_options);  // Urutkan
    error_log("Status LOP options from DB: " . implode(', ', $temp_options));  // Debug log
} else {
    error_log("Fallback hardcoded untuk Status LOP - query peserta gagal: " . mysqli_error($kon));
}

// TAMBAHAN: Query opsi Status Kelayakan Barang dari database
$status_kelayakan_options = [];
$status_kelayakan_query = "SELECT DISTINCT Status_Kelayakan_Barang FROM peserta WHERE Status_Kelayakan_Barang != '' AND Status_Kelayakan_Barang IS NOT NULL ORDER BY Status_Kelayakan_Barang ASC LIMIT 50";
$status_kelayakan_result = mysqli_query($kon, $status_kelayakan_query);
if ($status_kelayakan_result && mysqli_num_rows($status_kelayakan_result) > 0) {
    while ($row = mysqli_fetch_array($status_kelayakan_result)) {
        $opt = trim($row['Status_Kelayakan_Barang']);
        if (!in_array($opt, $status_kelayakan_options)) {  // Hindari duplikat
            $status_kelayakan_options[] = $opt;
        }
    }
    sort($status_kelayakan_options);  // Urutkan
    error_log("Status Kelayakan options from DB: " . implode(', ', $status_kelayakan_options));  // Debug log
} else {
    error_log("Query Status Kelayakan gagal: " . mysqli_error($kon));
}

// PERBAIKAN: Debug - Tampilkan nilai kategori (hapus setelah test)
if (!empty($kategori)) {
    echo "<!-- DEBUG: Kategori filter: $kategori -->";  // Cek di source code browser
}

// Set timezone
date_default_timezone_set('Asia/Jakarta');

// ... (logika penghapusan tetap sama) ...

// PERBAIKAN: Pagination logic (ubah query filter ke Nama_Barang, pakai $kon konsisten)
$limit = 12;

if (isset($_GET['page']) && is_numeric($_GET['page'])) {
    $page = (int)$_GET['page'];
    if ($page < 1) $page = 1;
} else {
    $page = 1;
}
$start_from = ($page - 1) * $limit;

// Count total records untuk pagination
$count_sql = "SELECT COUNT(id_peserta) FROM peserta WHERE 1=1";
if (!empty($kategori)) {
    // PERBAIKAN: Ubah ke Nama_Barang (exact match)
    $count_sql .= " AND Nama_Barang = '" . mysqli_real_escape_string($kon, $kategori) . "'";
    // Jika ingin partial match (uncomment ini, comment exact di atas):
    // $count_sql .= " AND Nama_Barang LIKE '%" . mysqli_real_escape_string($kon, $kategori) . "%'";
}
if (!empty($search_query)) {
    $count_sql .= " AND (Nama_Barang LIKE '%" . mysqli_real_escape_string($kon, $search_query) . "%' OR 
        Merek LIKE '%" . mysqli_real_escape_string($kon, $search_query) . "%' OR 
        Type LIKE '%" . mysqli_real_escape_string($kon, $search_query) . "%' OR 
        Serial_Number LIKE '%" . mysqli_real_escape_string($kon, $search_query) . "%' OR 
        Spesifikasi LIKE '%" . mysqli_real_escape_string($kon, $search_query) . "%' OR 
        Kelengkapan_Barang LIKE '%" . mysqli_real_escape_string($kon, $search_query) . "%' OR
        Kondisi_Barang LIKE '%" . mysqli_real_escape_string($kon, $search_query) . "%' OR
        Lokasi LIKE '%" . mysqli_real_escape_string($kon, $search_query) . "%' OR
        Id_Karyawan LIKE '%" . mysqli_real_escape_string($kon, $search_query) . "%' OR
        Jabatan LIKE '%" . mysqli_real_escape_string($kon, $search_query) . "%' OR
        Riwayat_Barang LIKE '%" . mysqli_real_escape_string($kon, $search_query) . "%' OR
        Photo_Barang LIKE '%" . mysqli_real_escape_string($kon, $search_query) . "%' OR 
        User_Perangkat LIKE '%" . mysqli_real_escape_string($kon, $search_query) . "%' OR 
        Jenis_Barang LIKE '%" . mysqli_real_escape_string($kon, $search_query) . "%' OR 
        Status_Kelayakan_Barang LIKE '%" . mysqli_real_escape_string($kon, $search_query) . "%' OR
        Status_Barang LIKE '%" . mysqli_real_escape_string($kon, $search_query) . "%')";
}
if (!empty($status_filter)) {
    $count_sql .= " AND Status_Barang = '" . mysqli_real_escape_string($kon, $status_filter) . "'";
}
if (!empty($brand_filter)) {
    $count_sql .= " AND Merek = '" . mysqli_real_escape_string($kon, $brand_filter) . "'";
}

// TAMBAHAN: Kondisi filter Status LOP (exact match)
if (!empty($status_lop_filter)) {
    $count_sql .= " AND Status_LOP = '" . mysqli_real_escape_string($kon, $status_lop_filter) . "'";
    // Partial match (opsional): $count_sql .= " AND Status_LOP LIKE '%" . mysqli_real_escape_string($kon, $status_lop_filter) . "%'";
}
// TAMBAHAN: Kondisi filter Status Kelayakan Barang (exact match)
if (!empty($status_kelayakan_filter)) {
    $count_sql .= " AND Status_Kelayakan_Barang = '" . mysqli_real_escape_string($kon, $status_kelayakan_filter) . "'";
}

// TAMBAHAN: Filter tanggal (inclusive end date)
if (!empty($start_date)) {
    $count_sql .= " AND Waktu >= '" . mysqli_real_escape_string($kon, $start_date) . " 00:00:00'";
}
if (!empty($end_date_exclusive)) {
    $count_sql .= " AND Waktu < '" . mysqli_real_escape_string($kon, $end_date_exclusive) . " 00:00:00'";
}

// PERBAIKAN: Error handling untuk count query
$result = mysqli_query($kon, $count_sql);
if (!$result) {
    die("Error count query: " . mysqli_error($kon));  // Debug jika gagal
}
$row = mysqli_fetch_row($result);
$total_records = $row[0] ?? 0;  // Fallback 0 jika null
$total_pages = ceil($total_records / $limit);

// PERBAIKAN: Debug - Tampilkan count SQL (hapus setelah test)
echo "<!-- DEBUG: Count SQL: $count_sql | Total Records: $total_records -->";

// Data query dengan pagination
$sql = "SELECT * FROM peserta WHERE 1=1";
if (!empty($kategori)) {
    // PERBAIKAN: Sama seperti count, ubah ke Nama_Barang
    $sql .= " AND Nama_Barang = '" . mysqli_real_escape_string($kon, $kategori) . "'";
    // Partial: $sql .= " AND Nama_Barang LIKE '%" . mysqli_real_escape_string($kon, $kategori) . "%'";
}
if (!empty($search_query)) {
    $sql .= " AND (Nama_Barang LIKE '%" . mysqli_real_escape_string($kon, $search_query) . "%' OR 
        Merek LIKE '%" . mysqli_real_escape_string($kon, $search_query) . "%' OR 
        Type LIKE '%" . mysqli_real_escape_string($kon, $search_query) . "%' OR 
        Serial_Number LIKE '%" . mysqli_real_escape_string($kon, $search_query) . "%' OR 
        Spesifikasi LIKE '%" . mysqli_real_escape_string($kon, $search_query) . "%' OR 
        Kelengkapan_Barang LIKE '%" . mysqli_real_escape_string($kon, $search_query) . "%' OR
        Kondisi_Barang LIKE '%" . mysqli_real_escape_string($kon, $search_query) . "%' OR
        Lokasi LIKE '%" . mysqli_real_escape_string($kon, $search_query) . "%' OR
        Id_Karyawan LIKE '%" . mysqli_real_escape_string($kon, $search_query) . "%' OR
        Jabatan LIKE '%" . mysqli_real_escape_string($kon, $search_query) . "%' OR
        Riwayat_Barang LIKE '%" . mysqli_real_escape_string($kon, $search_query) . "%' OR
        Photo_Barang LIKE '%" . mysqli_real_escape_string($kon, $search_query) . "%' OR 
        User_Perangkat LIKE '%" . mysqli_real_escape_string($kon, $search_query) . "%' OR 
        Jenis_Barang LIKE '%" . mysqli_real_escape_string($kon, $search_query) . "%' OR 
        Status_Kelayakan_Barang LIKE '%" . mysqli_real_escape_string($kon, $search_query) . "%' OR
        Status_Barang LIKE '%" . mysqli_real_escape_string($kon, $search_query) . "%')";
}
if (!empty($status_filter)) {
    $sql .= " AND Status_Barang = '" . mysqli_real_escape_string($kon, $status_filter) . "'";
}
if (!empty($brand_filter)) {
    $sql .= " AND Merek = '" . mysqli_real_escape_string($kon, $brand_filter) . "'";
}

// TAMBAHAN: Kondisi filter Status LOP (exact match)
if (!empty($status_lop_filter)) {
    $sql .= " AND Status_LOP = '" . mysqli_real_escape_string($kon, $status_lop_filter) . "'";
    // Partial match (opsional): $sql .= " AND Status_LOP LIKE '%" . mysqli_real_escape_string($kon, $status_lop_filter) . "%'";
}
// TAMBAHAN: Kondisi filter Status Kelayakan Barang (exact match)
if (!empty($status_kelayakan_filter)) {
    $sql .= " AND Status_Kelayakan_Barang = '" . mysqli_real_escape_string($kon, $status_kelayakan_filter) . "'";
}

// TAMBAHAN: Filter tanggal (inclusive end date)
if (!empty($start_date)) {
    $sql .= " AND Waktu >= '" . mysqli_real_escape_string($kon, $start_date) . " 00:00:00'";
}
if (!empty($end_date_exclusive)) {
    $sql .= " AND Waktu < '" . mysqli_real_escape_string($kon, $end_date_exclusive) . " 00:00:00'";
}
$sql .= " ORDER BY id_peserta DESC LIMIT $limit OFFSET $start_from";

// PERBAIKAN: Error handling untuk main query
$hasil = mysqli_query($kon, $sql);
if (!$hasil) {
    die("Error main query: " . mysqli_error($kon));  // Debug jika gagal
}

// PERBAIKAN: Debug - Tampilkan SQL (hapus setelah test)
echo "<!-- DEBUG: Main SQL: $sql -->";

echo "<!-- DEBUG: Main SQL: $sql -->";
function getStatusBadgeClass($status) {
    switch (strtoupper(trim($status))) {
        case 'READY': return 'bg-green-100 text-green-800 border-green-200';
        case 'IN USE': return 'bg-red-600 text-white border-red-700'; // ✅ Red background & white text
        case 'KOSONG': return 'bg-red-100 text-red-800 border-red-200';
        case 'REPAIR': return 'bg-yellow-100 text-yellow-800 border-yellow-200';
        case 'TEMPORARY': return 'bg-blue-100 text-blue-800 border-blue-200';
        case 'RUSAK': return 'bg-orange-100 text-orange-800 border-orange-200';
        case 'LUNAS': return 'bg-green-100 text-green-800 border-green-200';
        case 'BELUM LUNAS': return 'bg-red-100 text-red-800 border-red-200';
        case 'TIDAK LOP': return 'bg-gray-100 text-gray-800 border-gray-200';
        case 'LAYAK': return 'bg-green-100 text-green-800 border-green-200';
        case 'TIDAK LAYAK': return 'bg-red-100 text-red-800 border-red-200';
        case 'INVENTARIS': return 'bg-blue-100 text-blue-800 border-blue-200';
        case 'LOP': return 'bg-purple-100 text-purple-800 border-purple-200';
        default: return 'bg-gray-100 text-gray-800 border-gray-200';
    }
}

function formatRupiah($value) {
    $raw = trim((string)$value);
    if ($raw === '') {
        return '-';
    }

    // Normalisasi string angka (hapus koma ribuan/spasi)
    $normalized = str_replace([',', ' '], ['', ''], $raw);
    if (!is_numeric($normalized)) {
        return $raw;
    }

    // Rupiah umumnya tanpa desimal
    $amountInt = (int)round((float)$normalized);
    return 'Rp.' . number_format($amountInt, 0, ',', '.');
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ASSET IT CITRATEL</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../global.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <!-- PERBAIKAN: Select2 untuk Searchable Dropdown -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<style>
/* Table Wrapper - Responsive Container */
.table-wrapper {
  padding: 1.5rem;
  margin-bottom: 2rem;
}

@media (max-width: 768px) {
  .table-wrapper {
    padding: 1rem;
    margin-bottom: 1.5rem;
  }
}

/* Table container - proper flow below navbar */
.sticky-table-container {
  position: relative;
  z-index: 20;
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(10px);
  max-height: 85vh;
  border-radius: 0.875rem;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
  border: 1px solid #e5e7eb;
  overflow: hidden;
}

@media (max-width: 768px) {
  .sticky-table-container {
    max-height: 80vh;
    position: relative;
    top: auto;
  }
}

/* Sticky header across vertical scroll and sticky columns */
.table-sticky-wrap { overflow-x: auto; overflow-y: auto; max-height: calc(85vh - 0px); }

/* Tampilkan scrollbar di dalam tabel (inner scroll) */
.table-sticky-wrap::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}
.table-sticky-wrap::-webkit-scrollbar-track {
    background: #f3f4f6;
}
.table-sticky-wrap::-webkit-scrollbar-thumb {
    background: #cbd5f5;
    border-radius: 999px;
}
.table-sticky-wrap::-webkit-scrollbar-thumb:hover {
    background: #9ca3af;
}

@media (max-width: 768px) {
  .table-sticky-wrap { max-height: 70vh; }
}

.table-sticky { border-collapse: separate; border-spacing: 0; }
/* Header stays on top while vertical scroll */
.table-sticky thead th { position: sticky; top: 0; z-index: 10; background: #111827; color: #fff; box-shadow: 0 1px 0 rgba(0,0,0,0.08); padding: 0.75rem 0.5rem; font-size: 0.875rem; }
/* Row background to avoid transparency under header */
.table-sticky tbody td { background: #ffffff; padding: 0.75rem 0.5rem; font-size: 0.875rem; }

@media (max-width: 768px) {
  .table-sticky thead th { padding: 0.5rem 0.25rem; font-size: 0.75rem; }
  .table-sticky tbody td { padding: 0.5rem 0.25rem; font-size: 0.75rem; }
}

/* Sticky left columns */
.table-sticky .sticky-col { position: sticky; background: #ffffff; z-index: 2; }
.table-sticky thead th.sticky-col, .table-sticky thead th.sticky-right { position: sticky; top: 0; z-index: 15; background: #111827; color: #fff; }
.table-sticky .col-no { left: 0; min-width: 50px; }
.table-sticky .col-waktu { left: 50px; min-width: 110px; }
.table-sticky .col-createby { left: 160px; min-width: 150px; }
.table-sticky .col-namabarang { left: 310px; min-width: 180px; }
.table-sticky .col-nomoraset { min-width: 140px; }

@media (max-width: 768px) {
  .table-sticky .col-no { min-width: 40px; }
  .table-sticky .col-waktu { min-width: 80px; left: 40px; }
  .table-sticky .col-createby { min-width: 100px; left: 120px; }
  .table-sticky .col-namabarang { min-width: 120px; left: 220px; }
    .table-sticky .col-nomoraset { min-width: 120px; }
}
/* Sticky right column (Actions) */
.table-sticky .sticky-right { position: sticky; right: 0; z-index: 3; background: #ffffff; }
/* Subtle separators for sticky columns */
.table-sticky .sticky-col, .table-sticky .sticky-right { border-right: 1px solid #e5e7eb; }
.table-sticky .sticky-right { border-left: 1px solid #e5e7eb; border-right: none; }
/* Tooltip/Popover untuk Spesifikasi dan Lokasi */
.spec-tooltip {
  position: relative;
  cursor: help;
  border-bottom: 1px dotted #666;
}
.spec-tooltip:hover::after {
  content: attr(data-content);
  position: absolute;
  bottom: 125%;
  left: 50%;
  transform: translateX(-50%);
  background: #fff;
  border: 2px solid #333;
  border-radius: 8px;
  padding: 12px 16px;
  white-space: pre-wrap;
  word-wrap: break-word;
  max-width: 300px;
  font-size: 13px;
  line-height: 1.5;
  color: #333;
  z-index: 1000;
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}
.spec-tooltip:hover::before {
  content: '';
  position: absolute;
  bottom: 115%;
  left: 50%;
  transform: translateX(-50%);
  border: 6px solid transparent;
  border-top-color: #333;
  z-index: 1000;
}
/* Cell Content Preview dengan Ellipsis */
.cell-preview {
  cursor: pointer;
  max-width: 150px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  display: inline-block;
  padding: 4px 8px;
  border-radius: 4px;
  background-color: #f0f4f8;
  transition: all 0.2s ease;
  border: 1px solid transparent;
}
.cell-preview:hover {
  background-color: #e0e7ff;
  border-color: #4f46e5;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.cell-preview::after {
  content: '';
}

/* Modal untuk Detail Data */
.detail-modal {
  display: none;
  position: fixed;
  z-index: 9998;
  left: 0;
  top: 0;
  width: 100%;
  height: 100%;
  overflow: auto;
  background-color: rgba(0, 0, 0, 0.5);
  backdrop-filter: blur(3px);
}
.detail-modal.show {
  display: flex;
  align-items: center;
  justify-content: center;
}
.detail-modal-content {
  background-color: #fff;
  padding: 20px;
  border-radius: 12px;
  border: 1px solid #e5e7eb;
  width: 90%;
  max-width: 500px;
  max-height: 80vh;
  overflow-y: auto;
  box-shadow: 0 10px 40px rgba(0,0,0,0.3);
}
.detail-modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 15px;
  padding-bottom: 10px;
  border-bottom: 2px solid #f3f4f6;
}
.detail-modal-header h3 {
  margin: 0;
  font-size: 18px;
  font-weight: 600;
  color: #1f2937;
}
.detail-modal-close {
  background: none;
  border: none;
  font-size: 24px;
  color: #6b7280;
  cursor: pointer;
  transition: color 0.2s;
  padding: 0;
  width: 30px;
  height: 30px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.detail-modal-close:hover {
  color: #1f2937;
}
.detail-modal-body {
  color: #374151;
  line-height: 1.6;
  word-break: break-word;
  white-space: pre-wrap;
  font-size: 14px;
}

/* Responsive behaviors for tablets and small screens */
@media (max-width: 1024px) {
  .table-sticky thead th,
  .table-sticky .sticky-col,
  .table-sticky .sticky-right {
    position: static !important;
    left: auto !important; right: auto !important; top: auto !important;
  }
  .table-sticky-wrap { max-height: none; overflow-y: visible; }
}

@media (max-width: 768px) {
  .sticky-table-container {
    border-radius: 0.5rem;
  }
  
  .table-sticky thead th {
    padding: 0.5rem 0.25rem !important;
    font-size: 0.7rem !important;
    white-space: nowrap;
  }
  
  .table-sticky tbody td {
    padding: 0.5rem 0.25rem !important;
    font-size: 0.7rem !important;
  }
}
</style>
</head>
<body class="bg-gray-50">

        <!-- Loading Animation -->
<!-- Loading Animation -->
<div id="loadingOverlay" class="loading-overlay">
    <div class="loading-content">
        <div class="loading-logo">
            <img src="logo_form/logo ckt fix.png" alt="Logo Perusahaan" class="logo-image">  <!-- Path ke logo lokal Anda -->
        </div>
        <h1 class="text-2xl md:text-3xl font-bold mb-2 text-white">PT CIPTA KARYA TECHNOLOGY</h1>  <!-- Ganti nama perusahaan jika perlu -->
        <p class="text-gray-300 mb-4">Loading Sistem ASSET...</p>  <!-- Ganti teks deskripsi jika perlu -->
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

    <?php $activePage = 'assets'; require_once __DIR__ . '/sidebar_user_include.php'; ?>

    <!-- Main Content with Dynamic Push Effect -->
    <div id="main-content" class="transition-all duration-300 ease-in-out pt-20 ml-0 lg:ml-60">
<script>
(function(){
    var el = document.getElementById('main-content');
    if (!el) return;
    function apply(collapsed) {
        if (collapsed) { el.style.marginLeft = '0'; }
        else { el.style.marginLeft = ''; }
    }
    if (window.innerWidth >= 1024 && localStorage.getItem('sidebarCollapsed') === '1') { apply(true); }
    window.addEventListener('sidebarToggled', function(e) { if (window.innerWidth >= 1024) apply(e.detail.collapsed); });
    window.addEventListener('resize', function() {
        if (window.innerWidth >= 1024) { apply(localStorage.getItem('sidebarCollapsed') === '1'); }
        else { apply(false); }
    });
})();
</script>
        <div class="p-6">
            <!-- Centered Page Header -->
            <div class="mb-8 text-center">
                <h1 class="text-4xl font-bold bg-gradient-to-r from-orange-600 to-orange-800 bg-clip-text text-transparent mb-3">
                    DAFTAR ASSET IT
                </h1>
                <p class="text-gray-600 text-lg">Manage and track all IT assets in your organization</p>
                <div class="w-24 h-1 bg-gradient-to-r from-orange-400 to-orange-600 rounded-full mx-auto mt-4"></div>
            </div>

            <!-- Action Buttons -->
            <div class="flex flex-col sm:flex-row gap-4 mb-6">
                <a href="<?php echo htmlspecialchars($download_excel_href, ENT_QUOTES); ?>" class="inline-flex items-center px-6 py-3 border-2 border-green-500 text-green-600 font-medium rounded-lg shadow-md hover:shadow-lg hover:bg-green-50 transition-all duration-300 hover:scale-105">
                    <i class="fas fa-file-excel mr-2"></i>
                    Download Excel
                </a>
            </div>

            <!-- Enhanced Filter Section -->
            <div class="bg-white/80 backdrop-blur-sm rounded-xl p-4 mb-6 shadow-lg border border-gray-200">
                <form method="GET" action="view.php" class="flex flex-col lg:flex-row gap-4">
                    <!-- Filter Section -->
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-3">
                            <i class="fas fa-filter text-gray-600"></i>
                            <span class="font-medium text-gray-700">Filter Data:</span>
                        </div>
                        
                       <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">  <!-- 3 kolom desktop, responsive -->
    <!-- Kategori (div 1) -->
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Kategori:</label>
        <select id="kategori-filter" name="kategori" class="select2-filter w-full px-3 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white/90 backdrop-blur-sm text-sm max-h-40 overflow-y-auto" data-placeholder="Pilih atau ketik kategori...">
            <option value="">Semua Kategori</option>
            <?php
            if (!empty($kategori_options)) {
                foreach ($kategori_options as $opt) {
                    $selected = ($kategori == $opt) ? 'selected' : '';
                    echo "<option value='" . htmlspecialchars($opt) . "' $selected>" . htmlspecialchars($opt) . "</option>";
                }
            } else {
                echo "<option value=''>Tidak ada data kategori</option>";
            }
            ?>
        </select>
    </div>

    <!-- Status Barang (div 2) -->
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Status Barang:</label>
        <select name="status_filter" class="select2-filter w-full px-3 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white/90 backdrop-blur-sm text-sm" data-placeholder="Pilih atau ketik status...">
            <option value="">Semua Status</option>
            <option value="READY" <?php echo ($status_filter == 'READY') ? 'selected' : ''; ?>>Ready</option>
            <option value="KOSONG" <?php echo ($status_filter == 'KOSONG') ? 'selected' : ''; ?>>Kosong</option>
            <option value="IN USE" <?php echo ($status_filter == 'IN USE') ? 'selected' : ''; ?>>IN USE</option>
            <option value="REPAIR" <?php echo ($status_filter == 'REPAIR') ? 'selected' : ''; ?>>Repair</option>
            <option value="TEMPORARY" <?php echo ($status_filter == 'TEMPORARY') ? 'selected' : ''; ?>>Temporary</option>
            <option value="RUSAK" <?php echo ($status_filter == 'RUSAK') ? 'selected' : ''; ?>>Rusak</option>
        </select>
    </div>

    <!-- Merek (div 3) -->
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Merek:</label>
        <select id="brand-filter" name="brand_filter" class="select2-filter w-full px-3 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white/90 backdrop-blur-sm text-sm" data-placeholder="Pilih atau ketik merek...">
            <option value="">Semua Merek</option>
            <?php
            // PERBAIKAN: Filter Merek berdasarkan Kategori yang dipilih
            $brands_query = "SELECT DISTINCT Merek FROM peserta WHERE Merek != ''";
            if (!empty($kategori)) {
                $brands_query .= " AND Nama_Barang = '" . mysqli_real_escape_string($kon, $kategori) . "'";
            }
            $brands_query .= " ORDER BY Merek";
            
            $brands_result = mysqli_query($kon, $brands_query);
            if ($brands_result && mysqli_num_rows($brands_result) > 0) {
                while ($brand_row = mysqli_fetch_array($brands_result)) {
                    $selected = ($brand_filter == $brand_row['Merek']) ? 'selected' : '';
                    echo "<option value='" . htmlspecialchars($brand_row['Merek']) . "' $selected>" . htmlspecialchars($brand_row['Merek']) . "</option>";
                }
            } else {
                echo "<option value=''>Tidak ada merek</option>";
            }
            ?>
        </select>
    </div>

    <!-- Status LOP (div 4) -->
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Status LOP:</label>
        <select name="status_lop_filter" class="select2-filter w-full px-3 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white/90 backdrop-blur-sm text-sm" data-placeholder="Pilih atau ketik Status LOP...">
            <option value="">Semua Status LOP</option>
            <?php
            if (!empty($status_lop_options)) {
                foreach ($status_lop_options as $opt) {
                    $selected = ($status_lop_filter == $opt) ? 'selected' : '';
                    echo "<option value='" . htmlspecialchars($opt) . "' $selected>" . htmlspecialchars($opt) . "</option>";
                }
            } else {
                echo "<option value=''>Tidak ada data Status LOP</option>";
            }
            ?>
        </select>
    </div>

    <!-- Status Kelayakan Barang (div 5 - BARU) -->
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Status Kelayakan:</label>
        <select name="status_kelayakan_filter" class="select2-filter w-full px-3 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white/90 backdrop-blur-sm text-sm" data-placeholder="Pilih atau ketik Status Kelayakan...">
            <option value="">Semua Status Kelayakan</option>
            <?php
            if (!empty($status_kelayakan_options)) {
                foreach ($status_kelayakan_options as $opt) {
                    $selected = ($status_kelayakan_filter == $opt) ? 'selected' : '';
                    echo "<option value='" . htmlspecialchars($opt) . "' $selected>" . htmlspecialchars($opt) . "</option>";
                }
            } else {
                echo "<option value=''>Tidak ada data Status Kelayakan</option>";
            }
            ?>
        </select>
    </div>

    <!-- Start Date (div 6 - BARU) -->
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Start Date:</label>
        <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" class="w-full px-3 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white/90 backdrop-blur-sm text-sm" />
    </div>

    <!-- End Date (div 7 - BARU) -->
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">End Date:</label>
        <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" class="w-full px-3 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white/90 backdrop-blur-sm text-sm" />
    </div>
</div>  <!-- Tutup grid -->
                        
 <div>                       <!-- Hidden inputs to preserve search and view mode -->
<input type="hidden" name="search" value="<?php echo htmlspecialchars($search_query); ?>">
<input type="hidden" name="view_mode" value="<?php echo htmlspecialchars($view_mode); ?>">
</div>
                    </div>
                    <!-- View Mode Toggle and Submit -->
                    <div class="flex flex-col items-center lg:items-end justify-center gap-4">
                        <div class="flex flex-col items-center">
                            <span class="text-sm font-medium text-gray-700 mb-2">View Mode:</span>
                            <div class="flex bg-gray-100 rounded-lg p-1">
                                <button type="button" onclick="changeViewMode('table')" class="flex items-center space-x-2 px-4 py-2 rounded-md transition-all duration-200 <?php echo ($view_mode === 'table') ? 'bg-white text-blue-600 shadow-md' : 'text-gray-600 hover:text-gray-800'; ?>">
                                    <i class="fas fa-list"></i>
                                    <span class="hidden sm:inline">Table</span>
                                </button>
                                <button type="button" onclick="changeViewMode('grid')" class="flex items-center space-x-2 px-4 py-2 rounded-md transition-all duration-200 <?php echo ($view_mode === 'grid') ? 'bg-white text-blue-600 shadow-md' : 'text-gray-600 hover:text-gray-800'; ?>">
                                    <i class="fas fa-th"></i>
                                    <span class="hidden sm:inline">Grid</span>
                                </button>
                            </div>
                        </div>
                        
                        <button type="submit" class="px-6 py-2 bg-gradient-to-r from-blue-500 to-purple-600 text-white font-medium rounded-lg shadow-md hover:shadow-lg transition-all duration-300 hover:scale-105">
                            <i class="fas fa-search mr-2"></i>
                            Filter
                        </button>
                    </div>
                </form>
            </div>

            <!-- Asset Display -->
            <?php if ($view_mode === 'grid'): ?>
                <!-- Grid View (Compact like Tokopedia) -->
                <div class="asset-grid grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3 mb-8">
                    <?php
                    $no = $start_from + 1;
                    while ($data = mysqli_fetch_array($hasil)) {
                        $grid_photos = [];
                        if (!empty($data['Photo_Barang'])) $grid_photos[] = "../uploads/" . $data['Photo_Barang'];
                        if (!empty($data['Photo_Depan'])) $grid_photos[] = "../uploads/" . $data['Photo_Depan'];
                        if (!empty($data['Photo_Belakang'])) $grid_photos[] = "../uploads/" . $data['Photo_Belakang'];
                        if (!empty($data['Photo_SN'])) $grid_photos[] = "../uploads/" . $data['Photo_SN'];
                        $grid_photos_json = htmlspecialchars(json_encode($grid_photos), ENT_QUOTES, 'UTF-8');
                        $grid_has_multi_photos = count($grid_photos) > 1;
                        $grid_primary_photo = $grid_photos[0] ?? '';
                    ?>
                    <div class="bg-white/90 backdrop-blur-sm rounded-lg shadow-md hover:shadow-xl transition-all duration-300 hover:-translate-y-1 border border-gray-200 overflow-hidden group flex flex-col h-full" data-photos="<?php echo $grid_photos_json; ?>" data-photo-index="0">
                        <!-- Image Section -->
                        <div class="relative h-32 bg-gradient-to-br from-gray-100 to-gray-200 overflow-hidden">
                            <?php if (!empty($grid_primary_photo)): ?>
                                <img
                                    src="<?php echo htmlspecialchars($grid_primary_photo); ?>"
                                    alt="<?php echo htmlspecialchars($data['Nama_Barang']); ?>"
                                    class="grid-card-image w-full h-full object-cover cursor-pointer transition-transform duration-300 group-hover:scale-110"
                                    onclick="openImageModal('<?php echo htmlspecialchars($grid_primary_photo); ?>')"
                                />
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center">
                                    <i class="fas fa-image text-gray-400 text-2xl"></i>
                                </div>
                            <?php endif; ?>

                            <?php if ($grid_has_multi_photos): ?>
                                <!-- Photo Navigation (Responsive) -->
                                <button type="button"
                                        class="grid-photo-prev grid-photo-nav absolute left-1 top-1/2 -translate-y-1/2 w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-white/80 backdrop-blur border border-gray-200 text-gray-700 flex items-center justify-center hover:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 opacity-0 pointer-events-none transition-opacity duration-200 group-hover:opacity-100 group-hover:pointer-events-auto group-focus-within:opacity-100 group-focus-within:pointer-events-auto"
                                        aria-label="Foto sebelumnya">
                                    <i class="fas fa-chevron-left text-[10px] sm:text-xs"></i>
                                </button>
                                <button type="button"
                                        class="grid-photo-next grid-photo-nav absolute right-1 top-1/2 -translate-y-1/2 w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-white/80 backdrop-blur border border-gray-200 text-gray-700 flex items-center justify-center hover:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 opacity-0 pointer-events-none transition-opacity duration-200 group-hover:opacity-100 group-hover:pointer-events-auto group-focus-within:opacity-100 group-focus-within:pointer-events-auto"
                                        aria-label="Foto berikutnya">
                                    <i class="fas fa-chevron-right text-[10px] sm:text-xs"></i>
                                </button>
                            <?php endif; ?>

                            <!-- Status Badge -->
                            <div class="absolute top-1 left-1">
                                <span class="inline-block px-1.5 py-0.5 rounded-full text-xs font-semibold border <?php echo getStatusBadgeClass($data['Status_Barang']); ?>">
                                    <?php echo htmlspecialchars($data['Status_Barang']); ?>
                                </span>
                            </div>
                        </div>

                        <!-- Content Section -->
                        <div class="p-2 flex flex-col flex-grow">
                            <!-- Title -->
                            <h3 class="font-semibold text-[10px] text-gray-900 line-clamp-2 mb-0.5" title="<?php echo htmlspecialchars($data['Nama_Barang']); ?>">
                                <?php echo htmlspecialchars($data['Nama_Barang']); ?>
                            </h3>

                            <!-- Brand -->
                            <p class="text-[9px] text-gray-600 mb-0.5 truncate"><?php echo htmlspecialchars($data['Merek']); ?></p>

                            <!-- Type Badge -->
                            <div class="mb-1">
                                <span class="inline-block px-1 py-0.5 rounded text-[8px] font-semibold bg-blue-100 text-blue-700">
                                    <?php echo htmlspecialchars($data['Type']); ?>
                                </span>
                            </div>

                            <!-- Info Compact -->
                            <div class="text-[8px] text-gray-600 space-y-0.5 mb-1">
                                <div class="truncate">
                                    <i class="fas fa-user text-gray-400 w-3"></i>
                                    <span class="truncate inline-block max-w-[75%]"><?php echo htmlspecialchars(!empty($data['User_Perangkat']) ? $data['User_Perangkat'] : '-'); ?></span>
                                </div>
                                <div class="truncate">
                                    <i class="fas fa-briefcase text-gray-400 w-3"></i>
                                    <span class="truncate inline-block max-w-[75%]"><?php echo htmlspecialchars(!empty($data['Jabatan']) ? $data['Jabatan'] : '-'); ?></span>
                                </div>
                                <div class="truncate">
                                    <i class="fas fa-id-card text-gray-400 w-3"></i>
                                    <span class="truncate inline-block max-w-[75%]"><?php echo htmlspecialchars(!empty($data['Id_Karyawan']) ? $data['Id_Karyawan'] : '-'); ?></span>
                                </div>
                                <div>
                                    <div class="flex items-start gap-1">
                                        <i class="fas fa-clipboard-list text-gray-400 w-3 mt-[1px]"></i>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-start justify-between gap-1">
                                                <span class="truncate inline-block max-w-[75%]" title="<?php echo htmlspecialchars($data['Spesifikasi'] ?? ''); ?>">
                                                    <?php echo htmlspecialchars(!empty($data['Spesifikasi']) ? $data['Spesifikasi'] : '-'); ?>
                                                </span>
                                                <?php if (!empty($data['Spesifikasi'])): ?>
                                                    <button type="button" class="grid-spec-toggle text-gray-500 hover:text-gray-700 px-1" aria-expanded="false" aria-label="Lihat detail spesifikasi">
                                                        <i class="fas fa-chevron-down grid-spec-chevron text-[9px] transition-transform duration-200"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (!empty($data['Spesifikasi'])): ?>
                                                <div class="grid-spec-detail hidden mt-0.5 text-[8px] text-gray-700">
                                                    <?php
                                                    $spec_lines = preg_split("/\r\n|\r|\n/", trim((string)($data['Spesifikasi'] ?? '')));
                                                    if (count($spec_lines) === 1) {
                                                        $single = trim($spec_lines[0]);
                                                        if (strpos($single, ';') !== false) {
                                                            $spec_lines = array_map('trim', explode(';', $single));
                                                        }
                                                    }
                                                    foreach ($spec_lines as $line) {
                                                        $line = trim((string)$line);
                                                        if ($line === '') continue;
                                                        echo '<div class="flex items-start gap-1">'
                                                            . '<span class="text-gray-400 leading-none">•</span>'
                                                            . '<span class="truncate whitespace-nowrap min-w-0 flex-1">' . htmlspecialchars($line) . '</span>'
                                                            . '</div>';
                                                    }
                                                    ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="truncate">
                                    <i class="fas fa-map-marker-alt text-gray-400 w-3"></i>
                                    <span class="truncate inline-block max-w-[75%]"><?php echo htmlspecialchars($data['Lokasi']); ?></span>
                                </div>
                                <div class="truncate">
                                    <i class="fas fa-calendar text-gray-400 w-3"></i>
                                    <span class="text-[8px]"><?php echo date('d M Y', strtotime($data['Waktu'])); ?></span>
                                </div>
                            </div>

                            <!-- Serial Number -->
                            <div class="mb-1">
                                <span class="text-[7px] font-mono bg-gray-100 px-1 py-0.5 rounded text-gray-700 block truncate">
                                    <?php echo htmlspecialchars($data['Serial_Number']); ?>
                                </span>
                            </div>

                            <!-- Status Badges -->
                            <div class="flex flex-wrap gap-0.5 mb-1">
                                <span class="inline-block px-1 py-0.5 rounded text-[7px] font-medium border <?php echo getStatusBadgeClass($data['Jenis_Barang']); ?>">
                                    <?php echo htmlspecialchars(substr($data['Jenis_Barang'], 0, 5)); ?>
                                </span>
                                <span class="inline-block px-1 py-0.5 rounded text-[7px] font-medium border <?php echo getStatusBadgeClass($data['Status_Kelayakan_Barang']); ?>">
                                    <?php echo htmlspecialchars($data['Status_Kelayakan_Barang']); ?>
                                </span>
                            </div>

                            <!-- Action Buttons (Bottom) -->
                            <div class="flex gap-0.5 pt-1 border-t border-gray-100 mt-auto">
                                <a href="viewer.php?id_peserta=<?php echo htmlspecialchars($data['id_peserta']); ?>" 
                                   class="flex-1 p-0.5 text-blue-600 hover:bg-blue-50 rounded border border-blue-200 hover:border-blue-300 transition-all text-center"
                                   title="View Details">
                                    <i class="fas fa-eye text-[9px]"></i>
                                </a>
                                <a href="../qr_print.php?id_peserta=<?php echo htmlspecialchars($data['id_peserta']); ?>" 
                                   class="flex-1 p-0.5 text-purple-600 hover:bg-purple-50 rounded border border-purple-200 hover:border-purple-300 transition-all text-center"
                                   title="QR Code">
                                    <i class="fas fa-qrcode text-[9px]"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php
                    $no++;
                    }
                    ?>
                </div>
            <?php else: ?>
                <!-- Table Wrapper Container -->
                <div class="table-wrapper">
                <!-- Table View -->
                <div class="sticky-table-container">
                    <div id="assets-table-container" class="overflow-x-auto relative table-sticky-wrap">
                        <table class="w-full table-sticky">
                            <thead class="bg-gradient-to-r from-gray-800 to-gray-900 text-white">
                                <tr>
                                    <th class="px-4 py-4 text-left font-semibold sticky-col col-no">No</th>
                                    <th class="px-4 py-4 text-left font-semibold sticky-col col-waktu">Waktu</th>
                                    <th class="px-4 py-4 text-left font-semibold sticky-col col-createby">Create By</th>
                                    <th class="px-4 py-4 text-left font-semibold sticky-col col-namabarang">Nama Barang</th>
                                    <th class="px-4 py-4 text-left font-semibold col-nomoraset">Nomor Aset</th>
                                    <th class="px-4 py-4 text-left font-semibold">Merek</th>
                                    <th class="px-4 py-4 text-left font-semibold">Type</th>
                                    <th class="px-4 py-4 text-left font-semibold">Serial Number</th>
                                    <th class="px-4 py-4 text-left font-semibold">Spesifikasi</th>
                                    <th class="px-4 py-4 text-left font-semibold">User Perangkat</th>
                                    <th class="px-4 py-4 text-left font-semibold">Lokasi</th>
                                    <th class="px-4 py-4 text-left font-semibold">Employee ID</th>
                                    <th class="px-4 py-4 text-left font-semibold">Jabatan</th>
                                    <th class="px-4 py-4 text-left font-semibold">Jenis Barang</th>
                                    <th class="px-4 py-4 text-left font-semibold">Status Barang</th>
                                    <th class="px-4 py-4 text-left font-semibold">Status LOP</th>
                                    <th class="px-4 py-4 text-left font-semibold">Layak/Tidak</th>
                                    <th class="px-4 py-4 text-left font-semibold">Photo Barang Lengkap</th>
                                    <th class="px-4 py-4 text-left font-semibold">Photo Depan</th>
                                    <th class="px-4 py-4 text-left font-semibold">Photo Belakang</th>
                                    <th class="px-4 py-4 text-left font-semibold">Photo SN</th>
                                    <th class="px-4 py-4 text-left font-semibold">Harga Barang</th>
                                    <th class="px-4 py-4 text-left font-semibold">Tahun Rilis</th>
                                    <th class="px-4 py-4 text-left font-semibold">Waktu Pembelian</th>
                                    <th class="px-4 py-4 text-left font-semibold">Nama Vendor</th>
                                    <th class="px-4 py-4 text-left font-semibold">Kategori Pembelian</th>
                                    <th class="px-4 py-4 text-left font-semibold">Link Pembelian</th>
                                    <th class="px-4 py-4 text-center font-semibold sticky-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                mysqli_data_seek($hasil, 0); // Reset result pointer
                                $no = $start_from + 1;
                                while ($data = mysqli_fetch_array($hasil)) {
                                    $rowClass = ($no % 2 == 0) ? 'bg-white' : 'bg-gray-50/50';
                                ?>
                                <tr class="table-row border-b border-gray-100 <?php echo $rowClass; ?> hover:bg-gradient-to-r hover:from-blue-50 hover:to-purple-50">
                                    <td class="px-4 py-4 font-medium text-gray-900 sticky-col col-no"><?php echo $no++; ?></td>
                                    <td class="px-4 py-4 text-gray-700 sticky-col col-waktu"><?php echo $data["Waktu"]; ?></td>
                                    <td class="px-4 py-4 text-gray-700 sticky-col col-createby">
                                        <?php 
                                        // Tampilkan creator/admin pembuat data berdasarkan kolom Create_By pada row.
                                        // (Jangan pakai session user yang sedang login karena akan membuat semua baris sama.)
                                        $createBy = isset($data['Create_By']) ? trim((string)$data['Create_By']) : '';
                                        echo htmlspecialchars($createBy !== '' ? $createBy : '-');
                                        ?>
                                    </td>
                                    <td class="px-4 py-4 font-medium text-gray-900 max-w-xs sticky-col col-namabarang">
                                        <div class="truncate hover:whitespace-normal hover:overflow-visible" title="<?php echo htmlspecialchars($data['Nama_Barang']); ?>">
                                            <?php echo htmlspecialchars($data["Nama_Barang"]); ?>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 text-gray-700 font-mono text-sm col-nomoraset"><?php echo htmlspecialchars($data["Nomor_Aset"] ?? ''); ?></td>
                                    <td class="px-4 py-4 text-gray-700"><?php echo htmlspecialchars($data["Merek"]); ?></td>
                                    <td class="px-4 py-4 text-gray-700"><?php echo htmlspecialchars($data["Type"]); ?></td>
                                    <td class="px-4 py-4 text-gray-700 font-mono text-sm"><?php echo htmlspecialchars($data["Serial_Number"]); ?></td>
                                    <td class="px-4 py-4 text-gray-700 max-w-xs">
                                        <div class="line-clamp-2 overflow-hidden" title="<?php echo htmlspecialchars($data["Spesifikasi"]); ?>">
                                            <?php echo htmlspecialchars($data["Spesifikasi"]); ?>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 text-gray-700 max-w-xs">
                                        <div class="line-clamp-2 overflow-hidden" title="<?php echo htmlspecialchars($data["User_Perangkat"] ?? ''); ?>">
                                            <?php echo htmlspecialchars($data["User_Perangkat"] ?? ''); ?>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 text-gray-700 max-w-xs">
                                        <div class="line-clamp-2 overflow-hidden" title="<?php echo htmlspecialchars($data["Lokasi"]); ?>">
                                            <?php echo htmlspecialchars($data["Lokasi"]); ?>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 text-gray-700"><?php echo htmlspecialchars($data["Id_Karyawan"]); ?></td>
                                    <td class="px-4 py-4 text-gray-700"><?php echo htmlspecialchars($data["Jabatan"]); ?></td>
                                    
                                    <!-- Modern Status Badges -->
                                    <td class="px-4 py-4">
                                        <span class="status-badge <?php echo getStatusBadgeClass($data['Jenis_Barang']); ?>">
                                            <?php echo htmlspecialchars($data['Jenis_Barang']); ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-4">
                                        <span class="status-badge <?php echo getStatusBadgeClass($data['Status_Barang']); ?>">
                                            <?php echo htmlspecialchars($data['Status_Barang']); ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-4">
                                        <span class="status-badge <?php echo getStatusBadgeClass($data['Status_LOP']); ?>">
                                            <?php echo htmlspecialchars($data['Status_LOP']); ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-4">
                                        <span class="status-badge <?php echo getStatusBadgeClass($data['Status_Kelayakan_Barang']); ?>">
                                            <?php echo htmlspecialchars($data['Status_Kelayakan_Barang']); ?>
                                        </span>
                                    </td>
                                    
                                    <!-- Photo -->
                                    <td class="px-4 py-4">
                                        <?php if (!empty($data['Photo_Barang'])): ?>
                                            <div class="w-16 h-16 rounded-lg overflow-hidden shadow-md cursor-pointer hover:shadow-lg transition-all duration-300 hover:scale-105">
                                                <img src="../uploads/<?php echo htmlspecialchars($data['Photo_Barang']); ?>" 
                                                     alt="Foto Barang" 
                                                     class="w-full h-full object-cover"
                                                     onclick="openImageModal('../uploads/<?php echo htmlspecialchars($data['Photo_Barang']); ?>')">
                                            </div>
                                        <?php else: ?>
                                            <div class="w-16 h-16 bg-gray-200 rounded-lg flex items-center justify-center">
                                                <i class="fas fa-image text-gray-400"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Photo Depan -->
                                    <td class="px-4 py-4">
                                        <?php if (!empty($data['Photo_Depan'])): ?>
                                            <div class="w-16 h-16 rounded-lg overflow-hidden shadow-md cursor-pointer hover:shadow-lg transition-all duration-300 hover:scale-105">
                                                <img src="../uploads/<?php echo htmlspecialchars($data['Photo_Depan']); ?>" 
                                                     alt="Foto Depan" 
                                                     class="w-full h-full object-cover"
                                                     onclick="openImageModal('../uploads/<?php echo htmlspecialchars($data['Photo_Barang']); ?>')">
                                            </div>
                                        <?php else: ?>
                                            <div class="w-16 h-16 bg-gray-200 rounded-lg flex items-center justify-center">
                                                <i class="fas fa-image text-gray-400"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <!-- Photo Belakang -->
                                    <td class="px-4 py-4">
                                        <?php if (!empty($data['Photo_Belakang'])): ?>
                                            <div class="w-16 h-16 rounded-lg overflow-hidden shadow-md cursor-pointer hover:shadow-lg transition-all duration-300 hover:scale-105">
                                                <img src="../uploads/<?php echo htmlspecialchars($data['Photo_Belakang']); ?>" 
                                                     alt="Foto Belakang" 
                                                     class="w-full h-full object-cover"
                                                     onclick="openImageModal('../uploads/<?php echo htmlspecialchars($data['Photo_Belakang']); ?>')">
                                            </div>
                                        <?php else: ?>
                                            <div class="w-16 h-16 bg-gray-200 rounded-lg flex items-center justify-center">
                                                <i class="fas fa-image text-gray-400"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Photo SN -->
                                    <td class="px-4 py-4">
                                        <?php if (!empty($data['Photo_SN'])): ?>
                                            <div class="w-16 h-16 rounded-lg overflow-hidden shadow-md cursor-pointer hover:shadow-lg transition-all duration-300 hover:scale-105">
                                                <img src="../uploads/<?php echo htmlspecialchars($data['Photo_SN']); ?>" 
                                                     alt="Foto SN" 
                                                     class="w-full h-full object-cover"
                                                     onclick="openImageModal('../uploads/<?php echo htmlspecialchars($data['Photo_SN']); ?>')">
                                            </div>
                                        <?php else: ?>
                                            <div class="w-16 h-16 bg-gray-200 rounded-lg flex items-center justify-center">
                                                <i class="fas fa-image text-gray-400"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Harga Barang -->
                                    <td class="px-4 py-4 text-gray-700 font-medium">
                                        <?php echo htmlspecialchars(formatRupiah($data['Harga_Barang'] ?? '')); ?>
                                    </td>

                                    <!-- Tahun Rilis -->
                                    <td class="px-4 py-4 text-gray-700">
                                        <?php echo htmlspecialchars($data['Tahun_Rilis'] ?? '-'); ?>
                                    </td>

                                    <!-- Waktu Pembelian -->
                                    <td class="px-4 py-4 text-gray-700">
                                        <?php echo htmlspecialchars($data['Waktu_Pembelian'] ?? '-'); ?>
                                    </td>

                                    <!-- Nama Vendor -->
                                    <td class="px-4 py-4 text-gray-700">
                                        <?php echo htmlspecialchars($data['Nama_Toko_Pembelian'] ?? '-'); ?>
                                    </td>

                                    <!-- Kategori Pembelian -->
                                    <td class="px-4 py-4 text-gray-700">
                                        <?php echo htmlspecialchars($data['Kategori_Pembelian'] ?? '-'); ?>
                                    </td>

                                    <!-- Link Pembelian -->
                                    <td class="px-4 py-4 text-gray-700">
                                        <?php
                                        $kategoriPembelianRaw = trim((string)($data['Kategori_Pembelian'] ?? ''));
                                        $linkPembelianRaw = trim((string)($data['Link_Pembelian'] ?? ''));
                                        if (strcasecmp($kategoriPembelianRaw, 'Online') !== 0) {
                                            echo '-';
                                        } elseif ($linkPembelianRaw === '') {
                                            echo '-';
                                        } elseif (preg_match('~^https?://~i', $linkPembelianRaw)) {
                                            $safeHref = htmlspecialchars($linkPembelianRaw, ENT_QUOTES);
                                            $safeTitle = htmlspecialchars($linkPembelianRaw, ENT_QUOTES);
                                            echo '<a href="' . $safeHref . '" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline" title="' . $safeTitle . '"><i class="fas fa-link"></i></a>';
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>



                                        <!-- Modern Action Buttons -->
                                    <td class="px-4 py-4 sticky-right">
                                        <div class="flex items-center justify-center space-x-2">
                                            <a href="viewer.php?id_peserta=<?php echo htmlspecialchars($data['id_peserta']); ?>" 
                                               class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg border border-blue-200 hover:border-blue-300 transition-all duration-300 hover:scale-105">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="../qr_print.php?id_peserta=<?php echo htmlspecialchars($data['id_peserta']); ?>" 
                                               class="p-2 text-purple-600 hover:bg-purple-50 rounded-lg border border-purple-200 hover:border-purple-300 transition-all duration-300 hover:scale-105">
                                                <i class="fas fa-qrcode"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                </div>
            <?php endif; ?>

            <!-- Pagination Container -->
            <div id="pagination-container" class="mt-6 flex flex-col items-center">
            <!-- Modern Pagination (matching dashboard_admin.php) -->
            <?php if ($total_pages > 1): ?>
            <div class="w-full max-w-4xl flex flex-col items-center space-y-2">
                <div class="text-sm text-gray-600">
                    Showing <?php echo min(($page - 1) * $limit + 1, $total_records); ?> 
                    to <?php echo min($page * $limit, $total_records); ?> 
                    of <?php echo $total_records; ?> results
                </div>
                <nav class="inline-flex rounded-md shadow-sm -space-x-px justify-center">
                    <?php
                    // Build URL parameters
                    $url_params = [];
                    if (!empty($search_query)) $url_params[] = "search=" . urlencode($search_query);
                    if (!empty($kategori)) $url_params[] = "kategori=" . urlencode($kategori);
                    if (!empty($status_filter)) $url_params[] = "status_filter=" . urlencode($status_filter);
                    if (!empty($brand_filter)) $url_params[] = "brand_filter=" . urlencode($brand_filter);
                    if (!empty($status_lop_filter)) $url_params[] = "status_lop_filter=" . urlencode($status_lop_filter);
                    if (!empty($status_kelayakan_filter)) $url_params[] = "status_kelayakan_filter=" . urlencode($status_kelayakan_filter);
                    if (!empty($start_date)) $url_params[] = "start_date=" . urlencode($start_date);
                    if (!empty($end_date)) $url_params[] = "end_date=" . urlencode($end_date);
                    if (!empty($view_mode)) $url_params[] = "view_mode=" . urlencode($view_mode);
                    $url_suffix = !empty($url_params) ? '&' . implode('&', $url_params) : '';
                    
                    // Previous
                    if ($page > 1): ?>
                        <a href="view.php?page=<?php echo ($page - 1) . $url_suffix; ?>" class="pagination-link relative inline-flex items-center px-3 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                            <i class="fas fa-chevron-left"></i>
                            <span class="ml-1">Prev</span>
                        </a>
                    <?php endif; ?>
                    
                    <?php
                    // Pages
                    $start = max(1, $page - 2);
                    $end = min($total_pages, $page + 2);
                    
                    if ($start > 1): ?>
                        <a href="view.php?page=1<?php echo $url_suffix; ?>" class="pagination-link relative inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">1</a>
                        <?php if ($start > 2): ?>
                            <span class="relative inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-500">...</span>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php for ($i = $start; $i <= $end; $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="relative z-10 inline-flex items-center px-3 py-2 border border-orange-500 bg-orange-50 text-sm font-medium text-orange-600"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="view.php?page=<?php echo $i . $url_suffix; ?>" class="pagination-link relative inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($end < $total_pages): ?>
                        <?php if ($end < $total_pages - 1): ?>
                            <span class="relative inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-500">...</span>
                        <?php endif; ?>
                        <a href="view.php?page=<?php echo $total_pages . $url_suffix; ?>" class="pagination-link relative inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50"><?php echo $total_pages; ?></a>
                    <?php endif; ?>
                    
                    <?php
                    // Next
                    if ($page < $total_pages): ?>
                        <a href="view.php?page=<?php echo ($page + 1) . $url_suffix; ?>" class="pagination-link relative inline-flex items-center px-3 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                            <span class="mr-1">Next</span>
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </nav>
            </div>
            <?php endif; ?>
            </div>
            <!-- Total Data Display -->
            <div class="text-center text-gray-700 mt-6 mb-16">
                <div class="bg-white/80 backdrop-blur-sm rounded-lg p-4 inline-block shadow-lg border border-gray-200">
                    <span class="text-lg font-medium">Total Data: </span>
                    <span class="text-xl font-bold bg-gradient-to-r from-blue-500 to-purple-600 bg-clip-text text-transparent"><?php echo $total_records; ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Detail Modal (untuk teks panjang) -->
    <div id="detailModal" class="detail-modal">
        <div class="detail-modal-content">
            <div class="detail-modal-header">
                <h3 id="detailModalTitle">Detail</h3>
                <button class="detail-modal-close" onclick="closeDetailModal()">×</button>
            </div>
            <div class="detail-modal-body" id="detailModalBody"></div>
        </div>
    </div>

    <!-- Image Modal -->
    <div id="imageModal" class="fixed inset-0 z-[9999] flex items-center justify-center hidden">
        <!-- Backdrop -->
        <div class="modal-backdrop absolute inset-0" onclick="closeImageModal()"></div>
        
        <!-- Modal Content -->
        <div class="modal-content relative z-10 w-full h-full flex flex-col">
            <!-- Header -->
            <div class="flex items-center justify-between p-4 bg-black/20 backdrop-blur-sm">
                <div class="flex items-center space-x-4">
                    <h3 class="text-white font-semibold">Asset Image Viewer</h3>
                    <div class="text-sm text-white/70" id="imageInfo">
                        Click and drag to pan, scroll to zoom
                    </div>
                </div>

                <!-- Controls -->
                <div class="flex items-center space-x-2">
                    <button onclick="zoomOut()" class="p-2 text-white hover:bg-white/20 rounded-lg transition-all duration-200" title="Zoom Out">
                        <i class="fas fa-search-minus"></i>
                    </button>
                    
                    <button onclick="zoomIn()" class="p-2 text-white hover:bg-white/20 rounded-lg transition-all duration-200" title="Zoom In">
                        <i class="fas fa-search-plus"></i>
                    </button>
                    
                    <button onclick="resetZoom()" class="px-3 py-2 text-sm text-white hover:bg-white/20 rounded-lg transition-all duration-200" title="Reset View">
                        Reset
                    </button>
                    
                    <button onclick="downloadImage()" class="p-2 text-white hover:bg-white/20 rounded-lg transition-all duration-200" title="Download Image">
                        <i class="fas fa-download"></i>
                    </button>
                    
                    <button onclick="closeImageModal()" class="p-2 text-white hover:bg-white/20 rounded-lg transition-all duration-200" title="Close">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>

            <!-- Image Container -->
            <div class="flex-1 flex items-center justify-center overflow-hidden cursor-grab" id="imageContainer">
                <img id="modalImage" src="" alt="Asset Image" class="max-w-none max-h-none object-contain transition-transform duration-200 select-none" style="transform: scale(1)">
            </div>

            <!-- Footer -->
            <div class="p-4 bg-black/20 backdrop-blur-sm">
                <div class="text-center text-sm text-white/70">
                    Use mouse wheel to zoom, drag to pan when zoomed in, or use the controls above
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    
    

    <script>
        $(document).ready(function() {
            // FIX: Debug form submit (hapus setelah test)
$('form[method="GET"]').on('submit', function() {
    var lopValue = $('select[name="status_lop_filter"]').val();
    console.log('Submitting Status LOP value:', lopValue);  // Cek di browser console (F12)
    if (!lopValue) console.warn('Status LOP value kosong - cek select!');
});
    const urlParams = new URLSearchParams(window.location.search);
            
             console.log('jQuery loaded:', typeof $ !== 'undefined');
    console.log('Select2 loaded:', typeof $.fn.select2 !== 'undefined');
    if (typeof $.fn.select2 === 'undefined') {
        console.error('Select2 JS not loaded! Check CDN or internet.');
    }
           // Load state dari localStorage jika desktop (persist open/closed)
    let sidebarOpen;
    if (window.innerWidth >= 1024) {
        sidebarOpen = localStorage.getItem('sidebarOpenDesktop') === 'true';  // Load dari storage, default true jika kosong
    } else {
        sidebarOpen = false;  // Mobile default hidden
    }

    // Modern Sidebar Toggle with Animation
    const sidebar = $('#sidebar');
    const overlay = $('#overlay');
    const hamburgerBtn = $('#hamburger-btn');
    const closeSidebar = $('#close-sidebar');
    const mainContent = $('#main-content');
    const hamburgerIcon = $('#hamburger-icon');

    // Initialize state
    function initializeSidebar() {
        // Clean semua margin class dulu untuk hindari konflik
        mainContent.removeClass('ml-0 ml-80');
        
        if (window.innerWidth >= 1024) {
            // Desktop - load state dari storage (persist open/closed)
            const storedState = localStorage.getItem('sidebarOpenDesktop') === 'true';
            sidebarOpen = storedState;  // Gunakan state tersimpan
            
            if (sidebarOpen) {
                sidebar.removeClass('-translate-x-full').addClass('translate-x-0');
                mainContent.addClass('ml-80');  // Push jika open
            } else {
                sidebar.addClass('-translate-x-full').removeClass('translate-x-0');
                mainContent.addClass('ml-0');  // Ketarik jika closed
            }
        } else {
            // Mobile - hidden by default (tidak persist)
            sidebar.addClass('-translate-x-full').removeClass('translate-x-0');
            overlay.addClass('hidden');
            mainContent.addClass('ml-0');
            sidebarOpen = false;
        }
        updateHamburgerIcon();
    }

    // Update hamburger icon
    function updateHamburgerIcon() {
        if (sidebarOpen) {
            hamburgerIcon.removeClass('fa-bars').addClass('fa-times');
        } else {
            hamburgerIcon.removeClass('fa-times').addClass('fa-bars');
        }
    }

    // Toggle sidebar
    hamburgerBtn.on('click', function() {
        sidebarOpen = !sidebarOpen;
        
        if (window.innerWidth >= 1024) {
            // Desktop behavior: toggle show/hide dengan push content
            mainContent.removeClass('ml-0 ml-80');  // Clean dulu
            
            if (sidebarOpen) {
                sidebar.removeClass('-translate-x-full').addClass('translate-x-0');
                mainContent.addClass('ml-80');  // Push ke kanan
            } else {
                sidebar.addClass('-translate-x-full').removeClass('translate-x-0');
                mainContent.addClass('ml-0');  // Ketarik ke kiri
            }
            // Save state ke localStorage untuk persist
            localStorage.setItem('sidebarOpenDesktop', sidebarOpen);
        } else {
            // Mobile behavior: toggle dengan overlay, tanpa push content (tetap sama, no persist)
            if (sidebarOpen) {
                sidebar.removeClass('-translate-x-full').addClass('translate-x-0');
                overlay.removeClass('hidden');
            } else {
                sidebar.addClass('-translate-x-full').removeClass('translate-x-0');
                overlay.addClass('hidden');
            }
        }
        
        updateHamburgerIcon();
    });

    // Close sidebar
    closeSidebar.on('click', function() {
        sidebarOpen = false;
        sidebar.addClass('-translate-x-full').removeClass('translate-x-0');
        overlay.addClass('hidden');
        
        // Clean dan force adjust main content
        mainContent.removeClass('ml-0 ml-80').addClass('ml-0');  // Ketarik ke kiri
        
        // Save state ke localStorage untuk persist di desktop
        if (window.innerWidth >= 1024) {
            localStorage.setItem('sidebarOpenDesktop', sidebarOpen);  // Save false (closed)
        }
        
        updateHamburgerIcon();
    });

    // Close sidebar when clicking overlay
    overlay.on('click', function() {
        sidebarOpen = false;
        sidebar.addClass('-translate-x-full').removeClass('translate-x-0');
        overlay.addClass('hidden');
        updateHamburgerIcon();
    });

    // Mobile Search Toggle
    const mobileSearchToggle = $('#mobile-search-toggle');
    const mobileSearchForm = $('#mobile-search-form');
    const searchInputMobile = $('#search-input-mobile');

    mobileSearchToggle.on('click', function() {
        if (mobileSearchForm.hasClass('hidden')) {
            // Show form with fade-in and zoom-in
            mobileSearchForm.removeClass('hidden');
            setTimeout(() => {
                mobileSearchForm.removeClass('opacity-0 scale-95');
                mobileSearchForm.addClass('opacity-100 scale-100');
                searchInputMobile.focus();
            }, 10);
        } else {
            // Hide form with fade-out and zoom-out
            mobileSearchForm.removeClass('opacity-100 scale-100');
            mobileSearchForm.addClass('opacity-0 scale-95');
            setTimeout(() => {
                mobileSearchForm.addClass('hidden');
            }, 300);
        }
    });

    // AJAX Pagination Handler
    function loadPage(page) {
        const urlParams = new URLSearchParams(window.location.search);
        const status_filter = urlParams.get('status_filter') || '';
        const kategori = urlParams.get('kategori') || '';
        const search = urlParams.get('search') || '';
        const brand_filter = urlParams.get('brand_filter') || '';
        const status_lop_filter = urlParams.get('status_lop_filter') || '';
        const status_kelayakan_filter = urlParams.get('status_kelayakan_filter') || '';
        const start_date = urlParams.get('start_date') || '';
        const end_date = urlParams.get('end_date') || '';
        const per_page = urlParams.get('per_page') || '';
        const view_mode = urlParams.get('view_mode') || (document.querySelector('.asset-grid') ? 'grid' : 'table');
        
        const fetchUrl = `view.php?action=ajax_get_assets&page=${page}&status_filter=${encodeURIComponent(status_filter)}&kategori=${encodeURIComponent(kategori)}&search=${encodeURIComponent(search)}&brand_filter=${encodeURIComponent(brand_filter)}&status_lop_filter=${encodeURIComponent(status_lop_filter)}&status_kelayakan_filter=${encodeURIComponent(status_kelayakan_filter)}&start_date=${encodeURIComponent(start_date)}&end_date=${encodeURIComponent(end_date)}&view_mode=${encodeURIComponent(view_mode)}${per_page ? `&per_page=${encodeURIComponent(per_page)}` : ''}`;
        
        fetch(fetchUrl)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    console.error('AJAX Error:', data.error);
                    Swal.fire('Error', 'Gagal memuat data: ' + data.error, 'error');
                    return;
                }
                
                // Update content sesuai view mode
                const resolvedMode = data.view_mode || view_mode;
                if (resolvedMode === 'grid') {
                    const gridContainer = document.querySelector('.asset-grid');
                    if (gridContainer) {
                        gridContainer.innerHTML = data.grid_html || '';
                    }
                } else {
                    const tableContainer = document.getElementById('assets-table-container');
                    if (tableContainer) {
                        tableContainer.innerHTML = '<table class="w-full table-sticky">' + (data.table_html || '') + '</table>';
                    }
                }
                
                // Update pagination
                const paginationContainer = document.getElementById('pagination-container');
                if (paginationContainer) {
                    paginationContainer.innerHTML = data.pagination_html;
                }
                
                // Reattach event listeners
                attachPaginationListeners();
            })
            .catch(error => {
                console.error('Fetch Error:', error);
                Swal.fire('Error', 'Gagal memuat data', 'error');
            });
    }
    
    // Attach pagination listeners
    function attachPaginationListeners() {
        const paginationLinks = document.querySelectorAll('.pagination-link');
        paginationLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const url = new URL(this.href);
                const page = url.searchParams.get('page');
                if (page) {
                    loadPage(page);
                    // Biarkan posisi scroll tetap, hanya update URL
                    history.pushState(null, '', this.href);
                }
            });
        });
    }
    
    // Attach pagination listeners on ready
    attachPaginationListeners();

    // PERBAIKAN: Init Select2 untuk Searchable Dropdown (pindah ke sini, dalam ready utama)
    if (typeof $.fn.select2 !== 'undefined') {
        console.log('Initializing Select2...');
        $('.select2-filter').select2({
            theme: 'bootstrap-5',  // Theme match Tailwind/Bootstrap
            placeholder: function() {
                return $(this).data('placeholder') || 'Pilih opsi...';
            },
            allowClear: true,  // Tambah tombol clear (X) di dropdown
            width: '100%',  // Full width
            dropdownParent: $('#main-content'),  // Hindari z-index issue dengan sidebar/modal
            language: {
                noResults: function() {
                    return "Tidak ditemukan hasil";
                },
                searching: function() {
                    return "Mencari...";
                },
                inputTooShort: function(args) {
                    return "Ketik " + (args.minimum - args.input.length) + " karakter lagi untuk mencari";
                }
            },
            minimumInputLength: 0  // Search langsung tanpa minimum char
        });
        console.log('Select2 initialized successfully on', $('.select2-filter').length, 'elements');
    } else {
        console.error('Select2 not available! Check CDN load.');
        // Fallback: Alert user jika Select2 gagal
        // alert('Fitur search dropdown tidak tersedia. Cek koneksi internet.');
    }

    // Responsive adjustments
    $(window).on('resize', function() {
        mainContent.removeClass('ml-0 ml-80');  // Clean dulu sebelum reinitialize
        initializeSidebar();
    });

    // Initialize page
    initializeSidebar();
});

// View Mode Change Function (di luar ready, tetap sama)
function changeViewMode(mode) {
    const url = new URL(window.location);
    url.searchParams.set('view_mode', mode);
    window.location.href = url.toString();
}

// Handle browser back/forward buttons for pagination
window.addEventListener('popstate', function(event) {
    const page = new URL(window.location).searchParams.get('page') || 1;
    if (typeof loadPage === 'function') {
        loadPage(page);
    }
});

// Image Modal Functions (tetap sama)
let currentZoom = 1;
let currentImageSrc = '';

function openImageModal(imageSrc) {
    currentImageSrc = imageSrc;
    currentZoom = 1;
    
    const modal = document.getElementById('imageModal');
    const modalImage = document.getElementById('modalImage');
    
    modalImage.src = imageSrc;
    modalImage.style.transform = 'scale(1)';
    modal.classList.remove('hidden');
    
    // Prevent body scroll
    document.body.style.overflow = 'hidden';
}

function closeImageModal() {
    const modal = document.getElementById('imageModal');
    modal.classList.add('hidden');
    
    // Restore body scroll
    document.body.style.overflow = 'auto';
}

function zoomIn() {
    currentZoom = Math.min(currentZoom + 0.25, 3);
    updateImageTransform();
}

function zoomOut() {
    currentZoom = Math.max(currentZoom - 0.25, 0.5);
    updateImageTransform();
}

// Grid Photo Navigation (Event Delegation)
function updateGridCardPhoto(cardEl, delta) {
    if (!cardEl) return;
    const photosRaw = cardEl.getAttribute('data-photos') || '[]';
    let photos = [];
    try {
        photos = JSON.parse(photosRaw);
    } catch (e) {
        photos = [];
    }
    if (!Array.isArray(photos) || photos.length <= 1) return;

    const currentIndex = parseInt(cardEl.getAttribute('data-photo-index') || '0', 10) || 0;
    const nextIndex = (currentIndex + delta + photos.length) % photos.length;
    cardEl.setAttribute('data-photo-index', String(nextIndex));

    const img = cardEl.querySelector('img.grid-card-image');
    if (!img) return;
    const nextSrc = photos[nextIndex] || '';
    if (!nextSrc) return;

    img.src = nextSrc;
    img.onclick = function() { openImageModal(nextSrc); };
}

$(document).on('click', '.grid-photo-prev', function(e) {
    e.preventDefault();
    e.stopPropagation();
    const cardEl = this.closest('[data-photos]');
    updateGridCardPhoto(cardEl, -1);
});

$(document).on('click', '.grid-photo-next', function(e) {
    e.preventDefault();
    e.stopPropagation();
    const cardEl = this.closest('[data-photos]');
    updateGridCardPhoto(cardEl, 1);
});

// Grid Spesifikasi Dropdown (Event Delegation)
$(document).on('click', '.grid-spec-toggle', function(e) {
    e.preventDefault();
    e.stopPropagation();

    const clickedToggle = this;
    const cardEl = clickedToggle.closest('[data-photos]') || clickedToggle.closest('.bg-white/90');

    // Close other spec dropdowns in the same card
    if (cardEl) {
        cardEl.querySelectorAll('.grid-spec-toggle[aria-expanded="true"]').forEach(btn => {
            if (btn === clickedToggle) return;
            btn.setAttribute('aria-expanded', 'false');
            const btnWrapper = btn.closest('.min-w-0');
            const btnDetail = btnWrapper ? btnWrapper.querySelector('.grid-spec-detail') : null;
            if (btnDetail) btnDetail.classList.add('hidden');
            const btnIcon = btn.querySelector('.grid-spec-chevron');
            if (btnIcon) btnIcon.classList.remove('rotate-180');
        });
    }

    // Toggle the clicked one
    const expanded = clickedToggle.getAttribute('aria-expanded') === 'true';
    const nextExpanded = !expanded;
    clickedToggle.setAttribute('aria-expanded', String(nextExpanded));

    const wrapper = clickedToggle.closest('.min-w-0');
    const detail = wrapper ? wrapper.querySelector('.grid-spec-detail') : null;
    if (detail) {
        detail.classList.toggle('hidden', !nextExpanded);
    }

    const icon = clickedToggle.querySelector('.grid-spec-chevron');
    if (icon) {
        icon.classList.toggle('rotate-180', nextExpanded);
    }
});

function setGridPhotoNavVisibility(cardEl, visible) {
    if (!cardEl) return;
    const navButtons = cardEl.querySelectorAll('.grid-photo-nav');
    if (!navButtons || navButtons.length === 0) return;
    navButtons.forEach(btn => {
        if (visible) {
            btn.classList.remove('opacity-0', 'pointer-events-none');
            btn.classList.add('opacity-100', 'pointer-events-auto');
        } else {
            btn.classList.remove('opacity-100', 'pointer-events-auto');
            btn.classList.add('opacity-0', 'pointer-events-none');
        }
    });
}

function hideAllGridPhotoNav() {
    document.querySelectorAll('[data-photos]').forEach(card => setGridPhotoNavVisibility(card, false));
}

// Mobile-friendly behavior: 1st tap on image shows arrows (no modal), 2nd tap opens modal
document.addEventListener('click', function(e) {
    const img = e.target && e.target.closest ? e.target.closest('img.grid-card-image') : null;
    if (!img) return;
    const cardEl = img.closest('[data-photos]');
    if (!cardEl) return;

    let photos = [];
    try {
        photos = JSON.parse(cardEl.getAttribute('data-photos') || '[]');
    } catch (_) {
        photos = [];
    }
    if (!Array.isArray(photos) || photos.length <= 1) return;

    const anyNavBtn = cardEl.querySelector('.grid-photo-nav');
    const navHidden = anyNavBtn ? anyNavBtn.classList.contains('opacity-0') : true;
    if (navHidden) {
        // Intercept click to prevent opening modal (inline onclick)
        e.preventDefault();
        e.stopImmediatePropagation();
        hideAllGridPhotoNav();
        setGridPhotoNavVisibility(cardEl, true);
        return;
    }
}, true);

// Hide arrows when user scrolls the grid/page
window.addEventListener('scroll', function() {
    hideAllGridPhotoNav();
}, { passive: true });

// Hide arrows when user taps outside
document.addEventListener('click', function(e) {
    const insideCard = e.target && e.target.closest ? e.target.closest('[data-photos]') : null;
    if (!insideCard) {
        hideAllGridPhotoNav();
    }
});

function resetZoom() {
    currentZoom = 1;
    updateImageTransform();
}

function updateImageTransform() {
    const modalImage = document.getElementById('modalImage');
    modalImage.style.transform = `scale(${currentZoom})`;
}

// Download Image (tetap sama)
function downloadImage() {
    fetch(currentImageSrc)
        .then(response => response.blob())
        .then(blob => {
            const url = window.URL.createObjectURL(blob);

            const now = new Date();
            const year = now.getFullYear();
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const day = String(now.getDate()).padStart(2, '0');
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');

            const fileName = `Foto_evidence_Asset_ITCKT_${year}${month}${day}_${hours}${minutes}${seconds}.jpg`;

            const link = document.createElement('a');
            link.href = url;
            link.download = fileName;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            // Release the object URL after download
            window.URL.revokeObjectURL(url);
        })
        .catch(() => {
            alert('Gagal mengunduh gambar.');
        });
}

// Keyboard shortcuts for modal (tetap sama)
document.addEventListener('keydown', function(e) {
    const modal = document.getElementById('imageModal');
    if (!modal.classList.contains('hidden')) {
        switch(e.key) {
            case 'Escape':
                closeImageModal();
                break;
            case '+':
            case '=':
                e.preventDefault();
                zoomIn();
                break;
            case '-':
                e.preventDefault();
                zoomOut();
                break;
            case '0':
                e.preventDefault();
                resetZoom();
                break;
        }
    }
});

// Mouse wheel zoom (tetap sama)
document.getElementById('imageContainer').addEventListener('wheel', function(e) {
    e.preventDefault();
    if (e.deltaY < 0) {
        zoomIn();
    } else {
        zoomOut();
    }
});

         // Loading Animation - Disable untuk pagination/filter
    // Jangan tampilkan loading saat pagination/filter/search
    window.addEventListener('beforeunload', function(e) {
        const referrer = document.referrer;
        const currentUrl = window.location.href;
    });

    // PERBAIKAN: Hide loading setelah page fully loaded (bukan di beforeunload)
    window.addEventListener('load', function() {
        const loadingOverlay = document.getElementById('loadingOverlay');
        if (loadingOverlay) {
            // Fade out effect
            loadingOverlay.style.opacity = '0';
            setTimeout(function() {
                loadingOverlay.style.display = 'none';
                loadingOverlay.style.visibility = 'hidden';
            }, 500);
        }
    });

    // Hide loading saat ada click pada link (immediate hide sebelum navigate)
    document.addEventListener('click', function(e) {
        const link = e.target.closest('a');
        if (link) {
            const loadingOverlay = document.getElementById('loadingOverlay');
            if (loadingOverlay) {
                loadingOverlay.style.display = 'none !important';
                loadingOverlay.style.opacity = '0 !important';
                loadingOverlay.style.visibility = 'hidden !important';
            }
        }
    });


     // Fixed Sidebar Toggle Functionality
        document.addEventListener('DOMContentLoaded', function() {
            const hamburgerBtn = document.getElementById('hamburger-btn');
            const closeSidebarBtn = document.getElementById('close-sidebar');
            const sidebar = document.getElementById('sidebar');
            const mobileOverlay = document.getElementById('mobile-overlay');
            const hamburgerIcon = document.getElementById('hamburger-icon');

            let sidebarOpen = false;

            // Toggle sidebar
            function toggleSidebar() {
                sidebarOpen = !sidebarOpen;
                updateSidebar();
            }

            // Update sidebar state
            function updateSidebar() {
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

            // Close sidebar
            function closeSidebar() {
                sidebarOpen = false;
                updateSidebar();
            }

            // Event listeners
            hamburgerBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                toggleSidebar();
            });

            closeSidebarBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                closeSidebar();
            });

            mobileOverlay.addEventListener('click', function(e) {
                e.preventDefault();
                closeSidebar();
            });

            // Close sidebar when clicking on sidebar links (mobile only)
            const sidebarLinks = sidebar.querySelectorAll('a');
            sidebarLinks.forEach(link => {
                link.addEventListener('click', function() {
                    if (window.innerWidth < 1024) {
                        closeSidebar();
                    }
                });
            });

            // Handle window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth >= 1024) {
                    closeSidebar();
                }
            });

            // Initialize charts
            
        });

        
            

// Global state untuk dropdown (FIX: Let di global scope, defined awal)
    let dropdownOpenState = false;

    // Fixed Sidebar Toggle Functionality + Dropdown (Semua di DOMContentLoaded untuk timing aman)
    document.addEventListener('DOMContentLoaded', function() {
        console.log('DEBUG: DOM loaded - Initializing sidebar, charts, and dropdown');

        const hamburgerBtn = document.getElementById('hamburger-btn');
        const closeSidebarBtn = document.getElementById('close-sidebar');
        const sidebar = document.getElementById('sidebar');
        const mobileOverlay = document.getElementById('mobile-overlay');
        const hamburgerIcon = document.getElementById('hamburger-icon');

        let sidebarOpen = false;

        // Toggle sidebar (tetap sama)
        function toggleSidebar() {
            sidebarOpen = !sidebarOpen;
            updateSidebar();
        }

        function updateSidebar() {
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

        function closeSidebar() {
            sidebarOpen = false;
            updateSidebar();
        }

        // Event listeners sidebar (tetap sama, dengan null check)
        if (hamburgerBtn) {
            hamburgerBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                toggleSidebar();
            });
        }

        if (closeSidebarBtn) {
            closeSidebarBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                closeSidebar();
            });
        }

        if (mobileOverlay) {
            mobileOverlay.addEventListener('click', function(e) {
                e.preventDefault();
                closeSidebar();
            });
        }

        if (sidebar) {
            const sidebarLinks = sidebar.querySelectorAll('a');
            sidebarLinks.forEach(link => {
                link.addEventListener('click', function() {
                    if (window.innerWidth < 1024) {
                        closeSidebar();
                    }
                });
            });
        }

        window.addEventListener('resize', function() {
            if (window.innerWidth >= 1024) {
                closeSidebar();
            }
        });

        // FIX DROPDOWN: Pure JS Event Listeners (Sekarang di DOMContentLoaded, dengan full debug)
        const settingsParent = document.querySelector('.settings-parent');
        const submenu = document.getElementById('settings-dropdown');
        const arrow = document.getElementById('settings-arrow');

        // Otomatis buka dropdown jika di halaman Settings
        if (<?php echo (isset($is_settings_page) && $is_settings_page) ? 'true' : 'false'; ?>) {
            dropdownOpenState = true;
            if (submenu && arrow) {
                submenu.classList.add('open');
                submenu.style.maxHeight = '200px';
                submenu.style.opacity = '1';
                arrow.style.transform = 'rotate(180deg)';
            }
        }
        console.log('DEBUG: Dropdown elements found:', {
            parent: !!settingsParent,
            submenu: !!submenu,
            arrow: !!arrow
        });

        if (settingsParent && submenu && arrow) {
            console.log('DEBUG: Attaching dropdown event listeners');

            // Event untuk parent: Toggle on click (dengan debounce)
            settingsParent.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                // Safeguard: Prevent multiple rapid clicks
                if (settingsParent.hasAttribute('data-clicking')) {
                    console.log('DEBUG: Click ignored (debounce active)');
                    return;
                }
                settingsParent.setAttribute('data-clicking', 'true');
                setTimeout(() => settingsParent.removeAttribute('data-clicking'), 300);
                
                console.log('DEBUG: Settings clicked - current state:', dropdownOpenState ? 'open (will close)' : 'closed (will open)');
                toggleDropdown();
            });

            // Event delegation untuk submenu items
            submenu.addEventListener('click', function(e) {
                const item = e.target.closest('.submenu-item');
                if (item) {
                    e.stopPropagation();
                    const url = item.dataset.url;
                    console.log('DEBUG: Submenu item clicked:', url);
                    selectSubmenuItem(url);
                }
            });
        } else {
            console.error('ERROR: Settings elements not found! Check HTML classes/IDs. Parent:', settingsParent, 'Submenu:', submenu, 'Arrow:', arrow);
        }

        // Fungsi toggle (logic clean)
        function toggleDropdown() {
            console.log('DEBUG: toggleDropdown called - state before:', dropdownOpenState);
            if (dropdownOpenState) {
                // Close
                submenu.classList.remove('open');
                submenu.style.maxHeight = '0px';
                submenu.style.opacity = '0';
                submenu.style.overflow = 'hidden';
                arrow.style.transform = 'rotate(0deg)';
                dropdownOpenState = false;
                console.log('DEBUG: Dropdown closed - state now:', dropdownOpenState);
            } else {
                // Open
                submenu.classList.add('open');
                submenu.style.maxHeight = '200px';
                submenu.style.opacity = '1';
                submenu.style.overflow = 'visible';
                arrow.style.transform = 'rotate(180deg)';
                dropdownOpenState = true;
                console.log('DEBUG: Dropdown opened - state now:', dropdownOpenState);
            }
        }

        // Fungsi select item
        function selectSubmenuItem(url) {
            console.log('DEBUG: selectSubmenuItem called for:', url);
            if (dropdownOpenState) {
                toggleDropdown();
            }
            setTimeout(() => {
                if (url && url !== '#') {
                    console.log('DEBUG: Redirecting to:', url);
                    window.location.href = url;
                } else {
                    console.log('DEBUG: No URL, staying here');
                }
            }, 300);
        }

        

        // Initialize charts (dengan null check)
        
        // TAMBAHAN: Auto-update Merek filter saat Kategori berubah (AJAX real-time)
        // Attach setelah Select2 loaded dengan delay kecil
        setTimeout(() => {
            const kategoriSelect = document.getElementById('kategori-filter');
            const brandSelect = document.getElementById('brand-filter');
            
            if (!kategoriSelect || !brandSelect) {
                console.error('ERROR: Filter elements not found');
                return;
            }
            
            console.log('DEBUG: Attaching kategori change listener');
            
            // Function untuk update brands
            function updateBrands() {
                const selectedKategori = kategoriSelect.value;
                console.log('DEBUG: updateBrands triggered - kategori:', selectedKategori);
                
                // AJAX call untuk get brands
                const url = 'api_get_brands.php?kategori=' + encodeURIComponent(selectedKategori);
                
                fetch(url)
                    .then(response => {
                        if (!response.ok) throw new Error('HTTP ' + response.status);
                        return response.json();
                    })
                    .then(data => {
                        console.log('DEBUG: API response:', data);
                        
                        if (!data.success) {
                            console.error('API error:', data.error);
                            return;
                        }
                        
                        // Destroy Select2 dulu sebelum update HTML
                        if (jQuery && jQuery(brandSelect).data('select2')) {
                            jQuery(brandSelect).select2('destroy');
                        }
                        
                        // Clear dan rebuild options
                        brandSelect.innerHTML = '<option value="">Semua Merek</option>';
                        
                        if (data.brands && data.brands.length > 0) {
                            data.brands.forEach(brand => {
                                const option = document.createElement('option');
                                option.value = brand;
                                option.textContent = brand;
                                brandSelect.appendChild(option);
                            });
                            console.log('DEBUG: Added ' + data.brands.length + ' brands');
                        }
                        
                        // Re-init Select2
                        if (jQuery && jQuery.fn.select2) {
                            jQuery(brandSelect).select2({
                                placeholder: 'Pilih atau ketik merek...',
                                allowClear: true,
                                width: '100%'
                            });
                        }
                    })
                    .catch(err => console.error('ERROR:', err));
            }
            
            // Event listener untuk kategori change
            kategoriSelect.addEventListener('change', updateBrands);
            console.log('DEBUG: kategori listener attached');
            
        }, 500); // Delay untuk ensure Select2 loaded
        
    });
    
       
    // Loading Animation (tetap sama)
    window.addEventListener('load', function() {
        console.log('DEBUG: Window loaded - Hiding loading overlay');
        setTimeout(function() {
            const loadingOverlay = document.getElementById('loadingOverlay');
            if (loadingOverlay) {
                loadingOverlay.style.opacity = '0';
                setTimeout(function() {
                    loadingOverlay.style.display = 'none';
                }, 500);
            }
        }, 2000);
    });

    


    // Event listener untuk semua item submenu
document.addEventListener('DOMContentLoaded', function() {

    const submenuItems = document.querySelectorAll('.submenu-item');
    
    submenuItems.forEach(function(item) {
        item.addEventListener('click', function(e) {
            e.preventDefault();  // Cegah perilaku default jika ada link lain
            
            const targetUrl = this.getAttribute('data-url');
            if (targetUrl) {
                // Opsi 1: Buka di tab/same window yang sama (ganti window.open jika ingin tab baru)
                window.location.href = targetUrl;  // Atau window.location.replace(targetUrl) untuk replace history
                
                // Opsi 2: Jika ingin buka di tab baru
                // window.open(targetUrl, '_blank');
            }
        });
    });
});


// Validasi duplikat Nama_Lengkap
const namaInput = document.getElementById('Nama_Lengkap');
const namaFeedback = document.getElementById('nama_feedback');
let namaDebounceTimer = null;
let isNamaAvailable = false;

if (namaInput && namaFeedback) {
    namaInput.addEventListener('input', function() {
        const value = this.value.trim();
        isNamaAvailable = false;

        // Reset feedback
        namaFeedback.innerHTML = '';
        namaFeedback.className = 'mt-1 text-sm flex items-center space-x-1 min-h-[20px] transition-all duration-200';
        this.classList.remove('border-red-500', 'border-green-500');

        if (value.length < 2) {
            return;
        }

        // Debounce
        clearTimeout(namaDebounceTimer);
        namaDebounceTimer = setTimeout(() => {
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'check_duplicate',
                    field: 'Nama_Lengkap',
                    value: value
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.exists) {
                        namaFeedback.innerHTML = `
                            <i class="fas fa-exclamation-circle text-red-500"></i>
                            <span class="text-red-600 font-medium">Nama ini sudah terdaftar!</span>
                        `;
                        this.classList.add('border-red-500');
                        isNamaAvailable = false;
                    } else {
                        namaFeedback.innerHTML = `
                            <i class="fas fa-check-circle text-green-500"></i>
                            <span class="text-green-600">Aman bisa daftarkan ✅</span>
                        `;
                        this.classList.add('border-green-500');
                        isNamaAvailable = true;
                    }
                } else {
                    namaFeedback.innerHTML = `<span class="text-yellow-600">Gagal memeriksa...</span>`;
                    isNamaAvailable = false;
                }
            })
            .catch(err => {
                console.error('Nama duplicate check error:', err);
                namaFeedback.innerHTML = `<span class="text-yellow-600">Error koneksi</span>`;
                isNamaAvailable = false;
            });
        }, 500);
    });
}





// Validasi duplikat untuk ID Karyawan (username)
const usernameInput = document.getElementById('username');
const usernameFeedback = document.getElementById('username_feedback');
let usernameDebounceTimer = null;
let isUsernameAvailable = false;

if (usernameInput && usernameFeedback) {
    usernameInput.addEventListener('input', function() {
        const value = this.value.trim();
        isUsernameAvailable = false;

        // Reset feedback
        usernameFeedback.innerHTML = '';
        usernameFeedback.className = 'mt-1 text-sm flex items-center space-x-1 min-h-[20px] transition-all duration-200';
        this.classList.remove('border-red-500', 'border-green-500');

        if (value.length < 2) return;

        clearTimeout(usernameDebounceTimer);
        usernameDebounceTimer = setTimeout(() => {
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'check_duplicate',
                    field: 'username',
                    value: value
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.exists) {
                        usernameFeedback.innerHTML = `
                            <i class="fas fa-exclamation-circle text-red-500"></i>
                            <span class="text-red-600 font-medium">ID ini sudah terdaftar!</span>
                        `;
                        this.classList.add('border-red-500');
                        isUsernameAvailable = false;
                    } else {
                        usernameFeedback.innerHTML = `
                            <i class="fas fa-check-circle text-green-500"></i>
                            <span class="text-green-600">Aman bisa daftarkan ✅</span>
                        `;
                        this.classList.add('border-green-500');
                        isUsernameAvailable = true;
                    }
                } else {
                    usernameFeedback.innerHTML = `<span class="text-yellow-600">Gagal memeriksa...</span>`;
                    isUsernameAvailable = false;
                }
            })
            .catch(err => {
                console.error('Username duplicate check error:', err);
                usernameFeedback.innerHTML = `<span class="text-yellow-600">Error koneksi</span>`;
                isUsernameAvailable = false;
            });
        }, 500);
    });
}

// Tambahkan atribut data untuk status validasi
if (namaInput) {
    namaInput.dataset.valid = 'unknown'; // 'unknown', 'valid', 'invalid'
}
if (usernameInput) {
    usernameInput.dataset.valid = 'unknown';
}

// Perbarui handler input untuk set atribut
// (Sudah ada di kode-mu, pastikan di akhir callback tambahkan:)
// → di dalam .then(data => { ... })
//    if (data.exists) { ... input.dataset.valid = 'invalid'; }
//    else { ... input.dataset.valid = 'valid'; }

// Submit handler yang benar
document.querySelector('form[method="POST"]').addEventListener('submit', function(e) {
    const namaVal = namaInput?.value.trim();
    const usernameVal = usernameInput?.value.trim();

    const namaStatus = namaInput?.dataset.valid;
    const usernameStatus = usernameInput?.dataset.valid;

    // Jika masih 'unknown', berarti belum selesai cek → biarkan kirim (server akan validasi)
    // Tapi jika sudah 'invalid', blokir
    if (namaStatus === 'invalid' || usernameStatus === 'invalid') {
        e.preventDefault();
        Swal.fire({
            title: 'Data Sudah Terdaftar!',
            text: 'Nama Lengkap atau ID Karyawan sudah digunakan. Silakan gunakan data lain.',
            icon: 'warning',
            confirmButtonText: 'OK'
        });
        if (namaStatus === 'invalid') namaInput?.focus();
        else usernameInput?.focus();
        return;
    }

    // Opsional: Jika masih 'unknown', tampilkan loading
    // Tapi biarkan form tetap submit — validasi akhir tetap di PHP
});

// Toggle password visibility - Tambah Akun
const togglePasswordBtn = document.getElementById('togglePassword');
const passwordInput = document.getElementById('password');
const eyeIcon = document.getElementById('eyeIcon');

if (togglePasswordBtn && passwordInput && eyeIcon) {
    togglePasswordBtn.addEventListener('click', function() {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        eyeIcon.classList.toggle('fa-eye-slash', type === 'password');
        eyeIcon.classList.toggle('fa-eye', type === 'text');
    });
}

// Toggle password visibility - Edit Akun
const toggleEditPasswordBtn = document.getElementById('toggleEditPassword');
const editPasswordInput = document.getElementById('editPassword');
const editEyeIcon = document.getElementById('editEyeIcon');

if (toggleEditPasswordBtn && editPasswordInput && editEyeIcon) {
    toggleEditPasswordBtn.addEventListener('click', function() {
        const type = editPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        editPasswordInput.setAttribute('type', type);
        editEyeIcon.classList.toggle('fa-eye-slash', type === 'password');
        editEyeIcon.classList.toggle('fa-eye', type === 'text');
    });
}

// Tampilkan notifikasi berdasarkan URL parameter
(function() {
    const urlParams = new URLSearchParams(window.location.search);
    
    // ✅ Sukses: akun ditambahkan/edit
    if (urlParams.has('success') && urlParams.get('success') === 'added') {
        window.history.replaceState({}, document.title, window.location.pathname);
        Swal.fire({
            title: '✅ Berhasil!',
            text: 'Akun baru berhasil ditambahkan.',
            icon: 'success',
            confirmButtonText: 'OK',
            customClass: {
                popup: 'animate__animated animate__fadeInUp animate__faster',
                confirmButton: 'bg-gradient-to-r from-orange-500 to-orange-600 text-white px-6 py-2 rounded-lg shadow-md hover:from-orange-600 hover:to-orange-700'
            },
            buttonsStyling: false
        });
    }

    // ✅ Error: gagal tambah/edit
    const errorMsg = urlParams.get('error');
    if (errorMsg) {
        window.history.replaceState({}, document.title, window.location.pathname);
        Swal.fire({
            title: '❌ Gagal!',
            text: errorMsg,
            icon: 'error',
            confirmButtonText: 'OK',
            customClass: {
                popup: 'animate__animated animate__fadeInUp animate__faster'
            }
        });
    }
})();

    
    

    </script>
</body>
</html>