<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$role = $_SESSION['role'];
if ($role !== 'super_admin' && $role !== 'user') {
    header("Location: ../login.php");
    exit();
}

// Koneksi ke database
$conn = new mysqli("localhost", "root", "", "crud");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Pastikan session username sudah diatur
$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'User';
$Nama_Lengkap = isset($_SESSION['Nama_Lengkap']) ? $_SESSION['Nama_Lengkap'] : $username;

// Tampilkan jabatan (bukan label role hardcoded)
$Jabatan_Level_Session = trim((string)($_SESSION['Jabatan_Level'] ?? ''));
$Jabatan_Level_Display = $Jabatan_Level_Session !== '' ? $Jabatan_Level_Session : '-';


// Pagination dan filtering
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$view_mode = isset($_GET['view']) ? $_GET['view'] : 'table';
$current_page = isset($_GET['nav']) ? $_GET['nav'] : 'handover';

$offset = ($page - 1) * $limit;

// Build query dengan filtering
$where_conditions = [];
$params = [];
$types = "";

if (!empty($search)) {
    $where_conditions[] = "(ID_Form LIKE ? OR Create_By LIKE ? OR Employed_ID_Pengirim LIKE ? OR Dikirim_Oleh LIKE ? OR Kepada_Penerima LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param, $search_param]);
    $types .= "sssss";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Count total records
$count_sql = "SELECT COUNT(*) as total FROM serah_terima $where_clause";
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

// Get data dengan pagination
$sql = "SELECT * FROM serah_terima $where_clause ORDER BY No DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Statistics
$stats_sql = "SELECT 
    COUNT(*) as total_forms,
    SUM(CASE WHEN MONTH(STR_TO_DATE(SUBSTRING_INDEX(ID_Form, '-', -1), '%d')) = MONTH(CURDATE()) THEN 1 ELSE 0 END) as this_month
    FROM serah_terima";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Dashboard Admin - Asset Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <style>

        /* PERBAIKAN: Custom Styling untuk Select2 (match Tailwind) */
/* PERBAIKAN: Custom Styling untuk Select2 (match Tailwind, tambah force search) */
        .select2-container--default .select2-selection--single {
            height: 42px !important; /* Match py-2 px-3 */
            border: 1px solid #d1d5db !important; /* border-gray-300 */
            border-radius: 0.5rem !important; /* rounded-lg */
            background-color: rgba(255, 255, 255, 0.9) !important; /* bg-white/90 */
            backdrop-filter: blur(4px) !important;
            font-size: 0.875rem !important; /* text-sm */
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            color: #374151 !important; /* text-gray-700 */
            padding-left: 0.75rem !important; /* px-3 */
            padding-top: 0.5rem !important; /* py-2 */
            line-height: 1.25 !important;
        }

        .select2-container--default .select2-selection--single .select2-selection__placeholder {
            color: #9ca3af !important; /* placeholder-gray-500 */
        }

        .select2-container--default.select2-container--focus .select2-selection--single {
            border-color: #3b82f6 !important; /* focus:border-blue-500 */
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1) !important; /* focus:ring-2 ring-blue-500/10 */
            outline: none !important;
        }

        .select2-dropdown {
            border: 1px solid #d1d5db !important; /* border-gray-300 */
            border-radius: 0.5rem !important; /* rounded-lg */
            background-color: rgba(255, 255, 255, 0.95) !important; /* bg-white/95 */
            backdrop-filter: blur(4px) !important;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05) !important; /* shadow-lg */
            z-index: 9999 !important; /* Tinggi z-index untuk hindari tutup sidebar */
        }

        .select2-container--default .select2-results__option--highlighted[aria-selected] {
            background-color: #dbeafe !important; /* bg-blue-100 */
            color: #1e40af !important; /* text-blue-800 */
        }

        .select2-container--default .select2-results__option {
            padding: 0.5rem 0.75rem !important; /* py-2 px-3 */
            font-size: 0.875rem !important; /* text-sm */
        }

        .select2-container--default .select2-search--dropdown .select2-search__field {
            border: 1px solid #d1d5db !important;
            border-radius: 0.5rem !important;
            padding: 0.5rem 0.75rem !important;
            background-color: white !important;
            font-size: 0.875rem !important; /* text-sm */
        }

        /* PERBAIKAN: Force search box tampil di semua dropdown (bahkan jika opsi sedikit) */
        .select2-container .select2-search--inline {
            display: block !important;
            width: 100% !important;
            margin: 5px 0 !important;
        }

        .select2-container--default .select2-search--inline .select2-search__field {
            background: white !important;
            border: 1px solid #d1d5db !important;
            border-radius: 0.5rem !important;
            padding: 0.5rem !important;
            width: 100% !important;
        }

        /* Max height untuk dropdown panjang (seperti Kategori) */
        .select2-container--default .select2-results {
            max-height: 160px !important; /* max-h-40 ≈ 160px */
            overflow-y: auto !important;
        }

        /* Mobile responsive */
        @media (max-width: 768px) {
            .select2-container--default .select2-selection--single {
                height: 38px !important;
                font-size: 0.875rem !important;
            }
        }

        /* Hide hamburger in desktop (sidebar locked open) */
        #hamburger-btn {
            display: none;
        }
        @media (min-width: 1024px) {
            #hamburger-btn {
                display: flex !important;  /* Pastikan flex seperti style inline */
            }
        }
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        * {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }
        
        .animate-fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }
        
        .animate-slide-up {
            animation: slideUp 0.6s ease-out;
        }
        
        .animate-scale-in {
            animation: scaleIn 0.4s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes scaleIn {
            from { opacity: 0; transform: scale(0.9); }
            to { opacity: 1; transform: scale(1); }
        }
        
        .status-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            transform-origin: center;
        }
        
        .status-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        
        .sidebar {
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .sidebar-item {
            transition: all 0.2s ease;
        }
        
        .sidebar-item:hover {
            transform: translateX(4px);
        }
        
        /* Custom scrollbar */
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }
        
        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 3px;
        }
        
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 3px;
        }
        
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        /* Grid styles */
        .asset-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        /* Responsive grid adjustments */
        @media (max-width: 768px) {
            .asset-grid {
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
                gap: 1rem;
            }
        }

        @media (max-width: 640px) {
            .asset-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
        }

        /* Table row hover */
        .table-row {
            transition: all 0.3s ease;
        }

        .table-row:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 25px rgba(0, 0, 0, 0.1);
        }

        /* Modal styles */
        .modal-backdrop {
            backdrop-filter: blur(8px);
            background: rgba(0, 0, 0, 0.8);
        }

        .modal-content {
            animation: modalFadeIn 0.3s ease-out;
        }

        @keyframes modalFadeIn {
            from { 
                opacity: 0; 
                transform: translate(-50%, -50%) scale(0.9); 
            }
            to { 
                opacity: 1; 
                transform: translate(-50%, -50%) scale(1); 
            }
        }

        /* Status badge hover effects */
        .status-badge {
            transition: all 0.3s ease;
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-align: center;
            border: 1px solid transparent;
        }

        .status-badge:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        /* Sidebar Compact Sizing - Responsive untuk hindari zoom/overflow */
        #sidebar {
            /* Default untuk mobile: Ukuran asli (sudah pas) */
        }

        @media (min-width: 1024px) {
            /* Desktop: Kurangi ukuran agar mirip mobile (tidak zoom besar) */
            #sidebar .w-16 { width: 3.5rem; height: 3.5rem; }  /* Logo dari 64px → 56px */
            #sidebar .w-12 { width: 2.75rem; height: 2.75rem; }  /* Profile dari 48px → 44px */
            #sidebar .text-2xl { font-size: 1.125rem; line-height: 1.75rem; }  /* Logo icon dari 24px → 18px */
            #sidebar .text-lg { font-size: 1rem; line-height: 1.5rem; }  /* Header & profile icon/teks dari 18px → 16px */
            #sidebar .text-xl { font-size: 1.125rem; line-height: 1.75rem; }  /* Menu icon dari 20px → 18px */
            #sidebar h2 { font-size: 1rem; line-height: 1.5rem; }  /* Header title dari text-lg → text-base */
            #sidebar .p-6 { padding: 1rem; }  /* Padding header/profile dari 24px → 16px */
            #sidebar .pt-8 { padding-top: 1.25rem; }  /* Top padding header dari 32px → 20px */
            #sidebar .py-4 { padding-top: 0.75rem; padding-bottom: 0.75rem; }  /* Menu vertikal dari 16px → 12px */
            #sidebar .px-6 { padding-left: 1.25rem; padding-right: 1.25rem; }  /* Menu horizontal dari 24px → 20px */
            #sidebar .p-4 { padding: 0.75rem; }  /* Profile inner padding dari 16px → 12px */
            #sidebar .p-2 { padding: 0.5rem; }  /* Icon menu padding dari 8px → 4px (compact) */
            #sidebar .mx-2 { margin-left: 0.5rem; margin-right: 0.5rem; }  /* Menu margin dari 8px → 4px */
            #sidebar .mb-2 { margin-bottom: 0.5rem; }  /* Menu bottom margin dari 8px → 4px */
            #sidebar .mb-4 { margin-bottom: 1rem; }  /* Space antar section dari 16px → 16px (tetap) */
        }

        /* Adjust footer gradient untuk hindari tutup content di desktop */
        @media (min-width: 1024px) {
            #sidebar > .absolute.bottom-0 {  /* Target footer decoration */
                height: 1.5rem;  /* Dari h-20 (80px) → 24px, lebih tipis */
            }
        }


    /* Loading Animation - Gradient Biru-Oren Cerah dengan Shimmer (Lebih Menarik & Dinamis) */
.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    /* Base gradient: Biru cerah ke oren cerah dengan multi-stop untuk depth */
    background: linear-gradient(135deg, #4692f9 0%, #5a9df9 25%, #ffa700 50%, #ffb347 75%, #ff9500 100%);
    background-size: 400% 400%;  /* Ukuran besar untuk animasi shimmer (bergerak) */
    animation: shimmerGradient 4s ease-in-out infinite;  /* Animasi bergerak pelan (shimmer effect) */
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 9999;
    transition: opacity 0.5s ease-out;  /* Fade out halus */
}

/* Animasi Shimmer: Gradient bergerak seperti cahaya memantul */
@keyframes shimmerGradient {
    0% { 
        background-position: 0% 50%;  /* Mulai dari kiri */
    }
    50% { 
        background-position: 100% 50%;  /* Bergerak ke kanan (kilau) */
    }
    100% { 
        background-position: 0% 50%;  /* Kembali ke kiri (loop smooth) */
    }
}

.loading-content {
    text-align: center;
    color: #1e293b;  /* Teks navy gelap untuk kontras tajam di gradient cerah bergerak */
}

.loading-logo {
    width: 100px;  /* Ukuran seperti sebelumnya */
    height: 100px;
    margin: 0 auto 1.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    overflow: hidden;
    background: rgba(255, 255, 255, 0.9);  /* Background putih untuk kontras dengan shimmer */
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);  /* Shadow lembut */
    animation: logoRotate 2s ease-in-out;  /* Animasi rotasi logo tetap */
    border: 2px solid rgba(255, 255, 255, 0.4);  /* Border untuk highlight di shimmer */
}

.logo-image {
    width: 100%;
    height: 100%;
    object-fit: contain;
    border-radius: inherit;
}

.loading-bars {
    display: flex;
    justify-content: center;
    gap: 0.5rem;
    margin: 2rem 0;
}

.loading-bar {
    width: 12px;
    height: 12px;
    background: #1e293b;  /* Navy gelap untuk kontras dengan gradient bergerak */
    border-radius: 50%;
    animation: loadingPulse 1.5s ease-in-out infinite;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);  /* Shadow ringan */
}

.loading-bar:nth-child(2) { animation-delay: 0.2s; }
.loading-bar:nth-child(3) { animation-delay: 0.4s; }

.loading-progress {
    width: 200px;
    height: 4px;
    background: rgba(30, 41, 59, 0.2);  /* Background navy semi-transparan */
    border-radius: 2px;
    overflow: hidden;
    margin: 0 auto;
    box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.1);  /* Inner shadow untuk depth */
}

.loading-progress-bar {
    width: 0%;
    height: 100%;
    background: linear-gradient(90deg, #4692f9, #ffa700);  /* Gradient biru-orens cerah untuk progress (match base) */
    background-size: 200% 100%;  /* Ukuran untuk shimmer mini di progress */
    animation: progressShimmer 2s ease-out forwards, progressLoad 2s ease-out forwards;  /* Shimmer + isi progress */
    border-radius: 2px;
    box-shadow: 0 0 8px rgba(70, 146, 249, 0.4);  /* Glow biru cerah */
}

/* Shimmer untuk progress bar (opsional, mini version) */
@keyframes progressShimmer {
    0% { background-position: -200% 0; }
    100% { background-position: 200% 0; }
}

/* Keyframes tetap sama untuk elemen lain (jangan ubah) */
@keyframes logoRotate {
    0% { 
        transform: scale(0) rotate(-180deg); 
        opacity: 0; 
    }
    100% { 
        transform: scale(1) rotate(0deg); 
        opacity: 1; 
    }
}

@keyframes loadingPulse {
    0%, 100% { transform: scale(1); opacity: 0.7; }
    50% { transform: scale(1.5); opacity: 1; }
}

@keyframes progressLoad {
    0% { width: 0%; }
    100% { width: 100%; }
}
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        * {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            box-sizing: border-box;
        }
        
        .animate-fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }
        
        .animate-slide-up {
            animation: slideUp 0.6s ease-out;
        }
        
        .animate-scale-in {
            animation: scaleIn 0.4s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes scaleIn {
            from { opacity: 0; transform: scale(0.9); }
            to { opacity: 1; transform: scale(1); }
        }
        
        .status-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .status-card:hover {
            transform: translateY(-8px) scale(1.02);
        }
        
        .sidebar {
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        @media (max-width: 1024px) {
            #sidebar {
                transform: translateX(-100%);
            }
            
            #sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0 !important;
            }
        }
        
        .grid-card {
            transition: all 0.3s ease;
        }
        
        .grid-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }
        
        .table-hover:hover {
            background-color: rgba(59, 130, 246, 0.05);
        }
        
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            padding: 16px 24px;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            transform: translateX(400px);
            transition: transform 0.3s ease;
        }
        
        .notification.show {
            transform: translateX(0);
        }
        
        .notification.success {
            background: linear-gradient(135deg, #10b981, #059669);
        }
        
        .notification.error {
            background: linear-gradient(135deg, #ef4444, #dc2626);
        }
        
        .notification.info {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100" x-data="{ 
    currentPage: '<?= $current_page ?>', 
    viewMode: '<?= $view_mode ?>',
    showNotification: false,
    notificationType: 'success',
    notificationMessage: ''
}">

    <!-- Notification -->
    <div x-show="showNotification" x-transition class="notification" :class="notificationType">
        <span x-text="notificationMessage"></span>
    </div>

    <?php $activePage = 'serah_terima'; require_once __DIR__ . '/sidebar_admin.php'; ?>

    <!-- Main Content -->
    <div id="main-content-wrapper" class="main-content lg:ml-60 min-h-screen transition-all duration-300 ease-in-out">
    <script>
        (function() {
            var wrapper = document.getElementById('main-content-wrapper');
            if (!wrapper) return;
            function applyState() {
                if (window.innerWidth >= 1024) {
                    var collapsed = localStorage.getItem('sidebarCollapsed') === '1';
                    wrapper.style.marginLeft = collapsed ? '0' : '';
                } else {
                    wrapper.style.marginLeft = '0';
                }
            }
            applyState();
            window.addEventListener('sidebarToggled', function(e) { applyState(); });
            window.addEventListener('resize', function() { applyState(); });
        })();
    </script>
        <main class="p-4 lg:p-8">
            <?php if ($current_page === 'dashboard'): ?>
                <!-- Dashboard Content -->
                <div class="space-y-8 animate-fade-in">
                    <div class="flex flex-col lg:flex-row items-start gap-6">
                        <div class="flex-1">
                            <h1 class="text-3xl font-bold text-gray-900 mb-2">Dashboard Overview</h1>
                            <p class="text-gray-600">Welcome back! Here's what's happening with your asset management.</p>
                        </div>
                        <div class="relative">
                            <img src="https://images.unsplash.com/photo-1630283017802-785b7aff9aac?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxtb2Rlcm4lMjBvZmZpY2UlMjB3b3Jrc3BhY2V8ZW58MXx8fHwxNzU5MDEzOTM3fDA&ixlib=rb-4.1.0&q=80&w=1080" 
                                 alt="Modern Office" class="w-32 h-24 object-cover rounded-xl shadow-lg">
                        </div>
                    </div>

                    <!-- Stats Cards -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-6">
                        <?php
                        $stats_cards = [
                            ['title' => 'Total Forms', 'value' => $stats['total_forms'], 'change' => '+12%', 'icon' => 'fas fa-file-text', 'color' => 'blue'],
                            ['title' => 'Approved', 'value' => rand(50, 80), 'change' => '+8%', 'icon' => 'fas fa-check-circle', 'color' => 'green'],
                            ['title' => 'Pending', 'value' => rand(20, 40), 'change' => '+15%', 'icon' => 'fas fa-clock', 'color' => 'yellow'],
                            ['title' => 'Rejected', 'value' => rand(5, 15), 'change' => '-5%', 'icon' => 'fas fa-times-circle', 'color' => 'red'],
                            ['title' => 'This Month', 'value' => $stats['this_month'], 'change' => '+25%', 'icon' => 'fas fa-trending-up', 'color' => 'orange'],
                            ['title' => 'Active Users', 'value' => rand(20, 30), 'change' => '+3%', 'icon' => 'fas fa-users', 'color' => 'purple']
                        ];
                        
                        foreach ($stats_cards as $index => $stat):
                            $color_classes = [
                                'blue' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-600'],
                                'green' => ['bg' => 'bg-green-100', 'text' => 'text-green-600'],
                                'yellow' => ['bg' => 'bg-yellow-100', 'text' => 'text-yellow-600'],
                                'red' => ['bg' => 'bg-red-100', 'text' => 'text-red-600'],
                                'orange' => ['bg' => 'bg-orange-100', 'text' => 'text-orange-600'],
                                'purple' => ['bg' => 'bg-purple-100', 'text' => 'text-purple-600']
                            ];
                        ?>
                        <div class="status-card relative overflow-hidden border border-gray-100 bg-white rounded-xl p-6 shadow-lg animate-slide-up" style="animation-delay: <?= $index * 0.1 ?>s;">
                            <div class="flex items-center justify-between">
                                <div class="space-y-2">
                                    <p class="text-sm font-medium text-gray-600"><?= $stat['title'] ?></p>
                                    <p class="text-2xl font-bold text-gray-900"><?= $stat['value'] ?></p>
                                    <div class="flex items-center space-x-1">
                                        <span class="text-xs font-medium <?= strpos($stat['change'], '+') === 0 ? 'text-green-600' : 'text-red-600' ?>">
                                            <?= $stat['change'] ?>
                                        </span>
                                        <span class="text-xs text-gray-500">vs last month</span>
                                    </div>
                                </div>
                                <div class="p-3 rounded-full <?= $color_classes[$stat['color']]['bg'] ?>">
                                    <i class="<?= $stat['icon'] ?> w-6 h-6 <?= $color_classes[$stat['color']]['text'] ?>"></i>
                                </div>
                            </div>
                            <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-orange-400 to-blue-500"></div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Quick Actions -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div class="bg-gradient-to-br from-orange-400 to-orange-600 p-6 rounded-xl text-white cursor-pointer hover:scale-105 transition-transform shadow-lg"
                             onclick="window.location.href='isi_form.php'">
                            <h3 class="text-xl font-semibold mb-2">Create New Form</h3>
                            <p class="text-orange-100">Start a new handover process</p>
                        </div>
                        
                        <div class="bg-gradient-to-br from-blue-400 to-blue-600 p-6 rounded-xl text-white cursor-pointer hover:scale-105 transition-transform shadow-lg"
                             onclick="window.location.href='?nav=handover'">
                            <h3 class="text-xl font-semibold mb-2">View All Forms</h3>
                            <p class="text-blue-100">Browse existing handover forms</p>
                        </div>
                        
                        <div class="bg-gradient-to-br from-purple-400 to-purple-600 p-6 rounded-xl text-white cursor-pointer hover:scale-105 transition-transform shadow-lg"
                             onclick="window.location.href='?nav=assets'">
                            <h3 class="text-xl font-semibold mb-2">Manage Assets</h3>
                            <p class="text-purple-100">View and manage IT assets</p>
                        </div>
                    </div>
                </div>

            <?php elseif ($current_page === 'assets'): ?>
                <!-- Assets Content -->
                <div class="space-y-6 animate-fade-in">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900 mb-2">Assets IT</h1>
                        <p class="text-gray-600">Manage your IT assets and inventory</p>
                    </div>
                    
                    <div class="bg-white rounded-xl p-8 text-center shadow-lg">
                        <div class="text-gray-400 mb-4">
                            <i class="fas fa-server text-6xl"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-700 mb-2">Assets Management</h3>
                        <p class="text-gray-500">This section is under development. Assets management features will be available soon.</p>
                    </div>
                </div>

            <?php else: ?>
                <!-- Handover Forms Content -->
                <div class="space-y-6 animate-fade-in">
                    <div class="flex flex-col lg:flex-row justify-between items-start gap-4">
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900 mb-2">Form Serah Terima</h1>
                            <p class="text-gray-600">Manage handover forms and track asset transfers</p>
                        </div>
                    </div>

                    <!-- Stats Cards -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                        <?php
                        $mini_stats = [
                            ['title' => 'Total Forms', 'value' => $stats['total_forms'], 'icon' => 'fas fa-file-text', 'color' => 'blue'],
                            ['title' => 'This Month', 'value' => $stats['this_month'], 'icon' => 'fas fa-calendar', 'color' => 'orange'],
                            ['title' => 'Pending Review', 'value' => rand(5, 15), 'icon' => 'fas fa-clock', 'color' => 'yellow'],
                            ['title' => 'Completed', 'value' => rand(50, 80), 'icon' => 'fas fa-check', 'color' => 'green']
                        ];
                        
                        foreach ($mini_stats as $stat):
                        ?>
                        <div class="bg-white p-6 rounded-xl shadow-lg border border-gray-100 hover:shadow-xl transition-shadow">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-gray-600"><?= $stat['title'] ?></p>
                                    <p class="text-2xl font-bold text-gray-900"><?= $stat['value'] ?></p>
                                </div>
                                <div class="p-3 rounded-full bg-<?= $stat['color'] ?>-100">
                                    <i class="<?= $stat['icon'] ?> text-<?= $stat['color'] ?>-600"></i>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Controls -->
                    <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
                        <form method="GET" class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
                            <input type="hidden" name="nav" value="handover">
                            
                            <div class="flex flex-col sm:flex-row gap-4 flex-1">
                                <!-- Search -->
                                <div class="relative flex-1 max-w-md">
                                    <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                                           placeholder="Cari form..." 
                                           class="w-full pl-10 pr-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-orange-400">
                                </div>

                                <!-- Items per page -->
                                <div class="flex items-center gap-2">
                                    <span class="text-sm text-gray-600">Show:</span>
                                    <select name="limit" onchange="this.form.submit()" 
                                            class="text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-400">
                                        <option value="5" <?= $limit == 5 ? 'selected' : '' ?>>5</option>
                                        <option value="10" <?= $limit == 10 ? 'selected' : '' ?>>10</option>
                                        <option value="20" <?= $limit == 20 ? 'selected' : '' ?>>20</option>
                                        <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50</option>
                                    </select>
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <!-- Export Button -->
                                <button type="button" 
                                        onclick="window.print()" 
                                        class="flex items-center gap-2 px-4 py-2 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                                    <i class="fas fa-download text-sm"></i>
                                    Export
                                </button>

                                <!-- View Toggle -->
                                <div class="flex items-center border border-gray-200 rounded-lg p-1 bg-gray-50">
                                    <button type="submit" name="view" value="table" 
                                            class="px-3 py-1 rounded transition-colors <?= $view_mode === 'table' ? 'bg-orange-500 text-white' : 'text-gray-600 hover:bg-gray-100' ?>">
                                        <i class="fas fa-list"></i>
                                    </button>
                                    <button type="submit" name="view" value="grid" 
                                            class="px-3 py-1 rounded transition-colors <?= $view_mode === 'grid' ? 'bg-orange-500 text-white' : 'text-gray-600 hover:bg-gray-100' ?>">
                                        <i class="fas fa-th-large"></i>
                                    </button>
                                </div>

                                <!-- Add Button -->
                                <a href="isi_form.php" 
                                   class="bg-gradient-to-r from-orange-500 to-orange-600 hover:from-orange-600 hover:to-orange-700 text-white px-4 py-2 rounded-lg flex items-center gap-2 shadow-lg transition-all transform hover:scale-105">
                                    <i class="fas fa-plus"></i>
                                    <span class="hidden sm:inline">Tambah Form</span>
                                </a>

                                <!-- Search Button -->
                                <button type="submit" 
                                        class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition-colors">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Data Display -->
                    <?php if ($view_mode === 'table'): ?>
                        <!-- Table View -->
                        <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-100 animate-slide-up">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gradient-to-r from-blue-600 to-blue-500">
                                        <tr>
                                            <th class="px-6 py-4 text-left text-xs font-semibold text-white uppercase tracking-wider">No</th>
                                            <th class="px-6 py-4 text-left text-xs font-semibold text-white uppercase tracking-wider">ID Form</th>
                                            <th class="px-6 py-4 text-left text-xs font-semibold text-white uppercase tracking-wider">Created By</th>
                                            <th class="px-6 py-4 text-left text-xs font-semibold text-white uppercase tracking-wider">Employee ID</th>
                                            <th class="px-6 py-4 text-left text-xs font-semibold text-white uppercase tracking-wider">Sender</th>
                                            <th class="px-6 py-4 text-left text-xs font-semibold text-white uppercase tracking-wider">Receiver</th>
                                            <th class="px-6 py-4 text-left text-xs font-semibold text-white uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-100">
                                        <?php
                                        if ($result && $result->num_rows > 0) {
                                            $no = $offset + 1;
                                            while ($row = $result->fetch_assoc()) {
                                                echo '<tr class="table-hover transition-colors duration-200">';
                                                echo '<td class="px-6 py-4 font-medium text-gray-900">' . $no . '</td>';
                                                echo '<td class="px-6 py-4 font-mono text-sm text-gray-900">' . htmlspecialchars($row['ID_Form']) . '</td>';
                                                echo '<td class="px-6 py-4 text-gray-900">' . htmlspecialchars($row['Create_By']) . '</td>';
                                                echo '<td class="px-6 py-4 font-mono text-sm text-gray-900">' . htmlspecialchars($row['Employed_ID_Pengirim']) . '</td>';
                                                echo '<td class="px-6 py-4 text-gray-900 max-w-32 truncate">' . htmlspecialchars($row['Dikirim_Oleh']) . '</td>';
                                                echo '<td class="px-6 py-4 text-gray-900 max-w-32 truncate">' . htmlspecialchars($row['Kepada_Penerima']) . '</td>';
                                                echo '<td class="px-6 py-4">';
                                                echo '<div class="flex space-x-2">';
                                                echo '<a href="view_form.php?id=' . urlencode($row['ID_Form']) . '" class="text-green-600 border border-green-200 hover:bg-green-50 px-3 py-1 rounded transition-colors"><i class="fas fa-eye"></i></a>';
                                                echo '<a href="edit_serahterima.php?id=' . urlencode($row['ID_Form']) . '" class="text-yellow-600 border border-yellow-200 hover:bg-yellow-50 px-3 py-1 rounded transition-colors"><i class="fas fa-edit"></i></a>';
                                                echo '<a href="delete_form.php?id=' . urlencode($row['ID_Form']) . '" onclick="return confirm(\'Yakin ingin menghapus data ini?\')" class="text-red-600 border border-red-200 hover:bg-red-50 px-3 py-1 rounded transition-colors"><i class="fas fa-trash"></i></a>';
                                                echo '</div>';
                                                echo '</td>';
                                                echo '</tr>';
                                                $no++;
                                            }
                                        } else {
                                            echo '<tr><td colspan="7" class="text-center py-8 text-gray-500">Data tidak ditemukan.</td></tr>';
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    <?php else: ?>
                        <!-- Grid View -->
                        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6 animate-slide-up">
                            <?php
                            if ($result && $result->num_rows > 0) {
                                $result->data_seek(0); // Reset result pointer
                                while ($row = $result->fetch_assoc()) {
                            ?>
                            <div class="grid-card bg-white border border-gray-200 rounded-xl p-6 shadow-lg">
                                <div class="flex justify-between items-start mb-4">
                                    <div>
                                        <h3 class="font-semibold text-lg text-gray-900"><?= htmlspecialchars($row['ID_Form']) ?></h3>
                                        <p class="text-sm text-gray-500">Created by <?= htmlspecialchars($row['Create_By']) ?></p>
                                    </div>
                                    <span class="px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 border border-blue-200 rounded">
                                        Active
                                    </span>
                                </div>
                                
                                <div class="space-y-2 mb-4">
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-500">Employee ID:</span>
                                        <span class="font-mono"><?= htmlspecialchars($row['Employed_ID_Pengirim']) ?></span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-500">From:</span>
                                        <span class="truncate ml-2"><?= htmlspecialchars($row['Dikirim_Oleh']) ?></span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-500">To:</span>
                                        <span class="truncate ml-2"><?= htmlspecialchars($row['Kepada_Penerima']) ?></span>
                                    </div>
                                </div>
                                
                                <div class="flex space-x-2">
                                    <a href="view_form.php?id=<?= urlencode($row['ID_Form']) ?>" 
                                       class="flex-1 text-green-600 border border-green-200 hover:bg-green-50 px-3 py-2 rounded text-center transition-colors">
                                        <i class="fas fa-eye mr-1"></i> View
                                    </a>
                                    <a href="edit_serahterima.php?id=<?= urlencode($row['ID_Form']) ?>" 
                                       class="flex-1 text-yellow-600 border border-yellow-200 hover:bg-yellow-50 px-3 py-2 rounded text-center transition-colors">
                                        <i class="fas fa-edit mr-1"></i> Edit
                                    </a>
                                    <a href="delete_form.php?id=<?= urlencode($row['ID_Form']) ?>" 
                                       onclick="return confirm('Yakin ingin menghapus data ini?')"
                                       class="text-red-600 border border-red-200 hover:bg-red-50 px-3 py-2 rounded transition-colors">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </div>
                            <?php
                                }
                            } else {
                                echo '<div class="col-span-full text-center py-12 bg-white rounded-xl border border-gray-100">';
                                echo '<div class="text-gray-400 mb-4"><i class="fas fa-search text-6xl"></i></div>';
                                echo '<h3 class="text-lg font-semibold text-gray-600 mb-2">No data found</h3>';
                                echo '<p class="text-gray-500">Try adjusting your search criteria</p>';
                                echo '</div>';
                            }
                            ?>
                        </div>
                    <?php endif; ?>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
                        <div class="flex flex-col lg:flex-row justify-between items-center gap-4">
                            <!-- Info -->
                            <div class="flex flex-col sm:flex-row items-center gap-4">
                                <div class="text-sm text-gray-600 text-center sm:text-left">
                                    Showing <span class="font-semibold text-gray-900"><?= $offset + 1 ?></span> to 
                                    <span class="font-semibold text-gray-900"><?= min($offset + $limit, $total_records) ?></span> of 
                                    <span class="font-semibold text-gray-900"><?= $total_records ?></span> results
                                </div>
                            </div>

                            <!-- Pagination Controls -->
                            <div class="flex items-center gap-2">
                                <!-- First Page -->
                                <?php if ($page > 1): ?>
                                <a href="?nav=handover&page=1&limit=<?= $limit ?>&search=<?= urlencode($search) ?>&view=<?= $view_mode ?>" 
                                   class="px-3 py-2 border border-gray-200 rounded-lg hover:bg-orange-50 hover:border-orange-200 transition-colors">
                                    <i class="fas fa-angle-double-left"></i>
                                </a>
                                <a href="?nav=handover&page=<?= $page - 1 ?>&limit=<?= $limit ?>&search=<?= urlencode($search) ?>&view=<?= $view_mode ?>" 
                                   class="px-3 py-2 border border-gray-200 rounded-lg hover:bg-orange-50 hover:border-orange-200 transition-colors">
                                    <i class="fas fa-angle-left"></i>
                                </a>
                                <?php endif; ?>

                                <!-- Page Numbers -->
                                <?php
                                $start = max(1, $page - 2);
                                $end = min($total_pages, $page + 2);
                                
                                for ($i = $start; $i <= $end; $i++):
                                ?>
                                <a href="?nav=handover&page=<?= $i ?>&limit=<?= $limit ?>&search=<?= urlencode($search) ?>&view=<?= $view_mode ?>" 
                                   class="min-w-[40px] px-3 py-2 text-center rounded-lg transition-colors <?= $i == $page ? 'bg-gradient-to-r from-orange-500 to-orange-600 text-white shadow-lg' : 'border border-gray-200 hover:bg-orange-50 hover:border-orange-200' ?>">
                                    <?= $i ?>
                                </a>
                                <?php endfor; ?>

                                <!-- Next Page -->
                                <?php if ($page < $total_pages): ?>
                                <a href="?nav=handover&page=<?= $page + 1 ?>&limit=<?= $limit ?>&search=<?= urlencode($search) ?>&view=<?= $view_mode ?>" 
                                   class="px-3 py-2 border border-gray-200 rounded-lg hover:bg-orange-50 hover:border-orange-200 transition-colors">
                                    <i class="fas fa-angle-right"></i>
                                </a>
                                <a href="?nav=handover&page=<?= $total_pages ?>&limit=<?= $limit ?>&search=<?= urlencode($search) ?>&view=<?= $view_mode ?>" 
                                   class="px-3 py-2 border border-gray-200 rounded-lg hover:bg-orange-50 hover:border-orange-200 transition-colors">
                                    <i class="fas fa-angle-double-right"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        // Smooth scrolling and animations
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-hide notifications
            function showNotification(message, type = 'success') {
                const notification = document.querySelector('.notification');
                notification.textContent = message;
                notification.className = `notification ${type} show`;
                
                setTimeout(() => {
                    notification.classList.remove('show');
                }, 3000);
            }

            // Smooth animations on scroll
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, observerOptions);

            // Observe all cards
            document.querySelectorAll('.status-card, .grid-card').forEach(card => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                observer.observe(card);
            });

            // Enhanced hover effects
            document.querySelectorAll('.status-card').forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-8px) scale(1.02)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0) scale(1)';
                });
            });

            // Form submission with loading state
            document.querySelectorAll('form').forEach(form => {
                form.addEventListener('submit', function() {
                    const submitBtn = this.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Loading...';
                        submitBtn.disabled = true;
                    }
                });
            });

            // Auto-refresh stats every 30 seconds
            setInterval(() => {
                if (window.location.search.includes('nav=dashboard')) {
                    location.reload();
                }
            }, 30000);
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                // Close any modals if open
                var modalBackdrop = document.querySelector('.modal-backdrop');
                if (modalBackdrop) modalBackdrop.click();
            }
        });

        // Page transition effects
        window.addEventListener('beforeunload', function() {
            document.body.style.opacity = '0.5';
            document.body.style.transition = 'opacity 0.3s ease';
        });
    </script>

</body>
</html>

<?php
$conn->close();
?>