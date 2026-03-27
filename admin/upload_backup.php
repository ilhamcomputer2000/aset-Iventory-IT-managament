<?php
if (isset($_FILES['Photo_Barang'])) {
    $target_dir = "../uploads/";
    $uploadOk = 1;

    // Cek apakah file benar-benar gambar
    $check = getimagesize($_FILES["Photo_Barang"]["tmp_name"]);
    if ($check === false) {
        echo "File bukan gambar.";
        $uploadOk = 0;
    }

    // Ambil ekstensi file asli
    $imageFileType = strtolower(pathinfo($_FILES["Photo_Barang"]["name"], PATHINFO_EXTENSION));

    // Cek ukuran file (misal max 500 KB)
    if ($_FILES["Photo_Barang"]["size"] > 500000) {
        echo "File terlalu besar.";
        $uploadOk = 0;
    }

    // Cek ekstensi file yang diizinkan
    $allowedTypes = ['jpg', 'jpeg', 'png'];
    if (!in_array($imageFileType, $allowedTypes)) {
        echo "Hanya file JPG, JPEG, & PNG yang diperbolehkan.";
        $uploadOk = 0;
    }

    if ($uploadOk == 0) {
        echo "File tidak bisa diupload.";
    } else {
        // Buat folder uploads jika belum ada
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        // Buat nama file baru dengan format Foto_evidence_Asset_ITCKT_TGLTAHUN_WAKTU.ext
        // Contoh: Foto_evidence_Asset_ITCKT_20240610_153045.jpg
        $date = date('Ymd');      // Tanggal: YYYYMMDD
        $time = date('His');      // Waktu: HHMMSS
        $file_name = "Foto_evidence_Asset_ITCKT_" . $date . "_" . $time . "." . $imageFileType;
        $target_file = $target_dir . $file_name;

        // Upload file dengan nama baru
        if (move_uploaded_file($_FILES["Photo_Barang"]["tmp_name"], $target_file)) {
            echo "File " . htmlspecialchars($file_name) . " sudah diupload.";
            // Simpan nama file ke database jika perlu
        } else {
            echo "Ada error saat mengupload file.";
        }
    }
}
?>

