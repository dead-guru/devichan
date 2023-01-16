<?php
declare(strict_types=1);
function init_locale($locale) {
    $res = '';
    putenv("LANG=".$locale);
    if (setlocale(LC_ALL, $locale) === false) {
        echo('The specified locale (' . $locale . ') does not exist on your platform!');
    }
    $res .= bindtextdomain('tinyboard', './inc/locale');
    $res .= PHP_EOL;
    $res .= bind_textdomain_codeset('tinyboard', 'UTF-8');
    $res .= PHP_EOL;
    $res .= textdomain('tinyboard');
    $res .= PHP_EOL;
    $res .= PHP_EOL;
    
    return $res;
}

echo locale_get_default() . PHP_EOL. PHP_EOL;
echo init_locale('uk_UA.utf8');
echo locale_get_default() . PHP_EOL . PHP_EOL;
var_dump($_ENV['HTTP_ACCEPT_LANGUAGE']);
echo gettext("Start a New Thread");
