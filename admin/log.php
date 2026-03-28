<?php
session_start();
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/log_activity.php';

// Auth: hanya admin/super_admin
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php'); exit;
}
$role = (string)($_SESSION['role'] ?? '');
if (!in_array($role, ['super_admin', 'admin'])) {
    header('Location: dashboard_admin.php'); exit;
}

require_once __DIR__ . '/../app_url.php';

// Auto-create table
ensureUserLogsTable($kon);

$Nama_Lengkap  = (string)($_SESSION['Nama_Lengkap'] ?? 'Admin');
$Jabatan_Level = (string)($_SESSION['Jabatan_Level'] ?? '');
$activePage    = 'log';

// ---- Handle CSV Export ----
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $where  = '1=1';
    $params = [];
    $types  = '';
    if (!empty($_GET['filter_user'])) {
        $where   .= ' AND ul.username = ?';
        $params[] = $_GET['filter_user'];
        $types   .= 's';
    }
    if (!empty($_GET['filter_action'])) {
        $where   .= ' AND ul.action = ?';
        $params[] = $_GET['filter_action'];
        $types   .= 's';
    }
    if (!empty($_GET['date_from'])) {
        $where   .= ' AND DATE(ul.created_at) >= ?';
        $params[] = $_GET['date_from'];
        $types   .= 's';
    }
    if (!empty($_GET['date_to'])) {
        $where   .= ' AND DATE(ul.created_at) <= ?';
        $params[] = $_GET['date_to'];
        $types   .= 's';
    }
    if (!empty($_GET['search'])) {
        $s = '%' . $_GET['search'] . '%';
        $where   .= ' AND (ul.username LIKE ? OR ul.ip_address LIKE ? OR ul.city LIKE ? OR ul.action LIKE ?)';
        $params   = array_merge($params, [$s, $s, $s, $s]);
        $types   .= 'ssss';
    }

    $sql = "SELECT ul.id, ul.username, ul.role, ul.action, ul.ip_address, ul.city, ul.country,
                   ul.device_type, ul.browser, ul.os_name, ul.created_at
            FROM user_logs ul
            WHERE $where
            ORDER BY ul.created_at DESC";
    $stmt = $kon->prepare($sql);
    if ($stmt && $types) {
        $stmt->bind_param($types, ...$params);
    }
    if ($stmt) $stmt->execute();
    $res = $stmt ? $stmt->get_result() : null;

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="activity_log_' . date('Ymd_His') . '.csv"');
    echo "\xEF\xBB\xBF"; // BOM for Excel
    echo "No,Username,Role,Aksi,IP Address,Kota,Negara,Device,Browser,OS,Waktu\n";
    $no = 1;
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            echo implode(',', [
                $no++,
                '"' . str_replace('"', '""', $row['username']) . '"',
                '"' . str_replace('"', '""', $row['role'])     . '"',
                '"' . str_replace('"', '""', $row['action'])   . '"',
                '"' . str_replace('"', '""', $row['ip_address']) . '"',
                '"' . str_replace('"', '""', $row['city'])     . '"',
                '"' . str_replace('"', '""', $row['country'])  . '"',
                '"' . str_replace('"', '""', $row['device_type']) . '"',
                '"' . str_replace('"', '""', $row['browser'])  . '"',
                '"' . str_replace('"', '""', $row['os_name'])  . '"',
                '"' . str_replace('"', '""', $row['created_at']) . '"',
            ]) . "\n";
        }
    }
    exit;
}

// ---- Filters ----
$filter_user   = trim((string)($_GET['filter_user']   ?? ''));
$filter_action = trim((string)($_GET['filter_action'] ?? ''));
$date_from     = trim((string)($_GET['date_from']     ?? ''));
$date_to       = trim((string)($_GET['date_to']       ?? ''));
$search        = trim((string)($_GET['search']        ?? ''));
$page_num      = max(1, (int)($_GET['page'] ?? 1));
$per_page      = 15;

$where  = '1=1';
$params = [];
$types  = '';
if ($filter_user !== '') {
    $where .= ' AND ul.username = ?'; $params[] = $filter_user; $types .= 's';
}
if ($filter_action !== '') {
    $where .= ' AND ul.action = ?'; $params[] = $filter_action; $types .= 's';
}
if ($date_from !== '') {
    $where .= ' AND DATE(ul.created_at) >= ?'; $params[] = $date_from; $types .= 's';
}
if ($date_to !== '') {
    $where .= ' AND DATE(ul.created_at) <= ?'; $params[] = $date_to; $types .= 's';
}
if ($search !== '') {
    $s = '%' . $search . '%';
    $where .= ' AND (ul.username LIKE ? OR ul.ip_address LIKE ? OR ul.city LIKE ? OR ul.action LIKE ? OR ul.browser LIKE ?)';
    $params = array_merge($params, [$s, $s, $s, $s, $s]);
    $types .= 'sssss';
}

// Total count
$stmtCount = $kon->prepare("SELECT COUNT(*) FROM user_logs ul WHERE $where");
if ($stmtCount && $types) $stmtCount->bind_param($types, ...$params);
if ($stmtCount) { $stmtCount->execute(); $stmtCount->bind_result($total_records); $stmtCount->fetch(); $stmtCount->close(); }
else $total_records = 0;
$total_pages = max(1, (int)ceil($total_records / $per_page));
$offset = ($page_num - 1) * $per_page;

// Main query
$sql = "SELECT ul.id, ul.user_id, ul.username, ul.role, ul.action,
               ul.ip_address, ul.city, ul.country, ul.device_type,
               ul.browser, ul.os_name, ul.created_at
        FROM user_logs ul
        WHERE $where
        ORDER BY ul.created_at DESC
        LIMIT ? OFFSET ?";
$stmtData = $kon->prepare($sql);
$allTypes = $types . 'ii';
$allParams = array_merge($params, [$per_page, $offset]);
if ($stmtData) {
    if ($allTypes) $stmtData->bind_param($allTypes, ...$allParams);
    $stmtData->execute();
    $logs = $stmtData->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtData->close();
} else {
    $logs = [];
}

// Load filter options
$userList = $kon->query("SELECT DISTINCT username FROM user_logs ORDER BY username ASC");
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
        .badge-login    { background:#dcfce7; color:#166534; }
        .badge-logout   { background:#fee2e2; color:#991b1b; }
        .badge-view     { background:#dbeafe; color:#1e40af; }
        .badge-create   { background:#fef9c3; color:#854d0e; }
        .badge-update   { background:#ede9fe; color:#5b21b6; }
        .badge-delete   { background:#ffe4e6; color:#9f1239; }
        .badge-default  { background:#f3f4f6; color:#374151; }
        .log-row:hover  { background:#fef3c7 !important; transition: background .15s; }
    </style>
</head>
<body class="bg-gray-50">
<?php require_once __DIR__ . '/sidebar_admin.php'; ?>

<main class="lg:ml-60 pt-14 min-h-screen">
<div class="p-4 sm:p-6 max-w-[1400px] mx-auto">

    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                <i class="fas fa-history text-orange-500"></i> Activity Log
            </h1>
            <p class="text-gray-500 text-sm mt-0.5">Semua aktivitas pengguna — login, logout, dan aksi lainnya</p>
        </div>
        <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>"
           class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-xl transition-all duration-200 shadow-sm">
            <i class="fas fa-download"></i> Export CSV
        </a>
    </div>

    <!-- Stats Cards -->
    <?php
    $statsTotal  = $kon->query("SELECT COUNT(*) c FROM user_logs")->fetch_assoc()['c'] ?? 0;
    $statsToday  = $kon->query("SELECT COUNT(*) c FROM user_logs WHERE DATE(created_at)=CURDATE()")->fetch_assoc()['c'] ?? 0;
    $statsLogin  = $kon->query("SELECT COUNT(*) c FROM user_logs WHERE action='Login' AND DATE(created_at)=CURDATE()")->fetch_assoc()['c'] ?? 0;
    $statsUsers  = $kon->query("SELECT COUNT(DISTINCT username) c FROM user_logs")->fetch_assoc()['c'] ?? 0;
    $cards = [
        ['icon'=>'fa-list','label'=>'Total Log','value'=>number_format($statsTotal),'color'=>'orange'],
        ['icon'=>'fa-calendar-day','label'=>'Hari Ini','value'=>number_format($statsToday),'color'=>'blue'],
        ['icon'=>'fa-sign-in-alt','label'=>'Login Hari Ini','value'=>number_format($statsLogin),'color'=>'green'],
        ['icon'=>'fa-users','label'=>'Pengguna Aktif','value'=>number_format($statsUsers),'color'=>'purple'],
    ];
    ?>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <?php foreach ($cards as $c): ?>
        <div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-<?php echo $c['color']; ?>-100 flex items-center justify-center">
                    <i class="fas <?php echo $c['icon']; ?> text-<?php echo $c['color']; ?>-600 text-sm"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-500"><?php echo $c['label']; ?></p>
                    <p class="text-xl font-bold text-gray-900"><?php echo $c['value']; ?></p>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    </div>

    <!-- Filter Panel -->
    <form method="GET" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 mb-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <!-- Search -->
            <div class="relative lg:col-span-2">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                    placeholder="Cari username, IP, kota, aksi..."
                    class="w-full pl-9 pr-3 py-2 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-orange-300">
            </div>
            <!-- User -->
            <select name="filter_user" class="text-sm border border-gray-200 rounded-xl px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-300">
                <option value="">Semua User</option>
                <?php if ($userList): while ($u = $userList->fetch_assoc()): ?>
                <option value="<?php echo htmlspecialchars($u['username']); ?>" <?php echo $filter_user === $u['username'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($u['username']); ?>
                </option>
                <?php endwhile; endif; ?>
            </select>
            <!-- Action -->
            <select name="filter_action" class="text-sm border border-gray-200 rounded-xl px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-300">
                <option value="">Semua Aksi</option>
                <?php if ($actionList): while ($a = $actionList->fetch_assoc()): ?>
                <option value="<?php echo htmlspecialchars($a['action']); ?>" <?php echo $filter_action === $a['action'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($a['action']); ?>
                </option>
                <?php endwhile; endif; ?>
            </select>
            <!-- Buttons -->
            <div class="flex gap-2">
                <button type="submit" class="flex-1 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium rounded-xl px-4 py-2 transition">
                    <i class="fas fa-filter mr-1"></i> Filter
                </button>
                <a href="log.php" class="flex items-center justify-center px-3 py-2 text-sm text-gray-500 hover:text-gray-700 border border-gray-200 rounded-xl hover:bg-gray-50 transition">
                    <i class="fas fa-times"></i>
                </a>
            </div>
        </div>
        <!-- Date range -->
        <div class="flex flex-wrap gap-3 mt-3 items-center">
            <label class="text-xs text-gray-500 font-medium">Periode:</label>
            <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>"
                class="text-sm border border-gray-200 rounded-xl px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-orange-300">
            <span class="text-gray-400 text-sm">—</span>
            <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>"
                class="text-sm border border-gray-200 rounded-xl px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-orange-300">
        </div>
    </form>

    <!-- Results info -->
    <div class="flex items-center justify-between mb-3">
        <p class="text-sm text-gray-500">
            Menampilkan <span class="font-medium text-gray-900"><?php echo count($logs); ?></span> dari
            <span class="font-medium text-gray-900"><?php echo number_format($total_records); ?></span> record
        </p>
        <p class="text-sm text-gray-400">Halaman <?php echo $page_num; ?> / <?php echo $total_pages; ?></p>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-6">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead>
                    <tr class="bg-gray-50 text-left">
                        <th class="px-4 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider">#</th>
                        <th class="px-4 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider">User</th>
                        <th class="px-4 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider">Aksi</th>
                        <th class="px-4 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider">IP Address</th>
                        <th class="px-4 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider">Lokasi</th>
                        <th class="px-4 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider">Device</th>
                        <th class="px-4 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider">Browser</th>
                        <th class="px-4 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider">Waktu</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="8" class="px-4 py-16 text-center text-gray-400">
                            <i class="fas fa-history text-4xl mb-3 text-gray-200 block"></i>
                            Belum ada log aktivitas
                        </td>
                    </tr>
                <?php else: ?>
                <?php
                $no = $offset + 1;
                foreach ($logs as $row):
                    $action = htmlspecialchars($row['action']);
                    $actionLower = strtolower($row['action']);
                    if ($actionLower === 'login')        $badgeClass = 'badge-login';
                    elseif ($actionLower === 'logout')   $badgeClass = 'badge-logout';
                    elseif (str_contains($actionLower,'view'))   $badgeClass = 'badge-view';
                    elseif (str_contains($actionLower,'create') || str_contains($actionLower,'tambah')) $badgeClass = 'badge-create';
                    elseif (str_contains($actionLower,'update') || str_contains($actionLower,'edit'))   $badgeClass = 'badge-update';
                    elseif (str_contains($actionLower,'delete') || str_contains($actionLower,'hapus'))  $badgeClass = 'badge-delete';
                    else $badgeClass = 'badge-default';

                    $deviceIcon = 'fa-desktop';
                    $dType = strtolower($row['device_type'] ?? '');
                    if ($dType === 'mobile')  $deviceIcon = 'fa-mobile-alt';
                    elseif ($dType === 'tablet') $deviceIcon = 'fa-tablet-alt';

                    $roleColor = in_array($row['role'], ['super_admin','admin']) ? 'text-orange-600 bg-orange-50' : 'text-blue-600 bg-blue-50';
                ?>
                <tr class="log-row">
                    <td class="px-4 py-3 text-gray-400 text-xs"><?php echo $no++; ?></td>
                    <td class="px-4 py-3">
                        <div class="font-medium text-gray-900"><?php echo htmlspecialchars($row['username']); ?></div>
                        <span class="text-[11px] px-1.5 py-0.5 rounded <?php echo $roleColor; ?> font-medium"><?php echo htmlspecialchars($row['role']); ?></span>
                    </td>
                    <td class="px-4 py-3">
                        <span class="px-2.5 py-1 rounded-full text-xs font-semibold <?php echo $badgeClass; ?>">
                            <?php echo $action; ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 font-mono text-xs text-gray-700"><?php echo htmlspecialchars($row['ip_address'] ?? '-'); ?></td>
                    <td class="px-4 py-3 text-xs text-gray-600">
                        <?php
                        $city    = htmlspecialchars($row['city'] ?? '');
                        $country = htmlspecialchars($row['country'] ?? '');
                        echo $city && $country ? "$city, $country" : ($city ?: ($country ?: '<span class="text-gray-400">—</span>'));
                        ?>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-1.5 text-xs text-gray-600">
                            <i class="fas <?php echo $deviceIcon; ?> text-gray-400"></i>
                            <?php echo htmlspecialchars($row['device_type'] ?? '—'); ?>
                        </div>
                        <div class="text-[11px] text-gray-400 mt-0.5"><?php echo htmlspecialchars($row['os_name'] ?? ''); ?></div>
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-600"><?php echo htmlspecialchars($row['browser'] ?? '—'); ?></td>
                    <td class="px-4 py-3 text-xs text-gray-600 whitespace-nowrap">
                        <?php
                        $dt = new DateTime($row['created_at']);
                        echo $dt->format('d M Y') . '<br><span class="text-gray-400">' . $dt->format('H:i:s') . '</span>';
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="flex justify-center gap-2 mb-6">
        <?php
        $base = '?' . http_build_query(array_merge($_GET, ['page' => '']));
        if ($page_num > 1): ?>
        <a href="<?php echo $base . ($page_num-1); ?>" class="px-3 py-2 text-sm border border-gray-200 rounded-xl hover:bg-gray-50 transition text-gray-600">
            <i class="fas fa-chevron-left"></i>
        </a>
        <?php endif;
        $start = max(1, $page_num-2); $end = min($total_pages, $page_num+2);
        for ($i = $start; $i <= $end; $i++):
            $active = $i === $page_num;
        ?>
        <a href="<?php echo $base . $i; ?>"
            class="px-3 py-2 text-sm rounded-xl transition font-medium <?php echo $active ? 'bg-orange-500 text-white shadow-sm' : 'border border-gray-200 text-gray-600 hover:bg-gray-50'; ?>">
            <?php echo $i; ?>
        </a>
        <?php endfor;
        if ($page_num < $total_pages): ?>
        <a href="<?php echo $base . ($page_num+1); ?>" class="px-3 py-2 text-sm border border-gray-200 rounded-xl hover:bg-gray-50 transition text-gray-600">
            <i class="fas fa-chevron-right"></i>
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>
</main>
</body>
</html>