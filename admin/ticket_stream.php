<?php
/**
 * SSE Stream: Realtime Ticket Dashboard Notifications (Admin)
 *
 * Upgraded from the original ticket_stream.php to detect ANY ticket change
 * (new tickets, status updates, closures, rejections, etc.) using a
 * fingerprint of the aggregate status counts — not just MAX(Ticket_code).
 *
 * Events emitted:
 *   hello            → connection established, includes server fingerprint
 *   dashboard_update → fingerprint changed (something in ticket table changed)
 *   timeout          → stream ended (browser will auto-reconnect via SSE)
 *   error            → DB error occurred
 */

session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}
$user_role = isset($_SESSION['role']) ? (string)$_SESSION['role'] : 'user';
if ($user_role !== 'super_admin') {
    http_response_code(403);
    exit;
}
// IMPORTANT: Release PHP session lock immediately so other requests
// (AJAX tab switching etc.) are NOT blocked while this SSE stream runs.
session_write_close();

require_once __DIR__ . '/../koneksi.php';

// --- SSE headers ---
header('Content-Type: text/event-stream; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');  // Disable nginx proxy buffering

@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', '0');
@ini_set('implicit_flush', '1');
while (ob_get_level() > 0) { @ob_end_flush(); }
@ob_implicit_flush(true);

// --- Helpers ---
function sse_event(string $event, array $payload): void {
    $json = str_replace("\r", '', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    echo "event: {$event}\n";
    foreach (explode("\n", $json) as $line) {
        echo "data: {$line}\n";
    }
    echo "\n";
    @flush();
}

function sse_ping(): void {
    echo ": ping " . time() . "\n\n";
    @flush();
}

/**
 * Computes a fingerprint that captures:
 *  - Total ticket count
 *  - Max Ticket_code (new tickets)
 *  - Count per each status (status changes, closures, etc.)
 *
 * Returns null on DB error.
 */
function get_ticket_fingerprint(mysqli $kon): ?string {
    $res = @$kon->query(
        'SELECT
            COUNT(*) AS total,
            COALESCE(MAX(`Ticket_code`), 0) AS max_code,
            SUM(CASE WHEN `Status_Request` = "Open"        THEN 1 ELSE 0 END) AS c_open,
            SUM(CASE WHEN `Status_Request` = "In Progress" THEN 1 ELSE 0 END) AS c_ip,
            SUM(CASE WHEN `Status_Request` = "Done"        THEN 1 ELSE 0 END) AS c_done,
            SUM(CASE WHEN `Status_Request` = "Closed"      THEN 1 ELSE 0 END) AS c_closed,
            SUM(CASE WHEN `Status_Request` = "Reject"      THEN 1 ELSE 0 END) AS c_reject,
            SUM(CASE WHEN `Status_Request` = "Review"      THEN 1 ELSE 0 END) AS c_review
         FROM `ticket`'
    );
    if ($res === false) return null;
    $row = $res->fetch_assoc();
    $res->free();
    if (!$row) return null;

    return md5(
        (string)($row['total']    ?? 0) . '|' .
        (string)($row['max_code'] ?? 0) . '|' .
        (string)($row['c_open']   ?? 0) . '|' .
        (string)($row['c_ip']     ?? 0) . '|' .
        (string)($row['c_done']   ?? 0) . '|' .
        (string)($row['c_closed'] ?? 0) . '|' .
        (string)($row['c_reject'] ?? 0) . '|' .
        (string)($row['c_review'] ?? 0)
    );
}

// --- Compute initial fingerprint when SSE connection is established ---
$initialFingerprint = get_ticket_fingerprint($kon);
if ($initialFingerprint === null) {
    sse_event('error', ['ok' => false, 'message' => 'DB error on connect: ' . (string)$kon->error]);
    exit;
}

sse_event('hello', [
    'ok'          => true,
    'server_time' => date('c'),
    'fingerprint' => $initialFingerprint,
]);

// --- Poll loop ---
$timeoutSec   = 25;      // max stream lifetime before forcing reconnect
$pollIntervalUs = 900000;  // 0.9s between polls
$start        = time();
$lastPing     = 0;

while (true) {
    if (connection_aborted()) break;

    $elapsed = time() - $start;

    // Force reconnect after timeout (browser auto-reconnects, picks up new baseline)
    if ($elapsed >= $timeoutSec) {
        sse_event('timeout', ['ok' => true]);
        break;
    }

    // Keep-alive ping every ~10s to prevent proxy timeouts
    if ($elapsed - $lastPing >= 10) {
        $lastPing = $elapsed;
        sse_ping();
    }

    try {
        $currentFingerprint = get_ticket_fingerprint($kon);
        if ($currentFingerprint === null) {
            sse_event('error', ['ok' => false, 'message' => 'DB error: ' . (string)$kon->error]);
            break;
        }

        // Any change detected → notify client, client will call api_ticket_stats.php
        if ($currentFingerprint !== $initialFingerprint) {
            sse_event('dashboard_update', [
                'ok'          => true,
                'fingerprint' => $currentFingerprint,
            ]);
            break; // Browser auto-reconnects; next cycle picks new baseline
        }
    } catch (Throwable $e) {
        sse_event('error', ['ok' => false, 'message' => 'Exception: ' . $e->getMessage()]);
        break;
    }

    @usleep($pollIntervalUs);
}
