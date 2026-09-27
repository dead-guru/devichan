/*
 * quick-posts-controls.js
 * https://github.com/savetheinternet/Tinyboard/blob/master/js/quick-posts-controls.js
 *
 * Released under the MIT license
 * Copyright (c) 2012 Michael Save <savetheinternet@tinyboard.org>
 * Copyright (c) 2013 undido <firekid109@hotmail.com>
 * Copyright (c) 2013-2014 Marcin Łabanowski <marcin@6irc.net>
 *
 * Usage:
 *   $config['additional_javascript'][] = 'js/jquery.min.js';
 *   $config['additional_javascript'][] = 'js/quick-post-controls.js';
 *
 */

/* Post actions use the same fields as the board's report/delete form. */
$(function () {
	var $controls = $('form[name="postcontrols"]');
	if (!$controls.length) return;

	function openDialog(action, $post, button) {
		var id = $post.children('.intro').find('input.delete').attr('name');
		var board = $post.closest('.thread').data('board');
		var reporting = action === 'report';
		var title = reporting ? _('Report post No.{0}') : _('Delete post No.{0}');
		var $dialog = $('<dialog class="post-action-dialog" aria-labelledby="post-action-title">');
		var $close = $('<button type="button" class="post-action-close">').text('×').attr('aria-label', _('Close'));
		var $form = $('<form class="post-actions" method="post">').attr('action', $controls.attr('action'));
		var $fields = $('<div class="post-action-fields">');
		var $message = $('<p class="post-action-message" role="status" aria-live="polite" hidden>');
		var $submit = $('<input type="submit">').attr('name', action).val(reporting ? _('Report') : _('Delete'));
		var $cancel = $('<button type="button">').text(_('Cancel'));
		var captchaRequest;

		$form.append($('<input type="hidden" name="board">').val(board),
			$('<input type="hidden">').attr('name', id).val('on'),
			$controls.children('input[name="mod"]').clone());

		if (reporting) {
			var reasons = [_('Spam / advertising'), _('Illegal content'), _('Wrong board'), _('Insults / harassment'), _('Other')];
			var $reasons = $('<fieldset class="post-action-reasons">').attr('aria-label', _('Reason'));
			reasons.forEach(function (reason, i) {
				$reasons.append($('<label>').append(
					$('<input type="radio" name="preset" required>').val(i), ' ', document.createTextNode(reason)));
			});
			var $details = $('<textarea name="details" rows="2" maxlength="250">')
				.attr({placeholder: _('Details (optional)'), 'aria-label': _('Details (optional)')});
			$fields.append($reasons, $details, '<input type="hidden" name="reason">');
			$reasons.on('change', function () {
				var other = $reasons.find(':checked').val() === '4';
				$details.prop('required', other).attr('placeholder', other ? _('Reason') : _('Details (optional)'));
			});
			$form.on('submit', function (e) {
				var preset = $reasons.find(':checked').val();
				var details = $details.val().trim();
				if (preset === undefined || (preset === '4' && !details)) {
					e.preventDefault();
					$message.text(preset === undefined ? _('Choose a reason.') : _('Enter a reason.')).prop('hidden', false);
					return;
				}
				$form.find('input[name="reason"]').val(preset === '4' ? details : reasons[preset] + (details ? ': ' + details : ''));
			});
		} else {
			$fields.append(
				$('<p>').append($('<label>').text(_('Password') + ' ').append(
					$('<input type="password" name="password" size="16" maxlength="18" required autocomplete="current-password">').val(localStorage.password || ''))),
				$('<p>').append($('<label>').append('<input type="checkbox" name="file">', ' ', document.createTextNode(_('File only')))));
		}

		$dialog.append($close, $('<h2 id="post-action-title">').text(fmt(title, [id.replace('delete_', '')])), $form);
		$form.append($fields, $message, $('<div class="post-action-buttons">').append($submit, $cancel));
		$dialog.css('background-color', $('body').css('background-color')).appendTo('body');

		function close() { $dialog[0].close(); }
		$close.add($cancel).on('click', close);
		$dialog.on('click', function (e) {
			if (e.target !== this) return;
			var rect = this.getBoundingClientRect();
			if (e.clientX < rect.left || e.clientX > rect.right || e.clientY < rect.top || e.clientY > rect.bottom) close();
		}).on('close', function () {
			if (captchaRequest) captchaRequest.abort();
			$dialog.remove();
			button.focus();
		});
		$form.on('post-action-success', function () {
			if (reporting) {
				$fields.prop('hidden', true);
				$submit.hide();
				$message.text(_('Report sent.')).prop('hidden', false);
				$cancel.text(_('Close')).focus();
			} else if ($post.hasClass('op') && active_page === 'thread' && !$form.find('[name="file"]').prop('checked')) {
				window.location.href = $('#thread-return').attr('href');
			} else {
				window.location.reload();
			}
		});
		$dialog[0].showModal();
		$fields.find('input, textarea').first().focus();

		// The report page owns CAPTCHA generation, including per-board settings.
		if (reporting) {
			$submit.prop('disabled', true);
			captchaRequest = $.ajax({
				url: configRoot + 'report/',
				data: {board: board, post: id},
				dataType: 'html',
				success: function (html) {
					var $report = $('<div>').append($.parseHTML(html)).find('#report_form');
					if (!$report.length) {
						$message.text(_('Could not load the report form.')).prop('hidden', false);
						return;
					}
					$fields.append($report.find('.report-captcha'));
					$submit.prop('disabled', false);
				},
				error: function (xhr, status) {
					if (status !== 'abort') $message.text(_('Could not load the report form.')).prop('hidden', false);
				}
			});
		}
	}

	function initMenu() {
		Menu.add_item('reply_post_menu', _('Reply'));
		Menu.add_item('hide_post_menu', _('Hide'));
		Menu.add_item('report_menu', _('Report'));
		if ($controls.find('#delete-fields').length) Menu.add_item('delete_post_menu', _('Delete'));
		Menu.onclick(function (e, $menu) {
			var button = e.target;
			var $post = $(button).closest('.post');
			$menu.find('#reply_post_menu').on('click', function () {
				$post.children('.intro').find('a.post_no').last()[0].click();
			});
			$menu.find('#hide_post_menu').text($post.hasClass('post-hidden') ? _('Show') : _('Hide')).on('click', function () {
				$(document).trigger('toggle_post', [$post]);
			});
			$menu.find('#report_menu').on('click', function () { openDialog('report', $post, button); });
			$menu.find('#delete_post_menu').on('click', function () { openDialog('delete', $post, button); });
		});
		$controls.addClass('has-post-menu');
	}
	if (window.Menu) initMenu();
	else $(document).one('menu_ready', initMenu);
});
