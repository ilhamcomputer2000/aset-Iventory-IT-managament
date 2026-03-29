<?php
session_start();
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/log_activity.php';

// Auth: hanya admin/super_admin
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}
$role = (string)($_SESSION['role'] ?? '');
if (!in_array($role, ['super_admin', 'admin'])) {
    header('Location: dashboard_admin.php');
    exit;
}

require_once __DIR__ . '/../app_url.php';
ensureUserLogsTable($kon);

$Nama_Lengkap = (string)($_SESSION['Nama_Lengkap'] ?? 'Admin');
$Jabatan_Level = (string)($_SESSION['Jabatan_Level'] ?? '');
$activePage = 'log';

// ---- CSV Export ----
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $where = '1=1';
    $params = [];
    $types = '';
    if (!empty($_GET['filter_user'])) {
        $where .= ' AND ul.username = ?';
        $params[] = $_GET['filter_user'];
        $types .= 's';
    }
    if (!empty($_GET['filter_action'])) {
        $where .= ' AND ul.action = ?';
        $params[] = $_GET['filter_action'];
        $types .= 's';
    }
    if (!empty($_GET['date_from'])) {
        $where .= ' AND DATE(ul.created_at) >= ?';
        $params[] = $_GET['date_from'];
        $types .= 's';
    }
    if (!empty($_GET['date_to'])) {
        $where .= ' AND DATE(ul.created_at) <= ?';
        $params[] = $_GET['date_to'];
        $types .= 's';
    }
    if (!empty($_GET['search'])) {
        $s = '%' . $_GET['search'] . '%';
        $where .= ' AND (ul.username LIKE ? OR u.Nama_Lengkap LIKE ? OR ul.ip_address LIKE ? OR ul.city LIKE ? OR ul.action LIKE ?)';
        $params = array_merge($params, [$s, $s, $s, $s, $s]);
        $types .= 'sssss';
    }
    $stmt = $kon->prepare("SELECT ul.id, ul.username, COALESCE(u.Nama_Lengkap,'') AS Nama_Lengkap, ul.role, ul.action,
                                  ul.ip_address, ul.city, ul.country, ul.device_type, ul.browser, ul.os_name, ul.created_at
                           FROM user_logs ul LEFT JOIN users u ON u.username = ul.username
                           WHERE $where ORDER BY ul.created_at DESC");
    if ($stmt && $types)
        $stmt->bind_param($types, ...$params);
    if ($stmt)
        $stmt->execute();
    $res = $stmt ? $stmt->get_result() : null;
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="activity_log_' . date('Ymd_His') . '.csv"');
    echo "\xEF\xBB\xBF";
    echo "No,ID Karyawan,Nama Lengkap,Role,Aksi,IP Address,Kota,Negara,Device,Browser,OS,Waktu\n";
    $no = 1;
    if ($res)
        while ($row = $res->fetch_assoc()) {
            echo implode(',', array_map(fn($v) => '"' . str_replace('"', '""', $v) . '"',
            [$no++, $row['username'], $row['Nama_Lengkap'], $row['role'], $row['action'],
                $row['ip_address'], $row['city'], $row['country'], $row['device_type'],
                $row['browser'], $row['os_name'], $row['created_at']])) . "\n";
        }
    exit;
}

// ---- AJAX Pagination ----
if (isset($_GET['action']) && $_GET['action'] === 'ajax_log') {
    header('Content-Type: application/json; charset=utf-8');

    $filter_user = trim((string)($_GET['filter_user'] ?? ''));
    $filter_action = trim((string)($_GET['filter_action'] ?? ''));
    $date_from = trim((string)($_GET['date_from'] ?? ''));
    $date_to = trim((string)($_GET['date_to'] ?? ''));
    $search = trim((string)($_GET['search'] ?? ''));
    $per_page = in_array((int)($_GET['per_page'] ?? 15), [10, 15, 25, 50]) ? (int)$_GET['per_page'] : 15;
    $page_num = max(1, (int)($_GET['page'] ?? 1));

    $where = '1=1';
    $params = [];
    $types = '';
    if ($filter_user !== '') {
        $where .= ' AND ul.username = ?';
        $params[] = $filter_user;
        $types .= 's';
    }
    if ($filter_action !== '') {
        $where .= ' AND ul.action = ?';
        $params[] = $filter_action;
        $types .= 's';
    }
    if ($date_from !== '') {
        $where .= ' AND DATE(ul.created_at) >= ?';
        $params[] = $date_from;
        $types .= 's';
    }
    if ($date_to !== '') {
        $where .= ' AND DATE(ul.created_at) <= ?';
        $params[] = $date_to;
        $types .= 's';
    }
    if ($search !== '') {
        $s = '%' . $search . '%';
        $where .= ' AND (ul.username LIKE ? OR u.Nama_Lengkap LIKE ? OR ul.ip_address LIKE ? OR ul.city LIKE ? OR ul.action LIKE ? OR ul.browser LIKE ?)';
        $params = array_merge($params, [$s, $s, $s, $s, $s, $s]);
        $types .= 'ssssss';
    }

    // Count
    $stmtC = $kon->prepare("SELECT COUNT(*) FROM user_logs ul LEFT JOIN users u ON u.username = ul.username WHERE $where");
    if ($stmtC && $types)
        $stmtC->bind_param($types, ...$params);
    $total = 0;
    if ($stmtC) {
        $stmtC->execute();
        $stmtC->bind_result($total);
        $stmtC->fetch();
        $stmtC->close();
    }
    $total_pages = max(1, (int)ceil($total / $per_page));
    $page_num = min($page_num, $total_pages);
    $offset = ($page_num - 1) * $per_page;

    // Data
    $allParams = array_merge($params, [$per_page, $offset]);
    $allTypes = $types . 'ii';
    $stmtD = $kon->prepare("SELECT ul.id, ul.username, COALESCE(u.Nama_Lengkap,'') AS Nama_Lengkap,
                                   ul.role, ul.action, ul.ip_address, ul.city, ul.country,
                                   ul.device_type, ul.browser, ul.os_name, ul.created_at
                            FROM user_logs ul
                            LEFT JOIN users u ON u.username = ul.username
                            WHERE $where ORDER BY ul.created_at DESC LIMIT ? OFFSET ?");
    $rows = [];
    if ($stmtD) {
        if ($allTypes)
            $stmtD->bind_param($allTypes, ...$allParams);
        $stmtD->execute();
        $rows = $stmtD->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtD->close();
    }

    // Build table rows HTML
    $tbody = '';
    $no = $offset + 1;
    foreach ($rows as $row) {
        $action = htmlspecialchars($row['action']);
        $al = strtolower($row['action']);
        if ($al === 'login')
            $badge = 'badge-login';
        elseif ($al === 'logout')
            $badge = 'badge-logout';
        elseif (str_contains($al, 'create') || str_contains($al, 'tambah'))
            $badge = 'badge-create';
        elseif (str_contains($al, 'update') || str_contains($al, 'edit'))
            $badge = 'badge-update';
        elseif (str_contains($al, 'delete') || str_contains($al, 'hapus'))
            $badge = 'badge-delete';
        elseif (str_contains($al, 'view'))
            $badge = 'badge-view';
        else
            $badge = 'badge-default';

        $dType = strtolower($row['device_type'] ?? '');
        $dIcon = $dType === 'mobile' ? 'fa-mobile-alt' : ($dType === 'tablet' ? 'fa-tablet-alt' : 'fa-desktop');
        $roleColor = in_array($row['role'], ['super_admin', 'admin']) ? 'text-orange-600 bg-orange-50' : 'text-blue-600 bg-blue-50';

        $idKaryawan = htmlspecialchars($row['username'] ?? '—');
        $namaLengkap = htmlspecialchars($row['Nama_Lengkap'] ?? '');
        $city = htmlspecialchars($row['city'] ?? '');
        $country = htmlspecialchars($row['country'] ?? '');
        $lokasi = ($city && $country) ? "$city, $country" : ($city ?: ($country ?: '<span class="text-gray-400">—</span>'));

        $dt = new DateTime($row['created_at']);
        $waktu = $dt->format('d M Y') . '<br><span class="text-gray-400">' . $dt->format('H:i:s') . '</span>';

        $tbody .= "<tr class='log-row'>
            <td class='px-3 py-3 text-gray-400 text-xs'>$no</td>
            <td class='px-3 py-3'>
                <div class='font-semibold text-gray-900 text-xs'>$idKaryawan</div>
                " . ($namaLengkap ? "<div class='text-xs text-gray-500 mt-0.5'>$namaLengkap</div>" : '') . "
                <span class='text-[10px] px-1.5 py-0.5 rounded $roleColor font-medium mt-0.5 inline-block'>" . htmlspecialchars($row['role']) . "</span>
            </td>
            <td class='px-3 py-3'>
                <span class='px-2.5 py-1 rounded-full text-xs font-semibold $badge'>$action</span>
            </td>
            <td class='px-3 py-3 font-mono text-xs text-gray-700'>" . htmlspecialchars($row['ip_address'] ?? '—') . "</td>
            <td class='px-3 py-3 text-xs text-gray-600'>$lokasi</td>
            <td class='px-3 py-3'>
                <div class='flex items-center gap-1.5 text-xs text-gray-600'>
                    <i class='fas $dIcon text-gray-400'></i>" . htmlspecialchars($row['device_type'] ?? '—') . "
                </div>
                <div class='text-[11px] text-gray-400 mt-0.5'>" . htmlspecialchars($row['os_name'] ?? '') . "</div>
            </td>
            <td class='px-3 py-3 text-xs text-gray-600'>" . htmlspecialchars($row['browser'] ?? '—') . "</td>
            <td class='px-3 py-3 text-xs text-gray-600 whitespace-nowrap'>$waktu</td>
        </tr>";
        $no++;
    }

    if (empty($rows)) {
        $tbody = "<tr><td colspan='8' class='px-4 py-16 text-center text-gray-400'>
            <i class='fas fa-history text-4xl mb-3 text-gray-200 block'></i>Belum ada log aktivitas</td></tr>";
    }

    // Build pagination HTML (matching index.php style)
    $pag = '';
    if ($total_pages > 1) {
        $baseP = array_merge($_GET, ['action' => '', 'page' => '', 'per_page' => $per_page]);
        unset($baseP['action']);
        $pag .= '<div class="mt-4 flex flex-col items-center"><div class="w-full max-w-4xl flex flex-col items-center space-y-2">';
        $pag .= '<div class="text-sm text-gray-600 text-center">Showing ' . min($offset + 1, $total) . ' to ' . min($offset + $per_page, $total) . ' of ' . $total . ' results</div>';
        $pag .= '<nav class="inline-flex rounded-md shadow-sm -space-x-px justify-center">';
        if ($page_num > 1) {
            $pag .= '<a href="?' . http_build_query(array_merge($baseP, ['page' => $page_num - 1])) . '" class="pagination-link relative inline-flex items-center px-3 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50"><i class="fas fa-chevron-left"></i><span class="ml-1">Prev</span></a>';
        }
        $start = max(1, $page_num - 2);
        $end = min($total_pages, $page_num + 2);
        if ($start > 1) {
            $pag .= '<a href="?' . http_build_query(array_merge($baseP, ['page' => 1])) . '" class="pagination-link relative inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">1</a>';
            if ($start > 2)
                $pag .= '<span class="relative inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-500">...</span>';
        }
        for ($i = $start; $i <= $end; $i++) {
            if ($i == $page_num)
                $pag .= '<span class="relative z-10 inline-flex items-center px-3 py-2 border border-orange-500 bg-orange-50 text-sm font-medium text-orange-600">' . $i . '</span>';
            else
                $pag .= '<a href="?' . http_build_query(array_merge($baseP, ['page' => $i])) . '" class="pagination-link relative inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">' . $i . '</a>';
        }
        if ($end < $total_pages) {
            if ($end < $total_pages - 1)
                $pag .= '<span class="relative inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-500">...</span>';
            $pag .= '<a href="?' . http_build_query(array_merge($baseP, ['page' => $total_pages])) . '" class="pagination-link relative inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">' . $total_pages . '</a>';
        }
        if ($page_num < $total_pages) {
            $pag .= '<a href="?' . http_build_query(array_merge($baseP, ['page' => $page_num + 1])) . '" class="pagination-link relative inline-flex items-center px-3 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50"><span class="mr-1">Next</span><i class="fas fa-chevron-right"></i></a>';
        }
        $pag .= '</nav></div></div>';
    }

    echo json_encode([
        'tbody_html' => $tbody,
        'pagination_html' => $pag,
        'current_page' => $page_num,
        'total_pages' => $total_pages,
        'total_records' => $total,
        'showing_from' => $total > 0 ? $offset + 1 : 0,
        'showing_to' => min($offset + $per_page, $total),
    ]);
    exit;
}

// ---- Normal Page Load ----
$filter_user = trim((string)($_GET['filter_user'] ?? ''));
$filter_action = trim((string)($_GET['filter_action'] ?? ''));
$date_from = trim((string)($_GET['date_from'] ?? ''));
$date_to = trim((string)($_GET['date_to'] ?? ''));
$search = trim((string)($_GET['search'] ?? ''));
$per_page = in_array((int)($_GET['per_page'] ?? 15), [10, 15, 25, 50]) ? (int)$_GET['per_page'] : 15;

// Stats
$statsTotal = $kon->query("SELECT COUNT(*) c FROM user_logs")->fetch_assoc()['c'] ?? 0;
$statsToday = $kon->query("SELECT COUNT(*) c FROM user_logs WHERE DATE(created_at)=CURDATE()")->fetch_assoc()['c'] ?? 0;
$statsLogin = $kon->query("SELECT COUNT(*) c FROM user_logs WHERE action='Login' AND DATE(created_at)=CURDATE()")->fetch_assoc()['c'] ?? 0;
$statsUsers = $kon->query("SELECT COUNT(DISTINCT username) c FROM user_logs")->fetch_assoc()['c'] ?? 0;

// Filter options
$userList = $kon->query("SELECT DISTINCT ul.username, COALESCE(u.Nama_Lengkap,'') AS Nama_Lengkap FROM user_logs ul LEFT JOIN users u ON u.username=ul.username ORDER BY ul.username ASC");
$actionList = $kon->query("SELECT DISTINCT action FROM user_logs ORDER BY action ASC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Log — Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        * { font-family: 'Inter', sans-serif; }
        .badge-login   { background:#dcfce7; color:#166534; }
        .badge-logout  { background:#fee2e2; color:#991b1b; }
        .badge-view    { background:#dbeafe; color:#1e40af; }
        .badge-create  { background:#fef9c3; color:#854d0e; }
        .badge-update  { background:#ede9fe; color:#5b21b6; }
        .badge-delete  { background:#ffe4e6; color:#9f1239; }
        .badge-default { background:#f3f4f6; color:#374151; }
        .log-row:hover { background:#fef3c7 !important; transition:background .15s; }
    </style>
</head>
<body class="bg-gray-50">
<?php require_once __DIR__ . '/sidebar_admin.php'; ?>

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
<main class="mt-16 min-h-screen bg-slate-50 p-4 sm:p-6">
<div class="max-w-[1400px] mx-auto">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                <i class="fas fa-history text-orange-500"></i> Activity Log
            </h1>
            <p class="text-gray-500 text-sm mt-0.5">Semua aktivitas pengguna — login, logout, dan aksi lainnya</p>
        </div>
        <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>"
           class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-xl transition shadow-sm">
            <i class="fas fa-download"></i> Export CSV
        </a>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <?php foreach ([
['fa-list', 'Total Log', number_format($statsTotal), 'orange'],
['fa-calendar-day', 'Hari Ini', number_format($statsToday), 'blue'],
['fa-sign-in-alt', 'Login Hari Ini', number_format($statsLogin), 'green'],
['fa-users', 'Pengguna Aktif', number_format($statsUsers), 'purple'],
] as $c): ?>
        <div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-<?php echo $c[3]; ?>-100 flex items-center justify-center">
                    <i class="fas <?php echo $c[0]; ?> text-<?php echo $c[3]; ?>-600 text-sm"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-500"><?php echo $c[1]; ?></p>
                    <p class="text-xl font-bold text-gray-900"><?php echo $c[2]; ?></p>
                </div>
            </div>
        </div>
        <?php
endforeach; ?>
    </div>

    <!-- Filter Panel -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 mb-5" id="filter-panel">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <div class="relative lg:col-span-2">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                <input type="text" id="log-search" value="<?php echo htmlspecialchars($search); ?>"
                    placeholder="Cari ID, nama, IP, kota, aksi..."
                    class="w-full pl-9 pr-3 py-2 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-orange-300">
            </div>
            <select id="log-filter-user" class="text-sm border border-gray-200 rounded-xl px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-300">
                <option value="">Semua User</option>
                <?php if ($userList):
    while ($u = $userList->fetch_assoc()): ?>
                <option value="<?php echo htmlspecialchars($u['username']); ?>" <?php echo $filter_user === $u['username'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($u['username']); ?><?php echo $u['Nama_Lengkap'] ? ' — ' . htmlspecialchars($u['Nama_Lengkap']) : ''; ?>
                </option>
                <?php
    endwhile;
endif; ?>
            </select>
            <select id="log-filter-action" class="text-sm border border-gray-200 rounded-xl px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-300">
                <option value="">Semua Aksi</option>
                <?php if ($actionList):
    while ($a = $actionList->fetch_assoc()): ?>
                <option value="<?php echo htmlspecialchars($a['action']); ?>" <?php echo $filter_action === $a['action'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($a['action']); ?>
                </option>
                <?php
    endwhile;
endif; ?>
            </select>
            <div class="flex gap-2">
                <button id="btn-filter" class="flex-1 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium rounded-xl px-4 py-2 transition">
                    <i class="fas fa-filter mr-1"></i> Filter
                </button>
                <button id="btn-reset" class="flex items-center justify-center px-3 py-2 text-sm text-gray-500 hover:text-gray-700 border border-gray-200 rounded-xl hover:bg-gray-50 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <div class="flex flex-wrap gap-3 mt-3 items-center">
            <label class="text-xs text-gray-500 font-medium">Periode:</label>
            <input type="date" id="log-date-from" value="<?php echo htmlspecialchars($date_from); ?>"
                class="text-sm border border-gray-200 rounded-xl px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-orange-300">
            <span class="text-gray-400 text-sm">—</span>
            <input type="date" id="log-date-to" value="<?php echo htmlspecialchars($date_to); ?>"
                class="text-sm border border-gray-200 rounded-xl px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-orange-300">
            <select id="log-per-page" class="text-sm border border-gray-200 rounded-xl px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-orange-300">
                <?php foreach ([10, 15, 25, 50] as $pp): ?>
                <option value="<?php echo $pp; ?>" <?php echo $per_page == $pp ? 'selected' : ''; ?>><?php echo $pp; ?> / halaman</option>
                <?php
endforeach; ?>
            </select>
        </div>
    </div>

    <!-- Info + Table -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-4">
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
            <p class="text-sm text-gray-500" id="log-info">Memuat data...</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead>
                    <tr class="bg-gray-50 text-left">
                        <th class="px-3 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider">#</th>
                        <th class="px-3 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider">ID Karyawan / Nama</th>
                        <th class="px-3 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider">Aksi</th>
                        <th class="px-3 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider">IP Address</th>
                        <th class="px-3 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider">Lokasi</th>
                        <th class="px-3 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider">Device</th>
                        <th class="px-3 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider">Browser</th>
                        <th class="px-3 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider">Waktu</th>
                    </tr>
                </thead>
                <tbody id="log-tbody" class="divide-y divide-gray-50">
                    <tr><td colspan="8" class="px-4 py-10 text-center text-gray-400 text-sm">
                        <i class="fas fa-circle-notch fa-spin mr-2"></i>Memuat...
                    </td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <div id="log-pagination"></div>

</div>
</main>
</div>

<script>
(function() {
    let currentPage = 1;
    let isLoading = false;

    const state = {
        search:       document.getElementById('log-search'),
        filterUser:   document.getElementById('log-filter-user'),
        filterAction: document.getElementById('log-filter-action'),
        dateFrom:     document.getElementById('log-date-from'),
        dateTo:       document.getElementById('log-date-to'),
        perPage:      document.getElementById('log-per-page'),
    };

    function fetchLogs(page) {
        if (isLoading) return;
        isLoading = true;
        currentPage = page || 1;

        const params = new URLSearchParams({
            action:        'ajax_log',
            page:          currentPage,
            per_page:      state.perPage.value,
            search:        state.search.value.trim(),
            filter_user:   state.filterUser.value,
            filter_action: state.filterAction.value,
            date_from:     state.dateFrom.value,
            date_to:       state.dateTo.value,
        });

        document.getElementById('log-tbody').innerHTML =
            '<tr><td colspan="8" class="px-4 py-10 text-center text-gray-400"><i class="fas fa-circle-notch fa-spin mr-2"></i>Memuat...</td></tr>';

        fetch('?' + params.toString())
            .then(r => r.json())
            .then(data => {
                document.getElementById('log-tbody').innerHTML = data.tbody_html || '';
                document.getElementById('log-pagination').innerHTML = data.pagination_html || '';
                const from = data.showing_from || 0;
                const to   = data.showing_to   || 0;
                const tot  = data.total_records || 0;
                document.getElementById('log-info').textContent =
                    tot > 0
                    ? `Menampilkan ${from} - ${to} dari ${tot.toLocaleString('id')} record`
                    : 'Tidak ada data ditemukan';

                // Re-attach pagination click handlers
                document.querySelectorAll('.pagination-link').forEach(link => {
                    link.addEventListener('click', function(e) {
                        e.preventDefault();
                        const url = new URL(this.href);
                        const p = parseInt(url.searchParams.get('page') || '1');
                        fetchLogs(p);
                    });
                });
                isLoading = false;
            })
            .catch(() => {
                document.getElementById('log-tbody').innerHTML =
                    '<tr><td colspan="8" class="px-4 py-10 text-center text-red-400"><i class="fas fa-exclamation-circle mr-2"></i>Gagal memuat data</td></tr>';
                isLoading = false;
            });
    }

    // Filter button
    document.getElementById('btn-filter').addEventListener('click', () => fetchLogs(1));

    // Reset button
    document.getElementById('btn-reset').addEventListener('click', () => {
        state.search.value = '';
        state.filterUser.value = '';
        state.filterAction.value = '';
        state.dateFrom.value = '';
        state.dateTo.value = '';
        fetchLogs(1);
    });

    // Per page change
    state.perPage.addEventListener('change', () => fetchLogs(1));

    // Enter key in search
    state.search.addEventListener('keydown', e => { if (e.key === 'Enter') fetchLogs(1); });

    // Initial load
    fetchLogs(<?php echo max(1, (int)($_GET['page'] ?? 1)); ?>);
})();
</script>
</body>
</html>