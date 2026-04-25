<?php
$conn = new mysqli('localhost', 'root', '', 'crud');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "CREATE TABLE IF NOT EXISTS event_finalis (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    no_finalis VARCHAR(50) NOT NULL,
    nama_lengkap VARCHAR(150) NOT NULL,
    umur INT(3) NOT NULL,
    kota VARCHAR(100) NOT NULL,
    nama_pic VARCHAR(150) NOT NULL,
    no_wa VARCHAR(20) NOT NULL,
    foto_path VARCHAR(255) NULL,
    video_path VARCHAR(255) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "Table event_finalis created successfully\n";
} else {
    echo "Error creating table: " . $conn->error . "\n";
}

$conn->close();
?>
