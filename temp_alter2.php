<?php
$conn = new mysqli('localhost', 'root', '', 'crud');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$sql = "ALTER TABLE event_finalis ADD COLUMN jenis_kelamin ENUM('Pria', 'Wanita') NOT NULL AFTER nama_lengkap";
if ($conn->query($sql) === TRUE) {
    echo "Column jenis_kelamin added successfully";
} else {
    echo "Error: " . $conn->error;
}
$conn->close();
?>
