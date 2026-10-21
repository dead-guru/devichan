<?php
chdir(dirname(__DIR__, 2));
require_once __DIR__ . '/../bootstrap.php';

$status = http_response_code();
// A database outage is not a rejected challenge.
http_response_code(500);
sql_open();
http_response_code($status ?: 200);

$expires_in = 120;
$width = 250;
$height = 80;
$length = 6;
