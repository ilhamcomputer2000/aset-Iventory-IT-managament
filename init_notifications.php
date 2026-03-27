<?php
/**
 * Initialize notifications table.
 * Run once, or called automatically by helper function.
 */
require_once __DIR__ . '/koneksi.php';

$sql = "CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `target_role` ENUM('admin','user') NOT NULL,
    `target_user_id` INT NULL COMMENT 'For user notifications: specific user id',
    `type` VARCHAR(50) NOT NULL COMMENT 'ticket_created, ticket_closed, pinjaman_request, pinjaman_approved, pinjaman_rejected, ticket_status_changed',
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT NULL,
    `reference_id` INT NULL COMMENT 'Ticket code or request_pinjaman id',
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_target_role` (`target_role`, `is_read`, `created_at`),
    KEY `idx_target_user` (`target_user_id`, `is_read`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($kon->query($sql) === TRUE) {
    echo "Tabel notifications berhasil dibuat/dicek.\n";
} else {
    echo "Error membuat tabel: " . $kon->error . "\n";
}
