<?php
/**
 * api_ticket_stats.php (user)
 * Returns JSON: status counts, priority counts, avg response time,
 * 5 recent tickets, and a fingerprint for change detection.
 * Scoped to the currently logged-in user's tickets only.
 */
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthenticated']);
    exit();
}

$rawRole = isset($_SESSION['role']) ? (string) $_SESSION['role'] : 'user';
$role = strtolower(trim($rawRole));
if ($role === 'super_admin' || $role === 'admin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Use admin API instead']);
    exit();
}

$Id_Karyawan = isset($_SESSION['Id_Karyawan']) && (string) $_SESSION['Id_Karyawan'] !== ''
    ? (string) $_SESSION['Id_Karyawan']
    : (isset($_SESSION['username']) ? (string) $_SESSION['username'] : '');
$username = isset($_SESSION['username']) ? (string) $_SESSION['username'] : '';

if ($Id_Karyawan === '' || $username === '') {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'User data incomplete']);
    exit();
}

require_once __DIR__ . '/../koneksi.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

try {
    $statusList = ['Open', 'In Progress', 'Review', 'Done', 'Reject', 'Closed'];
    $statusCounts = array_fill_keys($statusList, 0);
    $total = 0;

    // Status counts (user scope)
    $stmtStatus = $kon->prepare(
        'SELECT `Status_Request`, COUNT(*) AS cnt FROM `ticket`
         WHERE `Id_Karyawan` = ? AND `Create_By_User` = ?
         GROUP BY `Status_Request`'
    );
    if ($stmtStatus) {
        $stmtStatus->bind_param('ss', $Id_Karyawan, $username);
        if ($stmtStatus->execute()) {
            $resStatus = $stmtStatus->get_result();
            if ($resStatus) {
                while ($r = $resStatus->fetch_assoc()) {
                    $st = (string) ($r['Status_Request'] ?? '');
                    $cnt = (int) ($r['cnt'] ?? 0);
                    $total += $cnt;
                    if (in_array($st, $statusList, true)) {
                        $statusCounts[$st] = $cnt;
                    }
                }
            }
        }
        $stmtStatus->close();
    }

    // Priority counts (user scope)
    $priorityCounts = ['Low' => 0, 'Medium' => 0, 'High' => 0, 'Urgent' => 0];
    $stmtPr = $kon->prepare(
        'SELECT `Priority`, COUNT(*) AS cnt FROM `ticket`
         WHERE `Id_Karyawan` = ? AND `Create_By_User` = ?
         GROUP BY `Priority`'
    );
    if ($stmtPr) {
        $stmtPr->bind_param('ss', $Id_Karyawan, $username);
        if ($stmtPr->execute()) {
            $resPr = $stmtPr->get_result();
            if ($resPr) {
                while ($r = $resPr->fetch_assoc()) {
                    $p = strtolower(trim((string) ($r['Priority'] ?? '')));
                    $cnt = (int) ($r['cnt'] ?? 0);
                    if ($p === 'low') $priorityCounts['Low'] += $cnt;
                    elseif ($p === 'medium') $priorityCounts['Medium'] += $cnt;
                    elseif ($p === 'high') $priorityCounts['High'] += $cnt;
                    elseif ($p === 'urgent') $priorityCounts['Urgent'] += $cnt;
                }
            }
        }
        $stmtPr->close();
    }

    // Average response time (user scope)
    $avgResponseTime = '-';
    $resTbl = $kon->query("SHOW TABLES LIKE 'ticket_status_history'");
    $hasHistoryTable = ($resTbl && $resTbl->num_rows > 0);
    if ($hasHistoryTable) {
        $sqlAvg = "SELECT AVG(TIMESTAMPDIFF(SECOND, t.Create_User, h.first_changed_at)) AS avg_sec
                   FROM ticket t
                   JOIN (SELECT Ticket_code, MIN(changed_at) AS first_changed_at FROM ticket_status_history GROUP BY Ticket_code) h
                     ON h.Ticket_code = t.Ticket_code
                   WHERE t.Id_Karyawan = ? AND t.Create_By_User = ?
                     AND t.Create_User IS NOT NULL AND h.first_changed_at IS NOT NULL";
        $stmtAvg = $kon->prepare($sqlAvg);
        if ($stmtAvg) {
            $stmtAvg->bind_param('ss', $Id_Karyawan, $username);
            if ($stmtAvg->execute()) {
                $resAvg = $stmtAvg->get_result();
                if ($resAvg) {
                    $avgRow = $resAvg->fetch_assoc();
                    $avgSec = isset($avgRow['avg_sec']) ? (float) $avgRow['avg_sec'] : 0.0;
                    if ($avgSec > 0) {
                        if ($avgSec >= 3600) {
                            $avgResponseTime = number_format($avgSec / 3600, 1) . 'h';
                        } elseif ($avgSec >= 60) {
                            $avgResponseTime = (string) round($avgSec / 60) . 'm';
                        } else {
                            $avgResponseTime = (string) round($avgSec) . 's';
                        }
                    }
                }
            }
            $stmtAvg->close();
        }
    }

    // Recent 5 tickets (user scope)
    $recentTickets = [];
    $stmtRecent = $kon->prepare(
        'SELECT `Ticket_code`, `Nama_User`, `Divisi_User`, `Subject`, `Kategori_Masalah`,
                `Priority`, `Status_Request`, `Create_User`
         FROM `ticket`
         WHERE `Id_Karyawan` = ? AND `Create_By_User` = ?
         ORDER BY `Ticket_code` DESC LIMIT 5'
    );
    if ($stmtRecent) {
        $stmtRecent->bind_param('ss', $Id_Karyawan, $username);
        if ($stmtRecent->execute()) {
            $resRecent = $stmtRecent->get_result();
            if ($resRecent) {
                while ($row = $resRecent->fetch_assoc()) {
                    $recentTickets[] = $row;
                }
            }
        }
        $stmtRecent->close();
    }

    // Fingerprint (detect any change in user's tickets)
    $fingerprintData = json_encode($statusCounts);
    $fingerprint = md5($fingerprintData . $total);

    echo json_encode([
        'ok'                => true,
        'total'             => $total,
        'status_counts'     => $statusCounts,
        'priority_counts'   => $priorityCounts,
        'avg_response_time' => $avgResponseTime,
        'recent_tickets'    => $recentTickets,
        'fingerprint'       => $fingerprint,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server error']);
    error_log('api_ticket_stats (user): ' . $e->getMessage());
}
