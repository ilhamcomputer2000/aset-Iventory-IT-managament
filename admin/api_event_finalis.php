<?php
session_start();
require_once __DIR__ . '/../koneksi.php';

// Validasi Admin
$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : 'user';
if ($user_role !== 'super_admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

// Handle Delete (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    
    if (isset($input['action']) && $input['action'] === 'delete') {
        $delete_id = intval($input['delete_id'] ?? 0);
        if ($delete_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID tidak valid.']);
            exit;
        }

        // Hapus file fisik jika ada
        $stmt = $conn->prepare("SELECT foto_path, video_path FROM event_finalis WHERE id = ?");
        $stmt->bind_param("i", $delete_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            if (!empty($row['foto_path']) && file_exists(__DIR__ . '/../' . $row['foto_path'])) {
                @unlink(__DIR__ . '/../' . $row['foto_path']);
            }
            if (!empty($row['video_path']) && file_exists(__DIR__ . '/../' . $row['video_path'])) {
                @unlink(__DIR__ . '/../' . $row['video_path']);
            }
        }
        $stmt->close();

        // Lakukan penghapusan data
        $stmt = $conn->prepare("DELETE FROM event_finalis WHERE id = ?");
        $stmt->bind_param("i", $delete_id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Data peserta finalis berhasil dihapus.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal menghapus data di sistem.']);
        }
        $stmt->close();
        exit;
    }
    
    echo json_encode(['success' => false, 'message' => 'Permintaan aksi invalid.']);
    exit;
}

// -----------------------------------------
// Handle Fetching Data Table (GET)
// -----------------------------------------
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
if ($page < 1) $page = 1;

$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
if (!in_array($limit, [10, 20, 30, 100])) {
    $limit = 10;
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$offset = ($page - 1) * $limit;

$whereClause = "WHERE 1=1";
$params = [];
$types = '';

if ($search !== '') {
    $whereClause .= " AND (nama_lengkap LIKE ? OR no_finalis LIKE ? OR kota LIKE ? OR nama_pic LIKE ? OR no_wa LIKE ?)";
    $searchWildcard = '%' . $search . '%';
    $params[] = $searchWildcard;
    $params[] = $searchWildcard;
    $params[] = $searchWildcard;
    $params[] = $searchWildcard;
    $params[] = $searchWildcard;
    $types .= 'sssss';
}

// 1. Calculate Total Match rows
$countSql = "SELECT COUNT(id) as total FROM event_finalis $whereClause";
$countStmt = $conn->prepare($countSql);
if ($types !== '') {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$countResult = $countStmt->get_result();
$totalRecords = $countResult->fetch_assoc()['total'];
$countStmt->close();

// 2. Query Row Set Based on Pagination Offset & Limit
$dataSql = "SELECT * FROM event_finalis $whereClause ORDER BY created_at DESC LIMIT ?, ?";
$dataStmt = $conn->prepare($dataSql);

if ($types !== '') {
    // Inject limit offset types and parameters dynamically since bind_param requires arbitrary amount parameters
    $types .= 'ii';
    $params[] = $offset;
    $params[] = $limit;
    $dataStmt->bind_param($types, ...$params);
} else {
    $dataStmt->bind_param("ii", $offset, $limit);
}

$dataStmt->execute();
$dataResult = $dataStmt->get_result();

$items = [];
while($row = $dataResult->fetch_assoc()) {
    $items[] = $row;
}
$dataStmt->close();

echo json_encode([
    'success' => true,
    'data' => $items,
    'total' => $totalRecords,
    'page' => $page,
    'limit' => $limit,
    'totalPages' => ceil($totalRecords / $limit)
]);
exit;
?>
