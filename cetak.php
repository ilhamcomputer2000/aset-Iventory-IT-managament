<?php
require 'vendor/autoload.php';

use PhpOffice\PhpWord\TemplateProcessor;
use TCPDF;

// Ambil ID data yang akan dicetak
$id = $_GET['id'];

// Koneksi database
$conn = new mysqli('localhost', 'root', '', 'crud');

// Cek koneksi
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Ambil data berdasarkan ID
$sql = "SELECT * FROM peserta WHERE id = $id";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();

    // Load dokumen Word template
    $templateProcessor = new TemplateProcessor('template.docx');

    // Isi template dengan data dari database
    $templateProcessor->setValue('Nama_Barang', $row['Nama_Barang']);
    $templateProcessor->setValue('Merek', $row['Merek']);
    // Tambahkan value lainnya sesuai kebutuhan

    // Simpan dokumen Word sementara
    $templateProcessor->saveAs('temp.docx');

    // Buat instance TCPDF dan mulai output buffering
    ob_start();
    $pdf = new TCPDF();
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 12);

    // Konversi dokumen Word ke teks dan tambahkan ke PDF
    $content = file_get_contents('temp.docx');
    $pdf->Write(0, $content);

    // Output PDF
    $pdf->Output('output.pdf', 'I'); // 'I' untuk menampilkan di browser, 'D' untuk mendownload

    // Hapus file sementara
    unlink('temp.docx');

    // Membersihkan output buffer
    ob_end_clean();
} else {
    echo "Data tidak ditemukan.";
}

$conn->close();
?>
