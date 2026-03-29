<?php
/**
 * chat.php — General Chat AJAX Endpoint (Enhanced)
 * Actions: get | send | edit | delete_msg | upload_file | online
 */
session_start();
require_once __DIR__ . '/koneksi.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$username = (string)($_SESSION['username'] ?? 'Unknown');
$nama = (string)($_SESSION['Nama_Lengkap'] ?? $username);
$role = (string)($_SESSION['role'] ?? 'user');
$jabatan = (string)($_SESSION['Jabatan_Level'] ?? '');
$action = trim((string)($_GET['action'] ?? $_POST['action'] ?? ''));

// ---- Ensure Tables ----
$kon->query("CREATE TABLE IF NOT EXISTS chat_messages (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    username        VARCHAR(100) NOT NULL,
    nama_lengkap    VARCHAR(150) NOT NULL,
    role            VARCHAR(50) NOT NULL,
    jabatan         VARCHAR(150) NOT NULL DEFAULT '',
    message         TEXT NOT NULL DEFAULT '',
    reply_to_id     INT NULL,
    edited_at       DATETIME NULL,
    attachment_path VARCHAR(500) NULL,
    attachment_name VARCHAR(255) NULL,
    attachment_type VARCHAR(50) NULL,
    attachment_size INT NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created (created_at),
    INDEX idx_id (id),
    INDEX idx_reply (reply_to_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Add columns if upgrading from old version
foreach ([
"ALTER TABLE chat_messages ADD COLUMN IF NOT EXISTS jabatan VARCHAR(150) NOT NULL DEFAULT ''",
"ALTER TABLE chat_messages ADD COLUMN IF NOT EXISTS reply_to_id INT NULL",
"ALTER TABLE chat_messages ADD COLUMN IF NOT EXISTS edited_at DATETIME NULL",
"ALTER TABLE chat_messages ADD COLUMN IF NOT EXISTS attachment_path VARCHAR(500) NULL",
"ALTER TABLE chat_messages ADD COLUMN IF NOT EXISTS attachment_name VARCHAR(255) NULL",
"ALTER TABLE chat_messages ADD COLUMN IF NOT EXISTS attachment_type VARCHAR(50) NULL",
"ALTER TABLE chat_messages ADD COLUMN IF NOT EXISTS attachment_size INT NULL",
] as $sql) {
    @$kon->query($sql);
}

$kon->query("CREATE TABLE IF NOT EXISTS chat_presence (
    user_id      INT PRIMARY KEY,
    username     VARCHAR(100) NOT NULL,
    nama_lengkap VARCHAR(150) NOT NULL,
    role         VARCHAR(50) NOT NULL,
    last_seen    DATETIME NOT NULL,
    INDEX idx_seen (last_seen)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$kon->query("CREATE TABLE IF NOT EXISTS chat_message_reads (
    message_id   INT NOT NULL,
    user_id      INT NOT NULL,
    nama_lengkap VARCHAR(150) NOT NULL,
    read_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (message_id, user_id),
    INDEX idx_msg (message_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Helper: update presence
function updatePresence($kon, $uid, $uname, $nama, $role)
{
    $u = $kon->real_escape_string($uname);
    $n = $kon->real_escape_string($nama);
    $r = $kon->real_escape_string($role);
    $kon->query("INSERT INTO chat_presence (user_id,username,nama_lengkap,role,last_seen)
                 VALUES ($uid,'$u','$n','$r',NOW())
                 ON DUPLICATE KEY UPDATE username='$u',nama_lengkap='$n',role='$r',last_seen=NOW()");
}

// Helper: format message row
function formatMsg($m, $myId)
{
    $m['is_own'] = ((int)$m['user_id'] === (int)$myId);
    $m['is_admin'] = in_array($m['role'], ['super_admin', 'admin']);
    $m['message'] = htmlspecialchars((string)$m['message']);
    $m['nama_display'] = htmlspecialchars($m['nama_lengkap'] ?: $m['username']);
    $m['is_edited'] = !empty($m['edited_at']);

    // Jabatan: use stored value or fallback fetch from users table
    $storedJabatan = trim((string)($m['jabatan'] ?? ''));
    if ($storedJabatan === '') {
        // Cache jabatan lookups to avoid N+1 queries
        static $jabatanCache = [];
        $uid = (int)$m['user_id'];
        if (!isset($jabatanCache[$uid])) {
            global $kon;
            $jq = $kon->query("SELECT Jabatan_Level FROM users WHERE id=$uid LIMIT 1");
            $jabatanCache[$uid] = ($jq && ($jr = $jq->fetch_assoc())) ? (string)$jr['Jabatan_Level'] : '';
        }
        $storedJabatan = $jabatanCache[$uid];
    }
    $m['jabatan'] = htmlspecialchars($storedJabatan);

    if (!empty($m['attachment_path'])) {
        // Build a web-accessible URL - strip from document root
        $m['attachment_url'] = '/' . ltrim(str_replace('\\', '/', $m['attachment_path']), '/');
    }
    try {
        $dt = new DateTime($m['created_at']);
        $m['time_display'] = $dt->format('H:i');
        $m['date_display'] = $dt->format('d M Y');
    }
    catch (Exception $e) {
        $m['time_display'] = '';
        $m['date_display'] = '';
    }
    // Reply-to snippet
    if (!empty($m['reply_to_id'])) {
        // fetch inline (already have $kon globally)
        global $kon;
        $rid = (int)$m['reply_to_id'];
        $rq = $kon->query("SELECT nama_lengkap, username, message, attachment_name FROM chat_messages WHERE id=$rid LIMIT 1");
        if ($rq && ($rr = $rq->fetch_assoc())) {
            $m['reply_snippet'] = [
                'name' => htmlspecialchars($rr['nama_lengkap'] ?: $rr['username']),
                'text' => htmlspecialchars(mb_substr($rr['message'], 0, 80)),
                'has_file' => !empty($rr['attachment_name']),
                'file' => htmlspecialchars($rr['attachment_name'] ?? ''),
            ];
        }
    }
    return $m;
}

// Helper: online count
function onlineCount($kon)
{
    $r = $kon->query("SELECT COUNT(*) c FROM chat_presence WHERE last_seen >= DATE_SUB(NOW(), INTERVAL 90 SECOND)");
    return $r ? (int)($r->fetch_assoc()['c'] ?? 0) : 0;
}

// ---- ACTION: get messages ----
if ($action === 'get') {
    $after_id = max(0, (int)($_GET['after_id'] ?? 0));
    updatePresence($kon, $user_id, $username, $nama, $role);

    if ($after_id > 0) {
        $stmt = $kon->prepare("SELECT id,user_id,username,nama_lengkap,role,jabatan,message,reply_to_id,edited_at,attachment_path,attachment_name,attachment_type,attachment_size,created_at
                               FROM chat_messages WHERE id > ? ORDER BY id ASC LIMIT 50");
        $stmt->bind_param('i', $after_id);
    }
    else {
        $limit = 60;
        $stmt = $kon->prepare("SELECT id,user_id,username,nama_lengkap,role,jabatan,message,reply_to_id,edited_at,attachment_path,attachment_name,attachment_type,attachment_size,created_at
                               FROM chat_messages ORDER BY id DESC LIMIT ?");
        $stmt->bind_param('i', $limit);
    }
    $stmt->execute();
    $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    if ($after_id === 0)
        $messages = array_reverse($messages);
    $messages = array_map(fn($m) => formatMsg($m, $user_id), $messages);

    // ---- Mark messages as read (skip own messages) ----
    if (!empty($messages)) {
        $n = htmlspecialchars_decode($nama); // plain name for storage
        $nEsc = $kon->real_escape_string($nama);
        $readValues = [];
        foreach ($messages as $m) {
            if ((int)$m['user_id'] !== $user_id) {
                $readValues[] = "({$m['id']},$user_id,'$nEsc',NOW())";
            }
        }
        if (!empty($readValues)) {
            $kon->query("INSERT IGNORE INTO chat_message_reads (message_id,user_id,nama_lengkap,read_at) VALUES " . implode(',', $readValues));
        }

        // ---- Attach read info to each message ----
        $msgIds = implode(',', array_column($messages, 'id'));
        $readsMap = [];
        $rq = $kon->query("SELECT message_id, nama_lengkap FROM chat_message_reads WHERE message_id IN ($msgIds) ORDER BY read_at ASC");
        if ($rq) {
            while ($rr = $rq->fetch_assoc()) {
                $readsMap[(int)$rr['message_id']][] = htmlspecialchars($rr['nama_lengkap']);
            }
        }
        foreach ($messages as &$m) {
            $mid = (int)$m['id'];
            $readers = $readsMap[$mid] ?? [];
            $m['read_by'] = $readers; // array of names
            $m['read_count'] = count($readers); // int
        }
        unset($m);
    }

    $lastId = empty($messages) ? $after_id : end($messages)['id'];

    echo json_encode([
        'messages' => $messages,
        'last_id' => (int)$lastId,
        'online' => onlineCount($kon),
        'my_id' => $user_id,
    ]);
    exit;
}

// ---- ACTION: send message ----
if ($action === 'send') {
    $message = trim((string)($_POST['message'] ?? ''));
    $reply_to = max(0, (int)($_POST['reply_to_id'] ?? 0));

    // Must have either message or attachment
    if ($message === '' && empty($_FILES['attachment'])) {
        echo json_encode(['error' => 'Pesan kosong']);
        exit;
    }
    if (mb_strlen($message) > 1000) {
        echo json_encode(['error' => 'Pesan terlalu panjang']);
        exit;
    }

    updatePresence($kon, $user_id, $username, $nama, $role);

    $att_path = $att_name = $att_type = null;
    $att_size = null;

    // Handle file upload
    if (!empty($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['attachment'];
        $origName = basename($file['name']);
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'zip'];
        if (!in_array($ext, $allowed)) {
            echo json_encode(['error' => 'Tipe file tidak diizinkan']);
            exit;
        }
        if ($file['size'] > 20 * 1024 * 1024) { // 20MB raw max
            echo json_encode(['error' => 'File terlalu besar (maks 20MB)']);
            exit;
        }
        $uploadDir = __DIR__ . '/uploads/chat/';
        if (!is_dir($uploadDir))
            mkdir($uploadDir, 0755, true);

        // Auto naming: chat_YYYYMMDD_HHmmss_RANDOM.ext
        $safeName = 'chat_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)), 0, 6) . '.' . $ext;
        $destPath = $uploadDir . $safeName;

        if (move_uploaded_file($file['tmp_name'], $destPath)) {
            // Build relative path from docroot
            $att_path = 'uploads/chat/' . $safeName;
            $att_name = $origName;
            $att_type = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']) ? 'image' : 'file';
            $att_size = $file['size'];
        }
        else {
            echo json_encode(['error' => 'Gagal simpan file']);
            exit;
        }
    }

    $reply_to_val = $reply_to > 0 ? $reply_to : null;
    // Types: i=user_id, s=username, s=nama, s=role, s=jabatan, s=message, i=reply_to_id, s=att_path, s=att_name, s=att_type, i=att_size = 11 params
    $stmt = $kon->prepare("INSERT INTO chat_messages (user_id,username,nama_lengkap,role,jabatan,message,reply_to_id,attachment_path,attachment_name,attachment_type,attachment_size) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->bind_param('isssssisssi', $user_id, $username, $nama, $role, $jabatan, $message, $reply_to_val, $att_path, $att_name, $att_type, $att_size);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'id' => $kon->insert_id]);
    }
    else {
        echo json_encode(['error' => 'Gagal kirim: ' . $kon->error]);
    }
    $stmt->close();
    exit;
}

// ---- ACTION: edit message ----
if ($action === 'edit') {
    $msg_id = max(0, (int)($_POST['id'] ?? 0));
    $newText = trim((string)($_POST['message'] ?? ''));
    if ($msg_id === 0 || $newText === '' || mb_strlen($newText) > 1000) {
        echo json_encode(['error' => 'Data tidak valid']);
        exit;
    }
    // Verify ownership and within 15 min
    $chk = $kon->prepare("SELECT user_id, created_at FROM chat_messages WHERE id=? LIMIT 1");
    $chk->bind_param('i', $msg_id);
    $chk->execute();
    $chk->bind_result($ownerId, $createdAt);
    $chk->fetch();
    $chk->close();
    if ((int)$ownerId !== $user_id) {
        echo json_encode(['error' => 'Tidak bisa edit pesan orang lain']);
        exit;
    }
    if (strtotime($createdAt) < time() - 900) { // 15 minutes
        echo json_encode(['error' => 'Pesan sudah tidak bisa diedit (> 15 menit)']);
        exit;
    }
    $upd = $kon->prepare("UPDATE chat_messages SET message=?, edited_at=NOW() WHERE id=?");
    $upd->bind_param('si', $newText, $msg_id);
    echo json_encode(['success' => $upd->execute()]);
    $upd->close();
    exit;
}

// ---- ACTION: delete message ----
if ($action === 'delete_msg') {
    $msg_id = max(0, (int)($_POST['id'] ?? 0));
    if ($msg_id === 0) {
        echo json_encode(['error' => 'ID tidak valid']);
        exit;
    }
    $chk = $kon->prepare("SELECT user_id, attachment_path FROM chat_messages WHERE id=? LIMIT 1");
    $chk->bind_param('i', $msg_id);
    $chk->execute();
    $chk->bind_result($ownerId, $attPath);
    $chk->fetch();
    $chk->close();
    $isAdmin = in_array($role, ['super_admin', 'admin']);
    if ((int)$ownerId !== $user_id && !$isAdmin) {
        echo json_encode(['error' => 'Tidak bisa hapus pesan orang lain']);
        exit;
    }
    // Delete attachment file
    if ($attPath && file_exists(__DIR__ . '/' . $attPath)) {
        @unlink(__DIR__ . '/' . $attPath);
    }
    $del = $kon->prepare("DELETE FROM chat_messages WHERE id=?");
    $del->bind_param('i', $msg_id);
    echo json_encode(['success' => $del->execute()]);
    $del->close();
    exit;
}

// ---- ACTION: online ping ----
if ($action === 'online') {
    updatePresence($kon, $user_id, $username, $nama, $role);
    echo json_encode(['online' => onlineCount($kon)]);
    exit;
}

// ---- ACTION: get_reads (for live receipt refresh) ----
if ($action === 'get_reads') {
    $rawIds = (string)($_GET['ids'] ?? '');
    // Parse to int array, only keep own messages
    $ids = array_filter(array_map('intval', explode(',', $rawIds)));
    if (empty($ids)) {
        echo json_encode(['reads' => []]);
        exit;
    }
    // Verify these messages belong to current user (security)
    $idsStr = implode(',', $ids);
    $ownCheck = $kon->query("SELECT id FROM chat_messages WHERE id IN ($idsStr) AND user_id=$user_id");
    $ownIds = [];
    if ($ownCheck)
        while ($oc = $ownCheck->fetch_assoc())
            $ownIds[] = (int)$oc['id'];
    if (empty($ownIds)) {
        echo json_encode(['reads' => []]);
        exit;
    }
    $ownStr = implode(',', $ownIds);
    $rq = $kon->query("SELECT message_id, nama_lengkap FROM chat_message_reads WHERE message_id IN ($ownStr) ORDER BY read_at ASC");
    $readsMap = [];
    if ($rq) {
        while ($rr = $rq->fetch_assoc()) {
            $mid = (int)$rr['message_id'];
            $readsMap[$mid]['names'][] = htmlspecialchars($rr['nama_lengkap']);
        }
    }
    $out = [];
    foreach ($ownIds as $mid) {
        $out[$mid] = [
            'count' => count($readsMap[$mid]['names'] ?? []),
            'names' => $readsMap[$mid]['names'] ?? [],
        ];
    }
    echo json_encode(['reads' => $out]);
    exit;
}

echo json_encode(['error' => 'Unknown action']);
