<?php
/**
 * api_it_status.php
 * Contextual IT team activity stats:
 * - Cek apakah tiket MILIK USER YANG LOGIN sedang dikerjakan (personalized)
 * - Jika tidak, tampilkan info global IT team activity
 */
require_once __DIR__ . '/../koneksi.php';
session_start();
session_write_close(); // release session lock immediately

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['ok' => false]);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

try {
    $myIdKaryawan  = isset($_SESSION['Id_Karyawan']) ? trim((string)$_SESSION['Id_Karyawan']) : '';
    $myUserId      = (int)($_SESSION['user_id'] ?? 0);
    $myUsername    = isset($_SESSION['username']) ? trim((string)$_SESSION['username']) : '';

    // Check if assigned_to column exists
    $colCheck   = $kon->query("SHOW COLUMNS FROM `ticket` LIKE 'assigned_to'");
    $hasAssigned = $colCheck && $colCheck->num_rows > 0;

    // =========================================================
    // 1. PERSONALIZED CHECK: Apakah tiket MILIK USER INI sedang dikerjakan?
    //    Gunakan username (Create_By_User) karena Id_Karyawan tidak selalu ada
    // =========================================================
    $myTicketActive  = false;
    $myAssignedTo    = null;
    $myActiveTickets = 0;

    // $myUsername dari session sudah pasti ada (dipakai untuk login)
    if ($hasAssigned && $myUsername !== '') {
        $stmt = $kon->prepare(
            "SELECT COUNT(*) AS cnt, assigned_to
             FROM `ticket`
             WHERE `Create_By_User` = ?
               AND `assigned_to` IS NOT NULL
               AND `assigned_to` != ''
               AND `Status_Request` NOT IN ('Done','Closed','Reject')
             GROUP BY assigned_to
             ORDER BY cnt DESC
             LIMIT 1"
        );
        if ($stmt) {
            $stmt->bind_param('s', $myUsername);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res && $row = $res->fetch_assoc()) {
                $myActiveTickets = (int)($row['cnt'] ?? 0);
                $myAssignedTo    = (string)($row['assigned_to'] ?? '');
                $myTicketActive  = $myActiveTickets > 0;
            }
            $stmt->close();
        }
    }

    // =========================================================
    // 2. GLOBAL STATS (untuk banner umum semua user)
    // =========================================================
    $activeCount = 0;
    $staffCount  = 0;
    if ($hasAssigned) {
        $rActive = $kon->query(
            "SELECT COUNT(*) AS cnt, COUNT(DISTINCT assigned_to) AS staff_cnt
             FROM `ticket`
             WHERE `assigned_to` IS NOT NULL
               AND `assigned_to` != ''
               AND `Status_Request` NOT IN ('Done','Closed','Reject')"
        );
        if ($rActive) {
            $row         = $rActive->fetch_assoc();
            $activeCount = (int)($row['cnt'] ?? 0);
            $staffCount  = (int)($row['staff_cnt'] ?? 0);
        }
    }

    // Open tickets in queue
    $rOpen     = $kon->query("SELECT COUNT(*) AS cnt FROM `ticket` WHERE `Status_Request` = 'Open'");
    $openCount = $rOpen ? (int)($rOpen->fetch_assoc()['cnt'] ?? 0) : 0;

    // Avg response time (global, last 30 days)
    $avgText = null;
    $rAvg = $kon->query(
        "SELECT AVG(TIMESTAMPDIFF(SECOND, t.Create_User, h.first_changed_at)) AS avg_sec
         FROM `ticket` t
         JOIN (
             SELECT Ticket_code, MIN(changed_at) AS first_changed_at
             FROM ticket_status_history
             GROUP BY Ticket_code
         ) h ON h.Ticket_code = t.Ticket_code
         WHERE t.Create_User >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
    );
    if ($rAvg) {
        $avgSec = (float)($rAvg->fetch_assoc()['avg_sec'] ?? 0);
        if ($avgSec > 0) {
            if ($avgSec >= 3600)     $avgText = number_format($avgSec / 3600, 1) . ' jam';
            elseif ($avgSec >= 60)   $avgText = round($avgSec / 60) . ' menit';
            else                     $avgText = round($avgSec) . ' detik';
        }
    }

    // =========================================================
    // 3. Determine banner mode:
    //    'mine'   → tiket user ini sedang dikerjakan (personalized)
    //    'active' → ada tiket lain yg dikerjakan (general)
    //    'queued' → ada antrian tapi belum diambil
    //    'idle'   → tidak ada tiket aktif
    // =========================================================
    if ($myTicketActive) {
        $statusLevel = 'mine';
    } elseif ($activeCount > 0) {
        $statusLevel = 'active';
    } elseif ($openCount > 0) {
        $statusLevel = 'queued';
    } else {
        $statusLevel = 'idle';
    }

    echo json_encode([
        'ok'                => true,
        // Personalized
        'my_ticket_active'  => $myTicketActive,
        'my_assigned_to'    => $myAssignedTo,
        'my_active_tickets' => $myActiveTickets,
        // Global
        'active_count'      => $activeCount,
        'staff_count'       => $staffCount,
        'open_count'        => $openCount,
        'avg_response'      => $avgText,
        'status_level'      => $statusLevel,
        'ts'                => time(),
    ]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
