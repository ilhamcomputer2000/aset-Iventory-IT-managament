<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Mulai session
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Role Check
$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : 'super_admin';
if ($user_role !== 'super_admin') {
    header("Location: index.php");
    exit();
}

// Include koneksi database
include "../koneksi.php";

// Session variables
$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'User';
$Nama_Lengkap = isset($_SESSION['Nama_Lengkap']) ? $_SESSION['Nama_Lengkap'] : $username;

// Tampilkan jabatan (bukan label role hardcoded)
$Jabatan_Level = trim((string)($_SESSION['Jabatan_Level'] ?? ''));

// Fallback: jika session Jabatan_Level kosong, ambil dari DB berdasarkan user_id
if ($Jabatan_Level === '' && isset($_SESSION['user_id'])) {
    $stmtMeta = $kon->prepare("SELECT Jabatan_Level, Nama_Lengkap, role FROM users WHERE id = ? LIMIT 1");
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
        error_log('Failed to prepare user meta query (admin/lacak_asset.php): ' . $kon->error);
    }
}

$Jabatan_Level_Display = $Jabatan_Level !== '' ? $Jabatan_Level : '-';

// Set timezone
date_default_timezone_set('Asia/Jakarta');

// Pagination
$limit = 12;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$start_from = ($page - 1) * $limit;

// Query aset
$current_user = mysqli_real_escape_string($kon, $_SESSION['username']);
$stmt = $kon->prepare("SELECT * FROM peserta WHERE Id_Karyawan = ? ORDER BY Waktu DESC LIMIT ?, ?");
$stmt->bind_param("sii", $current_user, $start_from, $limit);
$stmt->execute();
$hasil = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ASSET IT CITRATEL</title>
    <script>
        window.tailwind = window.tailwind || {};
        window.tailwind.config = Object.assign({}, window.tailwind.config || {}, { darkMode: 'class' });
        (function () {
            try {
                var stored = localStorage.getItem('theme');
                var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                var useDark = stored === 'dark' || (stored !== 'light' && prefersDark);
                if (useDark) document.documentElement.classList.add('dark');
                else document.documentElement.classList.remove('dark');
            } catch (e) {}
        })();
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Custom animations for loading */
        @keyframes wave {
            0%, 100% { height: 1rem; }
            50% { height: 3rem; }
        }
        @keyframes progress {
            0% { width: 0%; }
            100% { width: 100%; }
        }
        .loading-bar { animation: wave 1s ease-in-out infinite; }
        .loading-bar:nth-child(1) { animation-delay: 0s; }
        .loading-bar:nth-child(2) { animation-delay: 0.1s; }
        .loading-bar:nth-child(3) { animation-delay: 0.2s; }
        .loading-progress-bar { animation: progress 2s ease-in-out forwards; }
    </style>
</head>
<body class="bg-gray-50 dark:bg-slate-900 dark:text-slate-100 dark:[&_.bg-white]:bg-slate-800 dark:[&_.text-gray-900]:text-slate-100 dark:[&_.text-gray-800]:text-slate-100 dark:[&_.text-gray-700]:text-slate-200 dark:[&_.text-gray-600]:text-slate-300 dark:[&_.border-gray-200]:border-slate-700 dark:[&_.border-gray-300]:border-slate-700 dark:[&_.divide-gray-100]:divide-slate-700">

    <!-- Loading Animation -->
<div id="loadingOverlay" class="fixed inset-0 bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 z-[100] flex items-center justify-center transition-opacity duration-500">
    <div class="text-center">
        <div class="mb-6 flex items-center justify-center">
            <div class="w-24 h-24 bg-white rounded-full flex items-center justify-center shadow-2xl animate-pulse overflow-hidden border-4 border-orange-400">
                <!-- Gambar logo dengan border lingkaran polos -->
                <img src="logo_form/logo ckt fix.png" alt="Logo PT CIPTA KARYA TECHNOLOGY" class="w-full h-full object-cover rounded-full">
            </div>
        </div>
        <h1 class="text-2xl md:text-3xl font-bold mb-2 text-white">PT CIPTA KARYA TECHNOLOGY</h1>
        <p class="text-gray-300 mb-6">Loading Sistem ASSET...</p>
        <div class="flex items-end justify-center space-x-2 h-16 mb-4">
            <div class="loading-bar w-2 bg-orange-400 rounded-full"></div>
            <div class="loading-bar w-2 bg-orange-400 rounded-full"></div>
            <div class="loading-bar w-2 bg-orange-400 rounded-full"></div>
        </div>
        <div class="w-64 h-2 bg-slate-700 rounded-full overflow-hidden mx-auto">
            <div class="loading-progress-bar h-full bg-gradient-to-r from-orange-400 to-orange-600 rounded-full"></div>
        </div>
    </div>
</div>

    <?php $activePage = 'lacak'; require_once __DIR__ . '/sidebar_admin.php'; ?>

    <!-- Main Content -->
    <div id="main-content-wrapper" class="lg:ml-60 transition-all duration-300 ease-in-out">
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
    <main class="pt-20 p-6">
        <div class="max-w-7xl mx-auto">
            <!-- Header -->
            <div class="mb-8 text-center">
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Asset PT CIPTA KARYA TECHNOLOGY</h1>
                <p class="text-gray-600">Asset yang sedang Anda gunakan</p>
            </div>

            <!-- Asset Grid -->
            <?php if (mysqli_num_rows($hasil) > 0): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php while ($row = mysqli_fetch_assoc($hasil)): ?>
                        <?php
                        $nama_barang = htmlspecialchars($row['Nama_Barang'] ?? '');
                        $merek = htmlspecialchars($row['Merek'] ?? '');
                        $type = htmlspecialchars($row['Type'] ?? '');
                        $jabatan = htmlspecialchars($row['Jabatan'] ?? '');
                        $photo_barang = htmlspecialchars($row['Photo_Barang'] ?? '');
                        $label = trim("$nama_barang $merek $type");
                        ?>
                        <div class="bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow duration-300 border border-gray-200">
                            <h3 class="text-lg font-bold text-gray-900 truncate mb-4"><?= $label ?></h3>
                            
                            <div class="space-y-2 mb-4">
                                <p class="text-sm text-gray-700">
                                    <strong>SN:</strong> <span class="text-gray-600"><?= htmlspecialchars($row['Serial_Number'] ?? '') ?></span>
                                </p>
                                <p class="text-sm text-gray-700">
                                    <strong>Lokasi:</strong> <span class="text-gray-600"><?= htmlspecialchars($row['Lokasi'] ?? '') ?></span>
                                </p>
                                <p class="text-sm text-gray-700">
                                    <strong>Jabatan:</strong> <span class="text-gray-600"><?= $jabatan ?></span>
                                </p>
                            </div>

                            <!-- Photo Asset -->
                            <?php if (!empty($photo_barang) && file_exists("../uploads/" . $photo_barang)): ?>
                                <div class="mt-4">
                                    <strong class="text-sm text-gray-700">Photo Asset:</strong>
                                    <img src="../uploads/<?= $photo_barang ?>" 
                                         alt="Foto <?= $label ?>" 
                                         class="mt-2 w-32 h-auto rounded border border-gray-300 shadow-sm">
                                </div>
                            <?php else: ?>
                                <p class="mt-4 text-sm">
                                    <strong class="text-gray-700">Photo Asset:</strong> 
                                    <span class="text-gray-500">Tidak tersedia</span>
                                </p>
                            <?php endif; ?>

                            <p class="text-sm text-gray-500 mt-4 pt-4 border-t border-gray-200">
                                Digunakan oleh: <?= htmlspecialchars($row['User_Perangkat'] ?? '') ?> (<?= htmlspecialchars($row['Id_Karyawan'] ?? '') ?>)
                            </p>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                            <div class="text-center py-20">
                <div class="mb-6">
                    <i class="fas fa-box-open text-6xl text-gray-300"></i>
                </div>
                <h2 class="text-2xl font-semibold text-gray-700 mb-4">Belum Ada Asset yang Dipinjam</h2>
                <p class="text-gray-500 text-lg mb-6">Anda belum meminjam aset apa pun saat ini. Silakan hubungi administrator untuk meminjam aset yang diperlukan.</p>
                <div class="flex justify-center space-x-4">
                    <a href="index.php" class="bg-orange-500 hover:bg-orange-600 text-white px-6 py-3 rounded-lg font-semibold transition-colors duration-300">
                        <i class="fas fa-plus mr-2"></i>Lihat Daftar Asset
                    </a>
                    <a href="serah_terima.php" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-3 rounded-lg font-semibold transition-colors duration-300">
                        <i class="fas fa-file-alt mr-2"></i>Ajukan Pinjaman
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>

    </div><!-- /main-content-wrapper -->

    <script>
        // Loading animation
        window.addEventListener('load', function() {
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