/*
 * catalog-link.js - Adds catalog links to the board navigation.
 * https://github.com/vichan-devel/Tinyboard/blob/master/js/catalog-link.js
 *
 * Released under the MIT license
 * Copyright (c) 2013 copypaste <wizardchan@hush.com>
 * Copyright (c) 2013-2014 Marcin Łabanowski <marcin@6irc.net>
 *
 * Usage:
 *   $config['additional_javascript'][] = 'js/jquery.min.js';
 *   $config['additional_javascript'][] = 'js/catalog-link.js';
 */

function catalog() {
    var board = $('input[name="board"]').first().val();
    var url = configRoot + board + '/catalog.html';
    $('#thread-links_header, #thread-links').each(function () {
        if ($(this).find('.catalog-link').length) return;
        $('<a class="catalog-link"><i class="fa-solid fa-grip" aria-hidden="true"></i> </a>')
            .attr('href', url).append(document.createTextNode(_('Catalog'))).appendTo(this);
    });
}

if (active_page == 'thread' || active_page == 'index') {
    $(document).ready(catalog);
}
