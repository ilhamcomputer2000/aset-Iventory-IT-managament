<?php
// SSE stream endpoint: notifies admin/ticket.php when new tickets are created.
// PHP-only, works on VPS/cPanel with Apache when buffering/compression is disabled.

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

require_once __DIR__ . '/../koneksi.php';

// SSE headers
header('Content-Type: text/event-stream; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

// Best-effort: disable buffering/compression
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', '0');
@ini_set('implicit_flush', '1');
while (ob_get_level() > 0) {
    @ob_end_flush();
}
@ob_implicit_flush(true);

// Helpers
function sse_send_event(string $event, string $data): void {
    // Data must not contain CR; keep it one-line JSON.
    $data = str_replace(["\r"], '', $data);
    echo "event: {$event}\n";
    // Split by \n for SSE multi-line support
    $lines = explode("\n", $data);
    foreach ($lines as $line) {
        echo 'data: ' . $line . "\n";
    }
    echo "\n";
    @flush();
}

function sse_keepalive(): void {
    echo ": keep-alive " . time() . "\n\n";
    @flush();
}

$last = isset($_GET['last']) ? (int)$_GET['last'] : 0;
if ($last < 0) {
    $last = 0;
}

// Short-lived stream to avoid holding PHP-FPM workers too long.
$timeoutSeconds = 25;
$pollIntervalUs = 900000; // 0.9s
$start = time();
$lastKeepAlive = 0;

// Initial hello
sse_send_event('hello', json_encode([
    'ok' => true,
    'server_time' => date('c'),
    'last' => $last,
], JSON_UNESCAPED_SLASHES));

while (true) {
    if (connection_aborted()) {
        break;
    }

    $elapsed = time() - $start;
    if ($elapsed >= $timeoutSeconds) {
        // Let browser reconnect.
        sse_send_event('timeout', json_encode(['ok' => true], JSON_UNESCAPED_SLASHES));
        break;
    }

    // Keep-alive every ~10s to reduce proxy timeouts
    if ($elapsed - $lastKeepAlive >= 10) {
        $lastKeepAlive = $elapsed;
        sse_keepalive();
    }

    try {
        $res = @$kon->query('SELECT MAX(`Ticket_code`) AS max_code, COUNT(*) AS total FROM `ticket`');
        if ($res === false) {
            sse_send_event('error', json_encode([
                'ok' => false,
                'message' => 'DB error: ' . (string)$kon->error,
            ], JSON_UNESCAPED_SLASHES));
            break;
        }
        $row = $res->fetch_assoc();
        $res->free();

        $maxCode = isset($row['max_code']) ? (int)$row['max_code'] : 0;
        $total = isset($row['total']) ? (int)$row['total'] : 0;

        if ($maxCode > $last) {
            sse_send_event('ticket_max', json_encode([
                'ok' => true,
                'max_code' => $maxCode,
                'total' => $total,
            ], JSON_UNESCAPED_SLASHES));
            break;
        }
    } catch (Throwable $e) {
        sse_send_event('error', json_encode([
            'ok' => false,
            'message' => 'Exception: ' . get_class($e) . ' ' . (string)$e->getMessage(),
        ], JSON_UNESCAPED_SLASHES));
        break;
    }

    @usleep($pollIntervalUs);
}
