(function () {
    'use strict';

    const scriptUrl = new URL(document.currentScript.src);
    const vichan = window.VichanPath.vichan;
    vichan.rootUrl = new URL(vichan.root, scriptUrl.origin).href;

    function boardDirectory(board) {
        return vichan.boardPath.replace('%s', encodeURIComponent(board));
    }

    vichan.formatThreadApiUrl = function (board, thread) {
        const url = new URL('js/outside/thread.php', this.rootUrl);
        url.searchParams.set('board', board);
        url.searchParams.set('thread', thread);
        return url.href;
    };

    vichan.formatThreadHtmlUrl = function (board, thread) {
        const path = boardDirectory(board)
            + this.directories.thread
            + this.threadPage.replace('%d', thread);
        return new URL(path, this.rootUrl).href;
    };

    vichan.fetchThread = function (board, thread) {
        return fetch(this.formatThreadApiUrl(board, thread), {
            credentials: 'omit',
        });
    };

    vichan.listPostFiles = function (post, board, thread) {
        if (post.tim === undefined) {
            return [];
        }

        const files = [{
            tn_h: post.tn_h,
            tn_w: post.tn_w,
            h: post.h,
            w: post.w,
            fsize: post.fsize,
            ext: post.ext,
            tim: post.tim,
            filename: post.filename,
            md5: post.md5,
        }].concat(post.extra_files || []);
        const directory = boardDirectory(board);
        const postUrl = this.formatThreadHtmlUrl(board, thread) + '#' + post.no;

        return files.map(function (file) {
            return Object.assign({}, file, {
                thumbnailUrl: new URL(
                    directory + vichan.directories.thumbnail
                        + file.tim + '.' + vichan.thumbnailExtension,
                    vichan.rootUrl,
                ).href,
                sourceUrl: new URL(
                    directory + vichan.directories.image + file.tim + file.ext,
                    vichan.rootUrl,
                ).href,
                postUrl: postUrl,
                postNo: post.no,
            });
        });
    };

    window.VichanPath.process = function (callback) {
        callback(window.VichanPath.vichan);
    };

    while (window.VichanPath.length > 0) {
        window.VichanPath.process(window.VichanPath.shift());
    }

    window.VichanPath.push = window.VichanPath.process;
    window.VichanPath.unshift = window.VichanPath.process;
}());
