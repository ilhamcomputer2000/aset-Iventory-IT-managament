<?php
// Koneksi ke database
// $conn = new mysqli("localhost", "root", "", "crud");
$conn = new mysqli("localhost", "cktnosa2_admin", "uGXj8#eiI=P%", "cktnosa2_crud", "3306");

// Periksa koneksi
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode($data) {
    $b64 = strtr((string)$data, '-_', '+/');
    $pad = strlen($b64) % 4;
    if ($pad) {
        $b64 .= str_repeat('=', 4 - $pad);
    }
    return base64_decode($b64, true);
}

function getQrTokenSecret() {
    $tempDir = __DIR__ . '/temp';
    if (!is_dir($tempDir)) {
        @mkdir($tempDir, 0777, true);
    }

    $secretFile = $tempDir . '/qr_token_secret.php';
    if (is_file($secretFile)) {
        $loaded = include $secretFile;
        if (is_string($loaded) && $loaded !== '') {
            return $loaded;
        }
    }

    $secret = bin2hex(random_bytes(32));
    $content = "<?php\nreturn " . var_export($secret, true) . ";\n";
    @file_put_contents($secretFile, $content, LOCK_EX);
    return $secret;
}

function makeEncryptedIdToken($idPeserta, $ttlSeconds = 315360000) {
    $id = (int)$idPeserta;
    if ($id <= 0) {
        return '';
    }

    $secret = getQrTokenSecret();
    $key = hash('sha256', $secret, true);
    $iv = random_bytes(16);

    $payload = json_encode([
        'id' => $id,
        'iat' => time(),
        'exp' => time() + (int)$ttlSeconds,
    ], JSON_UNESCAPED_SLASHES);

    $ciphertext = openssl_encrypt($payload, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    if ($ciphertext === false) {
        return '';
    }

    $mac = hash_hmac('sha256', $iv . $ciphertext, $key, true);
    return base64url_encode($iv . $ciphertext . $mac);
}

function parseEncryptedIdToken($token, &$error = '') {
    $error = '';
    $raw = base64url_decode($token);
    if ($raw === false) {
        $error = 'Token tidak valid.';
        return 0;
    }

    if (strlen($raw) < (16 + 32 + 1)) {
        $error = 'Token terlalu pendek.';
        return 0;
    }

    $secret = getQrTokenSecret();
    $key = hash('sha256', $secret, true);

    $iv = substr($raw, 0, 16);
    $mac = substr($raw, -32);
    $ciphertext = substr($raw, 16, -32);

    $expected = hash_hmac('sha256', $iv . $ciphertext, $key, true);
    if (!hash_equals($expected, $mac)) {
        $error = 'Token signature tidak cocok.';
        return 0;
    }

    $plaintext = openssl_decrypt($ciphertext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    if ($plaintext === false || $plaintext === '') {
        $error = 'Token gagal didekripsi.';
        return 0;
    }

    $payload = json_decode($plaintext, true);
    if (!is_array($payload)) {
        $error = 'Token payload tidak valid.';
        return 0;
    }

    $id = (int)($payload['id'] ?? 0);
    $exp = (int)($payload['exp'] ?? 0);
    if ($id <= 0) {
        $error = 'ID tidak valid.';
        return 0;
    }
    if ($exp > 0 && time() > $exp) {
        $error = 'Token sudah expired.';
        return 0;
    }
    return $id;
}

function getBaseUrlForShare() {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return $scheme . '://' . $host . ($dir !== '' && $dir !== '.' ? $dir : '');
}

// Ambil ID dari parameter URL (baru: token encrypted `id`, lama: `id_peserta`)
$id_peserta = 0;
$id_token = trim((string)($_GET['id'] ?? ''));
$token_error = '';
if ($id_token !== '') {
    $idFromToken = parseEncryptedIdToken($id_token, $token_error);
    if ($idFromToken > 0) {
        $id_peserta = $idFromToken;
    }
}
if ($id_peserta <= 0) {
    $id_peserta = isset($_GET['id_peserta']) ? intval($_GET['id_peserta']) : 0;
}

// Ambil data berdasarkan ID
$sql = "SELECT * FROM peserta WHERE id_peserta = $id_peserta";
$result = $conn->query($sql);

// Periksa apakah data ditemukan
if ($result->num_rows > 0) {
    $data = $result->fetch_assoc();
} else {
    $data = null;
}

// Redirect legacy URL (?id_peserta=) to encrypted token URL (?id=)
if (!isset($_GET['action']) && $data && ($id_token === '')) {
    $newToken = makeEncryptedIdToken($id_peserta);
    if ($newToken !== '') {
        $redirectUrl = getBaseUrlForShare() . '/qr_print.php?id=' . urlencode($newToken);
        header('Location: ' . $redirectUrl, true, 302);
        exit;
    }
}

// Buat QR Code jika data ditemukan
$qrFile = '';
$shareUrl = '';
if ($data) {
    require_once __DIR__ . '/phpqrcode/qrlib.php';

    $newToken = makeEncryptedIdToken($id_peserta);
    $baseUrl = getBaseUrlForShare();
    $shareUrl = $baseUrl . '/qr_print.php?id=' . urlencode($newToken);

    $qrContent = $shareUrl;
    $qrFile = 'temp/qr_code_' . $id_peserta . '.png';
    
    // Buat folder temp jika belum ada
    if (!file_exists('temp')) {
        mkdir('temp', 0777, true);
    }
    
    QRcode::png($qrContent, $qrFile, QR_ECLEVEL_L, 10);
}


// Handle AJAX requests untuk total data
if (isset($_GET['action']) && $_GET['action'] == 'get_total_data') {
    $total_sql = "SELECT COUNT(*) as total FROM peserta";
    $total_result = $conn->query($total_sql);
    $total_data = $total_result->fetch_assoc();
    
    header('Content-Type: application/json');
    echo json_encode(['total' => $total_data['total']]);
    exit;
}

// FIX: Fungsi untuk render teks sebagai list (bullet points) - Kompatibel dengan $conn
function renderAsList($text, $listType = 'ul') {  // 'ul' untuk bullet, 'ol' untuk numbered
    if (empty($text) || $text === '') {
        return '<p class="text-gray-500 italic text-sm">Tidak ada data</p>';
    }
    
    // Escape HTML dulu
    $text = htmlspecialchars($text);
    
    // Split berdasarkan newline (baris baru)
    $items = explode("\n", $text);
    
    // Opsional: Jika tidak ada newline, split berdasarkan kalimat (titik atau koma) - uncomment jika perlu force list
    // if (count($items) <= 1) {
    //     $items = preg_split('/[.;,]+/', trim($text));  // Split kalimat
    // }
    
    // Filter empty items dan trim
    $items = array_filter(array_map('trim', $items));
    
    if (empty($items)) {
        return '<p class="text-gray-500 italic text-sm">Tidak ada data</p>';
    }
    
    // Render sebagai list
    $listItems = '';
    foreach ($items as $item) {
        if (!empty($item)) {
            $listItems .= '<li class="text-sm leading-relaxed text-gray-700">' . $item . '</li>';
        }
    }
    
    $listTag = ($listType === 'ol') ? 'ol' : 'ul';
    return '<' . $listTag . ' class="space-y-1 pl-5 list-disc marker:text-orange-500">' . $listItems . '</' . $listTag . '>';
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

function sanitizeRelativePath($relative) {
    $rel = trim((string)$relative);
    $rel = str_replace('\\', '/', $rel);
    $rel = ltrim($rel, '/');
    if (stripos($rel, 'uploads/') === 0) {
        $rel = substr($rel, strlen('uploads/'));
        $rel = ltrim($rel, '/');
    }
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
}

function buildUploadUrl($relative) {
    $rel = sanitizeRelativePath($relative);
    if ($rel === '') {
        return '';
    }
    return 'uploads/' . $rel;
}

function isValidHttpUrl($url) {
    $u = trim((string)$url);
    return (bool)preg_match('~^https?://~i', $u);
}

function tryParseRiwayatList($riwayatRaw, &$parseError = '') {
    $parseError = '';
    $raw = trim((string)$riwayatRaw);
    if ($raw === '') {
        return [];
    }

    $candidates = [$raw, stripslashes($raw), html_entity_decode($raw, ENT_QUOTES, 'UTF-8')];
    foreach ($candidates as $cand) {
        $decoded = json_decode($cand, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            if (is_string($decoded)) {
                $decoded2 = json_decode($decoded, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $decoded = $decoded2;
                }
            }

            if (is_array($decoded)) {
                if (count($decoded) === 0) {
                    return [];
                }
                $keys = array_keys($decoded);
                $isAssoc = $keys !== range(0, count($keys) - 1);
                return $isAssoc ? [$decoded] : $decoded;
            }
            return [];
        }
    }

    $parseError = json_last_error_msg();
    return [];
}

function renderRiwayatJsonCards($riwayatList) {
    if (empty($riwayatList)) {
        return '';
    }

    $html = '<div class="space-y-2">';
    foreach ($riwayatList as $item) {
        if (!is_array($item)) {
            continue;
        }

        $nama = (string)($item['nama'] ?? '');
        $jab = (string)($item['jabatan'] ?? '');
        $empl = (string)($item['empleId'] ?? '');
        $lok = (string)($item['lokasi'] ?? '');
        $tglSerah = (string)($item['tgl_serah_terima'] ?? '');
        $tglKembali = (string)($item['tgl_pengembalian'] ?? '');
        $catatan = (string)($item['catatan'] ?? '');

        $html .= '<div class="bg-white/70 border border-gray-200 rounded-lg p-3">';
        $html .= '<div class="font-semibold text-gray-800 text-sm">' . htmlspecialchars($nama !== '' ? $nama : '-') . '</div>';
        $html .= '<div class="text-xs text-gray-700 mt-1 space-y-0.5">';
        if ($jab !== '') $html .= '<div><strong>Jabatan:</strong> ' . htmlspecialchars($jab) . '</div>';
        if ($empl !== '') $html .= '<div><strong>Employee ID:</strong> ' . htmlspecialchars($empl) . '</div>';
        if ($lok !== '') $html .= '<div><strong>Lokasi:</strong> ' . htmlspecialchars($lok) . '</div>';
        if ($tglSerah !== '') $html .= '<div><strong>Tgl Serah:</strong> ' . htmlspecialchars($tglSerah) . '</div>';
        if ($tglKembali !== '') $html .= '<div><strong>Tgl Kembali:</strong> ' . htmlspecialchars($tglKembali) . '</div>';
        if ($catatan !== '') $html .= '<div><strong>Catatan:</strong> ' . htmlspecialchars($catatan) . '</div>';
        $html .= '</div>';
        $html .= '</div>';
    }
    $html .= '</div>';
    return $html;
}

// Prepare riwayat parsed list (for nicer rendering)
$riwayatParseError = '';
$riwayatList = (isset($data) && is_array($data)) ? tryParseRiwayatList($data['Riwayat_Barang'] ?? '', $riwayatParseError) : [];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Data Barang - Modern QR System</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom animations -->
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        * {
            font-family: 'Inter', sans-serif;
        }
        
        .gradient-bg {
            background: linear-gradient(135deg, #3b82f6 0%, #f97316 100%);
        }
        
        .loading-spinner {
            background: linear-gradient(135deg, #1e3a8a 0%, #c2410c 100%);
        }
        
        .animate-spin-slow {
            animation: spin 3s linear infinite;
        }
        
        .animate-bounce-slow {
            animation: bounce 2s infinite;
        }
        
        .animate-pulse-slow {
            animation: pulse 3s ease-in-out infinite;
        }
        
        .animate-float {
            animation: float 6s ease-in-out infinite;
        }
        
        .animate-rotate {
            animation: rotate 50s linear infinite;
        }
        
        .animate-rotate-reverse {
            animation: rotate-reverse 40s linear infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }
        
        @keyframes rotate {
            from { transform: rotate(0deg) scale(1); }
            50% { transform: rotate(180deg) scale(1.1); }
            to { transform: rotate(360deg) scale(1); }
        }
        
        @keyframes rotate-reverse {
            from { transform: rotate(0deg) scale(1); }
            50% { transform: rotate(-180deg) scale(1.2); }
            to { transform: rotate(-360deg) scale(1); }
        }
        
        @keyframes slideInFromTop {
            0% { transform: translateY(-100px); opacity: 0; }
            100% { transform: translateY(0); opacity: 1; }
        }
        
        @keyframes slideInFromLeft {
            0% { transform: translateX(-100px); opacity: 0; }
            100% { transform: translateX(0); opacity: 1; }
        }
        
        @keyframes slideInFromRight {
            0% { transform: translateX(100px); opacity: 0; }
            100% { transform: translateX(0); opacity: 1; }
        }
        
        @keyframes slideInFromBottom {
            0% { transform: translateY(100px); opacity: 0; }
            100% { transform: translateY(0); opacity: 1; }
        }
        
        @keyframes scaleIn {
            0% { transform: scale(0); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }
        
        .slide-in-top {
            animation: slideInFromTop 0.8s ease-out forwards;
        }
        
        .slide-in-left {
            animation: slideInFromLeft 0.8s ease-out forwards;
        }
        
        .slide-in-right {
            animation: slideInFromRight 0.8s ease-out forwards;
        }
        
        .slide-in-bottom {
            animation: slideInFromBottom 0.8s ease-out forwards;
        }
        
        .scale-in {
            animation: scaleIn 0.6s ease-out forwards;
        }
        
        .hover-scale {
            transition: transform 0.3s ease;
        }
        
        .hover-scale:hover {
            transform: scale(1.05);
        }
        
        .hover-lift {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .hover-lift:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }
        
        .qr-corner {
            animation: pulse 2s ease-in-out infinite;
        }
        
        .qr-corner:nth-child(1) { animation-delay: 0s; }
        .qr-corner:nth-child(2) { animation-delay: 0.5s; }
        .qr-corner:nth-child(3) { animation-delay: 1s; }
        .qr-corner:nth-child(4) { animation-delay: 1.5s; }
        
        .status-aktif { @apply bg-green-100 text-green-800 border-green-200; }
        .status-maintenance { @apply bg-yellow-100 text-yellow-800 border-yellow-200; }
        .status-rusak { @apply bg-red-100 text-red-800 border-red-200; }
        .kondisi-baik { @apply bg-green-100 text-green-800; }
        .kondisi-cukup { @apply bg-yellow-100 text-yellow-800; }
        .kondisi-buruk { @apply bg-red-100 text-red-800; }
        
        /* Loading Animation */
        .loading-dots div {
            animation: loading-wave 1.4s ease-in-out infinite both;
        }
        
        .loading-dots div:nth-child(1) { animation-delay: -0.32s; }
        .loading-dots div:nth-child(2) { animation-delay: -0.16s; }
        .loading-dots div:nth-child(3) { animation-delay: 0s; }
        
        @keyframes loading-wave {
            0%, 80%, 100% { 
                transform: scale(0);
                opacity: 0.5;
            }
            40% { 
                transform: scale(1.0);
                opacity: 1;
            }
        }
        
        /* Image zoom effect */
        .image-zoom {
            overflow: hidden;
        }
        
        .image-zoom img {
            transition: transform 0.3s ease;
        }
        
        .image-zoom:hover img {
            transform: scale(1.1);
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f5f9;
        }
        
        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #3b82f6, #f97316);
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #2563eb, #ea580c);
        }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-blue-50 via-white to-orange-50">
    
    <!-- Loading Screen -->
    <div id="loadingScreen" class="fixed inset-0 loading-spinner flex items-center justify-center z-50">
        <div class="text-center">
            <!-- Animated Spinner -->
            <div class="relative w-20 h-20 mx-auto mb-8">
                <div class="absolute inset-0 border-4 border-transparent border-t-orange-400 border-r-orange-400 rounded-full animate-spin"></div>
                <div class="absolute inset-2 border-4 border-transparent border-b-blue-400 border-l-blue-400 rounded-full animate-spin-slow"></div>
            </div>
            
            <h2 class="text-2xl font-semibold text-white mb-4 animate-pulse">Memuat Data Barang</h2>
            
            <!-- Loading Dots -->
            <div class="loading-dots flex justify-center space-x-1">
                <div class="w-2 h-2 bg-orange-400 rounded-full"></div>
                <div class="w-2 h-2 bg-orange-400 rounded-full"></div>
                <div class="w-2 h-2 bg-orange-400 rounded-full"></div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div id="mainContent" class="opacity-0 transition-opacity duration-1000">
        
        <!-- Header -->
        <header class="bg-white/80 backdrop-blur-sm border-b border-gray-200 sticky top-0 z-40 slide-in-top">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-16">
                    <div class="flex items-center gap-4">
                        <button onclick="window.history.back()" class="flex items-center gap-2 px-3 py-1 text-gray-600 hover:text-blue-600 transition-colors">
                            <i class="fas fa-arrow-left"></i>
                            <span>Kembali</span>
                        </button>
                        <div>
                            <h1 class="text-xl font-semibold text-blue-800">Detail Data Barang</h1>
                            <?php if ($data): ?>
                            <p class="text-sm text-gray-600">ID: <?php echo $data['id_peserta']; ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <button onclick="location.reload()" class="flex items-center gap-2 px-3 py-1 border border-orange-200 text-orange-600 hover:bg-orange-50 rounded-lg transition-all hover:scale-105">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
            </div>
        </header>

        <?php if (!$data): ?>
        <!-- Data Not Found -->
        <div class="min-h-screen flex items-center justify-center">
            <div class="text-center p-8">
                <div class="w-20 h-20 mx-auto mb-4 bg-red-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-exclamation-triangle text-3xl text-red-600"></i>
                </div>
                <h2 class="text-2xl font-semibold text-red-600 mb-4">Data tidak ditemukan</h2>
                <p class="text-gray-600 mb-6">ID yang Anda cari tidak ada dalam database.</p>
                <button onclick="location.reload()" class="px-6 py-2 border border-red-200 text-red-600 hover:bg-red-50 rounded-lg transition-all">
                    <i class="fas fa-sync-alt mr-2"></i>
                    Muat Ulang
                </button>
            </div>
        </div>
        
        <?php else: ?>
        <!-- Main Content -->
        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="space-y-8">
                
                <!-- Item Details Card -->
                <div class="bg-white shadow-2xl rounded-2xl overflow-hidden hover-lift slide-in-left" style="animation-delay: 0.2s;">
                    <!-- Card Header -->
                    <div class="gradient-bg text-white p-4 sm:p-6">
                        <div class="flex items-center gap-3 mb-4">
                            <i class="fas fa-box text-xl sm:text-2xl"></i>
                            <h2 class="text-xl sm:text-2xl font-bold"><?php echo htmlspecialchars($data['Nama_Barang']); ?></h2>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <span class="px-3 py-1 bg-white/20 text-white rounded-full text-sm border border-white/20">
                                <?php echo htmlspecialchars($data['Merek']); ?> - <?php echo htmlspecialchars($data['Type']); ?>
                            </span>
                            <span class="px-3 py-1 rounded-full text-sm border <?php 
                                $status = strtolower($data['Status_Barang']); 
                                echo $status == 'aktif' ? 'status-aktif' : ($status == 'maintenance' ? 'status-maintenance' : 'status-rusak'); 
                            ?>">
                                <i class="fas fa-check-circle mr-1"></i>
                                <?php echo htmlspecialchars($data['Status_Barang']); ?>
                            </span>
                        </div>
                    </div>

                    <!-- Card Content -->
                    <div class="p-4 sm:p-6">
                        <div class="grid gap-6 md:grid-cols-2">
                            
                            <!-- Image Section -->
                            <div class="space-y-4">
                                <h3 class="flex items-center gap-2 text-lg font-semibold text-blue-700">
                                    <i class="fas fa-eye"></i>
                                    Foto Barang
                                </h3>
                                
                                <?php if (!empty($data['Photo_Barang'])): ?>
                                <?php $photoBarangUrl = buildUploadUrl($data['Photo_Barang']); ?>
                                <div class="image-zoom relative overflow-hidden rounded-xl cursor-pointer group" onclick="openImageModal('<?php echo htmlspecialchars($photoBarangUrl, ENT_QUOTES); ?>')">
                                    <img src="<?php echo htmlspecialchars($photoBarangUrl); ?>" 
                                         alt="Photo Barang" 
                                         class="w-full h-48 sm:h-64 object-cover">
                                    <div class="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition-colors duration-300 flex items-center justify-center">
                                        <i class="fas fa-search-plus text-4xl text-white opacity-0 group-hover:opacity-100 transition-opacity duration-300"></i>
                                    </div>
                                </div>
                                <?php else: ?>
                                <div class="w-full h-48 sm:h-64 bg-gray-100 rounded-xl flex items-center justify-center">
                                    <i class="fas fa-image text-6xl text-gray-400"></i>
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- Details Section -->
                            <div class="space-y-4">
                                <h3 class="flex items-center gap-2 text-lg font-semibold text-blue-700 border-b border-gray-200 pb-2 md:hidden">
                                    <i class="fas fa-cog"></i>
                                    Detail Barang
                                </h3>
                                <div class="grid gap-3">
                                    <!-- Nomor Aset -->
                                    <div class="p-3 rounded-xl bg-gray-50 hover:bg-gray-100 transition-colors">
                                        <div class="block md:hidden">
                                            <div class="flex items-center gap-2 text-gray-600 mb-1">
                                                <i class="fas fa-hashtag"></i>
                                                <span class="text-sm font-medium">Nomor Aset</span>
                                            </div>
                                            <div class="text-gray-900 text-sm font-normal break-words">
                                                <?php echo htmlspecialchars($data['Nomor_Aset'] ?? '-'); ?>
                                            </div>
                                        </div>
                                        <div class="hidden md:flex md:items-center md:justify-between">
                                            <span class="flex items-center gap-2 text-gray-600">
                                                <i class="fas fa-hashtag"></i>
                                                Nomor Aset
                                            </span>
                                            <span class="text-gray-900 max-w-[60%] text-right truncate">
                                                <?php echo htmlspecialchars($data['Nomor_Aset'] ?? '-'); ?>
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Serial Number -->
                                    <div class="p-3 rounded-xl bg-gray-50 hover:bg-gray-100 transition-colors">
                                        <!-- Mobile Layout -->
                                        <div class="block md:hidden">
                                            <div class="flex items-center gap-2 text-gray-600 mb-1">
                                                <i class="fas fa-cog"></i>
                                                <span class="text-sm font-medium">Serial Number</span>
                                            </div>
                                            <div class="text-gray-900 text-sm font-normal break-words">
                                                <?php echo htmlspecialchars($data['Serial_Number']); ?>
                                            </div>
                                        </div>
                                        <!-- Desktop Layout -->
                                        <div class="hidden md:flex md:items-center md:justify-between">
                                            <span class="flex items-center gap-2 text-gray-600">
                                                <i class="fas fa-cog"></i>
                                                Serial Number
                                            </span>
                                            <span class="text-gray-900 max-w-[60%] text-right truncate">
                                                <?php echo htmlspecialchars($data['Serial_Number']); ?>
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Jenis Barang -->
                                    <div class="p-3 rounded-xl bg-gray-50 hover:bg-gray-100 transition-colors">
                                        <!-- Mobile Layout -->
                                        <div class="block md:hidden">
                                            <div class="flex items-center gap-2 text-gray-600 mb-1">
                                                <i class="fas fa-tags"></i>
                                                <span class="text-sm font-medium">Jenis Barang</span>
                                            </div>
                                            <div class="text-gray-900 text-sm font-normal break-words">
                                                <?php echo htmlspecialchars($data['Jenis_Barang']); ?>
                                            </div>
                                        </div>
                                        <!-- Desktop Layout -->
                                        <div class="hidden md:flex md:items-center md:justify-between">
                                            <span class="flex items-center gap-2 text-gray-600">
                                                <i class="fas fa-tags"></i>
                                                Jenis Barang
                                            </span>
                                            <span class="text-gray-900 max-w-[60%] text-right truncate">
                                                <?php echo htmlspecialchars($data['Jenis_Barang']); ?>
                                            </span>
                                        </div>
                                    </div>

                                    <!-- User Perangkat -->
                                    <div class="p-3 rounded-xl bg-gray-50 hover:bg-gray-100 transition-colors">
                                        <!-- Mobile Layout -->
                                        <div class="block md:hidden">
                                            <div class="flex items-center gap-2 text-gray-600 mb-1">
                                                <i class="fas fa-user"></i>
                                                <span class="text-sm font-medium">User Perangkat</span>
                                            </div>
                                            <div class="text-gray-900 text-sm font-normal break-words">
                                                <?php echo htmlspecialchars($data['User_Perangkat']); ?>
                                            </div>
                                        </div>
                                        <!-- Desktop Layout -->
                                        <div class="hidden md:flex md:items-center md:justify-between">
                                            <span class="flex items-center gap-2 text-gray-600">
                                                <i class="fas fa-user"></i>
                                                User Perangkat
                                            </span>
                                            <span class="text-gray-900 max-w-[60%] text-right truncate">
                                                <?php echo htmlspecialchars($data['User_Perangkat']); ?>
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Lokasi -->
                                    <div class="p-3 rounded-xl bg-gray-50 hover:bg-gray-100 transition-colors">
                                        <div class="block md:hidden">
                                            <div class="flex items-center gap-2 text-gray-600 mb-1">
                                                <i class="fas fa-building"></i>
                                                <span class="text-sm font-medium">Lokasi</span>
                                            </div>
                                            <div class="text-gray-900 text-sm font-normal break-words">
                                                <?php echo htmlspecialchars($data['Lokasi'] ?? '-'); ?>
                                            </div>
                                        </div>
                                        <div class="hidden md:flex md:items-center md:justify-between">
                                            <span class="flex items-center gap-2 text-gray-600">
                                                <i class="fas fa-building"></i>
                                                Lokasi
                                            </span>
                                            <span class="text-gray-900 max-w-[60%] text-right truncate">
                                                <?php echo htmlspecialchars($data['Lokasi'] ?? '-'); ?>
                                            </span>
                                        </div>
                                    </div>

                                    <!-- ID Karyawan -->
                                    <div class="p-3 rounded-xl bg-gray-50 hover:bg-gray-100 transition-colors">
                                        <div class="block md:hidden">
                                            <div class="flex items-center gap-2 text-gray-600 mb-1">
                                                <i class="fas fa-id-card"></i>
                                                <span class="text-sm font-medium">ID Karyawan</span>
                                            </div>
                                            <div class="text-gray-900 text-sm font-normal break-words">
                                                <?php echo htmlspecialchars($data['Id_Karyawan'] ?? '-'); ?>
                                            </div>
                                        </div>
                                        <div class="hidden md:flex md:items-center md:justify-between">
                                            <span class="flex items-center gap-2 text-gray-600">
                                                <i class="fas fa-id-card"></i>
                                                ID Karyawan
                                            </span>
                                            <span class="text-gray-900 max-w-[60%] text-right truncate">
                                                <?php echo htmlspecialchars($data['Id_Karyawan'] ?? '-'); ?>
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Jabatan -->
                                    <div class="p-3 rounded-xl bg-gray-50 hover:bg-gray-100 transition-colors">
                                        <div class="block md:hidden">
                                            <div class="flex items-center gap-2 text-gray-600 mb-1">
                                                <i class="fas fa-user-tie"></i>
                                                <span class="text-sm font-medium">Jabatan</span>
                                            </div>
                                            <div class="text-gray-900 text-sm font-normal break-words">
                                                <?php echo htmlspecialchars($data['Jabatan'] ?? '-'); ?>
                                            </div>
                                        </div>
                                        <div class="hidden md:flex md:items-center md:justify-between">
                                            <span class="flex items-center gap-2 text-gray-600">
                                                <i class="fas fa-user-tie"></i>
                                                Jabatan
                                            </span>
                                            <span class="text-gray-900 max-w-[60%] text-right truncate">
                                                <?php echo htmlspecialchars($data['Jabatan'] ?? '-'); ?>
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Tahun Rilis -->
                                    <div class="p-3 rounded-xl bg-gray-50 hover:bg-gray-100 transition-colors">
                                        <div class="block md:hidden">
                                            <div class="flex items-center gap-2 text-gray-600 mb-1">
                                                <i class="fas fa-calendar-alt"></i>
                                                <span class="text-sm font-medium">Tahun Rilis</span>
                                            </div>
                                            <div class="text-gray-900 text-sm font-normal break-words">
                                                <?php echo htmlspecialchars($data['Tahun_Rilis'] ?? '-'); ?>
                                            </div>
                                        </div>
                                        <div class="hidden md:flex md:items-center md:justify-between">
                                            <span class="flex items-center gap-2 text-gray-600">
                                                <i class="fas fa-calendar-alt"></i>
                                                Tahun Rilis
                                            </span>
                                            <span class="text-gray-900 max-w-[60%] text-right truncate">
                                                <?php echo htmlspecialchars($data['Tahun_Rilis'] ?? '-'); ?>
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Waktu Pembelian -->
                                    <div class="p-3 rounded-xl bg-gray-50 hover:bg-gray-100 transition-colors">
                                        <div class="block md:hidden">
                                            <div class="flex items-center gap-2 text-gray-600 mb-1">
                                                <i class="fas fa-calendar-check"></i>
                                                <span class="text-sm font-medium">Waktu Pembelian</span>
                                            </div>
                                            <div class="text-gray-900 text-sm font-normal break-words">
                                                <?php echo htmlspecialchars($data['Waktu_Pembelian'] ?? '-'); ?>
                                            </div>
                                        </div>
                                        <div class="hidden md:flex md:items-center md:justify-between">
                                            <span class="flex items-center gap-2 text-gray-600">
                                                <i class="fas fa-calendar-check"></i>
                                                Waktu Pembelian
                                            </span>
                                            <span class="text-gray-900 max-w-[60%] text-right truncate">
                                                <?php echo htmlspecialchars($data['Waktu_Pembelian'] ?? '-'); ?>
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Nama Vendor -->
                                    <div class="p-3 rounded-xl bg-gray-50 hover:bg-gray-100 transition-colors">
                                        <div class="block md:hidden">
                                            <div class="flex items-center gap-2 text-gray-600 mb-1">
                                                <i class="fas fa-store"></i>
                                                <span class="text-sm font-medium">Nama Vendor</span>
                                            </div>
                                            <div class="text-gray-900 text-sm font-normal break-words">
                                                <?php echo htmlspecialchars($data['Nama_Toko_Pembelian'] ?? '-'); ?>
                                            </div>
                                        </div>
                                        <div class="hidden md:flex md:items-center md:justify-between">
                                            <span class="flex items-center gap-2 text-gray-600">
                                                <i class="fas fa-store"></i>
                                                Nama Vendor
                                            </span>
                                            <span class="text-gray-900 max-w-[60%] text-right truncate">
                                                <?php echo htmlspecialchars($data['Nama_Toko_Pembelian'] ?? '-'); ?>
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Kategori Pembelian -->
                                    <div class="p-3 rounded-xl bg-gray-50 hover:bg-gray-100 transition-colors">
                                        <div class="block md:hidden">
                                            <div class="flex items-center gap-2 text-gray-600 mb-1">
                                                <i class="fas fa-tags"></i>
                                                <span class="text-sm font-medium">Kategori Pembelian</span>
                                            </div>
                                            <div class="text-gray-900 text-sm font-normal break-words">
                                                <?php echo htmlspecialchars($data['Kategori_Pembelian'] ?? '-'); ?>
                                            </div>
                                        </div>
                                        <div class="hidden md:flex md:items-center md:justify-between">
                                            <span class="flex items-center gap-2 text-gray-600">
                                                <i class="fas fa-tags"></i>
                                                Kategori Pembelian
                                            </span>
                                            <span class="text-gray-900 max-w-[60%] text-right truncate">
                                                <?php echo htmlspecialchars($data['Kategori_Pembelian'] ?? '-'); ?>
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Link Pembelian -->
                                    <div class="p-3 rounded-xl bg-gray-50 hover:bg-gray-100 transition-colors">
                                        <?php
                                        $kategoriPembelian = trim((string)($data['Kategori_Pembelian'] ?? ''));
                                        $linkPembelian = trim((string)($data['Link_Pembelian'] ?? ''));
                                        $canShowLink = (strcasecmp($kategoriPembelian, 'Online') === 0) && ($linkPembelian !== '') && isValidHttpUrl($linkPembelian);
                                        ?>
                                        <div class="block md:hidden">
                                            <div class="flex items-center gap-2 text-gray-600 mb-1">
                                                <i class="fas fa-link"></i>
                                                <span class="text-sm font-medium">Link Pembelian</span>
                                            </div>
                                            <div class="text-gray-900 text-sm font-normal break-words">
                                                <?php if ($canShowLink): ?>
                                                    <a href="<?php echo htmlspecialchars($linkPembelian, ENT_QUOTES); ?>" target="_blank" rel="noopener noreferrer" class="text-blue-600 underline">
                                                        Buka Link
                                                    </a>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="hidden md:flex md:items-center md:justify-between">
                                            <span class="flex items-center gap-2 text-gray-600">
                                                <i class="fas fa-link"></i>
                                                Link Pembelian
                                            </span>
                                            <span class="text-gray-900 max-w-[60%] text-right truncate">
                                                <?php if ($canShowLink): ?>
                                                    <a href="<?php echo htmlspecialchars($linkPembelian, ENT_QUOTES); ?>" target="_blank" rel="noopener noreferrer" class="text-blue-600 underline">
                                                        Buka Link
                                                    </a>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Status LOP -->
                                    <div class="p-3 rounded-xl bg-gray-50 hover:bg-gray-100 transition-colors">
                                        <div class="block md:hidden">
                                            <div class="flex items-center gap-2 text-gray-600 mb-1">
                                                <i class="fas fa-money-check"></i>
                                                <span class="text-sm font-medium">Status LOP</span>
                                            </div>
                                            <div class="text-gray-900 text-sm font-normal break-words">
                                                <?php echo htmlspecialchars($data['Status_LOP'] ?? '-'); ?>
                                            </div>
                                        </div>
                                        <div class="hidden md:flex md:items-center md:justify-between">
                                            <span class="flex items-center gap-2 text-gray-600">
                                                <i class="fas fa-money-check"></i>
                                                Status LOP
                                            </span>
                                            <span class="text-gray-900 max-w-[60%] text-right truncate">
                                                <?php echo htmlspecialchars($data['Status_LOP'] ?? '-'); ?>
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Status Kelayakan -->
                                    <div class="p-3 rounded-xl bg-gray-50 hover:bg-gray-100 transition-colors">
                                        <div class="block md:hidden">
                                            <div class="flex items-center gap-2 text-gray-600 mb-1">
                                                <i class="fas fa-check-double"></i>
                                                <span class="text-sm font-medium">Status Kelayakan</span>
                                            </div>
                                            <div class="text-gray-900 text-sm font-normal break-words">
                                                <?php echo htmlspecialchars($data['Status_Kelayakan_Barang'] ?? '-'); ?>
                                            </div>
                                        </div>
                                        <div class="hidden md:flex md:items-center md:justify-between">
                                            <span class="flex items-center gap-2 text-gray-600">
                                                <i class="fas fa-check-double"></i>
                                                Status Kelayakan
                                            </span>
                                            <span class="text-gray-900 max-w-[60%] text-right truncate">
                                                <?php echo htmlspecialchars($data['Status_Kelayakan_Barang'] ?? '-'); ?>
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Harga Barang -->
                                    <div class="p-3 rounded-xl bg-gray-50 hover:bg-gray-100 transition-colors">
                                        <div class="block md:hidden">
                                            <div class="flex items-center gap-2 text-gray-600 mb-1">
                                                <i class="fas fa-wallet"></i>
                                                <span class="text-sm font-medium">Harga Barang</span>
                                            </div>
                                            <div class="text-gray-900 text-sm font-normal break-words">
                                                <?php echo htmlspecialchars(formatRupiah($data['Harga_Barang'] ?? '')); ?>
                                            </div>
                                        </div>
                                        <div class="hidden md:flex md:items-center md:justify-between">
                                            <span class="flex items-center gap-2 text-gray-600">
                                                <i class="fas fa-wallet"></i>
                                                Harga Barang
                                            </span>
                                            <span class="text-gray-900 max-w-[60%] text-right truncate">
                                                <?php echo htmlspecialchars(formatRupiah($data['Harga_Barang'] ?? '')); ?>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="p-3 rounded-xl bg-gray-50 hover:bg-gray-100 transition-colors">
                                            <!-- Header dengan warna kondisi (seperti badge asli) -->
                                            <div class="flex items-center gap-2 mb-2">
                                                <i class="fas fa-check-circle text-lg"></i>
                                                <span class="text-sm font-medium text-gray-600">Kondisi Barang</span>
                                            </div>
                                            
                                            <!-- Warna container berdasarkan kondisi (hijau/kuning/merah) -->
                                            <div class="p-3 border rounded-lg min-h-[80px] max-h-[150px] overflow-y-auto <?php 
                                                $kondisi = strtolower($data['Kondisi_Barang']); 
                                                echo $kondisi == 'baik' ? 'bg-green-50 border-green-200' : 
                                                    ($kondisi == 'cukup' ? 'bg-yellow-50 border-yellow-200' : 'bg-red-50 border-red-200'); 
                                            ?>">
                                                <?php echo renderAsList($data['Kondisi_Barang']); ?>
                                            </div>
                                        </div>
                                        <!-- Desktop Layout -->
                                        <div class="hidden md:flex md:items-center md:justify-between">
                                            <span class="flex items-center gap-2 text-gray-600">
                                                <i class="fas fa-check-circle"></i>
                                                Kondisi
                                            </span>
                                            <span class="px-3 py-1 rounded-full text-sm font-medium <?php 
                                                $kondisi = strtolower($data['Kondisi_Barang']); 
                                                echo $kondisi == 'baik' ? 'kondisi-baik' : ($kondisi == 'cukup' ? 'kondisi-cukup' : 'kondisi-buruk'); 
                                            ?>">
                                                <?php echo htmlspecialchars($data['Kondisi_Barang']); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Expandable Details -->
                        <div class="mt-8 space-y-6 mb-8 md:mb-12">
                            <div class="grid gap-6 md:grid-cols-2">
                                <div class="p-6 rounded-xl border border-gray-200 bg-gradient-to-br from-blue-50 to-orange-50 hover-lift">
                            <h4 class="flex items-center gap-2 text-lg font-semibold text-blue-700 mb-3">
                                <i class="fas fa-microchip"></i>
                                Spesifikasi
                            </h4>
                            <div class="min-h-[100px] max-h-[200px] overflow-y-auto p-2 bg-white/50 rounded-lg">
                                <?php echo renderAsList($data['Spesifikasi']); ?>
                            </div>
                        </div>

                        <div class="p-6 rounded-xl border border-gray-200 bg-gradient-to-br from-blue-50 to-orange-50 hover-lift">
                                <h4 class="flex items-center gap-2 text-lg font-semibold text-blue-700 mb-3">
                                    <i class="fas fa-list-check"></i>
                                    Kelengkapan
                                </h4>
                                <div class="min-h-[100px] max-h-[200px] overflow-y-auto p-2 bg-white/50 rounded-lg">
                                    <?php echo renderAsList($data['Kelengkapan_Barang']); ?>
                                </div>
                            </div>

                            <div class="p-6 rounded-xl border border-gray-200 bg-gradient-to-br from-blue-50 to-orange-50 hover-lift">
                                <h4 class="flex items-center gap-2 text-lg font-semibold text-blue-700 mb-3">
                                    <i class="fas fa-history"></i>
                                    Riwayat Barang
                                </h4>
                                <div class="min-h-[120px] max-h-[250px] overflow-y-auto p-2 bg-white/50 rounded-lg">
                                    <?php
                                    $riwayatCards = renderRiwayatJsonCards($riwayatList);
                                    if ($riwayatCards !== '') {
                                        echo $riwayatCards;
                                    } else {
                                        echo renderAsList($data['Riwayat_Barang'], 'ol');
                                    }
                                    ?>
                                    <?php if (empty($riwayatList) && !empty($riwayatParseError)): ?>
                                        <p class="text-xs text-red-600 mt-2">Riwayat tersimpan tapi gagal dibaca: <?php echo htmlspecialchars($riwayatParseError); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                    </div>
                </div>

                <!-- Additional Photos Section (missing fields) -->
                <div class="bg-white shadow-2xl rounded-2xl overflow-hidden hover-lift slide-in-left" style="animation-delay: 0.3s;">
                    <div class="bg-gradient-to-r from-blue-600 to-orange-500 text-white p-4 sm:p-6">
                        <h2 class="flex items-center gap-3 text-xl sm:text-2xl font-bold">
                            <i class="fas fa-images"></i>
                            Foto Evidence Tambahan
                        </h2>
                    </div>
                    <div class="p-4 sm:p-6">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                            <?php
                            $extraPhotos = [
                                ['key' => 'Photo_Depan', 'label' => 'Foto Depan'],
                                ['key' => 'Photo_Belakang', 'label' => 'Foto Belakang'],
                                ['key' => 'Photo_SN', 'label' => 'Foto SN'],
                                ['key' => 'Photo_Invoice', 'label' => 'Foto Invoice'],
                            ];

                            foreach ($extraPhotos as $p) {
                                $rel = $data[$p['key']] ?? '';
                                $url = buildUploadUrl($rel);
                                $ext = strtolower(pathinfo(sanitizeRelativePath($rel), PATHINFO_EXTENSION));
                                $isPdf = ($p['key'] === 'Photo_Invoice' && $ext === 'pdf');
                                echo '<div class="p-4 rounded-xl border border-gray-200 bg-gray-50">';
                                echo '<div class="text-sm font-semibold text-gray-700 mb-2">' . htmlspecialchars($p['label']) . '</div>';

                                if (!empty($url) && !empty($rel)) {
                                    if ($isPdf) {
                                        echo '<div class="w-full h-32 bg-white rounded-lg border border-dashed border-gray-300 flex flex-col items-center justify-center">';
                                        echo '<i class="fas fa-file-pdf text-3xl text-red-500"></i>';
                                        echo '<a href="' . htmlspecialchars($url, ENT_QUOTES) . '" target="_blank" rel="noopener noreferrer" class="mt-2 text-blue-600 underline text-sm">Buka PDF</a>';
                                        echo '</div>';
                                    } else {
                                        echo '<div class="image-zoom relative overflow-hidden rounded-lg cursor-pointer group" onclick="openImageModal(\'' . htmlspecialchars($url, ENT_QUOTES) . '\')">';
                                        echo '<img src="' . htmlspecialchars($url, ENT_QUOTES) . '" alt="' . htmlspecialchars($p['label'], ENT_QUOTES) . '" class="w-full h-32 object-cover" />';
                                        echo '<div class="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition-colors duration-300 flex items-center justify-center">';
                                        echo '<i class="fas fa-search-plus text-2xl text-white opacity-0 group-hover:opacity-100 transition-opacity duration-300"></i>';
                                        echo '</div>';
                                        echo '</div>';
                                    }
                                } else {
                                    echo '<div class="w-full h-32 bg-white rounded-lg border border-dashed border-gray-300 flex items-center justify-center">';
                                    echo '<i class="fas fa-image text-3xl text-gray-400"></i>';
                                    echo '</div>';
                                }

                                echo '</div>';
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <!-- QR Code Section -->
                <div class="bg-white shadow-2xl rounded-2xl overflow-hidden hover-lift slide-in-right" style="animation-delay: 0.4s;">
                    <!-- QR Header -->
                    <div class="bg-gradient-to-r from-orange-500 to-blue-600 text-white p-4 sm:p-6">
                        <h2 class="flex items-center gap-3 text-xl sm:text-2xl font-bold">
                            <i class="fas fa-qrcode"></i>
                            QR Code untuk Detail Data
                        </h2>
                    </div>

                    <!-- QR Content -->
                    <div class="p-4 sm:p-8">
                        <div class="flex flex-col items-center space-y-6">
                            
                            <!-- QR Code with Animation -->
                            <div class="relative scale-in" style="animation-delay: 0.8s;">
                                <div class="p-6 bg-white rounded-3xl shadow-xl border-4 border-gradient-to-r from-blue-200 to-orange-200">
                                    <?php if ($qrFile && file_exists($qrFile)): ?>
                                    <img src="<?php echo $qrFile; ?>" alt="QR Code" class="w-48 h-48 rounded-xl">
                                    <?php else: ?>
                                    <div class="w-48 h-48 bg-gray-100 rounded-xl flex items-center justify-center">
                                        <i class="fas fa-qrcode text-6xl text-gray-400"></i>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Animated Corners -->
                                <div class="qr-corner absolute -top-2 -left-2 w-6 h-6 border-t-4 border-l-4 border-blue-500 rounded-tl-xl"></div>
                                <div class="qr-corner absolute -top-2 -right-2 w-6 h-6 border-t-4 border-r-4 border-orange-500 rounded-tr-xl"></div>
                                <div class="qr-corner absolute -bottom-2 -left-2 w-6 h-6 border-b-4 border-l-4 border-orange-500 rounded-bl-xl"></div>
                                <div class="qr-corner absolute -bottom-2 -right-2 w-6 h-6 border-b-4 border-r-4 border-blue-500 rounded-br-xl"></div>
                            </div>

                            <!-- QR Info -->
                            <div class="text-center space-y-2 slide-in-bottom" style="animation-delay: 1.2s;">
                                <p class="text-gray-600 text-lg">Scan QR Code ini untuk melihat detail lengkap barang</p>
                                <p class="text-sm text-gray-400">ID: <?php echo $data['id_peserta']; ?></p>
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex flex-col sm:flex-row gap-4 w-full max-w-md slide-in-bottom" style="animation-delay: 1.4s;">
                                <?php if ($qrFile && file_exists($qrFile)): ?>
                                <a href="<?php echo $qrFile; ?>" download="qr_code_<?php echo $id_peserta; ?>.png" 
                                   class="flex-1 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white px-6 py-3 rounded-xl transition-all duration-300 hover-scale text-center font-medium">
                                    <i class="fas fa-download mr-2"></i>
                                    Download QR
                                </a>
                                <?php endif; ?>
                                <button onclick="shareLink()" 
                                        class="flex-1 border-2 border-orange-300 text-orange-600 hover:bg-orange-50 px-6 py-3 rounded-xl transition-all duration-300 hover-scale font-medium">
                                    <i class="fas fa-share-alt mr-2"></i>
                                    Share Link
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </main>
        <?php endif; ?>

        <!-- Floating Action Button -->
        <button onclick="scrollToTop()" 
                class="fixed bottom-6 right-6 w-14 h-14 gradient-bg text-white rounded-full shadow-xl flex items-center justify-center hover-scale z-40 scale-in" 
                style="animation-delay: 1s;">
            <i class="fas fa-arrow-up text-xl"></i>
        </button>

        <!-- Background Decoration -->
        <div class="fixed inset-0 pointer-events-none overflow-hidden opacity-40 z-0">
            <div class="absolute -top-32 -right-32 w-64 h-64 bg-gradient-to-br from-blue-200/20 to-orange-200/20 rounded-full animate-rotate"></div>
            <div class="absolute -bottom-32 -left-32 w-80 h-80 bg-gradient-to-tr from-orange-200/20 to-blue-200/20 rounded-full animate-rotate-reverse"></div>
        </div>

    </div>

    <!-- Image Modal -->
    <div id="imageModal" class="fixed inset-0 bg-black/90 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
        <!-- Controls -->
        <div class="absolute top-4 right-4 flex gap-2 z-10">
            <button onclick="zoomIn()" class="bg-white/10 backdrop-blur-sm text-white hover:bg-white/20 p-2 rounded-lg transition-all">
                <i class="fas fa-search-plus"></i>
            </button>
            <button onclick="zoomOut()" class="bg-white/10 backdrop-blur-sm text-white hover:bg-white/20 p-2 rounded-lg transition-all">
                <i class="fas fa-search-minus"></i>
            </button>
            <button onclick="rotateImage()" class="bg-white/10 backdrop-blur-sm text-white hover:bg-white/20 p-2 rounded-lg transition-all">
                <i class="fas fa-redo"></i>
            </button>
            <button onclick="closeImageModal()" class="bg-red-500/20 backdrop-blur-sm text-white hover:bg-red-500/30 p-2 rounded-lg transition-all">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <!-- Zoom Indicator -->
        <div class="absolute top-4 left-4 bg-white/10 backdrop-blur-sm text-white px-3 py-1 rounded-full text-sm">
            <span id="zoomLevel">100%</span>
        </div>

        <!-- Image Container -->
        <div class="relative max-w-[90vw] max-h-[90vh] overflow-hidden">
            <img id="modalImage" src="" alt="Photo Barang" class="max-w-full max-h-full object-contain transition-transform duration-300 cursor-grab">
        </div>

        <!-- Instructions -->
        <div class="absolute bottom-4 left-1/2 transform -translate-x-1/2 bg-white/10 backdrop-blur-sm text-white px-4 py-2 rounded-full text-sm">
            Klik dua kali untuk reset • Drag untuk menggeser
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="toast" class="fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg transform translate-x-full transition-transform duration-300 z-50">
        <i class="fas fa-check-circle mr-2"></i>
        <span id="toastMessage">Berhasil!</span>
    </div>

    <script>
        const SHARE_URL = <?php echo json_encode($shareUrl ?: ''); ?>;
        // Global variables
        let currentZoom = 1;
        let currentRotation = 0;
        let isDragging = false;
        let dragStartX = 0;
        let dragStartY = 0;
        let translateX = 0;
        let translateY = 0;

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            // Hide loading screen after 2.5 seconds
            setTimeout(() => {
                document.getElementById('loadingScreen').style.opacity = '0';
                setTimeout(() => {
                    document.getElementById('loadingScreen').style.display = 'none';
                    document.getElementById('mainContent').style.opacity = '1';
                }, 500);
            }, 2500);

            // Add loading animation to elements
            const animatedElements = document.querySelectorAll('.slide-in-top, .slide-in-left, .slide-in-right, .slide-in-bottom, .scale-in');
            animatedElements.forEach(el => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(50px)';
            });

            setTimeout(() => {
                animatedElements.forEach((el, index) => {
                    setTimeout(() => {
                        el.style.opacity = '1';
                        el.style.transform = 'translateY(0)';
                    }, index * 200);
                });
            }, 3000);
        });

        // Image Modal Functions
        function openImageModal(imageSrc) {
            const modal = document.getElementById('imageModal');
            const modalImage = document.getElementById('modalImage');
            modalImage.src = imageSrc;
            modal.classList.remove('hidden');
            resetImageTransform();
        }

        function closeImageModal() {
            const modal = document.getElementById('imageModal');
            modal.classList.add('hidden');
            resetImageTransform();
        }

        function zoomIn() {
            currentZoom = Math.min(currentZoom * 1.2, 3);
            updateImageTransform();
        }

        function zoomOut() {
            currentZoom = Math.max(currentZoom / 1.2, 0.5);
            updateImageTransform();
        }

        function rotateImage() {
            currentRotation += 90;
            updateImageTransform();
        }

        function resetImageTransform() {
            currentZoom = 1;
            currentRotation = 0;
            translateX = 0;
            translateY = 0;
            updateImageTransform();
        }

        function updateImageTransform() {
            const modalImage = document.getElementById('modalImage');
            const zoomLevel = document.getElementById('zoomLevel');
            
            modalImage.style.transform = `scale(${currentZoom}) rotate(${currentRotation}deg) translate(${translateX}px, ${translateY}px)`;
            zoomLevel.textContent = Math.round(currentZoom * 100) + '%';
        }

        // Image dragging functionality
        document.getElementById('modalImage').addEventListener('mousedown', function(e) {
            if (currentZoom > 1) {
                isDragging = true;
                dragStartX = e.clientX - translateX;
                dragStartY = e.clientY - translateY;
                this.style.cursor = 'grabbing';
            }
        });

        document.addEventListener('mousemove', function(e) {
            if (isDragging) {
                translateX = e.clientX - dragStartX;
                translateY = e.clientY - dragStartY;
                updateImageTransform();
            }
        });

        document.addEventListener('mouseup', function() {
            if (isDragging) {
                isDragging = false;
                document.getElementById('modalImage').style.cursor = 'grab';
            }
        });

        // Double click to reset
        document.getElementById('modalImage').addEventListener('dblclick', resetImageTransform);

        // Close modal on background click
        document.getElementById('imageModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeImageModal();
            }
        });

        // Utility Functions
        function scrollToTop() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function shareLink() {
            const url = (typeof SHARE_URL !== 'undefined' && SHARE_URL) ? SHARE_URL : window.location.href;
            
            if (navigator.share) {
                navigator.share({
                    title: 'Detail Data Barang',
                    url: url
                });
            } else if (navigator.clipboard) {
                navigator.clipboard.writeText(url).then(() => {
                    showToast('Link berhasil disalin ke clipboard!');
                });
            } else {
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = url;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                showToast('Link berhasil disalin ke clipboard!');
            }
        }

        function showToast(message) {
            const toast = document.getElementById('toast');
            const toastMessage = document.getElementById('toastMessage');
            
            toastMessage.textContent = message;
            toast.style.transform = 'translateX(0)';
            
            setTimeout(() => {
                toast.style.transform = 'translateX(100%)';
            }, 3000);
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            const modal = document.getElementById('imageModal');
            if (!modal.classList.contains('hidden')) {
                switch(e.key) {
                    case 'Escape':
                        closeImageModal();
                        break;
                    case '+':
                    case '=':
                        zoomIn();
                        break;
                    case '-':
                        zoomOut();
                        break;
                    case 'r':
                    case 'R':
                        rotateImage();
                        break;
                    case ' ':
                        e.preventDefault();
                        resetImageTransform();
                        break;
                }
            }
        });

        // QR Code Scanner functionality (if needed)
        function startQRScanner() {
            // Implementation would go here if QR scanning is needed
            showToast('Fitur scan QR Code akan segera tersedia!');
        }

        // Add smooth scroll behavior
        document.documentElement.style.scrollBehavior = 'smooth';

        // Add intersection observer for animations
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

        // Observe all animated elements
        document.querySelectorAll('.hover-lift').forEach(el => {
            observer.observe(el);
        });
    </script>

</body>
</html>