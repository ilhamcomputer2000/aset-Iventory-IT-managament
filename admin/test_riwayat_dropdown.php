<?php
session_start();
include "../koneksi.php";

// Get existing options from database
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

$jabatanOptions = getOptions($kon, 'Jabatan');
$idKaryawanOptions = getOptions($kon, 'Id_Karyawan');
$lokasiOptions = getOptions($kon, 'Lokasi');

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

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Test Riwayat Dropdown Data</title>
    <style>
        body { font-family: Arial; margin: 20px; background: #f5f5f5; }
        .box { background: white; padding: 15px; margin: 10px 0; border-radius: 5px; border-left: 4px solid #2563eb; }
        .empty { border-left-color: #ef4444; color: #dc2626; }
        .has-data { border-left-color: #16a34a; color: #16a34a; }
        pre { background: #f3f4f6; padding: 10px; overflow-x: auto; border-radius: 4px; }
        h1 { color: #1f2937; }
    </style>
</head>
<body>
    <h1>🔍 Test Riwayat Dropdown Data</h1>
    
    <div class="box <?php echo empty($user_perangkat_list) ? 'empty' : 'has-data'; ?>">
        <strong>User_Perangkat (Nama Tangan Pertama):</strong> 
        <?php echo count($user_perangkat_list); ?> records
        <pre><?php echo json_encode($user_perangkat_list, JSON_PRETTY_PRINT); ?></pre>
    </div>

    <div class="box <?php echo empty($jabatanOptions) ? 'empty' : 'has-data'; ?>">
        <strong>Jabatan:</strong> 
        <?php echo count($jabatanOptions); ?> records
        <pre><?php echo json_encode($jabatanOptions, JSON_PRETTY_PRINT); ?></pre>
    </div>

    <div class="box <?php echo empty($idKaryawanOptions) ? 'empty' : 'has-data'; ?>">
        <strong>Id_Karyawan (Employee ID):</strong> 
        <?php echo count($idKaryawanOptions); ?> records
        <pre><?php echo json_encode($idKaryawanOptions, JSON_PRETTY_PRINT); ?></pre>
    </div>

    <div class="box <?php echo empty($lokasiOptions) ? 'empty' : 'has-data'; ?>">
        <strong>Lokasi:</strong> 
        <?php echo count($lokasiOptions); ?> records
        <pre><?php echo json_encode($lokasiOptions, JSON_PRETTY_PRINT); ?></pre>
    </div>

    <div class="box">
        <strong>Summary:</strong><br>
        Total Peserta di Database: 
        <?php 
        $count_query = "SELECT COUNT(*) as total FROM peserta";
        $count_result = mysqli_query($kon, $count_query);
        $count_row = mysqli_fetch_assoc($count_result);
        echo $count_row['total'];
        ?>
    </div>
</body>
</html>
