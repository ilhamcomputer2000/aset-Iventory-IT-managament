<?php
$conn = new mysqli('localhost', 'root', '', 'crud');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$sql = "ALTER TABLE event_finalis ADD COLUMN kategori ENUM('A', 'B', 'C', 'D') NOT NULL AFTER jenis_kelamin";
if ($conn->query($sql) === TRUE) {
    echo "Column kategori added successfully";
} else {
    echo "Error: " . $conn->error;
}
$conn->close();
?>
