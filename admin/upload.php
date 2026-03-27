<?php
// TAMBAHAN: Headers untuk disable cache gambar (force load ulang setiap kali akses URL)
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: text/html; charset=utf-8');  // Untuk output HTML/JSON

if (isset($_FILES['Photo_Barang'])) {
    $target_dir = "../uploads/";
    $uploadOk = 1;

    // Validasi apakah file adalah gambar
    $check = getimagesize($_FILES["Photo_Barang"]["tmp_name"]);
    if ($check === false) {
        echo json_encode(['status' => 'error', 'message' => 'File bukan gambar.']);
        $uploadOk = 0;
        exit();
    }

    // Ambil ekstensi file
    $imageFileType = strtolower(pathinfo($_FILES["Photo_Barang"]["name"], PATHINFO_EXTENSION));

    // Validasi ukuran file (maks 500KB, bisa diubah)
    if ($_FILES["Photo_Barang"]["size"] > 500000) {
        echo json_encode(['status' => 'error', 'message' => 'File terlalu besar (maksimum 500KB).']);
        $uploadOk = 0;
        exit();
    }

    // Validasi tipe file (hanya jpg, jpeg, png)
    $allowedTypes = ['jpg', 'jpeg', 'png'];
    if (!in_array($imageFileType, $allowedTypes)) {
        echo json_encode(['status' => 'error', 'message' => 'Hanya file JPG, JPEG, & PNG yang diperbolehkan.']);
        $uploadOk = 0;
        exit();
    }

    if ($uploadOk == 0) {
        echo json_encode(['status' => 'error', 'message' => 'File tidak bisa diupload.']);
        exit();
    } else {
        // Buat direktori jika belum ada
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        // Generate nama file baru dengan format Foto_evidence_Asset_ITCKT_tglbulantahun_waktu.ext
        $day = date('d');       // Hari (DD)
        $month = date('m');     // Bulan (MM)
        $year = date('Y');      // Tahun (YYYY)
        $hour = date('H');      // Jam (HH, 24-jam)
        $minute = date('i');    // Menit (MM)
        $second = date('s');    // Detik (SS)
        
        $tglbulantahun = $day . $month . $year;  // DDMMYYYY (misal: 25102024)
        $waktu = $hour . $minute . $second;      // HHMMSS (misal: 143022)
        
        // Opsional: Tambah uniqid() jika ingin unik (uncomment jika perlu)
        // $unique_id = '_' . uniqid();
        $unique_id = '';  // Kosong untuk format sederhana
        
        $file_name = "Foto_evidence_Asset_ITCKT_" . $tglbulantahun . "_" . $waktu . $unique_id . "." . $imageFileType;
        $target_file = $target_dir . $file_name;

        // Output nama file baru (untuk debug)
        echo "Nama file baru yang akan disimpan: " . htmlspecialchars($file_name) . "<br>";

        // Upload file
        if (move_uploaded_file($_FILES["Photo_Barang"]["tmp_name"], $target_file)) {
            // Output sukses
            echo json_encode([
                'status' => 'success', 
                'message' => 'File ' . htmlspecialchars($file_name) . ' sudah diupload.',
                'filename' => $file_name,
                'filepath' => $target_file
            ]) . "<br>";

            // TAMBAHAN: Base URL dynamic (otomatis detect localhost/domain)
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
            $host = $_SERVER['HTTP_HOST'];  // localhost atau domain
            $base_url = $protocol . $host . "/crud/uploads/";  // Sesuaikan '/crud/' jika folder beda

            // Cache-buster dengan timestamp + random (lebih kuat)
            $cache_buster = time() . rand(1000, 9999);  // Timestamp + random 4 digit
            $image_url = $base_url . $file_name . '?v=' . $cache_buster;
            
            echo '<img src="' . htmlspecialchars($image_url) . '" alt="Foto Barang" style="max-width:300px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">';

            // TAMBAHAN: Link test untuk verifikasi tab baru/download (paksa no-cache)
            echo '<br><br><strong>Test Tab Baru/Download (dengan no-cache):</strong><br>';
            echo '<a href="' . htmlspecialchars($image_url) . '" target="_blank" rel="noopener noreferrer">Buka Gambar di Tab Baru (harus nama baru + ?v=)</a><br>';
            echo '<a href="' . htmlspecialchars($image_url) . '" download="' . htmlspecialchars($file_name) . '" rel="noopener noreferrer">Download Gambar (nama harus Foto_evidence_...)</a><br>';

            // TAMBAHAN: Debug - Tampilkan URL lengkap untuk copy-paste test
            echo '<br><strong>URL Lengkap untuk Test Manual:</strong><br>';
            echo htmlspecialchars($image_url) . '<br>';
            echo '<small>Copy URL ini, paste di tab baru, atau hard refresh (Ctrl+Shift+R) untuk lihat nama file benar.</small>';

            // TAMBAHAN: Jika perlu simpan $file_name ke database, lakukan di sini
            // Contoh: include "../koneksi.php"; $conn->query("UPDATE peserta SET Photo_Barang = '$file_name' WHERE id_peserta = $id");

        } else {
            echo json_encode(['status' => 'error', 'message' => 'Ada error saat mengupload file. Cek permission direktori (chmod 755/777 untuk uploads).']);
        }
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Tidak ada file yang diupload.']);
}
?>