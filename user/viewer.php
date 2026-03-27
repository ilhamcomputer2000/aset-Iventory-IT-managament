<?php
ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Data - Modern Inventory</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Custom Styles -->
    <style>
        /* Loading Animation */
        .loading-container {
            position: fixed;
            inset: 0;
            background: linear-gradient(135deg, #fff7ed 0%, #ffffff 50%, #eff6ff 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }
        
        .loading-spinner {
            position: relative;
            width: 64px;
            height: 64px;
        }
        
        .loading-ring-1 {
            position: absolute;
            width: 64px;
            height: 64px;
            border: 4px solid #fed7aa;
            border-radius: 50%;
            animation: spin 2s linear infinite;
        }
        
        .loading-ring-2 {
            position: absolute;
            width: 64px;
            height: 64px;
            border: 4px solid #3b82f6;
            border-top: 4px solid transparent;
            border-radius: 50%;
            animation: spin 2s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Fade In Animation */
        .fade-in {
            animation: fadeIn 0.6s ease-in-out;
        }
        
        .fade-in-delay-1 { animation-delay: 0.2s; animation-fill-mode: both; }
        .fade-in-delay-2 { animation-delay: 0.3s; animation-fill-mode: both; }
        .fade-in-delay-3 { animation-delay: 0.4s; animation-fill-mode: both; }
        .fade-in-delay-4 { animation-delay: 0.5s; animation-fill-mode: both; }
        .fade-in-delay-5 { animation-delay: 0.6s; animation-fill-mode: both; }
        .fade-in-delay-6 { animation-delay: 0.7s; animation-fill-mode: both; }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Modal Animations */
        .modal-overlay {
            backdrop-filter: blur(4px);
            transition: all 0.3s ease-in-out;
        }
        
        .modal-content {
            transition: all 0.3s ease-in-out;
            transform: scale(0.9);
            opacity: 0;
        }
        
        .modal-content.show {
            transform: scale(1);
            opacity: 1;
        }
        
        /* Hover Effects */
        .hover-scale {
            transition: transform 0.3s ease-in-out;
        }
        
        .hover-scale:hover {
            transform: scale(1.05);
        }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f5f9;
        }
        
        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 3px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-orange-50 via-white to-blue-50 min-h-screen">
    <!-- Loading Spinner -->
    <div id="loading" class="loading-container">
        <div class="flex flex-col items-center space-y-4">
            <div class="loading-spinner">
                <div class="loading-ring-1"></div>
                <div class="loading-ring-2"></div>
            </div>
            <p class="text-lg text-gray-600">Loading inventory data...</p>
        </div>
    </div>

    <?php
    // Include file koneksi
    include "../koneksi.php";

    require_once __DIR__ . '/../token_id.php';

    $TOKEN_TTL_SECONDS = 900; // 15 menit
    $TOKEN_ROTATE_INTERVAL_SECONDS = 300; // rotate otomatis tiap 5 menit
    $TOKEN_ROTATE_BEFORE_SECONDS = 60; // rotate saat sisa waktu <= 1 menit

    // Fungsi untuk mencegah inputan karakter yang tidak sesuai
    function input($data) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data);
        return $data;
    }

    function formatRupiah($value) {
        $raw = trim((string)$value);
        if ($raw === '') {
            return '-';
        }

        $normalized = str_replace([',', ' '], ['', ''], $raw);
        if (!is_numeric($normalized)) {
            return $raw;
        }

        $amountInt = (int)round((float)$normalized);
        return 'Rp.' . number_format($amountInt, 0, ',', '.');
    }

    // Fungsi untuk mendapatkan icon status
    function getStatusIcon($status) {
        switch ($status) {
            case 'READY':
                return '<i data-lucide="check-circle" class="w-4 h-4"></i>';
            case 'KOSONG':
                return '<i data-lucide="x-circle" class="w-4 h-4"></i>';
            case 'REPAIR':
                return '<i data-lucide="wrench" class="w-4 h-4"></i>';
            case 'TEMPORARY':
                return '<i data-lucide="clock" class="w-4 h-4"></i>';
            case 'RUSAK':
                return '<i data-lucide="alert-triangle" class="w-4 h-4"></i>';
            default:
                return '<i data-lucide="package" class="w-4 h-4"></i>';
        }
    }

    // Fungsi untuk mendapatkan warna status
    function getStatusColor($status) {
        switch ($status) {
            case 'READY':
                return 'bg-green-100 text-green-800 border-green-200';
            case 'KOSONG':
                return 'bg-gray-100 text-gray-800 border-gray-200';
            case 'REPAIR':
                return 'bg-yellow-100 text-yellow-800 border-yellow-200';
            case 'TEMPORARY':
                return 'bg-blue-100 text-blue-800 border-blue-200';
            case 'RUSAK':
                return 'bg-red-100 text-red-800 border-red-200';
            default:
                return 'bg-gray-100 text-gray-800 border-gray-200';
        }
    }

    // Ambil ID dari URL: baru `id` (token), lama `id_peserta`
    $id_token = trim((string)($_GET['id'] ?? ''));
    $token_error = '';
    $token_payload = null;
    $id_peserta = 0;
    $using_token = false;

    if ($id_token !== '') {
        $token_payload = token_parse_id_payload($id_token, $token_error);
        if (is_array($token_payload)) {
            $id_peserta = (int)($token_payload['id'] ?? 0);
            $using_token = $id_peserta > 0;
        }
    }

    if ($id_peserta <= 0 && isset($_GET['id_peserta'])) {
        $id_peserta = (int)($_GET['id_peserta'] ?? 0);
    }

    if ($id_peserta <= 0) {
        $msg = ($id_token !== '' && $token_error !== '') ? ('Link tidak valid/expired. ' . $token_error) : 'ID Peserta tidak valid.';
        echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                Swal.fire({
                    title: 'Error!',
                    text: " . json_encode($msg) . ",
                    icon: 'error',
                    confirmButtonText: 'OK'
                }).then(function() {
                    window.location.href = 'index.php';
                });
            }, 500);
        });
        </script>";
        exit;
    }

    $rotate_requested = (string)($_GET['rotate'] ?? '') === '1';
    $need_rotate = false;
    if ($using_token && is_array($token_payload) && isset($token_payload['exp'])) {
        $remaining = (int)$token_payload['exp'] - time();
        $need_rotate = $remaining <= $TOKEN_ROTATE_BEFORE_SECONDS;
    }

    if (!$using_token || $rotate_requested || $need_rotate) {
        $newToken = token_make_id($id_peserta, $TOKEN_TTL_SECONDS);
        if ($newToken !== '') {
            header('Location: ' . token_current_script_url(['id' => $newToken]), true, 302);
            exit;
        }
    }

    // Ambil data dari database
    $sql = "SELECT * FROM peserta WHERE id_peserta = $id_peserta";
    $hasil = mysqli_query($kon, $sql);
    $data = mysqli_fetch_assoc($hasil);

    // Periksa apakah ID peserta ditemukan di database
    if (!$data) {
        echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                Swal.fire({
                    title: 'Error!',
                    text: 'ID Peserta tidak ditemukan.',
                    icon: 'error',
                    confirmButtonText: 'OK'
                }).then(function() {
                    window.location.href = 'index.php';
                });
            }, 500);
        });
        </script>";
        exit;
    }

    // Untuk auto-rotate token setelah jeda waktu
    $rotateUrl = token_current_script_url(['id' => $id_token, 'rotate' => 1]);
    $rotateAfterMs = (int)$TOKEN_ROTATE_INTERVAL_SECONDS * 1000;

        // Uploads helper (DB biasanya menyimpan path relatif di folder uploads)
        $uploadsBaseDir = realpath(__DIR__ . '/../uploads');
        $uploadsPrefix = '../uploads/';

        $sanitizeRelative = function($relative) {
            $rel = trim((string)$relative);
            $rel = str_replace('\\', '/', $rel);
            $rel = ltrim($rel, '/');
            if ($rel === '') {
                return '';
            }
            if (strpos($rel, '..') !== false) {
                return '';
            }
            if (preg_match('/^[a-zA-Z]:/', $rel)) {
                return '';
            }
            return $rel;
        };

        $resolveUploadPath = function($relative) use ($uploadsBaseDir, $sanitizeRelative) {
            $rel = $sanitizeRelative($relative);
            if ($rel === '' || $uploadsBaseDir === false) {
                return '';
            }
            return $uploadsBaseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        };

        $buildUploadUrl = function($relative) use ($uploadsPrefix, $sanitizeRelative) {
            $rel = $sanitizeRelative($relative);
            if ($rel === '') {
                return '';
            }
            return $uploadsPrefix . $rel;
        };

        // Parse Riwayat_Barang (JSON)
        $riwayatRaw = (string)($data['Riwayat_Barang'] ?? '');
        $riwayatList = [];
        $riwayatParseError = '';
        $riwayatNormalized = $riwayatRaw;

        if (trim($riwayatRaw) !== '') {
            $parsed = json_decode($riwayatRaw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $try = stripslashes($riwayatRaw);
                $parsed2 = json_decode($try, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $riwayatNormalized = $try;
                    $parsed = $parsed2;
                } else {
                    $try2 = html_entity_decode($riwayatRaw, ENT_QUOTES, 'UTF-8');
                    $parsed3 = json_decode($try2, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $riwayatNormalized = $try2;
                        $parsed = $parsed3;
                    }
                }
            }

            if (json_last_error() === JSON_ERROR_NONE) {
                if (is_string($parsed)) {
                    $parsedAgain = json_decode($parsed, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $parsed = $parsedAgain;
                    }
                }

                if (is_array($parsed)) {
                    if (count($parsed) === 0) {
                        $riwayatList = [];
                    } else {
                        $keys = array_keys($parsed);
                        $isAssoc = $keys !== range(0, count($keys) - 1);
                        $riwayatList = $isAssoc ? [$parsed] : $parsed;
                    }
                }
            } else {
                $riwayatParseError = json_last_error_msg();
                $riwayatList = [];
            }
        }
    ?>

    <!-- Main Content -->
    <div id="main-content" class="p-4 opacity-0 transition-opacity duration-500">
        <div class="max-w-6xl mx-auto">
            <div class="fade-in">
                <!-- Card Container -->
                <div class="bg-white/80 backdrop-blur-sm shadow-xl rounded-lg border-0 overflow-hidden">
                    <!-- Header -->
                    <div class="bg-gradient-to-r from-orange-500 to-blue-600 text-white p-8">
                        <div class="flex items-center justify-between flex-wrap gap-4">
                            <div class="flex items-center space-x-3">
                                <i data-lucide="package" class="w-8 h-8"></i>
                                <div>
                                    <h1 class="text-2xl font-bold">Detail Data Inventaris</h1>
                                    <p class="text-orange-100 mt-1">ID: <?php echo htmlspecialchars($data['id_peserta']); ?></p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 px-3 py-1 rounded-lg border <?php echo getStatusColor($data['Status_Barang']); ?>">
                                <?php echo getStatusIcon($data['Status_Barang']); ?>
                                <span class="font-medium"><?php echo htmlspecialchars($data['Status_Barang']); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Content -->
                    <div class="p-8 space-y-8">

                        <!-- Section 1: Basic Information (match update.php) -->
                        <div class="fade-in fade-in-delay-1">
                            <h3 class="flex items-center text-xl mb-4 bg-gradient-to-r from-orange-600 to-blue-600 bg-clip-text text-transparent">
                                <i data-lucide="info" class="w-5 h-5 mr-2 text-orange-500"></i>
                                Informasi Dasar
                            </h3>

                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                                <!-- Waktu -->
                                <div class="space-y-2">
                                    <label class="flex items-center gap-2 text-gray-700 font-medium">
                                        <i data-lucide="clock" class="w-4 h-4 text-blue-500"></i>
                                        Waktu
                                    </label>
                                    <input type="text" value="<?php echo htmlspecialchars($data['Waktu'] ?? '-'); ?>" readonly
                                           class="w-full px-3 py-2 border rounded-lg bg-gray-50 border-gray-200 focus:outline-none transition-colors">
                                </div>

                                <!-- Serial Number -->
                                <div class="space-y-2">
                                    <label class="flex items-center gap-2 text-gray-700 font-medium">
                                        <i data-lucide="barcode" class="w-4 h-4 text-orange-500"></i>
                                        Serial Number
                                    </label>
                                    <input type="text" value="<?php echo htmlspecialchars($data['Serial_Number'] ?? '-'); ?>" readonly
                                           class="w-full px-3 py-2 border rounded-lg bg-orange-50 border-orange-200 focus:outline-none transition-colors">
                                </div>

                                <!-- Nama Barang -->
                                <div class="space-y-2">
                                    <label class="flex items-center gap-2 text-gray-700 font-medium">
                                        <i data-lucide="tag" class="w-4 h-4 text-blue-500"></i>
                                        Nama Barang
                                    </label>
                                    <input type="text" value="<?php echo htmlspecialchars($data['Nama_Barang'] ?? '-'); ?>" readonly
                                           class="w-full px-3 py-2 border rounded-lg bg-blue-50 border-blue-200 focus:outline-none transition-colors">
                                </div>

                                <!-- Nomor Aset -->
                                <div class="space-y-2">
                                    <label class="flex items-center gap-2 text-gray-700 font-medium">
                                        <i data-lucide="hash" class="w-4 h-4 text-orange-500"></i>
                                        Nomor Aset
                                    </label>
                                    <input type="text" value="<?php echo htmlspecialchars($data['Nomor_Aset'] ?? '-'); ?>" readonly
                                           class="w-full px-3 py-2 border rounded-lg bg-orange-50 border-orange-200 focus:outline-none transition-colors">
                                </div>

                                <!-- Merek -->
                                <div class="space-y-2">
                                    <label class="flex items-center gap-2 text-gray-700 font-medium">
                                        <i data-lucide="copyright" class="w-4 h-4 text-orange-500"></i>
                                        Merek
                                    </label>
                                    <input type="text" value="<?php echo htmlspecialchars($data['Merek'] ?? '-'); ?>" readonly
                                           class="w-full px-3 py-2 border rounded-lg bg-orange-50 border-orange-200 focus:outline-none transition-colors">
                                </div>

                                <!-- Type -->
                                <div class="space-y-2">
                                    <label class="flex items-center gap-2 text-gray-700 font-medium">
                                        <i data-lucide="cog" class="w-4 h-4 text-blue-500"></i>
                                        Type
                                    </label>
                                    <input type="text" value="<?php echo htmlspecialchars($data['Type'] ?? '-'); ?>" readonly
                                           class="w-full px-3 py-2 border rounded-lg bg-blue-50 border-blue-200 focus:outline-none transition-colors">
                                </div>
                            </div>
                        </div>

                        <!-- Section 2: Specifications (match update.php) -->
                        <div class="fade-in fade-in-delay-2">
                            <h3 class="flex items-center text-xl mb-4 bg-gradient-to-r from-orange-600 to-blue-600 bg-clip-text text-transparent">
                                <i data-lucide="list" class="w-5 h-5 mr-2 text-orange-500"></i>
                                Spesifikasi & Detail
                            </h3>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                <!-- Spesifikasi -->
                                <div class="space-y-2">
                                    <label class="flex items-center gap-2 text-gray-700 font-medium">
                                        <i data-lucide="clipboard-list" class="w-4 h-4 text-blue-500"></i>
                                        Spesifikasi
                                    </label>
                                    <textarea readonly rows="6"
                                              class="w-full px-3 py-2 border rounded-lg bg-blue-50 border-blue-200 focus:outline-none resize-none transition-colors"><?php echo htmlspecialchars($data['Spesifikasi'] ?? ''); ?></textarea>
                                </div>

                                <!-- Kelengkapan Barang -->
                                <div class="space-y-2">
                                    <label class="flex items-center gap-2 text-gray-700 font-medium">
                                        <i data-lucide="check-circle" class="w-4 h-4 text-orange-500"></i>
                                        Kelengkapan Barang
                                    </label>
                                    <textarea readonly rows="6"
                                              class="w-full px-3 py-2 border rounded-lg bg-orange-50 border-orange-200 focus:outline-none resize-none transition-colors"><?php echo htmlspecialchars($data['Kelengkapan_Barang'] ?? ''); ?></textarea>
                                </div>

                                <!-- Tahun Rilis -->
                                <div class="space-y-2">
                                    <label class="flex items-center gap-2 text-gray-700 font-medium">
                                        <i data-lucide="calendar" class="w-4 h-4 text-blue-500"></i>
                                        Tahun Rilis
                                    </label>
                                    <input type="text" value="<?php echo htmlspecialchars($data['Tahun_Rilis'] ?? '-'); ?>" readonly
                                           class="w-full px-3 py-2 border rounded-lg bg-blue-50 border-blue-200 focus:outline-none transition-colors">
                                </div>

                                <!-- Waktu Pembelian -->
                                <div class="space-y-2">
                                    <label class="flex items-center gap-2 text-gray-700 font-medium">
                                        <i data-lucide="calendar-check" class="w-4 h-4 text-orange-500"></i>
                                        Waktu Pembelian
                                    </label>
                                    <input type="text" value="<?php echo htmlspecialchars($data['Waktu_Pembelian'] ?? '-'); ?>" readonly
                                           class="w-full px-3 py-2 border rounded-lg bg-orange-50 border-orange-200 focus:outline-none transition-colors">
                                </div>

                                <!-- Nama Vendor -->
                                <div class="space-y-2">
                                    <label class="flex items-center gap-2 text-gray-700 font-medium">
                                        <i data-lucide="store" class="w-4 h-4 text-blue-500"></i>
                                        Nama Vendor
                                    </label>
                                    <input type="text" value="<?php echo htmlspecialchars($data['Nama_Toko_Pembelian'] ?? '-'); ?>" readonly
                                           class="w-full px-3 py-2 border rounded-lg bg-blue-50 border-blue-200 focus:outline-none transition-colors">
                                </div>

                                <!-- Kategori Pembelian (dengan Link Pembelian) -->
                                <div class="space-y-2">
                                    <label class="flex items-center gap-2 text-gray-700 font-medium">
                                        <i data-lucide="tags" class="w-4 h-4 text-orange-500"></i>
                                        Kategori Pembelian
                                    </label>
                                    <input type="text" value="<?php echo htmlspecialchars($data['Kategori_Pembelian'] ?? '-'); ?>" readonly
                                           class="w-full px-3 py-2 border rounded-lg bg-orange-50 border-orange-200 focus:outline-none transition-colors">

                                    <?php
                                    $kategoriPembelianViewer = trim((string)($data['Kategori_Pembelian'] ?? ''));
                                    $linkPembelianViewer = trim((string)($data['Link_Pembelian'] ?? ''));
                                    $linkDisplay = '-';
                                    if (strcasecmp($kategoriPembelianViewer, 'Online') === 0 && $linkPembelianViewer !== '') {
                                        $linkDisplay = $linkPembelianViewer;
                                    }
                                    ?>
                                    <label class="flex items-center gap-2 text-gray-700 font-medium mt-3">
                                        <i data-lucide="link" class="w-4 h-4 text-blue-500"></i>
                                        Link Pembelian
                                    </label>
                                    <div class="flex items-center gap-2">
                                        <input type="text" value="<?php echo htmlspecialchars($linkDisplay); ?>" readonly
                                               class="flex-1 px-3 py-2 border rounded-lg bg-blue-50 border-blue-200 focus:outline-none transition-colors">
                                        <?php if ($linkDisplay !== '-' && preg_match('~^https?://~i', $linkDisplay)): ?>
                                            <a href="<?php echo htmlspecialchars($linkDisplay, ENT_QUOTES); ?>" target="_blank" rel="noopener noreferrer"
                                               class="px-3 py-2 rounded-lg border border-blue-200 bg-white hover:bg-blue-50 text-gray-700" title="Buka Link">
                                                <i data-lucide="external-link" class="w-4 h-4"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Section 3: Location & User Info (match update.php) -->
                        <div class="fade-in fade-in-delay-3">
                            <h3 class="flex items-center text-xl mb-4 bg-gradient-to-r from-orange-600 to-blue-600 bg-clip-text text-transparent">
                                <i data-lucide="map-pin" class="w-5 h-5 mr-2 text-orange-500"></i>
                                Lokasi & Pengguna
                            </h3>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <!-- Lokasi -->
                                <div class="space-y-2">
                                    <label class="flex items-center gap-2 text-gray-700 font-medium">
                                        <i data-lucide="building" class="w-4 h-4 text-blue-500"></i>
                                        Lokasi
                                    </label>
                                    <input type="text" value="<?php echo htmlspecialchars($data['Lokasi'] ?? '-'); ?>" readonly
                                           class="w-full px-3 py-2 border rounded-lg bg-blue-50 border-blue-200 focus:outline-none transition-colors">
                                </div>

                                <!-- ID Karyawan -->
                                <div class="space-y-2">
                                    <label class="flex items-center gap-2 text-gray-700 font-medium">
                                        <i data-lucide="id-card" class="w-4 h-4 text-orange-500"></i>
                                        ID Karyawan
                                    </label>
                                    <input type="text" value="<?php echo htmlspecialchars($data['Id_Karyawan'] ?? '-'); ?>" readonly
                                           class="w-full px-3 py-2 border rounded-lg bg-orange-50 border-orange-200 focus:outline-none transition-colors">
                                </div>

                                <!-- Jabatan -->
                                <div class="space-y-2">
                                    <label class="flex items-center gap-2 text-gray-700 font-medium">
                                        <i data-lucide="user" class="w-4 h-4 text-blue-500"></i>
                                        Jabatan
                                    </label>
                                    <input type="text" value="<?php echo htmlspecialchars($data['Jabatan'] ?? '-'); ?>" readonly
                                           class="w-full px-3 py-2 border rounded-lg bg-blue-50 border-blue-200 focus:outline-none transition-colors">
                                </div>
                            </div>
                        </div>

                        <!-- Section 4: Condition & History (match update.php) -->
                        <div class="fade-in fade-in-delay-4">
                            <h3 class="flex items-center text-xl mb-4 bg-gradient-to-r from-orange-600 to-blue-600 bg-clip-text text-transparent">
                                <i data-lucide="history" class="w-5 h-5 mr-2 text-orange-500"></i>
                                Kondisi & Riwayat
                            </h3>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mb-6">
                                <!-- Kondisi Barang -->
                                <div class="space-y-2">
                                    <label class="flex items-center gap-2 text-gray-700 font-medium">
                                        <i data-lucide="tool" class="w-4 h-4 text-blue-500"></i>
                                        Kondisi Barang
                                    </label>
                                    <textarea readonly rows="6"
                                              class="w-full px-3 py-2 border rounded-lg bg-blue-50 border-blue-200 focus:outline-none resize-none transition-colors"><?php echo htmlspecialchars($data['Kondisi_Barang'] ?? ''); ?></textarea>
                                </div>

                                <!-- Riwayat Barang -->
                                <div class="space-y-2">
                                    <label class="flex items-center gap-2 text-gray-700 font-medium">
                                        <i data-lucide="book" class="w-4 h-4 text-orange-500"></i>
                                        Riwayat Barang
                                    </label>
                                    <?php if (!empty($riwayatList)): ?>
                                        <div class="space-y-2 max-h-64 overflow-y-auto border border-gray-200 rounded-lg p-3 bg-gray-50">
                                            <?php foreach ($riwayatList as $item): ?>
                                                <?php
                                                $nama = (string)($item['nama'] ?? '');
                                                $jab = (string)($item['jabatan'] ?? '');
                                                $empl = (string)($item['empleId'] ?? '');
                                                $lok = (string)($item['lokasi'] ?? '');
                                                $tglSerah = (string)($item['tgl_serah_terima'] ?? '');
                                                $tglKembali = (string)($item['tgl_pengembalian'] ?? '');
                                                $catatan = (string)($item['catatan'] ?? '');
                                                ?>
                                                <div class="bg-white border border-gray-200 rounded-lg p-3">
                                                    <div class="font-semibold text-gray-800 text-sm"><?php echo htmlspecialchars($nama !== '' ? $nama : '-'); ?></div>
                                                    <div class="text-xs text-gray-700 mt-1 space-y-0.5">
                                                        <?php if ($jab !== ''): ?><div><strong>Jabatan:</strong> <?php echo htmlspecialchars($jab); ?></div><?php endif; ?>
                                                        <?php if ($empl !== ''): ?><div><strong>Employee ID:</strong> <?php echo htmlspecialchars($empl); ?></div><?php endif; ?>
                                                        <?php if ($lok !== ''): ?><div><strong>Lokasi:</strong> <?php echo htmlspecialchars($lok); ?></div><?php endif; ?>
                                                        <?php if ($tglSerah !== ''): ?><div><strong>Tanggal Serah:</strong> <?php echo htmlspecialchars($tglSerah); ?></div><?php endif; ?>
                                                        <?php if ($tglKembali !== ''): ?><div><strong>Tanggal Kembali:</strong> <?php echo htmlspecialchars($tglKembali); ?></div><?php endif; ?>
                                                        <?php if ($catatan !== ''): ?><div><strong>Catatan:</strong> <?php echo htmlspecialchars($catatan); ?></div><?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <textarea readonly rows="6"
                                                  class="w-full px-3 py-2 border rounded-lg bg-orange-50 border-orange-200 focus:outline-none resize-none transition-colors"><?php echo htmlspecialchars($riwayatNormalized); ?></textarea>
                                        <?php if (!empty($riwayatParseError)): ?>
                                            <div class="text-xs text-red-600">Riwayat tersimpan tapi gagal dibaca: <?php echo htmlspecialchars($riwayatParseError); ?></div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- User Perangkat -->
                            <div class="space-y-2">
                                <label class="flex items-center gap-2 text-gray-700 font-medium">
                                    <i data-lucide="user-cog" class="w-4 h-4 text-blue-500"></i>
                                    User yang menggunakan Perangkat
                                </label>
                                <textarea readonly rows="3"
                                          class="w-full px-3 py-2 border rounded-lg bg-gray-50 border-gray-200 focus:outline-none resize-none transition-colors"><?php echo htmlspecialchars($data['User_Perangkat'] ?? ''); ?></textarea>
                            </div>
                        </div>

                        <!-- Section 6: Status Information (match update.php) -->
                        <div class="fade-in fade-in-delay-5">
                            <h3 class="flex items-center text-xl mb-4 bg-gradient-to-r from-orange-600 to-blue-600 bg-clip-text text-transparent">
                                <i data-lucide="info" class="w-5 h-5 mr-2 text-orange-500"></i>
                                Status & Kategori
                            </h3>

                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                                <!-- Jenis Barang -->
                                <div class="space-y-2">
                                    <label class="flex items-center gap-2 text-gray-700 font-medium">
                                        <i data-lucide="sitemap" class="w-4 h-4 text-blue-500"></i>
                                        Jenis Barang
                                    </label>
                                    <select disabled class="w-full px-3 py-2 border rounded-lg bg-blue-50 border-blue-200 focus:outline-none">
                                        <option value="INVENTARIS" <?php if (($data['Jenis_Barang'] ?? '') == 'INVENTARIS') echo 'selected'; ?>>INVENTARIS</option>
                                        <option value="LOP" <?php if (($data['Jenis_Barang'] ?? '') == 'LOP') echo 'selected'; ?>>LOP</option>
                                    </select>
                                </div>

                                <!-- Status Barang -->
                                <div class="space-y-2">
                                    <label class="flex items-center gap-2 text-gray-700 font-medium">
                                        <i data-lucide="signal" class="w-4 h-4 text-orange-500"></i>
                                        Status Barang
                                    </label>
                                    <select disabled class="w-full px-3 py-2 border rounded-lg bg-orange-50 border-orange-200 focus:outline-none">
                                        <option value="READY" <?php if (($data['Status_Barang'] ?? '') == 'READY') echo 'selected'; ?>>READY</option>
                                        <option value="IN USE" <?php if (($data['Status_Barang'] ?? '') == 'IN USE') echo 'selected'; ?>>IN USE</option>
                                        <option value="KOSONG" <?php if (($data['Status_Barang'] ?? '') == 'KOSONG') echo 'selected'; ?>>KOSONG</option>
                                        <option value="REPAIR" <?php if (($data['Status_Barang'] ?? '') == 'REPAIR') echo 'selected'; ?>>REPAIR</option>
                                        <option value="TEMPORARY" <?php if (($data['Status_Barang'] ?? '') == 'TEMPORARY') echo 'selected'; ?>>TEMPORARY</option>
                                        <option value="RUSAK" <?php if (($data['Status_Barang'] ?? '') == 'RUSAK') echo 'selected'; ?>>RUSAK</option>
                                    </select>
                                </div>

                                <!-- Status LOP -->
                                <div class="space-y-2">
                                    <label class="flex items-center gap-2 text-gray-700 font-medium">
                                        <i data-lucide="credit-card" class="w-4 h-4 text-blue-500"></i>
                                        Status LOP
                                    </label>
                                    <input type="text" value="<?php echo htmlspecialchars($data['Status_LOP'] ?? '-'); ?>" readonly
                                           class="w-full px-3 py-2 border rounded-lg bg-blue-50 border-blue-200 focus:outline-none">
                                </div>

                                <!-- Status Kelayakan -->
                                <div class="space-y-2">
                                    <label class="flex items-center gap-2 text-gray-700 font-medium">
                                        <i data-lucide="check-double" class="w-4 h-4 text-orange-500"></i>
                                        Status Kelayakan
                                    </label>
                                    <input type="text" value="<?php echo htmlspecialchars($data['Status_Kelayakan_Barang'] ?? '-'); ?>" readonly
                                           class="w-full px-3 py-2 border rounded-lg bg-orange-50 border-orange-200 focus:outline-none">
                                </div>

                                <!-- Harga Barang -->
                                <div class="space-y-2">
                                    <label class="flex items-center gap-2 text-gray-700 font-medium">
                                        <i data-lucide="tags" class="w-4 h-4 text-blue-500"></i>
                                        Harga Barang
                                    </label>
                                    <input type="text" value="<?php echo htmlspecialchars(formatRupiah($data['Harga_Barang'] ?? '')); ?>" readonly
                                           class="w-full px-3 py-2 border rounded-lg bg-blue-50 border-blue-200 focus:outline-none">
                                </div>
                            </div>
                        </div>

                        <!-- Section 7: Upload Semua Foto (match update.php) -->
                        <div class="fade-in fade-in-delay-6">
                            <h3 class="flex items-center text-xl mb-4 bg-gradient-to-r from-orange-600 to-blue-600 bg-clip-text text-transparent">
                                <i data-lucide="images" class="w-5 h-5 mr-2 text-orange-500"></i>
                                Upload Foto Barang (4 Sisi)
                            </h3>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <?php
                                $photoFields = [
                                    ['key' => 'Photo_Barang', 'label' => 'Foto Barang (Umum)', 'icon' => 'camera'],
                                    ['key' => 'Photo_Depan', 'label' => 'Foto Depan', 'icon' => 'camera'],
                                    ['key' => 'Photo_Belakang', 'label' => 'Foto Belakang', 'icon' => 'camera'],
                                    ['key' => 'Photo_SN', 'label' => 'Foto Serial Number', 'icon' => 'camera'],
                                    ['key' => 'Photo_Invoice', 'label' => 'Foto Invoice', 'icon' => 'file-text'],
                                ];

                                foreach ($photoFields as $pf) {
                                    $rel = (string)($data[$pf['key']] ?? '');
                                    $relSan = $sanitizeRelative($rel);
                                    $url = $buildUploadUrl($relSan);
                                    $abs = $resolveUploadPath($relSan);
                                    $exists = ($abs !== '' && is_file($abs));
                                    $ext = strtolower(pathinfo($relSan, PATHINFO_EXTENSION));
                                    $isPdf = ($pf['key'] === 'Photo_Invoice' && $ext === 'pdf');

                                    echo '<div class="bg-white border border-gray-200 rounded-lg p-4">';
                                    echo '<div class="flex items-center gap-2 text-sm font-medium text-gray-700 mb-3">';
                                    echo '<i data-lucide="' . htmlspecialchars($pf['icon']) . '" class="w-4 h-4 text-blue-500"></i>';
                                    echo htmlspecialchars($pf['label']);
                                    echo '</div>';

                                    if ($exists && $url !== '') {
                                        if ($isPdf) {
                                            echo '<div class="w-full h-40 bg-gray-50 border border-dashed border-gray-300 rounded-lg flex flex-col items-center justify-center">';
                                            echo '<i data-lucide="file-text" class="w-10 h-10 text-red-500"></i>';
                                            echo '<a href="' . htmlspecialchars($url, ENT_QUOTES) . '" target="_blank" rel="noopener noreferrer" class="mt-2 text-blue-600 underline text-sm">Buka PDF</a>';
                                            echo '</div>';
                                        } else {
                                            echo '<div class="relative hover-scale cursor-pointer" onclick="openModal(\'' . htmlspecialchars($url, ENT_QUOTES) . '\')">';
                                            echo '<img src="' . htmlspecialchars($url, ENT_QUOTES) . '" alt="' . htmlspecialchars($pf['label'], ENT_QUOTES) . '" class="w-full h-40 object-cover rounded-lg shadow border-2 border-orange-200" />';
                                            echo '<div class="absolute inset-0 bg-black bg-opacity-0 hover:bg-opacity-20 rounded-lg transition-all duration-300 flex items-center justify-center">';
                                            echo '<span class="text-white opacity-0 hover:opacity-100 transition-opacity duration-300 text-sm">Click to enlarge</span>';
                                            echo '</div>';
                                            echo '</div>';
                                        }
                                    } else {
                                        echo '<div class="w-full h-40 bg-gray-100 rounded-lg border-2 border-dashed border-gray-300 flex items-center justify-center">';
                                        echo '<div class="text-center text-gray-500">';
                                        echo '<i data-lucide="image" class="w-10 h-10 mx-auto mb-2"></i>';
                                        echo '<p class="text-sm">Tidak tersedia</p>';
                                        echo '</div>';
                                        echo '</div>';
                                    }

                                    echo '</div>';
                                }
                                ?>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="fade-in fade-in-delay-6">
                            <div class="flex justify-start pt-6 border-t border-gray-200">
                                <button onclick="window.history.back()" 
                                        class="flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-orange-500 to-blue-600 text-white rounded-lg border-0 hover:from-orange-600 hover:to-blue-700 transition-all duration-300 font-medium">
                                    <i data-lucide="arrow-left" class="w-4 h-4"></i>
                                    Kembali
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal untuk menampilkan gambar -->
    <div id="imageModal" class="fixed inset-0 bg-black bg-opacity-75 modal-overlay hidden items-center justify-center z-50" onclick="closeModal()">
        <div class="relative max-w-4xl max-h-[90vh] mx-4">
            <button onclick="closeModal()" class="absolute top-4 right-4 text-white hover:text-gray-300 transition-colors z-10">
                <i data-lucide="x" class="w-8 h-8"></i>
            </button>
            <img id="modalImage" src="" alt="Gambar Besar" class="modal-content max-w-full max-h-full rounded-lg">
        </div>
    </div>

    <script>
        const TOKEN_ROTATE_URL = <?php echo json_encode($rotateUrl ?: ''); ?>;
        const TOKEN_ROTATE_AFTER_MS = <?php echo (int)$rotateAfterMs; ?>;

        if (TOKEN_ROTATE_URL && TOKEN_ROTATE_AFTER_MS > 0) {
            setTimeout(() => {
                window.location.replace(TOKEN_ROTATE_URL);
            }, TOKEN_ROTATE_AFTER_MS);
        }

        // Initialize Lucide icons
        lucide.createIcons();

        // Loading screen
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                document.getElementById('loading').style.display = 'none';
                document.getElementById('main-content').style.opacity = '1';
            }, 1500);
        });

        // Modal functions
        function openModal(imageSrc) {
            const modal = document.getElementById('imageModal');
            const modalImage = document.getElementById('modalImage');
            
            modalImage.src = imageSrc;
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            
            setTimeout(() => {
                modalImage.classList.add('show');
            }, 10);
            
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            const modal = document.getElementById('imageModal');
            const modalImage = document.getElementById('modalImage');
            
            modalImage.classList.remove('show');
            
            setTimeout(() => {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                document.body.style.overflow = 'auto';
            }, 300);
        }

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });

        // Prevent modal close when clicking on image
        document.getElementById('modalImage').addEventListener('click', function(e) {
            e.stopPropagation();
        });
    </script>
</body>
</html>