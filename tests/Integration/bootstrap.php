<?php

declare(strict_types=1);

chdir(dirname(__DIR__, 2));

$_SERVER['HTTP_HOST'] = 'caddy';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SCRIPT_FILENAME'] = __FILE__;

require 'inc/bootstrap.php';
