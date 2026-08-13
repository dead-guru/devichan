<?php

declare(strict_types=1);

require '/var/www/tests/c3.php';

if (str_starts_with($_SERVER['REQUEST_URI'], '/c3/report')) {
    return;
}

require '/var/www/smart_build.php';
