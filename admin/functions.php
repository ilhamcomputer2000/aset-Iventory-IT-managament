
<?php
function logUserActivity($conn, $user_id, $username, $full_name, $action) {
    // Ambil IP dan User Agent
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

    // Escape input untuk keamanan
    $user_id = (int)$user_id;
    $username = $conn->real_escape_string($username);
    $full_name = $conn->real_escape_string($full_name);
    $action = $conn->real_escape_string($action);
    $ip = $conn->real_escape_string($ip);
    $user_agent = $conn->real_escape_string(substr($user_agent, 0, 500)); // batasi panjang

    $sql = "INSERT INTO user_logs (user_id, username, full_name, action, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("isssss", $user_id, $username, $full_name, $action, $ip, $user_agent);
        $stmt->execute();
        $stmt->close();
    }
}
?>