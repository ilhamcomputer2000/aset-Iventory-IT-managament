<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);



// Mulai session
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}
// FIX: Role Check - Admin only untuk index.php, user redirect ke view.php
$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : 'super_admin';  // Default 'user' jika tidak ada role
if ($user_role !== 'super_admin') {
    header("Location: index.php");  // User ke view-only (read-only)
    exit();
}
// Role admin: Lanjut ke full dashboard (delete/edit/filter OK)

// Include koneksi database
include "../koneksi.php";





// Pastikan session username sudah diatur
$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'User';

// FIX: Ambil nama lengkap dari session (fallback ke username jika kosong)
$Nama_Lengkap = isset($_SESSION['Nama_Lengkap']) ? $_SESSION['Nama_Lengkap'] : $username;

// Tampilkan jabatan (bukan label role hardcoded)
$Jabatan_Level_Session = trim((string)($_SESSION['Jabatan_Level'] ?? ''));
$Jabatan_Level_Display = $Jabatan_Level_Session !== '' ? $Jabatan_Level_Session : '-';




    


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




?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ASSET IT CITRATEL - Modern Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="global.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <!-- PERBAIKAN: Select2 untuk Searchable Dropdown -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

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

    <!-- Enhanced Modern Sidebar - Higher z-index than navbar -->
    <div id="sidebar" class="fixed top-0 left-0 h-screen w-80 bg-gradient-to-b from-slate-900 via-slate-800 to-slate-900 text-white z-50 transform -translate-x-full transition-all duration-300 ease-in-out shadow-2xl overflow-y-auto">
        <!-- Close button for all screen sizes -->
        <button
            id="close-sidebar"
            class="absolute top-4 right-4 p-2 text-white hover:bg-slate-700 rounded-lg transition-all duration-200 z-10"
        >
            <i class="fas fa-times text-lg"></i>
        </button>

        <!-- Enhanced Sidebar Header -->
        <div class="p-6 pt-8 border-b border-slate-700/50">
            <div class="flex items-center justify-center mb-4">
                <div class="w-16 h-16 bg-gradient-to-br from-orange-400 to-orange-600 rounded-2xl flex items-center justify-center shadow-lg">
                    <i class="fas fa-building text-2xl text-white"></i>
                </div>
            </div>
            <h2 class="text-lg font-bold text-center text-white leading-tight">
                Asset Management
            </h2>
            <p class="text-sm text-slate-300 text-center mt-1">
                PT CIPTA KARYA TECHNOLOGY
            </p>
        </div>

        <!-- Enhanced Profile Section -->
        <div class="p-6 border-b border-slate-700/50">
            <div class="flex items-center space-x-4 bg-slate-800/50 rounded-xl p-4 backdrop-blur-sm">
                <div class="w-12 h-12 bg-gradient-to-br from-blue-400 to-purple-500 rounded-xl flex items-center justify-center shadow-lg">
                    <i class="fas fa-user text-lg text-white"></i>
                </div>
                <div>
                    <span class="font-semibold text-white block"> <?php echo htmlspecialchars($Nama_Lengkap); ?></span>
                    <span class="text-sm text-slate-300"><?php echo htmlspecialchars($Jabatan_Level_Display); ?></span>
                </div>
            </div>
        </div>

        <!-- Enhanced Navigation Menu -->
        <nav class="mt-4 px-4 relative">
            <a href="dashboard_admin.php" class="menu-item relative flex items-center space-x-4 py-4 px-6 mx-2 rounded-xl mb-2 transition-all duration-300 group text-slate-300 hover:bg-slate-700/50 hover:text-white">
                <div class="p-2 rounded-lg bg-slate-700/50 text-slate-400 group-hover:bg-slate-600/50 group-hover:text-white transition-all duration-300">
                    <i class="fas fa-tachometer-alt text-xl"></i>
                </div>
                <div class="flex-1">
                    <span class="font-semibold">Dashboard</span>
                </div>
                <div class="transition-all duration-300 opacity-0 -translate-x-2 group-hover:opacity-100 group-hover:translate-x-0">
                    <span class="text-sm">→</span>
                </div>
            </a>
            
            <a href="index.php" class="menu-item relative flex items-center space-x-4 py-4 px-6 mx-2 rounded-xl mb-2 transition-all duration-300 group text-slate-300 hover:bg-slate-700/50 hover:text-white">
                <div class="p-2 rounded-lg bg-slate-700/50 text-slate-400 group-hover:bg-slate-600/50 group-hover:text-white transition-all duration-300">
                    <i class="fas fa-cogs text-xl"></i>
                </div>
                <div class="flex-1">
                    <span class="font-semibold">Asset IT</span>
                </div>
                <div class="transition-all duration-300 opacity-0 -translate-x-2 group-hover:opacity-100 group-hover:translate-x-0">
                    <span class="text-sm">→</span>
                </div>
            </a>

            <a href="lacak_asset.php" class="menu-item relative flex items-center space-x-4 py-4 px-6 mx-2 rounded-xl mb-2 transition-all duration-300 group bg-gradient-to-r from-orange-500/20 to-orange-600/20 text-white shadow-lg border-l-4 border-orange-400">
                <div class="p-2 rounded-lg bg-orange-500/20 text-orange-400 transition-all duration-300">
                    <i class="fas fa-search-location text-xl"></i>
                </div>
                <div class="flex-1">
                    <span class="font-semibold">Lacak Asset</span>
                </div>
                <div class="absolute inset-0 bg-gradient-to-r from-orange-500/10 to-orange-600/10 rounded-xl blur-sm -z-10"></div>
                <div class="transition-all duration-300 opacity-100 translate-x-0">
                    <span class="text-sm">→</span>
                </div>
            </a>
            
            <a href="serah_terima.php" class="menu-item relative flex items-center space-x-4 py-4 px-6 mx-2 rounded-xl mb-2 transition-all duration-300 group text-slate-300 hover:bg-slate-700/50 hover:text-white">
                <div class="p-2 rounded-lg bg-slate-700/50 text-slate-400 group-hover:bg-slate-600/50 group-hover:text-white transition-all duration-300">
                    <i class="fas fa-file-alt text-xl"></i>
                </div>
                <div class="flex-1">
                    <span class="font-semibold">Form Serah Terima</span>
                </div>
                <div class="transition-all duration-300 opacity-0 -translate-x-2 group-hover:opacity-100 group-hover:translate-x-0">
                    <span class="text-sm">→</span>
                </div>
            </a>

            <a href="ticket.php" class="menu-item relative flex items-center space-x-4 py-4 px-6 mx-2 rounded-xl mb-2 transition-all duration-300 group text-slate-300 hover:bg-slate-700/50 hover:text-white">
                <div class="p-2 rounded-lg bg-slate-700/50 text-slate-400 group-hover:bg-slate-600/50 group-hover:text-white transition-all duration-300">
                    <i class="fas fa-ticket-alt text-xl"></i>
                </div>
                <div class="flex-1">
                    <span class="font-semibold">Ticket</span>
                </div>
                <div class="transition-all duration-300 opacity-0 -translate-x-2 group-hover:opacity-100 group-hover:translate-x-0">
                    <span class="text-sm">→</span>
                </div>
            </a>
            <!-- FIX: Settings sebagai Dropdown Parent (Pure Left-Click Toggle via JS, No Inline Onclick) -->
    <div class="sidebar-dropdown relative">  <!-- Wrapper untuk dropdown -->
        <div class="settings-parent sidebar-item relative flex items-center space-x-4 py-4 px-6 mx-2 rounded-xl mb-2 transition-all duration-300 group cursor-pointer 
    <?php echo $is_settings_page ? 'bg-gradient-to-r from-orange-500/20 to-orange-600/20 text-white border-l-4 border-orange-400' : 'text-slate-300 hover:bg-slate-700/50 hover:text-white'; ?>">
            <div class="p-2 rounded-lg bg-slate-700/50 text-slate-400 group-hover:bg-slate-600/50 group-hover:text-white transition-all duration-300">
                <i class="fas fa-cog text-xl"></i>
            </div>
            <div class="flex-1">
                <span class="font-semibold">Settings</span>
            </div>
            <div class="transition-all duration-300 opacity-0 -translate-x-2 group-hover:opacity-100 group-hover:translate-x-0">
                <i class="fas fa-chevron-down text-sm transition-transform duration-300" id="settings-arrow"></i>  <!-- Arrow rotate via JS -->
            </div>
        </div>
        
        <!-- Submenu Settings (Relative stack, indented, no inline onclick) -->
        <ul id="settings-dropdown" class="submenu overflow-hidden transition-all duration-300 ease-in-out" 
            style="max-height: 0; opacity: 0;">  <!-- No onclick di li, handle via JS delegation -->
            <li class="px-3 py-2 text-sm text-slate-300 hover:bg-slate-700/50 hover:text-white transition-all duration-200 cursor-pointer border-b border-slate-700/50 last:border-b-0 last:rounded-b-lg submenu-item" 
                data-url="profile.php">  <!-- Tambah class 'submenu-item' dan data-url untuk JS -->
                <div class="flex items-center space-x-3">
                    <i class="fas fa-user text-xs"></i>
                    <span>Profile</span>
                </div>
            </li>
            <li class="px-3 py-2 text-sm text-slate-300 hover:bg-slate-700/50 hover:text-white transition-all duration-200 cursor-pointer border-b border-slate-700/50 last:border-b-0 last:rounded-b-lg submenu-item" 
                data-url="add_akun.php">
                <div class="flex items-center space-x-3">
                    <i class="fas fa-user-plus text-xs"></i>  <!-- Opsional: Ganti ikon ke fa-user-plus untuk "add" -->
                    <span>Add Account</span>  <!-- Opsional: Ubah teks untuk konteks -->
                </div>
            </li>
            <li class="px-3 py-2 text-sm text-slate-300 hover:bg-slate-700/50 hover:text-white transition-all duration-200 cursor-pointer border-b border-slate-700/50 last:border-b-0 last:rounded-b-lg submenu-item" 
                data-url="log.php">
                <div class="flex items-center space-x-3">
                    <i class="fas fa-cogs text-xs"></i>
                    <span>System Settings</span>
                </div>
            </li>
            <li class="px-3 py-2 text-sm text-slate-300 hover:bg-slate-700/50 hover:text-white transition-all duration-200 cursor-pointer rounded-b-lg submenu-item" 
                data-url="help.php">
                <div class="flex items-center space-x-3">
                    <i class="fas fa-question-circle text-xs"></i>
                    <span>Help & Support</span>
                </div>
            </li>
        </ul>
    </div>
            
            <a href="../logout.php" class="menu-item relative flex items-center space-x-4 py-4 px-6 mx-2 rounded-xl mb-2 transition-all duration-300 group text-slate-300 hover:bg-slate-700/50 hover:text-white">
                <div class="p-2 rounded-lg bg-slate-700/50 text-slate-400 group-hover:bg-slate-600/50 group-hover:text-white transition-all duration-300">
                    <i class="fas fa-sign-out-alt text-xl"></i>
                </div>
                <div class="flex-1">
                    <span class="font-semibold">Logout</span>
                </div>
                <div class="transition-all duration-300 opacity-0 -translate-x-2 group-hover:opacity-100 group-hover:translate-x-0">
                    <span class="text-sm">→</span>
                </div>
            </a>
        </nav>

        <!-- Footer decoration -->
        <div class="absolute bottom-0 left-0 right-0 h-20 bg-gradient-to-t from-slate-900 to-transparent pointer-events-none"></div>
    </div>

    <!-- Modern Orange Navbar - Lower z-index than sidebar -->
    <nav class="bg-gradient-to-r from-orange-500 to-orange-600 shadow-lg fixed w-full z-40">
        <div class="px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center space-x-4">
                    <!-- Hamburger Menu -->
                    <button
                        id="hamburger-btn"
                        class="p-3 rounded-lg bg-white/20 backdrop-blur-sm text-white shadow-md hover:bg-white/30 transition-all duration-300 hover:scale-105"
                        style="display: flex !important; align-items: center; justify-content: center; min-width: 44px; min-height: 44px;"
                    >
                        <i id="hamburger-icon" class="fas fa-bars text-lg"></i>
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <!-- Enhanced Overlay for mobile -->
    <div id="overlay" class="lg:hidden fixed inset-0 bg-black/60 backdrop-blur-sm z-30 transition-all duration-300 hidden"></div>

        

          
            
                        
                                
                

            
    


<!-- JavaScript -->
    <script>
        $(document).ready(function() {
            // FIX: Debug form submit (hapus setelah test)
    $('form[method="GET"]').on('submit', function() {
        var lopValue = $('select[name="status_lop_filter"]').val();
        console.log('Submitting Status LOP value:', lopValue);  // Cek di browser console (F12)
        if (!lopValue) console.warn('Status LOP value kosong - cek select!');
    });
// Debug: Cek apakah jQuery dan Select2 terload dengan benar
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

    

// TAMBAHAN: Re-init Select2 jika filter berubah (opsional, untuk dynamic opsi; uncomment jika perlu)
    $(document).on('change', '.select2-filter', function() {
// Tidak auto-submit, biarkan user klik tombol Filter
// Opsional: Re-init jika opsi berubah via AJAX (tidak perlu di sini)
    // $(this).select2('destroy').select2({ ... });  // Sama config di atas
    });

// Re-init Select2 setelah view mode change (untuk grid/table switch, jika dropdown hilang)
    $(document).on('change', '.select2-filter', function() {
        // Tidak auto-submit, biarkan user klik Filter
    });

// Responsive adjustments
    $(window).on('resize', function() {
        mainContent.removeClass('ml-0 ml-80');  // Clean dulu sebelum reinitialize
        initializeSidebar();
    });

// Initialize page
    initializeSidebar();
});




// Loading Animation - Hide setelah 2 detik
    window.addEventListener('load', function() {
        setTimeout(function() {
            const loadingOverlay = document.getElementById('loadingOverlay');
            if (loadingOverlay) {
                loadingOverlay.style.opacity = '0';  // Fade out
                setTimeout(function() {
                    loadingOverlay.style.display = 'none';  // Hilangkan dari DOM
                }, 500);  // Delay fade 0.5 detik
            }
        }, 2000);  // Tunggu 2 detik (sesuaikan durasi)
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
        
    });
// Tambahan: Handle Edit Modal
const editModal = document.getElementById('editModal');
const closeModal = document.getElementById('closeModal');
const editForm = document.getElementById('editForm');
const editBtns = document.querySelectorAll('.edit-btn');
const deleteBtns = document.querySelectorAll('.delete-btn');



// 1. Pastikan modal tidak hidden & bisa diklik
        editModal.classList.remove('hidden', 'opacity-0', 'pointer-events-none');

// 2. Force reflow (penting untuk animasi)
        void editModal.offsetWidth;

// 3. Ambil konten modal
        const modalContent = editModal.querySelector('div.relative');

// 4. Jalankan animasi masuk
        editModal.classList.add('opacity-100');
        if (modalContent) {
            modalContent.classList.remove('scale-95');
            modalContent.classList.add('scale-100');
        }

       
closeModal.addEventListener('click', function() {
    const modalContent = editModal.querySelector('div.relative');
// Animasi keluar
    editModal.classList.remove('opacity-100');
    editModal.classList.add('opacity-0');
    modalContent.classList.remove('scale-100');
    modalContent.classList.add('scale-95');

// Setelah animasi selesai, sembunyikan
    setTimeout(() => {
        editModal.classList.add('hidden', 'pointer-events-none');
    }, 300); // Sesuaikan dengan duration animasi
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

    

    </script>
</body>
</html>