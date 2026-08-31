(function () {
    'use strict';

    if (window.active_page !== 'thread') {
        return;
    }

    const STORAGE_WIDTH = 'thread-file-list-width';
    const STORAGE_HEIGHT = 'thread-file-list-height';
    const VISUAL_SELECTOR = 'img.post-image, video.post-image';

    function findPostId(file) {
        const post = file.closest('.post');
        if (!post) {
            return null;
        }

        const previous = post.previousElementSibling;
        const anchor = post.querySelector('.post_anchor[id]')
            || (previous && previous.matches('.post_anchor[id]') ? previous : null)
            || post.parentElement.querySelector(':scope > .post_anchor[id]');

        return anchor ? anchor.id : null;
    }

    function describeFile(file) {
        const link = file.querySelector('.fileinfo a[href]');
        if (!link) {
            return null;
        }

        const preview = file.querySelector(VISUAL_SELECTOR);
        const width = preview ? parseInt(preview.getAttribute('width'), 10) || 0 : 0;
        const height = preview ? parseInt(preview.getAttribute('height'), 10) || 0 : 0;
        const path = new URL(link.href, document.baseURI).pathname;

        return {
            href: link.href,
            label: link.getAttribute('download') || path.substring(path.lastIndexOf('/') + 1),
            postId: findPostId(file),
            preview: preview,
            width: width,
            height: height,
        };
    }

    function createItem(fileInfo) {
        const item = document.createElement('div');
        item.className = 'thread-file-list__item';

        const fileLink = document.createElement('a');
        fileLink.href = fileInfo.href;
        fileLink.target = '_blank';
        fileLink.rel = 'noopener';
        fileLink.title = fileInfo.label;
        fileLink.className = fileInfo.width >= fileInfo.height ? 'landscape' : 'portrait';

        if (fileInfo.preview) {
            const preview = fileInfo.preview.cloneNode(true);
            preview.removeAttribute('id');
            preview.removeAttribute('style');
            preview.removeAttribute('loading');
            preview.className = 'thread-file-list__preview';
            fileLink.appendChild(preview);
        } else {
            const label = document.createElement('span');
            label.className = 'thread-file-list__filename';
            label.textContent = fileInfo.label;
            fileLink.appendChild(label);
        }

        item.appendChild(fileLink);

        if (fileInfo.postId) {
            const postLink = document.createElement('a');
            postLink.className = 'thread-file-list__post-link';
            postLink.href = '#' + fileInfo.postId;
            postLink.textContent = '>>' + fileInfo.postId;
            item.appendChild(postLink);
        }

        return item;
    }

    function filesIn(root) {
        return Array.from(root.querySelectorAll('.files > .file'))
            .map(describeFile)
            .filter(Boolean);
    }

    function addFiles(list, root, prepend) {
        const files = filesIn(root);
        const fragment = document.createDocumentFragment();

        files.forEach(function (file) {
            fragment.appendChild(createItem(file));
        });

        if (prepend) {
            list.prepend(fragment);
        } else {
            list.appendChild(fragment);
        }
    }

    function createGallery() {
        if (document.querySelector('.thread-file-list')) {
            return;
        }

        const style = document.createElement('style');
        style.textContent = `
.thread-file-list {
    background: rgb(136 136 136 / 13%);
    border: 2px solid;
    border-radius: 10px;
    box-sizing: border-box;
    margin: 5px;
    max-width: calc(100% - 10px);
    min-height: 120px;
    overflow: auto;
    padding: 8px;
    resize: both;
    width: min(90vw, 1200px);
}
.thread-file-list__title {
    display: block;
    margin-bottom: 6px;
}
.thread-file-list__items {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
}
.thread-file-list__item {
    display: flex;
    flex-direction: column;
    max-width: 180px;
    text-align: center;
}
.thread-file-list__preview {
    display: block;
    height: 125px;
    max-width: 180px;
    object-fit: contain;
    width: auto;
}
.thread-file-list__filename {
    display: block;
    max-width: 180px;
    overflow-wrap: anywhere;
    padding: 12px;
}
.thread-file-list__post-link {
    opacity: .35;
}
.thread-file-list__item:hover .thread-file-list__post-link,
.thread-file-list__post-link:focus {
    opacity: 1;
}
`;
        document.head.appendChild(style);

        const gallery = document.createElement('section');
        gallery.className = 'thread-file-list';
        gallery.setAttribute('aria-label', 'Thread files');

        const title = document.createElement('strong');
        title.className = 'thread-file-list__title';
        title.textContent = 'Thread files';
        gallery.appendChild(title);

        const list = document.createElement('div');
        list.className = 'thread-file-list__items';
        gallery.appendChild(list);

        try {
            gallery.style.width = localStorage.getItem(STORAGE_WIDTH) || '';
            gallery.style.height = localStorage.getItem(STORAGE_HEIGHT) || '';
        } catch (error) {
            // Storage can be unavailable in private browsing modes.
        }

        new MutationObserver(function () {
            try {
                if (gallery.style.width) {
                    localStorage.setItem(STORAGE_WIDTH, gallery.style.width);
                }
                if (gallery.style.height) {
                    localStorage.setItem(STORAGE_HEIGHT, gallery.style.height);
                }
            } catch (error) {
                // The gallery still works when its size cannot be persisted.
            }
        }).observe(gallery, {
            attributes: true,
            attributeFilter: ['style'],
        });

        addFiles(list, document, false);

        const insertionPoint = document.querySelector('.boardlist.bottom, footer');
        document.body.insertBefore(gallery, insertionPoint);

        $(document).on('new_post.threadFileList', function (event, post) {
            addFiles(list, post, true);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', createGallery, { once: true });
    } else {
        createGallery();
    }
}());
