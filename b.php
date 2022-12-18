<?php

function endsWith($haystack, $needles)
{
    if (!is_iterable($needles)) {
        $needles = (array)$needles;
    }
    
    foreach ($needles as $needle) {
        if ((string)$needle !== '' && str_ends_with($haystack, $needle)) {
            return true;
        }
    }
    
    return false;
}

$dir = 'static/banners/';

if (array_key_exists('board', $_GET) && is_dir($dir . ltrim($_GET['board'], '/'))) {
    $dir .= ltrim($_GET['board'], '/');
}

$images = array_filter(array_diff(scandir($dir), ['.', '..']), static function ($file) {
    return endsWith($file, ['.gif', '.jpg', '.jpeg', '.png', '.webp', '.svg', '.apng']);
});

$name = $images[array_rand($images)];
$fp = fopen($dir . $name, 'rb');
$type = mime_content_type($dir . $name);

$bytes = filesize($dir . $name);

header('Cache-Control: no-cache, no-store, must-revalidate'); // HTTP 1.1
header('Pragma: no-cache'); // HTTP 1.0
header('Expires: 0'); // Proxies
header('Content-Type: ' . $type);
header('Content-Length: ' . $bytes);

fpassthru($fp);
