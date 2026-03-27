<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$user_role = $_SESSION['role'] ?? 'user';
if ($user_role !== 'super_admin') {
    header('Location: ../user/view.php');
    exit();
}

$primaryLog = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'temp' . DIRECTORY_SEPARATOR . 'php_errors.log';
$fallbackLog = rtrim((string)sys_get_temp_dir(), "\\/") . DIRECTORY_SEPARATOR . 'crud' . DIRECTORY_SEPARATOR . 'php_errors.log';
$logFile = $primaryLog;
$exists = is_file($logFile);
if (!$exists && is_file($fallbackLog)) {
    $logFile = $fallbackLog;
    $exists = true;
}
$linesToShow = 200;

$content = '';
if ($exists) {
    $lines = @file($logFile);
    if (is_array($lines)) {
        $tail = array_slice($lines, -$linesToShow);
        $content = implode('', $tail);
    }
}

?><!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Temp Error Log</title>
    <style>
        body { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; margin: 20px; background:#111827; color:#e5e7eb; }
        .box { background:#0b1220; border:1px solid #374151; border-radius: 10px; padding: 14px; }
        .muted { color:#9ca3af; }
        a { color:#93c5fd; }
        pre { white-space: pre-wrap; word-break: break-word; margin:0; }
        .row { display:flex; gap:12px; align-items:center; flex-wrap:wrap; }
        .btn { display:inline-block; padding:8px 12px; border-radius:8px; background:#2563eb; color:white; text-decoration:none; }
        .btn:hover { background:#1d4ed8; }
        .danger { background:#dc2626; }
        .danger:hover { background:#b91c1c; }
    </style>
</head>
<body>
    <div class="row" style="margin-bottom:12px;">
        <a class="btn" href="" onclick="location.reload(); return false;">Refresh</a>
        <a class="btn" href="index.php">Kembali</a>
        <span class="muted">File: <?php echo htmlspecialchars($logFile, ENT_QUOTES, 'UTF-8'); ?></span>
    </div>

    <div class="box">
        <?php if (!$exists): ?>
            <div class="muted">Log belum ada. Coba submit update sekali, lalu refresh halaman ini.</div>
        <?php else: ?>
            <div class="muted" style="margin-bottom:10px;">Menampilkan <?php echo (int)$linesToShow; ?> baris terakhir.</div>
            <pre><?php echo htmlspecialchars($content, ENT_QUOTES, 'UTF-8'); ?></pre>
        <?php endif; ?>
    </div>
</body>
</html>
