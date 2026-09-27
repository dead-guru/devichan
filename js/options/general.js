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
    var str = prompt(_("Paste your storage data"));
    if (!str) return false;
    var obj;
    try {
      obj = JSON.parse(str);
      if (!obj || typeof obj !== 'object' || Array.isArray(obj) || Object.keys(obj).some(function (key) {
        return typeof obj[key] !== 'string';
      })) throw new Error('Invalid storage');
    } catch (err) {
      alert(_('Invalid storage data.'));
      return false;
    }

    localStorage.clear();
    for (var i in obj) {
      localStorage[i] = obj[i];
    }

    document.location.reload();
  });
  $("<button type='button'>"+_("Erase")+"</button>").appendTo(actions).on("click", function() {
    if (confirm(_("Are you sure you want to erase your storage? This involves your hidden threads, watched threads, post password and many more."))) {
      localStorage.clear();
      document.location.reload();
    }
  });


  var style = $('#style-select').detach().css('float', 'none');
  style.find('select').attr('aria-label', _('Style: '));
  tab.content.children('h2').after(style);
});

}();
