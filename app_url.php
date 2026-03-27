<?php
// URL helper functions (web paths, not filesystem paths).
// Use these for links and redirects so we never output Windows paths like C:/xampp/...

function app_base_path_from_docroot(): string
{
    // Return the *project* base path (e.g. "/crud"), not the current script directory.
    // This keeps links stable from any page and avoids duplicates like "/crud/admin/admin/index".

    $scriptName = isset($_SERVER['SCRIPT_NAME']) ? (string)$_SERVER['SCRIPT_NAME'] : '';
    if ($scriptName === '' && isset($_SERVER['PHP_SELF'])) {
        $scriptName = (string)$_SERVER['PHP_SELF'];
    }
    if ($scriptName === '') {
        return '';
    }

    $scriptWebDir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
    if ($scriptWebDir === '' || $scriptWebDir === '.' || $scriptWebDir === '/') {
        $scriptWebDir = '';
    }

    $projectRootFs = realpath(__DIR__);
    $scriptFs = isset($_SERVER['SCRIPT_FILENAME']) ? realpath((string)$_SERVER['SCRIPT_FILENAME']) : false;
    if ($projectRootFs === false || $scriptFs === false) {
        // Fallback: best effort based on web dir.
        return $scriptWebDir;
    }

    $projectRootFs = rtrim(str_replace('\\', '/', $projectRootFs), '/');
    $scriptDirFs = rtrim(str_replace('\\', '/', dirname($scriptFs)), '/');
    if ($projectRootFs === '' || strpos($scriptDirFs, $projectRootFs) !== 0) {
        return $scriptWebDir;
    }

    $relDir = trim(substr($scriptDirFs, strlen($projectRootFs)), '/');
    if ($relDir === '') {
        return $scriptWebDir;
    }

    $suffix = '/' . str_replace('\\', '/', $relDir);
    if ($scriptWebDir !== '' && substr($scriptWebDir, -strlen($suffix)) === $suffix) {
        $base = substr($scriptWebDir, 0, -strlen($suffix));
        $base = rtrim((string)$base, '/');
        return ($base === '') ? '' : $base;
    }

    return $scriptWebDir;
}

function app_abs_path(string $path): string
{
    $base = app_base_path_from_docroot();
    $p = '/' . ltrim($path, '/');
    return $base . $p;
}

function app_base_url(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = isset($_SERVER['HTTP_HOST']) ? (string)$_SERVER['HTTP_HOST'] : '';
    if ($host === '') {
        return app_abs_path('');
    }
    return $scheme . '://' . $host . app_abs_path('');
}

// NO CLOSING TAG
