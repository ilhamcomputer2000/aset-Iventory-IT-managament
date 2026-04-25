<?php
$conn = new mysqli('localhost', 'root', '', 'crud');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$sql = "ALTER TABLE event_finalis ADD COLUMN status_pendaftaran VARCHAR(50) NOT NULL DEFAULT 'Menunggu Verifikasi' AFTER video_path";
if ($conn->query($sql) === TRUE) {
    echo "Column status_pendaftaran added successfully";
} else {
    echo "Error: " . $conn->error;
}
$conn->close();
?>
