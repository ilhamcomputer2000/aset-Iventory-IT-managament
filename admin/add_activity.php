<?php
session_start();
include '../koneksi.php'; // Koneksi database

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user = $_SESSION['username'];
    $action = $_POST['action']; // Tindakan yang dilakukan
    $timestamp = date('Y-m-d H:i:s');

    $query = "INSERT INTO activities (user, action, timestamp) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('sss', $user, $action, $timestamp);
    $stmt->execute();
    $stmt->close();
    $conn->close();
}
?>
