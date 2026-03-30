<?php
/**
 * change_password.php — API endpoint untuk ganti password user
 */
session_start();
require_once __DIR__ . '/../koneksi.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']); exit;
}

$user_id     = (int)$_SESSION['user_id'];
$current_pw  = (string)($_POST['current_password'] ?? '');
$new_pw      = (string)($_POST['new_password'] ?? '');
$confirm_pw  = (string)($_POST['confirm_password'] ?? '');

// Validasi input
if ($current_pw === '' || $new_pw === '' || $confirm_pw === '') {
    echo json_encode(['success' => false, 'message' => 'Semua field wajib diisi']); exit;
}
if ($new_pw !== $confirm_pw) {
    echo json_encode(['success' => false, 'message' => 'Password baru dan konfirmasi tidak cocok']); exit;
}
if (strlen($new_pw) < 6) {
    echo json_encode(['success' => false, 'message' => 'Password baru minimal 6 karakter']); exit;
}
if (strlen($new_pw) > 100) {
    echo json_encode(['success' => false, 'message' => 'Password terlalu panjang']); exit;
}

// Ambil password lama dari DB
$stmt = $kon->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user   = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'User tidak ditemukan']); exit;
}

$storedHash = (string)$user['password'];

// Verifikasi password lama (support bcrypt & MD5 legacy)
$valid = false;
if (password_verify($current_pw, $storedHash)) {
    $valid = true;
} elseif ($storedHash === md5($current_pw)) {
    $valid = true;
} elseif ($storedHash === $current_pw) {
    // plain text (legacy, tidak disarankan)
    $valid = true;
}

if (!$valid) {
    echo json_encode(['success' => false, 'message' => 'Password saat ini tidak sesuai']); exit;
}

// Hash password baru
$newHash = password_hash($new_pw, PASSWORD_BCRYPT);

$upd = $kon->prepare("UPDATE users SET password = ? WHERE id = ?");
$upd->bind_param('si', $newHash, $user_id);
$ok = $upd->execute();
$upd->close();

if ($ok) {
    echo json_encode(['success' => true, 'message' => 'Password berhasil diubah']);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal menyimpan password: ' . $kon->error]);
}
