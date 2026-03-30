<?php
/**
 * chat.php — General Chat + Direct Message AJAX Endpoint
 * Actions: get | send | edit | delete_msg | online | get_reads | get_online_users | send_dm | get_dm
 */
session_start();
require_once __DIR__ . '/koneksi.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id  = (int)$_SESSION['user_id'];
$username = (string)($_SESSION['username'] ?? 'Unknown');
$nama     = (string)($_SESSION['Nama_Lengkap'] ?? $username);
$role     = (string)($_SESSION['role'] ?? 'user');
$jabatan  = (string)($_SESSION['Jabatan_Level'] ?? '');
$action   = trim((string)($_GET['action'] ?? $_POST['action'] ?? ''));

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
    jabatan      VARCHAR(150) NOT NULL DEFAULT '',
    last_seen    DATETIME NOT NULL,
    INDEX idx_seen (last_seen)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
// Add jabatan column to presence if missing
@$kon->query("ALTER TABLE chat_presence ADD COLUMN IF NOT EXISTS jabatan VARCHAR(150) NOT NULL DEFAULT ''");

$kon->query("CREATE TABLE IF NOT EXISTS chat_message_reads (
    message_id   INT NOT NULL,
    user_id      INT NOT NULL,
    nama_lengkap VARCHAR(150) NOT NULL,
    read_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (message_id, user_id),
    INDEX idx_msg (message_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ---- Direct Messages Table ----
$kon->query("CREATE TABLE IF NOT EXISTS chat_dm (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    from_user_id    INT NOT NULL,
    to_user_id      INT NOT NULL,
    from_nama       VARCHAR(150) NOT NULL,
    from_role       VARCHAR(50)  NOT NULL DEFAULT '',
    from_jabatan    VARCHAR(150) NOT NULL DEFAULT '',
    message         TEXT NOT NULL DEFAULT '',
    is_read         TINYINT(1) NOT NULL DEFAULT 0,
    attachment_path VARCHAR(500) NULL,
    attachment_name VARCHAR(255) NULL,
    attachment_type VARCHAR(50)  NULL,
    attachment_size INT NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_from (from_user_id),
    INDEX idx_to   (to_user_id),
    INDEX idx_pair (from_user_id, to_user_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Helper: update presence
function updatePresence($kon, $uid, $uname, $nama, $role, $jabatan = '')
{
    $u = $kon->real_escape_string($uname);
    $n = $kon->real_escape_string($nama);
    $r = $kon->real_escape_string($role);
    $j = $kon->real_escape_string($jabatan);
    $kon->query("INSERT INTO chat_presence (user_id,username,nama_lengkap,role,jabatan,last_seen)
                 VALUES ($uid,'$u','$n','$r','$j',NOW())
                 ON DUPLICATE KEY UPDATE username='$u',nama_lengkap='$n',role='$r',jabatan='$j',last_seen=NOW()");
}

// Helper: format message row
function formatMsg($m, $myId)
{
    $m['is_own']    = ((int)$m['user_id'] === (int)$myId);
    $m['is_admin']  = in_array($m['role'], ['super_admin', 'admin']);
    $m['message']   = htmlspecialchars((string)$m['message']);
    $m['nama_display'] = htmlspecialchars($m['nama_lengkap'] ?: $m['username']);
    $m['is_edited'] = !empty($m['edited_at']);

    $storedJabatan = trim((string)($m['jabatan'] ?? ''));
    if ($storedJabatan === '') {
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
        $m['attachment_url'] = '/' . ltrim(str_replace('\\', '/', $m['attachment_path']), '/');
    }
    try {
        $dt = new DateTime($m['created_at']);
        $m['time_display'] = $dt->format('H:i');
        $m['date_display'] = $dt->format('d M Y');
    } catch (Exception $e) {
        $m['time_display'] = '';
        $m['date_display'] = '';
    }
    if (!empty($m['reply_to_id'])) {
        global $kon;
        $rid = (int)$m['reply_to_id'];
        $rq  = $kon->query("SELECT nama_lengkap, username, message, attachment_name FROM chat_messages WHERE id=$rid LIMIT 1");
        if ($rq && ($rr = $rq->fetch_assoc())) {
            $m['reply_snippet'] = [
                'name'     => htmlspecialchars($rr['nama_lengkap'] ?: $rr['username']),
                'text'     => htmlspecialchars(mb_substr($rr['message'], 0, 80)),
                'has_file' => !empty($rr['attachment_name']),
                'file'     => htmlspecialchars($rr['attachment_name'] ?? ''),
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
    updatePresence($kon, $user_id, $username, $nama, $role, $jabatan);

    if ($after_id > 0) {
        $stmt = $kon->prepare("SELECT id,user_id,username,nama_lengkap,role,jabatan,message,reply_to_id,edited_at,attachment_path,attachment_name,attachment_type,attachment_size,created_at
                               FROM chat_messages WHERE id > ? ORDER BY id ASC LIMIT 50");
        $stmt->bind_param('i', $after_id);
    } else {
        $limit = 60;
        $stmt  = $kon->prepare("SELECT id,user_id,username,nama_lengkap,role,jabatan,message,reply_to_id,edited_at,attachment_path,attachment_name,attachment_type,attachment_size,created_at
                               FROM chat_messages ORDER BY id DESC LIMIT ?");
        $stmt->bind_param('i', $limit);
    }
    $stmt->execute();
    $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    if ($after_id === 0) $messages = array_reverse($messages);
    $messages = array_map(fn($m) => formatMsg($m, $user_id), $messages);

    if (!empty($messages)) {
        $nEsc       = $kon->real_escape_string($nama);
        $readValues = [];
        foreach ($messages as $m) {
            if ((int)$m['user_id'] !== $user_id) {
                $readValues[] = "({$m['id']},$user_id,'$nEsc',NOW())";
            }
        }
        if (!empty($readValues)) {
            $kon->query("INSERT IGNORE INTO chat_message_reads (message_id,user_id,nama_lengkap,read_at) VALUES " . implode(',', $readValues));
        }
        $msgIds  = implode(',', array_column($messages, 'id'));
        $readsMap = [];
        $rq = $kon->query("SELECT message_id, nama_lengkap FROM chat_message_reads WHERE message_id IN ($msgIds) ORDER BY read_at ASC");
        if ($rq) {
            while ($rr = $rq->fetch_assoc()) {
                $readsMap[(int)$rr['message_id']][] = htmlspecialchars($rr['nama_lengkap']);
            }
        }
        foreach ($messages as &$m) {
            $mid         = (int)$m['id'];
            $readers     = $readsMap[$mid] ?? [];
            $m['read_by']    = $readers;
            $m['read_count'] = count($readers);
        }
        unset($m);
    }

    $lastId = empty($messages) ? $after_id : end($messages)['id'];
    echo json_encode([
        'messages' => $messages,
        'last_id'  => (int)$lastId,
        'online'   => onlineCount($kon),
        'my_id'    => $user_id,
    ]);
    exit;
}

// ---- ACTION: send message ----
if ($action === 'send') {
    $message  = trim((string)($_POST['message'] ?? ''));
    $reply_to = max(0, (int)($_POST['reply_to_id'] ?? 0));

    if ($message === '' && empty($_FILES['attachment'])) {
        echo json_encode(['error' => 'Pesan kosong']);
        exit;
    }
    if (mb_strlen($message) > 1000) {
        echo json_encode(['error' => 'Pesan terlalu panjang']);
        exit;
    }
    updatePresence($kon, $user_id, $username, $nama, $role, $jabatan);

    $att_path = $att_name = $att_type = null;
    $att_size = null;

    if (!empty($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $file     = $_FILES['attachment'];
        $origName = basename($file['name']);
        $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        $allowed  = ['jpg','jpeg','png','gif','webp','pdf','doc','docx','xls','xlsx','ppt','pptx','txt','zip'];
        if (!in_array($ext, $allowed)) { echo json_encode(['error' => 'Tipe file tidak diizinkan']); exit; }
        if ($file['size'] > 20 * 1024 * 1024) { echo json_encode(['error' => 'File terlalu besar (maks 20MB)']); exit; }
        $uploadDir = __DIR__ . '/uploads/chat/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $safeName = 'chat_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)), 0, 6) . '.' . $ext;
        $destPath = $uploadDir . $safeName;
        if (move_uploaded_file($file['tmp_name'], $destPath)) {
            $att_path = 'uploads/chat/' . $safeName;
            $att_name = $origName;
            $att_type = in_array($ext, ['jpg','jpeg','png','gif','webp']) ? 'image' : 'file';
            $att_size = $file['size'];
        } else {
            echo json_encode(['error' => 'Gagal simpan file']); exit;
        }
    }

    $reply_to_val = $reply_to > 0 ? $reply_to : null;
    $stmt = $kon->prepare("INSERT INTO chat_messages (user_id,username,nama_lengkap,role,jabatan,message,reply_to_id,attachment_path,attachment_name,attachment_type,attachment_size) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->bind_param('isssssisssi', $user_id, $username, $nama, $role, $jabatan, $message, $reply_to_val, $att_path, $att_name, $att_type, $att_size);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'id' => $kon->insert_id]);
    } else {
        echo json_encode(['error' => 'Gagal kirim: ' . $kon->error]);
    }
    $stmt->close();
    exit;
}

// ---- ACTION: edit message ----
if ($action === 'edit') {
    $msg_id  = max(0, (int)($_POST['id'] ?? 0));
    $newText = trim((string)($_POST['message'] ?? ''));
    if ($msg_id === 0 || $newText === '' || mb_strlen($newText) > 1000) {
        echo json_encode(['error' => 'Data tidak valid']); exit;
    }
    $chk = $kon->prepare("SELECT user_id, created_at FROM chat_messages WHERE id=? LIMIT 1");
    $chk->bind_param('i', $msg_id);
    $chk->execute();
    $chk->bind_result($ownerId, $createdAt);
    $chk->fetch();
    $chk->close();
    if ((int)$ownerId !== $user_id) { echo json_encode(['error' => 'Tidak bisa edit pesan orang lain']); exit; }
    if (strtotime($createdAt) < time() - 900) { echo json_encode(['error' => 'Pesan sudah tidak bisa diedit (> 15 menit)']); exit; }
    $upd = $kon->prepare("UPDATE chat_messages SET message=?, edited_at=NOW() WHERE id=?");
    $upd->bind_param('si', $newText, $msg_id);
    echo json_encode(['success' => $upd->execute()]);
    $upd->close();
    exit;
}

// ---- ACTION: delete message ----
if ($action === 'delete_msg') {
    $msg_id = max(0, (int)($_POST['id'] ?? 0));
    if ($msg_id === 0) { echo json_encode(['error' => 'ID tidak valid']); exit; }
    $chk = $kon->prepare("SELECT user_id, attachment_path FROM chat_messages WHERE id=? LIMIT 1");
    $chk->bind_param('i', $msg_id);
    $chk->execute();
    $chk->bind_result($ownerId, $attPath);
    $chk->fetch();
    $chk->close();
    $isAdmin = in_array($role, ['super_admin', 'admin']);
    if ((int)$ownerId !== $user_id && !$isAdmin) { echo json_encode(['error' => 'Tidak bisa hapus pesan orang lain']); exit; }
    if ($attPath && file_exists(__DIR__ . '/' . $attPath)) @unlink(__DIR__ . '/' . $attPath);
    $del = $kon->prepare("DELETE FROM chat_messages WHERE id=?");
    $del->bind_param('i', $msg_id);
    echo json_encode(['success' => $del->execute()]);
    $del->close();
    exit;
}

// ---- ACTION: online ping ----
if ($action === 'online') {
    updatePresence($kon, $user_id, $username, $nama, $role, $jabatan);
    echo json_encode(['online' => onlineCount($kon)]);
    exit;
}

// ---- ACTION: get_reads ----
if ($action === 'get_reads') {
    $rawIds = (string)($_GET['ids'] ?? '');
    $ids    = array_filter(array_map('intval', explode(',', $rawIds)));
    if (empty($ids)) { echo json_encode(['reads' => []]); exit; }
    $idsStr   = implode(',', $ids);
    $ownCheck = $kon->query("SELECT id FROM chat_messages WHERE id IN ($idsStr) AND user_id=$user_id");
    $ownIds   = [];
    if ($ownCheck) while ($oc = $ownCheck->fetch_assoc()) $ownIds[] = (int)$oc['id'];
    if (empty($ownIds)) { echo json_encode(['reads' => []]); exit; }
    $ownStr   = implode(',', $ownIds);
    $rq       = $kon->query("SELECT message_id, nama_lengkap FROM chat_message_reads WHERE message_id IN ($ownStr) ORDER BY read_at ASC");
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

// ---- ACTION: get_online_users ----
if ($action === 'get_online_users') {
    updatePresence($kon, $user_id, $username, $nama, $role, $jabatan);
    $rq = $kon->query("SELECT user_id, nama_lengkap, username, role, jabatan
                       FROM chat_presence
                       WHERE last_seen >= DATE_SUB(NOW(), INTERVAL 90 SECOND)
                       ORDER BY role ASC, nama_lengkap ASC");
    $users = [];
    if ($rq) {
        while ($row = $rq->fetch_assoc()) {
            // Resolve jabatan if empty
            if (trim($row['jabatan']) === '') {
                $jq = $kon->query("SELECT Jabatan_Level FROM users WHERE id=" . (int)$row['user_id'] . " LIMIT 1");
                $row['jabatan'] = ($jq && ($jr = $jq->fetch_assoc())) ? (string)$jr['Jabatan_Level'] : '';
            }
            $users[] = [
                'user_id'  => (int)$row['user_id'],
                'nama'     => htmlspecialchars($row['nama_lengkap'] ?: $row['username']),
                'role'     => $row['role'],
                'jabatan'  => htmlspecialchars($row['jabatan']),
                'is_me'    => ((int)$row['user_id'] === $user_id),
                'is_admin' => in_array($row['role'], ['super_admin', 'admin']),
            ];
        }
    }
    // Count unread DMs per sender for current user
    $unreadRq = $kon->query("SELECT from_user_id, COUNT(*) c FROM chat_dm WHERE to_user_id=$user_id AND is_read=0 GROUP BY from_user_id");
    $unreadMap = [];
    if ($unreadRq) while ($ur = $unreadRq->fetch_assoc()) $unreadMap[(int)$ur['from_user_id']] = (int)$ur['c'];
    foreach ($users as &$u) $u['unread_dm'] = $unreadMap[$u['user_id']] ?? 0;
    unset($u);

    echo json_encode(['users' => $users, 'my_id' => $user_id]);
    exit;
}

// ---- ACTION: send_dm ----
if ($action === 'send_dm') {
    $to_id  = max(0, (int)($_POST['to_user_id'] ?? 0));
    $message = trim((string)($_POST['message'] ?? ''));
    if ($to_id === 0 || ($message === '' && empty($_FILES['attachment']))) {
        echo json_encode(['error' => 'Data tidak valid']); exit;
    }
    if ($to_id === $user_id) { echo json_encode(['error' => 'Tidak bisa kirim pesan ke diri sendiri']); exit; }

    // Verify target user exists
    $chkUser = $kon->query("SELECT id FROM users WHERE id=$to_id LIMIT 1");
    if (!$chkUser || $chkUser->num_rows === 0) { echo json_encode(['error' => 'Pengguna tidak ditemukan']); exit; }

    $att_path = $att_name = $att_type = null;
    $att_size = null;

    if (!empty($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $file     = $_FILES['attachment'];
        $origName = basename($file['name']);
        $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        $allowed  = ['jpg','jpeg','png','gif','webp','pdf','doc','docx','xls','xlsx','ppt','pptx','txt','zip'];
        if (!in_array($ext, $allowed)) { echo json_encode(['error' => 'Tipe file tidak diizinkan']); exit; }
        if ($file['size'] > 20 * 1024 * 1024) { echo json_encode(['error' => 'File terlalu besar']); exit; }
        $uploadDir = __DIR__ . '/uploads/chat/dm/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $safeName = 'dm_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)), 0, 6) . '.' . $ext;
        if (move_uploaded_file($file['tmp_name'], $uploadDir . $safeName)) {
            $att_path = 'uploads/chat/dm/' . $safeName;
            $att_name = $origName;
            $att_type = in_array($ext, ['jpg','jpeg','png','gif','webp']) ? 'image' : 'file';
            $att_size = $file['size'];
        }
    }

    updatePresence($kon, $user_id, $username, $nama, $role, $jabatan);
    $stmt = $kon->prepare("INSERT INTO chat_dm (from_user_id,to_user_id,from_nama,from_role,from_jabatan,message,attachment_path,attachment_name,attachment_type,attachment_size) VALUES (?,?,?,?,?,?,?,?,?,?)");
    $stmt->bind_param('iisssssssi', $user_id, $to_id, $nama, $role, $jabatan, $message, $att_path, $att_name, $att_type, $att_size);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'id' => $kon->insert_id]);
    } else {
        echo json_encode(['error' => 'Gagal kirim: ' . $kon->error]);
    }
    $stmt->close();
    exit;
}

// ---- ACTION: get_dm ----
if ($action === 'get_dm') {
    $with_id = max(0, (int)($_GET['with_user_id'] ?? 0));
    $after_id = max(0, (int)($_GET['after_id'] ?? 0));
    if ($with_id === 0) { echo json_encode(['error' => 'Pengguna tidak valid']); exit; }

    updatePresence($kon, $user_id, $username, $nama, $role, $jabatan);

    if ($after_id > 0) {
        $stmt = $kon->prepare("SELECT id, from_user_id, to_user_id, from_nama, from_role, from_jabatan, message, is_read, attachment_path, attachment_name, attachment_type, attachment_size, created_at
                               FROM chat_dm
                               WHERE ((from_user_id=? AND to_user_id=?) OR (from_user_id=? AND to_user_id=?))
                               AND id > ?
                               ORDER BY id ASC LIMIT 50");
        $stmt->bind_param('iiiii', $user_id, $with_id, $with_id, $user_id, $after_id);
    } else {
        $limit = 60;
        $stmt = $kon->prepare("SELECT id, from_user_id, to_user_id, from_nama, from_role, from_jabatan, message, is_read, attachment_path, attachment_name, attachment_type, attachment_size, created_at
                               FROM chat_dm
                               WHERE ((from_user_id=? AND to_user_id=?) OR (from_user_id=? AND to_user_id=?))
                               ORDER BY id DESC LIMIT ?");
        $stmt->bind_param('iiiii', $user_id, $with_id, $with_id, $user_id, $limit);
    }
    $stmt->execute();
    $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if ($after_id === 0) $messages = array_reverse($messages);

    // Mark as read
    $kon->query("UPDATE chat_dm SET is_read=1 WHERE to_user_id=$user_id AND from_user_id=$with_id AND is_read=0");

    // Format messages
    $WEB_BASE = rtrim((isset($_SERVER['SCRIPT_NAME']) ? dirname($_SERVER['SCRIPT_NAME']) : ''), '/');
    $formatted = [];
    foreach ($messages as $m) {
        $isOwn = ((int)$m['from_user_id'] === $user_id);
        try {
            $dt = new DateTime($m['created_at']);
            $timeDisplay = $dt->format('H:i');
            $dateDisplay = $dt->format('d M Y');
        } catch (Exception $e) {
            $timeDisplay = '';
            $dateDisplay = '';
        }
        $formatted[] = [
            'id'              => (int)$m['id'],
            'from_user_id'    => (int)$m['from_user_id'],
            'to_user_id'      => (int)$m['to_user_id'],
            'from_nama'       => htmlspecialchars($m['from_nama']),
            'from_role'       => $m['from_role'],
            'from_jabatan'    => htmlspecialchars($m['from_jabatan']),
            'message'         => htmlspecialchars($m['message']),
            'is_own'          => $isOwn,
            'is_admin'        => in_array($m['from_role'], ['super_admin', 'admin']),
            'is_read'         => (bool)$m['is_read'],
            'attachment_path' => $m['attachment_path'],
            'attachment_name' => htmlspecialchars($m['attachment_name'] ?? ''),
            'attachment_type' => $m['attachment_type'],
            'attachment_size' => (int)($m['attachment_size'] ?? 0),
            'time_display'    => $timeDisplay,
            'date_display'    => $dateDisplay,
        ];
    }

    $lastId = empty($formatted) ? $after_id : end($formatted)['id'];
    echo json_encode([
        'messages'  => $formatted,
        'last_id'   => (int)$lastId,
        'my_id'     => $user_id,
        'with_id'   => $with_id,
    ]);
    exit;
}

// ---- ACTION: get_dm_unread_count ----
if ($action === 'get_dm_unread_count') {
    $rq = $kon->query("SELECT from_user_id, COUNT(*) c FROM chat_dm WHERE to_user_id=$user_id AND is_read=0 GROUP BY from_user_id");
    $unreadMap = [];
    $total = 0;
    if ($rq) {
        while ($ur = $rq->fetch_assoc()) {
            $unreadMap[(int)$ur['from_user_id']] = (int)$ur['c'];
            $total += (int)$ur['c'];
        }
    }
    echo json_encode(['unread' => $unreadMap, 'total' => $total]);
    exit;
}

// ---- ACTION: get_all_users (semua akun terdaftar + last seen) ----
if ($action === 'get_all_users') {
    updatePresence($kon, $user_id, $username, $nama, $role, $jabatan);

    // Ambil semua user aktif dari tabel users, join dengan chat_presence untuk last_seen
    $rq = $kon->query("
        SELECT
            u.id,
            u.Nama_Lengkap,
            u.username,
            u.role,
            u.Jabatan_Level,
            u.Status_Akun,
            p.last_seen
        FROM users u
        LEFT JOIN chat_presence p ON p.user_id = u.id
        WHERE u.Status_Akun IN ('Aktif', 'aktif', 'active', 'Active', '1', '')
           OR u.Status_Akun IS NULL
        ORDER BY
            CASE WHEN p.last_seen >= DATE_SUB(NOW(), INTERVAL 90 SECOND) THEN 0 ELSE 1 END ASC,
            p.last_seen DESC,
            u.Nama_Lengkap ASC
        LIMIT 200
    ");

    $users = [];
    $now = time();
    if ($rq) {
        while ($row = $rq->fetch_assoc()) {
            $uid      = (int)$row['id'];
            $lastSeen = $row['last_seen'];
            $isOnline = false;
            $lastSeenTs = null;
            $lastSeenLabel = null;

            if ($lastSeen) {
                $lastSeenTs = strtotime($lastSeen);
                $diff = $now - $lastSeenTs;
                $isOnline = ($diff <= 90);

                if (!$isOnline) {
                    if ($diff < 60)        $lastSeenLabel = 'Baru saja';
                    elseif ($diff < 3600)  $lastSeenLabel = 'Aktif ' . floor($diff / 60) . ' menit lalu';
                    elseif ($diff < 86400) $lastSeenLabel = 'Aktif ' . floor($diff / 3600) . ' jam lalu';
                    elseif ($diff < 172800) $lastSeenLabel = 'Aktif kemarin';
                    elseif ($diff < 604800) $lastSeenLabel = 'Aktif ' . floor($diff / 86400) . ' hari lalu';
                    elseif ($diff < 2592000) $lastSeenLabel = 'Aktif ' . floor($diff / 604800) . ' minggu lalu';
                    else  $lastSeenLabel = 'Aktif ' . date('d M Y', $lastSeenTs);
                }
            } else {
                $lastSeenLabel = 'Belum pernah online';
            }

            $jabatanVal = trim((string)$row['Jabatan_Level']);
            $users[] = [
                'user_id'        => $uid,
                'nama'           => htmlspecialchars($row['Nama_Lengkap'] ?: $row['username']),
                'role'           => $row['role'],
                'jabatan'        => htmlspecialchars($jabatanVal),
                'is_me'          => ($uid === $user_id),
                'is_admin'       => in_array($row['role'], ['super_admin', 'admin']),
                'is_online'      => $isOnline,
                'last_seen_label'=> $lastSeenLabel,
                'last_seen_ts'   => $lastSeenTs,
            ];
        }
    }

    // Hitung unread DM per sender
    $unreadRq = $kon->query("SELECT from_user_id, COUNT(*) c FROM chat_dm WHERE to_user_id=$user_id AND is_read=0 GROUP BY from_user_id");
    $unreadMap = [];
    if ($unreadRq) while ($ur = $unreadRq->fetch_assoc()) $unreadMap[(int)$ur['from_user_id']] = (int)$ur['c'];
    foreach ($users as &$u) $u['unread_dm'] = $unreadMap[$u['user_id']] ?? 0;
    unset($u);

    // Hitung jumlah online
    $onlineCnt = array_reduce($users, fn($c, $u) => $c + ($u['is_online'] ? 1 : 0), 0);

    echo json_encode(['users' => $users, 'my_id' => $user_id, 'online_count' => $onlineCnt]);
    exit;
}

echo json_encode(['error' => 'Unknown action']);
