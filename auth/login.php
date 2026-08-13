<?php
chdir(__DIR__ . '/..');
require 'inc/bootstrap.php';
require_once 'inc/functions.php';

session_name('board_auth');
$secure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

$board = $_GET['board'] ?? $_POST['board'] ?? '';
$redirect = $_GET['redirect'] ?? $_POST['redirect'] ?? "/{$board}/";
$error = false;

if (empty($board) || !isset($config['secret_boards'][$board])) {
    error($config['error']['404']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $valid_passwords = $config['secret_boards'][$board] ?? [];
    
    foreach ($valid_passwords as $hash) {
        if (password_verify($password, $hash)) {
            session_regenerate_id(true);
            $_SESSION['board_auth'][$board] = time();
            header("Location: " . $redirect, true, $config['redirect_http']);
            exit;
        }
    }
    $error = true;
}

$page = Element('page.html', [
    'title' => sprintf(_('Access to /%s/'), $board),
    'config' => $config,
    'boardlist' => createBoardlist(),
    'body' => Element('auth/login.html', [
        'board' => $board,
        'redirect' => htmlspecialchars($redirect, ENT_QUOTES, 'UTF-8'),
        'error' => $error,
        'config' => $config
    ])
]);

echo $page;
