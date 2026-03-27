<?php
require('libs/fpdf/fpdf.php'); // Pastikan path sesuai dengan lokasi FPDF Anda
include('config.php');

// Periksa apakah ID diberikan
if (isset($_GET['id'])) {
    $id = $_GET['id'];

    // Ambil data dari database
    $query = "SELECT * FROM your_table WHERE id = $id";
    $result = mysqli_query($conn, $query);
    $data = mysqli_fetch_assoc($result);

    if ($data) {
        // Membuat objek FPDF
        $pdf = new FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 16);

        // Judul
        $pdf->Cell(0, 10, 'Laporan Data Barang', 0, 1, 'C');
        $pdf->Ln(10);

        // Isi PDF sesuai keinginan
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(50, 10, 'ID Barang:', 0, 0);
        $pdf->Cell(100, 10, $data['id'], 0, 1);
        $pdf->Cell(50, 10, 'Nama Barang:', 0, 0);
        $pdf->Cell(100, 10, $data['nama_barang'], 0, 1);
        $pdf->Cell(50, 10, 'Merek:', 0, 0);
        $pdf->Cell(100, 10, $data['merek'], 0, 1);
        // Tambahkan kolom lain sesuai kebutuhan

        // Output PDF
        $pdf->Output();
    } else {
        echo "Data tidak ditemukan.";
    }
} else {
    echo "ID tidak diberikan.";
}
?>
