(function() {
    'use strict';

    const EXIF_LIB_URL = 'https://cdn.jsdelivr.net/npm/exif-js';
    
    if (typeof window.tb_settings === 'undefined') window.tb_settings = {};
    if (typeof window.tb_settings['image-viewer'] === 'undefined') window.tb_settings['image-viewer'] = {};

    const SETTINGS_MAP = {
        'iv_enable': 'true',
        'iv_scroll_zoom': 'true',
        'iv_drag': 'false',
        'iv_zoom_min': '0.1',
        'iv_zoom_max': '20'
    };

    for (let key in SETTINGS_MAP) {
        let savedVal = localStorage.getItem(key);
        if (savedVal === null) {
            savedVal = SETTINGS_MAP[key];
        }
        window.tb_settings['image-viewer'][key] = savedVal;
    }

    var settings = new script_settings('image-viewer');

    if (window.Options && Options.get_tab('general')) {
        
        var _ = window._ || function(s) { return s; };

        var val_enable = settings.get('iv_enable', 'true');
        var val_scroll = settings.get('iv_scroll_zoom', 'true');
        var val_drag   = settings.get('iv_drag', 'false');
        var val_min    = settings.get('iv_zoom_min', '0.1');
        var val_max    = settings.get('iv_zoom_max', '20');

        var chk_enable = val_enable === 'true' ? 'checked' : '';
        var chk_scroll = val_scroll === 'true' ? 'checked' : '';
        var chk_drag   = val_drag === 'true' ? 'checked' : '';

        var html = 
            "<fieldset id='image-viewer-fs'><legend>" + _("Image Viewer") + "</legend>" +
                "<label><input type='checkbox' name='iv_enable' " + chk_enable + "> " + _("Enable Image Viewer") + "</label>" +
                "<label><input type='checkbox' name='iv_scroll_zoom' " + chk_scroll + "> " + _("Enable Scroll Zoom") + "</label>" +
                "<label><input type='checkbox' name='iv_drag' " + chk_drag + "> " + _("Enable Mouse Dragging") + "</label>" +
                "<div style='margin-top:5px; display:flex; gap:10px;'>" +
                    "<label>" + _("Min Zoom") + ": <input type='number' step='0.1' name='iv_zoom_min' value='" + val_min + "' style='width:60px;'></label>" +
                    "<label>" + _("Max Zoom") + ": <input type='number' step='1' name='iv_zoom_max' value='" + val_max + "' style='width:60px;'></label>" +
                "</div>" +
            "</fieldset>";

        Options.extend_tab("general", html);

        if (typeof $ !== 'undefined') {
            $(document).on('change', '#image-viewer-fs input', function() {
                var name = $(this).attr('name');
                var val;

                if ($(this).attr('type') === 'checkbox') {
                    val = $(this).is(':checked') ? 'true' : 'false';
                } else {
                    val = $(this).val();
                }

                localStorage.setItem(name, val);
                window.tb_settings['image-viewer'][name] = val;

                if (window.VichanImageViewer) {
                    window.VichanImageViewer.reloadSettings();
                }
            });
        }
    }

    class ImageViewer {
        constructor() {
            this.container = null;
            this.viewer = null;
            this.media = null;
            
            this.state = {
                scale: 1,
                isDragging: false,
                wasDragged: false,
                translateX: 0,
                translateY: 0,
                startX: 0,
                startY: 0
            };

            this.currentImage = null;
            this.allImages = [];
            this.currentIndex = -1;
            this.ZOOM_STEP = 0.2;
            this.isShowExif = false;
            
            this.loadSettings();
            this.init();
        }

        loadSettings() {
            this.cfg = {
                enable:     settings.get('iv_enable', 'true') === 'true',
                scrollZoom: settings.get('iv_scroll_zoom', 'true') === 'true',
                allowDrag:  settings.get('iv_drag', 'false') === 'true',
                minZoom:    parseFloat(settings.get('iv_zoom_min', '0.1')),
                maxZoom:    parseFloat(settings.get('iv_zoom_max', '20'))
            };
        }

        reloadSettings() {
            this.loadSettings();
            
            if (!this.cfg.enable && this.isOpen()) {
                this.close();
            }

            if (this.viewer) {
                if (this.cfg.allowDrag && this.state.scale > 1.05) {
                     this.viewer.classList.add('can-drag');
                } else {
                     this.viewer.classList.remove('can-drag', 'is-dragging');
                }
            }
        }

        init() {
            this.injectStyles();
            this.createContainer();
            this.attachEventListeners();
            this.collectImages();
            window.VichanImageViewer = this;
        }

        injectStyles() {
            const css = `
                .iv-overlay {
                    position: fixed; top: 0; left: 0; width: 100%; height: 100%;
                    background: rgba(10, 10, 12, 0.48); z-index: 10000;
                    display: none; flex-direction: column;
                    user-select: none;
                    backdrop-filter: blur(10px);
                }
                .iv-overlay.iv-active { display: flex; }
                
                .iv-viewer {
                    flex: 1; position: relative; overflow: hidden;
                    display: flex; align-items: center; justify-content: center;
                    cursor: default; width: 100%; height: 100%;
                }
                .iv-viewer.can-drag { cursor: grab; }
                .iv-viewer.is-dragging { cursor: grabbing; }

                .iv-image, .iv-video {
                    position: absolute;
                    max-width: none !important; max-height: none !important;
                    transform-origin: center center;
                    will-change: transform;
                    pointer-events: auto;
                }

                .iv-toolbar {
                    position: absolute; top: 0; right: 0;
                    padding: 10px; z-index: 20; pointer-events: none;
                }
                .iv-btn {
                    pointer-events: auto; background: transparent;
                    border: 1px solid rgba(255,255,255,0.3); color: #ccc;
                    height: 28px; padding: 0 12px;
                    border-radius: 3px; cursor: pointer;
                    font-size: 11px; font-weight: bold;
                    transition: 0.2s; text-transform: uppercase;
                }
                .iv-btn:hover { background: rgba(255,255,255,0.2); color: #fff; border-color: #fff; }

                .iv-info {
                    position: absolute; bottom: 0; left: 0; width: 100%;
                    padding: 8px; text-align: center;
                    background: rgba(0,0,0,0.6); color: #aaa;
                    font-family: monospace; font-size: 13px;
                    pointer-events: none; z-index: 20;
                    border-top: 1px solid #333;
                }

                .iv-nav {
                    position: absolute; top: 0; bottom: 0; width: 15%;
                    display: flex; align-items: center; justify-content: center;
                    font-size: 60px; color: rgba(255,255,255,0);
                    cursor: pointer; z-index: 10; transition: color 0.2s;
                }
                .iv-nav:hover { color: rgba(255,255,255,0.5); }
                .iv-prev { left: 0; background: linear-gradient(90deg, rgba(0,0,0,0.4), rgba(0,0,0,0)); }
                .iv-next { right: 0; background: linear-gradient(270deg, rgba(0,0,0,0.4), rgba(0,0,0,0)); }

                .iv-exif-panel {
                    position: absolute; top: 50px; right: 10px;
                    background: rgba(20,20,20,0.95); color: #ccc;
                    padding: 15px; border-radius: 4px; border: 1px solid #444;
                    width: 340px; font-size: 12px; font-family: monospace;
                    display: none; z-index: 30; pointer-events: auto;
                    box-shadow: 0 5px 15px rgba(0,0,0,0.5);
                    max-height: 80vh; overflow-y: auto; scrollbar-width: thin;
                }
                .iv-exif-panel.active { display: block; }
                
                .iv-exif-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
                .iv-exif-table td { 
                    padding: 3px 5px; border-bottom: 1px solid #333; 
                    word-wrap: break-word; vertical-align: top;
                }
                .iv-exif-table td:first-child { color: #888; width: 45%; font-weight: bold; }
                
                .iv-gps-link { 
                    color: #5fbeff; text-decoration: none; display: block; 
                    margin-top: 10px; text-align: center; border: 1px solid #5fbeff; 
                    padding: 5px; border-radius: 3px; font-weight: bold; font-family: sans-serif;
                }
                .iv-gps-link:hover { background: #5fbeff; color: #000; }
            `;
            const style = document.createElement('style');
            style.textContent = css;
            document.head.appendChild(style);
        }

        createContainer() {
            this.container = document.createElement('div');
            this.container.className = 'iv-overlay';
            this.container.innerHTML = `
                <div class="iv-toolbar">
                    <button class="iv-btn iv-btn-exif">EXIF</button>
                    <button class="iv-btn iv-btn-close" title="Close (Esc)">✕</button>
                </div>
                <div class="iv-exif-panel"></div>
                <div class="iv-viewer">
                    <div class="iv-nav iv-prev">‹</div>
                    <div class="iv-nav iv-next">›</div>
                </div>
                <div class="iv-info"></div>
            `;
            document.body.appendChild(this.container);
            
            this.viewer = this.container.querySelector('.iv-viewer');
            this.exifPanel = this.container.querySelector('.iv-exif-panel');
            this.infoBar = this.container.querySelector('.iv-info');
        }

        collectImages() {
            const imageLinks = document.querySelectorAll('a[href]');
            this.allImages = Array.from(imageLinks).filter(link => {
                let href = link.getAttribute('href');
                if (!href) return false;
                const isPostImg = link.querySelector('.post-image') || link.querySelector('img') || link.classList.contains('post-image-href');
                if (!isPostImg) return false;
                if (href.includes('player.php?v=')) return true;
                return this.isSupportedMedia(this.getFileExtension(href));
            });
        }

        getFileExtension(url) {
            return url.split('.').pop().toLowerCase().split('?')[0];
        }

        isSupportedMedia(ext) {
            return ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'webm', 'mp4'].includes(ext);
        }

        attachEventListeners() {
            document.addEventListener('click', (e) => {
                if (!this.cfg.enable) return;

                const link = e.target.closest('a');
                if (!link || !this.allImages.includes(link)) return;
                if (e.ctrlKey || e.metaKey) return;
                e.preventDefault();
                this.open(link);
            });

            this.container.addEventListener('click', (e) => {
                if (e.target.classList.contains('iv-prev')) { e.stopPropagation(); this.navigate(-1); }
                else if (e.target.classList.contains('iv-next')) { e.stopPropagation(); this.navigate(1); }
                else if (e.target.classList.contains('iv-btn-exif')) { e.stopPropagation(); this.toggleExif(); }
                else if (e.target.classList.contains('iv-btn-close')) { e.stopPropagation(); this.close(); }
            });

            this.container.addEventListener('wheel', (e) => {
                if (!this.media) return;
                if (!this.cfg.scrollZoom) return;

                if (this.exifPanel && this.exifPanel.classList.contains('active') && this.exifPanel.contains(e.target)) {
                    return;
                }

                e.preventDefault();
                const delta = e.deltaY > 0 ? -1 : 1;
                this.zoom(delta * this.ZOOM_STEP);
            }, { passive: false });

            if (this.exifPanel) {
                this.exifPanel.addEventListener('wheel', (e) => {
                    e.stopPropagation();
                }, { passive: true });
            }
            this.viewer.addEventListener('mousedown', (e) => {
                if (e.target.tagName === 'VIDEO' && e.target.hasAttribute('controls')) return; 
                if (e.button !== 0) return;

                if (!this.cfg.allowDrag) {
                     this.state.isDragging = true; 
                     this.state.wasDragged = false;
                     return;
                }

                this.state.isDragging = true;
                this.state.wasDragged = false;
                this.state.startX = e.clientX - this.state.translateX;
                this.state.startY = e.clientY - this.state.translateY;
                this.viewer.classList.add('is-dragging');
            });

            window.addEventListener('mousemove', (e) => {
                if (!this.state.isDragging) return;
                
                if (!this.cfg.allowDrag) return;

                e.preventDefault();
                const x = e.clientX - this.state.startX;
                const y = e.clientY - this.state.startY;
                if (Math.abs(x - this.state.translateX) > 2 || Math.abs(y - this.state.translateY) > 2) {
                    this.state.wasDragged = true;
                }
                this.state.translateX = x;
                this.state.translateY = y;
                this.applyTransform();
            });

            window.addEventListener('mouseup', (e) => {
                if (!this.state.isDragging) return;
                this.state.isDragging = false;
                this.viewer.classList.remove('is-dragging');
                
                if (!this.state.wasDragged && (e.target === this.viewer || e.target === this.media)) {
                    this.close();
                }
            });

            document.addEventListener('keydown', (e) => {
                if (!this.isOpen()) return;
                switch(e.key) {
                    case 'Escape': this.close(); break;
                    case 'ArrowLeft': this.navigate(-1); break;
                    case 'ArrowRight': this.navigate(1); break;
                    case 'i': case 'I': this.toggleExif(); break;
                    case '+': this.zoom(this.ZOOM_STEP); break;
                    case '-': this.zoom(-this.ZOOM_STEP); break;
                    case '0': this.fitToScreen(); break;
                }
            });
        }

        open(linkElement) {
            this.currentImage = linkElement;
            this.currentIndex = this.allImages.indexOf(linkElement);
            
            let href = linkElement.href;
            if (href.includes('player.php?v=')) {
                href = new URLSearchParams(href.split('?')[1]).get('v');
            }

            if (this.media) { this.media.remove(); this.media = null; }
            this.resetTransform();
            this.exifPanel.classList.remove('active');
            this.isShowExif = false;
            this.infoBar.textContent = 'Loading...';

            const ext = this.getFileExtension(href);
            const isVideo = ['webm', 'mp4'].includes(ext);

            if (isVideo) {
                this.media = document.createElement('video');
                this.media.className = 'iv-video';
                this.media.controls = true;
                this.media.autoplay = true;
                this.media.loop = true;
                const vol = localStorage.getItem('iv-vol');
                if (vol) this.media.volume = parseFloat(vol);
                this.media.onvolumechange = () => localStorage.setItem('iv-vol', this.media.volume);
                this.container.querySelector('.iv-btn-exif').style.display = 'none';
            } else {
                this.media = document.createElement('img');
                this.media.className = 'iv-image';
                this.media.draggable = false;
                this.container.querySelector('.iv-btn-exif').style.display = 'inline-block';
            }

            this.media.src = href;
            this.viewer.appendChild(this.media);

            const onLoaded = () => {
                this.fitToScreen();
                this.updateInfo();
            };

            if (isVideo) this.media.onloadedmetadata = onLoaded;
            else this.media.onload = onLoaded;

            this.container.classList.add('iv-active');
            document.body.style.overflow = 'hidden';
        }

        close() {
            if (this.media && this.media.tagName === 'VIDEO') this.media.pause();
            this.container.classList.remove('iv-active');
            document.body.style.overflow = '';
        }

        navigate(dir) {
            if (!this.allImages.length) return;
            let idx = this.currentIndex + dir;
            if (idx < 0) idx = this.allImages.length - 1;
            if (idx >= this.allImages.length) idx = 0;
            this.open(this.allImages[idx]);
        }

        isOpen() {
            return this.container.classList.contains('iv-active');
        }

        resetTransform() {
            this.state = { scale: 1, isDragging: false, wasDragged: false, translateX: 0, translateY: 0 };
            this.viewer.classList.remove('can-drag');
        }

        fitToScreen() {
            if (!this.media) return;
            const wW = window.innerWidth;
            const wH = window.innerHeight;
            const mW = this.media.videoWidth || this.media.naturalWidth;
            const mH = this.media.videoHeight || this.media.naturalHeight;
            if (!mW || !mH) return;

            const scaleX = wW / mW;
            const scaleY = wH / mH;
            this.state.scale = Math.min(1, Math.min(scaleX, scaleY) * 0.95);
            
            this.applyTransform();
            this.updateInfo();
        }

        zoom(amount) {
            let newScale = this.state.scale + amount;
            if (newScale < this.cfg.minZoom) newScale = this.cfg.minZoom;
            if (newScale > this.cfg.maxZoom) newScale = this.cfg.maxZoom;
            
            this.state.scale = newScale;
            this.applyTransform();
            this.updateInfo();
        }

        applyTransform() {
            if (!this.media) return;
            this.media.style.transform = `translate(${this.state.translateX}px, ${this.state.translateY}px) scale(${this.state.scale})`;
            
            if (this.cfg.allowDrag && this.state.scale > 1.05) {
                this.viewer.classList.add('can-drag');
            } else {
                this.viewer.classList.remove('can-drag');
            }
        }

        updateInfo() {
            if (!this.media) return;
            const w = this.media.videoWidth || this.media.naturalWidth;
            const h = this.media.videoHeight || this.media.naturalHeight;
            const zoom = Math.round(this.state.scale * 100);
            this.infoBar.textContent = `${w}x${h}px | ${zoom}% | ${this.currentIndex + 1}/${this.allImages.length}`;
        }

        async toggleExif() {
            if (this.isShowExif) {
                this.exifPanel.classList.remove('active');
                this.isShowExif = false;
                return;
            }

            this.exifPanel.classList.add('active');
            this.exifPanel.innerHTML = '<div style="color:#888; text-align:center;">Завантаження...</div>';
            this.isShowExif = true;

            try {
                if (typeof window.EXIF === 'undefined') {
                    await this.loadScript(EXIF_LIB_URL);
                }
                const self = this;
                window.EXIF.getData(this.media, function() {
                    const data = window.EXIF.getAllTags(this);
                    self.renderExif(data);
                });
            } catch (err) {
                this.exifPanel.innerHTML = 'Помилка завантаження бібліотеки EXIF';
            }
        }

        loadScript(src) {
            return new Promise((resolve, reject) => {
                if (document.querySelector(`script[src="${src}"]`)) return resolve();
                const s = document.createElement('script');
                s.src = src;
                s.onload = resolve;
                s.onerror = reject;
                document.head.appendChild(s);
            });
        }

        cleanValue(val) {
            if (val === undefined || val === null) return '';
            if (val instanceof Number || typeof val === 'number') {
                const n = Number(val);
                return Number.isInteger(n) ? n : Number(n.toFixed(2));
            }
            if (Array.isArray(val)) {
                return val.map(v => {
                    if (typeof v === 'number' || v instanceof Number) {
                        const n = Number(v);
                        return Number.isInteger(n) ? n : Number(n.toFixed(2));
                    }
                    return v;
                }).join(', ');
            }
            return String(val);
        }

        renderExif(data) {
            if (!data || Object.keys(data).length === 0) {
                this.exifPanel.innerHTML = '<div style="color:#888; text-align:center;">Метадані відсутні</div>';
                return;
            }

            let html = '<table class="iv-exif-table">';
            if (data['GPSLatitude'] && data['GPSLongitude']) {
                const lat = this.convertDMSToDD(data['GPSLatitude'][0], data['GPSLatitude'][1], data['GPSLatitude'][2], data['GPSLatitudeRef']);
                const lon = this.convertDMSToDD(data['GPSLongitude'][0], data['GPSLongitude'][1], data['GPSLongitude'][2], data['GPSLongitudeRef']);
                html += `<a href="https://www.openstreetmap.org/?mlat=${lat}&mlon=${lon}&zoom=15" target="_blank" class="iv-gps-link">📍 MAP LOCATION</a>`;
            }
            let skipKeys = ['thumbnail', 'undefined', 'Orientation', 'MakerNote', 'UserComment', 'SubjectArea'];
            for (let key in data) {
                if (skipKeys.includes(key)) continue;
                let rawVal = data[key];
                if (typeof rawVal === 'string' && rawVal.length > 100 && rawVal.includes('base64')) continue;
                let val = this.cleanValue(rawVal);
                if (val === '') continue;
                html += `<tr><td>${key}</td><td>${val}</td></tr>`;
            }
            html += '</table>';

            this.exifPanel.innerHTML = html;
        }

        convertDMSToDD(d, m, s, dir) {
            let dd = d + m / 60 + s / 3600;
            if (dir == "S" || dir == "W") dd = dd * -1;
            return dd.toFixed(6);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => new ImageViewer());
    } else {
        new ImageViewer();
    }

    if (typeof window.addEventListener !== 'undefined') {
        window.addEventListener('new_post', () => {
            if (window.VichanImageViewer) window.VichanImageViewer.collectImages();
        });
        if (typeof $ !== 'undefined') {
            $(document).on('new_post', () => {
                if (window.VichanImageViewer) window.VichanImageViewer.collectImages();
            });
        }
    }
})();
