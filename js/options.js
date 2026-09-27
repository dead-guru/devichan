/*
 * options.js - allow users choose board options as they wish
 *
 * Copyright (c) 2014 Marcin Łabanowski <marcin@6irc.net>
 *
 * Usage:
 *   $config['additional_javascript'][] = 'js/jquery.min.js';
 *   $config['additional_javascript'][] = 'js/options.js';
 */

+function () {
    var options_button, options_current_tab, previous_focus;
    var options_tabs = {};
    var Options = window.Options = {};

    var first_tab = function () {
        for (var id in options_tabs) return id;
    };

    Options.show = function () {
        if (!options_current_tab && !Options.select_tab(first_tab())) return false;
        previous_focus = document.activeElement;
        options_handler.show();
        $('body').addClass('options-open');
        options_current_tab.icon.focus();
        return false;
    };

    Options.hide = function () {
        options_handler.hide();
        $('body').removeClass('options-open');
        if (previous_focus && document.documentElement.contains(previous_focus)) previous_focus.focus();
    };

    Options.add_tab = function (id, icon, name, content) {
        var tab = {id: id, name: name};
        tab.icon = $('<button type="button" class="options_tab_icon" role="tab"></button>')
            .attr({id: 'options-tab-' + id, 'aria-controls': 'options-panel-' + id, 'aria-selected': 'false', tabindex: -1})
            .append($('<i aria-hidden="true"></i>').addClass(icon), $('<span></span>').text(name));
        tab.content = $('<div class="options_tab" role="tabpanel" tabindex="0"></div>')
            .attr({id: 'options-panel-' + id, 'aria-labelledby': 'options-tab-' + id})
            .hide().appendTo(options_div);
        $('<h2></h2>').text(name).appendTo(tab.content);
        if (content) tab.content.append(content);
        tab.icon.on('click', function () {
            Options.select_tab(id);
        }).appendTo(options_tablist);
        options_tabs[id] = tab;
        if (!options_current_tab) Options.select_tab(id);
        if (options_button) options_button.show();
        return tab;
    };

    Options.get_tab = function (id) {
        return options_tabs[id];
    };

    Options.extend_tab = function (id, content) {
        var tab = options_tabs[id];
        if (!tab) return false;
        tab.content.append(content);
        return tab;
    };

    Options.select_tab = function (id) {
        var tab = options_tabs[id];
        if (!tab) return false;
        if (options_current_tab) {
            options_current_tab.content.hide();
            options_current_tab.icon.removeClass('active').attr({'aria-selected': 'false', tabindex: -1});
        }
        options_current_tab = tab;
        tab.icon.addClass('active').attr({'aria-selected': 'true', tabindex: 0});
        tab.content.show();
        return tab;
    };

    var options_handler = $('<div id="options_handler"></div>').hide();
    $('<div id="options_background"></div>').on('click', Options.hide).appendTo(options_handler);
    var options_div = $('<div id="options_div" role="dialog" aria-modal="true"></div>')
        .attr('aria-label', _('Options')).appendTo(options_handler);
    $('<button type="button" id="options_close">×</button>')
        .attr({'aria-label': _('Close'), title: _('Close')}).on('click', Options.hide).appendTo(options_div);
    var options_tablist = $('<div id="options_tablist" role="tablist"></div>')
        .attr('aria-label', _('Options')).appendTo(options_div);
    var mobile = window.matchMedia('(max-width: 600px)');
    function updateOrientation() {
        options_tablist.attr('aria-orientation', mobile.matches ? 'horizontal' : 'vertical');
    }
    mobile.addEventListener('change', updateOrientation);
    updateOrientation();

    options_tablist.on('keydown', '[role="tab"]', function (event) {
        var tabs = options_tablist.children();
        var index = tabs.index(this);
        switch (event.key) {
            case 'ArrowLeft': case 'ArrowUp': index--; break;
            case 'ArrowRight': case 'ArrowDown': index++; break;
            case 'Home': index = 0; break;
            case 'End': index = tabs.length - 1; break;
            default: return;
        }
        event.preventDefault();
        tabs.eq((index + tabs.length) % tabs.length).trigger('click').focus();
    });

    options_handler.on('keydown', function (event) {
        if (event.key === 'Escape') {
            event.preventDefault();
            event.stopPropagation();
            Options.hide();
        } else if (event.key === 'Tab') {
            var controls = options_div.find('a[href], button, input, select, textarea, [tabindex]')
                .filter(':visible').not(':disabled, [tabindex="-1"]');
            var first = controls[0], last = controls[controls.length - 1];
            if (event.shiftKey && event.target === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && event.target === last) {
                event.preventDefault();
                first.focus();
            }
        }
    });

    $(function () {
        options_button = $('<a id="options_button" href="#options"></a>')
            .attr({title: _('Options'), 'aria-label': _('Options'), 'aria-haspopup': 'dialog'})
            .html('<i class="fa fa-gear" aria-hidden="true"></i>').toggle(!!first_tab());
        if ($('.boardlist.compact-boardlist').length) {
            options_button.addClass('cb-item cb-fa');
        }
        if ($('.boardlist:first').length) options_button.appendTo($('.boardlist:first'));
        else options_button.prependTo(document.body);
        options_button.on('click', Options.show);
        options_handler.appendTo(document.body);
    });
}();
