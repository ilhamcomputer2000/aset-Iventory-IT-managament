<?php
// api_get_brands.php - API untuk mendapatkan Merek berdasarkan Kategori (Nama_Barang)

// Tangkap error PHP untuk debug
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

// Koneksi database
include "../koneksi.php";

// Check connection
if (!$kon) {
    http_response_code(500);
    die(json_encode(['success' => false, 'error' => 'Database connection failed: ' . (isset($kon) ? mysqli_error($kon) : 'Unknown error')]));
}

// Validasi request
if (!isset($_GET['kategori'])) {
    http_response_code(400);
    die(json_encode(['success' => false, 'error' => 'Parameter kategori diperlukan']));
}

$kategori = trim($_GET['kategori']);

// Build query
$brands_query = "SELECT DISTINCT Merek FROM peserta WHERE Merek != '' AND Merek IS NOT NULL";
if (!empty($kategori)) {
    $brands_query .= " AND Nama_Barang = '" . mysqli_real_escape_string($kon, $kategori) . "'";
}
$brands_query .= " ORDER BY Merek";

error_log("DEBUG: Executing query: " . $brands_query);

// Execute query
$brands_result = mysqli_query($kon, $brands_query);

if (!$brands_result) {
    http_response_code(500);
    die(json_encode(['success' => false, 'error' => 'Database query failed: ' . mysqli_error($kon)]));
}

// Build response
$brands = [];
if (mysqli_num_rows($brands_result) > 0) {
    while ($row = mysqli_fetch_assoc($brands_result)) {
        if (!empty($row['Merek'])) {
            $brands[] = $row['Merek'];
        }
    }
}

error_log("DEBUG: Found " . count($brands) . " brands for kategori: " . $kategori);

// Return JSON
echo json_encode([
    'success' => true,
    'brands' => $brands,
    'debug' => [
        'kategori' => $kategori,
        'count' => count($brands)
    ]
]);
?>

