/*
 * catalog-search.js - filter the catalog by subject and comment
 *
 * Usage:
 *   $config['additional_javascript'][] = 'js/jquery.min.js';
 *   $config['additional_javascript'][] = 'js/catalog-search.js';
 *
 * The catalog theme supplies jquery.mixitup.min.js and catalog.js.
 */
if (active_page == 'catalog') {
	onready(function () {
		'use strict';

		var grid = $('#Grid');
		var timeout;
		var threads = grid.children('.mix').map(function () {
			var text = $(this).find('.replies').clone();
			text.children('strong').remove();
			return { node: this, text: text.text().toLowerCase() };
		}).get();
		var input = $('<input type="search" id="search_field">')
			.attr({ 'aria-label': _('Search'), placeholder: _('Search') });
		$('<label class="catalog_search"></label>').append(input).appendTo('.catalog-controls');

		function filter() {
			var term = input.val().trim().toLowerCase();
			threads.forEach(function (thread) {
				$(thread.node).toggleClass('catalog-match', thread.text.indexOf(term) !== -1);
			});
			grid.mixItUp('filter', term ? '.catalog-match' : 'all');
		}

		input.on('input', function () {
			window.clearTimeout(timeout);
			if (!this.value) filter();
			else timeout = window.setTimeout(filter, 150);
		});
		input.on('keydown', function (event) {
			if (event.key === 'Escape') {
				event.preventDefault();
				window.clearTimeout(timeout);
				input.val('');
				filter();
				this.blur();
			}
		});
		$('body').on('keydown', function (event) {
			if (event.key.toLowerCase() === 's' && event.target.tagName === 'BODY' &&
				!(event.ctrlKey || event.altKey || event.metaKey || event.shiftKey)) {
				event.preventDefault();
				input.trigger('focus');
			}
		});
	});
}
