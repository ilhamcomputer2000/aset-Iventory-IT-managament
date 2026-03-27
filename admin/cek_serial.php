<?php
header('Content-Type: application/json');

// Include file koneksi
include "../koneksi.php";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['serial'])) {
    $serial = trim($_POST['serial']);
    
    if (empty($serial)) {
        echo json_encode(['exists' => false]);
        exit;
    }

    // Escape string untuk keamanan
    $serial = mysqli_real_escape_string($kon, $serial);
    
    // Query untuk cek apakah serial number sudah ada
    $query = "SELECT COUNT(*) as total FROM peserta WHERE Serial_Number = '$serial'";
    $result = mysqli_query($kon, $query);
    
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        $exists = $row['total'] > 0;
        echo json_encode(['exists' => $exists]);
    } else {
        echo json_encode(['exists' => false, 'error' => 'Database error']);
    }
} else {
    echo json_encode(['exists' => false, 'error' => 'Invalid request']);
}
?>