<?php
require 'vendor/autoload.php';

use PhpOffice\PhpWord\IOFactory;
use TCPDF;

if (isset($_GET['id_peserta'])) {
    // Ambil data peserta berdasarkan id
    $id_peserta = $_GET['id_peserta'];
    // Query database untuk mendapatkan informasi yang ingin ditampilkan
    // (Anda bisa menambahkan query yang sesuai untuk mendapatkan data peserta berdasarkan id)

    // Path file Word yang ingin diubah menjadi PDF
    $wordFilePath = 'path/to/your/word/file.docx'; // Sesuaikan path file Word yang ingin diubah

    // Baca file Word menggunakan PHPWord
    $phpWord = IOFactory::load($wordFilePath, 'Word2007');

    // Konversi isi Word menjadi HTML (untuk di-render oleh TCPDF)
    $htmlWriter = IOFactory::createWriter($phpWord, 'HTML');
    ob_start();
    $htmlWriter->save('php://output');
    $htmlContent = ob_get_clean();

    // Buat instance TCPDF
    $pdf = new TCPDF();

    // Set pengaturan PDF
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('Your Name');
    $pdf->SetTitle('PDF Generated from Word');
    $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
    $pdf->AddPage();

    // Render HTML content ke PDF
    $pdf->writeHTML($htmlContent);

    // Output PDF
    $pdf->Output('output.pdf', 'I');
} else {
    echo "ID Peserta tidak ditemukan!";
}
