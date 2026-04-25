<?php
session_start();
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../app_url.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . app_abs_path('login.php'));
    exit();
}

$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : 'user';
if ($user_role !== 'super_admin') {
    header('Location: ' . app_abs_path('admin/index.php'));
    exit();
}

$activePage = 'event_dashboard';
$Nama_Lengkap = isset($_SESSION['Nama_Lengkap']) ? $_SESSION['Nama_Lengkap'] : 'Admin User';
$Jabatan_Level = isset($_SESSION['Jabatan_Level']) ? $_SESSION['Jabatan_Level'] : '-';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id === 0) {
    header('Location: dashboard_event.php');
    exit();
}

$msg_success = '';
$msg_error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $no_finalis = trim($_POST['no_finalis'] ?? '');
    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $jenis_kelamin = trim($_POST['jenis_kelamin'] ?? '');
    $kategori = trim($_POST['kategori'] ?? '');
    $umur = intval($_POST['umur'] ?? 0);
    $kota = trim($_POST['kota'] ?? '');
    $nama_pic = trim($_POST['nama_pic'] ?? '');
    $no_wa = trim($_POST['no_wa'] ?? '');
    $catatan_materi = trim($_POST['catatan_materi'] ?? '');
    $status_pendaftaran = trim($_POST['status_pendaftaran'] ?? 'Tersubmit');

    // Existing paths
    $foto_path = $_POST['existing_foto'] ?? '';
    $video_path = $_POST['existing_video'] ?? '';

    if (empty($no_finalis) || empty($nama_lengkap) || empty($jenis_kelamin) || empty($kategori) || empty($umur) || empty($kota) || empty($nama_pic) || empty($no_wa)) {
        $msg_error = "Harap isi semua kolom wajib.";
    } else {
        $upload_ok = true;
        $upload_dir = 'uploads/event/';
        
        if (!file_exists(__DIR__ . '/../' . $upload_dir)) {
            mkdir(__DIR__ . '/../' . $upload_dir, 0777, true);
        }

        // Handle Photo Update (Max 1MB)
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] != UPLOAD_ERR_NO_FILE) {
            $foto_size = $_FILES['foto']['size'];
            $max_foto_size = 1 * 1024 * 1024;
            
            if ($foto_size > $max_foto_size) {
                $msg_error = "Ukuran foto terlalu besar. Maksimal 1 MB.";
                $upload_ok = false;
            } else {
                $orig_foto_name = basename($_FILES['foto']['name']);
                $clean_foto_name = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $orig_foto_name);
                $foto_new_name = time() . '_foto_' . $clean_foto_name;
                $target_foto = __DIR__ . '/../' . $upload_dir . $foto_new_name;
                
                if (move_uploaded_file($_FILES['foto']['tmp_name'], $target_foto)) {
                    // Remove old if exists
                    if (!empty($foto_path) && file_exists(__DIR__ . '/../' . $foto_path)) {
                        @unlink(__DIR__ . '/../' . $foto_path);
                    }
                    $foto_path = $upload_dir . $foto_new_name;
                } else {
                    $msg_error = "Gagal mengunggah foto.";
                    $upload_ok = false;
                }
            }
        }

        // Handle Video Update (Tanpa Batas Ukuran)
        if ($upload_ok && isset($_FILES['video']) && $_FILES['video']['error'] != UPLOAD_ERR_NO_FILE) {
            $orig_video_name = basename($_FILES['video']['name']);
            $clean_video_name = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $orig_video_name);
            $video_new_name = time() . '_video_' . $clean_video_name;
            $target_video = __DIR__ . '/../' . $upload_dir . $video_new_name;
            
            if (move_uploaded_file($_FILES['video']['tmp_name'], $target_video)) {
                // Remove old if exists
                if (!empty($video_path) && file_exists(__DIR__ . '/../' . $video_path)) {
                    @unlink(__DIR__ . '/../' . $video_path);
                }
                $video_path = $upload_dir . $video_new_name;
            } else {
                $msg_error = "Gagal mengunggah video.";
                $upload_ok = false;
            }
        }

        if ($upload_ok && empty($msg_error)) {
            $stmt = $conn->prepare("UPDATE event_finalis SET no_finalis=?, nama_lengkap=?, jenis_kelamin=?, kategori=?, umur=?, kota=?, nama_pic=?, no_wa=?, catatan_materi=?, status_pendaftaran=?, foto_path=?, video_path=? WHERE id=?");
            $stmt->bind_param("ssssisssssssi", $no_finalis, $nama_lengkap, $jenis_kelamin, $kategori, $umur, $kota, $nama_pic, $no_wa, $catatan_materi, $status_pendaftaran, $foto_path, $video_path, $id);
            
            if ($stmt->execute()) {
                $msg_success = "Data peserta berhasil diperbarui.";
            } else {
                $msg_error = "Gagal memperbarui data: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// Fetch current data
$stmt = $conn->prepare("SELECT * FROM event_finalis WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$current_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$current_data) {
    header('Location: dashboard_event.php');
    exit();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Finalis - Asset Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">

    <?php require_once __DIR__ . '/sidebar_admin.php'; ?>

    <div id="main-content-wrapper" class="lg:ml-60 transition-all duration-300 ease-in-out font-sans">
        <main class="p-6 lg:p-8 mt-16 lg:mt-0">
            <div class="mb-8 flex justify-between items-center bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 mb-2">Edit Data Finalis</h1>
                    <p class="text-gray-500">Ubah informasi registrasi untuk peserta.</p>
                </div>
                <div>
                    <a href="dashboard_event.php" class="flex items-center space-x-2 bg-gray-500 hover:bg-gray-600 text-white px-5 py-3 rounded-lg font-medium shadow transition-colors">
                        <i class="fas fa-arrow-left"></i>
                        <span>Kembali</span>
                    </a>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden p-8">
                <?php if (!empty($msg_success)): ?>
                    <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 rounded-r-lg">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle text-green-500 text-2xl mr-3"></i>
                            <p class="text-green-700 font-medium"><?php echo htmlspecialchars($msg_success); ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($msg_error)): ?>
                    <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded-r-lg">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-circle text-red-500 text-xl mr-3"></i>
                            <p class="text-red-700 font-medium"><?php echo htmlspecialchars($msg_error); ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <form action="" method="POST" enctype="multipart/form-data" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <label class="block text-sm font-semibold text-gray-700">No Finalis</label>
                            <input type="text" name="no_finalis" required value="<?php echo htmlspecialchars($current_data['no_finalis']); ?>"
                                class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-orange-500 outline-none transition-colors">
                        </div>

                        <div class="space-y-2">
                            <label class="block text-sm font-semibold text-gray-700">Nama Lengkap</label>
                            <input type="text" name="nama_lengkap" required value="<?php echo htmlspecialchars($current_data['nama_lengkap']); ?>"
                                class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-orange-500 outline-none transition-colors">
                        </div>

                        <div class="space-y-2">
                            <label class="block text-sm font-semibold text-gray-700">Jenis Kelamin</label>
                            <select name="jenis_kelamin" required class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-orange-500 outline-none transition-colors appearance-none">
                                <option value="" disabled <?php echo empty($current_data['jenis_kelamin']) ? 'selected' : ''; ?>>Pilih Jenis Kelamin</option>
                                <option value="Pria" <?php echo ($current_data['jenis_kelamin'] === 'Pria') ? 'selected' : ''; ?>>Pria</option>
                                <option value="Wanita" <?php echo ($current_data['jenis_kelamin'] === 'Wanita') ? 'selected' : ''; ?>>Wanita</option>
                            </select>
                        </div>

                        <div class="space-y-2">
                            <label class="block text-sm font-semibold text-gray-700">Kategori</label>
                            <select name="kategori" required class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-orange-500 outline-none transition-colors appearance-none">
                                <option value="" disabled <?php echo empty($current_data['kategori']) ? 'selected' : ''; ?>>Pilih Kategori</option>
                                <option value="A" <?php echo ($current_data['kategori'] === 'A') ? 'selected' : ''; ?>>Kategori A</option>
                                <option value="B" <?php echo ($current_data['kategori'] === 'B') ? 'selected' : ''; ?>>Kategori B</option>
                                <option value="C" <?php echo ($current_data['kategori'] === 'C') ? 'selected' : ''; ?>>Kategori C</option>
                                <option value="D" <?php echo ($current_data['kategori'] === 'D') ? 'selected' : ''; ?>>Kategori D</option>
                            </select>
                        </div>

                        <div class="space-y-2">
                            <label class="block text-sm font-semibold text-gray-700">Umur/Usia</label>
                            <input type="number" name="umur" min="1" required value="<?php echo htmlspecialchars($current_data['umur']); ?>"
                                class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-orange-500 outline-none transition-colors">
                        </div>

                        <div class="space-y-2">
                            <label class="block text-sm font-semibold text-gray-700">Kota Asal</label>
                            <input type="text" name="kota" required value="<?php echo htmlspecialchars($current_data['kota']); ?>"
                                class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-orange-500 outline-none transition-colors">
                        </div>

                        <div class="space-y-2">
                            <label class="block text-sm font-semibold text-gray-700">Nama PIC</label>
                            <input type="text" name="nama_pic" required value="<?php echo htmlspecialchars($current_data['nama_pic']); ?>"
                                class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-orange-500 outline-none transition-colors">
                        </div>

                        <div class="space-y-2">
                            <label class="block text-sm font-semibold text-gray-700">No WhatsApp</label>
                            <input type="text" name="no_wa" required value="<?php echo htmlspecialchars($current_data['no_wa']); ?>"
                                class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-orange-500 outline-none transition-colors">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                        <div class="space-y-2">
                            <label class="block text-sm font-semibold text-gray-700">Catatan Materi Perform</label>
                            <textarea name="catatan_materi" rows="3" class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-orange-500 outline-none transition-colors"><?php echo htmlspecialchars($current_data['catatan_materi'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="space-y-2">
                            <label class="block text-sm font-semibold text-gray-700">Status Pendaftaran</label>
                            <select name="status_pendaftaran" required class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-orange-500 outline-none transition-colors appearance-none font-medium">
                                <option value="Tersubmit" <?php echo ($current_data['status_pendaftaran'] === 'Tersubmit') ? 'selected' : ''; ?>>Tersubmit</option>
                                <option value="Berkas Tidak Lengkap" <?php echo ($current_data['status_pendaftaran'] === 'Berkas Tidak Lengkap') ? 'selected' : ''; ?>>Berkas Tidak Lengkap</option>
                                <option value="Data Diterima" <?php echo ($current_data['status_pendaftaran'] === 'Data Diterima') ? 'selected' : ''; ?>>Data Diterima</option>
                                <option value="Ditolak" <?php echo ($current_data['status_pendaftaran'] === 'Ditolak') ? 'selected' : ''; ?>>Ditolak</option>
                            </select>
                            <p class="text-xs text-gray-400">Gunakan status ini untuk melacak kelengkapan data peserta.</p>
                        </div>
                    </div>

                    <div class="border-t border-gray-100 pt-6 mt-6">
                        <h3 class="text-lg font-bold text-gray-800 mb-4">Berkas Media</h3>
                        <input type="hidden" name="existing_foto" value="<?php echo htmlspecialchars($current_data['foto_path']); ?>">
                        <input type="hidden" name="existing_video" value="<?php echo htmlspecialchars($current_data['video_path']); ?>">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Foto Update -->
                            <div class="space-y-2">
                                <label class="block text-sm font-semibold text-gray-700">Perbarui Foto (Maks 1MB)</label>
                                <input type="file" name="foto" accept="image/*" class="w-full px-4 py-2 border rounded-lg">
                                <?php if (!empty($current_data['foto_path'])): ?>
                                    <p class="text-xs text-blue-600 mt-1"><i class="fas fa-check-circle"></i> File foto saat ini sudah ada. Biarkan kosong jika tidak ingin mengubah.</p>
                                    <div class="mt-2">
                                        <img src="<?php echo app_abs_path($current_data['foto_path']); ?>" alt="foto" class="h-24 rounded border">
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Video/Audio Update -->
                            <div class="space-y-2">
                                <label class="block text-sm font-semibold text-gray-700">Perbarui Video / Audio (Tanpa Batas Ukuran)</label>
                                <input type="file" name="video" accept="video/*,audio/mpeg,audio/mp3,audio/*" class="w-full px-4 py-2 border rounded-lg">
                                <?php if (!empty($current_data['video_path'])): ?>
                                    <p class="text-xs text-green-600 mt-1"><i class="fas fa-check-circle"></i> File video saat ini sudah ada. Biarkan kosong jika tidak ingin mengubah.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="pt-6">
                        <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white font-bold py-3 px-8 rounded-xl shadow-lg transition-colors">
                            <i class="fas fa-save mr-2"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        window.addEventListener('sidebarToggled', function (e) {
            var wrapper = document.getElementById('main-content-wrapper');
            if (window.innerWidth >= 1024) {
                wrapper.style.marginLeft = e.detail.collapsed ? '0' : '15rem';
            }
        });
    </script>
</body>
</html>
