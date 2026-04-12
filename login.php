<?php
session_start();

require_once __DIR__ . '/app_url.php';
require_once __DIR__ . '/koneksi.php';
// $conn is set by koneksi.php (reads DB_HOST/DB_USER/DB_PASS/DB_NAME env vars on hosting,
// falls back to localhost/root on local dev). No duplicate connection needed.


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
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['Nama_Lengkap'] = !empty($user['Nama_Lengkap']) ? $user['Nama_Lengkap'] : $user['username'];
            $_SESSION['Jabatan_Level'] = isset($user['Jabatan_Level']) ? (string) $user['Jabatan_Level'] : '';

            // --- Log Login Activity ---
            $__logActivityPath = __DIR__ . '/admin/log_activity.php';
            if (is_file($__logActivityPath) && !function_exists('logUserActivity')) {
                require_once $__logActivityPath;
            }
            if (function_exists('logUserActivity')) {
                logUserActivity(
                    $conn,
                    (int) $user['id'],
                    $user['username'],
                    $user['role'],
                    'Login'
                );
            }
            // --- End Log ---

            // Tentukan redirect berdasarkan role
            $redirect = 'admin/dashboard_admin.php'; // default admin
            if ($user['role'] === 'super_admin') {
                $redirect = 'admin/dashboard_admin.php';
            } elseif ($user['role'] === 'user') {
                $redirect = 'user/dashboard_user.php';
            }

            // Return success response for AJAX
            echo json_encode([
                'success' => true,
                'message' => 'Login berhasil!',
                'role' => $user['role'],
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
    <meta name="description" content="Sistem manajemen aset IT terpadu – login untuk akses dashboard">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <style>
        /* FIX: Jangan override ::before/::after — Font Awesome pakai pseudo-element untuk icon */
        * {
            font-family: 'Inter', system-ui, sans-serif;
            box-sizing: border-box;
        }

        html,
        body {
            min-height: 100%;
            margin: 0;
            overflow-y: auto;
            /* allow scroll on short screens */
            overflow-x: hidden;
            background: #f0f4ff;
        }

        /* ── Animated background ── */
        .login-bg {
            position: fixed;
            inset: 0;
            background: linear-gradient(135deg, #fff7ed 0%, #fffbeb 40%, #fef3c7 100%);
            overflow: hidden;
        }

        /* Floating tech circles */
        .bg-circle {
            position: absolute;
            border-radius: 50%;
            animation: floatCircle 8s ease-in-out infinite;
        }

        .bg-circle:nth-child(1) {
            width: 420px;
            height: 420px;
            top: -100px;
            left: -80px;
            background: radial-gradient(circle, rgba(249, 115, 22, .20) 0%, transparent 70%);
            animation-duration: 9s;
        }

        .bg-circle:nth-child(2) {
            width: 300px;
            height: 300px;
            bottom: -60px;
            right: -60px;
            background: radial-gradient(circle, rgba(251, 191, 36, .22) 0%, transparent 70%);
            animation-duration: 7s;
            animation-delay: -3s;
        }

        .bg-circle:nth-child(3) {
            width: 200px;
            height: 200px;
            top: 50%;
            left: 60%;
            background: radial-gradient(circle, rgba(52, 211, 153, .15) 0%, transparent 70%);
            animation-duration: 11s;
            animation-delay: -5s;
        }

        @keyframes floatCircle {

            0%,
            100% {
                transform: translate(0, 0) scale(1);
            }

            33% {
                transform: translate(20px, -20px) scale(1.04);
            }

            66% {
                transform: translate(-10px, 15px) scale(0.97);
            }
        }

        /* ── Grid pattern overlay ── */
        .bg-grid {
            position: absolute;
            inset: 0;
            background-image: linear-gradient(rgba(249, 115, 22, .05) 1px, transparent 1px), linear-gradient(90deg, rgba(249, 115, 22, .05) 1px, transparent 1px);
            background-size: 40px 40px;
        }

        /* ── Login card ── */
        .login-card {
            background: rgba(255, 255, 255, 0.88);
            backdrop-filter: blur(24px) saturate(180%);
            -webkit-backdrop-filter: blur(24px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.92);
            border-radius: 24px;
            box-shadow: 0 32px 64px -12px rgba(249, 115, 22, .15), 0 16px 32px -8px rgba(0, 0, 0, .07);
            animation: cardEntrance 0.7s cubic-bezier(0.22, 1, 0.36, 1) both;
        }

        @keyframes cardEntrance {
            from {
                opacity: 0;
                transform: translateY(32px) scale(0.97);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        /* ── Logo icon ── */
        .logo-ring {
            background: linear-gradient(135deg, #ea580c 0%, #f97316 55%, #fb923c 100%);
            box-shadow: 0 8px 24px rgba(249, 115, 22, .4), 0 0 0 6px rgba(249, 115, 22, .12);
            animation: logoPulse 3s ease-in-out infinite;
        }

        @keyframes logoPulse {

            0%,
            100% {
                box-shadow: 0 8px 24px rgba(249, 115, 22, .4), 0 0 0 6px rgba(249, 115, 22, .12);
            }

            50% {
                box-shadow: 0 12px 32px rgba(249, 115, 22, .55), 0 0 0 10px rgba(249, 115, 22, .08);
            }
        }

        /* ── Inputs ── */
        .form-input {
            background: rgba(248, 250, 252, 0.9);
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
            transition: border-color .25s, box-shadow .25s, background .25s;
        }

        .form-input:focus {
            outline: none;
            border-color: #f97316;
            background: #fff;
            box-shadow: 0 0 0 3.5px rgba(249, 115, 22, .16);
        }

        .form-input:hover:not(:focus) {
            border-color: #fed7aa;
        }

        /* ── Submit button ── */
        .btn-primary {
            background: linear-gradient(135deg, #ea580c 0%, #f97316 100%);
            box-shadow: 0 4px 14px rgba(249, 115, 22, .4);
            border-radius: 12px;
            transition: transform .2s, box-shadow .2s, filter .2s;
        }

        .btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(249, 115, 22, .5);
            filter: brightness(1.05);
        }

        .btn-primary:active:not(:disabled) {
            transform: translateY(0);
            box-shadow: 0 2px 8px rgba(249, 115, 22, .35);
        }

        .btn-primary:disabled {
            opacity: .75;
            cursor: not-allowed;
        }

        /* ── Spinner ── */
        .spin {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* ── Status dot ── */
        .status-dot {
            background: #10b981;
            box-shadow: 0 0 0 0 rgba(16, 185, 129, .5);
            animation: statusPing 2s ease-in-out infinite;
        }

        @keyframes statusPing {

            0%,
            100% {
                box-shadow: 0 0 0 0 rgba(16, 185, 129, .5);
            }

            50% {
                box-shadow: 0 0 0 6px rgba(16, 185, 129, 0);
            }
        }

        /* ── Demo box ── */
        .demo-box {
            background: linear-gradient(135deg, #eff6ff 0%, #f0fdf4 100%);
            border: 1px solid #bfdbfe;
            border-radius: 12px;
        }

        /* Info badge */
        .badge-it {
            background: linear-gradient(135deg, #ea580c, #f97316);
            color: #fff;
            font-size: .65rem;
            font-weight: 700;
            letter-spacing: .06em;
            padding: .25rem .6rem;
            border-radius: 6px;
        }

        /* Loading overlay */
        #loadingOverlay {
            background: rgba(248, 250, 255, .85);
            backdrop-filter: blur(12px);
        }

        .loading-card {
            background: #fff;
            border-radius: 20px;
            border: 1px solid #fed7aa;
            box-shadow: 0 24px 48px rgba(249, 115, 22, .12);
        }

        .loading-ring {
            width: 56px;
            height: 56px;
            border: 3px solid #ffedd5;
            border-top-color: #f97316;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        /* scrollbar */
        ::-webkit-scrollbar {
            width: 5px;
        }

        ::-webkit-scrollbar-track {
            background: transparent;
        }

        ::-webkit-scrollbar-thumb {
            background: #fed7aa;
            border-radius: 3px;
        }

        /* ══════════════════════════════════════════
           RESPONSIVE — Desktop 14" (≈1366×768)
           Compact card: kurangi spacing & font
        ══════════════════════════════════════════ */
        @media (max-height: 800px) and (min-width: 1024px) {
            .login-card {
                padding: 1.25rem 1.5rem !important;
            }

            .logo-ring {
                width: 2.75rem !important;
                height: 2.75rem !important;
                border-radius: 12px !important;
            }

            .logo-ring i {
                font-size: 1rem !important;
            }

            .badge-mb {
                margin-bottom: 0.75rem !important;
            }

            .title-mb {
                margin-bottom: 1rem !important;
            }

            h1.main-title {
                font-size: 1.25rem !important;
            }

            p.main-sub {
                font-size: 0.75rem !important;
            }

            .form-gap {
                gap: 0.75rem !important;
            }

            .form-input {
                height: 2.5rem !important;
            }

            .btn-primary {
                height: 2.5rem !important;
            }

            .divider-my {
                margin-top: 0.75rem !important;
                margin-bottom: 0.75rem !important;
            }

            .status-row,
            .demo-box,
            .help-links,
            .footer-brand {
                display: none !important;
            }
        }

        /* ══════════════════════════════════════════
           RESPONSIVE — Tablet (768px – 1023px)
        ══════════════════════════════════════════ */
        @media (min-width: 768px) and (max-width: 1023px) {
            .login-card {
                max-width: 400px !important;
            }
        }

        /* ══════════════════════════════════════════
           RESPONSIVE — Mobile (≤767px)
           Standar nasional Indonesia (SNI / WCAG):
           - Touch target minimal 48×48dp (Android Material)
           - Font minimal 14sp body, 16sp input
           - Safe area inset support (notch)
           - Full-width card dengan padding horizontal 1rem
        ══════════════════════════════════════════ */
        @media (max-width: 767px) {
            body {
                padding: 0 !important;
                align-items: flex-start !important;
                /* Safe area support (iPhone notch / Android rounded corners) */
                padding-top: env(safe-area-inset-top, 0) !important;
                padding-bottom: env(safe-area-inset-bottom, 0) !important;
            }

            .login-card {
                width: 100% !important;
                max-width: 100% !important;
                min-height: 100dvh;
                /* cover full screen on mobile */
                border-radius: 0 !important;
                /* edge-to-edge */
                box-shadow: none !important;
                padding: 2rem 1.25rem 1.5rem !important;
                display: flex;
                flex-direction: column;
                justify-content: center;
            }

            /* Touch targets ≥ 48dp (Android Material / iOS HIG) */
            .form-input {
                height: 3rem !important;
                /* 48px */
                font-size: 1rem !important;
                /* 16px — prevents iOS auto-zoom */
                border-radius: 14px !important;
            }

            .btn-primary {
                height: 3rem !important;
                /* 48px */
                font-size: 1rem !important;
                border-radius: 14px !important;
                letter-spacing: .01em;
            }

            /* Logo sedikit lebih kecil */
            .logo-ring {
                width: 3.5rem !important;
                height: 3.5rem !important;
                border-radius: 16px !important;
            }

            .logo-ring i {
                font-size: 1.25rem !important;
            }

            /* Demo credentials: touch-friendly */
            .demo-box .grid>div {
                padding: 0.5rem !important;
            }

            /* Notch-safe bottom */
            .footer-brand {
                padding-bottom: env(safe-area-inset-bottom, 0.5rem) !important;
            }
        }

        /* Landscape mobile */
        @media (max-width: 767px) and (orientation: landscape) {
            .login-card {
                min-height: auto !important;
                padding: 1.25rem 2rem !important;
            }

            .status-row,
            .demo-box,
            .help-links {
                display: none !important;
            }
        }
    </style>
</head>

<body class="flex items-center justify-center min-h-screen p-4 sm:p-6 lg:p-4">

    <!-- Animated background -->
    <div class="login-bg">
        <div class="bg-grid"></div>
        <div class="bg-circle"></div>
        <div class="bg-circle"></div>
        <div class="bg-circle"></div>
    </div>

    <!-- Login Card -->
    <div class="login-card relative z-10 w-full max-w-[420px] p-6 sm:p-7">

        <!-- Logo + Title -->
        <div class="text-center title-mb mb-5">
            <div
                style="width: 120px; height: 120px; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px auto;">
                <img src="<?php echo app_abs_path('logo-tab/' . rawurlencode('logo ckt fix bg.png')); ?>"
                    alt="CITRATEL Logo"
                    style="width: 120px; height: 120px; object-fit: contain; mix-blend-mode: multiply;"
                    onerror="this.style.display='none'; this.nextElementSibling.style.display='block'">
                <i class="fas fa-microchip text-orange-500 text-2xl" style="display:none"></i>
            </div>
            <h1 class="main-title text-2xl font-bold text-gray-900 tracking-tight">IT Asset Management</h1>
            <p class="main-sub text-gray-500 text-sm mt-1">Sistem manajemen aset IT & Ticketing</p>
        </div>

        <!-- Login Form -->
        <form id="loginForm" class="form-gap space-y-3" novalidate>

            <!-- Username -->
            <div>
                <label for="username" class="block text-sm font-semibold text-gray-700 mb-1.5">
                    <i class="fas fa-user text-orange-400 mr-1.5"></i>Username
                </label>
                <div class="relative">
                    <input type="text" id="username" name="username"
                        class="form-input w-full h-11 pl-4 pr-4 text-sm text-gray-800 placeholder-gray-400"
                        placeholder="Masukkan username Anda" autocomplete="username" required>
                </div>
                <p class="text-orange-500 text-xs mt-1 flex items-center gap-1" id="usernameHint">
                    <i class="fas fa-info-circle text-[10px]"></i>
                    Username minimal 3 karakter
                </p>
            </div>

            <!-- Password -->
            <div>
                <label for="password" class="block text-sm font-semibold text-gray-700 mb-1.5">
                    <i class="fas fa-lock text-orange-400 mr-1.5"></i>Password
                </label>
                <div class="relative">
                    <input type="password" id="password" name="password"
                        class="form-input w-full h-11 pl-4 pr-11 text-sm text-gray-800 placeholder-gray-400"
                        placeholder="Masukkan password" autocomplete="current-password" required>
                    <button type="button" id="togglePassword"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-orange-500 transition-colors">
                        <i class="fas fa-eye" id="eyeIcon"></i>
                    </button>
                </div>
            </div>

            <!-- Submit -->
            <button type="submit" id="loginBtn"
                class="btn-primary w-full h-11 text-white font-semibold text-sm flex items-center justify-center gap-2 mt-2">
                <i class="fas fa-shield-alt" id="shieldIcon"></i>
                <span id="loginText">Masuk ke Sistem</span>
                <i class="fas fa-arrow-right text-xs" id="loginArrow"></i>
                <i class="fas fa-circle-notch spin hidden text-sm" id="loginSpinner"></i>
            </button>
        </form>

        <!-- Divider -->
        <div class="divider-my flex items-center gap-3 my-4">
            <div class="flex-1 h-px bg-gray-200"></div>
            <span class="text-xs text-gray-400 font-medium">INFO SISTEM</span>
            <div class="flex-1 h-px bg-gray-200"></div>
        </div>

        <!-- System Status -->
        <div class="status-row flex items-center justify-between mb-3">
            <div class="flex items-center gap-2">
                <div class="status-dot w-2 h-2 rounded-full"></div>
                <span class="text-xs text-gray-500 font-medium">System Status</span>
            </div>
            <span
                class="bg-emerald-50 text-emerald-700 border border-emerald-200 text-xs font-semibold px-2.5 py-1 rounded-full">
                Operasional
            </span>
        </div>


        <!-- Help links -->
        <div class="help-links text-center mt-3 space-y-1">
            <p class="text-xs text-gray-500">
                Butuh bantuan?
                <a href="#" class="text-orange-600 hover:text-orange-700 font-semibold transition-colors">Hubungi IT
                    Support</a>
            </p>
            <p class="text-xs text-gray-500">
                Lupa password?
                <a href="#" class="text-orange-600 hover:text-orange-700 font-semibold transition-colors">Reset via
                    admin</a>
            </p>
        </div>

        <!-- Footer branding -->
        <div class="footer-brand text-center mt-4 pt-3 border-t border-gray-100">
            <p class="text-[10px] text-gray-400">© <?php echo date('Y'); ?> PT Cipta Karya Technology · v1.2</p>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="fixed inset-0 flex items-center justify-center z-50 hidden" id="loadingOverlay">
        <div class="loading-card p-8 text-center max-w-xs mx-4">
            <div class="flex items-center justify-center mx-auto mb-5" style="width: 80px; height: 80px;">
                <img src="<?php echo app_abs_path('logo-tab/' . rawurlencode('logo ckt fix bg.png')); ?>"
                    alt="CITRATEL Logo"
                    style="width: 80px; height: 80px; object-fit: contain; mix-blend-mode: multiply;">
            </div>
            <h3 class="text-base font-bold text-gray-800 mb-1">Memverifikasi Akses</h3>
            <p class="text-gray-500 text-sm mb-5">Mengamankan koneksi ke sistem...</p>
            <div class="loading-ring mx-auto mb-4"></div>
            <div class="flex justify-center gap-1.5">
                <div class="w-1.5 h-1.5 rounded-full bg-orange-400"
                    style="animation: statusPing 1.5s ease-in-out infinite"></div>
                <div class="w-1.5 h-1.5 rounded-full bg-orange-500"
                    style="animation: statusPing 1.5s ease-in-out infinite .2s"></div>
                <div class="w-1.5 h-1.5 rounded-full bg-orange-400"
                    style="animation: statusPing 1.5s ease-in-out infinite .4s"></div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            initializeFormInteractions();

            const loginError = <?php echo json_encode($login_error); ?>;
            const loginSuccess = <?php echo json_encode($login_success); ?>;

            if (loginError) {
                Swal.fire({
                    icon: 'error', title: 'Login Gagal',
                    text: 'Username atau password salah. Silahkan dicoba kembali.',
                    confirmButtonColor: '#f97316', confirmButtonText: 'Coba Lagi'
                });
            }
            if (loginSuccess) {
                Swal.fire({
                    icon: 'success', title: 'Login Berhasil',
                    text: 'Selamat datang kembali!',
                    confirmButtonColor: '#f97316', confirmButtonText: 'OK'
                });
            }
        });

        function initializeFormInteractions() {
            const usernameInput = document.getElementById('username');
            const passwordInput = document.getElementById('password');
            const togglePassword = document.getElementById('togglePassword');
            const eyeIcon = document.getElementById('eyeIcon');
            const loginForm = document.getElementById('loginForm');
            const usernameHint = document.getElementById('usernameHint');

            usernameInput.addEventListener('input', () => {
                usernameHint.style.display = usernameInput.value.length > 0 ? 'none' : 'flex';
            });

            togglePassword.addEventListener('click', () => {
                const isPass = passwordInput.type === 'password';
                passwordInput.type = isPass ? 'text' : 'password';
                eyeIcon.className = isPass ? 'fas fa-eye-slash' : 'fas fa-eye';
            });

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

                if (!validateForm(username, password)) return;

                loginBtn.disabled = true;
                loginText.textContent = 'Memverifikasi...';
                loginArrow.classList.add('hidden');
                shieldIcon.classList.add('hidden');
                loginSpinner.classList.remove('hidden');
                loadingOverlay.classList.remove('hidden');

                try {
                    const formData = new FormData();
                    formData.append('username', username);
                    formData.append('password', password);

                    const response = await fetch('login.php', { method: 'POST', body: formData });
                    const result = await response.json();

                    await new Promise(r => setTimeout(r, 1500));

                    if (result.success) {
                        loadingOverlay.classList.add('hidden');
                        Swal.fire({
                            icon: 'success', title: 'Login Berhasil!',
                            text: result.message,
                            timer: 1800, showConfirmButton: false,
                            confirmButtonColor: '#6366f1'
                        }).then(() => { window.location.href = result.redirect_url; });
                    } else {
                        throw new Error(result.message);
                    }
                } catch (error) {
                    loadingOverlay.classList.add('hidden');
                    Swal.fire({
                        icon: 'error', title: 'Login Gagal!',
                        text: error.message || 'Terjadi kesalahan. Silakan coba lagi.',
                        confirmButtonColor: '#6366f1', confirmButtonText: 'OK'
                    });
                } finally {
                    loginBtn.disabled = false;
                    loginText.textContent = 'Masuk ke Sistem';
                    loginArrow.classList.remove('hidden');
                    shieldIcon.classList.remove('hidden');
                    loginSpinner.classList.add('hidden');
                }
            });
        }

        function validateForm(username, password) {
            if (!username) {
                Swal.fire({ icon: 'warning', title: 'Username Diperlukan', text: 'Mohon masukkan username Anda', confirmButtonColor: '#6366f1', confirmButtonText: 'OK' });
                return false;
            }
            if (username.length < 3) {
                Swal.fire({ icon: 'warning', title: 'Username Terlalu Pendek', text: 'Username minimal 3 karakter', confirmButtonColor: '#6366f1', confirmButtonText: 'OK' });
                return false;
            }
            if (!password) {
                Swal.fire({ icon: 'warning', title: 'Password Diperlukan', text: 'Mohon masukkan password Anda', confirmButtonColor: '#6366f1', confirmButtonText: 'OK' });
                return false;
            }
            return true;
        }
    </script>
</body>

</html>