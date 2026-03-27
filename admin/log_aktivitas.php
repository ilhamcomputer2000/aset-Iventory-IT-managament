<?php
// Mulai session
session_start();

// Include koneksi database
include "../koneksi.php";

// Pastikan session username sudah diatur
$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'User';


// Set timezone
date_default_timezone_set('Asia/Jakarta');


// Ambil data notifikasi
$query = "SELECT * FROM log_aktivitas ORDER BY waktu DESC LIMIT 10";
$result = $kon->query($query);

$notifikasi = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $notifikasi[] = $row;
    }
}


// Ambil data dari form atau input
$aktivitas = 'ilham telah menambahkan data pada ' . date('Y-m-d H:i:s');

// Menyimpan aktivitas ke database
$sql = "INSERT INTO log_aktivitas (aktivitas) VALUES ('$aktivitas')";
if ($kon->query($sql) === TRUE) {
    echo "";
} else {
    echo "Error: " . $sql . "<br>" . $kon->error;
}



// Ambil data notifikasi
$query = "SELECT * FROM log_aktivitas ORDER BY waktu DESC LIMIT 25"; // Mengambil 25 log aktivitas terakhir
$result = $kon->query($query);

$notifikasi = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $notifikasi[] = $row;
    }
}


            // Logika penghapusan data ketika data ingin dihapus maka akan muncul pop up - lalu dia akan berhasil terhapus
            if (isset($_GET['id'])) {
                $id = htmlspecialchars($_GET["id"]);

                $sql = "DELETE FROM peserta WHERE id='$id' ";
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
if (isset($_GET['delete.php']) && $_GET['delete.php'] === 'true' && isset($_GET['id'])) {
    $id = $_GET['id'];
    $query = "DELETE FROM peserta WHERE id = ?";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: index.php");
    exit();
}

// Tentukan jumlah data per halaman
$limit = 10;

// Ambil parameter pencarian dan kategori
$search_query = isset($_GET['search']) ? mysqli_real_escape_string($kon, $_GET['search']) : '';

// Hitung total halaman
$sql = "SELECT COUNT(id) FROM peserta WHERE 
    aktivitas LIKE '%$search_query%' OR 
    waktu LIKE '%$search_query%' OR 
    Type LIKE '%$search_query%' ";
$result = mysqli_query($kon, $sql);
$row = mysqli_fetch_row($result);
$total_records = $row[0];
$total_pages = ceil($total_records / $limit);

// Tentukan halaman yang sedang aktif
$page = isset($_GET['page']) ? $_GET['page'] : 1;
$start_from = ($page - 1) * $limit;

// Ambil data berdasarkan halaman aktif dan pencarian
$sql = "SELECT * FROM log_aktivitas WHERE 
    aktivitas LIKE '%$search_query%' OR 
    waktu LIKE '%$search_query%' OR 
    Type LIKE '%$search_query%' OR 
    
    ORDER BY id DESC
    LIMIT $limit OFFSET $start_from";
$hasil = mysqli_query($kon, $sql);
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ASSET IT CITRATEL</title>
    <link rel="stylesheet" href="css/style.css">

    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@3.3.0/dist/tailwind.min.css" rel="stylesheet">
     <!-- SweetAlert2 CSS -->
     <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <!-- Tailwind CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>


.notification-icon {
            background-color: #f97316; /* Oranye sesuai dengan navbar */
            color: white;
            border-radius: 9999px; /* Bulat penuh */
            width: 2rem;
            height: 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            cursor: pointer;
        }
        .notification-popup {
            display: none;
            position: absolute;
            top: 2.5rem;
            left: 0;
            background: white;
            color: black;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            width: 300px;
            max-height: 300px; /* Maksimal tinggi popup */
            overflow-y: auto; /* Tambahkan scrollbar jika konten melebihi tinggi */
            padding: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            z-index: 10;
        }
        .notification-popup.show {
            display: block;
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
        .navbar-form {
        margin-left: auto; /* Memindahkan form ke kanan */
        position: relative; /* Mengatur posisi relatif */
        top: 1rem; /* Atur jarak dari bagian atas navbar */
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
        }
        .navbar {
            position: fixed;
            top: 0;
            left: 256px; /* Memberi jarak agar tidak menutupi sidebar */
            width: calc(100% - 250px); /* Menghindari tumpang tindih dengan sidebar */
            z-index: 20;
            background-color: orange; /* Background putih */
        }
        .content {
            margin-left: 250px; /* Padding untuk memberi jarak dari sidebar */
            padding-top: 4rem; /* Padding atas untuk menghindari tumpang tindih dengan navbar */
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
    </style>
</head>
<nav class="navbar p-4 shadow flex items-center">


<div class="relative">
    <!-- Tombol Notifikasi -->
    <button id="notificationButton" class="text-orange-500 hover:text-white dark:hover:text-gray-300 p-2 rounded-full bg-orange-600 hover:bg-orange-700 dark:bg-orange-500 dark:hover:bg-orange-400 transition-colors">
        <i class="fas fa-bell"></i>
    </button>
    <!-- Dropdown Notifikasi -->
    <div id="notificationDropdown" class="hidden absolute left-0 mt-2 w-80 bg-white dark:bg-gray-800 text-black dark:text-white rounded-lg shadow-lg ring-1 ring-black ring-opacity-5 dark:ring-white dark:ring-opacity-10">
        <ul class="divide-y divide-gray-200 dark:divide-gray-700">
            <?php if (count($notifikasi) > 0): ?>
                <?php foreach ($notifikasi as $notif): ?>
                    <li class="p-4 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                       <a href="http://www.facebook.com"><p class="text-sm"><?php echo htmlspecialchars($notif['aktivitas']); ?></p>
                        <p class="text-xs text-gray-500 dark:text-gray-400"><?php echo $notif['waktu']; ?></p>
                </a></li>
                <?php endforeach; ?>
            <?php else: ?>
                <li class="p-4 text-center">Tidak ada notifikasi</li>
            <?php endif; ?>
        </ul>
    </div>
</div>


        <div class="container mx-auto flex items-center">
            <!-- Form Pencarian -->
            <form class="flex items-center ml-auto space-x-2 shadow-lg" action="index.php" method="get">
                <input class="form-input px-3 py-2 border rounded-lg" type="search" name="search" placeholder="Search" aria-label="Search">
                <button class="bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600" type="submit">Search</button>
                 

            </form>
        </div>
    </nav>
<body class="bg-gray-100">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <div class="w-64 bg-gray-800 text-white fixed h-full">
            <div class="p-4 text-center text-lg font-bold flex items-center justify-center">
                Asset PT CIPTA KARYA TECHNOLOGY
            </div>
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
                <a href="#" class="block py-2 px-4 hover:bg-gray-700 transition-colors duration-200">
                    <i class="fas fa-file-alt mr-2"></i> Reports
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

    
    <div class="container mx-auto p-4 content">
        <h4 class="text-center text-2xl font-semibold mb-10" style="margin-top: 50px;">DAFTAR ASSET IT</h4>

        <!-- Tombol Tambah Data -->
        <a href="create.php" class="btn btn-tambah bg-blue-500 text-white hover:bg-blue-600">
            <i class="fas fa-plus mr-1"></i>
            <span>Tambah Data</span>
        </a>

        
        <?php
            
            
            


            // Tentukan jumlah data per halaman
            $limit = 10; // Jumlah data per halaman

            // Ambil parameter pencarian
            $search_query = isset($_GET['search']) ? mysqli_real_escape_string($kon, $_GET['search']) : '';
            
            // Hitung total halaman
            $sql = "SELECT COUNT(id) FROM peserta WHERE 
                aktivitas LIKE '%$search_query%' OR 
                waktu LIKE '%$search_query%' OR 
                Type LIKE '%$search_query%' OR 
                Serial_Number LIKE '%$search_query%' OR 
                Spesifikasi LIKE '%$search_query%' OR 
                Kelengkapan_Barang LIKE '%$search_query%' OR
                Kondisi_Barang LIKE '%$search_query%' OR
                Riwayat_Barang LIKE '%$search_query%' OR
                User_Perangkat LIKE '%$search_query%' OR   
                Status_Barang LIKE '%$search_query%'";
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
            $sql = "SELECT * FROM peserta WHERE 
                aktivitas LIKE '%$search_query%' OR 
                waktu LIKE '%$search_query%' OR 
                Type LIKE '%$search_query%' OR 
                Serial_Number LIKE '%$search_query%' OR 
                Spesifikasi LIKE '%$search_query%' OR 
                Kelengkapan_Barang LIKE '%$search_query%' OR
                Kondisi_Barang LIKE '%$search_query%' OR
                Riwayat_Barang LIKE '%$search_query%' OR  
                User_Perangkat LIKE '%$search_query%' OR 
                Status_Barang LIKE '%$search_query%'
                ORDER BY id DESC
                LIMIT $limit OFFSET $start_from";
            $hasil = mysqli_query($kon, $sql);
        ?>
        

        <table class="min-w-full bg-white border border-gray-200 rounded-lg shadow-md">
            <thead>
                <tr class="bg-blue-200 text-left shadow-sm">
                    <th class="px-4 py-2 border">No</th>
                    <th class="px-4 py-2 border">Waktu</th>
                    <th class="px-4 py-2 border">Nama Barang</th>
                    <th class="px-4 py-2 border">waktu</th>
                    <th class="px-4 py-2 border">Type</th>
                    <th class="px-4 py-2 border">Serial Number</th>
                    <th class="px-16 py-2 border">Spesifikasi</th>
                    <th class="px-4 py-2 border">Kelengkapan Barang</th>
                    <th class="px-4 py-2 border">Kondisi Barang</th>
                    <th class="px-4 py-2 border">Riwayat Barang</th>
                    <th class="px-4 py-2 border">User yang menggunakan Perangkat</th>
                    <th class="px-4 py-2 border">Status Barang</th>
                    <th class="px-4 py-2 border text-center" colspan="3">Aksi</th>
                </tr>
            </thead>
            
            <tbody>

            <?php
while ($row = mysqli_fetch_assoc($result)) {
    echo "<tr>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . $row['aktivitas'] . "</td>";
    echo "<td>" . $row['waktu'] . "</td>";
    
    echo "<td>";
    if ($_SESSION['role'] == 'super_admin') {
        echo "<a href='update.p.php?id=" . $row['id'] . "' class='btn btn-warning'>Edit</a>";
        echo "<a href='delete.php?id=" . $row['id'] . "' class='btn btn-danger' onclick='return confirm(\"Yakin ingin menghapus?\")'>Hapus</a>";
        echo "<a href='create.php' class='btn btn-primary'>Tambah</a>";
       echo "<a href='viewer.php?id=" . $row['id'] . "' class='btn btn-info'><i class='fas fa-eye mr-2'></i>Viewer</a>";
    } elseif ($_SESSION['role'] == 'user') {
        echo "<a href='view.php?id=" . $row['id'] . "' class='btn btn-info'>View</a>";
        echo "<a href='cetak_pdf.php?id=" . $row['id'] . "' class='btn btn-success'>Cetak PDF</a>";
        echo "<a href='viewer.php?id=" . $row['id'] . "' class='btn btn-info'><i class='fas fa-eye mr-2'></i>Viewer</a>";
    }
    echo "</td>";
    
    echo "</tr>";
}
?>

                <?php
                    $no = $start_from + 1;
                    while ($data = mysqli_fetch_array($hasil)) {
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
                            default:
                                $status_class = 'bg-gray-400 text-white-700'; // Default color for unknown status
                                break;
                        }
                        // Penanda untuk baris genap atau ganjil
                        $rowClass = ($no % 2 == 0) ? 'bg-gray-100' : 'bg-white';

                

                ?>
                <tr class ="<?php echo $rowClass; ?>">
                    <td class="px-4 py-2"><?php echo $no++; ?></td>
                    <td class="px-4 py-2"><?php echo $data["Waktu"]; ?></td>
                    <td class="px-4 py-2"><?php echo $data["aktivitas"]; ?></td>
                    <td class="px-4 py-2"><?php echo $data["waktu"]; ?></td>
                    <td class="px-4 py-2"><?php echo $data["Type"]; ?></td>
                    <td class="px-4 py-2"><?php echo $data["Serial_Number"]; ?></td>
                    <td class="px-16 py-2 specification-column"><?php echo nl2br(htmlspecialchars($data["Spesifikasi"])); ?></td>
                    <td class="px-4 py-2"><?php echo nl2br(htmlspecialchars($data["Kelengkapan_Barang"])); ?></td>
                    <td class="px-4 py-2"><?php echo nl2br(htmlspecialchars($data["Kondisi_Barang"])); ?></td>
                    <td class="px-4 py-2"><?php echo $data["Riwayat_Barang"]; ?></td>
                    <td class="px-4 py-2"><?php echo $data["User_Perangkat"]; ?></td>
                    <td class="<?php echo $rowClass; ?> table-cell">
                    <span class="status-badge <?php echo $status_class; ?>">
                        <?php echo htmlspecialchars($data['Status_Barang']); ?>
                    </span>
                    </td>



                    <td class="px-4 py-2 border">                                        
                    <a href="update.php?id=<?php echo htmlspecialchars($data['id']); ?>" class="btn bg-green-500 text-white hover:bg-green-600 flex items-center mt-2">
                            <!-- Icon Edit -->
                            <i class="fas fa-edit mr-2"></i>
                            <span>Edit</span>
                        </a>
                    </td>

                    <!-- Tombol Hapus -->
                    <td class="px-4 py-2">
                    <button class="btn bg-red-500 text-white hover:bg-red-600 delete-btn" data-id="<?php echo htmlspecialchars($data['id']); ?>">
                        <i class="fas fa-trash mr-2"></i>
                        <span>Hapus</span>
                    </button>
                    </td>


                    <td class="px-4 py-2 border">
                    <a href="viewer.php?id=<?php echo htmlspecialchars($data['id']); ?>" class="flex items-center space-x-2 btn bg-blue-500 text-white hover:bg-blue-600 px-4 py-2 rounded-lg">
                        <!-- Icon Eye -->
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M10 2C6.13 2 2.61 4.44 1 8c1.61 3.56 5.13 6 9 6s7.39-2.44 9-6c-1.61-3.56-5.13-6-9-6zM10 12a4 4 0 110-8 4 4 0 010 8z" />
                            <path d="M10 10a2 2 0 110-4 2 2 0 010 4z" />
                        </svg>
                        <span>Viewer</span>
                    </a>
                </td>


                </tr>
                <?php
                    }
                ?>
            </tbody>
        </table>
        
        <!-- Pagination -->
        <nav class="mt-4">
            <ul class="flex justify-center space-x-2">
                <?php
                // Menampilkan tautan ke halaman sebelumnya
                if ($page > 1) {
                    echo "<li><a class='bg-gray-300 text-gray-700 px-3 py-1 rounded-lg hover:bg-gray-400' href='index.php?page=" . ($page - 1) . "&search=" . urlencode($search_query) . "'>Previous</a></li>";
                }

                // Menampilkan tautan ke setiap halaman
                for ($i = 1; $i <= $total_pages; $i++) {
                    if ($i == $page) {
                        echo "<li><a class='bg-blue-500 text-white px-3 py-1 rounded-lg' href='index.php?page=$i&search=" . urlencode($search_query) . "'>$i</a></li>";
                    } else {
                        echo "<li><a class='bg-gray-300 text-gray-700 px-3 py-1 rounded-lg hover:bg-gray-400' href='index.php?page=$i&search=" . urlencode($search_query) . "'>$i</a></li>";
                    }
                }

                // Menampilkan tautan ke halaman berikutnya
                if ($page < $total_pages) {
                    echo "<li><a class='bg-gray-300 text-gray-700 px-3 py-1 rounded-lg hover:bg-gray-400' href='index.php?page=" . ($page + 1) . "&search=" . urlencode($search_query) . "'>Next</a></li>";
                }
                ?>
            </ul>
        </nav>
    </div>


    <script>
       
    </script>


    <script>
        $(document).ready(function() {
            // Sembunyikan loading screen saat halaman selesai dimuat
            $('#loading').hide();

            $('.btn-tambah').click(function(event) {
                event.preventDefault(); // Mencegah pengalihan langsung

                // Tampilkan loading screen
                $('#loading').fadeIn();

                // Setelah 2 detik, arahkan ke halaman create.php
                setTimeout(function() {
                    window.location.href = $('.btn-tambah').attr('href');
                }, 2000);
            });
        });

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
                            window.location.href = 'index.php?delete=true&id=' + id;
                        }
                    });
                });
            });
        });



            


        document.getElementById('notificationButton').addEventListener('click', function() {
    var dropdown = document.getElementById('notificationDropdown');
    if (dropdown.classList.contains('hidden')) {
        dropdown.classList.remove('hidden');
    } else {
        dropdown.classList.add('hidden');
    }
});
         // Menampilkan atau menyembunyikan dropdown notifikasi
         document.getElementById('notifBtn').addEventListener('click', function () {
            var dropdown = document.getElementById('notifDropdown');
            dropdown.classList.toggle('hidden');
        });

        // Menyembunyikan dropdown jika klik di luar
        document.addEventListener('click', function (event) {
            var dropdown = document.getElementById('notifDropdown');
            var button = document.getElementById('notifBtn');
            if (!button.contains(event.target) && !dropdown.contains(event.target)) {
                dropdown.classList.add('hidden');
            }
        });

        // Fungsi untuk memuat notifikasi
        function loadNotifications() {
            fetch('get_notifications.php') // Endpoint untuk mendapatkan notifikasi
                .then(response => response.json())
                .then(data => {
                    const notifContent = document.getElementById('notifContent');
                    notifContent.innerHTML = data.map(notification => `
                        <p class="px-4 py-2 text-sm text-gray-700">${notification.message} pada ${notification.timestamp}</p>
                    `).join('');
                });
        }

        // Panggil fungsi untuk memuat notifikasi saat halaman dimuat
        document.addEventListener('DOMContentLoaded', loadNotifications);



    </script>



    


    

</body>
</html>
