/* image-hover.js
 * This script is copied almost verbatim from https://github.com/Pashe/8chanX/blob/2-0/8chan-x.user.js
 * All I did was remove the sprintf dependency and integrate it into 8chan's Options as opposed to Pashe's.
 * I also changed initHover() to also bind on new_post.
 * Thanks Pashe for using WTFPL.
 */

if (active_page === "catalog" || active_page === "thread" || active_page === "index") {
    $(document).ready(function () {
        if(!localStorage.imageHover) localStorage.imageHover = "true";
        if(!localStorage.catalogImageHover) localStorage.catalogImageHover = "true";
        if(!localStorage.imageHoverFollowCursor) localStorage.imageHoverFollowCursor = "true";

        if (window.Options && Options.get_tab('general')) {
            Options.extend_tab("general",
                "<fieldset><legend>" + _('Image hover') + "</legend>"
                + ("<label class='image-hover' id='imageHover'><input type='checkbox' /> " + _('Image hover') + "</label>")
                + ("<label class='image-hover' id='catalogImageHover'><input type='checkbox' /> " + _('Image hover on catalog') + "</label>")
                + ("<label class='image-hover' id='imageHoverFollowCursor'><input type='checkbox' /> " + _('Image hover should follow cursor') + "</label>")
                + "</fieldset>");
        }

        $('.image-hover').on('change', function () {
            var setting = $(this).attr('id');

            localStorage[setting] = $(this).children('input').is(':checked');
        });

        if (!localStorage.imageHover || !localStorage.catalogImageHover || !localStorage.imageHoverFollowCursor) {
            localStorage.imageHover = 'false';
            localStorage.catalogImageHover = 'false';
            localStorage.imageHoverFollowCursor = 'false';
        }

        if (getSetting('imageHover')) $('#imageHover>input').prop('checked', 'checked');
        if (getSetting('catalogImageHover')) $('#catalogImageHover>input').prop('checked', 'checked');
        if (getSetting('imageHoverFollowCursor')) $('#imageHoverFollowCursor>input').prop('checked', 'checked');

        function getFileExtension(filename) { //Pashe, WTFPL
            if (filename.match(/\.([a-z0-9]+)(&loop.*)?$/i) !== null) {
                return filename.match(/\.([a-z0-9]+)(&loop.*)?$/i)[1];
            } else if (filename.match(/^https?:\/\/(\w+\.)?(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9\-_]{10,11})(&.+)?$/i)) {
                return 'Youtube';
            } else {
                return "unknown: " + filename;
            }
        }

        function isImage(fileExtension) { //Pashe, WTFPL
            return ($.inArray(fileExtension, ["jpg", "jpeg", "gif", "png"]) !== -1);
        }

        function isVideo(fileExtension) { //Pashe, WTFPL
            return ($.inArray(fileExtension, ["webm", "mp4"]) !== -1);
        }

        function isOnCatalog() {
            return window.active_page === "catalog";
        }

        function isOnThread() {
            return window.active_page === "thread";
        }

        function getSetting(key) {
            return (localStorage[key] == 'true');
        }

        function initImageHover() { //Pashe, influenced by tux, et al, WTFPL
            if (!getSetting("imageHover") && !getSetting("catalogImageHover")) {
                return;
            }

            var selectors = [];

            if (getSetting("imageHover")) {
                selectors.push("img.post-image", "canvas.post-image");
            }
            if (getSetting("catalogImageHover") && isOnCatalog()) {
                selectors.push(".thread-image");
                $(".theme-catalog div.thread").css("position", "inherit");
            }

            function bindEvents(el) {
                $(el).find(selectors.join(", ")).each(function () {
                    if ($(this).parent().data("expanded")) {
                        return;
                    }

                    var $this = $(this);

                    $this.on("mousemove", imageHoverStart);
                    $this.on("mouseout", imageHoverEnd);
                    $this.on("click", imageHoverEnd);
                });
            }

            bindEvents(document.body);
            $(document).on('new_post', function (e, post) {
                bindEvents(post);
            });
        }

        function getMeta(url, callback) {
            const img = new Image();
            img.src = url;
            img.onload = function () {
                callback(this.width, this.height);
            }
        }

        function imageHoverStart(e) { //Pashe, anonish, WTFPL
            var hoverImage = $("#chx_hoverImage");

            if (hoverImage.length) {
                if (getSetting("imageHoverFollowCursor")) {
                    var scrollTop = $(window).scrollTop();
                    var imgY = e.pageY;
                    var imgTop = imgY;
                    var windowWidth = $(window).width();
                    var imgWidth = hoverImage.width() + e.pageX;

                    if (imgY < scrollTop + 15) {
                        imgTop = scrollTop;
                    } else if (imgY > scrollTop + $(window).height() - hoverImage.height() - 15) {
                        imgTop = scrollTop + $(window).height() - hoverImage.height() - 15;
                    }

                    if (imgWidth > windowWidth) {
                        hoverImage.css({
                            'left': (e.pageX + (windowWidth - imgWidth)),
                            'top': imgTop,
                        });
                    } else {
                        hoverImage.css({
                            'left': e.pageX,
                            'top': imgTop,
                        });
                    }

                    hoverImage.appendTo($("body"));
                }

                return;
            }

            var $this = $(this);

            var fullUrl;

            if ($this.parent().attr("href")) { //.match("src")
                fullUrl = $this.parent().attr("href");
            }
            if (isOnCatalog()) {
                fullUrl = $this.attr("data-fullimage");
                if (!isImage(getFileExtension(fullUrl))) {
                    fullUrl = $this.attr("src");
                }
            }
            if (isVideo(getFileExtension(fullUrl))) {
                return;
            }

            if (getFileExtension(fullUrl) === 'Youtube') {
                fullUrl = $this.attr("src");
            }

            hoverImage = $('<img id="chx_hoverImage" src="' + fullUrl + '" />');

            if (getSetting("imageHoverFollowCursor")) {
                var size = [0, 0],
                    //var size = $this.parents('.file').find('.unimportant').text().match(/\b(\d+)x(\d+)\b/),
                    maxWidth = $(window).width() / 1.3,
                    maxHeight = $(window).height() / 1.3;

                getMeta(fullUrl, (width, height) => {
                    var scale = Math.min(1, maxWidth / width, maxHeight / height);
                    hoverImage.css({
                        "position": "absolute",
                        "z-index": 101,
                        "pointer-events": "none",
                        "width": width + "px",
                        "height": height + "px",
                        "max-width": (width * scale) + "px",
                        "max-height": (height * scale) + "px",
                        'left': e.pageX,
                        'top': imgTop,
                    });
                });
            } else {
                hoverImage.css({
                    "position": "fixed",
                    "top": 0,
                    "right": 0,
                    "z-index": 101,
                    "pointer-events": "none",
                    "max-width": "100%",
                    "max-height": "100%",
                });
            }
            hoverImage.appendTo($("body"));
            //if (isOnThread()) {$this.css("cursor", "none");}
        }

        function imageHoverEnd() { //Pashe, WTFPL
            $("#chx_hoverImage").remove();
        }

        initImageHover();
    });
}

