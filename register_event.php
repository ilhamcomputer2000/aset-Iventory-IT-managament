<?php
session_start();
require_once __DIR__ . '/koneksi.php';

$success_msg = '';
$error_msg = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Collect form data
    $no_finalis = trim($_POST['no_finalis'] ?? '');
    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $jenis_kelamin = trim($_POST['jenis_kelamin'] ?? '');
    $kategori = trim($_POST['kategori'] ?? '');
    $umur = intval($_POST['umur'] ?? 0);
    $kota = trim($_POST['kota'] ?? '');
    $nama_pic = trim($_POST['nama_pic'] ?? '');
    $no_wa = trim($_POST['no_wa'] ?? '');
    $catatan_materi = trim($_POST['catatan_materi'] ?? '');

    // Validate
    if (empty($no_finalis) || empty($nama_lengkap) || empty($jenis_kelamin) || empty($kategori) || empty($umur) || empty($kota) || empty($nama_pic) || empty($no_wa)) {
        $error_msg = "Harap isi semua kolom wajib.";
    } else {
        $foto_path = '';
        $video_path = '';
        $upload_dir = 'uploads/event/';

        // Cek dan buat direktori jika belum ada
        if (!file_exists(__DIR__ . '/' . $upload_dir)) {
            mkdir(__DIR__ . '/' . $upload_dir, 0777, true);
        }

        $upload_ok = true;

        // Handle Photo Upload (Max 1MB)
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] != UPLOAD_ERR_NO_FILE) {
            $foto_size = $_FILES['foto']['size'];
            $max_foto_size = 1 * 1024 * 1024; // 1 MB

            if ($foto_size > $max_foto_size) {
                $error_msg = "Ukuran foto terlalu besar. Maksimal 1 MB.";
                $upload_ok = false;
            } else {
                $orig_foto_name = basename($_FILES['foto']['name']);
                $clean_foto_name = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $orig_foto_name);
                $foto_new_name = time() . '_foto_' . $clean_foto_name;
                $target_foto = __DIR__ . '/' . $upload_dir . $foto_new_name;

                if (move_uploaded_file($_FILES['foto']['tmp_name'], $target_foto)) {
                    $foto_path = $upload_dir . $foto_new_name;
                } else {
                    $error_msg = "Gagal mengunggah foto.";
                    $upload_ok = false;
                }
            }
        }

        // Handle Video Upload (Tanpa Batas Ukuran)
        if ($upload_ok && isset($_FILES['video']) && $_FILES['video']['error'] != UPLOAD_ERR_NO_FILE) {
            $orig_video_name = basename($_FILES['video']['name']);
            $clean_video_name = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $orig_video_name);
            $video_new_name = time() . '_video_' . $clean_video_name;
            $target_video = __DIR__ . '/' . $upload_dir . $video_new_name;

            if (move_uploaded_file($_FILES['video']['tmp_name'], $target_video)) {
                $video_path = $upload_dir . $video_new_name;
            } else {
                $error_msg = "Gagal mengunggah video.";
                $upload_ok = false;
            }
        }

        // Insert into DB if all uploads and validations pass
        if ($upload_ok && empty($error_msg)) {
            $stmt = $conn->prepare("INSERT INTO event_finalis (no_finalis, nama_lengkap, jenis_kelamin, kategori, umur, kota, nama_pic, no_wa, catatan_materi, foto_path, video_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssissssss", $no_finalis, $nama_lengkap, $jenis_kelamin, $kategori, $umur, $kota, $nama_pic, $no_wa, $catatan_materi, $foto_path, $video_path);

            if ($stmt->execute()) {
                $success_msg = "Pendaftaran berhasil! Terimakasih telah mendaftar.";
            } else {
                $error_msg = "Terjadi kesalahan saat menyimpan data: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pendaftaran Finalis Event</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="bg-gray-50 flex items-center justify-center min-h-screen p-4 py-10 font-sans">

    <div
        class="w-full max-w-2xl bg-white rounded-2xl shadow-xl overflow-hidden animate-fade-in relative z-10 border border-gray-100">

        <!-- Header -->
        <div class="bg-gradient-to-r from-orange-500 to-orange-600 px-8 py-6 text-white text-center">
            <h1 class="text-3xl font-extrabold mb-2 tracking-wide">Registrasi Finalis</h1>
            <p class="text-orange-100 font-medium">Mohon isi data di bawah dengan lengkap dan benar.</p>
        </div>

        <div class="p-8">
            <?php if (!empty($success_msg)): ?>
                <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 rounded-r-lg">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle text-green-500 text-2xl mr-3"></i>
                        <div>
                            <h3 class="text-green-800 font-bold">Sukses!</h3>
                            <p class="text-green-700 text-sm"><?php echo htmlspecialchars($success_msg); ?></p>
                        </div>
                    </div>
                </div>

                <div class="text-center mt-6">
                    <a href="register_event.php"
                        class="bg-orange-50 text-orange-600 hover:bg-orange-100 hover:text-orange-700 rounded-lg px-6 py-3 font-semibold transition-colors inline-block">Daftarkan
                        Peserta Lain</a>
                </div>

            <?php else: ?>

                <?php if (!empty($error_msg)): ?>
                    <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded-r-lg">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-circle text-red-500 text-xl mr-3"></i>
                            <p class="text-red-700"><?php echo htmlspecialchars($error_msg); ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <form action="" method="POST" enctype="multipart/form-data" class="space-y-6">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- No Finalis -->
                        <div class="space-y-2">
                            <label class="block text-sm font-semibold text-gray-700">No Finalis <span
                                    class="text-red-500">*</span></label>
                            <div class="relative">
                                <span class="absolute left-3 top-3 text-gray-400"><i class="fas fa-id-badge"></i></span>
                                <input type="text" name="no_finalis" required placeholder="Contoh: FN-001"
                                    class="w-full pl-10 pr-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 outline-none transition-colors">
                            </div>
                        </div>

                        <!-- Nama Lengkap -->
                        <div class="space-y-2">
                            <label class="block text-sm font-semibold text-gray-700">Nama Lengkap <span
                                    class="text-red-500">*</span></label>
                            <div class="relative">
                                <span class="absolute left-3 top-3 text-gray-400"><i class="fas fa-user"></i></span>
                                <input type="text" name="nama_lengkap" required placeholder="Nama lengkap sesuai KTP"
                                    class="w-full pl-10 pr-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 outline-none transition-colors">
                            </div>
                        </div>

                        <!-- Jenis Kelamin -->
                        <div class="space-y-2">
                            <label class="block text-sm font-semibold text-gray-700">Jenis Kelamin <span
                                    class="text-red-500">*</span></label>
                            <div class="relative">
                                <span class="absolute left-3 top-3 text-gray-400"><i class="fas fa-venus-mars"></i></span>
                                <select name="jenis_kelamin" required
                                    class="w-full pl-10 pr-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 outline-none transition-colors appearance-none">
                                    <option value="" disabled selected>Pilih Jenis Kelamin</option>
                                    <option value="Pria">Pria</option>
                                    <option value="Wanita">Wanita</option>
                                </select>
                            </div>
                        </div>

                        <!-- Kategori -->
                        <div class="space-y-2">
                            <label class="block text-sm font-semibold text-gray-700">Kategori <span
                                    class="text-red-500">*</span></label>
                            <div class="relative">
                                <span class="absolute left-3 top-3 text-gray-400"><i class="fas fa-layer-group"></i></span>
                                <select name="kategori" required
                                    class="w-full pl-10 pr-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 outline-none transition-colors appearance-none">
                                    <option value="" disabled selected>Pilih Kategori</option>
                                    <option value="A">Kategori A</option>
                                    <option value="B">Kategori B</option>
                                    <option value="C">Kategori C</option>
                                    <option value="D">Kategori D</option>
                                </select>
                            </div>
                        </div>

                        <!-- Umur -->
                        <div class="space-y-2">
                            <label class="block text-sm font-semibold text-gray-700">Umur/Usia <span
                                    class="text-red-500">*</span></label>
                            <div class="relative">
                                <span class="absolute left-3 top-3 text-gray-400"><i class="fas fa-calendar-alt"></i></span>
                                <input type="number" name="umur" min="1" required placeholder="Contoh: 25"
                                    class="w-full pl-10 pr-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 outline-none transition-colors">
                            </div>
                        </div>

                        <!-- Kota -->
                        <div class="space-y-2">
                            <label class="block text-sm font-semibold text-gray-700">Kota Asal <span
                                    class="text-red-500">*</span></label>
                            <div class="relative">
                                <span class="absolute left-3 top-3 text-gray-400"><i
                                        class="fas fa-map-marker-alt"></i></span>
                                <input type="text" name="kota" required placeholder="Kota Domisili"
                                    class="w-full pl-10 pr-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 outline-none transition-colors">
                            </div>
                        </div>

                        <!-- Nama PIC -->
                        <div class="space-y-2">
                            <label class="block text-sm font-semibold text-gray-700">Nama PIC <span
                                    class="text-red-500">*</span></label>
                            <div class="relative">
                                <span class="absolute left-3 top-3 text-gray-400"><i class="fas fa-user-tie"></i></span>
                                <input type="text" name="nama_pic" required placeholder="Penanggung Jawab"
                                    class="w-full pl-10 pr-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 outline-none transition-colors">
                            </div>
                        </div>

                        <!-- No WA -->
                        <div class="space-y-2">
                            <label class="block text-sm font-semibold text-gray-700">No WhatsApp <span
                                    class="text-red-500">*</span></label>
                            <div class="relative">
                                <span class="absolute left-3 top-3 text-gray-400"><i class="fab fa-whatsapp"></i></span>
                                <input type="text" name="no_wa" required placeholder="08xxxxxxxxxx"
                                    class="w-full pl-10 pr-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 outline-none transition-colors">
                            </div>
                        </div>
                    </div>

                    <!-- Catatan Materi -->
                    <div class="space-y-2">
                        <label class="block text-sm font-semibold text-gray-700">Catatan untuk Materi Perform di
                            Panggung</label>
                        <textarea name="catatan_materi" rows="3"
                            placeholder="Tulis catatan atau kebutuhan materi pentas (opsional)"
                            class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 outline-none transition-colors"></textarea>
                    </div>

                    <div class="border-t border-gray-100 pt-6 mt-4">
                        <h3 class="text-lg font-bold text-gray-800 mb-4">Berkas Media</h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Foto Upload -->
                            <div class="space-y-2">
                                <label class="block text-sm font-semibold text-gray-700">Upload Foto</label>
                                <p class="text-xs text-gray-500 mb-2">Format: JPG, PNG. Maksimal: <span
                                        class="font-bold text-orange-500">1 MB</span></p>
                                <div class="relative border-2 border-dashed border-gray-300 rounded-xl p-4 text-center hover:bg-gray-50 hover:border-orange-400 transition-colors group cursor-pointer"
                                    onclick="document.getElementById('foto').click()">
                                    <i
                                        class="fas fa-cloud-upload-alt text-3xl text-gray-400 group-hover:text-orange-500 mb-2 transition-colors"></i>
                                    <span id="foto-label" class="block text-sm text-gray-600 font-medium">Klik untuk pilih
                                        foto</span>
                                    <input type="file" id="foto" name="foto" accept="image/*" class="hidden"
                                        onchange="updateFileLabel('foto', 'foto-label')">
                                </div>
                            </div>

                            <!-- Video/Audio Upload -->
                            <div class="space-y-2">
                                <label class="block text-sm font-semibold text-gray-700">Upload Video / Audio</label>
                                <p class="text-xs text-gray-500 mb-2">Format: MP4, MOV, <span class="font-bold">MP3</span>.
                                    <span class="font-bold text-orange-500">Tanpa Batas Ukuran</span></p>
                                <div class="relative border-2 border-dashed border-gray-300 rounded-xl p-4 text-center hover:bg-gray-50 hover:border-orange-400 transition-colors group cursor-pointer"
                                    onclick="document.getElementById('video').click()">
                                    <i
                                        class="fas fa-file-video text-3xl text-gray-400 group-hover:text-orange-500 mb-2 transition-colors"></i>
                                    <span id="video-label" class="block text-sm text-gray-600 font-medium">Klik untuk pilih
                                        video/audio</span>
                                    <input type="file" id="video" name="video" accept="video/*,audio/mpeg,audio/mp3,audio/*"
                                        class="hidden" onchange="updateFileLabel('video', 'video-label')">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="pt-6">
                        <button type="submit"
                            class="w-full bg-gradient-to-r from-orange-500 to-orange-600 hover:from-orange-600 hover:to-orange-700 text-white font-bold py-3.5 px-6 rounded-xl shadow-lg transform transition hover:-translate-y-1 hover:shadow-orange-500/30 flex justify-center items-center gap-2">
                            <i class="fas fa-paper-plane"></i>
                            Kirim Pendaftaran
                        </button>
                        <p class="text-xs text-center text-gray-400 mt-4"><i class="fas fa-lock mr-1"></i> Data Anda
                            tersimpan dengan aman.</p>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function updateFileLabel(inputId, labelId) {
            const input = document.getElementById(inputId);
            const label = document.getElementById(labelId);
            if (input.files && input.files.length > 0) {
                let fileName = input.files[0].name;
                // Truncate name if too long
                if (fileName.length > 25) {
                    fileName = fileName.substring(0, 20) + '...';
                }
                label.textContent = fileName;
                label.classList.add('text-orange-600', 'font-bold');
            } else {
                label.textContent = inputId === 'foto' ? 'Klik untuk pilih foto' : 'Klik untuk pilih video';
                label.classList.remove('text-orange-600', 'font-bold');
            }
        }
    </script>
</body>

</html>