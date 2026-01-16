/*
 * paint-tool.js - Simple drawing tool for posts
 *
 * Usage:
 *   $config['additional_javascript'][] = 'js/jquery.min.js';
 *   $config['additional_javascript'][] = 'js/paint-tool.js';
 */

(function($) {
    'use strict';

    if (typeof active_page === 'undefined' || (active_page !== 'thread' && active_page !== 'index')) {
        return;
    }

    class PaintState {
        constructor() {
            this.color = '#000000';
            this.opacity = 1;
            this.brushSize = 4;
            this.load();
        }

        save() {
            try {
                localStorage.setItem('paint-tool-settings', JSON.stringify({
                    color: this.color,
                    opacity: this.opacity,
                    brushSize: this.brushSize
                }));
            } catch (e) {}
        }

        load() {
            try {
                const saved = localStorage.getItem('paint-tool-settings');
                if (saved) Object.assign(this, JSON.parse(saved));
            } catch (e) {}
        }
    }

    class Tool {
        constructor(engine) { this.engine = engine; }
        onStart(x, y) { this.startX = x; this.startY = y; }
        onMove(x, y) {}
        onEnd(x, y) {}
    }

    class BrushTool extends Tool {
        onStart(x, y) {
            this.engine.saveSnapshot();
            this.drawDot(x, y);
        }
        onMove(x, y) {
            const ctx = this.engine.tempCtx;
            ctx.strokeStyle = this.engine.state.color;
            ctx.lineWidth = this.engine.state.brushSize;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            ctx.lineTo(x, y);
            ctx.stroke();
            this.engine.renderTemp();
        }
        drawDot(x, y) {
            const ctx = this.engine.tempCtx;
            this.engine.clearTemp();
            ctx.fillStyle = this.engine.state.color;
            ctx.beginPath();
            ctx.arc(x, y, this.engine.state.brushSize / 2, 0, Math.PI * 2);
            ctx.fill();
            ctx.beginPath();
            ctx.moveTo(x, y);
            this.engine.renderTemp();
        }
    }

    class EraserTool extends Tool {
        onStart(x, y) { this.draw(x, y); }
        onMove(x, y) { this.draw(x, y); }
        draw(x, y) {
            const ctx = this.engine.ctx;
            ctx.fillStyle = '#ffffff';
            ctx.strokeStyle = '#ffffff';
            ctx.lineWidth = this.engine.state.brushSize * 2;
            ctx.lineCap = 'round';
            ctx.beginPath();
            ctx.arc(x, y, this.engine.state.brushSize, 0, Math.PI * 2);
            ctx.fill();
        }
    }

    class ShapeTool extends Tool {
        onStart(x, y) {
            super.onStart(x, y);
            this.engine.saveSnapshot();
        }
        drawShape(ctx, x, y) {}
        onMove(x, y) {
            this.engine.restoreSnapshot();
            this.engine.ctx.globalAlpha = this.engine.state.opacity;
            this.engine.ctx.strokeStyle = this.engine.state.color;
            this.engine.ctx.lineWidth = this.engine.state.brushSize;
            this.engine.ctx.beginPath();
            this.drawShape(this.engine.ctx, x, y);
            this.engine.ctx.stroke();
            this.engine.ctx.globalAlpha = 1;
        }
    }

    class LineTool extends ShapeTool {
        drawShape(ctx, x, y) {
            ctx.lineCap = 'round';
            ctx.moveTo(this.startX, this.startY);
            ctx.lineTo(x, y);
        }
    }

    class RectTool extends ShapeTool {
        drawShape(ctx, x, y) {
            ctx.rect(this.startX, this.startY, x - this.startX, y - this.startY);
        }
    }

    class CircleTool extends ShapeTool {
        drawShape(ctx, x, y) {
            const radiusX = Math.abs(x - this.startX) / 2;
            const radiusY = Math.abs(y - this.startY) / 2;
            const centerX = this.startX + (x - this.startX) / 2;
            const centerY = this.startY + (y - this.startY) / 2;
            ctx.ellipse(centerX, centerY, radiusX, radiusY, 0, 0, Math.PI * 2);
        }
    }

    class TextTool extends Tool {
        onStart(x, y) {
            const text = prompt('Enter text:');
            if (!text) return;
            const ctx = this.engine.ctx;
            const fontSize = Math.max(16, this.engine.state.brushSize * 4);
            ctx.globalAlpha = this.engine.state.opacity;
            ctx.font = `${fontSize}px Arial`;
            ctx.fillStyle = this.engine.state.color;
            ctx.fillText(text, x, y);
            ctx.globalAlpha = 1;
            this.engine.commitState();
        }
    }

    class FillTool extends Tool {
        onStart(x, y) {
            this.floodFill(Math.floor(x), Math.floor(y));
            this.engine.commitState();
        }

        floodFill(x, y) {
            const ctx = this.engine.ctx;
            const width = ctx.canvas.width;
            const height = ctx.canvas.height;
            const imageData = ctx.getImageData(0, 0, width, height);
            const data = imageData.data;

            const targetIdx = (y * width + x) * 4;
            const targetColor = { r: data[targetIdx], g: data[targetIdx + 1], b: data[targetIdx + 2] };
            const fillColor = this.hexToRgb(this.engine.state.color);

            if (targetColor.r === fillColor.r && targetColor.g === fillColor.g && targetColor.b === fillColor.b) return;

            const stack = [[x, y]];
            const visited = new Uint8Array(width * height);

            while (stack.length) {
                const [px, py] = stack.pop();
                if (px < 0 || px >= width || py < 0 || py >= height) continue;
                
                const key = py * width + px;
                if (visited[key]) continue;
                
                const idx = key * 4;
                if (Math.abs(data[idx] - targetColor.r) > 10 ||
                    Math.abs(data[idx+1] - targetColor.g) > 10 ||
                    Math.abs(data[idx+2] - targetColor.b) > 10) continue;

                visited[key] = 1;
                data[idx] = fillColor.r;
                data[idx + 1] = fillColor.g;
                data[idx + 2] = fillColor.b;
                data[idx + 3] = 255;

                stack.push([px + 1, py], [px - 1, py], [px, py + 1], [px, py - 1]);
            }
            ctx.putImageData(imageData, 0, 0);
        }

        hexToRgb(hex) {
            const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
            return result ? { r: parseInt(result[1], 16), g: parseInt(result[2], 16), b: parseInt(result[3], 16) } : { r: 0, g: 0, b: 0 };
        }
    }

    class DrawingEngine {
        constructor(canvas, state) {
            this.canvas = canvas;
            this.state = state;
            this.ctx = canvas.getContext('2d', { willReadFrequently: true });
            
            this.tempCanvas = document.createElement('canvas');
            this.tempCanvas.width = canvas.width;
            this.tempCanvas.height = canvas.height;
            this.tempCtx = this.tempCanvas.getContext('2d');

            this.history = [];
            this.historyIndex = -1;
            this.snapshot = null;
            
            this.tools = {
                brush: new BrushTool(this),
                eraser: new EraserTool(this),
                line: new LineTool(this),
                rect: new RectTool(this),
                circle: new CircleTool(this),
                text: new TextTool(this),
                fill: new FillTool(this)
            };
            this.currentTool = this.tools.brush;
        }

        setTool(toolName) {
            if (this.tools[toolName]) this.currentTool = this.tools[toolName];
        }

        start(x, y) {
            this.currentTool.onStart(x, y);
        }

        move(x, y) {
            this.currentTool.onMove(x, y);
        }

        end() {
            this.currentTool.onEnd();
            if (this.currentTool instanceof BrushTool || this.currentTool instanceof EraserTool) {
               this.commitState();
            }
        }

        saveSnapshot() {
            this.snapshot = this.ctx.getImageData(0, 0, this.canvas.width, this.canvas.height);
        }
        restoreSnapshot() {
            if (this.snapshot) this.ctx.putImageData(this.snapshot, 0, 0);
        }
        clearTemp() {
            this.tempCtx.clearRect(0, 0, this.tempCanvas.width, this.tempCanvas.height);
        }
        renderTemp() {
            this.restoreSnapshot();
            this.ctx.globalAlpha = this.state.opacity;
            this.ctx.drawImage(this.tempCanvas, 0, 0);
            this.ctx.globalAlpha = 1;
        }

        commitState() {
            if (this.historyIndex < this.history.length - 1) {
                this.history = this.history.slice(0, this.historyIndex + 1);
            }
            this.history.push(this.canvas.toDataURL());
            this.historyIndex++;
            if (this.history.length > 30) {
                this.history.shift();
                this.historyIndex--;
            }
        }

        undo() {
            if (this.historyIndex <= 0) return;
            this.historyIndex--;
            this.loadHistoryItem();
        }

        redo() {
            if (this.historyIndex >= this.history.length - 1) return;
            this.historyIndex++;
            this.loadHistoryItem();
        }

        loadHistoryItem() {
            const img = new Image();
            img.onload = () => {
                this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
                this.ctx.fillStyle = '#ffffff';
                this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);
                this.ctx.drawImage(img, 0, 0);
            };
            img.src = this.history[this.historyIndex];
        }

        clear() {
            this.ctx.fillStyle = '#ffffff';
            this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);
            this.commitState();
        }

        resize(w, h) {
            const data = this.canvas.toDataURL();
            const img = new Image();
            img.onload = () => {
                this.canvas.width = w; this.canvas.height = h;
                this.tempCanvas.width = w; this.tempCanvas.height = h;
                this.ctx.fillStyle = '#ffffff';
                this.ctx.fillRect(0, 0, w, h);
                this.ctx.drawImage(img, 0, 0);
                this.commitState();
            };
            img.src = data;
        }

        loadFromImage(img) {
            let w = img.width, h = img.height;
            const maxW = Math.min(window.innerWidth - 100, 1200);
            const maxH = Math.min(window.innerHeight - 300, 800);
            
            if (w > maxW || h > maxH) {
                const scale = Math.min(maxW / w, maxH / h);
                w = Math.floor(w * scale);
                h = Math.floor(h * scale);
            }
            
            this.canvas.width = w;
            this.canvas.height = h;
            this.tempCanvas.width = w;
            this.tempCanvas.height = h;
            
            this.ctx.fillStyle = '#ffffff';
            this.ctx.fillRect(0, 0, w, h);
            this.ctx.drawImage(img, 0, 0, w, h);
            
            this.history = [];
            this.historyIndex = -1;
            this.commitState();
            return { w, h };
        }
    }

    class UIManager {
        constructor(controller) {
            this.controller = controller;
            this.state = controller.state;
            this.injectStyles();
        }

        open(imageUrl) {
            if ($('.paint-modal-overlay').length) return;
            const isMobile = window.innerWidth <= 600;
            const dims = {
                w: isMobile ? Math.min(window.innerWidth - 40, 400) : 600,
                h: isMobile ? Math.min(window.innerHeight - 250, 400) : 400
            };

            this.renderModal(dims);
            this.bindEvents();
            
            const canvas = $('.paint-canvas')[0];
            this.controller.initEngine(canvas);
            this.controller.engine.clear(); 
            
            if (imageUrl) this.controller.loadImage(imageUrl);
        }

        renderModal(dims) {
            const html = `
                <div class="paint-modal-overlay">
                    <div class="paint-modal">
                        <div class="paint-header"><h3>Dead Paint</h3><button class="paint-close">&times;</button></div>
                        <div class="paint-toolbar">
                            <div class="paint-toolbar-group">
                                ${['brush', 'eraser', 'line', 'rect', 'circle', 'fill', 'text'].map(t => 
                                    `<button class="paint-btn ${t === 'brush' ? 'active' : ''}" title="${t.toUpperCase()}" data-tool="${t}">${t[0].toUpperCase()}</button>`
                                ).join('')}
                            </div>
                            <div class="paint-toolbar-group">
                                <input type="color" class="paint-color-input" value="${this.state.color}">
                                <input type="range" title="Opacity" class="paint-range opacity-ctl" min="0" max="100" value="${this.state.opacity*100}">
                                <span class="paint-lbl opacity-val">${Math.round(this.state.opacity*100)}%</span>
                            </div>
                            <div class="paint-toolbar-group">
                                <input type="range" title="Size" class="paint-range size-ctl" min="1" max="50" value="${this.state.brushSize}">
                                <span class="paint-lbl size-val">${this.state.brushSize}px</span>
                            </div>
                            <div class="paint-toolbar-group">
                                <button class="paint-btn action-btn" data-action="undo" title="Undo">&lt;</button>
                                <button class="paint-btn action-btn" data-action="redo" title="Redo">&gt;</button>
                                <button class="paint-btn action-btn" data-action="clear" title="Clear">X</button>
                                <button class="paint-btn action-btn" data-action="load" title="Load">Load</button>
                                <button class="paint-btn action-btn" data-action="save" title="Save">Save</button>
                            </div>
                        </div>
                        <div class="paint-canvas-container">
                            <canvas class="paint-canvas" width="${dims.w}" height="${dims.h}"></canvas>
                        </div>
                        <div class="paint-footer">
                            <div class="paint-dimensions">
                                <input type="number" id="paint-w" class="paint-dim" value="${dims.w}"> x 
                                <input type="number" id="paint-h" class="paint-dim" value="${dims.h}">
                                <button class="paint-btn" data-action="resize" title="Resize">Resize</button>
                            </div>
                            <div class="paint-actions">
                                <button class="paint-btn" data-action="cancel" title="Cancel">Cancel</button>
                                <button class="paint-btn paint-btn-done" data-action="done" title="Done">Done</button>
                            </div>
                        </div>
                    </div>
                </div>`;
            $(html).appendTo('body');
        }

        bindEvents() {
            const self = this;
            
            $('[data-tool]').on('click', function() {
                $('[data-tool]').removeClass('active');
                $(this).addClass('active');
                self.controller.setTool($(this).data('tool'));
            });

            $('.paint-color-input').on('input', function() { self.state.color = this.value; self.state.save(); });
            $('.opacity-ctl').on('input', function() { 
                self.state.opacity = this.value / 100; 
                $('.opacity-val').text(this.value + '%');
                self.state.save(); 
            });
            $('.size-ctl').on('input', function() { 
                self.state.brushSize = this.value; 
                $('.size-val').text(this.value + 'px');
                self.state.save(); 
            });

            $('.paint-modal [data-action]').on('click', function() {
                const action = $(this).data('action');
                if (self.controller[action]) self.controller[action]();
                else if (self.controller.engine && self.controller.engine[action]) self.controller.engine[action]();
            });

            $('.paint-close, [data-action="cancel"], .paint-modal-overlay').on('click', function(e) {
                if (e.target === this) self.controller.close();
            });

            const canvas = $('.paint-canvas')[0];
            const getCoords = (e) => {
                const rect = canvas.getBoundingClientRect();
                const scaleX = canvas.width / rect.width;
                const scaleY = canvas.height / rect.height;
                const clientX = e.touches ? e.touches[0].clientX : e.clientX;
                const clientY = e.touches ? e.touches[0].clientY : e.clientY;
                return { x: (clientX - rect.left) * scaleX, y: (clientY - rect.top) * scaleY };
            };

            let isDrawing = false;
            const start = (e) => {
                e.preventDefault();
                isDrawing = true;
                const c = getCoords(e);
                self.controller.engine.start(c.x, c.y);
            };
            const move = (e) => {
                if (!isDrawing) return;
                e.preventDefault();
                const c = getCoords(e);
                self.controller.engine.move(c.x, c.y);
            };
            const end = (e) => {
                if (!isDrawing) return;
                isDrawing = false;
                self.controller.engine.end();
            };

            canvas.addEventListener('mousedown', start);
            canvas.addEventListener('mousemove', move);
            canvas.addEventListener('mouseup', end);
            canvas.addEventListener('mouseleave', end);
            canvas.addEventListener('touchstart', start, { passive: false });
            canvas.addEventListener('touchmove', move, { passive: false });
            canvas.addEventListener('touchend', end);
            
            $(document).on('keydown.paint', (e) => {
                if (e.key === 'Escape') self.controller.close();
                if ((e.ctrlKey || e.metaKey) && e.key === 'z') {
                    e.preventDefault();
                    e.shiftKey ? self.controller.engine.redo() : self.controller.engine.undo();
                }
            });
            
            $(document).on('paste.paint', (e) => self.controller.handlePaste(e));
        }

        cleanup() {
            $('.paint-modal-overlay').remove();
            $(document).off('.paint');
        }

        injectStyles() {
            if ($('#paint-tool-styles').length) return;
            const css = `
                .paint-modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 10000; display: flex; align-items: center; justify-content: center; }
                .paint-modal { background: #f0f0f0; border: 1px solid #888; box-shadow: 2px 2px 10px rgba(0,0,0,0.3); display: flex; flex-direction: column; max-width: 95vw; max-height: 95vh; }
                .paint-header { display: flex; justify-content: space-between; padding: 6px 10px; background: #e0e0e0; border-bottom: 1px solid #888; }
                .paint-header h3 { margin: 0; font-size: 13px; color: #333; }
                .paint-close { cursor: pointer; border: 1px solid #888; background: #ddd; width: 20px; padding: 0; margin: 0; }
                .paint-toolbar { display: flex; flex-wrap: wrap; gap: 4px; padding: 4px; background: #d4d4d4; border-bottom: 1px solid #888; }
                .paint-toolbar-group { display: flex; gap: 2px; padding: 0 6px; border-right: 1px solid #aaa; align-items: center; }
                .paint-btn { background: #e8e8e8; border: 1px solid #888; cursor: pointer; min-width: 28px; height: 28px; font-size: 12px; padding: 0 5px; margin: 0; }
                .paint-btn:hover { background: #d0d0d0; }
                .paint-btn.active { background: #b0b0b0; border-color: #555; box-shadow: inset 1px 1px 2px rgba(0,0,0,0.2); }
                .paint-btn-done { background: #90c090; font-weight: bold; }
                .paint-color-input { width: 28px; height: 28px; border: 1px solid #888; padding: 0; cursor: pointer; }
                .paint-canvas-container { flex: 1; overflow: auto; background: #808080; padding: 10px; display: flex; justify-content: center; }
                .paint-canvas { background: #fff; box-shadow: 2px 2px 5px rgba(0,0,0,0.3); cursor: crosshair; touch-action: none; }
                .paint-footer { display: flex; justify-content: space-between; padding: 6px 10px; background: #d4d4d4; border-top: 1px solid #888; }
                .paint-dim { width: 50px; text-align: center; }
                .paint-lbl { font-size: 11px; min-width: 30px; text-align: right; }
                .paint-range { width: 60px; height: 18px; cursor: pointer; }
                .paint-edit-image { cursor: pointer; opacity: 0.6; } .paint-edit-image:hover { opacity: 1; }
            `;
            $('<style id="paint-tool-styles">').text(css).appendTo('head');
        }
    }

    class Integration {
        constructor(controller) {
            this.controller = controller;
            this.files = [];
            this.init();
        }

        init() {
            this.addToolbarButtons();
            this.addImageEditButtons();
            
            $(document).on('ajax_before_post.painttool', (e, formData) => {
                this.files.forEach((f, i) => {
                    let key = 'file';
                    const hasKey = (k) => { let r=false; formData.forEach((v, key)=> { if(key===k) r=true; }); return r; };
                    let count = 0; formData.forEach((v, k) => { if(k.match(/^file/)) count++; });
                    if(count > 0) key = 'file' + (count + 1);
                    formData.append(key, f, f.name);
                });
            });
            
            $(document).on('ajax_after_post.painttool', () => {
                this.files = [];
                $('.paint-file-indicator').remove();
            });
        }

        addToolbarButtons() {
            const self = this;
            const btnHtml = '<button type="button" class="paint-toolbar-btn" style="font-weight:bold;font-size:11px;margin-left:3px;" title="Draw (D)">D</button>';
            
            $(document).on('click', '.paint-toolbar-btn', function(e) {
                e.preventDefault();
                self.controller.open();
            });

            const add = function() {
                $('.format-text').each(function() {
                    if ($(this).find('.paint-toolbar-btn').length) return;
                    $(this).append(btnHtml);
                });
            };

            $(document).on('formatText', add);
            $(document).ready(add);
        }

        addImageEditButtons() {
            if (active_page !== 'thread') return;
            const add = () => {
                $('.fileinfo').each((i, el) => {
                    if ($(el).find('.paint-edit-image').length) return;
                    const $imgLink = $(el).closest('.file').find('img.post-image').parent('a');
                    if (!$imgLink.length || !/\.(jpg|png|gif|webp)$/i.test($imgLink.attr('href'))) return;

                    const $btn = $('<a href="#" class="paint-edit-image"><i class="fa fa-pencil"></i></a>')
                        .on('click', (e) => {
                            e.preventDefault();
                            let pid = null;
                            const $post = $(el).closest('.post, .op');
                            if($post.length) pid = $post.attr('id').replace(/reply_|op_/, '') || $post.find('.post_no').last().text();
                            
                            this.controller.open($imgLink.attr('href'), pid);
                        });
                    $(el).append($btn);
                });
            };
            $(document).ready(add);
            $(document).on('new_post', () => setTimeout(add, 100));
        }

        handleExport(blob, replyToPost) {
            const file = new File([blob], `drawing_${Date.now()}.png`, { type: 'image/png' });
            
            if (replyToPost && active_page === 'thread') {
                const $txt = $('textarea[name="body"]');
                if ($txt.val().indexOf('>>' + replyToPost) === -1) $txt.val('>>' + replyToPost + '\n' + $txt.val());
            }

            if (window.FileSelector) {
                window.FileSelector.addFile(file);
            } else {
                this.files.push(file);
                this.showFileIndicator(file);
            }
        }

        showFileIndicator(file) {
            const $indicator = $(`<div class="paint-file-indicator" style="padding:4px;border:1px solid green;background:#e0ffe0;margin-top:5px;font-size:11px;">[paint] ${file.name} </div>`);
            $indicator.append($('<a href="#" style="color:red;margin-left:5px;">[x]</a>').click((e) => {
                e.preventDefault();
                this.files = this.files.filter(f => f !== file);
                $indicator.remove();
            }));
            $('form[name="post"] input[type="submit"]').before($indicator);
        }
    }

    class PaintController {
        constructor() {
            this.state = new PaintState();
            this.ui = new UIManager(this);
            this.integration = new Integration(this);
            this.engine = null;
            this.replyTo = null;
        }

        open(url, postId) {
            this.replyTo = postId;
            this.ui.open(url);
        }

        close() {
            this.ui.cleanup();
            this.engine = null;
        }

        initEngine(canvas) {
            this.engine = new DrawingEngine(canvas, this.state);
        }

        setTool(t) { if(this.engine) this.engine.setTool(t); }

        loadImage(url) {
            const img = new Image();
            img.crossOrigin = 'anonymous';
            img.onload = () => {
                const dims = this.engine.loadFromImage(img);
                $('#paint-w').val(dims.w);
                $('#paint-h').val(dims.h);
            };
            img.onerror = () => {
                const img2 = new Image();
                img2.src = url; 
                img2.onload = () => this.engine.loadFromImage(img2);
            }
            img.src = url;
        }

        handlePaste(e) {
            const items = (e.originalEvent || e).clipboardData.items;
            for (let i = 0; i < items.length; i++) {
                if (items[i].type.indexOf('image') !== -1) {
                    const blob = items[i].getAsFile();
                    const img = new Image();
                    img.onload = () => {
                        const ratio = Math.min(this.engine.canvas.width / img.width, this.engine.canvas.height / img.height, 1);
                        const w = img.width * ratio, h = img.height * ratio;
                        this.engine.ctx.drawImage(img, (this.engine.canvas.width-w)/2, (this.engine.canvas.height-h)/2, w, h);
                        this.engine.commitState();
                    };
                    img.src = URL.createObjectURL(blob);
                    break;
                }
            }
        }

        save() {
            const a = document.createElement('a');
            a.download = `drawing_${Date.now()}.png`;
            a.href = this.engine.canvas.toDataURL();
            a.click();
        }

        load() {
            const input = document.createElement('input');
            input.type = 'file'; input.accept = 'image/*';
            input.onchange = (e) => {
                if(!e.target.files[0]) return;
                const url = URL.createObjectURL(e.target.files[0]);
                this.loadImage(url);
            };
            input.click();
        }

        resize() {
            this.engine.resize(parseInt($('#paint-w').val()), parseInt($('#paint-h').val()));
        }

        done() {
            this.engine.canvas.toBlob((blob) => {
                this.integration.handleExport(blob, this.replyTo);
                this.close();
            });
        }
    }

    $(document).ready(() => new PaintController());

})(jQuery);