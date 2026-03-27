<?php
// Include file koneksi database
require_once '../koneksi.php';

// Set header untuk JSON response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle OPTIONS request for CORS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Function untuk upload file
function uploadFile($file, $target_dir = "uploads/") {
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $target_file = $target_dir . basename($file["name"]);
    $uploadOk = 1;
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    
    // Check if image file is actual image
    $check = getimagesize($file["tmp_name"]);
    if($check === false) {
        return ["success" => false, "message" => "File is not an image."];
    }
    
    // Check file size (2MB max)
    if ($file["size"] > 2000000) {
        return ["success" => false, "message" => "Sorry, your file is too large."];
    }
    
    // Allow certain file formats
    if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif" ) {
        return ["success" => false, "message" => "Sorry, only JPG, JPEG, PNG & GIF files are allowed."];
    }
    
    // Generate unique filename
    $unique_name = uniqid() . "_" . time() . "." . $imageFileType;
    $target_file = $target_dir . $unique_name;
    
    if (move_uploaded_file($file["tmp_name"], $target_file)) {
        return ["success" => true, "filename" => $unique_name, "path" => $target_file];
    } else {
        return ["success" => false, "message" => "Sorry, there was an error uploading your file."];
    }
}

// Function untuk cek serial number
function checkSerialNumber($kon, $serial) {
    $serial = mysqli_real_escape_string($kon, $serial);
    $query = "SELECT COUNT(*) as total FROM data_asset WHERE serial_number = '$serial'";
    $result = mysqli_query($kon, $query);
    
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        return $row['total'] > 0;
    }
    return false;
}

// Function untuk get dropdown options
function getDropdownOptions($kon, $table, $column) {
    $query = "SELECT DISTINCT $column FROM $table WHERE $column IS NOT NULL AND $column != '' ORDER BY $column";
    $result = mysqli_query($kon, $query);
    $options = [];
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $options[] = $row[$column];
        }
    }
    
    return $options;
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Check serial number availability
    if (isset($_POST['action']) && $_POST['action'] === 'check_serial') {
        $serial = trim($_POST['serial']);
        $exists = checkSerialNumber($kon, $serial);
        echo json_encode(['exists' => $exists]);
        exit;
    }
    
    // Get dropdown options
    if (isset($_POST['action']) && $_POST['action'] === 'get_options') {
        $type = $_POST['type'];
        $options = [];
        
        switch($type) {
            case 'namaBarang':
                $options = getDropdownOptions($kon, 'data_asset', 'nama_barang');
                break;
            case 'merek':
                $options = getDropdownOptions($kon, 'data_asset', 'merek');
                break;
            case 'type':
                $options = getDropdownOptions($kon, 'data_asset', 'type');
                break;
            case 'lokasi':
                $options = getDropdownOptions($kon, 'data_asset', 'lokasi');
                break;
            case 'idKaryawan':
                $options = getDropdownOptions($kon, 'karyawan', 'id_karyawan');
                break;
            case 'jabatan':
                $options = getDropdownOptions($kon, 'karyawan', 'jabatan');
                break;
        }
        
        echo json_encode(['options' => $options]);
        exit;
    }
    
    // Save new data asset
    if (isset($_POST['action']) && $_POST['action'] === 'save_asset') {
        try {
            // Validate required fields
            $required_fields = [
                'serial_number', 'nama_barang', 'merek', 'type', 'spesifikasi',
                'kelengkapan_barang', 'lokasi', 'id_karyawan', 'jabatan',
                'kondisi_barang', 'riwayat_barang', 'user_perangkat',
                'jenis_barang', 'status_barang', 'status_lop', 'status_kelayakan'
            ];
            
            foreach ($required_fields as $field) {
                if (empty($_POST[$field])) {
                    throw new Exception("Field $field is required");
                }
            }
            
            // Check if serial number already exists
            if (checkSerialNumber($kon, $_POST['serial_number'])) {
                throw new Exception("Serial number already exists");
            }
            
            // Handle file upload
            $photo_path = '';
            if (isset($_FILES['photo_barang']) && $_FILES['photo_barang']['error'] === UPLOAD_ERR_OK) {
                $upload_result = uploadFile($_FILES['photo_barang']);
                if ($upload_result['success']) {
                    $photo_path = $upload_result['filename'];
                } else {
                    throw new Exception($upload_result['message']);
                }
            } else {
                throw new Exception("Photo is required");
            }
            
            // Escape all inputs
            $data = [];
            foreach ($_POST as $key => $value) {
                if ($key !== 'action') {
                    $data[$key] = mysqli_real_escape_string($kon, $value);
                }
            }
            
            // Insert data to database
            $query = "INSERT INTO data_asset (
                serial_number, nama_barang, merek, type, spesifikasi, kelengkapan_barang,
                lokasi, id_karyawan, jabatan, kondisi_barang, riwayat_barang, user_perangkat,
                jenis_barang, status_barang, status_lop, status_kelayakan, photo_barang,
                created_at, updated_at
            ) VALUES (
                '{$data['serial_number']}', '{$data['nama_barang']}', '{$data['merek']}', 
                '{$data['type']}', '{$data['spesifikasi']}', '{$data['kelengkapan_barang']}',
                '{$data['lokasi']}', '{$data['id_karyawan']}', '{$data['jabatan']}',
                '{$data['kondisi_barang']}', '{$data['riwayat_barang']}', '{$data['user_perangkat']}',
                '{$data['jenis_barang']}', '{$data['status_barang']}', '{$data['status_lop']}',
                '{$data['status_kelayakan']}', '$photo_path', NOW(), NOW()
            )";
            
            if (mysqli_query($kon, $query)) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Data asset berhasil disimpan',
                    'id' => mysqli_insert_id($kon)
                ]);
            } else {
                throw new Exception("Database error: " . mysqli_error($kon));
            }
            
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
        exit;
    }
}

// Handle GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    
    // Get dropdown options
    if (isset($_GET['action']) && $_GET['action'] === 'get_options') {
        $type = $_GET['type'];
        $options = [];
        
        switch($type) {
            case 'namaBarang':
                $options = getDropdownOptions($kon, 'data_asset', 'nama_barang');
                break;
            case 'merek':
                $options = getDropdownOptions($kon, 'data_asset', 'merek');
                break;
            case 'type':
                $options = getDropdownOptions($kon, 'data_asset', 'type');
                break;
            case 'lokasi':
                $options = getDropdownOptions($kon, 'data_asset', 'lokasi');
                break;
            case 'idKaryawan':
                $options = getDropdownOptions($kon, 'karyawan', 'id_karyawan');
                break;
            case 'jabatan':
                $options = getDropdownOptions($kon, 'karyawan', 'jabatan');
                break;
        }
        
        echo json_encode(['options' => $options]);
        exit;
    }
    
    // Check serial number
    if (isset($_GET['action']) && $_GET['action'] === 'check_serial') {
        $serial = trim($_GET['serial']);
        $exists = checkSerialNumber($kon, $serial);
        echo json_encode(['exists' => $exists]);
        exit;
    }
}

// Default response
echo json_encode(['error' => 'Invalid request']);
?>