<?php
// Ticket page (user)
session_start();
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
            if ($scriptWebDir === '' || $scriptWebDir === '.' || $scriptWebDir === '/') $scriptWebDir = '';
            if ($scriptWebDir !== '' && preg_match('#/(user|admin)$#', $scriptWebDir)) {
                $scriptWebDir = (string)preg_replace('#/(user|admin)$#', '', $scriptWebDir);
                $scriptWebDir = rtrim($scriptWebDir, '/');
                if ($scriptWebDir === '' || $scriptWebDir === '/') $scriptWebDir = '';
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
            if ($host === '') return app_abs_path('');
            return $scheme . '://' . $host . app_abs_path('');
        }
    }
}
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . app_abs_path('login'));
    exit();
}

$user_role = isset($_SESSION['role']) ? (string)$_SESSION['role'] : 'user';
if ($user_role !== 'user') {
    // If admin reaches here, send them to admin ticket.
    header('Location: ' . app_abs_path('admin/ticket'));
    exit();
}

$username = isset($_SESSION['username']) ? (string)$_SESSION['username'] : 'User';
?><!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Ticket - User</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" />
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
<div class="flex min-h-screen">
    <!-- Sidebar (simple, consistent with other simple sidebar pages) -->
    <div class="w-64 bg-gray-800 text-white fixed h-full">
        <div class="p-4 text-center text-lg font-bold flex items-center justify-center">
            Asset PT CIPTA KARYA TECHNOLOGY
        </div>
        <div class="p-4 flex items-center space-x-3 border-b border-gray-700">
            <i class="fas fa-user-circle"></i>
            <div>
                <span class="block"><?php echo htmlspecialchars($username); ?></span>
            </div>
        </div>
        <nav class="mt-6">
            <a href="<?php echo htmlspecialchars(app_abs_path('dashboard_user')); ?>" class="block py-2 px-4 hover:bg-gray-700 transition-colors duration-200">
                <i class="fas fa-tachometer-alt mr-1"></i> Dashboard
            </a>
            <a href="<?php echo htmlspecialchars(app_abs_path('user/view')); ?>" class="block py-2 px-4 hover:bg-gray-700 transition-colors duration-200">
                <i class="fas fa-cogs mr-0"></i> Assets IT
            </a>
            <a href="<?php echo htmlspecialchars(app_abs_path('user/lacak_asset')); ?>" class="block py-2 px-4 hover:bg-gray-700 transition-colors duration-200">
                <i class="fas fa-search-location mr-1"></i> Lacak Asset
            </a>
            <a href="<?php echo htmlspecialchars(app_abs_path('user/ticket')); ?>" class="block py-2 px-4 bg-gray-700 transition-colors duration-200">
                <i class="fas fa-ticket-alt mr-2"></i> Ticket
            </a>
            <a href="<?php echo htmlspecialchars(app_abs_path('logout')); ?>" class="block py-2 px-4 hover:bg-gray-700 transition-colors duration-200">
                <i class="fas fa-sign-out-alt mr-1"></i> Logout
            </a>
        </nav>
    </div>

    <!-- Content -->
    <main class="flex-1 ml-64 p-6">
        <div class="max-w-4xl mx-auto">
            <h1 class="text-2xl font-bold text-gray-900 mb-2">Menu Ticket</h1>
            <p class="text-gray-600 mb-6">Halaman ticket (user) sudah tersedia. Silakan tentukan alur ticket (buat ticket, lihat status, dsb).</p>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-start gap-4">
                    <div class="w-10 h-10 rounded-lg bg-orange-100 flex items-center justify-center">
                        <i class="fas fa-ticket-alt text-orange-600"></i>
                    </div>
                    <div>
                        <h2 class="font-semibold text-gray-900">Coming soon</h2>
                        <p class="text-gray-700 mt-1">Menu sudah terpasang di sidebar user. Fitur ticket belum dibuat.</p>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
</body>
</html>
