<?php
// Simple version & diagnostics check for create.php compatibility

$output = array();

$output['PHP Version'] = phpversion();
$output['PHP SAPI'] = php_sapi_name();

// Check key features needed by create.php
$output['Support mysqli'] = extension_loaded('mysqli') ? 'YES' : 'NO';
$output['Support json_encode'] = function_exists('json_encode') ? 'YES' : 'NO';
$output['Support filter'] = extension_loaded('filter') ? 'YES' : 'NO';
$output['Support gd (images)'] = extension_loaded('gd') ? 'YES' : 'NO';

// Check critical arrays for create.php
$output['$_SESSION'] = isset($_SESSION) ? 'Available' : 'NOT available';
$output['$_FILES'] = isset($_FILES) ? 'Available' : 'NOT available';
$output['$_POST'] = isset($_POST) ? 'Available' : 'NOT available';

// Database
$kon = @new mysqli("localhost", "root", "", "crud");
if ($kon->connect_error) {
    $output['Database Connection'] = 'FAILED: ' . $kon->connect_error;
} else {
    $output['Database Connection'] = 'OK (DB: crud)';
    $output['MySQL Version'] = $kon->server_info;
    $kon->close();
}

// Output as plain text
header('Content-Type: text/plain; charset=utf-8');
foreach ($output as $key => $value) {
    echo $key . ": " . $value . "\n";
}
?>
