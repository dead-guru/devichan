<?php
declare(strict_types=1);
require 'inc/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$boards = [];

foreach ($config['index_boards'] as $index_board) {
    $boards = array_merge($boards, $index_board);
}

echo json_encode($boards);
