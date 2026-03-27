<?php
require 'koneksi.php';

$sql = "CREATE TABLE IF NOT EXISTS `request_pinjaman` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `id_karyawan` VARCHAR(50) NOT NULL,
    `id_aset` INT NOT NULL,
    `tgl_pinjam` DATE NOT NULL,
    `catatan` TEXT,
    `status` ENUM('PENDING', 'APPROVED', 'REJECTED') DEFAULT 'PENDING',
    `alasan_reject` TEXT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($kon->query($sql) === TRUE) {
    echo "Tabel request_pinjaman berhasil dibuat/dicek.\n";
} else {
    echo "Error membuat tabel: " . $kon->error;
}
?>
