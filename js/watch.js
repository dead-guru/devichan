/*
 * watch.js - board watch, thread watch and board pinning
 * https://github.com/vichan-devel/Tinyboard/blob/master/js/watch.js
 *
 * Released under the MIT license
 * Copyright (c) 2014 Marcin Łabanowski <marcin@6irc.net>
 *
 * Usage:
 *   $config['api']['enabled'] = true;
 *   $config['additional_javascript'][] = 'js/jquery.min.js';
 *   $config['additional_javascript'][] = 'js/mobile-style.js';
 *   //$config['additional_javascript'][] = 'js/titlebar-notifications.js';
 *   //$config['additional_javascript'][] = 'js/auto-reload.js';
 *   //$config['additional_javascript'][] = 'js/hide-threads.js';
 *   //$config['additional_javascript'][] = 'js/compact-boardlist.js';
 *   $config['additional_javascript'][] = 'js/watch.js';
 *
 */

$(function () {
    // migrate from old name
    if (typeof localStorage.watch == "string") {
        localStorage.watch_js = localStorage.watch;
        delete localStorage.watch;
    }

    var window_active = true;
    $(window).focus(function () {
        window_active = true;
        $(window).trigger('scroll');
    });
    $(window).blur(function () {
        window_active = false;
    });

    var status = {};

    time_loaded = Date.now();

    var updating_suspended = false;

    var storage = function () {
        var storage = JSON.parse(localStorage.watch_js !== undefined ? localStorage.watch_js : "{}");
        delete storage.undefined; // fix for some bug
        return storage;
    };

    var storage_save = function (s) {
        localStorage.watch_js = JSON.stringify(s);
    };

    var osize = function (o) {
        var size = 0;
        for (var key in o) {
            if (o.hasOwnProperty(key)) size++;
        }
        return size;
    };

    var is_pinned = function (boardconfig) {
        return boardconfig.pinned || boardconfig.watched || (boardconfig.threads ? osize(boardconfig.threads) : false);
    };
    var is_boardwatched = function (boardconfig) {
        return boardconfig.watched;
    };
    var is_threadwatched = function (boardconfig, thread) {
        return boardconfig && boardconfig.threads && boardconfig.threads[thread];
    };
    var toggle_pinned = function (board) {
        var st = storage();
        var bc = st[board] || {};
        if (is_pinned(bc)) {
            bc.pinned = false;
            bc.watched = false;
            bc.threads = {};
        } else {
            bc.pinned = true;
        }
        st[board] = bc;
        storage_save(st);
        return bc.pinned;
    };
    var toggle_boardwatched = function (board) {
        var st = storage();
        var bc = st[board] || {};
        bc.watched = !is_boardwatched(bc) && Date.now();
        st[board] = bc;
        storage_save(st);
        return bc.watched;
    };
    var toggle_threadwatched = function (board, thread) {
        var st = storage();
        var bc = st[board] || {};
        if (is_threadwatched(bc, thread)) {
            delete bc.threads[thread];
            if (bc.slugs) delete bc.slugs[thread];
        } else {
            bc.threads = bc.threads || {};
            bc.threads[thread] = Date.now();

            bc.slugs = bc.slugs || {};
            bc.slugs[thread] = document.location.pathname + document.location.search;
        }
        st[board] = bc;
        storage_save(st);
        return is_threadwatched(bc, thread);
    };
    var construct_watchlist_for = function (board) {
        var list = $('<span class="cb-menu watch-menu" role="list">').attr('data-board', board);
        var bc = storage()[board];
        list.css({backgroundColor: $('body').css('background-color'), color: $('body').css('color')});

        for (var tid in bc.threads) {
            var count = status[board] && status[board].threads && status[board].threads[tid] || 0;
            var label = count == -404 ? _('Thread not found') : fmt(_('New posts: {0}'), [count]);
            var row = $('<span class="watch-row" role="listitem">').attr('data-thread', tid).appendTo(list);
            var link = $('<a class="watch-link">').attr({
                href: bc.slugs && bc.slugs[tid] || modRoot + board + '/res/' + tid + '.html',
                'data-thread': tid
            }).appendTo(row);
            $('<span class="watch-thread-id">').text('#' + tid).appendTo(link);
            $('<span class="watch-count">').text(count == -404 ? '—' : count)
                .toggleClass('watch-unread', count > 0).attr({title: label, 'aria-label': label}).appendTo(link);
            $('<button type="button" class="watch-remove">').text('×').attr({
                title: _('Stop watching this thread'),
                'aria-label': _('Stop watching this thread') + ' #' + tid
            }).appendTo(row).on('click', function () {
                var row = $(this).closest('.watch-row');
                var tid = row.attr('data-thread');
                var next = row.next().find('button');
                if (!next.length) next = row.prev().find('button');
                toggle_threadwatched(board, tid);
                if (status[board] && status[board].threads) delete status[board].threads[tid];
                row.remove();
                if (active_page == 'thread' && board == board_name && tid == $('input[name="thread"]').val()) {
                    $('#watch-thread a').text(_('Watch this thread'));
                }
                if (next.length) {
                    next.focus();
                } else {
                    var trigger = list.parent().children('a')[0];
                    updating_suspended = false;
                    update_pinned();
                    if (trigger && document.contains(trigger)) trigger.focus();
                }
            });
        }
        return list;
    };

    var update_pinned = function () {
        if (updating_suspended) return;

        if (typeof update_title != "undefined") update_title();

        var bl = $('.boardlist').first();
        $('#watch-pinned, .watch-menu').remove();
        bl.find('.watch-board-link').removeAttr('aria-expanded')
            .removeClass('watch-board-link').css('font-style', '').each(function () {
                $(this).html(this.origtitle);
            }).unwrap();
        var pinned = $('<div id="watch-pinned"></div>').appendTo(bl);

        if (device_type == "desktop")
            bl.off('.watch').on("mouseenter.watch focusin.watch", function () {
                updating_suspended = true;
            }).on("mouseleave.watch focusout.watch", function (e) {
                if (e.relatedTarget && $.contains(this, e.relatedTarget)) return;
                if ($(this).is(':hover') || $.contains(this, document.activeElement)) return;
                updating_suspended = false;
                update_pinned();
            });

        var st = storage();
        for (var i in st) {
            if (is_pinned(st[i])) {
                var link;
                link = bl.find('a').filter(function () {
                    return $(this).attr('href') == modRoot + i + '/' || $(this).attr('href') == modRoot + i + '/index.html';
                }).first();
                if (!link.length) link = $('<a href="' + modRoot + i + '/" class="cb-item cb-cat">/' + i + '/</a>').appendTo(pinned);

                if (link[0].origtitle === undefined) {
                    link[0].origtitle = link.html();
                } else {
                    link.html(link[0].origtitle);
                }

                if (st[i].watched) {
                    link.css("font-weight", "bold");
                    if (status && status[i] && status[i].new_threads) {
                        link.html(link.html() + " (" + status[i].new_threads + ")");
                    }
                } else if (st[i].threads && osize(st[i].threads)) {
                    link.css("font-style", "italic");

                    link.attr("data-board", i);

                    if (status && status[i] && status[i].threads) {
                        var new_posts = 0;
                        for (var tid in status[i].threads) {
                            if (status[i].threads[tid] > 0) {
                                new_posts += status[i].threads[tid];
                            }
                        }
                        if (new_posts > 0) {
                            link.html(link.html() + " (" + new_posts + ")");
                        }
                    }

                    if (device_type == "desktop") {
                        link.addClass('watch-board-link').attr('aria-expanded', 'false')
                            .wrap('<span class="watch-board"></span>');
                        link.parent().on('mouseenter.watch focusin.watch', function () {
                            if ($(this).find('.watch-menu').length) return;
                            $('.cb-menu').remove();
                            var trigger = $(this).children('a').attr('aria-expanded', 'true');
                            var rect = trigger[0].getBoundingClientRect();
                            var wl = construct_watchlist_for(trigger.attr('data-board')).appendTo(this);
                            wl.css({top: rect.bottom, left: Math.max(8, Math.min(rect.left,
                                document.documentElement.clientWidth - wl.outerWidth() - 8)),
                                maxHeight: Math.max(80, window.innerHeight - rect.bottom - 8)});

                            if (typeof init_hover != "undefined")
                                wl.find('a.watch-link').each(init_hover);
                        }).on('mouseleave.watch focusout.watch', function (e) {
                            if (e.relatedTarget && $.contains(this, e.relatedTarget)) return;
                            if ($(this).is(':hover') || $.contains(this, document.activeElement)) return;
                            $(this).children('a').attr('aria-expanded', 'false');
                            $(this).find('.watch-menu').remove();
                        }).on('keydown.watch', function (e) {
                            if (e.key != 'Escape') return;
                            e.preventDefault();
                            $(this).children('a').focus().attr('aria-expanded', 'false');
                            $(this).find('.watch-menu').remove();
                        });
                    }
                }
            }
        }

        if (device_type == "mobile" && (active_page == 'thread' || active_page == 'index')) {
            var board = $('form[name="post"] input[name="board"]').val();
            var boardData = storage()[board];

            $('.watch-menu').remove();

            if (boardData && boardData.threads && osize(boardData.threads)) {
                construct_watchlist_for(board).addClass('watch-menu-mobile').insertAfter('#watch-thread, #watch-board');
            }
        }
    };
    var fetch_jsons = function () {
        if (window_active) check_scroll();

        var st = storage();

        var sched = 0;
        var sched_diff = 2000;

        for (var i in st) {
            if (st[i].watched) {
                (function (i) {
                    setTimeout(function () {
                        var r = $.getJSON(configRoot + i + "/threads.json", function (j, x, r) {
                            handle_board_json(r.board, j);
                        });
                        r.board = i;
                    }, sched);
                    sched += sched_diff;
                })(i);
            } else if (st[i].threads) {
                for (var j in st[i].threads) {
                    (function (i, j) {
                        setTimeout(function () {
                            var r = $.getJSON(configRoot + i + "/res/" + j + ".json", function (k, x, r) {
                                handle_thread_json(r.board, r.thread, k);
                            }).fail(function (jqxhr, textStatus, error) {
                                if (jqxhr.status === 404) handle_thread_404(i, j);
                            });

                            r.board = i;
                            r.thread = j;
                        }, sched);
                    })(i, j);
                    sched += sched_diff;
                }
            }
        }

        setTimeout(fetch_jsons, sched + sched_diff);
    };

    var handle_board_json = function (board, json) {
        var last_thread;

        var new_threads = 0;

        var hidden_data = {};
        if (localStorage.hiddenthreads) {
            hidden_data = JSON.parse(localStorage.hiddenthreads);
        }

        for (var i in json) {
            for (var j in json[i].threads) {
                var thread = json[i].threads[j];

                if (hidden_data[board]) { // hide threads integration
                    var cont = false;
                    for (var k in hidden_data[board]) {
                        if (parseInt(k) == thread.no) {
                            cont = true;
                            break;
                        }
                    }
                    if (cont) continue;
                }

                if (thread.last_modified > storage()[board].watched / 1000) {
                    last_thread = thread.no;

                    new_threads++;
                }
            }
        }

        status = status || {};
        status[board] = status[board] || {};
        if (status[board].last_thread != last_thread || status[board].new_threads != new_threads) {
            status[board].last_thread = last_thread;
            status[board].new_threads = new_threads;
            update_pinned();
        }
    };
    var handle_thread_json = function (board, threadid, json) {
        var bc = storage()[board];
        if (!is_threadwatched(bc, threadid)) return;
        var new_posts = 0;
        for (var i in json.posts) {
            var post = json.posts[i];

            if (post.time > bc.threads[threadid] / 1000) {
                new_posts++;
            }
        }

        status = status || {};
        status[board] = status[board] || {};
        status[board].threads = status[board].threads || {};

        if (status[board].threads[threadid] != new_posts) {
            status[board].threads[threadid] = new_posts;
            update_pinned();
        }
    };
    var handle_thread_404 = function (board, threadid) {
        if (!is_threadwatched(storage()[board], threadid)) return;
        status = status || {};
        status[board] = status[board] || {};
        status[board].threads = status[board].threads || {};
        if (status[board].threads[threadid] != -404) {
            status[board].threads[threadid] = -404; //notify 404
            update_pinned();
        }
    };

    if (active_page == "thread") {
        var board = $('form[name="post"] input[name="board"]').val();
        var thread = $('form[name="post"] input[name="thread"]').val();

        var boardconfig = storage()[board] || {};

        $('hr:first').before('<div id="watch-thread" style="text-align:right"><a class="unimportant" href="javascript:void(0)">-</a></div>');
        $('#watch-thread a').html(is_threadwatched(boardconfig, thread) ? _("Stop watching this thread") : _("Watch this thread")).click(function () {
            $(this).html(toggle_threadwatched(board, thread) ? _("Stop watching this thread") : _("Watch this thread"));
            update_pinned();
        });
    }
    if (active_page == "index") {
        var board = $('form[name="post"] input[name="board"]').val();

        var boardconfig = storage()[board] || {};

        $('hr:first').before('<div id="watch-pin" style="text-align:right"><a class="unimportant" href="javascript:void(0)">-</a></div>');
        $('#watch-pin a').html(is_pinned(boardconfig) ? _("Unpin this board") : _("Pin this board")).click(function () {
            $(this).html(toggle_pinned(board) ? _("Unpin this board") : _("Pin this board"));
            $('#watch-board a').html(is_boardwatched(boardconfig) ? _("Stop watching this board") : _("Watch this board"));
            update_pinned();
        });

        $('hr:first').before('<div id="watch-board" style="text-align:right"><a class="unimportant" href="javascript:void(0)">-</a></div>');
        $('#watch-board a').html(is_boardwatched(boardconfig) ? _("Stop watching this board") : _("Watch this board")).click(function () {
            $(this).html(toggle_boardwatched(board) ? _("Stop watching this board") : _("Watch this board"));
            $('#watch-pin a').html(is_pinned(boardconfig) ? _("Unpin this board") : _("Pin this board"));
            update_pinned();
        });

    }

    var check_post = function (frame, post) {
        return post.length && $(frame).scrollTop() + $(frame).height() >=
            post.position().top + post.height();
    }

    var check_scroll = function () {
        if (!status) return;
        var refresh = false;
        for (var bid in status) {
            if (((status[bid].new_threads && (active_page == "ukko" || active_page == "index")) || status[bid].new_threads == 1)
                && check_post(this, $('[data-board="' + bid + '"]#thread_' + status[bid].last_thread))) {
                var st = storage()
                st[bid].watched = time_loaded;
                storage_save(st);
                refresh = true;
            }
            if (!status[bid].threads) continue;

            for (var tid in status[bid].threads) {
                if (status[bid].threads[tid] && check_post(this, $('[data-board="' + bid + '"]#thread_' + tid))) {
                    var st = storage();
                    st[bid].threads[tid] = time_loaded;
                    storage_save(st);
                    refresh = true;
                }
            }
        }
        return refresh;
    };

    $(window).scroll(function () {
        var refresh = check_scroll();
        if (refresh) {
            //fetch_jsons();
            refresh = false;
        }
    });

    if (typeof add_title_collector != "undefined")
        add_title_collector(function () {
            if (!status) return 0;
            var sum = 0;
            for (var bid in status) {
                if (status[bid].new_threads) {
                    sum += status[bid].new_threads;
                    if (!status[bid].threads) continue;
                    for (var tid in status[bid].threads) {
                        if (status[bid].threads[tid] > 0) {
                            if (auto_reload_enabled && active_page == "thread") {
                                var board = $('form[name="post"] input[name="board"]').val();
                                var thread = $('form[name="post"] input[name="thread"]').val();

                                if (board == bid && thread == tid) continue;
                            }
                            sum += status[bid].threads[tid];
                        }
                    }
                }
            }
            return sum;
        });

    update_pinned();
    fetch_jsons();
});
