<?php
// Get form data if submitted
$formData = [];
if ($_POST) {
    $formData = $_POST;
    // Process form submission here
}

// Generate unique form ID
$formId = 'FORM-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
?>

<div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <!-- Form Header -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6 print:shadow-none print:rounded-none">
            <div class="text-center">
                <h1 class="text-2xl font-bold text-gray-800 mb-2">FORM SERAH TERIMA BARANG</h1>
                <p class="text-sm text-gray-600">PT CIPTA KARYA TECHNOLOGY</p>
                <div class="mt-4 flex justify-between items-center text-sm text-gray-500">
                    <span>Form ID: <?= $formId ?></span>
                    <span>Tanggal: <?= date('d/m/Y H:i') ?></span>
                </div>
            </div>
        </div>

        <!-- Receipt Form -->
        <form id="receiptForm" method="POST" class="space-y-6">
            <!-- Sender Information -->
            <div class="bg-white rounded-lg shadow-lg p-6 print:shadow-none print:rounded-none">
                <h2 class="text-xl font-semibold text-gray-800 mb-4 border-b border-gray-200 pb-2">
                    <i class="fas fa-user-tie text-blue-600 mr-2"></i>
                    Informasi Pengirim
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Nama Pengirim <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="sender_name" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="Masukkan nama pengirim"
                               value="<?= htmlspecialchars($formData['sender_name'] ?? '') ?>">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Jabatan
                        </label>
                        <input type="text" name="sender_position"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="Masukkan jabatan"
                               value="<?= htmlspecialchars($formData['sender_position'] ?? '') ?>">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Departemen
                        </label>
                        <input type="text" name="sender_department"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="Masukkan departemen"
                               value="<?= htmlspecialchars($formData['sender_department'] ?? '') ?>">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            No. Telepon
                        </label>
                        <input type="tel" name="sender_phone"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="Masukkan nomor telepon"
                               value="<?= htmlspecialchars($formData['sender_phone'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <!-- Receiver Information -->
            <div class="bg-white rounded-lg shadow-lg p-6 print:shadow-none print:rounded-none">
                <h2 class="text-xl font-semibold text-gray-800 mb-4 border-b border-gray-200 pb-2">
                    <i class="fas fa-user-check text-green-600 mr-2"></i>
                    Informasi Penerima
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Nama Penerima <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="receiver_name" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="Masukkan nama penerima"
                               value="<?= htmlspecialchars($formData['receiver_name'] ?? '') ?>">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Jabatan
                        </label>
                        <input type="text" name="receiver_position"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="Masukkan jabatan"
                               value="<?= htmlspecialchars($formData['receiver_position'] ?? '') ?>">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Departemen
                        </label>
                        <input type="text" name="receiver_department"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="Masukkan departemen"
                               value="<?= htmlspecialchars($formData['receiver_department'] ?? '') ?>">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            No. Telepon
                        </label>
                        <input type="tel" name="receiver_phone"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="Masukkan nomor telepon"
                               value="<?= htmlspecialchars($formData['receiver_phone'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <!-- Item Details -->
            <div class="bg-white rounded-lg shadow-lg p-6 print:shadow-none print:rounded-none">
                <h2 class="text-xl font-semibold text-gray-800 mb-4 border-b border-gray-200 pb-2">
                    <i class="fas fa-box text-orange-600 mr-2"></i>
                    Detail Barang
                </h2>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Nama Barang <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="item_name" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="Masukkan nama barang"
                               value="<?= htmlspecialchars($formData['item_name'] ?? '') ?>">
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Jumlah <span class="text-red-500">*</span>
                            </label>
                            <input type="number" name="item_quantity" required min="1"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="0"
                                   value="<?= htmlspecialchars($formData['item_quantity'] ?? '') ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Satuan
                            </label>
                            <select name="item_unit"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="">Pilih satuan</option>
                                <option value="pcs" <?= ($formData['item_unit'] ?? '') === 'pcs' ? 'selected' : '' ?>>Pcs</option>
                                <option value="unit" <?= ($formData['item_unit'] ?? '') === 'unit' ? 'selected' : '' ?>>Unit</option>
                                <option value="set" <?= ($formData['item_unit'] ?? '') === 'set' ? 'selected' : '' ?>>Set</option>
                                <option value="box" <?= ($formData['item_unit'] ?? '') === 'box' ? 'selected' : '' ?>>Box</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Kondisi <span class="text-red-500">*</span>
                            </label>
                            <select name="item_condition" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="">Pilih kondisi</option>
                                <option value="baru" <?= ($formData['item_condition'] ?? '') === 'baru' ? 'selected' : '' ?>>Baru</option>
                                <option value="bekas_baik" <?= ($formData['item_condition'] ?? '') === 'bekas_baik' ? 'selected' : '' ?>>Bekas (Baik)</option>
                                <option value="bekas_rusak" <?= ($formData['item_condition'] ?? '') === 'bekas_rusak' ? 'selected' : '' ?>>Bekas (Rusak)</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Keterangan
                        </label>
                        <textarea name="item_description" rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                  placeholder="Masukkan keterangan tambahan"><?= htmlspecialchars($formData['item_description'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Document Types -->
            <div class="bg-white rounded-lg shadow-lg p-6 print:shadow-none print:rounded-none">
                <h2 class="text-xl font-semibold text-gray-800 mb-4 border-b border-gray-200 pb-2">
                    <i class="fas fa-file-alt text-purple-600 mr-2"></i>
                    Dokumen Pendukung
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php
                    $documents = [
                        'invoice' => 'Invoice/Faktur',
                        'receipt' => 'Kwitansi',
                        'delivery_note' => 'Surat Jalan',
                        'warranty' => 'Kartu Garansi',
                        'manual' => 'Manual Book',
                        'other' => 'Lainnya'
                    ];

                    foreach($documents as $key => $label):
                    ?>
                    <label class="flex items-center space-x-3 p-3 rounded-lg border border-gray-200 hover:bg-gray-50 cursor-pointer transition-colors duration-200">
                        <input type="checkbox" name="documents[]" value="<?= $key ?>" 
                               class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 focus:ring-2"
                               <?= in_array($key, $formData['documents'] ?? []) ? 'checked' : '' ?>>
                        <span class="text-sm text-gray-700"><?= $label ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Signature Sections -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Sender Signature -->
                <div class="bg-white rounded-lg shadow-lg p-6 print:shadow-none print:rounded-none">
                    <?php include 'SignaturePad.php'; echo renderSignaturePad('sender', 'Tanda Tangan Pengirim'); ?>
                </div>

                <!-- Receiver Signature -->
                <div class="bg-white rounded-lg shadow-lg p-6 print:shadow-none print:rounded-none">
                    <?php include 'SignaturePad.php'; echo renderSignaturePad('receiver', 'Tanda Tangan Penerima'); ?>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="bg-white rounded-lg shadow-lg p-6 print:hidden">
                <div class="flex flex-col sm:flex-row gap-4 justify-between">
                    <div class="flex flex-col sm:flex-row gap-4">
                        <button type="submit" 
                                class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors duration-200 flex items-center justify-center">
                            <i class="fas fa-save mr-2"></i>
                            Simpan Form
                        </button>
                        
                        <button type="button" onclick="printForm()"
                                class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-colors duration-200 flex items-center justify-center">
                            <i class="fas fa-print mr-2"></i>
                            Print Form
                        </button>
                    </div>
                    
                    <button type="button" onclick="resetForm()"
                            class="px-6 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-colors duration-200 flex items-center justify-center">
                        <i class="fas fa-trash-alt mr-2"></i>
                        Reset Form
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
// Form functionality
function printForm() {
    // Hide non-printable elements
    const printElements = document.querySelectorAll('.print\\:hidden');
    printElements.forEach(el => el.style.display = 'none');
    
    // Print the page
    window.print();
    
    // Restore hidden elements
    printElements.forEach(el => el.style.display = '');
}

function resetForm() {
    if (confirm('Apakah Anda yakin ingin mereset form? Semua data akan hilang.')) {
        document.getElementById('receiptForm').reset();
        
        // Clear signature pads
        clearAllSignatures();
        
        // Show success message
        showNotification('Form berhasil direset', 'info');
    }
}

function clearAllSignatures() {
    // Clear sender signature
    const senderCanvas = document.getElementById('signature-sender');
    if (senderCanvas) {
        const ctx = senderCanvas.getContext('2d');
        ctx.clearRect(0, 0, senderCanvas.width, senderCanvas.height);
    }
    
    // Clear receiver signature
    const receiverCanvas = document.getElementById('signature-receiver');
    if (receiverCanvas) {
        const ctx = receiverCanvas.getContext('2d');
        ctx.clearRect(0, 0, receiverCanvas.width, receiverCanvas.height);
    }
    
    // Reset signature metadata
    document.querySelectorAll('.signature-metadata').forEach(el => {
        el.style.display = 'none';
    });
    
    document.querySelectorAll('.signature-status').forEach(el => {
        el.textContent = '';
    });
}

function showNotification(message, type = 'success') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg max-w-sm transform transition-all duration-300 translate-x-full opacity-0`;
    
    // Set notification style based on type
    const styles = {
        success: 'bg-green-600 text-white',
        error: 'bg-red-600 text-white',
        info: 'bg-blue-600 text-white',
        warning: 'bg-yellow-600 text-white'
    };
    
    notification.className += ` ${styles[type] || styles.success}`;
    
    // Set notification content
    notification.innerHTML = `
        <div class="flex items-center">
            <div class="flex-1">${message}</div>
            <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-white hover:text-gray-200">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    
    // Add to document
    document.body.appendChild(notification);
    
    // Animate in
    setTimeout(() => {
        notification.classList.remove('translate-x-full', 'opacity-0');
        notification.classList.add('translate-x-0', 'opacity-100');
    }, 100);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        notification.classList.add('translate-x-full', 'opacity-0');
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 300);
    }, 5000);
}

// Form validation
document.getElementById('receiptForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Validate required fields
    const requiredFields = this.querySelectorAll('[required]');
    let isValid = true;
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            isValid = false;
            field.classList.add('border-red-500');
            field.focus();
        } else {
            field.classList.remove('border-red-500');
        }
    });
    
    if (!isValid) {
        showNotification('Mohon lengkapi semua field yang wajib diisi', 'error');
        return;
    }
    
    // Check if at least one signature is present
    const senderSigned = checkSignatureExists('sender');
    const receiverSigned = checkSignatureExists('receiver');
    
    if (!senderSigned && !receiverSigned) {
        showNotification('Minimal satu tanda tangan harus diisi', 'warning');
        return;
    }
    
    // If validation passes, submit the form
    showNotification('Form berhasil disimpan!', 'success');
    
    // Here you would normally submit the form data
    // this.submit();
});

function checkSignatureExists(type) {
    const canvas = document.getElementById(`signature-${type}`);
    if (!canvas) return false;
    
    const ctx = canvas.getContext('2d');
    const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
    
    // Check if canvas has any non-transparent pixels
    for (let i = 0; i < imageData.data.length; i += 4) {
        if (imageData.data[i + 3] > 0) {
            return true;
        }
    }
    return false;
}

// Auto-save functionality (optional)
let autoSaveTimer;
function startAutoSave() {
    // Save form data to localStorage every 30 seconds
    autoSaveTimer = setInterval(() => {
        const formData = new FormData(document.getElementById('receiptForm'));
        const data = Object.fromEntries(formData.entries());
        localStorage.setItem('receiptFormData', JSON.stringify(data));
    }, 30000);
}

function loadAutoSavedData() {
    const savedData = localStorage.getItem('receiptFormData');
    if (savedData) {
        try {
            const data = JSON.parse(savedData);
            Object.keys(data).forEach(key => {
                const field = document.querySelector(`[name="${key}"]`);
                if (field) {
                    field.value = data[key];
                }
            });
        } catch (e) {
            console.error('Error loading auto-saved data:', e);
        }
    }
}

// Initialize auto-save when page loads
document.addEventListener('DOMContentLoaded', function() {
    loadAutoSavedData();
    startAutoSave();
});

// Clear auto-save data when form is successfully submitted
function clearAutoSave() {
    localStorage.removeItem('receiptFormData');
    if (autoSaveTimer) {
        clearInterval(autoSaveTimer);
    }
}
</script>