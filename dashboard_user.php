<?php
session_start();
require_once __DIR__ . '/app_url.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . app_abs_path('login'));
    exit();
}

// Pastikan session username sudah diatur
$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'User';

// FIX: Ambil nama lengkap dari session (fallback ke username jika kosong)
$Nama_Lengkap = isset($_SESSION['Nama_Lengkap']) ? $_SESSION['Nama_Lengkap'] : $username;

// Greeting day name in English (e.g., Saturday)
$Hari_Nice = date('l');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <style>
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

        /* Tambahkan gaya untuk tombol hamburger */
        .hamburger {
            display: none;
            cursor: pointer;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            height: 40px;
            width: 40px;
        }

        .bar {
            width: 100%;
            height: 4px;
            background-color: #fff;
            margin: 2px 0;
            transition: 0.4s;
        }

        /* Responsif */
        @media (max-width: 768px) {
            .hamburger {
                display: flex;
            }

            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .sidebar.active {
                transform: translateX(0);
            }
            .sidebar-visible {
                display: block;
            }
        }
    </style>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin</title>
    <!-- Tailwind CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.0/dist/chart.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

</head>
<body class="bg-gray-100">
<div class="flex h-screen">
        <!-- Sidebar -->
        <div class="w-64 bg-gray-800 text-white fixed h-full sidebar sidebar-hidden lg:sidebar-visible">
            <div class="flex justify-between items-center p-4">
                <div class="text-lg font-bold">Asset PT CIPTA KARYA TECHNOLOGY</div>
                <button id="close-button" class="text-white focus:outline-none">
                    <i class="fas fa-times"></i> <!-- Tombol Close -->
                </button>
            </div>
            <div class="p-4 flex items-center space-x-3 border-b border-gray-700">
                <i class="fas fa-user-circle"></i> <!-- Icon Profil -->
                <div>
                    <span class="block"><?php echo htmlspecialchars($username); ?></span> <!-- Nama pengguna -->
                </div>
            </div>
            <nav class="mt-6">
                <a href="view.php" class="block py-2 px-4 hover:bg-gray-700 transition-colors duration-200">
                    <i class="fas fa-cogs mr-0"></i> Assets IT
                </a>
                <a href="report.php" class="block py-2 px-4 hover:bg-gray-700 transition-colors duration-200">
                    <i class="fas fa-file-alt mr-2"></i> Reports
                </a>
                <a href="<?php echo htmlspecialchars(app_abs_path('logout.php')); ?>" class="block py-2 px-4 hover:bg-gray-700 transition-colors duration-200">
                    <i class="fas fa-sign-out-alt mr-1"></i> Logout
                </a>
            </nav>
        </div>

        <!-- Tombol Hamburger -->
        <div class="lg:hidden p-4">
            <button id="hamburger-button" class="text-gray-800">
                <i class="fas fa-bars fa-2x"></i>
            </button>
        </div>

        <!-- Main content -->
        <div class="flex-1 ml-0 lg:ml-64 p-6 transition-all duration-300">
            <div class="flex flex-wrap items-start justify-between gap-4 mb-4">
                <div>
                    <h1 class="text-2xl font-bold">Dashboard</h1>
                    <p class="mt-2">Welcome to the Asset PT CIPTA KARYA TECHNOLOGY Dashboard!</p>
                </div>
                <div class="text-right">
                    <div class="text-4xl font-extrabold text-orange-600 leading-tight">Halo, <?php echo htmlspecialchars($Nama_Lengkap); ?></div>
                    <div class="text-2xl font-bold text-gray-800 mt-1">Have a Nice <?php echo htmlspecialchars($Hari_Nice); ?>!</div>
                </div>
            </div>
            <!-- Grafik -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h2 class="text-xl font-semibold mb-4">Jumlah Laptop Ready</h2>
                <canvas id="laptop-chart" style="height: 300px; width: 100%;"></canvas>
            </div>
        </div>
    </div>

    <!-- Script Chart.js -->
    <?php
    // Koneksi ke database
    $conn = new mysqli("localhost", "root", "", "crud"); // Gantilah "crud" dengan nama database Anda

    // Cek koneksi
    if ($conn->connect_error) {
        die("Koneksi gagal: " . $conn->connect_error);
    }

    // Query untuk menghitung jumlah berdasarkan status
    $sql = "
        SELECT 
            SUM(CASE WHEN Status_Barang = 'READY' THEN 1 ELSE 0 END) AS total_ready,
            SUM(CASE WHEN Status_Barang = 'KOSONG' THEN 1 ELSE 0 END) AS total_kosong,
            SUM(CASE WHEN Status_Barang = 'REPAIR' THEN 1 ELSE 0 END) AS total_repair,
            SUM(CASE WHEN Status_Barang = 'TEMPORARY' THEN 1 ELSE 0 END) AS total_temporary,
            SUM(CASE WHEN Status_Barang = 'RUSAK' THEN 1 ELSE 0 END) AS total_rusak FROM peserta";
    $result = $conn->query($sql);

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
    ?>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0/dist/chartjs-plugin-datalabels.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var ctx = document.getElementById('laptop-chart').getContext('2d');
            var laptopChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['Ready', 'Kosong', 'Repair', 'Temporary', 'Rusak'],
                    datasets: [{
                        label: 'Jumlah Laptop per Status',
                        data: [
                            <?php echo $jumlah_ready; ?>,
                            <?php echo $jumlah_kosong; ?>,
                            <?php echo $jumlah_repair; ?>,
                            <?php echo $jumlah_temporary; ?>,
                            <?php echo $jumlah_rusak; ?>
                        ],
                        backgroundColor: [
                            'rgba(75, 192, 192, 0.8)', // Ready
                            'rgba(255, 99, 132, 0.8)', // Kosong
                            'rgba(255, 159, 64, 0.8)', // Repair
                            'rgba(153, 102, 255, 0.8)',  // Temporary
                            'rgba(255, 0, 0, 0.8)',  // Rusak
                        ],
                        borderColor: [
                            'rgba(75, 192, 192, 1)', // Ready
                            'rgba(255, 99, 132, 1)', // Kosong
                            'rgba(255, 159, 64, 1)', // Repair
                            'rgba(153, 102, 255, 1)',  // Temporary
                            'rgba(255, 0, 0, 0.8)',  // Rusak
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: true
                        },
                        tooltip: {
                            callbacks: {
                                label: function(tooltipItem) {
                                    return tooltipItem.dataset.label + ': ' + tooltipItem.raw;
                                }
                            }
                        },
                        datalabels: {
                            anchor: 'end',
                            align: 'start',
                            color: 'white', // Mengatur warna teks di dalam batang
                            font: {
                                weight: 'bold',
                                size: 16
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });

            // Toggle sidebar
            const hamburgerButton = document.getElementById('hamburger-button');
            const sidebar = document.querySelector('.sidebar');
            const closeButton = document.getElementById('close-button');

            hamburgerButton.addEventListener('click', function() {
                sidebar.classList.toggle('active');
            });

            closeButton.addEventListener('click', function() {
                sidebar.classList.remove('active');
            });
        });
    </script>
</body>
</html>
