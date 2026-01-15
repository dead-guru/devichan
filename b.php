<?php
$baseDir = realpath(__DIR__ . '/static/banners') ?: (__DIR__ . '/static/banners');
$dir = $baseDir;

if (isset($_GET['board']) && is_string($_GET['board'])) {
    $board = trim($_GET['board']);
    $board = trim($board, "\t\n\r\0\x0B/\\");
    if ($board !== '' && preg_match('/^[A-Za-z0-9_-]+$/', $board)) {
        $candidate = $baseDir . DIRECTORY_SEPARATOR . $board;
        $real = realpath($candidate);
        $baseReal = realpath($baseDir) ?: $baseDir;
        if ($real !== false && is_dir($real) && str_starts_with($real, $baseReal . DIRECTORY_SEPARATOR)) {
            $dir = $real;
        }
    }
}

$allowedExtensions = ['gif', 'jpg', 'jpeg', 'png', 'webp', 'svg', 'apng'];
$entries = @scandir($dir, SCANDIR_SORT_NONE) ?: [];
$images = [];

foreach ($entries as $file) {
    if ($file === '.' || $file === '..' || $file === '' || $file[0] === '.') {
        continue;
    }
    $path = $dir . DIRECTORY_SEPARATOR . $file;
    if (!is_file($path)) {
        continue;
    }
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (in_array($ext, $allowedExtensions, true)) {
        $images[] = $file;
    }
}

if (!$images) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    echo 'No banners available';
    exit;
}

$selected = $images[array_rand($images)];
$path = $dir . DIRECTORY_SEPARATOR . $selected;

$finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
$type = $finfo ? finfo_file($finfo, $path) : null;
if ($finfo) {
    finfo_close($finfo);
}
if (!$type) {
    $ext = strtolower(pathinfo($selected, PATHINFO_EXTENSION));
    $map = [
        'gif' => 'image/gif',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'svg' => 'image/svg+xml',
        'apng' => 'image/apng',
    ];
    $type = $map[$ext] ?? 'application/octet-stream';
}

$bytes = filesize($path);
$lastModified = gmdate('D, d M Y H:i:s', filemtime($path)) . ' GMT';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'");
header('Referrer-Policy: no-referrer');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
header('Cross-Origin-Resource-Policy: same-origin');

header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

header('Content-Type: ' . $type);
header('Content-Length: ' . $bytes);
header('Last-Modified: ' . $lastModified);
$filename = basename($selected);
header('Content-Disposition: inline; filename="' . rawurlencode($filename) . '"');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') {
    exit;
}

$fp = fopen($path, 'rb');
if ($fp === false) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Failed to open banner';
    exit;
}
fpassthru($fp);
fclose($fp);
