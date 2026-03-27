<?php

function token_base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function token_base64url_decode($data) {
    $b64 = strtr((string)$data, '-_', '+/');
    $pad = strlen($b64) % 4;
    if ($pad) {
        $b64 .= str_repeat('=', 4 - $pad);
    }
    return base64_decode($b64, true);
}

function token_get_secret() {
    $tempDir = __DIR__ . '/temp';
    if (!is_dir($tempDir)) {
        @mkdir($tempDir, 0777, true);
    }

    // Reuse secret created by qr_print.php if available
    $secretFile = $tempDir . '/qr_token_secret.php';
    if (is_file($secretFile)) {
        $loaded = include $secretFile;
        if (is_string($loaded) && $loaded !== '') {
            return $loaded;
        }
    }

    $secret = bin2hex(random_bytes(32));
    $content = "<?php\nreturn " . var_export($secret, true) . ";\n";
    @file_put_contents($secretFile, $content, LOCK_EX);
    return $secret;
}

function token_make_id($idPeserta, $ttlSeconds) {
    if (!function_exists('hash_hmac') || !function_exists('hash')) {
        return '';
    }
    if (!function_exists('random_bytes')) {
        return '';
    }
    if (!function_exists('openssl_encrypt')) {
        return '';
    }

    $id = (int)$idPeserta;
    if ($id <= 0) {
        return '';
    }

    $secret = token_get_secret();
    $key = hash('sha256', $secret, true);
    $iv = random_bytes(16);

    $payload = json_encode([
        'id' => $id,
        'iat' => time(),
        'exp' => time() + (int)$ttlSeconds,
    ], JSON_UNESCAPED_SLASHES);

    $ciphertext = openssl_encrypt($payload, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    if ($ciphertext === false) {
        return '';
    }

    $mac = hash_hmac('sha256', $iv . $ciphertext, $key, true);
    return token_base64url_encode($iv . $ciphertext . $mac);
}

function token_parse_id_payload($token, &$error = '') {
    $error = '';
    if (!function_exists('hash_hmac') || !function_exists('hash')) {
        $error = 'Fitur token tidak tersedia (hash).';
        return null;
    }
    if (!function_exists('openssl_decrypt')) {
        $error = 'Fitur token tidak tersedia (OpenSSL).';
        return null;
    }

    $raw = token_base64url_decode($token);
    if ($raw === false) {
        $error = 'Token tidak valid.';
        return null;
    }

    if (strlen($raw) < (16 + 32 + 1)) {
        $error = 'Token terlalu pendek.';
        return null;
    }

    $secret = token_get_secret();
    $key = hash('sha256', $secret, true);

    $iv = substr($raw, 0, 16);
    $mac = substr($raw, -32);
    $ciphertext = substr($raw, 16, -32);

    $expected = hash_hmac('sha256', $iv . $ciphertext, $key, true);
    if (!hash_equals($expected, $mac)) {
        $error = 'Token signature tidak cocok.';
        return null;
    }

    $plaintext = openssl_decrypt($ciphertext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    if ($plaintext === false || $plaintext === '') {
        $error = 'Token gagal didekripsi.';
        return null;
    }

    $payload = json_decode($plaintext, true);
    if (!is_array($payload)) {
        $error = 'Token payload tidak valid.';
        return null;
    }

    $id = (int)($payload['id'] ?? 0);
    $exp = (int)($payload['exp'] ?? 0);
    if ($id <= 0) {
        $error = 'ID tidak valid.';
        return null;
    }
    if ($exp > 0 && time() > $exp) {
        $error = 'Token sudah expired.';
        return null;
    }

    return $payload;
}

function token_current_script_url(array $query) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path = $_SERVER['SCRIPT_NAME'] ?? '';
    $qs = http_build_query($query);
    return $scheme . '://' . $host . $path . ($qs !== '' ? ('?' . $qs) : '');
}
