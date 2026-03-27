<?php
// Start output buffering FIRST untuk avoid "headers already sent" error
ob_start();

// Debug log file (lebih mudah diakses daripada server error_log di shared hosting)
function create_debug_log($message) {
    $baseDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'temp';
    $fallbackDir = sys_get_temp_dir();
    $logDir = is_dir($baseDir) ? $baseDir : $fallbackDir;
    $logFile = $logDir . DIRECTORY_SEPARATOR . 'create_hosting_debug.log';

    $ts = date('Y-m-d H:i:s');
    $uri = isset($_SERVER['REQUEST_URI']) ? (string)$_SERVER['REQUEST_URI'] : '';
    $ip = isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : '';
    $line = '[' . $ts . '] ' . $ip . ' ' . $uri . ' | ' . $message . "\n";

    // Best effort logging; never break request
    @file_put_contents($logFile, $line, FILE_APPEND);
}

create_debug_log('BOOT php=' . PHP_VERSION . ' sapi=' . PHP_SAPI);

// Tangkap exception yang tidak tertangani (hosting sering melempar mysqli_sql_exception)
set_exception_handler(function ($e) {
    $msg = 'UNCAUGHT exception: ' . get_class($e) . ' code=' . (string)$e->getCode() . ' msg=' . (string)$e->getMessage();
    if (function_exists('create_debug_log')) {
        create_debug_log($msg);
    }
    error_log('CREATE.PHP ' . $msg);
    if (PHP_SAPI !== 'cli') {
        http_response_code(500);
        // Tampilkan pesan aman (tanpa detail) agar user tidak blank 500
        echo 'Terjadi kesalahan server saat menyimpan data. Silakan buka /admin/create.php?debug=1&showlog=1 untuk melihat log (super_admin).';
    }
    exit;
});

// Log fatal errors (helps debugging HTTP 500 on hosting)
register_shutdown_function(function () {
    $err = error_get_last();
    if (!$err) {
        return;
    }
    $fatalTypes = array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR);
    if (!in_array($err['type'], $fatalTypes, true)) {
        return;
    }
    $msg = sprintf(
        'FATAL in create.php: %s in %s:%s (URI=%s)',
        isset($err['message']) ? $err['message'] : 'unknown',
        isset($err['file']) ? $err['file'] : 'unknown',
        isset($err['line']) ? $err['line'] : 'unknown',
        isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : ''
    );
    error_log($msg);
    // Mirror to local debug file (hosting-friendly)
    if (function_exists('create_debug_log')) {
        create_debug_log($msg);
    }
});

// TAMBAHAN: Set timezone ke WIB (Asia/Jakarta) agar waktu upload akurat (GMT+7)
date_default_timezone_set('Asia/Jakarta');  // Fix waktu lokal Indonesia

// Mulai session
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Guard: hanya super_admin boleh akses halaman create
$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : 'user';
if ($user_role !== 'super_admin') {
    header("Location: ../user/view.php");
    exit();
}

// Optional: enable error display for hosting debugging (only for super_admin)
// Usage: /admin/create.php?debug=1
if (isset($_GET['debug']) && $_GET['debug'] === '1' && $user_role === 'super_admin') {
    @ini_set('display_errors', '1');
    @ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
    create_debug_log('DEBUG mode enabled');
}

// Endpoint untuk melihat log debug langsung (hanya super_admin)
if (
    isset($_GET['debug'], $_GET['showlog'])
    && $_GET['debug'] === '1'
    && $_GET['showlog'] === '1'
    && $user_role === 'super_admin'
) {
    $baseDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'temp';
    $fallbackDir = sys_get_temp_dir();
    $logDir = is_dir($baseDir) ? $baseDir : $fallbackDir;
    $logFile = $logDir . DIRECTORY_SEPARATOR . 'create_hosting_debug.log';
    header('Content-Type: text/plain; charset=UTF-8');
    if (is_file($logFile)) {
        // Batasi output supaya tidak terlalu besar
        $data = @file_get_contents($logFile);
        if ($data === false) {
            echo "Cannot read log file: {$logFile}\n";
        } else {
            // Show last ~20000 chars
            $max = 20000;
            if (strlen($data) > $max) {
                $data = substr($data, -$max);
                echo "(truncated)\n";
            }
            echo $data;
        }
    } else {
        echo "Log file not found: {$logFile}\n";
    }
    exit();
}

// ===== PENTING: SEMUA FORM PROCESSING HARUS SEBELUM OUTPUT APAPUN (SEBELUM DOCTYPE) =====

// Include file koneksi (lebih aman di hosting: path absolut berdasarkan file ini)
require_once __DIR__ . "/../koneksi.php";
create_debug_log('DB include ok');

// Fungsi redirect yang robust dengan JavaScript fallback
function safe_redirect($url) {
    if (!headers_sent()) {
        if (ob_get_level() > 0) {
            @ob_end_clean();
        }
        header("Location: " . $url);
        exit();
    } else {
        // Fallback ke JavaScript jika headers sudah dikirim
        echo '<script type="text/javascript">';
        echo 'window.location.href="' . $url . '";';
        echo '</script>';
        echo '<noscript>';
        echo '<meta http-equiv="refresh" content="0;url=' . $url . '" />';
        echo '</noscript>';
        exit();
    }
}

// Fungsi untuk mencegah inputan karakter yang tidak sesuai
function input($data) {
    if (is_array($data) || is_object($data)) {
        return '';
    }
    $data = (string)$data;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// TAMBAHAN: Fungsi untuk memproses upload foto dengan rename otomatis & folder dinamis
function processPhotoUpload($file_input_name, &$photo_variable) {
    global $kon;
    $target_dir = "../uploads/";
    
    if (isset($_FILES[$file_input_name]) && $_FILES[$file_input_name]["error"] == 0) {
        $uploadOk = 1;
        $imageFileType = strtolower(pathinfo($_FILES[$file_input_name]["name"], PATHINFO_EXTENSION));

        $isInvoice = ($file_input_name === 'Photo_Invoice');

        // Cek apakah file gambar (invoice boleh PDF)
        if (!$isInvoice || $imageFileType !== 'pdf') {
            if (function_exists('getimagesize')) {
                $check = @getimagesize($_FILES[$file_input_name]["tmp_name"]);
                if ($check === false) {
                    $uploadOk = 0;
                    return "File bukan gambar";
                }
            }
        }

        // Cek ukuran file (maksimum 2MB)
        if ($_FILES[$file_input_name]["size"] > 2000000) {
            return "Ukuran file terlalu besar. Maksimum 2MB.";
        }

        // Cek tipe file
        $allowed = array('jpg', 'png', 'jpeg', 'gif');
        if ($isInvoice) {
            $allowed[] = 'pdf';
        }
        if (!in_array($imageFileType, $allowed, true)) {
            return $isInvoice
                ? "Hanya file JPG, PNG, JPEG, GIF, dan PDF yang diperbolehkan."
                : "Hanya file JPG, PNG, JPEG, dan GIF yang diperbolehkan.";
        }

        // Tentukan folder berdasarkan tipe foto
        $subfolder = "";
        if ($file_input_name === "Photo_Depan") {
            $subfolder = "foto depan/";
        } elseif ($file_input_name === "Photo_Belakang") {
            $subfolder = "foto belakang/";
        } elseif ($file_input_name === "Photo_SN") {
            $subfolder = "foto sn/";
        } elseif ($file_input_name === "Photo_Invoice") {
            $subfolder = "foto invoice/";
        } elseif ($file_input_name === "Photo_Barang") {
            $subfolder = "";  // Langsung di uploads root
        }

        // Buat folder jika belum ada
        $upload_dir = $target_dir . $subfolder;
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) {
                return "Tidak dapat membuat folder upload. Cek permission folder uploads.";
            }
        }

        // Generate nama file baru dengan format Foto_evidence_[tipe]_Asset_ITCKT_DDMMYYYY_HHMMSS_uniqid.ext
        if ($uploadOk == 1) {
            $day = date('d');
            $month = date('m');
            $year = date('Y');
            $hour = date('H');
            $minute = date('i');
            $second = date('s');
            $unique_id = uniqid('_');  // Gunakan uniqid untuk memastikan filename unik
            
            $tglbulantahun = $day . $month . $year;
            $waktu = $hour . $minute . $second;
            
            $file_name = "Foto_evidence_" . $file_input_name . "_Asset_ITCKT_" . $tglbulantahun . "_" . $waktu . $unique_id . "." . $imageFileType;
            $target_file = $upload_dir . $file_name;

            // Coba untuk mengunggah file
            if (move_uploaded_file($_FILES[$file_input_name]["tmp_name"], $target_file)) {
                // Simpan path relative untuk database (termasuk subfolder)
                $photo_variable = $subfolder . $file_name;
                return true;
            } else {
                return "Terjadi kesalahan saat mengunggah file. Cek permission folder uploads.";
            }
        }
    }
    return true;  // File optional, tidak perlu error jika kosong
}

// Inisialisasi variabel inputan untuk menampilkan kembali data yang sudah dimasukkan
$Nomor_Aset = "";
$Nama_Barang = $Merek = $Type = $Spesifikasi = $Kelengkapan_Barang = $Kondisi_Barang = $Riwayat_Barang = $User_Perangkat = $Status_Barang = $Status_LOP = $Status_Kelayakan_Barang = $Serial_Number = $Jenis_Barang = $Lokasi = $Id_Karyawan = $Jabatan = $Harga_Barang = $Tahun_Rilis = $Waktu_Pembelian = $Nama_Toko_Pembelian = $Kategori_Pembelian = $Link_Pembelian = "";
$target_dir = "../uploads/"; // Direktori untuk menyimpan foto
$Photo_Barang = ""; // Inisialisasi variabel foto
$Photo_Depan = ""; // Inisialisasi variabel foto depan
$Photo_Belakang = ""; // Inisialisasi variabel foto belakang
$Photo_SN = ""; // Inisialisasi variabel foto SN
$Photo_Invoice = ""; // Inisialisasi dokumen invoice (opsional)

// Generate Nomor_Aset otomatis jika dikosongkan
function generateNomorAset($kon) {
    // Format master sederhana: ITCKT-YYYY-####
    $prefix = 'ITCKT-' . date('Y') . '-';

    // Jika kolom belum ada di DB / query gagal, fallback ke timestamp agar tetap unik
    $prefixEsc = mysqli_real_escape_string($kon, $prefix);
    $sql = "SELECT Nomor_Aset FROM peserta WHERE Nomor_Aset LIKE '{$prefixEsc}%' ORDER BY Nomor_Aset DESC LIMIT 1";
    $res = mysqli_query_safe($kon, $sql, 'generateNomorAset:last');
    if (!$res) {
        return 'ITCKT-' . date('Ymd-His');
    }

    $next = 1;
    if ($row = mysqli_fetch_assoc($res)) {
        $last = isset($row['Nomor_Aset']) ? (string)$row['Nomor_Aset'] : '';
        if (preg_match('/(\d{4})$/', $last, $m)) {
            $next = ((int)$m[1]) + 1;
        }
    }

    // Pastikan tidak menghasilkan nomor yang sudah dipakai (race condition / data lompat)
    for ($i = 0; $i < 80; $i++) {
        $candidate = $prefix . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
        $candEsc = mysqli_real_escape_string($kon, $candidate);
        $checkSql = "SELECT 1 FROM peserta WHERE Nomor_Aset = '{$candEsc}' LIMIT 1";
        $checkRes = mysqli_query_safe($kon, $checkSql, 'generateNomorAset:check');
        if ($checkRes && mysqli_num_rows($checkRes) === 0) {
            return $candidate;
        }
        if (!$checkRes) {
            // Jika query gagal (mis. kolom belum ada), fallback unik
            return 'ITCKT-' . date('Ymd-His');
        }
        $next++;
    }

    return 'ITCKT-' . date('Ymd-His');
}

// Ambil daftar kolom yang tersedia di tabel (untuk kompatibilitas DB di hosting)
function getTableColumnsAssoc($kon, $tableName) {
    $cols = array();
    $tableSafe = preg_replace('/[^A-Za-z0-9_]/', '', (string)$tableName);
    if ($tableSafe === '') {
        return null;
    }

    $res = mysqli_query_safe($kon, "SHOW COLUMNS FROM `{$tableSafe}`", 'SHOW COLUMNS');
    if (!$res) {
        return null;
    }
    while ($row = mysqli_fetch_assoc($res)) {
        if (isset($row['Field'])) {
            $cols[(string)$row['Field']] = true;
        }
    }
    return $cols;
}

// Wrapper query agar tidak jadi HTTP 500 di hosting ketika mysqli melempar exception
function mysqli_query_safe($conn, $sql, $context = '') {
    try {
        return @mysqli_query($conn, $sql);
    } catch (mysqli_sql_exception $e) {
        $errnoConn = @mysqli_errno($conn);
        $errno = $errnoConn ? (int)$errnoConn : (int)$e->getCode();
        $errTextConn = @mysqli_error($conn);
        $errText = ($errTextConn !== null && $errTextConn !== '') ? (string)$errTextConn : (string)$e->getMessage();
        if (function_exists('create_debug_log')) {
            create_debug_log('SQL exception' . ($context !== '' ? (' [' . $context . ']') : '') . ' errno=' . $errno . ' err=' . $errText);
        }
        error_log('CREATE.PHP SQL exception' . ($context !== '' ? (' [' . $context . ']') : '') . ': errno=' . $errno . ' err=' . $errText);
        return false;
    }
}

// ===== HANDLE FORM SUBMISSION =====
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    error_log('CREATE.PHP: POST start (URI=' . (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '') . ')');
    create_debug_log('POST start content_length=' . (isset($_SERVER['CONTENT_LENGTH']) ? (string)$_SERVER['CONTENT_LENGTH'] : '') . ' post=' . count($_POST) . ' files=' . count($_FILES));

    // Deteksi kondisi umum di hosting: payload POST terlalu besar,
    // sehingga PHP mengosongkan $_POST dan $_FILES.
    if (empty($_POST) && empty($_FILES)) {
        $contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int)$_SERVER['CONTENT_LENGTH'] : 0;
        if ($contentLength > 0) {
            $_SESSION['form_error'] = 'Upload gagal: ukuran data melebihi batas server (post_max_size / upload_max_filesize). Kompres foto atau kecilkan ukuran file.';
            error_log('CREATE.PHP: POST likely too large (CONTENT_LENGTH=' . $contentLength . ')');
            create_debug_log('POST empty but CONTENT_LENGTH>0; likely post_max_size/upload_max_filesize');
            safe_redirect($_SERVER['PHP_SELF']);
        }
    }

    $Nomor_Aset = isset($_POST["Nomor_Aset"]) ? trim((string)$_POST["Nomor_Aset"]) : '';
    $Nama_Barang = input(isset($_POST["Nama_Barang"]) ? $_POST["Nama_Barang"] : '');
    $Merek = input(isset($_POST["Merek"]) ? $_POST["Merek"] : '');
    $Type = input(isset($_POST["Type"]) ? $_POST["Type"] : '');
    $Spesifikasi = input(isset($_POST["Spesifikasi"]) ? $_POST["Spesifikasi"] : '');
    $Kelengkapan_Barang = input(isset($_POST["Kelengkapan_Barang"]) ? $_POST["Kelengkapan_Barang"] : '');
    $Kondisi_Barang = input(isset($_POST["Kondisi_Barang"]) ? $_POST["Kondisi_Barang"] : '');
    // Untuk Riwayat_Barang (JSON), jangan pakai htmlspecialchars - ambil langsung
    $Riwayat_Barang = isset($_POST["Riwayat_Barang"]) ? trim($_POST["Riwayat_Barang"]) : '';

    // Server-side validation: Riwayat_Barang wajib minimal 1 entry
    $decoded_riwayat = array();
    if ($Riwayat_Barang !== '') {
        $decoded_riwayat = json_decode($Riwayat_Barang, true);
        if (!is_array($decoded_riwayat)) {
            $decoded_riwayat = array();
        }
    }
    if (count($decoded_riwayat) === 0) {
        $_SESSION['form_error'] = 'Minimal harus ada 1 entry di Riwayat Barang sebelum simpan.';
        create_debug_log('Validation failed: Riwayat_Barang empty');
        safe_redirect($_SERVER['PHP_SELF']);
    }

    // IMPORTANT: JSON riwayat bisa berisi karakter seperti apostrophe (') atau backslash (\)
    // Jika tidak di-escape untuk SQL, data bisa tersimpan rusak dan akhirnya JSON.parse gagal di halaman update.
    $Riwayat_Barang_sql = mysqli_real_escape_string($kon, $Riwayat_Barang);
    
    error_log("=== CREATE.PHP POST DATA ===");
    error_log("Riwayat_Barang POST value: " . var_export($Riwayat_Barang, true));
    
    $User_Perangkat = input(isset($_POST["User_Perangkat"]) ? $_POST["User_Perangkat"] : '');
    $Jenis_Barang = input(isset($_POST["Jenis_Barang"]) ? $_POST["Jenis_Barang"] : '');
    $Status_Barang = input(isset($_POST["Status_Barang"]) ? $_POST["Status_Barang"] : '');
    $Status_LOP = input(isset($_POST["Status_LOP"]) ? $_POST["Status_LOP"] : '');
    $Status_Kelayakan_Barang = input(isset($_POST["Status_Kelayakan_Barang"]) ? $_POST["Status_Kelayakan_Barang"] : '');
    $Serial_Number = input(isset($_POST["Serial_Number"]) ? $_POST["Serial_Number"] : '');
    $Lokasi = input(isset($_POST["Lokasi"]) ? $_POST["Lokasi"] : '');
    $Id_Karyawan = input(isset($_POST["Id_Karyawan"]) ? $_POST["Id_Karyawan"] : '');
    $Jabatan = input(isset($_POST["Jabatan"]) ? $_POST["Jabatan"] : '');
    $Harga_Barang = isset($_POST["Harga_Barang"]) ? input($_POST["Harga_Barang"]) : '';
    $Harga_Barang_sql = mysqli_real_escape_string($kon, (string)$Harga_Barang);

    // Hosting strict-mode friendly: jika harga kosong, insert NULL (bukan string kosong)
    // Jika kolomnya NOT NULL, kita akan retry dengan 0 (lihat loop INSERT).
    $Harga_Barang_insert_sql = 'NULL';
    if (trim((string)$Harga_Barang) !== '') {
        // Normalisasi input harga (mis. "1.500.000" -> "1500000")
        $normalized = preg_replace('/[^0-9]/', '', (string)$Harga_Barang);
        if ($normalized === '' || $normalized === null) {
            $Harga_Barang_insert_sql = '0';
        } else {
            $Harga_Barang_insert_sql = (string)((int)$normalized);
        }
    }

    $Tahun_Rilis = isset($_POST["Tahun_Rilis"]) ? input($_POST["Tahun_Rilis"]) : '';
    $Tahun_Rilis_sql = mysqli_real_escape_string($kon, (string)$Tahun_Rilis);
    // Hosting strict-mode friendly: jika tahun rilis kosong, insert NULL (bukan '')
    // Jika kolom NOT NULL, akan retry dengan 0.
    $Tahun_Rilis_insert_sql = 'NULL';
    if (trim((string)$Tahun_Rilis) !== '') {
        $normalizedYear = preg_replace('/[^0-9]/', '', (string)$Tahun_Rilis);
        if ($normalizedYear === '' || $normalizedYear === null) {
            $Tahun_Rilis_insert_sql = '0';
        } else {
            $Tahun_Rilis_insert_sql = (string)((int)$normalizedYear);
        }
    }
    $Waktu_Pembelian = isset($_POST["Waktu_Pembelian"]) ? input($_POST["Waktu_Pembelian"]) : '';
    $Waktu_Pembelian_sql = mysqli_real_escape_string($kon, (string)$Waktu_Pembelian);
    // Jika kosong, simpan NULL
    $Waktu_Pembelian_insert_sql = 'NULL';
    if (trim((string)$Waktu_Pembelian) !== '') {
        $Waktu_Pembelian_insert_sql = "'{$Waktu_Pembelian_sql}'";
    }

    $Nama_Toko_Pembelian = isset($_POST["Nama_Toko_Pembelian"]) ? input($_POST["Nama_Toko_Pembelian"]) : '';
    $Nama_Toko_Pembelian_sql = mysqli_real_escape_string($kon, (string)$Nama_Toko_Pembelian);

    $Kategori_Pembelian = isset($_POST["Kategori_Pembelian"]) ? input($_POST["Kategori_Pembelian"]) : '';
    $Kategori_Pembelian_sql = mysqli_real_escape_string($kon, (string)$Kategori_Pembelian);

    // URL: simpan apa adanya (hindari htmlspecialchars yang mengubah '&' menjadi '&amp;')
    $Link_Pembelian = isset($_POST["Link_Pembelian"]) ? trim((string)$_POST["Link_Pembelian"]) : '';
    // Jika kategori bukan Online, jangan simpan link
    if (strcasecmp(trim((string)$Kategori_Pembelian), 'Online') !== 0) {
        $Link_Pembelian = '';
    }
    $Link_Pembelian_sql = mysqli_real_escape_string($kon, (string)$Link_Pembelian);

    // Escape semua field string yang dipakai langsung di SQL (hindari error quote / injection)
    $Nama_Barang_sql = mysqli_real_escape_string($kon, (string)$Nama_Barang);
    $Merek_sql = mysqli_real_escape_string($kon, (string)$Merek);
    $Type_sql = mysqli_real_escape_string($kon, (string)$Type);
    $Spesifikasi_sql = mysqli_real_escape_string($kon, (string)$Spesifikasi);
    $Kelengkapan_Barang_sql = mysqli_real_escape_string($kon, (string)$Kelengkapan_Barang);
    $Kondisi_Barang_sql = mysqli_real_escape_string($kon, (string)$Kondisi_Barang);
    $User_Perangkat_sql = mysqli_real_escape_string($kon, (string)$User_Perangkat);
    $Jenis_Barang_sql = mysqli_real_escape_string($kon, (string)$Jenis_Barang);
    $Status_Barang_sql = mysqli_real_escape_string($kon, (string)$Status_Barang);
    $Status_LOP_sql = mysqli_real_escape_string($kon, (string)$Status_LOP);
    $Status_Kelayakan_Barang_sql = mysqli_real_escape_string($kon, (string)$Status_Kelayakan_Barang);
    $Serial_Number_sql = mysqli_real_escape_string($kon, (string)$Serial_Number);
    $Lokasi_sql = mysqli_real_escape_string($kon, (string)$Lokasi);
    $Id_Karyawan_sql = mysqli_real_escape_string($kon, (string)$Id_Karyawan);
    $Jabatan_sql = mysqli_real_escape_string($kon, (string)$Jabatan);

    // Jika Nomor_Aset kosong, auto-generate
    if ($Nomor_Aset === '') {
        $Nomor_Aset = generateNomorAset($kon);
    }

    // Escaping SQL untuk Nomor_Aset (biar aman dari karakter khusus)
    $Nomor_Aset_sql = mysqli_real_escape_string($kon, $Nomor_Aset);

    // Validasi unik: Nomor_Aset tidak boleh duplikat
    // Jika kolom belum ada di DB, query bisa error 1054; skip validasi agar tidak memblokir.
    $cek_nomor_aset_query = "SELECT COUNT(*) as total FROM peserta WHERE Nomor_Aset = '$Nomor_Aset_sql'";
    $cek_nomor_aset_result = mysqli_query_safe($kon, $cek_nomor_aset_query, 'cek_nomor_aset');
    if ($cek_nomor_aset_result) {
        $cek_nomor_aset_row = mysqli_fetch_assoc($cek_nomor_aset_result);
        $totalDup = isset($cek_nomor_aset_row['total']) ? (int)$cek_nomor_aset_row['total'] : 0;
        if ($totalDup > 0) {
            $_SESSION['form_error'] = "Nomor Aset sudah terdaftar, silakan gunakan yang lain.";
            safe_redirect($_SERVER['PHP_SELF']);
        }
    }

    // Cek folder uploads tersedia dan writable sebelum mulai process
    $base_upload_dir = "../uploads/";
    if (!is_dir($base_upload_dir)) {
        if (!mkdir($base_upload_dir, 0755, true)) {
            $_SESSION['form_error'] = "Folder uploads tidak dapat dibuat. Hubungi administrator.";
            error_log("CREATE.PHP: Folder uploads creation FAILED");
            create_debug_log('uploads mkdir FAILED path=' . $base_upload_dir);
            safe_redirect($_SERVER['PHP_SELF']);
        }
    }
    if (!is_writable($base_upload_dir)) {
        $_SESSION['form_error'] = "Folder uploads tidak writable. Hubungi administrator.";
        error_log("CREATE.PHP: Folder uploads NOT WRITABLE");
        create_debug_log('uploads NOT WRITABLE path=' . $base_upload_dir);
        safe_redirect($_SERVER['PHP_SELF']);
    }

    create_debug_log('uploads writable OK');

    // Proses upload semua foto
    $photo_errors = array();
    
    // Cek Photo_Barang (wajib)
    if (empty($_FILES["Photo_Barang"]["name"])) {
        $photo_errors[] = "Foto Barang wajib diunggah!";
    } else {
        $result = processPhotoUpload("Photo_Barang", $Photo_Barang);
        if ($result !== true && $result !== false) {
            $photo_errors[] = "Photo_Barang: " . $result;
        }
    }

    // Cek Photo_Depan (wajib)
    if (empty($_FILES["Photo_Depan"]["name"])) {
        $photo_errors[] = "Foto Depan wajib diunggah!";
    } else {
        $result = processPhotoUpload("Photo_Depan", $Photo_Depan);
        if ($result !== true && $result !== false) {
            $photo_errors[] = "Photo_Depan: " . $result;
        }
    }

    // Cek Photo_Belakang (wajib)
    if (empty($_FILES["Photo_Belakang"]["name"])) {
        $photo_errors[] = "Foto Belakang wajib diunggah!";
    } else {
        $result = processPhotoUpload("Photo_Belakang", $Photo_Belakang);
        if ($result !== true && $result !== false) {
            $photo_errors[] = "Photo_Belakang: " . $result;
        }
    }

    // Cek Photo_SN (wajib)
    if (empty($_FILES["Photo_SN"]["name"])) {
        $photo_errors[] = "Foto SN wajib diunggah!";
    } else {
        $result = processPhotoUpload("Photo_SN", $Photo_SN);
        if ($result !== true && $result !== false) {
            $photo_errors[] = "Photo_SN: " . $result;
        }
    }

    // Cek Photo_Invoice (optional) - bisa foto atau PDF
    if (!empty($_FILES["Photo_Invoice"]["name"])) {
        $result = processPhotoUpload("Photo_Invoice", $Photo_Invoice);
        if ($result !== true && $result !== false) {
            $photo_errors[] = "Photo_Invoice: " . $result;
        }
    }

    // Jika ada error photo, simpan ke session dan redirect ke halaman yang sama untuk tampilkan error
    if (!empty($photo_errors)) {
        $_SESSION['form_errors'] = $photo_errors;
        create_debug_log('Photo validation failed: ' . implode(' | ', $photo_errors));
        safe_redirect($_SERVER['PHP_SELF']);
    }

    create_debug_log('Photo upload ok: barang=' . $Photo_Barang . ' depan=' . $Photo_Depan . ' belakang=' . $Photo_Belakang . ' sn=' . $Photo_SN . ' invoice=' . $Photo_Invoice);

    // Cek apakah Serial Number sudah ada di database
    $cek_serial_query = "SELECT COUNT(*) as total FROM peserta WHERE Serial_Number = '$Serial_Number_sql'";
    $cek_serial_result = mysqli_query_safe($kon, $cek_serial_query, 'cek_serial');
    if (!$cek_serial_result) {
        $_SESSION['form_error'] = 'Database error saat cek Serial Number. Lihat log debug.';
        safe_redirect($_SERVER['PHP_SELF']);
    }
    $cek_serial_row = mysqli_fetch_assoc($cek_serial_result);

    if ($cek_serial_row['total'] > 0) {
        $_SESSION['form_error'] = "Serial Number sudah terdaftar, silakan gunakan yang lain.";
        create_debug_log('Validation failed: duplicate serial');
        safe_redirect($_SERVER['PHP_SELF']);
    }

    // Query insert data ke tabel peserta
    // TAMBAHAN: Tambah Create_By dengan format username - Nama Lengkap
    $create_by = (isset($_SESSION['username']) ? $_SESSION['username'] : '-') . ' - ' . (isset($_SESSION['Nama_Lengkap']) ? $_SESSION['Nama_Lengkap'] : 'Unknown');

    $buildInsertSql = function ($includeNomorAset, $includeHargaBarang, $includeCreateBy, $includePhotoInvoice, $includeTahunRilis, $includeWaktuPembelian, $includeNamaVendor, $includeKategoriPembelian, $includeLinkPembelian) use (
        $Nomor_Aset_sql,
        $Nama_Barang_sql,
        $Merek_sql,
        $Type_sql,
        $Spesifikasi_sql,
        $Kelengkapan_Barang_sql,
        $Lokasi_sql,
        $Id_Karyawan_sql,
        $Jabatan_sql,
        $Kondisi_Barang_sql,
        $Riwayat_Barang_sql,
        $User_Perangkat_sql,
        $Jenis_Barang_sql,
        $Status_Barang_sql,
        $Status_LOP_sql,
        $Status_Kelayakan_Barang_sql,
        $Serial_Number_sql,
        $Photo_Barang,
        $Photo_Depan,
        $Photo_Belakang,
        $Photo_SN,
        $Photo_Invoice,
        &$Harga_Barang_insert_sql,
        &$Tahun_Rilis_insert_sql,
        &$Waktu_Pembelian_insert_sql,
        $Nama_Toko_Pembelian_sql,
        $Kategori_Pembelian_sql,
        $Link_Pembelian_sql,
        $create_by
    ) {
        $cols = array(
            'Waktu',
            'Nama_Barang',
            'Merek',
            'Type',
            'Spesifikasi',
            'Kelengkapan_Barang',
            'Lokasi',
            'Id_Karyawan',
            'Jabatan',
            'Kondisi_Barang',
            'Riwayat_Barang',
            'User_Perangkat',
            'Jenis_Barang',
            'Status_Barang',
            'Status_LOP',
            'Status_Kelayakan_Barang',
            'Serial_Number',
            'Photo_Barang',
            'Photo_Depan',
            'Photo_Belakang',
            'Photo_SN',
        );

        $vals = array(
            'NOW()',
            "'$Nama_Barang_sql'",
            "'$Merek_sql'",
            "'$Type_sql'",
            "'$Spesifikasi_sql'",
            "'$Kelengkapan_Barang_sql'",
            "'$Lokasi_sql'",
            "'$Id_Karyawan_sql'",
            "'$Jabatan_sql'",
            "'$Kondisi_Barang_sql'",
            "'$Riwayat_Barang_sql'",
            "'$User_Perangkat_sql'",
            "'$Jenis_Barang_sql'",
            "'$Status_Barang_sql'",
            "'$Status_LOP_sql'",
            "'$Status_Kelayakan_Barang_sql'",
            "'$Serial_Number_sql'",
            "'$Photo_Barang'",
            "'$Photo_Depan'",
            "'$Photo_Belakang'",
            "'$Photo_SN'",
        );

        if ($includePhotoInvoice) {
            $cols[] = 'Photo_Invoice';
            $vals[] = "'$Photo_Invoice'";
        }

        if ($includeNomorAset) {
            array_splice($cols, 1, 0, array('Nomor_Aset'));
            array_splice($vals, 1, 0, array("'$Nomor_Aset_sql'"));
        }

        if ($includeHargaBarang) {
            $cols[] = 'Harga_Barang';
            $vals[] = $Harga_Barang_insert_sql;
        }

        if ($includeTahunRilis) {
            $cols[] = 'Tahun_Rilis';
            $vals[] = $Tahun_Rilis_insert_sql;
        }

        if ($includeWaktuPembelian) {
            $cols[] = 'Waktu_Pembelian';
            $vals[] = $Waktu_Pembelian_insert_sql;
        }

        if ($includeNamaVendor) {
            $cols[] = 'Nama_Toko_Pembelian';
            $vals[] = "'$Nama_Toko_Pembelian_sql'";
        }

        if ($includeKategoriPembelian) {
            $cols[] = 'Kategori_Pembelian';
            $vals[] = "'$Kategori_Pembelian_sql'";
        }

        if ($includeLinkPembelian) {
            $cols[] = 'Link_Pembelian';
            $vals[] = "'$Link_Pembelian_sql'";
        }

        if ($includeCreateBy) {
            $cols[] = 'Create_By';
            $vals[] = "'$create_by'";
        }

        return "INSERT INTO peserta (\n    " . implode(', ', $cols) . "\n) VALUES (\n    " . implode(', ', $vals) . "\n)";
    };

    // Insert yang cepat & ramah hosting:
    // - Cek kolom yang tersedia sekali saja (SHOW COLUMNS)
    // - Jalankan INSERT 1x (fallback adaptif maksimal ~9-12x jika privilege SHOW COLUMNS diblok)
    @set_time_limit(60);
    error_log('CREATE.PHP: before INSERT peserta');
    create_debug_log('before INSERT peserta');

    $columns = getTableColumnsAssoc($kon, 'peserta');

    $flags = array(
        'includeNomorAset' => true,
        'includeHargaBarang' => true,
        'includeCreateBy' => true,
        'includePhotoInvoice' => true,
        'includeTahunRilis' => true,
        'includeWaktuPembelian' => true,
        'includeNamaVendor' => true,
        'includeKategoriPembelian' => true,
        'includeLinkPembelian' => true,
    );

    if (is_array($columns)) {
        $flags['includeNomorAset'] = isset($columns['Nomor_Aset']);
        $flags['includeHargaBarang'] = isset($columns['Harga_Barang']);
        $flags['includeCreateBy'] = isset($columns['Create_By']);
        $flags['includePhotoInvoice'] = isset($columns['Photo_Invoice']);
        $flags['includeTahunRilis'] = isset($columns['Tahun_Rilis']);
        $flags['includeWaktuPembelian'] = isset($columns['Waktu_Pembelian']);
        $flags['includeNamaVendor'] = isset($columns['Nama_Toko_Pembelian']);
        $flags['includeKategoriPembelian'] = isset($columns['Kategori_Pembelian']);
        $flags['includeLinkPembelian'] = isset($columns['Link_Pembelian']);
    }

    // Catatan hosting: jika kolom Photo_Invoice bersifat NOT NULL tanpa default,
    // kita WAJIB mengirim nilai (meskipun string kosong) agar tidak error 1364.

    $unknownColumnToFlag = array(
        'Nomor_Aset' => 'includeNomorAset',
        'Harga_Barang' => 'includeHargaBarang',
        'Create_By' => 'includeCreateBy',
        'Photo_Invoice' => 'includePhotoInvoice',
        'Tahun_Rilis' => 'includeTahunRilis',
        'Waktu_Pembelian' => 'includeWaktuPembelian',
        'Nama_Toko_Pembelian' => 'includeNamaVendor',
        'Kategori_Pembelian' => 'includeKategoriPembelian',
        'Link_Pembelian' => 'includeLinkPembelian',
    );

    $hasil = false;
    for ($attempt = 0; $attempt < 12; $attempt++) {
        // Reset error vars per attempt (jangan reset setelah di-set, karena dipakai untuk retry logic)
        unset($errno, $errText);
        $sqlTry = $buildInsertSql(
            $flags['includeNomorAset'],
            $flags['includeHargaBarang'],
            $flags['includeCreateBy'],
            $flags['includePhotoInvoice'],
            $flags['includeTahunRilis'],
            $flags['includeWaktuPembelian'],
            $flags['includeNamaVendor'],
            $flags['includeKategoriPembelian'],
            $flags['includeLinkPembelian']
        );

        try {
            $hasil = @mysqli_query($kon, $sqlTry);
        } catch (mysqli_sql_exception $e) {
            $hasil = false;
            // Di beberapa hosting, getCode() bisa SQLSTATE (non-numeric) -> jadi 0.
            // Ambil errno asli dari koneksi bila tersedia.
            $errnoConn = @mysqli_errno($kon);
            $errno = $errnoConn ? (int)$errnoConn : (int)$e->getCode();
            $errTextConn = @mysqli_error($kon);
            $errText = ($errTextConn !== null && $errTextConn !== '') ? (string)$errTextConn : (string)$e->getMessage();
            error_log('CREATE.PHP: INSERT exception errno=' . $errno . ' error=' . $errText . ' (attempt=' . ($attempt + 1) . ')');
            create_debug_log('INSERT exception errno=' . $errno . ' error=' . $errText . ' attempt=' . ($attempt + 1));
        }
        if ($hasil) {
            error_log('CREATE.PHP: INSERT ok (id=' . mysqli_insert_id($kon) . ', attempts=' . ($attempt + 1) . ')');
            create_debug_log('INSERT ok id=' . mysqli_insert_id($kon) . ' attempts=' . ($attempt + 1));
            break;
        }

        if (!isset($errno)) {
            $errno = mysqli_errno($kon);
        }
        if (!isset($errText)) {
            $errText = mysqli_error($kon);
        }
        error_log('CREATE.PHP: INSERT failed errno=' . $errno . ' error=' . $errText . ' (attempt=' . ($attempt + 1) . ')');
        create_debug_log('INSERT failed errno=' . $errno . ' error=' . $errText . ' attempt=' . ($attempt + 1));

        // Jika Unknown column, matikan flag sesuai kolom yang disebut lalu retry
        if ($errno === 1054 && preg_match("/Unknown column '([^']+)'/", (string)$errText, $m)) {
            $col = (string)$m[1];
            if (isset($unknownColumnToFlag[$col])) {
                $flagName = $unknownColumnToFlag[$col];
                if (!empty($flags[$flagName])) {
                    $flags[$flagName] = false;
                    continue;
                }
            }
        }

        // Jika harga kosong menyebabkan error strict (NULL tidak boleh / tidak ada default / tipe integer), fallback ke 0 lalu retry
        if (
            (
                $errno === 1048
                || $errno === 1366
                || $errno === 1364
                || (stripos((string)$errText, "doesn't have a default value") !== false)
            )
            && stripos((string)$errText, 'Harga_Barang') !== false
            && $flags['includeHargaBarang']
            && $Harga_Barang_insert_sql === 'NULL'
        ) {
            $Harga_Barang_insert_sql = '0';
            create_debug_log('Retry Harga_Barang with 0 due to strict-mode error: ' . (string)$errText);
            continue;
        }

        // Jika tahun rilis kosong menyebabkan strict error, fallback ke 0 lalu retry
        if (
            (
                $errno === 1048
                || $errno === 1366
                || $errno === 1364
                || (stripos((string)$errText, "doesn't have a default value") !== false)
                || (stripos((string)$errText, 'Incorrect integer value') !== false)
            )
            && stripos((string)$errText, 'Tahun_Rilis') !== false
            && $flags['includeTahunRilis']
            && $Tahun_Rilis_insert_sql === 'NULL'
        ) {
            $Tahun_Rilis_insert_sql = '0';
            create_debug_log('Retry Tahun_Rilis with 0 due to strict-mode error: ' . (string)$errText);
            continue;
        }

        // Bukan unknown column / tidak bisa di-resolve: stop
        break;
    }
    
    if ($hasil) {
        error_log('CREATE.PHP: success branch - before activity log');
        create_debug_log('success branch - before activity log');
        $log_file = __DIR__ . "/log_activity.php";
        if (file_exists($log_file)) {
            // Activity log is optional; never break create flow.
            @include_once $log_file;
            if (function_exists('logUserActivity')) {
                $uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
                $uname = isset($_SESSION['username']) ? (string)$_SESSION['username'] : '';
                $role = isset($_SESSION['role']) ? (string)$_SESSION['role'] : '';
                $newId = mysqli_insert_id($kon);
                logUserActivity(
                    $kon,
                    $uid,
                    $uname,
                    $role,
                    "CREATE",
                    "peserta",
                    $newId,
                    null
                );
            }
        }
        error_log('CREATE.PHP: before redirect to index');
        
        // ✅ FIX: Redirect langsung ke index.php dengan status success
        // Flush output buffer untuk ensure clean redirect
        safe_redirect("index.php?status=success&message=Asset+berhasil+ditambahkan");
    } else {
        // Simpan error ke session untuk ditampilkan di halaman form
        $db_error = "Database error: " . mysqli_error($kon);
        error_log("CREATE.PHP INSERT FAILED: " . $db_error);
        create_debug_log('INSERT final failed: ' . $db_error);
        $_SESSION['form_error'] = $db_error;
        safe_redirect($_SERVER['PHP_SELF']);
    }
}

// ===== AKHIR FORM PROCESSING =====
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Data Asset IT - Modern & Interactive</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        /* TAMBAHAN: Custom Styling untuk Select2 (match Tailwind & form modern) */
.select2-container--default .select2-selection--single {
    height: 48px !important; /* Match py-3 (12px top/bottom) */
    border: 1px solid #d1d5db !important; /* border-gray-300 */
    border-radius: 0.75rem !important; /* rounded-xl */
    background-color: rgba(255, 255, 255, 0.9) !important; /* bg-white/90 */
    backdrop-filter: blur(10px) !important;
    font-size: 0.875rem !important; /* text-sm */
    padding-left: 1rem !important; /* px-4 */
    padding-top: 0.75rem !important; /* py-3 */
    line-height: 1.25 !important;
}

.select2-container--default .select2-selection--single .select2-selection__rendered {
    color: #374151 !important; /* text-gray-700 */
    padding-left: 0 !important; /* Sudah di atas */
}

.select2-container--default .select2-selection--single .select2-selection__placeholder {
    color: #9ca3af !important; /* placeholder-gray-500 */
}

.select2-container--default.select2-container--focus .select2-selection--single {
    border-color: #3b82f6 !important; /* focus:border-blue-500 */
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1) !important; /* focus:ring-2 ring-blue-500/10 */
    outline: none !important;
    transform: translateY(-1px) !important; /* Match input-modern focus */
}

.select2-dropdown {
    border: 1px solid #d1d5db !important; /* border-gray-300 */
    border-radius: 0.75rem !important; /* rounded-xl */
    background-color: rgba(255, 255, 255, 0.95) !important; /* bg-white/95 */
    backdrop-filter: blur(10px) !important;
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.1) !important; /* Match form shadow */
    z-index: 1050 !important; /* Lebih tinggi dari modal (1000) */
}

.select2-container--default .select2-results__option--highlighted[aria-selected] {
    background-color: #dbeafe !important; /* bg-blue-100 */
    color: #1e40af !important; /* text-blue-800 */
}

.select2-container--default .select2-results__option {
    padding: 0.75rem 1rem !important; /* py-3 px-4 */
    font-size: 0.875rem !important; /* text-sm */
}

.select2-container--default .select2-search--dropdown .select2-search__field {
    border: 1px solid #d1d5db !important;
    border-radius: 0.75rem !important; /* rounded-xl */
    padding: 0.75rem 1rem !important; /* py-3 px-4 */
    background-color: white !important;
    font-size: 0.875rem !important; /* text-sm */
}

/* Force search box di dropdown (bahkan jika opsi sedikit) */
.select2-container .select2-search--inline {
    display: block !important;
    width: 100% !important;
    margin: 5px 0 !important;
}

.select2-container--default .select2-search--inline .select2-search__field {
    background: white !important;
    border: 1px solid #d1d5db !important;
    border-radius: 0.75rem !important;
    padding: 0.75rem !important;
    width: 100% !important;
}

/* Max height dropdown panjang */
.select2-container--default .select2-results {
    max-height: 200px !important; /* Cukup untuk opsi banyak */
    overflow-y: auto !important;
}

/* Mobile responsive */
@media (max-width: 768px) {
    .select2-container--default .select2-selection--single {
        height: 44px !important;
        font-size: 0.875rem !important;
    }
}

        * {
            font-family: 'Inter', sans-serif;
        }

        /* Ensure proper scrolling and clean background */
        html {
            scroll-behavior: smooth;
        }

        body {
            overflow-x: hidden;
            scroll-behavior: smooth;
        }

        /* Custom scrollbar styling */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #f97316, #2563eb);
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #ea580c, #1d4ed8);
        }

        /* Form container styling */
        .form-container {
            backdrop-filter: blur(20px);
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.1);
            border-radius: 1.5rem;
        }

        /* Form sections */
        .form-section {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            transition: all 0.3s ease;
            border-radius: 1rem;
        }

        .form-section:hover {
            background: rgba(255, 255, 255, 0.85);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        /* Interactive input styling */
        .input-modern {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .input-modern:focus {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .textarea-modern {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            resize: vertical;
        }

        .textarea-modern:focus {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        /* Button styling */
        .btn-gradient {
            background: linear-gradient(135deg, #f97316, #2563eb);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .btn-gradient::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn-gradient:hover::before {
            left: 100%;
        }

        .btn-gradient:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 35px rgba(249, 115, 22, 0.4);
        }

        /* Custom select styling */
        .custom-select-container {
            position: relative;
        }

        .custom-select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m5 8 5 5 5-5'/%3e%3c/svg%3e");
            background-position: right 0.5rem center;
            background-repeat: no-repeat;
            background-size: 1.5em 1.5em;
            padding-right: 2.5rem;
        }

        .add-option-btn {
            transition: all 0.3s ease;
        }

        .add-option-btn:hover {
            transform: scale(1.05);
            background-color: rgba(249, 115, 22, 0.1);
            border-color: rgba(249, 115, 22, 0.3);
        }

        /* Modal animations */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.show {
            display: flex;
            animation: modalFadeIn 0.3s ease;
        }

        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25);
            width: 90%;
            max-width: 400px;
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalFadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes modalSlideIn {
            from { opacity: 0; transform: scale(0.9) translateY(-20px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }

        /* Photo upload styling */
        .photo-upload {
            position: relative;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px dashed #d1d5db;
            background-color: rgba(255, 255, 255, 0.5);
        }

        .photo-upload:hover {
            border-color: #f97316;
            background-color: rgba(249, 115, 22, 0.05);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(249, 115, 22, 0.1);
        }

        .photo-upload.border-orange-500 {
            border-color: #f97316;
            background-color: rgba(249, 115, 22, 0.08);
        }

        .photo-upload input[type=file] {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }

        /* Success/Error feedback */
        .feedback-success {
            color: #10b981;
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .feedback-error {
            color: #ef4444;
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        /* Loading animation */
        .loading-spinner {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        /* Section animations */
        .animate-fade-in-up {
            animation: fadeInUp 0.6s ease forwards;
            opacity: 0;
            transform: translateY(20px);
        }

        @keyframes fadeInUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Staggered animation delays */
        .animate-delay-1 { animation-delay: 0.1s; }
        .animate-delay-2 { animation-delay: 0.2s; }
        .animate-delay-3 { animation-delay: 0.3s; }
        .animate-delay-4 { animation-delay: 0.4s; }
        .animate-delay-5 { animation-delay: 0.5s; }
        .animate-delay-6 { animation-delay: 0.6s; }
    </style>
</head>

<body class="min-h-screen bg-gradient-to-br from-orange-50 to-blue-50 py-8">
    <div class="container mx-auto px-4">
        
        <?php
        // ===== LOAD OPTIONS FROM DATABASE =====
        // Function to get existing options from database
        function getOptions($kon, $field) {
            $options = array();
            $query = "SELECT DISTINCT $field FROM peserta WHERE $field IS NOT NULL AND $field != '' ORDER BY $field ASC";
            $result = mysqli_query($kon, $query);
            if ($result) {
                while ($row = mysqli_fetch_assoc($result)) {
                    $options[] = $row[$field];
                }
            }
            return $options;
        }

        $namaBarangOptions = getOptions($kon, 'Nama_Barang');
        $merekOptions = getOptions($kon, 'Merek');
        $typeOptions = getOptions($kon, 'Type');
        $lokasiOptions = getOptions($kon, 'Lokasi');
        $idKaryawanOptions = getOptions($kon, 'Id_Karyawan');
        $jabatanOptions = getOptions($kon, 'Jabatan');
        $vendorOptions = getOptions($kon, 'Nama_Toko_Pembelian');
        
        // Untuk User_Perangkat (ambil dari query yang sama)
        $query_user_perangkat = "SELECT DISTINCT User_Perangkat FROM peserta WHERE User_Perangkat IS NOT NULL AND User_Perangkat != '' ORDER BY User_Perangkat ASC";
        $result_user_perangkat = mysqli_query($kon, $query_user_perangkat);
        $user_perangkat_list = array();
        if ($result_user_perangkat) {
            while ($row = mysqli_fetch_assoc($result_user_perangkat)) {
                if (!empty($row['User_Perangkat'])) {
                    $user_perangkat_list[] = $row['User_Perangkat'];
                }
            }
        }
        
        // Convert arrays ke JSON untuk JavaScript
        $jabatanJson = json_encode($jabatanOptions);
        $idKaryawanJson = json_encode($idKaryawanOptions);
        $lokasiJson = json_encode($lokasiOptions);
        $userPerangkatJson = json_encode($user_perangkat_list);

        // ===== TAMPILKAN ERROR FORM JIKA ADA =====
        // PENTING: Simpan error ke variable SEBELUM unset untuk avoid race condition
        $display_form_error = isset($_SESSION['form_error']) ? $_SESSION['form_error'] : null;
        $display_form_errors = isset($_SESSION['form_errors']) ? $_SESSION['form_errors'] : array();
        
        // Clear session immediately agar tidak persist di refresh
        unset($_SESSION['form_error']);
        unset($_SESSION['form_errors']);
        
        if ($display_form_error) {
            echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Terjadi Kesalahan!',
                    text: '" . addslashes($display_form_error) . "',
                    confirmButtonText: 'OK'
                });
            });
            </script>";
        }

        if (!empty($display_form_errors)) {
            $error_msg = implode("\n", $display_form_errors);
            echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal Upload Foto!',
                    text: '" . addslashes($error_msg) . "',
                    confirmButtonText: 'OK'
                });
            });
            </script>";
        }
        ?>

        <!-- Header Section -->
        <div class="form-container mb-6 sm:mb-8 p-4 sm:p-6 animate-fade-in-up">
            <div class="text-center">
                <div class="flex justify-center mb-4">
                    <div class="w-14 sm:w-16 h-14 sm:h-16 bg-gradient-to-r from-orange-500 to-blue-600 rounded-2xl flex items-center justify-center hover:scale-105 transition-transform cursor-pointer">
                        <i class="fas fa-plus text-white text-xl sm:text-2xl"></i>
                    </div>
                </div>
                <h1 class="text-2xl sm:text-3xl bg-gradient-to-r from-orange-600 to-blue-600 bg-clip-text text-transparent mb-2">
                    Create Data Asset IT
                </h1>
                <p class="text-sm sm:text-base text-gray-600">Tambahkan data asset IT baru dengan mudah dan cepat</p>
            </div>
        </div>

        <!-- Main Form -->
        <div class="form-container p-4 sm:p-6 md:p-8 animate-fade-in-up animate-delay-1">
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data" id="assetForm">
                
                <!-- Section 1: Basic Information -->
                <div class="form-section p-4 sm:p-6 mb-6 sm:mb-8 animate-fade-in-up animate-delay-2">
                    <h3 class="flex items-center text-lg sm:text-xl mb-4 sm:mb-6 bg-gradient-to-r from-orange-600 to-blue-600 bg-clip-text text-transparent">
                        <i class="fas fa-info-circle mr-3 text-orange-500"></i>
                        Informasi Dasar
                    </h3>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">
                        <!-- Waktu -->
                        <div class="form-group">
                            <label for="Waktu" class="block text-gray-700 mb-2">
                                <i class="fas fa-clock mr-2 text-blue-500"></i>Waktu
                            </label>
                            <input type="text" name="Waktu" class="input-modern w-full px-4 py-3 border border-gray-300 rounded-xl bg-gray-50" value="<?php echo date('Y-m-d H:i:s'); ?>" readonly />
                        </div>

                        <!-- Serial Number -->
                        <div class="form-group">
                            <label for="Serial_Number" class="block text-gray-700 mb-2">
                                <i class="fas fa-barcode mr-2 text-orange-500"></i>Serial Number
                            </label>
                            <div class="relative">
                                <input type="text" name="Serial_Number" id="Serial_Number" class="input-modern w-full px-4 py-3 pr-10 border border-gray-300 rounded-xl focus:border-orange-500 focus:ring-2 focus:ring-orange-200" placeholder="Masukkan Serial Number" value="<?php echo $Serial_Number; ?>" required autocomplete="off"/>
                                <div id="serialNumberSpinner" class="absolute right-3 top-1/2 transform -translate-y-1/2 hidden">
                                    <div class="w-4 h-4 border-2 border-orange-500 border-t-transparent rounded-full loading-spinner"></div>
                                </div>
                                <div id="serialNumberIcon" class="absolute right-3 top-1/2 transform -translate-y-1/2 hidden">
                                    <i class="fas fa-check text-green-500" id="availableIcon" style="display: none;"></i>
                                    <i class="fas fa-exclamation-circle text-red-500" id="takenIcon" style="display: none;"></i>
                                </div>
                            </div>
                            <div id="serialNumberFeedback" class="mt-2 px-3 py-2 rounded-lg text-sm hidden"></div>
                        </div>

                        <!-- Nama Barang -->
                        <div class="form-group">
                            <label for="Nama_Barang" class="block text-gray-700 mb-2">
                                <i class="fas fa-tag mr-2 text-blue-500"></i>Nama Barang
                            </label>
                            <div class="flex gap-2">
                                <div class="flex-1">
                                    <select id="Nama_Barang" name="Nama_Barang" class="select2-field custom-select input-modern w-full px-4 py-3 border border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-200" required>
                                        <option value="" disabled <?php if (empty($Nama_Barang)) echo 'selected'; ?>>Pilih Nama Barang</option>
                                        <?php foreach ($namaBarangOptions as $nama): ?>
                                            <option value="<?php echo htmlspecialchars($nama); ?>" <?php if ($Nama_Barang == $nama) echo 'selected'; ?>>
                                                <?php echo htmlspecialchars($nama); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="button" class="add-option-btn px-3 py-3 border border-gray-300 rounded-xl hover:bg-orange-50 hover:border-orange-300" onclick="showAddModal('namaBarang', 'Nama Barang')">
                                    <i class="fas fa-plus text-gray-600"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Nomor Aset -->
                        <div class="form-group">
                            <label for="Nomor_Aset" class="block text-gray-700 mb-2">
                                <i class="fas fa-hashtag mr-2 text-orange-500"></i>Nomor Aset
                            </label>
                            <input type="text" name="Nomor_Aset" id="Nomor_Aset"
                                   class="input-modern w-full px-4 py-3 border border-gray-300 rounded-xl focus:border-orange-500 focus:ring-2 focus:ring-orange-200"
                                   placeholder="Contoh: ITCKT-<?php echo date('Y'); ?>-0001 (boleh dikosongkan untuk auto)"
                                   value="<?php echo htmlspecialchars(isset($Nomor_Aset) ? $Nomor_Aset : '', ENT_QUOTES, 'UTF-8'); ?>"
                                   autocomplete="off" />
                            <small class="text-gray-500 mt-1 block">Jika dikosongkan, sistem akan membuat otomatis format <span class="font-mono">ITCKT-YYYY-####</span>.</small>
                        </div>

                        <!-- Merek -->
                        <div class="form-group">
                            <label for="Merek" class="block text-gray-700 mb-2">
                                <i class="fas fa-copyright mr-2 text-orange-500"></i>Merek
                            </label>
                            <div class="flex gap-2">
                                <div class="flex-1">
                                    <select id="Merek" name="Merek" class="select2-field custom-select input-modern w-full px-4 py-3 border border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-200" required>
                                        <option value="" disabled <?php if (empty($Merek)) echo 'selected'; ?>>Pilih Merek</option>
                                        <?php foreach ($merekOptions as $merek): ?>
                                            <option value="<?php echo htmlspecialchars($merek); ?>" <?php if ($Merek == $merek) echo 'selected'; ?>>
                                                <?php echo htmlspecialchars($merek); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="button" class="add-option-btn px-3 py-3 border border-gray-300 rounded-xl hover:bg-orange-50 hover:border-orange-300" onclick="showAddModal('merek', 'Merek')">
                                    <i class="fas fa-plus text-gray-600"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Type -->
                        <div class="form-group">
                            <label for="Type" class="block text-gray-700 mb-2">
                                <i class="fas fa-cogs mr-2 text-blue-500"></i>Type
                            </label>
                            <div class="flex gap-2">
                                <div class="flex-1">
                                    <select id="Type" name="Type" class="select2-field custom-select input-modern w-full px-4 py-3 border border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-200" required>
                                        <option value="" disabled <?php if (empty($Type)) echo 'selected'; ?>>Pilih Type</option>
                                        <?php foreach ($typeOptions as $type): ?>
                                            <option value="<?php echo htmlspecialchars($type); ?>" <?php if ($Type == $type) echo 'selected'; ?>>
                                                <?php echo htmlspecialchars($type); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="button" class="add-option-btn px-3 py-3 border border-gray-300 rounded-xl hover:bg-orange-50 hover:border-orange-300" onclick="showAddModal('type', 'Type')">
                                    <i class="fas fa-plus text-gray-600"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 2: Specifications -->
                <div class="form-section p-4 sm:p-6 mb-6 sm:mb-8 animate-fade-in-up animate-delay-3">
                    <h3 class="flex items-center text-lg sm:text-xl mb-4 sm:mb-6 bg-gradient-to-r from-orange-600 to-blue-600 bg-clip-text text-transparent">
                        <i class="fas fa-list-ul mr-3 text-orange-500"></i>
                        Spesifikasi & Detail
                    </h3>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">
                        <!-- Spesifikasi -->
                        <div class="form-group">
                            <label for="Spesifikasi" class="block text-gray-700 mb-2">
                                <i class="fas fa-clipboard-list mr-2 text-blue-500"></i>Spesifikasi
                            </label>
                            <textarea name="Spesifikasi" class="textarea-modern w-full px-4 py-3 border border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-200" rows="6" placeholder="Masukkan spesifikasi detail..." required><?php echo $Spesifikasi; ?></textarea>
                        </div>

                        <!-- Kelengkapan Barang -->
                        <div class="form-group">
                            <label for="Kelengkapan_Barang" class="block text-gray-700 mb-2">
                                <i class="fas fa-check-circle mr-2 text-orange-500"></i>Kelengkapan Barang
                            </label>
                            <textarea name="Kelengkapan_Barang" class="textarea-modern w-full px-4 py-3 border border-gray-300 rounded-xl focus:border-orange-500 focus:ring-2 focus:ring-orange-200" rows="6" placeholder="Masukkan kelengkapan barang..." required><?php echo $Kelengkapan_Barang; ?></textarea>
                        </div>

                        <!-- Tahun Rilis -->
                        <div class="form-group">
                            <label for="Tahun_Rilis" class="block text-gray-700 mb-2">
                                <i class="fas fa-calendar-alt mr-2 text-blue-500"></i>Tahun Rilis
                            </label>
                            <?php
                            $tahunRilisSelected = isset($Tahun_Rilis) ? trim((string)$Tahun_Rilis) : '';
                            $tahunNow = (int)date('Y');
                            $tahunMin = 1980;
                            $tahunMax = $tahunNow + 10;
                            ?>
                            <select name="Tahun_Rilis" id="Tahun_Rilis"
                                    class="input-modern w-full px-4 py-3 border border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                                <option value="" <?php echo ($tahunRilisSelected === '' ? 'selected' : ''); ?>>Pilih Tahun</option>
                                <?php for ($y = $tahunMax; $y >= $tahunMin; $y--): ?>
                                    <option value="<?php echo $y; ?>" <?php echo ($tahunRilisSelected === (string)$y ? 'selected' : ''); ?>>
                                        <?php echo $y; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <!-- Waktu Pembelian -->
                        <div class="form-group">
                            <label for="Waktu_Pembelian" class="block text-gray-700 mb-2">
                                <i class="fas fa-calendar-check mr-2 text-orange-500"></i>Waktu Pembelian
                            </label>
                            <input type="date" name="Waktu_Pembelian" id="Waktu_Pembelian"
                                   class="input-modern w-full px-4 py-3 border border-gray-300 rounded-xl focus:border-orange-500 focus:ring-2 focus:ring-orange-200"
                                   value="<?php echo htmlspecialchars(isset($Waktu_Pembelian) ? $Waktu_Pembelian : ''); ?>">
                        </div>

                        <!-- Nama Vendor -->
                        <div class="form-group">
                            <label for="Nama_Toko_Pembelian" class="block text-gray-700 mb-2">
                                <i class="fas fa-store mr-2 text-blue-500"></i>Nama Vendor
                            </label>
                            <div class="flex gap-2">
                                <div class="flex-1">
                                    <select id="Nama_Toko_Pembelian" name="Nama_Toko_Pembelian" class="select2-field custom-select input-modern w-full px-4 py-3 border border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                                        <option value="" <?php if (empty($Nama_Toko_Pembelian)) echo 'selected'; ?>>Pilih Nama Vendor</option>
                                        <?php foreach ($vendorOptions as $vendor): ?>
                                            <option value="<?php echo htmlspecialchars($vendor); ?>" <?php if ((isset($Nama_Toko_Pembelian) ? $Nama_Toko_Pembelian : '') == $vendor) echo 'selected'; ?>>
                                                <?php echo htmlspecialchars($vendor); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="button" class="add-option-btn px-3 py-3 border border-gray-300 rounded-xl hover:bg-orange-50 hover:border-orange-300" onclick="showAddModal('namaVendor', 'Nama Vendor')">
                                    <i class="fas fa-plus text-gray-600"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Kategori Pembelian -->
                        <div class="form-group">
                            <label for="Kategori_Pembelian" class="block text-gray-700 mb-2">
                                <i class="fas fa-tags mr-2 text-orange-500"></i>Kategori Pembelian
                            </label>
                            <select name="Kategori_Pembelian" id="Kategori_Pembelian"
                                    class="input-modern w-full px-4 py-3 border border-gray-300 rounded-xl focus:border-orange-500 focus:ring-2 focus:ring-orange-200">
                                <option value="" <?php echo (empty($Kategori_Pembelian) ? 'selected' : ''); ?>>Pilih Kategori Pembelian</option>
                                <option value="Online" <?php echo (((isset($Kategori_Pembelian) ? $Kategori_Pembelian : '') === 'Online') ? 'selected' : ''); ?>>Online</option>
                                <option value="Offline" <?php echo (((isset($Kategori_Pembelian) ? $Kategori_Pembelian : '') === 'Offline') ? 'selected' : ''); ?>>Offline</option>
                            </select>
                            <p id="remarkKategoriPembelianOnline" class="mt-2 text-xs text-gray-500 hidden">
                                Rekomendasi: jika pembelian Online, cantumkan link pembelian (URL) untuk memudahkan tracking.
                            </p>

                            <div id="linkPembelianGroup" class="mt-3 hidden">
                                <label for="Link_Pembelian" class="block text-gray-700 mb-2">
                                    <i class="fas fa-link mr-2 text-blue-500"></i>Link Pembelian
                                </label>
                                <input type="url" name="Link_Pembelian" id="Link_Pembelian"
                                       class="input-modern w-full px-4 py-3 border border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                                       placeholder="https://..."
                                       value="<?php echo htmlspecialchars(isset($Link_Pembelian) ? $Link_Pembelian : ''); ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 3: Location & User Info -->
                <div class="form-section p-6 mb-8 animate-fade-in-up animate-delay-4">
                    <h3 class="flex items-center text-xl mb-6 bg-gradient-to-r from-orange-600 to-blue-600 bg-clip-text text-transparent">
                        <i class="fas fa-map-marker-alt mr-3 text-orange-500"></i>
                        Lokasi & Pengguna
                    </h3>
                    
                    <div class="grid md:grid-cols-3 gap-6">
                        <!-- Lokasi -->
                        <div class="form-group">
                            <label for="Lokasi" class="block text-gray-700 mb-2">
                                <i class="fas fa-building mr-2 text-blue-500"></i>Lokasi
                            </label>
                            <div class="flex gap-2">
                                <div class="flex-1">
                                    <select id="Lokasi" name="Lokasi" class="select2-field custom-select input-modern w-full px-4 py-3 border border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-200" required>
                                        <option value="" disabled <?php if (empty($Lokasi)) echo 'selected'; ?>>Pilih Lokasi</option>
                                        <?php foreach ($lokasiOptions as $lokasi): ?>
                                            <option value="<?php echo htmlspecialchars($lokasi); ?>" <?php if ($Lokasi == $lokasi) echo 'selected'; ?>>
                                                <?php echo htmlspecialchars($lokasi); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="button" class="add-option-btn px-3 py-3 border border-gray-300 rounded-xl hover:bg-orange-50 hover:border-orange-300" onclick="showAddModal('lokasi', 'Lokasi')">
                                    <i class="fas fa-plus text-gray-600"></i>
                                </button>
                            </div>
                        </div>

                        <!-- ID Karyawan -->
                        <div class="form-group">
                            <label for="Id_Karyawan" class="block text-gray-700 mb-2">
                                <i class="fas fa-id-card mr-2 text-orange-500"></i>ID Karyawan
                            </label>
                            <div class="flex gap-2">
                                <div class="flex-1">
                                    <select id="Id_Karyawan" name="Id_Karyawan" class="select2-field custom-select input-modern w-full px-4 py-3 border border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-200" required>
                                        <option value="" disabled <?php if (empty($Id_Karyawan)) echo 'selected'; ?>>Pilih ID Karyawan</option>
                                        <?php foreach ($idKaryawanOptions as $karyawan): ?>
                                            <option value="<?php echo htmlspecialchars($karyawan); ?>" <?php if ($Id_Karyawan == $karyawan) echo 'selected'; ?>>
                                                <?php echo htmlspecialchars($karyawan); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="button" class="add-option-btn px-3 py-3 border border-gray-300 rounded-xl hover:bg-orange-50 hover:border-orange-300" onclick="showAddModal('idKaryawan', 'ID Karyawan')">
                                    <i class="fas fa-plus text-gray-600"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Jabatan -->
                        <div class="form-group">
                            <label for="Jabatan" class="block text-gray-700 mb-2">
                                <i class="fas fa-user-tie mr-2 text-blue-500"></i>Jabatan
                            </label>
                            <div class="flex gap-2">
                                <div class="flex-1">
                                    <select id="Jabatan" name="Jabatan" class="select2-field custom-select input-modern w-full px-4 py-3 border border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-200" required>
                                        <option value="" disabled <?php if (empty($Jabatan)) echo 'selected'; ?>>Pilih Jabatan</option>
                                        <?php foreach ($jabatanOptions as $jabatan): ?>
                                            <option value="<?php echo htmlspecialchars($jabatan); ?>" <?php if ($Jabatan == $jabatan) echo 'selected'; ?>>
                                                <?php echo htmlspecialchars($jabatan); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="button" class="add-option-btn px-3 py-3 border border-gray-300 rounded-xl hover:bg-orange-50 hover:border-orange-300" onclick="showAddModal('jabatan', 'Jabatan')">
                                    <i class="fas fa-plus text-gray-600"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 4: Condition & History -->
                <div class="form-section p-4 sm:p-6 mb-6 sm:mb-8 animate-fade-in-up animate-delay-5">
                    <h3 class="flex items-center text-lg sm:text-xl mb-4 sm:mb-6 bg-gradient-to-r from-orange-600 to-blue-600 bg-clip-text text-transparent">
                        <i class="fas fa-history mr-3 text-orange-500"></i>
                        Kondisi & Riwayat
                    </h3>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6 mb-4 sm:mb-6">
                        <!-- Kondisi Barang -->
                        <div class="form-group">
                            <label for="Kondisi_Barang" class="block text-gray-700 mb-2">
                                <i class="fas fa-tools mr-2 text-blue-500"></i>Kondisi Barang
                            </label>
                            <textarea name="Kondisi_Barang" class="textarea-modern w-full px-4 py-3 border border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-200" rows="6" placeholder="Masukkan kondisi barang..." required><?php echo $Kondisi_Barang; ?></textarea>
                        </div>

                        <!-- Riwayat Barang -->
                        <div class="form-group">
                            <label for="Riwayat_Barang" class="block text-gray-700 mb-2">
                                <i class="fas fa-book mr-2 text-orange-500"></i>Riwayat Barang
                            </label>
                            
                            <!-- Riwayat Entry Container -->
                            <div id="riwayatContainer" class="space-y-3 mb-4 max-h-64 overflow-y-auto border border-gray-200 rounded-lg p-3 bg-gray-50">
                                <!-- Entries akan ditampilkan di sini -->
                            </div>
                            
                            <!-- Hidden input untuk menyimpan riwayat dalam format JSON -->
                            <textarea name="Riwayat_Barang" id="Riwayat_Barang" class="hidden"><?php echo htmlspecialchars($Riwayat_Barang, ENT_QUOTES, 'UTF-8'); ?></textarea>
                            
                            <!-- Form untuk menambah entry riwayat -->
                            <div class="bg-white border border-gray-300 rounded-lg p-4 mb-3">
                                <h4 class="text-sm font-semibold text-gray-700 mb-3">Tambah Entry Riwayat</h4>
                                
                                <!-- Tanggal Serah Terima -->
                                <div class="mb-3">
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Tanggal Serah Terima</label>
                                    <input type="date" id="riwayatTglSerah" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:border-orange-500 focus:ring-2 focus:ring-orange-200" />
                                </div>

                                <!-- Tanggal Pengembalian
                                <div class="mb-3">
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Tanggal Pengembalian</label>
                                    <input type="date" id="riwayatTglKembali" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:border-orange-500 focus:ring-2 focus:ring-orange-200" />
                                </div> -->
                                
                                <!-- Nama (Select2 dari User_Perangkat) -->
                                <div class="mb-3">
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Nama Tangan</label>
                                    <div class="flex gap-2">
                                        <select id="riwayatNama" class="riwayat-nama-select flex-1 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:border-orange-500 focus:ring-2 focus:ring-orange-200" style="width: 100%;">
                                            <option value="">-- Pilih Nama --</option>
                                        </select>
                                        <div class="flex items-center gap-2">
                                            <button type="button" id="addNameBtn" class="px-2 py-2 border border-gray-300 rounded-lg hover:bg-orange-50 hover:border-orange-300 text-sm" title="Tambah Nama Baru">
                                                <i class="fas fa-plus text-gray-600"></i>
                                            </button>
                                            <button type="button" id="fillTanganPertamaBtn" class="px-2 py-2 border border-gray-300 rounded-lg hover:bg-green-50 hover:border-green-300 text-sm" title="Isi Nama Tangan Pertama">
                                                <i class="fas fa-handshake text-green-600"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Jabatan (Select2) -->
                                <div class="mb-3">
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Jabatan</label>
                                    <div class="flex gap-2">
                                        <select id="riwayatJabatan" class="riwayat-jabatan-select flex-1 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:border-orange-500 focus:ring-2 focus:ring-orange-200" style="width: 100%;">
                                            <option value="">-- Pilih Jabatan --</option>
                                        </select>
                                        <button type="button" id="addJabatanBtn" class="px-2 py-2 border border-gray-300 rounded-lg hover:bg-orange-50 hover:border-orange-300 text-sm" title="Tambah Jabatan Baru">
                                            <i class="fas fa-plus text-gray-600"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Employee ID (Select2) -->
                                <div class="mb-3">
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Employee ID / NIK</label>
                                    <div class="flex gap-2">
                                        <select id="riwayatEmplId" class="riwayat-emplid-select flex-1 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:border-orange-500 focus:ring-2 focus:ring-orange-200" style="width: 100%;">
                                            <option value="">-- Pilih Employee ID --</option>
                                        </select>
                                        <button type="button" id="addEmplIdBtn" class="px-2 py-2 border border-gray-300 rounded-lg hover:bg-orange-50 hover:border-orange-300 text-sm" title="Tambah Employee ID Baru">
                                            <i class="fas fa-plus text-gray-600"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Lokasi (Select2) -->
                                <div class="mb-3">
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Lokasi</label>
                                    <div class="flex gap-2">
                                        <select id="riwayatLokasi" class="riwayat-lokasi-select flex-1 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:border-orange-500 focus:ring-2 focus:ring-orange-200" style="width: 100%;">
                                            <option value="">-- Pilih Lokasi --</option>
                                        </select>
                                        <button type="button" id="addLokasiBtn" class="px-2 py-2 border border-gray-300 rounded-lg hover:bg-orange-50 hover:border-orange-300 text-sm" title="Tambah Lokasi Baru">
                                            <i class="fas fa-plus text-gray-600"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Catatan (Manual) -->
                                <div class="mb-3">
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Catatan (Optional)</label>
                                    <textarea id="riwayatCatatan" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:border-orange-500 focus:ring-2 focus:ring-orange-200" rows="2" placeholder="Catatan tambahan..."></textarea>
                                </div>
                                
                                <!-- Tombol Tambah -->
                                <button type="button" id="addRiwayatBtn" class="w-full px-3 py-2 bg-blue-500 hover:bg-blue-600 text-white text-sm font-semibold rounded-lg transition-all flex items-center justify-center">
                                    <i class="fas fa-plus mr-2"></i>Tambah Entry
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- User Perangkat -->
                    <div class="form-group">
                        <label for="User_Perangkat" class="block text-gray-700 mb-2">
                            <i class="fas fa-user-cog mr-2 text-blue-500"></i>User yang menggunakan Perangkat
                        </label>
                        <select name="User_Perangkat" id="User_Perangkat" class="w-full select-user-perangkat" required>
                            <option value="">-- Pilih atau Ketik Nama User --</option>
                            <?php
                            foreach ($user_perangkat_list as $user) {
                                $selected = ($User_Perangkat === $user) ? 'selected' : '';
                                echo '<option value="' . htmlspecialchars($user) . '" ' . $selected . '>' . htmlspecialchars($user) . '</option>';
                            }
                            ?>
                        </select>
                        <small class="text-gray-500 mt-1 block">Pilih dari daftar atau ketik nama baru untuk menambah user baru</small>
                    </div>
                </div>

                <!-- Section 6: Status Information -->
                <div class="form-section p-4 sm:p-6 mb-6 sm:mb-8 animate-fade-in-up animate-delay-6">
                    <h3 class="flex items-center text-lg sm:text-xl mb-4 sm:mb-6 bg-gradient-to-r from-orange-600 to-blue-600 bg-clip-text text-transparent">
                        <i class="fas fa-info-circle mr-3 text-orange-500"></i>
                        Status & Kategori
                    </h3>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
                        <!-- Jenis Barang -->
                        <div class="form-group">
                            <label for="Jenis_Barang" class="block text-gray-700 mb-2">
                                <i class="fas fa-sitemap mr-2 text-blue-500"></i>Jenis Barang
                            </label>
                            <select name="Jenis_Barang" class="input-modern w-full px-4 py-3 border border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-200" required>
                                <option value="" disabled <?php if (empty($Jenis_Barang)) echo 'selected'; ?>>Pilih Jenis Barang</option>
                                <option value="INVENTARIS" <?php if ($Jenis_Barang == 'INVENTARIS') echo 'selected'; ?>>INVENTARIS</option>
                                <option value="LOP" <?php if ($Jenis_Barang == 'LOP') echo 'selected'; ?>>LOP</option>
                            </select>
                        </div>

                        <!-- Status Barang -->
                        <div class="form-group">
                            <label for="Status_Barang" class="block text-gray-700 mb-2">
                                <i class="fas fa-signal mr-2 text-orange-500"></i>Status Barang
                            </label>
                            <select name="Status_Barang" class="input-modern w-full px-4 py-3 border border-gray-300 rounded-xl focus:border-orange-500 focus:ring-2 focus:ring-orange-200" required>
                                <option value="" disabled <?php if (empty($Status_Barang)) echo 'selected'; ?>>Pilih Status Barang</option>
                                <option value="READY" <?php if ($Status_Barang == 'READY') echo 'selected'; ?>>READY</option>
                                <option value="IN USE" <?php if ($Status_Barang == 'IN USE') echo 'selected'; ?>>IN USE</option>
                                <option value="KOSONG" <?php if ($Status_Barang == 'KOSONG') echo 'selected'; ?>>KOSONG</option>
                                <option value="REPAIR" <?php if ($Status_Barang == 'REPAIR') echo 'selected'; ?>>REPAIR</option>
                                <option value="TEMPORARY" <?php if ($Status_Barang == 'TEMPORARY') echo 'selected'; ?>>TEMPORARY</option>
                                <option value="RUSAK" <?php if ($Status_Barang == 'RUSAK') echo 'selected'; ?>>RUSAK</option>
                            </select>
                        </div>

                        <!-- Status LOP -->
                        <div class="form-group">
                            <label for="Status_LOP" class="block text-gray-700 mb-2">
                                <i class="fas fa-money-check mr-2 text-blue-500"></i>Status LOP
                            </label>
                            <select name="Status_LOP" class="input-modern w-full px-4 py-3 border border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-200" required>
                                <option value="" disabled <?php if (empty($Status_LOP)) echo 'selected'; ?>>Pilih Status LOP</option>
                                <option value="LUNAS" <?php if ($Status_LOP == 'LUNAS') echo 'selected'; ?>>LUNAS</option>
                                <option value="BELUM LUNAS" <?php if ($Status_LOP == 'BELUM LUNAS') echo 'selected'; ?>>BELUM LUNAS</option>
                                <option value="TIDAK LOP" <?php if ($Status_LOP == 'TIDAK LOP') echo 'selected'; ?>>TIDAK LOP</option>
                            </select>
                        </div>

                        <!-- Status Kelayakan -->
                        <div class="form-group">
                            <label for="Status_Kelayakan_Barang" class="block text-gray-700 mb-2">
                                <i class="fas fa-check-double mr-2 text-orange-500"></i>Status Kelayakan
                            </label>
                            <select name="Status_Kelayakan_Barang" class="input-modern w-full px-4 py-3 border border-gray-300 rounded-xl focus:border-orange-500 focus:ring-2 focus:ring-orange-200" required>
                                <option value="" disabled <?php if (empty($Status_Kelayakan_Barang)) echo 'selected'; ?>>Pilih Status Kelayakan</option>
                                <option value="LAYAK" <?php if ($Status_Kelayakan_Barang == 'LAYAK') echo 'selected'; ?>>LAYAK</option>
                                <option value="TIDAK LAYAK" <?php if ($Status_Kelayakan_Barang == 'TIDAK LAYAK') echo 'selected'; ?>>TIDAK LAYAK</option>
                            </select>
                        </div>

                        <!-- Harga Barang -->
                        <div class="form-group">
                            <label for="Harga_Barang" class="block text-gray-700 mb-2">
                                <i class="fas fa-tags mr-2 text-blue-500"></i>Harga Barang
                            </label>
                            <input type="text" name="Harga_Barang" id="Harga_Barang"
                                   value="<?php echo htmlspecialchars($Harga_Barang); ?>"
                                   class="input-modern w-full px-4 py-3 border border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                                   placeholder="Contoh: 3500000 (opsional)">
                        </div>
                    </div>
                </div>

                <!-- Section 7: Upload Semua Foto -->
                <div class="form-section p-4 sm:p-6 mb-6 sm:mb-8 animate-fade-in-up animate-delay-6">
                    <h3 class="flex items-center text-lg sm:text-xl mb-4 sm:mb-6 bg-gradient-to-r from-orange-600 to-blue-600 bg-clip-text text-transparent">
                        <i class="fas fa-images mr-3 text-orange-500"></i>
                        Upload Foto Barang (5 Sisi)
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6">
                        <!-- Photo Barang -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-3">
                                <i class="fas fa-camera mr-2 text-blue-500"></i>Foto Barang (Umum)
                            </label>
                            <div class="photo-upload rounded-xl p-6 text-center cursor-pointer border-2 border-dashed border-gray-300 hover:border-blue-500 transition-colors">
                                <label for="Photo_Barang" class="block cursor-pointer">
                                    <i class="fas fa-cloud-upload-alt text-3xl text-gray-400 mb-3"></i>
                                    <p class="text-gray-600 mb-2 text-sm">Klik atau drag & drop</p>
                                    <p class="text-xs text-gray-500">JPG, PNG, GIF (max. 2MB)</p>
                                </label>
                                <input type="file" name="Photo_Barang" id="Photo_Barang" accept="image/*" required class="hidden" />
                            </div>
                            <div class="mt-3 flex justify-center">
                                <button type="button" class="btn-gradient text-white px-4 py-2 rounded-lg text-sm flex items-center justify-center cameraOpenBtn" data-photo-id="Photo_Barang">
                                    <i class="fas fa-camera mr-2"></i>Gunakan Kamera
                                </button>
                            </div>
                            <div id="cameraBox_Barang" class="mt-3 hidden p-3 bg-blue-50 rounded-lg border-2 border-blue-200">
                                <video id="cameraVideo_Barang" playsinline autoplay class="w-full max-w-xs rounded-lg shadow-md mx-auto"></video>
                                <div id="cameraGeo_Barang" class="mt-2 text-[11px] text-gray-700 text-center">
                                    <i class="fas fa-location-dot mr-1 text-blue-500"></i>Menunggu lokasi...
                                </div>
                                <div class="mt-3 flex gap-2 justify-center">
                                    <button type="button" class="btn-gradient text-white px-4 py-2 rounded-lg text-sm flex items-center justify-center cameraCaptureBtn" data-photo-id="Photo_Barang">
                                        <i class="fas fa-circle-dot mr-2"></i>Capture
                                    </button>
                                    <button type="button" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg text-sm flex items-center justify-center cameraCloseBtn" data-photo-id="Photo_Barang">
                                        <i class="fas fa-xmark mr-2"></i>Tutup
                                    </button>
                                </div>
                            </div>
                            <div id="photoPreview_Barang" class="mt-3 hidden text-center p-3 bg-orange-50 rounded-lg border-2 border-orange-200">
                                <img id="previewImage_Barang" class="w-32 h-32 object-cover rounded-lg shadow-md mx-auto" alt="Preview" />
                            </div>
                        </div>

                        <!-- Photo Depan -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-3">
                                <i class="fas fa-camera mr-2 text-green-500"></i>Foto Depan
                            </label>
                            <div class="photo-upload rounded-xl p-6 text-center cursor-pointer border-2 border-dashed border-gray-300 hover:border-green-500 transition-colors">
                                <label for="Photo_Depan" class="block cursor-pointer">
                                    <i class="fas fa-cloud-upload-alt text-3xl text-gray-400 mb-3"></i>
                                    <p class="text-gray-600 mb-2 text-sm">Klik atau drag & drop</p>
                                    <p class="text-xs text-gray-500">JPG, PNG, GIF (max. 2MB)</p>
                                </label>
                                <input type="file" name="Photo_Depan" id="Photo_Depan" accept="image/*" required class="hidden" />
                            </div>
                            <div class="mt-3 flex justify-center">
                                <button type="button" class="btn-gradient text-white px-4 py-2 rounded-lg text-sm flex items-center justify-center cameraOpenBtn" data-photo-id="Photo_Depan">
                                    <i class="fas fa-camera mr-2"></i>Gunakan Kamera
                                </button>
                            </div>
                            <div id="cameraBox_Depan" class="mt-3 hidden p-3 bg-green-50 rounded-lg border-2 border-green-200">
                                <video id="cameraVideo_Depan" playsinline autoplay class="w-full max-w-xs rounded-lg shadow-md mx-auto"></video>
                                <div id="cameraGeo_Depan" class="mt-2 text-[11px] text-gray-700 text-center">
                                    <i class="fas fa-location-dot mr-1 text-green-500"></i>Menunggu lokasi...
                                </div>
                                <div class="mt-3 flex gap-2 justify-center">
                                    <button type="button" class="btn-gradient text-white px-4 py-2 rounded-lg text-sm flex items-center justify-center cameraCaptureBtn" data-photo-id="Photo_Depan">
                                        <i class="fas fa-circle-dot mr-2"></i>Capture
                                    </button>
                                    <button type="button" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg text-sm flex items-center justify-center cameraCloseBtn" data-photo-id="Photo_Depan">
                                        <i class="fas fa-xmark mr-2"></i>Tutup
                                    </button>
                                </div>
                            </div>
                            <div id="photoPreview_Depan" class="mt-3 hidden text-center p-3 bg-green-50 rounded-lg border-2 border-green-200">
                                <img id="previewImage_Depan" class="w-32 h-32 object-cover rounded-lg shadow-md mx-auto" alt="Preview" />
                            </div>
                        </div>

                        <!-- Photo Belakang -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-3">
                                <i class="fas fa-camera mr-2 text-yellow-500"></i>Foto Belakang
                            </label>
                            <div class="photo-upload rounded-xl p-6 text-center cursor-pointer border-2 border-dashed border-gray-300 hover:border-yellow-500 transition-colors">
                                <label for="Photo_Belakang" class="block cursor-pointer">
                                    <i class="fas fa-cloud-upload-alt text-3xl text-gray-400 mb-3"></i>
                                    <p class="text-gray-600 mb-2 text-sm">Klik atau drag & drop</p>
                                    <p class="text-xs text-gray-500">JPG, PNG, GIF (max. 2MB)</p>
                                </label>
                                <input type="file" name="Photo_Belakang" id="Photo_Belakang" accept="image/*" required class="hidden" />
                            </div>
                            <div class="mt-3 flex justify-center">
                                <button type="button" class="btn-gradient text-white px-4 py-2 rounded-lg text-sm flex items-center justify-center cameraOpenBtn" data-photo-id="Photo_Belakang">
                                    <i class="fas fa-camera mr-2"></i>Gunakan Kamera
                                </button>
                            </div>
                            <div id="cameraBox_Belakang" class="mt-3 hidden p-3 bg-yellow-50 rounded-lg border-2 border-yellow-200">
                                <video id="cameraVideo_Belakang" playsinline autoplay class="w-full max-w-xs rounded-lg shadow-md mx-auto"></video>
                                <div id="cameraGeo_Belakang" class="mt-2 text-[11px] text-gray-700 text-center">
                                    <i class="fas fa-location-dot mr-1 text-yellow-500"></i>Menunggu lokasi...
                                </div>
                                <div class="mt-3 flex gap-2 justify-center">
                                    <button type="button" class="btn-gradient text-white px-4 py-2 rounded-lg text-sm flex items-center justify-center cameraCaptureBtn" data-photo-id="Photo_Belakang">
                                        <i class="fas fa-circle-dot mr-2"></i>Capture
                                    </button>
                                    <button type="button" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg text-sm flex items-center justify-center cameraCloseBtn" data-photo-id="Photo_Belakang">
                                        <i class="fas fa-xmark mr-2"></i>Tutup
                                    </button>
                                </div>
                            </div>
                            <div id="photoPreview_Belakang" class="mt-3 hidden text-center p-3 bg-yellow-50 rounded-lg border-2 border-yellow-200">
                                <img id="previewImage_Belakang" class="w-32 h-32 object-cover rounded-lg shadow-md mx-auto" alt="Preview" />
                            </div>
                        </div>

                        <!-- Photo SN -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-3">
                                <i class="fas fa-camera mr-2 text-red-500"></i>Foto Serial Number
                            </label>
                            <div class="photo-upload rounded-xl p-6 text-center cursor-pointer border-2 border-dashed border-gray-300 hover:border-red-500 transition-colors">
                                <label for="Photo_SN" class="block cursor-pointer">
                                    <i class="fas fa-cloud-upload-alt text-3xl text-gray-400 mb-3"></i>
                                    <p class="text-gray-600 mb-2 text-sm">Klik atau drag & drop</p>
                                    <p class="text-xs text-gray-500">JPG, PNG, GIF (max. 2MB)</p>
                                </label>
                                <input type="file" name="Photo_SN" id="Photo_SN" accept="image/*" required class="hidden" />
                            </div>
                            <div class="mt-3 flex justify-center">
                                <button type="button" class="btn-gradient text-white px-4 py-2 rounded-lg text-sm flex items-center justify-center cameraOpenBtn" data-photo-id="Photo_SN">
                                    <i class="fas fa-camera mr-2"></i>Gunakan Kamera
                                </button>
                            </div>
                            <div id="cameraBox_SN" class="mt-3 hidden p-3 bg-red-50 rounded-lg border-2 border-red-200">
                                <video id="cameraVideo_SN" playsinline autoplay class="w-full max-w-xs rounded-lg shadow-md mx-auto"></video>
                                <div id="cameraGeo_SN" class="mt-2 text-[11px] text-gray-700 text-center">
                                    <i class="fas fa-location-dot mr-1 text-red-500"></i>Menunggu lokasi...
                                </div>
                                <div class="mt-3 flex gap-2 justify-center">
                                    <button type="button" class="btn-gradient text-white px-4 py-2 rounded-lg text-sm flex items-center justify-center cameraCaptureBtn" data-photo-id="Photo_SN">
                                        <i class="fas fa-circle-dot mr-2"></i>Capture
                                    </button>
                                    <button type="button" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg text-sm flex items-center justify-center cameraCloseBtn" data-photo-id="Photo_SN">
                                        <i class="fas fa-xmark mr-2"></i>Tutup
                                    </button>
                                </div>
                            </div>
                            <div id="photoPreview_SN" class="mt-3 hidden text-center p-3 bg-red-50 rounded-lg border-2 border-red-200">
                                <img id="previewImage_SN" class="w-32 h-32 object-cover rounded-lg shadow-md mx-auto" alt="Preview" />
                            </div>
                        </div>

                        <!-- Photo Invoice (Optional) -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-3">
                                <i class="fas fa-file-invoice mr-2 text-blue-500"></i>Dokumen Invoice (Optional)
                            </label>
                            <div class="photo-upload rounded-xl p-6 text-center cursor-pointer border-2 border-dashed border-gray-300 hover:border-blue-500 transition-colors">
                                <label for="Photo_Invoice" class="block cursor-pointer">
                                    <i class="fas fa-cloud-upload-alt text-3xl text-gray-400 mb-3"></i>
                                    <p class="text-gray-600 mb-2 text-sm">Klik atau drag & drop</p>
                                    <p class="text-xs text-gray-500">JPG, PNG, GIF, PDF (max. 2MB)</p>
                                </label>
                                <input type="file" name="Photo_Invoice" id="Photo_Invoice" accept="image/*,application/pdf,.pdf" class="hidden" />
                            </div>
                            <div id="photoPreview_Invoice" class="mt-3 hidden text-center p-3 bg-blue-50 rounded-lg border-2 border-blue-200">
                                <img id="previewImage_Invoice" class="w-32 h-32 object-cover rounded-lg shadow-md mx-auto" alt="Preview" />
                            </div>
                        </div>
                    </div>
                </div>



                <!-- Submit Buttons -->
                <div class="flex flex-col sm:flex-row gap-3 sm:gap-4 justify-center animate-fade-in-up animate-delay-6 mt-6 sm:mt-8">
                    <button type="submit" class="btn-gradient text-white px-6 sm:px-8 py-3 sm:py-4 rounded-xl text-base sm:text-lg hover:shadow-lg transform transition-all duration-300 flex items-center justify-center w-full sm:w-auto">
                        <i class="fas fa-save mr-2 sm:mr-3"></i>
                        <span>Simpan Data Asset</span>
                    </button>
                    
                    <a href="index.php" class="bg-gray-500 hover:bg-gray-600 text-white px-6 sm:px-8 py-3 sm:py-4 rounded-xl text-base sm:text-lg text-center transition-all duration-300 flex items-center justify-center w-full sm:w-auto">
                        <i class="fas fa-times mr-2 sm:mr-3"></i>
                        <span>Batal</span>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Template for Adding Custom Options -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <h3 class="text-xl mb-4 bg-gradient-to-r from-orange-600 to-blue-600 bg-clip-text text-transparent flex items-center">
                <i class="fas fa-plus mr-2 text-orange-500"></i>
                <span id="modalTitle">Tambah Option Baru</span>
            </h3>
            <div class="mb-4">
                <label class="block text-gray-700 mb-2">Nama <span id="modalFieldName"></span></label>
                <input type="text" id="customOptionInput" class="input-modern w-full px-4 py-3 border border-gray-300 rounded-xl" placeholder="Masukkan nilai baru">
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" id="cancelModalBtn" class="bg-gray-400 hover:bg-gray-500 text-white px-6 py-2 rounded-lg transition-all">Batal</button>
                <button type="button" id="saveModalBtn" class="btn-gradient text-white px-6 py-2 rounded-lg">
                    <i class="fas fa-check mr-2"></i>Simpan
                </button>
            </div>
        </div>
    </div>

    <script>
        let currentField = '';
        let currentFieldName = '';

        // Fungsi untuk re-init Select2 setelah menambah opsi baru
        function reinitSelect2(fieldId) {
            const selectElement = $('#' + fieldId);
            if (selectElement.length && selectElement.hasClass('select2-field')) {
                // Destroy existing Select2
                if (selectElement.data('select2')) {
                    selectElement.select2('destroy');
                }
                
                // Re-initialize Select2
                selectElement.select2({
                    theme: 'default',
                    placeholder: selectElement.attr('placeholder') || 'Pilih atau ketik...',
                    allowClear: true,
                    width: '100%',
                    dropdownParent: $('body'),
                    language: {
                        noResults: function() {
                            return "Tidak ditemukan hasil";
                        },
                        searching: function() {
                            return "Mencari...";
                        }
                    }
                });
            }
        }

        // Serial Number validation
        let serialTimeout;
        $('#Serial_Number').on('input', function() {
            const serial = $(this).val().trim();
            const feedback = $('#serialNumberFeedback');
            const spinner = $('#serialNumberSpinner');
            const iconContainer = $('#serialNumberIcon');
            const availableIcon = $('#availableIcon');
            const takenIcon = $('#takenIcon');
            
            // Clear previous timeout
            clearTimeout(serialTimeout);
            
            // Hide all indicators first
            spinner.addClass('hidden');
            iconContainer.addClass('hidden');
            availableIcon.hide();
            takenIcon.hide();
            feedback.addClass('hidden').removeClass('feedback-error feedback-success');
            
            if (serial.length > 0) {
                // Show spinner
                spinner.removeClass('hidden');
                
                // Debounce the API call
                serialTimeout = setTimeout(() => {
                    $.ajax({
                        url: 'cek_serial.php',
                        method: 'POST',
                        data: { serial: serial },
                        dataType: 'json',
                        success: function(res) {
                            spinner.addClass('hidden');
                            iconContainer.removeClass('hidden');
                            
                            if (res && typeof res.exists !== "undefined") {
                                if (res.exists === true) {
                                    takenIcon.show();
                                    feedback.removeClass('hidden feedback-success').addClass('feedback-error').text('Serial Number sudah terdaftar!');
                                } else {
                                    availableIcon.show();
                                    feedback.removeClass('hidden feedback-error').addClass('feedback-success').text('Serial Number tersedia.');
                                }
                            }
                        },
                        error: function() {
                            spinner.addClass('hidden');
                            iconContainer.removeClass('hidden');
                            takenIcon.show();
                            feedback.removeClass('hidden feedback-success').addClass('feedback-error').text('Terjadi kesalahan saat memeriksa Serial Number.');
                        }
                    });
                }, 500);
            }
        });

        // TAMBAHAN: Kompres gambar client-side sebelum upload (best-effort ke ~<100KB)
        const IMAGE_COMPRESS_TARGET_BYTES = 100 * 1024; // 100KB
        const IMAGE_COMPRESS_MAX_DIM = 1600; // px

        function replaceInputFile(fileInput, newFile) {
            if (!fileInput || !newFile) return;
            try {
                const dt = new DataTransfer();
                dt.items.add(newFile);
                fileInput.files = dt.files;
            } catch (e) {
                console.warn('replaceInputFile failed; fallback to original file.', e);
            }
        }

        async function compressImageToTarget(file, targetBytes, maxDim) {
            if (!file || !file.type || !file.type.startsWith('image/')) return file;
            if (file.type === 'image/gif') return file; // keep animated GIF

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
                console.warn('Failed to decode image for compression:', e);
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
                // white background to avoid black for transparent PNG
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, w, h);
                ctx.drawImage(bitmap, 0, 0, w, h);
            };

            const toJpegBlob = (quality) => new Promise((resolve) => {
                canvas.toBlob((b) => resolve(b), 'image/jpeg', quality);
            });

            let bestBlob = null;
            let quality = 0.85;
            let attempts = 0;
            let dimAttempts = 0;

            render(targetW, targetH);

            while (attempts < 8) {
                const blob = await toJpegBlob(quality);
                if (!blob) break;
                bestBlob = blob;

                if (blob.size <= targetBytes) {
                    break;
                }

                // reduce quality first
                if (quality > 0.55) {
                    quality = Math.max(0.55, quality - 0.07);
                } else {
                    // then reduce dimensions a bit
                    if (dimAttempts >= 3) break;
                    dimAttempts += 1;
                    targetW = Math.max(1, Math.round(targetW * 0.9));
                    targetH = Math.max(1, Math.round(targetH * 0.9));
                    render(targetW, targetH);
                }

                attempts += 1;
            }

            // Cleanup bitmap URL if Image() fallback was used
            if (!(bitmap instanceof ImageBitmap) && bitmap && bitmap.src && bitmap.src.startsWith('blob:')) {
                try { URL.revokeObjectURL(bitmap.src); } catch (e) {}
            }

            if (!bestBlob) return file;

            // Only use compressed if it's smaller
            if (bestBlob.size >= file.size) return file;

            const baseName = (file.name || 'image').replace(/\.[^/.]+$/, '');
            const newName = baseName + '.jpg';
            return new File([bestBlob], newName, { type: 'image/jpeg', lastModified: Date.now() });
        }

        async function prepareFileBeforeUpload(file, photoFieldId) {
            if (!file) return file;
            // PDF invoice: leave as-is
            if (photoFieldId === 'Photo_Invoice' && file.type === 'application/pdf') return file;
            // images: compress best-effort
            if (file.type && file.type.startsWith('image/')) {
                return await compressImageToTarget(file, IMAGE_COMPRESS_TARGET_BYTES, IMAGE_COMPRESS_MAX_DIM);
            }
            return file;
        }

        // TAMBAHAN: Kamera + koordinat real-time (hanya untuk foto barang, bukan invoice)
        const cameraState = {};
        let geoWatchId = null;
        let geoLast = null;
        const geocodeCache = new Map();
        let geocodeInFlightKey = null;
        let geocodeLastAt = 0;

        function formatDateTimeLocal(d) {
            const pad = (n) => String(n).padStart(2, '0');
            return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) +
                ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
        }

        function renderGeoText(targetEl) {
            if (!targetEl) return;
            if (geoLast && geoLast.lat) {
                const coordsLine = `Lat: ${geoLast.lat.toFixed(6)}, Lng: ${geoLast.lng.toFixed(6)} (±${Math.round(geoLast.acc)}m)`;
                const addrLine = geoLast.addr
                    ? `Alamat: ${geoLast.addr}`
                    : (geoLast.addrPending ? 'Alamat: mencari...' : 'Alamat: belum tersedia');
                targetEl.innerHTML = `${addrLine}<br>${coordsLine}`;
            } else if (geoLast && geoLast.error) {
                targetEl.textContent = `Lokasi tidak tersedia: ${geoLast.error}`;
            } else {
                targetEl.textContent = 'Menunggu lokasi...';
            }
        }

        function updateAllGeoEls() {
            document.querySelectorAll('[id^="cameraGeo_"]').forEach((el) => renderGeoText(el));
        }

        async function reverseGeocodeAddress(lat, lng) {
            // cache key rounded to ~11m
            const key = `${lat.toFixed(4)},${lng.toFixed(4)}`;
            if (geocodeCache.has(key)) {
                return geocodeCache.get(key);
            }

            // throttle requests (avoid spam)
            const now = Date.now();
            if (now - geocodeLastAt < 2500) {
                return null;
            }
            if (geocodeInFlightKey) {
                return null;
            }
            geocodeInFlightKey = key;
            geocodeLastAt = now;

            try {
                const url = `https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${encodeURIComponent(lat)}&lon=${encodeURIComponent(lng)}&zoom=18&addressdetails=1`;
                const res = await fetch(url, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json'
                    }
                });
                if (!res.ok) {
                    return null;
                }
                const data = await res.json();
                const addr = (data && (data.display_name || (data.name ? data.name : ''))) ? String(data.display_name || data.name) : '';
                const trimmed = addr.length > 140 ? (addr.slice(0, 137) + '...') : addr;
                if (trimmed) {
                    geocodeCache.set(key, trimmed);
                    return trimmed;
                }
                return null;
            } catch (e) {
                return null;
            } finally {
                geocodeInFlightKey = null;
            }
        }

        function startGeoWatch() {
            if (!navigator.geolocation) {
                geoLast = { error: 'Geolocation tidak didukung browser.' };
                return;
            }
            if (geoWatchId !== null) return;
            geoWatchId = navigator.geolocation.watchPosition(
                (pos) => {
                    geoLast = {
                        lat: pos.coords.latitude,
                        lng: pos.coords.longitude,
                        acc: pos.coords.accuracy,
                        ts: pos.timestamp,
                        addr: geoLast && geoLast.addr ? geoLast.addr : null,
                        addrPending: true
                    };
                    updateAllGeoEls();

                    // best-effort reverse geocode
                    (async () => {
                        const addr = await reverseGeocodeAddress(geoLast.lat, geoLast.lng);
                        if (addr && geoLast) {
                            geoLast.addr = addr;
                            geoLast.addrPending = false;
                            updateAllGeoEls();
                        } else if (geoLast) {
                            geoLast.addrPending = false;
                            updateAllGeoEls();
                        }
                    })();
                },
                (err) => {
                    geoLast = { error: err && err.message ? err.message : 'Gagal ambil lokasi.' };
                    updateAllGeoEls();
                },
                { enableHighAccuracy: true, maximumAge: 5000, timeout: 15000 }
            );
        }

        function stopGeoWatchIfUnused() {
            const anyOpen = Object.values(cameraState).some((s) => s && s.isOpen);
            if (!anyOpen && geoWatchId !== null) {
                try { navigator.geolocation.clearWatch(geoWatchId); } catch (e) {}
                geoWatchId = null;
            }
        }

        async function openCameraFor(photoFieldId) {
            if (photoFieldId === 'Photo_Invoice') return; // ignore invoice
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                Swal.fire({ icon: 'error', title: 'Error!', text: 'Browser tidak mendukung akses kamera.' });
                return;
            }

            const base = photoFieldId.replace('Photo_', '');
            const box = document.getElementById('cameraBox_' + base);
            const video = document.getElementById('cameraVideo_' + base);
            const geoEl = document.getElementById('cameraGeo_' + base);
            if (!box || !video) return;

            // close others
            for (const key of Object.keys(cameraState)) {
                if (key !== photoFieldId) {
                    await closeCameraFor(key);
                }
            }

            startGeoWatch();
            renderGeoText(geoEl);

            try {
                const stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: { ideal: 'environment' } },
                    audio: false
                });
                video.srcObject = stream;
                await video.play();
                box.classList.remove('hidden');

                cameraState[photoFieldId] = { stream, isOpen: true };
            } catch (err) {
                Swal.fire({
                    icon: 'error',
                    title: 'Kamera tidak bisa dibuka',
                    text: (err && err.message) ? err.message : 'Periksa izin kamera di browser.'
                });
            }
        }

        async function closeCameraFor(photoFieldId) {
            const base = photoFieldId.replace('Photo_', '');
            const box = document.getElementById('cameraBox_' + base);
            const video = document.getElementById('cameraVideo_' + base);
            if (box) box.classList.add('hidden');

            const st = cameraState[photoFieldId];
            if (st && st.stream) {
                try { st.stream.getTracks().forEach((t) => t.stop()); } catch (e) {}
            }
            if (video) {
                try { video.pause(); } catch (e) {}
                video.srcObject = null;
            }
            cameraState[photoFieldId] = { isOpen: false };
            stopGeoWatchIfUnused();
        }

        async function captureFromCamera(photoFieldId) {
            const base = photoFieldId.replace('Photo_', '');
            const video = document.getElementById('cameraVideo_' + base);
            const fileInput = document.getElementById(photoFieldId);
            const previewId = 'photoPreview_' + base;

            if (!video || !fileInput) return;
            if (!video.videoWidth || !video.videoHeight) {
                Swal.fire({ icon: 'warning', title: 'Tunggu sebentar', text: 'Kamera belum siap.' });
                return;
            }

            const maxDim = IMAGE_COMPRESS_MAX_DIM;
            const scale = Math.min(1, maxDim / Math.max(video.videoWidth, video.videoHeight));
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

            const nowText = formatDateTimeLocal(new Date());
            const addrLine = (geoLast && geoLast.addr)
                ? `Alamat: ${geoLast.addr}`
                : 'Alamat: belum tersedia';
            const coordsLine = (geoLast && geoLast.lat)
                ? `Lat:${geoLast.lat.toFixed(6)} Lng:${geoLast.lng.toFixed(6)} Acc:±${Math.round(geoLast.acc)}m | ${nowText}`
                : `Lokasi: tidak tersedia | ${nowText}`;

            ctx.font = '14px sans-serif';
            const padding = 10;
            const maxTextWidth = w - padding * 2 - 16;
            const textW1 = Math.min(maxTextWidth, ctx.measureText(addrLine).width);
            const textW2 = Math.min(maxTextWidth, ctx.measureText(coordsLine).width);
            const boxW = Math.max(Math.max(textW1, textW2) + 16, 260);
            const boxH = 50;
            ctx.fillStyle = 'rgba(0,0,0,0.45)';
            ctx.fillRect(padding, h - boxH - padding, Math.min(boxW, w - padding * 2), boxH);
            ctx.fillStyle = '#ffffff';
            ctx.fillText(addrLine, padding + 8, h - padding - 28);
            ctx.fillText(coordsLine, padding + 8, h - padding - 10);

            const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.92));
            if (!blob) {
                Swal.fire({ icon: 'error', title: 'Error!', text: 'Gagal mengambil gambar dari kamera.' });
                return;
            }

            const fileName = `${photoFieldId}_${Date.now()}.jpg`;
            let capturedFile = new File([blob], fileName, { type: 'image/jpeg', lastModified: Date.now() });
            try {
                capturedFile = await prepareFileBeforeUpload(capturedFile, photoFieldId);
            } catch (e) {}

            replaceInputFile(fileInput, capturedFile);
            handlePhotoFile(capturedFile, photoFieldId, previewId);
            await closeCameraFor(photoFieldId);
        }

        // TAMBAHAN: Fungsi universal untuk handle photo upload (support preview, drag & drop, validasi)
function initPhotoUpload(photoFieldId) {
    const fileInput = document.getElementById(photoFieldId);
    // Parse ID tanpa "Photo_" untuk preview ID
    // Photo_Barang → Barang, Photo_Depan → Depan, etc
    const basePhotoName = photoFieldId.replace('Photo_', '');
    const photoPreviewId = 'photoPreview_' + basePhotoName;
    
    if (!fileInput) {
        console.error('File input not found:', photoFieldId);
        return;
    }
    
    console.log('Initializing photo upload for:', photoFieldId);
    console.log('Looking for preview with ID:', photoPreviewId);
    
    // Find the upload container (parent .photo-upload)
    const uploadContainer = fileInput.closest('.photo-upload');
    const previewDiv = document.getElementById(photoPreviewId);
    
    if (!uploadContainer) {
        console.error('Upload container not found for:', photoFieldId);
        return;
    }
    
    if (!previewDiv) {
        console.error('Preview div not found:', photoPreviewId);
        return;
    }

    console.log('✅ Found upload container and preview div for:', photoFieldId);

    // Handle file selection (click)
    fileInput.addEventListener('change', async function(e) {
        console.log('File input changed for:', photoFieldId, 'Files count:', this.files.length);
        if (this.files && this.files.length > 0) {
            const originalFile = this.files[0];
            let preparedFile = originalFile;
            try {
                preparedFile = await prepareFileBeforeUpload(originalFile, photoFieldId);
            } catch (err) {
                console.warn('prepareFileBeforeUpload failed:', err);
                preparedFile = originalFile;
            }

            if (preparedFile && preparedFile !== originalFile) {
                replaceInputFile(fileInput, preparedFile);
            }

            handlePhotoFile(preparedFile, photoFieldId, photoPreviewId);
        }
    });

    // Handle drag & drop
    uploadContainer.addEventListener('dragover', function(e) {
        e.preventDefault();
        e.stopPropagation();
        console.log('Drag over:', photoFieldId);
        this.style.borderColor = '#f97316';
        this.style.backgroundColor = 'rgba(249, 115, 22, 0.08)';
    });

    uploadContainer.addEventListener('dragleave', function(e) {
        e.preventDefault();
        e.stopPropagation();
        console.log('Drag leave:', photoFieldId);
        this.style.borderColor = '';
        this.style.backgroundColor = '';
    });

    uploadContainer.addEventListener('drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        this.style.borderColor = '';
        this.style.backgroundColor = '';
        
        console.log('Drop detected for:', photoFieldId);
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            fileInput.files = files;
            // Trigger change event
            const event = new Event('change', { bubbles: true });
            fileInput.dispatchEvent(event);
        }
    });
}

// Fungsi untuk memproses file foto
function handlePhotoFile(file, photoFieldId, photoPreviewId) {
    console.log('=== handlePhotoFile called ===');
    console.log('photoFieldId:', photoFieldId);
    console.log('photoPreviewId:', photoPreviewId);
    console.log('file:', file);
    
    if (!file) {
        console.error('No file provided');
        return;
    }

    console.log('Processing file:', file.name, 'Type:', file.type, 'Size:', file.size);

    // Get preview container FIRST
    const previewDiv = document.getElementById(photoPreviewId);
    console.log('Preview div found:', previewDiv ? 'YES' : 'NO');
    
    if (!previewDiv) {
        console.error('Preview div not found with id:', photoPreviewId);
        return;
    }

    // Validasi ukuran file (2MB max)
    const MAX_SIZE = 2 * 1024 * 1024; // 2MB
    if (file.size > MAX_SIZE) {
        console.error('File too large');
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'Ukuran file terlalu besar. Maksimum 2MB.',
            confirmButtonText: 'OK'
        });
        document.getElementById(photoFieldId).value = '';
        return;
    }

    // Validasi tipe file
    const isInvoice = (photoFieldId === 'Photo_Invoice');
    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    if (isInvoice) {
        allowedTypes.push('application/pdf');
    }
    if (!allowedTypes.includes(file.type)) {
        console.error('File type not allowed:', file.type);
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: isInvoice
                ? 'Format file tidak didukung. Gunakan JPG, PNG, GIF, WebP, atau PDF.'
                : 'Format file tidak didukung. Gunakan JPG, PNG, GIF, atau WebP.',
            confirmButtonText: 'OK'
        });
        document.getElementById(photoFieldId).value = '';
        return;
    }

    // Show loading spinner
    console.log('Showing loading spinner');
    const loadingHTML = '<div class="w-32 h-32 bg-gray-200 rounded-lg flex items-center justify-center mx-auto"><i class="fas fa-spinner fa-spin text-gray-500 text-lg"></i><p class="text-xs text-gray-500 mt-2">Loading...</p></div>';
    previewDiv.classList.remove('hidden');
    previewDiv.innerHTML = loadingHTML;

    // Jika sebelumnya ada blob URL (mis. PDF), bersihkan dulu
    if (previewDiv.dataset && previewDiv.dataset.blobUrl) {
        try {
            URL.revokeObjectURL(previewDiv.dataset.blobUrl);
        } catch (err) {
            console.warn('Failed to revoke old blob URL:', err);
        }
        delete previewDiv.dataset.blobUrl;
    }

    // Untuk PDF invoice: gunakan blob URL (lebih stabil daripada data URL)
    if (isInvoice && file.type === 'application/pdf') {
        const blobUrl = URL.createObjectURL(file);
        previewDiv.dataset.blobUrl = blobUrl;

        const safeFileName = String(file.name || 'invoice.pdf');
        const previewHTML = `
            <div class="relative inline-block">
                <div class="w-32 h-32 bg-white rounded-lg shadow-md border-2 border-blue-200 flex flex-col items-center justify-center mx-auto p-2">
                    <i class="fas fa-file-pdf text-red-500 text-3xl"></i>
                    <div class="text-[10px] text-gray-600 mt-2 break-words">${safeFileName}</div>
                    <a href="${blobUrl}" target="_blank" rel="noopener" class="text-[11px] text-blue-600 underline mt-1">Buka PDF</a>
                </div>
                <button type="button" class="deletePhotoBtn absolute top-1 right-1 bg-red-500 hover:bg-red-600 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs" data-photo-id="${photoFieldId}" title="Hapus file">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;

        previewDiv.innerHTML = previewHTML;

        Swal.fire({
            icon: 'success',
            title: 'Berhasil!',
            text: 'File Invoice berhasil dipilih',
            timer: 2000,
            showConfirmButton: false
        });

        const deleteBtn = previewDiv.querySelector('.deletePhotoBtn');
        if (deleteBtn) {
            deleteBtn.addEventListener('click', function(e) {
                e.preventDefault();
                deletePhoto(photoFieldId, photoPreviewId);
            });
        }

        return;
    }

    // FileReader untuk preview
    const reader = new FileReader();
    
    reader.onload = function(e) {
        console.log('FileReader onload triggered');
        console.log('Data URL length:', e.target.result.length);

        const isPdf = (file.type === 'application/pdf');
        const safeFileName = String(file.name || 'invoice.pdf');
        const previewHTML = isPdf
            ? `
            <div class="relative inline-block">
                <div class="w-32 h-32 bg-white rounded-lg shadow-md border-2 border-blue-200 flex flex-col items-center justify-center mx-auto p-2">
                    <i class="fas fa-file-pdf text-red-500 text-3xl"></i>
                    <div class="text-[10px] text-gray-600 mt-2 break-words">${safeFileName}</div>
                    <a href="${e.target.result}" target="_blank" rel="noopener" class="text-[11px] text-blue-600 underline mt-1">Buka PDF</a>
                </div>
                <button type="button" class="deletePhotoBtn absolute top-1 right-1 bg-red-500 hover:bg-red-600 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs" data-photo-id="${photoFieldId}" title="Hapus file">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            `
            : `
            <div class="relative inline-block">
                <img src="${e.target.result}" alt="Preview Gambar" class="w-32 h-32 object-cover rounded-lg shadow-md border-2 border-orange-300" />
                <button type="button" class="deletePhotoBtn absolute top-1 right-1 bg-red-500 hover:bg-red-600 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs" data-photo-id="${photoFieldId}" title="Hapus foto">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            `;
        previewDiv.innerHTML = previewHTML;
        console.log('Preview HTML set successfully');
        
        // Show success alert
        Swal.fire({
            icon: 'success',
            title: 'Berhasil!',
            text: 'Foto ' + photoFieldId.replace('Photo_', '') + ' berhasil diupload',
            timer: 2000,
            showConfirmButton: false
        });
        
        // Attach delete button event listener
        const deleteBtn = previewDiv.querySelector('.deletePhotoBtn');
        console.log('Delete button found:', deleteBtn ? 'YES' : 'NO');
        
        if (deleteBtn) {
            deleteBtn.addEventListener('click', function(e) {
                e.preventDefault();
                deletePhoto(photoFieldId, photoPreviewId);
            });
        }
    };
    
    reader.onerror = function(e) {
        console.error('FileReader error:', e);
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'Gagal membaca file gambar.',
            confirmButtonText: 'OK'
        });
        document.getElementById(photoFieldId).value = '';
        previewDiv.classList.add('hidden');
    };
    
    reader.onprogress = function(e) {
        if (e.lengthComputable) {
            console.log('Reading progress:', Math.round((e.loaded / e.total) * 100) + '%');
        }
    };
    
    console.log('Starting FileReader.readAsDataURL');
    reader.readAsDataURL(file);
}

// Fungsi untuk delete/hapus foto
function deletePhoto(photoFieldId, photoPreviewId) {
    Swal.fire({
        title: 'Hapus Foto?',
        text: 'Apakah Anda yakin ingin menghapus foto ini?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            // Clear file input
            document.getElementById(photoFieldId).value = '';
            
            // Hide preview
            const previewDiv = document.getElementById(photoPreviewId);

            // Revoke blob URL (untuk PDF invoice) jika ada
            if (previewDiv && previewDiv.dataset && previewDiv.dataset.blobUrl) {
                try {
                    URL.revokeObjectURL(previewDiv.dataset.blobUrl);
                } catch (err) {
                    console.warn('Failed to revoke blob URL:', err);
                }
                delete previewDiv.dataset.blobUrl;
            }

            previewDiv.classList.add('hidden');
            previewDiv.innerHTML = '';
            
            // Show deleted alert
            Swal.fire({
                icon: 'success',
                title: 'Dihapus!',
                text: 'Foto ' + photoFieldId.replace('Photo_', '') + ' sudah dihapus',
                timer: 2000,
                showConfirmButton: false
            });
        }
    });
}
// Expose to global scope
window.deletePhoto = deletePhoto;

// Inisialisasi semua photo uploads pada document ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM ready - initializing photo uploads');
    
    // Photo upload initialization
    initPhotoUpload('Photo_Barang');
    initPhotoUpload('Photo_Depan');
    initPhotoUpload('Photo_Belakang');
    initPhotoUpload('Photo_SN');
    initPhotoUpload('Photo_Invoice');

    // Kamera untuk foto (bukan invoice)
    document.querySelectorAll('.cameraOpenBtn').forEach((btn) => {
        btn.addEventListener('click', async (e) => {
            e.preventDefault();
            const photoFieldId = btn.getAttribute('data-photo-id');
            if (photoFieldId) {
                await openCameraFor(photoFieldId);
            }
        });
    });
    document.querySelectorAll('.cameraCloseBtn').forEach((btn) => {
        btn.addEventListener('click', async (e) => {
            e.preventDefault();
            const photoFieldId = btn.getAttribute('data-photo-id');
            if (photoFieldId) {
                await closeCameraFor(photoFieldId);
            }
        });
    });
    document.querySelectorAll('.cameraCaptureBtn').forEach((btn) => {
        btn.addEventListener('click', async (e) => {
            e.preventDefault();
            const photoFieldId = btn.getAttribute('data-photo-id');
            if (photoFieldId) {
                await captureFromCamera(photoFieldId);
            }
        });
    });
    
    console.log('Photo uploads initialized');
    
    // TAMBAHAN: Inisialisasi Select2 untuk dropdown searchable
    if (typeof $ !== 'undefined' && $.fn.select2) {
        $('.select2-field').select2({
            theme: 'default',
            placeholder: function() {
                return $(this).data('placeholder') || 'Pilih atau ketik...';
            },
            allowClear: true,
            width: '100%',
            dropdownParent: $('body'),
            language: {
                noResults: function() {
                    return "Tidak ditemukan hasil";
                },
                searching: function() {
                    return "Mencari...";
                },
                inputTooShort: function(args) {
                    var remainingChars = args.minimum - args.input.length;
                    return "Ketik " + remainingChars + " karakter lagi untuk mencari";
                }
            }
        });
    }
                    
// Trigger initial animations using vanilla JS
window.addEventListener('load', function() {
    setTimeout(() => {
        document.querySelectorAll('.animate-fade-in-up').forEach((element, index) => {
            setTimeout(() => {
                element.style.animationPlayState = 'running';
            }, index * 100);
        });
    }, 100);
});

        // Show add modal
        function showAddModal(field, fieldName) {
            console.log('showAddModal called with:', field, fieldName);
            currentField = field;
            currentFieldName = fieldName;
            $('#modalTitle').text('Tambah ' + fieldName + ' Baru');
            $('#modalFieldName').text(fieldName);
            $('#customOptionInput').val('');
            $('#addModal').addClass('show');
            // Focus setelah modal muncul
            setTimeout(function() {
                $('#customOptionInput').focus();
            }, 100);
        }
        // Expose to global scope for onclick attribute
        window.showAddModal = showAddModal;

        // Hide modal
        function hideModal() {
            console.log('hideModal called');
            $('#addModal').removeClass('show');
            $('#customOptionInput').val('');
            currentField = '';
            currentFieldName = '';
        }
        // Expose to global scope
        window.hideModal = hideModal;

        // Save custom option (updated dengan re-init Select2)
        function saveCustomOption() {
            console.log('saveCustomOption called');
            const value = $('#customOptionInput').val().trim();
            
            if (!value) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Input Kosong!',
                    text: 'Silakan masukkan nilai yang valid.',
                    confirmButtonText: 'OK'
                });
                return;
            }

            // Map field names to actual select elements
            const fieldMap = {
                'namaBarang': 'Nama_Barang',
                'namaVendor': 'Nama_Toko_Pembelian',
                'merek': 'Merek',
                'type': 'Type',
                'lokasi': 'Lokasi',
                'idKaryawan': 'Id_Karyawan',
                'jabatan': 'Jabatan'
            };

            const selectElement = $('#' + fieldMap[currentField]);
            
            if (!selectElement.length) {
                console.error('Select element not found for field:', currentField);
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'Terjadi kesalahan sistem. Field tidak ditemukan.',
                    confirmButtonText: 'OK'
                });
                return;
            }
            
            // Check if option already exists
            let optionExists = false;
            selectElement.find('option').each(function() {
                if ($(this).val().toLowerCase() === value.toLowerCase()) {
                    optionExists = true;
                    return false;
                }
            });

            if (optionExists) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Peringatan!',
                    text: currentFieldName + ' "' + value + '" sudah ada.',
                    confirmButtonText: 'OK'
                });
                return;
            }

            // Add new option
            const newOption = $('<option></option>')
                .attr('value', value)
                .text(value)
                .prop('selected', true);
            
            selectElement.append(newOption);

            // Re-init Select2 agar opsi baru searchable
            console.log('Calling reinitSelect2 for:', fieldMap[currentField]);
            reinitSelect2(fieldMap[currentField]);

            // Show success message
            Swal.fire({
                icon: 'success',
                title: 'Berhasil!',
                text: currentFieldName + ' "' + value + '" berhasil ditambahkan!',
                timer: 2000,
                showConfirmButton: false
            });

            hideModal();
        }
        // Expose to global scope
        window.saveCustomOption = saveCustomOption;

        // Initialize animations on scroll
        $(window).scroll(function() {
            $('.form-section').each(function() {
                const elementTop = $(this).offset().top;
                const elementBottom = elementTop + $(this).outerHeight();
                const viewportTop = $(window).scrollTop();
                const viewportBottom = viewportTop + $(window).height();
                
                if (elementBottom > viewportTop && elementTop < viewportBottom) {
                    $(this).addClass('animate-fade-in-up');
                }
            });
        });

        // Initialize Select2 for User_Perangkat with tags support (searchable and custom input)
        $(document).ready(function() {
            // ============ MODAL EVENT LISTENERS ============
            console.log('Initializing modal event listeners');
            
            $('#cancelModalBtn').on('click', function() {
                console.log('Cancel button clicked');
                hideModal();
            });
            
            $('#addModal').on('click', function(e) {
                if (e.target === this) {
                    console.log('Modal backdrop clicked');
                    hideModal();
                }
            });

            $('#customOptionInput').on('keypress', function(e) {
                if (e.which === 13) { // Enter key
                    console.log('Enter key pressed in modal input');
                    e.preventDefault();
                    saveCustomOption();
                }
            });

            $('#saveModalBtn').on('click', function() {
                console.log('Save button clicked');
                saveCustomOption();
            });
            
            console.log('Modal event listeners initialized successfully');
            // ============ END MODAL EVENT LISTENERS ============

            // ============ KATEGORI PEMBELIAN REMARK ============
            function syncKategoriPembelianRemark() {
                const val = String($('#Kategori_Pembelian').val() || '').trim();
                if (val === 'Online') {
                    $('#remarkKategoriPembelianOnline').removeClass('hidden');
                    $('#linkPembelianGroup').removeClass('hidden');
                } else {
                    $('#remarkKategoriPembelianOnline').addClass('hidden');
                    $('#linkPembelianGroup').addClass('hidden');
                    // Clear value supaya tidak ikut tersubmit saat Offline
                    $('#Link_Pembelian').val('');
                }
            }
            $('#Kategori_Pembelian').on('change', syncKategoriPembelianRemark);
            syncKategoriPembelianRemark();
            // ============ END KATEGORI PEMBELIAN REMARK =======
            
            // ============ RIWAYAT BARANG LOGIC ============
            let riwayatList = [];
            
            // Parse existing riwayat jika ada
            const existingRiwayat = $('#Riwayat_Barang').val().trim();
            if (existingRiwayat) {
                try {
                    riwayatList = JSON.parse(existingRiwayat);
                } catch (e) {
                    riwayatList = [];
                }
            }
            
            // Fungsi render riwayat list (tanpa kategori, menampilkan tanggal)
            function renderRiwayatList() {
                const container = $('#riwayatContainer');
                container.empty();

                if (riwayatList.length === 0) {
                    container.html('<p class="text-sm text-gray-500 text-center py-2">Belum ada entry riwayat</p>');
                    return;
                }

                riwayatList.forEach((item, index) => {
                    const entryHtml = `
                        <div class="bg-white border border-gray-300 rounded-lg p-3 flex justify-between items-start">
                            <div class="flex-1">
                                <p class="text-sm font-semibold text-gray-800">${item.nama || '-'}</p>
                                <div class="text-xs text-gray-600 mt-1 space-y-0.5">
                                    ${item.jabatan ? `<p><strong>Jabatan:</strong> ${item.jabatan}</p>` : ''}
                                    ${item.empleId ? `<p><strong>Employee ID:</strong> ${item.empleId}</p>` : ''}
                                    ${item.lokasi ? `<p><strong>Lokasi:</strong> ${item.lokasi}</p>` : ''}
                                    ${item.tgl_serah_terima ? `<p><strong>Tgl Serah Terima:</strong> ${item.tgl_serah_terima}</p>` : ''}
                                    ${item.tgl_pengembalian ? `<p><strong>Tgl Kembali:</strong> ${item.tgl_pengembalian}</p>` : ''}
                                    ${item.catatan ? `<p><strong>Catatan:</strong> ${item.catatan}</p>` : ''}
                                </div>
                            </div>
                            <button type="button" class="delete-riwayat-btn ml-2 p-2 text-red-600 hover:bg-red-50 rounded transition-all" data-index="${index}" title="Hapus">
                                <i class="fas fa-trash text-xs"></i>
                            </button>
                        </div>
                    `;
                    container.append(entryHtml);
                });

                // Update hidden textarea dengan JSON
                $('#Riwayat_Barang').val(JSON.stringify(riwayatList));
            }
            
            // Initialize Select2 untuk field-field riwayat
            $('.riwayat-nama-select').select2({
                data: <?php echo $userPerangkatJson; ?>.map(item => ({id: item, text: item})),
                placeholder: '-- Pilih Nama --',
                allowClear: true,
                tags: true
            });
            
            $('.riwayat-jabatan-select').select2({
                data: <?php echo $jabatanJson; ?>.map(item => ({id: item, text: item})),
                placeholder: '-- Pilih Jabatan --',
                allowClear: true,
                tags: true
            });
            
            $('.riwayat-emplid-select').select2({
                data: <?php echo $idKaryawanJson; ?>.map(item => ({id: item, text: item})),
                placeholder: '-- Pilih Employee ID --',
                allowClear: true,
                tags: true
            });
            
            $('.riwayat-lokasi-select').select2({
                data: <?php echo $lokasiJson; ?>.map(item => ({id: item, text: item})),
                placeholder: '-- Pilih Lokasi --',
                allowClear: true,
                tags: true
            });
            
            // Event: Tombol Tambah Nama
            $('#addNameBtn').on('click', function() {
                showRiwayatAddModal('Nama', 'riwayatNama', 'nama');
            });

            // Event: Tombol Isi Nama Tangan Pertama (autofill dari main form)
            $('#fillTanganPertamaBtn').on('click', function() {
                const userPerangkat = $('#User_Perangkat').val() || '';
                const jabatan = $('#Jabatan').val() || '';
                const emplId = $('#Id_Karyawan').val() || '';
                const lokasi = $('#Lokasi').val() || '';
                const today = new Date().toISOString().slice(0,10);

                if (userPerangkat) {
                    $('#riwayatNama').val(userPerangkat).trigger('change');
                }
                if (jabatan) {
                    $('#riwayatJabatan').val(jabatan).trigger('change');
                }
                if (emplId) {
                    $('#riwayatEmplId').val(emplId).trigger('change');
                }
                if (lokasi) {
                    $('#riwayatLokasi').val(lokasi).trigger('change');
                }
                // Set tanggal serah ke hari ini jika kosong
                if (!$('#riwayatTglSerah').val()) {
                    $('#riwayatTglSerah').val(today);
                }

                // Focus on Catatan for quick entry
                $('#riwayatCatatan').focus();
            });
            
            // Event: Tombol Tambah Jabatan
            $('#addJabatanBtn').on('click', function() {
                showRiwayatAddModal('Jabatan', 'riwayatJabatan', 'jabatan');
            });
            
            // Event: Tombol Tambah Employee ID
            $('#addEmplIdBtn').on('click', function() {
                showRiwayatAddModal('Employee ID', 'riwayatEmplId', 'emplId');
            });
            
            // Event: Tombol Tambah Lokasi
            $('#addLokasiBtn').on('click', function() {
                showRiwayatAddModal('Lokasi', 'riwayatLokasi', 'lokasi');
            });
            
            // Fungsi tambah data baru untuk riwayat
            function showRiwayatAddModal(fieldName, selectId, dataType) {
                Swal.fire({
                    title: 'Tambah ' + fieldName + ' Baru',
                    input: 'text',
                    inputPlaceholder: 'Masukkan ' + fieldName + ' baru...',
                    inputAttributes: {
                        autocapitalize: 'on',
                        autocomplete: 'off'
                    },
                    icon: 'info',
                    showCancelButton: true,
                    confirmButtonText: 'Tambah',
                    cancelButtonText: 'Batal',
                    confirmButtonColor: '#3b82f6',
                    cancelButtonColor: '#6b7280',
                    allowOutsideClick: false
                }).then((result) => {
                    if (result.isConfirmed && result.value) {
                        const newValue = result.value.trim();
                        if (newValue !== '') {
                            const selectElement = $('#' + selectId);
                            
                            // Cek jika opsi sudah ada
                            let optionExists = false;
                            const currentData = selectElement.select2('data');
                            currentData.forEach(item => {
                                if (item.text.toLowerCase() === newValue.toLowerCase()) {
                                    optionExists = true;
                                }
                            });
                            
                            if (optionExists) {
                                Swal.fire({
                                    icon: 'warning',
                                    title: 'Duplikat!',
                                    text: fieldName + ' "' + newValue + '" sudah ada di daftar.',
                                    timer: 2000,
                                    showConfirmButton: false
                                });
                            } else {
                                // Ambil data current dari select2
                                let currentOptions = selectElement.select2('data');
                                
                                // Tambah opsi baru
                                const newOption = {id: newValue, text: newValue};
                                let options = currentOptions.slice();
                                options.push(newOption);
                                
                                // Update select2 data
                                selectElement.select2({
                                    data: options
                                });
                                
                                // Set value ke option baru
                                selectElement.val(newValue).trigger('change');
                                
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Berhasil!',
                                    text: fieldName + ' "' + newValue + '" berhasil ditambahkan.',
                                    timer: 2000,
                                    showConfirmButton: false
                                });
                            }
                        }
                    }
                });
            }
            
            // Event: Tombol Tambah Entry (tanpa kategori, menyertakan tanggal)
            $('#addRiwayatBtn').on('click', function() {
                const nama = $('#riwayatNama').val();

                if (!nama) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Nama Kosong!',
                        text: 'Silakan masukkan nama',
                        timer: 2000,
                        showConfirmButton: false
                    });
                    return;
                }

                const newEntry = {
                    nama: nama,
                    jabatan: $('#riwayatJabatan').val() || '',
                    empleId: $('#riwayatEmplId').val() || '',
                    lokasi: $('#riwayatLokasi').val() || '',
                    tgl_serah_terima: $('#riwayatTglSerah').val() || '',
                    tgl_pengembalian: $('#riwayatTglKembali').val() || '',
                    catatan: $('#riwayatCatatan').val().trim()
                };

                riwayatList.push(newEntry);
                renderRiwayatList();

                // Clear form
                $('#riwayatNama').val('').trigger('change');
                $('#riwayatJabatan').val('').trigger('change');
                $('#riwayatEmplId').val('').trigger('change');
                $('#riwayatLokasi').val('').trigger('change');
                $('#riwayatTglSerah').val('');
                $('#riwayatTglKembali').val('');
                $('#riwayatCatatan').val('');

                Swal.fire({
                    icon: 'success',
                    title: 'Entry Ditambahkan!',
                    text: 'Riwayat barang berhasil ditambahkan',
                    timer: 1500,
                    showConfirmButton: false
                });
            });
            
            // Event: Delete entry
            $(document).on('click', '.delete-riwayat-btn', function() {
                const index = $(this).data('index');
                riwayatList.splice(index, 1);
                renderRiwayatList();
                
                Swal.fire({
                    icon: 'success',
                    title: 'Entry Dihapus!',
                    text: 'Riwayat barang berhasil dihapus',
                    timer: 1500,
                    showConfirmButton: false
                });
            });
            
            // Initial render
            renderRiwayatList();
            
            // ============ END RIWAYAT LOGIC ============
            
            // ============ FORM SUBMISSION VALIDATION ============
            $('#assetForm').on('submit', function(e) {
                // DEBUG: Lihat apa yang akan di-submit
                console.log('Form Submit - Riwayat_Barang Value:', $('#Riwayat_Barang').val());
                console.log('riwayatList Array:', riwayatList);
                
                // Validasi Serial Number
                const feedback = $('#serialNumberFeedback').text();
                if (feedback.includes('sudah terdaftar')) {
                    e.preventDefault();
                    Swal.fire({
                        title: 'Gagal Validasi!',
                        text: 'Serial Number sudah terdaftar, silakan gunakan yang lain.',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                    $('#Serial_Number').focus();
                    return false;
                }

                // Validasi Riwayat_Barang (minimal harus ada 1 entry)
                if (riwayatList.length === 0) {
                    e.preventDefault();
                    Swal.fire({
                        title: 'Gagal Validasi!',
                        text: 'Minimal harus ada 1 entry di Riwayat Barang sebelum simpan.',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                    document.getElementById('riwayatContainer').scrollIntoView({ behavior: 'smooth' });
                    return false;
                }

                // Validasi semua photo wajib diupload
                const photoFields = ['Photo_Barang', 'Photo_Depan', 'Photo_Belakang', 'Photo_SN'];
                const missingPhotos = [];
                
                photoFields.forEach(function(fieldId) {
                    const fileInput = $('#' + fieldId)[0];
                    if (!fileInput.files || fileInput.files.length === 0) {
                        const photoLabel = fieldId.replace('Photo_', 'Foto ');
                        missingPhotos.push(photoLabel);
                    }
                });

                if (missingPhotos.length > 0) {
                    e.preventDefault();
                    Swal.fire({
                        title: 'Gagal Validasi!',
                        text: 'Foto berikut wajib diunggah: ' + missingPhotos.join(', '),
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                    return false;
                }

                // Update hidden textarea sebelum submit (pastikan ada data terbaru)
                $('#Riwayat_Barang').val(JSON.stringify(riwayatList));
                console.log('Form Submit - Final Riwayat_Barang:', $('#Riwayat_Barang').val());
                
                // ALLOW FORM TO SUBMIT NORMALLY - no preventDefault, no manual submit
                // Form will submit naturally to PHP backend
                return true;
            });
            // ============ END FORM SUBMISSION VALIDATION ============
            
            // Tambah opsi "Tambah Baru" di awal
            const selectElement = $('.select-user-perangkat');
            
            selectElement.prepend('<option value="__add_new__" data-add-new="true"><i class="fas fa-plus"></i> Tambah Data User Baru</option>');
            
            selectElement.select2({
                tags: true,
                tokenSeparators: [','],
                allowClear: false,
                placeholder: '-- Pilih atau Ketik Nama User --',
                minimumResultsForSearch: 0,
                width: '100%',
                dropdownParent: $('body'),
                escapeMarkup: function(markup) { return markup; },
                matcher: function(params, data) {
                    // Allow case-insensitive search
                    if (!params.term) {
                        return data;
                    }
                    if ($(data.element).text().toUpperCase().indexOf(params.term.toUpperCase()) > -1) {
                        return data;
                    }
                    return null;
                },
                templateSelection: function(data) {
                    if (data.id === '__add_new__') {
                        return '<i class="fas fa-plus text-blue-500 mr-2"></i>Tambah Data User Baru';
                    }
                    return data.text;
                },
                templateResult: function(data) {
                    if (data.id === '__add_new__') {
                        return $('<span><i class="fas fa-plus text-blue-500 mr-2"></i>Tambah Data User Baru</span>');
                    }
                    return data.text;
                }
            });
            
            // Handle "Tambah Baru" option click
            selectElement.on('select2:select', function(e) {
                if (e.params.data.id === '__add_new__') {
                    showAddUserModal();
                    // Reset dropdown
                    selectElement.val('').trigger('change');
                }
            });
        });

        // Modal untuk tambah user baru
        function showAddUserModal() {
            const { value: userInput } = Swal.fire({
                title: 'Tambah User Perangkat Baru',
                input: 'text',
                inputPlaceholder: 'Masukkan nama user perangkat...',
                inputAttributes: {
                    autocapitalize: 'on',
                    autocomplete: 'off'
                },
                icon: 'info',
                showCancelButton: true,
                confirmButtonText: 'Tambah',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#3b82f6',
                cancelButtonColor: '#6b7280',
                allowOutsideClick: false,
                didOpen: function() {
                    // Focus ke input setelah modal terbuka
                    const input = Swal.getInput();
                    if (input) {
                        input.focus();
                    }
                }
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    const newUserName = result.value.trim();
                    if (newUserName !== '') {
                        // Tambah opsi baru ke dropdown
                        const selectElement = $('.select-user-perangkat');
                        
                        // Cek jika opsi sudah ada
                        let optionExists = false;
                        selectElement.find('option').each(function() {
                            if ($(this).val() === newUserName) {
                                optionExists = true;
                                return false;
                            }
                        });
                        
                        if (optionExists) {
                            Swal.fire({
                                icon: 'warning',
                                title: 'Duplikat!',
                                text: 'User "' + newUserName + '" sudah ada di daftar.',
                                timer: 2000,
                                showConfirmButton: false
                            });
                        } else {
                            // Tambah opsi sebelum "Tambah Baru"
                            const addNewOption = selectElement.find('option[data-add-new="true"]');
                            $('<option value="' + newUserName + '" selected>' + newUserName + '</option>').insertBefore(addNewOption);
                            selectElement.val(newUserName).trigger('change');
                            
                            Swal.fire({
                                icon: 'success',
                                title: 'Berhasil!',
                                text: 'User "' + newUserName + '" berhasil ditambahkan.',
                                timer: 2000,
                                showConfirmButton: false
                            });
                        }
                    }
                }
            });
        }
        });  // Close $(document).ready(function() {
    </script>
</body>
</html>