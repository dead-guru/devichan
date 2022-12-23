/*
* youtube
* https://github.com/savetheinternet/Tinyboard/blob/master/js/youtube.js
*
* Don't load the YouTube player unless the video image is clicked.
* This increases performance issues when many videos are embedded on the same page.
* Currently only compatiable with YouTube.
*
* Proof of concept.
*
* Released under the MIT license
* Copyright (c) 2013 Michael Save <savetheinternet@tinyboard.org>
* Copyright (c) 2013-2014 Marcin Łabanowski <marcin@6irc.net>
*
* Usage:
*	$config['embedding'] = array();
*	$config['embedding'][0] = array(
*		'/^https?:\/\/(\w+\.)?(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9\-_]{10,11})(&.+)?$/i',
*		$config['youtube_js_html']);
*   $config['additional_javascript'][] = 'js/jquery.min.js';
*   $config['additional_javascript'][] = 'js/youtube.js';
*
*/

//YT auto play
$(document).ready(function () {
    if (window.Options && Options.get_tab('general')) {
        Options.extend_tab("general", "<span id='youtube-size'>" + _('YouTube size') + ": <input type='number' id='youtube-width' value='360'>x<input type='number' id='youtube-height' value='270'>");

        if (typeof localStorage.youtube_size === 'undefined') {
            localStorage.youtube_size = '{"width":360,"height":270}';
            var our_yt = JSON.parse(localStorage.youtube_size);
        } else {
            var our_yt = JSON.parse(localStorage.youtube_size);
            $('#youtube-height').val(our_yt.height);
            $('#youtube-width').val(our_yt.width);
        }


        $('#youtube-width, #youtube-height').on('change', function () {
            if ($(this).attr('id') === 'youtube-height') {
                our_yt.height = $(this).val();
            } else {
                our_yt.width = $(this).val();
            }

            localStorage.youtube_size = JSON.stringify(our_yt);
        });
    }

    var do_embed_yt = function (tag) {
        if (typeof our_yt === "undefined") {
            our_yt = {"width": 360, "height": 270};
        }

        $('div.video-container a', tag).click(function () {
            $(this.parentNode).append('<iframe style="float:left;margin: 10px 20px" type="text/html" ' +
                'width="' + our_yt.width + '" height="' + our_yt.height + '" src="https://www.youtube.com/embed/' + $(this.parentNode).data('video') +
                '?autoplay=1&html5=1' + $(this.parentNode).data('params') + '" allowfullscreen frameborder="0"/>');
            $(this).remove();
            return false;
        });
        /*Vidme*/
        $('div.video-container-vidme a', tag).click(function () {
            $(this.parentNode).append('<iframe style="float:left;margin: 10px 20px" type="text/html" ' +
                'width="' + our_yt.width + '" height="' + our_yt.height + '" src="https://www.vid.me/e/' + $(this.parentNode).data('video') +
                '?stats=1" allowfullscreen frameborder="0"/>');
            $(this).remove();
            return false;
        });

        /*TGW*/
        $('div.video-container-tgw1 a', tag).click(function () {
            $(this.parentNode).append('<iframe style="float:left;margin: 10px 20px" type="text/html" ' +
                'width="' + our_yt.width + '" height="' + our_yt.height + '" src="https://video1.thegoldwater.com/api/player.php?id=' + $(this.parentNode).data('video') +
                '" allowfullscreen frameborder="0"/>');
            $(this).remove();
            return false;
        });

        /*TGW*/
        $('div.video-container-tgw2 a', tag).click(function () {
            $(this.parentNode).append('<iframe style="float:left;margin: 10px 20px" type="text/html" ' +
                'width="' + our_yt.width + '" height="' + our_yt.height + '" src="https://video2.thegoldwater.com/api/player.php?id=' + $(this.parentNode).data('video') +
                '" allowfullscreen frameborder="0"/>');
            $(this).remove();
            return false;
        });

        /*Xhamster*/
        $('div.video-container-xhamster a', tag).click(function () {
            $(this.parentNode).append('<iframe style="float:left;margin: 10px 20px" type="text/html" ' +
                'width="380" height="280" src="https://www.xhamster.com/xembed.php?video=' + $(this.parentNode).data('video') +
                '" allowfullscreen scrolling="no" frameborder="0"/>');
            $(this).remove();
            return false;
        });
        /*Redtube*/
        $('div.video-container-redtube a', tag).click(function () {
            $(this.parentNode).append('<iframe style="float:left;margin: 10px 20px" type="text/html" ' +
                ' src="https://embed.redtube.com/?id=' + $(this.parentNode).data('video') +
                '&bgcolor=000000" frameborder="0" width="380" height="280" scrolling="no" allowfullscreen/>');
            $(this).remove();
            return false;
        });
        /*Pornhub*/
        $('div.video-container-pornhub a', tag).click(function () {
            $(this.parentNode).append('<iframe style="float:left;margin: 10px 20px" type="text/html" ' +
                'width="380" height="280" src="https://www.pornhub.com/embed/' + $(this.parentNode).data('video') +
                '" allowfullscreen scrolling="no" frameborder="0"/>');
            $(this).remove();
            return false;
        });
        /*Vimeo*/
        $('div.video-container-vimeo a', tag).click(function () {
            $(this.parentNode).append('<iframe style="float:left;margin: 10px 20px" type="text/html" ' +
                'width="' + our_yt.width + '" height="' + our_yt.height + '" src="https://player.vimeo.com/video/' + $(this.parentNode).data('video') +
                '?color=ffffff" webkitallowfullscreen mozallowfullscreen allowfullscreen frameborder="0"/>');
            $(this).remove();
            return false;
        });
        /*Tube8*/
        $('div.video-container-tube8 a', tag).click(function () {
            $(this.parentNode).append('<iframe style="float:left;margin: 10px 20px" type="text/html" ' +
                'width="608" height="481" src="https://www.tube8.com/embed/' + $(this.parentNode).data('video') +
                '" scrolling="no" allowfullscreen="true" webkitallowfullscreen="true" mozallowfullscreen="true" name="t8_embed_video" frameborder="0"/>');
            $(this).remove();
            return false;
        });
        /*Xvideos*/
        $('div.video-container-xvideos a', tag).click(function () {
            $(this.parentNode).append('<iframe style="float:left;margin: 10px 20px" type="text/html" ' +
                'width="' + our_yt.width + '" height="' + our_yt.height + '" src="https://flashservice.xvideos.com/embedframe/' + $(this.parentNode).data('video') +
                '" scrolling=no allowfullscreen=allowfullscreen frameborder="0"/>');
            $(this).remove();
            return false;
        });
        /*Youjizz*/
        $('div.video-container-youjizz a', tag).click(function () {
            $(this.parentNode).append('<iframe style="float:left;margin: 10px 20px" type="text/html" ' +
                'width="' + our_yt.width + '" height="' + our_yt.height + '" src="https://www.youjizz.com/videos/embed/' + $(this.parentNode).data('video') +
                '" allowfullscreen scrolling="no" allowtransparency="true" frameborder="0"/>');
            $(this).remove();
            return false;
        });
        /*Twitch*/
        $('div.video-container-twitch a', tag).click(function () {
            $(this.parentNode).append('<iframe style="float:left;margin: 10px 20px" type="text/html" ' +
                'width="' + our_yt.width + '" height="' + our_yt.height + '" src="https://player.twitch.tv/?channel=' + $(this.parentNode).data('video') +
                '" allowfullscreen scrolling="no" allowtransparency="true" frameborder="0"/>');
            $(this).remove();
            return false;
        });
        /*Dailymotion*/
        $('div.video-container-dailymotion a', tag).click(function () {
            $(this.parentNode).append('<iframe style="float:left;margin: 10px 20px" type="text/html" ' +
                'width="' + our_yt.width + '" height="' + our_yt.height + '" src="https://www.dailymotion.com/embed/video/' + $(this.parentNode).data('video') +
                '" allowfullscreen scrolling="no" frameborder="0"/>');
            $(this).remove();
            return false;
        });
        /*vaughnlive*/
        $('div.video-container-vaughnlive a', tag).click(function () {
            $(this.parentNode).append('<iframe style="float:left;margin: 10px 20px" type="text/html" ' +
                'width="' + our_yt.width + '" height="' + our_yt.height + '" src="https://vaughnlive.tv/embed/video/' + $(this.parentNode).data('video') +
                '" allowfullscreen scrolling="no" frameborder="0"/>');
            $(this).remove();
            return false;
        });
        /*liveleak*/
        $('div.video-container-liveleak a', tag).click(function () {
            $(this.parentNode).append('<iframe style="float:left;margin: 10px 20px" type="text/html" ' +
                'width="' + our_yt.width + '" height="' + our_yt.height + '" src="https://www.liveleak.com/ll_embed?i=' + $(this.parentNode).data('video') +
                '" allowfullscreen scrolling="no" frameborder="0"/>');
            $(this).remove();
            return false;
        });
        /*nicovideo*/
        $('div.video-container-nicovideo a', tag).click(function () {
            $(this.parentNode).append('<iframe style="float:left;margin: 10px 20px" type="text/html" ' +
                'width="' + our_yt.width + '" height="' + our_yt.height + '" src="https://embed.nicovideo.jp/watch/sm' + $(this.parentNode).data('video') +
                '?oldScript=1" allowfullscreen scrolling="no" frameborder="0"/>');
            $(this).remove();
            return false;
        });
        /*streamable*/
        $('div.video-container-streamable a', tag).click(function () {
            $(this.parentNode).append('<iframe style="float:left;margin: 10px 20px" type="text/html" ' +
                'width="' + our_yt.width + '" height="' + our_yt.height + '" src="https://streamable.com/e/' + $(this.parentNode).data('video') +
                '?r=a" allowfullscreen scrolling="no" frameborder="0"/>');
            $(this).remove();
            return false;
        });
        /*soundcloud*/
        $('div.video-container-soundcloud a', tag).click(function () {
            $(this.parentNode).append('<iframe style="float:left;margin: 10px 20px" type="text/html" ' +
                'width="' + our_yt.width + '" height="' + our_yt.height + '" src="https://w.soundcloud.com/player/?url=' + $(this.parentNode).data('video') +
                '&amp;auto_play=false&amp;hide_related=false&amp;show_comments=true&amp;show_user=true&amp;show_reposts=false&amp;visual=true" allowfullscreen scrolling="no" frameborder="0"/>');
            $(this).remove();
            return false;
        });
        /*xaniatube*/
        $('div.video-container-xaniatube a', tag).click(function () {
            $(this.parentNode).append('<iframe style="float:left;margin: 10px 20px" type="text/html" ' +
                'width="640" height="360" src="https://www.xaniatube.com/embed.php?vid=' + $(this.parentNode).data('video') +
                '" allowfullscreen seamless scrolling="no" frameborder="0"/>');
            $(this).remove();
            return false;
        });
        /*Vlive*/
        $('div.video-container-vlive a', tag).click(function () {
            $(this.parentNode).append('<iframe style="float:left;margin: 10px 20px" type="text/html" ' +
                'width="' + our_yt.width + '" height="' + our_yt.height + '" src="https://www.vlive.tv/embed/' + $(this.parentNode).data('video') +
                '#playerBoxArea" allowfullscreen scrolling="no" frameborder="0"/>');
            $(this).remove();
            return false;
        });

        /*Vocaroo*/
        $('div.video-container-vocaroo a', tag).click(function () {
            $(this.parentNode).append('<object width="148" height="44">' +
                '<param name="movie" value="https://vocaroo.com/player.swf?playMediaID=' + $(this.parentNode).data('video') + '&autoplay=0"></param>' +
                '<param name="wmode" value="transparent"></param>' +
                '<embed src="https://vocaroo.com/player.swf?playMediaID=' + $(this.parentNode).data('video') + '&autoplay=0" width="148" height="44" wmode="transparent" type="application/x-shockwave-flash"></embed>' +
                '</object>');
            $(this).remove();
            return false;
        });

        /*Hooktube*/
        $('div.video-container-hooktube a', tag).click(function () {
            $(this.parentNode).append('<iframe style="float:left;margin: 10px 20px" type="text/html" ' +
                'width="' + our_yt.width + '" height="' + our_yt.height + '" src="https://hooktube.com/embed/' + $(this.parentNode).data('video') +
                '" allowfullscreen scrolling="no" frameborder="0"/>');
            $(this).remove();
            return false;
        });

        /*Smashcast*/
        $('div.video-container-smashcast a', tag).click(function () {
            $(this.parentNode).append('<iframe style="float:left;margin: 10px 20px" type="text/html" ' +
                'width="' + our_yt.width + '" height="' + our_yt.height + '" src="https://www.smashcast.tv/embed/' + $(this.parentNode).data('video') +
                '" allowfullscreen scrolling="no" frameborder="0"/>');
            $(this).remove();
            return false;
        });

        //Twitch YT size
        $('div.video-container.twitch').find('object').each(function (i, v) {
            $(v).attr('width', our_yt.width).attr('height', our_yt.height);
        });

        /*Invidio*/
        $('div.video-container-invidio a', tag).click(function () {
            $(this.parentNode).append('<iframe style="float:left;margin: 10px 20px" type="text/html" ' +
                'width="' + our_yt.width + '" height="' + our_yt.height + '" src="https://invidio.us/embed/' + $(this.parentNode).data('video') +
                '" allowfullscreen scrolling="no" frameborder="0"/>');
            $(this).remove();
            return false;
        });


    };
    do_embed_yt(document);

    // allow to work with auto-reload.js, etc.
    $(document).on('new_post', function (e, post) {
        do_embed_yt(post);
    });
});

//YT draggable
$(document).ready(function () {
    //Options for jQuery-UI draggable
    var ui_draggable_opts = {
        handle: ".video-handle",
        containment: 'window',
        scroll: false,
        distance: 10,
        stop: function () {
            $(this).css('position', 'fixed');
        }
    }
    //Get a suitable background color, based on the current CSS
    var dummy_reply = $('<div class="post reply"></div>').appendTo($('body'));
    var reply_background = dummy_reply.css('backgroundColor');
    dummy_reply.remove();

    //Add pop buttons
    $('.video-container').prepend($('<a href="#" class="video-pop" style="font-weight:bold;float:right"><i class="fa fa-picture-o" aria-hidden="true"></i></a>'))
    $('.video-container').css({display: 'inline-block', float: 'left'});
    $('.thread>.video-container>a>img').css('margin-bottom', 0)

    $('.video-pop').on('click', function (e) {
        e.preventDefault();
        var vc = $(this).parents('.video-container');

        if (vc.hasClass('popped')) {
            //vc.remove();
            vc.removeClass('popped');
            vc.draggable('destroy');
            vc.removeClass('ui-draggable');
            vc.css('position', 'static');
            vc.find('.video-handle').remove();
            $(this).text('<i class="fa fa-picture-o" aria-hidden="true"></i>');

        } else {
            $(this).text('[return]');
            vc.prepend($('<i class="fa fa-arrows video-handle" style="border:1px solid black;padding:2px;cursor:move">'));
            vc.addClass('ui-draggable');
            vc.addClass('popped');
            vc.css('background-color', reply_background);
            //No hiding under the nav
            vc.css('z-index', 31);
            //Correct displacement that would occur when the height of the page changes when a video is first dragged; ui draggable is meant to be used for pos:relative not pos:fixed
            vc.css('top', vc.offset().top - $(window).scrollTop());
            vc.css('left', vc.offset().left - $(window).scrollLeft());
            vc.css('position', 'fixed');
            vc.draggable(ui_draggable_opts);
        }
    });
    /*Vidme*/
    //Add pop buttons
    $('.video-container-vidme').prepend($('<a href="#" class="video-pop" style="font-weight:bold;float:right"><i class="fa fa-picture-o" aria-hidden="true"></i></a>'))
    $('.video-container-vidme').css({display: 'inline-block', float: 'left'});
    $('.thread>.video-container-vidme>a>img').css('margin-bottom', 0)

    $('.video-pop').on('click', function (e) {
        e.preventDefault();
        var vc = $(this).parents('.video-container-vidme');

        if (vc.hasClass('popped')) {
            //vc.remove();
            vc.removeClass('popped');
            vc.draggable('destroy');
            vc.removeClass('ui-draggable');
            vc.css('position', 'static');
            vc.find('.video-handle').remove();
            $(this).text('<i class="fa fa-picture-o" aria-hidden="true"></i>');

        } else {
            $(this).text('[return]');
            vc.prepend($('<i class="fa fa-arrows video-handle" style="border:1px solid black;padding:2px;cursor:move">'));
            vc.addClass('ui-draggable');
            vc.addClass('popped');
            vc.css('background-color', reply_background);
            //No hiding under the nav
            vc.css('z-index', 31);
            //Correct displacement that would occur when the height of the page changes when a video is first dragged; ui draggable is meant to be used for pos:relative not pos:fixed
            vc.css('top', vc.offset().top - $(window).scrollTop());
            vc.css('left', vc.offset().left - $(window).scrollLeft());
            vc.css('position', 'fixed');
            vc.draggable(ui_draggable_opts);
        }
    });

    /*TGW*/
    //Add pop buttons
    $('.video-container-tgw1').prepend($('<a href="#" class="video-pop" style="font-weight:bold;float:right"><i class="fa fa-picture-o" aria-hidden="true"></i></a>'))
    $('.video-container-tgw1').css({display: 'inline-block', float: 'left'});
    $('.thread>.video-container-tgw1>a>img').css('margin-bottom', 0)

    $('.video-pop').on('click', function (e) {
        e.preventDefault();
        var vc = $(this).parents('.video-container-tgw1');

        if (vc.hasClass('popped')) {
            //vc.remove();
            vc.removeClass('popped');
            vc.draggable('destroy');
            vc.removeClass('ui-draggable');
            vc.css('position', 'static');
            vc.find('.video-handle').remove();
            $(this).text('<i class="fa fa-picture-o" aria-hidden="true"></i>');

        } else {
            $(this).text('[return]');
            vc.prepend($('<i class="fa fa-arrows video-handle" style="border:1px solid black;padding:2px;cursor:move">'));
            vc.addClass('ui-draggable');
            vc.addClass('popped');
            vc.css('background-color', reply_background);
            //No hiding under the nav
            vc.css('z-index', 31);
            //Correct displacement that would occur when the height of the page changes when a video is first dragged; ui draggable is meant to be used for pos:relative not pos:fixed
            vc.css('top', vc.offset().top - $(window).scrollTop());
            vc.css('left', vc.offset().left - $(window).scrollLeft());
            vc.css('position', 'fixed');
            vc.draggable(ui_draggable_opts);
        }
    });

    /*TGW*/
    //Add pop buttons
    $('.video-container-tgw2').prepend($('<a href="#" class="video-pop" style="font-weight:bold;float:right"><i class="fa fa-picture-o" aria-hidden="true"></i></a>'))
    $('.video-container-tgw2').css({display: 'inline-block', float: 'left'});
    $('.thread>.video-container-tgw2>a>img').css('margin-bottom', 0)

    $('.video-pop').on('click', function (e) {
        e.preventDefault();
        var vc = $(this).parents('.video-container-tgw2');

        if (vc.hasClass('popped')) {
            //vc.remove();
            vc.removeClass('popped');
            vc.draggable('destroy');
            vc.removeClass('ui-draggable');
            vc.css('position', 'static');
            vc.find('.video-handle').remove();
            $(this).text('<i class="fa fa-picture-o" aria-hidden="true"></i>');

        } else {
            $(this).text('[return]');
            vc.prepend($('<i class="fa fa-arrows video-handle" style="border:1px solid black;padding:2px;cursor:move">'));
            vc.addClass('ui-draggable');
            vc.addClass('popped');
            vc.css('background-color', reply_background);
            //No hiding under the nav
            vc.css('z-index', 31);
            //Correct displacement that would occur when the height of the page changes when a video is first dragged; ui draggable is meant to be used for pos:relative not pos:fixed
            vc.css('top', vc.offset().top - $(window).scrollTop());
            vc.css('left', vc.offset().left - $(window).scrollLeft());
            vc.css('position', 'fixed');
            vc.draggable(ui_draggable_opts);
        }
    });

    /*xhamster*/
    //Add pop buttons
    $('.video-container-xhamster').prepend($('<a href="#" class="video-pop" style="font-weight:bold;float:right"><i class="fa fa-picture-o" aria-hidden="true"></i></a>'))
    $('.video-container-xhamster').css({display: 'inline-block', float: 'left'});
    $('.thread>.video-container-xhamster>a>img').css('margin-bottom', 0)

    $('.video-pop').on('click', function (e) {
        e.preventDefault();
        var vc = $(this).parents('.video-container-xhamster');

        if (vc.hasClass('popped')) {
            //vc.remove();
            vc.removeClass('popped');
            vc.draggable('destroy');
            vc.removeClass('ui-draggable');
            vc.css('position', 'static');
            vc.find('.video-handle').remove();
            $(this).text('<i class="fa fa-picture-o" aria-hidden="true"></i>');

        } else {
            $(this).text('[return]');
            vc.prepend($('<i class="fa fa-arrows video-handle" style="border:1px solid black;padding:2px;cursor:move">'));
            vc.addClass('ui-draggable');
            vc.addClass('popped');
            vc.css('background-color', reply_background);
            //No hiding under the nav
            vc.css('z-index', 31);
            //Correct displacement that would occur when the height of the page changes when a video is first dragged; ui draggable is meant to be used for pos:relative not pos:fixed
            vc.css('top', vc.offset().top - $(window).scrollTop());
            vc.css('left', vc.offset().left - $(window).scrollLeft());
            vc.css('position', 'fixed');
            vc.draggable(ui_draggable_opts);
        }
    });

    /*redtube*/
    //Add pop buttons
    $('.video-container-redtube').prepend($('<a href="#" class="video-pop" style="font-weight:bold;float:right"><i class="fa fa-picture-o" aria-hidden="true"></i></a>'))
    $('.video-container-redtube').css({display: 'inline-block', float: 'left'});
    $('.thread>.video-container-redtube>a>img').css('margin-bottom', 0)

    $('.video-pop').on('click', function (e) {
        e.preventDefault();
        var vc = $(this).parents('.video-container-redtube');

        if (vc.hasClass('popped')) {
            //vc.remove();
            vc.removeClass('popped');
            vc.draggable('destroy');
            vc.removeClass('ui-draggable');
            vc.css('position', 'static');
            vc.find('.video-handle').remove();
            $(this).text('<i class="fa fa-picture-o" aria-hidden="true"></i>');

        } else {
            $(this).text('[return]');
            vc.prepend($('<i class="fa fa-arrows video-handle" style="border:1px solid black;padding:2px;cursor:move">'));
            vc.addClass('ui-draggable');
            vc.addClass('popped');
            vc.css('background-color', reply_background);
            //No hiding under the nav
            vc.css('z-index', 31);
            //Correct displacement that would occur when the height of the page changes when a video is first dragged; ui draggable is meant to be used for pos:relative not pos:fixed
            vc.css('top', vc.offset().top - $(window).scrollTop());
            vc.css('left', vc.offset().left - $(window).scrollLeft());
            vc.css('position', 'fixed');
            vc.draggable(ui_draggable_opts);
        }
    });

    /*pornhub*/
    //Add pop buttons
    $('.video-container-pornhub').prepend($('<a href="#" class="video-pop" style="font-weight:bold;float:right"><i class="fa fa-picture-o" aria-hidden="true"></i></a>'))
    $('.video-container-pornhub').css({display: 'inline-block', float: 'left'});
    $('.thread>.video-container-pornhub>a>img').css('margin-bottom', 0)

    $('.video-pop').on('click', function (e) {
        e.preventDefault();
        var vc = $(this).parents('.video-container-pornhub');

        if (vc.hasClass('popped')) {
            //vc.remove();
            vc.removeClass('popped');
            vc.draggable('destroy');
            vc.removeClass('ui-draggable');
            vc.css('position', 'static');
            vc.find('.video-handle').remove();
            $(this).text('<i class="fa fa-picture-o" aria-hidden="true"></i>');

        } else {
            $(this).text('[return]');
            vc.prepend($('<i class="fa fa-arrows video-handle" style="border:1px solid black;padding:2px;cursor:move">'));
            vc.addClass('ui-draggable');
            vc.addClass('popped');
            vc.css('background-color', reply_background);
            //No hiding under the nav
            vc.css('z-index', 31);
            //Correct displacement that would occur when the height of the page changes when a video is first dragged; ui draggable is meant to be used for pos:relative not pos:fixed
            vc.css('top', vc.offset().top - $(window).scrollTop());
            vc.css('left', vc.offset().left - $(window).scrollLeft());
            vc.css('position', 'fixed');
            vc.draggable(ui_draggable_opts);
        }
    });

    /*tube8*/
    //Add pop buttons
    $('.video-container-tube8').prepend($('<a href="#" class="video-pop" style="font-weight:bold;float:right"><i class="fa fa-picture-o" aria-hidden="true"></i></a>'))
    $('.video-container-tube8').css({display: 'inline-block', float: 'left'});
    $('.thread>.video-container-tube8>a>img').css('margin-bottom', 0)

    $('.video-pop').on('click', function (e) {
        e.preventDefault();
        var vc = $(this).parents('.video-container-tube8');

        if (vc.hasClass('popped')) {
            //vc.remove();
            vc.removeClass('popped');
            vc.draggable('destroy');
            vc.removeClass('ui-draggable');
            vc.css('position', 'static');
            vc.find('.video-handle').remove();
            $(this).text('<i class="fa fa-picture-o" aria-hidden="true"></i>');

        } else {
            $(this).text('[return]');
            vc.prepend($('<i class="fa fa-arrows video-handle" style="border:1px solid black;padding:2px;cursor:move">'));
            vc.addClass('ui-draggable');
            vc.addClass('popped');
            vc.css('background-color', reply_background);
            //No hiding under the nav
            vc.css('z-index', 31);
            //Correct displacement that would occur when the height of the page changes when a video is first dragged; ui draggable is meant to be used for pos:relative not pos:fixed
            vc.css('top', vc.offset().top - $(window).scrollTop());
            vc.css('left', vc.offset().left - $(window).scrollLeft());
            vc.css('position', 'fixed');
            vc.draggable(ui_draggable_opts);
        }
    });

    /*xvideos*/
    //Add pop buttons
    $('.video-container-xvideos').prepend($('<a href="#" class="video-pop" style="font-weight:bold;float:right"><i class="fa fa-picture-o" aria-hidden="true"></i></a>'))
    $('.video-container-xvideos').css({display: 'inline-block', float: 'left'});
    $('.thread>.video-container-xvideos>a>img').css('margin-bottom', 0)

    $('.video-pop').on('click', function (e) {
        e.preventDefault();
        var vc = $(this).parents('.video-container-xvideos');

        if (vc.hasClass('popped')) {
            //vc.remove();
            vc.removeClass('popped');
            vc.draggable('destroy');
            vc.removeClass('ui-draggable');
            vc.css('position', 'static');
            vc.find('.video-handle').remove();
            $(this).text('<i class="fa fa-picture-o" aria-hidden="true"></i>');

        } else {
            $(this).text('[return]');
            vc.prepend($('<i class="fa fa-arrows video-handle" style="border:1px solid black;padding:2px;cursor:move">'));
            vc.addClass('ui-draggable');
            vc.addClass('popped');
            vc.css('background-color', reply_background);
            //No hiding under the nav
            vc.css('z-index', 31);
            //Correct displacement that would occur when the height of the page changes when a video is first dragged; ui draggable is meant to be used for pos:relative not pos:fixed
            vc.css('top', vc.offset().top - $(window).scrollTop());
            vc.css('left', vc.offset().left - $(window).scrollLeft());
            vc.css('position', 'fixed');
            vc.draggable(ui_draggable_opts);
        }
    });

    /*youjizz*/
    //Add pop buttons
    $('.video-container-youjizz').prepend($('<a href="#" class="video-pop" style="font-weight:bold;float:right"><i class="fa fa-picture-o" aria-hidden="true"></i></a>'))
    $('.video-container-youjizz').css({display: 'inline-block', float: 'left'});
    $('.thread>.video-container-youjizz>a>img').css('margin-bottom', 0)

    $('.video-pop').on('click', function (e) {
        e.preventDefault();
        var vc = $(this).parents('.video-container-youizz');

        if (vc.hasClass('popped')) {
            //vc.remove();
            vc.removeClass('popped');
            vc.draggable('destroy');
            vc.removeClass('ui-draggable');
            vc.css('position', 'static');
            vc.find('.video-handle').remove();
            $(this).text('<i class="fa fa-picture-o" aria-hidden="true"></i>');

        } else {
            $(this).text('[return]');
            vc.prepend($('<i class="fa fa-arrows video-handle" style="border:1px solid black;padding:2px;cursor:move">'));
            vc.addClass('ui-draggable');
            vc.addClass('popped');
            vc.css('background-color', reply_background);
            //No hiding under the nav
            vc.css('z-index', 31);
            //Correct displacement that would occur when the height of the page changes when a video is first dragged; ui draggable is meant to be used for pos:relative not pos:fixed
            vc.css('top', vc.offset().top - $(window).scrollTop());
            vc.css('left', vc.offset().left - $(window).scrollLeft());
            vc.css('position', 'fixed');
            vc.draggable(ui_draggable_opts);
        }
    });

    /*twitch*/
    //Add pop buttons
    $('.video-container-twitch').prepend($('<a href="#" class="video-pop" style="font-weight:bold;float:right"><i class="fa fa-picture-o" aria-hidden="true"></i></a>'))
    $('.video-container-twitch').css({display: 'inline-block', float: 'left'});
    $('.thread>.video-container-twitch>a>img').css('margin-bottom', 0)

    $('.video-pop').on('click', function (e) {
        e.preventDefault();
        var vc = $(this).parents('.video-container-twitch');

        if (vc.hasClass('popped')) {
            //vc.remove();
            vc.removeClass('popped');
            vc.draggable('destroy');
            vc.removeClass('ui-draggable');
            vc.css('position', 'static');
            vc.find('.video-handle').remove();
            $(this).text('<i class="fa fa-picture-o" aria-hidden="true"></i>');

        } else {
            $(this).text('[return]');
            vc.prepend($('<i class="fa fa-arrows video-handle" style="border:1px solid black;padding:2px;cursor:move">'));
            vc.addClass('ui-draggable');
            vc.addClass('popped');
            vc.css('background-color', reply_background);
            //No hiding under the nav
            vc.css('z-index', 31);
            //Correct displacement that would occur when the height of the page changes when a video is first dragged; ui draggable is meant to be used for pos:relative not pos:fixed
            vc.css('top', vc.offset().top - $(window).scrollTop());
            vc.css('left', vc.offset().left - $(window).scrollLeft());
            vc.css('position', 'fixed');
            vc.draggable(ui_draggable_opts);
        }
    });

    /*Dailymotion*/
    //Add pop buttons
    $('.video-container-dailymotion').prepend($('<a href="#" class="video-pop" style="font-weight:bold;float:right"><i class="fa fa-picture-o" aria-hidden="true"></i></a>'))
    $('.video-container-dailymotion').css({display: 'inline-block', float: 'left'});
    $('.thread>.video-container-dailymotion>a>img').css('margin-bottom', 0)

    $('.video-pop').on('click', function (e) {
        e.preventDefault();
        var vc = $(this).parents('.video-container-dailymotion');

        if (vc.hasClass('popped')) {
            //vc.remove();
            vc.removeClass('popped');
            vc.draggable('destroy');
            vc.removeClass('ui-draggable');
            vc.css('position', 'static');
            vc.find('.video-handle').remove();
            $(this).text('<i class="fa fa-picture-o" aria-hidden="true"></i>');

        } else {
            $(this).text('[return]');
            vc.prepend($('<i class="fa fa-arrows video-handle" style="border:1px solid black;padding:2px;cursor:move">'));
            vc.addClass('ui-draggable');
            vc.addClass('popped');
            vc.css('background-color', reply_background);
            //No hiding under the nav
            vc.css('z-index', 31);
            //Correct displacement that would occur when the height of the page changes when a video is first dragged; ui draggable is meant to be used for pos:relative not pos:fixed
            vc.css('top', vc.offset().top - $(window).scrollTop());
            vc.css('left', vc.offset().left - $(window).scrollLeft());
            vc.css('position', 'fixed');
            vc.draggable(ui_draggable_opts);
        }
    });

    /*vaughnlive*/
    //Add pop buttons
    $('.video-container-vaughnlive').prepend($('<a href="#" class="video-pop" style="font-weight:bold;float:right"><i class="fa fa-picture-o" aria-hidden="true"></i></a>'))
    $('.video-container-vaughnlive').css({display: 'inline-block', float: 'left'});
    $('.thread>.video-container-vaughnlive>a>img').css('margin-bottom', 0)

    $('.video-pop').on('click', function (e) {
        e.preventDefault();
        var vc = $(this).parents('.video-container-vaughnlive');

        if (vc.hasClass('popped')) {
            //vc.remove();
            vc.removeClass('popped');
            vc.draggable('destroy');
            vc.removeClass('ui-draggable');
            vc.css('position', 'static');
            vc.find('.video-handle').remove();
            $(this).text('<i class="fa fa-picture-o" aria-hidden="true"></i>');

        } else {
            $(this).text('[return]');
            vc.prepend($('<i class="fa fa-arrows video-handle" style="border:1px solid black;padding:2px;cursor:move">'));
            vc.addClass('ui-draggable');
            vc.addClass('popped');
            vc.css('background-color', reply_background);
            //No hiding under the nav
            vc.css('z-index', 31);
            //Correct displacement that would occur when the height of the page changes when a video is first dragged; ui draggable is meant to be used for pos:relative not pos:fixed
            vc.css('top', vc.offset().top - $(window).scrollTop());
            vc.css('left', vc.offset().left - $(window).scrollLeft());
            vc.css('position', 'fixed');
            vc.draggable(ui_draggable_opts);
        }
    });

    /*Liveleak*/
    //Add pop buttons
    $('.video-container-liveleak').prepend($('<a href="#" class="video-pop" style="font-weight:bold;float:right"><i class="fa fa-picture-o" aria-hidden="true"></i></a>'))
    $('.video-container-liveleak').css({display: 'inline-block', float: 'left'});
    $('.thread>.video-container-liveleak>a>img').css('margin-bottom', 0)

    $('.video-pop').on('click', function (e) {
        e.preventDefault();
        var vc = $(this).parents('.video-container-liveleak');

        if (vc.hasClass('popped')) {
            //vc.remove();
            vc.removeClass('popped');
            vc.draggable('destroy');
            vc.removeClass('ui-draggable');
            vc.css('position', 'static');
            vc.find('.video-handle').remove();
            $(this).text('<i class="fa fa-picture-o" aria-hidden="true"></i>');

        } else {
            $(this).text('[return]');
            vc.prepend($('<i class="fa fa-arrows video-handle" style="border:1px solid black;padding:2px;cursor:move">'));
            vc.addClass('ui-draggable');
            vc.addClass('popped');
            vc.css('background-color', reply_background);
            //No hiding under the nav
            vc.css('z-index', 31);
            //Correct displacement that would occur when the height of the page changes when a video is first dragged; ui draggable is meant to be used for pos:relative not pos:fixed
            vc.css('top', vc.offset().top - $(window).scrollTop());
            vc.css('left', vc.offset().left - $(window).scrollLeft());
            vc.css('position', 'fixed');
            vc.draggable(ui_draggable_opts);
        }
    });

    /*Nicovideo*/
    //Add pop buttons
    $('.video-container-nicovideo').prepend($('<a href="#" class="video-pop" style="font-weight:bold;float:right"><i class="fa fa-picture-o" aria-hidden="true"></i></a>'))
    $('.video-container-nicovideo').css({display: 'inline-block', float: 'left'});
    $('.thread>.video-container-nicovideo>a>img').css('margin-bottom', 0)

    $('.video-pop').on('click', function (e) {
        e.preventDefault();
        var vc = $(this).parents('.video-container-nicovideo');

        if (vc.hasClass('popped')) {
            //vc.remove();
            vc.removeClass('popped');
            vc.draggable('destroy');
            vc.removeClass('ui-draggable');
            vc.css('position', 'static');
            vc.find('.video-handle').remove();
            $(this).text('<i class="fa fa-picture-o" aria-hidden="true"></i>');

        } else {
            $(this).text('[return]');
            vc.prepend($('<i class="fa fa-arrows video-handle" style="border:1px solid black;padding:2px;cursor:move">'));
            vc.addClass('ui-draggable');
            vc.addClass('popped');
            vc.css('background-color', reply_background);
            //No hiding under the nav
            vc.css('z-index', 31);
            //Correct displacement that would occur when the height of the page changes when a video is first dragged; ui draggable is meant to be used for pos:relative not pos:fixed
            vc.css('top', vc.offset().top - $(window).scrollTop());
            vc.css('left', vc.offset().left - $(window).scrollLeft());
            vc.css('position', 'fixed');
            vc.draggable(ui_draggable_opts);
        }
    });

    /*Streamable*/
    //Add pop buttons
    $('.video-container-streamable').prepend($('<a href="#" class="video-pop" style="font-weight:bold;float:right"><i class="fa fa-picture-o" aria-hidden="true"></i></a>'))
    $('.video-container-streamable').css({display: 'inline-block', float: 'left'});
    $('.thread>.video-container-streamable>a>img').css('margin-bottom', 0)

    $('.video-pop').on('click', function (e) {
        e.preventDefault();
        var vc = $(this).parents('.video-container-streamable');

        if (vc.hasClass('popped')) {
            //vc.remove();
            vc.removeClass('popped');
            vc.draggable('destroy');
            vc.removeClass('ui-draggable');
            vc.css('position', 'static');
            vc.find('.video-handle').remove();
            $(this).text('<i class="fa fa-picture-o" aria-hidden="true"></i>');

        } else {
            $(this).text('[return]');
            vc.prepend($('<i class="fa fa-arrows video-handle" style="border:1px solid black;padding:2px;cursor:move">'));
            vc.addClass('ui-draggable');
            vc.addClass('popped');
            vc.css('background-color', reply_background);
            //No hiding under the nav
            vc.css('z-index', 31);
            //Correct displacement that would occur when the height of the page changes when a video is first dragged; ui draggable is meant to be used for pos:relative not pos:fixed
            vc.css('top', vc.offset().top - $(window).scrollTop());
            vc.css('left', vc.offset().left - $(window).scrollLeft());
            vc.css('position', 'fixed');
            vc.draggable(ui_draggable_opts);
        }
    });

    /*Soundcloud*/
    //Add pop buttons
    $('.video-container-soundcloud').prepend($('<a href="#" class="video-pop" style="font-weight:bold;float:right"><i class="fa fa-picture-o" aria-hidden="true"></i></a>'))
    $('.video-container-soundcloud').css({display: 'inline-block', float: 'left'});
    $('.thread>.video-container-soundcloud>a>img').css('margin-bottom', 0)

    $('.video-pop').on('click', function (e) {
        e.preventDefault();
        var vc = $(this).parents('.video-container-soundcloud');

        if (vc.hasClass('popped')) {
            //vc.remove();
            vc.removeClass('popped');
            vc.draggable('destroy');
            vc.removeClass('ui-draggable');
            vc.css('position', 'static');
            vc.find('.video-handle').remove();
            $(this).text('<i class="fa fa-picture-o" aria-hidden="true"></i>');

        } else {
            $(this).text('[return]');
            vc.prepend($('<i class="fa fa-arrows video-handle" style="border:1px solid black;padding:2px;cursor:move">'));
            vc.addClass('ui-draggable');
            vc.addClass('popped');
            vc.css('background-color', reply_background);
            //No hiding under the nav
            vc.css('z-index', 31);
            //Correct displacement that would occur when the height of the page changes when a video is first dragged; ui draggable is meant to be used for pos:relative not pos:fixed
            vc.css('top', vc.offset().top - $(window).scrollTop());
            vc.css('left', vc.offset().left - $(window).scrollLeft());
            vc.css('position', 'fixed');
            vc.draggable(ui_draggable_opts);
        }
    });

    /*Xaniatube*/
    //Add pop buttons
    $('.video-container-xaniatube').prepend($('<a href="#" class="video-pop" style="font-weight:bold;float:right"><i class="fa fa-picture-o" aria-hidden="true"></i></a>'))
    $('.video-container-xaniatube').css({display: 'inline-block', float: 'left'});
    $('.thread>.video-container-xaniatube>a>img').css('margin-bottom', 0)

    $('.video-pop').on('click', function (e) {
        e.preventDefault();
        var vc = $(this).parents('.video-container-xaniatube');

        if (vc.hasClass('popped')) {
            //vc.remove();
            vc.removeClass('popped');
            vc.draggable('destroy');
            vc.removeClass('ui-draggable');
            vc.css('position', 'static');
            vc.find('.video-handle').remove();
            $(this).text('<i class="fa fa-picture-o" aria-hidden="true"></i>');

        } else {
            $(this).text('[return]');
            vc.prepend($('<i class="fa fa-arrows video-handle" style="border:1px solid black;padding:2px;cursor:move">'));
            vc.addClass('ui-draggable');
            vc.addClass('popped');
            vc.css('background-color', reply_background);
            //No hiding under the nav
            vc.css('z-index', 31);
            //Correct displacement that would occur when the height of the page changes when a video is first dragged; ui draggable is meant to be used for pos:relative not pos:fixed
            vc.css('top', vc.offset().top - $(window).scrollTop());
            vc.css('left', vc.offset().left - $(window).scrollLeft());
            vc.css('position', 'fixed');
            vc.draggable(ui_draggable_opts);
        }
    });

    /*Vlive*/
    //Add pop buttons
    $('.video-container-vlive').prepend($('<a href="#" class="video-pop" style="font-weight:bold;float:right"><i class="fa fa-picture-o" aria-hidden="true"></i></a>'))
    $('.video-container-vlive').css({display: 'inline-block', float: 'left'});
    $('.thread>.video-container-vlive>a>img').css('margin-bottom', 0)

    $('.video-pop').on('click', function (e) {
        e.preventDefault();
        var vc = $(this).parents('.video-container-vlive');

        if (vc.hasClass('popped')) {
            vc.removeClass('popped');
            vc.draggable('destroy');
            vc.removeClass('ui-draggable');
            vc.css('position', 'static');
            vc.find('.video-handle').remove();
            $(this).text('<i class="fa fa-picture-o" aria-hidden="true"></i>');

        } else {
            $(this).text('[return]');
            vc.prepend($('<i class="fa fa-arrows video-handle" style="border:1px solid black;padding:2px;cursor:move">'));
            vc.addClass('ui-draggable');
            vc.addClass('popped');
            vc.css('background-color', reply_background);
            //No hiding under the nav
            vc.css('z-index', 31);
            //Correct displacement that would occur when the height of the page changes when a video is first dragged; ui draggable is meant to be used for pos:relative not pos:fixed
            vc.css('top', vc.offset().top - $(window).scrollTop());
            vc.css('left', vc.offset().left - $(window).scrollLeft());
            vc.css('position', 'fixed');
            vc.draggable(ui_draggable_opts);
        }
    });

    /*Vocaroo*/
    //Add pop buttons
    $('.video-container-vocaroo').prepend($('<a href="#" class="video-pop" style="font-weight:bold;float:right"><i class="fa fa-picture-o" aria-hidden="true"></i></a>'))
    $('.video-container-vocaroo').css({display: 'inline-block', float: 'left'});
    $('.thread>.video-container-vocaroo>a>img').css('margin-bottom', 0)

    $('.video-pop').on('click', function (e) {
        e.preventDefault();
        var vc = $(this).parents('.video-container-vocaroo');

        if (vc.hasClass('popped')) {
            //vc.remove();
            vc.removeClass('popped');
            vc.draggable('destroy');
            vc.removeClass('ui-draggable');
            vc.css('position', 'static');
            vc.find('.video-handle').remove();
            $(this).text('<i class="fa fa-picture-o" aria-hidden="true"></i>');

        } else {
            $(this).text('[return]');
            vc.prepend($('<i class="fa fa-arrows video-handle" style="border:1px solid black;padding:2px;cursor:move">'));
            vc.addClass('ui-draggable');
            vc.addClass('popped');
            vc.css('background-color', reply_background);
            //No hiding under the nav
            vc.css('z-index', 31);
            //Correct displacement that would occur when the height of the page changes when a video is first dragged; ui draggable is meant to be used for pos:relative not pos:fixed
            vc.css('top', vc.offset().top - $(window).scrollTop());
            vc.css('left', vc.offset().left - $(window).scrollLeft());
            vc.css('position', 'fixed');
            vc.draggable(ui_draggable_opts);
        }
    });


    /*Hooktube*/
    //Add pop buttons
    $('.video-container-hooktube').prepend($('<a href="#" class="video-pop" style="font-weight:bold;float:right"><i class="fa fa-picture-o" aria-hidden="true"></i></a>'))
    $('.video-container-hooktube').css({display: 'inline-block', float: 'left'});
    $('.thread>.video-container-hooktube>a>img').css('margin-bottom', 0)

    $('.video-pop').on('click', function (e) {
        e.preventDefault();
        var vc = $(this).parents('.video-container-hooktube');

        if (vc.hasClass('popped')) {
            //vc.remove();
            vc.removeClass('popped');
            vc.draggable('destroy');
            vc.removeClass('ui-draggable');
            vc.css('position', 'static');
            vc.find('.video-handle').remove();
            $(this).text('<i class="fa fa-picture-o" aria-hidden="true"></i>');

        } else {
            $(this).text('[return]');
            vc.prepend($('<i class="fa fa-arrows video-handle" style="border:1px solid black;padding:2px;cursor:move">'));
            vc.addClass('ui-draggable');
            vc.addClass('popped');
            vc.css('background-color', reply_background);
            //No hiding under the nav
            vc.css('z-index', 31);
            //Correct displacement that would occur when the height of the page changes when a video is first dragged; ui draggable is meant to be used for pos:relative not pos:fixed
            vc.css('top', vc.offset().top - $(window).scrollTop());
            vc.css('left', vc.offset().left - $(window).scrollLeft());
            vc.css('position', 'fixed');
            vc.draggable(ui_draggable_opts);
        }
    });

    /*Smashcast*/
    //Add pop buttons
    $('.video-container-smashcast').prepend($('<a href="#" class="video-pop" style="font-weight:bold;float:right"><i class="fa fa-picture-o" aria-hidden="true"></i></a>'))
    $('.video-container-smashcast').css({display: 'inline-block', float: 'left'});
    $('.thread>.video-container-smashcast>a>img').css('margin-bottom', 0)

    $('.video-pop').on('click', function (e) {
        e.preventDefault();
        var vc = $(this).parents('.video-container-smashcast');

        if (vc.hasClass('popped')) {
            //vc.remove();
            vc.removeClass('popped');
            vc.draggable('destroy');
            vc.removeClass('ui-draggable');
            vc.css('position', 'static');
            vc.find('.video-handle').remove();
            $(this).text('<i class="fa fa-picture-o" aria-hidden="true"></i>');

        } else {
            $(this).text('[return]');
            vc.prepend($('<i class="fa fa-arrows video-handle" style="border:1px solid black;padding:2px;cursor:move">'));
            vc.addClass('ui-draggable');
            vc.addClass('popped');
            vc.css('background-color', reply_background);
            //No hiding under the nav
            vc.css('z-index', 31);
            //Correct displacement that would occur when the height of the page changes when a video is first dragged; ui draggable is meant to be used for pos:relative not pos:fixed
            vc.css('top', vc.offset().top - $(window).scrollTop());
            vc.css('left', vc.offset().left - $(window).scrollLeft());
            vc.css('position', 'fixed');
            vc.draggable(ui_draggable_opts);
        }
    });


    /*Invidio*/
    //Add pop buttons
    $('.video-container-invidio').prepend($('<a href="#" class="video-pop" style="font-weight:bold;float:right"><i class="fa fa-picture-o" aria-hidden="true"></i></a>'))
    $('.video-container-invidio').css({display: 'inline-block', float: 'left'});
    $('.thread>.video-container-invidio>a>img').css('margin-bottom', 0)

    $('.video-pop').on('click', function (e) {
        e.preventDefault();
        var vc = $(this).parents('.video-container-invidio');

        if (vc.hasClass('popped')) {
            //vc.remove();
            vc.removeClass('popped');
            vc.draggable('destroy');
            vc.removeClass('ui-draggable');
            vc.css('position', 'static');
            vc.find('.video-handle').remove();
            $(this).text('<i class="fa fa-picture-o" aria-hidden="true"></i>');

        } else {
            $(this).text('[return]');
            vc.prepend($('<i class="fa fa-arrows video-handle" style="border:1px solid black;padding:2px;cursor:move">'));
            vc.addClass('ui-draggable');
            vc.addClass('popped');
            vc.css('background-color', reply_background);
            //No hiding under the nav
            vc.css('z-index', 31);
            //Correct displacement that would occur when the height of the page changes when a video is first dragged; ui draggable is meant to be used for pos:relative not pos:fixed
            vc.css('top', vc.offset().top - $(window).scrollTop());
            vc.css('left', vc.offset().left - $(window).scrollLeft());
            vc.css('position', 'fixed');
            vc.draggable(ui_draggable_opts);
        }
    });

});
