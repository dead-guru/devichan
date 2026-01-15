<?php
chdir(__DIR__ . '/..');
require 'inc/bootstrap.php';
require_once 'inc/functions.php';

session_name('board_auth');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

$board = $_GET['board'] ?? '';

if (empty($board) || !isset($config['secret_boards'][$board])) {
    http_response_code(200);
    exit;
}

$allowed = $_SESSION['board_auth'] ?? [];
$ttl = $config['secret_boards_ttl'] ?? 86400;

if (isset($allowed[$board]) && (time() - $allowed[$board]) < $ttl) {
    http_response_code(200);
    exit;
}

http_response_code(401);
