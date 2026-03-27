<?php
// Central DB connection file.
// On hosting, set credentials here (or via env vars DB_HOST/DB_USER/DB_PASS/DB_NAME).

mysqli_report(MYSQLI_REPORT_OFF);

$dbHost = getenv('DB_HOST');
$dbUser = getenv('DB_USER');
$dbPass = getenv('DB_PASS');
$dbName = getenv('DB_NAME');

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

$kon = @new mysqli($dbHost, $dbUser, $dbPass, $dbName);

if ($kon->connect_error) {
    error_log('DB connect failed: ' . $kon->connect_error . ' (host=' . $dbHost . ', user=' . $dbUser . ', db=' . $dbName . ')');
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
