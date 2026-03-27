<?php
// Simple hosting diagnostic page.
// Access: /admin/hosting_diagnostic.php (optionally ?json=1)

declare(strict_types=0);

session_start();

// Optional: restrict to logged-in users if your app uses login.
if (isset($_GET['public']) !== true) {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(403);
        echo 'Forbidden (not logged in). Add ?public=1 only for temporary testing.';
        exit;
    }
}

$asJson = isset($_GET['json']);

function bytes_to_human($bytes) {
    if (!is_numeric($bytes)) {
        return (string)$bytes;
    }
    $units = ['B','KB','MB','GB','TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units)-1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

function ini_bytes($val) {
    $val = trim((string)$val);
    if ($val === '') return 0;
    $last = strtolower($val[strlen($val)-1]);
    $num = (int)$val;
    switch ($last) {
        case 'g': $num *= 1024;
        case 'm': $num *= 1024;
        case 'k': $num *= 1024;
    }
    return $num;
}

$report = [];
$report['time'] = date('c');
$report['php'] = [
    'version' => PHP_VERSION,
    'sapi' => PHP_SAPI,
    'os' => PHP_OS_FAMILY,
];
$report['extensions'] = [
    'mysqli' => extension_loaded('mysqli'),
    'gd' => extension_loaded('gd'),
    'fileinfo' => extension_loaded('fileinfo'),
    'mbstring' => extension_loaded('mbstring'),
    'openssl' => extension_loaded('openssl'),
];
$report['ini'] = [
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'max_file_uploads' => ini_get('max_file_uploads'),
    'max_input_vars' => ini_get('max_input_vars'),
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time'),
];

// DB check
$db = ['include_ok' => false, 'connect_ok' => false, 'error' => null, 'server_info' => null];
try {
    require_once __DIR__ . '/../koneksi.php';
    $db['include_ok'] = true;
    if (isset($kon) && ($kon instanceof mysqli)) {
        $db['connect_ok'] = true;
        $db['server_info'] = $kon->server_info;
        // basic query
        $res = @$kon->query('SELECT 1 AS ok');
        $db['query_ok'] = $res ? true : false;
        $db['query_error'] = $res ? null : $kon->error;
        // peserta table existence
        $tbl = @$kon->query("SHOW TABLES LIKE 'peserta'");
        $db['peserta_table'] = ($tbl && $tbl->num_rows > 0);
    } else {
        $db['error'] = 'koneksi.php did not create $kon (mysqli).';
    }
} catch (Throwable $e) {
    $db['error'] = $e->getMessage();
}
$report['db'] = $db;

// Upload directory checks
$uploads = [];
$base = realpath(__DIR__ . '/../uploads');
$uploads['path'] = $base ?: (__DIR__ . '/../uploads');
$uploads['exists'] = is_dir(__DIR__ . '/../uploads');
$uploads['writable'] = is_writable(__DIR__ . '/../uploads');

$testDir = __DIR__ . '/../uploads/_diag_test_' . date('Ymd_His');
$uploads['test_dir'] = $testDir;
$uploads['test_mkdir_ok'] = false;
$uploads['test_write_ok'] = false;
$uploads['test_spaces_dir_ok'] = false;
$uploads['errors'] = [];

if (!$uploads['exists']) {
    $uploads['errors'][] = 'uploads directory does not exist.';
} else {
    if (!@mkdir($testDir, 0755, true)) {
        $uploads['errors'][] = 'mkdir test failed (check permissions / open_basedir).';
    } else {
        $uploads['test_mkdir_ok'] = true;
        $testFile = $testDir . '/write_test.txt';
        if (@file_put_contents($testFile, 'ok ' . date('c')) === false) {
            $uploads['errors'][] = 'write test failed in uploads.';
        } else {
            $uploads['test_write_ok'] = true;
        }

        $spacesDir = $testDir . '/foto depan';
        if (@mkdir($spacesDir, 0755, true)) {
            $uploads['test_spaces_dir_ok'] = true;
        } else {
            $uploads['errors'][] = 'mkdir with spaces failed (foto depan).';
        }

        // cleanup (best effort)
        @unlink($testDir . '/write_test.txt');
        @rmdir($spacesDir);
        @rmdir($testDir);
    }
}
$report['uploads'] = $uploads;

// Recommendation summary
$reco = [];
$postMax = ini_bytes($report['ini']['post_max_size']);
$uploadMax = ini_bytes($report['ini']['upload_max_filesize']);
$reco['note'] = 'If you upload 4 photos, ensure post_max_size and upload_max_filesize are sufficient.';
$reco['upload_limits_bytes'] = [
    'post_max_size' => $postMax,
    'upload_max_filesize' => $uploadMax,
    'post_max_size_human' => bytes_to_human($postMax),
    'upload_max_filesize_human' => bytes_to_human($uploadMax),
];
$report['recommendations'] = $reco;

if ($asJson) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($report, JSON_PRETTY_PRINT);
    exit;
}

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Hosting Diagnostic</title>
  <style>
    body{font-family:Arial, sans-serif; margin:20px; background:#f6f7fb}
    .card{background:#fff; padding:16px; border-radius:10px; margin-bottom:12px; box-shadow:0 2px 10px rgba(0,0,0,.06)}
    .ok{color:#0a7a2f; font-weight:700}
    .bad{color:#b00020; font-weight:700}
    pre{background:#111827; color:#e5e7eb; padding:12px; border-radius:10px; overflow:auto}
    code{background:#eef2ff; padding:2px 6px; border-radius:6px}
  </style>
</head>
<body>
  <div class="card">
    <h2>Hosting Diagnostic</h2>
    <p>Open JSON: <code>?json=1</code></p>
    <p>Time: <?php echo htmlspecialchars($report['time']); ?></p>
  </div>

  <div class="card">
    <h3>PHP</h3>
    <ul>
      <li>Version: <strong><?php echo htmlspecialchars($report['php']['version']); ?></strong></li>
      <li>SAPI: <?php echo htmlspecialchars($report['php']['sapi']); ?></li>
      <li>OS: <?php echo htmlspecialchars($report['php']['os']); ?></li>
    </ul>
  </div>

  <div class="card">
    <h3>Extensions</h3>
    <ul>
      <?php foreach ($report['extensions'] as $k => $v): ?>
        <li><?php echo htmlspecialchars($k); ?>: <span class="<?php echo $v ? 'ok' : 'bad'; ?>"><?php echo $v ? 'OK' : 'MISSING'; ?></span></li>
      <?php endforeach; ?>
    </ul>
  </div>

  <div class="card">
    <h3>INI (upload related)</h3>
    <ul>
      <?php foreach ($report['ini'] as $k => $v): ?>
        <li><?php echo htmlspecialchars($k); ?>: <strong><?php echo htmlspecialchars((string)$v); ?></strong></li>
      <?php endforeach; ?>
    </ul>
  </div>

  <div class="card">
    <h3>Database</h3>
    <ul>
      <li>Include koneksi.php: <span class="<?php echo $db['include_ok'] ? 'ok' : 'bad'; ?>"><?php echo $db['include_ok'] ? 'OK' : 'FAIL'; ?></span></li>
      <li>Connect: <span class="<?php echo $db['connect_ok'] ? 'ok' : 'bad'; ?>"><?php echo $db['connect_ok'] ? 'OK' : 'FAIL'; ?></span></li>
      <li>Query SELECT 1: <span class="<?php echo !empty($db['query_ok']) ? 'ok' : 'bad'; ?>"><?php echo !empty($db['query_ok']) ? 'OK' : 'FAIL'; ?></span></li>
      <li>Table peserta exists: <span class="<?php echo !empty($db['peserta_table']) ? 'ok' : 'bad'; ?>"><?php echo !empty($db['peserta_table']) ? 'OK' : 'FAIL'; ?></span></li>
    </ul>
    <?php if (!empty($db['error'])): ?>
      <p class="bad">Error: <?php echo htmlspecialchars((string)$db['error']); ?></p>
    <?php endif; ?>
    <?php if (!empty($db['query_error'])): ?>
      <p class="bad">Query error: <?php echo htmlspecialchars((string)$db['query_error']); ?></p>
    <?php endif; ?>
  </div>

  <div class="card">
    <h3>Uploads directory</h3>
    <ul>
      <li>Exists: <span class="<?php echo $uploads['exists'] ? 'ok' : 'bad'; ?>"><?php echo $uploads['exists'] ? 'OK' : 'FAIL'; ?></span></li>
      <li>Writable: <span class="<?php echo $uploads['writable'] ? 'ok' : 'bad'; ?>"><?php echo $uploads['writable'] ? 'OK' : 'FAIL'; ?></span></li>
      <li>mkdir test: <span class="<?php echo $uploads['test_mkdir_ok'] ? 'ok' : 'bad'; ?>"><?php echo $uploads['test_mkdir_ok'] ? 'OK' : 'FAIL'; ?></span></li>
      <li>write test: <span class="<?php echo $uploads['test_write_ok'] ? 'ok' : 'bad'; ?>"><?php echo $uploads['test_write_ok'] ? 'OK' : 'FAIL'; ?></span></li>
      <li>mkdir with spaces (foto depan): <span class="<?php echo $uploads['test_spaces_dir_ok'] ? 'ok' : 'bad'; ?>"><?php echo $uploads['test_spaces_dir_ok'] ? 'OK' : 'FAIL'; ?></span></li>
    </ul>
    <?php if (!empty($uploads['errors'])): ?>
      <p class="bad">Errors:</p>
      <pre><?php echo htmlspecialchars(implode("\n", $uploads['errors'])); ?></pre>
    <?php endif; ?>
  </div>

  <div class="card">
    <h3>Raw JSON (for sharing)</h3>
    <pre><?php echo htmlspecialchars(json_encode($report, JSON_PRETTY_PRINT)); ?></pre>
  </div>
</body>
</html>
