<?php
// Include file koneksi
include "../koneksi.php";

// Fungsi untuk mencegah inputan karakter yang tidak sesuai
function input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

if (isset($_GET['id'])) {
    $id = input($_GET['id']);
    $id_escaped = mysqli_real_escape_string($kon, $id);
    
    $query = "SELECT * FROM peserta WHERE id = '$id_escaped'";
    $result = mysqli_query($kon, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
    } else {
        echo "<script>alert('Data tidak ditemukan!'); window.close();</script>";
        exit;
    }
} else {
    echo "<script>alert('ID tidak valid!'); window.close();</script>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Asset - <?php echo htmlspecialchars($row['Nama_Barang']); ?></title>
    <style>
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
            .page-break { page-break-before: always; }
        }
        
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            color: #333;
            background: white;
        }
        
        .header {
            text-align: center;
            border-bottom: 3px solid #f97316;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .header h1 {
            color: #f97316;
            margin: 0;
            font-size: 24px;
        }
        
        .header p {
            margin: 5px 0;
            color: #666;
        }
        
        .asset-info {
            display: grid;
            grid-template-columns: 1fr 200px;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .photo-section {
            text-align: center;
        }
        
        .photo-section img {
            max-width: 180px;
            max-height: 180px;
            border: 2px solid #ddd;
            border-radius: 8px;
            object-fit: cover;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .info-item {
            border-bottom: 1px solid #eee;
            padding-bottom: 8px;
        }
        
        .info-label {
            font-weight: bold;
            color: #f97316;
            font-size: 12px;
            text-transform: uppercase;
        }
        
        .info-value {
            margin-top: 3px;
            font-size: 14px;
        }
        
        .section {
            margin-bottom: 25px;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
        }
        
        .section-title {
            font-size: 16px;
            font-weight: bold;
            color: #f97316;
            margin-bottom: 15px;
            border-bottom: 1px solid #f97316;
            padding-bottom: 5px;
        }
        
        .status-badges {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .badge-ready { background: #dcfce7; color: #166534; }
        .badge-repair { background: #fef3c7; color: #92400e; }
        .badge-rusak { background: #fee2e2; color: #dc2626; }
        .badge-kosong { background: #f3f4f6; color: #374151; }
        .badge-temporary { background: #ede9fe; color: #7c3aed; }
        
        .text-area {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 10px;
            white-space: pre-wrap;
            font-size: 13px;
            line-height: 1.4;
        }
        
        .print-info {
            margin-top: 30px;
            text-align: center;
            border-top: 1px solid #ddd;
            padding-top: 15px;
            font-size: 12px;
            color: #666;
        }
        
        .btn-print {
            background: #f97316;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            margin-bottom: 20px;
        }
        
        .btn-print:hover {
            background: #ea580c;
        }
    </style>
</head>

<body>
    <div class="no-print">
        <button class="btn-print" onclick="window.print()">
            <i class="fas fa-print"></i> Print Dokumen
        </button>
        <button class="btn-print" onclick="window.close()" style="background: #6b7280;">
            Tutup
        </button>
    </div>

    <div class="header">
        <h1>DETAIL ASSET IT</h1>
        <p>Sistem Manajemen Asset IT</p>
        <p>Dicetak pada: <?php echo date('d F Y, H:i:s'); ?></p>
    </div>

    <div class="asset-info">
        <div>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Serial Number</div>
                    <div class="info-value"><?php echo htmlspecialchars($row['Serial_Number']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Waktu Input</div>
                    <div class="info-value"><?php echo date('d/m/Y H:i', strtotime($row['Waktu'])); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Nama Barang</div>
                    <div class="info-value"><?php echo htmlspecialchars($row['Nama_Barang']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Merek</div>
                    <div class="info-value"><?php echo htmlspecialchars($row['Merek']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Type</div>
                    <div class="info-value"><?php echo htmlspecialchars($row['Type']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Lokasi</div>
                    <div class="info-value"><?php echo htmlspecialchars($row['Lokasi']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">ID Karyawan</div>
                    <div class="info-value"><?php echo htmlspecialchars($row['Id_Karyawan']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Jabatan</div>
                    <div class="info-value"><?php echo htmlspecialchars($row['Jabatan']); ?></div>
                </div>
            </div>
        </div>
        
        <div class="photo-section">
            <div class="info-label">Foto Asset</div>
            <?php if (!empty($row['Photo_Barang']) && file_exists($row['Photo_Barang'])): ?>
                <img src="<?php echo htmlspecialchars($row['Photo_Barang']); ?>" alt="Asset Photo">
            <?php else: ?>
                <div style="width: 180px; height: 180px; border: 2px dashed #ddd; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #999; margin: 10px auto;">
                    Tidak ada foto
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Status & Kategori</div>
        <div class="status-badges">
            <span class="badge badge-<?php echo strtolower($row['Status_Barang']); ?>">
                Status: <?php echo htmlspecialchars($row['Status_Barang']); ?>
            </span>
            <span class="badge" style="background: #dbeafe; color: #1e40af;">
                Jenis: <?php echo htmlspecialchars($row['Jenis_Barang']); ?>
            </span>
            <span class="badge" style="background: <?php echo $row['Status_LOP'] == 'LUNAS' ? '#dcfce7; color: #166534' : '#fee2e2; color: #dc2626'; ?>">
                LOP: <?php echo htmlspecialchars($row['Status_LOP']); ?>
            </span>
            <span class="badge" style="background: <?php echo $row['Status_Kelayakan_Barang'] == 'LAYAK' ? '#dcfce7; color: #166534' : '#fee2e2; color: #dc2626'; ?>">
                Kelayakan: <?php echo htmlspecialchars($row['Status_Kelayakan_Barang']); ?>
            </span>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Spesifikasi</div>
        <div class="text-area"><?php echo htmlspecialchars($row['Spesifikasi']); ?></div>
    </div>

    <div class="section">
        <div class="section-title">Kelengkapan Barang</div>
        <div class="text-area"><?php echo htmlspecialchars($row['Kelengkapan_Barang']); ?></div>
    </div>

    <div class="section">
        <div class="section-title">Kondisi Barang</div>
        <div class="text-area"><?php echo htmlspecialchars($row['Kondisi_Barang']); ?></div>
    </div>

    <div class="section">
        <div class="section-title">Riwayat Barang</div>
        <div class="text-area"><?php echo htmlspecialchars($row['Riwayat_Barang']); ?></div>
    </div>

    <div class="section">
        <div class="section-title">User yang Menggunakan Perangkat</div>
        <div class="text-area"><?php echo htmlspecialchars($row['User_Perangkat']); ?></div>
    </div>

    <div class="print-info">
        <p><strong>Dokumen ini digenerate otomatis oleh Sistem Manajemen Asset IT</strong></p>
        <p>Dicetak pada: <?php echo date('d F Y, H:i:s'); ?> | Serial Number: <?php echo htmlspecialchars($row['Serial_Number']); ?></p>
    </div>

    <script>
        // Auto print when page loads (optional)
        // window.onload = function() { window.print(); }
    </script>
</body>
</html>