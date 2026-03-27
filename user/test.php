<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// FIX: Role Check - Admin only untuk index.php, user redirect ke view.php
$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : 'user';  // Default 'user' jika tidak ada role
if ($user_role !== 'user') {
    header("Location: view.php");  // User ke view-only
    exit();
}

// Pastikan session username sudah diatur
$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'Admin User';
// FIX: Ambil nama lengkap dari session (fallback ke username jika kosong)
$Nama_Lengkap = isset($_SESSION['Nama_Lengkap']) ? $_SESSION['Nama_Lengkap'] : $username;

// Tampilkan jabatan (bukan label role hardcoded)
$Jabatan_Level = trim((string)($_SESSION['Jabatan_Level'] ?? ''));

// Koneksi ke database
// $conn = new mysqli("localhost", "root", "", "crud");
$conn = new mysqli("localhost:3306", "cktnosa2_admin", "uGXj8#eiI=P%", "cktnosa2_crud"); 
// Cek koneksi
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Fallback: jika session Jabatan_Level kosong, ambil dari DB berdasarkan user_id
if ($Jabatan_Level === '' && isset($_SESSION['user_id'])) {
    $stmtMeta = $conn->prepare("SELECT Jabatan_Level, Nama_Lengkap, role FROM users WHERE id = ? LIMIT 1");
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
        error_log('Failed to prepare user meta query (test.php): ' . $conn->error);
    }
}

// Handle AJAX request untuk pagination
if (isset($_GET['action']) && $_GET['action'] === 'ajax_get_assets') {
    header('Content-Type: application/json; charset=utf-8');
    
    // Debug: Log yang kita terima
    error_log('AJAX Request: status_filter=' . (isset($_GET['status_filter']) ? $_GET['status_filter'] : 'not set') . 
              ', category_filter=' . (isset($_GET['category_filter']) ? $_GET['category_filter'] : 'not set') . 
              ', page=' . (isset($_GET['page']) ? $_GET['page'] : 'not set'));
    
    $status_filter = isset($_GET['status_filter']) ? trim($_GET['status_filter']) : 'all';
    $category_filter = isset($_GET['category_filter']) ? trim($_GET['category_filter']) : 'all';
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $page = max(1, $page);
    
    $limit = 10;
    $offset = ($page - 1) * $limit;
    
    // Build WHERE clause
    $where = "1=1";
    if ($status_filter !== 'all' && !empty($status_filter)) {
        $where .= " AND Status_Barang = '" . $conn->real_escape_string($status_filter) . "'";
    }
    if ($category_filter !== 'all' && !empty($category_filter)) {
        $where .= " AND Nama_Barang = '" . $conn->real_escape_string($category_filter) . "'";
    }
    
    error_log('SQL WHERE clause: ' . $where);
    
    // Count records
    $count_result = $conn->query("SELECT COUNT(*) as total FROM peserta WHERE $where");
    if (!$count_result) {
        error_log('Count query failed: ' . $conn->error);
        http_response_code(500);
        echo json_encode(['error' => 'Count query failed: ' . $conn->error]);
        exit();
    }
    
    $count_row = $count_result->fetch_assoc();
    if (!$count_row) {
        error_log('Count row fetch failed');
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch count']);
        exit();
    }
    $total_filtered = intval($count_row['total']);
    $total_pages = $total_filtered > 0 ? ceil($total_filtered / $limit) : 1;
    $page = min($page, $total_pages);
    
    // Get data
    $query = "SELECT id_peserta, Nama_Barang, Merek, Type, Serial_Number, Status_Barang, 
                     Kondisi_Barang, User_Perangkat, Photo_Barang, Lokasi
              FROM peserta WHERE $where LIMIT $limit OFFSET $offset";
    error_log('Data query: ' . $query);
    
    $result = $conn->query($query);
    if (!$result) {
        error_log('Data query failed: ' . $conn->error);
        http_response_code(500);
        echo json_encode(['error' => 'Data query failed: ' . $conn->error]);
        exit();
    }
    
    // Build table HTML - matching initial page table structure exactly
    $table_html = '<thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Asset Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Location</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Brand</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Serial Number</th>
                    </tr>
                  </thead>
                  <tbody class="bg-white divide-y divide-gray-200">';
    
    while ($row = $result->fetch_assoc()) {
        $status_class = '';
        switch($row['Status_Barang']) {
            case 'READY': $status_class = 'bg-green-100 text-green-800'; break;
            case 'IN USE': $status_class = 'bg-red-600 text-white'; break;
            case 'KOSONG': $status_class = 'bg-red-100 text-red-800'; break;
            case 'REPAIR': $status_class = 'bg-yellow-100 text-yellow-800'; break;
            case 'TEMPORARY': $status_class = 'bg-purple-100 text-purple-800'; break;
            case 'RUSAK': $status_class = 'bg-pink-100 text-pink-800'; break;
            default: $status_class = 'bg-gray-100 text-gray-800';
        }
        
        $table_html .= '<tr class="table-row hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="font-medium text-gray-900">' . htmlspecialchars($row['Nama_Barang']) . '</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                ' . htmlspecialchars($row['Nama_Barang'] ?: 'N/A') . '
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="status-badge inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ' . $status_class . '">
                                ' . htmlspecialchars($row['Status_Barang']) . '
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            ' . htmlspecialchars($row['Lokasi'] ?: 'N/A') . '
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            ' . htmlspecialchars($row['Merek'] ?: 'N/A') . '
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            ' . htmlspecialchars($row['Serial_Number'] ?: 'N/A') . '
                        </td>
                    </tr>';
    }
    
    $table_html .= '</tbody>';
    
    // Build pagination HTML with consistent filter preservation
    $pagination_html = '';
    if ($total_pages > 1) {
        $pagination_html .= '<div class="mt-6 flex items-center justify-between">
                              <div class="text-sm text-gray-600">
                                Showing ' . min($offset + 1, $total_filtered) . ' 
                                to ' . min($offset + $limit, $total_filtered) . ' 
                                of ' . $total_filtered . ' results
                              </div>
                              <nav class="inline-flex rounded-md shadow-sm -space-x-px">';
        
        // Build base URL params to preserve filters
        $base_params = [
            'status_filter' => $status_filter,
            'category_filter' => $category_filter
        ];
        
        // Previous
        if ($page > 1) {
            $prev_params = array_merge($base_params, ['page' => $page - 1]);
            $prev_url = "?" . http_build_query($prev_params);
            $pagination_html .= '<a href="' . $prev_url . '" class="pagination-link relative inline-flex items-center px-3 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                  <i class="fas fa-chevron-left"></i>
                                  <span class="ml-1">Prev</span>
                                </a>';
        }
        
        // Pages
        $start = max(1, $page - 2);
        $end = min($total_pages, $page + 2);
        
        if ($start > 1) {
            $first_params = array_merge($base_params, ['page' => 1]);
            $pagination_html .= '<a href="?' . http_build_query($first_params) . '" class="pagination-link relative inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">1</a>';
            if ($start > 2) {
                $pagination_html .= '<span class="relative inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-500">...</span>';
            }
        }
        
        for ($i = $start; $i <= $end; $i++) {
            if ($i == $page) {
                $pagination_html .= '<span class="relative z-10 inline-flex items-center px-3 py-2 border border-orange-500 bg-orange-50 text-sm font-medium text-orange-600">' . $i . '</span>';
            } else {
                $page_params = array_merge($base_params, ['page' => $i]);
                $pagination_html .= '<a href="?' . http_build_query($page_params) . '" class="pagination-link relative inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">' . $i . '</a>';
            }
        }
        
        if ($end < $total_pages) {
            if ($end < $total_pages - 1) {
                $pagination_html .= '<span class="relative inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-500">...</span>';
            }
            $last_params = array_merge($base_params, ['page' => $total_pages]);
            $pagination_html .= '<a href="?' . http_build_query($last_params) . '" class="pagination-link relative inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">' . $total_pages . '</a>';
        }
        
        // Next
        if ($page < $total_pages) {
            $next_params = array_merge($base_params, ['page' => $page + 1]);
            $next_url = "?" . http_build_query($next_params);
            $pagination_html .= '<a href="' . $next_url . '" class="pagination-link relative inline-flex items-center px-3 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                  <span class="mr-1">Next</span>
                                  <i class="fas fa-chevron-right"></i>
                                </a>';
        }
        
        $pagination_html .= '</nav></div>';
    }
    
    // Return JSON
    echo json_encode([
        'table_html' => $table_html,
        'pagination_html' => $pagination_html,
        'current_page' => $page,
        'total_pages' => $total_pages,
        'total_records' => $total_filtered
    ]);
    exit();
}

// Handle filter parameters
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : 'all';
$category_filter = isset($_GET['category_filter']) ? $_GET['category_filter'] : 'all';

// Base query untuk menghitung jumlah berdasarkan status
$base_where = "1=1";
$params = array();
$types = "";

// Add filters to query
if ($status_filter !== 'all') {
    $base_where .= " AND Status_Barang = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if ($category_filter !== 'all') {
    $base_where .= " AND Nama_Barang = ?";
    $params[] = $category_filter;
    $types .= "s";
}

// Query untuk menghitung jumlah berdasarkan status dengan filter
$sql = "
    SELECT 
        SUM(CASE WHEN Status_Barang = 'READY' THEN 1 ELSE 0 END) AS total_ready,
        SUM(CASE WHEN Status_Barang = 'KOSONG' THEN 1 ELSE 0 END) AS total_kosong,
        SUM(CASE WHEN Status_Barang = 'REPAIR' THEN 1 ELSE 0 END) AS total_repair,
        SUM(CASE WHEN Status_Barang = 'TEMPORARY' THEN 1 ELSE 0 END) AS total_temporary,
        SUM(CASE WHEN Status_Barang = 'IN USE' THEN 1 ELSE 0 END) AS total_inuse,
        SUM(CASE WHEN Status_Barang = 'RUSAK' THEN 1 ELSE 0 END) AS total_rusak 
    FROM peserta 
    WHERE $base_where";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $jumlah_ready = $row['total_ready'];
    $jumlah_kosong = $row['total_kosong'];
    $jumlah_repair = $row['total_repair'];
    $jumlah_temporary = $row['total_temporary'];
    $jumlah_inuse = $row['total_inuse'];
    $jumlah_rusak = $row['total_rusak'];
} else {
    $jumlah_ready = 0;
    $jumlah_kosong = 0;
    $jumlah_repair = 0;
    $jumlah_temporary = 0;
    $jumlah_inuse = 0;
    $jumlah_rusak = 0;
}

// Get available categories for filter dropdown
$category_sql = "SELECT DISTINCT Nama_Barang FROM peserta WHERE Nama_Barang IS NOT NULL AND Nama_Barang != '' ORDER BY Nama_Barang";
$category_result = $conn->query($category_sql);
$categories = array();
if ($category_result) {
    while ($row = $category_result->fetch_assoc()) {
        $categories[] = $row['Nama_Barang'];
    }
}

// Get filtered asset details for table
// Pagination setup
// Pagination setup
$limit = 10; // Jumlah data per halaman
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page); // Minimal halaman 1
$offset = ($page - 1) * $limit;

// Hitung total data (sama seperti sebelumnya)
$total_filtered = 0;
if ($status_filter !== 'all' || $category_filter !== 'all') {
    $count_sql = "SELECT COUNT(*) as total FROM peserta WHERE $base_where";
    $count_stmt = $conn->prepare($count_sql);
    if (!empty($params)) {
        $count_stmt->bind_param($types, ...$params);
    }
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $total_filtered = $count_result->fetch_assoc()['total'];
} else {
    $total_filtered = $jumlah_ready + $jumlah_kosong + $jumlah_repair + $jumlah_temporary + $jumlah_inuse + $jumlah_rusak;
}

// ✅ Sekarang $total_filtered sudah ada → hitung $total_pages
$total_pages = ceil($total_filtered / $limit);

// Ambil data sesuai halaman
$detail_sql = "SELECT * FROM peserta WHERE $base_where ORDER BY id_peserta DESC LIMIT $limit OFFSET $offset";
$detail_stmt = $conn->prepare($detail_sql);
if (!empty($params)) {
    $detail_stmt->bind_param($types, ...$params);
}
$detail_stmt->execute();
$detail_result = $detail_stmt->get_result();

$total_filtered = 0;
if ($status_filter !== 'all' || $category_filter !== 'all') {
    $count_sql = "SELECT COUNT(*) as total FROM peserta WHERE $base_where";
    $count_stmt = $conn->prepare($count_sql);
    if (!empty($params)) {
        $count_stmt->bind_param($types, ...$params);
    }
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $total_filtered = $count_result->fetch_assoc()['total'];
} else {
    $total_filtered = $jumlah_ready + $jumlah_kosong + $jumlah_repair + $jumlah_temporary + $jumlah_inuse + $jumlah_rusak;
}

// Get total count without filters
$total_sql = "SELECT COUNT(*) as total FROM peserta";
$total_result = $conn->query($total_sql);
$total_assets = $total_result->fetch_assoc()['total'];

// Tutup koneksi
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Asset Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
    <link rel="stylesheet" href="../global_dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>
    <script>
    // Daftarkan plugin SEKALI SAJA, secara global
    Chart.register(ChartDataLabels);
    </script>
    
    
</style>
</head>

<body class="bg-gray-50">
    <!-- Loading Animation -->
<div id="loadingOverlay" class="loading-overlay">
    <div class="loading-content">
        <div class="loading-logo">
            <img src="logo_form/logo ckt fix.png" alt="Logo Perusahaan" class="logo-image">  <!-- Path ke logo lokal Anda -->
        </div>
        <h1 class="text-2xl md:text-3xl font-bold mb-2">PT CIPTA KARYA TECHNOLOGY</h1>  <!-- Ganti nama perusahaan jika perlu -->
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

    <!-- Mobile Overlay -->
    <div id="mobile-overlay" class="mobile-overlay lg:hidden"></div>

    <!-- Enhanced Modern Sidebar -->
    <div id="sidebar" class="sidebar fixed top-0 left-0 h-screen w-80 bg-gradient-to-b from-slate-900 via-slate-800 to-slate-900 text-white z-50">
        <!-- Close button for mobile -->
        <button id="close-sidebar" class="lg:hidden absolute top-4 right-4 p-2 text-white hover:bg-slate-700 rounded-lg transition-all duration-200 z-10">
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
                    <span class="text-sm text-slate-300"><?php echo htmlspecialchars($Jabatan_Level !== '' ? $Jabatan_Level : '-'); ?></span>
                </div>
            </div>
        </div>

        <!-- Enhanced Navigation Menu dengan Dropdown Settings -->
<nav class="mt-4 px-4 relative">
    <a href="dashboard_user.php" class="sidebar-item relative flex items-center space-x-4 py-4 px-6 mx-2 rounded-xl mb-2 transition-all duration-300 group bg-gradient-to-r from-orange-500/20 to-orange-600/20 text-white shadow-lg border-l-4 border-orange-400">
        <div class="p-2 rounded-lg bg-orange-500/20 text-orange-400 transition-all duration-300">
            <i class="fas fa-tachometer-alt text-xl"></i>
        </div>
        <div class="flex-1">
            <span class="font-semibold">Dashboard</span>
        </div>
        <div class="transition-all duration-300 opacity-100 translate-x-0">
            <span class="text-sm">→</span>
        </div>
    </a>
    
    <a href="index.php" class="sidebar-item relative flex items-center space-x-4 py-4 px-6 mx-2 rounded-xl mb-2 transition-all duration-300 group text-slate-300 hover:bg-slate-700/50 hover:text-white">
        <div class="p-2 rounded-lg bg-slate-700/50 text-slate-400 group-hover:bg-slate-600/50 group-hover:text-white transition-all duration-300">
            <i class="fas fa-cogs text-xl"></i>
        </div>
        <div class="flex-1">
            <span class="font-semibold">Assets IT</span>
        </div>
        <div class="transition-all duration-300 opacity-0 -translate-x-2 group-hover:opacity-100 group-hover:translate-x-0">
            <span class="text-sm">→</span>
        </div>
    </a>
    <a href="lacak_asset.php" class="sidebar-item relative flex items-center space-x-4 py-4 px-6 mx-2 rounded-xl mb-2 transition-all duration-300 group text-slate-300 hover:bg-slate-700/50 hover:text-white">
        <div class="p-2 rounded-lg bg-slate-700/50 text-slate-400 group-hover:bg-blue-500/20 group-hover:text-blue-400 transition-all duration-300">
            <i class="fas fa-search-location text-xl"></i>
        </div>
        <div class="flex-1">
            <span class="font-semibold">Lacak Asset</span>
        </div>
        <div class="transition-all duration-300 opacity-0 -translate-x-2 group-hover:opacity-100 group-hover:translate-x-0">
            <span class="text-sm">→</span>
        </div>
    </a>

    <a href="ticket.php" class="sidebar-item relative flex items-center space-x-4 py-4 px-6 mx-2 rounded-xl mb-2 transition-all duration-300 group text-slate-300 hover:bg-slate-700/50 hover:text-white">
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
        <div class="settings-parent sidebar-item relative flex items-center space-x-4 py-4 px-6 mx-2 rounded-xl mb-2 transition-all duration-300 group cursor-pointer text-slate-300 hover:bg-slate-700/50 hover:text-white">  <!-- Tambah class 'settings-parent' untuk JS target, hapus onclick inline -->
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
                data-url="system-settings.php">
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
    
    <!-- FIX: Logout di luar dropdown (pastikan visible & clickable) -->
    <a href="../logout.php" class="sidebar-item relative flex items-center space-x-4 py-4 px-6 mx-2 rounded-xl mb-4 transition-all duration-300 group text-slate-300 hover:bg-slate-700/50 hover:text-white">  <!-- mb-4 untuk spasi bawah -->
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

    <!-- Navbar -->
    <nav class="bg-gradient-to-r from-orange-500 to-orange-600 shadow-lg fixed top-0 left-0 w-full z-40 h-16 flex items-center">
        <div class="px-4 flex justify-between w-full">
            <div class="flex items-center space-x-4">
                <!-- Fixed Hamburger Menu -->
                <button id="hamburger-btn" class="hamburger-btn lg:hidden p-3 rounded-lg bg-white/20 backdrop-blur-sm text-white shadow-md hover:bg-white/30 transition-all duration-300 hover:scale-105">
                    <i id="hamburger-icon" class="fas fa-bars text-lg"></i>
                </button>
                <h1 class="text-xl font-bold text-white">ASSET IT CITRATEL</h1>
            </div>
        </div>
    </nav>

    <div class="lg:ml-80 transition-all duration-300">
        <main class="p-6 lg:p-8">
            <div class="mb-8 animate-fade-in">
                <h1 class="text-3xl font-bold text-gray-900 mb-2 mt-16">Dashboard</h1>
                <p class="text-gray-600">Welcome to the Asset PT CIPTA KARYA TECHNOLOGY Dashboard!</p>
                 <!-- Real-time Clock in Indonesian -->
                <p class="text-sm text-gray-500 mt-2 flex items-center gap-2">
                    <i class="fas fa-clock text-orange-500"></i>
                    <span id="realtime-clock">Loading...</span>
                </p>
            </div>
            <!-- Filter Section -->
            <div class="filter-card mb-8 p-6">
                <form method="GET" action="<?php echo $_SERVER['PHP_SELF']; ?>" class="space-y-6">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="p-2 bg-orange-100 rounded-lg">
                            <i class="fas fa-filter text-orange-600 text-xl"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900">Filter Assets</h3>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="space-y-3">
                            <label class="block text-sm font-medium text-gray-700">Filter by Status:</label>
                            <select name="status_filter" class="custom-select w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition-all duration-200">
                                <option value="all" <?php echo ($status_filter == 'all') ? 'selected' : ''; ?>>All Status</option>
                                <option value="READY" <?php echo ($status_filter == 'READY') ? 'selected' : ''; ?>>Ready</option>
                                <option value="KOSONG" <?php echo ($status_filter == 'KOSONG') ? 'selected' : ''; ?>>Kosong</option>
                                <option value="REPAIR" <?php echo ($status_filter == 'REPAIR') ? 'selected' : ''; ?>>Repair</option>
                                <option value="TEMPORARY" <?php echo ($status_filter == 'TEMPORARY') ? 'selected' : ''; ?>>Temporary</option>
                                <option value="IN USE" <?php echo ($status_filter == 'IN USE') ? 'selected' : ''; ?>>In Use</option>
                                <option value="RUSAK" <?php echo ($status_filter == 'RUSAK') ? 'selected' : ''; ?>>Rusak</option>
                            </select>
                        </div>
                        
                        <div class="space-y-3">
                            <label class="block text-sm font-medium text-gray-700">Filter by Category:</label>
                            <select name="category_filter" class="custom-select w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition-all duration-200">
                                <option value="all" <?php echo ($category_filter == 'all') ? 'selected' : ''; ?>>All Categories</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo htmlspecialchars($category); ?>" <?php echo ($category_filter == $category) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="flex items-end gap-3">
                            <button type="submit" class="px-6 py-3 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-all duration-200 font-medium flex items-center gap-2">
                                <i class="fas fa-search"></i>
                                Apply Filters
                            </button>
                            <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="px-6 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-all duration-200 font-medium flex items-center gap-2">
                                <i class="fas fa-redo"></i>
                                Reset
                            </a>
                        </div>
                    </div>
                    
                    <?php if ($status_filter !== 'all' || $category_filter !== 'all'): ?>
                    <div class="bg-blue-50 border-l-4 border-blue-400 p-4 rounded-r-lg">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-info-circle text-blue-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-blue-700">
                                    <strong>Active Filters:</strong> 
                                    <?php if ($status_filter !== 'all') echo "Status: " . $status_filter . " "; ?>
                                    <?php if ($category_filter !== 'all') echo "Category: " . $category_filter; ?>
                                    - Showing <?php echo $total_filtered; ?> of <?php echo $total_assets; ?> assets
                                </p>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Status Cards -->
            <div class="mb-8">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-6">
                    <div class="status-card relative overflow-hidden cursor-pointer bg-white rounded-xl shadow-lg animate-bounce-in" style="animation-delay: 0.1s;">
                        <a href="dashboard_ready.php" class="block p-6">
                            <div class="absolute inset-0 bg-gradient-to-br from-green-400 to-green-600 opacity-90"></div>
                            <div class="absolute right-4 top-4 opacity-30">
                                <i class="fas fa-check-circle text-white text-5xl"></i>
                            </div>
                            <div class="relative z-10">
                                <div class="flex items-center justify-between mb-4">
                                    <div class="p-3 rounded-xl bg-white bg-opacity-25 backdrop-blur-sm shadow-lg">
                                        <i class="fas fa-check-circle text-white text-2xl"></i>
                                    </div>
                                </div>
                                <div class="text-white">
                                    <h3 class="text-lg font-semibold mb-2">Ready</h3>
                                    <p class="text-3xl font-bold mb-1"><?php echo $jumlah_ready; ?></p>
                                    <p class="text-white text-opacity-90 text-sm flex items-center">
                                        <i class="fas fa-check-circle mr-2"></i>
                                        Available
                                    </p>
                                </div>
                            </div>
                        </a>
                    </div>

                    <div class="status-card relative overflow-hidden cursor-pointer bg-white rounded-xl shadow-lg animate-bounce-in" style="animation-delay: 0.2s;">
                        <a href="dashboard_kosong.php" class="block p-6">
                            <div class="absolute inset-0 bg-gradient-to-br from-red-400 to-red-600 opacity-90"></div>
                            <div class="absolute right-4 top-4 opacity-30">
                                <i class="fas fa-times-circle text-white text-5xl"></i>
                            </div>
                            <div class="relative z-10">
                                <div class="flex items-center justify-between mb-4">
                                    <div class="p-3 rounded-xl bg-white bg-opacity-25 backdrop-blur-sm shadow-lg">
                                        <i class="fas fa-times-circle text-white text-2xl"></i>
                                    </div>
                                </div>
                                <div class="text-white">
                                    <h3 class="text-lg font-semibold mb-2">Kosong</h3>
                                    <p class="text-3xl font-bold mb-1"><?php echo $jumlah_kosong; ?></p>
                                    <p class="text-white text-opacity-90 text-sm flex items-center">
                                        <i class="fas fa-ban mr-2"></i>
                                        Empty
                                    </p>
                                </div>
                            </div>
                        </a>
                    </div>

                    <div class="status-card relative overflow-hidden cursor-pointer bg-white rounded-xl shadow-lg animate-bounce-in" style="animation-delay: 0.3s;">
                        <a href="dashboard_repair.php" class="block p-6">
                            <div class="absolute inset-0 bg-gradient-to-br from-yellow-400 to-yellow-600 opacity-90"></div>
                            <div class="absolute right-4 top-4 opacity-30">
                                <i class="fas fa-tools text-white text-5xl"></i>
                            </div>
                            <div class="relative z-10">
                                <div class="flex items-center justify-between mb-4">
                                    <div class="p-3 rounded-xl bg-white bg-opacity-25 backdrop-blur-sm shadow-lg">
                                        <i class="fas fa-tools text-white text-2xl"></i>
                                    </div>
                                </div>
                                <div class="text-gray-900">
                                    <h3 class="text-lg font-semibold mb-2">Repair</h3>
                                    <p class="text-3xl font-bold mb-1"><?php echo $jumlah_repair; ?></p>
                                    <p class="text-gray-700 text-opacity-90 text-sm flex items-center">
                                        <i class="fas fa-wrench mr-2"></i>
                                        In Repair
                                    </p>
                                </div>
                            </div>
                        </a>
                    </div>

                    <div class="status-card relative overflow-hidden cursor-pointer bg-white rounded-xl shadow-lg animate-bounce-in" style="animation-delay: 0.4s;">
                        <a href="dashboard_ready.php" class="block p-6">
                            <div class="absolute inset-0 bg-gradient-to-br from-purple-400 to-purple-600 opacity-90"></div>
                            <div class="absolute right-4 top-4 opacity-30">
                                <i class="fas fa-hourglass-half text-white text-5xl"></i>
                            </div>
                            <div class="relative z-10">
                                <div class="flex items-center justify-between mb-4">
                                    <div class="p-3 rounded-xl bg-white bg-opacity-25 backdrop-blur-sm shadow-lg">
                                        <i class="fas fa-hourglass-half text-white text-2xl"></i>
                                    </div>
                                </div>
                                <div class="text-white">
                                    <h3 class="text-lg font-semibold mb-2">Temporary</h3>
                                    <p class="text-3xl font-bold mb-1"><?php echo $jumlah_temporary; ?></p>
                                    <p class="text-white text-opacity-90 text-sm flex items-center">
                                        <i class="fas fa-clock mr-2"></i>
                                        Temporary
                                    </p>
                                </div>
                            </div>
                        </a>
                    </div>

                    <div class="status-card relative overflow-hidden cursor-pointer bg-white rounded-xl shadow-lg animate-bounce-in" style="animation-delay: 0.5s;">
                        <a href="dashboard_ready.php" class="block p-6">
                            <div class="absolute inset-0 bg-gradient-to-br from-red-500 to-red-700 opacity-90"></div>
                            <div class="absolute right-4 top-4 opacity-30">
                                <i class="fas fa-user text-white text-5xl"></i>
                            </div>
                            <div class="relative z-10">
                                <div class="flex items-center justify-between mb-4">
                                    <div class="p-3 rounded-xl bg-white bg-opacity-25 backdrop-blur-sm shadow-lg">
                                        <i class="fas fa-user text-white text-2xl"></i>
                                    </div>
                                </div>
                                <div class="text-white">
                                    <h3 class="text-lg font-semibold mb-2">In Use</h3>
                                    <p class="text-3xl font-bold mb-1"><?php echo $jumlah_inuse; ?></p>
                                    <p class="text-white text-opacity-90 text-sm flex items-center">
                                        <i class="fas fa-circle mr-2"></i>
                                        Being Used
                                    </p>
                                </div>
                            </div>
                        </a>
                    </div>

                    <div class="status-card relative overflow-hidden cursor-pointer bg-white rounded-xl shadow-lg animate-bounce-in" style="animation-delay: 0.6s;">
                        <a href="dashboard_rusak.php" class="block p-6">
                            <div class="absolute inset-0 bg-gradient-to-br from-pink-500 to-pink-700 opacity-90"></div>
                            <div class="absolute right-4 top-4 opacity-30">
                                <i class="fas fa-exclamation-triangle text-white text-5xl"></i>
                            </div>
                            <div class="relative z-10">
                                <div class="flex items-center justify-between mb-4">
                                    <div class="p-3 rounded-xl bg-white bg-opacity-25 backdrop-blur-sm shadow-lg">
                                        <i class="fas fa-exclamation-triangle text-white text-2xl"></i>
                                    </div>
                                </div>
                                <div class="text-white">
                                    <h3 class="text-lg font-semibold mb-2">Rusak</h3>
                                    <p class="text-3xl font-bold mb-1"><?php echo $jumlah_rusak; ?></p>
                                    <p class="text-white text-opacity-90 text-sm flex items-center">
                                        <i class="fas fa-bug mr-2"></i>
                                        Broken
                                    </p>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                <!-- Bar Chart -->
                <div class="bg-white rounded-xl shadow-lg p-6 animate-slide-up">
                    <div class="mb-6">
                        <h2 class="text-xl font-semibold text-gray-900 mb-2 flex items-center gap-2">
                            <i class="fas fa-chart-bar text-orange-600"></i>
                            Asset Distribution (Bar Chart)
                        </h2>
                        <p class="text-gray-600">Overview of asset status across all categories</p>
                    </div>
                    <div class="relative h-80">
                        <canvas id="asset-bar-chart"></canvas>
                    </div>
                </div>

                <!-- Pie Chart -->
                <div class="bg-white rounded-xl shadow-lg p-6 animate-slide-up">
                    <div class="mb-6">
                        <h2 class="text-xl font-semibold text-gray-900 mb-2 flex items-center gap-2">
                            <i class="fas fa-chart-pie text-orange-600"></i>
                            Asset Distribution (Pie Chart)
                        </h2>
                        <p class="text-gray-600">Proportional view of asset status</p>
                    </div>
                    <div class="relative h-80">
                        <canvas id="asset-pie-chart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Asset Details Table -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="mb-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-2 flex items-center gap-2">
                        <i class="fas fa-table text-orange-600"></i>
                        Asset Details
                    </h2>
                    <p class="text-gray-600">
                        Showing <?php echo $total_filtered; ?> of <?php echo $total_assets; ?> assets
                        <?php if ($status_filter !== 'all'): ?>
                            (filtered by status: <span class="font-medium"><?php echo $status_filter; ?></span>)
                        <?php endif; ?>
                        <?php if ($category_filter !== 'all'): ?>
                            (filtered by category: <span class="font-medium"><?php echo $category_filter; ?></span>)
                        <?php endif; ?>
                    </p>
                </div>
                
                
                <div id="assets-table-container" class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Asset Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Location</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Brand</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Serial Number</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if ($detail_result && $detail_result->num_rows > 0): ?>
                                <?php while ($row = $detail_result->fetch_assoc()): ?>
                                    <tr class="table-row hover:bg-gray-50 transition-colors">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="font-medium text-gray-900"><?php echo htmlspecialchars($row['Nama_Barang']); ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                <?php echo htmlspecialchars($row['Nama_Barang'] ?: 'N/A'); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php
                                            $status_class = '';
                                            switch ($row['Status_Barang']) {
                                                case 'READY':
                                                    $status_class = 'bg-green-100 text-green-800';
                                                    break;
                                                case 'KOSONG':
                                                    $status_class = 'bg-red-100 text-red-800';
                                                    break;
                                                case 'REPAIR':
                                                    $status_class = 'bg-yellow-100 text-yellow-800';
                                                    break;
                                                case 'TEMPORARY':
                                                    $status_class = 'bg-purple-100 text-purple-800';
                                                    break;
                                                case 'IN USE':
                                                    $status_class = 'bg-red-600 text-white';
                                                    break;
                                                case 'RUSAK':
                                                    $status_class = 'bg-pink-100 text-pink-800';
                                                    break;
                                                default:
                                                    $status_class = 'bg-gray-100 text-gray-800';
                                            }
                                            ?>
                                            <span class="status-badge inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $status_class; ?>">
                                                <?php echo htmlspecialchars($row['Status_Barang']); ?>
                                            </span>
                                        </td>
                                         <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo htmlspecialchars($row['Lokasi'] ?: 'N/A'); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo htmlspecialchars($row['Merek'] ?: 'N/A'); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo htmlspecialchars($row['Serial_Number'] ?: 'N/A'); ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                                        <div class="flex flex-col items-center">
                                            <i class="fas fa-box-open text-4xl text-gray-300 mb-4"></i>
                                            <p class="text-lg font-medium">No assets found</p>
                                            <p class="text-sm">Try adjusting your filters or adding new assets.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <div id="pagination-container">
                <?php if ($total_pages > 1): ?>
                <div class="mt-6 flex items-center justify-between">
                    <div class="text-sm text-gray-600">
                        Showing <?php echo min($offset + 1, $total_filtered); ?> 
                        to <?php echo min($offset + $limit, $total_filtered); ?> 
                        of <?php echo $total_filtered; ?> results
                    </div>

                    <nav class="inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                        <!-- Previous Button -->
                        <?php if ($page > 1): ?>
                            <a href="<?php echo $_SERVER['PHP_SELF'] . '?' . http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
                            class="pagination-link relative inline-flex items-center px-3 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                <i class="fas fa-chevron-left"></i>
                                <span class="ml-1">Prev</span>
                            </a>
                        <?php endif; ?>

                        <!-- Page Numbers -->
                        <?php
                        $start = max(1, $page - 2);
                        $end = min($total_pages, $page + 2);
                        if ($start > 1) {
                            echo '<a href="' . $_SERVER['PHP_SELF'] . '?' . http_build_query(array_merge($_GET, ['page' => 1])) . '" class="pagination-link relative inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">1</a>';
                            if ($start > 2) {
                                echo '<span class="relative inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-500">...</span>';
                            }
                        }
                        for ($i = $start; $i <= $end; $i++) {
                            if ($i == $page) {
                                echo '<span class="relative z-10 inline-flex items-center px-3 py-2 border border-orange-500 bg-orange-50 text-sm font-medium text-orange-600">' . $i . '</span>';
                            } else {
                                echo '<a href="' . $_SERVER['PHP_SELF'] . '?' . http_build_query(array_merge($_GET, ['page' => $i])) . '" class="pagination-link relative inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">' . $i . '</a>';
                            }
                        }
                        if ($end < $total_pages) {
                            if ($end < $total_pages - 1) {
                                echo '<span class="relative inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-500">...</span>';
                            }
                            echo '<a href="' . $_SERVER['PHP_SELF'] . '?' . http_build_query(array_merge($_GET, ['page' => $total_pages])) . '" class="pagination-link relative inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">' . $total_pages . '</a>';
                        }
                        ?>

                        <!-- Next Button -->
                        <?php if ($page < $total_pages): ?>
                            <a href="<?php echo $_SERVER['PHP_SELF'] . '?' . http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" 
                            class="pagination-link relative inline-flex items-center px-3 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                <span class="mr-1">Next</span>
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </nav>
                </div>
                <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <script>
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
            initializeCharts();
        });

        // Chart initialization
        function initializeCharts() {
            const barCtx = document.getElementById('asset-bar-chart').getContext('2d');
new Chart(barCtx, {
    type: 'bar',
    data: {
        labels: ['Ready', 'Kosong', 'Repair', 'Temporary', 'In Use', 'Rusak'],
        datasets: [{
            label: 'Jumlah Asset',
            data: [
                <?php echo $jumlah_ready; ?>,
                <?php echo $jumlah_kosong; ?>,
                <?php echo $jumlah_repair; ?>,
                <?php echo $jumlah_temporary; ?>,
                <?php echo $jumlah_inuse; ?>,
                <?php echo $jumlah_rusak; ?>
            ],
            backgroundColor: [
                'rgba(34, 197, 94, 0.8)',
                'rgba(239, 68, 68, 0.8)',
                'rgba(251, 191, 36, 0.8)',
                'rgba(147, 51, 234, 0.8)',
                'rgba(220, 38, 38, 0.8)',
                'rgba(236, 72, 153, 0.8)'
            ],
            borderColor: [
                'rgb(34, 197, 94)',
                'rgb(239, 68, 68)',
                'rgb(251, 191, 36)',
                'rgb(147, 51, 234)',
                'rgb(220, 38, 38)',
                'rgb(236, 72, 153)'
            ],
            borderWidth: 2,
            borderRadius: 8,
            borderSkipped: false,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                titleColor: 'white',
                bodyColor: 'white',
                borderColor: 'rgba(255, 255, 255, 0.1)',
                borderWidth: 1,
                cornerRadius: 8,
                displayColors: false
            },
            
            // 👇 Tambahkan ini
        datalabels: {
            color: '#fff',
            font: { weight: 'bold', size: 14 },
            anchor: 'end',
            align: 'top',
            offset: -10,
            formatter: value => value > 0 ? value : ''
        }
    },
        scales: {
            x: {
                grid: {
                    display: false
                },
                ticks: {
                    font: {
                        weight: 'bold'
                    }
                }
            },
            y: {
                beginAtZero: true,
                grid: {
                    color: '#f3f4f6'
                },
                ticks: {
                    stepSize: 1
                }
            }
        },
        animation: {
            duration: 2000,
            easing: 'easeOutQuart'
        }
    }
});

            // Pie Chart
            const pieCtx = document.getElementById('asset-pie-chart').getContext('2d');
            new Chart(pieCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Ready', 'Kosong', 'Repair', 'Temporary', 'In Use', 'Rusak'],
                    datasets: [{
                        data: [
                            <?php echo $jumlah_ready; ?>,
                            <?php echo $jumlah_kosong; ?>,
                            <?php echo $jumlah_repair; ?>,
                            <?php echo $jumlah_temporary; ?>,
                            <?php echo $jumlah_inuse; ?>,
                            <?php echo $jumlah_rusak; ?>
                        ],
                        backgroundColor: [
                            'rgba(34, 197, 94, 0.8)',   // green
                            'rgba(239, 68, 68, 0.8)',   // red  
                            'rgba(251, 191, 36, 0.8)', // yellow
                            'rgba(147, 51, 234, 0.8)', // purple
                            'rgba(220, 38, 38, 0.8)',  // red-dark (In Use)
                            'rgba(236, 72, 153, 0.8)'  // pink
                        ],
                        borderColor: [
                            'rgb(34, 197, 94)',
                            'rgb(239, 68, 68)', 
                            'rgb(251, 191, 36)',
                            'rgb(147, 51, 234)',
                            'rgb(220, 38, 38)',
                            'rgb(236, 72, 153)'
                        ],
                        borderWidth: 2,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                font: {
                                    size: 12
                                }
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            titleColor: 'white',
                            bodyColor: 'white',
                            borderColor: 'rgba(255, 255, 255, 0.1)',
                            borderWidth: 1,
                            cornerRadius: 8,
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((value / total) * 100).toFixed(1);
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    },
                    cutout: '60%',
                    animation: {
                        duration: 2000,
                        easing: 'easeOutQuart'
                    }
                }
            });
        }
         // Loading Animation - Disable untuk pagination
    // Tangkap semua link pagination dan hide loading SEBELUM navigasi
    document.addEventListener('click', function(e) {
        const link = e.target.closest('a');
        const button = e.target.closest('.pagination-btn');
        
        if (link || button) {
            const href = link ? link.getAttribute('href') : null;
            
            // Jika adalah pagination/filter/search link
            if (href && (href.includes('page=') || href.includes('search=') || href.includes('status=') || 
                href.includes('category='))) {
                
                // LANGSUNG HIDE loading overlay SEBELUM navigasi
                const loadingOverlay = document.getElementById('loadingOverlay');
                if (loadingOverlay) {
                    loadingOverlay.style.display = 'none !important';
                    loadingOverlay.style.opacity = '0 !important';
                    loadingOverlay.style.visibility = 'hidden !important';
                }
            }
        }
    });

    // Hide loading setelah page load HANYA jika bukan dari pagination
    window.addEventListener('load', function() {
        const url = new URL(window.location);
        const hasPageParam = url.searchParams.has('page');
        
        // Jika URL punya parameter pagination, jangan tampilkan loading
        if (hasPageParam) {
            const loadingOverlay = document.getElementById('loadingOverlay');
            if (loadingOverlay) {
                loadingOverlay.style.display = 'none';
            }
            return;  // Exit, jangan tampilkan loading
        }
        
        // Jika bukan pagination, tampilkan loading normal 1.5 detik
        setTimeout(function() {
            const loadingOverlay = document.getElementById('loadingOverlay');
            if (loadingOverlay) {
                loadingOverlay.style.opacity = '0';
                setTimeout(function() {
                    loadingOverlay.style.display = 'none';
                }, 500);
            }
        }, 1500);
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

        // Close on outside click
        document.addEventListener('click', function(event) {
            const dropdownWrapper = document.querySelector('.sidebar-dropdown');
            if (dropdownOpenState && dropdownWrapper && !dropdownWrapper.contains(event.target)) {
                console.log('DEBUG: Click outside - closing dropdown');
                toggleDropdown();
            }
        });

        // Initialize charts (dengan null check)
        initializeCharts();
    });

    // Chart initialization (tetap sama, dengan check)
    function initializeCharts() {
        console.log('DEBUG: Initializing charts');
        
        // Bar Chart
        const barCtx = document.getElementById('asset-bar-chart');
        if (barCtx) {
            const barContext = barCtx.getContext('2d');
            new Chart(barContext, {
                type: 'bar',
                data: {
                    labels: ['Ready', 'Kosong', 'Repair', 'Temporary', 'In Use', 'Rusak'],
                    datasets: [{
                        label: 'Jumlah Asset',
                        data: [
                            <?php echo $jumlah_ready; ?>,
                            <?php echo $jumlah_kosong; ?>,
                            <?php echo $jumlah_repair; ?>,
                            <?php echo $jumlah_temporary; ?>,
                            <?php echo $jumlah_inuse; ?>,
                            <?php echo $jumlah_rusak; ?>
                        ],
                        backgroundColor: [
                            'rgba(34, 197, 94, 0.8)',
                            'rgba(239, 68, 68, 0.8)',
                            'rgba(251, 191, 36, 0.8)',
                            'rgba(147, 51, 234, 0.8)',
                            'rgba(220, 38, 38, 0.8)',
                            'rgba(236, 72, 153, 0.8)'
                        ],
                        borderColor: [
                            'rgb(34, 197, 94)',
                            'rgb(239, 68, 68)',
                            'rgb(251, 191, 36)',
                            'rgb(147, 51, 234)',
                            'rgb(220, 38, 38)',
                            'rgb(236, 72, 153)'
                        ],
                        borderWidth: 2,
                        borderRadius: 8,
                        borderSkipped: false,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            titleColor: 'white',
                            bodyColor: 'white',
                            borderColor: 'rgba(255, 255, 255, 0.1)',
                            borderWidth: 1,
                            cornerRadius: 8,
                            displayColors: false
                        }
                    },
                    scales: {
                        x: { grid: { display: false }, ticks: { font: { weight: 'bold' } } },
                        y: { beginAtZero: true, grid: { color: '#f3f4f6' }, ticks: { stepSize: 1 } }
                    },
                    animation: { duration: 2000, easing: 'easeOutQuart' }
                }
            });
            console.log('DEBUG: Bar chart initialized');
        } else {
            console.warn('WARN: Bar chart canvas not found');
        }

        // Pie Chart
        const pieCtx = document.getElementById('asset-pie-chart');
        if (pieCtx) {
            const pieContext = pieCtx.getContext('2d');
            new Chart(pieContext, {
                type: 'doughnut',
                data: {
                    labels: ['Ready', 'Kosong', 'Repair', 'Temporary', 'In Use', 'Rusak'],
                    datasets: [{
                        data: [
                            <?php echo $jumlah_ready; ?>,
                            <?php echo $jumlah_kosong; ?>,
                            <?php echo $jumlah_repair; ?>,
                            <?php echo $jumlah_temporary; ?>,
                            <?php echo $jumlah_inuse; ?>,
                            <?php echo $jumlah_rusak; ?>
                        ],
                        backgroundColor: [
                            'rgba(34, 197, 94, 0.8)',
                            'rgba(239, 68, 68, 0.8)',
                            'rgba(251, 191, 36, 0.8)',
                            'rgba(147, 51, 234, 0.8)',
                            'rgba(220, 38, 38, 0.8)',
                            'rgba(236, 72, 153, 0.8)'
                        ],
                        borderColor: [
                            'rgb(34, 197, 94)',
                            'rgb(239, 68, 68)',
                            'rgb(251, 191, 36)',
                            'rgb(147, 51, 234)',
                            'rgb(220, 38, 38)',
                            'rgb(236, 72, 153)'
                        ],
                        borderWidth: 2,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { padding: 20, font: { size: 12 } } },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            titleColor: 'white',
                            bodyColor: 'white',
                            borderColor: 'rgba(255, 255, 255, 0.1)',
                            borderWidth: 1,
                            cornerRadius: 8,
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    },
                    cutout: '60%',
                    animation: { duration: 2000, easing: 'easeOutQuart' }
                }
            });
            console.log('DEBUG: Pie chart initialized');
        } else {
            console.warn('WARN: Pie chart canvas not found');
        }
    }

    // Loading Animation - Disable untuk pagination
    window.addEventListener('load', function() {
        const url = new URL(window.location);
        const hasPageParam = url.searchParams.has('page');
        
        // Jika URL punya parameter pagination, jangan tampilkan loading
        if (hasPageParam) {
            const loadingOverlay = document.getElementById('loadingOverlay');
            if (loadingOverlay) {
                loadingOverlay.style.display = 'none';
            }
            return;
        }
        
        // Jika bukan pagination, tampilkan loading normal 1.5 detik
        setTimeout(function() {
            const loadingOverlay = document.getElementById('loadingOverlay');
            if (loadingOverlay) {
                loadingOverlay.style.opacity = '0';
                setTimeout(function() {
                    loadingOverlay.style.display = 'none';
                }, 500);
            }
        }, 1500);
    });

    console.log('DEBUG: Script fully loaded - Dropdown ready for clicks');


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


// Fungsi untuk format hari dan bulan dalam Bahasa Indonesia
function getIndonesianDay(dayIndex) {
    const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    return days[dayIndex];
}

function getIndonesianMonth(monthIndex) {
    const months = [
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    return months[monthIndex];
}

// Update waktu setiap detik
function updateRealTimeClock() {
    const now = new Date();
    
    // Ambil komponen waktu
    const dayName = getIndonesianDay(now.getDay());
    const day = String(now.getDate()).padStart(2, '0');
    const monthName = getIndonesianMonth(now.getMonth());
    const year = now.getFullYear();
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    const seconds = String(now.getSeconds()).padStart(2, '0');
    
    // Format: Minggu, 02 November 2025 | 09:52:20 WIB
    const timeString = `${dayName}, ${day} ${monthName} ${year} | ${hours}:${minutes}:${seconds} WIB`;
    
    // Update elemen di DOM
    document.getElementById('realtime-clock').textContent = timeString;
}

// Jalankan pertama kali saat halaman dimuat
updateRealTimeClock();

// Update setiap detik
setInterval(updateRealTimeClock, 1000);

function loadPage(page) {
    // Ambil semua query params saat ini
    const url = new URL(window.location.href);
    url.searchParams.set('page', page);

    const statusFilter = url.searchParams.get('status_filter') || 'all';
    const categoryFilter = url.searchParams.get('category_filter') || 'all';

    // Gunakan parameter di-encode dengan benar
    const fetchUrl = `${window.location.pathname}?action=ajax_get_assets&status_filter=${encodeURIComponent(statusFilter)}&category_filter=${encodeURIComponent(categoryFilter)}&page=${page}`;
    
    console.log('Fetching from:', fetchUrl);

    fetch(fetchUrl)
        .then(response => {
            console.log('Response status:', response.status);
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            console.log('Data received:', data);
            // Check if error di response
            if (data.error) {
                console.error('API Error:', data.error);
                alert('Error: ' + data.error);
                return;
            }
            
            if (data.table_html) {
                const tableContainer = document.getElementById('assets-table-container');
                const paginationContainer = document.getElementById('pagination-container');
                
                console.log('Table container found:', !!tableContainer);
                console.log('Pagination container found:', !!paginationContainer);
                
                if (!tableContainer) {
                    console.error('assets-table-container not found in DOM');
                    alert('Error: Table container not found');
                    return;
                }
                
                if (!paginationContainer) {
                    console.error('pagination-container not found in DOM');
                    alert('Error: Pagination container not found');
                    return;
                }
                
                tableContainer.innerHTML = '<table class="w-full">' + data.table_html + '</table>';
                paginationContainer.innerHTML = data.pagination_html;
                // Update URL history
                history.pushState({}, '', url.toString());
                
                // Re-attach pagination event listeners setelah HTML diupdate
                attachPaginationListeners();
                
                // Scroll ke atas tabel
                tableContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
            } else {
                console.error('No table_html in response');
                alert('Data tidak valid diterima dari server');
            }
        })
        .catch(err => {
            console.error('Fetch error:', err);
            alert('Gagal memuat data. Silakan coba lagi.\nError: ' + err.message);
        });
}

// Fungsi untuk attach pagination event listeners
function attachPaginationListeners() {
    // Tangkap semua pagination links
    const paginationContainer = document.getElementById('pagination-container');
    if (!paginationContainer) {
        console.log('Pagination container not found');
        return;
    }
    
    // Cari semua link pagination dengan class pagination-link
    const paginationLinks = paginationContainer.querySelectorAll('a.pagination-link');
    
    console.log('Found ' + paginationLinks.length + ' pagination links');
    
    paginationLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();  // Jangan reload halaman
            
            // Extract page number dari URL
            const href = this.getAttribute('href');
            const urlParams = new URLSearchParams(href.substring(href.indexOf('?') + 1));
            const page = urlParams.get('page');
            
            console.log('Pagination clicked - page from href:', page);
            
            if (page) {
                loadPage(parseInt(page));  // Load data via AJAX
            }
        });
    });
}

// Attach pagination listeners saat halaman pertama kali load
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOMContentLoaded - attaching pagination listeners');
    attachPaginationListeners();
});

// Backup call jika DOMContentLoaded sudah lewat
if (document.readyState === 'loading') {
    // Document masih loading, listener akan fire
} else {
    // Document sudah loaded, panggil langsung
    console.log('Document already loaded - attaching pagination listeners immediately');
    attachPaginationListeners();
}


    </script>
</body>
</html>