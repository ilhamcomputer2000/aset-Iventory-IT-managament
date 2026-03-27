<?php
// Insert test Riwayat_Barang data directly to database for testing
session_start();
include('../koneksi.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['insert_test'])) {
    // Get latest peserta ID
    $sql = "SELECT MAX(ID_Peserta) as max_id FROM peserta";
    $result = mysqli_query($kon, $sql);
    $row = mysqli_fetch_assoc($result);
    $latest_id = $row['max_id'] ?: 1;
    
    // Create test Riwayat_Barang data
    $test_data = [
        [
            'nama' => 'Bambang Irawan',
            'jabatan' => 'Direktur',
            'empleId' => 'ADM-0001',
            'lokasi' => 'Kantor Pusat',
            'tgl_serah_terima' => date('Y-m-d'),
            'tgl_pengembalian' => '',
            'catatan' => 'Test entry'
        ],
        [
            'nama' => 'Siti Nurhaliza',
            'jabatan' => 'Manager IT',
            'empleId' => 'IT-0001',
            'lokasi' => 'Ruang IT',
            'tgl_serah_terima' => date('Y-m-d'),
            'tgl_pengembalian' => '',
            'catatan' => 'Testing riwayat'
        ]
    ];
    
    $json_data = json_encode($test_data);
    
    // Update latest peserta with test Riwayat_Barang
    $update_sql = "UPDATE peserta SET Riwayat_Barang = '$json_data' WHERE ID_Peserta = $latest_id";
    
    if (mysqli_query($kon, $update_sql)) {
        $success_msg = "✓ Test data berhasil diinsert ke ID: $latest_id";
        $test_id = $latest_id;
    } else {
        $error_msg = "❌ Error: " . mysqli_error($kon);
    }
}

// Get all peserta for reference
$all_sql = "SELECT ID_Peserta, Nama_Barang, Riwayat_Barang FROM peserta ORDER BY ID_Peserta DESC LIMIT 10";
$all_result = mysqli_query($kon, $all_sql);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Insert Test Riwayat Data</title>
    <style>
        body { font-family: Arial; margin: 20px; background: #f5f5f5; }
        .box { background: white; padding: 20px; border-radius: 8px; margin: 10px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 4px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px; margin: 10px 0; }
        button { background: #007bff; color: white; padding: 12px 24px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        button:hover { background: #0056b3; }
        a { color: #007bff; text-decoration: none; margin-left: 10px; }
        a:hover { text-decoration: underline; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        th { background: #f5f5f5; }
        .has-data { color: #27ae60; font-weight: bold; }
        .no-data { color: #e74c3c; font-weight: bold; }
        pre { background: #f0f0f0; padding: 10px; border-radius: 4px; overflow-x: auto; font-size: 12px; max-height: 150px; }
    </style>
</head>
<body>
    <div class="box">
        <h1>🧪 Insert Test Riwayat_Barang Data</h1>
        <p>Ini untuk test apakah system bisa display Riwayat_Barang dengan benar</p>
    </div>
    
    <?php if (isset($success_msg)): ?>
        <div class="box success">
            <?php echo $success_msg; ?>
            <br><br>
            <a href="update.php?id_peserta=<?php echo $test_id; ?>" target="_blank">
                <button>Buka Update.php dengan Test Data</button>
            </a>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error_msg)): ?>
        <div class="box error">
            <?php echo $error_msg; ?>
        </div>
    <?php endif; ?>
    
    <div class="box">
        <h2>Langkah 1: Insert Test Data</h2>
        <form method="POST">
            <button type="submit" name="insert_test" value="1">➕ Insert Test Riwayat Data ke Latest Peserta</button>
        </form>
        <p style="margin-top: 15px; font-size: 12px; color: #666;">
            Ini akan menambah sample Riwayat_Barang ke peserta terakhir yang ada
        </p>
    </div>
    
    <div class="box">
        <h2>Langkah 2: Check Database</h2>
        <table>
            <tr>
                <th>ID</th>
                <th>Nama Barang</th>
                <th>Riwayat Status</th>
                <th>Content Preview</th>
                <th>Action</th>
            </tr>
            <?php while ($r = mysqli_fetch_assoc($all_result)): 
                $has_data = !empty($r['Riwayat_Barang']);
                $status = $has_data ? '<span class="has-data">✓ Ada Data</span>' : '<span class="no-data">❌ Kosong</span>';
                $content = $has_data ? htmlspecialchars(substr($r['Riwayat_Barang'], 0, 50)) : '(kosong)';
            ?>
            <tr>
                <td><?php echo $r['ID_Peserta']; ?></td>
                <td><?php echo htmlspecialchars($r['Nama_Barang']); ?></td>
                <td><?php echo $status; ?></td>
                <td><pre><?php echo $content; ?></pre></td>
                <td>
                    <a href="update.php?id_peserta=<?php echo $r['ID_Peserta']; ?>" target="_blank">
                        <button style="padding: 8px 16px; font-size: 14px;">Edit</button>
                    </a>
                </td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>
    
    <div class="box">
        <h2>Langkah 3: Hasil</h2>
        <p>
            ✓ Jika test data berhasil diinsert dan muncul di update.php → <strong>System display bekerja</strong><br>
            ✓ Jika test data ada di database tapi tidak muncul di update.php → <strong>Ada bug di parsing logic</strong><br>
            ✓ Jika test data tidak ada di database → <strong>Ada bug di insertion logic</strong>
        </p>
    </div>
</body>
</html>
