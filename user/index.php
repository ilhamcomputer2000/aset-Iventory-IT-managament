<?php
// Entry point for /crud/user/
// Redirect to the main user list page.

if (!headers_sent()) {
  $queryString = isset($_SERVER['QUERY_STRING']) ? (string)$_SERVER['QUERY_STRING'] : '';
  $suffix = $queryString !== '' ? ('?' . $queryString) : '';

  if (is_file(__DIR__ . '/view.php')) {
    header('Location: view.php' . $suffix);
    exit;
  }

  if (is_file(__DIR__ . '/dashboard_user.php')) {
    header('Location: dashboard_user.php' . $suffix);
    exit;
  }
}

// Fallback: show a minimal message if headers already sent or files missing.
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>User</title>
</head>
<body>
  <p>Halaman user tidak ditemukan. Coba buka <a href="dashboard_user.php">dashboard_user.php</a> atau <a href="view.php">view.php</a>.</p>
</body>
</html>
