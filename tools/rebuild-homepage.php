#!/usr/bin/env php
<?php

require __DIR__ . '/inc/cli.php';
require_once 'templates/themes/basic/theme.php';

$opts = getopt('q', ['quiet']);
$quiet = isset($opts['q']) || isset($opts['quiet']);
$query = query("SELECT `name`, `value` FROM ``theme_settings`` WHERE `theme` = 'basic' AND `name` IS NOT NULL");
if (!$query) {
    fwrite(STDERR, "Could not read Basic settings.\n");
    exit(1);
}
$settings = $query->fetchAll(PDO::FETCH_KEY_PAIR);
if (empty($settings['active_homepage'])) {
    if (!$quiet) echo "Active homepage is disabled.\n";
    exit(0);
}
if (!Basic::build('homepage', $settings)) {
    fwrite(STDERR, "Homepage rebuild failed.\n");
    exit(1);
}
if (!$quiet) echo "Homepage rebuilt.\n";
