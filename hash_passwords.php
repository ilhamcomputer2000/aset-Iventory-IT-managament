<?php
include __DIR__ . "/koneksi.php";

// Ambil semua user
$sql = "SELECT id, password FROM users";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $id = $row['id'];
        $password = $row['password'];

        // Skip kalau sudah hashed (biasanya panjang hash > 50)
        if (strlen($password) < 50) {
            $hashed = password_hash($password, PASSWORD_DEFAULT);

            $update = $conn->prepare("UPDATE users SET password=? WHERE id=?");
            $update->bind_param("si", $hashed, $id);
            $update->execute();

            echo "User ID $id password berhasil di-hash<br>";
        } else {
            echo "User ID $id sudah hashed, dilewati<br>";
        }
    }
} else {
    echo "Tidak ada user di tabel users.";
}

$conn->close();
