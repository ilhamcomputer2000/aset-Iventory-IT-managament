<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}



// Pastikan session username sudah diatur
$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'User';
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
    .profile-info .icon {
        margin-right: 1rem;
        font-size: 24px; /* Ukuran ikon */
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
</head>
<body class="bg-gray-100">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <div class="w-64 bg-gray-800 text-white fixed h-full">
            <div class="p-4 text-center text-lg font-bold flex items-center justify-center">
                Asset IT PT CKT 
            </div>

            <!-- Profil Area -->
    <div class="p-4 flex items-center space-x-3 border-b border-gray-700">
        <i class="fas fa-user-circle"></i> <!-- Icon Profil -->
        <div>
        <span class="block"><?php echo htmlspecialchars($username); ?></span> <!-- Nama pengguna -->
        </div>
    </div>
    <nav class="mt-6">
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

        <!-- Main content -->
        <div class="flex-1 ml-64 p-6">
            <h1 class="text-2xl font-bold mb-4">Dashboard</h1>
            <p class="mb-4">Welcome to the Asset PT CIPTA KARYA TECHNOLOGY Dashboard!</p>
            <!-- Grafik -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h2 class="text-xl font-semibold mb-4">Jumlah Barang tersedia</h2>
                <canvas id="laptop-chart" style="height: 300px; width: 100%;"></canvas>
            </div>
        </div>
    </div>

    <!-- Script Chart.js -->
    <?php
// Koneksi ke database
$conn = new mysqli("localhost:3306", "cktnosa2", "fDSfe71Yj:)U68", "cktnosa2_crud"); // Gantilah "crud" dengan nama database Anda

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
        SUM(CASE WHEN Status_Barang = 'TEMPORARY' THEN 1 ELSE 0 END) AS total_temporary FROM peserta";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $jumlah_ready = $row['total_ready'];
    $jumlah_kosong = $row['total_kosong'];
    $jumlah_repair = $row['total_repair'];
    $jumlah_temporary = $row['total_temporary'];
} else {
    $jumlah_ready = 0;
    $jumlah_kosong = 0;
    $jumlah_repair = 0;
    $jumlah_temporary = 0;
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
                labels: ['Ready', 'Kosong', 'Repair', 'Temporary'],
                datasets: [{
                    label: 'Jumlah Laptop per Status',
                    data: [
                        <?php echo $jumlah_ready; ?>,
                        <?php echo $jumlah_kosong; ?>,
                        <?php echo $jumlah_repair; ?>,
                        <?php echo $jumlah_temporary; ?>
                    ],
                    backgroundColor: [
                        'rgba(75, 192, 192, 0.8)', // Ready
                        'rgba(255, 99, 132, 0.8)', // Kosong
                        'rgba(255, 159, 64, 0.8)', // Repair
                        'rgba(153, 102, 255, 0.8)'  // Temporary
                    ],
                    borderColor: [
                        'rgba(75, 192, 192, 1)', // Ready
                        'rgba(255, 99, 132, 1)', // Kosong
                        'rgba(255, 159, 64, 1)', // Repair
                        'rgba(153, 102, 255, 1)'  // Temporary
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
                            size: 14
                        },
                        formatter: function(value) {
                            return value; // Menampilkan angka langsung
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true
                    },
                    y: {
                        beginAtZero: true
                    }
                }
            },
            plugins: [ChartDataLabels] // Tambahkan plugin ke array plugin
        });
    });
</script>

</body>
</html>
