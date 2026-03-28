<?php
session_start();
include "koneksi.php";

require_once __DIR__ . '/app_url.php';

// Koneksi ke database
$conn = new mysqli("localhost", "root", "", "crud");
// $conn = new mysqli("localhost:3306", "cktnosa2_admin", "uGXj8#eiI=P%", "cktnosa2_crud");

// Cek koneksi
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}


// Periksa apakah form login telah dikirim
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    // Query untuk memeriksa pengguna di database
    $sql = "SELECT id, username, password, role, Nama_Lengkap, Jabatan_Level FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $dbPassword = $user['password'];

        // ✅ Cek password hashed atau plain
        $loginValid = false;
        if (password_verify($password, $dbPassword)) {
            $loginValid = true; // hashed
        } elseif ($password === $dbPassword) {
            $loginValid = true; // plain text
        }

        if ($loginValid) {
            // Simpan informasi pengguna ke dalam session
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role']     = $user['role'];
            $_SESSION['Nama_Lengkap'] = !empty($user['Nama_Lengkap']) ? $user['Nama_Lengkap'] : $user['username'];
            $_SESSION['Jabatan_Level'] = isset($user['Jabatan_Level']) ? (string)$user['Jabatan_Level'] : '';

            // --- Log Login Activity ---
            $__logActivityPath = __DIR__ . '/admin/log_activity.php';
            if (is_file($__logActivityPath) && !function_exists('logUserActivity')) {
                require_once $__logActivityPath;
            }
            if (function_exists('logUserActivity')) {
                logUserActivity(
                    $conn,
                    (int)$user['id'],
                    $user['username'],
                    $user['role'],
                    'Login'
                );
            }
            // --- End Log ---

            // Tentukan redirect berdasarkan role (clean URLs via .htaccess)
            $redirect = 'dashboard_admin'; // default
            if ($user['role'] === 'super_admin') {
                $redirect = 'dashboard_admin';
            } elseif ($user['role'] === 'user') {
                $redirect = 'dashboard_user';
            }

            // Return success response for AJAX
            echo json_encode([
                'success' => true,
                'message' => 'Login berhasil!',
                'role'    => $user['role'],
                'Nama_Lengkap' => $_SESSION['Nama_Lengkap'],  // FIX: Kembalikan nama lengkap ke JS jika perlu
                'Jabatan_Level' => $_SESSION['Jabatan_Level'],
                'redirect_url' => app_abs_path($redirect)
            ]);
            exit();
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Password salah!'
            ]);
            exit();
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Username tidak ditemukan!'
        ]);
        exit();
    }
}

// Opsional: hapus pesan error/success lama
$login_error = $_SESSION['login_error'] ?? false;
if ($login_error) {
    unset($_SESSION['login_error']);
}
$login_success = $_GET['login_success'] ?? false;



// Test: echo "LOGIN DEBUG: Role = " . $_SESSION['role']; die();  // Hapus setelah test 
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IT Asset Management - Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        * {
            font-family: 'Inter', sans-serif;
        }



        /* Pulse animation for status and logo */
        .custom-pulse {
            animation: customPulse 2s ease-in-out infinite;
        }

        @keyframes customPulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.8; transform: scale(1.02); }
        }

        /* Loading spinner */
        .loading-spinner {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        /* Button hover effects */
        .btn-hover {
            transition: all 0.3s ease;
        }

        .btn-hover:hover {
            transform: scale(1.02);
        }

        .btn-hover:active {
            transform: scale(0.98);
        }

        /* Input focus effects */
        .input-focus {
            transition: all 0.3s ease;
        }

        .input-focus:focus {
            transform: scale(1.01);
        }



        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
        }

        ::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.1);
        }

        ::-webkit-scrollbar-thumb {
            background: rgba(59, 130, 246, 0.3);
            border-radius: 3px;
        }

        /* Responsive adjustments */
        @media (max-height: 700px) {
            body {
                padding: 0.5rem !important;
            }
        }

        @media (max-height: 600px) {
            body {
                padding: 0.25rem !important;
            }
        }

        /* Ensure no scroll issues */
        html, body {
            height: 100%;
            overflow: hidden;
        }

        /* Custom scrollbar for the main content area */
        .max-h-\[95vh\]::-webkit-scrollbar {
            width: 4px;
        }

        .max-h-\[95vh\]::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.1);
            border-radius: 2px;
        }

        .max-h-\[95vh\]::-webkit-scrollbar-thumb {
            background: rgba(249, 115, 22, 0.3);
            border-radius: 2px;
        }

        .max-h-\[95vh\]::-webkit-scrollbar-thumb:hover {
            background: rgba(249, 115, 22, 0.5);
        }
    </style>
</head>
<body class="h-screen w-full bg-white flex items-center justify-center p-2 sm:p-4 relative overflow-hidden">


    <!-- Main Content -->
    <div class="relative z-10 w-full max-w-md max-h-[95vh] overflow-y-auto">
        <div class="bg-white border border-gray-200 shadow-lg rounded-2xl p-4 sm:p-6 md:p-8 m-2">
            <!-- Header -->
            <div class="text-center mb-4 sm:mb-6">
                <div class="relative mx-auto w-12 h-12 sm:w-16 sm:h-16 mb-3 sm:mb-4">
                    <div class="w-12 h-12 sm:w-16 sm:h-16 bg-gradient-to-br from-orange-500 to-blue-600 rounded-2xl flex items-center justify-center shadow-lg custom-pulse">
                        <i class="fas fa-microchip text-white text-lg sm:text-2xl"></i>
                    </div>
                    <div class="absolute -top-1 -right-1 w-5 h-5 sm:w-6 sm:h-6 bg-green-500 rounded-full flex items-center justify-center">
                        <i class="fas fa-check text-white text-xs"></i>
                    </div>
                </div>
                <h1 class="text-xl sm:text-2xl font-semibold text-gray-800 mb-1 sm:mb-2">
                    IT Asset Management
                </h1>
                <p class="text-gray-600 text-xs sm:text-sm">
                    Sistem manajemen dan tracking informasi
                </p>
            </div>

            <!-- Login Form -->
            <form id="loginForm" class="space-y-4 sm:space-y-6">
                <!-- Username Field -->
                <div class="space-y-1 sm:space-y-2">
                    <label for="username" class="flex items-center gap-2 text-sm font-medium text-gray-700">
                        <i class="fas fa-user w-4 h-4 text-orange-600"></i>
                        <span>Username</span>
                    </label>
                    <div class="relative">
                        <div class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400">
                            <i class="fas fa-user w-4 h-4" id="userIcon"></i>
                        </div>
                        <input 
                            type="text" 
                            id="username" 
                            name="username" 
                            class="input-focus w-full pl-10 h-10 sm:h-12 border border-gray-300 rounded-lg focus:border-orange-500 focus:ring-2 focus:ring-orange-200 focus:outline-none transition-all duration-300 hover:border-orange-300 bg-white text-gray-900"
                            placeholder="admin, user, atau staff"
                            required
                        >
                    </div>
                    <p class="text-orange-600 text-xs flex items-center gap-1" id="usernameHint">
                        <span class="w-1 h-1 bg-orange-600 rounded-full"></span>
                        Username minimal 3 karakter
                    </p>
                </div>

                <!-- Password Field -->
                <div class="space-y-1 sm:space-y-2">
                    <label for="password" class="flex items-center gap-2 text-sm font-medium text-gray-700">
                        <i class="fas fa-lock w-4 h-4 text-blue-600"></i>
                        <span>Password</span>
                    </label>
                    <div class="relative">
                        <div class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400">
                            <i class="fas fa-lock w-4 h-4" id="lockIcon"></i>
                        </div>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            class="input-focus w-full pl-10 pr-12 h-10 sm:h-12 border border-gray-300 rounded-lg focus:border-blue-500 focus:ring-2 focus:ring-blue-200 focus:outline-none transition-all duration-300 hover:border-blue-300 bg-white text-gray-900"
                            placeholder="******"
                            required
                        >
                        <button 
                            type="button" 
                            id="togglePassword" 
                            class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors"
                        >
                            <i class="fas fa-eye w-4 h-4" id="eyeIcon"></i>
                        </button>
                    </div>
                </div>

                <!-- Submit Button -->
                <button 
                    type="submit" 
                    id="loginBtn"
                    class="btn-hover w-full h-10 sm:h-12 bg-gradient-to-r from-orange-500 to-blue-600 hover:from-orange-600 hover:to-blue-700 text-white font-medium rounded-lg shadow-lg transition-all duration-300 flex items-center justify-center gap-2"
                >
                    <i class="fas fa-shield-alt" id="shieldIcon"></i>
                    <span id="loginText">Akses Sistem</span>
                    <i class="fas fa-arrow-right" id="loginArrow"></i>
                    <i class="fas fa-spinner loading-spinner hidden" id="loginSpinner"></i>
                </button>
            </form>

            <!-- System Status -->
            <div class="mt-4 sm:mt-6 pt-4 sm:pt-6 border-t border-gray-200">
                <div class="flex items-center justify-between text-sm">
                    <div class="flex items-center gap-2">
                        <div class="w-2 h-2 bg-green-500 rounded-full custom-pulse"></div>
                        <span class="text-gray-600">System Status</span>
                    </div>
                    <span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full border border-green-200">
                        Operasional
                    </span>
                </div>
            </div>

            <!-- Help Links -->
            <div class="mt-4 sm:mt-6 space-y-2 sm:space-y-3 text-center">
                <p class="text-xs sm:text-sm text-gray-600">
                    Butuh bantuan? 
                    <a href="#" class="text-orange-600 hover:text-orange-700 hover:underline font-medium">
                        Hubungi IT Support
                    </a>
                </p>
                <p class="text-xs sm:text-sm text-gray-600">
                    Lupa password? 
                    <a href="#" class="text-blue-600 hover:text-blue-700 hover:underline font-medium">
                        Reset melalui admin
                    </a>
                </p>
            </div>

            <!-- Demo Credentials -->
            <div class="mt-4 sm:mt-6 p-3 sm:p-4 bg-gradient-to-r from-orange-50 to-blue-50 rounded-lg border border-orange-200">
                <h4 class="text-sm font-medium text-gray-700 mb-2">Demo Credentials:</h4>
                <div class="space-y-1 text-xs text-gray-600">
                    <div>• Admin: admin / admin123</div>
                    <div>• User: user / user123</div>
                    <div>• Staff: staff / staff123</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-2xl p-8 shadow-2xl text-center max-w-sm mx-4 border border-gray-200">
            <div class="w-16 h-16 bg-gradient-to-br from-orange-500 to-blue-600 rounded-2xl flex items-center justify-center mx-auto mb-6 relative custom-pulse">
                <i class="fas fa-shield-alt text-white text-2xl"></i>
                <div class="absolute inset-0 rounded-2xl border-2 border-orange-300 custom-pulse"></div>
            </div>
            
            <h3 class="text-xl font-semibold text-gray-800 mb-2">
                Memverifikasi Akses
            </h3>
            <p class="text-gray-600 mb-4">Mengamankan koneksi ke sistem...</p>
            
            <div class="flex items-center justify-center gap-2">
                <div class="w-2 h-2 bg-orange-500 rounded-full custom-pulse"></div>
                <div class="w-2 h-2 bg-blue-600 rounded-full custom-pulse" style="animation-delay: 0.2s;"></div>
                <div class="w-2 h-2 bg-orange-500 rounded-full custom-pulse" style="animation-delay: 0.4s;"></div>
            </div>
        </div>
    </div>

    <script>
        // Initialize form interactions
        document.addEventListener('DOMContentLoaded', function() {
            initializeFormInteractions();
            
            // Show initial success/error messages
            const loginError = <?php echo json_encode($login_error); ?>;
            const loginSuccess = <?php echo json_encode($login_success); ?>;
            
            if (loginError) {
                Swal.fire({
                    icon: 'error',
                    title: 'Login Gagal',
                    text: 'Username atau password salah. Silahkan dicoba kembali.',
                    confirmButtonText: 'Ok',
                    customClass: {
                        popup: 'animate__animated animate__fadeInDown'
                    }
                });
            }
            
            if (loginSuccess) {
                Swal.fire({
                    icon: 'success',
                    title: 'Login Berhasil',
                    text: 'Selamat datang kembali!',
                    confirmButtonText: 'Ok'
                });
            }
        });

        function initializeFormInteractions() {
            const usernameInput = document.getElementById('username');
            const passwordInput = document.getElementById('password');
            const userIcon = document.getElementById('userIcon');
            const lockIcon = document.getElementById('lockIcon');
            const togglePassword = document.getElementById('togglePassword');
            const eyeIcon = document.getElementById('eyeIcon');
            const loginForm = document.getElementById('loginForm');
            const usernameHint = document.getElementById('usernameHint');

            // Username input focus effects
            usernameInput.addEventListener('focus', () => {
                userIcon.className = 'fas fa-user w-4 h-4 text-orange-600';
            });

            usernameInput.addEventListener('blur', () => {
                userIcon.className = 'fas fa-user w-4 h-4 text-gray-400';
            });

            // Hide username hint when user starts typing
            usernameInput.addEventListener('input', () => {
                if (usernameInput.value.length > 0) {
                    usernameHint.style.display = 'none';
                } else {
                    usernameHint.style.display = 'flex';
                }
            });

            // Password input focus effects  
            passwordInput.addEventListener('focus', () => {
                lockIcon.className = 'fas fa-lock w-4 h-4 text-blue-600';
            });

            passwordInput.addEventListener('blur', () => {
                lockIcon.className = 'fas fa-lock w-4 h-4 text-gray-400';
            });

            // Toggle password visibility
            togglePassword.addEventListener('click', () => {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                
                if (type === 'password') {
                    eyeIcon.className = 'fas fa-eye w-4 h-4';
                } else {
                    eyeIcon.className = 'fas fa-eye-slash w-4 h-4';
                }
            });

            // Form submission
            loginForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                
                const username = usernameInput.value.trim();
                const password = passwordInput.value;
                const loginBtn = document.getElementById('loginBtn');
                const loginText = document.getElementById('loginText');
                const loginArrow = document.getElementById('loginArrow');
                const loginSpinner = document.getElementById('loginSpinner');
                const shieldIcon = document.getElementById('shieldIcon');
                const loadingOverlay = document.getElementById('loadingOverlay');

                // Validate form
                if (!validateForm(username, password)) {
                    return;
                }

                // Show loading state
                loginBtn.disabled = true;
                loginText.textContent = 'Memverifikasi kredensial...';
                loginArrow.classList.add('hidden');
                shieldIcon.classList.add('hidden');
                loginSpinner.classList.remove('hidden');
                loadingOverlay.classList.remove('hidden');

                try {
                    const formData = new FormData();
                    formData.append('username', username);
                    formData.append('password', password);

                    const response = await fetch('login.php', {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();

                    // Simulate processing delay for better UX
                    await new Promise(resolve => setTimeout(resolve, 2000));

                    if (result.success) {
                        loadingOverlay.classList.add('hidden');
                        
                        Swal.fire({
                            icon: 'success',
                            title: 'Login Berhasil!',
                            text: result.message,
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            window.location.href = result.redirect_url;
                        });

                    } else {
                        throw new Error(result.message);
                    }

                } catch (error) {
                    loadingOverlay.classList.add('hidden');
                    
                    Swal.fire({
                        icon: 'error',
                        title: 'Login Gagal!',
                        text: error.message || 'Terjadi kesalahan. Silakan coba lagi.',
                        confirmButtonText: 'Ok'
                    });
                } finally {
                    // Reset button state
                    loginBtn.disabled = false;
                    loginText.textContent = 'Akses Sistem';
                    loginArrow.classList.remove('hidden');
                    shieldIcon.classList.remove('hidden');
                    loginSpinner.classList.add('hidden');
                }
            });
        }

        function validateForm(username, password) {
            let isValid = true;
            
            // Validate username
            if (!username) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Username Diperlukan',
                    text: 'Mohon masukkan username Anda',
                    confirmButtonText: 'Ok'
                });
                isValid = false;
            } else if (username.length < 3) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Username Terlalu Pendek',
                    text: 'Username minimal 3 karakter',
                    confirmButtonText: 'Ok'
                });
                isValid = false;
            }
            
            // Validate password
            if (!password && isValid) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Password Diperlukan',
                    text: 'Mohon masukkan password Anda',
                    confirmButtonText: 'Ok'
                });
                isValid = false;
            }
            
            return isValid;
        }
    </script>
</body>
</html>