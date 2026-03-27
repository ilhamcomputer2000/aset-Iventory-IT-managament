<?php
// get_assets_table.php - API untuk mengambil data tabel dan pagination via AJAX

header('Content-Type: application/json; charset=utf-8');

session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die(json_encode(['error' => 'Unauthorized']));
}

// Koneksi database
$conn = @new mysqli("localhost", "root", "", "crud");
if ($conn->connect_error) {
    http_response_code(500);
    die(json_encode(['error' => 'Database connection failed']));
}

$conn->set_charset("utf8mb4");

// Get parameters
$status_filter = isset($_GET['status_filter']) ? trim($_GET['status_filter']) : 'all';
$category_filter = isset($_GET['category_filter']) ? trim($_GET['category_filter']) : 'all';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$page = max(1, $page);

$limit = 10;
$offset = ($page - 1) * $limit;

// Build query dengan prepared statement yang lebih sederhana
$where_parts = array("1=1");

if ($status_filter !== 'all' && !empty($status_filter)) {
    $where_parts[] = "Status_Barang = '" . $conn->real_escape_string($status_filter) . "'";
}

if ($category_filter !== 'all' && !empty($category_filter)) {
    $where_parts[] = "Nama_Barang = '" . $conn->real_escape_string($category_filter) . "'";
}

$where_clause = implode(" AND ", $where_parts);

// Count total records
$count_sql = "SELECT COUNT(*) as total FROM peserta WHERE $where_clause";
$count_result = @$conn->query($count_sql);

if (!$count_result) {
    http_response_code(500);
    die(json_encode(['error' => 'Count query failed: ' . $conn->error]));
}

$count_row = $count_result->fetch_assoc();
$total_filtered = intval($count_row['total']);
$total_pages = $total_filtered > 0 ? ceil($total_filtered / $limit) : 1;

// Ensure page is within range
$page = min($page, $total_pages);

// Get data untuk tabel
$sql = "SELECT id_peserta, Nama_Barang, Merek, Type, Serial_Number, Status_Barang, 
               Kondisi_Barang, User_Perangkat, Photo_Barang 
        FROM peserta 
        WHERE $where_clause
        LIMIT $limit OFFSET $offset";

$result = @$conn->query($sql);

if (!$result) {
    http_response_code(500);
    die(json_encode(['error' => 'Select query failed: ' . $conn->error]));
}

// Generate table HTML
$table_html = '<thead class="bg-gray-200">
                <tr>
                    <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Nama Barang</th>
                    <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Merek</th>
                    <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Serial</th>
                    <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Status</th>
                    <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">User</th>
                    <th class="px-4 py-2 text-center text-sm font-medium text-gray-700">Aksi</th>
                </tr>
              </thead>
              <tbody>';

$row_num = $offset + 1;
while ($row = $result->fetch_assoc()) {
    $status_class = '';
    switch($row['Status_Barang']) {
        case 'READY': $status_class = 'bg-green-100 text-green-800'; break;
        case 'KOSONG': $status_class = 'bg-gray-100 text-gray-800'; break;
        case 'REPAIR': $status_class = 'bg-yellow-100 text-yellow-800'; break;
        case 'TEMPORARY': $status_class = 'bg-blue-100 text-blue-800'; break;
        case 'RUSAK': $status_class = 'bg-red-100 text-red-800'; break;
        default: $status_class = 'bg-gray-100 text-gray-800';
    }
    
    $table_html .= '<tr class="border-b hover:bg-gray-50">
                    <td class="px-4 py-2 text-sm text-gray-800">' . htmlspecialchars($row['Nama_Barang']) . '</td>
                    <td class="px-4 py-2 text-sm text-gray-800">' . htmlspecialchars($row['Merek']) . '</td>
                    <td class="px-4 py-2 text-sm text-gray-800">' . htmlspecialchars($row['Serial_Number']) . '</td>
                    <td class="px-4 py-2 text-sm">
                        <span class="px-2 py-1 rounded-full text-xs font-semibold ' . $status_class . '">
                            ' . htmlspecialchars($row['Status_Barang']) . '
                        </span>
                    </td>
                    <td class="px-4 py-2 text-sm text-gray-800">' . htmlspecialchars($row['User_Perangkat']) . '</td>
                    <td class="px-4 py-2 text-center text-sm">
                        <a href="viewer.php?id=' . $row['id_peserta'] . '" class="text-blue-600 hover:text-blue-800 text-xs">View</a>
                    </td>
                </tr>';
    $row_num++;
}

$table_html .= '</tbody>';

// Generate pagination HTML
$pagination_html = '';
if ($total_pages > 1) {
    $pagination_html .= '<div class="mt-6 flex items-center justify-between">
                          <div class="text-sm text-gray-600">
                            Showing ' . min($offset + 1, $total_filtered) . ' 
                            to ' . min($offset + $limit, $total_filtered) . ' 
                            of ' . $total_filtered . ' results
                          </div>
                          <nav class="inline-flex rounded-md shadow-sm -space-x-px">
                            ';
    
    // Previous button
    if ($page > 1) {
        $prev_url = "?" . http_build_query(array_merge($_GET, ['page' => $page - 1]));
        $pagination_html .= '<a href="' . $prev_url . '" class="relative inline-flex items-center px-3 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                              <i class="fas fa-chevron-left"></i>
                              <span class="ml-1">Prev</span>
                            </a>';
    }
    
    // Page numbers
    $start = max(1, $page - 2);
    $end = min($total_pages, $page + 2);
    
    if ($start > 1) {
        $pagination_html .= '<a href="?page=1" class="relative inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">1</a>';
        if ($start > 2) {
            $pagination_html .= '<span class="relative inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-500">...</span>';
        }
    }
    
    for ($i = $start; $i <= $end; $i++) {
        if ($i == $page) {
            $pagination_html .= '<span class="relative z-10 inline-flex items-center px-3 py-2 border border-orange-500 bg-orange-50 text-sm font-medium text-orange-600">' . $i . '</span>';
        } else {
            $pagination_html .= '<a href="?page=' . $i . '" class="relative inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">' . $i . '</a>';
        }
    }
    
    if ($end < $total_pages) {
        if ($end < $total_pages - 1) {
            $pagination_html .= '<span class="relative inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-500">...</span>';
        }
        $pagination_html .= '<a href="?page=' . $total_pages . '" class="relative inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">' . $total_pages . '</a>';
    }
    
    // Next button
    if ($page < $total_pages) {
        $next_url = "?" . http_build_query(array_merge($_GET, ['page' => $page + 1]));
        $pagination_html .= '<a href="' . $next_url . '" class="relative inline-flex items-center px-3 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                              <span class="mr-1">Next</span>
                              <i class="fas fa-chevron-right"></i>
                            </a>';
    }
    
    $pagination_html .= '</nav>
                        </div>';
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode([
    'table_html' => $table_html,
    'pagination_html' => $pagination_html,
    'current_page' => $page,
    'total_pages' => $total_pages,
    'total_records' => $total_filtered
]);

$conn->close();
?>
