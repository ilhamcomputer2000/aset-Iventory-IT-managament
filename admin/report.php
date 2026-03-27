<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Responsive Design</title>
    <link rel="stylesheet" href="style.css"> <!-- Link ke CSS -->
    <style>
        /* style.css */
.container {
    max-width: 1200px; /* Maksimal lebar kontainer */
    margin: auto; /* Center kontainer */
    padding: 20px;
}

.table {
    width: 100%; /* Menggunakan 100% dari lebar kontainer */
    border-collapse: collapse; /* Menghilangkan jarak antar border */
}

.table th, .table td {
    padding: 8px;
    border: 1px solid #ddd;
    text-align: left; /* Atur teks ke kiri */
}

@media (max-width: 768px) {
    .table th, .table td {
        font-size: 14px; /* Mengurangi ukuran font pada layar kecil */
    }
}

    </style>
</head>
<body>
    <div class="container">
        <h1>Tabel Data</h1>
        <table class="table">
            <thead>
                <tr>
                    <th>Nama</th>
                    <th>Usia</th>
                    <th>Alamat</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Alice</td>
                    <td>30</td>
                    <td>Jakarta</td>
                </tr>
                <!-- Baris lainnya -->
            </tbody>
        </table>
    </div>
</body>
</html>
