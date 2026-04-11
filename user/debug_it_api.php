<?php
/**
 * debug_it_api.php — HAPUS SETELAH DEBUG
 * Kunjungi: localhost/crud/user/debug_it_api.php saat login sebagai Indah
 */
require_once __DIR__ . '/../koneksi.php';
session_start();
session_write_close();
header('Content-Type: application/json; charset=utf-8');

$out = [
    'session_user_id'    => $_SESSION['user_id'] ?? null,
    'session_username'   => $_SESSION['username'] ?? null,
    'session_Id_Karyawan'=> $_SESSION['Id_Karyawan'] ?? null,
    'session_Nama_Lengkap'=> $_SESSION['Nama_Lengkap'] ?? null,
];

// Cek kolom assigned_to
$colCheck = $kon->query("SHOW COLUMNS FROM `ticket` LIKE 'assigned_to'");
$out['has_assigned_column'] = ($colCheck && $colCheck->num_rows > 0);

$myIdKaryawan = trim((string)($_SESSION['Id_Karyawan'] ?? ''));

if ($myIdKaryawan !== '') {
    // Semua tiket milik user ini yg sedang dikerjakan
    $r = $kon->query(
        "SELECT Ticket_code, assigned_to, Status_Request, Id_Karyawan
         FROM `ticket`
         WHERE `Id_Karyawan` = '" . $kon->real_escape_string($myIdKaryawan) . "'
           AND `assigned_to` IS NOT NULL
           AND `assigned_to` != ''
           AND `Status_Request` NOT IN ('Done','Closed','Reject')
         LIMIT 10"
    );
    $out['my_active_tickets'] = $r ? $r->fetch_all(MYSQLI_ASSOC) : $kon->error;

    // Juga cek semua tiket milik user ini
    $r2 = $kon->query(
        "SELECT Ticket_code, assigned_to, Status_Request, Id_Karyawan
         FROM `ticket`
         WHERE `Id_Karyawan` = '" . $kon->real_escape_string($myIdKaryawan) . "'
         ORDER BY Ticket_code DESC LIMIT 5"
    );
    $out['my_recent_tickets'] = $r2 ? $r2->fetch_all(MYSQLI_ASSOC) : $kon->error;
} else {
    $out['warning'] = 'Id_Karyawan is EMPTY in session!';

    // Cari dari tabel users berdasarkan user_id
    $uid = (int)($_SESSION['user_id'] ?? 0);
    if ($uid > 0) {
        $r3 = $kon->query("SELECT id, username, Id_Karyawan, Nama_Lengkap FROM users WHERE id = $uid LIMIT 1");
        $out['user_row'] = $r3 ? $r3->fetch_assoc() : $kon->error;
    }
}

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
