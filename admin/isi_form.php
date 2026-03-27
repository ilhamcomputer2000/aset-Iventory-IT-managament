<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$role = $_SESSION['role']; // Ambil role dari session
if ($role !== 'super_admin' && $role !== 'user') {
    header("Location: ../login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "crud");

// Pastikan session username sudah diatur
$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'User';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil data dari form
    $Kepada_Penerima = $_POST['Kepada_Penerima'];
    $Dikirim_Oleh = $_POST['Dikirim_Oleh'];
    $Employed_ID_Penerima = $_POST['Employed_ID_Penerima'];
    $Employed_ID_Pengirim = $_POST['Employed_ID_Pengirim'];
    $Diterima_Oleh = $_POST['Diterima_Oleh'];
    $Tanggal_Pengirim_Barang = $_POST['Tanggal_Pengirim_Barang'];
    $Tanggal_Terima_Barang = $_POST['Tanggal_Terima_Barang'];
    $Tanda_Tangan_Pengirim = $_POST['Tanda_Tangan_Pengirim'];
    $Tanda_Tangan_Penerima = $_POST['Tanda_Tangan_Penerima'];
    $Detail_Barang = $_POST['Detail_Barang'];

    // Checkbox (jika tidak dicentang, tidak ada di $_POST)
    $Dokumen = isset($_POST['Dokumen']) ? 1 : 0;
    $Invoice = isset($_POST['Invoice']) ? 1 : 0;
    $Kwitansi = isset($_POST['Kwitansi']) ? 1 : 0;
    $Faktur = isset($_POST['Faktur']) ? 1 : 0;
    $Surat = isset($_POST['Surat']) ? 1 : 0;
    $Barang = isset($_POST['Barang']) ? 1 : 0;
    $Lain = isset($_POST['Lain']) ? 1 : 0;

    // Query insert
    $sql = "INSERT INTO serah_terima 
        (Kepada_Penerima, Dikirim_Oleh, Employed_ID_Penerima, Employed_ID_Pengirim, Diterima_Oleh, Tanggal_Pengirim_Barang, Tanggal_Terima_Barang, Tanda_Tangan_Pengirim, Tanda_Tangan_Penerima, dokumen, invoice, kwitansi, faktur, surat, barang, lain, Detail_Barang)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "sssssssssiiiiiiis", // 9 string, 7 integer, 1 string
        $Kepada_Penerima,
        $Dikirim_Oleh,
        $Employed_ID_Penerima,
        $Employed_ID_Pengirim,
        $Diterima_Oleh,
        $Tanggal_Pengirim_Barang,
        $Tanggal_Terima_Barang,
        $Tanda_Tangan_Pengirim,
        $Tanda_Tangan_Penerima,
        $Dokumen,
        $Invoice,
        $Kwitansi,
        $Faktur,
        $Surat,
        $Barang,
        $Lain,
        $Detail_Barang
    );

    if ($stmt->execute()) {
        echo '<script>
            document.addEventListener("DOMContentLoaded", function() {
                Swal.fire({
                    title: "Berhasil!",
                    text: "Data berhasil disimpan.",
                    icon: "success",
                    confirmButtonColor: "#2563eb"
                });
            });
        </script>';
    } else {
        echo '<script>
            document.addEventListener("DOMContentLoaded", function() {
                Swal.fire({
                    title: "Error!",
                    text: "Gagal menyimpan data: ' . htmlspecialchars($stmt->error) . '",
                    icon: "error",
                    confirmButtonColor: "#dc2626"
                });
            });
        </script>';
    }
    $stmt->close();
}

// Generate ID Form
$query = "SELECT MAX(ID_Form) AS max_id FROM serah_terima";
$result = mysqli_query($conn, $query);
if (!$result) {
    die('<div class="text-red-600 font-bold">Query gagal: ' . htmlspecialchars(mysqli_error($conn)) . '</div>');
}
$row = mysqli_fetch_assoc($result);
$lastId = isset($row['max_id']) && $row['max_id'] ? intval($row['max_id']) : 0;
$newId = $lastId + 1;
$formattedId = str_pad($newId, 5, '0', STR_PAD_LEFT);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Form Serah Terima - PT CIPTA KARYA TECHNOLOGY</title>
    <!-- Tailwind CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- QR Code Generator -->
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
    
    <style>
        /* Loading Animation */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #1e3a8a, #7c3aed, #3730a3);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            transition: opacity 0.5s ease-out;
        }

        .loading-content {
            text-align: center;
            color: white;
        }

        .loading-logo {
            width: 80px;
            height: 80px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            font-size: 1.5rem;
            font-weight: bold;
            color: #1e3a8a;
            animation: logoRotate 2s ease-in-out;
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
            background: white;
            border-radius: 50%;
            animation: loadingPulse 1.5s ease-in-out infinite;
        }

        .loading-bar:nth-child(2) { animation-delay: 0.2s; }
        .loading-bar:nth-child(3) { animation-delay: 0.4s; }

        .loading-progress {
            width: 200px;
            height: 4px;
            background: rgba(255,255,255,0.3);
            border-radius: 2px;
            overflow: hidden;
            margin: 0 auto;
        }

        .loading-progress-bar {
            width: 0%;
            height: 100%;
            background: linear-gradient(90deg, #60a5fa, #a855f7);
            border-radius: 2px;
            animation: progressLoad 2s ease-out forwards;
        }

        @keyframes logoRotate {
            0% { transform: scale(0) rotate(-180deg); }
            100% { transform: scale(1) rotate(0deg); }
        }

        @keyframes loadingPulse {
            0%, 100% { transform: scale(1); opacity: 0.7; }
            50% { transform: scale(1.5); opacity: 1; }
        }

        @keyframes progressLoad {
            0% { width: 0%; }
            100% { width: 100%; }
        }

        /* Sidebar Animations */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            background: linear-gradient(180deg, #1f2937, #111827);
            color: white;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 40;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        .sidebar.collapsed {
            width: 80px;
        }

        .sidebar.expanded {
            width: 280px;
        }

        .sidebar-item {
            transition: all 0.2s ease;
            position: relative;
            overflow: hidden;
        }

        .sidebar-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
            transition: left 0.5s ease;
        }

        .sidebar-item:hover::before {
            left: 100%;
        }

        .sidebar-item:hover {
            background: rgba(59, 130, 246, 0.1);
            transform: translateX(4px);
        }

        .sidebar-item.active {
            background: linear-gradient(90deg, #2563eb, #3b82f6);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }

        .main-content {
            transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Signature Pad Styles */
        .signature-pad {
            border: 2px dashed #d1d5db;
            border-radius: 8px;
            background: white;
            cursor: crosshair;
            touch-action: none;
        }

        .signature-pad:hover {
            border-color: #2563eb;
        }

        /* Print Styles */
        @media print {
            @page {
                size: A4 landscape;
                margin: 15mm;
            }

            body {
                background: white !important;
                -webkit-print-color-adjust: exact;
                color-adjust: exact;
            }

            .print-hidden {
                display: none !important;
            }

            .print-form {
                width: 100% !important;
                max-width: none !important;
                margin: 0 !important;
                padding: 0 !important;
                box-shadow: none !important;
                border: none !important;
            }

            .print-header {
                border-bottom: 2px solid #000;
                padding-bottom: 10px;
                margin-bottom: 20px;
            }

            .signature-area {
                min-height: 80px;
                border: 1px solid #000;
            }
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .sidebar.expanded {
                position: fixed;
                z-index: 50;
            }
            
            .main-content {
                margin-left: 0 !important;
            }
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr !important;
            }
        }

        /* Animation classes */
        .fade-in {
            animation: fadeIn 0.6s ease-out;
        }

        .slide-in-left {
            animation: slideInLeft 0.6s ease-out;
        }

        .slide-in-right {
            animation: slideInRight 0.6s ease-out;
        }

        .bounce-in {
            animation: bounceIn 0.8s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes slideInLeft {
            from { opacity: 0; transform: translateX(-30px); }
            to { opacity: 1; transform: translateX(0); }
        }

        @keyframes slideInRight {
            from { opacity: 0; transform: translateX(30px); }
            to { opacity: 1; transform: translateX(0); }
        }

        @keyframes bounceIn {
            0% { opacity: 0; transform: scale(0.3); }
            50% { transform: scale(1.05); }
            70% { transform: scale(0.9); }
            100% { opacity: 1; transform: scale(1); }
        }

        /* Mobile overlay */
        .mobile-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 30;
        }

        @media (max-width: 1024px) {
            .mobile-overlay.active {
                display: block;
            }
        }
    </style>
</head>

<body class="bg-gray-50">
    <!-- Loading Animation -->
    <div id="loadingOverlay" class="loading-overlay">
        <div class="loading-content">
            <div class="loading-logo">CKT</div>
            <h1 class="text-2xl md:text-3xl font-bold mb-2">PT CIPTA KARYA TECHNOLOGY</h1>
            <p class="text-gray-300 mb-4">Form Serah Terima Digital</p>
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
    <div id="mobileOverlay" class="mobile-overlay" onclick="toggleSidebar()"></div>

    <!-- Sidebar -->
    <div id="sidebar" class="sidebar expanded">
        <!-- Sidebar Header -->
        <div class="p-4 border-b border-gray-700">
            <div class="flex items-center justify-between">
                <div id="sidebarLogo" class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center">
                        <span class="font-bold text-sm">CKT</span>
                    </div>
                    <div class="sidebar-text">
                        <h2 class="font-bold text-sm">PT CIPTA KARYA</h2>
                        <p class="text-xs text-gray-400">TECHNOLOGY</p>
                    </div>
                </div>
                <button id="sidebarToggle" class="p-2 hover:bg-gray-700 rounded-lg transition-colors" onclick="toggleSidebar()">
                    <i id="toggleIcon" class="fas fa-chevron-left"></i>
                </button>
            </div>
        </div>

        <!-- User Info -->
        <div class="p-4 border-b border-gray-700">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 bg-blue-500 rounded-full flex items-center justify-center">
                    <i class="fas fa-user"></i>
                </div>
                <div class="sidebar-text">
                    <p class="font-medium text-sm"><?php echo htmlspecialchars($username); ?></p>
                    <p class="text-xs text-gray-400"><?php echo htmlspecialchars($role); ?></p>
                </div>
            </div>
        </div>

        <!-- Navigation -->
        <nav class="p-4 space-y-2">
            <a href="dashboard_admin.php" class="sidebar-item flex items-center space-x-3 p-3 rounded-lg">
                <i class="fas fa-tachometer-alt w-5"></i>
                <span class="sidebar-text">Dashboard</span>
            </a>
            <a href="index.php" class="sidebar-item flex items-center space-x-3 p-3 rounded-lg">
                <i class="fas fa-cogs w-5"></i>
                <span class="sidebar-text">Assets IT</span>
            </a>
            <a href="serah_terima.php" class="sidebar-item active flex items-center space-x-3 p-3 rounded-lg">
                <i class="fas fa-file-alt w-5"></i>
                <span class="sidebar-text">Form Serah Terima</span>
            </a>
            <a href="ticket.php" class="sidebar-item flex items-center space-x-3 p-3 rounded-lg">
                <i class="fas fa-ticket-alt w-5"></i>
                <span class="sidebar-text">Ticket</span>
            </a>
            <a href="../logout.php" class="sidebar-item flex items-center space-x-3 p-3 rounded-lg">
                <i class="fas fa-sign-out-alt w-5"></i>
                <span class="sidebar-text">Logout</span>
            </a>
        </nav>

        <!-- Footer -->
        <div class="absolute bottom-4 left-4 right-4 sidebar-text">
            <div class="text-xs text-gray-500 text-center">
                <p>© 2024 PT Cipta Karya Technology</p>
                <p>Version 1.0.0</p>
            </div>
        </div>
    </div>

    <!-- Mobile Toggle Button -->
    <button id="mobileToggle" class="fixed top-4 left-4 z-50 p-3 bg-gray-900 text-white rounded-lg shadow-lg lg:hidden print-hidden" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Main Content -->
    <div id="mainContent" class="main-content" style="margin-left: 280px;">
        <div class="p-6">
            <!-- Form Container -->
            <div class="max-w-5xl mx-auto">
                <div class="bg-white rounded-lg shadow-lg overflow-hidden print-form fade-in">
                    <!-- Form Header -->
                    <div class="print-header p-6 border-b">
                        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
                            <div class="flex items-center space-x-4">
                                <div class="flex-shrink-0">
                                    <div class="w-16 h-16 bg-blue-600 rounded-lg flex items-center justify-center print:bg-blue-600">
                                        <span class="text-white font-bold text-lg">CKT</span>
                                    </div>
                                </div>
                                <div>
                                    <h1 class="text-xl font-bold text-gray-900">PT. CIPTA KARYA TECHNOLOGY</h1>
                                    <p class="text-sm text-gray-600 mt-1">
                                        Komp Perkantoran Bonagabe Blok B 17, Jl. Jatinegara Timur Raya No. 101, Jakarta Timur
                                    </p>
                                    <p class="text-sm text-gray-600">Telp. : 021-8515931</p>
                                </div>
                            </div>
                            <div class="text-right flex-shrink-0">
                                <div class="text-3xl font-bold text-blue-600"><?php echo $formattedId; ?></div>
                                <p class="text-sm text-gray-500">Form ID</p>
                            </div>
                        </div>
                        <div class="text-center mt-6">
                            <h2 class="text-xl font-bold text-gray-900">FORM TANDA TERIMA - RECEIPT FORM</h2>
                        </div>
                    </div>

                    <!-- Form Content -->
                    <div class="p-6">
                        <!-- Action Buttons -->
                        <div class="flex flex-wrap gap-3 mb-6 print-hidden">
                            <button onclick="window.print()" type="button" class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition-colors">
                                <i class="fas fa-print mr-2"></i>
                                Cetak
                            </button>
                            <button type="submit" form="serahTerimaForm" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
                                <i class="fas fa-save mr-2"></i>
                                Simpan
                            </button>
                        </div>

                        <form id="serahTerimaForm" action="" method="post" class="space-y-6">
                            <!-- Form Fields Grid -->
                            <div class="form-grid grid grid-cols-1 lg:grid-cols-2 gap-6">
                                <!-- Left Column -->
                                <div class="space-y-4 slide-in-left">
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Kepada Penerima</label>
                                        <input type="text" name="Kepada_Penerima" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" required>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Employee ID Penerima</label>
                                        <input type="text" name="Employed_ID_Penerima" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Di Terima Oleh</label>
                                        <input type="text" name="Diterima_Oleh" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" required>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Tanggal Penerima</label>
                                        <input type="date" name="Tanggal_Terima_Barang" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                                    </div>
                                </div>

                                <!-- Right Column -->
                                <div class="space-y-4 slide-in-right">
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Dikirim Oleh</label>
                                        <input type="text" name="Dikirim_Oleh" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Employee ID Pengirim</label>
                                        <input type="text" name="Employed_ID_Pengirim" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" required>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Tanggal Pengirim</label>
                                        <input type="date" name="Tanggal_Pengirim_Barang" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                                    </div>
                                </div>
                            </div>

                            <!-- Document Type Checkboxes -->
                            <div class="bounce-in">
                                <label class="block text-sm font-semibold text-gray-700 mb-3">Jenis:</label>
                                <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3">
                                    <label class="flex items-center space-x-2 text-sm">
                                        <input type="checkbox" name="Dokumen" class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                        <span>Dokumen</span>
                                    </label>
                                    <label class="flex items-center space-x-2 text-sm">
                                        <input type="checkbox" name="Invoice" class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                        <span>Invoice</span>
                                    </label>
                                    <label class="flex items-center space-x-2 text-sm">
                                        <input type="checkbox" name="Kwitansi" class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                        <span>Kwitansi</span>
                                    </label>
                                    <label class="flex items-center space-x-2 text-sm">
                                        <input type="checkbox" name="Faktur" class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                        <span>Faktur Pajak</span>
                                    </label>
                                    <label class="flex items-center space-x-2 text-sm">
                                        <input type="checkbox" name="Surat" class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                        <span>Surat</span>
                                    </label>
                                    <label class="flex items-center space-x-2 text-sm">
                                        <input type="checkbox" name="Barang" class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500" checked>
                                        <span>Barang</span>
                                    </label>
                                    <label class="flex items-center space-x-2 text-sm">
                                        <input type="checkbox" name="Lain" class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                        <span>Lain-lain</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Detail Barang -->
                            <div class="fade-in">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Detail Barang:</label>
                                <textarea name="Detail_Barang" rows="4" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors resize-y" placeholder="Contoh: 1 unit laptop Asus X450ZA SN: SINO..."></textarea>
                            </div>

                            <!-- Signature Sections -->
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-8">
                                <!-- Signature Pengirim -->
                                <div class="slide-in-left">
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Tanda Tangan Pengirim</label>
                                    <div class="signature-container">
                                        <canvas id="signaturePengirim" class="signature-pad w-full h-32" style="touch-action: none;"></canvas>
                                        <input type="hidden" name="Tanda_Tangan_Pengirim" id="signatureDataPengirim" required>
                                        <div class="flex justify-between items-center mt-2">
                                            <button type="button" onclick="clearSignature('signaturePengirim')" class="text-red-600 hover:text-red-700 text-sm">
                                                <i class="fas fa-trash mr-1"></i>Hapus
                                            </button>
                                            <div id="timestampPengirim" class="text-right text-xs text-gray-500"></div>
                                        </div>
                                        <div id="qrPengirim" class="mt-2 flex justify-end"></div>
                                    </div>
                                </div>

                                <!-- Signature Penerima -->
                                <div class="slide-in-right">
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Tanda Tangan Penerima</label>
                                    <div class="signature-container">
                                        <canvas id="signaturePenerima" class="signature-pad w-full h-32" style="touch-action: none;"></canvas>
                                        <input type="hidden" name="Tanda_Tangan_Penerima" id="signatureDataPenerima" required>
                                        <div class="flex justify-between items-center mt-2">
                                            <button type="button" onclick="clearSignature('signaturePenerima')" class="text-red-600 hover:text-red-700 text-sm">
                                                <i class="fas fa-trash mr-1"></i>Hapus
                                            </button>
                                            <div id="timestampPenerima" class="text-right text-xs text-gray-500"></div>
                                        </div>
                                        <div id="qrPenerima" class="mt-2 flex justify-end"></div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Loading Animation
        window.addEventListener('load', function() {
            setTimeout(function() {
                const loadingOverlay = document.getElementById('loadingOverlay');
                loadingOverlay.style.opacity = '0';
                setTimeout(function() {
                    loadingOverlay.style.display = 'none';
                }, 500);
            }, 2000);
        });

        // Sidebar Toggle
        let sidebarCollapsed = false;

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            const mobileOverlay = document.getElementById('mobileOverlay');
            const toggleIcon = document.getElementById('toggleIcon');
            const sidebarTexts = document.querySelectorAll('.sidebar-text');

            sidebarCollapsed = !sidebarCollapsed;

            if (window.innerWidth <= 1024) {
                // Mobile behavior
                if (sidebarCollapsed) {
                    sidebar.style.transform = 'translateX(-100%)';
                    mobileOverlay.classList.remove('active');
                } else {
                    sidebar.style.transform = 'translateX(0)';
                    mobileOverlay.classList.add('active');
                }
                mainContent.style.marginLeft = '0';
            } else {
                // Desktop behavior
                if (sidebarCollapsed) {
                    sidebar.classList.remove('expanded');
                    sidebar.classList.add('collapsed');
                    mainContent.style.marginLeft = '80px';
                    toggleIcon.className = 'fas fa-chevron-right';
                    sidebarTexts.forEach(text => text.style.display = 'none');
                } else {
                    sidebar.classList.remove('collapsed');
                    sidebar.classList.add('expanded');
                    mainContent.style.marginLeft = '280px';
                    toggleIcon.className = 'fas fa-chevron-left';
                    sidebarTexts.forEach(text => text.style.display = 'block');
                }
            }
        }

        // Responsive handling
        window.addEventListener('resize', function() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            
            if (window.innerWidth <= 1024) {
                mainContent.style.marginLeft = '0';
                if (sidebarCollapsed) {
                    sidebar.style.transform = 'translateX(-100%)';
                }
            } else {
                sidebar.style.transform = 'translateX(0)';
                if (sidebarCollapsed) {
                    mainContent.style.marginLeft = '80px';
                } else {
                    mainContent.style.marginLeft = '280px';
                }
            }
        });

        // Signature Pad Implementation
        function setupSignaturePad(canvasId, inputId, timestampId, qrId) {
            const canvas = document.getElementById(canvasId);
            const input = document.getElementById(inputId);
            const timestampDiv = document.getElementById(timestampId);
            const qrDiv = document.getElementById(qrId);
            const ctx = canvas.getContext('2d');
            
            let drawing = false;
            let lastX = 0;
            let lastY = 0;

            // Set canvas size
            function resizeCanvas() {
                const rect = canvas.getBoundingClientRect();
                canvas.width = rect.width * window.devicePixelRatio;
                canvas.height = rect.height * window.devicePixelRatio;
                ctx.scale(window.devicePixelRatio, window.devicePixelRatio);
                canvas.style.width = rect.width + 'px';
                canvas.style.height = rect.height + 'px';
            }

            resizeCanvas();
            window.addEventListener('resize', resizeCanvas);

            function startDraw(e) {
                drawing = true;
                [lastX, lastY] = getXY(e);
                
                // Generate timestamp and QR code on first draw
                if (!input.value) {
                    const now = new Date();
                    const timestamp = now.toLocaleString('id-ID', {
                        day: '2-digit',
                        month: '2-digit', 
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit',
                        second: '2-digit'
                    });
                    
                    timestampDiv.innerHTML = `
                        <div class="flex items-center text-xs text-gray-500">
                            <i class="fas fa-calendar mr-1"></i>
                            ${timestamp}
                        </div>
                    `;
                    
                    // Generate QR Code
                    const qrData = JSON.stringify({
                        type: canvasId,
                        timestamp: now.toISOString(),
                        user: '<?php echo htmlspecialchars($username); ?>',
                        formId: '<?php echo $formattedId; ?>'
                    });
                    
                    QRCode.toDataURL(qrData, {width: 60, margin: 1}, function(err, url) {
                        if (!err) {
                            qrDiv.innerHTML = `<img src="${url}" alt="QR Code" class="w-8 h-8">`;
                        }
                    });
                }
            }

            function endDraw() {
                drawing = false;
                input.value = canvas.toDataURL();
                ctx.beginPath();
            }

            function draw(e) {
                if (!drawing) return;
                const [x, y] = getXY(e);
                
                ctx.beginPath();
                ctx.moveTo(lastX, lastY);
                ctx.lineTo(x, y);
                ctx.strokeStyle = '#000';
                ctx.lineWidth = 2;
                ctx.lineCap = 'round';
                ctx.stroke();
                
                [lastX, lastY] = [x, y];
            }

            function getXY(e) {
                const rect = canvas.getBoundingClientRect();
                if (e.touches && e.touches.length) {
                    return [
                        e.touches[0].clientX - rect.left,
                        e.touches[0].clientY - rect.top
                    ];
                } else {
                    return [
                        e.offsetX !== undefined ? e.offsetX : e.clientX - rect.left,
                        e.offsetY !== undefined ? e.offsetY : e.clientY - rect.top
                    ];
                }
            }

            // Mouse events
            canvas.addEventListener('mousedown', startDraw);
            canvas.addEventListener('mouseup', endDraw);
            canvas.addEventListener('mouseout', endDraw);
            canvas.addEventListener('mousemove', draw);

            // Touch events
            canvas.addEventListener('touchstart', function(e) {
                e.preventDefault();
                startDraw(e);
            });
            canvas.addEventListener('touchend', function(e) {
                e.preventDefault();
                endDraw();
            });
            canvas.addEventListener('touchcancel', function(e) {
                e.preventDefault();
                endDraw();
            });
            canvas.addEventListener('touchmove', function(e) {
                e.preventDefault();
                draw(e);
            });
        }

        function clearSignature(canvasId) {
            const canvas = document.getElementById(canvasId);
            const ctx = canvas.getContext('2d');
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            
            if (canvasId === 'signaturePengirim') {
                document.getElementById('signatureDataPengirim').value = '';
                document.getElementById('timestampPengirim').innerHTML = '';
                document.getElementById('qrPengirim').innerHTML = '';
            } else if (canvasId === 'signaturePenerima') {
                document.getElementById('signatureDataPenerima').value = '';
                document.getElementById('timestampPenerima').innerHTML = '';
                document.getElementById('qrPenerima').innerHTML = '';
            }
        }

        // Initialize signature pads when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            setupSignaturePad('signaturePengirim', 'signatureDataPengirim', 'timestampPengirim', 'qrPengirim');
            setupSignaturePad('signaturePenerima', 'signatureDataPenerima', 'timestampPenerima', 'qrPenerima');
        });

        // Form validation
        document.getElementById('serahTerimaForm').addEventListener('submit', function(e) {
            const pengirimSignature = document.getElementById('signatureDataPengirim').value;
            const penerimaSignature = document.getElementById('signatureDataPenerima').value;
            
            if (!pengirimSignature || !penerimaSignature) {
                e.preventDefault();
                Swal.fire({
                    title: 'Peringatan!',
                    text: 'Silakan lengkapi tanda tangan pengirim dan penerima',
                    icon: 'warning',
                    confirmButtonColor: '#f59e0b'
                });
                return false;
            }
        });
    </script>
</body>
</html>