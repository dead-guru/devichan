<?php
require 'inc/bootstrap.php';

$dir = "static/404/";
$files = scandir($dir);
$images = array_diff($files, array('.', '..'));
$name = $images[array_rand($images)];

echo Element($config['file_page_template'], [
    'config' => $config,
    'title' => _('404 Not Found'),
    'boardlist' => createBoardlist(),
    'body' => '<img style="margin: 0 auto;max-width: 700px;display: block;" alt="404" src="/static/404/' . $name . '">'
]);
