<?php
session_start();
require_once __DIR__ . '/../koneksi.php';

// Check if user is admin
$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : 'user';
if ($user_role !== 'super_admin') {
    die('Unauthorized access.');
}

$all = isset($_GET['all']) ? intval($_GET['all']) : 0;
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($all === 1) {
    $stmt = $conn->prepare("SELECT * FROM event_finalis");
    $stmt->execute();
    $result = $stmt->get_result();
    $filename = "Semua_Finalis_Event_" . date('Ymd_His') . ".zip";
} elseif ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM event_finalis WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // We will get the first (and only) row to name the zip specifically if single download
    $row = $result->fetch_assoc();
    if (!$row) {
        die("Data tidak ditemukan.");
    }
    
    // Prepare naming convention
    $no_finalis = preg_replace('/[^a-zA-Z0-9]/', '', $row['no_finalis']);
    $nama = trim(preg_replace('/[^a-zA-Z0-9\s]/', '', $row['nama_lengkap']));
    $raw_kategori = trim(preg_replace('/[^a-zA-Z0-9]/', '', $row['kategori']));
    $kategori = !empty($raw_kategori) ? 'Kategori_' . $raw_kategori : 'Kategori_Tidak_Diketahui';
    $catatan = trim(preg_replace('/[^a-zA-Z0-9\s]/', '', substr($row['catatan_materi'], 0, 50)));
    if (empty($catatan)) { $catatan = 'Tanpa Catatan'; }
    
    $folderName = $no_finalis . "_" . str_replace(" ", "_", $nama) . "_" . $kategori . "_" . str_replace(" ", "_", $catatan);
    $filename = $folderName . ".zip";
    
    // We need to re-execute or just use array since we already fetched
    $dataArray = [$row];
} else {
    die("Invalid request");
}

if ($all === 1) {
    $dataArray = [];
    while ($r = $result->fetch_assoc()) {
        $dataArray[] = $r;
    }
}

if (count($dataArray) === 0) {
    die("Tidak ada data untuk didownload.");
}

$zip = new ZipArchive();
$zipPath = sys_get_temp_dir() . '/' . uniqid('zip_') . '.zip';

if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
    die("Cannot create zip file.");
}

foreach ($dataArray as $row) {
    $no_finalis = preg_replace('/[^a-zA-Z0-9]/', '', $row['no_finalis']);
    $nama = trim(preg_replace('/[^a-zA-Z0-9\s]/', '', $row['nama_lengkap']));
    $raw_kategori = trim(preg_replace('/[^a-zA-Z0-9]/', '', $row['kategori']));
    $kategori = !empty($raw_kategori) ? 'Kategori_' . $raw_kategori : 'Kategori_Tidak_Diketahui';
    $catatan = trim(preg_replace('/[^a-zA-Z0-9\s]/', '', substr($row['catatan_materi'], 0, 50)));
    if (empty($catatan)) { $catatan = 'Tanpa Catatan'; }
    
    if (isset($all) && $all === 1) {
        // Group into category folders if downloading all
        $folderName = $kategori . '/' . $no_finalis . "_" . str_replace(" ", "_", $nama) . "_" . str_replace(" ", "_", $catatan);
    } else {
        $folderName = $no_finalis . "_" . str_replace(" ", "_", $nama) . "_" . $kategori . "_" . str_replace(" ", "_", $catatan);
    }
    
    // Add directory for this finalis
    $zip->addEmptyDir($folderName);
    
    // Add foto
    if (!empty($row['foto_path'])) {
        $foto_path = __DIR__ . '/../' . $row['foto_path'];
        if (file_exists($foto_path)) {
            $foto_basename = basename($foto_path);
            $clean_foto_name = preg_replace('/^[0-9]+_foto_/', '', $foto_basename);
            $zip->addFile($foto_path, $folderName . '/' . $clean_foto_name);
        }
    }
    
    // Add video
    if (!empty($row['video_path'])) {
        $video_path = __DIR__ . '/../' . $row['video_path'];
        if (file_exists($video_path)) {
            $video_basename = basename($video_path);
            $clean_video_name = preg_replace('/^[0-9]+_video_/', '', $video_basename);
            $zip->addFile($video_path, $folderName . '/' . $clean_video_name);
        }
    }
}

$zip->close();

if (file_exists($zipPath)) {
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($zipPath));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    readfile($zipPath);
    @unlink($zipPath); // clean up
    exit;
} else {
    die("Gagal memproses file ZIP.");
}
?>
