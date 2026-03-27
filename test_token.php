<?php
define('ENCRYPT_SALT', 'mySecretKey2024');
function encryptId($id) {
    if (!is_numeric($id) || $id <= 0) return false;
    return base64_encode($id . ':' . md5($id . ENCRYPT_SALT));
}
echo encryptId(457);  // Output: string seperti NzQ3OmFiYzEyMzQ1NmY3ODlhYmNkZWYxMjM0NTY3ODlhYmNk
?>