<?php
session_start();
include '../koneksi.php'; // Koneksi database

header('Content-Type: application/json');

$query = "SELECT * FROM activities WHERE user = ? ORDER BY timestamp DESC LIMIT 10";
$stmt = $conn->prepare($query);
$stmt->bind_param('s', $_SESSION['username']);
$stmt->execute();
$result = $stmt->get_result();

$notifications = [];
while ($row = $result->fetch_assoc()) {
    $notifications[] = [
        'message' => $row['action'],
        'timestamp' => $row['timestamp']
    ];
}

echo json_encode($notifications);

$stmt->close();
$conn->close();
?>
