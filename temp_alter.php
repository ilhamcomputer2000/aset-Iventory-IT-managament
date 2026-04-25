<?php
$conn = new mysqli('localhost', 'root', '', 'crud');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$sql = "ALTER TABLE event_finalis ADD COLUMN catatan_materi TEXT NULL AFTER no_wa";
if ($conn->query($sql) === TRUE) {
    echo "Column added successfully";
} else {
    echo "Error: " . $conn->error;
}
$conn->close();
?>