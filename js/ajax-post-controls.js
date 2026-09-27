/*
 * ajax-post-controls.js
 * https://github.com/savetheinternet/Tinyboard/blob/master/js/ajax-post-controls.js
 *
 * Released under the MIT license
 * Copyright (c) 2013 Michael Save <savetheinternet@tinyboard.org>
 *
 * Usage:
 *   $config['additional_javascript'][] = 'js/jquery.min.js';
 *   $config['additional_javascript'][] = 'js/ajax-post-controls.js';
 *
 */

/* AJAX submission for board controls and individual post dialogs. */
$(function () {
	var selector = 'form[name="postcontrols"], form.post-actions';

	$(document).on('click', 'form[name="postcontrols"] :submit, form.post-actions :submit', function () {
		$(this.form).data('submit-btn', this);
	});

	$(document).on('submit', selector, function (e) {
		if (e.isDefaultPrevented()) return;
		e.preventDefault();
		var form = this;
		var $form = $(form);
		if ($form.data('submitting')) return;
		var submit = (e.originalEvent && e.originalEvent.submitter) || $form.data('submit-btn') || $form.find(':submit')[0];
		if (!submit) return;
		var $submit = $(submit);
		var label = $submit.val();
		var action = submit.name;
		var data = new FormData(form);
		data.append(action, submit.value);
		data.append('json_response', '1');
		var $message = $form.find('.post-action-message');
		if (!$message.length) $message = $('<p class="post-action-message" role="status">').appendTo($form);
		$message.text(_('Working...')).prop('hidden', false);
		$form.data('submitting', true).attr('aria-busy', 'true');
		$submit.prop('disabled', true).val(_('Working...'));

		$.ajax({
			url: form.action,
			type: 'POST',
			data: data,
			dataType: 'json',
			contentType: false,
			processData: false,
			success: function (response) {
				if (response && response.error) {
					$message.text(response.error);
				} else if (response && response.success) {
					if ($form.hasClass('post-actions')) {
						$form.trigger('post-action-success');
					} else if (action === 'report') {
						$message.text(_('Report sent.'));
						$form.find('[name="reason"]').val('');
					} else {
						window.location.reload();
					}
				} else {
					$message.text(_('Could not submit. Try again.'));
				}
			},
			error: function (xhr) {
				$message.text(xhr.responseJSON && xhr.responseJSON.error || _('Could not submit. Try again.'));
			},
			complete: function () {
				$form.removeData('submitting').removeAttr('aria-busy');
				$submit.prop('disabled', false).val(label);
			}
		});
	});
});
