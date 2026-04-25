<?php
session_start();
require_once __DIR__ . '/../koneksi.php';

// Check if user is admin
$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : 'user';
if ($user_role !== 'super_admin') {
    die('Unauthorized access.');
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=Data_Finalis_Event_' . date('Y-m-d_H-i-s') . '.csv');

$output = fopen('php://output', 'w');

// Add BOM for Excel to properly read UTF-8 characters
fputs($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// Write headers (using semicolon for Indonesian Excel compatibility)
fputcsv($output, [
    'No Finalis', 
    'Nama Lengkap', 
    'Jenis Kelamin', 
    'Kategori', 
    'Umur', 
    'Kota', 
    'Nama PIC', 
    'No WhatsApp', 
    'Catatan Materi Perform', 
    'Status Pendaftaran', 
    'Waktu Daftar'
], ';');

$sql = "SELECT * FROM event_finalis ORDER BY created_at DESC";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['no_finalis'],
            $row['nama_lengkap'],
            $row['jenis_kelamin'],
            $row['kategori'],
            $row['umur'],
            $row['kota'],
            $row['nama_pic'],
            $row['no_wa'],
            $row['catatan_materi'],
            $row['status_pendaftaran'],
            $row['created_at']
        ], ';');
    }
}

fclose($output);
exit;
?>
