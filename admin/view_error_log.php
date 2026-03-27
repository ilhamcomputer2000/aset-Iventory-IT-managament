<?php
// Display PHP error log for debugging
$php_error_log = ini_get('error_log');

if (!$php_error_log || !file_exists($php_error_log)) {
    // Try common locations
    $possible_logs = [
        'C:\\xampp\\apache\\logs\\error.log',
        'C:\\xampp\\apache\\logs\\access.log',
        php_ini_get('error_log'),
        getenv('PHP_ERROR_LOG'),
    ];
    
    foreach ($possible_logs as $log) {
        if ($log && file_exists($log)) {
            $php_error_log = $log;
            break;
        }
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>PHP Error Log Viewer</title>
    <style>
        body { font-family: monospace; margin: 20px; background: #1e1e1e; color: #d4d4d4; }
        .container { max-width: 1000px; margin: 0 auto; }
        h1 { color: #4ec9b0; }
        .info { background: #2d2d2d; padding: 15px; border-radius: 4px; margin: 10px 0; border-left: 4px solid #007acc; }
        pre { background: #1e1e1e; padding: 15px; border: 1px solid #555; border-radius: 4px; overflow-x: auto; max-height: 600px; }
        .debug-log { color: #ce9178; }
        .error { color: #f48771; }
        .success { color: #6a9955; }
        button { background: #007acc; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #0089d6; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📋 PHP Error Log Viewer</h1>
        
        <div class="info">
            <strong>Log Location:</strong> <?php echo $php_error_log ?: 'NOT FOUND'; ?>
        </div>
        
        <?php if ($php_error_log && file_exists($php_error_log)): ?>
            <div class="info">
                <button onclick="location.reload()">🔄 Refresh</button>
                <button onclick="document.querySelector('pre').innerText = ''">Clear View</button>
            </div>
            
            <pre><?php
                $lines = file($php_error_log);
                // Show last 100 lines
                $last_lines = array_slice($lines, -100);
                foreach ($last_lines as $line) {
                    $line = htmlspecialchars($line);
                    
                    // Color code
                    if (strpos($line, '=== CREATE.PHP POST DATA ===') !== false) {
                        echo '<span class="debug-log">' . $line . '</span>';
                    } elseif (strpos($line, 'ERROR') !== false || strpos($line, 'error') !== false) {
                        echo '<span class="error">' . $line . '</span>';
                    } elseif (strpos($line, 'Riwayat_Barang') !== false) {
                        echo '<span class="debug-log">' . $line . '</span>';
                    } else {
                        echo $line;
                    }
                }
            ?></pre>
        <?php else: ?>
            <div class="info" style="border-left-color: #f48771;">
                ❌ PHP Error log tidak ditemukan atau belum ada entries.
                <br><br>
                <strong>Coba:</strong>
                <ol>
                    <li>Buka <code>create.php</code></li>
                    <li>Submit form dengan riwayat entry</li>
                    <li>Refresh halaman ini</li>
                </ol>
            </div>
        <?php endif; ?>
        
    </div>
</body>
</html>
