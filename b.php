<?php
$dir = "static/banners/";
$files = scandir($dir);
$images = array_diff($files, array('.', '..'));
$name = $images[array_rand($images)];
// open the file in a binary mode
$fp = fopen($dir . $name, 'rb');
$type = mime_content_type($dir . $name);

$bytes = filesize($dir . $name);
// send the right headers
header('Cache-Control: no-cache, no-store, must-revalidate'); // HTTP 1.1
header('Pragma: no-cache'); // HTTP 1.0
header('Expires: 0'); // Proxies
header('Content-Type: ' . $type);
header('Content-Length: ' . $bytes);

// dump the picture and stop the script
fpassthru($fp);
exit;
?>
