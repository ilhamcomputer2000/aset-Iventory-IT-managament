<?php

// Debug helper for hosting blank page (enable with ?debug=1)
// NOTE: action form memakai PHP_SELF (tanpa query string), jadi debug juga bisa dikirim via POST.
$__build = '2026-03-08-TR2';
$__debug = ((string)($_GET['debug'] ?? ($_POST['debug'] ?? '')) === '1');
if ($__debug) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
    // Marker to confirm hosting is running this build
    header('X-Debug-Update-Build: ' . $__build);
} else {
    ini_set('display_errors', '0');
}
ini_set('log_errors', '1');
// Log to project temp folder if possible (../temp from /admin). If not writable (hosting), fallback to system temp.
$__tempDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'temp';
if (!is_dir($__tempDir)) {
    @mkdir($__tempDir, 0755, true);
}
if (!is_dir($__tempDir) || !is_writable($__tempDir)) {
    $fallback = rtrim((string)sys_get_temp_dir(), "\\/") . DIRECTORY_SEPARATOR . 'crud';
    if (!is_dir($fallback)) {
        @mkdir($fallback, 0755, true);
    }
    if (is_dir($fallback) && is_writable($fallback)) {
        $__tempDir = $fallback;
    }
}
$__errorLogFile = $__tempDir . DIRECTORY_SEPARATOR . 'php_errors.log';
@ini_set('error_log', $__errorLogFile);
// Ensure the log file exists
if (!is_file($__errorLogFile)) {
    @file_put_contents($__errorLogFile, '');
}

// Debug-only diagnostics page (hosting troubleshooting)
if ($__debug && (string)($_GET['diag'] ?? '') === '1') {
    header('Content-Type: text/html; charset=UTF-8');
    $diag = [
        'build' => $__build,
        'php_version' => PHP_VERSION,
        'sapi' => PHP_SAPI,
        'server_software' => (string)($_SERVER['SERVER_SOFTWARE'] ?? ''),
        'error_log_ini' => (string)ini_get('error_log'),
        'log_file_in_use' => $__errorLogFile,
        'temp_dir' => $__tempDir,
        'temp_dir_writable' => (is_dir($__tempDir) && is_writable($__tempDir)) ? 'yes' : 'no',
        'ext_openssl' => extension_loaded('openssl') ? 'yes' : 'no',
        'fn_openssl_encrypt' => function_exists('openssl_encrypt') ? 'yes' : 'no',
        'fn_openssl_decrypt' => function_exists('openssl_decrypt') ? 'yes' : 'no',
        'fn_random_bytes' => function_exists('random_bytes') ? 'yes' : 'no',
        'post_max_size' => (string)ini_get('post_max_size'),
        'upload_max_filesize' => (string)ini_get('upload_max_filesize'),
        'memory_limit' => (string)ini_get('memory_limit'),
        'max_execution_time' => (string)ini_get('max_execution_time'),
    ];

    // Optional DB diagnostics (schema + sql_mode). Only show when logged in as super_admin.
    $dbDiag = [
        'authorized' => 'no',
        'note' => 'Login sebagai super_admin untuk melihat detail DB (sql_mode, kolom Tahun_Rilis, triggers).',
    ];
    if (function_exists('session_status') && session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    $role = (string)($_SESSION['role'] ?? '');
    if (isset($_SESSION['user_id']) && $role === 'super_admin') {
        $dbDiag['authorized'] = 'yes';
        $dbDiag['note'] = '';
        $connFile = __DIR__ . '/../koneksi.php';
        if (is_file($connFile)) {
            include_once $connFile;
        }

        if (isset($kon) && $kon instanceof mysqli) {
            $meta = [];
            $metaRes = @mysqli_query($kon, "SELECT DATABASE() AS db_name, VERSION() AS db_version, @@sql_mode AS sql_mode");
            if ($metaRes) {
                $meta = (array)mysqli_fetch_assoc($metaRes);
            }
            $dbDiag['meta'] = $meta;

            $tahunRilisCol = null;
            $colRes = @mysqli_query($kon, "SHOW COLUMNS FROM peserta LIKE 'Tahun_Rilis'");
            if ($colRes) {
                $tahunRilisCol = mysqli_fetch_assoc($colRes);
            }
            $dbDiag['peserta_Tahun_Rilis'] = $tahunRilisCol;

            $triggers = [];
            $trRes = @mysqli_query($kon, "SHOW TRIGGERS LIKE 'peserta'");
            if ($trRes) {
                while ($r = mysqli_fetch_assoc($trRes)) {
                    $triggers[] = [
                        'Trigger' => (string)($r['Trigger'] ?? ''),
                        'Timing' => (string)($r['Timing'] ?? ''),
                        'Event' => (string)($r['Event'] ?? ''),
                    ];
                }
            }
            $dbDiag['peserta_triggers'] = $triggers;
        } else {
            $dbDiag['db_connect'] = 'failed_or_unavailable';
        }
    }
    $diag['db'] = $dbDiag;

    echo "<!DOCTYPE html><html lang='id'><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width, initial-scale=1.0'><title>Update Diagnostics</title></head><body>";
    echo "<h3>Diagnostics update.php</h3>";
    echo "<pre style='white-space:pre-wrap;background:#f7f7f7;padding:12px;border:1px solid #ddd;'>" . htmlspecialchars(print_r($diag, true), ENT_QUOTES, 'UTF-8') . "</pre>";
    echo "<p><a href='index.php'>Kembali</a></p>";
    echo "</body></html>";
    exit;
}

function __update_write_log($line, $filePath) {
    $text = '[' . date('Y-m-d H:i:s') . '] ' . (string)$line . "\n";
    $ok = false;
    if (is_string($filePath) && $filePath !== '') {
        $ok = (@file_put_contents($filePath, $text, FILE_APPEND) !== false);
    }
    // Also send to PHP error_log as fallback
    @error_log((string)$line);
    return $ok;
}

// Buffer output so we can reliably show a minimal result page (hosting-safe)
ob_start();

// Exception handler: uncaught exceptions (mis. mysqli_sql_exception) bisa membuat HTTP 500 tanpa tercatat oleh error_get_last().
set_exception_handler(function ($e) use ($__debug, $__tempDir, $__errorLogFile) {
    $type = is_object($e) ? get_class($e) : 'Exception';
    $message = '';
    $file = '';
    $line = '';
    $trace = '';
    if (is_object($e)) {
        $message = (string)($e->getMessage() ?? '');
        $file = (string)($e->getFile() ?? '');
        $line = (string)($e->getLine() ?? '');
        $trace = (string)($e->getTraceAsString() ?? '');
    }

    $log = 'UPDATE.PHP EXCEPTION [' . $type . ']: ' . $message . ' in ' . $file . ':' . $line;
    if ($trace !== '') {
        $log .= "\n" . $trace;
    }

    __update_write_log($log, $__errorLogFile);

    if (ob_get_length()) {
        ob_clean();
    }

    header('Content-Type: text/html; charset=UTF-8');
    if (!$__debug) {
        echo "<!DOCTYPE html><html lang='id'><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width, initial-scale=1.0'><title>Error</title><script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script></head><body>";
        echo "<script>(function(){var url='index.php';if(typeof Swal==='undefined'){location.href=url;return;}Swal.fire({icon:'error',title:'Terjadi Error',text:'Terjadi kesalahan saat memproses permintaan. Silakan coba lagi.',confirmButtonText:'OK'}).then(function(){location.href=url;});})();</script>";
        echo "</body></html>";
        exit;
    }

    echo "<!DOCTYPE html><html lang='id'><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width, initial-scale=1.0'><title>Exception</title></head><body>";
    echo "<h3>Terjadi exception</h3>";
    echo "<pre style='white-space:pre-wrap;background:#f7f7f7;padding:12px;border:1px solid #ddd;'>" . htmlspecialchars($log, ENT_QUOTES, 'UTF-8') . "</pre>";
    echo "<p>Log file: <code>" . htmlspecialchars((string)$__errorLogFile, ENT_QUOTES, 'UTF-8') . "</code></p>";
    echo "</body></html>";
    exit;
});

// Shutdown handler: log fatal errors that often show as blank page on hosting.
register_shutdown_function(function () use ($__debug) {
    $err = error_get_last();
    if (!$err || !is_array($err)) {
        return;
    }

    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array((int)($err['type'] ?? 0), $fatalTypes, true)) {
        return;
    }

    $msg = 'UPDATE.PHP FATAL: ' . (string)($err['message'] ?? '') . ' in ' . (string)($err['file'] ?? '') . ':' . (string)($err['line'] ?? '');
    // Use our logger if available; fall back to default error_log
    if (function_exists('__update_write_log')) {
        __update_write_log($msg, ini_get('error_log'));
    } else {
        error_log($msg);
    }

    if (!$__debug) {
        return;
    }

    if (ob_get_length()) {
        ob_clean();
    }
    header('Content-Type: text/html; charset=UTF-8');
    echo "<!DOCTYPE html><html lang='id'><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width, initial-scale=1.0'><title>Fatal Error</title></head><body>";
    echo "<h3>Terjadi error fatal saat submit</h3>";
    echo "<pre style='white-space:pre-wrap;background:#f7f7f7;padding:12px;border:1px solid #ddd;'>" . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . "</pre>";
    echo "<p>Cek file log: <code>temp/php_errors.log</code></p>";
    echo "</body></html>";
});

if ($__debug) {
    error_log('UPDATE.PHP build=' . $__build . ' method=' . ($_SERVER['REQUEST_METHOD'] ?? '') . ' id_peserta=' . (string)($_GET['id_peserta'] ?? $_POST['id_peserta'] ?? '') . ' t=' . (string)($_GET['t'] ?? $_POST['t'] ?? ''));
}

// Headers no-cache
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Timezone
date_default_timezone_set('Asia/Jakarta');

// Mulai session
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Guard: hanya super_admin boleh akses halaman update
$user_role = $_SESSION['role'] ?? 'user';
if ($user_role !== 'super_admin') {
    header("Location: ../user/view.php");
    exit();
}



// Include file koneksi
include "../koneksi.php";

// Token helper (untuk menyembunyikan id_peserta di URL: update.php?t=...)
require_once dirname(__DIR__) . '/token_id.php';

// Fungsi untuk mencegah inputan karakter yang tidak sesuai
function input($data) {
    if ($data === null) {
        $data = '';
    }
    if (is_array($data)) {
        $data = '';
    }
    $data = (string)$data;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Terima id_peserta langsung ATAU token terenkripsi lewat parameter t
$__token_param = input($_GET['t'] ?? $_POST['t'] ?? '');
$__token_payload = null;
if ($__token_param !== '') {
    $__token_error = '';
    $__token_payload = token_parse_id_payload($__token_param, $__token_error);
    if (!$__token_payload) {
        // Jika ini POST (atau GET) yang sudah membawa id_peserta, jangan blokir update hanya karena token expired.
        // Token di sini fungsinya untuk menyembunyikan ID di URL, bukan sebagai autentikasi.
        if (isset($_GET['id_peserta']) || isset($_POST['id_peserta'])) {
            if ($__debug) {
                error_log('UPDATE.PHP token invalid/expired but id_peserta provided; continuing without token. err=' . (string)$__token_error);
            }
            $__token_param = '';
            $__token_payload = null;
        } else {
        echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script></head><body><script>
        Swal.fire({
            icon: 'error',
            title: 'Link Tidak Valid',
            text: " . json_encode((string)$__token_error, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) . ",
            confirmButtonText: 'OK'
        }).then(function() {
            window.location.href = 'index.php';
        });
        </script></body></html>";
        exit();
        }
    }
}

// Cek apakah ada parameter id_peserta di URL/POST atau token t
if (!isset($_GET['id_peserta']) && !isset($_POST['id_peserta']) && $__token_payload === null) {
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script></head><body><script>
    Swal.fire({
        icon: 'error',
        title: 'Error',
        text: 'ID peserta tidak diberikan.',
        confirmButtonText: 'OK'
    }).then(function() {
        window.location.href = 'index.php';
    });
    </script></body></html>";
    exit();
}

// Ambil id_peserta dari GET/POST atau dari token
$id_peserta = input($_GET['id_peserta'] ?? $_POST['id_peserta'] ?? '');
if ($__token_payload !== null) {
    $idFromToken = (int)($__token_payload['id'] ?? 0);
    $idFromParam = (int)$id_peserta;

    // Jika keduanya ada tapi tidak sama, anggap tampering
    if ($idFromParam > 0 && $idFromParam !== $idFromToken) {
        echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script></head><body><script>
        Swal.fire({
            icon: 'error',
            title: 'Link Tidak Konsisten',
            text: 'Parameter ID dan token tidak cocok.',
            confirmButtonText: 'OK'
        }).then(function() {
            window.location.href = 'index.php';
        });
        </script></body></html>";
        exit();
    }
    $id_peserta = (string)$idFromToken;
}

$id_peserta_int = (int)$id_peserta;
if ($id_peserta_int <= 0) {
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script></head><body><script>
    Swal.fire({
        icon: 'error',
        title: 'Error',
        text: 'ID peserta tidak valid.',
        confirmButtonText: 'OK'
    }).then(function() {
        window.location.href = 'index.php';
    });
    </script></body></html>";
    exit();
}
$id_peserta = (string)$id_peserta_int;

// Query untuk mengambil data peserta
$sql = "SELECT * FROM peserta WHERE id_peserta = $id_peserta_int";
$result = mysqli_query($kon, $sql);
$old_data = mysqli_fetch_assoc($result);

if (!$old_data) {
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script></head><body><script>
    Swal.fire({
        icon: 'error',
        title: 'Data Tidak Ditemukan',
        text: 'Data yang Anda cari tidak ditemukan.',
        confirmButtonText: 'OK'
    }).then(function() {
        window.location.href = 'index.php';
    });
    </script></body></html>";
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../img/logo%20ckt%20fix.png">
    <title>Update Data Asset IT - Modern & Interactive</title>
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
        // Inisialisasi variable untuk message
        $swal_message = null;
        $swal_icon = null;
        $swal_title = null;
        $redirect_after = false;
        
        // Inisialisasi variabel dari data yang diambil dari database
        $Nomor_Aset = $old_data['Nomor_Aset'] ?? '';
        $Nama_Barang = $old_data['Nama_Barang'] ?? '';
        $Merek = $old_data['Merek'] ?? '';
        $Type = $old_data['Type'] ?? '';
        $Spesifikasi = $old_data['Spesifikasi'] ?? '';
        $Kelengkapan_Barang = $old_data['Kelengkapan_Barang'] ?? '';
        $Kondisi_Barang = $old_data['Kondisi_Barang'] ?? '';
        $Riwayat_Barang = $old_data['Riwayat_Barang'] ?? '';
        // Normalisasi untuk kasus data lama yang tersimpan dengan escaping tambahan
        // (mis. {\"key\":...} atau HTML entities) sehingga JSON.parse di JS tidak gagal.
        if ($Riwayat_Barang !== '') {
            $decodedRiwayat = json_decode($Riwayat_Barang, true);
            if (!is_array($decodedRiwayat)) {
                $riwayatTry = stripslashes($Riwayat_Barang);
                $decodedTry = json_decode($riwayatTry, true);
                if (is_array($decodedTry)) {
                    $Riwayat_Barang = $riwayatTry;
                } else {
                    $riwayatTry2 = html_entity_decode($Riwayat_Barang, ENT_QUOTES, 'UTF-8');
                    $decodedTry2 = json_decode($riwayatTry2, true);
                    if (is_array($decodedTry2)) {
                        $Riwayat_Barang = $riwayatTry2;
                    }
                }
            }
        }
        $User_Perangkat = $old_data['User_Perangkat'] ?? '';
        $Status_Barang = $old_data['Status_Barang'] ?? '';
        $Status_LOP = $old_data['Status_LOP'] ?? '';
        $Status_Kelayakan_Barang = $old_data['Status_Kelayakan_Barang'] ?? '';
        $Harga_Barang = $old_data['Harga_Barang'] ?? '';
        $Tahun_Rilis = $old_data['Tahun_Rilis'] ?? '';
        $Waktu_Pembelian = $old_data['Waktu_Pembelian'] ?? '';
        $Nama_Toko_Pembelian = $old_data['Nama_Toko_Pembelian'] ?? '';
        $Kategori_Pembelian = $old_data['Kategori_Pembelian'] ?? '';
        $Link_Pembelian = $old_data['Link_Pembelian'] ?? '';
        $Serial_Number = $old_data['Serial_Number'] ?? '';
        $Jenis_Barang = $old_data['Jenis_Barang'] ?? '';
        $Lokasi = $old_data['Lokasi'] ?? '';
        $Id_Karyawan = $old_data['Id_Karyawan'] ?? '';
        $Jabatan = $old_data['Jabatan'] ?? '';
        $Photo_Barang = $old_data['Photo_Barang'] ?? '';
        $Photo_Depan = $old_data['Photo_Depan'] ?? '';
        $Photo_Belakang = $old_data['Photo_Belakang'] ?? '';
        $Photo_SN = $old_data['Photo_SN'] ?? '';
        $Photo_Invoice = $old_data['Photo_Invoice'] ?? '';
        
        $target_dir = "../uploads/"; // Direktori untuk menyimpan foto
        $uploads_base_dir = realpath(__DIR__ . '/../uploads');

        // Helper: cek apakah path relative upload benar-benar ada di disk (hindari 404 di browser)
        $uploadFileExists = function(string $relativePath) use ($uploads_base_dir): bool {
            if ($uploads_base_dir === false) {
                return false;
            }
            $relativePath = trim((string)$relativePath);
            if ($relativePath === '') {
                return false;
            }

            $rel = str_replace('\\', '/', $relativePath);
            $firstChar = $rel !== '' ? $rel[0] : '';
            if ($firstChar === '/' || $firstChar === '\\') {
                return false;
            }
            if (strpos($rel, '..') !== false) {
                return false;
            }
            if (preg_match('/^[a-zA-Z]:/', $rel)) {
                return false;
            }

            $full = $uploads_base_dir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
            return is_file($full);
        };

        // Function to get existing options from database
        function getOptions($kon, $field) {
            $options = [];
            $query = "SELECT DISTINCT $field FROM peserta WHERE $field IS NOT NULL AND $field != '' ORDER BY $field ASC";
            $result = mysqli_query($kon, $query);
            if ($result) {
                while ($row = mysqli_fetch_assoc($result)) {
                    $options[] = $row[$field];
                }
            }
            return $options;
        }

        // Get options dari database
        $namaBarangOptions = getOptions($kon, 'Nama_Barang');
        $merekOptions = getOptions($kon, 'Merek');
        $typeOptions = getOptions($kon, 'Type');
        $lokasiOptions = getOptions($kon, 'Lokasi');
        $idKaryawanOptions = getOptions($kon, 'Id_Karyawan');
        $jabatanOptions = getOptions($kon, 'Jabatan');
        $vendorOptions = getOptions($kon, 'Nama_Toko_Pembelian');
        
        // Untuk User_Perangkat
        $query_user_perangkat = "SELECT DISTINCT User_Perangkat FROM peserta WHERE User_Perangkat IS NOT NULL AND User_Perangkat != '' ORDER BY User_Perangkat ASC";
        $result_user_perangkat = mysqli_query($kon, $query_user_perangkat);
        $user_perangkat_list = [];
        if ($result_user_perangkat) {
            while ($row = mysqli_fetch_assoc($result_user_perangkat)) {
                if (!empty($row['User_Perangkat'])) {
                    $user_perangkat_list[] = $row['User_Perangkat'];
                }
            }
        }
        
        // Convert arrays ke JSON untuk JavaScript (safe embed inside <script>)
        $jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;

        $jabatanJson = json_encode($jabatanOptions, $jsonFlags);
        if ($jabatanJson === false) {
            $jabatanJson = '[]';
        }
        $idKaryawanJson = json_encode($idKaryawanOptions, $jsonFlags);
        if ($idKaryawanJson === false) {
            $idKaryawanJson = '[]';
        }
        $lokasiJson = json_encode($lokasiOptions, $jsonFlags);
        if ($lokasiJson === false) {
            $lokasiJson = '[]';
        }
        $userPerangkatJson = json_encode($user_perangkat_list, $jsonFlags);
        if ($userPerangkatJson === false) {
            $userPerangkatJson = '[]';
        }
        ?>

        <!-- Header Section -->
        <div class="form-container mb-6 sm:mb-8 p-4 sm:p-6 animate-fade-in-up">
            <div class="text-center">
                <div class="flex justify-center mb-4">
                    <div class="w-14 sm:w-16 h-14 sm:h-16 bg-gradient-to-r from-orange-500 to-blue-600 rounded-2xl flex items-center justify-center hover:scale-105 transition-transform cursor-pointer">
                        <i class="fas fa-edit text-white text-xl sm:text-2xl"></i>
                    </div>
                </div>
                <h1 class="text-2xl sm:text-3xl bg-gradient-to-r from-orange-600 to-blue-600 bg-clip-text text-transparent mb-2">
                    Update Data Asset IT
                </h1>
                <p class="text-sm sm:text-base text-gray-600">Perbarui data asset IT dengan mudah dan cepat</p>
            </div>
        </div>

        <?php
        // Handle POST request
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            if ($__debug) {
                $contentLength = (string)($_SERVER['CONTENT_LENGTH'] ?? '');
                error_log('UPDATE.PHP POST meta content_length=' . $contentLength
                    . ' post_max_size=' . (string)ini_get('post_max_size')
                    . ' upload_max_filesize=' . (string)ini_get('upload_max_filesize')
                    . ' max_file_uploads=' . (string)ini_get('max_file_uploads')
                    . ' memory_limit=' . (string)ini_get('memory_limit')
                    . ' max_input_vars=' . (string)ini_get('max_input_vars')
                );
                error_log('UPDATE.PHP POST counts _POST=' . (string)count($_POST) . ' _FILES=' . (string)count($_FILES));
                foreach ($_FILES as $k => $f) {
                    if (!is_array($f)) {
                        continue;
                    }
                    $err = isset($f['error']) ? $f['error'] : null;
                    $size = isset($f['size']) ? $f['size'] : null;
                    $name = isset($f['name']) ? $f['name'] : null;
                    error_log('UPDATE.PHP FILE ' . (string)$k . ' name=' . (string)$name . ' size=' . (string)$size . ' error=' . (string)$err);
                }
            }

            // Jika POST kosong tapi ada CONTENT_LENGTH, biasanya kena limit post_max_size
            if (empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
                $swal_icon = 'error';
                $swal_title = 'Gagal Submit';
                $swal_message = 'Data form tidak terbaca oleh server (POST kosong). Biasanya karena ukuran upload melebihi limit hosting (post_max_size / upload_max_filesize). Coba submit tanpa upload foto dulu, atau kecilkan ukuran file.';
            }

            $normalizeForCompare = function(string $value): string {
                // Trim including non-breaking spaces (common from copy/paste)
                $value = preg_replace('/^[\s\x{00A0}]+|[\s\x{00A0}]+$/u', '', $value);
                return (string)$value;
            };

            $Nomor_Aset_post = isset($_POST["Nomor_Aset"]) ? trim((string)$_POST["Nomor_Aset"]) : '';
            // Safety: kalau dikosongkan, jangan hapus nilai lama
            $Nomor_Aset = ($Nomor_Aset_post !== '') ? $Nomor_Aset_post : ($old_data['Nomor_Aset'] ?? '');
            $Nomor_Aset_sql = mysqli_real_escape_string($kon, (string)$Nomor_Aset);
            $id_peserta_sql = mysqli_real_escape_string($kon, (string)$id_peserta);
            $id_peserta_int = (int)$id_peserta;
            $oldNomorAsetTrim = $normalizeForCompare((string)($old_data['Nomor_Aset'] ?? ''));

            $Nama_Barang = input($_POST["Nama_Barang"] ?? '');
            $Merek = input($_POST["Merek"] ?? '');
            $Type = input($_POST["Type"] ?? '');
            $Spesifikasi = input($_POST["Spesifikasi"] ?? '');
            $Kelengkapan_Barang = input($_POST["Kelengkapan_Barang"] ?? '');
            $Kondisi_Barang = input($_POST["Kondisi_Barang"] ?? '');
            // Untuk Riwayat_Barang (JSON), jangan pakai htmlspecialchars - ambil langsung
            // NOTE: beberapa hosting/WAF memblok POST yang berisi JSON mentah. Kita dukung transport Base64.
            $Riwayat_Barang = '';
            $riwayatB64 = isset($_POST['Riwayat_Barang_b64']) ? (string)$_POST['Riwayat_Barang_b64'] : '';
            if ($riwayatB64 !== '') {
                $decoded = base64_decode($riwayatB64, true);
                if ($decoded !== false) {
                    $Riwayat_Barang = (string)$decoded;
                }
            }
            if ($Riwayat_Barang === '') {
                $Riwayat_Barang = isset($_POST["Riwayat_Barang"]) ? trim((string)$_POST["Riwayat_Barang"]) : '';
            }

            if ($__debug) {
                error_log('UPDATE.PHP riwayat_len=' . (string)strlen($Riwayat_Barang) . ' riwayat_b64_len=' . (string)strlen($riwayatB64));
                error_log('UPDATE.PHP riwayat_head=' . substr(str_replace(["\n", "\r"], ' ', $Riwayat_Barang), 0, 180));
            }
            // Escape untuk SQL agar JSON tidak rusak saat mengandung karakter khusus
            $Riwayat_Barang_sql = mysqli_real_escape_string($kon, $Riwayat_Barang);
            $User_Perangkat = input($_POST["User_Perangkat"] ?? '');
            $Jenis_Barang = input($_POST["Jenis_Barang"] ?? '');
            $Status_Barang = input($_POST["Status_Barang"] ?? '');
            $Status_LOP = input($_POST["Status_LOP"] ?? '');
            $Status_Kelayakan_Barang = input($_POST["Status_Kelayakan_Barang"] ?? '');
            $Harga_Barang = isset($_POST["Harga_Barang"]) ? input($_POST["Harga_Barang"]) : '';
            $Harga_Barang_sql = mysqli_real_escape_string($kon, (string)$Harga_Barang);
            // Tahun_Rilis adalah kolom INT di beberapa DB hosting (strict mode). Jangan kirim '' karena akan error.
            $Tahun_Rilis = isset($_POST["Tahun_Rilis"]) ? trim((string)$_POST["Tahun_Rilis"]) : '';
            $Tahun_Rilis_int = null;
            if ($Tahun_Rilis !== '' && preg_match('/^\d{4}$/', $Tahun_Rilis)) {
                $Tahun_Rilis_int = (int)$Tahun_Rilis;
                if ($Tahun_Rilis_int < 1900 || $Tahun_Rilis_int > ((int)date('Y') + 1)) {
                    // tahun tidak masuk akal -> perlakukan sebagai kosong
                    $Tahun_Rilis_int = null;
                }
            }
            $Tahun_Rilis_sql_expr = ($Tahun_Rilis_int === null) ? 'NULL' : (string)$Tahun_Rilis_int;
            $Waktu_Pembelian = isset($_POST["Waktu_Pembelian"]) ? input($_POST["Waktu_Pembelian"]) : '';
            $Waktu_Pembelian_sql = mysqli_real_escape_string($kon, (string)$Waktu_Pembelian);
            $Nama_Toko_Pembelian = isset($_POST["Nama_Toko_Pembelian"]) ? input($_POST["Nama_Toko_Pembelian"]) : '';
            $Nama_Toko_Pembelian_sql = mysqli_real_escape_string($kon, (string)$Nama_Toko_Pembelian);
            $Kategori_Pembelian = isset($_POST["Kategori_Pembelian"]) ? input($_POST["Kategori_Pembelian"]) : '';
            $Kategori_Pembelian_sql = mysqli_real_escape_string($kon, (string)$Kategori_Pembelian);
            // URL: simpan apa adanya (hindari htmlspecialchars yang mengubah '&' menjadi '&amp;')
            $Link_Pembelian = isset($_POST["Link_Pembelian"]) ? trim((string)$_POST["Link_Pembelian"]) : '';
            // Jika kategori bukan Online, kosongkan link agar tidak tersisa nilai lama
            if (strcasecmp(trim((string)$Kategori_Pembelian), 'Online') !== 0) {
                $Link_Pembelian = '';
            }
            $Link_Pembelian_sql = mysqli_real_escape_string($kon, (string)$Link_Pembelian);
            $Serial_Number = input($_POST["Serial_Number"] ?? '');
            $Lokasi = input($_POST["Lokasi"] ?? '');
            $Id_Karyawan = input($_POST["Id_Karyawan"] ?? '');
            $Jabatan = input($_POST["Jabatan"] ?? '');

            // Validasi unik: Nomor_Aset tidak boleh duplikat (exclude id_peserta saat ini)
            // Jika kolom belum ada di DB, query bisa error 1054; skip validasi agar tidak memblokir update.
            $NomorAsetTrim = $normalizeForCompare((string)$Nomor_Aset);
            $needCheckNomorAset = ($NomorAsetTrim !== '' && $NomorAsetTrim !== $oldNomorAsetTrim);

            if ($needCheckNomorAset) {
                // Use prepared statement (more reliable on hosting) and exclude current row by id
                $stmtNomor = $kon->prepare('SELECT id_peserta FROM peserta WHERE Nomor_Aset = ? AND id_peserta <> ? LIMIT 1');
                if ($stmtNomor) {
                    $stmtNomor->bind_param('si', $NomorAsetTrim, $id_peserta_int);
                    if ($stmtNomor->execute()) {
                        $conflictId = null;
                        $stmtNomor->bind_result($conflictId);
                        if ($stmtNomor->fetch()) {
                            $swal_icon = 'error';
                            $swal_title = 'Error!';
                            $swal_message = 'Nomor Aset sudah terdaftar, silakan gunakan yang lain.';
                            error_log('UPDATE.PHP duplicate Nomor_Aset: id=' . $id_peserta_int . ' nomor=' . $NomorAsetTrim . ' conflict_id=' . (string)$conflictId);
                        }
                    }
                    $stmtNomor->close();
                }
            }

            if (!isset($swal_icon)) {

            // TAMBAHAN: Fungsi untuk memproses upload foto dengan rename otomatis & folder dinamis
            function processPhotoUpload($file_input_name, &$photo_variable) {
                global $target_dir;
                
                if (isset($_FILES[$file_input_name]) && $_FILES[$file_input_name]["error"] == 0) {
                    $uploadOk = 1;
                    $imageFileType = strtolower(pathinfo($_FILES[$file_input_name]["name"], PATHINFO_EXTENSION));
                    $isInvoice = ($file_input_name === 'Photo_Invoice');

                    // Cek apakah file gambar (skip untuk PDF invoice)
                    if (!($isInvoice && $imageFileType === 'pdf')) {
                        $check = getimagesize($_FILES[$file_input_name]["tmp_name"]);
                        if ($check === false) {
                            $uploadOk = 0;
                            return "File bukan gambar";
                        }
                    }

                    // Cek ukuran file (maksimum 2MB)
                    if ($_FILES[$file_input_name]["size"] > 2000000) {
                        return "Ukuran file terlalu besar. Maksimum 2MB.";
                    }

                    // Cek tipe file
                    $allowedExt = $isInvoice
                        ? ['jpg', 'png', 'jpeg', 'gif', 'webp', 'pdf']
                        : ['jpg', 'png', 'jpeg', 'gif', 'webp'];
                    if (!in_array($imageFileType, $allowedExt, true)) {
                        return $isInvoice
                            ? "Hanya file JPG, PNG, JPEG, GIF, WebP, dan PDF yang diperbolehkan untuk Invoice."
                            : "Hanya file JPG, PNG, JPEG, GIF, dan WebP yang diperbolehkan.";
                    }

                    // Tentukan folder berdasarkan tipe foto
                    $subfolder = "";
                    if ($file_input_name === "Photo_Depan") {
                        $subfolder = "foto depan/";
                    } elseif ($file_input_name === "Photo_Belakang") {
                        $subfolder = "foto belakang/";
                    } elseif ($file_input_name === "Photo_SN") {
                        $subfolder = "foto sn/";
                    } elseif ($file_input_name === "Photo_Barang") {
                        $subfolder = "";  // Langsung di uploads root
                    } elseif ($file_input_name === "Photo_Invoice") {
                        $subfolder = "foto invoice/";
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

        // Proses upload semua foto
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            $photo_errors = [];

            // Track uploads untuk cleanup (hapus file lama / rollback file baru)
            $uploadedFiles = [];
            
            // Cek Photo_Barang (optional)
            if (!empty($_FILES["Photo_Barang"]["name"])) {
                $oldPath = $Photo_Barang;
                $result = processPhotoUpload("Photo_Barang", $Photo_Barang);
                if ($result === true) {
                    $uploadedFiles['Photo_Barang'] = ['old' => $oldPath, 'new' => $Photo_Barang];
                } elseif ($result !== false) {
                    $photo_errors[] = "Photo_Barang: " . $result;
                }
            }

            // Cek Photo_Depan (optional)
            if (!empty($_FILES["Photo_Depan"]["name"])) {
                $oldPath = $Photo_Depan;
                $result = processPhotoUpload("Photo_Depan", $Photo_Depan);
                if ($result === true) {
                    $uploadedFiles['Photo_Depan'] = ['old' => $oldPath, 'new' => $Photo_Depan];
                } elseif ($result !== false) {
                    $photo_errors[] = "Photo_Depan: " . $result;
                }
            }

            // Cek Photo_Belakang (optional)
            if (!empty($_FILES["Photo_Belakang"]["name"])) {
                $oldPath = $Photo_Belakang;
                $result = processPhotoUpload("Photo_Belakang", $Photo_Belakang);
                if ($result === true) {
                    $uploadedFiles['Photo_Belakang'] = ['old' => $oldPath, 'new' => $Photo_Belakang];
                } elseif ($result !== false) {
                    $photo_errors[] = "Photo_Belakang: " . $result;
                }
            }

            // Cek Photo_SN (optional)
            if (!empty($_FILES["Photo_SN"]["name"])) {
                $oldPath = $Photo_SN;
                $result = processPhotoUpload("Photo_SN", $Photo_SN);
                if ($result === true) {
                    $uploadedFiles['Photo_SN'] = ['old' => $oldPath, 'new' => $Photo_SN];
                } elseif ($result !== false) {
                    $photo_errors[] = "Photo_SN: " . $result;
                }
            }

            // Cek Photo_Invoice (optional)
            if (!empty($_FILES["Photo_Invoice"]["name"])) {
                $oldPath = $Photo_Invoice;
                $result = processPhotoUpload("Photo_Invoice", $Photo_Invoice);
                if ($result === true) {
                    $uploadedFiles['Photo_Invoice'] = ['old' => $oldPath, 'new' => $Photo_Invoice];
                } elseif ($result !== false) {
                    $photo_errors[] = "Photo_Invoice: " . $result;
                }
            }

            // Jika ada error photo, tampilkan dan stop
            if (!empty($photo_errors)) {
                $error_msg = implode(" | ", $photo_errors);
                $swal_icon = 'error';
                $swal_title = 'Error Upload Foto!';
                $swal_message = $error_msg;
            }
        }

            // Cek apakah Serial Number sudah ada di database (exclude id_peserta saat ini)
            $oldSerialTrim = trim((string)($old_data['Serial_Number'] ?? ''));
            $serialTrim = trim((string)$Serial_Number);
            $needCheckSerial = ($serialTrim !== '' && $serialTrim !== $oldSerialTrim);

            if ($needCheckSerial) {
                $stmtSerial = $kon->prepare('SELECT id_peserta FROM peserta WHERE Serial_Number = ? AND id_peserta <> ? LIMIT 1');
                if ($stmtSerial) {
                    $stmtSerial->bind_param('si', $serialTrim, $id_peserta_int);
                    if ($stmtSerial->execute()) {
                        $conflictId = null;
                        $stmtSerial->bind_result($conflictId);
                        if ($stmtSerial->fetch()) {
                            $swal_icon = 'error';
                            $swal_title = 'Error!';
                            $swal_message = 'Serial Number sudah terdaftar, silakan gunakan yang lain.';
                            error_log('UPDATE.PHP duplicate Serial_Number: id=' . $id_peserta_int . ' serial=' . $serialTrim . ' conflict_id=' . (string)$conflictId);
                        }
                    }
                    $stmtSerial->close();
                }
            }

            if (!isset($swal_icon)) {
                // Query update data ke tabel peserta
                $buildUpdateSql = function(bool $includeNomorAset, bool $includeHargaBarang, bool $includeTahunRilis, bool $includeWaktuPembelian, bool $includeNamaVendor, bool $includeKategoriPembelian, bool $includeLinkPembelian) use (
                    $Nomor_Aset_sql,
                    $Nama_Barang,
                    $Merek,
                    $Type,
                    $Spesifikasi,
                    $Kelengkapan_Barang,
                    $Lokasi,
                    $Id_Karyawan,
                    $Jabatan,
                    $Kondisi_Barang,
                    $Riwayat_Barang_sql,
                    $User_Perangkat,
                    $Jenis_Barang,
                    $Status_Barang,
                    $Status_LOP,
                    $Status_Kelayakan_Barang,
                    $Harga_Barang_sql,
                    $Tahun_Rilis_sql_expr,
                    $Waktu_Pembelian_sql,
                    $Nama_Toko_Pembelian_sql,
                    $Kategori_Pembelian_sql,
                    $Link_Pembelian_sql,
                    $Serial_Number
                ) {
                    $set = "";
                    if ($includeNomorAset) {
                        $set .= "Nomor_Aset = '$Nomor_Aset_sql',\n";
                    }
                    $set .= "Nama_Barang = '$Nama_Barang',\n";
                    $set .= "Merek = '$Merek',\n";
                    $set .= "Type = '$Type',\n";
                    $set .= "Spesifikasi = '$Spesifikasi',\n";
                    $set .= "Kelengkapan_Barang = '$Kelengkapan_Barang',\n";
                    $set .= "Lokasi = '$Lokasi',\n";
                    $set .= "Id_Karyawan = '$Id_Karyawan',\n";
                    $set .= "Jabatan = '$Jabatan',\n";
                    $set .= "Kondisi_Barang = '$Kondisi_Barang',\n";
                    $set .= "Riwayat_Barang = '$Riwayat_Barang_sql',\n";
                    $set .= "User_Perangkat = '$User_Perangkat',\n";
                    $set .= "Jenis_Barang = '$Jenis_Barang',\n";
                    $set .= "Status_Barang = '$Status_Barang',\n";
                    $set .= "Status_LOP = '$Status_LOP',\n";
                    $set .= "Status_Kelayakan_Barang = '$Status_Kelayakan_Barang',\n";
                    if ($includeHargaBarang) {
                        $set .= "Harga_Barang = '$Harga_Barang_sql',\n";
                    }
                    if ($includeTahunRilis) {
                        $set .= "Tahun_Rilis = $Tahun_Rilis_sql_expr,\n";
                    }
                    if ($includeWaktuPembelian) {
                        $set .= "Waktu_Pembelian = '$Waktu_Pembelian_sql',\n";
                    }
                    if ($includeNamaVendor) {
                        $set .= "Nama_Toko_Pembelian = '$Nama_Toko_Pembelian_sql',\n";
                    }
                    if ($includeKategoriPembelian) {
                        $set .= "Kategori_Pembelian = '$Kategori_Pembelian_sql',\n";
                    }
                    if ($includeLinkPembelian) {
                        $set .= "Link_Pembelian = '$Link_Pembelian_sql',\n";
                    }
                    $set .= "Serial_Number = '$Serial_Number'";
                    return "UPDATE peserta SET \n" . $set;
                };

                // Detect existing columns once (hosting-safe, avoids expensive retry loops)
                $existingColumns = [];
                $colRes = @mysqli_query($kon, 'SHOW COLUMNS FROM peserta');
                if ($colRes) {
                    while ($colRow = mysqli_fetch_assoc($colRes)) {
                        $field = (string)($colRow['Field'] ?? '');
                        if ($field !== '') {
                            $existingColumns[$field] = true;
                        }
                    }
                }

                $hasCol = function(string $col) use ($existingColumns): bool {
                    return isset($existingColumns[$col]);
                };

                $includeNomorAset = $hasCol('Nomor_Aset');
                // Strict-mode hosting: jangan update kolom numeric/date menjadi '' (bisa error dan membuat UPDATE gagal total).
                // Jika input kosong, biarkan nilai lama (kolom tidak diikutkan dalam SET).
                $includeHargaBarang = ($hasCol('Harga_Barang') && trim((string)$Harga_Barang) !== '');
                $hasTahunRilisCol = $hasCol('Tahun_Rilis');
                // Hosting bisa strict + kolom INT NOT NULL. Jika input kosong, jangan update kolom ini (biarkan nilai lama).
                $includeTahunRilis = ($hasTahunRilisCol && isset($Tahun_Rilis_int) && $Tahun_Rilis_int !== null);
                $includeWaktuPembelian = ($hasCol('Waktu_Pembelian') && trim((string)$Waktu_Pembelian) !== '');
                $includeNamaVendor = ($hasCol('Nama_Toko_Pembelian') && trim((string)$Nama_Toko_Pembelian) !== '');
                // Kategori biasanya dropdown wajib; tetap update jika ada input.
                $includeKategoriPembelian = ($hasCol('Kategori_Pembelian') && trim((string)$Kategori_Pembelian) !== '');
                // Link: kalau kategori bukan Online kita memang mengosongkan link → harus tetap diupdate agar nilai lama terhapus.
                $shouldClearLink = (strcasecmp(trim((string)$Kategori_Pembelian), 'Online') !== 0);
                $includeLinkPembelian = ($hasCol('Link_Pembelian') && ($shouldClearLink || trim((string)$Link_Pembelian) !== ''));

                // If user tries to update fields/attachments that don't exist in hosting DB, surface it.
                $missingColumns = [];
                if (!$includeHargaBarang && trim((string)$Harga_Barang) !== '') {
                    $missingColumns[] = 'Harga_Barang';
                }
                if (!$hasTahunRilisCol && trim((string)$Tahun_Rilis) !== '') {
                    $missingColumns[] = 'Tahun_Rilis';
                }
                if (!$includeWaktuPembelian && trim((string)$Waktu_Pembelian) !== '') {
                    $missingColumns[] = 'Waktu_Pembelian';
                }
                if (!$includeNamaVendor && trim((string)$Nama_Toko_Pembelian) !== '') {
                    $missingColumns[] = 'Nama_Toko_Pembelian';
                }
                if (!$includeKategoriPembelian && trim((string)$Kategori_Pembelian) !== '') {
                    $missingColumns[] = 'Kategori_Pembelian';
                }
                if (!$includeLinkPembelian && trim((string)$Link_Pembelian) !== '') {
                    $missingColumns[] = 'Link_Pembelian';
                }
                if (!empty($_FILES["Photo_Barang"]["name"]) && !$hasCol('Photo_Barang')) {
                    $missingColumns[] = 'Photo_Barang';
                }
                if (!empty($_FILES["Photo_Depan"]["name"]) && !$hasCol('Photo_Depan')) {
                    $missingColumns[] = 'Photo_Depan';
                }
                if (!empty($_FILES["Photo_Belakang"]["name"]) && !$hasCol('Photo_Belakang')) {
                    $missingColumns[] = 'Photo_Belakang';
                }
                if (!empty($_FILES["Photo_SN"]["name"]) && !$hasCol('Photo_SN')) {
                    $missingColumns[] = 'Photo_SN';
                }
                if (!empty($_FILES["Photo_Invoice"]["name"]) && !$hasCol('Photo_Invoice')) {
                    $missingColumns[] = 'Photo_Invoice';
                }
                $missingColumnsText = '';
                if (!empty($missingColumns)) {
                    $missingColumns = array_values(array_unique($missingColumns));
                    $missingColumnsText = 'Kolom belum ada di database hosting: ' . implode(', ', $missingColumns) . '.';
                }

                if ($__debug) {
                    error_log('UPDATE.PHP columns_detected=' . count($existingColumns) . ' missing=' . $missingColumnsText);
                    error_log('UPDATE.PHP tahun_rilis_raw=' . (string)$Tahun_Rilis . ' tahun_rilis_int=' . (isset($Tahun_Rilis_int) ? var_export($Tahun_Rilis_int, true) : 'unset') . ' includeTahunRilis=' . ($includeTahunRilis ? 'yes' : 'no') . ' colExists=' . ($hasTahunRilisCol ? 'yes' : 'no'));
                    error_log('UPDATE.PHP includeHarga=' . ($includeHargaBarang ? 'yes' : 'no') . ' includeWaktu=' . ($includeWaktuPembelian ? 'yes' : 'no') . ' includeVendor=' . ($includeNamaVendor ? 'yes' : 'no') . ' includeKategori=' . ($includeKategoriPembelian ? 'yes' : 'no') . ' includeLink=' . ($includeLinkPembelian ? 'yes' : 'no') . ' clearLink=' . ($shouldClearLink ? 'yes' : 'no'));
                }

                $sql = $buildUpdateSql($includeNomorAset, $includeHargaBarang, $includeTahunRilis, $includeWaktuPembelian, $includeNamaVendor, $includeKategoriPembelian, $includeLinkPembelian);

                // Add photo updates only if uploaded AND column exists
                if (!empty($_FILES["Photo_Barang"]["name"]) && $hasCol('Photo_Barang')) {
                    $sql .= ", Photo_Barang = '$Photo_Barang'";
                }
                if (!empty($_FILES["Photo_Depan"]["name"]) && $hasCol('Photo_Depan')) {
                    $sql .= ", Photo_Depan = '$Photo_Depan'";
                }
                if (!empty($_FILES["Photo_Belakang"]["name"]) && $hasCol('Photo_Belakang')) {
                    $sql .= ", Photo_Belakang = '$Photo_Belakang'";
                }
                if (!empty($_FILES["Photo_SN"]["name"]) && $hasCol('Photo_SN')) {
                    $sql .= ", Photo_SN = '$Photo_SN'";
                }
                if (!empty($_FILES["Photo_Invoice"]["name"]) && $hasCol('Photo_Invoice')) {
                    $sql .= ", Photo_Invoice = '$Photo_Invoice'";
                }

                $sql .= " WHERE id_peserta = '$id_peserta_sql'";

                if ($__debug) {
                    $sqlHead = substr(str_replace(array("\r", "\n", "\t"), ' ', (string)$sql), 0, 260);
                    error_log('UPDATE.PHP build=' . $__build . ' update_sql_has_tahun_rilis=' . (strpos($sql, 'Tahun_Rilis') !== false ? 'yes' : 'no') . ' sql_head=' . $sqlHead);
                }

                // Mengeksekusi atau menjalankan query
                $hasil = mysqli_query($kon, $sql);

                if ($__debug) {
                    error_log('UPDATE.PHP update_exec ok=' . ($hasil ? '1' : '0') . ' errno=' . mysqli_errno($kon) . ' err=' . mysqli_error($kon));
                }

                // Cleanup uploads: hapus file lama jika update sukses; rollback file baru jika update gagal/kolom tidak ada
                if (isset($uploadedFiles) && is_array($uploadedFiles) && !empty($uploadedFiles)) {
                    $safeDeleteUploadFile = function(string $relativePath) use ($uploads_base_dir): void {
                        $relativePath = trim((string)$relativePath);
                        if ($relativePath === '' || $uploads_base_dir === false) {
                            return;
                        }

                        $rel = str_replace('\\', '/', $relativePath);
                        $firstChar = $rel !== '' ? $rel[0] : '';
                        $hasDotDot = $rel !== '' && strpos($rel, '..') !== false;
                        $startsSlash = ($firstChar === '/' || $firstChar === '\\');
                        if ($rel === '' || $hasDotDot || $startsSlash || preg_match('/^[a-zA-Z]:/', $rel)) {
                            return;
                        }

                        $full = $uploads_base_dir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
                        if (is_file($full)) {
                            @unlink($full);
                        }
                    };

                    $didUpdateColumn = function(string $column, string $expectedValue) use ($kon, $id_peserta): bool {
                        $column = trim($column);
                        if ($column === '') {
                            return false;
                        }
                        $idSql = mysqli_real_escape_string($kon, (string)$id_peserta);
                        $q = "SELECT `$column` AS v FROM peserta WHERE id_peserta = '$idSql' LIMIT 1";
                        $res = @mysqli_query($kon, $q);
                        if (!$res) {
                            return false;
                        }
                        $row = mysqli_fetch_assoc($res);
                        $val = (string)($row['v'] ?? '');
                        return $val === (string)$expectedValue;
                    };

                    if ($hasil) {
                        foreach ($uploadedFiles as $col => $paths) {
                            $oldPath = (string)($paths['old'] ?? '');
                            $newPath = (string)($paths['new'] ?? '');
                            if ($newPath === '') {
                                continue;
                            }

                            // Jika DB benar-benar terupdate ke path baru → hapus file lama.
                            // Jika tidak (kolom tidak ada / tidak terupdate) → hapus file baru agar tidak jadi sampah.
                            if ($didUpdateColumn((string)$col, $newPath)) {
                                if ($oldPath !== '' && $oldPath !== $newPath) {
                                    $safeDeleteUploadFile($oldPath);
                                }
                            } else {
                                $safeDeleteUploadFile($newPath);
                            }
                        }
                    } else {
                        // Update gagal → hapus file baru yang sudah terlanjur terupload
                        foreach ($uploadedFiles as $paths) {
                            $newPath = (string)($paths['new'] ?? '');
                            if ($newPath !== '') {
                                $safeDeleteUploadFile($newPath);
                            }
                        }
                    }
                }

                // Activity log should never block the update flow
                try {
                    $logFile = __DIR__ . '/log_activity.php';
                    if (file_exists($logFile)) {
                        include_once $logFile;
                        if (function_exists('logUserActivity')) {
                            logUserActivity(
                                $kon,
                                isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0,
                                isset($_SESSION['username']) ? (string)$_SESSION['username'] : '',
                                isset($_SESSION['role']) ? (string)$_SESSION['role'] : '',
                                'UPDATE',
                                'peserta',
                                $id_peserta,
                                null
                            );
                        }
                    }
                } catch (Throwable $e) {
                    error_log('UPDATE.PHP: activity log skipped: ' . $e->getMessage());
                }

                // Kondisi apakah berhasil atau tidak
                if ($hasil) {
                    $swal_icon = 'success';
                    $swal_title = 'Sukses!';
                    $swal_message = 'Data Berhasil Diperbarui';
                    $redirect_after = true;

                    // Hosting-safe: immediately show success + redirect, don't depend on long scripts below.
                    $indexUrl = 'index.php';
                    if (ob_get_length()) {
                        ob_clean();
                    }
                    echo "<!DOCTYPE html><html lang='id'><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width, initial-scale=1.0'>";
                    echo "<title>Redirect...</title>";
                    echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
                    echo "</head><body>";
                    echo "<script>(function(){\n";
                    echo "var url=" . json_encode($indexUrl, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) . ";\n";
                    echo "function go(){window.location.href=url;}\n";
                    echo "if(typeof Swal==='undefined'){go();return;}\n";
                    echo "Swal.fire({icon:'success',title:" . json_encode($swal_title, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) . ",text:" . json_encode($swal_message, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) . ",timer:1200,showConfirmButton:false,allowOutsideClick:false}).then(go);\n";
                    echo "setTimeout(go,1600);\n";
                    echo "})();</script></body></html>";
                    exit();
                } else {
                    $swal_icon = 'error';
                    $swal_title = 'Error!';
                    $swal_message = 'Data Gagal disimpan. ' . mysqli_error($kon);
                }
            } // end if (!isset($swal_icon)) update flow

            // If we get here on POST and have an error message, show it in a minimal page and return to edit.
            if (isset($swal_icon) && $swal_icon) {
                $returnUrl = $_SERVER['PHP_SELF'] . (!empty($__token_param) ? ('?t=' . rawurlencode((string)$__token_param)) : ('?id_peserta=' . rawurlencode((string)$id_peserta)));
                if ($__debug) {
                    $returnUrl .= '&debug=1';
                }
                if (ob_get_length()) {
                    ob_clean();
                }
                echo "<!DOCTYPE html><html lang='id'><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width, initial-scale=1.0'>";
                echo "<title>Info</title>";
                echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
                echo "</head><body>";
                echo "<script>(function(){\n";
                echo "var url=" . json_encode($returnUrl, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) . ";\n";
                echo "function go(){window.location.href=url;}\n";
                echo "var icon=" . json_encode((string)$swal_icon, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) . ";\n";
                echo "var title=" . json_encode((string)($swal_title ?? 'Info'), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) . ";\n";
                echo "var text=" . json_encode((string)($swal_message ?? ''), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) . ";\n";
                echo "if(typeof Swal==='undefined'){alert(title+'\\n\\n'+text);go();return;}\n";
                echo "Swal.fire({icon:icon,title:title,text:text,confirmButtonText:'OK'}).then(go);\n";
                echo "})();</script></body></html>";
                exit();
            }
            } // end if (!isset($swal_icon)) (uploads + serial + update)
        } // end if POST

        // Header Section akan ditampilkan di bawah ?>



        <!-- Main Form -->
        <div class="form-container p-4 sm:p-6 md:p-8 animate-fade-in-up animate-delay-1">
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data" id="assetForm">
                <!-- Hidden field untuk id_peserta -->
                <input type="hidden" name="id_peserta" value="<?php echo $id_peserta; ?>" />
                <?php if (!empty($__token_param)): ?>
                    <input type="hidden" name="t" value="<?php echo htmlspecialchars($__token_param); ?>" />
                <?php endif; ?>
                <?php if (isset($__debug) && $__debug): ?>
                    <input type="hidden" name="debug" value="1" />
                <?php endif; ?>
                
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
                                   placeholder="Contoh: ITCKT-<?php echo date('Y'); ?>-0001"
                                   value="<?php echo htmlspecialchars($Nomor_Aset ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                   autocomplete="off" />
                            <small class="text-gray-500 mt-1 block">Jika dikosongkan, sistem akan mempertahankan nilai sebelumnya.</small>
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
                            $tahunRilisSelected = trim((string)($Tahun_Rilis ?? ''));
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
                            <?php
                            $Waktu_Pembelian_value = '';
                            if (!empty($Waktu_Pembelian)) {
                                $ts_wp = strtotime((string)$Waktu_Pembelian);
                                if ($ts_wp !== false) {
                                    $Waktu_Pembelian_value = date('Y-m-d', $ts_wp);
                                }
                            }
                            ?>
                            <input type="date" name="Waktu_Pembelian" id="Waktu_Pembelian"
                                   class="input-modern w-full px-4 py-3 border border-gray-300 rounded-xl focus:border-orange-500 focus:ring-2 focus:ring-orange-200"
                                   value="<?php echo htmlspecialchars($Waktu_Pembelian_value); ?>">
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
                                            <option value="<?php echo htmlspecialchars($vendor); ?>" <?php if (($Nama_Toko_Pembelian ?? '') == $vendor) echo 'selected'; ?>>
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
                                <option value="Online" <?php echo (($Kategori_Pembelian ?? '') === 'Online' ? 'selected' : ''); ?>>Online</option>
                                <option value="Offline" <?php echo (($Kategori_Pembelian ?? '') === 'Offline' ? 'selected' : ''); ?>>Offline</option>
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
                                       value="<?php echo htmlspecialchars($Link_Pembelian ?? ''); ?>">
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
                            <textarea name="Riwayat_Barang" id="Riwayat_Barang" class="hidden" required><?php echo htmlspecialchars($Riwayat_Barang, ENT_QUOTES, 'UTF-8'); ?></textarea>
                            <input type="hidden" name="Riwayat_Barang_b64" id="Riwayat_Barang_b64" value="" />
                            
                            <!-- Form untuk menambah entry riwayat -->
                            <div class="bg-white border border-gray-300 rounded-lg p-4 mb-3">
                                <h4 class="text-sm font-semibold text-gray-700 mb-3">Tambah Entry Riwayat</h4>
                                
                                <!-- Tanggal Serah Terima -->
                                <div class="mb-3">
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Tanggal Serah Terima</label>
                                    <input type="date" id="riwayatTglSerah" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:border-orange-500 focus:ring-2 focus:ring-orange-200" />
                                </div>

                                <!-- Tanggal Pengembalian -->
                                <div class="mb-3">
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Tanggal Pengembalian</label>
                                    <input type="date" id="riwayatTglKembali" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:border-orange-500 focus:ring-2 focus:ring-orange-200" />
                                </div>
                                
                                <!-- Nama (Select2 dari User_Perangkat) -->
                                <div class="mb-3">
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Nama Tangan Pertama</label>
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
                        Upload Foto Barang (4 Sisi)
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6">
                        <!-- Photo Barang -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-3">
                                <i class="fas fa-camera mr-2 text-blue-500"></i>Foto Barang (Umum)
                            </label>
                            <?php if (!empty($Photo_Barang) && isset($uploadFileExists) && $uploadFileExists((string)$Photo_Barang)): ?>
                                <div class="mt-3 text-center p-3 bg-blue-50 rounded-lg border-2 border-blue-200 mb-3">
                                    <p class="text-xs text-blue-600 mb-2">Foto Saat Ini:</p>
                                    <img src="../uploads/<?php echo htmlspecialchars($Photo_Barang); ?>" class="w-32 h-32 object-cover rounded-lg shadow-md mx-auto" alt="Current Photo" />
                                </div>
                            <?php endif; ?>
                            <div class="photo-upload rounded-xl p-6 text-center cursor-pointer border-2 border-dashed border-gray-300 hover:border-blue-500 transition-colors">
                                <label for="Photo_Barang" class="block cursor-pointer">
                                    <i class="fas fa-cloud-upload-alt text-3xl text-gray-400 mb-3"></i>
                                    <p class="text-gray-600 mb-2 text-sm">Klik atau drag & drop</p>
                                    <p class="text-xs text-gray-500">JPG, PNG, GIF (max. 2MB) - Kosongkan jika tidak ingin mengubah</p>
                                </label>
                                <input type="file" name="Photo_Barang" id="Photo_Barang" accept="image/*" class="hidden" />
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
                            <?php if (!empty($Photo_Depan) && isset($uploadFileExists) && $uploadFileExists((string)$Photo_Depan)): ?>
                                <div class="mt-3 text-center p-3 bg-green-50 rounded-lg border-2 border-green-200 mb-3">
                                    <p class="text-xs text-green-600 mb-2">Foto Saat Ini:</p>
                                    <img src="../uploads/<?php echo htmlspecialchars($Photo_Depan); ?>" class="w-32 h-32 object-cover rounded-lg shadow-md mx-auto" alt="Current Photo" />
                                </div>
                            <?php endif; ?>
                            <div class="photo-upload rounded-xl p-6 text-center cursor-pointer border-2 border-dashed border-gray-300 hover:border-green-500 transition-colors">
                                <label for="Photo_Depan" class="block cursor-pointer">
                                    <i class="fas fa-cloud-upload-alt text-3xl text-gray-400 mb-3"></i>
                                    <p class="text-gray-600 mb-2 text-sm">Klik atau drag & drop</p>
                                    <p class="text-xs text-gray-500">JPG, PNG, GIF (max. 2MB) - Kosongkan jika tidak ingin mengubah</p>
                                </label>
                                <input type="file" name="Photo_Depan" id="Photo_Depan" accept="image/*" class="hidden" />
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
                            <?php if (!empty($Photo_Belakang) && isset($uploadFileExists) && $uploadFileExists((string)$Photo_Belakang)): ?>
                                <div class="mt-3 text-center p-3 bg-yellow-50 rounded-lg border-2 border-yellow-200 mb-3">
                                    <p class="text-xs text-yellow-600 mb-2">Foto Saat Ini:</p>
                                    <img src="../uploads/<?php echo htmlspecialchars($Photo_Belakang); ?>" class="w-32 h-32 object-cover rounded-lg shadow-md mx-auto" alt="Current Photo" />
                                </div>
                            <?php endif; ?>
                            <div class="photo-upload rounded-xl p-6 text-center cursor-pointer border-2 border-dashed border-gray-300 hover:border-yellow-500 transition-colors">
                                <label for="Photo_Belakang" class="block cursor-pointer">
                                    <i class="fas fa-cloud-upload-alt text-3xl text-gray-400 mb-3"></i>
                                    <p class="text-gray-600 mb-2 text-sm">Klik atau drag & drop</p>
                                    <p class="text-xs text-gray-500">JPG, PNG, GIF (max. 2MB) - Kosongkan jika tidak ingin mengubah</p>
                                </label>
                                <input type="file" name="Photo_Belakang" id="Photo_Belakang" accept="image/*" class="hidden" />
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
                            <?php if (!empty($Photo_SN) && isset($uploadFileExists) && $uploadFileExists((string)$Photo_SN)): ?>
                                <div class="mt-3 text-center p-3 bg-red-50 rounded-lg border-2 border-red-200 mb-3">
                                    <p class="text-xs text-red-600 mb-2">Foto Saat Ini:</p>
                                    <img src="../uploads/<?php echo htmlspecialchars($Photo_SN); ?>" class="w-32 h-32 object-cover rounded-lg shadow-md mx-auto" alt="Current Photo" />
                                </div>
                            <?php endif; ?>
                            <div class="photo-upload rounded-xl p-6 text-center cursor-pointer border-2 border-dashed border-gray-300 hover:border-red-500 transition-colors">
                                <label for="Photo_SN" class="block cursor-pointer">
                                    <i class="fas fa-cloud-upload-alt text-3xl text-gray-400 mb-3"></i>
                                    <p class="text-gray-600 mb-2 text-sm">Klik atau drag & drop</p>
                                    <p class="text-xs text-gray-500">JPG, PNG, GIF (max. 2MB) - Kosongkan jika tidak ingin mengubah</p>
                                </label>
                                <input type="file" name="Photo_SN" id="Photo_SN" accept="image/*" class="hidden" />
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
                                <i class="fas fa-file-invoice mr-2 text-blue-500"></i>Dokumen Invoice (Opsional)
                            </label>
                            <?php if (!empty($Photo_Invoice) && isset($uploadFileExists) && $uploadFileExists((string)$Photo_Invoice)): ?>
                                <?php $isInvoicePdf = strtolower(pathinfo((string)$Photo_Invoice, PATHINFO_EXTENSION)) === 'pdf'; ?>
                                <div class="mt-3 text-center p-3 bg-blue-50 rounded-lg border-2 border-blue-200 mb-3">
                                    <p class="text-xs text-blue-600 mb-2">File Saat Ini:</p>
                                    <?php if ($isInvoicePdf): ?>
                                        <div class="w-32 h-32 bg-white rounded-lg shadow-md border-2 border-blue-200 flex flex-col items-center justify-center mx-auto p-2">
                                            <i class="fas fa-file-pdf text-red-500 text-3xl"></i>
                                            <a href="../uploads/<?php echo htmlspecialchars($Photo_Invoice); ?>" target="_blank" rel="noopener" class="text-[11px] text-blue-600 underline mt-2">Buka PDF</a>
                                        </div>
                                    <?php else: ?>
                                        <img src="../uploads/<?php echo htmlspecialchars($Photo_Invoice); ?>" class="w-32 h-32 object-cover rounded-lg shadow-md mx-auto" alt="Current Invoice" />
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <div class="photo-upload rounded-xl p-6 text-center cursor-pointer border-2 border-dashed border-gray-300 hover:border-blue-500 transition-colors">
                                <label for="Photo_Invoice" class="block cursor-pointer">
                                    <i class="fas fa-cloud-upload-alt text-3xl text-gray-400 mb-3"></i>
                                    <p class="text-gray-600 mb-2 text-sm">Klik atau drag & drop</p>
                                    <p class="text-xs text-gray-500">JPG, PNG, GIF, WebP, PDF (max. 2MB) - Kosongkan jika tidak ingin mengubah</p>
                                </label>
                                <input type="file" name="Photo_Invoice" id="Photo_Invoice" accept="image/*,application/pdf" class="hidden" />
                            </div>
                            <div id="photoPreview_Invoice" class="mt-3 hidden text-center p-3 bg-blue-50 rounded-lg border-2 border-blue-200"></div>
                        </div>
                    </div>
                </div>



                <!-- Submit Buttons -->
                <div class="flex flex-col sm:flex-row gap-3 sm:gap-4 justify-center animate-fade-in-up animate-delay-6 mt-6 sm:mt-8">
                    <button type="submit" class="btn-gradient text-white px-6 sm:px-8 py-3 sm:py-4 rounded-xl text-base sm:text-lg hover:shadow-lg transform transition-all duration-300 flex items-center justify-center w-full sm:w-auto">
                        <i class="fas fa-save mr-2 sm:mr-3"></i>
                        <span>Perbarui Data Asset</span>
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
                if (selectElement.data('select2')) {
                    selectElement.select2('destroy');
                }
                selectElement.select2({
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
                        }
                    },
                    minimumInputLength: 0
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
                try { URL.revokeObjectURL(bitmap.src); } catch (e) {}
            }

            if (!bestBlob) return file;
            if (bestBlob.size >= file.size) return file;

            const baseName = (file.name || 'image').replace(/\.[^/.]+$/, '');
            const newName = baseName + '.jpg';
            return new File([bestBlob], newName, { type: 'image/jpeg', lastModified: Date.now() });
        }

        async function prepareFileBeforeUpload(file, photoFieldId) {
            if (!file) return file;
            if (photoFieldId === 'Photo_Invoice' && file.type === 'application/pdf') return file;
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
            const key = `${lat.toFixed(4)},${lng.toFixed(4)}`;
            if (geocodeCache.has(key)) {
                return geocodeCache.get(key);
            }
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
                if (!res.ok) return null;
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
            if (photoFieldId === 'Photo_Invoice') return;
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                Swal.fire({ icon: 'error', title: 'Error!', text: 'Browser tidak mendukung akses kamera.' });
                return;
            }

            const base = photoFieldId.replace('Photo_', '');
            const box = document.getElementById('cameraBox_' + base);
            const video = document.getElementById('cameraVideo_' + base);
            const geoEl = document.getElementById('cameraGeo_' + base);
            if (!box || !video) return;

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
        
        const previewHTML = `
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
                    return "Ketik " + (args.minimum - args.input.length) + " karakter lagi untuk mencari";
                }
            },
            minimumInputLength: 0
        });
    }
});

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
            currentField = field;
            currentFieldName = fieldName;
            $('#modalTitle').text('Tambah ' + fieldName + ' Baru');
            $('#modalFieldName').text(fieldName);
            $('#customOptionInput').val('').focus();
            $('#addModal').addClass('show');
        }

        // Hide modal
        function hideModal() {
            $('#addModal').removeClass('show');
            currentField = '';
            currentFieldName = '';
        }

        // Modal event listeners
        $('#cancelModalBtn').click(hideModal);
        
        $('#addModal').click(function(e) {
            if (e.target === this) {
                hideModal();
            }
        });

        $('#customOptionInput').keypress(function(e) {
            if (e.which === 13) { // Enter key
                e.preventDefault();
                saveCustomOption();
            }
        });

        $('#saveModalBtn').click(function() {
            saveCustomOption();
        });

        // Save custom option
        // Save custom option (updated dengan re-init Select2)
function saveCustomOption() {
    const value = $('#customOptionInput').val().trim();
    if (!value) {
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

    // TAMBAHAN: Re-init Select2 agar opsi baru searchable
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
            // ============ KATEGORI PEMBELIAN REMARK ============
            function syncKategoriPembelianRemark() {
                const val = String($('#Kategori_Pembelian').val() || '').trim();
                if (val === 'Online') {
                    $('#remarkKategoriPembelianOnline').removeClass('hidden');
                    $('#linkPembelianGroup').removeClass('hidden');
                } else {
                    $('#remarkKategoriPembelianOnline').addClass('hidden');
                    $('#linkPembelianGroup').addClass('hidden');
                    // Clear value supaya saat submit jadi '-' dan menghapus nilai lama
                    $('#Link_Pembelian').val('');
                }
            }
            $('#Kategori_Pembelian').on('change', syncKategoriPembelianRemark);
            syncKategoriPembelianRemark();
            // ============ END KATEGORI PEMBELIAN REMARK =======

            // ============ RIWAYAT BARANG LOGIC ============
            let riwayatList = [];
            let riwayatParseError = '';
            
            // Parse existing riwayat jika ada (decode HTML entities first if present)
            const existingRiwayatRaw = $('#Riwayat_Barang').val() ? $('#Riwayat_Barang').val().trim() : '';
            let existingRiwayat = existingRiwayatRaw;
            if (existingRiwayatRaw.indexOf('&') !== -1) {
                const txtDecode = document.createElement('textarea');
                txtDecode.innerHTML = existingRiwayatRaw;
                existingRiwayat = txtDecode.value;
            }

            if (existingRiwayat && existingRiwayat !== '') {
                try {
                    let parsed = JSON.parse(existingRiwayat);

                    // Handle kasus data tersimpan sebagai JSON string (double-encoded)
                    // contoh: "[{\"nama\":\"...\"}]" -> parse pertama jadi string
                    if (typeof parsed === 'string') {
                        parsed = JSON.parse(parsed);
                    }

                    // Pastikan selalu array
                    if (Array.isArray(parsed)) {
                        riwayatList = parsed;
                    } else if (parsed && typeof parsed === 'object') {
                        riwayatList = [parsed];
                    } else {
                        riwayatList = [];
                    }
                } catch (e) {
                    riwayatList = [];
                    riwayatParseError = (e && e.message) ? e.message : 'Unknown JSON parse error';
                }
            }
            
            // Fungsi render riwayat list
            function renderRiwayatList() {
                const container = $('#riwayatContainer');
                container.empty();

                // Jika parse gagal, tampilkan info agar tidak terlihat seperti "kosong"
                if (riwayatParseError) {
                    const rawPreview = existingRiwayatRaw ? existingRiwayatRaw.substring(0, 220) : '';
                    container.html(
                        '<div class="text-xs text-red-600 bg-red-50 border border-red-200 rounded-lg p-3">' +
                        '<div class="font-semibold mb-1">Riwayat tersimpan tapi gagal dibaca (JSON invalid).</div>' +
                        '<div class="mb-1">Error: ' + $('<div>').text(riwayatParseError).html() + '</div>' +
                        (rawPreview ? ('<div class="text-gray-700">Preview: ' + $('<div>').text(rawPreview).html() + (existingRiwayatRaw.length > 220 ? '…' : '') + '</div>') : '') +
                        '</div>'
                    );
                    return;
                }

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
            const userPerangkatData = <?php echo $userPerangkatJson; ?>;
            const jabatanData = <?php echo $jabatanJson; ?>;
            const idKaryawanData = <?php echo $idKaryawanJson; ?>;
            const lokasiData = <?php echo $lokasiJson; ?>;

            $('.riwayat-nama-select').select2({
                data: (Array.isArray(userPerangkatData) ? userPerangkatData : []).map(item => ({id: item, text: item})),
                placeholder: '-- Pilih Nama --',
                allowClear: true,
                tags: true
            });
            
            $('.riwayat-jabatan-select').select2({
                data: (Array.isArray(jabatanData) ? jabatanData : []).map(item => ({id: item, text: item})),
                placeholder: '-- Pilih Jabatan --',
                allowClear: true,
                tags: true
            });
            
            $('.riwayat-emplid-select').select2({
                data: (Array.isArray(idKaryawanData) ? idKaryawanData : []).map(item => ({id: item, text: item})),
                placeholder: '-- Pilih Employee ID --',
                allowClear: true,
                tags: true
            });
            
            $('.riwayat-lokasi-select').select2({
                data: (Array.isArray(lokasiData) ? lokasiData : []).map(item => ({id: item, text: item})),
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
            
            // ============ FORM SUBMISSION VALIDATION + CONFIRM UPDATE ============
            let isManualSubmitting = false;

            function showInfo(icon, title, text) {
                if (typeof Swal === 'undefined') {
                    alert((title || 'Info') + "\n\n" + (text || ''));
                    return;
                }
                Swal.fire({
                    title: title || 'Info',
                    text: text || '',
                    icon: icon || 'info',
                    confirmButtonText: 'OK'
                });
            }

            $('#assetForm').on('submit', function(e) {
                if (isManualSubmitting) {
                    return true;
                }

                // Update hidden textarea dengan JSON sebelum submit
                const riwayatJson = JSON.stringify(riwayatList);
                $('#Riwayat_Barang').val(riwayatJson);
                try {
                    // UTF-8 safe Base64
                    const b64 = btoa(unescape(encodeURIComponent(riwayatJson)));
                    $('#Riwayat_Barang_b64').val(b64);
                } catch (e) {
                    // Fallback: kosongkan b64, server akan pakai field JSON biasa
                    $('#Riwayat_Barang_b64').val('');
                }

                // Validasi Serial Number
                const feedback = $('#serialNumberFeedback').text();
                if (feedback.includes('sudah terdaftar')) {
                    e.preventDefault();
                    showInfo('error', 'Error!', 'Serial Number sudah terdaftar, silakan gunakan yang lain.');
                    $('#Serial_Number').focus();
                    return false;
                }

                // Validasi Riwayat_Barang (minimal harus ada 1 entry)
                if (riwayatList.length === 0) {
                    e.preventDefault();
                    showInfo('error', 'Error!', 'Minimal harus ada 1 entry di Riwayat Barang sebelum simpan.');
                    document.getElementById('riwayatContainer').scrollIntoView({ behavior: 'smooth' });
                    return false;
                }

                // Pastikan hidden textarea sudah update
                $('#Riwayat_Barang').val(riwayatJson);
                console.log('Form Submit - Final Riwayat_Barang:', $('#Riwayat_Barang').val());

                // Confirm sebelum update
                e.preventDefault();
                const formEl = this;
                if (typeof Swal === 'undefined') {
                    const ok = window.confirm('Update Data?\n\nPastikan data sudah benar sebelum disimpan.');
                    if (!ok) {
                        return false;
                    }
                    isManualSubmitting = true;
                    formEl.submit();
                    return false;
                }

                Swal.fire({
                    title: 'Update Data?',
                    text: 'Pastikan data sudah benar sebelum disimpan.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, Update',
                    cancelButtonText: 'Batal',
                    allowOutsideClick: false
                }).then((result) => {
                    if (!result.isConfirmed) {
                        return;
                    }

                    Swal.fire({
                        title: 'Menyimpan Data...',
                        text: 'Mohon tunggu sebentar',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    isManualSubmitting = true;
                    formEl.submit();
                });

                return false;
            });
            // ============ END FORM SUBMISSION VALIDATION + CONFIRM UPDATE ============
            
            // Tambah opsi "Tambah Baru" di awal
            const selectElement = $('.select-user-perangkat');

            if (selectElement.length) {
                // Pastikan opsi khusus hanya ditambahkan sekali
                if (selectElement.find('option[value="__add_new__"]').length === 0) {
                    selectElement.prepend('<option value="__add_new__" data-add-new="true">Tambah Data User Baru</option>');
                }

                // Jangan re-init Select2 kalau sudah terinisialisasi (mencegah duplikasi item)
                if (!selectElement.data('select2')) {
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
                }

                // Handle "Tambah Baru" option click (hindari handler ganda)
                selectElement.off('select2:select.add_new_user').on('select2:select.add_new_user', function(e) {
                    if (e.params && e.params.data && e.params.data.id === '__add_new__') {
                        if (typeof window.showAddUserModal === 'function') {
                            window.showAddUserModal();
                        } else {
                            showAddUserModal();
                        }
                        // Reset dropdown
                        selectElement.val('').trigger('change');
                    }
                });
            }

        // Modal untuk tambah user baru
        function showAddUserModal() {
            Swal.fire({
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

        // Expose agar bisa dipakai script lain jika diperlukan
        window.showAddUserModal = showAddUserModal;

        });  // Close $(document).ready(function() {
    </script>

    <script>
        // Flash Swal (after POST) - run independently of the big jQuery block
        (function() {
            const swalIcon = <?php echo json_encode($swal_icon ?? '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
            const swalTitle = <?php echo json_encode($swal_title ?? '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
            const swalMessage = <?php echo json_encode($swal_message ?? '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
            const redirectAfter = <?php echo isset($redirect_after) && $redirect_after ? 'true' : 'false'; ?>;

            if (!swalIcon || !swalTitle || !swalMessage) {
                return;
            }

            if (typeof Swal === 'undefined') {
                alert(swalTitle + "\n\n" + swalMessage);
                if (redirectAfter) {
                    window.location.href = 'index.php';
                }
                return;
            }

            // Success: auto-redirect to index.php
            if (redirectAfter && swalIcon === 'success') {
                Swal.fire({
                    icon: swalIcon,
                    title: swalTitle,
                    text: swalMessage,
                    timer: 1400,
                    showConfirmButton: false,
                    allowOutsideClick: false
                }).then(function() {
                    window.location.href = 'index.php';
                });
                return;
            }

            // Other cases (error/warn): user acknowledges
            Swal.fire({
                icon: swalIcon,
                title: swalTitle,
                text: swalMessage,
                confirmButtonText: 'OK'
            }).then(function() {
                if (redirectAfter) {
                    window.location.href = 'index.php';
                }
            });
        })();
    </script>

    <script>
        // (removed) Duplicate Select2 initializer for .select-user-perangkat
    </script>
</body>
</html>