<?php
// Pastikan koneksi ke database Anda sudah benar
include "../koneksi.php";

// Pastikan parameter 'field' dan 'query' ada
if (isset($_GET['field']) && isset($_GET['query'])) {
    $field = $_GET['field'];
    $query = $_GET['query'];

    // Validasi input untuk mencegah SQL Injection
    $allowed_fields = ['Nama_Barang', 'Merek', 'Type', 'Lokasi', 'Spesifikasi', 'Kelengkapan_Barang', 'Id_Karyawan', 'Jabatan'];
    if (!in_array($field, $allowed_fields)) {
        echo json_encode(['error' => 'Invalid field']);
        exit();
    }

    // Gunakan prepared statement untuk keamanan
    $sql = "SELECT DISTINCT " . mysqli_real_escape_string($kon, $field) . " FROM peserta WHERE " . mysqli_real_escape_string($kon, $field) . " LIKE ? ORDER BY " . mysqli_real_escape_string($kon, $field) . " ASC LIMIT 50";
    
    $stmt = mysqli_prepare($kon, $sql);
    $search_param = "%" . $query . "%";
    mysqli_stmt_bind_param($stmt, "s", $search_param);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $options = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $options[] = $row[$field];
    }

    echo json_encode($options);
} else {
    echo json_encode(['error' => 'Missing parameters']);
}

// Tutup koneksi
mysqli_close($kon);
?>