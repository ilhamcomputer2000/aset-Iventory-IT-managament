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

$conn = new mysqli("localhost", "root", "", "crud");
$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'User';

// --- AMBIL DATA BERDASARKAN ID ---
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$data = [
    'Kepada_Penerima' => '',
    'Dikirim_Oleh' => '',
    'Employed_ID_Penerima' => '',
    'Employed_ID_Pengirim' => '',
    'Diterima_Oleh' => '',
    'Tanggal_Pengirim_Barang' => '',
    'Tanggal_Terima_Barang' => '',
    'Tanda_Tangan_Pengirim' => '',
    'Tanda_Tangan_Penerima' => '',
    'Dokumen' => 0,
    'Invoice' => 0,
    'Kwitansi' => 0,
    'Faktur' => 0,
    'Surat' => 0,
    'Barang' => 0,
    'Lain' => 0,
    'Detail_Barang' => ''
];

if ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM serah_terima WHERE ID_Form = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $data = $row;
    }
    $stmt->close();
}

// PDF download handler - HARUS SEBELUM OUTPUT APAPUN!
if (isset($_POST['download_pdf'])) {
    require_once '../koneksi.php';
    require_once '../vendor/autoload.php'; // pastikan mPDF sudah diinstall via composer

    // Ambil nomor ID_Form terakhir dari database
    $query = "SELECT MAX(ID_Form) AS max_id FROM serah_terima";
    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_assoc($result);
    $lastId = isset($row['max_id']) && $row['max_id'] ? intval($row['max_id']) : 0;
    $newId = $lastId + 1;
    $formattedId = str_pad($newId, 5, '0', STR_PAD_LEFT);

    ob_start();
    ?>
    <table style="width: 100%; font-family: Arial, sans-serif;">
    <tr>
        <!-- Logo -->
        <td style="width: 120px; padding: 0;">
            <img src="logo_form/logo ckt fix.png" alt="Logo" style="height: 120px; display: block;">
        </td>

        <!-- Teks Perusahaan -->
        <td style="text-align: left; padding: 0;">
            <div style="font-weight: bold; font-size: 18px; margin-bottom: 4px;">
                PT. CIPTA KARYA TECHNOLOGY
            </div>
            <div style="font-size: 11px; color: #666;">
                Komp Perkantoran Bonagabe Blok B 17, Jl. Jatinegara Timur Raya No. 101, Jakarta Timur, Telp. : 021-8515931
            </div>
        </td>

        <!-- Nomor Dokumen -->
        <td style="width: 100px; text-align: right; font-weight: bold; font-size: 18px;">
            <?php echo htmlspecialchars($formattedId); ?>
        </td>
    </tr>
</table>

        <hr>
        <div style="text-align:center;font-weight:bold;font-size:16px;margin:12px 0;">FORM TANDA TERIMA - RECEIPT FORM</div>
        <table style="width:100%;font-size:13px;margin-bottom:10px;">
            <tr>
                <td><b>Kepada Penerima:</b> <?php echo htmlspecialchars($data['Kepada_Penerima']); ?></td>
                <td style="text-align:right;"><b>Dikirim Oleh:</b> <?php echo htmlspecialchars($data['Dikirim_Oleh']); ?></td>
            </tr>
            <tr>
                <td><b>Employed ID Penerima:</b> <?php echo htmlspecialchars($data['Employed_ID_Penerima']); ?></td>
                <td style="text-align:right;"><b>Employed ID Pengirim:</b> <?php echo htmlspecialchars($data['Employed_ID_Pengirim']); ?></td>
            </tr>
            <tr>
                <td><b>Di Terima Oleh:</b> <?php echo htmlspecialchars($data['Diterima_Oleh']); ?></td>
                <td td style="text-align:right;"><b>Tanggal Pengirim:</b> <?php echo htmlspecialchars($data['Tanggal_Pengirim_Barang']); ?></td>
            </tr>
            <tr>
                <td><b>Tanggal Penerima:</b> <?php echo htmlspecialchars($data['Tanggal_Terima_Barang']); ?></td>
                <td></td>
            </tr>
        </table>
        <div style="margin-bottom:8px;">
            <b>Jenis:</b>
            <?php
            $jenis = [];
            if ($data['Dokumen']) $jenis[] = 'Dokumen';
            if ($data['Invoice']) $jenis[] = 'Invoice';
            if ($data['Kwitansi']) $jenis[] = 'Kwitansi';
            if ($data['Faktur']) $jenis[] = 'Faktur Pajak';
            if ($data['Surat']) $jenis[] = 'Surat';
            if ($data['Barang']) $jenis[] = 'Barang';
            if ($data['Lain']) $jenis[] = 'Lain Lain';
            echo implode(', ', $jenis);
            ?>
        </div>
        <div style="margin-bottom:12px;">
            <b>Detail Barang:</b><br>
            <div style="border:1px solid #ccc;padding:8px;border-radius:4px;min-height:40px;"><?php echo nl2br(htmlspecialchars($data['Detail_Barang'])); ?></div>
        </div>
        <table style="width:100%;margin-top:24px;">
            <tr>
                <td style="text-align:center;">
                    <b>Tanda Tangan Pengirim</b><br>
                    <?php if ($data['Tanda_Tangan_Pengirim']) { ?>
                        <img src="<?php echo $data['Tanda_Tangan_Pengirim']; ?>" style="width:180px;height:60px;border:1px solid #ccc;">
                    <?php } else { ?>
                        <div style="width:180px;height:60px;border:1px solid #ccc;"></div>
                    <?php } ?>
                </td>
                <td style="text-align:center;">
                    <b>Tanda Tangan Penerima</b><br>
                    <?php if ($data['Tanda_Tangan_Penerima']) { ?>
                        <img src="<?php echo $data['Tanda_Tangan_Penerima']; ?>" style="width:180px;height:60px;border:1px solid #ccc;">
                    <?php } else { ?>
                        <div style="width:180px;height:60px;border:1px solid #ccc;"></div>
                    <?php } ?>
                </td>
            </tr>
        </table>
    </div>
    <?php
    $html = ob_get_clean();

    $mpdf = new \Mpdf\Mpdf(['format' => 'A4-L']);
    $mpdf->WriteHTML($html);
    $filename = 'Serah_Terima_' . $formattedId . '.pdf';
    $mpdf->Output($filename, 'D');
    exit;
}

// Tambahkan debug di sini
echo '<pre style="background:#eee;padding:10px;">';
echo 'GET id: '; var_dump($id);
echo 'DATA: '; print_r($data);
echo '</pre>';

// ...existing code...

// Pada bagian <input> dan <textarea> di form, tambahkan value/value terisi otomatis:
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin</title>
    <!-- Tailwind CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="navbar.css">
    <style>

        @page {
    margin: 0;
}
body {
    margin: 0;
    padding: 0;
}
        .sidebar-collapsed {
            width: 64px !important;
            transition: width 0.3s;
        }
        .sidebar-expanded {
            width: 256px !important;
            transition: width 0.3s;
        }
        .sidebar {
            transition: width 0.3s;
        }
        .sidebar .sidebar-content {
            display: block;
        }
        .sidebar-collapsed .sidebar-content .sidebar-label {
            display: none;
        }
        .sidebar-collapsed .sidebar-content {
            align-items: center;
        }
        .sidebar-collapsed .sidebar-header {
            display: none;
        }
        .sidebar-collapsed .sidebar-user {
            justify-content: center;
            margin-top: 32px; /* Tambahkan jarak ke bawah saat collapsed */
        }
        .sidebar-toggle {
            display: none;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .sidebar-collapsed .sidebar-toggle {
            display: flex !important;
        }
    </style>
</head>
<body class="bg-gray-100">
<div class="flex h-screen">
    <!-- Sidebar -->
    <div id="sidebar" class="sidebar sidebar-expanded w-64 bg-gray-800 text-white fixed h-full flex flex-col">
        <!-- Hamburger (shown only when expanded) -->
        <button id="hamburger-btn" class="absolute top-4 left-4 text-white focus:outline-none" style="z-index:10;">
            <i class="fas fa-bars fa-lg"></i>
        </button>
        <!-- Sidebar Content -->
        <div class="sidebar-content h-full flex flex-col">
            <div class="sidebar-header flex justify-center items-center p-4">
                <div class="text-lg font-bold text-center">Asset PT CIPTA KARYA TECHNOLOGY</div>
            </div>
            <div class="sidebar-user p-4 flex items-center space-x-3 border-b border-gray-700">
                <i class="fas fa-user-circle"></i>
                <div class="sidebar-label">
                    <span class="block"><?php echo htmlspecialchars($username); ?></span>
                </div>
            </div>
            <!-- Nav: dipindahkan lebih dekat ke atas, tapi tetap ada jarak -->
            <nav class="mt-4 flex-1 flex flex-col gap-1">
                <a href="dashboard_admin.php" class="block py-2 px-4 hover:bg-gray-700 transition-colors duration-200 flex items-center">
                    <i class="fas fa-tachometer-alt mr-1"></i>
                    <span class="sidebar-label">Dashboard</span>
                </a>
                <a href="index.php" class="block py-2 px-4 hover:bg-gray-700 transition-colors duration-200 flex items-center">
                    <i class="fas fa-cogs mr-0"></i>
                    <span class="sidebar-label">Assets IT</span>
                </a>
                <a href="serah_terima.php" class="block py-2 px-4 hover:bg-gray-700 transition-colors duration-200 flex items-center">
                    <i class="fas fa-file-alt mr-2"></i>
                    <span class="sidebar-label">Form Serah Terima</span>
                </a>
                <a href="ticket.php" class="block py-2 px-4 hover:bg-gray-700 transition-colors duration-200 flex items-center">
                    <i class="fas fa-ticket-alt mr-2"></i>
                    <span class="sidebar-label">Ticket</span>
                </a>
                <a href="../logout.php" class="block py-2 px-4 hover:bg-gray-700 transition-colors duration-200 flex items-center">
                    <i class="fas fa-sign-out-alt mr-1"></i>
                    <span class="sidebar-label">Logout</span>
                </a>
            </nav>
        </div>
        <!-- Hamburger (shown only when collapsed) -->
        <button id="expand-btn" class="absolute top-4 left-4 text-white focus:outline-none" style="display:none;z-index:10;">
            <i class="fas fa-bars fa-lg"></i>
        </button>
    </div>
    <script>
    const sidebar = document.getElementById('sidebar');
    const hamburgerBtn = document.getElementById('hamburger-btn');
    const expandBtn = document.getElementById('expand-btn');

    function collapseSidebar() {
        sidebar.classList.remove('sidebar-expanded');
        sidebar.classList.add('sidebar-collapsed');
        sidebar.style.width = '64px';
        hamburgerBtn.style.display = 'none';
        expandBtn.style.display = 'block';
    }

    function expandSidebar() {
        sidebar.classList.remove('sidebar-collapsed');
        sidebar.classList.add('sidebar-expanded');
        sidebar.style.width = '256px';
        hamburgerBtn.style.display = 'block';
        expandBtn.style.display = 'none';
    }

    hamburgerBtn.addEventListener('click', collapseSidebar);
    expandBtn.addEventListener('click', expandSidebar);

    // Initial state
    expandSidebar();
    </script>

      
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Serah Terima</title>
    <!-- Tailwind CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .alert {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }

       @media print {
    @page {
        size: A4 landscape;
        margin: 10mm;
    }
    body {
        background: #fff !important;
    }
    .shadow, .rounded-lg {
        box-shadow: none !important;
        border-radius: 0 !important;
    }
    .bg-white {
        background: #fff !important;
    }
}
    </style>
</head>
<body class="bg-gray-100 print:bg-white">
    <!-- Form Serah Terima Barang -->
<div class="mx-auto mt-10 p-6 bg-white rounded-lg shadow" style="width:794px; min-height:1123px;">
    <div class="flex items-center mb-4">
        <img src="logo_form/logo ckt fix.png" alt="Logo" class="h-28 mr-8" style="margin-left:-2rem; margin-right:2rem;">
        <div>
            <div class="font-bold text-lg text-left" style="margin-left:-3rem;">PT. CIPTA KARYA TECHNOLOGY</div>
            <div class="text-xs text-gray-600 text-left" style="margin-left:-3rem;">Komp Perkantoran Bonagabe Blok B 17, Jl. Jatinegara Timur Raya No. 101, Jakarta Timur, Telp. : 021-8515931</div>
        </div>
        <?php
        // Koneksi ke database
        require_once '../koneksi.php';

        // Pastikan koneksi berhasil
        if (!isset($conn) || !$conn) {
            die('<div class="text-red-600 font-bold">Koneksi ke database gagal.</div>');
        }

        // Ambil nomor ID_Form terakhir dari database
        $query = "SELECT MAX(ID_Form) AS max_id FROM serah_terima";
        $result = mysqli_query($conn, $query);
        if (!$result) {
            die('<div class="text-red-600 font-bold">Query gagal: ' . htmlspecialchars(mysqli_error($conn)) . '</div>');
        }
        $row = mysqli_fetch_assoc($result);

        // Jika belum ada data, mulai dari 1
        $lastId = isset($row['max_id']) && $row['max_id'] ? intval($row['max_id']) : 0;
        $newId = $lastId + 1;
        $formattedId = str_pad($newId, 5, '0', STR_PAD_LEFT);
        ?>
        <div class="ml-auto text-right font-bold text-xl"><?php echo htmlspecialchars($formattedId); ?></div>
    </div>
    <div class="border-b border-gray-300 mb-2"></div>
    <div class="text-center font-bold text-base mb-4">FORM TANDA TERIMA - RECEIPT FORM</div>
    <!-- Tombol Cetak -->
    <div class="flex justify-end mb-4 print:hidden">
        <button onclick="window.print()" type="button" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded font-semibold text-sm">
            <i class="fas fa-print mr-2"></i>Cetak
        </button>
    </div>
    <!-- Tombol Download PDF -->
    <div class="flex justify-end mb-2 print:hidden">
        <form method="post" action="" style="display:inline;">
            <button type="submit" name="download_pdf" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded font-semibold text-sm">
                <i class="fas fa-file-pdf mr-2"></i>Download PDF
            </button>
        </form>
    </div>
    <?php
    if (isset($_POST['download_pdf'])) {
        require_once '../vendor/autoload.php'; // pastikan mPDF sudah diinstall via composer

        ob_start();
        ?>
        <div style="font-family:Arial,sans-serif;">
            <div style="display:flex;align-items:center;margin-bottom:16px;">
            <div style="flex:1;">
                <div style="font-weight:bold;font-size:18px;"></div>
                <div style="font-size:11px;color:#666;">Komp Perkantoran Bonagabe Blok B 17, Jl. Jatinegara Timur Raya No. 101, Jakarta Timur, Telp. : 021-8515931</div>
            </div>
            <div style="display:flex;flex-direction:column;align-items:flex-end;justify-content:flex-end; height:70px;">
                <div style="font-weight:bold;font-size:20px;text-align:right;min-width:120px;align-self:flex-end;">
                    <?php echo htmlspecialchars($formattedId); ?>
                </div>
                <img src="logo_form/logo ckt fix.png" alt="Logo" style="height:48px;margin-top:8px;align-self:flex-end;">
            </div>
            </div>
            <hr>
            <div style="text-align:center;font-weight:bold;font-size:16px;margin:12px 0;">FORM TANDA TERIMA - RECEIPT FORM</div>
            <table style="width:100%;font-size:13px;margin-bottom:10px;">
                <tr>
                    <td><b>Kepada Penerima:</b> <?php echo htmlspecialchars($data['Kepada_Penerima']); ?></td>
                    <td><b>Dikirim Oleh:</b> <?php echo htmlspecialchars($data['Dikirim_Oleh']); ?></td>
                </tr>
                <tr>
                    <td><b>Employed ID Penerima:</b> <?php echo htmlspecialchars($data['Employed_ID_Penerima']); ?></td>
                    <td><b>Employed ID Pengirim:</b> <?php echo htmlspecialchars($data['Employed_ID_Pengirim']); ?></td>
                </tr>
                <tr>
                    <td><b>Di Terima Oleh:</b> <?php echo htmlspecialchars($data['Diterima_Oleh']); ?></td>
                    <td><b>Tanggal Pengirim:</b> <?php echo htmlspecialchars($data['Tanggal_Pengirim_Barang']); ?></td>
                </tr>
                <tr>
                    <td><b>Tanggal Penerima:</b> <?php echo htmlspecialchars($data['Tanggal_Terima_Barang']); ?></td>
                    <td></td>
                </tr>
            </table>
            <div style="margin-bottom:8px;">
                <b>Jenis:</b>
                <?php
                $jenis = [];
                if ($data['Dokumen']) $jenis[] = 'Dokumen';
                if ($data['Invoice']) $jenis[] = 'Invoice';
                if ($data['Kwitansi']) $jenis[] = 'Kwitansi';
                if ($data['Faktur']) $jenis[] = 'Faktur Pajak';
                if ($data['Surat']) $jenis[] = 'Surat';
                if ($data['Barang']) $jenis[] = 'Barang';
                if ($data['Lain']) $jenis[] = 'Lain Lain';
                echo implode(', ', $jenis);
                ?>
            </div>
            <div style="margin-bottom:12px;">
                <b>Detail Barang:</b><br>
                <div style="border:1px solid #ccc;padding:8px;border-radius:4px;min-height:40px;"><?php echo nl2br(htmlspecialchars($data['Detail_Barang'])); ?></div>
            </div>
            <table style="width:100%;margin-top:24px;">
                <tr>
                    <td style="text-align:center;">
                        <b>Tanda Tangan Pengirim</b><br>
                        <?php if ($data['Tanda_Tangan_Pengirim']) { ?>
                            <img src="<?php echo $data['Tanda_Tangan_Pengirim']; ?>" style="width:180px;height:60px;border:1px solid #ccc;">
                        <?php } else { ?>
                            <div style="width:180px;height:60px;border:1px solid #ccc;"></div>
                        <?php } ?>
                    </td>
                    <td style="text-align:center;">
                        <b>Tanda Tangan Penerima</b><br>
                        <?php if ($data['Tanda_Tangan_Penerima']) { ?>
                            <img src="<?php echo $data['Tanda_Tangan_Penerima']; ?>" style="width:180px;height:60px;border:1px solid #ccc;">
                        <?php } else { ?>
                            <div style="width:180px;height:60px;border:1px solid #ccc;"></div>
                        <?php } ?>
                    </td>
                </tr>
            </table>
        </div>
        <?php
        $html = ob_get_clean();

        $mpdf = new \Mpdf\Mpdf(['format' => 'A4-L']);
        $mpdf->WriteHTML($html);
        $filename = 'Serah_Terima_' . $formattedId . '.pdf';
        $mpdf->Output($filename, 'D');
        exit;
    }
    ?>
    <form action="" method="post" class="space-y-4">
        <div class="grid grid-cols-2 gap-4 mb-2">
            <div>
                <label class="block text-xs font-semibold mb-1">Kepada Penerima</label>
                <input type="text" name="Kepada_Penerima" class="form-input w-full border rounded px-2 py-1 text-sm" required value="<?php echo htmlspecialchars($data['Kepada_Penerima']); ?>">
            </div>
            <div>
                <label class="block text-xs font-semibold mb-1">Dikirim Oleh</label>
                <input type="text" name="Dikirim_Oleh" class="form-input w-full border rounded px-2 py-1 text-sm" value="<?php echo htmlspecialchars($data['Dikirim_Oleh']); ?>">
            </div>
            <div>
                <label class="block text-xs font-semibold mb-1">Employed ID Penerima</label>
                <input type="text" name="Employed_ID_Penerima" class="form-input w-full border rounded px-2 py-1 text-sm" value="<?php echo htmlspecialchars($data['Employed_ID_Penerima']); ?>">
            </div>
            <div>
                <label class="block text-xs font-semibold mb-1">Employed ID Pengirim</label>
                <input type="text" name="Employed_ID_Pengirim" class="form-input w-full border rounded px-2 py-1 text-sm" required value="<?php echo htmlspecialchars($data['Employed_ID_Pengirim']); ?>">
            </div>
            <div>
                <label class="block text-xs font-semibold mb-1">Di Terima Oleh</label>
                <input type="text" name="Diterima_Oleh" class="form-input w-full border rounded px-2 py-1 text-sm" required value="<?php echo htmlspecialchars($data['Diterima_Oleh']); ?>">
            </div>
            <div>
                <label class="block text-xs font-semibold mb-1">Tanggal Pengirim</label>
                <input type="date" name="Tanggal_Pengirim_Barang" class="form-input w-full border rounded px-2 py-1 text-sm" value="<?php echo htmlspecialchars($data['Tanggal_Pengirim_Barang']); ?>">
            </div>
            <div>
                <label class="block text-xs font-semibold mb-1">Tanggal Penerima</label>
                <input type="date" name="Tanggal_Terima_Barang" class="form-input w-full border rounded px-2 py-1 text-sm" value="<?php echo htmlspecialchars($data['Tanggal_Terima_Barang']); ?>">
            </div>
            <div>
                <label class="block text-xs font-semibold mb-1">Tanda Tangan Pengirim</label>
                <canvas id="ttd_pengirim" class="border rounded w-full h-32 bg-white mb-2" style="touch-action: none;"></canvas>
                <input type="hidden" name="Tanda_Tangan_Pengirim" id="input_ttd_pengirim" required value="<?php echo htmlspecialchars($data['Tanda_Tangan_Pengirim']); ?>">
                <button type="button" onclick="clearCanvas('ttd_pengirim')" class="text-xs text-red-600 underline mb-2">Hapus Tanda Tangan</button>
            </div>
            <div>
                <label class="block text-xs font-semibold mb-1">Tanda Tangan Penerima</label>
                <canvas id="ttd_penerima" class="border rounded w-full h-32 bg-white mb-2" style="touch-action: none;"></canvas>
                <input type="hidden" name="Tanda_Tangan_Penerima" id="input_ttd_penerima" required value="<?php echo htmlspecialchars($data['Tanda_Tangan_Penerima']); ?>">
                <button type="button" onclick="clearCanvas('ttd_penerima')" class="text-xs text-red-600 underline mb-2">Hapus Tanda Tangan</button>
            </div>
            <script>
            // Signature Pad for Pengirim & Penerima
            function setupSignaturePad(canvasId, inputId) {
                const canvas = document.getElementById(canvasId);
                const input = document.getElementById(inputId);
                const ctx = canvas.getContext('2d');
                let drawing = false, lastX = 0, lastY = 0;

                // Resize canvas to match display size
                function resizeCanvas() {
                    const data = canvas.toDataURL();
                    canvas.width = canvas.offsetWidth;
                    canvas.height = canvas.offsetHeight;
                    if (data) {
                        const img = new window.Image();
                        img.onload = function() { ctx.drawImage(img, 0, 0); };
                        img.src = data;
                    }
                }
                resizeCanvas();
                window.addEventListener('resize', resizeCanvas);

                function startDraw(e) {
                    drawing = true;
                    [lastX, lastY] = getXY(e);
                }
                function endDraw() {
                    drawing = false;
                    input.value = canvas.toDataURL();
                }
                function draw(e) {
                    if (!drawing) return;
                    const [x, y] = getXY(e);
                    ctx.beginPath();
                    ctx.moveTo(lastX, lastY);
                    ctx.lineTo(x, y);
                    ctx.strokeStyle = '#111';
                    ctx.lineWidth = 2;
                    ctx.lineCap = 'round';
                    ctx.stroke();
                    [lastX, lastY] = [x, y];
                }
                function getXY(e) {
                    if (e.touches && e.touches.length) {
                        const rect = canvas.getBoundingClientRect();
                        return [
                            e.touches[0].clientX - rect.left,
                            e.touches[0].clientY - rect.top
                        ];
                    } else {
                        const rect = canvas.getBoundingClientRect();
                        return [
                            (e.offsetX !== undefined ? e.offsetX : e.clientX - rect.left),
                            (e.offsetY !== undefined ? e.offsetY : e.clientY - rect.top)
                        ];
                    }
                }
                // Mouse events
                canvas.addEventListener('mousedown', startDraw);
                canvas.addEventListener('mouseup', endDraw);
                canvas.addEventListener('mouseout', endDraw);
                canvas.addEventListener('mousemove', draw);
                // Touch events
                canvas.addEventListener('touchstart', function(e){ e.preventDefault(); startDraw(e); });
                canvas.addEventListener('touchend', function(e){ e.preventDefault(); endDraw(e); });
                canvas.addEventListener('touchcancel', function(e){ e.preventDefault(); endDraw(e); });
                canvas.addEventListener('touchmove', function(e){ e.preventDefault(); draw(e); });
            }
            function clearCanvas(canvasId) {
                const canvas = document.getElementById(canvasId);
                const ctx = canvas.getContext('2d');
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                if (canvasId === 'ttd_pengirim') {
                    document.getElementById('input_ttd_pengirim').value = '';
                } else if (canvasId === 'ttd_penerima') {
                    document.getElementById('input_ttd_penerima').value = '';
                }
            }
            // Setup both signature pads
            window.addEventListener('DOMContentLoaded', function() {
                setupSignaturePad('ttd_pengirim', 'input_ttd_pengirim');
                setupSignaturePad('ttd_penerima', 'input_ttd_penerima');
            });
            </script>
            
        </div>
        <div class="flex flex-wrap gap-2 mb-2">
            <label class="flex items-center text-xs font-semibold">
                <input type="checkbox" name="Dokumen" class="mr-1"> Dokumen
            </label>
            <label class="flex items-center text-xs font-semibold">
                <input type="checkbox" name="Invoice" class="mr-1"> Invoice
            </label>
            <label class="flex items-center text-xs font-semibold">
                <input type="checkbox" name="Kwitansi" class="mr-1"> Kwitansi
            </label>
            <label class="flex items-center text-xs font-semibold">
                <input type="checkbox" name="Faktur" class="mr-1"> Faktur Pajak
            </label>
            <label class="flex items-center text-xs font-semibold">
                <input type="checkbox" name="Surat" class="mr-1"> Surat
            </label>
            <label class="flex items-center text-xs font-semibold">
                <input type="checkbox" name="Barang" class="mr-1" checked> Barang
            </label>
            <label class="flex items-center text-xs font-semibold">
                <input type="checkbox" name="Lain" class="mr-1"> Lain Lain
            </label>
        </div>
        <div class="mb-2">
            <label class="block text-xs font-semibold mb-1">Detail Barang</label>
          <textarea name="Detail_Barang" rows="5" class="form-textarea w-full border rounded px-2 py-1 text-sm resize-y" placeholder="Contoh: 1 unit laptop Asus X450ZA SN: SINO..."><?php echo htmlspecialchars($data['Detail_Barang']); ?></textarea>
        </div>
        <div class="flex justify-end print:hidden">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded font-semibold text-sm">Simpan</button>
        </div>
        
    </form>
</div>
<style>
    @media print {
        html, body {
            overflow: visible !important;
            height: auto !important;
        }
        .sidebar, .print\:hidden {
            display: none !important;
        }
        textarea, input, select, button {
            box-shadow: none !important;
        }
        .mx-auto {
            width: 100% !important;
            min-height: auto !important;
            max-height: 100vh !important;
            page-break-inside: avoid !important;
            page-break-before: avoid !important;
            page-break-after: avoid !important;
        }
        .bg-white {
            background: #fff !important;
        }
        /* Hindari pemisahan elemen penting */
        .mx-auto, .p-6, form, .flex, .grid, .mb-2, .mb-4 {
            page-break-inside: avoid !important;
        }
    }
</style>
<Script>
    // ...existing code...

window.addEventListener('DOMContentLoaded', function() {
    setupSignaturePad('ttd_pengirim', 'input_ttd_pengirim');
    setupSignaturePad('ttd_penerima', 'input_ttd_penerima');

    // Tampilkan gambar tanda tangan jika sudah ada di database
    function showSignatureFromValue(canvasId, inputId) {
        const input = document.getElementById(inputId);
        const canvas = document.getElementById(canvasId);
        const ctx = canvas.getContext('2d');
        if (input.value) {
            const img = new window.Image();
            img.onload = function() {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
            };
            img.src = input.value;
        }
    }
    showSignatureFromValue('ttd_pengirim', 'input_ttd_pengirim');
    showSignatureFromValue('ttd_penerima', 'input_ttd_penerima');
});
</Script>
</html>