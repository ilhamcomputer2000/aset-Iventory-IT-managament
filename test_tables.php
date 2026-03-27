<?php
require 'koneksi.php';
$res = $kon->query("SHOW TABLES");
while ($row = $res->fetch_array()) {
    echo $row[0] . "\n";
}
