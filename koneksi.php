<?php
// Central DB connection file.
// On hosting, set credentials here (or via env vars DB_HOST/DB_USER/DB_PASS/DB_NAME).

mysqli_report(MYSQLI_REPORT_OFF);

$dbHost = getenv('DB_HOST');
$dbUser = getenv('DB_USER');
$dbPass = getenv('DB_PASS');
$dbName = getenv('DB_NAME');
$dbPort = getenv('DB_PORT');

if ($dbHost === false || $dbHost === '') {
    $dbHost = 'localhost';
}
if ($dbUser === false || $dbUser === '') {
    $dbUser = 'root';
}
if ($dbPass === false) {
    $dbPass = '';
}
if ($dbName === false || $dbName === '') {
    $dbName = 'crud';
}

if ($dbPort === false || $dbPort === '') {
    $dbPort = 3306;
} else {
    $dbPort = (int)$dbPort;
    if ($dbPort <= 0) {
        $dbPort = 3306;
    }
}

// Allow DB_HOST like "localhost:3306" when DB_PORT isn't set.
if (is_string($dbHost) && strpos($dbHost, ':') !== false && (getenv('DB_PORT') === false || getenv('DB_PORT') === '')) {
    if (preg_match('/^(.+):(\d+)$/', $dbHost, $m)) {
        $dbHost = $m[1];
        $dbPort = (int)$m[2];
    }
}

$kon = @new mysqli($dbHost, $dbUser, $dbPass, $dbName, $dbPort);

if ($kon->connect_error) {
    error_log('DB connect failed: errno=' . $kon->connect_errno . ' error=' . $kon->connect_error . ' (host=' . $dbHost . ', port=' . $dbPort . ', user=' . $dbUser . ', db=' . $dbName . ')');
    if (PHP_SAPI !== 'cli') {
        http_response_code(500);
    }
    die('Database connection failed. Check credentials in koneksi.php (or env DB_HOST/DB_USER/DB_PASS/DB_NAME).');
}

// Ensure consistent encoding.
@$kon->set_charset('utf8mb4');

// Backward-compat: some files use $conn.
$conn = $kon;

// NO CLOSING TAG - best practice to avoid whitespace issues
