<?php
/**
 * Shared Admin Sidebar + Navbar Component
 * 
 * Usage: Set $activePage before including this file.
 * Example:
 *   $activePage = 'dashboard'; // or 'assets', 'lacak', 'serah_terima', 'ticket', 'request_pinjaman'
 *   require_once __DIR__ . '/sidebar_admin.php';
 * 
 * Requires: $Nama_Lengkap, $Jabatan_Level (from session or parent page)
 */

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

$activePage = isset($activePage) ? (string) $activePage : '';
$Nama_Lengkap = isset($Nama_Lengkap) ? (string) $Nama_Lengkap : 'Admin';
$Jabatan_Level = isset($Jabatan_Level)
    ? (string) $Jabatan_Level
    : trim((string) ($_SESSION['Jabatan_Level'] ?? ''));

// Fallback: jika session Jabatan_Level kosong, coba ambil dari DB
if ($Jabatan_Level === '' && isset($_SESSION['user_id'])) {
    $db = null;
    if (isset($kon) && $kon instanceof mysqli) {
        $db = $kon;
    } elseif (isset($conn) && $conn instanceof mysqli) {
        $db = $conn;
    }

    if ($db instanceof mysqli) {
        $stmtMeta = $db->prepare("SELECT Jabatan_Level, Nama_Lengkap, role, profile_picture FROM users WHERE id = ? LIMIT 1");
        if ($stmtMeta) {
            $uid = (int) $_SESSION['user_id'];
            $stmtMeta->bind_param('i', $uid);
            if ($stmtMeta->execute()) {
                $jabDb = null;
                $namaDb = null;
                $roleDb = null;
                $picDb = null;
                $stmtMeta->bind_result($jabDb, $namaDb, $roleDb, $picDb);
                if ($stmtMeta->fetch()) {
                    $jab = trim((string) ($jabDb ?? ''));
                    if ($jab !== '') {
                        $Jabatan_Level = $jab;
                        $_SESSION['Jabatan_Level'] = $jab;
                    }

                    if (empty($_SESSION['Nama_Lengkap']) && !empty($namaDb)) {
                        $_SESSION['Nama_Lengkap'] = (string) $namaDb;
                    }
                    if (empty($_SESSION['role']) && !empty($roleDb)) {
                        $_SESSION['role'] = (string) $roleDb;
                    }
                    if (!empty($picDb)) {
                        $_SESSION['profile_picture'] = (string) $picDb;
                    }
                }
            }
            $stmtMeta->close();
        }
    }
}

$Jabatan_Level = $Jabatan_Level !== '' ? $Jabatan_Level : '-';

// Profile picture path for sidebar avatar
$_sidebarProfilePic = '';
if (!empty($_SESSION['profile_picture'])) {
    $_sidebarProfilePic = app_abs_path($_SESSION['profile_picture']);
} elseif (isset($_SESSION['user_id'])) {
    // Query from DB if not in session yet — reuse existing connection using ping with Throwable catch
    $_picConn = null;
    try {
        if (isset($kon) && $kon instanceof mysqli && @$kon->ping()) {
            $_picConn = $kon;
        } elseif (isset($conn) && $conn instanceof mysqli && @$conn->ping()) {
            $_picConn = $conn;
        }
    } catch (\Throwable $e) {
        $_picConn = null;
    }

    if (!$_picConn) {
        // Fallback: include koneksi.php to get a connection safely
        @require_once __DIR__ . '/../koneksi.php';
        try {
            if (isset($kon) && $kon instanceof mysqli && @$kon->ping()) {
                $_picConn = $kon;
            }
        } catch (\Throwable $e) {}
    }

    if ($_picConn) {
        try {
            $_stmtPic = @$_picConn->prepare("SELECT profile_picture FROM users WHERE id = ? LIMIT 1");
            if ($_stmtPic) {
                $_uid = (int)$_SESSION['user_id'];
                $_stmtPic->bind_param('i', $_uid);
                $_stmtPic->execute();
                $_picVal = null;
                $_stmtPic->bind_result($_picVal);
                if ($_stmtPic->fetch() && !empty($_picVal)) {
                    $_SESSION['profile_picture'] = (string)$_picVal;
                    $_sidebarProfilePic = app_abs_path($_picVal);
                }
                $_stmtPic->close();
            }
        } catch (\Throwable $e) {
            // Silently ignore

        }
    }
}

$isDashboard = ($activePage === 'dashboard');
$isAssets = ($activePage === 'assets');
$isLacak = ($activePage === 'lacak');
$isSerahTerima = ($activePage === 'serah_terima');
$isTicket = ($activePage === 'ticket');
$isRequestPinjaman = ($activePage === 'request_pinjaman');
$isProfile = ($activePage === 'profile');
$isAddAkun = ($activePage === 'add_akun');
$isLog = ($activePage === 'log');
$isEventDashboard = ($activePage === 'event_dashboard');
$isSettingsSubPage = ($isProfile || $isAddAkun || $isLog);

// Build sidebar links
$_sidebarBaseDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
// Ensure we're pointing to the /admin/ directory
if (basename($_sidebarBaseDir) !== 'admin') {
    $_sidebarBaseDir = dirname($_sidebarBaseDir) . '/admin';
}
$_sidebarBaseDir = rtrim($_sidebarBaseDir, '/');

$_adminLinks = [
    'dashboard' => $_sidebarBaseDir . '/dashboard_admin.php',
    'assets' => $_sidebarBaseDir . '/index.php',
    'lacak' => $_sidebarBaseDir . '/lacak_asset.php',
    'serah_terima' => $_sidebarBaseDir . '/serah_terima.php',
    'ticket' => $_sidebarBaseDir . '/ticket.php',
    'request_pinjaman' => $_sidebarBaseDir . '/request_pinjaman.php',
    'event_dashboard' => $_sidebarBaseDir . '/dashboard_event.php',
    'profile' => $_sidebarBaseDir . '/profile.php',
    'add_akun' => $_sidebarBaseDir . '/add_akun.php',
    'log' => $_sidebarBaseDir . '/log.php',
    'logout' => app_abs_path('logout.php'),
];
?>

<!-- Overlay untuk mobile -->
<div id="overlay"
    class="fixed inset-0 bg-black/60 backdrop-blur-sm z-40 lg:hidden transition-opacity duration-300 opacity-0 pointer-events-none">
</div>

<!-- Global CSS: Sidebar Collapse Responsiveness -->
<style>
    /* When desktop sidebar is open (default): content has left margin = sidebar width */
    @media (min-width: 1024px) {
        .sidebar-content-push {
            margin-left: 15rem;
            /* 240px = w-60 */
            transition: margin-left 0.3s ease;
            min-width: 0;
            /* Prevent content from overflowing on zoom-out */
        }

        /* When sidebar is collapsed via hamburger: slide sidebar out, content takes full width */
        body.sidebar-collapsed #sidebar {
            transform: translateX(-100%);
        }

        body.sidebar-collapsed .sidebar-content-push {
            margin-left: 0 !important;
        }

        /* Keep sidebar transition smooth */
        #sidebar {
            transition: transform 0.3s ease;
        }
    }

    /* Global fix: prevent horizontal overflow on all screen sizes */
    body {
        overflow-x: hidden;
    }
</style>

<!-- Sidebar -->
<aside id="sidebar"
    class="fixed top-0 left-0 h-screen w-60 bg-white border-r border-gray-200 z-50 transform -translate-x-full lg:translate-x-0 shadow-lg lg:shadow-none overflow-y-auto">
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
            <?php if ($_sidebarProfilePic): ?>
                <img src="<?php echo htmlspecialchars($_sidebarProfilePic); ?>" alt=""
                    class="w-8 h-8 rounded-lg object-cover flex-shrink-0">
            <?php else: ?>
                <div
                    class="w-8 h-8 bg-gradient-to-br from-blue-400 to-purple-500 rounded-lg flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-user text-white text-xs"></i>
                </div>
            <?php endif; ?>
            <div class="min-w-0">
                <span
                    class="text-sm font-semibold text-gray-900 block truncate"><?php echo htmlspecialchars($Nama_Lengkap); ?></span>
                <span
                    class="text-[11px] text-gray-500 truncate block"><?php echo htmlspecialchars($Jabatan_Level); ?></span>
            </div>
        </div>
    </div>

    <!-- Navigation Menu -->
    <nav class="mt-2 px-3 pb-4">
        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider px-3 mb-2">MENU</p>

        <a href="<?php echo htmlspecialchars($_adminLinks['dashboard']); ?>"
            class="flex items-center space-x-3 py-2.5 px-3 rounded-lg mb-1 transition-all duration-200 <?php echo $isDashboard ? 'bg-orange-50 text-orange-700 border-l-[3px] border-orange-500' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'; ?>">
            <i
                class="fas fa-tachometer-alt text-base w-5 text-center <?php echo $isDashboard ? 'text-orange-500' : 'text-gray-400'; ?>"></i>
            <span class="text-sm <?php echo $isDashboard ? 'font-semibold' : 'font-medium'; ?>">Dashboard</span>
        </a>

        <a href="<?php echo htmlspecialchars($_adminLinks['assets']); ?>"
            class="flex items-center space-x-3 py-2.5 px-3 rounded-lg mb-1 transition-all duration-200 <?php echo $isAssets ? 'bg-orange-50 text-orange-700 border-l-[3px] border-orange-500' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'; ?>">
            <i
                class="fas fa-cogs text-base w-5 text-center <?php echo $isAssets ? 'text-orange-500' : 'text-gray-400'; ?>"></i>
            <span class="text-sm <?php echo $isAssets ? 'font-semibold' : 'font-medium'; ?>">Assets IT</span>
        </a>

        <a href="<?php echo htmlspecialchars($_adminLinks['lacak']); ?>"
            class="flex items-center space-x-3 py-2.5 px-3 rounded-lg mb-1 transition-all duration-200 <?php echo $isLacak ? 'bg-orange-50 text-orange-700 border-l-[3px] border-orange-500' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'; ?>">
            <i
                class="fas fa-search-location text-base w-5 text-center <?php echo $isLacak ? 'text-orange-500' : 'text-gray-400'; ?>"></i>
            <span class="text-sm <?php echo $isLacak ? 'font-semibold' : 'font-medium'; ?>">Lacak Asset</span>
        </a>

        <a href="<?php echo htmlspecialchars($_adminLinks['serah_terima']); ?>"
            class="flex items-center space-x-3 py-2.5 px-3 rounded-lg mb-1 transition-all duration-200 <?php echo $isSerahTerima ? 'bg-orange-50 text-orange-700 border-l-[3px] border-orange-500' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'; ?>">
            <i
                class="fas fa-file-alt text-base w-5 text-center <?php echo $isSerahTerima ? 'text-orange-500' : 'text-gray-400'; ?>"></i>
            <span class="text-sm <?php echo $isSerahTerima ? 'font-semibold' : 'font-medium'; ?>">Form Serah
                Terima</span>
        </a>

        <a href="<?php echo htmlspecialchars($_adminLinks['ticket']); ?>"
            class="flex items-center space-x-3 py-2.5 px-3 rounded-lg mb-1 transition-all duration-200 <?php echo $isTicket ? 'bg-orange-50 text-orange-700 border-l-[3px] border-orange-500' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'; ?>">
            <i
                class="fas fa-ticket-alt text-base w-5 text-center <?php echo $isTicket ? 'text-orange-500' : 'text-gray-400'; ?>"></i>
            <span class="text-sm <?php echo $isTicket ? 'font-semibold' : 'font-medium'; ?>">Ticket</span>
        </a>

        <a href="<?php echo htmlspecialchars($_adminLinks['request_pinjaman']); ?>"
            class="flex items-center space-x-3 py-2.5 px-3 rounded-lg mb-1 transition-all duration-200 <?php echo $isRequestPinjaman ? 'bg-orange-50 text-orange-700 border-l-[3px] border-orange-500' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'; ?>">
            <i
                class="fas fa-hand-holding text-base w-5 text-center <?php echo $isRequestPinjaman ? 'text-orange-500' : 'text-gray-400'; ?>"></i>
            <span class="text-sm <?php echo $isRequestPinjaman ? 'font-semibold' : 'font-medium'; ?>">Request
                Pinjaman</span>
        </a>

        <a href="<?php echo htmlspecialchars($_adminLinks['event_dashboard']); ?>"
            class="flex items-center space-x-3 py-2.5 px-3 rounded-lg mb-1 transition-all duration-200 <?php echo $isEventDashboard ? 'bg-orange-50 text-orange-700 border-l-[3px] border-orange-500' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'; ?>">
            <i
                class="fas fa-calendar-alt text-base w-5 text-center <?php echo $isEventDashboard ? 'text-orange-500' : 'text-gray-400'; ?>"></i>
            <span class="text-sm <?php echo $isEventDashboard ? 'font-semibold' : 'font-medium'; ?>">Event Dashboard</span>
        </a>

        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider px-3 mt-4 mb-2">SETTINGS</p>

        <!-- Settings Dropdown -->
        <div class="relative">
            <button id="settings-toggle"
                class="w-full flex items-center space-x-3 py-2.5 px-3 rounded-lg mb-1 transition-all duration-200 <?php echo $isSettingsSubPage ? 'bg-orange-50 text-orange-700' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'; ?>">
                <i
                    class="fas fa-cog text-base w-5 text-center <?php echo $isSettingsSubPage ? 'text-orange-500' : 'text-gray-400'; ?>"></i>
                <span
                    class="text-sm <?php echo $isSettingsSubPage ? 'font-semibold' : 'font-medium'; ?> flex-1 text-left">Settings</span>
                <i id="settings-arrow"
                    class="fas fa-chevron-down text-xs <?php echo $isSettingsSubPage ? 'text-orange-400' : 'text-gray-400'; ?> transition-transform duration-300<?php echo $isSettingsSubPage ? ' rotate-180' : ''; ?>"></i>
            </button>

            <!-- Submenu -->
            <ul id="settings-submenu" class="overflow-hidden transition-all duration-300 ease-in-out"
                style="<?php echo $isSettingsSubPage ? 'max-height: 300px; opacity: 1;' : 'max-height: 0; opacity: 0;'; ?>">
                <li>
                    <a href="<?php echo htmlspecialchars($_adminLinks['profile']); ?>"
                        class="w-full flex items-center space-x-3 py-2 px-3 pl-11 text-sm transition-all duration-200 rounded-lg <?php echo $isProfile ? 'bg-orange-50 text-orange-700 font-semibold' : 'text-gray-500 hover:bg-gray-100 hover:text-gray-900'; ?>">
                        <i class="fas fa-user text-xs <?php echo $isProfile ? 'text-orange-500' : ''; ?>"></i>
                        <span>Profile</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo htmlspecialchars($_adminLinks['add_akun']); ?>"
                        class="w-full flex items-center space-x-3 py-2 px-3 pl-11 text-sm transition-all duration-200 rounded-lg <?php echo $isAddAkun ? 'bg-orange-50 text-orange-700 font-semibold' : 'text-gray-500 hover:bg-gray-100 hover:text-gray-900'; ?>">
                        <i class="fas fa-user-plus text-xs <?php echo $isAddAkun ? 'text-orange-500' : ''; ?>"></i>
                        <span>Add Account</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo htmlspecialchars($_adminLinks['log']); ?>"
                        class="w-full flex items-center space-x-3 py-2 px-3 pl-11 text-sm transition-all duration-200 rounded-lg <?php echo $isLog ? 'bg-orange-50 text-orange-700 font-semibold' : 'text-gray-500 hover:bg-gray-100 hover:text-gray-900'; ?>">
                        <i class="fas fa-history text-xs <?php echo $isLog ? 'text-orange-500' : ''; ?>"></i>
                        <span>Activity Log</span>
                    </a>
                </li>
            </ul>
        </div>

        <a href="<?php echo htmlspecialchars($_adminLinks['logout']); ?>"
            class="flex items-center space-x-3 py-2.5 px-3 rounded-lg mb-1 transition-all duration-200 text-red-500 hover:bg-red-50 hover:text-red-600">
            <i class="fas fa-sign-out-alt text-base w-5 text-center"></i>
            <span class="text-sm font-medium">Logout</span>
        </a>
    </nav>

</aside>

<!-- Page Footer: Copyright (outside sidebar, at bottom of content area) -->
<footer id="page-footer"
    class="fixed bottom-0 z-20 border-t border-gray-200 bg-white/95 backdrop-blur-sm px-5 py-2 flex items-center justify-between">
    <div class="flex items-center gap-2">
        <div
            class="w-4 h-4 bg-gradient-to-br from-orange-400 to-orange-600 rounded flex items-center justify-center flex-shrink-0">
            <i class="fas fa-microchip text-white" style="font-size:7px"></i>
        </div>
        <span class="text-[10px] font-bold text-gray-700">PT CIPTA KARYA TECHNOLOGY</span>
    </div>
    <div class="flex items-center gap-3">
        <span class="text-[9px] text-gray-400">&copy; <?php echo date('Y'); ?> All rights reserved</span>
        <span class="text-[9px] font-semibold text-orange-400 bg-orange-50 px-1.5 py-0.5 rounded">v1.2</span>
    </div>
</footer>

<!-- Sidebar responsive styles -->
<style>
    /* Hamburger always visible */
    #hamburger-btn {
        display: flex !important;
    }

    /* Mobile-first: navbar & footer full-width (no sidebar offset) */
    #sidebar-navbar {
        left: 0;
        right: 0;
    }

    #page-footer {
        left: 0 !important;
        right: 0;
        transition: left 0.3s ease;
    }

    /* Desktop: navbar & footer offset when sidebar is open */
    @media (min-width: 1024px) {
        #sidebar-navbar {
            left: 15rem;
            right: 0;
            transition: left 0.3s ease;
        }

        #page-footer {
            left: 15rem !important;
            right: 0;
        }

        /* When sidebar is collapsed on desktop */
        body.sidebar-collapsed #sidebar-navbar {
            left: 0;
        }

        body.sidebar-collapsed #sidebar {
            transform: translateX(-100%) !important;
        }

        body.sidebar-collapsed #page-footer {
            left: 0 !important;
        }
    }
</style>

<!-- Navbar -->
<nav id="sidebar-navbar" class="bg-gradient-to-r from-orange-500 to-orange-600 shadow-lg fixed z-30 top-0">
    <div class="px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-14">
            <div class="flex items-center space-x-4">
                <button id="hamburger-btn"
                    class="p-3 rounded-lg bg-white/20 backdrop-blur-sm text-white shadow-md hover:bg-white/30 transition-all duration-300 hover:scale-105 items-center justify-center min-w-[44px] min-h-[44px]">
                    <i id="hamburger-icon" class="fas fa-bars text-lg"></i>
                </button>
            </div>
            <div class="flex items-center space-x-3">
                <!-- Notification Bell -->
                <button id="notif-bell-btn"
                    class="relative p-3 rounded-lg bg-white/20 backdrop-blur-sm text-white shadow-md hover:bg-white/30 transition-all duration-300 hover:scale-105 items-center justify-center min-w-[44px] min-h-[44px]"
                    title="Notifikasi">
                    <i class="fas fa-bell text-lg"></i>
                    <span id="notif-badge"
                        class="hidden absolute -top-1 -right-1 min-w-[20px] h-5 px-1 bg-red-500 text-white text-[11px] font-bold rounded-full flex items-center justify-center ring-2 ring-orange-500 animate-pulse">0</span>
                </button>
            </div>
        </div>
    </div>
</nav>

<!-- Notification Panel Overlay -->
<div id="notif-overlay"
    class="fixed inset-0 bg-black/40 backdrop-blur-sm z-[60] transition-opacity duration-300 opacity-0 pointer-events-none">
</div>

<!-- Notification Slide Panel -->
<div id="notif-panel"
    class="fixed top-0 right-0 h-full w-full sm:w-[400px] bg-white/95 backdrop-blur-xl z-[61] shadow-2xl transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col">
    <!-- Panel Header -->
    <div
        class="flex items-center justify-between px-5 py-4 border-b border-gray-200 bg-gradient-to-r from-orange-500 to-orange-600">
        <div class="flex items-center space-x-3">
            <div class="w-9 h-9 rounded-xl bg-white/20 flex items-center justify-center">
                <i class="fas fa-bell text-white text-sm"></i>
            </div>
            <div>
                <h3 class="text-white font-bold text-base">Notifikasi</h3>
                <p id="notif-subtitle" class="text-orange-100 text-xs">0 belum dibaca</p>
            </div>
        </div>
        <div class="flex items-center space-x-2">
            <button id="notif-mark-all"
                class="px-3 py-1.5 bg-white/20 hover:bg-white/30 text-white text-xs font-medium rounded-lg transition-all duration-200"
                title="Tandai semua dibaca">
                <i class="fas fa-check-double mr-1"></i> Tandai Dibaca
            </button>
            <button id="notif-close"
                class="p-2 text-white/80 hover:text-white hover:bg-white/20 rounded-lg transition-all duration-200">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>
    </div>

    <!-- Panel Body -->
    <div id="notif-list" class="flex-1 overflow-y-auto">
        <!-- Loading state -->
        <div id="notif-loading" class="flex flex-col items-center justify-center py-16">
            <div class="w-10 h-10 border-3 border-orange-200 border-t-orange-500 rounded-full animate-spin mb-4"></div>
            <p class="text-gray-400 text-sm">Memuat notifikasi...</p>
        </div>
        <!-- Empty state -->
        <div id="notif-empty" class="hidden flex-col items-center justify-center py-16 px-6">
            <div class="w-20 h-20 rounded-full bg-orange-50 flex items-center justify-center mb-4">
                <i class="fas fa-bell-slash text-3xl text-orange-300"></i>
            </div>
            <p class="text-gray-500 font-medium text-base mb-1">Belum Ada Notifikasi</p>
            <p class="text-gray-400 text-sm text-center">Notifikasi akan muncul saat ada aktivitas dari user.</p>
        </div>
        <!-- Notification items container -->
        <div id="notif-items" class="divide-y divide-gray-100"></div>
    </div>
</div>

<!-- ===== POPUP TOAST NOTIFIKASI ===== -->
<style>
    #notif-toast-container {
        position: fixed;
        top: 70px;
        right: 16px;
        z-index: 9999;
        display: flex;
        flex-direction: column;
        gap: 10px;
        max-width: 360px;
        width: calc(100vw - 32px);
        pointer-events: none;
    }

    .notif-toast {
        pointer-events: all;
        background: #fff;
        border-radius: 14px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15), 0 2px 8px rgba(0, 0, 0, 0.08);
        border-left: 4px solid #f97316;
        overflow: hidden;
        transform: translateX(120%);
        opacity: 0;
        transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.3s ease;
        position: relative;
    }

    .notif-toast.show {
        transform: translateX(0);
        opacity: 1;
    }

    .notif-toast.hide {
        transform: translateX(120%);
        opacity: 0;
    }

    .notif-toast-inner {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 14px 40px 14px 16px;
        cursor: pointer;
    }

    .notif-toast-inner:hover {
        background: #fafafa;
    }

    .notif-toast-icon {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 14px;
    }

    .notif-toast-content {
        flex: 1;
        min-width: 0;
    }

    .notif-toast-title {
        font-size: 13px;
        font-weight: 700;
        color: #111827;
        overflow: hidden;
        white-space: nowrap;
        text-overflow: ellipsis;
        margin-bottom: 2px;
    }

    .notif-toast-msg {
        font-size: 11.5px;
        color: #6b7280;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        line-height: 1.4;
    }

    .notif-toast-time {
        font-size: 10px;
        color: #9ca3af;
        margin-top: 4px;
    }

    .notif-toast-close {
        position: absolute;
        top: 8px;
        right: 8px;
        width: 24px;
        height: 24px;
        border-radius: 50%;
        background: #f3f4f6;
        border: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        color: #6b7280;
        transition: background 0.2s, color 0.2s;
        z-index: 2;
    }

    .notif-toast-close:hover {
        background: #e5e7eb;
        color: #111827;
    }
</style>
<div id="notif-toast-container"></div>


<script>
    document.addEventListener('DOMContentLoaded', function () {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');
        const hamburgerBtn = document.getElementById('hamburger-btn');
        const closeSidebarBtn = document.getElementById('close-sidebar');
        const hamburgerIcon = document.getElementById('hamburger-icon');
        const settingsToggle = document.getElementById('settings-toggle');
        const settingsSubmenu = document.getElementById('settings-submenu');
        const settingsArrow = document.getElementById('settings-arrow');

        if (!sidebar || !hamburgerBtn) return;

        let mobileSidebarOpen = false;
        let settingsOpen = <?php echo $isSettingsSubPage ? 'true' : 'false'; ?>;
        const STORAGE_KEY = 'sidebarCollapsed';

        // --- Desktop sidebar state from localStorage ---
        function isDesktop() { return window.innerWidth >= 1024; }

        function applyDesktopState() {
            var collapsed = localStorage.getItem(STORAGE_KEY) === '1';
            if (collapsed) {
                document.body.classList.add('sidebar-collapsed');
            } else {
                document.body.classList.remove('sidebar-collapsed');
            }
            updateIcon();
            // Footer position is handled purely by CSS:
            //   body.sidebar-collapsed #page-footer { left: 0 !important } @ desktop
            // No inline style needed — this avoids overriding mobile media queries
            // Dispatch event so content wrappers can react
            window.dispatchEvent(new CustomEvent('sidebarToggled', { detail: { collapsed: collapsed } }));
        }

        function updateIcon() {
            if (!hamburgerIcon) return;
            // Always show bars icon
            hamburgerIcon.classList.add('fa-bars');
            hamburgerIcon.classList.remove('fa-times');
        }

        // Initialize desktop state on load (before transition kicks in)
        if (isDesktop()) {
            applyDesktopState();
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
            updateIcon();
        }

        function closeMobileSidebar() {
            mobileSidebarOpen = false;
            sidebar.classList.add('-translate-x-full');
            sidebar.classList.remove('translate-x-0');
            if (overlay) {
                overlay.classList.add('opacity-0', 'pointer-events-none');
                overlay.classList.remove('opacity-100');
            }
            updateIcon();
        }

        // --- Hamburger click: works for both mobile and desktop ---
        hamburgerBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (isDesktop()) {
                // Toggle desktop collapse
                var nowCollapsed = !document.body.classList.contains('sidebar-collapsed');
                localStorage.setItem(STORAGE_KEY, nowCollapsed ? '1' : '0');
                applyDesktopState();
            } else {
                // Mobile toggle
                if (mobileSidebarOpen) {
                    closeMobileSidebar();
                } else {
                    openMobileSidebar();
                }
            }
        });

        // Close sidebar button (mobile)
        if (closeSidebarBtn) {
            closeSidebarBtn.addEventListener('click', function (e) {
                e.preventDefault();
                closeMobileSidebar();
            });
        }

        // Close sidebar when clicking overlay
        if (overlay) {
            overlay.addEventListener('click', function () {
                closeMobileSidebar();
            });
        }

        // Settings dropdown toggle
        if (settingsToggle) {
            settingsToggle.addEventListener('click', function (e) {
                e.preventDefault();
                settingsOpen = !settingsOpen;
                if (settingsOpen) {
                    settingsSubmenu.style.maxHeight = '300px';
                    settingsSubmenu.style.opacity = '1';
                    settingsArrow.style.transform = 'rotate(180deg)';
                } else {
                    settingsSubmenu.style.maxHeight = '0';
                    settingsSubmenu.style.opacity = '0';
                    settingsArrow.style.transform = 'rotate(0deg)';
                }
            });
        }

        // Handle window resize
        window.addEventListener('resize', function () {
            if (isDesktop()) {
                // On desktop, sidebar visibility is controlled by body.sidebar-collapsed
                // Reset mobile state
                mobileSidebarOpen = false;
                sidebar.classList.remove('-translate-x-full');
                sidebar.classList.add('translate-x-0');
                if (overlay) {
                    overlay.classList.add('opacity-0', 'pointer-events-none');
                    overlay.classList.remove('opacity-100');
                }
                applyDesktopState();
            } else {
                // On mobile, remove desktop collapsed class
                document.body.classList.remove('sidebar-collapsed');
                if (!mobileSidebarOpen) {
                    sidebar.classList.add('-translate-x-full');
                    sidebar.classList.remove('translate-x-0');
                }
                updateIcon();
            }
        });
    });
</script>

<!-- Notification JS -->
<script>
    (function () {
        const bellBtn = document.getElementById('notif-bell-btn');
        const panel = document.getElementById('notif-panel');
        const overlay = document.getElementById('notif-overlay');
        const closeBtn = document.getElementById('notif-close');
        const markAllBtn = document.getElementById('notif-mark-all');
        const badge = document.getElementById('notif-badge');
        const subtitle = document.getElementById('notif-subtitle');
        const loadingEl = document.getElementById('notif-loading');
        const emptyEl = document.getElementById('notif-empty');
        const itemsEl = document.getElementById('notif-items');

        if (!bellBtn || !panel) return;

        let isOpen = false;
        const API_URL = '<?php echo app_abs_path("admin/api_notifications.php"); ?>';

        function getNavUrl(type) {
            const map = {
                'ticket_created': '<?php echo app_abs_path("admin/ticket.php"); ?>',
                'ticket_closed': '<?php echo app_abs_path("admin/ticket.php"); ?>',
                'ticket_status_changed': '<?php echo app_abs_path("admin/ticket.php"); ?>',
                'ticket_approval_needed': '<?php echo app_abs_path("admin/ticket.php"); ?>',
                'pinjaman_request': '<?php echo app_abs_path("admin/request_pinjaman.php"); ?>',
                'pinjaman_approved': '<?php echo app_abs_path("admin/request_pinjaman.php"); ?>',
                'pinjaman_rejected': '<?php echo app_abs_path("admin/request_pinjaman.php"); ?>'
            };
            return map[type] || null;
        }

        function openPanel() {
            isOpen = true;
            panel.classList.remove('translate-x-full');
            panel.classList.add('translate-x-0');
            overlay.classList.remove('opacity-0', 'pointer-events-none');
            overlay.classList.add('opacity-100');
            fetchNotifications();
        }

        function closePanel() {
            isOpen = false;
            panel.classList.add('translate-x-full');
            panel.classList.remove('translate-x-0');
            overlay.classList.add('opacity-0', 'pointer-events-none');
            overlay.classList.remove('opacity-100');
        }

        bellBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            isOpen ? closePanel() : openPanel();
        });

        closeBtn.addEventListener('click', closePanel);
        overlay.addEventListener('click', closePanel);

        function updateBadge(count) {
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count;
                badge.classList.remove('hidden');
                badge.classList.add('flex');
            } else {
                badge.classList.add('hidden');
                badge.classList.remove('flex');
            }
            subtitle.textContent = count + ' belum dibaca';
        }

        function typeIcon(type) {
            const map = {
                'ticket_created': { icon: 'fa-ticket-alt', color: 'bg-blue-100 text-blue-600' },
                'ticket_closed': { icon: 'fa-check-circle', color: 'bg-gray-100 text-gray-600' },
                'ticket_status_changed': { icon: 'fa-exchange-alt', color: 'bg-orange-100 text-orange-600' },
                'ticket_approval_needed': { icon: 'fa-clipboard-check', color: 'bg-amber-100 text-amber-600' },
                'pinjaman_request': { icon: 'fa-hand-holding', color: 'bg-purple-100 text-purple-600' },
                'pinjaman_approved': { icon: 'fa-check', color: 'bg-green-100 text-green-600' },
                'pinjaman_rejected': { icon: 'fa-times', color: 'bg-red-100 text-red-600' }
            };
            return map[type] || { icon: 'fa-info-circle', color: 'bg-gray-100 text-gray-600' };
        }

        function timeAgo(dateStr) {
            const now = new Date();
            const d = new Date(dateStr.replace(' ', 'T'));
            const diff = Math.floor((now - d) / 1000);
            if (diff < 60) return 'Baru saja';
            if (diff < 3600) return Math.floor(diff / 60) + ' menit lalu';
            if (diff < 86400) return Math.floor(diff / 3600) + ' jam lalu';
            if (diff < 604800) return Math.floor(diff / 86400) + ' hari lalu';
            return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
        }

        function renderNotifications(data) {
            loadingEl.style.display = 'none';
            const notifs = data.notifications || [];
            updateBadge(data.unread_count || 0);

            if (notifs.length === 0) {
                emptyEl.classList.remove('hidden');
                emptyEl.classList.add('flex');
                itemsEl.innerHTML = '';
                return;
            }

            emptyEl.classList.add('hidden');
            emptyEl.classList.remove('flex');

            let html = '';
            notifs.forEach(function (n) {
                const ti = typeIcon(n.type);
                const unreadDot = n.is_read == 0 ? '<div class="w-2.5 h-2.5 rounded-full bg-blue-500 ring-2 ring-blue-100 flex-shrink-0"></div>' : '';
                const unreadBg = n.is_read == 0 ? 'bg-blue-50/50 hover:bg-blue-50' : 'hover:bg-gray-50';
                html += '<div class="notif-item flex items-start gap-3 px-5 py-4 cursor-pointer transition-all duration-200 ' + unreadBg + '" data-id="' + n.id + '" data-type="' + (n.type || '') + '">' +
                    '<div class="w-10 h-10 rounded-xl ' + ti.color + ' flex items-center justify-center flex-shrink-0 mt-0.5">' +
                    '  <i class="fas ' + ti.icon + ' text-sm"></i>' +
                    '</div>' +
                    '<div class="flex-1 min-w-0">' +
                    '  <div class="flex items-center justify-between gap-2">' +
                    '    <p class="text-sm font-semibold text-gray-900 truncate">' + (n.title || '') + '</p>' +
                    '    ' + unreadDot +
                    '  </div>' +
                    '  <p class="text-xs text-gray-500 mt-0.5 line-clamp-2">' + (n.message || '') + '</p>' +
                    '  <p class="text-[11px] text-gray-400 mt-1"><i class="fas fa-clock mr-1"></i>' + timeAgo(n.created_at) + '</p>' +
                    '</div>' +
                    '<div class="flex-shrink-0 self-center"><i class="fas fa-chevron-right text-gray-300 text-xs"></i></div>' +
                    '</div>';
            });
            itemsEl.innerHTML = html;

            // Click to mark as read + navigate
            itemsEl.querySelectorAll('.notif-item').forEach(function (el) {
                el.addEventListener('click', function () {
                    const id = el.getAttribute('data-id');
                    const type = el.getAttribute('data-type');
                    markRead(id, el);
                    const url = getNavUrl(type);
                    if (url) setTimeout(function () { window.location.href = url; }, 200);
                });
            });
        }

        function fetchNotifications() {
            loadingEl.style.display = 'flex';
            emptyEl.classList.add('hidden');
            itemsEl.innerHTML = '';
            fetch(API_URL + '?action=fetch')
                .then(r => r.json())
                .then(data => renderNotifications(data))
                .catch(err => {
                    loadingEl.style.display = 'none';
                    itemsEl.innerHTML = '<div class="px-5 py-8 text-center text-gray-400 text-sm"><i class="fas fa-exclamation-triangle mr-2"></i>Gagal memuat notifikasi</div>';
                });
        }

        function fetchBadgeOnly() {
            fetch(API_URL + '?action=fetch')
                .then(r => r.json())
                .then(data => updateBadge(data.unread_count || 0))
                .catch(() => { });
        }

        function markRead(id, el) {
            const fd = new FormData();
            fd.append('action', 'mark_read');
            fd.append('id', id);
            fetch(API_URL + '?action=mark_read', { method: 'POST', body: fd })
                .then(() => {
                    if (el) {
                        el.classList.remove('bg-blue-50/50', 'hover:bg-blue-50');
                        el.classList.add('hover:bg-gray-50');
                        const dot = el.querySelector('.bg-blue-500');
                        if (dot) dot.remove();
                    }
                    fetchBadgeOnly();
                })
                .catch(() => { });
        }

        markAllBtn.addEventListener('click', function () {
            const fd = new FormData();
            fd.append('action', 'mark_all_read');
            fetch(API_URL + '?action=mark_all_read', { method: 'POST', body: fd })
                .then(() => fetchNotifications())
                .catch(() => { });
        });

        // Initial badge fetch + periodic polling every 30s
        fetchBadgeOnly();
        setInterval(fetchBadgeOnly, 30000);

        // ===== POPUP TOAST SYSTEM =====
        const TOAST_SEEN_KEY = 'admin_notif_seen_ids';
        const toastContainer = document.getElementById('notif-toast-container');

        function getSeenIds() {
            try { return JSON.parse(localStorage.getItem(TOAST_SEEN_KEY) || '[]'); } catch { return []; }
        }
        function addSeenId(id) {
            const ids = getSeenIds();
            if (!ids.includes(id)) {
                ids.push(id);
                // Simpan max 200 id terakhir saja
                if (ids.length > 200) ids.splice(0, ids.length - 200);
                localStorage.setItem(TOAST_SEEN_KEY, JSON.stringify(ids));
            }
        }

        function showToast(notif) {
            const ti = typeIcon(notif.type);
            const url = getNavUrl(notif.type);

            // Langsung tandai sebagai "sudah dilihat" agar polling berikutnya tidak tampilkan lagi
            addSeenId(parseInt(notif.id));

            const toast = document.createElement('div');
            toast.className = 'notif-toast';
            toast.dataset.id = notif.id;

            const iconColors = {
                'bg-blue-100 text-blue-600': '#dbeafe;color:#2563eb',
                'bg-gray-100 text-gray-600': '#f3f4f6;color:#4b5563',
                'bg-orange-100 text-orange-600': '#ffedd5;color:#ea580c',
                'bg-amber-100 text-amber-600': '#fef3c7;color:#d97706',
                'bg-purple-100 text-purple-600': '#f3e8ff;color:#9333ea',
                'bg-green-100 text-green-600': '#dcfce7;color:#16a34a',
                'bg-red-100 text-red-600': '#fee2e2;color:#dc2626'
            };
            const iconStyle = iconColors[ti.color] || '#f3f4f6;color:#4b5563';
            const [bg, fg] = iconStyle.split(';color:');

            toast.innerHTML = `
            <div class="notif-toast-inner">
                <div class="notif-toast-icon" style="background:${bg};color:#${fg}">
                    <i class="fas ${ti.icon}"></i>
                </div>
                <div class="notif-toast-content">
                    <div class="notif-toast-title">${notif.title || ''}</div>
                    <div class="notif-toast-msg">${notif.message || ''}</div>
                    <div class="notif-toast-time" data-created="${notif.created_at}"><i class="fas fa-clock" style="margin-right:3px"></i>${timeAgo(notif.created_at)}</div>
                </div>
            </div>
            <button class="notif-toast-close" title="Tutup"><i class="fas fa-times"></i></button>
        `;

            toastContainer.appendChild(toast);

            // Update waktu setiap 60 detik selama toast masih ada
            const timeEl = toast.querySelector('.notif-toast-time');
            const timeInterval = setInterval(() => {
                if (!toast.parentNode) { clearInterval(timeInterval); return; }
                timeEl.innerHTML = '<i class="fas fa-clock" style="margin-right:3px"></i>' + timeAgo(notif.created_at);
            }, 60000);

            // Animate in
            requestAnimationFrame(() => {
                requestAnimationFrame(() => toast.classList.add('show'));
            });

            // Click toast body → navigate
            toast.querySelector('.notif-toast-inner').addEventListener('click', function () {
                markRead(notif.id, null);
                addSeenId(parseInt(notif.id));
                dismissToast(toast);
                if (url) window.location.href = url;
            });

            // Click × → dismiss only
            toast.querySelector('.notif-toast-close').addEventListener('click', function (e) {
                e.stopPropagation();
                addSeenId(parseInt(notif.id));
                dismissToast(toast);
            });
        }

        function dismissToast(toast) {
            toast.classList.remove('show');
            toast.classList.add('hide');
            setTimeout(() => { if (toast.parentNode) toast.parentNode.removeChild(toast); }, 400);
        }

        // === REALTIME POLLING dengan check_new (ringan, 5 detik) ===
        // Track ID notif tertinggi yang sudah pernah dilihat
        let highestSeenId = 0;

        function initHighestId() {
            // Ambil ID tertinggi dari localStorage sebelumnya
            try {
                const ids = getSeenIds();
                if (ids.length > 0) highestSeenId = Math.max(...ids);
            } catch (e) { }
        }

        function fetchNewToasts() {
            fetch(API_URL + '?action=check_new&since_id=' + highestSeenId)
                .then(r => r.json())
                .then(data => {
                    updateBadge(data.unread_count || 0);
                    const newNotifs = data.new_notifs || [];
                    if (newNotifs.length === 0) return;

                    // Update highestSeenId ke ID terbesar dari hasil
                    const maxId = Math.max(...newNotifs.map(n => parseInt(n.id)));
                    if (maxId > highestSeenId) highestSeenId = maxId;

                    // Filter yang belum di-dismiss oleh user
                    const seenIds = getSeenIds();
                    const toShow = newNotifs.filter(n => !seenIds.includes(parseInt(n.id)));

                    // Tampilkan max 3 toast, berurutan
                    toShow.slice(0, 3).forEach((n, idx) => {
                        setTimeout(() => showToast(n), idx * 400);
                    });
                })
                .catch(() => { });
        }

        // Fetch awal: ambil semua notif unread untuk inisiasi highestSeenId
        function initToasts() {
            fetch(API_URL + '?action=fetch')
                .then(r => r.json())
                .then(data => {
                    updateBadge(data.unread_count || 0);
                    const notifs = data.notifications || [];
                    if (notifs.length > 0) {
                        // Set highestSeenId ke ID notif terbesar yang ada
                        const maxExisting = Math.max(...notifs.map(n => parseInt(n.id)));
                        if (maxExisting > highestSeenId) highestSeenId = maxExisting;
                    }
                    // Tampilkan toast untuk yang unread dan belum pernah dilihat
                    const seenIds = getSeenIds();
                    const toShow = notifs.filter(n => n.is_read == 0 && !seenIds.includes(parseInt(n.id)));
                    toShow.slice(0, 3).forEach((n, idx) => {
                        setTimeout(() => showToast(n), idx * 400 + 1500);
                    });
                })
                .catch(() => { });
        }

        initHighestId();
        initToasts();
        // Polling setiap 5 detik — sangat ringan karena hanya ambil id > highestSeenId
        setInterval(fetchNewToasts, 5000);
    })();
</script>

<?php
// Floating Chat Widget — muncul di semua halaman admin
if (isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/../chat_widget.php';
}
?>