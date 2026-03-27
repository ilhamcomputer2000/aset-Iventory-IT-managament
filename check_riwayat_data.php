<?php
include 'koneksi.php';

// Get beberapa data peserta
$result = mysqli_query($kon, "SELECT id_peserta, Nama_Barang, Riwayat_Barang FROM peserta LIMIT 10");

echo "<h2>Checking Riwayat_Barang in Database</h2>";
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>ID Peserta</th><th>Nama Barang</th><th>Riwayat_Barang</th><th>Length</th></tr>";

$count = 0;
while ($row = mysqli_fetch_assoc($result)) {
    $count++;
    $riwayat = $row['Riwayat_Barang'];
    $length = strlen($riwayat ?? '');
    echo "<tr>";
    echo "<td>" . $row['id_peserta'] . "</td>";
    echo "<td>" . $row['Nama_Barang'] . "</td>";
    echo "<td>";
    if (empty($riwayat)) {
        echo "<span style='color: red;'>[KOSONG/NULL]</span>";
    } else {
        echo "<pre>" . htmlspecialchars(substr($riwayat, 0, 200)) . (strlen($riwayat) > 200 ? "..." : "") . "</pre>";
    }
    echo "</td>";
    echo "<td>" . $length . " chars</td>";
    echo "</tr>";
}
echo "</table>";
echo "<p>Total rows checked: $count</p>";

mysqli_close($kon);
?>
