<?php
$c = new mysqli('localhost', 'root', '', 'crud');
$r = $c->query('SHOW TABLES');
while($row = $r->fetch_array()) {
    echo "--- " . $row[0] . " ---\n";
    $r2 = $c->query("DESCRIBE " . $row[0]);
    if ($r2) {
        while($row2 = $r2->fetch_assoc()) {
            echo "  " . $row2['Field'] . " (" . $row2['Type'] . ")\n";
        }
    }
}
