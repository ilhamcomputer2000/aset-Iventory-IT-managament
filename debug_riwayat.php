<?php
include 'koneksi.php';

$id_peserta = isset($_GET['id']) ? $_GET['id'] : 586;

$result = mysqli_query($kon, "SELECT id_peserta, Riwayat_Barang FROM peserta WHERE id_peserta = '$id_peserta' LIMIT 1");
$row = mysqli_fetch_assoc($result);

echo "<pre>";
if ($row) {
    echo "id_peserta: " . $row['id_peserta'] . "\n";
    echo "Riwayat_Barang value: [" . $row['Riwayat_Barang'] . "]\n";
    echo "Riwayat_Barang is NULL: " . ($row['Riwayat_Barang'] === null ? 'YES' : 'NO') . "\n";
    echo "Riwayat_Barang is empty: " . (empty($row['Riwayat_Barang']) ? 'YES' : 'NO') . "\n";
    echo "Riwayat_Barang length: " . strlen($row['Riwayat_Barang'] ?? '') . "\n";
    echo "\nRawData:\n";
    var_dump($row['Riwayat_Barang']);
} else {
    echo "Data not found for id_peserta $id_peserta\n";
}
echo "</pre>";
?>
