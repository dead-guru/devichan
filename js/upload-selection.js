/*
 * upload-selection.js - makes upload fields in post form more compact
 * https://github.com/vichan-devel/Tinyboard/blob/master/js/upload-selection.js
 *
 * Released under the MIT license
 * Copyright (c) 2014 Marcin Łabanowski <marcin@6irc.net>
 *
 * Usage:
 *   $config['additional_javascript'][] = 'js/jquery.min.js';
 *   //$config['additional_javascript'][] = 'js/wpaint.js';
 *   $config['additional_javascript'][] = 'js/upload-selection.js';
 *                                                  
 */

$(function(){
  var enabled_file = true;
  var enabled_url = $("#upload_url").length > 0;
  var enabled_embed = $("#upload_embed").length > 0;
  var enabled_oekaki = typeof window.oekaki != "undefined";

  var select_mode = function(mode) {
    $('#upload_selection button').each(function() {
      $(this).attr('aria-pressed', $(this).data('mode') === mode ? 'true' : 'false');
    });
  };

  var disable_all = function() {
    $("#upload").hide();
    $("[id^=upload_file]").hide();
    $(".file_separator").hide();
    $("#upload_url").hide();
    $("#upload_embed").hide();
    $(".add_image").hide();
    $(".dropzone-wrap").hide();

    $('[id^=upload_file]').each(function(i, v) {
        $(v).val('');
    });

    $("#upload_embed > td > input").val("");

    if (enabled_oekaki) {
      if (window.oekaki.initialized) {
        window.oekaki.deinit();
      }
    }
  };

  enable_file = function() {
    disable_all();
    $("#upload").show();
    $(".dropzone-wrap").show();
    $(".file_separator").show();
    $("[id^=upload_file]").show();
    $(".add_image").show();
    select_mode('file');
  };

  enable_url = function() {
    disable_all();
    $("#upload").show();
    $("#upload_url").show();

    $('#file_url').attr('placeholder', _('URL'));
    select_mode('url');
  };

  enable_embed = function() {
    disable_all();
    $("#upload_embed").show();
    select_mode('embed');
  };

  enable_oekaki = function() {
    disable_all();

    window.oekaki.init();
    select_mode('oekaki');
  };

  if (enabled_url || enabled_embed || enabled_oekaki) {
    $("<tr class='upload-modes'><th></th><td id='upload_selection'></td></tr>").insertBefore("#upload");
    var choices = $('<div role="group"></div>').attr('aria-label', _('Select')).appendTo('#upload_selection');
    var add_choice = function(mode, label, enable) {
      $('<button type="button" aria-pressed="false"></button>')
        .text(label).data('mode', mode).on('click', function() {
          if ($(this).attr('aria-pressed') !== 'true') enable();
        }).appendTo(choices);
    };
    add_choice('file', _('File'), enable_file);
    if (enabled_url) {
      add_choice('url', _('Remote'), enable_url);
    }
    if (enabled_embed) {
      add_choice('embed', _('Embed'), enable_embed);
    }
    if (enabled_oekaki) {
      add_choice('oekaki', _('Oekaki'), enable_oekaki);

      $("#confirm_oekaki_label").hide();
    }
    enable_file();
  }
});
