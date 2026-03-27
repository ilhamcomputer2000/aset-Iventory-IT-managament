<?php
// One-time normalization script: decode HTML entities in Riwayat_Barang and update DB
// Usage: open this file in browser when ready. It updates all peserta rows where
// the stored Riwayat_Barang contains HTML entities like &quot; or &amp;.

include('../koneksi.php');

$sql = "SELECT ID_Peserta, Riwayat_Barang FROM peserta WHERE Riwayat_Barang IS NOT NULL AND Riwayat_Barang != ''";
$res = mysqli_query($kon, $sql);
$updated = 0;
$errors = [];

while ($row = mysqli_fetch_assoc($res)) {
    $id = $row['ID_Peserta'];
    $raw = $row['Riwayat_Barang'];
    // Detect likely HTML-escaped content
    if (strpos($raw, '&quot;') !== false || strpos($raw, '&amp;') !== false || strpos($raw, '&lt;') !== false) {
        $decoded = html_entity_decode($raw, ENT_QUOTES, 'UTF-8');
        // Validate JSON
        $parsed = json_decode($decoded, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
            $safe = mysqli_real_escape_string($kon, $decoded);
            $u = "UPDATE peserta SET Riwayat_Barang = '$safe' WHERE ID_Peserta = $id";
            if (mysqli_query($kon, $u)) {
                $updated++;
            } else {
                $errors[] = "ID $id update failed: " . mysqli_error($kon);
            }
        } else {
            // If decode didn't produce valid JSON, skip
            $errors[] = "ID $id decoded but not valid JSON: " . json_last_error_msg();
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <title>Normalize Riwayat_Barang</title>
    <style>body{font-family:Arial;margin:20px;} .ok{color:green;} .err{color:#c0392b;}</style>
</head>
<body>
    <h1>Normalize Riwayat_Barang</h1>
    <p>Updated rows: <strong class="ok"><?php echo $updated; ?></strong></p>
    <?php if (!empty($errors)): ?>
        <h3>Errors / Skipped:</h3>
        <ul>
            <?php foreach ($errors as $e): ?>
                <li class="err"><?php echo htmlspecialchars($e); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No errors.</p>
    <?php endif; ?>
    <p>After running, open the relevant <code>update.php?id_peserta=...</code> to verify entries display.</p>
</body>
</html>
