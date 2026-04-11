<?php
/**
 * ticket_stream.php (user)
 * Server-Sent Events: notifies client when user's tickets change
 * (new status, new ticket, etc.) using a fingerprint-based approach.
 */
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit();
}

$rawRole = isset($_SESSION['role']) ? (string) $_SESSION['role'] : 'user';
$role = strtolower(trim($rawRole));
if ($role === 'super_admin' || $role === 'admin') {
    http_response_code(403);
    exit();
}

$Id_Karyawan = isset($_SESSION['Id_Karyawan']) && (string) $_SESSION['Id_Karyawan'] !== ''
    ? (string) $_SESSION['Id_Karyawan']
    : (isset($_SESSION['username']) ? (string) $_SESSION['username'] : '');
$username = isset($_SESSION['username']) ? (string) $_SESSION['username'] : '';

if ($Id_Karyawan === '' || $username === '') {
    http_response_code(401);
    exit();
}

// IMPORTANT: Release PHP session lock immediately so other requests
// (AJAX tab switching etc.) are NOT blocked while this SSE stream runs.
session_write_close();

require_once __DIR__ . '/../koneksi.php';

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');

if (ob_get_level()) {
    ob_end_clean();
}

// Get initial fingerprint of user's tickets
function getUserTicketFingerprint(mysqli $kon, string $idKaryawan, string $uname): string {
    $statusCounts = [];
    $stmt = $kon->prepare(
        'SELECT `Status_Request`, COUNT(*) AS cnt FROM `ticket`
         WHERE `Id_Karyawan` = ? AND `Create_By_User` = ?
         GROUP BY `Status_Request`'
    );
    if ($stmt) {
        $stmt->bind_param('ss', $idKaryawan, $uname);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            if ($res) {
                while ($r = $res->fetch_assoc()) {
                    $statusCounts[(string)$r['Status_Request']] = (int)$r['cnt'];
                }
            }
        }
        $stmt->close();
    }
    ksort($statusCounts);
    return md5(json_encode($statusCounts));
}

$lastFingerprint = getUserTicketFingerprint($kon, $Id_Karyawan, $username);
$startTime = time();
$maxRunTime = 25; // seconds before reconnect
$pollInterval = 1; // seconds between DB checks

// Initial connected event
echo "event: connected\n";
echo "data: {\"status\":\"ok\"}\n\n";
flush();

while (true) {
    if (connection_aborted()) {
        break;
    }

    $elapsed = time() - $startTime;
    if ($elapsed >= $maxRunTime) {
        echo "event: ping\n";
        echo "data: {\"t\":" . time() . "}\n\n";
        flush();
        break;
    }

    sleep($pollInterval);

    if (connection_aborted()) {
        break;
    }

    $newFingerprint = getUserTicketFingerprint($kon, $Id_Karyawan, $username);

    if ($newFingerprint !== $lastFingerprint) {
        $lastFingerprint = $newFingerprint;
        echo "event: dashboard_update\n";
        echo "data: {\"t\":" . time() . "}\n\n";
        flush();
    } else {
        // Keep-alive ping every ~5 seconds
        if ($elapsed % 5 === 0) {
            echo ": ping\n\n";
            flush();
        }
    }
}
