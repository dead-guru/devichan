(function () {
    'use strict';

    const VISUAL_EXTENSIONS = new Set([
        '.png', '.jpg', '.jpeg', '.gif', '.webp', '.avif', '.mp4', '.webm', '.ts',
    ]);

    function positiveInteger(value, fallback) {
        const parsed = Number.parseInt(value, 10);
        return Number.isInteger(parsed) && parsed >= 0 ? parsed : fallback;
    }

    function scaledDimensions(file, maximumWidth, maximumHeight) {
        const width = Number(file.tn_w) || 0;
        const height = Number(file.tn_h) || 0;
        if (width === 0 || height === 0) {
            return { width: maximumWidth || 0, height: maximumHeight || 0 };
        }

        const widthScale = maximumWidth === null ? Infinity : maximumWidth / width;
        const heightScale = maximumHeight === null ? Infinity : maximumHeight / height;
        const scale = Math.min(widthScale, heightScale, 1);

        return {
            width: Math.round(width * scale),
            height: Math.round(height * scale),
        };
    }

    function createThumbnail(file, maximumWidth, maximumHeight) {
        const cell = document.createElement('div');
        const visual = VISUAL_EXTENSIONS.has(String(file.ext).toLowerCase());
        cell.className = 'thread-file';
        cell.classList.add(visual
            ? (Number(file.tn_w) >= Number(file.tn_h) ? 'landscape' : 'portrait')
            : 'file');

        const link = document.createElement('a');
        link.href = file.sourceUrl;
        link.target = '_blank';
        link.rel = 'noopener';
        link.title = (file.filename || file.tim) + (file.ext || '');

        if (visual) {
            const image = document.createElement('img');
            image.className = 'thread-file__thumbnail';
            image.src = file.thumbnailUrl;
            image.alt = link.title;
            image.loading = 'lazy';
            const dimensions = scaledDimensions(file, maximumWidth, maximumHeight);
            if (dimensions.width > 0) {
                image.width = dimensions.width;
            }
            if (dimensions.height > 0) {
                image.height = dimensions.height;
            }
            link.appendChild(image);
        } else {
            const filename = document.createElement('span');
            filename.className = 'thread-file__filename';
            filename.textContent = link.title;
            link.appendChild(filename);
        }

        cell.appendChild(link);

        const postLink = document.createElement('a');
        postLink.className = 'thread-file__post-link';
        postLink.href = file.postUrl;
        postLink.textContent = '>>' + file.postNo;
        cell.appendChild(postLink);

        return cell;
    }

    async function renderGallery(vichan, gallery) {
        const board = gallery.getAttribute('data-board');
        const thread = gallery.getAttribute('data-thread');
        if (!board || !thread) {
            throw new Error('Thread gallery requires data-board and data-thread.');
        }

        const maximumImages = positiveInteger(
            gallery.getAttribute('data-max-images'),
            Number.MAX_SAFE_INTEGER,
        );
        const maximumWidth = positiveInteger(gallery.getAttribute('data-thumb-width'), null);
        const maximumHeight = positiveInteger(gallery.getAttribute('data-thumb-height'), null);
        const visualOnly = gallery.getAttribute('data-visual-only') === 'true';

        gallery.setAttribute('aria-busy', 'true');
        const response = await vichan.fetchThread(board, thread);
        if (!response.ok) {
            throw new Error('Unable to load thread: HTTP ' + response.status + '.');
        }

        const payload = await response.json();
        const fragment = document.createDocumentFragment();
        let count = 0;

        for (let postIndex = payload.posts.length - 1; postIndex >= 0; postIndex -= 1) {
            const files = vichan.listPostFiles(payload.posts[postIndex], board, thread);
            for (const file of files) {
                if (visualOnly && !VISUAL_EXTENSIONS.has(String(file.ext).toLowerCase())) {
                    continue;
                }
                if (count >= maximumImages) {
                    break;
                }
                fragment.appendChild(createThumbnail(file, maximumWidth, maximumHeight));
                count += 1;
            }
            if (count >= maximumImages) {
                break;
            }
        }

        gallery.replaceChildren(fragment);
        gallery.removeAttribute('aria-busy');
    }

    function showError(gallery, error) {
        const message = document.createElement('span');
        message.setAttribute('role', 'alert');
        message.textContent = error instanceof Error ? error.message : String(error);
        gallery.replaceChildren(message);
        gallery.removeAttribute('aria-busy');
    }

    function initialize(vichan) {
        const start = function () {
            document.querySelectorAll('[data-vichan-thread-files]').forEach(function (gallery) {
                renderGallery(vichan, gallery).catch(function (error) {
                    showError(gallery, error);
                });
            });
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', start, { once: true });
        } else {
            start();
        }
    }

    window.VichanPath = window.VichanPath || [];
    window.VichanPath.push(initialize);
}());
