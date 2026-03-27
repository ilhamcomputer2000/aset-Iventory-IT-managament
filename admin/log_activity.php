<?php
function logUserActivity($conn, $user_id, $username, $role, $action, $target_table = null, $record_id = null, $old_values = null, $new_values = null) {
    if (!$conn || !($conn instanceof mysqli)) {
        return false;
    }

    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
    $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    
    // Encode values sebelum bind_param
    $old_values_json = json_encode($old_values);
    $new_values_json = json_encode($new_values);

    // Sanitasi input
    $stmt = $conn->prepare("
        INSERT INTO user_logs 
        (user_id, username, role, ip_address, user_agent, action, target_table, record_id, old_values, new_values) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    if ($stmt === false) {
        error_log('logUserActivity prepare failed: ' . $conn->error);
        return false;
    }

    $stmt->bind_param(
        "issssssiss",
        $user_id,
        $username,
        $role,
        $ip,
        $user_agent,
        $action,
        $target_table,
        $record_id,
        $old_values_json,
        $new_values_json
    );
    if (!$stmt->execute()) {
        error_log('logUserActivity execute failed: ' . $stmt->error);
        $stmt->close();
        return false;
    }

    $stmt->close();
    return true;
}
?>