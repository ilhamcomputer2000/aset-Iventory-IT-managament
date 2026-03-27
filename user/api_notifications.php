<?php
/**
 * User Notification API
 * 
 * GET  ?action=fetch          → JSON list of notifications for THIS user + unread count
 * POST ?action=mark_read      → Mark single notification as read
 * POST ?action=mark_all_read  → Mark all user's notifications as read
 * 
 * IMPORTANT: Only returns notifications for the logged-in user (target_user_id = session user_id)
 */
session_start();
require_once __DIR__ . '/../koneksi.php';

header('Content-Type: application/json; charset=utf-8');

// Auth check
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

// Ensure table exists
$kon->query("CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `target_role` ENUM('admin','user') NOT NULL,
    `target_user_id` INT NULL,
    `type` VARCHAR(50) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT NULL,
    `reference_id` INT NULL,
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_target_role` (`target_role`, `is_read`, `created_at`),
    KEY `idx_target_user` (`target_user_id`, `is_read`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$action = isset($_REQUEST['action']) ? trim($_REQUEST['action']) : 'fetch';

if ($action === 'mark_read' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($id > 0) {
        // Only mark if it belongs to this user
        $stmt = $kon->prepare("UPDATE `notifications` SET `is_read` = 1 WHERE `id` = ? AND `target_role` = 'user' AND `target_user_id` = ?");
        $stmt->bind_param('ii', $id, $userId);
        $stmt->execute();
        $stmt->close();
    }
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'mark_all_read' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $kon->prepare("UPDATE `notifications` SET `is_read` = 1 WHERE `target_role` = 'user' AND `target_user_id` = ? AND `is_read` = 0");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => true]);
    exit;
}

// Default: fetch notifications scoped to this user only
$limit = 50;
$stmt = $kon->prepare("SELECT `id`, `type`, `title`, `message`, `reference_id`, `is_read`, `created_at` FROM `notifications` WHERE `target_role` = 'user' AND `target_user_id` = ? ORDER BY `created_at` DESC LIMIT ?");
$stmt->bind_param('ii', $userId, $limit);
$stmt->execute();
$result = $stmt->get_result();

$notifications = [];
while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}
$stmt->close();

// Unread count scoped to this user
$stmt2 = $kon->prepare("SELECT COUNT(*) AS cnt FROM `notifications` WHERE `target_role` = 'user' AND `target_user_id` = ? AND `is_read` = 0");
$stmt2->bind_param('i', $userId);
$stmt2->execute();
$countRes = $stmt2->get_result();
$unreadCount = 0;
if ($countRes) {
    $cr = $countRes->fetch_assoc();
    $unreadCount = (int)$cr['cnt'];
}
$stmt2->close();

echo json_encode([
    'unread_count' => $unreadCount,
    'notifications' => $notifications
]);
