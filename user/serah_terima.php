<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// $role = $_SESSION['role']; // Ambil role dari session
// if ($role !== 'super_admin' && $role !== 'user') {
//     header("Location: ../login.php");
//     exit();
// }

// Pastikan session username sudah diatur
$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'User';
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
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.0/dist/chart.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="navbar.css">

</head>
<body class="bg-gray-100">
<div class="flex h-screen">
        <!-- Sidebar -->
        <div class="w-64 bg-gray-800 text-white fixed h-full sidebar sidebar-hidden lg:sidebar-visible">
            <div class="flex justify-center items-center p-4">
            <div class="text-lg font-bold text-center">Asset PT CIPTA KARYA TECHNOLOGY</div>
            <button id="close-button" class="text-white focus:outline-none absolute top-4 right-4">
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
            <a href="dashboard_user.php" class="block py-2 px-4 hover:bg-gray-700 transition-colors duration-200">
                <i class="fas fa-tachometer-alt mr-1"></i> Dashboard
            </a>
                <a href="view.php" class="block py-2 px-4 hover:bg-gray-700 transition-colors duration-200">
                    <i class="fas fa-cogs mr-0"></i> Assets IT
                </a>
                <a href="serah_terima.php" class="block py-2 px-4 hover:bg-gray-700 transition-colors duration-200">
                    <i class="fas fa-file-alt mr-2"></i> Form Serah Terima
                </a>
                <a href="ticket.php" class="block py-2 px-4 hover:bg-gray-700 transition-colors duration-200">
                    <i class="fas fa-ticket-alt mr-2"></i> Ticket
                </a>
                <a href="<?php echo htmlspecialchars(rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\') . '/logout.php'); ?>" class="block py-2 px-4 hover:bg-gray-700 transition-colors duration-200">
                    <i class="fas fa-sign-out-alt mr-1"></i> Logout
                </a>
            </nav>
        </div>