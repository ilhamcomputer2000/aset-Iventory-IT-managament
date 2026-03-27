<?php


session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Pastikan session username sudah diatur
$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'User';

// Koneksi ke database
// $conn = new mysqli("localhost:3306", "cktnosa2_admin", "uGXj8#eiI=P%", "cktnosa2_crud"); 
$conn = new mysqli("localhost", "root", "", "crud");
// Ambil halaman sekarang
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max($page, 1);

// Jumlah data per halaman
$limit = 10;

// Hitung offset
$offset = ($page - 1) * $limit;

// Hitung total data (buat pagination link)
$total_sql = "SELECT COUNT(*) AS total FROM peserta WHERE Status_Barang = 'KOSONG'";
$total_result = $conn->query($total_sql);
$total_row = $total_result->fetch_assoc();
$total_data = $total_row['total'];
$total_pages = ceil($total_data / $limit);

// Query data (pakai LIMIT & OFFSET)
$sql = "SELECT * FROM peserta 
        WHERE Status_Barang = 'KOSONG' 
        ORDER BY Waktu DESC 
        LIMIT $limit OFFSET $offset";
$result = $conn->query($sql);

// Nomor urut mulai dari offset + 1
$no = $offset + 1;



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

if ($result && $result->num_rows > 0) {
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
        <!-- Hamburger Button (visible on mobile, outside sidebar) -->
        <button id="hamburger-button" class="hamburger lg:hidden fixed left-4 top-4 z-40" aria-label="Toggle Sidebar">
            <span class="bar bg-white"></span>
            <span class="bar bg-white"></span>
            <span class="bar bg-white"></span>
        </button>
        <!-- Tombol Close (X) sebagai pengganti hamburger saat sidebar aktif -->
        <button id="close-sidebar-button" class="hidden lg:hidden fixed right-4 top-4 z-40 text-white text-2xl bg-gray-800 rounded-full w-10 h-10 flex items-center justify-center shadow-lg transition-all duration-300 ease-in-out transform hover:scale-110 hover:bg-gray-700 active:scale-95 focus:outline-none focus:ring-2 focus:ring-blue-400 animate-fade-in" aria-label="Close Sidebar">
            <i class="fas fa-times"></i>
        </button>
        <div class="w-64 bg-gray-800 text-white fixed h-full sidebar transform -translate-x-full lg:translate-x-0 transition-transform duration-300 z-30">
            <div class="flex justify-center items-center p-4 relative">
            <div class="text-lg font-bold text-center">Asset PT CIPTA KARYA TECHNOLOGY</div>
            </div>
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
        
        <!-- Main content -->
        <div class="flex-1 ml-0 lg:ml-64 p-6 transition-all duration-300">
            <!-- Dropdown Filter with Search (Search inside dropdown) -->
            <div class="mb-8 flex justify-center">
                <div class="w-full max-w-xs">
                    
                    <div class="relative">
                       
                        <div id="dropdownMenu" class="absolute z-20 w-full bg-white border-2 border-blue-300 rounded-xl shadow-2xl mt-2 hidden animate-fade-in">
                            <div class="flex items-center px-4 py-2 border-b bg-blue-50 rounded-t-xl">
                                <i class="fas fa-search text-blue-400 mr-2"></i>
                                <input type="text" id="search-barang" placeholder="Cari barang..." class="w-full px-2 py-1 bg-transparent focus:outline-none text-gray-700" autocomplete="off">
                            </div>
                            <ul id="barangList" class="max-h-56 overflow-y-auto py-2">
                                <li data-value="" class="px-4 py-2 hover:bg-blue-100 cursor-pointer rounded">-- Pilih Barang --</li>
                                <li data-value="LAPTOP" class="px-4 py-2 hover:bg-blue-100 cursor-pointer rounded">LAPTOP</li>
                                <li data-value="MONITOR" class="px-4 py-2 hover:bg-blue-100 cursor-pointer rounded">MONITOR</li>
                                <li data-value="PC" class="px-4 py-2 hover:bg-blue-100 cursor-pointer rounded">PC</li>
                                <li data-value="KEYBOARD" class="px-4 py-2 hover:bg-blue-100 cursor-pointer rounded">KEYBOARD</li>
                                <li data-value="HP" class="px-4 py-2 hover:bg-blue-100 cursor-pointer rounded">HP</li>
                                <li data-value="KOMPUTER" class="px-4 py-2 hover:bg-blue-100 cursor-pointer rounded">KOMPUTER</li>
                                <li data-value="PRINTER" class="px-4 py-2 hover:bg-blue-100 cursor-pointer rounded">PRINTER</li>
                                <li data-value="CASING KOMPUTER" class="px-4 py-2 hover:bg-blue-100 cursor-pointer rounded">CASING KOMPUTER</li>
                                <li data-value="MAINBOARD" class="px-4 py-2 hover:bg-blue-100 cursor-pointer rounded">MAINBOARD</li>
                                <li data-value="DVR" class="px-4 py-2 hover:bg-blue-100 cursor-pointer rounded">DVR</li>
                                <li data-value="CAMERA CCTV" class="px-4 py-2 hover:bg-blue-100 cursor-pointer rounded">CAMERA CCTV</li>
                                <li data-value="HARDISK" class="px-4 py-2 hover:bg-blue-100 cursor-pointer rounded">HARDISK</li>
                                <li data-value="ROUTER" class="px-4 py-2 hover:bg-blue-100 cursor-pointer rounded">ROUTER</li>
                            </ul>
                        </div>
                        <input type="hidden" id="filter-barang" name="filter-barang" value="">
                    </div>
                </div>
            </div>
            <style>
            @keyframes fade-in {
                from { opacity: 0; transform: translateY(-10px);}
                to { opacity: 1; transform: translateY(0);}
            }
            .animate-fade-in {
                animation: fade-in 0.2s ease;
            }
            </style>
            <script>
            document.addEventListener('DOMContentLoaded', function() {
               
              

                

                // Filter list
                searchInput.addEventListener('input', function() {
                    filterList(this.value);
                });

                function filterList(search) {
                    Array.from(barangList.children).forEach(li => {
                        if (li.textContent.toLowerCase().includes(search.toLowerCase())) {
                            li.style.display = '';
                        } else {
                            li.style.display = 'none';
                        }
                    });
                }

                // Select item
                barangList.addEventListener('click', function(e) {
                    if (e.target.tagName === 'LI') {
                        selectedBarang.textContent = e.target.textContent;
                        hiddenInput.value = e.target.getAttribute('data-value');
                        dropdownMenu.classList.add('hidden');
                    }
                });

                // Hide dropdown if click outside
                document.addEventListener('click', function(e) {
                    if (!dropdownButton.contains(e.target) && !dropdownMenu.contains(e.target)) {
                        dropdownMenu.classList.add('hidden');
                    }
                });
            });
            </script>
            <h1 class="text-2xl font-bold mb-4">Dashboard Kosong</h1>

            <p class="mb-4"></p>
            Welcome to the Asset PT CIPTA KARYA TECHNOLOGY Dashboard Kosong!
            <!-- Modern Dashboard Cards (Laravel-like style) -->
            <?php
include "../config.php"; // koneksi DB

// ambil page
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// query data dengan limit
$sql = "SELECT * FROM peserta 
        WHERE Status_Barang = 'KOSONG' 
        ORDER BY Waktu DESC 
        LIMIT $limit OFFSET $offset";
$result = $conn->query($sql);

// total data untuk pagination
$totalResult = $conn->query("SELECT COUNT(*) as total FROM peserta WHERE Status_Barang = 'KOSONG'");
$totalRow = $totalResult->fetch_assoc();
$totalPages = ceil($totalRow['total'] / $limit);

// nomor urut
$no = $offset + 1;
?>
            <div class="bg-white rounded-xl shadow-lg p-4 md:p-6 mt-6">
                <div class="w-full overflow-x-auto rounded-xl shadow-inner border border-gray-200 bg-gradient-to-br from-white via-gray-50 to-gray-100">
                    <table class="min-w-[1200px] w-full divide-y divide-gray-200 text-sm md:text-base">
                        <thead class="bg-gradient-to-r from-blue-600 via-blue-500 to-blue-400 text-white">
                            <tr>
                                <th class="px-3 py-3 text-left font-bold tracking-wider rounded-tl-xl">No</th>
                                <th class="px-3 py-3 text-left font-bold tracking-wider">Waktu</th>
                                <th class="px-3 py-3 text-left font-bold tracking-wider">Nama Barang</th>
                                <th class="px-3 py-3 text-left font-bold tracking-wider">Merek</th>
                                <th class="px-3 py-3 text-left font-bold tracking-wider">Type</th>
                                <th class="px-3 py-3 text-left font-bold tracking-wider">Serial Number</th>
                                <th class="px-3 py-3 text-left font-bold tracking-wider">Spesifikasi</th>
                                <th class="px-3 py-3 text-left font-bold tracking-wider">Lokasi</th>
                                <th class="px-3 py-3 text-left font-bold tracking-wider">Employe ID</th>
                                <th class="px-3 py-3 text-left font-bold tracking-wider">Jabatan</th>
                                <th class="px-3 py-3 text-left font-bold tracking-wider">Jenis Barang</th>
                                <th class="px-3 py-3 text-left font-bold tracking-wider">Status Barang</th>
                                <th class="px-3 py-3 text-left font-bold tracking-wider">Status LOP</th>
                                <th class="px-3 py-3 text-left font-bold tracking-wider">Layak / Tidak Layak</th>
                                <th class="px-3 py-3 text-left font-bold tracking-wider rounded-tr-xl">Photo Barang</th>
                            </tr>
                        </thead>
                      <tbody class="bg-white divide-y divide-gray-100">
    <?php
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
           echo '<tr class="hover:bg-blue-50 transition">';
echo '<td class="px-3 py-2 font-semibold text-gray-700">' . $no++ . '</td>';
echo '<td class="px-3 py-2 text-gray-600 whitespace-nowrap">' . htmlspecialchars($row['Waktu']) . '</td>';
echo '<td class="px-3 py-2 text-gray-700">' . htmlspecialchars($row['Nama_Barang']) . '</td>';
echo '<td class="px-3 py-2 text-gray-700">' . htmlspecialchars($row['Merek']) . '</td>';
echo '<td class="px-3 py-2 text-gray-700">' . htmlspecialchars($row['Type']) . '</td>';
echo '<td class="px-3 py-2 text-gray-700">' . htmlspecialchars($row['Serial_Number']) . '</td>';
echo '<td class="px-3 py-2 text-gray-700">' . htmlspecialchars($row['Spesifikasi']) . '</td>';
echo '<td class="px-3 py-2 text-gray-700">' . htmlspecialchars($row['Lokasi']) . '</td>';
echo '<td class="px-3 py-2 text-gray-700">' . htmlspecialchars($row['Id_Karyawan']) . '</td>';
echo '<td class="px-3 py-2 text-gray-700">' . htmlspecialchars($row['Jabatan']) . '</td>';
echo '<td class="px-3 py-2 text-gray-700">' . htmlspecialchars($row['Jenis_Barang']) . '</td>';
echo '<td class="px-3 py-2 text-gray-700">' . htmlspecialchars($row['Status_LOP']) . '</td>';

echo '<td class="px-3 py-2">';
echo '<span class="inline-block px-2 py-1 rounded-full text-xs font-bold ';
switch ($row['Status_Barang']) {
    case 'READY':
        echo 'bg-green-100 text-green-700 border border-green-300';
        break;
    case 'KOSONG':
        echo 'bg-red-100 text-red-700 border border-red-300';
        break;
    case 'REPAIR':
        echo 'bg-yellow-100 text-yellow-800 border border-yellow-300';
        break;
    case 'TEMPORARY':
        echo 'bg-purple-100 text-purple-700 border border-purple-300';
        break;
    case 'RUSAK':
        echo 'bg-pink-100 text-pink-700 border border-pink-300';
        break;
    default:
        echo 'bg-gray-100 text-gray-700 border border-gray-300';
}
echo '">' . htmlspecialchars($row['Status_Barang']) . '</span>';
echo '</td>';

echo '<td class="px-3 py-2">';
if (strtolower($row['Status_Kelayakan_Barang']) == 'layak') {
    echo '<span class="inline-block px-2 py-1 rounded-full bg-green-200 text-green-900 font-semibold text-xs border border-green-300">Layak</span>';
} else {
    echo '<span class="inline-block px-2 py-1 rounded-full bg-red-200 text-red-900 font-semibold text-xs border border-red-300">Tidak Layak</span>';
}
echo '</td>';

echo '<td class="px-3 py-2">';
if (!empty($row['Photo_Barang'])) {
    echo '<img src="' . htmlspecialchars($row['Photo_Barang']) . '" alt="Photo" class="w-14 h-14 object-cover rounded-lg border border-gray-200 shadow-sm">';
} else {
    echo '<span class="text-gray-400 italic">No Photo</span>';
}
echo '</td>';
echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="15" class="px-3 py-2 text-center text-gray-500">No data available</td></tr>';    
    }
    ?>
</tbody>
                    </table>


                    

                    <!-- Pagination -->
<div class="flex justify-center mt-6">
    <nav class="inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
        <!-- Previous -->
        <?php if ($page > 1): ?>
            <a href="?page=<?php echo $page - 1; ?>" 
               class="px-3 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-100">
                Previous
            </a>
        <?php endif; ?>

        <!-- Numbered pages -->
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <a href="?page=<?php echo $i; ?>" 
               class="px-3 py-2 border border-gray-300 text-sm font-medium 
                      <?php echo ($i == $page) 
                          ? 'bg-blue-500 text-white' 
                          : 'bg-white text-gray-700 hover:bg-gray-100'; ?>">
                <?php echo $i; ?>
            </a>
        <?php endfor; ?>

        <!-- Next -->
        <?php if ($page < $total_pages): ?>
            <a href="?page=<?php echo $page + 1; ?>" 
               class="px-3 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-100">
                Next
            </a>
        <?php endif; ?>
    </nav>
</div>
                </div>
            </div>

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
                                label: 'Jumlah Barang per Status',
                                data: [
                                    <?php echo $jumlah_ready; ?>,
                                    <?php echo $jumlah_kosong; ?>,
                                    <?php echo $jumlah_repair; ?>,
                                    <?php echo $jumlah_temporary; ?>,
                                    <?php echo $jumlah_rusak; ?>
                                ],
                                backgroundColor: [
                                    'rgba(16, 185, 129, 0.85)', // Ready
                                    'rgba(239, 68, 68, 0.85)', // Kosong
                                    'rgba(251, 191, 36, 0.85)', // Repair
                                    'rgba(139, 92, 246, 0.85)',  // Temporary
                                    'rgba(236, 72, 153, 0.85)',  // Rusak
                                ],
                                borderRadius: 12,
                                borderSkipped: false,
                                maxBarThickness: 40
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false },
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
                                    color: '#222',
                                    font: {
                                        weight: 'bold',
                                        size: 14
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    grid: { display: false },
                                    ticks: { font: { weight: 'bold' } }
                                },
                                y: {
                                    beginAtZero: true,
                                    grid: { color: '#f3f4f6' },
                                    ticks: { stepSize: 1 }
                                }
                            }
                        },
                        plugins: [ChartDataLabels]
                    });

                    // Toggle sidebar and hamburger/close button visibility
                    const hamburgerButton = document.getElementById('hamburger-button');
                    const sidebar = document.querySelector('.sidebar');
                    const closeSidebarButton = document.getElementById('close-sidebar-button');

                    function openSidebar() {
                        sidebar.classList.add('active');
                        hamburgerButton.classList.add('hidden');
                        closeSidebarButton.classList.remove('hidden');
                    }

                    function closeSidebar() {
                        sidebar.classList.remove('active');
                        hamburgerButton.classList.remove('hidden');
                        closeSidebarButton.classList.add('hidden');
                    }

                    hamburgerButton && hamburgerButton.addEventListener('click', openSidebar);
                    closeSidebarButton && closeSidebarButton.addEventListener('click', closeSidebar);

                    // Optional: close sidebar when clicking outside on mobile
                    document.addEventListener('click', function(e) {
                        if (
                            sidebar.classList.contains('active') &&
                            !sidebar.contains(e.target) &&
                            !hamburgerButton.contains(e.target) &&
                            !closeSidebarButton.contains(e.target)
                        ) {
                            closeSidebar();
                        }
                    });
                });
            </script>

               


            </html>
