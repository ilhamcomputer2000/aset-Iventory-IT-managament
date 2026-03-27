<?php
// Backward-compat wrapper: many files include config.php expecting $conn.
require_once __DIR__ . '/koneksi.php';

// koneksi.php defines $kon and $conn; keep $conn explicitly for clarity.
$conn = $kon;

// NO CLOSING TAG
