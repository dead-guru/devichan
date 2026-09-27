/*
 * post-menu.js - adds dropdown menu to posts
 *
 * Creates a global Menu object with four public methods:
 *
 *   Menu.onclick(fnc)
 *     registers a function to be executed after button click, before the menu is displayed
 *   Menu.add_item(id, text[, title])
 *     adds an item to the top level of menu
 *   Menu.add_submenu(id, text)
 *     creates and returns a List object through which to manipulate the content of the submenu
 *   Menu.get_submenu(id)
 *     returns the submenu with the specified id from the top level menu
 *
 *   The List object contains all the methods from Menu except onclick()
 *
 *   Example usage:
 *     Menu.add_item('filter-menu-hide', 'Hide post');
 *     Menu.add_item('filter-menu-unhide', 'Unhide post');
 *
 *     submenu = Menu.add_submenu('filter-menu-add', 'Add filter');
 *         submenu.add_item('filter-add-post-plus', 'Post +', 'Hide post and all replies');
 *         submenu.add_item('filter-add-id', 'ID');
 *  
 * Usage:
 *   $config['additional_javascript'][] = 'js/jquery.min.js';
 *   $config['additional_javascript'][] = 'js/post-menu.js';
 */
$(document).ready(function () {

var List = function (menuId, text) {
	this.id = menuId;
	this.text = text;
	this.items = [];

	this.add_item = function (itemId, text, title) {
		this.items.push(new Item(itemId, text, title));
	};
	this.list_items = function () {
		var array = [];
		var i, length, obj, $ele;

		if ($.isEmptyObject(this.items))
			return;

		length = this.items.length;
		for (i = 0; i < length; i++) {
			obj = this.items[i];

			$ele = $('<li>', {id: obj.id, role: 'menuitem', tabindex: '-1'}).text(obj.text);
			if ('title' in obj) $ele.attr('title', obj.title);

			if (obj instanceof Item) {
				$ele.addClass('post-item');
			} else {
				$ele.addClass('post-submenu');

				$ele.prepend(obj.list_items());
				$ele.append($('<span>', {class: 'post-menu-arrow'}).text('»'));
			}

			array.push($ele);
		}

		return $('<ul role="menu">').append(array);
	};
	this.add_submenu = function (menuId, text) {
		var ele = new List(menuId, text);
		this.items.push(ele);
		return ele;
	};
	this.get_submenu = function (menuId) {
		for (var i = 0; i < this.items.length; i++) {
			if ((this.items[i] instanceof Item) || this.items[i].id != menuId) continue;
			return this.items[i];
		}
	};
};

var Item = function (itemId, text, title) {
	this.id = itemId;
	this.text = text;

	// optional
	if (typeof title != 'undefined') this.title = title;
};

function buildMenu(e) {
	var rect = e.target.getBoundingClientRect();
	var i, length;

	var $menu = $('<div class="post-menu" id="post-menu"></div>').append(mainMenu.list_items());

	//  execute registered click handlers
	length = onclick_callbacks.length;
	for (i = 0; i < length; i++) {
		onclick_callbacks[i](e, $menu);
	}

	$menu.css('background-color', $('body').css('background-color')).appendTo('body');
	var left = Math.max(4, Math.min(rect.left, document.documentElement.clientWidth - $menu.outerWidth() - 4));
	var top = rect.bottom + 2;
	if (top + $menu.outerHeight() > window.innerHeight && rect.top > $menu.outerHeight()) {
		top = rect.top - $menu.outerHeight() - 2;
	}
	$menu.css({top: top + window.pageYOffset, left: left + window.pageXOffset});
	$menu.find('.post-item:visible').first().focus();
}

function addButton(post) {
	$(post).find('.post').addBack('.post').each(function () {
		var $intro = $(this).children('.intro');
		if ($intro.find('.post-btn').length) return;
		$intro.find('a.post_no').last().after(
			$('<button>', {type: 'button', class: 'post-btn', title: _('Post menu'),
				'aria-label': _('Post menu'), 'aria-haspopup': 'menu', 'aria-expanded': 'false', 'aria-controls': 'post-menu'})
				.append($('<i>', {class: 'fa fa-caret-right', 'aria-hidden': 'true'}))
		);
	});
}

function closeMenu(restoreFocus) {
	var $button = $('.post-btn-open');
	$('.post-menu').remove();
	$button.removeClass('post-btn-open').attr('aria-expanded', 'false');
	if (restoreFocus) $button.focus();
}


/* * * * * * * * * *
    Public methods
 * * * * * * * * * */
var Menu = {};
var mainMenu = new List();
var onclick_callbacks = [];

Menu.onclick = function (fnc) {
	onclick_callbacks.push(fnc);
};

Menu.add_item = function (itemId, text, title) {
	mainMenu.add_item(itemId, text, title);
};

Menu.add_submenu = function (menuId, text) {
	return mainMenu.add_submenu(menuId, text);
};

Menu.get_submenu = function (id) {
	return mainMenu.get_submenu(id);
};

window.Menu = Menu;


/* * * * * * * *
    Initialize
 * * * * * * * */

/*  Add buttons
 */
$('.reply:not(.hidden), .thread>.op').each(function () {
	addButton(this);
 });

 /*  event handlers
  */
$('form[name=postcontrols]').on('click', '.post-btn', function (e) {
	e.preventDefault();
	var wasOpen = $(this).hasClass('post-btn-open');
	closeMenu(false);
	if (!wasOpen) {
		$(this).addClass('post-btn-open').attr('aria-expanded', 'true');
		buildMenu(e);
	}
});

$(document).on('click', function (e){
	if ($(e.target).hasClass('post-btn') || $(e.target).hasClass('post-submenu'))
		return;

	closeMenu(false);
});

$(document).on('keydown', '.post-menu, .post-btn', function (e) {
	var $items = $('.post-menu .post-item:visible');
	var index = $items.index(document.activeElement);
	if (e.key === 'Escape') {
		e.preventDefault();
		closeMenu(true);
	} else if (e.key === 'ArrowDown' || e.key === 'ArrowUp' || e.key === 'Home' || e.key === 'End') {
		e.preventDefault();
		if (!$items.length) {
			$(this).trigger('click');
			return;
		}
		var next = e.key === 'Home' ? 0 : e.key === 'End' ? $items.length - 1 :
			(index + (e.key === 'ArrowDown' ? 1 : -1) + $items.length) % $items.length;
		$items.eq(next).focus();
	} else if ((e.key === 'Enter' || e.key === ' ') && $(e.target).hasClass('post-item')) {
		e.preventDefault();
		$(e.target).trigger('click');
	} else if (e.key === 'Tab') {
		closeMenu(true);
	}
});
$(window).on('resize', function () { closeMenu(false); });

// on new posts
$(document).on('new_post', function (e, post) {
	addButton(post);
});

$(document).trigger('menu_ready');
});
