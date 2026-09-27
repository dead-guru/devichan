/*
 * hide-threads.js
 * https://github.com/savetheinternet/Tinyboard/blob/master/js/hide-threads.js
 *
 * Released under the MIT license
 * Copyright (c) 2013 Michael Save <savetheinternet@tinyboard.org>
 * Copyright (c) 2013-2014 Marcin Łabanowski <marcin@6irc.net>
 *
 * Usage:
 *   $config['additional_javascript'][] = 'js/jquery.min.js';
 *   $config['additional_javascript'][] = 'js/hide-threads.js';
 *
 */

/* Hide threads and replies locally; entries expire after seven days. */
$(function () {
	if (!/^(index|ukko|thread)$/.test(window.active_page)) return;

	var hidden = JSON.parse(localStorage.hiddenthreads || '{}');
	var now = Math.floor(Date.now() / 1000);
	Object.keys(hidden).forEach(function (board) {
		Object.keys(hidden[board]).forEach(function (id) {
			if (hidden[board][id] < now - 7 * 86400) delete hidden[board][id];
		});
	});
	localStorage.hiddenthreads = JSON.stringify(hidden);

	function setHidden($post, hide) {
		$post.toggleClass('post-hidden', hide);
		if ($post.hasClass('op')) {
			$post.closest('.thread').toggleClass('hidden_thread', hide && active_page !== 'thread');
			$post.children('.intro').find('.hide-thread-link')
				.text(hide ? '[+]' : '[−]').attr('title', hide ? _('Show') : _('Hide'));
		}
	}

	function toggle($post) {
		var board = $post.closest('.thread').data('board');
		var id = $post.children('.intro').find('input.delete').attr('name').replace('delete_', '');
		var hide = !$post.hasClass('post-hidden');
		if (!hidden[board]) hidden[board] = {};
		if (hide) hidden[board][id] = Math.floor(Date.now() / 1000);
		else delete hidden[board][id];
		localStorage.hiddenthreads = JSON.stringify(hidden);
		setHidden($post, hide);
	}

	function init(root) {
		$(root).find('.post').addBack('.post').each(function () {
			var $post = $(this);
			var $intro = $post.children('.intro');
			var $checkbox = $intro.find('input.delete');
			if (!$checkbox.length) return;
			var board = $post.closest('.thread').data('board');
			var id = $checkbox.attr('name').replace('delete_', '');
			if ($post.hasClass('op') && active_page !== 'thread' && !$intro.find('.hide-thread-link').length) {
				$('<a href="#" class="hide-thread-link">').prependTo($intro);
			}
			setHidden($post, !!(hidden[board] && hidden[board][id]));
		});
	}
	$(document).on('toggle_post', function (e, post) { toggle($(post)); });
	$(document).on('click', '.hide-thread-link', function (e) {
		e.preventDefault();
		toggle($(this).closest('.post'));
	});
	$(document).on('new_post', function (e, post) { init(post); });
	init($('form[name="postcontrols"]'));
});
