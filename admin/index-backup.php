<?php


require 'vendor/autoload.php'; // Jika menggunakan Composer

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

function generateQrCode($data) {
    $qrCode = new QrCode($data);
    $qrCode->setSize(150); // Ukuran QR Code
    $qrCode->setMargin(10); // Margin QR Code

    $writer = new PngWriter();
    $result = $writer->write($qrCode);

    // Simpan QR Code ke file sementara
    $filePath = 'temp/' . uniqid() . '.png';
    file_put_contents($filePath, $result->getString());

    return $filePath;
}

// Mulai session
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Include koneksi database
include "../koneksi.php";


// Koneksi ke database
$conn = new mysqli("localhost:3306", "cktnosa2_admin", "uGXj8#eiI=P%", "cktnosa2_crud"); 
// $conn = new mysqli("localhost", "root", "", "crud"); // Gantilah "crud" dengan nama database Anda

// Cek koneksi
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}



echo '</ul>';


// Query untuk menghitung total data
$total_query = "SELECT COUNT(*) as total FROM peserta"; // Ganti "peserta" dengan nama tabel Anda
$total_result = $conn->query($total_query);
$total_row = $total_result->fetch_assoc();
$total_data = $total_row['total']; // Menyimpan total data ke variabel

// Query untuk menghitung jumlah berdasarkan status (seperti sebelumnya)
$sql = "
    SELECT 
        SUM(CASE WHEN Status_Barang = 'READY' THEN 1 ELSE 0 END) AS total_ready,
        SUM(CASE WHEN Status_Barang = 'KOSONG' THEN 1 ELSE 0 END) AS total_kosong,
        SUM(CASE WHEN Status_Barang = 'REPAIR' THEN 1 ELSE 0 END) AS total_repair,
        SUM(CASE WHEN Status_Barang = 'TEMPORARY' THEN 1 ELSE 0 END) AS total_temporary,
        SUM(CASE WHEN Status_Barang = 'RUSAK' THEN 1 ELSE 0 END) AS total_rusak FROM peserta";
$result = $conn->query($sql);

// Ambil data jumlah status
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $jumlah_ready = $row['total_ready'];
    $jumlah_kosong = $row['total_kosong'];
    $jumlah_repair = $row['total_repair'];
    $jumlah_temporary = $row['total_temporary'];
    $jumlah_rusak = $row['total_rusak'];
} else {
    $jumlah_ready = 0;
    $jumlah_kosong = 0;
    $jumlah_repair = 0;
    $jumlah_temporary = 0;
    $jumlah_rusak = 0;
}

// Tutup koneksi
$conn->close();

// Pastikan session username sudah diatur
$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'User';

// Ambil nilai dropdown dan search query
$kategori = isset($_GET['kategori']) ? mysqli_real_escape_string($kon, $_GET['kategori']) : '';
$search_query = isset($_GET['search']) ? mysqli_real_escape_string($kon, $_GET['search']) : '';

// Query SQL dengan kondisi WHERE yang lebih spesifik
$sql = "SELECT * FROM peserta WHERE 1=1";
if (!empty($kategori)) {
    $sql .= " AND Type = '$kategori'";
}

// Jika search_query tidak kosong, tambahkan filter ke semua kolom termasuk Lokasi
if (!empty($search_query)) {
    $escaped_query = mysqli_real_escape_string($kon, $search_query);
    $sql .= " AND (
        Nama_Barang LIKE '%$escaped_query%' OR 
        Merek LIKE '%$escaped_query%' OR 
        Type LIKE '%$escaped_query%' OR 
        Serial_Number LIKE '%$escaped_query%' OR 
        Spesifikasi LIKE '%$escaped_query%' OR 
        Kelengkapan_Barang LIKE '%$escaped_query%' OR
        Kondisi_Barang LIKE '%$escaped_query%' OR
        Lokasi LIKE '%$escaped_query%' OR
        Id_Karyawan LIKE '%$escaped_query%' OR
        Jabatan LIKE '%$escaped_query%' OR
        Riwayat_Barang LIKE '%$escaped_query%' OR
        Photo_Barang LIKE '%$escaped_query%' OR 
        User_Perangkat LIKE '%$escaped_query%' OR 
        Jenis_Barang LIKE '%$escaped_query%' OR 
        Status_LOP LIKE '%$escaped_query%' OR
        Status_Kelayakan_Barang	LIKE '%$search_query%' OR
        Status_Barang LIKE '%$escaped_query%'
    )";
}
if (!empty($search_query)) {
    $escaped_query = mysqli_real_escape_string($kon, $search_query);
    $sql .= " AND (Nama_Barang LIKE '%$escaped_query%' OR 
        Merek LIKE '%$escaped_query%' OR 
        Type LIKE '%$escaped_query%' OR 
        Serial_Number LIKE '%$escaped_query%' OR 
        Spesifikasi LIKE '%$escaped_query%' OR 
        Kelengkapan_Barang LIKE '%$escaped_query%' OR
        Kondisi_Barang LIKE '%$escaped_query%' OR
        Lokasi LIKE '%$escaped_query%' OR
        Id_Karyawan LIKE '%$escaped_query%' OR
        Jabatan LIKE '%$escaped_query%' OR
        Riwayat_Barang LIKE '%$escaped_query%' OR
        Photo_Barang LIKE '%$escaped_query%' OR 
        User_Perangkat LIKE '%$escaped_query%' OR 
        Jenis_Barang LIKE '%$escaped_query%' OR 
        Status_LOP LIKE '%$escaped_query%' OR
        Status_Kelayakan_Barang	LIKE '%$search_query%' OR
        Status_Barang LIKE '%$escaped_query%')";
}


// Set timezone
date_default_timezone_set('Asia/Jakarta');




            // Logika penghapusan data ketika data ingin dihapus maka akan muncul pop up - lalu dia akan berhasil terhapus
            if (isset($_GET['id_peserta'])) {
                $id_peserta = htmlspecialchars($_GET["id_peserta"]);

                $sql = "DELETE FROM peserta WHERE id_peserta='$id_peserta' ";
                $hasil = mysqli_query($kon, $sql);

                if ($hasil) {
                    header("Location: index.php");
                } else {
                    echo "<div class='bg-red-500 text-white p-4 rounded-lg mb-4'>Data Gagal dihapus.</div>";
                }
            }


// Cek apakah pengguna login dan memiliki role yang sesuai
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'super_admin') {
    header("Location: admin/index.php");
    exit();
}

// Cek apakah pengguna sudah login
if (!isset($_SESSION['role'])) {
    header('Location: login.php');
    exit();
}

// Ambil role pengguna dari session
$role = $_SESSION['role'];

// Pengalihan berdasarkan peran pengguna
if (isset($_GET['home'])) {
    if ($role == 'super_admin') {
        header("Location: dashboard_admin.php");
    } elseif ($role == 'user') {
        header("Location: dashboard_user.php");
    }
    exit();
}

// Handle penghapusan data
if (isset($_GET['delete.php']) && $_GET['delete.php'] === 'true' && isset($_GET['id_peserta'])) {
    $id_peserta = $_GET['id_peserta'];
    $query = "DELETE FROM peserta WHERE id_peserta = ?";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("i", $id_peserta);
    $stmt->execute();
    $stmt->close();
    header("Location: index.php");
    exit();
}

// Tentukan jumlah data per halaman
$limit = 10;

// Tentukan halaman yang sedang aktif
if (isset($_GET['page']) && is_numeric($_GET['page'])) {
    $page = (int)$_GET['page'];
    if ($page < 1) $page = 1;
} else {
    $page = 1;
}
$start_from = ($page - 1) * $limit;

// Ambil parameter pencarian dan kategori
$kategori = isset($_GET['kategori']) ? mysqli_real_escape_string($kon, $_GET['kategori']) : '';
$search_query = isset($_GET['search']) ? mysqli_real_escape_string($kon, $_GET['search']) : '';

// Query data peserta dengan filter kategori dan pencarian
$sql = "SELECT * FROM peserta WHERE 1=1";
if (!empty($kategori)) {
    $sql .= " AND Type = '$kategori'";
}
if (!empty($search_query)) {
    $escaped_query = mysqli_real_escape_string($kon, $search_query);
    $sql .= " AND (
        Nama_Barang LIKE '%$escaped_query%' OR 
        Merek LIKE '%$escaped_query%' OR 
        Type LIKE '%$escaped_query%' OR 
        Serial_Number LIKE '%$escaped_query%' OR 
        Spesifikasi LIKE '%$escaped_query%' OR 
        Kelengkapan_Barang LIKE '%$escaped_query%' OR
        Kondisi_Barang LIKE '%$escaped_query%' OR
        Lokasi LIKE '%$escaped_query%' OR
        Id_Karyawan LIKE '%$escaped_query%' OR
        Jabatan LIKE '%$escaped_query%' OR
        Riwayat_Barang LIKE '%$escaped_query%' OR
        Photo_Barang LIKE '%$escaped_query%' OR 
        User_Perangkat LIKE '%$escaped_query%' OR 
        Jenis_Barang LIKE '%$escaped_query%' OR 
        Status_LOP LIKE '%$escaped_query%' OR
        Status_Kelayakan_Barang	LIKE '%$search_query%' OR
        Status_Barang LIKE '%$escaped_query%'
    )";
}
$sql .= " ORDER BY id_peserta DESC LIMIT $limit OFFSET $start_from";
$hasil = mysqli_query($kon, $sql);
$kategori = isset($_GET['kategori']) ? mysqli_real_escape_string($kon, $_GET['kategori']) : '';
$search_query = isset($_GET['search']) ? mysqli_real_escape_string($kon, $_GET['search']) : '';

// Query data peserta dengan filter kategori dan pencarian
$sql = "SELECT * FROM peserta WHERE 1=1";
if (!empty($kategori)) {
    $sql .= " AND Type = '$kategori'";
}
if (!empty($search_query)) {
    $escaped_query = mysqli_real_escape_string($kon, $search_query);
    $sql .= " AND (
        Nama_Barang LIKE '%$escaped_query%' OR 
        Merek LIKE '%$escaped_query%' OR 
        Type LIKE '%$escaped_query%' OR 
        Serial_Number LIKE '%$escaped_query%' OR 
        Spesifikasi LIKE '%$escaped_query%' OR 
        Kelengkapan_Barang LIKE '%$escaped_query%' OR
        Kondisi_Barang LIKE '%$escaped_query%' OR
        Lokasi LIKE '%$escaped_query%' OR
        Id_Karyawan LIKE '%$escaped_query%' OR
        Jabatan LIKE '%$escaped_query%' OR
        Riwayat_Barang LIKE '%$escaped_query%' OR
        Photo_Barang LIKE '%$escaped_query%' OR 
        User_Perangkat LIKE '%$escaped_query%' OR 
        Jenis_Barang LIKE '%$escaped_query%' OR 
        Status_LOP LIKE '%$escaped_query%' OR
        Status_Kelayakan_Barang	LIKE '%$search_query%' OR
        Status_Barang LIKE '%$escaped_query%'
    )";
}
$sql .= " ORDER BY id_peserta DESC LIMIT $limit OFFSET $start_from";
$hasil = mysqli_query($kon, $sql);
$kategori = isset($_GET['kategori']) ? mysqli_real_escape_string($kon, $_GET['kategori']) : '';
$search_query = isset($_GET['search']) ? mysqli_real_escape_string($kon, $_GET['search']) : '';

// Perbaiki agar hasil pencarian muncul walaupun sudah ada di query, dan pastikan warna teks input search hitam
echo '<style>
    .column-search {
        color: #111 !important;
        background-color: #fff !important;
    }
</style>';
$sql = "SELECT COUNT(id_peserta) FROM peserta WHERE 1=1";
if (!empty($kategori)) {
    $sql .= " AND Type = '$kategori'";
}
if (!empty($search_query)) {
    $sql .= " AND (Nama_Barang LIKE '%$search_query%' OR 
        Merek LIKE '%$search_query%' OR 
        Type LIKE '%$search_query%' OR 
        Serial_Number LIKE '%$search_query%' OR 
        Spesifikasi LIKE '%$search_query%' OR 
        Kelengkapan_Barang LIKE '%$search_query%' OR
        Kondisi_Barang LIKE '%$search_query%' OR
        Lokasi LIKE '%$search_query%' OR
        Id_Karyawan LIKE '%$search_query%' OR
        Jabatan LIKE '%$search_query%' OR
        Riwayat_Barang LIKE '%$search_query%' OR
        Photo_Barang LIKE '%$search_query%' OR 
        User_Perangkat LIKE '%$search_query%' OR 
        Jenis_Barang LIKE '%$search_query%' OR 
        Status_Kelayakan_Barang	LIKE '%$search_query%' OR
        Status_Barang LIKE '%$search_query%')";
}
$result = mysqli_query($kon, $sql);
$row = mysqli_fetch_row($result);
$total_records = $row[0];
$total_pages = ceil($total_records / $limit);

// Query data peserta dengan filter dan pagination
$sql = "SELECT * FROM peserta WHERE 1=1";
if (!empty($kategori)) {
    $sql .= " AND Type = '$kategori'";
}
if (!empty($search_query)) {
    $sql .= " AND (Nama_Barang LIKE '%$search_query%' OR 
        Merek LIKE '%$search_query%' OR 
        Type LIKE '%$search_query%' OR 
        Serial_Number LIKE '%$search_query%' OR 
        Spesifikasi LIKE '%$search_query%' OR 
        Kelengkapan_Barang LIKE '%$search_query%' OR
        Kondisi_Barang LIKE '%$search_query%' OR
        Lokasi LIKE '%$search_query%' OR
        Id_Karyawan LIKE '%$search_query%' OR
        Jabatan LIKE '%$search_query%' OR
        Riwayat_Barang LIKE '%$search_query%' OR
        Photo_Barang LIKE '%$search_query%' OR 
        User_Perangkat LIKE '%$search_query%' OR 
        Jenis_Barang LIKE '%$search_query%' OR 
        Status_Kelayakan_Barang	LIKE '%$search_query%' OR
        Status_Barang LIKE '%$search_query%')";
}
$sql .= " ORDER BY id_peserta DESC LIMIT $limit OFFSET $start_from";
$hasil = mysqli_query($kon, $sql);
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ASSET IT CITRATEL</title>
    <link rel="stylesheet" href="css/style.css">
     <!-- SweetAlert2 CSS -->
     <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <!-- Tailwind CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>

        

.special-5 {
    margin-top: 20px; /* Sesuaikan nilai untuk menurunkan angka */
    transform: translateY(20px); /* Geser angka ke bawah */
}

.special-pagination {
    margin-top: 10px; /* Sesuaikan nilai untuk menurunkan angka */
}
.pagination li a {
    padding: 8px 12px;
    text-decoration: none;
    color: #333;
    border: 1px solid #ddd;
    border-radius: 4px;
    display: inline-block;
    margin-top: 5px; /* Tambahkan margin atas */
}

.pagination li.active a {
    background-color: #007bff;
    color: white;
    margin-top: 5px; /* Tambahkan margin atas untuk elemen aktif */
}

/* Badge untuk Jenis Barang */
.jenis-badge {
    display: inline-block;
    padding: 0.2rem 0.5rem;
    border-radius: 9999px; /* Full rounded corners */
    font-size: 0.75rem;
    text-align: center;
    font-weight: 600;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); /* Shadow lebih halus */
    white-space: nowrap; /* Mencegah teks membungkus */
}

/* Sembunyikan kolom dengan kelas hidden-column */
.hidden-column {
    display: none;
}

/* Close button inside the sidebar */
#close-sidebar {
    position: absolute;
    top: 10px;
    right: 10px;
    font-size: 20px;
    cursor: pointer;
    background: none;
    border: none;
}

/* When sidebar is closed, hide it */
#sidebar {
    transform: translateX(-100%); /* Initially hidden */
    transition: transform 0.3s ease;
}

/* When sidebar is open, show it */
#sidebar.open {
    transform: translateX(0); /* Sidebar is visible */
}

/* Ensure hamburger icon appears again when sidebar is closed */
#hamburger {
    display: block;
}

/* Sidebar adjustments */
#sidebar {
    position: fixed;
    top: 0;
    left: 0;
    height: 100vh;
    width: 250px;
    background-color: #333;
    z-index: 9999;
    transition: transform 0.3s ease;
}

/* For mobile responsiveness */
@media (min-width: 768px) {
    #sidebar {
        width: 250px;
    }
}

/* Close button inside the sidebar */
#close-sidebar {
    position: absolute;
    top: 10px;
    right: 10px;
    font-size: 20px;
    cursor: pointer;
    background: none;
    border: none;
}

.table-container {
    max-height: 500px; /* Adjust the height according to your preference */
    overflow-y: auto;  /* Enable vertical scrolling */
        }

        table {
            width: 100%;
            max-width: 1000px;
            font-size: 12px;
            margin: auto;
            border-collapse: collapse;
        }

        th, td {
            padding: 6px;
            text-align: center;
            border: 1px solid #ddd;
            word-wrap: break-word;
        }

        th {
            background-color: #4CAF50;
            color: white;
        }

        img {
            max-width: 50px;
            height: auto;
        }

.fixed-element {
    position: fixed; /* atau sticky jika ingin lebih fleksibel */
    top: 20px; /* sesuaikan dengan kebutuhan */
    left: 20px; /* sesuaikan dengan kebutuhan */
    z-index: 999; /* agar selalu di atas */
    transform: scale(1); /* pastikan elemen tidak terdistorsi */
}

.fa-user-circle {
        font-size: 2rem; /* Sesuaikan ukuran sesuai kebutuhan */
    }

        .profile-info {
        display: flex;
        align-items: center;
        padding: 1rem;
        background-color: #2d3748; /* Warna latar belakang sidebar */
        border-bottom: 1px solid #4a5568; /* Batas bawah untuk pemisahan */
    }
    .profile-info img {
        border-radius: 50%;
        width: 40px;
        height: 40px;
        object-fit: cover;
        margin-right: 1rem;
    }
    .profile-info .name {
        color: #fff;
    }
    .profile-info .icon {
        margin-right: 1rem;
        font-size: 24px; /* Ukuran ikon */
    }


        #filterDropdown {
                display: none;
                position: absolute;
                z-index: 10;
                background-color: white;
                border: 1px solid #ddd;
                border-radius: 4px;
            }

            #filterDropdown.show {
                display: block;
            }
            

            /* Menambahkan flexbox pada kolom tabel untuk penataan badge */
/* Menambahkan flexbox pada kolom tabel untuk penataan badge */
.table-cell {
    display: flex;
    align-items: center; /* Vertikal center */
    justify-content: center; /* Horizontal center */
    padding: 2.0rem; /* Padding di dalam sel */
}

.status-badge {
    display: inline-block;
    padding: 0.2rem 0.5rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    text-align: center;
    font-weight: 600;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    animation: blink 1.5s infinite;
    white-space: nowrap; /* Mencegah teks membungkus */
}

@keyframes blink {
    0% {
        opacity: 1;
    }
    50% {
        opacity: 0.5;
    }
    100% {
        opacity: 1;
    }
}


            /* Badge untuk Jenis Barang */
.jenis-badge {
    display: inline-block;
    padding: 0.2rem 0.5rem;
    border-radius: 9999px; /* Full rounded corners */
    font-size: 0.75rem;
    text-align: center;
    font-weight: 600;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); /* Shadow lebih halus */
    white-space: nowrap; /* Mencegah teks membungkus */
}

/* Badge untuk Jenis Barang */
.jenis-badge {
    display: inline-block;
    padding: 0.2rem 0.5rem;
    border-radius: 9999px; /* Full rounded corners */
    font-size: 0.75rem;
    text-align: center;
    font-weight: 600;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); /* Shadow lebih halus */
    white-space: nowrap; /* Mencegah teks membungkus */
}

/* Warna untuk setiap jenis barang */
.jenis-inventaris {
    background-color: #48bb78; /* Hijau */
    color: #ffffff; /* Putih */
}

.jenis-lop {
    background-color: #f56565; /* Merah */
    color: #ffffff; /* Putih */
}

.jenis-unknown {
    background-color: #a0aec0; /* Abu-abu */
    color: #ffffff; /* Putih */
}
            /* CSS untuk latar belakang merah dengan teks putih */
            .bg-red-text-white {
                background-color: #f56565; /* Merah */
                color: #ffffff; /* Putih */
            }
            /* CSS untuk latar belakang hijau dengan teks putih */
            .bg-green-text-white {
                background-color: #48bb78; /* Hijau */
                color: #ffffff; /* Putih */
            }

            .status-temporary {
                background-color: #fefcbf; /* Kuning muda */
                color: #4a5568; /* Warna teks abu-abu gelap */
            }

            .status-repair {
            background-color: #facc15; /* Kuning */
            color: #fff;
             }

            .status-rusak {
                background-color: #FFA500; /* Oranye Jeruk */
                color: #fff; /* Putih */
            }

         .status-badge {
            padding: 0.2rem 0.5rem;
            border-radius: 9999px; /* Full rounded corners */
            font-size: 0.75rem;
            display: inline-block;
            text-align: center;
            font-weight: 600;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); /* Shadow lebih halus */
        }
        .specification-column {
            text-align: left; /* Mengatur teks agar rata kiri */
            white-space: normal; /* Menjaga newline dalam teks tetap pada baris yang sama */
            overflow-wrap: break-word; /* Memastikan kata yang terlalu panjang tetap mematuhi lebar kolom */
            padding-left: 0; /* Menghapus padding kiri */
            margin-left: 0; /* Menghapus margin kiri */
        }


        .status_barang {
            text-align: left; /* Mengatur teks agar rata kiri */
            white-space: normal; /* Menjaga newline dalam teks tetap pada baris yang sama */
            overflow-wrap: break-word; /* Memastikan kata yang terlalu panjang tetap mematuhi lebar kolom */
            padding-left: 0; /* Menghapus padding kiri */
            margin-left: 0; /* Menghapus margin kiri */
        }

        .navbar-form {
        margin-left: auto; /* Memindahkan form ke kanan */
      
    }
        /* Styling untuk sidebar */
        
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 250px; /* Sesuaikan dengan lebar sidebar */
            background-color: #f8f9fa; /* Warna latar belakang sidebar */
            z-index: 1; /* Pastikan ini lebih rendah dari z-index navbar */
        /* Reset CSS */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

html, body {
    width: 100%;
    height: 100%;
    overflow-x: hidden;
}

/* Tombol Hamburger */
#hamburger {
    position: fixed; /* Tetap di posisi yang sama */
    top: 20px; /* Jarak dari atas */
    left: 20px; /* Jarak dari kiri */
    z-index: 1001; /* Pastikan tombol berada di atas elemen lainnya */
    cursor: pointer;
    width: 2.5rem; /* Ukuran tombol */
    height: 2.5rem;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    background-color: transparent; /* Pastikan tidak ada warna latar */
}

#hamburger span {
    display: block;
    width: 2rem; /* Lebar garis hamburger */
    height: 0.25rem; /* Tinggi garis hamburger */
    background-color: #333; /* Warna garis */
    margin: 0.3rem 0; /* Jarak antar garis */
    transition: all 0.3s ease;
}

/* Media Query */
@media (max-width: 768px) {
    #hamburger {
        top: 10px; /* Sesuaikan jarak dari atas untuk layar kecil */
        left: 10px; /* Sesuaikan jarak dari kiri untuk layar kecil */
    }
}
}
.navbar {
    background-color: orange;
    padding: 8px 16px;
}
.container {
    max-width: 100%;
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
form {
    margin-left: auto;
}
@media (max-width: 768px) {
    .navbar {
        padding: 6px 8px;
    }
    .container {
        flex-direction: row;
        padding: 0;
    }
    form {
        width: 100%;
        margin-left: 0;
    }
}

        }
        @media (max-width: 768px) {

            /* Sidebar should cover navbar on mobile and desktop */
            @media (max-width: 768px) {
                #sidebar {
                    width: 100%; /* On mobile, sidebar will cover full width */
                }
            }
        }
            nav ul {
                display: flex;
                justify-content: center; /* Center the pagination */
                padding-left: 0; /* Remove padding to ensure it's centered */
            }

            .pagination {
                display: flex;
                justify-content: flex-end; /* Align pagination items to the right */
                align-items: center;
                list-style: none;
                padding: 0;
                margin-top: 20px; /* Adjust margin as needed */
                margin-bottom: 20px; /* Add bottom margin for spacing */
            }
            .pagination li {
                margin: 0 5px 10px; /* Add bottom margin for spacing */
            }
            }
        
        .pagination li a {
            padding: 8px 12px;
            text-decoration: none;
            color: #333;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .pagination li.active a {
            background-color: #007bff;
            color: white;
        }

        /* Handle the 'Next' button */
        .pagination li a.next {
            font-weight: bold;
        }

        .pagination li a:hover {
            background-color: #ddd;
        }

         #sidebar.open {
                transform: translateX(0); /* When sidebar is open, bring it into view */
            }

        /* Sidebar adjustments to cover the navbar */
            #sidebar {
                position: fixed; /* Keep the sidebar fixed */
                top: 0;
                left: 0;
                height: 100vh;
                width: 250px; /* Sidebar width */
                background-color: #2d3748; /* Dark background like in the image */
                color: white; /* Set text color to white */
                z-index: 9999; /* Make sure sidebar is above the navbar */
                transform: translateX(-100%); /* Initially hidden */
                transition: transform 0.3s ease;
            }

            

            /* Show the hamburger button on mobile only */
            #hamburger {
                display: block;
            }

                            @media (min-width: 768px) {
                    /* Make sure sidebar is always visible on desktop */
                    #sidebar {
                        transform: translateX(0); /* Sidebar is always visible on desktop */
                    }

                    #hamburger {
                        display: none; /* Hide hamburger button on desktop */
                    }
                }

        /* Menghilangkan ruang tambahan atas pada konten utama */
        

        /* Menyesuaikan ukuran tombol */
        .btn {
            display: inline-flex;
            align-items: center;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            font-weight: 500;
            text-align: center;
            border-radius: 0.375rem;
        }

        .btn-icon {
            display: inline-flex;
            align-items: center;
            padding: 0.5rem;
            font-size: 1rem;
            border-radius: 0.375rem;
        }

        /* Tambahkan jarak bawah pada tombol tambah data */
        .btn-tambah {
            margin-bottom: 1rem; /* Jarak yang diinginkan antara tombol dan tabel */
        }


        /* kodingan 23/02/2025 */

        

    </style>
</head>
<nav class="navbar fixed top-0 left-0 w-full z-50 shadow flex items-center" style="background-color: orange; padding: 8px 16px;">
    <div class="container mx-auto flex items-center justify-between">
        <!-- Hamburger Button -->
        <button id="hamburger" class="md:hidden text-white mr-4" style="top: 3px; position: relative;">
            <i class="fas fa-bars"></i>
        </button>
            
            <!-- Form Pencarian -->
            <form class="flex items-center space-x-2 ml-auto" action="index.php" method="get">
            <input class="form-input px-3 py-2 border rounded-lg" type="search" name="search" placeholder="Search" aria-label="Search">
            <button class="bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600" type="submit">Search</button>
        </form>
    </div>
</nav>
    <body class="bg-gray-100">
    <style>
        #sidebar {
            background-color: #202938; /* Mengubah warna sidebar menjadi kode warna 202938 */
        }

        /* Ensure the hamburger button is visible on mobile devices */
        #hamburger {
    position: fixed; /* Tetap di posisi yang sama */
    top: 20px; /* Jarak dari atas */
    left: 20px; /* Jarak dari kiri */
    z-index: 1001; /* Pastikan tombol berada di atas elemen lainnya */
    cursor: pointer;
    width: 2.5rem; /* Ukuran tombol */
    height: 2.5rem;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    background-color: transparent; /* Pastikan tidak ada warna latar */
}

@media (max-width: 768px) {
    #hamburger {
        /* Jangan ubah posisi jika tidak diperlukan */
    }
}
    </style>
    <div class="flex min-h-screen pt-20">
        <!-- Sidebar di Mobile dan Desktop -->
        <div id="sidebar" class="w-64 bg-gray-800 text-white fixed h-full overflow-y-auto transition-transform duration-300 transform -translate-x-full md:translate-x-0">
            <div class="p-4 text-center text-lg font-bold flex items-center justify-center">
                Asset PT CIPTA KARYA TECHNOLOGY
            </div>
                        <!-- Close Icon in the Sidebar -->
            <button id="close-sidebar" class="text-white p-4 absolute top-4 right-4">
                <i class="fas fa-times"></i>
            </button>
            <!-- Profil Area -->
            <div class="p-4 flex items-center space-x-3 border-b border-gray-700">
                <i class="fas fa-user-circle"></i> <!-- Icon Profil -->
                <div>
                    <span class="block"><?php echo htmlspecialchars($username); ?></span> <!-- Nama pengguna -->
                </div>
            </div>
            <nav class="mt-6">
                <a href="dashboard_admin.php" class="block py-2 px-4 hover:bg-gray-700 transition-colors duration-200">
                    <i class="fas fa-tachometer-alt mr-1"></i> Dashboard
                </a>
                <a href="index.php" class="block py-2 px-4 hover:bg-gray-700 transition-colors duration-200">
                    <i class="fas fa-cogs mr-0"></i> Assets IT
                </a>
                <a href="serah_terima.php" class="block py-2 px-4 hover:bg-gray-700 transition-colors duration-200">
                    <i class="fas fa-file-alt mr-2"></i> Form Serah Terima
                </a>
                <a href="ticket.php" class="block py-2 px-4 hover:bg-gray-700 transition-colors duration-200">
                    <i class="fas fa-ticket-alt mr-2"></i> Ticket
                </a>
                <a href="../logout.php" class="block py-2 px-4 hover:bg-gray-700 transition-colors duration-200">
                    <i class="fas fa-sign-out-alt mr-1"></i> Logout
                </a>
            </nav>
        </div>

        
    <!-- Sidebar -->
    

    <!-- Navbar -->

    <div class="mx-auto bg-white rounded-lg shadow p-1 mb-8 w-full">
    <div class="flex-grow md:ml-64 p-20 overflow-auto">    
    <h4 class="text-center text-2xl font-semibold mt-2 mb-6">DAFTAR ASSET IT</h4>




        <!-- Tombol Tambah Data -->
        <a href="create.php" class="btn btn-tambah bg-blue-500 text-white hover:bg-blue-600">
            <i class="fas fa-plus mr-1"></i>
            <span>Tambah Data</span>
        </a>
        <a href="download_excel.php" class="btn bg-green-500 text-white hover:bg-green-600 flex items-center">
        <i class="fas fa-file-excel mr-2"></i> Download Excel
        </a>


        <!-- Form Filter -->
    <form method="GET" action="">
    <div class="flex items-center mb-4">
        <label for="kategori" class="mr-2">Filter Kategori:</label>
        <select name="kategori" id="kategori" class="form-select">
            <option value="">Semua</option>
            <option value="LAPTOP" <?php echo (isset($_GET['kategori']) && $_GET['kategori'] == 'LAPTOP') ? 'selected' : ''; ?>>Laptop</option>
            <option value="MONITOR" <?php echo (isset($_GET['kategori']) && $_GET['kategori'] == 'MONITOR') ? 'selected' : ''; ?>>Monitor</option>
            <option value="PC" <?php echo (isset($_GET['kategori']) && $_GET['kategori'] == 'PC') ? 'selected' : ''; ?>>PC</option>
        </select>
        <button type="submit" class="ml-2 btn bg-blue-500 text-white hover:bg-blue-600 px-4 py-2 rounded-lg">Filter</button>
    </div>
</form>

        <?php
            
                
            


            // Tentukan jumlah data per halaman
            $limit = 10; // Jumlah data per halaman
            

            

            // Ambil parameter pencarian
            $search_query = isset($_GET['search']) ? mysqli_real_escape_string($kon, $_GET['search']) : '';
            
            // Hitung total halaman
            $sql = "SELECT COUNT(id_peserta) FROM peserta WHERE 1=1";
            if (!empty($search_query)) {
                $sql .= " AND (
                    Nama_Barang LIKE '%$search_query%' OR 
                    Merek LIKE '%$search_query%' OR 
                    Type LIKE '%$search_query%' OR 
                    Serial_Number LIKE '%$search_query%' OR 
                    Spesifikasi LIKE '%$search_query%' OR 
                    Kelengkapan_Barang LIKE '%$search_query%' OR
                    Kondisi_Barang LIKE '%$search_query%' OR
                    Lokasi LIKE '%$search_query%' OR
                    Id_Karyawan LIKE '%$search_query%' OR
                    Jabatan LIKE '%$search_query%' OR
                    Riwayat_Barang LIKE '%$search_query%' OR
                    Photo_Barang LIKE '%$search_query%' OR 
                    User_Perangkat LIKE '%$search_query%' OR   
                    Jenis_Barang LIKE '%$search_query%' OR
                    Status_LOP LIKE '%$search_query%' OR
                    Status_Kelayakan_Barang	LIKE '%$search_query%' OR
                    Status_Barang LIKE '%$search_query%'
                )";
            }
            $result = mysqli_query($kon, $sql);
            $row = mysqli_fetch_row($result);
            $total_records = $row[0];
            
            $total_pages = ceil($total_records / $limit);
            
            // Tentukan halaman yang sedang aktif
            if (isset($_GET['page'])) {
                $page = $_GET['page'];
            } else {
                $page = 1;
            }
            $start_from = ($page - 1) * $limit;
            
            // Ambil data berdasarkan halaman aktif dan pencarian, urutkan berdasarkan ID secara menurun
            $sql = "SELECT * FROM peserta WHERE 1=1";
            if (!empty($search_query)) {
                $sql .= " AND (
                    Nama_Barang LIKE '%$search_query%' OR 
                    Merek LIKE '%$search_query%' OR 
                    Type LIKE '%$search_query%' OR 
                    Serial_Number LIKE '%$search_query%' OR 
                    Spesifikasi LIKE '%$search_query%' OR 
                    Kelengkapan_Barang LIKE '%$search_query%' OR
                    Kondisi_Barang LIKE '%$search_query%' OR
                    Lokasi LIKE '%$search_query%' OR
                    Id_Karyawan LIKE '%$search_query%' OR
                    Jabatan LIKE '%$search_query%' OR
                    Riwayat_Barang LIKE '%$search_query%' OR  
                    User_Perangkat LIKE '%$search_query%' OR 
                    Jenis_Barang LIKE '%$search_query%' OR 
                    Photo_Barang LIKE '%$search_query%' OR 
                    Status_Barang LIKE '%$search_query%' OR
                    Status_Kelayakan_Barang	LIKE '%$search_query%' OR
                    Status_LOP LIKE '%$search_query%'
                )";
            }
            $sql .= " ORDER BY id_peserta DESC
                LIMIT $limit OFFSET $start_from";
            $hasil = mysqli_query($kon, $sql);
        ?>
        <style>
          #hamburger {
    z-index: 9999; /* Pastikan tombol berada di atas elemen lainnya */
}        
        /* CSS untuk navbar */'

            .container {
            max-width: 100%;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 20px;
            }       
            .navbar {
                background-color: orange;
                padding: 8px 16px;
            }
            .content {
                transition: margin-left 0.3s ease; /* Tambahkan transisi untuk efek geser */
                margin-left: 0; /* Default margin ketika sidebar tertutup */
                padding: 1rem; /* Tambahkan padding untuk memberikan ruang */
                box-sizing: border-box; /* Pastikan padding tidak memengaruhi lebar total */
            }
    
            @media (max-width: 768px) {
                .content {
                    margin-left: 0; /* Pastikan konten tidak bergeser pada layar kecil */
                    padding: 0.5rem; /* Kurangi padding untuk layar kecil */
                }
    
                table {
                    font-size: 10px; /* Sesuaikan ukuran font tabel untuk layar kecil */
                }
    
                th, td {
                    padding: 4px; /* Kurangi padding untuk tabel */
                }
    
                .table-container {
                    overflow-x: auto; /* Tambahkan scroll horizontal untuk tabel */
                }
            }

            #sidebar.open ~ .content {
            margin-left: 250px; /* Geser konten ke kanan saat sidebar terbuka */
            }
        </style>
        <script>
            document.getElementById('hamburger').addEventListener('click', function() {
                const sidebar = document.getElementById('sidebar');
                const content = document.querySelector('.flex-grow'); // Ensure content shifts correctly
                sidebar.classList.toggle('open');
                if (sidebar.classList.contains('open')) {
                    sidebar.style.transform = 'translateX(0)';
                    content.style.marginLeft = '250px'; // Adjust content position
                } else {
                    sidebar.style.transform = 'translateX(-100%)';
                    content.style.marginLeft = '0'; // Reset content position
                }
            });

            document.getElementById('close-sidebar').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            const content = document.querySelector('.flex-grow'); // Pastikan memilih elemen dengan class flex-grow
            sidebar.classList.remove('open');
            content.style.marginLeft = '0'; // Geser konten ke kiri
            });
        </script>
        <!-- Tabel -->
        <div class="overflow-x-auto">
        <script>
            // Open/Close Sidebar using Hamburger and Close Button
            document.getElementById('hamburger').addEventListener('click', function() {
                const sidebar = document.getElementById('sidebar');
                const content = document.querySelector('.flex-grow'); // Ensure content shifts correctly
                sidebar.classList.toggle('open');
                this.classList.toggle('animate'); // Add animation class to hamburger
            
                if (sidebar.classList.contains('open')) {
                    sidebar.style.transform = 'translateX(0)';
                    content.style.marginLeft = '250px'; // Shift content to the right
                } else {
                    sidebar.style.transform = 'translateX(-100%)';
                    content.style.marginLeft = '0'; // Reset content position
                }
            });

            document.getElementById('close-sidebar').addEventListener('click', function() {
                const sidebar = document.getElementById('sidebar');
                sidebar.classList.remove('open'); // Hide sidebar
                document.getElementById('hamburger').classList.remove('animate'); // Reset animation class
                sidebar.style.transform = 'translateX(-100%)'; // Ensure sidebar is hidden
            });
        </script>

        <style>
            /* Hamburger Animation */
            #hamburger {
            position: relative;
            width: 30px;
            height: 20px;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            }

            #hamburger span {
            #hamburger.animate span:nth-child(1) {
            transform: rotate(45deg) translate(5px, 5px);
            background-color: #ff5722; /* Add color change for animation */
            }

            #hamburger.animate span:nth-child(2) {
            opacity: 0;
            }

            #hamburger.animate span:nth-child(3) {
            transform: rotate(-45deg) translate(5px, -5px);
            background-color: #ff5722; /* Add color change for animation */
            }
            #hamburger.animate span:nth-child(2) {
            opacity: 0;
            }

            #hamburger.animate span:nth-child(3) {
            transform: rotate(-45deg) translate(5px, -5px);
            }

            /* Sidebar Responsiveness */
            #sidebar {
            transform: translateX(-100%);
            transition: transform 0.3s ease-in-out;
            }

            #sidebar.open {
            transform: translateX(0);
            }

            @media (min-width: 768px) {
            #hamburger {
                display: none; /* Hide hamburger on desktop */
            }

            #sidebar {
                transform: translateX(0); /* Sidebar always visible on desktop */
            }
            }
        </style>
        <!-- Search input fields for each column, positioned below the table headers -->
        <div class="table-container">
            <div class="overflow-x-auto rounded-lg shadow">
  <table class="min-w-full divide-y divide-gray-200 bg-white text-sm">
    <thead class="bg-gradient-to-r from-green-500 to-green-700 text-white sticky top-0 z-10">
                    <tr>
                        <th class="px-4 py-2 border sticky top-0 bg-[#4cb050] text-white">No</th>
                        <th class="px-4 py-2 border sticky top-0 bg-[#4cb050] text-white">Waktu</th>
                        <th class="px-4 py-2 border sticky top-0 bg-[#4cb050] text-white">Nama Barang</th>
                        <th class="px-4 py-2 border sticky top-0 bg-[#4cb050] text-white">Merek</th>
                        <th class="px-4 py-2 border sticky top-0 bg-[#4cb050] text-white">Type</th>
                        <th class="px-4 py-2 border sticky top-0 bg-[#4cb050] text-white">Serial Number</th>
                        <th class="px-16 py-2 border sticky top-0 bg-[#4cb050] text-white">Spesifikasi</th>
                        <th class="px-4 py-2 border sticky top-0 bg-[#4cb050] text-white hidden-column">Kelengkapan Barang</th>
                        <th class="px-4 py-2 border sticky top-0 bg-[#4cb050] text-white hidden-column">Kondisi Barang</th>
                        <th class="px-4 py-2 border sticky top-0 bg-[#4cb050] text-white">Lokasi</th>
                        <th class="px-4 py-2 border sticky top-0 bg-[#4cb050] text-white">Employe ID</th>
                        <th class="px-4 py-2 border sticky top-0 bg-[#4cb050] text-white">Jabatan</th>
                        <th class="px-4 py-2 border sticky top-0 bg-[#4cb050] text-white hidden-column">Riwayat Barang</th>
                        <th class="px-4 py-2 border sticky top-0 bg-[#4cb050] text-white hidden-column">User yang menggunakan Perangkat</th>
                        <th class="px-4 py-2 border sticky top-0 bg-[#4cb050] text-white">Jenis Barang</th>
                        <th class="px-4 py-2 border sticky top-0 bg-[#4cb050] text-white">Status Barang</th>
                        <th class="px-4 py-2 border sticky top-0 bg-[#4cb050] text-white">Status LOP</th>
                        <th class="px-4 py-2 border sticky top-0 bg-[#4cb050] text-white">Layak / Tidak Layak</th>
                        <th class="px-4 py-2 border sticky top-0 bg-[#4cb050] text-white">Photo Barang</th>
                        <th class="px-4 py-2 border sticky top-0 bg-[#4cb050] text-white text-center" colspan="4">Aksi</th>
                    </tr>
                    <!-- Search row -->
                    <tr>
                        <th></th>
                        <th><input type="text" class="column-search border rounded px-2 py-1 w-full" placeholder="Cari Waktu" data-column="1"></th>
                        <th><input type="text" class="column-search border rounded px-2 py-1 w-full" placeholder="Cari Nama Barang" data-column="2"></th>
                        <th><input type="text" class="column-search border rounded px-2 py-1 w-full" placeholder="Cari Merek" data-column="3"></th>
                        <th><input type="text" class="column-search border rounded px-2 py-1 w-full" placeholder="Cari Type" data-column="4"></th>
                        <th><input type="text" class="column-search border rounded px-2 py-1 w-full" placeholder="Cari Serial Number" data-column="5"></th>
                        <th><input type="text" class="column-search border rounded px-2 py-1 w-full" placeholder="Cari Spesifikasi" data-column="6"></th>
                        <th class="hidden-column"><input type="text" class="column-search border rounded px-2 py-1 w-full" placeholder="Cari Kelengkapan" data-column="7"></th>
                        <th class="hidden-column"><input type="text" class="column-search border rounded px-2 py-1 w-full" placeholder="Cari Kondisi" data-column="8"></th>
                       <th><input type="text" class="column-search border rounded px-2 py-1 w-full" placeholder="Cari Lokasi" data-column="9"></th>
                       <th><input type="text" class="column-search border rounded px-2 py-1 w-full" placeholder="Cari Employe ID" data-column="10"></th>
                       <th><input type="text" class="column-search border rounded px-2 py-1 w-full" placeholder="Cari Jabatan" data-column="11"></th>
                        <th class="hidden-column"><input type="text" class="column-search border rounded px-2 py-1 w-full" placeholder="Cari Riwayat" data-column="12"></th>
                        <th class="hidden-column"><input type="text" class="column-search border rounded px-2 py-1 w-full" placeholder="Cari User" data-column="13"></th>
                        <th><input type="text" class="column-search border rounded px-2 py-1 w-full" placeholder="Cari Jenis" data-column="14"></th>
                        <th><input type="text" class="column-search border rounded px-2 py-1 w-full" placeholder="Cari Status" data-column="15"></th>
                        <th><input type="text" class="column-search border rounded px-2 py-1 w-full" placeholder="Cari Status LOP" data-column="16"></th>
                        <th><input type="text" class="column-search border rounded px-2 py-1 w-full" placeholder="Cari Layak / Tidak Layak " data-column="17"></th>
                        <th><input type="text" class="column-search border rounded px-2 py-1 w-full" placeholder="Cari Foto" data-column="18"></th>
                        <th colspan="4"></th>
                    </tr>
                </thead>
<tbody id="table-body">
    <tbody class="divide-y divide-gray-100">
    <!-- Isi tabel tetap sama, akan diisi oleh PHP -->

                <?php
                    $no = $start_from + 1;
                    while ($data = mysqli_fetch_array($hasil)) {
                        // Status Barang
                        switch ($data['Status_Barang']) {
                            case 'READY':
                                $status_class = 'bg-green-text-white';
                                break;
                            case 'KOSONG':
                                $status_class = 'bg-red-text-white';
                                break;
                            case 'REPAIR':
                                $status_class = 'status-repair';
                                break;
                            case 'TEMPORARY':
                                $status_class = 'status-temporary';
                                break;
                            case 'RUSAK':
                                $status_class = 'status-rusak';
                                break;
                            default:
                                $status_class = 'bg-gray-400 text-white-700'; // Default color for unknown status
                                break;
                        }


                        // Status LOP
                        switch (strtoupper(trim($data['Status_LOP']))) {
                            case 'LUNAS':
                                $status_lop_class = 'bg-green-text-white';
                                break;
                            case 'BELUM LUNAS':
                                $status_lop_class = 'bg-red-text-white';
                                break;
                            case 'TIDAK LOP':
                                $status_lop_class = 'status-temporary';
                                break;
                            default:
                                $status_lop_class = 'bg-gray-400 text-white'; // Default color for unknown status
                                break;
                        }


                        // Status Kelayakan Barang
                        switch (strtoupper(trim($data['Status_Kelayakan_Barang']))) {
                            case 'LAYAK':
                                $status_kelayakan_barang_class = 'bg-green-text-white';
                                break;
                            case 'TIDAK LAYAK':
                                $status_kelayakan_barang_class = 'bg-red-text-white';
                                break;
                            default:
                                $status_kelayakan_barang_class = 'bg-gray-400 text-white'; // Default color for unknown status
                                break;
                        }
                    
                        // Penanda untuk baris genap atau ganjil
                        $rowClass = ($no % 2 == 0) ? 'bg-gray-100' : 'bg-white';

                ?>

<?php
// Tentukan kelas CSS untuk Jenis Barang
switch ($data['Jenis_Barang']) {
    case 'INVENTARIS':
        $jenis_class = 'jenis-inventaris';
        break;
    case 'LOP':
        $jenis_class = 'jenis-lop';
        break;
    default:
        $jenis_class = 'jenis-unknown'; // Default untuk jenis barang yang tidak dikenal
        break;
}
?>
                

                <tr class ="<?php echo $rowClass; ?>">
                    <td class="px-4 py-2"><?php echo $no++; ?></td>
                    <td class="px-4 py-2"><?php echo $data["Waktu"]; ?></td>
                    <td class="px-4 py-2"><?php echo $data["Nama_Barang"]; ?></td>
                    <td class="px-4 py-2"><?php echo $data["Merek"]; ?></td>
                    <td class="px-4 py-2"><?php echo $data["Type"]; ?></td>
                    <td class="px-4 py-2"><?php echo $data["Serial_Number"]; ?></td>
                    <td class="px-16 py-2 specification-column"><?php echo nl2br(htmlspecialchars($data["Spesifikasi"])); ?></td>
                    <td class="px-4 py-2 hidden-column"><?php echo nl2br(htmlspecialchars($data["Kelengkapan_Barang"])); ?></td>
                    <td class="px-4 py-2 hidden-column"><?php echo nl2br(htmlspecialchars($data["Kondisi_Barang"])); ?></td>
                    <td class="px-4 py-2"><?php echo nl2br(htmlspecialchars($data["Lokasi"])); ?></td>
                    <td class="px-4 py-2"><?php echo nl2br(htmlspecialchars($data["Id_Karyawan"])); ?></td>
                    <td class="px-4 py-2"><?php echo nl2br(htmlspecialchars($data["Jabatan"])); ?></td>
                    <td class="px-4 py-2 hidden-column"><?php echo $data["Riwayat_Barang"]; ?></td>
                    <td class="px-4 py-2 hidden-column"><?php echo $data["User_Perangkat"]; ?></td>
                    <td class="px-4 py-2">
                        <span class="jenis-badge <?php echo $jenis_class; ?>">
                            <?php echo htmlspecialchars($data['Jenis_Barang']); ?>
                        </span>
                    </td>
                    <td class="px-4 py-2">
                    <span class="status-badge <?php echo $status_class; ?>">
                        <?php echo htmlspecialchars($data['Status_Barang']); ?>
                    </span>

                      <td class="px-4 py-2">
                    <span class="status-badge <?php echo $status_lop_class; ?>">
                        <?php echo htmlspecialchars($data['Status_LOP']); ?>
                    </span>
                     </td>

                    <td class="px-4 py-2">
                    <span class="status-badge <?php echo $status_kelayakan_barang_class; ?>">
                        <?php echo htmlspecialchars($data['Status_Kelayakan_Barang']); ?>
                    </span>
                    </td>
                    
                    <td class="px-4 py-2">
                    <?php if (!empty($data['Photo_Barang'])): ?> <!-- Cek jika ada gambar -->
                        <img src="../uploads/<?php echo htmlspecialchars($data['Photo_Barang']); ?>" alt="Foto Barang" class="w-20 h-20 object-cover">
                    <?php else: ?>
                        <span>Tidak ada foto</span> <!-- Teks alternatif jika tidak ada gambar -->
                    <?php endif; ?>
                </td>
    



                    <td class="px-4 py-2 border">                                        
                    <a href="update.php?id_peserta=<?php echo htmlspecialchars($data['id_peserta']); ?>" class="btn bg-green-500 text-white hover:bg-green-600 flex items-center mt-2">
                            <!-- Icon Edit -->
                            <i class="fas fa-edit mr-2"></i>
                            <span>Edit</span>
                        </a>
                    </td>

                    <!-- Tombol Hapus -->
                    <td class="px-4 py-2">
                    <button class="btn bg-red-500 text-white hover:bg-red-600 delete-btn" data-id="<?php echo htmlspecialchars($data['id_peserta']); ?>">
                        <i class="fas fa-trash mr-2"></i>
                        <span>Hapus</span>
                    </button>
                    </td>


                    <td class="px-4 py-2 border">
                    <a href="viewer.php?id_peserta=<?php echo htmlspecialchars($data['id_peserta']); ?>" class="flex items-center space-x-2 btn bg-blue-500 text-white hover:bg-blue-600 px-4 py-2 rounded-lg">
                        <!-- Icon Eye -->
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M10 2C6.13 2 2.61 4.44 1 8c1.61 3.56 5.13 6 9 6s7.39-2.44 9-6c-1.61-3.56-5.13-6-9-6zM10 12a4 4 0 110-8 4 4 0 010 8z" />
                            <path d="M10 10a2 2 0 110-4 2 2 0 010 4z" />
                        </svg>
                        <span>Viewer</span>
                    </a>

                    <td class="px-4 py-2 border">
                    <a href="../qr_print.php?id_peserta=<?php echo htmlspecialchars($data['id_peserta']); ?>" class="flex items-center justify-center btn bg-pink-500 text-white hover:bg-pink-600 px-4 py-2 rounded-lg">
                        <!-- Icon QR -->
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M3 3h4v4H3V3zm10 0h4v4h-4V3zM3 13h4v4H3v-4zm10 0h4v4h-4v-4zM7 7h2v2H7V7zm4 0h2v2h-2V7zm-4 4h2v2H7v-2zm4 0h2v2h-2v-2z" />
                        </svg>
                        <span>QR</span>
                    </a>
                </td>


                </tr>
                <?php
                    }
                ?>
            </tbody>
        </table>
        
        <!-- Pagination -->
        <nav id="js-pagination" class="mt-2" style="display:none"></nav>
        <br>
        <ul class="flex flex-wrap justify-center space-x-2 items-center">
            <?php
            $max_links = 5; // Jumlah maksimal halaman yang ditampilkan
            $start_page = max(1, $page - floor($max_links / 2));
            $end_page = min($total_pages, $start_page + $max_links - 1);

            // Menampilkan tautan ke halaman sebelumnya
            if ($page > 1) {
            echo "<li><a class='bg-gray-200 text-gray-800 px-4 py-2 rounded-full shadow-md hover:bg-gray-300 transition duration-200' href='index.php?page=" . ($page - 1) . "&search=" . urlencode($search_query) . "'>&laquo; Previous</a></li>";
            }

            // Menampilkan tautan ke setiap halaman dalam batasan
            for ($i = $start_page; $i <= $end_page; $i++) {
            if ($i == $page) {
            echo "<li class='mb-2'><a class='bg-blue-500 text-white px-4 py-2 rounded-full shadow-md font-bold' href='index.php?page=$i&search=" . urlencode($search_query) . "'>$i</a></li>";
            } else {
            echo "<li class='mb-2'><a class='bg-gray-200 text-gray-800 px-4 py-2 rounded-full shadow-md hover:bg-gray-300 transition duration-200' href='index.php?page=$i&search=" . urlencode($search_query) . "'>$i</a></li>";
            }
            }

            // Menampilkan tautan ke halaman berikutnya
            if ($page < $total_pages) {
            echo "<li><a class='bg-gray-200 text-gray-800 px-4 py-2 rounded-full shadow-md hover:bg-gray-300 transition duration-200' href='index.php?page=" . ($page + 1) . "&search=" . urlencode($search_query) . "'>Next &raquo;</a></li>";
            }
            ?>
            </ul>
        </nav>
            <!-- Menampilkan total data -->
            <div class="mt-4 md:mt-8 text-center text-gray-700 mb-16">
            Total Data: <strong><?php echo $total_data; ?></strong>
            </div>
        </nav>



    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
       

        document.getElementById('filterDropdownButton').addEventListener('click', function(event) {
        var dropdown = document.getElementById('filterDropdown');
        dropdown.classList.toggle('show');
        event.stopPropagation(); // Prevents the click event from bubbling up to the window
    });

    window.addEventListener('click', function(event) {
        var dropdown = document.getElementById('filterDropdown');
        var button = document.getElementById('filterDropdownButton');
        if (!button.contains(event.target) && !dropdown.contains(event.target)) {
            dropdown.classList.remove('show');
        }
    });
       
    </script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.delete-btn').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const id = this.getAttribute('data-id');
                    Swal.fire({
                        title: 'Apakah kamu yakin?',
                        text: 'Data ini akan dihapus permanen! Apakah kamu sudah yakin?',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Hapus',
                        cancelButtonText: 'Batal'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = 'index.php?delete=true&id_peserta=' + id;
                        }
                    });
                });
            });
        });
    </script>

 <script>
       // Open/Close Sidebar using Hamburger and Close Button
document.getElementById('hamburger').addEventListener('click', function() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('open');
});

document.getElementById('close-sidebar').addEventListener('click', function() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.remove('open'); // Hide sidebar
});

    </script>
    <script>
    // Real-time per-column search/filter for the table (match each input to its field only)
    document.addEventListener('DOMContentLoaded', function() {
        const searchInputs = document.querySelectorAll('.column-search');
        const table = document.getElementById('main-table');
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));

        // Mapping: input index => table column index (skip 'No' column)
        // Urutan: [Waktu, Nama_Barang, Merek, Type, Serial_Number, Spesifikasi, Kelengkapan_Barang, Kondisi_Barang, Lokasi, Riwayat_Barang, User_Perangkat, Jenis_Barang, Status_Barang, Photo_Barang]
        const columnMap = [1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16];

        searchInputs.forEach(function(input, idx) {
            input.addEventListener('input', function() {
                const filter = input.value.trim().toLowerCase();
                rows.forEach(row => {
                    const cells = row.querySelectorAll('td');
                    let show = true;
                    if (filter && cells[columnMap[idx]]) {
                        const cellText = cells[columnMap[idx]].innerText.toLowerCase();
                        if (!cellText.includes(filter)) {
                            show = false;
                        }
                    }
                    row.style.display = show ? '' : 'none';
                });
            });
        });
    });
    </script>
    <script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInputs = document.querySelectorAll('.column-search');
    const table = document.getElementById('main-table');
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    const phpPagination = document.querySelector('nav ul.flex');
    const jsPagination = document.getElementById('js-pagination');
    const rowsPerPage = 10;
    let currentPage = 1;

    function getFilteredRows() {
        return rows.filter(row => row.style.display !== 'none');
    }

    function renderPagination(filteredRows) {
        jsPagination.innerHTML = '';
        const totalPages = Math.ceil(filteredRows.length / rowsPerPage);
        if (totalPages <= 1) {
            jsPagination.style.display = 'none';
            return;
        }
        jsPagination.style.display = '';
        const ul = document.createElement('ul');
        ul.className = 'flex flex-wrap justify-center space-x-2 items-center';

        for (let i = 1; i <= totalPages; i++) {
            const li = document.createElement('li');
            li.className = 'mb-2';
            const a = document.createElement('a');
            a.className = (i === currentPage)
                ? 'bg-blue-500 text-white px-4 py-2 rounded-full shadow-md font-bold'
                : 'bg-gray-200 text-gray-800 px-4 py-2 rounded-full shadow-md hover:bg-gray-300 transition duration-200';
            a.href = '#';
            a.textContent = i;
            a.addEventListener('click', function(e) {
                e.preventDefault();
                currentPage = i;
                showPage(filteredRows, currentPage);
                renderPagination(filteredRows);
            });
            li.appendChild(a);
            ul.appendChild(li);
        }
        jsPagination.appendChild(ul);
    }

    function showPage(filteredRows, page) {
        const start = (page - 1) * rowsPerPage;
        const end = start + rowsPerPage;
        rows.forEach(row => row.style.display = 'none');
        filteredRows.slice(start, end).forEach(row => row.style.display = '');
    }

    function updateTable() {
        let filteredRows = rows;
        searchInputs.forEach(function(input, idx) {
            const filter = input.value.trim().toLowerCase();
            if (filter) {
                filteredRows = filteredRows.filter(row => {
                    const cells = row.querySelectorAll('td');
                    const cell = cells[idx + 1]; // +1 karena kolom No
                    return cell && cell.innerText.toLowerCase().includes(filter);
                });
            }
        });
        currentPage = 1;
        showPage(filteredRows, currentPage);
        renderPagination(filteredRows);

        // Sembunyikan pagination PHP jika search aktif
        const isSearching = Array.from(searchInputs).some(input => input.value.trim() !== '');
        if (phpPagination) phpPagination.parentElement.style.display = isSearching ? 'none' : '';
        jsPagination.style.display = isSearching ? '' : 'none';
    }

    // Inisialisasi
    showPage(rows, currentPage);
    renderPagination(rows);

    // Event listener untuk search
    searchInputs.forEach(input => {
        input.addEventListener('input', updateTable);
    });
});
</script>
</body>
</html>
