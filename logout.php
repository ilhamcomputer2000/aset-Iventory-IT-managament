<?php
session_start();

// Hosting hardening: load app_url.php for redirect helper
$__appUrlPath = __DIR__ . '/app_url.php';
if (is_file($__appUrlPath)) {
    require_once $__appUrlPath;
} else {
    if (!function_exists('app_base_path_from_docroot')) {
        function app_base_path_from_docroot(): string {
            $scriptName = (string)($_SERVER['SCRIPT_NAME'] ?? ($_SERVER['PHP_SELF'] ?? ''));
            if ($scriptName === '') return '';
            $scriptWebDir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
            if ($scriptWebDir === '' || $scriptWebDir === '.' || $scriptWebDir === '/') $scriptWebDir = '';
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
}

// Log Logout activity before destroying session
if (isset($_SESSION['user_id'])) {
    // Load DB connection
    $__koneksiPath = __DIR__ . '/koneksi.php';
    if (is_file($__koneksiPath)) {
        require_once $__koneksiPath;
    } else {
        $kon = @new mysqli('localhost', 'root', '', 'crud');
    }
    $__dbConn = isset($kon) && $kon instanceof mysqli ? $kon : (isset($conn) && $conn instanceof mysqli ? $conn : null);

    // Load log helper
    $__logActivityPath = __DIR__ . '/admin/log_activity.php';
    if ($__dbConn && is_file($__logActivityPath) && !function_exists('logUserActivity')) {
        require_once $__logActivityPath;
    }

    if ($__dbConn && function_exists('logUserActivity')) {
        logUserActivity(
            $__dbConn,
            (int)$_SESSION['user_id'],
            $_SESSION['username']    ?? '',
            $_SESSION['role']        ?? '',
            'Logout'
        );
    }

    if (isset($__dbConn) && $__dbConn instanceof mysqli) {
        @$__dbConn->close();
    }
}

session_unset();
session_destroy();
header('Location: ' . app_abs_path('login'));
exit;
?>
