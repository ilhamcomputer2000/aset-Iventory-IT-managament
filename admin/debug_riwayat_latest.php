<?php
// DIAGNOSTIC: Check Riwayat_Barang di database
session_start();
include('../koneksi.php');

// Get latest peserta
$sql = "SELECT ID_Peserta, Nama_Barang, Serial_Number, Riwayat_Barang FROM peserta ORDER BY ID_Peserta DESC LIMIT 1";
$result = mysqli_query($kon, $sql);
$row = mysqli_fetch_assoc($result);

header('Content-Type: application/json');

if (!$row) {
    echo json_encode(['error' => 'Tidak ada data peserta', 'database_status' => 'Connected']);
    exit;
}

$riwayat_raw = $row['Riwayat_Barang'];
$is_empty = empty($riwayat_raw) || trim($riwayat_raw) === '';
$is_json_array_empty = $riwayat_raw === '[]';

// Try parse JSON
$decoded = null;
if (!$is_empty) {
    $decoded = json_decode($riwayat_raw, true);
}

echo json_encode([
    'id_peserta' => $row['ID_Peserta'],
    'nama_barang' => $row['Nama_Barang'],
    'serial_number' => $row['Serial_Number'],
    'riwayat_raw' => $riwayat_raw,
    'riwayat_length' => strlen($riwayat_raw),
    'is_empty_string' => $is_empty,
    'is_json_array_empty' => $is_json_array_empty,
    'decoded_json' => $decoded,
    'json_last_error' => json_last_error_msg(),
    'first_50_chars' => substr($riwayat_raw, 0, 50)
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>
