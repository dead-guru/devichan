<?php

chdir(dirname(__DIR__, 2));
require_once 'inc/bootstrap.php';

header('Content-Type: application/javascript; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('X-Content-Type-Options: nosniff');

$requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$siteRoot = dirname($requestPath, 3);
$siteRoot = $siteRoot === '/' ? '/' : rtrim($siteRoot, '/') . '/';

$publicConfig = array(
	'root' => $siteRoot,
	'boardPath' => $config['board_path'],
	'directories' => array(
		'image' => $config['dir']['img'],
		'thumbnail' => $config['dir']['thumb'],
		'thread' => $config['dir']['res'],
	),
	'threadPage' => $config['file_page'],
	'thumbnailExtension' => $config['thumb_ext'],
);

echo 'window.VichanPath = window.VichanPath || [];';
echo 'window.VichanPath.vichan = ' . json_encode(
	$publicConfig,
	JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES
) . ';';
echo file_get_contents(__DIR__ . '/_vichan-path.js');
