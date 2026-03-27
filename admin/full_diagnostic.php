<?php
// Full diagnostic - check database, parsing, everything
session_start();
include('../koneksi.php');

// Get latest peserta
$sql = "SELECT ID_Peserta, Nama_Barang, Riwayat_Barang FROM peserta ORDER BY ID_Peserta DESC LIMIT 1";
$result = mysqli_query($kon, $sql);
$row = mysqli_fetch_assoc($result);

if (!$row) {
    die("Tidak ada data peserta");
}

$id = $row['ID_Peserta'];
$riwayat_raw = $row['Riwayat_Barang'];

?>
<!DOCTYPE html>
<html>
<head>
    <title>Full Diagnostic</title>
    <style>
        body { font-family: Arial; margin: 20px; background: #f5f5f5; }
        .box { background: white; padding: 20px; margin: 10px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .title { font-size: 18px; font-weight: bold; margin: 15px 0 10px 0; }
        .status { padding: 10px; border-radius: 4px; margin: 10px 0; }
        .ok { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
        pre { background: #f0f0f0; padding: 15px; border-radius: 4px; overflow-x: auto; font-size: 12px; }
        code { background: #f0f0f0; padding: 2px 6px; border-radius: 3px; }
        a { color: #0066cc; text-decoration: none; }
        a:hover { text-decoration: underline; }
        button { background: #0066cc; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #0052a3; }
    </style>
</head>
<body>
    <div class="box">
        <h1>🔍 Full Diagnostic Riwayat_Barang</h1>
        <p>Latest ID: <strong><?php echo $id; ?></strong></p>
    </div>
    
    <div class="box">
        <div class="title">1. Database Level Check</div>
        <p><strong>Raw Value dari Database:</strong></p>
        <pre><?php 
            echo "Value: " . var_export($riwayat_raw, true) . "\n\n";
            echo "Length: " . strlen($riwayat_raw) . " characters\n";
            echo "Is NULL: " . (is_null($riwayat_raw) ? "YES" : "NO") . "\n";
            echo "Is Empty: " . (empty($riwayat_raw) ? "YES" : "NO") . "\n";
            echo "Trimmed: '" . trim($riwayat_raw) . "'\n";
        ?></pre>
        
        <?php if (empty($riwayat_raw)): ?>
            <div class="status error">
                ❌ PROBLEM: Riwayat_Barang di database KOSONG!
                <br><strong>Root Cause:</strong> Form create.php tidak menyimpan data
                <br><strong>Solution:</strong> Perlu check form submission di create.php
            </div>
        <?php else: ?>
            <div class="status ok">
                ✓ Database memiliki data Riwayat_Barang
            </div>
        <?php endif; ?>
    </div>
    
    <div class="box">
        <div class="title">2. JSON Parsing Check</div>
        <pre><?php 
            if (empty($riwayat_raw)) {
                echo "(Data kosong, tidak bisa parse)";
            } else {
                $decoded = json_decode($riwayat_raw, true);
                echo "Decoded Result:\n";
                var_export($decoded);
                echo "\n\nJSON Error: " . json_last_error_msg();
                echo "\nIs Array: " . (is_array($decoded) ? "YES" : "NO");
                echo "\nArray Count: " . (is_array($decoded) ? count($decoded) : "N/A");
            }
        ?></pre>
    </div>
    
    <div class="box">
        <div class="title">3. JavaScript Parsing Simulation</div>
        <pre><?php
            if (!empty($riwayat_raw)) {
                echo "JavaScript akan menerima string:\n";
                echo htmlspecialchars($riwayat_raw) . "\n\n";
                echo "Parsing logic:\n";
                echo "const existingRiwayat = $('#Riwayat_Barang').val().trim();\n";
                echo "// existingRiwayat = '" . htmlspecialchars($riwayat_raw) . "'\n";
                echo "const parsed = JSON.parse(existingRiwayat);\n";
                echo "// parsed length: " . count(json_decode($riwayat_raw, true)) . "\n";
            } else {
                echo "Tidak ada data untuk parse";
            }
        ?></pre>
    </div>
    
    <div class="box">
        <div class="title">4. Next Actions</div>
        <?php if (empty($riwayat_raw)): ?>
            <p>🔴 <strong>ISSUE CONFIRMED:</strong> Database tidak ada Riwayat_Barang data</p>
            <p><strong>Debug Steps:</strong></p>
            <ol>
                <li>Buka <code>create.php</code> di browser</li>
                <li>Buka DevTools (F12) → Console tab</li>
                <li>Isi form lengkap + tambah 1 riwayat entry</li>
                <li>Sebelum submit, check console untuk lihat: <code>riwayatList</code> dan <code>$('#Riwayat_Barang').val()</code></li>
                <li>Submit form</li>
                <li>Check console log pada submit event untuk lihat: <code>Form Submit - Final Riwayat_Barang:</code></li>
                <li>Refresh halaman ini untuk check database</li>
            </ol>
        <?php else: ?>
            <p>✓ <strong>Data ada di database!</strong></p>
            <p>Buka update.php untuk check display:</p>
            <p><a href="update.php?id_peserta=<?php echo $id; ?>"><button>Buka Update dengan ID <?php echo $id; ?></button></a></p>
            <p>Setelah terbuka, buka DevTools (F12) → Console untuk lihat debug logs yang dimulai dengan <code>=== RIWAYAT_BARANG DEBUG ===</code></p>
        <?php endif; ?>
    </div>
    
</body>
</html>
