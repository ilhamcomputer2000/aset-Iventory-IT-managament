<?php
/**
 * Activity Logging Helper
 * Provides logUserActivity(), getIpGeolocation(), parseUserAgent()
 */

/**
 * Auto-create / migrate the user_logs table with all required columns.
 */
function ensureUserLogsTable($conn) {
    $conn->query("CREATE TABLE IF NOT EXISTS user_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        username VARCHAR(100) DEFAULT '',
        role VARCHAR(50) DEFAULT '',
        ip_address VARCHAR(50) DEFAULT '',
        user_agent TEXT,
        action VARCHAR(100) DEFAULT '',
        target_table VARCHAR(100) DEFAULT NULL,
        record_id INT DEFAULT NULL,
        old_values TEXT DEFAULT NULL,
        new_values TEXT DEFAULT NULL,
        city VARCHAR(100) DEFAULT '',
        country VARCHAR(100) DEFAULT '',
        device_type VARCHAR(50) DEFAULT '',
        browser VARCHAR(100) DEFAULT '',
        os_name VARCHAR(100) DEFAULT '',
        session_token VARCHAR(100) DEFAULT '',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Migrate: add missing columns if table already existed
    $migrations = [
        "ALTER TABLE user_logs ADD COLUMN IF NOT EXISTS city VARCHAR(100) DEFAULT ''",
        "ALTER TABLE user_logs ADD COLUMN IF NOT EXISTS country VARCHAR(100) DEFAULT ''",
        "ALTER TABLE user_logs ADD COLUMN IF NOT EXISTS device_type VARCHAR(50) DEFAULT ''",
        "ALTER TABLE user_logs ADD COLUMN IF NOT EXISTS browser VARCHAR(100) DEFAULT ''",
        "ALTER TABLE user_logs ADD COLUMN IF NOT EXISTS os_name VARCHAR(100) DEFAULT ''",
        "ALTER TABLE user_logs ADD COLUMN IF NOT EXISTS session_token VARCHAR(100) DEFAULT ''",
    ];
    foreach ($migrations as $sql) {
        @$conn->query($sql);
    }
}

/**
 * Parse User-Agent string to extract browser, OS, and device type.
 * Returns array ['browser', 'os', 'device_type']
 */
function parseUserAgent($ua) {
    $ua = (string)$ua;

    // Device type
    $device_type = 'Desktop';
    if (preg_match('/Mobile|Android.*Mobile|iPhone|iPod|BlackBerry|IEMobile|Windows Phone/i', $ua)) {
        $device_type = 'Mobile';
    } elseif (preg_match('/iPad|Android(?!.*Mobile)|Tablet/i', $ua)) {
        $device_type = 'Tablet';
    }

    // Browser detection (order matters — specific first)
    $browser = 'Unknown';
    if (preg_match('/Edg\//i', $ua)) {
        $browser = 'Microsoft Edge';
    } elseif (preg_match('/OPR\//i', $ua) || preg_match('/Opera\//i', $ua)) {
        $browser = 'Opera';
    } elseif (preg_match('/SamsungBrowser\//i', $ua)) {
        $browser = 'Samsung Browser';
    } elseif (preg_match('/Chrome\//i', $ua) && !preg_match('/Chromium/i', $ua)) {
        $browser = 'Chrome';
    } elseif (preg_match('/Firefox\//i', $ua)) {
        $browser = 'Firefox';
    } elseif (preg_match('/Safari\//i', $ua) && !preg_match('/Chrome/i', $ua)) {
        $browser = 'Safari';
    } elseif (preg_match('/MSIE|Trident/i', $ua)) {
        $browser = 'Internet Explorer';
    }

    // OS detection
    $os = 'Unknown';
    if (preg_match('/Windows NT 10/i', $ua)) {
        $os = 'Windows 10/11';
    } elseif (preg_match('/Windows NT 6\.3/i', $ua)) {
        $os = 'Windows 8.1';
    } elseif (preg_match('/Windows NT 6\.1/i', $ua)) {
        $os = 'Windows 7';
    } elseif (preg_match('/Windows/i', $ua)) {
        $os = 'Windows';
    } elseif (preg_match('/Android ([0-9\.]+)/i', $ua, $m)) {
        $os = 'Android ' . $m[1];
    } elseif (preg_match('/iPhone OS ([0-9_]+)/i', $ua, $m)) {
        $os = 'iOS ' . str_replace('_', '.', $m[1]);
    } elseif (preg_match('/iPad.*OS ([0-9_]+)/i', $ua, $m)) {
        $os = 'iPadOS ' . str_replace('_', '.', $m[1]);
    } elseif (preg_match('/Mac OS X ([0-9_\.]+)/i', $ua, $m)) {
        $os = 'macOS ' . str_replace('_', '.', $m[1]);
    } elseif (preg_match('/Linux/i', $ua)) {
        $os = 'Linux';
    }

    return [
        'browser'     => $browser,
        'os'          => $os,
        'device_type' => $device_type,
    ];
}

/**
 * Get IP geolocation using ip-api.com (free, no API key needed).
 * Results are cached in PHP session for 1 hour per IP to avoid spamming.
 * Returns ['city' => ..., 'country' => ...]
 */
function getIpGeolocation($ip) {
    if (!$ip || $ip === 'unknown' || $ip === '127.0.0.1' || $ip === '::1') {
        return ['city' => 'Localhost', 'country' => 'Local'];
    }

    // Cache in $_SESSION
    $cacheKey = 'geo_' . md5($ip);
    if (isset($_SESSION[$cacheKey]) && is_array($_SESSION[$cacheKey])) {
        return $_SESSION[$cacheKey];
    }

    $result = ['city' => '', 'country' => ''];
    try {
        $url = 'http://ip-api.com/json/' . urlencode($ip) . '?fields=city,country,status';
        $ctx = stream_context_create(['http' => ['timeout' => 3]]);
        $json = @file_get_contents($url, false, $ctx);
        if ($json) {
            $data = json_decode($json, true);
            if (isset($data['status']) && $data['status'] === 'success') {
                $result['city']    = $data['city']    ?? '';
                $result['country'] = $data['country'] ?? '';
            }
        }
    } catch (Exception $e) {
        // silently fail
    }

    $_SESSION[$cacheKey] = $result;
    return $result;
}

/**
 * Get the real client IP address.
 */
function getRealIpAddress() {
    $headers = [
        'HTTP_CF_CONNECTING_IP',   // Cloudflare
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        'REMOTE_ADDR',
    ];
    foreach ($headers as $h) {
        if (!empty($_SERVER[$h])) {
            $ip = trim(explode(',', $_SERVER[$h])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return 'unknown';
}

/**
 * Log a user activity to user_logs.
 *
 * @param mysqli    $conn
 * @param int       $user_id
 * @param string    $username
 * @param string    $role
 * @param string    $action        e.g. 'Login', 'Logout', 'View Assets'
 * @param string    $target_table  (optional)
 * @param int       $record_id     (optional)
 * @param mixed     $old_values    (optional)
 * @param mixed     $new_values    (optional)
 */
function logUserActivity($conn, $user_id, $username, $role, $action, $target_table = null, $record_id = null, $old_values = null, $new_values = null) {
    if (!$conn || !($conn instanceof mysqli)) {
        return false;
    }

    // Ensure table exists
    ensureUserLogsTable($conn);

    // Gather info
    $ip         = getRealIpAddress();
    $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? (string)$_SERVER['HTTP_USER_AGENT'] : '';
    $ua_parsed  = parseUserAgent($user_agent);
    $geo        = getIpGeolocation($ip);
    $session_token = session_id() ?: '';

    $old_values_json = $old_values !== null ? json_encode($old_values) : null;
    $new_values_json = $new_values !== null ? json_encode($new_values) : null;

    $stmt = $conn->prepare("
        INSERT INTO user_logs
        (user_id, username, role, ip_address, user_agent, action, target_table, record_id,
         old_values, new_values, city, country, device_type, browser, os_name, session_token)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    if ($stmt === false) {
        error_log('logUserActivity prepare failed: ' . $conn->error);
        return false;
    }

    $stmt->bind_param(
        'issssssissssssss',
        $user_id,
        $username,
        $role,
        $ip,
        $user_agent,
        $action,
        $target_table,
        $record_id,
        $old_values_json,
        $new_values_json,
        $geo['city'],
        $geo['country'],
        $ua_parsed['device_type'],
        $ua_parsed['browser'],
        $ua_parsed['os'],
        $session_token
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