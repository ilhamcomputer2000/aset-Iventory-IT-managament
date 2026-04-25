<?php
$conn = new mysqli('localhost', 'root', '', 'crud');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$sql = "ALTER TABLE event_finalis ALTER status_pendaftaran SET DEFAULT 'Tersubmit'";
$conn->query($sql);

$sql_update = "UPDATE event_finalis SET status_pendaftaran = 'Tersubmit' WHERE status_pendaftaran = 'Menunggu Verifikasi'";
$conn->query($sql_update);

echo "Update default value to 'Tersubmit' executed.\n";
$conn->close();
?>
