<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$_SESSION['user_id'] = $user['id']; // Menyimpan ID pengguna
// Mengambil username dari session
$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'User';


include "koneksi.php";

require_once __DIR__ . '/app_url.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = mysqli_real_escape_string($kon, $_POST['username']);
    $password = mysqli_real_escape_string($kon, $_POST['password']);

    $sql = "SELECT * FROM users WHERE username='$username' AND password='$password'";
    $result = mysqli_query($kon, $sql);

    if (mysqli_num_rows($result) == 1) {
        $user = mysqli_fetch_assoc($result);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username']; // Menyimpan username ke session
        $_SESSION['role'] = $user['role'];
        $_SESSION['Id_Karyawan'] = $user['Id_Karyawan']; // TAMBAHAN: Simpan ID Karyawan
        $_SESSION['Nama_Lengkap'] = $user['Nama_Lengkap']; // TAMBAHAN: Simpan Nama Lengkap
        $_SESSION['Jabatan_Level'] = isset($user['Jabatan_Level']) ? (string)$user['Jabatan_Level'] : '';

        if ($user['role'] == 'super_admin') {
            header("Location: " . app_abs_path('dashboard_admin'));
            exit();
        } else if ($user['role'] == 'user') {
            header("Location: " . app_abs_path('dashboard_user'));
            exit();
        }
    } else {
        $_SESSION['login_error'] = true;
        header("Location: " . app_abs_path('login'));
        exit();
    }
}
?>