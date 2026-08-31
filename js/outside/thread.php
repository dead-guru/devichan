<?php

chdir(dirname(__DIR__, 2));
require_once 'inc/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('X-Content-Type-Options: nosniff');

function thread_api_error($status, $message) {
	http_response_code($status);
	echo json_encode(array('error' => $message));
	exit;
}

$boardName = isset($_GET['board']) ? (string) $_GET['board'] : '';
$thread = isset($_GET['thread']) ? (string) $_GET['thread'] : '';

if (!preg_match('/^(?:' . $config['board_regex'] . ')$/u', $boardName)
	|| !preg_match('/^[1-9][0-9]*$/', $thread)) {
	thread_api_error(400, 'Invalid board or thread.');
}

if (isset($config['secret_boards'][$boardName])) {
	thread_api_error(404, 'Thread not found.');
}

if (!openBoard($boardName)) {
	thread_api_error(404, 'Thread not found.');
}

$threadFile = $board['dir'] . $config['dir']['res'] . $thread . '.json';
if (!is_file($threadFile)) {
	thread_api_error(404, 'Thread not found.');
}

header('Cache-Control: public, max-age=30');
readfile($threadFile);
