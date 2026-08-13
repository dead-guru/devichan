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
            this.smoothing = 0;
            this.taper = 0;
            this.fillTolerance = 10;
            this.fillGapClose = 0;
            this.selection = {
                active: false,
                phase: 'pristine',      // 'pristine' | 'modified'
                rect: null,
                transform: null,
                floating: null,
                pristineSnapshot: null,
                isClone: false
            };
            this.guides = [];   // [{axis:'h'|'v', pos: number}, ...]   pos in canvas-pixel coords
                                // Reset on modal close. Not persisted to localStorage.
                                // Not in undo/redo history.
            this.load();
        }

        save() {
            try {
                localStorage.setItem('paint-tool-settings', JSON.stringify({
                    color: this.color,
                    opacity: this.opacity,
                    brushSize: this.brushSize,
                    smoothing: this.smoothing,
                    taper: this.taper,
                    fillTolerance: this.fillTolerance,
                    fillGapClose: this.fillGapClose
                }));
            } catch (e) {}
        }

        load() {
            try {
                const saved = localStorage.getItem('paint-tool-settings');
                if (!saved) return;
                const data = JSON.parse(saved);
                if (data.color) this.color = data.color;
                if (data.opacity != null) this.opacity = data.opacity;
                if (data.brushSize != null) this.brushSize = data.brushSize;
                if (data.smoothing != null) this.smoothing = data.smoothing;
                if (data.taper != null) this.taper = data.taper;
                if (data.fillTolerance != null) this.fillTolerance = data.fillTolerance;
                if (data.fillGapClose != null) this.fillGapClose = data.fillGapClose;
            } catch (e) {}
        }
    }

    class StrokeProcessor {
        constructor(state) {
            this.state = state;
            this.reset();
        }

        reset() {
            this.B = null;
            this.C = null;
            this.prevT = null;
            this._prevPoint = null;
            this.smoothSpeed = null;     // null until first speed sample arrives — see _computeWidth
            this._smoothedWidth = null;  // EMA on width itself, hides per-frame jitter
            this._lastWidth = 0;
            this.firstPoint = true;
        }

        sync() {
            // Re-read settings before each stroke and coerce to numbers
            // (slider .value is a string, which would break arithmetic — string+0 = "70" not 70)
            this.smoothing = +this.state.smoothing || 0;
            this.taper = +this.state.taper || 0;
            this.brushSize = +this.state.brushSize || 1;
        }

        _computeWidth(t) {
            if (this.firstPoint || this.prevT === null) return this.brushSize;
            // Width is exponentially smoothed AFTER the speed-based target is computed,
            // adding a second layer of damping that hides per-frame jitter.
            const dx = this.C.x - this._prevPoint.x;
            const dy = this.C.y - this._prevPoint.y;
            const dist = Math.hypot(dx, dy);
            const dt = Math.max(1, Math.min(50, t - this.prevT));
            const speed = dist / dt;
            // First sample seeds smoothSpeed with raw speed so taper responds
            // immediately to a fast initial flick (no "fat first segment" while the
            // exponential smoother ramps up from zero).
            if (this.smoothSpeed === null) {
                this.smoothSpeed = speed;
            } else {
                this.smoothSpeed = this.smoothSpeed * 0.7 + speed * 0.3;
            }
            const speedNorm = Math.min(this.smoothSpeed / 2.0, 1);
            const range = (this.taper / 100) * 0.7;
            const target = this.brushSize * (1 - speedNorm * range);
            // Exponentially smooth width itself — same EMA pattern as smoothSpeed —
            // so taper transitions glide instead of stepping per-segment.
            if (this._smoothedWidth == null) {
                this._smoothedWidth = target;
            } else {
                this._smoothedWidth = this._smoothedWidth * 0.55 + target * 0.45;
            }
            return this._smoothedWidth;
        }

        feed({x, y, t}) {
            const C = {x, y};
            if (this.firstPoint) {
                this.B = {x, y};
                this.C = C;
                this._prevPoint = C;
                this.prevT = t;
                this.firstPoint = false;
                this._lastWidth = this.brushSize;
                // No segment emitted yet — the first visible "dot" will be the round
                // cap at the start of the first onMove segment, already at the
                // speed-correct width. Single-click-without-move falls back via
                // BrushTool.onEnd.
                return [];
            }

            this.C = C;
            const width = this._computeWidth(t);
            this._prevPoint = C;
            this.prevT = t;

            const L = (this.smoothing / 100) * 80;
            const dx = C.x - this.B.x;
            const dy = C.y - this.B.y;
            const dist = Math.hypot(dx, dy);

            if (dist > L) {
                const move = dist - L;
                const newB = {
                    x: this.B.x + (dx / dist) * move,
                    y: this.B.y + (dy / dist) * move
                };
                const prevWidth = this._lastWidth || width;
                const seg = { x1: this.B.x, y1: this.B.y, x2: newB.x, y2: newB.y, width, prevWidth };
                this.B = newB;
                this._lastWidth = width;
                return [seg];
            }
            return [];
        }

        finish() {
            if (this.firstPoint || !this.B || !this.C) return [];
            const dx = this.C.x - this.B.x;
            const dy = this.C.y - this.B.y;
            if (dx === 0 && dy === 0) return [];
            const N = 5;
            const width = this._lastWidth || this.brushSize;
            const segs = [];
            for (let i = 0; i < N; i++) {
                const newB = {
                    x: this.B.x + dx / N,
                    y: this.B.y + dy / N
                };
                segs.push({ x1: this.B.x, y1: this.B.y, x2: newB.x, y2: newB.y, width });
                this.B = newB;
            }
            return segs;
        }
    }

    class Tool {
        constructor(engine) { this.engine = engine; }
        onStart(x, y, t) { this.startX = x; this.startY = y; }
        onMove(x, y, t) {}
        onEnd(t) {}
        getOptionsPanel() { return null; }
    }

    class BrushTool extends Tool {
        constructor(engine) {
            super(engine);
            this.processor = new StrokeProcessor(engine.state);
        }

        onStart(x, y, t) {
            this.engine.saveSnapshot();
            this.engine.clearTemp();
            this.processor.sync();
            this.processor.reset();
            this._anyMove = false;
            // First feed primes the processor but emits no segment.
            this.processor.feed({x, y, t});
        }

        onMove(x, y, t) {
            const segs = this.processor.feed({x, y, t});
            if (segs && segs.length) this._anyMove = true;
            this._drawSegments(segs);
            this.engine.renderTemp();
        }

        onEnd(t) {
            // No catch-up: stroke ends exactly where the last input event placed B.
            // If the user clicked without moving, draw a single dot at brushSize so
            // click-to-dot still works.
            if (!this._anyMove && this.processor.B) {
                const B = this.processor.B;
                const w = this.processor.brushSize;
                this._drawSegments([{ x1: B.x, y1: B.y, x2: B.x, y2: B.y, width: w }]);
                this.engine.renderTemp();
            }
        }

        _drawSegments(segs) {
            if (!segs || !segs.length) return;
            const ctx = this.engine.tempCtx;
            ctx.strokeStyle = this.engine.state.color;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            const SUB_N = 8;  // sub-strokes per segment for smooth width interpolation
            for (const s of segs) {
                const pw = s.prevWidth != null ? s.prevWidth : s.width;
                if (Math.abs(pw - s.width) < 0.5) {
                    // No noticeable width change — single stroke.
                    ctx.lineWidth = s.width;
                    ctx.beginPath();
                    ctx.moveTo(s.x1, s.y1);
                    ctx.lineTo(s.x2, s.y2);
                    ctx.stroke();
                    continue;
                }
                // Width steps perceptibly — sub-divide into SUB_N micro-strokes with
                // linearly interpolated lineWidth. lineCap='round' makes adjacent
                // micro-strokes overlap seamlessly.
                const dx = s.x2 - s.x1, dy = s.y2 - s.y1;
                for (let i = 0; i < SUB_N; i++) {
                    const t0 = i / SUB_N, t1 = (i + 1) / SUB_N;
                    const x0 = s.x1 + dx * t0, y0 = s.y1 + dy * t0;
                    const x1 = s.x1 + dx * t1, y1 = s.y1 + dy * t1;
                    ctx.lineWidth = pw + (s.width - pw) * ((t0 + t1) / 2);
                    ctx.beginPath();
                    ctx.moveTo(x0, y0);
                    ctx.lineTo(x1, y1);
                    ctx.stroke();
                }
            }
        }

        getOptionsPanel() {
            const s = this.engine.state;
            return `
                <div class="paint-toolbar-group">
                    <span class="paint-lbl">Size</span>
                    <input type="range" title="Size" class="paint-range size-ctl" min="1" max="50" value="${s.brushSize}">
                    <span class="paint-lbl size-val">${s.brushSize}px</span>
                </div>
                <div class="paint-toolbar-group">
                    <span class="paint-lbl">Opacity</span>
                    <input type="range" title="Opacity" class="paint-range opacity-ctl" min="0" max="100" value="${s.opacity*100}">
                    <span class="paint-lbl opacity-val">${Math.round(s.opacity*100)}%</span>
                </div>
                <div class="paint-toolbar-group">
                    <span class="paint-lbl">Smooth</span>
                    <input type="range" title="Smoothing" class="paint-range smoothing-ctl" min="0" max="100" value="${s.smoothing}">
                    <span class="paint-lbl smoothing-val">${s.smoothing}%</span>
                </div>
                <div class="paint-toolbar-group">
                    <span class="paint-lbl">Taper</span>
                    <input type="range" title="Taper" class="paint-range taper-ctl" min="0" max="100" value="${s.taper}">
                    <span class="paint-lbl taper-val">${s.taper}%</span>
                </div>
            `;
        }
    }

    class EraserTool extends Tool {
        onStart(x, y) {
            this._lastX = x;
            this._lastY = y;
            this._anyMove = false;
            // Single dot at start so click-without-drag erases a spot.
            const ctx = this.engine.ctx;
            const r = +this.engine.state.brushSize || 1;
            ctx.fillStyle = '#ffffff';
            ctx.beginPath();
            ctx.arc(x, y, r, 0, Math.PI * 2);
            ctx.fill();
        }
        onMove(x, y) {
            this._anyMove = true;
            const ctx = this.engine.ctx;
            const r = +this.engine.state.brushSize || 1;
            ctx.strokeStyle = '#ffffff';
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            ctx.lineWidth = r * 2;
            ctx.beginPath();
            ctx.moveTo(this._lastX, this._lastY);
            ctx.lineTo(x, y);
            ctx.stroke();
            this._lastX = x;
            this._lastY = y;
        }

        getOptionsPanel() {
            const s = this.engine.state;
            return `
                <div class="paint-toolbar-group">
                    <span class="paint-lbl">Size</span>
                    <input type="range" title="Size" class="paint-range size-ctl" min="1" max="50" value="${s.brushSize}">
                    <span class="paint-lbl size-val">${s.brushSize}px</span>
                </div>
            `;
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

        getOptionsPanel() {
            const s = this.engine.state;
            return `
                <div class="paint-toolbar-group">
                    <span class="paint-lbl">Size</span>
                    <input type="range" title="Size" class="paint-range size-ctl" min="1" max="50" value="${s.brushSize}">
                    <span class="paint-lbl size-val">${s.brushSize}px</span>
                </div>
                <div class="paint-toolbar-group">
                    <span class="paint-lbl">Opacity</span>
                    <input type="range" title="Opacity" class="paint-range opacity-ctl" min="0" max="100" value="${s.opacity*100}">
                    <span class="paint-lbl opacity-val">${Math.round(s.opacity*100)}%</span>
                </div>
            `;
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

        getOptionsPanel() {
            const s = this.engine.state;
            return `
                <div class="paint-toolbar-group">
                    <span class="paint-lbl">Size</span>
                    <input type="range" title="Size" class="paint-range size-ctl" min="1" max="50" value="${s.brushSize}">
                    <span class="paint-lbl size-val">${s.brushSize}px</span>
                </div>
                <div class="paint-toolbar-group">
                    <span class="paint-lbl">Opacity</span>
                    <input type="range" title="Opacity" class="paint-range opacity-ctl" min="0" max="100" value="${s.opacity*100}">
                    <span class="paint-lbl opacity-val">${Math.round(s.opacity*100)}%</span>
                </div>
            `;
        }
    }

    class FillTool extends Tool {
        onStart(x, y) {
            // Convert CSS-pixel click coords to device-pixel imageData indices.
            const dpr = this.engine.dpr;
            this.floodFill(Math.floor(x * dpr), Math.floor(y * dpr));
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
            const tol = +this.engine.state.fillTolerance || 0;
            const gapClose = +this.engine.state.fillGapClose || 0;

            if (targetColor.r === fillColor.r && targetColor.g === fillColor.g && targetColor.b === fillColor.b) return;

            let obstacleMask = null;
            if (gapClose > 0) {
                obstacleMask = this._buildObstacleMask(data, width, height, gapClose);
            }

            const stack = [[x, y]];
            const visited = new Uint8Array(width * height);

            while (stack.length) {
                const [px, py] = stack.pop();
                if (px < 0 || px >= width || py < 0 || py >= height) continue;

                const key = py * width + px;
                if (visited[key]) continue;
                if (obstacleMask && obstacleMask[key]) continue;

                const idx = key * 4;
                if (Math.abs(data[idx] - targetColor.r) > tol ||
                    Math.abs(data[idx+1] - targetColor.g) > tol ||
                    Math.abs(data[idx+2] - targetColor.b) > tol) continue;

                visited[key] = 1;
                data[idx] = fillColor.r;
                data[idx + 1] = fillColor.g;
                data[idx + 2] = fillColor.b;
                data[idx + 3] = 255;

                stack.push([px + 1, py], [px - 1, py], [px, py + 1], [px, py - 1]);
            }
            ctx.putImageData(imageData, 0, 0);
        }

        _buildObstacleMask(data, width, height, iterations) {
            // Initial mask: pixels with average luminance < 128 are "obstacles" (dark lines)
            let mask = new Uint8Array(width * height);
            for (let i = 0; i < width * height; i++) {
                const idx = i * 4;
                const lum = (data[idx] + data[idx+1] + data[idx+2]) / 3;
                if (lum < 128) mask[i] = 1;
            }
            // Morphological dilate, 3x3 kernel, N iterations — each pass grows
            // dark regions by 1 pixel in all directions, so small gaps close up.
            for (let iter = 0; iter < iterations; iter++) {
                const next = new Uint8Array(width * height);
                for (let y = 0; y < height; y++) {
                    for (let x = 0; x < width; x++) {
                        const k = y * width + x;
                        if (mask[k]) { next[k] = 1; continue; }
                        if ((x > 0 && mask[k-1]) ||
                            (x < width-1 && mask[k+1]) ||
                            (y > 0 && mask[k-width]) ||
                            (y < height-1 && mask[k+width]) ||
                            (x > 0 && y > 0 && mask[k-width-1]) ||
                            (x < width-1 && y > 0 && mask[k-width+1]) ||
                            (x > 0 && y < height-1 && mask[k+width-1]) ||
                            (x < width-1 && y < height-1 && mask[k+width+1])) {
                            next[k] = 1;
                        }
                    }
                }
                mask = next;
            }
            return mask;
        }

        hexToRgb(hex) {
            const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
            return result ? { r: parseInt(result[1], 16), g: parseInt(result[2], 16), b: parseInt(result[3], 16) } : { r: 0, g: 0, b: 0 };
        }

        getOptionsPanel() {
            const s = this.engine.state;
            return `
                <div class="paint-toolbar-group">
                    <span class="paint-lbl">Tolerance</span>
                    <input type="range" title="Tolerance" class="paint-range tolerance-ctl" min="0" max="100" value="${s.fillTolerance}">
                    <span class="paint-lbl tolerance-val">${s.fillTolerance}</span>
                </div>
                <div class="paint-toolbar-group">
                    <span class="paint-lbl">Gap Close</span>
                    <input type="range" title="Gap Close" class="paint-range gapclose-ctl" min="0" max="5" value="${s.fillGapClose}">
                    <span class="paint-lbl gapclose-val">${s.fillGapClose}px</span>
                </div>
            `;
        }
    }

    class SelectionTool extends Tool {
        constructor(engine) {
            super(engine);
            this._dragMode = null;       // 'newRect' | 'move' | 'scale-TL' | 'scale-TR' | 'scale-BL' | 'scale-BR' | 'scale-T' | 'scale-B' | 'scale-L' | 'scale-R' | 'rotate' | null
            this._mouseStart = null;
            this._transformStart = null;
            this._anchorWorld = null;
            this._baseAngle = 0;
            this._startAngle = 0;
            this._cloneKey = false;      // sticky: true if Alt/Shift held at any point during drag → clone instead of move
        }

        get sel() { return this.engine.state.selection; }

        onStart(x, y, t, e) {
            // Either Alt OR Shift (whichever the user prefers / whichever isn't intercepted by the OS)
            this._cloneKey = !!(e && (e.altKey || e.shiftKey));
            const sel = this.sel;
            if (!sel.active) {
                // No selection — start a new rect
                this._dragMode = 'newRect';
                this._mouseStart = { x, y };
                sel.active = true;
                this.engine.startDashTimer();
                sel.phase = 'pristine';
                sel.rect = { x, y, w: 0, h: 0 };
                sel.transform = { tx: 0, ty: 0, sx: 1, sy: 1, angle: 0 };
                sel.floating = null;
                sel.pristineSnapshot = null;
                sel.isClone = false;
                this.engine.captureSelectionBase();
                this.engine.renderSelection();
                this.engine.controllerUI.renderToolOptions();
                return;
            }
            // Selection exists — hit-test
            const zone = this._hitTest(x, y);
            if (zone === 'background') {
                // PRISTINE → start new rect (drop old). MODIFIED → hard-block.
                if (sel.phase === 'modified') {
                    this.engine.controllerUI.pulseSelectionButtons();
                    this._dragMode = null;
                    return;
                }
                // Restore old base to clear stale ants/handles overlay from canvas
                // before capturing a new one — otherwise the new base would include the overlay.
                if (this.engine.selectionRenderBase) {
                    this.engine.ctx.putImageData(this.engine.selectionRenderBase, 0, 0);
                    this.engine.selectionRenderBase = null;
                }
                this._reset();
                this.engine.captureSelectionBase();
                this._dragMode = 'newRect';
                this._mouseStart = { x, y };
                sel.active = true;
                this.engine.startDashTimer();
                sel.phase = 'pristine';
                sel.rect = { x, y, w: 0, h: 0 };
                sel.transform = { tx: 0, ty: 0, sx: 1, sy: 1, angle: 0 };
                this.engine.renderSelection();
                this.engine.controllerUI.renderToolOptions();
                return;
            }
            if (zone === 'inside') {
                this._dragMode = 'move';
                this._mouseStart = { x, y };
                this._transformStart = { ...sel.transform };
                return;
            }
            if (zone === 'rotate') {
                this._dragMode = 'rotate';
                const cx = sel.rect.x + sel.rect.w/2 + sel.transform.tx;
                const cy = sel.rect.y + sel.rect.h/2 + sel.transform.ty;
                this._startAngle = Math.atan2(y - cy, x - cx);
                this._baseAngle = sel.transform.angle;
                return;
            }
            // Scale handle: zone is one of 'scale-TL/TR/BL/BR/T/B/L/R'
            if (zone.startsWith('scale-')) {
                this._dragMode = zone;
                this._mouseStart = { x, y };
                this._transformStart = { ...sel.transform };
                this._anchorWorld = this._anchorPointForHandle(zone);
                return;
            }
        }

        onMove(x, y, t, e) {
            if (!this._dragMode) {
                // Hover: update cursor based on zone (handled by UIManager via hitTest)
                return;
            }
            // Sticky clone-key — if Alt or Shift is held at ANY point during drag, latch it on
            if (e && (e.altKey || e.shiftKey)) this._cloneKey = true;
            const sel = this.sel;
            if (this._dragMode === 'newRect') {
                const w = x - this._mouseStart.x;
                const h = y - this._mouseStart.y;
                sel.rect.x = w >= 0 ? this._mouseStart.x : x;
                sel.rect.y = h >= 0 ? this._mouseStart.y : y;
                sel.rect.w = Math.abs(w);
                sel.rect.h = Math.abs(h);
                this.engine.renderSelection();
                return;
            }
            // Any other dragMode = destructive transform → ensure MODIFIED phase
            if (sel.phase === 'pristine') {
                this._liftSelection(this._cloneKey);
                // Phase changed pristine→modified; refresh options panel to surface ✓ Apply
                this.engine.controllerUI.renderToolOptions();
            }
            if (this._dragMode === 'move') {
                sel.transform.tx = this._transformStart.tx + (x - this._mouseStart.x);
                sel.transform.ty = this._transformStart.ty + (y - this._mouseStart.y);
            } else if (this._dragMode === 'rotate') {
                const cx = sel.rect.x + sel.rect.w/2 + sel.transform.tx;
                const cy = sel.rect.y + sel.rect.h/2 + sel.transform.ty;
                const cur = Math.atan2(y - cy, x - cx);
                sel.transform.angle = this._baseAngle + (cur - this._startAngle);
            } else if (this._dragMode.startsWith('scale-')) {
                this._applyScale(x, y);
            }
            this.engine.renderSelection();
        }

        onEnd(t) {
            if (this._dragMode === 'newRect') {
                const sel = this.sel;
                if (Math.abs(sel.rect.w) < 4 || Math.abs(sel.rect.h) < 4) {
                    // Too small — drop selection
                    this._reset();
                    this.engine.renderSelection();
                    this.engine.controllerUI.renderToolOptions();
                }
            }
            this._dragMode = null;
            this._mouseStart = null;
            this._transformStart = null;
            this._anchorWorld = null;
        }

        // === public API ===
        apply() {
            const sel = this.sel;
            if (!sel.active) return;
            if (sel.phase === 'modified') {
                this._bakeFloating();
                this.engine.commitState();
                // Bake is final canvas state — drop base so renderSelection's no-active branch doesn't overwrite it.
                this.engine.selectionRenderBase = null;
            }
            this._reset();
            this.engine.renderSelection();
            this.engine.controllerUI.renderToolOptions();
        }

        cancel() {
            const sel = this.sel;
            if (!sel.active) return;
            if (sel.phase === 'modified' && sel.pristineSnapshot) {
                // Explicit full-canvas restore from snapshot taken before lift.
                this.engine.ctx.putImageData(sel.pristineSnapshot, 0, 0);
                // Restore is final — drop base so renderSelection doesn't overwrite with base-with-hole.
                this.engine.selectionRenderBase = null;
            }
            // For PRISTINE cancel, keep selectionRenderBase so renderSelection() restores it
            // and clears the ants/handles overlay automatically.
            this._reset();
            this.engine.renderSelection();
            this.engine.controllerUI.renderToolOptions();
        }

        flipH() {
            const sel = this.sel;
            if (!sel.active) return;
            if (sel.phase === 'pristine') this._liftSelection(false);
            sel.transform.sx *= -1;
            this.engine.renderSelection();
            this.engine.controllerUI.renderToolOptions();
        }

        flipV() {
            const sel = this.sel;
            if (!sel.active) return;
            if (sel.phase === 'pristine') this._liftSelection(false);
            sel.transform.sy *= -1;
            this.engine.renderSelection();
            this.engine.controllerUI.renderToolOptions();
        }

        getOptionsPanel() {
            const sel = this.sel;
            if (!sel.active) return '';
            // Apply is shown in both pristine (= deselect) and modified (= bake) for UX consistency.
            // Duplicate works in both phases too:
            //   PRISTINE: lifts in clone mode (original stays, floating copy ready to drag)
            //   MODIFIED: stamps current floating, snaps it back to original position for next stamp
            return `
                <div class="paint-toolbar-group">
                    <button class="paint-btn paint-btn-done" data-action="apply" title="Apply (Enter)"><i class="fa-solid fa-check"></i> Apply</button>
                    <button class="paint-btn" data-action="selCancel" title="Cancel (Esc)"><i class="fa-solid fa-xmark"></i> Cancel</button>
                </div>
                <div class="paint-toolbar-group">
                    <button class="paint-btn" data-action="flipH" title="Flip horizontal"><i class="fa-solid fa-arrows-left-right"></i> Flip H</button>
                    <button class="paint-btn" data-action="flipV" title="Flip vertical"><i class="fa-solid fa-arrows-up-down"></i> Flip V</button>
                </div>
                <div class="paint-toolbar-group">
                    <button class="paint-btn" data-action="duplicate" title="Duplicate / Stamp (Alt/Shift+drag)"><i class="fa-solid fa-stamp"></i> Duplicate</button>
                </div>
            `;
        }

        duplicate() {
            const sel = this.sel;
            if (!sel.active) return;

            if (sel.phase === 'pristine') {
                // First stamp from PRISTINE: just lift in clone mode at identity transform.
                // User now sees original + floating overlapping. They drag to position, then stamp again.
                this._liftSelection(true);
                this.engine.controllerUI.renderToolOptions();
                this.engine.renderSelection();
                return;
            }

            // MODIFIED: stamp the current floating at its transform, then snap floating back
            // to original position so user can drag and stamp again. Stamping accumulates in
            // selectionRenderBase — Cancel still reverts everything via pristineSnapshot.
            const eng = this.engine;
            const ctx = eng.ctx;
            const canvas = eng.canvas;
            const W = sel.rect.w, H = sel.rect.h;

            // Step A: restore the current base (will have a white hole if user was in move-mode)
            if (eng.selectionRenderBase) {
                ctx.putImageData(eng.selectionRenderBase, 0, 0);
            }
            // Step B: if we were in move-mode (hole on canvas), patch the original pixels back
            // by drawing floating at identity over the hole — this becomes the new "base layer".
            if (!sel.isClone) {
                ctx.save();
                ctx.imageSmoothingEnabled = true;
                ctx.imageSmoothingQuality = 'high';
                ctx.drawImage(sel.floating, sel.rect.x, sel.rect.y, W, H);
                ctx.restore();
            }
            // Step C: bake floating at the user's current transform — this is the stamp deposit.
            const cx = sel.rect.x + W/2;
            const cy = sel.rect.y + H/2;
            ctx.save();
            ctx.translate(cx + sel.transform.tx, cy + sel.transform.ty);
            ctx.rotate(sel.transform.angle);
            ctx.scale(sel.transform.sx, sel.transform.sy);
            ctx.translate(-W/2, -H/2);
            ctx.imageSmoothingEnabled = true;
            ctx.imageSmoothingQuality = 'high';
            ctx.drawImage(sel.floating, 0, 0, W, H);
            ctx.restore();
            // Step D: capture canvas (base + original + this stamp) as new render base.
            eng.selectionRenderBase = ctx.getImageData(0, 0, canvas.width, canvas.height);
            // Step E: reset transform — floating snaps back to original rect position for next stamp.
            sel.transform = { tx: 0, ty: 0, sx: 1, sy: 1, angle: 0 };
            sel.isClone = true;  // from now on we don't paint a hole; original pixels are preserved in base.
            // Step F: refresh display
            eng.renderSelection();
            eng.controllerUI.renderToolOptions();
        }

        hitTestZone(x, y) { return this._hitTest(x, y); }

        // === internals (stubs to fill in Tasks 2-6) ===
        _hitTest(x, y) {
            const sel = this.sel;
            if (!sel.active || !sel.rect) return 'background';
            // Handles and ROT are tested in SCREEN space against their world positions —
            // tolerance is fixed in CSS pixels regardless of scale/rotation, matching visuals.
            const corners = this._computeCorners();
            const isTouch = ('ontouchstart' in window);
            const half = (isTouch ? 24 : 14) / 2;
            const hitWorld = (p) => Math.abs(x - p.x) <= half && Math.abs(y - p.y) <= half;
            // Priority: rotate, corners, edges, then inside.
            if (hitWorld(corners.ROT)) return 'rotate';
            if (hitWorld(corners.TL)) return 'scale-TL';
            if (hitWorld(corners.TR)) return 'scale-TR';
            if (hitWorld(corners.BL)) return 'scale-BL';
            if (hitWorld(corners.BR)) return 'scale-BR';
            if (hitWorld(corners.T))  return 'scale-T';
            if (hitWorld(corners.B))  return 'scale-B';
            if (hitWorld(corners.L))  return 'scale-L';
            if (hitWorld(corners.R))  return 'scale-R';
            // Inside-test: inverse-transform cursor into rect-local space.
            const tr = sel.transform || { tx: 0, ty: 0, sx: 1, sy: 1, angle: 0 };
            const cx = sel.rect.x + sel.rect.w/2 + tr.tx;
            const cy = sel.rect.y + sel.rect.h/2 + tr.ty;
            const cos = Math.cos(-tr.angle), sin = Math.sin(-tr.angle);
            const dx = x - cx, dy = y - cy;
            const sxSigned = tr.sx === 0 ? 0.0001 : tr.sx;
            const sySigned = tr.sy === 0 ? 0.0001 : tr.sy;
            const lx = (dx * cos - dy * sin) / sxSigned + sel.rect.w/2;
            const ly = (dx * sin + dy * cos) / sySigned + sel.rect.h/2;
            if (lx >= 0 && lx <= sel.rect.w && ly >= 0 && ly <= sel.rect.h) return 'inside';
            return 'background';
        }
        _anchorPointForHandle(zone) {
            const sel = this.sel;
            const tr = sel.transform;
            const cx = sel.rect.x + sel.rect.w/2 + tr.tx;
            const cy = sel.rect.y + sel.rect.h/2 + tr.ty;
            // Local coords of opposite handle (in untransformed rect-space, origin at rect center)
            let lx = 0, ly = 0;
            switch (zone) {
                case 'scale-TL': lx = sel.rect.w/2;  ly = sel.rect.h/2; break;  // anchor BR
                case 'scale-TR': lx = -sel.rect.w/2; ly = sel.rect.h/2; break;  // anchor BL
                case 'scale-BL': lx = sel.rect.w/2;  ly = -sel.rect.h/2; break; // anchor TR
                case 'scale-BR': lx = -sel.rect.w/2; ly = -sel.rect.h/2; break; // anchor TL
                case 'scale-T':  lx = 0;             ly = sel.rect.h/2; break;  // anchor B
                case 'scale-B':  lx = 0;             ly = -sel.rect.h/2; break; // anchor T
                case 'scale-L':  lx = sel.rect.w/2;  ly = 0; break;             // anchor R
                case 'scale-R':  lx = -sel.rect.w/2; ly = 0; break;             // anchor L
                default: return null;
            }
            // World coord = center + rotated(scaled(local))
            const sx = lx * tr.sx, sy = ly * tr.sy;
            const cos = Math.cos(tr.angle), sin = Math.sin(tr.angle);
            return {
                x: cx + sx * cos - sy * sin,
                y: cy + sx * sin + sy * cos
            };
        }
        _applyScale(x, y) {
            const sel = this.sel;
            const tr = sel.transform;
            const tr0 = this._transformStart;       // captured at mousedown
            const anchor = this._anchorWorld;       // world pos of opposite handle, fixed
            const zone = this._dragMode;
            const W = sel.rect.w;
            const H = sel.rect.h;
            // Vector cursor → anchor in rect-local-pre-transform space.
            // Drag handle should land at cursor, anchor stays fixed → length of handle-anchor
            // diagonal in local space = (2 * handle_local_x * sx, 2 * handle_local_y * sy).
            // Inverse-rotate (cursor - anchor) into local space, then divide by full W/H.
            const cos0 = Math.cos(-tr0.angle), sin0 = Math.sin(-tr0.angle);
            const dx = x - anchor.x;
            const dy = y - anchor.y;
            const Lx = dx * cos0 - dy * sin0;
            const Ly = dx * sin0 + dy * cos0;
            // Divisor signs: handle local x is +halfW for TR/BR/R, -halfW for TL/BL/L → 2*Hx = ±W.
            //                handle local y is +halfH for BL/BR/B, -halfH for TL/TR/T → 2*Hy = ±H.
            let newSx = tr0.sx, newSy = tr0.sy;
            switch (zone) {
                case 'scale-TL': newSx = Lx / -W; newSy = Ly / -H; break;
                case 'scale-TR': newSx = Lx /  W; newSy = Ly / -H; break;
                case 'scale-BL': newSx = Lx / -W; newSy = Ly /  H; break;
                case 'scale-BR': newSx = Lx /  W; newSy = Ly /  H; break;
                case 'scale-T':  newSy = Ly / -H; break;
                case 'scale-B':  newSy = Ly /  H; break;
                case 'scale-L':  newSx = Lx / -W; break;
                case 'scale-R':  newSx = Lx /  W; break;
            }
            tr.sx = newSx;
            tr.sy = newSy;
            // Re-compensate tx, ty so anchor stays at its world-fixed position.
            // anchor_world = (cx_orig + tx_new, cy_orig + ty_new) + R(angle) * (alx * sx, aly * sy)
            // where alx, aly = anchor LOCAL position relative to rect center (= -handle_local).
            let alx = 0, aly = 0;
            switch (zone) {
                case 'scale-TL': alx =  W/2; aly =  H/2; break;
                case 'scale-TR': alx = -W/2; aly =  H/2; break;
                case 'scale-BL': alx =  W/2; aly = -H/2; break;
                case 'scale-BR': alx = -W/2; aly = -H/2; break;
                case 'scale-T':  alx = 0;    aly =  H/2; break;
                case 'scale-B':  alx = 0;    aly = -H/2; break;
                case 'scale-L':  alx =  W/2; aly = 0; break;
                case 'scale-R':  alx = -W/2; aly = 0; break;
            }
            const sxa = alx * tr.sx;
            const sya = aly * tr.sy;
            const ang = tr0.angle; // angle unchanged during scale drag
            const c = Math.cos(ang), s = Math.sin(ang);
            const rotAx = sxa * c - sya * s;
            const rotAy = sxa * s + sya * c;
            const rectCenterX = sel.rect.x + W/2;
            const rectCenterY = sel.rect.y + H/2;
            tr.tx = anchor.x - rectCenterX - rotAx;
            tr.ty = anchor.y - rectCenterY - rotAy;
            tr.angle = ang;
        }
        _liftSelection(asClone) {
            const sel = this.sel;
            const dpr = this.engine.dpr;
            const ctx = this.engine.ctx;
            const canvas = this.engine.canvas;

            // The canvas currently has the PRISTINE overlay (ants + handles) drawn on top.
            // Restore from the clean base before snapshotting / pixel-copying — otherwise
            // the snapshot and floating buffer would include the overlay.
            if (this.engine.selectionRenderBase) {
                ctx.putImageData(this.engine.selectionRenderBase, 0, 0);
            }
            // 1. Full-canvas snapshot for Cancel revert (now clean, no overlay)
            sel.pristineSnapshot = ctx.getImageData(0, 0, canvas.width, canvas.height);

            // 2. Off-screen floating canvas
            const fw = Math.max(1, Math.round(sel.rect.w * dpr));
            const fh = Math.max(1, Math.round(sel.rect.h * dpr));
            sel.floating = document.createElement('canvas');
            sel.floating.width = fw;
            sel.floating.height = fh;
            const fctx = sel.floating.getContext('2d');

            // 3. Copy pixels from main canvas (source coords in INTERNAL pixels, that's what mainCanvas exposes)
            fctx.drawImage(
                canvas,
                Math.round(sel.rect.x * dpr), Math.round(sel.rect.y * dpr),
                fw, fh,
                0, 0,
                fw, fh
            );

            // 4. Update render base — for non-clone we need base WITHOUT the lifted area (white hole baked in).
            //    For clone, leave the original pixels in base so they stay visible underneath floating.
            sel.isClone = !!asClone;
            if (!asClone) {
                // Compose new base: current canvas with white rect over the selection area.
                // Render base is what putImageData restores each frame, so we need the hole baked in.
                const baseImg = ctx.getImageData(0, 0, canvas.width, canvas.height);
                const tmp = document.createElement('canvas');
                tmp.width = canvas.width;
                tmp.height = canvas.height;
                const tctx = tmp.getContext('2d');
                tctx.putImageData(baseImg, 0, 0);
                // Fill hole at internal-pixel coords (no scale on tmp ctx)
                tctx.fillStyle = '#ffffff';
                tctx.fillRect(
                    Math.round(sel.rect.x * dpr),
                    Math.round(sel.rect.y * dpr),
                    Math.round(sel.rect.w * dpr),
                    Math.round(sel.rect.h * dpr)
                );
                this.engine.selectionRenderBase = tctx.getImageData(0, 0, canvas.width, canvas.height);
            }
            // For clone, selectionRenderBase already captured at PRISTINE start — keep it as is (no hole).

            sel.phase = 'modified';
        }
        _bakeFloating() {
            const sel = this.sel;
            const ctx = this.engine.ctx;
            // Restore base (clean — without ants/handles overlay)
            if (this.engine.selectionRenderBase) {
                ctx.putImageData(this.engine.selectionRenderBase, 0, 0);
            }
            // Bake floating with current transform
            const cx = sel.rect.x + sel.rect.w/2;
            const cy = sel.rect.y + sel.rect.h/2;
            ctx.save();
            ctx.translate(cx + sel.transform.tx, cy + sel.transform.ty);
            ctx.rotate(sel.transform.angle);
            ctx.scale(sel.transform.sx, sel.transform.sy);
            ctx.translate(-sel.rect.w/2, -sel.rect.h/2);
            ctx.imageSmoothingEnabled = true;
            ctx.imageSmoothingQuality = 'high';
            ctx.drawImage(sel.floating, 0, 0, sel.rect.w, sel.rect.h);
            ctx.restore();
        }

        // Compute world-space corner positions of the selection rect after current transform.
        // Returns named map { TL, T, TR, R, BR, B, BL, L, center, ROT } in CSS pixels.
        _computeCorners() {
            const sel = this.sel;
            const tr = sel.transform || { tx: 0, ty: 0, sx: 1, sy: 1, angle: 0 };
            const W = sel.rect.w, H = sel.rect.h;
            const cx = sel.rect.x + W/2 + tr.tx;
            const cy = sel.rect.y + H/2 + tr.ty;
            const cosA = Math.cos(tr.angle), sinA = Math.sin(tr.angle);
            const local = (lx, ly) => {
                const sx = lx * tr.sx, sy = ly * tr.sy;
                return {
                    x: cx + sx * cosA - sy * sinA,
                    y: cy + sx * sinA + sy * cosA
                };
            };
            const corners = {
                TL: local(-W/2, -H/2), T: local(0, -H/2), TR: local(W/2, -H/2),
                R:  local(W/2,  0),                       L:  local(-W/2, 0),
                BL: local(-W/2, H/2), B: local(0, H/2),  BR: local(W/2,  H/2),
                center: { x: cx, y: cy }
            };
            // ROT handle: 24 CSS px outward from T-edge along the (center→T) direction.
            const dx = corners.T.x - cx, dy = corners.T.y - cy;
            const len = Math.sqrt(dx*dx + dy*dy);
            const ux = len > 0.001 ? dx / len : 0;
            const uy = len > 0.001 ? dy / len : -1;
            corners.ROT = { x: corners.T.x + ux * 24, y: corners.T.y + uy * 24 };
            return corners;
        }

        // Marching ants drawn in CSS-pixel screen space along the rect's transformed quad.
        // Uniform 1px stroke regardless of scale/rotation — no border-thickening artefacts.
        _drawAnts(corners) {
            const ctx = this.engine.ctx;
            const offset = this.engine.dashOffset || 0;
            ctx.save();
            ctx.lineWidth = 1;
            ctx.setLineDash([4, 4]);
            ctx.beginPath();
            ctx.moveTo(corners.TL.x, corners.TL.y);
            ctx.lineTo(corners.TR.x, corners.TR.y);
            ctx.lineTo(corners.BR.x, corners.BR.y);
            ctx.lineTo(corners.BL.x, corners.BL.y);
            ctx.closePath();
            ctx.strokeStyle = '#ffffff';
            ctx.lineDashOffset = -offset;
            ctx.stroke();
            ctx.strokeStyle = '#000000';
            ctx.lineDashOffset = -offset + 4;
            ctx.stroke();
            ctx.restore();
        }

        // Handles drawn in CSS-pixel screen space — always 8x8 squares with 1px border.
        // Independent of any current transform on ctx; positions come from _computeCorners.
        _drawHandles(corners) {
            const ctx = this.engine.ctx;
            const s = 8;   // visual size in CSS px
            const lw = 1;
            const positions = [corners.TL, corners.T, corners.TR, corners.R, corners.BR, corners.B, corners.BL, corners.L];
            ctx.save();
            ctx.fillStyle = '#ffffff';
            ctx.strokeStyle = '#000000';
            ctx.lineWidth = lw;
            for (const p of positions) {
                ctx.fillRect(p.x - s/2, p.y - s/2, s, s);
                ctx.strokeRect(p.x - s/2, p.y - s/2, s, s);
            }
            // Connector line T → ROT
            ctx.beginPath();
            ctx.moveTo(corners.T.x, corners.T.y);
            ctx.lineTo(corners.ROT.x, corners.ROT.y);
            ctx.stroke();
            // ROT handle (circle in screen space)
            ctx.beginPath();
            ctx.arc(corners.ROT.x, corners.ROT.y, s/2, 0, Math.PI * 2);
            ctx.fill();
            ctx.stroke();
            ctx.restore();
        }

        _reset() {
            const sel = this.sel;
            sel.active = false;
            sel.phase = 'pristine';
            sel.rect = null;
            sel.transform = null;
            sel.floating = null;
            sel.pristineSnapshot = null;
            sel.isClone = false;
            this.engine.stopDashTimer();
        }
    }


    class GuideManager {
        constructor(state, drawCanvas, overlayCanvas, rulerTop, rulerLeft, canvasHostEl) {
            this.state = state;
            this.drawCanvas = drawCanvas;
            this.overlayCanvas = overlayCanvas;
            this.rulerTop = rulerTop;
            this.rulerLeft = rulerLeft;
            this.canvasHostEl = canvasHostEl;
            this.dpr = window.devicePixelRatio || 1;

            this.overlayCtx = overlayCanvas.getContext('2d');
            this.overlayCtx.scale(this.dpr, this.dpr);

            this.rulerTopCtx = rulerTop.getContext('2d');
            this.rulerTopCtx.scale(this.dpr, this.dpr);

            this.rulerLeftCtx = rulerLeft.getContext('2d');
            this.rulerLeftCtx.scale(this.dpr, this.dpr);

            this.activeDrag = null;
            this.cursorX = null;          // canvas-pixel x of current cursor (null when not over canvas)
            this.cursorY = null;
            this._onWindowMove = (e) => this._handleWindowMove(e);
            this._onWindowUp   = (e) => this._handleWindowUp(e);
        }

        renderRulers() {
            this._drawRuler(this.rulerTopCtx,  'h', this.drawCanvas.width / this.dpr, 20);
            this._drawRuler(this.rulerLeftCtx, 'v', 20, this.drawCanvas.height / this.dpr);
            this._drawCursorMark();
        }

        setCursor(x, y) {
            this.cursorX = x;
            this.cursorY = y;
            this.renderRulers();
        }

        _drawCursorMark() {
            if (this.cursorX != null) {
                const ctx = this.rulerTopCtx;
                ctx.strokeStyle = '#d33';
                ctx.lineWidth = 1;
                ctx.beginPath();
                ctx.moveTo(this.cursorX + 0.5, 0);
                ctx.lineTo(this.cursorX + 0.5, 20);
                ctx.stroke();
            }
            if (this.cursorY != null) {
                const ctx = this.rulerLeftCtx;
                ctx.strokeStyle = '#d33';
                ctx.lineWidth = 1;
                ctx.beginPath();
                ctx.moveTo(0,  this.cursorY + 0.5);
                ctx.lineTo(20, this.cursorY + 0.5);
                ctx.stroke();
            }
        }

        renderGuides() {
            const ctx = this.overlayCtx;
            const w = this.drawCanvas.width  / this.dpr;
            const h = this.drawCanvas.height / this.dpr;
            ctx.clearRect(0, 0, w, h);

            ctx.lineWidth = 1;
            ctx.strokeStyle = '#5cd1e6';
            ctx.setLineDash([]);

            // 1) Committed guides
            for (let i = 0; i < this.state.guides.length; i++) {
                const g = this.state.guides[i];
                const isMovingThis = !!(this.activeDrag && this.activeDrag.mode === 'move' && this.activeDrag.idx === i);
                const willDelete = isMovingThis && this.activeDrag.pointerOverCanvas === false;
                ctx.globalAlpha = willDelete ? 0.4 : 1;
                ctx.beginPath();
                if (g.axis === 'h') {
                    ctx.moveTo(0, g.pos + 0.5);
                    ctx.lineTo(w, g.pos + 0.5);
                } else {
                    ctx.moveTo(g.pos + 0.5, 0);
                    ctx.lineTo(g.pos + 0.5, h);
                }
                ctx.stroke();
            }
            ctx.globalAlpha = 1;

            // 2) Create-drag preview (dashed) — only if cursor is currently over canvas-host
            if (this.activeDrag && this.activeDrag.mode === 'create' && this.activeDrag.pointerOverCanvas) {
                ctx.setLineDash([4, 4]);
                ctx.beginPath();
                if (this.activeDrag.axis === 'h') {
                    ctx.moveTo(0, this.activeDrag.pos + 0.5);
                    ctx.lineTo(w, this.activeDrag.pos + 0.5);
                } else {
                    ctx.moveTo(this.activeDrag.pos + 0.5, 0);
                    ctx.lineTo(this.activeDrag.pos + 0.5, h);
                }
                ctx.stroke();
                ctx.setLineDash([]);
            }
        }

        onRulerMouseDown(axis, e) {
            if (this.activeDrag) return;
            const { x: cx, y: cy } = this._eventClient(e);
            const local = this._clientToCanvas(cx, cy);
            this.activeDrag = {
                mode: 'create',
                axis,
                pos: (axis === 'h') ? local.y : local.x,
                pointerOverCanvas: this._isPointerOverCanvas(cx, cy)
            };
            this._bindWindowDrag();
            this.renderGuides();
        }

        hitTest(x, y) {
            for (let i = this.state.guides.length - 1; i >= 0; i--) {
                const g = this.state.guides[i];
                const d = (g.axis === 'h') ? Math.abs(y - g.pos) : Math.abs(x - g.pos);
                if (d <= 5) return { idx: i, axis: g.axis };
            }
            return null;
        }

        startGuideDrag(idx) {
            if (this.activeDrag) return;
            const g = this.state.guides[idx];
            if (!g) return;
            this.activeDrag = {
                mode: 'move',
                axis: g.axis,
                idx,
                originalPos: g.pos,
                pointerOverCanvas: true
            };
            this._bindWindowDrag();
            this.renderGuides();
        }

        reset() {
            this.state.guides = [];
            if (this.activeDrag) {
                window.removeEventListener('mousemove', this._onWindowMove);
                window.removeEventListener('mouseup',   this._onWindowUp);
                window.removeEventListener('touchmove', this._onWindowMove);
                window.removeEventListener('touchend',  this._onWindowUp);
                this.activeDrag = null;
            }
        }

        resize(newW, newH) {
            // Resize the overlay canvas
            this.overlayCanvas.width  = newW * this.dpr;
            this.overlayCanvas.height = newH * this.dpr;
            this.overlayCanvas.style.width  = newW + 'px';
            this.overlayCanvas.style.height = newH + 'px';
            this.overlayCtx = this.overlayCanvas.getContext('2d');
            this.overlayCtx.scale(this.dpr, this.dpr);

            // Resize ruler-top
            this.rulerTop.width  = newW * this.dpr;
            this.rulerTop.height = 20  * this.dpr;
            this.rulerTop.style.width  = newW + 'px';
            this.rulerTop.style.height = '20px';
            this.rulerTopCtx = this.rulerTop.getContext('2d');
            this.rulerTopCtx.scale(this.dpr, this.dpr);

            // Resize ruler-left
            this.rulerLeft.width  = 20  * this.dpr;
            this.rulerLeft.height = newH * this.dpr;
            this.rulerLeft.style.width  = '20px';
            this.rulerLeft.style.height = newH + 'px';
            this.rulerLeftCtx = this.rulerLeft.getContext('2d');
            this.rulerLeftCtx.scale(this.dpr, this.dpr);

            // Drop off-bounds guides
            this.state.guides = this.state.guides.filter(g =>
                (g.axis === 'h') ? (g.pos >= 0 && g.pos <= newH)
                                 : (g.pos >= 0 && g.pos <= newW)
            );

            this.renderRulers();
            this.renderGuides();
        }

        _tickSpacing() {
            const w = this.drawCanvas.width / this.dpr;
            const h = this.drawCanvas.height / this.dpr;
            const m = Math.max(w, h);
            if (m <=  300) return { minor:  5, major:  25 };
            if (m <= 1000) return { minor: 10, major:  50 };
            if (m <= 3000) return { minor: 20, major: 100 };
            return                  { minor: 50, major: 250 };
        }

        _drawRuler(ctx, axis, widthCSS, heightCSS) {
            ctx.clearRect(0, 0, widthCSS, heightCSS);
            ctx.fillStyle = '#ececec';
            ctx.fillRect(0, 0, widthCSS, heightCSS);

            const { minor, major } = this._tickSpacing();
            const limit = (axis === 'h') ? widthCSS : heightCSS;

            ctx.lineWidth = 1;
            ctx.font = '9px sans-serif';
            ctx.textBaseline = 'top';

            for (let pos = 0; pos <= limit; pos += minor) {
                const isMajor = (pos % major === 0);
                if (axis === 'h') {
                    ctx.strokeStyle = isMajor ? '#666' : '#999';
                    ctx.beginPath();
                    ctx.moveTo(pos + 0.5, isMajor ? 16 : 18);
                    ctx.lineTo(pos + 0.5, 20);
                    ctx.stroke();
                    if (isMajor && pos !== 0) {
                        ctx.fillStyle = '#444';
                        ctx.textAlign = 'left';
                        ctx.fillText(String(pos), pos + 2, 1);
                    }
                } else {
                    ctx.strokeStyle = isMajor ? '#666' : '#999';
                    ctx.beginPath();
                    ctx.moveTo(isMajor ? 16 : 18, pos + 0.5);
                    ctx.lineTo(20,                pos + 0.5);
                    ctx.stroke();
                    if (isMajor && pos !== 0) {
                        ctx.save();
                        ctx.translate(11, pos + 2);
                        ctx.rotate(-Math.PI / 2);
                        ctx.fillStyle = '#444';
                        ctx.textAlign = 'right';
                        ctx.fillText(String(pos), 0, 0);
                        ctx.restore();
                    }
                }
            }
        }

        _eventClient(e) {
            if (e.touches && e.touches.length) return { x: e.touches[0].clientX, y: e.touches[0].clientY };
            if (e.changedTouches && e.changedTouches.length) return { x: e.changedTouches[0].clientX, y: e.changedTouches[0].clientY };
            return { x: e.clientX, y: e.clientY };
        }

        _clientToCanvas(clientX, clientY) {
            const r = this.canvasHostEl.getBoundingClientRect();
            return { x: clientX - r.left, y: clientY - r.top };
        }

        _isPointerOverCanvas(clientX, clientY) {
            const r = this.canvasHostEl.getBoundingClientRect();
            return clientX >= r.left && clientX <= r.right && clientY >= r.top && clientY <= r.bottom;
        }

        _bindWindowDrag() {
            window.addEventListener('mousemove', this._onWindowMove);
            window.addEventListener('mouseup',   this._onWindowUp);
            window.addEventListener('touchmove', this._onWindowMove, { passive: false });
            window.addEventListener('touchend',  this._onWindowUp);
        }

        _unbindWindowDrag() {
            window.removeEventListener('mousemove', this._onWindowMove);
            window.removeEventListener('mouseup',   this._onWindowUp);
            window.removeEventListener('touchmove', this._onWindowMove);
            window.removeEventListener('touchend',  this._onWindowUp);
        }

        _handleWindowMove(e) {
            if (!this.activeDrag) return;
            if (e.cancelable) e.preventDefault();
            const { x: cx, y: cy } = this._eventClient(e);
            const local = this._clientToCanvas(cx, cy);
            const ad = this.activeDrag;
            ad.pointerOverCanvas = this._isPointerOverCanvas(cx, cy);
            if (ad.mode === 'create') {
                ad.pos = (ad.axis === 'h') ? local.y : local.x;
            } else if (ad.mode === 'move') {
                const g = this.state.guides[ad.idx];
                if (g) g.pos = (ad.axis === 'h') ? local.y : local.x;
            }
            this.renderGuides();
        }

        _handleWindowUp(e) {
            if (!this.activeDrag) return;
            const { x: cx, y: cy } = this._eventClient(e);
            const overCanvas = this._isPointerOverCanvas(cx, cy);
            const ad = this.activeDrag;

            if (ad.mode === 'create') {
                if (overCanvas) {
                    const local = this._clientToCanvas(cx, cy);
                    const pos = (ad.axis === 'h') ? local.y : local.x;
                    this.state.guides.push({ axis: ad.axis, pos });
                }
                // else: drag cancelled — no guide added
            } else if (ad.mode === 'move') {
                if (!overCanvas) {
                    this.state.guides.splice(ad.idx, 1);
                }
                // else: keep the new pos as already mutated during move
            }

            this.activeDrag = null;
            this._unbindWindowDrag();
            this.renderGuides();
        }
    }

    class DrawingEngine {
        constructor(canvas, state) {
            this.canvas = canvas;
            this.state = state;
            this.dpr = window.devicePixelRatio || 1;
            this.ctx = canvas.getContext('2d', { willReadFrequently: true });
            this.ctx.scale(this.dpr, this.dpr);

            this.tempCanvas = document.createElement('canvas');
            this.tempCanvas.width = canvas.width;
            this.tempCanvas.height = canvas.height;
            this.tempCtx = this.tempCanvas.getContext('2d');
            this.tempCtx.scale(this.dpr, this.dpr);

            this.history = [];
            this.historyIndex = -1;
            this.snapshot = null;
            this.selectionRenderBase = null;  // ImageData captured when selection becomes active; used to wipe per-frame overlays
            this.controllerUI = null;          // backref set by UIManager.open() — needed by SelectionTool to call renderToolOptions / pulseSelectionButtons
            this.guides = null;            // GuideManager instance — set by UIManager after modal DOM exists
            this.dashOffset = 0;
            this._dashTimer = null;

            this.tools = {
                brush: new BrushTool(this),
                eraser: new EraserTool(this),
                line: new LineTool(this),
                rect: new RectTool(this),
                circle: new CircleTool(this),
                text: new TextTool(this),
                fill: new FillTool(this),
                selection: new SelectionTool(this)
            };
            this.currentTool = this.tools.brush;
        }

        setTool(toolName) {
            if (this.tools[toolName]) this.currentTool = this.tools[toolName];
        }

        start(x, y, t, e) {
            this.currentTool.onStart(x, y, t, e);
        }

        move(x, y, t, e) {
            this.currentTool.onMove(x, y, t, e);
        }

        end(t) {
            this.currentTool.onEnd(t);
            if (this.currentTool instanceof BrushTool || this.currentTool instanceof EraserTool) {
               this.commitState();
            }
            // SelectionTool commits explicitly via apply()
        }

        saveSnapshot() {
            this.snapshot = this.ctx.getImageData(0, 0, this.canvas.width, this.canvas.height);
        }
        restoreSnapshot() {
            if (this.snapshot) this.ctx.putImageData(this.snapshot, 0, 0);
        }
        clearTemp() {
            // ctx is dpr-scaled, so logical dimensions in user-coord space:
            this.tempCtx.clearRect(0, 0, this.tempCanvas.width / this.dpr, this.tempCanvas.height / this.dpr);
        }
        renderTemp() {
            this.restoreSnapshot();
            this.ctx.globalAlpha = this.state.opacity;
            // Explicit destination size in CSS pixels (scaled ctx maps to internal):
            const w = this.canvas.width / this.dpr;
            const h = this.canvas.height / this.dpr;
            this.ctx.drawImage(this.tempCanvas, 0, 0, w, h);
            this.ctx.globalAlpha = 1;
        }

        captureSelectionBase() {
            this.selectionRenderBase = this.ctx.getImageData(0, 0, this.canvas.width, this.canvas.height);
        }

        renderSelection() {
            const sel = this.state.selection;
            if (!sel.active) {
                if (this.selectionRenderBase) {
                    this.ctx.putImageData(this.selectionRenderBase, 0, 0);
                    this.selectionRenderBase = null;
                }
                return;
            }
            if (this.selectionRenderBase) {
                this.ctx.putImageData(this.selectionRenderBase, 0, 0);
            }
            const tool = this.tools.selection;
            // Pixel content: floating buffer transformed onto canvas (only in MODIFIED phase).
            if (sel.phase === 'modified' && sel.floating) {
                if (!sel.isClone) {
                    this.ctx.save();
                    this.ctx.fillStyle = '#ffffff';
                    this.ctx.fillRect(sel.rect.x, sel.rect.y, sel.rect.w, sel.rect.h);
                    this.ctx.restore();
                }
                const cx = sel.rect.x + sel.rect.w/2;
                const cy = sel.rect.y + sel.rect.h/2;
                this.ctx.save();
                this.ctx.translate(cx + sel.transform.tx, cy + sel.transform.ty);
                this.ctx.rotate(sel.transform.angle);
                this.ctx.scale(sel.transform.sx, sel.transform.sy);
                this.ctx.translate(-sel.rect.w/2, -sel.rect.h/2);
                this.ctx.imageSmoothingEnabled = true;
                this.ctx.imageSmoothingQuality = 'high';
                this.ctx.drawImage(sel.floating, 0, 0, sel.rect.w, sel.rect.h);
                this.ctx.restore();
            }
            // Overlays (ants + handles) drawn in screen space — independent of transform.
            // This guarantees uniform 1px borders regardless of scale/rotation, and prevents
            // border-merging artefacts when handles overlap (small rect / large scale).
            const corners = tool._computeCorners();
            tool._drawAnts(corners);
            tool._drawHandles(corners);
        }

        startDashTimer() {
            if (this._dashTimer) return;
            this._dashTimer = setInterval(() => {
                if (!this.state.selection.active) return;
                this.dashOffset = (this.dashOffset + 1) % 8;
                this.renderSelection();
            }, 120);
        }

        stopDashTimer() {
            if (this._dashTimer) {
                clearInterval(this._dashTimer);
                this._dashTimer = null;
            }
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
                const w = this.canvas.width / this.dpr;
                const h = this.canvas.height / this.dpr;
                this.ctx.clearRect(0, 0, w, h);
                this.ctx.fillStyle = '#ffffff';
                this.ctx.fillRect(0, 0, w, h);
                this.ctx.drawImage(img, 0, 0, w, h);
            };
            img.src = this.history[this.historyIndex];
        }

        clear() {
            const w = this.canvas.width / this.dpr;
            const h = this.canvas.height / this.dpr;
            this.ctx.fillStyle = '#ffffff';
            this.ctx.fillRect(0, 0, w, h);
            this.commitState();
        }

        _applyDprToCanvas(logicalW, logicalH) {
            // Resize canvas to dpr-scaled internal pixels with logical CSS sizing,
            // then re-apply ctx.scale (canvas resize resets transform).
            this.canvas.width = logicalW * this.dpr;
            this.canvas.height = logicalH * this.dpr;
            this.canvas.style.width = logicalW + 'px';
            this.canvas.style.height = logicalH + 'px';
            this.tempCanvas.width = logicalW * this.dpr;
            this.tempCanvas.height = logicalH * this.dpr;
            this.ctx.scale(this.dpr, this.dpr);
            this.tempCtx.scale(this.dpr, this.dpr);
        }

        resize(w, h) {
            const data = this.canvas.toDataURL();
            const img = new Image();
            img.onload = () => {
                this._applyDprToCanvas(w, h);
                this.ctx.fillStyle = '#ffffff';
                this.ctx.fillRect(0, 0, w, h);
                this.ctx.drawImage(img, 0, 0, w, h);
                this.commitState();
                if (this.guides) this.guides.resize(w, h);
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

            this._applyDprToCanvas(w, h);

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
            this.controller.engine.controllerUI = this;
            const drawCanvas    = $('.paint-canvas')[0];
            const overlayCanvas = $('.paint-guide-overlay')[0];
            const rulerTop      = $('.paint-ruler-top')[0];
            const rulerLeft     = $('.paint-ruler-left')[0];
            const canvasHostEl  = $('.paint-canvas-host')[0];
            this.controller.engine.guides = new GuideManager(
                this.state, drawCanvas, overlayCanvas, rulerTop, rulerLeft, canvasHostEl
            );
            this.controller.engine.guides.renderRulers();
            this.controller.engine.guides.renderGuides();
            this.controller.engine.clear();
            this.renderToolOptions();

            if (imageUrl) this.controller.loadImage(imageUrl);
        }

        renderModal(dims) {
            const dpr = window.devicePixelRatio || 1;
            const html = `
                <div class="paint-modal-overlay">
                    <div class="paint-modal">
                        <div class="paint-header"><h3>Dead Paint</h3><button class="paint-close">&times;</button></div>
                        <div class="paint-toolbar">
                            <div class="paint-toolbar-group">
                                ${[
                                    {n: 'brush',     i: 'fa-solid fa-paintbrush',    t: 'Brush'},
                                    {n: 'eraser',    i: 'fa-solid fa-eraser',        t: 'Eraser'},
                                    {n: 'line',      i: 'fa-solid fa-slash',         t: 'Line'},
                                    {n: 'rect',      i: 'fa-regular fa-square',      t: 'Rectangle'},
                                    {n: 'circle',    i: 'fa-regular fa-circle',      t: 'Circle'},
                                    {n: 'fill',      i: 'fa-solid fa-fill-drip',     t: 'Fill'},
                                    {n: 'text',      i: 'fa-solid fa-font',          t: 'Text'},
                                    {n: 'selection', i: 'fa-solid fa-vector-square', t: 'Selection'}
                                ].map(t =>
                                    `<button class="paint-btn ${t.n === 'brush' ? 'active' : ''}" title="${t.t}" data-tool="${t.n}"><i class="${t.i}"></i></button>`
                                ).join('')}
                            </div>
                            <div class="paint-toolbar-group">
                                <input type="color" class="paint-color-input" value="${this.state.color}">
                            </div>
                            <div class="paint-toolbar-group">
                                <button class="paint-btn action-btn" data-action="undo" title="Undo"><i class="fa-solid fa-rotate-left"></i></button>
                                <button class="paint-btn action-btn" data-action="redo" title="Redo"><i class="fa-solid fa-rotate-right"></i></button>
                                <button class="paint-btn action-btn" data-action="clear" title="Clear"><i class="fa-solid fa-trash"></i></button>
                                <button class="paint-btn action-btn" data-action="load" title="Load"><i class="fa-solid fa-folder-open"></i></button>
                                <button class="paint-btn action-btn" data-action="save" title="Save"><i class="fa-solid fa-floppy-disk"></i></button>
                            </div>
                        </div>
                        <div class="paint-tool-options"></div>
                        <div class="paint-canvas-container">
                            <div class="paint-canvas-frame">
                                <div class="paint-ruler-corner"></div>
                                <canvas class="paint-ruler-top"   width="${dims.w * dpr}" height="${20 * dpr}" style="width: ${dims.w}px; height: 20px;"></canvas>
                                <canvas class="paint-ruler-left"  width="${20 * dpr}"     height="${dims.h * dpr}" style="width: 20px; height: ${dims.h}px;"></canvas>
                                <div class="paint-canvas-host">
                                    <canvas class="paint-canvas"        width="${dims.w * dpr}" height="${dims.h * dpr}" style="width: ${dims.w}px; height: ${dims.h}px;"></canvas>
                                    <canvas class="paint-guide-overlay" width="${dims.w * dpr}" height="${dims.h * dpr}" style="width: ${dims.w}px; height: ${dims.h}px;"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="paint-footer">
                            <div class="paint-dimensions">
                                <input type="number" id="paint-w" class="paint-dim" value="${dims.w}"> x
                                <input type="number" id="paint-h" class="paint-dim" value="${dims.h}">
                                <button class="paint-btn" data-action="resize" title="Resize"><i class="fa-solid fa-up-right-and-down-left-from-center"></i></button>
                            </div>
                            <div class="paint-actions">
                                <button class="paint-btn" data-action="cancel" title="Cancel"><i class="fa-solid fa-xmark"></i> Cancel</button>
                                <button class="paint-btn paint-btn-done" data-action="done" title="Done"><i class="fa-solid fa-check"></i> Done</button>
                            </div>
                        </div>
                    </div>
                </div>`;
            $(html).appendTo('body');
        }

        bindEvents() {
            const self = this;
            
            $('[data-tool]').on('click', function() {
                const eng = self.controller.engine;
                const seltool = eng && eng.tools && eng.tools.selection;
                // Hard-block when selection is MODIFIED
                if (seltool && eng.state.selection.active && eng.state.selection.phase === 'modified') {
                    self.pulseSelectionButtons();
                    return;
                }
                // PRISTINE → drop selection silently before tool switch
                if (seltool && eng.state.selection.active && eng.state.selection.phase === 'pristine') {
                    seltool.cancel();
                }
                $('[data-tool]').removeClass('active');
                $(this).addClass('active');
                self.controller.setTool($(this).data('tool'));
                self.renderToolOptions();
                const canvasEl = $('.paint-canvas')[0];
                if (canvasEl) canvasEl.style.cursor = 'crosshair';
            });

            $('.paint-color-input').on('input', function() { self.state.color = this.value; self.state.save(); });

            const $modal = $('.paint-modal');
            $modal.on('input', '.size-ctl', function() {
                self.state.brushSize = parseInt(this.value, 10);
                $modal.find('.size-val').text(this.value + 'px');
                self.state.save();
            });
            $modal.on('input', '.opacity-ctl', function() {
                self.state.opacity = this.value / 100;
                $modal.find('.opacity-val').text(this.value + '%');
                self.state.save();
            });
            $modal.on('input', '.smoothing-ctl', function() {
                self.state.smoothing = parseInt(this.value, 10);
                $modal.find('.smoothing-val').text(this.value + '%');
                self.state.save();
            });
            $modal.on('input', '.taper-ctl', function() {
                self.state.taper = parseInt(this.value, 10);
                $modal.find('.taper-val').text(this.value + '%');
                self.state.save();
            });
            $modal.on('input', '.tolerance-ctl', function() {
                self.state.fillTolerance = parseInt(this.value, 10);
                $modal.find('.tolerance-val').text(this.value);
                self.state.save();
            });
            $modal.on('input', '.gapclose-ctl', function() {
                self.state.fillGapClose = parseInt(this.value, 10);
                $modal.find('.gapclose-val').text(this.value + 'px');
                self.state.save();
            });

            $('.paint-modal').on('click', '[data-action]', function() {
                const action = $(this).data('action');
                const eng = self.controller.engine;
                const seltool = eng && eng.tools && eng.tools.selection;
                // Selection-specific actions route to SelectionTool
                if (seltool && (action === 'apply' || action === 'selCancel' || action === 'flipH' || action === 'flipV' || action === 'duplicate')) {
                    if (action === 'apply') seltool.apply();
                    else if (action === 'selCancel') seltool.cancel();
                    else if (action === 'flipH') seltool.flipH();
                    else if (action === 'flipV') seltool.flipV();
                    else if (action === 'duplicate') seltool.duplicate();
                    return;
                }
                const destructive = (action === 'undo' || action === 'redo' || action === 'clear' ||
                                    action === 'load' || action === 'resize');
                // Hard-block destructive + export actions while MODIFIED — floating isn't baked yet,
                // so save/done would export the wrong state.
                if (seltool && eng.state.selection.active && eng.state.selection.phase === 'modified') {
                    if (destructive || action === 'save' || action === 'done') {
                        self.pulseSelectionButtons();
                        return;
                    }
                }
                // PRISTINE + destructive → drop selection so its rect doesn't dangle on stale coords
                if (seltool && eng.state.selection.active && eng.state.selection.phase === 'pristine' && destructive) {
                    seltool.cancel();
                }
                if (self.controller[action]) self.controller[action]();
                else if (eng && eng[action]) eng[action]();
            });

            $('.paint-close, [data-action="cancel"]').on('click', function(e) {
                if (e.target === this) self.controller.close();
            });

            const canvas = $('.paint-canvas')[0];
            const getCoords = (e) => {
                // Returns CSS-pixel coordinates relative to canvas. The DrawingEngine
                // applies ctx.scale(dpr, dpr) so all drawing happens in CSS-pixel space;
                // returning logical coords keeps everything consistent.
                const rect = canvas.getBoundingClientRect();
                const clientX = e.touches ? e.touches[0].clientX : e.clientX;
                const clientY = e.touches ? e.touches[0].clientY : e.clientY;
                const t = (e.timeStamp != null) ? e.timeStamp : performance.now();
                return { x: clientX - rect.left, y: clientY - rect.top, t };
            };

            let isDrawing = false;
            const start = (e) => {
                e.preventDefault();
                const eng = self.controller.engine;
                const c = getCoords(e);

                // Priority 1: Selection geometry (handle / inside / rotate)
                const seltool = eng && eng.tools && eng.tools.selection;
                if (seltool && eng.state.selection.active) {
                    const zone = seltool.hitTestZone(c.x, c.y);
                    if (zone !== 'background') {
                        isDrawing = true;
                        eng.start(c.x, c.y, c.t, e);
                        return;
                    }
                    // zone === 'background' → fall through to guide hit-test
                }

                // Priority 2: Guide hit-test
                if (eng && eng.guides) {
                    const hit = eng.guides.hitTest(c.x, c.y);
                    if (hit) {
                        eng.guides.startGuideDrag(hit.idx);
                        isDrawing = false;
                        return;
                    }
                }

                // Priority 3: Tool default (selection PRISTINE drop happens inside SelectionTool.onStart as today)
                isDrawing = true;
                eng.start(c.x, c.y, c.t, e);
            };
            const moveHandler = (e) => {
                if (!isDrawing) return;
                e.preventDefault();
                const c = getCoords(e);
                self.controller.engine.move(c.x, c.y, c.t, e);
            };
            const end = (e) => {
                if (!isDrawing) return;
                isDrawing = false;
                const t = (e && e.timeStamp != null) ? e.timeStamp : performance.now();
                self.controller.engine.end(t);
            };

            canvas.addEventListener('mousedown', start);
            canvas.addEventListener('mousemove', moveHandler);
            canvas.addEventListener('mouseup', end);
            canvas.addEventListener('mouseleave', end);
            canvas.addEventListener('touchstart', start, { passive: false });
            canvas.addEventListener('touchmove', moveHandler, { passive: false });
            canvas.addEventListener('touchend', end);

            const trackCursor = (e) => {
                const eng = self.controller.engine;
                if (!eng || !eng.guides) return;
                const c = getCoords(e);
                eng.guides.setCursor(c.x, c.y);
            };
            canvas.addEventListener('mousemove', trackCursor);
            canvas.addEventListener('touchmove', trackCursor, { passive: false });
            canvas.addEventListener('mouseleave', () => {
                const eng = self.controller.engine;
                if (eng && eng.guides) eng.guides.setCursor(null, null);
            });

            canvas.addEventListener('mousemove', (e) => {
                if (isDrawing) return;
                const eng = self.controller.engine;
                if (!eng) return;
                if (eng.guides && eng.guides.activeDrag) return;   // active drag controls cursor on its own

                const c = getCoords(e);
                const seltool = eng.tools && eng.tools.selection;

                // Selection-tool hover cursor (existing behaviour)
                if (seltool && eng.currentTool === seltool && eng.state.selection.active) {
                    const zone = seltool.hitTestZone(c.x, c.y);
                    if (zone !== 'background') {
                        let cursor = 'crosshair';
                        switch (zone) {
                            case 'inside': cursor = 'move'; break;
                            case 'rotate': cursor = 'grab'; break;
                            case 'scale-TL': case 'scale-BR': cursor = 'nwse-resize'; break;
                            case 'scale-TR': case 'scale-BL': cursor = 'nesw-resize'; break;
                            case 'scale-T': case 'scale-B': cursor = 'ns-resize'; break;
                            case 'scale-L': case 'scale-R': cursor = 'ew-resize'; break;
                        }
                        if (canvas.style.cursor !== cursor) canvas.style.cursor = cursor;
                        return;
                    }
                }

                // Guide hover (when no selection geometry was hit)
                if (eng.guides) {
                    const hit = eng.guides.hitTest(c.x, c.y);
                    if (hit) {
                        const cur = (hit.axis === 'h') ? 'row-resize' : 'col-resize';
                        if (canvas.style.cursor !== cur) canvas.style.cursor = cur;
                        return;
                    }
                }

                // Default — restore current-tool cursor
                if (canvas.style.cursor !== 'crosshair') canvas.style.cursor = 'crosshair';
            });
            
            $(document).on('keydown.paint', (e) => {
                const eng = self.controller.engine;
                const seltool = eng && eng.tools && eng.tools.selection;
                const sel = eng && eng.state.selection;
                // Selection-priority keys
                if (seltool && sel && sel.active) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        seltool.apply();
                        return;
                    }
                    if (e.key === 'Escape') {
                        e.preventDefault();
                        seltool.cancel();
                        return;
                    }
                }
                if (e.key === 'Escape') self.controller.close();
                if ((e.ctrlKey || e.metaKey) && e.key === 'z') {
                    // Hard-block undo/redo while modified
                    if (sel && sel.active && sel.phase === 'modified') {
                        self.pulseSelectionButtons();
                        e.preventDefault();
                        return;
                    }
                    // PRISTINE → drop selection so its rect doesn't dangle on stale coords
                    if (seltool && sel && sel.active && sel.phase === 'pristine') {
                        seltool.cancel();
                    }
                    e.preventDefault();
                    e.shiftKey ? eng.redo() : eng.undo();
                }
            });
            
            $(document).on('paste.paint', (e) => self.controller.handlePaste(e));

            $('.paint-ruler-top').on('mousedown touchstart', function(e) {
                const eng = self.controller.engine;
                if (!eng || !eng.guides) return;
                e.preventDefault();
                eng.guides.onRulerMouseDown('h', e.originalEvent || e);
            });
            $('.paint-ruler-left').on('mousedown touchstart', function(e) {
                const eng = self.controller.engine;
                if (!eng || !eng.guides) return;
                e.preventDefault();
                eng.guides.onRulerMouseDown('v', e.originalEvent || e);
            });
        }

        renderToolOptions() {
            const tool = this.controller.engine && this.controller.engine.currentTool;
            const html = (tool && tool.getOptionsPanel && tool.getOptionsPanel()) || '';
            $('.paint-tool-options').html(html);
        }

        pulseSelectionButtons() {
            const $btns = $('.paint-tool-options [data-action="apply"], .paint-tool-options [data-action="selCancel"]');
            $btns.addClass('pulse');
            setTimeout(() => $btns.removeClass('pulse'), 800);
        }

        cleanup() {
            if (this.controller.engine) this.controller.engine.stopDashTimer();
            // Reset selection state — PaintState persists across modal close/reopen,
            // so leaving sel.active=true would leak stale rect/snapshot/floating to the next session.
            const sel = this.state.selection;
            if (sel) {
                sel.active = false;
                sel.phase = 'pristine';
                sel.rect = null;
                sel.transform = null;
                sel.floating = null;
                sel.pristineSnapshot = null;
                sel.isClone = false;
            }
            this.state.guides = [];
            if (this.controller.engine && this.controller.engine.guides) {
                this.controller.engine.guides.reset();
                this.controller.engine.guides = null;
            }
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
                .paint-tool-options { display: flex; flex-wrap: wrap; gap: 4px; padding: 4px; background: #cdcdcd; border-bottom: 1px solid #888; min-height: 28px; box-sizing: content-box; }
                .paint-btn { background: #e8e8e8; border: 1px solid #888; cursor: pointer; min-width: 28px; height: 28px; font-size: 12px; padding: 0 5px; margin: 0; display: inline-flex; align-items: center; justify-content: center; gap: 4px; }
                .paint-btn i { font-size: 14px; line-height: 1; pointer-events: none; }
                .paint-btn:hover { background: #d0d0d0; }
                .paint-btn.active { background: #b0b0b0; border-color: #555; box-shadow: inset 1px 1px 2px rgba(0,0,0,0.2); }
                .paint-btn-done { background: #90c090; font-weight: bold; }
                .paint-btn.pulse { animation: paintBtnPulse 0.6s ease-in-out 2; }
                @keyframes paintBtnPulse {
                    0% { box-shadow: 0 0 0 0 rgba(255, 180, 0, 0.7); background: #ffd060; }
                    50% { box-shadow: 0 0 0 6px rgba(255, 180, 0, 0); background: #ffe890; }
                    100% { box-shadow: 0 0 0 0 rgba(255, 180, 0, 0); background: #e8e8e8; }
                }
                .paint-color-input { width: 28px; height: 28px; border: 1px solid #888; padding: 0; cursor: pointer; }
                .paint-canvas-container { flex: 1; overflow: auto; background: #808080; padding: 10px; display: flex; justify-content: center; }
                .paint-canvas { background: #fff; box-shadow: 2px 2px 5px rgba(0,0,0,0.3); cursor: crosshair; touch-action: none; }
                .paint-footer { display: flex; justify-content: space-between; padding: 6px 10px; background: #d4d4d4; border-top: 1px solid #888; }
                .paint-dim { width: 50px; text-align: center; }
                .paint-lbl { font-size: 11px; min-width: 30px; text-align: right; }
                .paint-range { width: 60px; height: 18px; cursor: pointer; }
                .paint-edit-image { cursor: pointer; opacity: 0.6; } .paint-edit-image:hover { opacity: 1; }
                .paint-canvas-frame {
                    display: grid;
                    grid-template-columns: 20px auto;
                    grid-template-rows:    20px auto;
                }
                .paint-ruler-corner {
                    background: #d4d4d4;
                    border-right:  1px solid #888;
                    border-bottom: 1px solid #888;
                    grid-row: 1; grid-column: 1;
                }
                .paint-ruler-top {
                    background: #ececec;
                    border-bottom: 1px solid #888;
                    cursor: row-resize;
                    touch-action: none;
                    grid-row: 1; grid-column: 2;
                    display: block;
                }
                .paint-ruler-left {
                    background: #ececec;
                    border-right: 1px solid #888;
                    cursor: col-resize;
                    touch-action: none;
                    grid-row: 2; grid-column: 1;
                    display: block;
                }
                .paint-canvas-host {
                    position: relative;
                    grid-row: 2; grid-column: 2;
                }
                .paint-canvas-host > canvas { display: block; }
                .paint-guide-overlay {
                    position: absolute;
                    top: 0; left: 0;
                    pointer-events: none;
                }
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