<?php
session_start();
include "../koneksi.php";

// Copy exact logic dari create.php
function getOptions($kon, $field) {
    $options = [];
    $query = "SELECT DISTINCT $field FROM peserta WHERE $field IS NOT NULL AND $field != '' ORDER BY $field ASC";
    $result = mysqli_query($kon, $query);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $options[] = $row[$field];
        }
    }
    return $options;
}

$namaBarangOptions = getOptions($kon, 'Nama_Barang');
$merekOptions = getOptions($kon, 'Merek');
$typeOptions = getOptions($kon, 'Type');
$lokasiOptions = getOptions($kon, 'Lokasi');
$idKaryawanOptions = getOptions($kon, 'Id_Karyawan');
$jabatanOptions = getOptions($kon, 'Jabatan');

// User_Perangkat
$query_user_perangkat = "SELECT DISTINCT User_Perangkat FROM peserta WHERE User_Perangkat IS NOT NULL AND User_Perangkat != '' ORDER BY User_Perangkat ASC";
$result_user_perangkat = mysqli_query($kon, $query_user_perangkat);
$user_perangkat_list = [];
if ($result_user_perangkat) {
    while ($row = mysqli_fetch_assoc($result_user_perangkat)) {
        if (!empty($row['User_Perangkat'])) {
            $user_perangkat_list[] = $row['User_Perangkat'];
        }
    }
}

// JSON encode
$jabatanJson = json_encode($jabatanOptions);
$idKaryawanJson = json_encode($idKaryawanOptions);
$lokasiJson = json_encode($lokasiOptions);
$userPerangkatJson = json_encode($user_perangkat_list);

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Test Create.php Riwayat Data</title>
    <style>
        body { font-family: Arial; margin: 20px; background: #f5f5f5; }
        .box { background: white; padding: 15px; margin: 10px 0; border-radius: 5px; border-left: 4px solid #2563eb; }
        .empty { border-left-color: #ef4444; color: #dc2626; }
        .has-data { border-left-color: #16a34a; color: #16a34a; }
        pre { background: #f3f4f6; padding: 10px; overflow-x: auto; border-radius: 4px; font-size: 12px; max-height: 300px; overflow-y: auto; }
        h1 { color: #1f2937; }
        .summary { font-weight: bold; font-size: 18px; }
    </style>
</head>
<body>
    <h1>🔍 Test CREATE.php Riwayat Data</h1>
    
    <div class="box <?php echo empty($user_perangkat_list) ? 'empty' : 'has-data'; ?>">
        <strong class="summary">User_Perangkat (Nama Tangan Pertama):</strong> 
        <span style="color: #2563eb; font-size: 16px;"><?php echo count($user_perangkat_list); ?> records</span>
        <pre><?php echo json_encode($user_perangkat_list, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); ?></pre>
    </div>

    <div class="box <?php echo empty($jabatanOptions) ? 'empty' : 'has-data'; ?>">
        <strong class="summary">Jabatan:</strong> 
        <span style="color: #2563eb; font-size: 16px;"><?php echo count($jabatanOptions); ?> records</span>
        <pre><?php echo json_encode($jabatanOptions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); ?></pre>
    </div>

    <div class="box <?php echo empty($idKaryawanOptions) ? 'empty' : 'has-data'; ?>">
        <strong class="summary">Id_Karyawan (Employee ID):</strong> 
        <span style="color: #2563eb; font-size: 16px;"><?php echo count($idKaryawanOptions); ?> records</span>
        <pre><?php echo json_encode($idKaryawanJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); ?></pre>
    </div>

    <div class="box <?php echo empty($lokasiOptions) ? 'empty' : 'has-data'; ?>">
        <strong class="summary">Lokasi:</strong> 
        <span style="color: #2563eb; font-size: 16px;"><?php echo count($lokasiOptions); ?> records</span>
        <pre><?php echo json_encode($lokasiOptions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); ?></pre>
    </div>

    <div class="box">
        <strong class="summary">JavaScript akan terima:</strong><br>
        <small style="color: #666;">Paste ini di browser console untuk test:</small>
        <pre>
// Test User_Perangkat
const userPerangkatData = <?php echo $userPerangkatJson; ?>;
console.log('User_Perangkat:', userPerangkatData);

// Test Jabatan
const jabatanData = <?php echo $jabatanJson; ?>;
console.log('Jabatan:', jabatanData);

// Test Employee ID
const idKaryawanData = <?php echo $idKaryawanJson; ?>;
console.log('Employee ID:', idKaryawanData);

// Test Lokasi
const lokasiData = <?php echo $lokasiJson; ?>;
console.log('Lokasi:', lokasiData);
        </pre>
    </div>
</body>
</html>
