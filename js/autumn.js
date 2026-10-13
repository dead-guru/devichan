/*
 * Autumn decorations. Enable with:
 *   $config['additional_javascript'][] = 'js/autumn.js';
 *
 * GIFs from GifCities:
 * https://blob.gifcities.org/gifcities/3BHIKPNWKDPOSX2QGOLJDGT4XQFAY3UH.gif
 * https://blob.gifcities.org/gifcities/DXYWPKZKQOXATD6F3JM3NONAQDJCB7CG.gif
 */
(function () {
    'use strict';

    var assets = configRoot + 'static/seasonal/autumn/';
    var style = document.createElement('style');
    style.textContent = `
        .autumn-free {
            width: 1.5em;
            height: 1.5em;
            margin-left: .2em;
            vertical-align: .15em;
            pointer-events: none;
        }
        .autumn-banner { position: relative; }
        .autumn-banner-leaves {
            position: absolute;
            background: url(${JSON.stringify(assets + 'leaves.gif')}) repeat;
            pointer-events: none;
        }
        @media (prefers-reduced-motion: reduce) {
            .autumn-free, .autumn-banner-leaves { display: none; }
        }
    `;
    document.head.appendChild(style);

    function decorate() {
        var form = document.querySelector('form[name="post"]');
        var heading = document.querySelector('.open-form');
        if (form && heading && !form.querySelector('input[name="thread"]')) {
            var free = document.createElement('img');
            free.className = 'autumn-free';
            free.src = assets + 'free.gif';
            free.alt = '';
            heading.appendChild(free);
        }

        document.querySelectorAll('img.board_image, img.banner').forEach(function (image) {
            var wrapper = document.createElement('div');
            wrapper.className = 'autumn-banner';
            image.before(wrapper);
            wrapper.appendChild(image);

            var leaves = document.createElement('span');
            leaves.className = 'autumn-banner-leaves';
            leaves.setAttribute('aria-hidden', 'true');
            wrapper.appendChild(leaves);

            function resize() {
                leaves.style.left = image.offsetLeft + image.clientLeft + 'px';
                leaves.style.top = image.offsetTop + image.clientTop + 'px';
                leaves.style.width = image.clientWidth + 'px';
                leaves.style.height = image.clientHeight + 'px';
            }

            var observer = new ResizeObserver(resize);
            observer.observe(image);
            observer.observe(wrapper);
            image.addEventListener('load', resize);
            resize();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', decorate);
    } else {
        decorate();
    }
}());
