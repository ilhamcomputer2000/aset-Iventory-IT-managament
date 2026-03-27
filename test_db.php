<?php
require 'koneksi.php';
$res = $kon->query('DESCRIBE users');
while($r = $res->fetch_assoc()) {
    echo $r['Field'] . "\n";
}
