/*
 * options/general.js - general settings tab for options panel
 *
 * Copyright (c) 2014 Marcin Łabanowski <marcin@6irc.net>
 *
 * Usage:
 *   $config['additional_javascript'][] = 'js/jquery.min.js';
 *   $config['additional_javascript'][] = 'js/options.js';
 *   $config['additional_javascript'][] = 'js/style-select.js';
 *   $config['additional_javascript'][] = 'js/options/general.js';
 */

+function(){

if (!window.Options) return;

var tab = Options.add_tab("general", "fa fa-home", _("General"));

$(function(){
  var menu = $('body > .boardlist:not(.bottom)').first();
  if (menu.length) {
    menu.addClass('top-menu');
    var position = localStorage.top_menu_position;
    var fixed = position === 'fixed' || (position !== 'scroll' && /^(fixed|sticky)$/.test(menu.css('position')));
    var label = $('<label></label>');
    var toggle = $('<input type="checkbox">').prop('checked', fixed).appendTo(label);
    label.append(document.createTextNode(_('Scroll the menu with the page')));
    tab.content.children('h2').after(label);

    function updateMenuHeight() {
      document.documentElement.style.setProperty('--top-menu-height', menu[0].getBoundingClientRect().height + 'px');
    }

    function setMenuPosition(position) {
      document.documentElement.setAttribute('data-top-menu', position);
      updateMenuHeight();
    }

    setMenuPosition(fixed ? 'fixed' : 'scroll');
    new ResizeObserver(updateMenuHeight).observe(menu[0]);
    toggle.on('change', function () {
      localStorage.top_menu_position = this.checked ? 'fixed' : 'scroll';
      setMenuPosition(localStorage.top_menu_position);
    });
  }

  var stor = $('<fieldset></fieldset>').append($('<legend></legend>').text(_('Storage')));
  stor.appendTo(tab.content);
  var actions = $('<div class="options-actions"></div>').appendTo(stor);

  $("<button type='button'>"+_("Export")+"</button>").appendTo(actions).on("click", function() {
    var str = JSON.stringify(localStorage);

    stor.find('.options-output').remove();
    $('<textarea class="options-output" rows="3" readonly></textarea>')
      .attr('aria-label', _('Storage')).appendTo(stor).val(str).trigger('focus').select();
  });
  $("<button type='button'>"+_("Import")+"</button>").appendTo(actions).on("click", function() {
    var dialog = $('<dialog id="options-import" class="ui-dialog" aria-labelledby="options-import-title">');
    var close = $('<button type="button" class="ui-dialog-close">').attr('aria-label', _('Close'));
    var input = $('<textarea id="options-import-data" rows="6" spellcheck="false"></textarea>');
    var form = $('<form class="ui-dialog-body">');
    dialog.append($('<div class="ui-dialog-header">').append(
      $('<h2 id="options-import-title" class="ui-dialog-title">').text(_('Import')), close));
    form.append($('<label for="options-import-data">').text(_('Paste your storage data')), input,
      $('<div class="ui-dialog-actions">').append(
        $('<button type="button">').text(_('Cancel')).on('click', function() { dialog[0].close(); }),
        $('<button type="submit">').text(_('Import'))));
    form.on('submit', function(event) {
      event.preventDefault();
      var str = input.val();
      if (!str) {
        dialog[0].close();
        return;
      }
      var obj;
      try {
        obj = JSON.parse(str);
        if (!obj || typeof obj !== 'object' || Array.isArray(obj) || Object.keys(obj).some(function (key) {
          return typeof obj[key] !== 'string';
        })) throw new Error('Invalid storage');
      } catch (err) {
        alert(_('Invalid storage data.'));
        return;
      }

      localStorage.clear();
      for (var i in obj) {
        localStorage[i] = obj[i];
      }

      document.location.reload();
    });
    dialog.append(form).appendTo('body');
    openDialog(dialog[0]);
    dialog.one('close', function() { dialog.remove(); });
    input.trigger('focus');
  });
  $("<button type='button'>"+_("Erase")+"</button>").appendTo(actions).on("click", function() {
    alert(_("Are you sure you want to erase your storage? This involves your hidden threads, watched threads, post password and many more."), true, function() {
      localStorage.clear();
      document.location.reload();
    });
  });


  var style = $('#style-select').detach().addClass('options-row').css('float', 'none');
  style.find('select').attr('aria-label', _('Style: '));
  tab.content.children('h2').after(style);
});

}();
