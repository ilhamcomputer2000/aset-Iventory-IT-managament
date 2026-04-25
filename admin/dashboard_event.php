<?php
session_start();
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../app_url.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . app_abs_path('login.php'));
    exit();
}

$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : 'user';
if ($user_role !== 'super_admin') {
    header('Location: ' . app_abs_path('admin/index.php'));
    exit();
}

// Gunakan URL public yang statis agar Print Barcode selalu mengarah ke live server (meskipun dibuka dari localhost)
$register_url = 'https://app.cktnosa.com/register_event.php';

// Handle Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $delete_id = intval($_POST['delete_id']);
    // Optional: Get file paths to delete files from server
    $stmt = $conn->prepare("SELECT foto_path, video_path FROM event_finalis WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        if (!empty($row['foto_path']) && file_exists(__DIR__ . '/../' . $row['foto_path'])) {
            unlink(__DIR__ . '/../' . $row['foto_path']);
        }
        if (!empty($row['video_path']) && file_exists(__DIR__ . '/../' . $row['video_path'])) {
            unlink(__DIR__ . '/../' . $row['video_path']);
        }
    }
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM event_finalis WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        $msg_success = "Peserta finalis berhasil dihapus.";
    } else {
        $msg_error = "Gagal menghapus peserta finalis.";
    }
    $stmt->close();
}

// Prepare query to get all event finalists
$sql = "SELECT * FROM event_finalis ORDER BY created_at DESC";
$result = $conn->query($sql);

$activePage = 'event_dashboard';
$Nama_Lengkap = isset($_SESSION['Nama_Lengkap']) ? $_SESSION['Nama_Lengkap'] : 'Admin User';
$Jabatan_Level = isset($_SESSION['Jabatan_Level']) ? $_SESSION['Jabatan_Level'] : '-';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Dashboard - Asset Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
</head>
<body class="bg-gray-50">

    <?php require_once __DIR__ . '/sidebar_admin.php'; ?>

    <div id="main-content-wrapper" class="lg:ml-60 transition-all duration-300 ease-in-out font-sans">
        <script>
            // Sidebar toggle handler for content wrapper
            (function () {
                var wrapper = document.getElementById('main-content-wrapper');
                if (!wrapper) return;
                function applyState() {
                    if (window.innerWidth >= 1024) {
                        var collapsed = localStorage.getItem('sidebarCollapsed') === '1';
                        wrapper.style.marginLeft = collapsed ? '0' : '15rem';
                    } else {
                        wrapper.style.marginLeft = '0';
                    }
                }
                applyState();
                window.addEventListener('sidebarToggled', function (e) { applyState(); });
                window.addEventListener('resize', function () { applyState(); });
            })();
        </script>
        <main class="p-6 lg:p-8 mt-16 lg:mt-0">
            <div class="mt-4 lg:mt-6 mb-8 flex flex-col sm:flex-row justify-between items-start sm:items-center bg-white p-6 rounded-xl shadow-sm border border-gray-100 gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 mb-2">Event Dashboard</h1>
                    <p class="text-gray-500">Kelola pendaftaran peserta finalis acara.</p>
                </div>
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 w-full lg:w-auto mt-4 sm:mt-0">
                    <a href="export_excel.php" class="flex justify-center items-center space-x-2 bg-green-600 hover:bg-green-700 text-white px-5 py-3 rounded-lg font-medium shadow-md transition-all duration-200 transform hover:scale-105 w-full sm:w-auto text-sm">
                        <i class="fas fa-file-excel"></i>
                        <span>Export Excel</span>
                    </a>
                    <a href="download_zip.php?all=1" class="flex justify-center items-center space-x-2 bg-gray-800 hover:bg-gray-900 text-white px-5 py-3 rounded-lg font-medium shadow-md transition-all duration-200 transform hover:scale-105 w-full sm:w-auto text-sm">
                        <i class="fas fa-file-archive"></i>
                        <span>Download (ZIP)</span>
                    </a>
                    <button onclick="openBarcodeModal()" class="flex justify-center items-center space-x-2 bg-gradient-to-r from-orange-500 to-orange-600 hover:from-orange-600 hover:to-orange-700 text-white px-5 py-3 rounded-lg font-medium shadow-md transition-all duration-200 transform hover:scale-105 w-full sm:w-auto text-sm">
                        <i class="fas fa-qrcode"></i>
                        <span>Barcode</span>
                    </button>
                </div>
            </div>

            <?php if (isset($msg_success)): ?>
                <div class="mb-4 bg-green-50 border-l-4 border-green-500 p-4 rounded-r-lg">
                    <p class="text-green-700 font-medium"><i class="fas fa-check-circle mr-2"></i> <?php echo htmlspecialchars($msg_success); ?></p>
                </div>
            <?php endif; ?>
            <?php if (isset($msg_error)): ?>
                <div class="mb-4 bg-red-50 border-l-4 border-red-500 p-4 rounded-r-lg">
                    <p class="text-red-700 font-medium"><i class="fas fa-exclamation-circle mr-2"></i> <?php echo htmlspecialchars($msg_error); ?></p>
                </div>
            <?php endif; ?>

            <!-- TABEL DATA FINALIS -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="p-6 border-b border-gray-100 flex flex-col md:flex-row justify-between items-center gap-4">
                    <h2 class="text-lg font-bold text-gray-800">Daftar Finalis</h2>
                    <div class="flex flex-col sm:flex-row items-center gap-4 w-full md:w-auto">
                        <div class="relative w-full sm:w-64">
                            <input type="text" id="searchInput" placeholder="Cari nama, daftar, atau no. wa..." class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 text-sm transition-all shadow-sm">
                            <i class="fas fa-search absolute left-3 top-2.5 text-gray-400"></i>
                        </div>
                        <div class="flex items-center space-x-2 w-full sm:w-auto shrink-0 bg-gray-50 px-3 py-1.5 rounded-lg border border-gray-200">
                            <label class="text-sm font-medium text-gray-600">Tampil:</label>
                            <select id="pageSize" class="bg-transparent font-medium py-1 focus:outline-none focus:ring-0 text-gray-800 text-sm hover:text-orange-600 cursor-pointer">
                                <option value="10">10</option>
                                <option value="20">20</option>
                                <option value="30">30</option>
                                <option value="100">100</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">No Finalis</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Nama Lengkap</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Kategori</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Umur</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Kota</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Nama PIC</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">No WA</th>
                                <th class="px-6 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Media</th>
                                <th class="px-6 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Waktu Daftar</th>
                                <th class="px-6 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody" class="bg-white divide-y divide-gray-200">
                            <tr><td colspan="11" class="px-6 py-8 text-center text-gray-500"><i class="fas fa-spinner fa-spin mr-2 text-orange-500"></i> Memuat data...</td></tr>
                        </tbody>
                    </table>
                </div>
                <!-- Pagination Footer -->
                <div class="px-6 py-4 border-t border-gray-100 flex flex-col sm:flex-row justify-between items-center gap-4 bg-gray-50/50">
                    <div class="text-sm text-gray-500 font-medium tracking-wide" id="tableInfo">Menampilkan 0 hingga 0 dari 0 data</div>
                    <div class="flex gap-1" id="paginationControls"></div>
                </div>
            </div>
        </main>
    </div>

    <!-- Barcode Modal -->
    <div id="barcodeModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 backdrop-blur-sm opacity-0 pointer-events-none transition-opacity duration-300 hidden">
        <div class="bg-white rounded-2xl shadow-2xl p-8 max-w-md w-full transform scale-95 transition-transform duration-300 mx-4 relative" id="barcodeModalContent">
            
            <button onclick="closeBarcodeModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 hover:bg-gray-100 p-2 rounded-full transition-colors">
                <i class="fas fa-times text-xl"></i>
            </button>
            
            <div class="text-center mb-6">
                <div class="w-16 h-16 bg-orange-100 text-orange-600 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-qrcode text-3xl"></i>
                </div>
                <h3 class="text-2xl font-bold text-gray-900">Scan untuk Mendaftar</h3>
                <p class="text-sm text-gray-500 mt-2">Peserta dapat menscan barcode ini atau mengunjungi URL pendaftaran langsung</p>
            </div>
            
            <div class="flex justify-center mb-6 bg-gray-50 p-4 rounded-xl border border-gray-100">
                <div id="qrcode" class="p-2 bg-white rounded shadow-sm border border-gray-200"></div>
            </div>

            <div class="mb-4">
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">URL Pendaftaran URL</label>
                <div class="flex items-center">
                    <input type="text" readonly value="<?php echo htmlspecialchars($register_url); ?>" id="regUrl" class="w-full text-sm bg-gray-50 border border-gray-200 rounded-l-lg py-2 px-3 text-gray-700 outline-none focus:ring-2 focus:ring-orange-500/20 focus:border-orange-500">
                    <button onclick="copyUrl()" class="bg-orange-50 text-orange-600 hover:bg-orange-100 hover:text-orange-700 border border-l-0 border-gray-200 rounded-r-lg px-4 py-2 font-medium transition-colors" title="Salin URL">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>
            </div>
            
            <div class="flex justify-center gap-3">
                <a href="<?php echo htmlspecialchars($register_url); ?>" target="_blank" class="w-1/2 text-center px-4 py-2 bg-gray-800 text-white rounded-lg hover:bg-gray-900 transition-colors font-medium">Buka Halaman Form</a>
                <button onclick="printBarcode()" class="w-1/2 text-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-medium"><i class="fas fa-print mr-2"></i> Print / PDF</button>
            </div>
        </div>
    </div>

    <script>
        // Barcode Modal logic
        let qrCodeInstance = null;

        function printBarcode() {
            const qrCanvas = document.querySelector('#qrcode canvas');
            if (qrCanvas) {
                const qrImage = qrCanvas.toDataURL("image/png");
                const url = document.getElementById('regUrl').value;
                const printWindow = window.open('', '_blank', 'width=600,height=700');
                if(printWindow) {
                    printWindow.document.write(`
                        <html>
                            <head>
                                <title>Print Barcode Registrasi Event</title>
                                <style>
                                    body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; text-align: center; padding: 40px; margin: 0; color: #333; }
                                    .container { border: 2px dashed #cbd5e1; padding: 40px; display: inline-block; border-radius: 16px; background-color: #fff; }
                                    h2 { margin-top: 0; color: #f97316; font-size: 24px; margin-bottom: 10px; }
                                    p { color: #64748b; font-size: 15px; margin-bottom: 20px; }
                                    img { margin: 20px 0; border: 1px solid #f1f5f9; padding: 10px; border-radius: 10px; display: inline-block; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); }
                                    .url { font-family: monospace; background: #f8fafc; padding: 12px; border-radius: 8px; word-break: break-all; color: #475569; border: 1px solid #e2e8f0; }
                                    @media print {
                                        .no-print { display: none; }
                                        body { padding: 0; }
                                        .container { border: 1px solid #000; box-shadow: none; }
                                    }
                                </style>
                            </head>
                            <body>
                                <div class="container">
                                    <h2>Scan untuk Mendaftar Event</h2>
                                    <p>Gunakan kamera ponsel Anda untuk menscan Barcode di bawah ini</p>
                                    <img src="${qrImage}" alt="QR Code" width="300" height="300">
                                    <p style="margin-top: 10px; margin-bottom: 5px;">Atau kunjungi URL pendaftaran langsung:</p>
                                    <div class="url">${url}</div>
                                </div>
                            </body>
                        </html>
                    `);
                    printWindow.document.close();
                    printWindow.focus();
                    
                    // Allow time for the image to render before printing
                    setTimeout(() => {
                        printWindow.print();
                    }, 500);
                } else {
                    alert("Gagal membuka jendela pop-up. Pastikan popup blocker browser Anda dinonaktifkan.");
                }
            } else {
                alert("QR Code belum ter-generate. Silakan tutup dan buka kembali tombol Generate Barcode.");
            }
        }
        
        function openBarcodeModal() {
            const modal = document.getElementById('barcodeModal');
            const content = document.getElementById('barcodeModalContent');
            const url = document.getElementById('regUrl').value;
            
            modal.classList.remove('hidden');
            // Allow display block to apply before animating opacity
            setTimeout(() => {
                modal.classList.remove('opacity-0', 'pointer-events-none');
                modal.classList.add('opacity-100');
                content.classList.remove('scale-95');
                content.classList.add('scale-100');
            }, 10);

            // Generate QR code if not already generated
            if (!qrCodeInstance) {
                qrCodeInstance = new QRCode(document.getElementById("qrcode"), {
                    text: url,
                    width: 200,
                    height: 200,
                    colorDark : "#000000",
                    colorLight : "#ffffff",
                    correctLevel : QRCode.CorrectLevel.H
                });
            }
        }

        function closeBarcodeModal() {
            const modal = document.getElementById('barcodeModal');
            const content = document.getElementById('barcodeModalContent');
            
            modal.classList.remove('opacity-100');
            modal.classList.add('opacity-0', 'pointer-events-none');
            content.classList.remove('scale-100');
            content.classList.add('scale-95');
            
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 300);
        }

        function copyUrl() {
            const urlInput = document.getElementById('regUrl');
            urlInput.select();
            urlInput.setSelectionRange(0, 99999);
            document.execCommand("copy");
            alert("URL disalin ke clipboard!");
        }

        // --- AJAX Table Logic ---
        const apiUrl = 'api_event_finalis.php';
        const absoluteUrl = '<?php echo app_abs_path(''); ?>';
        let currentPage = 1;

        const searchInput = document.getElementById('searchInput');
        const pageSizeSelect = document.getElementById('pageSize');
        const tableBody = document.getElementById('tableBody');
        const paginationControls = document.getElementById('paginationControls');
        const tableInfo = document.getElementById('tableInfo');

        let debounceTimer;

        searchInput.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                currentPage = 1;
                fetchTableData();
            }, 400);
        });

        pageSizeSelect.addEventListener('change', () => {
            currentPage = 1;
            fetchTableData();
        });

        function fetchTableData() {
            tableBody.innerHTML = `<tr><td colspan="11" class="px-6 py-8 text-center text-gray-500"><i class="fas fa-spinner fa-spin mr-2 text-orange-500"></i> Memuat data...</td></tr>`;
            
            const limit = pageSizeSelect.value;
            const search = encodeURIComponent(searchInput.value.trim());
            const url = `${apiUrl}?page=${currentPage}&limit=${limit}&search=${search}`;

            fetch(url)
                .then(res => res.json())
                .then(response => {
                    if (response.success) {
                        renderTable(response.data);
                        renderPagination(response);
                    } else {
                        tableBody.innerHTML = `<tr><td colspan="11" class="px-6 py-8 text-center text-red-500"><i class="fas fa-exclamation-triangle mr-2"></i> Gagal memuat data.</td></tr>`;
                    }
                })
                .catch(err => {
                    console.error("Fetch error: ", err);
                    tableBody.innerHTML = `<tr><td colspan="11" class="px-6 py-8 text-center text-red-500"><i class="fas fa-wifi mr-2"></i> Koneksi bermasalah.</td></tr>`;
                });
        }

        function escapeHTML(str) {
            if (str === null || str === undefined) return '';
            return String(str).replace(/[&<>'"]/g, 
                tag => ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    "'": '&#39;',
                    '"': '&quot;'
                }[tag])
            );
        }

        function renderTable(data) {
            tableBody.innerHTML = '';
            if (data.length === 0) {
                tableBody.innerHTML = `<tr><td colspan="11" class="px-6 py-8 text-center text-gray-500"><i class="fas fa-folder-open text-3xl mb-3 block opacity-50"></i>Tidak ada data ditemukan.</td></tr>`;
                return;
            }

            data.forEach(row => {
                let mediaHtml = '<div class="flex items-center justify-center space-x-2">';
                if (row.foto_path) {
                    mediaHtml += `<a href="${absoluteUrl + row.foto_path}" target="_blank" class="w-8 h-8 flex items-center justify-center bg-blue-50 text-blue-500 hover:bg-blue-100 hover:text-blue-700 rounded-lg transition-colors" title="Lihat Foto"><i class="fas fa-image"></i></a>`;
                } else {
                    mediaHtml += `<span class="w-8 h-8 flex items-center justify-center text-gray-300 bg-gray-50 rounded-lg"><i class="fas fa-image"></i></span>`;
                }
                if (row.video_path) {
                    mediaHtml += `<a href="${absoluteUrl + row.video_path}" target="_blank" class="w-8 h-8 flex items-center justify-center bg-green-50 text-green-500 hover:bg-green-100 hover:text-green-700 rounded-lg transition-colors" title="Lihat Video"><i class="fas fa-video"></i></a>`;
                } else {
                    mediaHtml += `<span class="w-8 h-8 flex items-center justify-center text-gray-300 bg-gray-50 rounded-lg"><i class="fas fa-video"></i></span>`;
                }
                mediaHtml += '</div>';

                let stColor = 'bg-gray-100 text-gray-800 border-gray-200';
                let stLower = (row.status_pendaftaran || '').toLowerCase();
                
                if (stLower.includes('tersubmit') || stLower.includes('lengkap') || stLower.includes('diterima') || stLower.includes('disetujui')) {
                    if (stLower.includes('tidak lengkap')) {
                        stColor = 'bg-yellow-100 text-yellow-800 border-yellow-200';
                    } else {
                        stColor = 'bg-green-100 text-green-800 border-green-200';
                    }
                } else if (stLower.includes('tolak') || stLower.includes('gagal')) {
                    stColor = 'bg-red-100 text-red-800 border-red-200';
                }

                let dateObj = new Date(row.created_at);
                let formattedDate = dateObj.toLocaleDateString('id-ID', {day: '2-digit', month: 'short', year: 'numeric'}) + ' ' + dateObj.toLocaleTimeString('id-ID', {hour: '2-digit', minute:'2-digit'});

                const tr = document.createElement('tr');
                tr.className = 'hover:bg-gray-50 transition-colors duration-150';
                tr.innerHTML = `
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">${escapeHTML(row.no_finalis)}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 font-medium">${escapeHTML(row.nama_lengkap)}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 font-bold text-orange-600">${escapeHTML(row.kategori)}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">${escapeHTML(row.umur)} th</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">${escapeHTML(row.kota)}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">${escapeHTML(row.nama_pic)}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-600">${escapeHTML(row.no_wa)}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-center">${mediaHtml}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold border ${stColor} shadow-sm">
                            ${escapeHTML(row.status_pendaftaran)}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${formattedDate}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                        <div class="flex items-center justify-center space-x-2">
                            <a href="download_zip.php?id=${row.id}" class="text-green-600 hover:text-green-800 bg-green-50 hover:bg-green-100 p-2 rounded-lg transition-colors" title="Download ZIP">
                                <i class="fas fa-download"></i>
                            </a>
                            <a href="edit_event.php?id=${row.id}" class="text-blue-600 hover:text-blue-800 bg-blue-50 hover:bg-blue-100 p-2 rounded-lg transition-colors" title="Edit Data">
                                <i class="fas fa-edit"></i>
                            </a>
                            <button onclick="deleteParticipant(${row.id})" class="text-red-500 hover:text-red-700 bg-red-50 hover:bg-red-100 p-2 rounded-lg transition-colors" title="Hapus Data">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                `;
                tableBody.appendChild(tr);
            });
        }

        function renderPagination(response) {
            const { page, limit, total, totalPages } = response;
            paginationControls.innerHTML = '';
            
            let startCount = total === 0 ? 0 : ((page - 1) * limit) + 1;
            let endCount = Math.min(page * limit, total);
            tableInfo.innerHTML = `Menampilkan <span class="font-bold text-gray-800">${startCount}</span> hingga <span class="font-bold text-gray-800">${endCount}</span> dari <span class="font-bold text-orange-600">${total}</span> data`;

            if (totalPages <= 1) return;

            let btnClass = "w-8 h-8 flex items-center justify-center rounded-lg text-sm font-medium transition-all duration-200 ";
            
            let prevBtn = document.createElement('button');
            prevBtn.innerHTML = '<i class="fas fa-chevron-left text-xs"></i>';
            prevBtn.className = btnClass + (page <= 1 ? "bg-gray-50 text-gray-400 cursor-not-allowed" : "bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 hover:text-orange-600 shadow-sm");
            if (page > 1) {
                prevBtn.onclick = () => { currentPage--; fetchTableData(); };
            }
            paginationControls.appendChild(prevBtn);

            let startPage = Math.max(1, page - 2);
            let endPage = Math.min(totalPages, page + 2);
            
            for (let i = startPage; i <= endPage; i++) {
                let pBtn = document.createElement('button');
                pBtn.innerText = i;
                if (i === page) {
                    pBtn.className = btnClass + "bg-gradient-to-r from-orange-500 to-orange-600 text-white shadow-md shadow-orange-500/30 transform hover:-translate-y-0.5";
                } else {
                    pBtn.className = btnClass + "bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 hover:text-orange-600 hover:border-orange-200 shadow-sm";
                    pBtn.onclick = () => { currentPage = i; fetchTableData(); };
                }
                paginationControls.appendChild(pBtn);
            }

            let nextBtn = document.createElement('button');
            nextBtn.innerHTML = '<i class="fas fa-chevron-right text-xs"></i>';
            nextBtn.className = btnClass + (page >= totalPages ? "bg-gray-50 text-gray-400 cursor-not-allowed" : "bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 hover:text-orange-600 shadow-sm");
            if (page < totalPages) {
                nextBtn.onclick = () => { currentPage++; fetchTableData(); };
            }
            paginationControls.appendChild(nextBtn);
        }

        function deleteParticipant(id) {
            if (!confirm("Apakah Anda yakin ingin menghapus data peserta ini beserta semua berkasnya?")) return;
            
            fetch(apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete', delete_id: id })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // Cek tabel jika item habis di page terakhir, dec page
                    fetchTableData();
                } else {
                    alert("Gagal menghapus: " + (data.message || 'Error server'));
                }
            })
            .catch(err => {
                alert("Kesalahan jaringan. Gagal terhubung ke API.");
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            fetchTableData();
        });
    </script>
</body>
</html>
