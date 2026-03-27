<?php
require 'koneksi.php';
header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

if ($action === 'get_by_name') {
    $nama = $_GET['nama'] ?? '';
    if (empty(trim($nama))) {
        echo json_encode(['ok' => false]);
        exit;
    }
    // Query users where Nama_Lengkap = ?
    $stmt = $kon->prepare("SELECT username, Jabatan_Level, Region FROM users WHERE Nama_Lengkap = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('s', $nama);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            echo json_encode([
                'ok' => true, 
                'employee_id' => $row['username'], 
                'jabatan' => $row['Jabatan_Level'], 
                'lokasi' => $row['Region']
            ]);
        } else {
            echo json_encode(['ok' => false]);
        }
    } else {
        echo json_encode(['ok' => false, 'error' => $kon->error]);
    }
} elseif ($action === 'get_by_id') {
    $id = $_GET['id'] ?? '';
    if (empty(trim($id))) {
        echo json_encode(['ok' => false]);
        exit;
    }
    // Query users where username = ?
    $stmt = $kon->prepare("SELECT Nama_Lengkap, Jabatan_Level, Region FROM users WHERE username = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('s', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            echo json_encode([
                'ok' => true, 
                'nama' => $row['Nama_Lengkap'], 
                'jabatan' => $row['Jabatan_Level'], 
                'lokasi' => $row['Region']
            ]);
        } else {
            echo json_encode(['ok' => false]);
        }
    } else {
        echo json_encode(['ok' => false, 'error' => $kon->error]);
    }
} else {
    echo json_encode(['ok' => false, 'error' => 'Invalid action']);
}
?>
