<?php
/**
 * chat.php — General Chat + Direct Message AJAX Endpoint
 * Actions: get | send | edit | delete_msg | online | get_reads | get_online_users | send_dm | get_dm
 */
session_start();
require_once __DIR__ . '/koneksi.php';

header('Content-Type: application/json; charset=utf-8');

$_action = trim((string)($_GET['action'] ?? $_POST['action'] ?? ''));

// ---- ACTION: go_offline (harus diproses SEBELUM session check) ----
// Token HMAC statis berbasis user_id + server secret — TIDAK bergantung session_id()
// agar bisa bekerja walau session sudah dihancurkan oleh logout.php
define('CW_OFFLINE_SECRET', 'cw_offline_s3cr3t_2025_aset_it');

if ($_action === 'go_offline') {
    $cw_uid   = (int)($_POST['cw_uid'] ?? 0);
    $cw_token = (string)($_POST['cw_token'] ?? '');
    $deleted  = false;

    if ($cw_uid > 0 && strlen($cw_token) >= 32) {
        // Validasi token dalam 5 window waktu (50 menit toleransi) untuk akomodasi network delay hosting
        $tNow = (int)floor(time() / 600);
        // Token hanya menggunakan user_id — TIDAK pakai session_id() agar konsisten di hosting
        foreach ([$tNow, $tNow - 1, $tNow - 2, $tNow + 1, $tNow + 2] as $tWindow) {
            $expected = hash_hmac('sha256', $cw_uid . '|' . $tWindow, CW_OFFLINE_SECRET);
            if (hash_equals($expected, $cw_token)) {
                // UPDATE bukan DELETE agar last_seen tetap ada (tampil "Aktif X menit lalu", bukan "Belum pernah online")
                $kon->query("UPDATE chat_presence SET last_seen = DATE_SUB(NOW(), INTERVAL 95 SECOND) WHERE user_id = $cw_uid");
                $deleted = true;
                break;
            }
        }
    }

    // Fallback: jika session masih aktif dan valid, percayai saja
    if (!$deleted && isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === $cw_uid && $cw_uid > 0) {
        $kon->query("UPDATE chat_presence SET last_seen = DATE_SUB(NOW(), INTERVAL 95 SECOND) WHERE user_id = $cw_uid");
        $deleted = true;
    }

    echo json_encode(['ok' => $deleted]);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}


$user_id  = (int)$_SESSION['user_id'];
$username = (string)($_SESSION['username'] ?? 'Unknown');
$nama     = (string)($_SESSION['Nama_Lengkap'] ?? $username);
$role     = (string)($_SESSION['role'] ?? 'user');
$jabatan  = (string)($_SESSION['Jabatan_Level'] ?? '');
$action   = $_action; // sudah didefinisikan sebelum session check

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

// Upgrade chat_dm columns
foreach ([
    "ALTER TABLE chat_dm ADD COLUMN IF NOT EXISTS read_at DATETIME NULL",
    "ALTER TABLE chat_dm ADD COLUMN IF NOT EXISTS reply_to_id INT NULL",
] as $sql) @$kon->query($sql);

// DM Reactions table
$kon->query("CREATE TABLE IF NOT EXISTS chat_dm_reactions (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    dm_id      INT NOT NULL,
    user_id    INT NOT NULL,
    emoji      VARCHAR(10) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_react (dm_id, user_id),
    INDEX idx_dm (dm_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Call Signals table (WebRTC signaling via polling)
$kon->query("CREATE TABLE IF NOT EXISTS chat_call_signals (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    from_user_id    INT NOT NULL,
    to_user_id      INT NOT NULL,
    type            VARCHAR(20) NOT NULL,
    data            MEDIUMTEXT NOT NULL DEFAULT '{}',
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_to    (to_user_id, created_at),
    INDEX idx_pair  (from_user_id, to_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
// Auto-purge signals older than 60 seconds
@$kon->query("DELETE FROM chat_call_signals WHERE created_at < DATE_SUB(NOW(), INTERVAL 60 SECOND)");

// Call Logs table (riwayat panggilan)
$kon->query("CREATE TABLE IF NOT EXISTS chat_call_logs (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    call_uid        VARCHAR(64) NOT NULL UNIQUE,
    caller_id       INT NOT NULL,
    caller_nama     VARCHAR(150) NOT NULL DEFAULT '',
    callee_id       INT NOT NULL,
    callee_nama     VARCHAR(150) NOT NULL DEFAULT '',
    call_type       ENUM('audio','video') NOT NULL DEFAULT 'audio',
    status          ENUM('initiated','answered','rejected','missed','cancelled') NOT NULL DEFAULT 'initiated',
    duration_sec    INT NOT NULL DEFAULT 0,
    started_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    answered_at     DATETIME NULL,
    ended_at        DATETIME NULL,
    INDEX idx_caller (caller_id, started_at),
    INDEX idx_callee (callee_id, started_at),
    INDEX idx_uid    (call_uid)
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
    $rq       = $kon->query("SELECT message_id, user_id, nama_lengkap, read_at FROM chat_message_reads WHERE message_id IN ($ownStr) ORDER BY read_at ASC");
    $readsMap = [];
    if ($rq) {
        while ($rr = $rq->fetch_assoc()) {
            $mid = (int)$rr['message_id'];
            $readAtFmt = null;
            if ($rr['read_at']) {
                try { $dt = new DateTime($rr['read_at']); $readAtFmt = $dt->format('H:i, d M Y'); } catch (Exception $e) {}
            }
            $readsMap[$mid]['readers'][] = [
                'user_id' => (int)$rr['user_id'],
                'name'    => htmlspecialchars($rr['nama_lengkap']),
                'read_at' => $readAtFmt,
            ];
            $readsMap[$mid]['names'][] = htmlspecialchars($rr['nama_lengkap']);
        }
    }
    $out = [];
    foreach ($ownIds as $mid) {
        $out[$mid] = [
            'count'   => count($readsMap[$mid]['readers'] ?? []),
            'names'   => $readsMap[$mid]['names'] ?? [],
            'readers' => $readsMap[$mid]['readers'] ?? [],
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
    $to_id      = max(0, (int)($_POST['to_user_id'] ?? 0));
    $message    = trim((string)($_POST['message'] ?? ''));
    $reply_to   = max(0, (int)($_POST['reply_to_id'] ?? 0));
    if ($to_id === 0 || ($message === '' && empty($_FILES['attachment']))) {
        echo json_encode(['error' => 'Data tidak valid']); exit;
    }
    if ($to_id === $user_id) { echo json_encode(['error' => 'Tidak bisa kirim pesan ke diri sendiri']); exit; }

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
    $reply_to_val = $reply_to > 0 ? $reply_to : null;
    $stmt = $kon->prepare("INSERT INTO chat_dm (from_user_id,to_user_id,from_nama,from_role,from_jabatan,message,reply_to_id,attachment_path,attachment_name,attachment_type,attachment_size) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->bind_param('iissssisssi', $user_id, $to_id, $nama, $role, $jabatan, $message, $reply_to_val, $att_path, $att_name, $att_type, $att_size);

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
    $with_id  = max(0, (int)($_GET['with_user_id'] ?? 0));
    $after_id = max(0, (int)($_GET['after_id'] ?? 0));
    if ($with_id === 0) { echo json_encode(['error' => 'Pengguna tidak valid']); exit; }

    updatePresence($kon, $user_id, $username, $nama, $role, $jabatan);

    if ($after_id > 0) {
        $stmt = $kon->prepare("SELECT id, from_user_id, to_user_id, from_nama, from_role, from_jabatan, message, is_read, read_at, reply_to_id, attachment_path, attachment_name, attachment_type, attachment_size, created_at
                               FROM chat_dm
                               WHERE ((from_user_id=? AND to_user_id=?) OR (from_user_id=? AND to_user_id=?))
                               AND id > ? ORDER BY id ASC LIMIT 50");
        $stmt->bind_param('iiiii', $user_id, $with_id, $with_id, $user_id, $after_id);
    } else {
        $limit = 60;
        $stmt = $kon->prepare("SELECT id, from_user_id, to_user_id, from_nama, from_role, from_jabatan, message, is_read, read_at, reply_to_id, attachment_path, attachment_name, attachment_type, attachment_size, created_at
                               FROM chat_dm
                               WHERE ((from_user_id=? AND to_user_id=?) OR (from_user_id=? AND to_user_id=?))
                               ORDER BY id DESC LIMIT ?");
        $stmt->bind_param('iiiii', $user_id, $with_id, $with_id, $user_id, $limit);
    }
    $stmt->execute();
    $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    if ($after_id === 0) $messages = array_reverse($messages);

    // Mark incoming as read (preserve original read_at)
    $kon->query("UPDATE chat_dm SET is_read=1, read_at=IF(read_at IS NULL,NOW(),read_at) WHERE to_user_id=$user_id AND from_user_id=$with_id AND is_read=0");

    // Format messages
    $formatted = [];
    foreach ($messages as $m) {
        $isOwn = ((int)$m['from_user_id'] === $user_id);
        try { $dt = new DateTime($m['created_at']); $timeDisplay = $dt->format('H:i'); $dateDisplay = $dt->format('d M Y'); }
        catch (Exception $e) { $timeDisplay = ''; $dateDisplay = ''; }
        $readAtDisplay = null;
        if ($isOwn && $m['is_read'] && $m['read_at']) {
            try { $rdt = new DateTime($m['read_at']); $readAtDisplay = $rdt->format('H:i'); } catch (Exception $e) {}
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
            'read_at_display' => $readAtDisplay,
            'reply_to_id'     => (int)($m['reply_to_id'] ?? 0),
            'attachment_path' => $m['attachment_path'],
            'attachment_name' => htmlspecialchars($m['attachment_name'] ?? ''),
            'attachment_type' => $m['attachment_type'],
            'attachment_size' => (int)($m['attachment_size'] ?? 0),
            'time_display'    => $timeDisplay,
            'date_display'    => $dateDisplay,
            'reactions'       => [],
            'reply_snippet'   => null,
        ];
    }

    // Fetch reply snippets
    foreach ($formatted as &$m) {
        if ($m['reply_to_id'] > 0) {
            $rid = $m['reply_to_id'];
            $rq2 = $kon->query("SELECT from_user_id, from_nama, message, attachment_name FROM chat_dm WHERE id=$rid LIMIT 1");
            if ($rq2 && ($rr2 = $rq2->fetch_assoc())) {
                $m['reply_snippet'] = [
                    'name'     => htmlspecialchars($rr2['from_nama']),
                    'text'     => htmlspecialchars(mb_substr($rr2['message'], 0, 80)),
                    'has_file' => !empty($rr2['attachment_name']),
                    'file'     => htmlspecialchars($rr2['attachment_name'] ?? ''),
                    'is_mine'  => ((int)$rr2['from_user_id'] === $user_id),
                ];
            }
        }
    } unset($m);

    // Fetch reactions
    if (!empty($formatted)) {
        $allIds = implode(',', array_map('intval', array_column($formatted, 'id')));
        $reactRq = $kon->query("SELECT dm_id, emoji, COUNT(*) cnt, GROUP_CONCAT(user_id) uids FROM chat_dm_reactions WHERE dm_id IN ($allIds) GROUP BY dm_id, emoji");
        $reactMap = [];
        if ($reactRq) while ($rr = $reactRq->fetch_assoc()) {
            $reactMap[(int)$rr['dm_id']][] = ['emoji'=>$rr['emoji'],'count'=>(int)$rr['cnt'],'mine'=>in_array((string)$user_id,explode(',',$rr['uids']))];
        }
        foreach ($formatted as &$m) { $m['reactions'] = $reactMap[$m['id']] ?? []; } unset($m);
    }

    $lastId = empty($formatted) ? $after_id : end($formatted)['id'];
    echo json_encode(['messages'=>$formatted,'last_id'=>(int)$lastId,'my_id'=>$user_id,'with_id'=>$with_id]);
    exit;
}

// ---- ACTION: react_dm ----
if ($action === 'react_dm') {
    $dm_id = max(0, (int)($_POST['dm_id'] ?? 0));
    $emoji = trim((string)($_POST['emoji'] ?? ''));
    if ($dm_id === 0 || $emoji === '') { echo json_encode(['error' => 'Invalid']); exit; }
    $chk = $kon->prepare("SELECT emoji FROM chat_dm_reactions WHERE dm_id=? AND user_id=? LIMIT 1");
    $chk->bind_param('ii', $dm_id, $user_id); $chk->execute();
    $chkRes = $chk->get_result();
    $existingEmoji = $chkRes->num_rows > 0 ? $chkRes->fetch_assoc()['emoji'] : null;
    $chk->close();
    if ($existingEmoji !== null && $existingEmoji === $emoji) {
        $del = $kon->prepare("DELETE FROM chat_dm_reactions WHERE dm_id=? AND user_id=?");
        $del->bind_param('ii', $dm_id, $user_id); $del->execute(); $del->close();
        echo json_encode(['success'=>true,'action'=>'removed']);
    } else {
        $e = $kon->real_escape_string($emoji);
        $kon->query("INSERT INTO chat_dm_reactions (dm_id,user_id,emoji) VALUES ($dm_id,$user_id,'$e') ON DUPLICATE KEY UPDATE emoji='$e',created_at=NOW()");
        echo json_encode(['success'=>true,'action'=>$existingEmoji!==null?'updated':'added']);
    }
    exit;
}

// ---- ACTION: get_dm_receipts ----
if ($action === 'get_dm_receipts') {
    $rawIds = (string)($_GET['ids'] ?? '');
    $ids = array_filter(array_map('intval', explode(',', $rawIds)));
    if (empty($ids)) { echo json_encode(['receipts'=>[]]); exit; }
    $idsStr = implode(',', $ids);
    $rq = $kon->query("SELECT id, is_read, read_at FROM chat_dm WHERE id IN ($idsStr) AND from_user_id=$user_id");
    $out = [];
    if ($rq) while ($rr = $rq->fetch_assoc()) {
        $rat = null;
        if ($rr['is_read'] && $rr['read_at']) { try { $dt=new DateTime($rr['read_at']); $rat=$dt->format('H:i'); } catch(Exception $e){} }
        $out[(int)$rr['id']] = ['is_read'=>(bool)$rr['is_read'],'read_at_display'=>$rat];
    }
    echo json_encode(['receipts'=>$out]);
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
    // Gunakan TIMESTAMPDIFF di MySQL agar tidak ada timezone mismatch antara PHP dan MySQL
    $rq = $kon->query("
        SELECT
            u.id,
            u.Nama_Lengkap,
            u.username,
            u.role,
            u.Jabatan_Level,
            u.Status_Akun,
            p.last_seen,
            CASE WHEN p.last_seen IS NOT NULL AND TIMESTAMPDIFF(SECOND, p.last_seen, NOW()) <= 90 THEN 1 ELSE 0 END AS is_online_mysql,
            CASE WHEN p.last_seen IS NOT NULL THEN TIMESTAMPDIFF(SECOND, p.last_seen, NOW()) ELSE NULL END AS seconds_ago
        FROM users u
        LEFT JOIN chat_presence p ON p.user_id = u.id
        WHERE u.Status_Akun IN ('Aktif', 'aktif', 'active', 'Active', '1', '')
           OR u.Status_Akun IS NULL
        ORDER BY
            CASE WHEN p.last_seen IS NOT NULL AND TIMESTAMPDIFF(SECOND, p.last_seen, NOW()) <= 90 THEN 0 ELSE 1 END ASC,
            p.last_seen DESC,
            u.Nama_Lengkap ASC
        LIMIT 200
    ");

    $users = [];
    if ($rq) {
        while ($row = $rq->fetch_assoc()) {
            $uid        = (int)$row['id'];
            $lastSeen   = $row['last_seen'];
            // Gunakan hasil kalkulasi MySQL (TIMESTAMPDIFF) bukan PHP time() untuk hindari timezone mismatch
            $isOnline   = (isset($row['is_online_mysql']) && (int)$row['is_online_mysql'] === 1);
            $lastSeenTs = null;
            $lastSeenLabel = null;

            if ($lastSeen && isset($row['seconds_ago'])) {
                $diff = (int)$row['seconds_ago'];
                $lastSeenTs = strtotime($lastSeen);

                if (!$isOnline) {
                    if ($diff < 60)         $lastSeenLabel = 'Baru saja';
                    elseif ($diff < 3600)   $lastSeenLabel = 'Aktif ' . floor($diff / 60) . ' menit lalu';
                    elseif ($diff < 86400)  $lastSeenLabel = 'Aktif ' . floor($diff / 3600) . ' jam lalu';
                    elseif ($diff < 172800) $lastSeenLabel = 'Aktif kemarin';
                    elseif ($diff < 604800) $lastSeenLabel = 'Aktif ' . floor($diff / 86400) . ' hari lalu';
                    elseif ($diff < 2592000) $lastSeenLabel = 'Aktif ' . floor($diff / 604800) . ' minggu lalu';
                    else                    $lastSeenLabel = 'Aktif ' . ($lastSeenTs ? date('d M Y', $lastSeenTs) : '-');
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

// ---- ACTION: go_offline (beacon saat user tutup/tinggalkan halaman) ----
if ($action === 'go_offline') {
    // Geser last_seen ke luar window online — TIDAK DELETE agar "Aktif X menit lalu" tetap tampil
    $kon->query("UPDATE chat_presence SET last_seen = DATE_SUB(NOW(), INTERVAL 95 SECOND) WHERE user_id = $user_id");
    echo json_encode(['ok' => true]);
    exit;
}

// ---- ACTION: call_signal ----
if ($action === 'call_signal') {
    $to_id = max(0, (int)($_POST['to_user_id'] ?? 0));
    $type  = trim((string)($_POST['type'] ?? ''));
    $data  = trim((string)($_POST['data'] ?? '{}'));
    $allowed = ['offer','answer','ice','reject','end'];
    if ($to_id === 0 || !in_array($type, $allowed)) {
        echo json_encode(['error' => 'Invalid']); exit;
    }
    $dataEsc = $kon->real_escape_string($data);
    $typeEsc = $kon->real_escape_string($type);
    $kon->query("INSERT INTO chat_call_signals (from_user_id,to_user_id,type,data) VALUES ($user_id,$to_id,'$typeEsc','$dataEsc')");
    echo json_encode(['success' => true]);
    exit;
}

// ---- ACTION: get_call_signals ----
if ($action === 'get_call_signals') {
    $after_id = max(0, (int)($_GET['after_id'] ?? 0));
    $rq = $kon->query("
        SELECT cs.id, cs.from_user_id, cs.type, cs.data,
               COALESCE(u.Nama_Lengkap, u.username, 'Pengguna') AS from_name
        FROM chat_call_signals cs
        JOIN users u ON u.id = cs.from_user_id
        WHERE cs.to_user_id = $user_id AND cs.id > $after_id
        ORDER BY cs.id ASC LIMIT 30"
    );
    $signals = [];
    if ($rq) while ($r = $rq->fetch_assoc()) {
        $signals[] = [
            'id'           => (int)$r['id'],
            'from_user_id' => (int)$r['from_user_id'],
            'from_name'    => htmlspecialchars($r['from_name']),
            'type'         => $r['type'],
            'data'         => $r['data'],
        ];
    }
    echo json_encode(['signals' => $signals]);
    exit;
}

// ---- ACTION: log_call ----
// Dipanggil dari JS saat berbagai event panggilan terjadi
if ($action === 'log_call') {
    $call_uid    = trim((string)($_POST['call_uid']    ?? ''));
    $callee_id   = max(0, (int)($_POST['callee_id']    ?? 0));
    $call_type   = trim((string)($_POST['call_type']   ?? 'audio'));
    $call_status = trim((string)($_POST['call_status'] ?? 'initiated'));
    $duration    = max(0, (int)($_POST['duration_sec'] ?? 0));

    if ($call_uid === '' || $callee_id === 0) {
        echo json_encode(['error' => 'Invalid']); exit;
    }
    if (!in_array($call_type, ['audio', 'video'])) $call_type = 'audio';
    if (!in_array($call_status, ['initiated', 'answered', 'rejected', 'missed', 'cancelled'])) $call_status = 'initiated';

    // Resolve callee name
    $cnq = $kon->query("SELECT COALESCE(Nama_Lengkap, username, 'Pengguna') AS n FROM users WHERE id=$callee_id LIMIT 1");
    $callee_nama = ($cnq && ($cnr = $cnq->fetch_assoc())) ? $cnr['n'] : 'Pengguna';

    $call_uid_e    = $kon->real_escape_string($call_uid);
    $call_type_e   = $kon->real_escape_string($call_type);
    $call_status_e = $kon->real_escape_string($call_status);
    $caller_nama_e = $kon->real_escape_string($nama);
    $callee_nama_e = $kon->real_escape_string($callee_nama);

    // answered_at / ended_at logic
    $answeredSql = '';
    $endedSql    = '';
    if ($call_status === 'answered') {
        $answeredSql = ", answered_at = IF(answered_at IS NULL, NOW(), answered_at)";
    }
    if (in_array($call_status, ['answered', 'rejected', 'missed', 'cancelled'])) {
        $endedSql = ", ended_at = IF(ended_at IS NULL, NOW(), ended_at)";
    }

    $kon->query("INSERT INTO chat_call_logs
        (call_uid, caller_id, caller_nama, callee_id, callee_nama, call_type, status, duration_sec, started_at)
        VALUES ('$call_uid_e', $user_id, '$caller_nama_e', $callee_id, '$callee_nama_e', '$call_type_e', '$call_status_e', $duration, NOW())
        ON DUPLICATE KEY UPDATE
            status = CASE
                WHEN status = 'initiated' THEN '$call_status_e'
                WHEN status = 'answered'  AND '$call_status_e' IN ('missed','rejected','cancelled') THEN status
                ELSE '$call_status_e'
            END,
            duration_sec = GREATEST(duration_sec, $duration)
            $answeredSql
            $endedSql
    ");

    echo json_encode(['ok' => true]);
    exit;
}

// ---- ACTION: get_call_history ----
if ($action === 'get_call_history') {
    $limit  = min(50, max(1, (int)($_GET['limit'] ?? 30)));
    $offset = max(0, (int)($_GET['offset'] ?? 0));

    $rq = $kon->query("
        SELECT cl.*,
               TIMESTAMPDIFF(SECOND, cl.started_at, IFNULL(cl.ended_at, NOW())) AS age_sec
        FROM chat_call_logs cl
        WHERE cl.caller_id = $user_id OR cl.callee_id = $user_id
        ORDER BY cl.started_at DESC
        LIMIT $limit OFFSET $offset
    ");

    $rows = [];
    if ($rq) {
        while ($r = $rq->fetch_assoc()) {
            $isOutgoing = ((int)$r['caller_id'] === $user_id);
            $peerName   = $isOutgoing ? htmlspecialchars($r['callee_nama']) : htmlspecialchars($r['caller_nama']);
            $peerId     = $isOutgoing ? (int)$r['callee_id'] : (int)$r['caller_id'];

            // Format started_at
            $startedLabel = '';
            try {
                $dt = new DateTime($r['started_at']);
                $today  = new DateTime(); $today->setTime(0,0,0);
                $yest   = (clone $today)->modify('-1 day');
                $dtDate = (clone $dt)->setTime(0,0,0);
                if ($dtDate == $today)      $startedLabel = 'Hari ini ' . $dt->format('H:i');
                elseif ($dtDate == $yest)   $startedLabel = 'Kemarin ' . $dt->format('H:i');
                else                        $startedLabel = $dt->format('d M Y, H:i');
            } catch (Exception $e) { $startedLabel = $r['started_at']; }

            // Duration label
            $dur = (int)$r['duration_sec'];
            $durLabel = '';
            if ($dur > 0) {
                if ($dur < 60) $durLabel = $dur . ' dtk';
                else           $durLabel = floor($dur/60) . ' mnt ' . ($dur%60) . ' dtk';
            }

            // Status visual
            $status = $r['status'];
            if (!$isOutgoing && $status === 'missed') $statusLabel = 'Tidak Diangkat';
            elseif (!$isOutgoing && $status === 'rejected') $statusLabel = 'Ditolak';
            elseif (!$isOutgoing && $status === 'initiated') $statusLabel = 'Panggilan Masuk';
            elseif (!$isOutgoing && $status === 'answered') $statusLabel = 'Diterima';
            elseif ($isOutgoing && $status === 'cancelled') $statusLabel = 'Dibatalkan';
            elseif ($isOutgoing && $status === 'rejected')  $statusLabel = 'Ditolak';
            elseif ($isOutgoing && $status === 'missed')    $statusLabel = 'Tidak Terjawab';
            elseif ($isOutgoing && $status === 'answered')  $statusLabel = 'Terjawab';
            elseif ($isOutgoing && $status === 'initiated') $statusLabel = 'Memanggil';
            else $statusLabel = $status;

            $rows[] = [
                'id'           => (int)$r['id'],
                'call_uid'     => $r['call_uid'],
                'peer_id'      => $peerId,
                'peer_name'    => $peerName,
                'call_type'    => $r['call_type'],
                'status'       => $status,
                'status_label' => $statusLabel,
                'direction'    => $isOutgoing ? 'outgoing' : 'incoming',
                'duration_sec' => $dur,
                'duration_label' => $durLabel,
                'started_at'   => $r['started_at'],
                'started_label' => $startedLabel,
            ];
        }
    }

    // Count total
    $countRq = $kon->query("SELECT COUNT(*) c FROM chat_call_logs WHERE caller_id=$user_id OR callee_id=$user_id");
    $total   = ($countRq && ($cr = $countRq->fetch_assoc())) ? (int)$cr['c'] : 0;

    echo json_encode(['logs' => $rows, 'total' => $total, 'my_id' => $user_id]);
    exit;
}

echo json_encode(['error' => 'Unknown action']);
