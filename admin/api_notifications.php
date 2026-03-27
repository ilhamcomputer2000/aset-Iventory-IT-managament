<?php
/**
 * Admin Notification API
 * 
 * GET  ?action=fetch          → JSON list of admin notifications + unread count
 * POST ?action=mark_read      → Mark single notification as read (id in POST body)
 * POST ?action=mark_all_read  → Mark all admin notifications as read
 */
session_start();
require_once __DIR__ . '/../koneksi.php';

header('Content-Type: application/json; charset=utf-8');

// Auth check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] === 'user') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

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
        $stmt = $kon->prepare("UPDATE `notifications` SET `is_read` = 1 WHERE `id` = ? AND `target_role` = 'admin'");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    }
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'mark_all_read' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $kon->query("UPDATE `notifications` SET `is_read` = 1 WHERE `target_role` = 'admin' AND `is_read` = 0");
    echo json_encode(['success' => true]);
    exit;
}

// Default: fetch notifications
$limit = 50;
$stmt = $kon->prepare("SELECT `id`, `type`, `title`, `message`, `reference_id`, `is_read`, `created_at` FROM `notifications` WHERE `target_role` = 'admin' ORDER BY `created_at` DESC LIMIT ?");
$stmt->bind_param('i', $limit);
$stmt->execute();
$result = $stmt->get_result();

$notifications = [];
while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}
$stmt->close();

// Unread count
$countRes = $kon->query("SELECT COUNT(*) AS cnt FROM `notifications` WHERE `target_role` = 'admin' AND `is_read` = 0");
$unreadCount = 0;
if ($countRes) {
    $cr = $countRes->fetch_assoc();
    $unreadCount = (int)$cr['cnt'];
}

echo json_encode([
    'unread_count' => $unreadCount,
    'notifications' => $notifications
]);
