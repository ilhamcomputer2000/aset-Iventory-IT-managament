<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Data</title>
    <!-- Tailwind CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        /* Animasi loading */
        #loading {
            display: none;
            position: fixed;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            z-index: 9999;
            border: 16px solid #f3f3f3;
            border-radius: 50%;
            border-top: 16px solid #3498db;
            width: 120px;
            height: 120px;
            animation: spin 10s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Loading Spinner -->
    <div id="loading"></div>

    <div class="container mx-auto p-4 bg-white shadow-lg rounded-lg mt-10">
    <?php
        // Include file koneksi
        include "../koneksi.php";

        // Fungsi untuk mencegah inputan karakter yang tidak sesuai
        function input($data) {
            $data = trim($data);
            $data = stripslashes($data);
            $data = htmlspecialchars($data);
            return $data;
        }

        // Cek apakah ada nilai yang dikirim menggunakan method GET dengan nama id_peserta
if (isset($_GET['id_peserta'])) {
    $id_peserta = input($_GET["id_peserta"]);

    // Ambil data dari database
    $sql = "SELECT * FROM peserta WHERE id_peserta = $id_peserta";
    $hasil = mysqli_query($kon, $sql);
    $data = mysqli_fetch_assoc($hasil);

    

    // Periksa apakah ID peserta ditemukan di database
    if (!$data) {
        echo "<script>
        Swal.fire({
            title: 'Error!',
            text: 'ID Peserta tidak ditemukan.',
            icon: 'error',
            confirmButtonText: 'OK'
        }).then(function() {
            window.location.href = 'index.php';
        });
        </script>";
        exit;
    }
} else {
    echo "<script>
    Swal.fire({
        title: 'Error!',
        text: 'ID Peserta tidak ditemukan.',
        icon: 'error',
        confirmButtonText: 'OK'
    }).then(function() {
        window.location.href = 'index.php';
    });
    </script>";
    exit;
}

        ?>


        <h2 class="text-2xl font-bold mb-4">View Data</h2>

        <form method="post" class="space-y-4">
            <input type="hidden" name="id_peserta" value="<?php echo htmlspecialchars($data['id_peserta']); ?>" />

            <div class="flex flex-wrap gap-4 mb-4">
                <div class="form-group flex-1 min-w-[180px]">
                    <label for="Waktu" class="block text-gray-700 font-medium mb-1">Waktu:</label>
                    <input type="text" name="Waktu" class="form-input w-full px-3 py-2 border rounded-lg bg-gray-100" value="<?php echo htmlspecialchars($data['Waktu']); ?>" readonly />
                </div>
                <div class="form-group flex-1 min-w-[180px]">
                    <label for="Nama_Barang" class="block text-gray-700 font-medium mb-1">Nama Barang:</label>
                    <input type="text" name="Nama_Barang" class="form-input w-full px-3 py-2 border rounded-lg bg-gray-100" value="<?php echo htmlspecialchars($data['Nama_Barang']); ?>" readonly />
                </div>
                <div class="form-group flex-1 min-w-[180px]">
                    <label for="Merek" class="block text-gray-700 font-medium mb-1">Merek:</label>
                    <input type="text" name="Merek" class="form-input w-full px-3 py-2 border rounded-lg bg-gray-100" value="<?php echo htmlspecialchars($data['Merek']); ?>" readonly />
                </div>
                <div class="form-group flex-1 min-w-[180px]">
                    <label for="Type" class="block text-gray-700 font-medium mb-1">Type:</label>
                    <input type="text" name="Type" class="form-input w-full px-3 py-2 border rounded-lg bg-gray-100" value="<?php echo htmlspecialchars($data['Type']); ?>" readonly />
                </div>
                <div class="form-group flex-1 min-w-[180px]">
                    <label for="Serial_Number" class="block text-gray-700 font-medium mb-1">Serial Number:</label>
                    <input type="text" name="Serial_Number" class="form-input w-full px-3 py-2 border rounded-lg bg-gray-100" value="<?php echo htmlspecialchars($data['Serial_Number']); ?>" readonly />
                </div>
            </div>

            <div class="form-group">
                <label for="Spesifikasi" class="block text-gray-700 font-medium mb-1">Spesifikasi:</label>
                <textarea name="Spesifikasi" class="form-textarea w-full px-3 py-2 border rounded-lg bg-gray-100" rows="5" readonly><?php echo htmlspecialchars($data['Spesifikasi']); ?></textarea>
            </div>

            <div class="flex flex-wrap gap-4 mb-4">
                <div class="form-group flex-1 min-w-[180px]">
                    <label for="Kelengkapan_Barang" class="block text-gray-700 font-medium mb-1">Kelengkapan Barang:</label>
                    <textarea name="Kelengkapan_Barang" class="form-textarea w-full px-3 py-2 border rounded-lg bg-gray-100" rows="5" readonly><?php echo htmlspecialchars($data['Kelengkapan_Barang']); ?></textarea>
                </div>
                <div class="form-group flex-1 min-w-[180px]">
                    <label for="Kondisi_Barang" class="block text-gray-700 font-medium mb-1">Kondisi Barang:</label>
                    <textarea name="Kondisi_Barang" class="form-textarea w-full px-3 py-2 border rounded-lg bg-gray-100" rows="5" readonly><?php echo htmlspecialchars($data['Kondisi_Barang']); ?></textarea>
                </div>
            </div>

            <div class="flex flex-wrap gap-4 mb-4">
                <div class="form-group flex-1 min-w-[180px]">
                    <label for="Riwayat_Barang" class="block text-gray-700 font-medium mb-1">Riwayat Barang:</label>
                    <textarea name="Riwayat_Barang" class="form-textarea w-full px-3 py-2 border rounded-lg bg-gray-100" rows="5" readonly><?php echo htmlspecialchars($data['Riwayat_Barang']); ?></textarea>
                </div>
                <div class="form-group flex-1 min-w-[180px]">
                    <label for="User_Perangkat" class="block text-gray-700 font-medium mb-1">Nama User yang menggunakan perangkat:</label>
                    <textarea name="User_Perangkat" class="form-textarea w-full px-3 py-2 border rounded-lg bg-gray-100" rows="5" readonly><?php echo htmlspecialchars($data['User_Perangkat']); ?></textarea>
                </div>
            </div>

            <div class="flex flex-wrap gap-4 mb-4">
                <div class="form-group flex-1 min-w-[180px]">
                    <label for="Status_Barang" class="block text-gray-700 font-medium mb-1">Status Barang:</label>
                    <select name="Status_Barang" class="form-select w-full px-3 py-2 border rounded-lg bg-gray-100" readonly>
                        <option value="READY" <?php if ($data['Status_Barang'] == 'READY') echo 'selected'; ?>>READY</option>
                        <option value="KOSONG" <?php if ($data['Status_Barang'] == 'KOSONG') echo 'selected'; ?>>KOSONG</option>
                        <option value="REPAIR" <?php if ($data['Status_Barang'] == 'REPAIR') echo 'selected'; ?>>REPAIR</option>
                        <option value="TEMPORARY" <?php if ($data['Status_Barang'] == 'TEMPORARY') echo 'selected'; ?>>TEMPORARY</option>
                        <option value="RUSAK" <?php if ($data['Status_Barang'] == 'RUSAK') echo 'selected'; ?>>RUSAK</option>
                    </select>
                </div>
                <div class="form-group flex-1 min-w-[180px]">
                    <label for="Jenis_Barang" class="block text-gray-700 font-medium mb-1">Jenis Barang:</label>
                    <select name="Jenis_Barang" class="form-select w-full px-3 py-2 border rounded-lg bg-gray-100" readonly>
                        <option value="INVENTARIS" <?php if ($data['Jenis_Barang'] == 'INVENTARIS') echo 'selected'; ?>>INVENTARIS</option>
                        <option value="LOP" <?php if ($data['Jenis_Barang'] == 'LOP') echo 'selected'; ?>>LOP</option>    
                    </select>
                </div>
            </div>
                        <div class="form-group">
                <label for="photo_barang" class="block text-gray-700 font-medium mb-1">Photo Barang:</label>
                <?php
                // Cek apakah ada gambar yang diupload
                if (!empty($data['Photo_Barang']) && file_exists($data['Photo_Barang'])) {
                    echo "<div class='relative'>
                            <img src='" . htmlspecialchars($data['Photo_Barang']) . "' alt='Photo Barang' class='w-48 h-48 object-cover cursor-pointer transition-transform duration-300 transform hover:scale-105' onclick=\"openModal('" . htmlspecialchars($data['Photo_Barang']) . "')\">";
                    echo "</div>";
                } else {
                    echo "<p>Photo Barang tidak tersedia.</p>";
                }
                ?>
            </div>
            
                    <!-- Modal untuk menampilkan gambar -->
            <div id="imageModal" class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center opacity-0 scale-0 transition-all duration-300 hidden" onclick="closeModal()">
                <span class="absolute top-4 right-4 text-white cursor-pointer" onclick="closeModal()">&times;</span>
                <img id="modalImage" src="" alt="Gambar Besar" class="max-w-full max-h-full transition-all duration-300 transform scale-75 opacity-0">
            </div>
            
            <div class="flex space-x-2">
                <a href="index.php" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600">Kembali</a>
            </div>
        </form>
    </div>

    <script>
        function showLoading() {
            document.getElementById('loading').style.display = 'block';
        }

        function openModal(imageSrc) {
        const modalImage = document.getElementById('modalImage');
        modalImage.src = imageSrc;
        const modal = document.getElementById('imageModal');
        
        modal.classList.remove('hidden');
        setTimeout(() => {
            modal.classList.remove('opacity-0', 'scale-0'); // Remove initial classes
            modal.classList.add('opacity-100', 'scale-100'); // Show modal with scaling and fading effect
            modalImage.classList.remove('opacity-0', 'scale-75'); // Prepare image to show
            modalImage.classList.add('opacity-100', 'scale-100'); // Show image with fading effect
        }, 10); // Delay for transition
        document.body.style.overflow = 'hidden'; // Disable scrolling
    }

    function closeModal() {
        const modal = document.getElementById('imageModal');
        const modalImage = document.getElementById('modalImage');
        
        modalImage.classList.remove('opacity-100', 'scale-100'); // Remove visible classes
        modalImage.classList.add('opacity-0', 'scale-75'); // Hide image with fading and scaling effect
        modal.classList.remove('opacity-100', 'scale-100'); // Remove visible classes
        modal.classList.add('opacity-0', 'scale-0'); // Hide modal with scaling and fading effect
        
        setTimeout(() => {
            modal.classList.add('hidden'); // Add hidden class after transition
        }, 300); // Match this with transition duration
        
        document.body.style.overflow = 'auto'; // Enable scrolling
    }
    </script>
</body>
</html>
