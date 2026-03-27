<?php
// Test script untuk verify Riwayat_Barang data di database
session_start();
include('../koneksi.php');

// Query 10 peserta terbaru
$sql = "SELECT ID_Peserta, Nama_Barang, Serial_Number, Riwayat_Barang, Create_By, Waktu 
        FROM peserta 
        ORDER BY ID_Peserta DESC 
        LIMIT 10";

$result = mysqli_query($kon, $sql);

if (!$result) {
    die("Query error: " . mysqli_error($kon));
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Riwayat Barang Data</title>
    <style>
        body { font-family: Arial; margin: 20px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f5f5f5; }
        .empty { color: red; font-weight: bold; }
        .has-data { color: green; font-weight: bold; }
        pre { background: #f5f5f5; padding: 10px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>Test: Riwayat Barang Data di Database</h1>
    <p>Menampilkan 10 peserta terbaru dan status Riwayat_Barang field</p>
    
    <table>
        <tr>
            <th>ID</th>
            <th>Nama Barang</th>
            <th>Serial Number</th>
            <th>Riwayat Status</th>
            <th>Riwayat Content (first 200 chars)</th>
            <th>Created</th>
        </tr>
        <?php while ($row = mysqli_fetch_assoc($result)) { 
            $riwayat = $row['Riwayat_Barang'];
            $is_empty = empty($riwayat) || trim($riwayat) === '' || $riwayat === '[]';
            $status_class = $is_empty ? 'empty' : 'has-data';
            $status_text = $is_empty ? '❌ EMPTY' : '✓ HAS DATA';
            $content = $is_empty ? '(empty)' : substr($riwayat, 0, 200) . '...';
        ?>
        <tr>
            <td><?php echo $row['ID_Peserta']; ?></td>
            <td><?php echo $row['Nama_Barang']; ?></td>
            <td><?php echo $row['Serial_Number']; ?></td>
            <td class="<?php echo $status_class; ?>"><?php echo $status_text; ?></td>
            <td><pre><?php echo htmlspecialchars($content); ?></pre></td>
            <td><?php echo substr($row['Waktu'], 0, 10); ?></td>
        </tr>
        <?php } ?>
    </table>
    
    <hr>
    <h2>Debug Info:</h2>
    <p>
        <strong>Cara mengecek lebih detail:</strong><br>
        1. Klik salah satu ID di atas<br>
        2. Buka update.php dengan ID tersebut<br>
        3. Buka DevTools (F12) → Console<br>
        4. Lihat apakah Riwayat_Barang data ter-parse sebagai riwayatList array<br>
    </p>
    
</body>
</html>
