<?php
declare(strict_types=1);
function init_locale($locale, $error='error') {
    if (extension_loaded('gettext')) {
        if (setlocale(LC_ALL, $locale) === false) {
            echo('The specified locale (' . $locale . ') does not exist on your platform!');
        }
        bindtextdomain('tinyboard', './inc/locale');
        bind_textdomain_codeset('tinyboard', 'UTF-8');
        textdomain('tinyboard');
    } else {
        if (_setlocale(LC_ALL, $locale) === false) {
            echo('The specified locale (' . $locale . ') does not exist on your platform!');
        }
        _bindtextdomain('tinyboard', './inc/locale');
        _bind_textdomain_codeset('tinyboard', 'UTF-8');
        _textdomain('tinyboard');
    }
}

init_locale('uk_UA.utf8');
echo locale_get_default() . PHP_EOL;
echo gettext("Start a New Thread");
