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
 * The quick reply form (js/quick-reply.js) gets the same switch. Leaving the
 * file mode also empties the js/file-selector.js queue.
 */

$(function() {
  var labels = {file: _('File'), url: _('Remote'), embed: _('Embed'), oekaki: _('Oekaki')};
  // Quick reply mirrors the main form, so all post forms share one mode.
  var mode = 'file';

  var modes = function($form) {
    var list = ['file'];
    if ($form.find('[name="file_url"]').length) list.push('url');
    if ($form.find('[name="embed"]').length) list.push('embed');
    if (window.oekaki && !$form.is('#quick-reply')) list.push('oekaki');
    return list;
  };

  var show = function($form) {
    var m = modes($form).indexOf(mode) < 0 ? 'file' : mode;
    $form.find('#upload').toggle(m === 'file' || m === 'url');
    $form.find('[type="file"], .dropzone-wrap, .add_image, .file_separator').toggle(m === 'file');
    $form.find('[name="file_url"]').closest('#upload_url, tr').toggle(m === 'url');
    $form.find('[name="embed"]').closest('tr').toggle(m === 'embed');
    $form.find('.upload-modes button').each(function() {
      $(this).attr('aria-pressed', String($(this).attr('data-mode') === m));
    });
  };

  // A hidden attachment must not get posted, so switching drops the others.
  var select = function(m) {
    var $forms = $('form[name="post"]');
    mode = m;
    if (m !== 'file') {
      $forms.find('[type="file"]').val('');
      if (window.FileSelector) window.FileSelector.clear();
    }
    if (m !== 'url') $forms.find('[name="file_url"]').val('');
    if (m !== 'embed') $forms.find('[name="embed"]').val('');
    if (window.oekaki) {
      if (m === 'oekaki') window.oekaki.init();
      else if (window.oekaki.initialized) window.oekaki.deinit();
    }
    $forms.each(function() { show($(this)); });
  };

  var decorate = function() {
    var $form = $(this);
    var $upload = $form.find('#upload');
    if (!$upload.length || $form.data('upload-modes')) return;
    $form.data('upload-modes', true);
    // quick reply clones the main form together with its switch
    $form.find('.upload-modes').remove();
    // shown alone in its own mode, so not "Or URL"
    $form.find('[name="file_url"]').attr('placeholder', _('URL'));

    var list = modes($form);
    if (list.length > 1) {
      var $group = $('<div role="group"></div>').attr('aria-label', _('Select'));
      list.forEach(function(m) {
        $('<button type="button"></button>').attr('data-mode', m).text(labels[m]).appendTo($group);
      });
      // match the upload row, quick reply drops the <th> column
      var $row = $('<tr class="upload-modes"></tr>').insertBefore($upload);
      var span = 0;
      $upload.children('td').each(function() { span += this.colSpan; });
      if ($upload.children('th').length) $row.append('<th></th>');
      $('<td></td>').attr('colspan', span).append($group).appendTo($row);
    }
    show($form);
  };

  $(document).on('click', 'form[name="post"] .upload-modes button', function() {
    if ($(this).attr('aria-pressed') !== 'true') select($(this).attr('data-mode'));
  });
  $(window).on('quick-reply', function() {
    $('form[name="post"]').each(decorate);
  });

  $('form[name="post"]').each(decorate);
  select(mode);
});
