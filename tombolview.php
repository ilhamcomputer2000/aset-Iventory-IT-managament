<?php
session_start();

// Include file koneksi
include "koneksi.php";

// Token helper untuk menyembunyikan id_peserta di URL (admin/update.php?t=...)
require_once __DIR__ . '/token_id.php';

function tombolview_update_url($idPeserta) {
    $id = (int)$idPeserta;
    if ($id <= 0) {
        return 'admin/update.php';
    }
    $t = token_make_id($id, 86400);
    if (is_string($t) && $t !== '') {
        return 'admin/update.php?t=' . rawurlencode($t);
    }
    return 'admin/update.php?id_peserta=' . rawurlencode((string)$id);
}




if (!isset($_SESSION['role'])) {
    echo "Role tidak ditemukan";
    exit;
}


// Pastikan role admin atau user sudah diatur dalam session
if (!isset($_SESSION['role'])) {
    header('Location: login.php');
    exit;
}

// Fungsi untuk mencegah inputan karakter yang tidak sesuai
function input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Cek apakah ada nilai yang dikirim menggunakan method GET dengan nama id_peserta
if (isset($_GET['id_peserta'])) {
    $id_peserta = input($_GET["id_peserta"]);

    // Ambil data dari database
    $sql = "SELECT * FROM peserta WHERE id_peserta = $id_peserta";
    $hasil = mysqli_query($kon, $sql);
    $data = mysqli_fetch_assoc($hasil);

    // Periksa apakah ID peserta ditemukan di database
    if (!$data) {
        echo "<script>
        Swal.fire({
            title: 'Error!',
            text: 'ID Peserta tidak ditemukan.',
            icon: 'error',
            confirmButtonText: 'OK'
        }).then(function() {
            window.location.href = 'index.php';
        });
        </script>";
        exit;
    }
} else {
    echo "<script>
    Swal.fire({
        title: 'Error!',
        text: 'ID Peserta tidak ditemukan.',
        icon: 'error',
        confirmButtonText: 'OK'
    }).then(function() {
        window.location.href = 'index.php';
    });
    </script>";
    exit;
}

// Cek role dan tentukan arah pengalihan setelah klik tombol close
// Cek role dan tentukan arah pengalihan setelah klik tombol close
$redirectUrl = ($_SESSION['role'] == 'admin') ? 'index.php' : 'view.php';


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Data</title>
    <!-- Tailwind CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto p-4 bg-white shadow-lg rounded-lg mt-10">
        <h2 class="text-2xl font-bold mb-4">View Data</h2>

        <div class="space-y-4">
            <div class="form-group">
                <label class="block text-gray-700 font-medium mb-1">Waktu:</label>
                <p class="px-3 py-2 border rounded-lg bg-gray-100"><?php echo htmlspecialchars($data['Waktu']); ?></p>
            </div>

            <div class="form-group">
                <label class="block text-gray-700 font-medium mb-1">Nama Barang:</label>
                <p class="px-3 py-2 border rounded-lg bg-gray-100"><?php echo htmlspecialchars($data['Nama_Barang']); ?></p>
            </div>

            <div class="form-group">
                <label class="block text-gray-700 font-medium mb-1">Merek:</label>
                <p class="px-3 py-2 border rounded-lg bg-gray-100"><?php echo htmlspecialchars($data['Merek']); ?></p>
            </div>

            <div class="form-group">
                <label class="block text-gray-700 font-medium mb-1">Type:</label>
                <p class="px-3 py-2 border rounded-lg bg-gray-100"><?php echo htmlspecialchars($data['Type']); ?></p>
            </div>

            <div class="form-group">
                <label class="block text-gray-700 font-medium mb-1">Serial Number:</label>
                <p class="px-3 py-2 border rounded-lg bg-gray-100"><?php echo htmlspecialchars($data['Serial_Number']); ?></p>
            </div>

            <div class="form-group">
                <label class="block text-gray-700 font-medium mb-1">Spesifikasi:</label>
                <p class="px-3 py-2 border rounded-lg bg-gray-100"><?php echo nl2br(htmlspecialchars($data['Spesifikasi'])); ?></p>
            </div>

            <div class="form-group">
                <label class="block text-gray-700 font-medium mb-1">Kelengkapan Barang:</label>
                <p class="px-3 py-2 border rounded-lg bg-gray-100"><?php echo nl2br(htmlspecialchars($data['Kelengkapan_Barang'])); ?></p>
            </div>

            <div class="form-group">
                <label class="block text-gray-700 font-medium mb-1">Kondisi Barang:</label>
                <p class="px-3 py-2 border rounded-lg bg-gray-100"><?php echo nl2br(htmlspecialchars($data['Kondisi_Barang'])); ?></p>
            </div>

            <div class="form-group">
                <label class="block text-gray-700 font-medium mb-1">Riwayat Barang:</label>
                <p class="px-3 py-2 border rounded-lg bg-gray-100"><?php echo nl2br(htmlspecialchars($data['Riwayat_Barang'])); ?></p>
            </div>

            <div class="form-group">
                <label class="block text-gray-700 font-medium mb-1">Nama User yang menggunakan perangkat:</label>
                <p class="px-3 py-2 border rounded-lg bg-gray-100"><?php echo nl2br(htmlspecialchars($data['User_Perangkat'])); ?></p>
            </div>

            <div class="form-group">
                <label class="block text-gray-700 font-medium mb-1">Status Barang:</label>
                <p class="px-3 py-2 border rounded-lg bg-gray-100"><?php echo htmlspecialchars($data['Status_Barang']); ?></p>
            </div>

            <?php if ($_SESSION['role'] == 'admin'): ?>
                <div class="flex justify-end mt-4">
                    <a href="<?php echo htmlspecialchars(tombolview_update_url($id_peserta), ENT_QUOTES); ?>" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-700">Update</a>
                </div>
            <?php endif; ?>


            

            <div class="flex justify-end mt-4">
                <a href="<?php echo $redirectUrl; ?>" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600">Close</a>
            </div>
        </div>
    </div>
</body>
</html>
