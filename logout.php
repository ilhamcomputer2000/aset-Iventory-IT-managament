<?php
session_start();

// Hosting hardening: load app_url.php for redirect helper
$__appUrlPath = __DIR__ . '/app_url.php';
if (is_file($__appUrlPath)) {
    require_once $__appUrlPath;
}
else {
    if (!function_exists('app_base_path_from_docroot')) {
        function app_base_path_from_docroot(): string
        {
            $scriptName = (string)($_SERVER['SCRIPT_NAME'] ?? ($_SERVER['PHP_SELF'] ?? ''));
            if ($scriptName === '')
                return '';
            $scriptWebDir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
            if ($scriptWebDir === '' || $scriptWebDir === '.' || $scriptWebDir === '/')
                $scriptWebDir = '';
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
}

// Log user activity before destroying session
if (isset($_SESSION['user_id'])) {
    // $conn = new mysqli("localhost:3306", "cktnosa2_admin", "uGXj8#eiI=P%", "cktnosa2_crud");
    $conn = new mysqli("localhost", "root", "", "crud");
    if (!$conn->connect_error && function_exists('logUserActivity')) {
        logUserActivity($conn, $_SESSION['user_id'], $_SESSION['username'], $_SESSION['Nama_Lengkap'], "Logout");
    }
    if ($conn && !$conn->connect_error)
        $conn->close();
}

session_unset();
session_destroy();
header('Location: ' . app_abs_path('login'));
exit;
?>
