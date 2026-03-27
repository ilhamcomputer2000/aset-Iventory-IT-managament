<?php
// File API untuk mengambil daftar tiket pengguna
header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *"); // Mengizinkan akses dari aplikasi mobile (Flutter)

require_once __DIR__ . '/koneksi.php';

// Pastikan request menggunakan method GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
    exit();
}

try {
    // Parameter opsional: id_karyawan
    $idKaryawan = isset($_GET['id_karyawan']) ? $_GET['id_karyawan'] : '';
    
    // Dasar query
    $sql = "SELECT `Ticket_code`, `Subject`, `Kategori_Masalah`, `Priority`, `Status_Request`, `Type_Pekerjaan`, `Create_User`, `Create_By_User`, `Divisi_User`, `Jabatan_User`, `Region`, `Deskripsi_Masalah`, `Foto_Ticket`, `Document`, `Jawaban_IT`, `Photo_IT` FROM `ticket`";
    
    // Menyiapkan parameter jika user id dikirimkan
    $params = [];
    $types = '';
    
    if ($idKaryawan !== '') {
        $sql .= " WHERE `Id_Karyawan` = ?";
        $params[] = $idKaryawan;
        $types .= 's'; // 's' berarti string (meskipun ID bisa berupa angka, string lebih fleksibel jika memuat karakter khusus)
    }
    
    // Urutkan dari yang terbaru
    $sql .= " ORDER BY `Ticket_code` DESC LIMIT 50";
    
    $stmt = $kon->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare statement failed: " . $kon->error);
    }
    
    // Bind parameter jika ada
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    if (!$stmt->execute()) {
        throw new Exception("Query failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $tickets = [];
    
    // Looping data
    while ($row = $result->fetch_assoc()) {
        // Manipulasi sedikit data jika perlu, misalnya format URL gambar The upload directory
        if (!empty($row['Foto_Ticket'])) {
            // Asumsi hostnya localhost dan foldernya crud. Akan lebih baik jika dibuat dinamis.
            $row['Foto_Url'] = "http://localhost/crud/uploads/ticket/" . $row['Foto_Ticket'];
        }
        $tickets[] = $row;
    }
    
    $stmt->close();
    
    // Return berhasil dengan JSON
    echo json_encode([
        'status' => 'success',
        'message' => 'Data tiket berhasil diambil',
        'total_data' => count($tickets),
        'data' => $tickets
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
