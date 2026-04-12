<?php
session_start();
require_once __DIR__ . '/../koneksi.php';

// Auth check
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../app_url.php';

$userId = (int)$_SESSION['user_id'];
$Nama_Lengkap = (string)($_SESSION['Nama_Lengkap'] ?? 'User');
$Jabatan_Level = (string)($_SESSION['Jabatan_Level'] ?? '');
$activePage = 'profile';

// ========== AJAX HANDLERS ==========

// --- Upload Photo ---
if (isset($_POST['action']) && $_POST['action'] === 'upload_photo') {
    header('Content-Type: application/json');
    
    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Tidak ada file yang diupload']);
        exit;
    }
    
    $file = $_FILES['photo'];
    $allowed = ['image/jpeg', 'image/png', 'image/webp'];
    $maxSize = 10 * 1024 * 1024; // 10MB
    
    if (!in_array($file['type'], $allowed)) {
        echo json_encode(['success' => false, 'message' => 'Format file harus JPG, PNG, atau WEBP']);
        exit;
    }
    if ($file['size'] > $maxSize) {
        echo json_encode(['success' => false, 'message' => 'Ukuran file maksimal 10MB']);
        exit;
    }
    
    // Get User info for formatting filename
    $stmt = $kon->prepare("SELECT username, Nama_Lengkap, Jabatan_Level, Divisi, Region FROM users WHERE id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $uData = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$uData) {
        echo json_encode(['success' => false, 'message' => 'Data user tidak ditemukan']);
        exit;
    }

    // Format: Id karyawan_Nama Lengkap_Jabatan_divisi_region_waktu
    $u_id      = preg_replace('/[^a-zA-Z0-9_-]/', '', str_replace(' ', '', $uData['username']));
    $u_nama    = preg_replace('/[^a-zA-Z0-9_-]/', '', str_replace(' ', '', $uData['Nama_Lengkap']));
    $u_jabatan = preg_replace('/[^a-zA-Z0-9_-]/', '', str_replace(' ', '', $uData['Jabatan_Level']));
    $u_divisi  = preg_replace('/[^a-zA-Z0-9_-]/', '', str_replace(' ', '', $uData['Divisi']));
    $u_region  = preg_replace('/[^a-zA-Z0-9_-]/', '', str_replace(' ', '', $uData['Region']));
    $u_time    = date('dmYHis');
    
    $filename = "{$u_id}_{$u_nama}_{$u_jabatan}_{$u_divisi}_{$u_region}_{$u_time}.jpg";
    $uploadDir = __DIR__ . '/../uploads/profile_pictures/';
    $uploadPath = $uploadDir . $filename;
    
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Delete old photo
    $stmt = $kon->prepare("SELECT profile_picture FROM users WHERE id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $oldPic = null;
    $stmt->bind_result($oldPic);
    $stmt->fetch();
    $stmt->close();
    
    if ($oldPic && file_exists($uploadDir . basename($oldPic))) {
        @unlink($uploadDir . basename($oldPic));
    }
    
    // IMAGE COMPRESSION (GD)
    $source = $file['tmp_name'];
    $info = getimagesize($source);
    if (!$info) {
        echo json_encode(['success' => false, 'message' => 'File gambar tidak valid']);
        exit;
    }
    list($width, $height) = $info;
    $mime = $info['mime'];

    switch ($mime) {
        case 'image/jpeg': $img = @imagecreatefromjpeg($source); break;
        case 'image/png': $img = @imagecreatefrompng($source); break;
        case 'image/webp': $img = @imagecreatefromwebp($source); break;
        default: echo json_encode(['success' => false, 'message' => 'Format file tidak didukung']); exit;
    }

    if (!$img) {
        echo json_encode(['success' => false, 'message' => 'Gagal membaca gambar. Pastikan file valid.']);
        exit;
    }

    // Target size for very low file size < 10KB
    $targetWidth = 150;
    $targetHeight = 150;
    
    // Crop & Scale to square
    $minDim = min($width, $height);
    $cropX = ($width - $minDim) / 2;
    $cropY = ($height - $minDim) / 2;

    $newImg = imagecreatetruecolor($targetWidth, $targetHeight);
    
    // Provide White background before JPEG save
    $bgImg = imagecreatetruecolor($targetWidth, $targetHeight);
    $white = imagecolorallocate($bgImg, 255, 255, 255);
    imagefilledrectangle($bgImg, 0, 0, $targetWidth, $targetHeight, $white);

    // Support transparency blending if original has transparency
    imagecopyresampled($newImg, $img, 0, 0, $cropX, $cropY, $targetWidth, $targetHeight, $minDim, $minDim);
    
    // Merge onto white bg
    imagecopy($bgImg, $newImg, 0, 0, 0, 0, $targetWidth, $targetHeight);

    // Save heavily compressed JPEG
    if (imagejpeg($bgImg, $uploadPath, 45)) { // Quality 45
        imagedestroy($img);
        imagedestroy($newImg);
        imagedestroy($bgImg);
        
        $relPath = 'uploads/profile_pictures/' . $filename;
        $stmt = $kon->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
        $stmt->bind_param('si', $relPath, $userId);
        $stmt->execute();
        $stmt->close();
        
        echo json_encode(['success' => true, 'path' => app_abs_path($relPath), 'message' => 'Foto berhasil diupload']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan file yang sudah dikompres']);
    }
    exit;
}

// --- Delete Photo ---
if (isset($_POST['action']) && $_POST['action'] === 'delete_photo') {
    header('Content-Type: application/json');
    
    $stmt = $kon->prepare("SELECT profile_picture FROM users WHERE id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $oldPic = null;
    $stmt->bind_result($oldPic);
    $stmt->fetch();
    $stmt->close();
    
    if ($oldPic) {
        $fullPath = __DIR__ . '/../' . $oldPic;
        if (file_exists($fullPath)) @unlink($fullPath);
        $stmt = $kon->prepare("UPDATE users SET profile_picture = NULL WHERE id = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();
    }
    
    echo json_encode(['success' => true, 'message' => 'Foto berhasil dihapus']);
    exit;
}

// ========== LOAD USER DATA ==========
$stmt = $kon->prepare("SELECT id, username, Nama_Lengkap, Email, No_Telp_WA, Divisi, Jabatan_Level, Region, role, Status_Akun, profile_picture, created_at FROM users WHERE id = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    die('User not found');
}

$profilePic = $user['profile_picture'] ? app_abs_path($user['profile_picture']) : '';
$initial = strtoupper(substr($user['Nama_Lengkap'] ?? 'U', 0, 1));
$createdAt = date('d F Y, H:i', strtotime($user['created_at']));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile — User</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        * { font-family: 'Inter', sans-serif; box-sizing: border-box; }
        
        .profile-avatar {
            width: 120px; height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #fff;
            box-shadow: 0 8px 24px rgba(0,0,0,.12);
        }
        .profile-avatar-placeholder {
            width: 120px; height: 120px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 40px; font-weight: 800; color: white;
            background: linear-gradient(135deg, #f97316, #ea580c);
            border: 4px solid #fff;
            box-shadow: 0 8px 24px rgba(0,0,0,.12);
        }
        .upload-overlay {
            position: absolute; inset: 0; border-radius: 50%;
            background: rgba(0,0,0,.5); opacity: 0;
            display: flex; align-items: center; justify-content: center;
            transition: opacity .2s; cursor: pointer;
        }
        .avatar-wrapper:hover .upload-overlay { opacity: 1; }

        /* ---- Info Rows ---- */
        .info-row {
            display: flex;
            flex-direction: column;
            gap: 4px;
            padding: 12px 0;
            border-bottom: 1px solid #f3f4f6;
        }
        .info-row:last-child { border-bottom: none; }
        .info-label {
            font-size: 12px; font-weight: 500; color: #6b7280;
            flex-shrink: 0;
        }
        .info-value {
            font-size: 14px; font-weight: 500; color: #111827;
            word-break: break-word;
            overflow-wrap: anywhere;
        }
        @media (min-width: 640px) {
            .info-row {
                flex-direction: row;
                align-items: center;
                gap: 0;
            }
            .info-label {
                width: 140px;
                flex-shrink: 0;
                font-size: 13px;
            }
            .info-value {
                flex: 1;
                min-width: 0;
            }
        }
        
        .status-badge {
            padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600;
        }
        .status-aktif { background: #dcfce7; color: #166534; }
        .status-nonaktif { background: #fee2e2; color: #991b1b; }
        
        .role-badge {
            padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600;
            background: #eff6ff; color: #1d4ed8;
        }

        /* ---- Info Cards Grid ---- */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 16px;
        }
        @media (min-width: 1024px) {
            .info-grid {
                grid-template-columns: 1fr 1fr;
                gap: 24px;
            }
        }
        .info-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,.04);
            border: 1px solid #f3f4f6;
            padding: 20px;
        }
        @media (min-width: 640px) {
            .info-card {
                padding: 24px;
            }
        }
    </style>
</head>
<body class="bg-gray-50">
<?php require_once __DIR__ . '/sidebar_user_include.php'; ?>

<div id="main-content-wrapper" class="lg:ml-60 transition-all duration-300 ease-in-out">
<script>
    (function() {
        var wrapper = document.getElementById('main-content-wrapper');
        if (!wrapper) return;
        function applyState() {
            if (window.innerWidth >= 1024) {
                var collapsed = localStorage.getItem('sidebarCollapsed') === '1';
                wrapper.style.marginLeft = collapsed ? '0' : '';
            } else {
                wrapper.style.marginLeft = '0';
            }
        }
        applyState();
        window.addEventListener('sidebarToggled', function() { applyState(); });
        window.addEventListener('resize', function() { applyState(); });
    })();
</script>
<main class="mt-16 min-h-screen p-4 sm:p-6 pb-24">
<div class="max-w-4xl mx-auto">

    <!-- Header -->
    <div class="mb-6">
        <h1 class="text-xl font-bold text-gray-900 flex items-center gap-2">
            <i class="fas fa-user-circle text-orange-500"></i> Profile
        </h1>
        <p class="text-gray-500 text-sm mt-0.5">Informasi profil akun Anda</p>
    </div>

    <!-- Profile Card -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-6">
        <!-- Banner -->
        <div class="h-28 sm:h-32 bg-gradient-to-r from-orange-400 via-orange-500 to-amber-500 relative">
            <div class="absolute inset-0 opacity-20">
                <svg width="100%" height="100%"><defs><pattern id="dots" x="0" y="0" width="20" height="20" patternUnits="userSpaceOnUse"><circle cx="2" cy="2" r="1" fill="white"/></pattern></defs><rect fill="url(#dots)" width="100%" height="100%"/></svg>
            </div>
        </div>
        
        <!-- Avatar + Name -->
        <div class="px-4 sm:px-8 pb-6 relative z-10">
            <div class="flex flex-col sm:flex-row items-center sm:items-start gap-4 sm:gap-6">
                <!-- Avatar -->
                <div class="avatar-wrapper relative flex-shrink-0 w-[120px] h-[120px] -mt-12 sm:-mt-16">
                    <?php if ($profilePic): ?>
                        <img src="<?php echo htmlspecialchars($profilePic); ?>" alt="Profile" class="profile-avatar w-full h-full" id="profile-img">
                    <?php else: ?>
                        <div class="profile-avatar-placeholder w-full h-full" id="profile-img-placeholder"><?php echo $initial; ?></div>
                    <?php endif; ?>
                    <div class="upload-overlay" onclick="document.getElementById('photo-input').click()">
                        <i class="fas fa-camera text-white text-xl"></i>
                    </div>
                    <input type="file" id="photo-input" accept="image/jpeg,image/png,image/webp" style="display:none" onchange="uploadPhoto(this)">
                </div>
                
                <!-- Name & Role -->
                <div class="text-center sm:text-left flex-1 min-w-0 sm:mt-4">
                    <h2 class="text-xl sm:text-2xl font-bold text-gray-900 break-words leading-tight"><?php echo htmlspecialchars($user['Nama_Lengkap']); ?></h2>
                    <p class="text-sm text-gray-500 mt-1 break-words"><?php echo htmlspecialchars($user['Jabatan_Level']); ?> &mdash; <?php echo htmlspecialchars($user['Divisi']); ?></p>
                    <div class="flex items-center gap-2 mt-3 justify-center sm:justify-start flex-wrap">
                        <span class="role-badge"><i class="fas fa-user mr-1"></i><?php echo htmlspecialchars(ucfirst($user['role'])); ?></span>
                        <span class="status-badge <?php echo strtolower($user['Status_Akun']) === 'aktif' ? 'status-aktif' : 'status-nonaktif'; ?>">
                            <i class="fas fa-circle text-[6px] mr-1"></i><?php echo htmlspecialchars($user['Status_Akun']); ?>
                        </span>
                    </div>
                </div>
                
                <!-- Photo Actions -->
                <div class="flex justify-center flex-wrap gap-2 pt-2 sm:pt-0 sm:mt-5">
                    <button onclick="document.getElementById('photo-input').click()" class="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white text-xs font-semibold rounded-lg transition flex items-center gap-1.5 shadow-sm">
                        <i class="fas fa-camera"></i> Ganti Foto
                    </button>
                    <?php if ($profilePic): ?>
                    <button onclick="deletePhoto()" class="px-3 py-2 border border-gray-200 bg-white text-gray-500 hover:text-red-500 hover:border-red-200 text-xs font-semibold rounded-lg transition shadow-sm" id="btn-delete-photo">
                        <i class="fas fa-trash"></i>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Detail Information (READ-ONLY for user) -->
    <div class="info-grid">
        <!-- Personal Info -->
        <div class="info-card">
            <h3 class="text-sm font-bold text-gray-900 mb-4 flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-orange-100 flex items-center justify-center">
                    <i class="fas fa-user text-orange-500 text-xs"></i>
                </div>
                Informasi Pribadi
            </h3>
            
            <div class="info-row">
                <div class="info-label"><i class="fas fa-id-badge mr-2 text-gray-400"></i>Nama Lengkap</div>
                <div class="info-value"><?php echo htmlspecialchars($user['Nama_Lengkap']); ?></div>
            </div>
            
            <div class="info-row">
                <div class="info-label"><i class="fas fa-fingerprint mr-2 text-gray-400"></i>Username</div>
                <div class="info-value font-mono text-gray-600"><?php echo htmlspecialchars($user['username']); ?></div>
            </div>
            
            <div class="info-row">
                <div class="info-label"><i class="fas fa-envelope mr-2 text-gray-400"></i>Email</div>
                <div class="info-value"><?php echo htmlspecialchars($user['Email'] ?: '—'); ?></div>
            </div>
            
            <div class="info-row">
                <div class="info-label"><i class="fab fa-whatsapp mr-2 text-gray-400"></i>No. Telp / WA</div>
                <div class="info-value"><?php echo htmlspecialchars($user['No_Telp_WA'] ?: '—'); ?></div>
            </div>
        </div>
        
        <!-- Work Info -->
        <div class="info-card">
            <h3 class="text-sm font-bold text-gray-900 mb-4 flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center">
                    <i class="fas fa-briefcase text-blue-500 text-xs"></i>
                </div>
                Informasi Pekerjaan
            </h3>
            
            <div class="info-row">
                <div class="info-label"><i class="fas fa-building mr-2 text-gray-400"></i>Divisi</div>
                <div class="info-value"><?php echo htmlspecialchars($user['Divisi'] ?: '—'); ?></div>
            </div>
            
            <div class="info-row">
                <div class="info-label"><i class="fas fa-user-tie mr-2 text-gray-400"></i>Jabatan</div>
                <div class="info-value"><?php echo htmlspecialchars($user['Jabatan_Level'] ?: '—'); ?></div>
            </div>
            
            <div class="info-row">
                <div class="info-label"><i class="fas fa-map-marker-alt mr-2 text-gray-400"></i>Region</div>
                <div class="info-value"><?php echo htmlspecialchars($user['Region'] ?: '—'); ?></div>
            </div>
            
            <div class="info-row">
                <div class="info-label"><i class="fas fa-shield-alt mr-2 text-gray-400"></i>Role</div>
                <div class="info-value"><span class="role-badge"><?php echo htmlspecialchars(ucfirst($user['role'])); ?></span></div>
            </div>
            
            <div class="info-row">
                <div class="info-label"><i class="fas fa-toggle-on mr-2 text-gray-400"></i>Status</div>
                <div class="info-value">
                    <span class="status-badge <?php echo strtolower($user['Status_Akun']) === 'aktif' ? 'status-aktif' : 'status-nonaktif'; ?>">
                        <?php echo htmlspecialchars($user['Status_Akun']); ?>
                    </span>
                </div>
            </div>
            
            <div class="info-row">
                <div class="info-label"><i class="fas fa-calendar-alt mr-2 text-gray-400"></i>Terdaftar</div>
                <div class="info-value text-gray-600 text-sm"><?php echo $createdAt; ?></div>
            </div>
        </div>
    </div>
    
    <!-- Note -->
    <div class="mt-8 mb-4 text-center pb-8 border-t border-gray-100 pt-8">
        <p class="text-xs text-gray-400 max-w-sm mx-auto leading-relaxed"><i class="fas fa-info-circle mr-1"></i>Untuk mengubah informasi akun, silakan hubungi Admin IT.</p>
    </div>

</div>
</main>
</div>

<script>
function uploadPhoto(input) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];
    
    if (file.size > 10 * 1024 * 1024) {
        Swal.fire('Error', 'Ukuran file maksimal 10MB', 'error');
        return;
    }
    
    const fd = new FormData();
    fd.append('action', 'upload_photo');
    fd.append('photo', file);
    
    Swal.fire({ title: 'Mengupload...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    
    fetch(window.location.pathname, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                Swal.fire({ icon: 'success', title: 'Berhasil!', text: data.message, timer: 1500, showConfirmButton: false });
                const wrapper = document.querySelector('.avatar-wrapper');
                const placeholder = document.getElementById('profile-img-placeholder');
                const existing = document.getElementById('profile-img');
                
                if (placeholder) placeholder.remove();
                if (existing) {
                    existing.src = data.path;
                } else {
                    const img = document.createElement('img');
                    img.src = data.path;
                    img.alt = 'Profile';
                    img.className = 'profile-avatar';
                    img.id = 'profile-img';
                    wrapper.insertBefore(img, wrapper.querySelector('.upload-overlay'));
                }
                
                if (!document.getElementById('btn-delete-photo')) {
                    location.reload();
                }
            } else {
                Swal.fire('Error', data.message, 'error');
            }
        })
        .catch(() => Swal.fire('Error', 'Gagal mengupload foto', 'error'));
    
    input.value = '';
}

function deletePhoto() {
    Swal.fire({
        title: 'Hapus Foto?',
        text: 'Foto profil akan dihapus',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal'
    }).then(result => {
        if (result.isConfirmed) {
            const fd = new FormData();
            fd.append('action', 'delete_photo');
            fetch(window.location.pathname, { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({ icon: 'success', title: 'Dihapus!', timer: 1200, showConfirmButton: false });
                        setTimeout(() => location.reload(), 1200);
                    }
                });
        }
    });
}
</script>
</body>
</html>
