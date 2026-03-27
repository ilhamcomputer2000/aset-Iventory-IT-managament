<?php
session_start();
include "koneksi.php";

require_once __DIR__ . '/app_url.php';

// Koneksi ke database
$conn = new mysqli("localhost", "root", "", "crud");

// Cek koneksi
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Periksa apakah form login telah dikirim
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Query untuk memeriksa pengguna di database
    // Pakai SELECT * agar kompatibel walau struktur kolom berbeda antar server
    $sql = "SELECT * FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        // Verifikasi password
        if (password_verify($password, $user['password'])) {
            // Simpan informasi pengguna ke dalam session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'] ?? ''; // Setel session username

            // Sinkron session dengan login.php/login_process.php
            $_SESSION['role'] = $user['role'] ?? 'user';
            $_SESSION['Nama_Lengkap'] = $user['Nama_Lengkap'] ?? ($_SESSION['username'] ?: '');
            $_SESSION['Jabatan_Level'] = (string)($user['Jabatan_Level'] ?? '');

            // Redirect ke dashboard
            if (($_SESSION['role'] ?? 'user') === 'super_admin') {
                header("Location: " . app_abs_path('dashboard_admin'));
            } else {
                header("Location: " . app_abs_path('dashboard_user'));
            }
            exit();
        } else {
            echo "Password salah!";
        }
    } else {
        echo "Pengguna tidak ditemukan!";
    }
}
?>