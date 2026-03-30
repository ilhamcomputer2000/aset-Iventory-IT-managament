<?php
/**
 * Shared User Sidebar + Navbar Component
 * 
 * Usage: Set $activePage before including this file.
 * Example:
 *   $activePage = 'lacak_asset'; // or 'dashboard', 'assets', 'ticket'
 *   require_once __DIR__ . '/sidebar_user_include.php';
 * 
 * Requires: $Nama_Lengkap, $Jabatan_Level (from session)
 */
// Hosting hardening: load app_url.php if available, else define fallback
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
}

if (!isset($activePage))
    $activePage = '';

$isDashboard = ($activePage === 'dashboard');
$isAssets = ($activePage === 'assets');
$isLacak = ($activePage === 'lacak_asset');
$isTicket = ($activePage === 'ticket');
$isLog = ($activePage === 'log');
$isSettingsSubPage = $isLog;

// Build proper base URL for links (works regardless of URL rewriting)
$_sidebarBaseDir = dirname($_SERVER['SCRIPT_NAME']);
// Ensure we're pointing to the /user/ directory
if (basename($_sidebarBaseDir) !== 'user') {
    // If the current script is NOT in /user/ directory (e.g. URL rewriting),
    // find /user/ relative to crud root
    $_sidebarBaseDir = dirname($_sidebarBaseDir) . '/user';
}
$_sidebarBaseDir = rtrim($_sidebarBaseDir, '/');

$_sidebarLinks = [
    'dashboard' => $_sidebarBaseDir . '/dashboard_user.php',
    'assets' => $_sidebarBaseDir . '/view.php',
    'lacak' => $_sidebarBaseDir . '/lacak_asset.php',
    'ticket' => $_sidebarBaseDir . '/ticket.php',
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
    @media (min-width: 1024px) {
        .sidebar-content-push {
            margin-left: 15rem;
            transition: margin-left 0.3s ease;
            min-width: 0;
            /* Prevent content from overflowing on zoom-out */
        }

        body.sidebar-collapsed #sidebar {
            transform: translateX(-100%);
        }

        body.sidebar-collapsed .sidebar-content-push {
            margin-left: 0 !important;
        }

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
            <div
                class="w-8 h-8 bg-gradient-to-br from-blue-400 to-purple-500 rounded-lg flex items-center justify-center flex-shrink-0">
                <i class="fas fa-user text-white text-xs"></i>
            </div>
            <div class="min-w-0">
                <span
                    class="text-sm font-semibold text-gray-900 block truncate"><?php echo htmlspecialchars($Nama_Lengkap ?? 'User'); ?></span>
                <span
                    class="text-[11px] text-gray-500 truncate block"><?php echo htmlspecialchars(($Jabatan_Level ?? '') !== '' ? $Jabatan_Level : '-'); ?></span>
            </div>
        </div>
    </div>

    <!-- Navigation Menu -->
    <nav class="mt-2 px-3 pb-4">
        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider px-3 mb-2">MENU</p>

        <a href="<?php echo htmlspecialchars($_sidebarLinks['dashboard']); ?>"
            class="flex items-center space-x-3 py-2.5 px-3 rounded-lg mb-1 transition-all duration-200 <?php echo $isDashboard ? 'bg-orange-50 text-orange-700 border-l-[3px] border-orange-500' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'; ?>">
            <i
                class="fas fa-tachometer-alt text-base w-5 text-center <?php echo $isDashboard ? 'text-orange-500' : 'text-gray-400'; ?>"></i>
            <span class="text-sm <?php echo $isDashboard ? 'font-semibold' : 'font-medium'; ?>">Dashboard</span>
        </a>

        <a href="<?php echo htmlspecialchars($_sidebarLinks['assets']); ?>"
            class="flex items-center space-x-3 py-2.5 px-3 rounded-lg mb-1 transition-all duration-200 <?php echo $isAssets ? 'bg-orange-50 text-orange-700 border-l-[3px] border-orange-500' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'; ?>">
            <i
                class="fas fa-cogs text-base w-5 text-center <?php echo $isAssets ? 'text-orange-500' : 'text-gray-400'; ?>"></i>
            <span class="text-sm <?php echo $isAssets ? 'font-semibold' : 'font-medium'; ?>">Asset IT</span>
        </a>

        <a href="<?php echo htmlspecialchars($_sidebarLinks['lacak']); ?>"
            class="flex items-center space-x-3 py-2.5 px-3 rounded-lg mb-1 transition-all duration-200 <?php echo $isLacak ? 'bg-orange-50 text-orange-700 border-l-[3px] border-orange-500' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'; ?>">
            <i
                class="fas fa-search-location text-base w-5 text-center <?php echo $isLacak ? 'text-orange-500' : 'text-gray-400'; ?>"></i>
            <span class="text-sm <?php echo $isLacak ? 'font-semibold' : 'font-medium'; ?>">Lacak Asset</span>
        </a>

        <a href="<?php echo htmlspecialchars($_sidebarLinks['ticket']); ?>"
            class="flex items-center space-x-3 py-2.5 px-3 rounded-lg mb-1 transition-all duration-200 <?php echo $isTicket ? 'bg-orange-50 text-orange-700 border-l-[3px] border-orange-500' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'; ?>">
            <i
                class="fas fa-ticket-alt text-base w-5 text-center <?php echo $isTicket ? 'text-orange-500' : 'text-gray-400'; ?>"></i>
            <span class="text-sm <?php echo $isTicket ? 'font-semibold' : 'font-medium'; ?>">Ticket</span>
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
                    <button id="btn-open-change-password" type="button"
                        class="w-full flex items-center space-x-3 py-2 px-3 pl-11 text-sm text-gray-500 hover:bg-gray-100 hover:text-gray-900 transition-all duration-200 rounded-lg">
                        <i class="fas fa-key text-xs"></i>
                        <span>Ganti Password</span>
                    </button>
                </li>
                <li>
                    <a href="<?php echo htmlspecialchars($_sidebarLinks['log']); ?>"
                        class="w-full flex items-center space-x-3 py-2 px-3 pl-11 text-sm transition-all duration-200 rounded-lg <?php echo $isLog ? 'bg-orange-50 text-orange-700 font-semibold' : 'text-gray-500 hover:bg-gray-100 hover:text-gray-900'; ?>">
                        <i class="fas fa-history text-xs <?php echo $isLog ? 'text-orange-500' : ''; ?>"></i>
                        <span>Aktivitas Saya</span>
                    </a>
                </li>
            </ul>
        </div>

        <a href="<?php echo htmlspecialchars($_sidebarLinks['logout']); ?>"
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

<!-- Sidebar responsive styles (inline to avoid Tailwind CDN race conditions) -->
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
        <div id="notif-loading" class="flex flex-col items-center justify-center py-16">
            <div class="w-10 h-10 border-3 border-orange-200 border-t-orange-500 rounded-full animate-spin mb-4"></div>
            <p class="text-gray-400 text-sm">Memuat notifikasi...</p>
        </div>
        <div id="notif-empty" class="hidden flex-col items-center justify-center py-16 px-6">
            <div class="w-20 h-20 rounded-full bg-orange-50 flex items-center justify-center mb-4">
                <i class="fas fa-bell-slash text-3xl text-orange-300"></i>
            </div>
            <p class="text-gray-500 font-medium text-base mb-1">Belum Ada Notifikasi</p>
            <p class="text-gray-400 text-sm text-center">Notifikasi akan muncul saat admin melakukan tindakan pada tiket
                atau pinjaman Anda.</p>
        </div>
        <div id="notif-items" class="divide-y divide-gray-100"></div>
    </div>
</div>

<!-- Sidebar JS -->
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
            // Update footer position via body class — CSS handles per-breakpoint (no inline style to override mobile)
            // body.sidebar-collapsed #page-footer { left: 0 } is handled in CSS
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
        const API_URL = '<?php echo app_abs_path("user/api_notifications.php"); ?>';

        function getNavUrl(type) {
            const map = {
                'ticket_status_changed': '<?php echo app_abs_path("user/ticket"); ?>',
                'ticket_approval_needed': '<?php echo app_abs_path("user/ticket"); ?>?status=Done',
                'ticket_created': '<?php echo app_abs_path("user/ticket"); ?>',
                'ticket_closed': '<?php echo app_abs_path("user/ticket"); ?>',
                'pinjaman_approved': '<?php echo app_abs_path("user/lacak_asset"); ?>',
                'pinjaman_rejected': '<?php echo app_abs_path("user/lacak_asset"); ?>',
                'pinjaman_request': '<?php echo app_abs_path("user/lacak_asset"); ?>'
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

        fetchBadgeOnly();
        setInterval(fetchBadgeOnly, 30000);
    })();
</script>

<?php
// Floating Chat Widget — muncul di semua halaman user
if (isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/../chat_widget.php';
}

// Modal Ganti Password — muncul di semua halaman user
if (isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/modal_change_password.html';
}
?>