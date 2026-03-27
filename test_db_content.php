<?php
require 'koneksi.php';
$res = $kon->query("SELECT * FROM users WHERE username = 'C2505003'");
print_r($res->fetch_assoc());
