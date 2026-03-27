<?php
// Role-based redirect to the appropriate Ticket page.
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$role = isset($_SESSION['role']) ? (string)$_SESSION['role'] : 'user';
if ($role === 'super_admin') {
    header('Location: admin/ticket.php');
    exit();
}

header('Location: user/ticket.php');
exit();
