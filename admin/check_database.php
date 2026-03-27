<?php
// Check database directly - no session required for debugging
include('../koneksi.php');

// Get latest 5 peserta
$sql = "SELECT ID_Peserta, Nama_Barang, Riwayat_Barang FROM peserta ORDER BY ID_Peserta DESC LIMIT 5";
$result = mysqli_query($kon, $sql);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Database Riwayat Check</title>
    <style>
        body { font-family: Arial; margin: 20px; background: #f5f5f5; }
        .container { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        th { background: #3498db; color: white; }
        tr:nth-child(even) { background: #f9f9f9; }
        .empty { color: #e74c3c; font-weight: bold; }
        .has-data { color: #27ae60; font-weight: bold; }
        .json-content { background: #f0f0f0; padding: 8px; border-radius: 4px; font-family: monospace; font-size: 12px; max-height: 200px; overflow-y: auto; word-break: break-all; }
        .button { background: #3498db; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; }
        .button:hover { background: #2980b9; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Database Riwayat_Barang Check</h1>
        
        <table>
            <tr>
                <th>ID</th>
                <th>Nama Barang</th>
                <th>Status</th>
                <th>Content (first 100 chars)</th>
                <th>Action</th>
            </tr>
            <?php while ($row = mysqli_fetch_assoc($result)) {
                $riwayat = $row['Riwayat_Barang'];
                $is_empty = empty($riwayat) || trim($riwayat) === '';
                $status = $is_empty ? '<span class="empty">❌ KOSONG</span>' : '<span class="has-data">✓ ADA DATA</span>';
                $content = $is_empty ? '(empty)' : htmlspecialchars(substr($riwayat, 0, 100));
            ?>
            <tr>
                <td><strong><?php echo $row['ID_Peserta']; ?></strong></td>
                <td><?php echo htmlspecialchars($row['Nama_Barang']); ?></td>
                <td><?php echo $status; ?></td>
                <td><pre class="json-content"><?php echo $content; ?></pre></td>
                <td>
                    <a href="test_parsing.php?id=<?php echo $row['ID_Peserta']; ?>" class="button">Test</a>
                    <a href="update.php?id_peserta=<?php echo $row['ID_Peserta']; ?>" class="button">Edit</a>
                </td>
            </tr>
            <?php } ?>
        </table>
        
        <h2>📋 Analisis:</h2>
        <?php 
            // Count kosong vs ada data
            $sql_count = "SELECT 
                SUM(CASE WHEN Riwayat_Barang IS NULL OR Riwayat_Barang = '' THEN 1 ELSE 0 END) as kosong,
                SUM(CASE WHEN Riwayat_Barang IS NOT NULL AND Riwayat_Barang != '' THEN 1 ELSE 0 END) as ada_data
            FROM peserta";
            $count_result = mysqli_query($kon, $sql_count);
            $count = mysqli_fetch_assoc($count_result);
        ?>
        <p>
            <strong>Total Peserta:</strong> <?php echo ($count['kosong'] + $count['ada_data']); ?><br>
            <strong>Dengan Riwayat_Barang:</strong> <span class="has-data"><?php echo $count['ada_data'] ?: 0; ?></span><br>
            <strong>Tanpa Riwayat_Barang:</strong> <span class="empty"><?php echo $count['kosong'] ?: 0; ?></span>
        </p>
        
        <h2>🔧 Diagnosis:</h2>
        <?php if ($count['ada_data'] == 0): ?>
            <p style="color: #e74c3c; font-weight: bold;">
                ⚠️ PROBLEM DITEMUKAN: Tidak ada peserta dengan data Riwayat_Barang!
            </p>
            <p>
                Ini berarti:<br>
                1. Form create.php tidak menyimpan Riwayat_Barang ke database<br>
                2. ATAU form tidak mengupdate textarea sebelum submit<br>
                3. ATAU INSERT query tidak include Riwayat_Barang field
            </p>
        <?php else: ?>
            <p style="color: #27ae60; font-weight: bold;">
                ✓ Data ada di database! Klik tombol "Test" untuk debug lebih lanjut.
            </p>
        <?php endif; ?>
    </div>
</body>
</html>
