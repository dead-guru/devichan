<?php

declare(strict_types=1);

chdir(dirname(__DIR__, 2));

$_SERVER['HTTP_HOST'] = 'caddy';
$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/tests/cli-runtime';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['SCRIPT_FILENAME'] = __FILE__;

require 'inc/bootstrap.php';

$scenario = $argv[1] ?? '';

switch ($scenario) {
    case 'login':
        loginForm('Invalid credentials', 'integration-user', '/?/dashboard');
        break;

    case 'basic-error':
        error_reporting(0);
        var_export(verbose_error_handler(E_WARNING, 'suppressed warning', __FILE__, __LINE__));
        error_reporting(E_ALL);
        $config['deprecation_errors'] = false;
        var_export(verbose_error_handler(E_DEPRECATED, 'legacy warning', __FILE__, __LINE__));
        basic_error_function_because_the_other_isnt_loaded_yet('Integration bootstrap error', false);
        break;

    case 'cli-error':
        error('Integration CLI error', false, ['source' => 'e2e']);
        break;

    default:
        fwrite(STDERR, "Unknown runtime scenario.\n");
        exit(2);
}
