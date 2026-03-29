<?php
session_start();
ini_set('display_errors', 1); // Tetap sembunyi error di production
error_reporting(E_ALL);

// Logging awal
error_log("Script started");

// Session check dengan JSON
if (!isset($_SESSION['user_id'])) {
    error_log("Session user_id missing");
    echo json_encode(['success' => false, 'message' => 'Session tidak valid.']);
    // ob_end_clean();
    exit();
}

$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : null;
if (!$user_role || $user_role !== 'super_admin') {
    error_log("Role invalid: $user_role");
    echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
    // ob_end_clean();
    exit();
}

// Koneksi DB dengan try-catch
try {
    $conn = new mysqli("localhost", "root", "", "crud");
    // $conn = new mysqli("localhost", "cktnosa2_admin", "uGXj8#eiI=P%", "cktnosa2_crud");
    if ($conn->connect_error) {
        throw new Exception("DB connect failed: " . $conn->connect_error);
    }
} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Koneksi database gagal.']);
    // ob_end_clean();
    exit();
}

$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'Admin User';
$Nama_Lengkap = isset($_SESSION['Nama_Lengkap']) ? $_SESSION['Nama_Lengkap'] : $username;
$Jabatan_Level_Session = trim((string) ($_SESSION['Jabatan_Level'] ?? ''));
$Jabatan_Level_Display = $Jabatan_Level_Session !== '' ? $Jabatan_Level_Session : '-';

// Handle Form Submit (Tambah Akun)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['action'])) {
    $Nama_Lengkap = $_POST['Nama_Lengkap'] ?? '';
    $username = $_POST['username'] ?? '';
    $Jabatan_Level = $_POST['Jabatan_Level'] ?? '';
    $Divisi = $_POST['Divisi'] ?? '';
    $Region = $_POST['Region'] ?? '';
    $role = $_POST['role'] ?? '';
    $Email = $_POST['Email'] ?? '';
    $Status_Akun = $_POST['Status_Akun'] ?? 'Aktif';

    // Cek duplikat Nama_Lengkap atau username
    $checkStmt = $conn->prepare("SELECT id FROM users WHERE Nama_Lengkap = ? OR username = ?");
    $checkStmt->bind_param("ss", $Nama_Lengkap, $username);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if ($checkResult->num_rows > 0) {
        $error_msg = "Nama Lengkap atau ID Karyawan sudah terdaftar!";
    } else {
        $password = password_hash($_POST['password'] ?? '', PASSWORD_DEFAULT);
        $Create_by = $_SESSION['Nama_Lengkap'] ?? 'System';
        $Create_datetime = date('Y-m-d H:i:s');
        $sql = "INSERT INTO users (Nama_Lengkap, username, Jabatan_Level, Divisi, Region, role, Email, password, Status_Akun, Create_by, Create_datetime) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssssssss", $Nama_Lengkap, $username, $Jabatan_Level, $Divisi, $Region, $role, $Email, $password, $Status_Akun, $Create_by, $Create_datetime);
        if ($stmt->execute()) {
            header("Location: add_akun.php?success=added");
            exit();
        } else {
            header("Location: add_akun.php?error=" . urlencode("Gagal menambah akun: " . $stmt->error));
            exit();
        }
        $stmt->close();
    }
    $checkStmt->close();
}

// Handle AJAX Edit
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'edit') {
    header('Content-Type: application/json');
    try {
        $id = $_POST['id'] ?? null;
        if (!$id || !is_numeric($id)) {
            throw new Exception('ID tidak valid.');
        }
        $Nama_Lengkap = $_POST['Nama_Lengkap'] ?? '';
        $username = $_POST['username'] ?? '';
        $Jabatan_Level = $_POST['Jabatan_Level'] ?? '';
        $Divisi = $_POST['Divisi'] ?? '';
        $Region = $_POST['Region'] ?? '';
        $role = $_POST['role'] ?? '';
        $Email = $_POST['Email'] ?? '';
        $Status_Akun = $_POST['Status_Akun'] ?? '';

        if (empty($Nama_Lengkap) || empty($username) || empty($Jabatan_Level) || empty($Divisi) || empty($Region) || empty($role) || empty($Email) || empty($Status_Akun)) {
            throw new Exception('Semua field wajib diisi.');
        }

        $password = !empty($_POST['password']) ? password_hash($_POST['password'], PASSWORD_DEFAULT) : null;

        // Gunakan koneksi utama ($conn), jangan buat baru!
        if ($password) {
            $sql = "UPDATE users SET Nama_Lengkap = ?, username = ?, Jabatan_Level = ?, Divisi = ?, Region = ?, role = ?, Email = ?, password = ?, Status_Akun = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssssssi", $Nama_Lengkap, $username, $Jabatan_Level, $Divisi, $Region, $role, $Email, $password, $Status_Akun, $id);
        } else {
            $sql = "UPDATE users SET Nama_Lengkap = ?, username = ?, Jabatan_Level = ?, Divisi = ?, Region = ?, role = ?, Email = ?, Status_Akun = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssssssi", $Nama_Lengkap, $username, $Jabatan_Level, $Divisi, $Region, $role, $Email, $Status_Akun, $id);
        }

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Akun berhasil diupdate!']);
        } else {
            throw new Exception("Gagal menyimpan perubahan: " . $stmt->error);
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("Edit error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit(); // Penting: hentikan eksekusi
}

// Handle AJAX Delete
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete') {
    // Pastikan header JSON dikirim
    header('Content-Type: application/json');

    try {
        $id = $_POST['id'] ?? null;
        if (!$id || !is_numeric($id)) {
            throw new Exception('ID tidak valid.');
        }

        $current_user_id = $_SESSION['user_id'] ?? null;
        if ($id == $current_user_id) {
            echo json_encode(['success' => false, 'message' => 'Tidak bisa hapus akun sendiri!']);
            exit();
        }

        $sql = "DELETE FROM users WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare gagal: " . $conn->error);
        }

        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Akun berhasil dihapus!']);
        } else {
            throw new Exception("Eksekusi gagal: " . $stmt->error);
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("Delete error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit(); // Hanya exit(), jangan pakai ob_end_clean() di sini
}

// ---- AJAX Pagination untuk Tabel Users ----
if (isset($_GET['action']) && $_GET['action'] === 'ajax_users') {
    header('Content-Type: application/json; charset=utf-8');
    $search = trim((string) ($_GET['search'] ?? ''));
    $per_page = in_array((int) ($_GET['per_page'] ?? 10), [10, 15, 25, 50]) ? (int) $_GET['per_page'] : 10;
    $page_num = max(1, (int) ($_GET['page'] ?? 1));

    $where = '1=1';
    $params = [];
    $types = '';
    if ($search !== '') {
        $s = '%' . $search . '%';
        $where .= ' AND (Nama_Lengkap LIKE ? OR username LIKE ? OR Jabatan_Level LIKE ? OR Divisi LIKE ? OR Region LIKE ? OR Email LIKE ?)';
        $params = [$s, $s, $s, $s, $s, $s];
        $types = 'ssssss';
    }

    // Count
    $stmtC = $conn->prepare("SELECT COUNT(*) FROM users WHERE $where");
    if ($stmtC && $types)
        $stmtC->bind_param($types, ...$params);
    $total = 0;
    if ($stmtC) {
        $stmtC->execute();
        $stmtC->bind_result($total);
        $stmtC->fetch();
        $stmtC->close();
    }
    $total_pages = max(1, (int) ceil($total / $per_page));
    $page_num = min($page_num, $total_pages);
    $offset_ajax = ($page_num - 1) * $per_page;

    // Data
    $allParams = array_merge($params, [$per_page, $offset_ajax]);
    $allTypes = $types . 'ii';
    $stmtD = $conn->prepare("SELECT id, Nama_Lengkap, username, Jabatan_Level, Divisi, Region, role, Email, Status_Akun, Create_datetime, Create_by FROM users WHERE $where ORDER BY id DESC LIMIT ? OFFSET ?");
    $rows = [];
    if ($stmtD) {
        if ($allTypes)
            $stmtD->bind_param($allTypes, ...$allParams);
        $stmtD->execute();
        $rows = $stmtD->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtD->close();
    }

    // Build tbody HTML
    $tbody = '';
    $no = $offset_ajax + 1;
    foreach ($rows as $user) {
        $id = (int) $user['id'];
        $nama = htmlspecialchars($user['Nama_Lengkap']);
        $uname = htmlspecialchars($user['username']);
        $jabatan = htmlspecialchars($user['Jabatan_Level']);
        $divisi = htmlspecialchars($user['Divisi']);
        $region = htmlspecialchars($user['Region'] ?? '');
        $role_v = $user['role'];
        $email = htmlspecialchars($user['Email']);
        $status = $user['Status_Akun'];
        $createBy = !empty($user['Create_by']) ? htmlspecialchars($user['Create_by']) : '<span class="text-gray-400">—</span>';
        $createDt = !empty($user['Create_datetime']) ? date('d M Y H:i', strtotime($user['Create_datetime'])) : '<span class="text-gray-400">—</span>';
        $roleBadge = in_array($role_v, ['super_admin', 'admin']) ? 'role-super_admin' : 'role-user';
        $roleLabel = ucfirst(str_replace('_', ' ', $role_v));
        $statusBadge = ($status === 'Aktif') ? 'status-active' : 'status-inactive';

        $tbody .= "<tr class='table-row' data-id='$id' data-nama='$nama' data-username='$uname' data-jabatan='$jabatan' data-divisi='$divisi' data-region='$region' data-role='$role_v' data-email='$email' data-status='$status'>
            <td class='px-7 py-4 whitespace-nowrap text-sm text-gray-500'>$createDt</td>
            <td class='px-7 py-4 whitespace-nowrap text-sm text-gray-500'>$createBy</td>
            <td class='px-7 py-4 whitespace-nowrap text-sm font-medium text-gray-900'>$nama</td>
            <td class='px-7 py-4 whitespace-nowrap text-sm text-gray-500'>$uname</td>
            <td class='px-7 py-4 whitespace-nowrap text-sm text-gray-500'>$jabatan</td>
            <td class='px-7 py-4 whitespace-nowrap text-sm text-gray-500'>$divisi</td>
            <td class='px-7 py-4 whitespace-nowrap text-sm text-gray-500'>$region</td>
            <td class='px-7 py-4 whitespace-nowrap'><span class='status-badge $roleBadge'>$roleLabel</span></td>
            <td class='px-7 py-4 whitespace-nowrap text-sm text-gray-500'>******</td>
            <td class='px-7 py-4 whitespace-nowrap'><span class='status-badge $statusBadge'>$status</span></td>
            <td class='px-7 py-4 whitespace-nowrap text-sm font-medium space-x-2'>
                <button class='ajax-edit-btn text-blue-600 hover:text-blue-900 transition-colors' data-id='$id'><i class='fas fa-edit mr-1'></i>Edit</button>
                <button class='ajax-delete-btn text-red-600 hover:text-red-900 transition-colors' data-id='$id'><i class='fas fa-trash mr-1'></i>Delete</button>
            </td>
        </tr>";
        $no++;
    }
    if (empty($rows)) {
        $tbody = "<tr><td colspan='11' class='px-4 py-16 text-center text-gray-400'><i class='fas fa-users text-4xl mb-3 text-gray-200 block'></i>Tidak ada data akun ditemukan</td></tr>";
    }

    // Build pagination HTML
    $pag = '';
    if ($total_pages >= 1) {
        $pag .= '<div class="mt-4 flex flex-col items-center"><div class="w-full max-w-4xl flex flex-col items-center space-y-2">';
        $pag .= '<div class="text-sm text-gray-600 text-center">Showing ' . ($total > 0 ? $offset_ajax + 1 : 0) . ' to ' . min($offset_ajax + $per_page, $total) . ' of ' . $total . ' results</div>';
        $pag .= '<nav class="inline-flex rounded-md shadow-sm -space-x-px justify-center">';
        if ($page_num > 1) {
            $pag .= '<button onclick="fetchUsers(' . ($page_num - 1) . ')" class="relative inline-flex items-center px-3 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50"><i class="fas fa-chevron-left"></i><span class="ml-1">Prev</span></button>';
        }
        $start_p = max(1, $page_num - 2);
        $end_p = min($total_pages, $page_num + 2);
        if ($start_p > 1) {
            $pag .= '<button onclick="fetchUsers(1)" class="relative inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">1</button>';
            if ($start_p > 2)
                $pag .= '<span class="relative inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-500">...</span>';
        }
        for ($i = $start_p; $i <= $end_p; $i++) {
            if ($i == $page_num)
                $pag .= '<span class="relative z-10 inline-flex items-center px-3 py-2 border border-orange-500 bg-orange-50 text-sm font-medium text-orange-600">' . $i . '</span>';
            else
                $pag .= '<button onclick="fetchUsers(' . $i . ')" class="relative inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">' . $i . '</button>';
        }
        if ($end_p < $total_pages) {
            if ($end_p < $total_pages - 1)
                $pag .= '<span class="relative inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-500">...</span>';
            $pag .= '<button onclick="fetchUsers(' . $total_pages . ')" class="relative inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">' . $total_pages . '</button>';
        }
        if ($page_num < $total_pages) {
            $pag .= '<button onclick="fetchUsers(' . ($page_num + 1) . ')" class="relative inline-flex items-center px-3 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50"><span class="mr-1">Next</span><i class="fas fa-chevron-right"></i></button>';
        }
        $pag .= '</nav></div></div>';
    }

    echo json_encode([
        'tbody_html' => $tbody,
        'pagination_html' => $pag,
        'current_page' => $page_num,
        'total_pages' => $total_pages,
        'total_records' => $total,
        'showing_from' => $total > 0 ? $offset_ajax + 1 : 0,
        'showing_to' => min($offset_ajax + $per_page, $total),
    ]);
    exit;
}

// Fetch distinct master data for combo inputs
$masterJabatan = [];
$masterDivisi = [];
$masterRegion = [];
$resMaster = $conn->query("SELECT DISTINCT Jabatan_Level FROM users WHERE Jabatan_Level IS NOT NULL AND Jabatan_Level != '' ORDER BY Jabatan_Level ASC");
if ($resMaster) {
    while ($r = $resMaster->fetch_assoc())
        $masterJabatan[] = $r['Jabatan_Level'];
}
$resMaster = $conn->query("SELECT DISTINCT Divisi FROM users WHERE Divisi IS NOT NULL AND Divisi != '' ORDER BY Divisi ASC");
if ($resMaster) {
    while ($r = $resMaster->fetch_assoc())
        $masterDivisi[] = $r['Divisi'];
}
$resMaster = $conn->query("SELECT DISTINCT Region FROM users WHERE Region IS NOT NULL AND Region != '' ORDER BY Region ASC");
if ($resMaster) {
    while ($r = $resMaster->fetch_assoc())
        $masterRegion[] = $r['Region'];
}

$current_page = basename($_SERVER['PHP_SELF']);
$is_settings_page = in_array($current_page, ['add_akun.php', 'profile.php', 'system-settings.php', 'help.php']);

// Handle AJAX Duplicate Check untuk Nama_Lengkap dan username (ID Karyawan)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'check_duplicate') {
    header('Content-Type: application/json');

    try {
        $field = $_POST['field'] ?? '';
        $value = trim($_POST['value'] ?? '');

        // Hanya izinkan field yang valid
        if (!in_array($field, ['Nama_Lengkap', 'username'])) {
            throw new Exception('Field tidak diizinkan.');
        }

        if (strlen($value) < 2) {
            echo json_encode(['success' => true, 'exists' => false]);
            exit();
        }

        // $conn = new mysqli("localhost", "root", "", "crud");
        // $conn = new mysqli("localhost", "cktnosa2_admin", "uGXj8#eiI=P%", "cktnosa2_crud");
        if ($conn->connect_error) {
            throw new Exception("Koneksi database gagal.");
        }

        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE `$field` = ?");
        $stmt->bind_param("s", $value);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $exists = $row['count'] > 0;

        echo json_encode(['success' => true, 'exists' => $exists]);

        $stmt->close();
        $conn->close();
    } catch (Exception $e) {
        error_log("Duplicate check error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Gagal memeriksa duplikat.']);
    }
    exit();
}

$conn->close();
// ob_end_clean();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Asset Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        /* PERBAIKAN: Custom Styling untuk Select2 (match Tailwind) */
        /* PERBAIKAN: Custom Styling untuk Select2 (match Tailwind, tambah force search) */
        .select2-container--default .select2-selection--single {
            height: 42px !important;
            /* Match py-2 px-3 */
            border: 1px solid #d1d5db !important;
            /* border-gray-300 */
            border-radius: 0.5rem !important;
            /* rounded-lg */
            background-color: rgba(255, 255, 255, 0.9) !important;
            /* bg-white/90 */
            backdrop-filter: blur(4px) !important;
            font-size: 0.875rem !important;
            /* text-sm */
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            color: #374151 !important;
            /* text-gray-700 */
            padding-left: 0.75rem !important;
            /* px-3 */
            padding-top: 0.5rem !important;
            /* py-2 */
            line-height: 1.25 !important;
        }

        .select2-container--default .select2-selection--single .select2-selection__placeholder {
            color: #9ca3af !important;
            /* placeholder-gray-500 */
        }

        .select2-container--default.select2-container--focus .select2-selection--single {
            border-color: #3b82f6 !important;
            /* focus:border-blue-500 */
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1) !important;
            /* focus:ring-2 ring-blue-500/10 */
            outline: none !important;
        }

        .select2-dropdown {
            border: 1px solid #d1d5db !important;
            /* border-gray-300 */
            border-radius: 0.5rem !important;
            /* rounded-lg */
            background-color: rgba(255, 255, 255, 0.95) !important;
            /* bg-white/95 */
            backdrop-filter: blur(4px) !important;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05) !important;
            /* shadow-lg */
            z-index: 9999 !important;
            /* Tinggi z-index untuk hindari tutup sidebar */
        }

        .select2-container--default .select2-results__option--highlighted[aria-selected] {
            background-color: #dbeafe !important;
            /* bg-blue-100 */
            color: #1e40af !important;
            /* text-blue-800 */
        }

        .select2-container--default .select2-results__option {
            padding: 0.5rem 0.75rem !important;
            /* py-2 px-3 */
            font-size: 0.875rem !important;
            /* text-sm */
        }

        .select2-container--default .select2-search--dropdown .select2-search__field {
            border: 1px solid #d1d5db !important;
            border-radius: 0.5rem !important;
            padding: 0.5rem 0.75rem !important;
            background-color: white !important;
            font-size: 0.875rem !important;
            /* text-sm */
        }

        /* PERBAIKAN: Force search box tampil di semua dropdown (bahkan jika opsi sedikit) */
        .select2-container .select2-search--inline {
            display: block !important;
            width: 100% !important;
            margin: 5px 0 !important;
        }

        .select2-container--default .select2-search--inline .select2-search__field {
            background: white !important;
            border: 1px solid #d1d5db !important;
            border-radius: 0.5rem !important;
            padding: 0.5rem !important;
            width: 100% !important;
        }

        /* Max height untuk dropdown panjang (seperti Kategori) */
        .select2-container--default .select2-results {
            max-height: 160px !important;
            /* max-h-40 ≈ 160px */
            overflow-y: auto !important;
        }

        /* Mobile responsive */
        @media (max-width: 768px) {
            .select2-container--default .select2-selection--single {
                height: 38px !important;
                font-size: 0.875rem !important;
            }
        }

        /* Hide hamburger in desktop (sidebar locked open) */
        #hamburger-btn {
            display: none;
        }

        @media (min-width: 1024px) {
            #hamburger-btn {
                display: flex !important;
                /* Pastikan flex seperti style inline */
            }
        }


        * {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }

        .animate-fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }

        .animate-slide-up {
            animation: slideUp 0.6s ease-out;
        }

        .animate-scale-in {
            animation: scaleIn 0.4s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes scaleIn {
            from {
                opacity: 0;
                transform: scale(0.9);
            }

            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .status-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            transform-origin: center;
        }

        .status-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        .sidebar {
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .sidebar-item {
            transition: all 0.2s ease;
        }

        .sidebar-item:hover {
            transform: translateX(4px);
        }

        /* Custom scrollbar */
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 3px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 3px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        /* Grid styles */
        .asset-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        /* Responsive grid adjustments */
        @media (max-width: 768px) {
            .asset-grid {
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
                gap: 1rem;
            }
        }

        @media (max-width: 640px) {
            .asset-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
        }

        /* Table row hover */
        .table-row {
            transition: all 0.3s ease;
        }

        .table-row:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 25px rgba(0, 0, 0, 0.1);
        }

        /* Modal styles */
        .modal-backdrop {
            backdrop-filter: blur(8px);
            background: rgba(0, 0, 0, 0.8);
        }

        .modal-content {
            animation: modalFadeIn 0.3s ease-out;
        }

        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: translate(-50%, -50%) scale(0.9);
            }

            to {
                opacity: 1;
                transform: translate(-50%, -50%) scale(1);
            }
        }

        /* Status badge hover effects */
        .status-badge {
            transition: all 0.3s ease;
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-align: center;
            border: 1px solid transparent;
        }

        .status-badge:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        /* Sidebar Compact Sizing - Responsive untuk hindari zoom/overflow */
        #sidebar {
            /* Default untuk mobile: Ukuran asli (sudah pas) */
        }

        @media (min-width: 1024px) {

            /* Desktop: Kurangi ukuran agar mirip mobile (tidak zoom besar) */
            #sidebar .w-16 {
                width: 3.5rem;
                height: 3.5rem;
            }

            /* Logo dari 64px → 56px */
            #sidebar .w-12 {
                width: 2.75rem;
                height: 2.75rem;
            }

            /* Profile dari 48px → 44px */
            #sidebar .text-2xl {
                font-size: 1.125rem;
                line-height: 1.75rem;
            }

            /* Logo icon dari 24px → 18px */
            #sidebar .text-lg {
                font-size: 1rem;
                line-height: 1.5rem;
            }

            /* Header & profile icon/teks dari 18px → 16px */
            #sidebar .text-xl {
                font-size: 1.125rem;
                line-height: 1.75rem;
            }

            /* Menu icon dari 20px → 18px */
            #sidebar h2 {
                font-size: 1rem;
                line-height: 1.5rem;
            }

            /* Header title dari text-lg → text-base */
            #sidebar .p-6 {
                padding: 1rem;
            }

            /* Padding header/profile dari 24px → 16px */
            #sidebar .pt-8 {
                padding-top: 1.25rem;
            }

            /* Top padding header dari 32px → 20px */
            #sidebar .py-4 {
                padding-top: 0.75rem;
                padding-bottom: 0.75rem;
            }

            /* Menu vertikal dari 16px → 12px */
            #sidebar .px-6 {
                padding-left: 1.25rem;
                padding-right: 1.25rem;
            }

            /* Menu horizontal dari 24px → 20px */
            #sidebar .p-4 {
                padding: 0.75rem;
            }

            /* Profile inner padding dari 16px → 12px */
            #sidebar .p-2 {
                padding: 0.5rem;
            }

            /* Icon menu padding dari 8px → 4px (compact) */
            #sidebar .mx-2 {
                margin-left: 0.5rem;
                margin-right: 0.5rem;
            }

            /* Menu margin dari 8px → 4px */
            #sidebar .mb-2 {
                margin-bottom: 0.5rem;
            }

            /* Menu bottom margin dari 8px → 4px */
            #sidebar .mb-4 {
                margin-bottom: 1rem;
            }

            /* Space antar section dari 16px → 16px (tetap) */
        }

        /* Adjust footer gradient untuk hindari tutup content di desktop */
        @media (min-width: 1024px) {
            #sidebar>.absolute.bottom-0 {
                /* Target footer decoration */
                height: 1.5rem;
                /* Dari h-20 (80px) → 24px, lebih tipis */
            }
        }


        /* Loading Animation - Gradient Biru-Oren Cerah dengan Shimmer (Lebih Menarik & Dinamis) */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            /* Base gradient: Biru cerah ke oren cerah dengan multi-stop untuk depth */
            background: linear-gradient(135deg, #4692f9 0%, #5a9df9 25%, #ffa700 50%, #ffb347 75%, #ff9500 100%);
            background-size: 400% 400%;
            /* Ukuran besar untuk animasi shimmer (bergerak) */
            animation: shimmerGradient 4s ease-in-out infinite;
            /* Animasi bergerak pelan (shimmer effect) */
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            transition: opacity 0.5s ease-out;
            /* Fade out halus */
        }

        .loading-content {
            text-align: center;
            color: #1e293b;
            /* Teks navy gelap untuk kontras tajam di gradient cerah bergerak */
        }

        .loading-logo {
            width: 100px;
            /* Ukuran seperti sebelumnya */
            height: 100px;
            margin: 0 auto 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            overflow: hidden;
            background: rgba(255, 255, 255, 0.9);
            /* Background putih untuk kontras dengan shimmer */
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            /* Shadow lembut */
            animation: logoRotate 2s ease-in-out;
            /* Animasi rotasi logo tetap */
            border: 2px solid rgba(255, 255, 255, 0.4);
            /* Border untuk highlight di shimmer */
        }

        .logo-image {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: inherit;
        }

        .loading-bars {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin: 2rem 0;
        }

        .loading-bar {
            width: 12px;
            height: 12px;
            background: #1e293b;
            /* Navy gelap untuk kontras dengan gradient bergerak */
            border-radius: 50%;
            animation: loadingPulse 1.5s ease-in-out infinite;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            /* Shadow ringan */
        }

        .loading-bar:nth-child(2) {
            animation-delay: 0.2s;
        }

        .loading-bar:nth-child(3) {
            animation-delay: 0.4s;
        }

        .loading-progress {
            width: 200px;
            height: 4px;
            background: rgba(30, 41, 59, 0.2);
            /* Background navy semi-transparan */
            border-radius: 2px;
            overflow: hidden;
            margin: 0 auto;
            box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.1);
            /* Inner shadow untuk depth */
        }

        .loading-progress-bar {
            width: 0%;
            height: 100%;
            background: linear-gradient(90deg, #4692f9, #ffa700);
            /* Gradient biru-orens cerah untuk progress (match base) */
            background-size: 200% 100%;
            /* Ukuran untuk shimmer mini di progress */
            animation: progressShimmer 2s ease-out forwards, progressLoad 2s ease-out forwards;
            /* Shimmer + isi progress */
            border-radius: 2px;
            box-shadow: 0 0 8px rgba(70, 146, 249, 0.4);
            /* Glow biru cerah */
        }

        /* Shimmer untuk progress bar (opsional, mini version) */
        @keyframes progressShimmer {
            0% {
                background-position: -200% 0;
            }

            100% {
                background-position: 200% 0;
            }
        }

        /* Keyframes tetap sama untuk elemen lain (jangan ubah) */
        @keyframes logoRotate {
            0% {
                transform: scale(0) rotate(-180deg);
                opacity: 0;
            }

            100% {
                transform: scale(1) rotate(0deg);
                opacity: 1;
            }
        }

        @keyframes loadingPulse {

            0%,
            100% {
                transform: scale(1);
                opacity: 0.7;
            }

            50% {
                transform: scale(1.5);
                opacity: 1;
            }
        }

        @keyframes progressLoad {
            0% {
                width: 0%;
            }

            100% {
                width: 100%;
            }
        }

        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

        * {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }

        /* Status card animations */
        .status-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            transform-origin: center;
        }

        .status-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        /* Sidebar animations */
        .sidebar {
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .sidebar-item {
            transition: all 0.2s ease;
        }

        .sidebar-item:hover {
            transform: translateX(4px);
        }

        /* Mobile overlay */
        .mobile-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 40;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .mobile-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        /* Sidebar responsive states */
        .sidebar {
            transform: translateX(-100%);
        }

        .sidebar.open {
            transform: translateX(0);
        }

        @media (min-width: 1024px) {
            .sidebar {
                transform: translateX(0);
            }
        }

        /* Filter card styling */
        .filter-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 25px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        /* Custom select styling */
        .custom-select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 0.5rem center;
            background-repeat: no-repeat;
            background-size: 1.5em 1.5em;
            padding-right: 2.5rem;
        }

        /* Table hover effects */
        .table-row {
            transition: all 0.3s ease;
        }

        .table-row:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 25px rgba(0, 0, 0, 0.1);
        }

        /* Status badge animations */
        .status-badge {
            transition: all 0.3s ease;
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-align: center;
            border: 1px solid transparent;
        }

        .status-badge:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        /* Animations */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes bounceIn {
            0% {
                opacity: 0;
                transform: scale(0.3);
            }

            50% {
                opacity: 1;
                transform: scale(1.05);
            }

            70% {
                transform: scale(0.9);
            }

            100% {
                opacity: 1;
                transform: scale(1);
            }
        }

        .animate-fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }

        .animate-slide-up {
            animation: slideUp 0.6s ease-out;
        }

        .animate-bounce-in {
            animation: bounceIn 0.8s ease-out;
        }

        /* Hamburger button fix */
        .hamburger-btn {
            display: flex !important;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            position: relative;
        }

        @media (min-width: 1024px) {
            .hamburger-btn {
                display: none !important;
            }
        }

        /* Loading Animation - Gradient Biru-Oren Cerah dengan Shimmer (Fixed: Lebih Vibrant & Bergerak) */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            /* FIX: Full viewport width (vw) untuk cover sempurna */
            height: 100vh;
            /* FIX: Full viewport height (vh) */
            /* Gradient lebih vibrant: Biru cerah ke oren terang dengan stop lebih banyak untuk depth */
            background: linear-gradient(135deg,
                    #3b82f6 0%,
                    /* Biru Tailwind lebih cerah */
                    #60a5fa 20%,
                    /* Biru muda transisi */
                    #f59e0b 40%,
                    /* Oren Tailwind cerah */
                    #fbbf24 60%,
                    /* Kuning-oren transisi */
                    #f97316 80%,
                    /* Oren gelap vibrant */
                    #ea580c 100%
                    /* Oren panas akhir */
                );
            background-size: 300% 300%;
            /* FIX: Ukuran lebih kecil untuk shimmer lebih cepat & smooth */
            animation: shimmerGradient 3s ease-in-out infinite;
            /* FIX: Speed lebih cepat (3s) untuk efek bergerak nyata */
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 99999;
            /* FIX: Z-index lebih tinggi dari sidebar/modal (99999) */
            opacity: 1;
            /* FIX: Mulai full opacity */
            transition: opacity 0.4s ease-out;
            /* Fade out lebih cepat */
            /* FIX: Backdrop blur untuk bikin loading "masuk" & pop di atas konten */
            backdrop-filter: blur(8px) saturate(150%);
            /* Blur halaman belakang + tingkatkan saturasi warna */
            -webkit-backdrop-filter: blur(8px) saturate(150%);
            /* Support Safari */
        }

        /* FIX: Shimmer animasi lebih smooth dengan pause di tengah untuk efek "cahaya memantul" */
        @keyframes shimmerGradient {
            0% {
                background-position: 0% 50%;
                opacity: 0.9;
                /* FIX: Mulai sedikit transparan untuk depth */
            }

            25% {
                background-position: 100% 50%;
            }

            50% {
                background-position: 100% 50%;
                opacity: 1;
                /* FIX: Puncak brightness di tengah shimmer */
            }

            75% {
                background-position: 0% 50%;
            }

            100% {
                background-position: 0% 50%;
                opacity: 0.9;
            }
        }

        /* FIX: Loading content dengan kontras lebih tinggi untuk "masuk" di gradient bergerak */
        .loading-content {
            text-align: center;
            color: #ffffff;
            /* FIX: Teks putih full untuk kontras tajam di biru-oranye */
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
            /* FIX: Shadow teks agar readable di shimmer */
            z-index: 100000;
            /* FIX: Lebih tinggi dari overlay */
        }

        .loading-logo {
            width: 120px;
            /* FIX: Sedikit lebih besar untuk visibility */
            height: 120px;
            margin: 0 auto 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            overflow: hidden;
            background: rgba(255, 255, 255, 0.95);
            /* FIX: Lebih opaque untuk kontras */
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
            /* FIX: Shadow lebih tebal */
            animation: logoRotate 2s ease-in-out infinite alternate;
            /* FIX: Alternate (bolak-balik) untuk dinamis */
            border: 3px solid rgba(255, 255, 255, 0.6);
            /* FIX: Border lebih tebal */
            /* FIX: Glow match gradient untuk "masuk" */
            box-shadow: 0 0 20px rgba(70, 146, 249, 0.5), 0 0 30px rgba(255, 167, 0, 0.3);
            /* Glow biru + oren */
        }

        .logo-image {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: inherit;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.2));
            /* FIX: Shadow gambar untuk depth */
        }

        .loading-bars {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin: 2rem 0;
        }

        .loading-bar {
            width: 16px;
            /* FIX: Lebih besar untuk visibility */
            height: 16px;
            background: linear-gradient(45deg, #3b82f6, #f59e0b);
            /* FIX: Gradient mini biru-oranye di bar */
            border-radius: 50%;
            animation: loadingPulse 1.2s ease-in-out infinite;
            /* FIX: Speed lebih cepat */
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            /* FIX: Shadow lebih */
        }

        .loading-bar:nth-child(2) {
            animation-delay: 0.15s;
        }

        /* FIX: Delay lebih rapat */
        .loading-bar:nth-child(3) {
            animation-delay: 0.3s;
        }

        .loading-progress {
            width: 250px;
            /* FIX: Lebih lebar */
            height: 6px;
            /* FIX: Lebih tebal */
            background: rgba(255, 255, 255, 0.3);
            /* FIX: Background transparan putih untuk kontras shimmer */
            border-radius: 3px;
            overflow: hidden;
            margin: 0 auto;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.1);
            /* FIX: Inner shadow lebih */
        }

        .loading-progress-bar {
            width: 0%;
            height: 100%;
            background: linear-gradient(90deg, #3b82f6, #f59e0b, #ef4444);
            /* FIX: Tambah merah akhir untuk vibran */
            background-size: 200% 100%;
            animation: progressShimmer 1.5s ease-out infinite, progressLoad 3s ease-out forwards;
            /* FIX: Shimmer loop + load sekali */
            border-radius: 3px;
            box-shadow: 0 0 12px rgba(59, 130, 246, 0.6);
            /* FIX: Glow biru lebih kuat */
        }

        /* FIX: Shimmer progress lebih smooth */
        @keyframes progressShimmer {
            0% {
                background-position: -200% 0;
            }

            100% {
                background-position: 200% 0;
            }
        }

        /* Keyframes lain tetap sama, tapi tweak untuk vibran */
        @keyframes logoRotate {
            0% {
                transform: scale(0.8) rotate(-10deg);
                opacity: 0.8;
            }

            100% {
                transform: scale(1.1) rotate(10deg);
                opacity: 1;
            }
        }

        @keyframes loadingPulse {

            0%,
            100% {
                transform: scale(1);
                opacity: 0.6;
                box-shadow: 0 0 0 rgba(59, 130, 246, 0.4);
                /* FIX: Glow biru di pulse */
            }

            50% {
                transform: scale(1.3);
                opacity: 1;
                box-shadow: 0 0 20px rgba(245, 158, 11, 0.6);
                /* FIX: Glow oren di puncak */
            }
        }

        @keyframes progressLoad {
            0% {
                width: 0%;
            }

            100% {
                width: 100%;
            }
        }



        /* Dropdown Menu Settings - Pure Left-Click Toggle, Stack Normal (Fix: Logout Visible, Scroll Sidebar) */
        .sidebar {
            overflow-y: auto !important;
            /* FIX: Scroll internal sidebar saat dropdown push Logout (tidak hilang) */
            height: 100vh !important;
            /* Ganti h-screen ke 100vh untuk full height + scroll */
        }

        .sidebar-dropdown {
            position: relative !important;
            /* Parent relative untuk flow normal */
            overflow: visible !important;
            /* Visible agar submenu tidak clipped */
            margin-bottom: 1rem !important;
            /* FIX: Spasi ekstra bawah parent (agar Logout punya ruang, tidak nempel) */
        }

        .submenu {
            position: relative !important;
            /* FIX: Relative untuk stack di bawah Settings, push Logout ke bawah - no overlap/hilang */
            left: 0;
            top: auto;
            /* No top absolute */
            width: 100% !important;
            /* Full width parent */
            background: rgba(71, 85, 105, 0.8) !important;
            /* Slate-700/80 match sidebar */
            backdrop-blur: blur(4px) !important;
            /* Blur subtle */
            border-radius: 0 0 0.75rem 0.75rem !important;
            /* Rounded bawah saja (match stack) */
            margin-top: 0 !important;
            /* No margin top, langsung di bawah parent */
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15) !important;
            /* Shadow ringan untuk indent effect */
            z-index: 10 !important;
            /* Rendah, karena stack normal */
            overflow: hidden !important;
            /* Hidden saat collapse */
            transition: max-height 0.3s ease-in-out, opacity 0.3s ease-in-out !important;
            /* Smooth slide + fade */
            opacity: 0 !important;
            /* Hidden awal */
            display: block !important;
            /* Force block */
            list-style: none !important;
            /* No bullet */
            padding: 0 !important;
            /* No padding default */
            margin: 0 !important;
            /* No margin default */
            /* FIX: Indent submenu item (agar terlihat nested, tidak full width seperti parent) */
            padding-left: 2rem !important;
            /* Indent 2rem ke kanan untuk rapi */
        }

        /* Open state: Slide down & fade in (stack push Logout, sidebar scroll) */
        .submenu.open {
            max-height: 200px !important;
            /* Tinggi max kecil agar tidak panjang & tutup Logout */
            opacity: 1 !important;
            overflow-y: auto !important;
            /* Scroll jika item banyak */
            padding: 0.5rem 0 !important;
            /* Padding internal saat open */
            background: rgba(71, 85, 105, 0.9) !important;
            /* Sedikit lebih opaque saat open */
        }

        /* Arrow rotate: Dari down ke up saat open (hanya on click) */
        #settings-arrow {
            transition: transform 0.3s ease-in-out !important;
        }

        .submenu.open+#settings-arrow,
        .sidebar-dropdown .open+#settings-arrow {
            /* Selector spesifik untuk arrow */
            transform: rotate(180deg) !important;
            /* Rotate 180° untuk ↑ */
        }

        /* Hover submenu item (tetap open saat hover) */
        .submenu li:hover {
            background: rgba(51, 65, 85, 0.6) !important;
            /* Slate-600/60 subtle, lebih visible di indent */
            border-radius: 0.5rem !important;
            /* Rounded item untuk rapi */
        }

        /* FIX: Keep open saat mouse di submenu */
        .submenu:hover,
        .submenu.open:hover {
            opacity: 1 !important;
            max-height: 200px !important;
            /* Force tetap open */
        }

        /* Responsive: Mobile - Sama seperti desktop (stack normal, sidebar scroll) */
        @media (max-width: 1023px) {
            .submenu {
                padding-left: 1.5rem !important;
                /* Indent lebih kecil di mobile */
                border-radius: 0 0 0.5rem 0.5rem !important;
                /* Rounded lebih kecil */
            }

            .submenu.open {
                max-height: 300px !important;
                /* Lebih tinggi di mobile jika perlu scroll */
            }

            /* FIX: Sidebar scroll lebih smooth di mobile */
            #sidebar {
                -webkit-overflow-scrolling: touch !important;
                /* Smooth scroll iOS */
            }
        }

        /* Pastikan Logout visible & clickable (no overlap, spasi atas) */
        .sidebar-item[href="../logout.php"] {
            margin-top: 0.5rem !important;
            /* Spasi ekstra di atas Logout */
            z-index: 20 !important;
            /* Tinggi agar tidak tertutup shadow submenu */
            pointer-events: auto !important;
            /* Pastikan clickable */
        }



        /* FIX Arrow Rotate: Target parent saat submenu open */
        .settings-parent .fa-chevron-down {
            transition: transform 0.3s ease-in-out !important;
        }

        /* Saat dropdown open, rotate arrow di parent */
        #settings-dropdown.open~.settings-parent .fa-chevron-down,
        .settings-parent:has(#settings-dropdown.open) .fa-chevron-down {
            transform: rotate(180deg) !important;
            /* Modern CSS :has() untuk support browser baru, fallback di JS */
        }

        /* FIX: Pastikan submenu tidak clipped di sidebar */
        .sidebar-dropdown {
            overflow: visible !important;
            /* Allow submenu to expand */
        }

        .submenu-item {
            pointer-events: auto !important;
            /* Pastikan li clickable */
        }

        .form-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .status-badge:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .role-super_admin,
        .role-admin {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        /* Green untuk admin */
        .role-user {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #93c5fd;
        }

        /* Blue untuk user */
        .status-active {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .status-inactive {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .table-row {
            transition: all 0.3s ease;
        }

        .table-row:hover {
            background: #f8fafc;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        /* Smooth modal open/close */
        #editModal.opacity-100 {
            opacity: 1 !important;
            pointer-events: auto !important;
        }

        #editModal .transform.scale-100 {
            transform: scale(1) !important;
        }

        #editModal .transform.scale-95 {
            transform: scale(0.95) !important;
        }
    </style>
</head>

<body class="bg-gray-50">
    <!-- Loading Animation -->
    <div id="loadingOverlay" class="loading-overlay">
        <div class="loading-content">
            <div class="loading-logo">
                <img src="logo_form/logo ckt fix.png" alt="Logo Perusahaan" class="logo-image">
                <!-- Path ke logo lokal Anda -->
            </div>
            <h1 class="text-2xl md:text-3xl font-bold mb-2">PT CIPTA KARYA TECHNOLOGY</h1>
            <!-- Ganti nama perusahaan jika perlu -->
            <p class="text-gray-300 mb-4">Loading Sistem ASSET...</p> <!-- Ganti teks deskripsi jika perlu -->
            <div class="loading-bars">
                <div class="loading-bar"></div>
                <div class="loading-bar"></div>
                <div class="loading-bar"></div>
            </div>
            <div class="loading-progress">
                <div class="loading-progress-bar"></div>
            </div>
        </div>
    </div>

    <?php
    // Include sidebar + navbar admin
    $activePage = 'add_akun';
    require_once __DIR__ . '/sidebar_admin.php';
    ?>

    <div id="main-content-wrapper" class="lg:ml-60 transition-all duration-300 ease-in-out">
        <script>
            (function () {
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
                window.addEventListener('sidebarToggled', function (e) { applyState(); });
                window.addEventListener('resize', function () { applyState(); });
            })();
        </script>
        <main class="mt-16 min-h-screen bg-slate-50 p-6 pt-8">
            <div class="mb-8 animate-fade-in">
                <h1 class="text-3xl font-bold text-gray-900 mb-2 mt-16">Tambah Akun Pengguna</h1>
                <p class="text-gray-600">Kelola data akses karyawan: tambah baru atau lihat daftar existing.</p>

                <?php if (isset($success_msg)): ?>
                    <div
                        class="mt-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg mb-4 animate-bounce-in">
                        <?php echo $success_msg; ?>
                    </div>
                    <?php
                endif; ?>
                <?php if (isset($error_msg)): ?>
                    <div class="mt-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg mb-4">
                        <?php echo $error_msg; ?>
                    </div>
                    <?php
                endif; ?>
            </div>

            <!-- Form Tambah Akun (Responsive Grid, Gradient Button) -->
            <div class="form-card">
                <h2 class="text-xl font-semibold mb-4 text-gray-800 flex items-center"><i
                        class="fas fa-user-plus mr-2 text-green-500"></i>Tambah Akun Baru</h2>
                <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Nama Lengkap *</label>
                        <div class="relative">
                            <input type="text" name="Nama_Lengkap" id="Nama_Lengkap" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                placeholder="e.g., John Doe">
                            <div id="nama_feedback"
                                class="mt-1 text-sm flex items-center space-x-1 min-h-[20px] transition-all duration-200">
                            </div>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">ID Karyawan *</label>
                        <div class="relative">
                            <input type="text" name="username" id="username" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                placeholder="e.g., KRY-001">
                            <div id="username_feedback"
                                class="mt-1 text-sm flex items-center space-x-1 min-h-[20px] transition-all duration-200">
                            </div>
                        </div>
                    </div>
                    <!-- Jabatan - Searchable Combo -->
                    <div class="combo-field relative" data-combo="jabatan">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Jabatan *</label>
                        <div class="relative">
                            <input type="text" name="Jabatan_Level" id="combo-jabatan" required autocomplete="off"
                                class="w-full px-3 py-2 pr-8 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition-all duration-200"
                                placeholder="Ketik atau pilih jabatan...">
                            <button type="button"
                                class="combo-toggle absolute inset-y-0 right-0 flex items-center pr-2.5 text-gray-400 hover:text-gray-600 transition-colors"
                                tabindex="-1">
                                <i class="fas fa-chevron-down text-xs transition-transform duration-200"></i>
                            </button>
                        </div>
                        <ul
                            class="combo-dropdown hidden absolute z-50 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                            <?php foreach ($masterJabatan as $val): ?>
                                <li class="combo-option px-3 py-2 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-700 cursor-pointer transition-colors duration-150"
                                    data-value="<?php echo htmlspecialchars($val); ?>">
                                    <i
                                        class="fas fa-briefcase text-xs text-gray-400 mr-2"></i><?php echo htmlspecialchars($val); ?>
                                </li>
                                <?php
                            endforeach; ?>
                            <li class="combo-empty hidden px-3 py-2 text-sm text-gray-400 italic"><i
                                    class="fas fa-plus-circle text-xs mr-1"></i>Tekan Enter untuk tambah baru</li>
                        </ul>
                    </div>
                    <!-- Divisi - Searchable Combo -->
                    <div class="combo-field relative" data-combo="divisi">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Divisi *</label>
                        <div class="relative">
                            <input type="text" name="Divisi" id="combo-divisi" required autocomplete="off"
                                class="w-full px-3 py-2 pr-8 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition-all duration-200"
                                placeholder="Ketik atau pilih divisi...">
                            <button type="button"
                                class="combo-toggle absolute inset-y-0 right-0 flex items-center pr-2.5 text-gray-400 hover:text-gray-600 transition-colors"
                                tabindex="-1">
                                <i class="fas fa-chevron-down text-xs transition-transform duration-200"></i>
                            </button>
                        </div>
                        <ul
                            class="combo-dropdown hidden absolute z-50 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                            <?php foreach ($masterDivisi as $val): ?>
                                <li class="combo-option px-3 py-2 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-700 cursor-pointer transition-colors duration-150"
                                    data-value="<?php echo htmlspecialchars($val); ?>">
                                    <i
                                        class="fas fa-building text-xs text-gray-400 mr-2"></i><?php echo htmlspecialchars($val); ?>
                                </li>
                                <?php
                            endforeach; ?>
                            <li class="combo-empty hidden px-3 py-2 text-sm text-gray-400 italic"><i
                                    class="fas fa-plus-circle text-xs mr-1"></i>Tekan Enter untuk tambah baru</li>
                        </ul>
                    </div>
                    <!-- Region - Searchable Combo -->
                    <div class="combo-field relative" data-combo="region">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Region *</label>
                        <div class="relative">
                            <input type="text" name="Region" id="combo-region" required autocomplete="off"
                                class="w-full px-3 py-2 pr-8 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition-all duration-200"
                                placeholder="Ketik atau pilih region...">
                            <button type="button"
                                class="combo-toggle absolute inset-y-0 right-0 flex items-center pr-2.5 text-gray-400 hover:text-gray-600 transition-colors"
                                tabindex="-1">
                                <i class="fas fa-chevron-down text-xs transition-transform duration-200"></i>
                            </button>
                        </div>
                        <ul
                            class="combo-dropdown hidden absolute z-50 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                            <?php foreach ($masterRegion as $val): ?>
                                <li class="combo-option px-3 py-2 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-700 cursor-pointer transition-colors duration-150"
                                    data-value="<?php echo htmlspecialchars($val); ?>">
                                    <i
                                        class="fas fa-map-marker-alt text-xs text-gray-400 mr-2"></i><?php echo htmlspecialchars($val); ?>
                                </li>
                                <?php
                            endforeach; ?>
                            <li class="combo-empty hidden px-3 py-2 text-sm text-gray-400 italic"><i
                                    class="fas fa-plus-circle text-xs mr-1"></i>Tekan Enter untuk tambah baru</li>
                        </ul>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Role *</label>
                        <select name="role" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Pilih Role</option>
                            <option value="super_admin">Super Admin</option>
                            <option value="admin">Admin</option>
                            <option value="user">User </option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status Akun *</label>
                        <select name="Status_Akun" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Pilih Status Akun</option>
                            <option value="Aktif">Aktif</option>
                            <option value="Tidak Aktif">Tidak Aktif</option>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                        <input type="Email" name="Email" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            placeholder="example@company.com">
                    </div>
                    <div class="md:col-span-2 relative">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Password *</label>
                        <div class="relative">
                            <input type="password" name="password" id="password" required
                                class="w-full px-3 py-2 pl-4 pr-10 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                placeholder="password aman minimal 8 karakter">
                            <button type="button" id="togglePassword"
                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-500 hover:text-gray-700">
                                <i class="fas fa-eye-slash" id="eyeIcon"></i>
                            </button>
                        </div>
                    </div>
                    <div class="md:col-span-2 flex justify-end">
                        <button type="submit"
                            class="bg-gradient-to-r from-orange-500 to-orange-600 text-white py-3 px-6 rounded-lg hover:from-orange-600 hover:to-orange-700 transition-all duration-300 font-semibold shadow-lg flex items-center justify-center min-w-[180px]">
                            <i class="fas fa-plus mr-2"></i>Tambah Akun
                        </button>
                    </div>
                </form>
            </div>

            <!-- Tabel Data Akses (AJAX Pagination - Activity Log Pattern) -->
            <div class="form-card">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
                    <h2 class="text-xl font-semibold text-gray-800 flex items-center"><i
                            class="fas fa-users mr-2 text-blue-500"></i>Daftar Data Akses Akun</h2>
                    <div class="flex flex-wrap items-center gap-2">
                        <!-- Search -->
                        <div class="relative">
                            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                            <input type="text" id="users-search" placeholder="Cari nama, ID, divisi..."
                                class="pl-9 pr-3 py-2 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-orange-300 w-56">
                        </div>
                        <!-- Per page -->
                        <select id="users-per-page"
                            class="text-sm border border-gray-200 rounded-xl px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-300">
                            <option value="10">10 / halaman</option>
                            <option value="15">15 / halaman</option>
                            <option value="25">25 / halaman</option>
                            <option value="50">50 / halaman</option>
                        </select>
                        <!-- Reset -->
                        <button id="users-reset"
                            class="flex items-center justify-center px-3 py-2 text-sm text-gray-500 hover:text-gray-700 border border-gray-200 rounded-xl hover:bg-gray-50 transition">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>

                <!-- Info row -->
                <p class="text-sm text-gray-500 mb-3" id="users-info">Memuat data...</p>

                <div class="overflow-x-auto rounded-lg">
                    <table class="w-full table-auto bg-white divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Create Time</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Create By</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Nama Lengkap</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    ID Karyawan</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Jabatan</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Divisi</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Region</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Role</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Password</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Status</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions</th>
                            </tr>
                        </thead>
                        <tbody id="users-tbody" class="bg-white divide-y divide-gray-200">
                            <tr>
                                <td colspan="11" class="px-4 py-10 text-center text-gray-400 text-sm">
                                    <i class="fas fa-circle-notch fa-spin mr-2"></i>Memuat...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination placeholder -->
                <div id="users-pagination"></div>
            </div>

            <!-- Modal Edit Akun (Baru: Tambahkan di sini) -->
            <div id="editModal"
                class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full opacity-0 pointer-events-none z-50 transition-opacity duration-300">
                <div
                    class="relative top-20 mx-auto p-5 border w-11/12 md:w-96 shadow-lg rounded-md bg-white transform scale-95 transition-transform duration-300">
                    <div class="mt-3">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Edit Akun</h3>
                        <form id="editForm" class="grid grid-cols-1 gap-4">
                            <input type="hidden" id="editId" name="id">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Lengkap *</label>
                                <input type="text" id="editNama_Lengkap" name="Nama_Lengkap" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">ID Karyawan *</label>
                                <input type="text" id="editUsername" name="username" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Jabatan *</label>
                                <input type="text" id="editJabatan_Level" name="Jabatan_Level" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Divisi *</label>
                                <input type="text" id="editDivisi" name="Divisi" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Region *</label>
                                <input type="text" id="editRegion" name="Region" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Role *</label>
                                <select id="editRole" name="role" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="super_admin">Super Admin</option>
                                    <option value="admin">Admin</option>
                                    <option value="user">User</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                                <input type="email" id="editEmail" name="Email" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Status Akun *</label>
                                <select id="editStatus_Akun" name="Status_Akun" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="Aktif">Aktif</option>
                                    <option value="Tidak Aktif">Tidak Aktif</option>
                                </select>
                            </div>
                            <div class="relative">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Password Baru
                                    (Opsional)</label>
                                <div class="relative">
                                    <input type="password" id="editPassword" name="password"
                                        class="w-full px-3 py-2 pl-4 pr-10 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        placeholder="Kosongkan jika tidak ingin ubah">
                                    <button type="button" id="toggleEditPassword"
                                        class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-500 hover:text-gray-700">
                                        <i class="fas fa-eye-slash" id="editEyeIcon"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="flex justify-end space-x-2">
                                <button type="button" id="closeModal"
                                    class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400">Batal</button>
                                <button type="submit"
                                    class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">Simpan</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
    </div>





    <script>
        // Fixed Sidebar Toggle Functionality
        document.addEventListener('DOMContentLoaded', function () {
            const hamburgerBtn = document.getElementById('hamburger-btn');
            const closeSidebarBtn = document.getElementById('close-sidebar');
            const sidebar = document.getElementById('sidebar');
            const mobileOverlay = document.getElementById('mobile-overlay');
            const hamburgerIcon = document.getElementById('hamburger-icon');

            let sidebarOpen = false;

            // Toggle sidebar
            function toggleSidebar() {
                sidebarOpen = !sidebarOpen;
                updateSidebar();
            }

            // Update sidebar state
            function updateSidebar() {
                if (sidebarOpen) {
                    sidebar.classList.add('open');
                    mobileOverlay.classList.add('active');
                    hamburgerIcon.classList.remove('fa-bars');
                    hamburgerIcon.classList.add('fa-times');
                } else {
                    sidebar.classList.remove('open');
                    mobileOverlay.classList.remove('active');
                    hamburgerIcon.classList.remove('fa-times');
                    hamburgerIcon.classList.add('fa-bars');
                }
            }

            // Close sidebar
            function closeSidebar() {
                sidebarOpen = false;
                updateSidebar();
            }

            // Event listeners
            hamburgerBtn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                toggleSidebar();
            });

            closeSidebarBtn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                closeSidebar();
            });

            mobileOverlay.addEventListener('click', function (e) {
                e.preventDefault();
                closeSidebar();
            });

            // Close sidebar when clicking on sidebar links (mobile only)
            const sidebarLinks = sidebar.querySelectorAll('a');
            sidebarLinks.forEach(link => {
                link.addEventListener('click', function () {
                    if (window.innerWidth < 1024) {
                        closeSidebar();
                    }
                });
            });

            // Handle window resize
            window.addEventListener('resize', function () {
                if (window.innerWidth >= 1024) {
                    closeSidebar();
                }
            });

            // Initialize charts

        });




        // Global state untuk dropdown (FIX: Let di global scope, defined awal)
        let dropdownOpenState = false;

        // Fixed Sidebar Toggle Functionality + Dropdown (Semua di DOMContentLoaded untuk timing aman)
        document.addEventListener('DOMContentLoaded', function () {
            console.log('DEBUG: DOM loaded - Initializing sidebar, charts, and dropdown');

            const hamburgerBtn = document.getElementById('hamburger-btn');
            const closeSidebarBtn = document.getElementById('close-sidebar');
            const sidebar = document.getElementById('sidebar');
            const mobileOverlay = document.getElementById('mobile-overlay');
            const hamburgerIcon = document.getElementById('hamburger-icon');

            let sidebarOpen = false;

            // Toggle sidebar (tetap sama)
            function toggleSidebar() {
                sidebarOpen = !sidebarOpen;
                updateSidebar();
            }

            function updateSidebar() {
                if (sidebarOpen) {
                    sidebar.classList.add('open');
                    mobileOverlay.classList.add('active');
                    hamburgerIcon.classList.remove('fa-bars');
                    hamburgerIcon.classList.add('fa-times');
                } else {
                    sidebar.classList.remove('open');
                    mobileOverlay.classList.remove('active');
                    hamburgerIcon.classList.remove('fa-times');
                    hamburgerIcon.classList.add('fa-bars');
                }
            }

            function closeSidebar() {
                sidebarOpen = false;
                updateSidebar();
            }

            // Event listeners sidebar (tetap sama, dengan null check)
            if (hamburgerBtn) {
                hamburgerBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    toggleSidebar();
                });
            }

            if (closeSidebarBtn) {
                closeSidebarBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    closeSidebar();
                });
            }

            if (mobileOverlay) {
                mobileOverlay.addEventListener('click', function (e) {
                    e.preventDefault();
                    closeSidebar();
                });
            }

            if (sidebar) {
                const sidebarLinks = sidebar.querySelectorAll('a');
                sidebarLinks.forEach(link => {
                    link.addEventListener('click', function () {
                        if (window.innerWidth < 1024) {
                            closeSidebar();
                        }
                    });
                });
            }

            window.addEventListener('resize', function () {
                if (window.innerWidth >= 1024) {
                    closeSidebar();
                }
            });

            // FIX DROPDOWN: Pure JS Event Listeners (Sekarang di DOMContentLoaded, dengan full debug)
            const settingsParent = document.querySelector('.settings-parent');
            const submenu = document.getElementById('settings-dropdown');
            const arrow = document.getElementById('settings-arrow');

            // Otomatis buka dropdown jika di halaman Settings
            if (<?php echo (isset($is_settings_page) && $is_settings_page) ? 'true' : 'false'; ?>) {
                dropdownOpenState = true;
                if (submenu && arrow) {
                    submenu.classList.add('open');
                    submenu.style.maxHeight = '200px';
                    submenu.style.opacity = '1';
                    arrow.style.transform = 'rotate(180deg)';
                }
            }
            console.log('DEBUG: Dropdown elements found:', {
                parent: !!settingsParent,
                submenu: !!submenu,
                arrow: !!arrow
            });

            if (settingsParent && submenu && arrow) {
                console.log('DEBUG: Attaching dropdown event listeners');

                // Event untuk parent: Toggle on click (dengan debounce)
                settingsParent.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();

                    // Safeguard: Prevent multiple rapid clicks
                    if (settingsParent.hasAttribute('data-clicking')) {
                        console.log('DEBUG: Click ignored (debounce active)');
                        return;
                    }
                    settingsParent.setAttribute('data-clicking', 'true');
                    setTimeout(() => settingsParent.removeAttribute('data-clicking'), 300);

                    console.log('DEBUG: Settings clicked - current state:', dropdownOpenState ? 'open (will close)' : 'closed (will open)');
                    toggleDropdown();
                });

                // Event delegation untuk submenu items
                submenu.addEventListener('click', function (e) {
                    const item = e.target.closest('.submenu-item');
                    if (item) {
                        e.stopPropagation();
                        const url = item.dataset.url;
                        console.log('DEBUG: Submenu item clicked:', url);
                        selectSubmenuItem(url);
                    }
                });
            } else {
                console.error('ERROR: Settings elements not found! Check HTML classes/IDs. Parent:', settingsParent, 'Submenu:', submenu, 'Arrow:', arrow);
            }

            // Fungsi toggle (logic clean)
            function toggleDropdown() {
                console.log('DEBUG: toggleDropdown called - state before:', dropdownOpenState);
                if (dropdownOpenState) {
                    // Close
                    submenu.classList.remove('open');
                    submenu.style.maxHeight = '0px';
                    submenu.style.opacity = '0';
                    submenu.style.overflow = 'hidden';
                    arrow.style.transform = 'rotate(0deg)';
                    dropdownOpenState = false;
                    console.log('DEBUG: Dropdown closed - state now:', dropdownOpenState);
                } else {
                    // Open
                    submenu.classList.add('open');
                    submenu.style.maxHeight = '200px';
                    submenu.style.opacity = '1';
                    submenu.style.overflow = 'visible';
                    arrow.style.transform = 'rotate(180deg)';
                    dropdownOpenState = true;
                    console.log('DEBUG: Dropdown opened - state now:', dropdownOpenState);
                }
            }

            // Fungsi select item
            function selectSubmenuItem(url) {
                console.log('DEBUG: selectSubmenuItem called for:', url);
                if (dropdownOpenState) {
                    toggleDropdown();
                }
                setTimeout(() => {
                    if (url && url !== '#') {
                        console.log('DEBUG: Redirecting to:', url);
                        window.location.href = url;
                    } else {
                        console.log('DEBUG: No URL, staying here');
                    }
                }, 300);
            }



            // Initialize charts (dengan null check)

        });

        // ========== AJAX USERS TABLE (Activity Log Pattern) ==========
        (function () {
            let currentPage = 1;
            let isLoading = false;

            const searchEl = document.getElementById('users-search');
            const perPageEl = document.getElementById('users-per-page');
            const tbodyEl = document.getElementById('users-tbody');
            const paginEl = document.getElementById('users-pagination');
            const infoEl = document.getElementById('users-info');

            window.fetchUsers = function (page) {
                if (isLoading) return;
                isLoading = true;
                currentPage = page || 1;

                const params = new URLSearchParams({
                    action: 'ajax_users',
                    page: currentPage,
                    per_page: perPageEl.value,
                    search: searchEl.value.trim(),
                });

                tbodyEl.innerHTML = '<tr><td colspan="11" class="px-4 py-10 text-center text-gray-400"><i class="fas fa-circle-notch fa-spin mr-2"></i>Memuat...</td></tr>';

                fetch('?' + params.toString())
                    .then(r => r.json())
                    .then(data => {
                        tbodyEl.innerHTML = data.tbody_html || '';
                        paginEl.innerHTML = data.pagination_html || '';
                        const from = data.showing_from || 0;
                        const to = data.showing_to || 0;
                        const tot = data.total_records || 0;
                        infoEl.textContent = tot > 0
                            ? `Menampilkan ${from} - ${to} dari ${tot.toLocaleString('id')} akun`
                            : 'Tidak ada data ditemukan';

                        // Re-attach edit/delete handlers setelah render AJAX
                        tbodyEl.querySelectorAll('.ajax-edit-btn').forEach(btn => {
                            btn.addEventListener('click', function () {
                                const row = this.closest('tr');
                                openEditModal(row);
                            });
                        });
                        tbodyEl.querySelectorAll('.ajax-delete-btn').forEach(btn => {
                            btn.addEventListener('click', function () {
                                handleDelete(this.dataset.id);
                            });
                        });
                        isLoading = false;
                    })
                    .catch(() => {
                        tbodyEl.innerHTML = '<tr><td colspan="11" class="px-4 py-10 text-center text-red-400"><i class="fas fa-exclamation-circle mr-2"></i>Gagal memuat data</td></tr>';
                        isLoading = false;
                    });
            };

            // Search on Enter
            searchEl.addEventListener('keydown', e => { if (e.key === 'Enter') fetchUsers(1); });
            // Per-page change
            perPageEl.addEventListener('change', () => fetchUsers(1));
            // Reset
            document.getElementById('users-reset').addEventListener('click', () => {
                searchEl.value = '';
                perPageEl.value = '10';
                fetchUsers(1);
            });

            // Initial load
            fetchUsers(1);
        })();

        // ========== Edit Modal Helpers ==========
        const editModal = document.getElementById('editModal');
        const closeModal = document.getElementById('closeModal');
        const editForm = document.getElementById('editForm');

        function openEditModal(row) {
            document.getElementById('editId').value = row.dataset.id;
            document.getElementById('editNama_Lengkap').value = row.dataset.nama;
            document.getElementById('editUsername').value = row.dataset.username;
            document.getElementById('editJabatan_Level').value = row.dataset.jabatan;
            document.getElementById('editDivisi').value = row.dataset.divisi;
            document.getElementById('editRegion').value = row.dataset.region || '';
            document.getElementById('editRole').value = row.dataset.role;
            document.getElementById('editEmail').value = row.dataset.email;
            document.getElementById('editStatus_Akun').value = row.dataset.status;
            document.getElementById('editPassword').value = '';

            editModal.classList.remove('hidden', 'opacity-0', 'pointer-events-none');
            void editModal.offsetWidth;
            const modalContent = editModal.querySelector('div.relative');
            editModal.classList.add('opacity-100');
            if (modalContent) {
                modalContent.classList.remove('scale-95');
                modalContent.classList.add('scale-100');
            }
            setTimeout(() => { document.getElementById('editNama_Lengkap')?.focus(); }, 300);
        }

        closeModal.addEventListener('click', function () {
            const modalContent = editModal.querySelector('div.relative');
            // Animasi keluar
            editModal.classList.remove('opacity-100');
            editModal.classList.add('opacity-0');
            modalContent.classList.remove('scale-100');
            modalContent.classList.add('scale-95');

            // Setelah animasi selesai, sembunyikan
            setTimeout(() => {
                editModal.classList.add('hidden', 'pointer-events-none');
            }, 300); // Sesuaikan dengan duration animasi
        });

        // Submit Edit via AJAX (diperbaiki)
        editForm.addEventListener('submit', function (e) {
            e.preventDefault();

            // Validasi manual (opsional tapi membantu UX)
            const requiredFields = ['Nama_Lengkap', 'username', 'Jabatan_Level', 'Divisi', 'Region', 'role', 'Email', 'Status_Akun'];
            let isValid = true;
            requiredFields.forEach(field => {
                const el = document.getElementById('edit' + field.charAt(0).toUpperCase() + field.slice(1));
                if (!el || !el.value.trim()) {
                    isValid = false;
                }
            });

            if (!isValid) {
                Swal.fire({
                    title: 'Form Belum Lengkap!',
                    text: 'Pastikan semua field wajib diisi.',
                    icon: 'warning',
                    confirmButtonText: 'OK'
                });
                return;
            }

            const formData = new FormData(this);
            formData.append('action', 'edit');

            // Tampilkan loading di tombol
            const submitBtn = editForm.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';
            submitBtn.disabled = true;

            fetch('', {
                method: 'POST',
                body: formData
            })
                .then(response => {
                    // Pastikan respons adalah JSON
                    if (!response.ok) {
                        throw new Error('Jaringan gagal: ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            title: 'Berhasil!',
                            text: data.message,
                            icon: 'success',
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            editModal.classList.add('hidden');
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            title: 'Gagal!',
                            text: data.message || 'Terjadi kesalahan saat menyimpan.',
                            icon: 'error'
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        title: 'Error Jaringan!',
                        text: 'Gagal menghubungi server. Coba lagi nanti.',
                        icon: 'error'
                    });
                })
                .finally(() => {
                    // Kembalikan tombol ke kondisi semula
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                });
        });

        // Handle Delete via AJAX (reusable function)
        function handleDelete(id) {
            Swal.fire({
                title: 'Yakin hapus akun ini?',
                text: 'Data akan dihapus permanen dan tidak bisa dikembalikan!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal',
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('action', 'delete');
                    formData.append('id', id);
                    fetch('', { method: 'POST', body: formData })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire({ title: 'Terhapus!', text: data.message, icon: 'success', timer: 2000, showConfirmButton: false })
                                    .then(() => fetchUsers(1));
                            } else {
                                Swal.fire({ title: 'Gagal!', text: data.message, icon: 'error' });
                            }
                        })
                        .catch(() => Swal.fire({ title: 'Error!', text: 'Terjadi kesalahan jaringan.', icon: 'error' }));
                }
            });
        }


        // Loading Animation (tetap sama)
        window.addEventListener('load', function () {
            console.log('DEBUG: Window loaded - Hiding loading overlay');
            setTimeout(function () {
                const loadingOverlay = document.getElementById('loadingOverlay');
                if (loadingOverlay) {
                    loadingOverlay.style.opacity = '0';
                    setTimeout(function () {
                        loadingOverlay.style.display = 'none';
                    }, 500);
                }
            }, 2000);
        });




        // Event listener untuk semua item submenu
        document.addEventListener('DOMContentLoaded', function () {

            const submenuItems = document.querySelectorAll('.submenu-item');

            submenuItems.forEach(function (item) {
                item.addEventListener('click', function (e) {
                    e.preventDefault();  // Cegah perilaku default jika ada link lain

                    const targetUrl = this.getAttribute('data-url');
                    if (targetUrl) {
                        // Opsi 1: Buka di tab/same window yang sama (ganti window.open jika ingin tab baru)
                        window.location.href = targetUrl;  // Atau window.location.replace(targetUrl) untuk replace history

                        // Opsi 2: Jika ingin buka di tab baru
                        // window.open(targetUrl, '_blank');
                    }
                });
            });
        });


        // Validasi duplikat Nama_Lengkap
        const namaInput = document.getElementById('Nama_Lengkap');
        const namaFeedback = document.getElementById('nama_feedback');
        let namaDebounceTimer = null;
        let isNamaAvailable = false;

        if (namaInput && namaFeedback) {
            namaInput.addEventListener('input', function () {
                const value = this.value.trim();
                isNamaAvailable = false;

                // Reset feedback
                namaFeedback.innerHTML = '';
                namaFeedback.className = 'mt-1 text-sm flex items-center space-x-1 min-h-[20px] transition-all duration-200';
                this.classList.remove('border-red-500', 'border-green-500');

                if (value.length < 2) {
                    return;
                }

                // Debounce
                clearTimeout(namaDebounceTimer);
                namaDebounceTimer = setTimeout(() => {
                    fetch('', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action: 'check_duplicate',
                            field: 'Nama_Lengkap',
                            value: value
                        })
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                if (data.exists) {
                                    namaFeedback.innerHTML = `
                            <i class="fas fa-exclamation-circle text-red-500"></i>
                            <span class="text-red-600 font-medium">Nama ini sudah terdaftar!</span>
                        `;
                                    this.classList.add('border-red-500');
                                    isNamaAvailable = false;
                                } else {
                                    namaFeedback.innerHTML = `
                            <i class="fas fa-check-circle text-green-500"></i>
                            <span class="text-green-600">Aman bisa daftarkan ✅</span>
                        `;
                                    this.classList.add('border-green-500');
                                    isNamaAvailable = true;
                                }
                            } else {
                                namaFeedback.innerHTML = `<span class="text-yellow-600">Gagal memeriksa...</span>`;
                                isNamaAvailable = false;
                            }
                        })
                        .catch(err => {
                            console.error('Nama duplicate check error:', err);
                            namaFeedback.innerHTML = `<span class="text-yellow-600">Error koneksi</span>`;
                            isNamaAvailable = false;
                        });
                }, 500);
            });
        }





        // Validasi duplikat untuk ID Karyawan (username)
        const usernameInput = document.getElementById('username');
        const usernameFeedback = document.getElementById('username_feedback');
        let usernameDebounceTimer = null;
        let isUsernameAvailable = false;

        if (usernameInput && usernameFeedback) {
            usernameInput.addEventListener('input', function () {
                const value = this.value.trim();
                isUsernameAvailable = false;

                // Reset feedback
                usernameFeedback.innerHTML = '';
                usernameFeedback.className = 'mt-1 text-sm flex items-center space-x-1 min-h-[20px] transition-all duration-200';
                this.classList.remove('border-red-500', 'border-green-500');

                if (value.length < 2) return;

                clearTimeout(usernameDebounceTimer);
                usernameDebounceTimer = setTimeout(() => {
                    fetch('', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action: 'check_duplicate',
                            field: 'username',
                            value: value
                        })
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                if (data.exists) {
                                    usernameFeedback.innerHTML = `
                            <i class="fas fa-exclamation-circle text-red-500"></i>
                            <span class="text-red-600 font-medium">ID ini sudah terdaftar!</span>
                        `;
                                    this.classList.add('border-red-500');
                                    isUsernameAvailable = false;
                                } else {
                                    usernameFeedback.innerHTML = `
                            <i class="fas fa-check-circle text-green-500"></i>
                            <span class="text-green-600">Aman bisa daftarkan ✅</span>
                        `;
                                    this.classList.add('border-green-500');
                                    isUsernameAvailable = true;
                                }
                            } else {
                                usernameFeedback.innerHTML = `<span class="text-yellow-600">Gagal memeriksa...</span>`;
                                isUsernameAvailable = false;
                            }
                        })
                        .catch(err => {
                            console.error('Username duplicate check error:', err);
                            usernameFeedback.innerHTML = `<span class="text-yellow-600">Error koneksi</span>`;
                            isUsernameAvailable = false;
                        });
                }, 500);
            });
        }

        // Tambahkan atribut data untuk status validasi
        if (namaInput) {
            namaInput.dataset.valid = 'unknown'; // 'unknown', 'valid', 'invalid'
        }
        if (usernameInput) {
            usernameInput.dataset.valid = 'unknown';
        }

        // Perbarui handler input untuk set atribut
        // (Sudah ada di kode-mu, pastikan di akhir callback tambahkan:)
        // → di dalam .then(data => { ... })
        //    if (data.exists) { ... input.dataset.valid = 'invalid'; }
        //    else { ... input.dataset.valid = 'valid'; }

        // Submit handler yang benar
        document.querySelector('form[method="POST"]').addEventListener('submit', function (e) {
            const namaVal = namaInput?.value.trim();
            const usernameVal = usernameInput?.value.trim();

            const namaStatus = namaInput?.dataset.valid;
            const usernameStatus = usernameInput?.dataset.valid;

            // Jika masih 'unknown', berarti belum selesai cek → biarkan kirim (server akan validasi)
            // Tapi jika sudah 'invalid', blokir
            if (namaStatus === 'invalid' || usernameStatus === 'invalid') {
                e.preventDefault();
                Swal.fire({
                    title: 'Data Sudah Terdaftar!',
                    text: 'Nama Lengkap atau ID Karyawan sudah digunakan. Silakan gunakan data lain.',
                    icon: 'warning',
                    confirmButtonText: 'OK'
                });
                if (namaStatus === 'invalid') namaInput?.focus();
                else usernameInput?.focus();
                return;
            }

            // Opsional: Jika masih 'unknown', tampilkan loading
            // Tapi biarkan form tetap submit — validasi akhir tetap di PHP
        });

        // Toggle password visibility - Tambah Akun
        const togglePasswordBtn = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        const eyeIcon = document.getElementById('eyeIcon');

        if (togglePasswordBtn && passwordInput && eyeIcon) {
            togglePasswordBtn.addEventListener('click', function () {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                eyeIcon.classList.toggle('fa-eye-slash', type === 'password');
                eyeIcon.classList.toggle('fa-eye', type === 'text');
            });
        }

        // Toggle password visibility - Edit Akun
        const toggleEditPasswordBtn = document.getElementById('toggleEditPassword');
        const editPasswordInput = document.getElementById('editPassword');
        const editEyeIcon = document.getElementById('editEyeIcon');

        if (toggleEditPasswordBtn && editPasswordInput && editEyeIcon) {
            toggleEditPasswordBtn.addEventListener('click', function () {
                const type = editPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                editPasswordInput.setAttribute('type', type);
                editEyeIcon.classList.toggle('fa-eye-slash', type === 'password');
                editEyeIcon.classList.toggle('fa-eye', type === 'text');
            });
        }

        // Tampilkan notifikasi berdasarkan URL parameter
        (function () {
            const urlParams = new URLSearchParams(window.location.search);

            // ✅ Sukses: akun ditambahkan/edit
            if (urlParams.has('success') && urlParams.get('success') === 'added') {
                window.history.replaceState({}, document.title, window.location.pathname);
                Swal.fire({
                    title: '✅ Berhasil!',
                    text: 'Akun baru berhasil ditambahkan.',
                    icon: 'success',
                    confirmButtonText: 'OK',
                    customClass: {
                        popup: 'animate__animated animate__fadeInUp animate__faster',
                        confirmButton: 'bg-gradient-to-r from-orange-500 to-orange-600 text-white px-6 py-2 rounded-lg shadow-md hover:from-orange-600 hover:to-orange-700'
                    },
                    buttonsStyling: false
                });
            }

            // ✅ Error: gagal tambah/edit
            const errorMsg = urlParams.get('error');
            if (errorMsg) {
                window.history.replaceState({}, document.title, window.location.pathname);
                Swal.fire({
                    title: '❌ Gagal!',
                    text: errorMsg,
                    icon: 'error',
                    confirmButtonText: 'OK',
                    customClass: {
                        popup: 'animate__animated animate__fadeInUp animate__faster'
                    }
                });
            }
        })();

        // ========== COMBO INPUT (Search + Master Data) ==========
        (function () {
            document.querySelectorAll('.combo-field').forEach(function (field) {
                const input = field.querySelector('input[type="text"]');
                const dropdown = field.querySelector('.combo-dropdown');
                const toggle = field.querySelector('.combo-toggle');
                const options = field.querySelectorAll('.combo-option');
                const emptyHint = field.querySelector('.combo-empty');
                const arrowIcon = toggle ? toggle.querySelector('i') : null;

                if (!input || !dropdown) return;

                function openDropdown() {
                    dropdown.classList.remove('hidden');
                    if (arrowIcon) arrowIcon.style.transform = 'rotate(180deg)';
                    filterOptions(input.value);
                }

                function closeDropdown() {
                    dropdown.classList.add('hidden');
                    if (arrowIcon) arrowIcon.style.transform = 'rotate(0deg)';
                }

                function filterOptions(query) {
                    let visibleCount = 0;
                    const q = query.toLowerCase().trim();
                    options.forEach(function (opt) {
                        const val = (opt.getAttribute('data-value') || '').toLowerCase();
                        const show = q === '' || val.includes(q);
                        opt.style.display = show ? '' : 'none';
                        if (show) visibleCount++;
                    });
                    if (emptyHint) {
                        if (visibleCount === 0 && q !== '') {
                            emptyHint.classList.remove('hidden');
                            emptyHint.style.display = '';
                        } else {
                            emptyHint.classList.add('hidden');
                            emptyHint.style.display = 'none';
                        }
                    }
                }

                if (toggle) {
                    toggle.addEventListener('click', function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                        if (dropdown.classList.contains('hidden')) {
                            openDropdown();
                        } else {
                            closeDropdown();
                        }
                    });
                }

                input.addEventListener('focus', function () { openDropdown(); });
                input.addEventListener('input', function () { openDropdown(); });

                options.forEach(function (opt) {
                    opt.addEventListener('click', function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                        input.value = opt.getAttribute('data-value');
                        closeDropdown();
                        input.focus();
                    });
                });

                document.addEventListener('click', function (e) {
                    if (!field.contains(e.target)) closeDropdown();
                });

                input.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape') closeDropdown();
                    else if (e.key === 'ArrowDown' && dropdown.classList.contains('hidden')) {
                        e.preventDefault();
                        openDropdown();
                    }
                });
            });
        })();

    </script>
</body>

</html>