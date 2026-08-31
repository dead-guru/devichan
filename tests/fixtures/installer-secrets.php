<?php

declare(strict_types=1);

$config['db']['type'] = 'mysql';
$config['db']['server'] = getenv('MYSQL_HOST') ?: 'cmysql';
$config['db']['user'] = getenv('MYSQL_USER') ?: 'devichan_installer_e2e';
$config['db']['password'] = getenv('MYSQL_PASSWORD') ?: 'devichan_installer_e2e';
$config['db']['database'] = getenv('MYSQL_DATABASE') ?: 'devichan_installer_e2e';

$config['cookies']['salt'] = 'devichan-installer-e2e-cookie-salt';
$config['has_installed'] = 'tests/_output/installer.installed';
$config['board_path'] = 'tests/_output/installer-site/%s/';
$config['file_script'] = 'tests/_output/installer-site/main.js';
$config['cache']['enabled'] = false;
$config['cache_config'] = false;
$config['debug'] = false;
$config['syslog'] = false;
$config['referer_match'] = false;
$config['purge'] = [];
