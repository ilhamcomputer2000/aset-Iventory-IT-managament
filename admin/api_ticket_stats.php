<?php
/**
 * API: Realtime Ticket Dashboard Stats (Admin)
 * Returns status counts, priority counts, recent tickets, avg response time, and a fingerprint.
 * Designed to be polled by the JS realtime module in admin/ticket.php.
 */
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}
$user_role = isset($_SESSION['role']) ? (string)$_SESSION['role'] : 'user';
if ($user_role !== 'super_admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

require_once __DIR__ . '/../koneksi.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$statusCounts   = ['Open' => 0, 'In Progress' => 0, 'Review' => 0, 'Done' => 0, 'Reject' => 0, 'Closed' => 0];
$priorityCounts = ['Low' => 0, 'Medium' => 0, 'High' => 0, 'Urgent' => 0];
$totalTickets   = 0;
$recentTickets  = [];
$avgResponseTime = '-';

// --- Status counts ---
try {
    $res = $kon->query('SELECT `Status_Request`, COUNT(*) AS total FROM `ticket` GROUP BY `Status_Request`');
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $st  = (string)($r['Status_Request'] ?? '');
            $cnt = (int)($r['total'] ?? 0);
            $totalTickets += $cnt;
            if (array_key_exists($st, $statusCounts)) {
                $statusCounts[$st] = $cnt;
            }
        }
        $res->free();
    }
} catch (Throwable $e) { /* best-effort */ }

// --- Priority counts ---
try {
    $res = $kon->query('SELECT `Priority`, COUNT(*) AS total FROM `ticket` GROUP BY `Priority`');
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $p   = strtolower(trim((string)($r['Priority'] ?? '')));
            $cnt = (int)($r['total'] ?? 0);
            if ($p === 'low')    $priorityCounts['Low']    += $cnt;
            elseif ($p === 'medium') $priorityCounts['Medium'] += $cnt;
            elseif ($p === 'high')   $priorityCounts['High']   += $cnt;
            elseif ($p === 'urgent') $priorityCounts['Urgent'] += $cnt;
        }
        $res->free();
    }
} catch (Throwable $e) { /* best-effort */ }

// --- Recent tickets (last 5) ---
try {
    $res = $kon->query(
        'SELECT `Ticket_code`, `Nama_User`, `Divisi_User`, `Subject`, `Kategori_Masalah`, `Priority`, `Status_Request`, `Create_User`
         FROM `ticket`
         ORDER BY `Ticket_code` DESC
         LIMIT 5'
    );
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $recentTickets[] = $row;
        }
        $res->free();
    }
} catch (Throwable $e) { /* best-effort */ }

// --- Average response time (first status change) ---
try {
    $resTbl = $kon->query("SHOW TABLES LIKE 'ticket_status_history'");
    if ($resTbl && $resTbl->num_rows > 0) {
        $resTbl->free();
        $sqlAvg = "SELECT AVG(TIMESTAMPDIFF(SECOND, t.Create_User, h.first_changed_at)) AS avg_sec
                   FROM ticket t
                   JOIN (SELECT Ticket_code, MIN(changed_at) AS first_changed_at
                         FROM ticket_status_history GROUP BY Ticket_code) h
                     ON h.Ticket_code = t.Ticket_code
                   WHERE t.Create_User IS NOT NULL AND h.first_changed_at IS NOT NULL";
        $resAvg = $kon->query($sqlAvg);
        if ($resAvg) {
            $avgRow = $resAvg->fetch_assoc();
            $avgSec = isset($avgRow['avg_sec']) ? (float)$avgRow['avg_sec'] : 0.0;
            if ($avgSec > 0) {
                if ($avgSec >= 3600)     $avgResponseTime = number_format($avgSec / 3600, 1) . 'h';
                elseif ($avgSec >= 60)   $avgResponseTime = (string)round($avgSec / 60) . 'm';
                else                     $avgResponseTime = (string)round($avgSec) . 's';
            }
            $resAvg->free();
        }
    } elseif ($resTbl) {
        $resTbl->free();
    }
} catch (Throwable $e) { /* best-effort */ }

// Fingerprint - captures any change in status distribution or new ticket
$latestCode  = !empty($recentTickets) ? (int)($recentTickets[0]['Ticket_code'] ?? 0) : 0;
$fingerprint = md5(
    json_encode($statusCounts) .
    '|' . $totalTickets .
    '|' . $latestCode
);

echo json_encode([
    'ok'               => true,
    'total'            => $totalTickets,
    'status_counts'    => $statusCounts,
    'priority_counts'  => $priorityCounts,
    'avg_response_time'=> $avgResponseTime,
    'recent_tickets'   => $recentTickets,
    'fingerprint'      => $fingerprint,
    'server_time'      => date('c'),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
