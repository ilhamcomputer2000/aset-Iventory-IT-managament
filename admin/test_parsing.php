<?php
// Simple test - load update.php dengan specific ID dan check database
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id === 0) {
    die('Please provide ?id=XXX parameter');
}

session_start();
include('../koneksi.php');

$sql = "SELECT ID_Peserta, Nama_Barang, Riwayat_Barang FROM peserta WHERE ID_Peserta = $id LIMIT 1";
$result = mysqli_query($kon, $sql);

if (!$result || mysqli_num_rows($result) === 0) {
    die("ID tidak ditemukan");
}

$row = mysqli_fetch_assoc($result);
$riwayat = $row['Riwayat_Barang'];

?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Riwayat Parsing</title>
    <style>
        body { font-family: Arial; margin: 20px; }
        .debug { background: #f5f5f5; border: 1px solid #ccc; padding: 10px; margin: 10px 0; }
        pre { overflow-x: auto; }
    </style>
</head>
<body>
    <h1>Debug Riwayat_Barang untuk ID: <?php echo $id; ?></h1>
    
    <div class="debug">
        <h3>Database Info:</h3>
        <p><strong>Nama Barang:</strong> <?php echo $row['Nama_Barang']; ?></p>
        <p><strong>Riwayat_Barang Raw Value:</strong></p>
        <pre><?php echo htmlspecialchars($riwayat); ?></pre>
        <p><strong>Length:</strong> <?php echo strlen($riwayat); ?> characters</p>
        <p><strong>Is Empty?</strong> <?php echo (empty($riwayat) ? 'YES (PROBLEM!)' : 'NO (has data)'); ?></p>
    </div>
    
    <div class="debug">
        <h3>Parsing Test:</h3>
        <pre><?php 
            if (!empty($riwayat)) {
                $parsed = json_decode($riwayat, true);
                echo "JSON Decoded:\n";
                var_export($parsed);
                echo "\n\nJSON Error: " . json_last_error_msg();
            } else {
                echo "Riwayat is empty - cannot parse";
            }
        ?></pre>
    </div>
    
    <div class="debug">
        <h3>Next Step:</h3>
        <p>Open <a href="update.php?id_peserta=<?php echo $id; ?>" target="_blank">update.php?id_peserta=<?php echo $id; ?></a></p>
        <p>Then open DevTools (F12) → Console tab and check the logs starting with "=== RIWAYAT_BARANG DEBUG ==="</p>
    </div>
</body>
</html>
