<?php
session_start();
if ($_SESSION['role'] !== 'super_admin') {
    header("Location: dashboard_admin.php");
    exit();
}
$conn = new mysqli("localhost:3306", "cktnosa2_admin", "uGXj8#eiI=P%", "cktnosa2_crud");
$result = $conn->query("SELECT * FROM user_logs ORDER BY created_at DESC LIMIT 100");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Activity Log</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-6">
    <h1 class="text-2xl font-bold mb-4">User Activity Log</h1>
    <table class="min-w-full bg-white border">
        <thead><tr>
            <th class="border px-4 py-2">Time</th>
            <th class="border px-4 py-2">User</th>
            <th class="border px-4 py-2">Action</th>
            <th class="border px-4 py-2">IP</th>
        </tr></thead>
        <tbody>
        <?php while ($row = $result->fetch_assoc()): ?>
        <tr>
            <td class="border px-4 py-2"><?= htmlspecialchars($row['created_at']) ?></td>
            <td class="border px-4 py-2"><?= htmlspecialchars($row['full_name'] ?: $row['username']) ?></td>
            <td class="border px-4 py-2"><?= htmlspecialchars($row['action']) ?></td>
            <td class="border px-4 py-2 text-xs"><?= htmlspecialchars($row['ip_address']) ?></td>
        </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</body>
</html>