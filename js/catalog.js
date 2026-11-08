if (active_page == 'catalog') $(function(){
	if (localStorage.catalog !== undefined) {
		var catalog = JSON.parse(localStorage.catalog);
	} else {
		var catalog = {};
		localStorage.catalog = JSON.stringify(catalog);
	}

	$("#sort_by").change(function(){
		var value = this.value;
		$('#Grid').mixItUp('sort', (value == "random:desc" ? "random" : "sticky:desc " + value));
		catalog.sort_by = value;
		localStorage.catalog = JSON.stringify(catalog);
	});

	$('#Grid').mixItUp({
		layout: {
			containerClass: 'catalog-filterable'
		},
		animation: {
			enable: false
		}
	});

	if (catalog.sort_by !== undefined) {
		$('#sort_by').val(catalog.sort_by).trigger('change');
	}
	$('div.thread').on('click', function(e) {
		if ($(this).css('overflow-y') === 'hidden') {
			$(this).css('overflow-y', 'auto');
			$(this).css('width', '100%');
		} else {
			$(this).css('overflow-y', 'hidden');
			$(this).css('width', 'auto');
		}
	});
});
