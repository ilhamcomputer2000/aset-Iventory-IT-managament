<?php
session_start();
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../admin/log_activity.php';

// Auth: hanya user (bukan admin)
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php'); exit;
}
$current_user_id = (int)$_SESSION['user_id'];
$role = (string)($_SESSION['role'] ?? '');

// Redirect admin ke halaman admin log
if (in_array($role, ['super_admin', 'admin'])) {
    header('Location: ../admin/log.php'); exit;
}

require_once __DIR__ . '/../app_url.php';

// Auto-create table
ensureUserLogsTable($kon);

$Nama_Lengkap  = (string)($_SESSION['Nama_Lengkap'] ?? 'User');
$Jabatan_Level = (string)($_SESSION['Jabatan_Level'] ?? '');
$activePage    = 'log';

// ---- Filters (user hanya bisa filter data dirinya) ----
$filter_action = trim((string)($_GET['filter_action'] ?? ''));
$date_from     = trim((string)($_GET['date_from']     ?? ''));
$date_to       = trim((string)($_GET['date_to']       ?? ''));
$search        = trim((string)($_GET['search']        ?? ''));
$page_num      = max(1, (int)($_GET['page'] ?? 1));
$per_page      = 10;

$where  = 'ul.user_id = ?';
$params = [$current_user_id];
$types  = 'i';

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
    $where .= ' AND (ul.ip_address LIKE ? OR ul.city LIKE ? OR ul.action LIKE ? OR ul.browser LIKE ?)';
    $params = array_merge($params, [$s, $s, $s, $s]);
    $types .= 'ssss';
}

// Total count
$stmtCount = $kon->prepare("SELECT COUNT(*) FROM user_logs ul WHERE $where");
if ($stmtCount) { $stmtCount->bind_param($types, ...$params); $stmtCount->execute(); $stmtCount->bind_result($total_records); $stmtCount->fetch(); $stmtCount->close(); }
else $total_records = 0;
$total_pages = max(1, (int)ceil($total_records / $per_page));
$offset = ($page_num - 1) * $per_page;

// Main query (own logs only)
$sql = "SELECT id, action, ip_address, city, country, device_type, browser, os_name, created_at
        FROM user_logs ul
        WHERE $where
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?";
$stmtData = $kon->prepare($sql);
if ($stmtData) {
    $dataParams = array_merge($params, [$per_page, $offset]);
    $stmtData->bind_param($types . 'ii', ...$dataParams);
    $stmtData->execute();
    $logs = $stmtData->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtData->close();
} else {
    $logs = [];
}

// Summary stats (own data only, no filter)
$totalLogins = $kon->query("SELECT COUNT(*) FROM user_logs WHERE user_id=$current_user_id AND action='Login'")->fetch_row()[0] ?? 0;
$monthLogins = $kon->query("SELECT COUNT(*) FROM user_logs WHERE user_id=$current_user_id AND action='Login' AND MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())")->fetch_row()[0] ?? 0;
$lastRecord  = $kon->query("SELECT ip_address, city, country, browser, device_type, created_at FROM user_logs WHERE user_id=$current_user_id ORDER BY created_at DESC LIMIT 1")->fetch_assoc();
$actionList  = $kon->query("SELECT DISTINCT action FROM user_logs WHERE user_id=$current_user_id ORDER BY action ASC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Aktivitas Saya</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        * { font-family: 'Inter', sans-serif; }
        .badge-login    { background:#dcfce7; color:#166534; }
        .badge-logout   { background:#fee2e2; color:#991b1b; }
        .badge-default  { background:#f3f4f6; color:#374151; }
        .log-row:hover  { background:#fffbeb !important; transition: background .15s; }
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
<main class="mt-16 min-h-screen bg-slate-50 p-4 sm:p-6">
<div class="max-w-5xl mx-auto">

    <!-- Header -->
    <div class="mb-6">
        <h1 class="text-xl font-bold text-gray-900 flex items-center gap-2">
            <i class="fas fa-history text-orange-500"></i> Riwayat Aktivitas Saya
        </h1>
        <p class="text-gray-500 text-sm mt-0.5">Log aktivitas akun Anda — kapan masuk, dari mana, pakai apa</p>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <!-- Total Login -->
        <div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100">
            <div class="w-9 h-9 rounded-xl bg-green-100 flex items-center justify-center mb-2">
                <i class="fas fa-sign-in-alt text-green-600 text-sm"></i>
            </div>
            <p class="text-2xl font-bold text-gray-900"><?php echo number_format($totalLogins); ?></p>
            <p class="text-xs text-gray-500 mt-0.5">Total Login</p>
        </div>
        <!-- Login bulan ini -->
        <div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100">
            <div class="w-9 h-9 rounded-xl bg-blue-100 flex items-center justify-center mb-2">
                <i class="fas fa-calendar-alt text-blue-600 text-sm"></i>
            </div>
            <p class="text-2xl font-bold text-gray-900"><?php echo number_format($monthLogins); ?></p>
            <p class="text-xs text-gray-500 mt-0.5">Login Bulan Ini</p>
        </div>
        <!-- IP Terakhir -->
        <div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100">
            <div class="w-9 h-9 rounded-xl bg-orange-100 flex items-center justify-center mb-2">
                <i class="fas fa-network-wired text-orange-600 text-sm"></i>
            </div>
            <p class="text-sm font-bold text-gray-900 truncate"><?php echo htmlspecialchars($lastRecord['ip_address'] ?? '—'); ?></p>
            <p class="text-xs text-gray-500 mt-0.5">IP Terakhir</p>
        </div>
        <!-- Device Terakhir -->
        <div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100">
            <?php
            $lastDevice = $lastRecord['device_type'] ?? '';
            $dIcon = $lastDevice === 'Mobile' ? 'fa-mobile-alt' : ($lastDevice === 'Tablet' ? 'fa-tablet-alt' : 'fa-desktop');
            ?>
            <div class="w-9 h-9 rounded-xl bg-purple-100 flex items-center justify-center mb-2">
                <i class="fas <?php echo $dIcon; ?> text-purple-600 text-sm"></i>
            </div>
            <p class="text-sm font-bold text-gray-900"><?php echo htmlspecialchars($lastDevice ?: '—'); ?></p>
            <p class="text-xs text-gray-500 mt-0.5"><?php echo htmlspecialchars($lastRecord['browser'] ?? ''); ?></p>
        </div>
    </div>

    <!-- Last Login Info Banner -->
    <?php if ($lastRecord): ?>
    <div class="bg-gradient-to-r from-orange-50 to-amber-50 border border-orange-200 rounded-2xl p-4 mb-6 flex flex-col sm:flex-row sm:items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-orange-100 flex items-center justify-center flex-shrink-0">
            <i class="fas fa-shield-alt text-orange-600"></i>
        </div>
        <div class="flex-1">
            <p class="text-sm font-semibold text-gray-900">Aktivitas Terakhir</p>
            <p class="text-xs text-gray-600 mt-0.5">
                <span class="font-medium"><?php echo htmlspecialchars($lastRecord['action'] ?? ''); ?></span>
                &nbsp;·&nbsp; <?php echo htmlspecialchars($lastRecord['ip_address'] ?? ''); ?>
                <?php if (!empty($lastRecord['city'])): ?>
                &nbsp;·&nbsp; <i class="fas fa-map-marker-alt text-orange-400"></i> <?php echo htmlspecialchars($lastRecord['city'] . ', ' . $lastRecord['country']); ?>
                <?php endif; ?>
                &nbsp;·&nbsp; <i class="fas fa-clock text-gray-400"></i>
                <?php
                $dt = new DateTime($lastRecord['created_at']);
                echo $dt->format('d M Y, H:i');
                ?>
            </p>
        </div>
        <div class="text-xs text-orange-600 bg-orange-100 px-2.5 py-1 rounded-full font-semibold">
            <i class="fas fa-check-circle mr-1"></i>Akun Aman
        </div>
    </div>
    <?php endif; ?>

    <!-- Filter Panel -->
    <form method="GET" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 mb-6">
        <div class="flex flex-wrap gap-3 items-end">
            <div class="relative flex-1 min-w-[180px]">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                    placeholder="Cari IP, kota, browser..."
                    class="w-full pl-9 pr-3 py-2 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-orange-300">
            </div>
            <select name="filter_action" class="text-sm border border-gray-200 rounded-xl px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-300">
                <option value="">Semua Aksi</option>
                <?php if ($actionList): while ($a = $actionList->fetch_assoc()): ?>
                <option value="<?php echo htmlspecialchars($a['action']); ?>" <?php echo $filter_action === $a['action'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($a['action']); ?>
                </option>
                <?php endwhile; endif; ?>
            </select>
            <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>"
                class="text-sm border border-gray-200 rounded-xl px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-300">
            <span class="text-gray-400 text-sm">—</span>
            <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>"
                class="text-sm border border-gray-200 rounded-xl px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-300">
            <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium rounded-xl px-4 py-2 transition">
                <i class="fas fa-filter mr-1"></i> Filter
            </button>
            <a href="log.php" class="flex items-center px-3 py-2 text-sm text-gray-500 border border-gray-200 rounded-xl hover:bg-gray-50 transition">
                <i class="fas fa-times"></i>
            </a>
        </div>
    </form>

    <!-- Table -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-6">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead>
                    <tr class="bg-gray-50 text-left">
                        <th class="px-4 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider">#</th>
                        <th class="px-4 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider">Aksi</th>
                        <th class="px-4 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider">IP Address</th>
                        <th class="px-4 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider">Lokasi</th>
                        <th class="px-4 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider">Device</th>
                        <th class="px-4 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider">Browser / OS</th>
                        <th class="px-4 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider">Waktu</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="7" class="px-4 py-16 text-center text-gray-400">
                            <i class="fas fa-history text-4xl mb-3 text-gray-200 block"></i>
                            Belum ada log aktivitas
                        </td>
                    </tr>
                <?php else: ?>
                <?php
                $no = $offset + 1;
                foreach ($logs as $row):
                    $action      = htmlspecialchars($row['action']);
                    $actionLower = strtolower($row['action']);
                    $badgeClass  = $actionLower === 'login' ? 'badge-login' : ($actionLower === 'logout' ? 'badge-logout' : 'badge-default');
                    $deviceIcon  = 'fa-desktop';
                    $dType       = strtolower($row['device_type'] ?? '');
                    if ($dType === 'mobile')  $deviceIcon = 'fa-mobile-alt';
                    elseif ($dType === 'tablet') $deviceIcon = 'fa-tablet-alt';
                ?>
                <tr class="log-row">
                    <td class="px-4 py-3 text-gray-400 text-xs"><?php echo $no++; ?></td>
                    <td class="px-4 py-3">
                        <span class="px-2.5 py-1 rounded-full text-xs font-semibold <?php echo $badgeClass; ?>">
                            <?php echo $action; ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 font-mono text-xs text-gray-700"><?php echo htmlspecialchars($row['ip_address'] ?? '—'); ?></td>
                    <td class="px-4 py-3 text-xs text-gray-600">
                        <?php
                        $city    = htmlspecialchars($row['city'] ?? '');
                        $country = htmlspecialchars($row['country'] ?? '');
                        if ($city && $country) echo "<i class='fas fa-map-marker-alt text-orange-400 mr-1'></i>$city, $country";
                        else echo $city ?: ($country ?: '<span class="text-gray-400">—</span>');
                        ?>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-1.5 text-xs text-gray-600">
                            <i class="fas <?php echo $deviceIcon; ?> text-gray-400"></i>
                            <?php echo htmlspecialchars($row['device_type'] ?? '—'); ?>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-600">
                        <div><?php echo htmlspecialchars($row['browser'] ?? '—'); ?></div>
                        <div class="text-gray-400"><?php echo htmlspecialchars($row['os_name'] ?? ''); ?></div>
                    </td>
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
            class="px-3 py-2 text-sm rounded-xl font-medium transition <?php echo $active ? 'bg-orange-500 text-white shadow-sm' : 'border border-gray-200 text-gray-600 hover:bg-gray-50'; ?>">
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
</div>
</body>
</html>
