<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit();
}

$user_role = (string)($_SESSION['role'] ?? 'super_admin');
if ($user_role !== 'super_admin') {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit();
}

// Lepas session lock supaya request AJAX lain tidak ke-block.
// Endpoint ini hanya membaca session (user_id/role/username).
session_write_close();

require_once __DIR__ . '/../koneksi.php';

date_default_timezone_set('Asia/Jakarta');

function json_out(array $payload, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit();
}

function read_post(string $key): string {
    return isset($_POST[$key]) ? trim((string)$_POST[$key]) : '';
}

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$action = '';
if ($method === 'GET') {
    $action = isset($_GET['action']) ? trim((string)$_GET['action']) : '';
} else {
    $action = read_post('action');
}

if ($action === 'get' && $method === 'GET') {
    $id = isset($_GET['id_peserta']) ? (int)$_GET['id_peserta'] : 0;
    if ($id <= 0) {
        json_out(['ok' => false, 'error' => 'Invalid id_peserta'], 400);
    }

    $sql = 'SELECT id_peserta, Nama_Barang, Nomor_Aset, Serial_Number, User_Perangkat, Jabatan, Id_Karyawan, Lokasi, Riwayat_Barang FROM peserta WHERE id_peserta = ? LIMIT 1';
    $stmt = $kon->prepare($sql);
    if (!$stmt) {
        json_out(['ok' => false, 'error' => 'Prepare failed: ' . $kon->error], 500);
    }

    $stmt->bind_param('i', $id);
    if (!$stmt->execute()) {
        $stmt->close();
        json_out(['ok' => false, 'error' => 'Execute failed'], 500);
    }

    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
        json_out(['ok' => false, 'error' => 'Asset not found'], 404);
    }

    $riwayatRaw = (string)($row['Riwayat_Barang'] ?? '');
    if ($riwayatRaw !== '') {
        $riwayatRaw = html_entity_decode($riwayatRaw, ENT_QUOTES, 'UTF-8');
    }

    $riwayatList = [];
    if ($riwayatRaw !== '') {
        $decoded = json_decode($riwayatRaw, true);
        if (is_array($decoded)) {
            $riwayatList = $decoded;
        }
    }

    json_out([
        'ok' => true,
        'asset' => [
            'id_peserta' => (int)($row['id_peserta'] ?? 0),
            'Nama_Barang' => (string)($row['Nama_Barang'] ?? ''),
            'Nomor_Aset' => (string)($row['Nomor_Aset'] ?? ''),
            'Serial_Number' => (string)($row['Serial_Number'] ?? ''),
            'User_Perangkat' => (string)($row['User_Perangkat'] ?? ''),
            'Jabatan' => (string)($row['Jabatan'] ?? ''),
            'Id_Karyawan' => (string)($row['Id_Karyawan'] ?? ''),
            'Lokasi' => (string)($row['Lokasi'] ?? ''),
        ],
        'riwayat' => $riwayatList,
    ]);
}

if ($action === 'transfer' && $method === 'POST') {
    $id = (int)read_post('id_peserta');
    if ($id <= 0) {
        json_out(['ok' => false, 'error' => 'Invalid id_peserta'], 400);
    }

    $action_type = strtoupper(read_post('action_type'));
    if ($action_type !== 'TRANSFER' && $action_type !== 'RETURN') {
        $action_type = 'TRANSFER';
    }

    $nama = read_post('nama');
    $jabatan = read_post('jabatan');
    $empleId = read_post('empleId');
    $lokasi = read_post('lokasi');
    $tglSerah = read_post('tgl_serah_terima');
    $tglKembali = read_post('tgl_pengembalian');
    $catatan = read_post('catatan');

    if ($action_type === 'TRANSFER' && ($nama === '' || $jabatan === '' || $empleId === '' || $lokasi === '' || $tglSerah === '')) {
        json_out(['ok' => false, 'error' => 'Field wajib: nama, jabatan, empleId, lokasi, tgl_serah_terima untuk transfer.'], 422);
    }

    // Ambil data asset existing
    $stmtSel = $kon->prepare('SELECT User_Perangkat, Jabatan, Id_Karyawan, Lokasi, Status_Barang, Riwayat_Barang FROM peserta WHERE id_peserta = ? LIMIT 1');
    if (!$stmtSel) {
        json_out(['ok' => false, 'error' => 'Prepare failed: ' . $kon->error], 500);
    }
    $stmtSel->bind_param('i', $id);
    if (!$stmtSel->execute()) {
        $stmtSel->close();
        json_out(['ok' => false, 'error' => 'Execute failed'], 500);
    }

    $resSel = $stmtSel->get_result();
    $rowSel = $resSel ? $resSel->fetch_assoc() : null;
    $stmtSel->close();

    if (!$rowSel) {
        json_out(['ok' => false, 'error' => 'Asset not found'], 404);
    }

    $riwayatRaw = (string)($rowSel['Riwayat_Barang'] ?? '');
    if ($riwayatRaw !== '') {
        $riwayatRaw = html_entity_decode($riwayatRaw, ENT_QUOTES, 'UTF-8');
    }

    $riwayatList = [];
    if ($riwayatRaw !== '') {
        $decoded = json_decode($riwayatRaw, true);
        if (is_array($decoded)) {
            $riwayatList = $decoded;
        }
    }

    $current_status = strtoupper((string)($rowSel['Status_Barang'] ?? ''));
    $new_status = $current_status;

    if ($action_type === 'TRANSFER') {
        $new_status = 'IN USE';
        
        $newEntry = [
            'nama' => $nama,
            'jabatan' => $jabatan,
            'empleId' => $empleId,
            'lokasi' => $lokasi,
            'tgl_serah_terima' => $tglSerah,
            'tgl_pengembalian' => $tglKembali,
            'catatan' => $catatan,
            'aksi' => $action_type,
            'created_by' => (string)($_SESSION['username'] ?? ''),
            'created_at' => date('Y-m-d H:i:s'),
        ];
        $riwayatList[] = $newEntry;
        
    } elseif ($action_type === 'RETURN') {
        $new_status = 'READY';
        
        // Hapus (blank) data user yang lama karena kembali ke master ('Return')
        $nama = '';
        $jabatan = '';
        $empleId = '';
        $lokasi = '';
        
        if (count($riwayatList) > 0) {
            $lastIdx = count($riwayatList) - 1;
            $riwayatList[$lastIdx]['tgl_pengembalian'] = $tglKembali;
            
            // Append catatan return jika diisi
            if (trim($catatan) !== '') {
                $existing_catatan = trim((string)($riwayatList[$lastIdx]['catatan'] ?? ''));
                $riwayatList[$lastIdx]['catatan'] = $existing_catatan !== '' ? $existing_catatan . ' | Return Note: ' . $catatan : $catatan;
            }
        } else {
            // Edge case: tidak ada riwayat yang valid, buat baru meskipun harusnya tidak terjadi
            $riwayatList[] = [
                'nama' => '',
                'jabatan' => '',
                'empleId' => '',
                'lokasi' => '',
                'tgl_serah_terima' => date('Y-m-d'),
                'tgl_pengembalian' => $tglKembali,
                'catatan' => $catatan,
                'aksi' => 'RETURN',
                'created_by' => (string)($_SESSION['username'] ?? ''),
                'created_at' => date('Y-m-d H:i:s'),
            ];
        }
    }

    $riwayatJson = json_encode($riwayatList, JSON_UNESCAPED_UNICODE);
    if ($riwayatJson === false) {
        json_out(['ok' => false, 'error' => 'Failed to encode riwayat JSON'], 500);
    }

    // Update kolom aktif + append riwayat + status barang
    $stmtUpd = $kon->prepare('UPDATE peserta SET User_Perangkat = ?, Jabatan = ?, Id_Karyawan = ?, Lokasi = ?, Status_Barang = ?, Riwayat_Barang = ? WHERE id_peserta = ?');
    if (!$stmtUpd) {
        json_out(['ok' => false, 'error' => 'Prepare failed: ' . $kon->error], 500);
    }

    $stmtUpd->bind_param('ssssssi', $nama, $jabatan, $empleId, $lokasi, $new_status, $riwayatJson, $id);
    if (!$stmtUpd->execute()) {
        $err = $stmtUpd->error;
        $stmtUpd->close();
        json_out(['ok' => false, 'error' => 'Update failed: ' . $err], 500);
    }
    $stmtUpd->close();

    json_out(['ok' => true]);
}

json_out(['ok' => false, 'error' => 'Invalid action'], 400);
