<?php
include '../koneksi.php';

// Debug: Cek apakah ID diterima dengan benar
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
} else {
    echo "ID tidak ditemukan.";
    exit;
}

if ($id > 0) {
    $sql = "DELETE FROM peserta WHERE id = ?";
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            echo "Data berhasil dihapus.";
        } else {
            echo "Data tidak ditemukan atau sudah dihapus.";
        }

        $stmt->close();
    } else {
        echo "Query gagal.";
    }
} else {
    echo "ID tidak valid.";
}

if (isset($conn)) {
    $conn->close();
}
?>
