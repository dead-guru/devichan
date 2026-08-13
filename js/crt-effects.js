(function() {
    'use strict';

    var THEME_STYLESHEET = 'dark_new_photon';

    function isThemeActive() {
        var links = document.querySelectorAll('link[rel="stylesheet"]');
        for (var i = 0; i < links.length; i++) {
            if (links[i].href && links[i].href.indexOf(THEME_STYLESHEET) !== -1) {
                return true;
            }
        }
        return false;
    }

    if (!isThemeActive()) return;

    var DEFAULT_CONFIG = {
        scanlines: false,
        vignette: false,
        noise: false,
        glitch: false,
        phosphor: false,
        flicker: false,
        mouseglow: false
    };

    var CONFIG = loadConfig();

    function loadConfig() {
        try {
            var saved = localStorage.getItem('crt-fx-config');
            if (saved) {
                return Object.assign({}, DEFAULT_CONFIG, JSON.parse(saved));
            }
        } catch (e) {}
        return Object.assign({}, DEFAULT_CONFIG);
    }

    function saveConfig() {
        try {
            localStorage.setItem('crt-fx-config', JSON.stringify(CONFIG));
        } catch (e) {}
    }

    var canvas, ctx, animationId;
    var noiseData = [];
    var lastGlitchTime = 0;
    var glitchActive = false;
    var glitchDuration = 0;

    var mouseX = 0, mouseY = 0;
    var targetMouseX = 0, targetMouseY = 0;
    var ripples = [];

    function createCanvas() {
        canvas = document.createElement('canvas');
        canvas.id = 'crt-overlay';
        canvas.style.cssText =
            'position:fixed;top:0;left:0;width:100%;height:100%;' +
            'pointer-events:none;z-index:99999;opacity:0.4;mix-blend-mode:screen;display:none;';
        document.body.appendChild(canvas);
        ctx = canvas.getContext('2d');
        resize();
        window.addEventListener('resize', resize);
    }

    function updateCanvasVisibility() {
        var anyEnabled = CONFIG.scanlines || CONFIG.noise || CONFIG.glitch ||
                         CONFIG.phosphor || CONFIG.vignette || CONFIG.flicker || CONFIG.mouseglow;
        canvas.style.display = anyEnabled ? 'block' : 'none';
    }

    function resize() {
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
        generateNoiseData();
    }

    function generateNoiseData() {
        var size = 128;
        noiseData = [];
        for (var i = 0; i < size * size; i++) {
            noiseData.push(Math.random());
        }
    }

    function drawScanlines() {
        if (!CONFIG.scanlines) return;

        ctx.fillStyle = 'rgba(0, 255, 136, 0.075)';
        var lineHeight = 3;
        for (var y = 0; y < canvas.height; y += lineHeight * 2) {
            ctx.fillRect(0, y, canvas.width, 1);
        }
    }

    function drawVignette() {
        if (!CONFIG.vignette) return;

        var gradient = ctx.createRadialGradient(
            canvas.width / 2, canvas.height / 2, canvas.height * 0.2,
            canvas.width / 2, canvas.height / 2, canvas.height * 0.9
        );
        gradient.addColorStop(0, 'rgba(0, 0, 0, 0)');
        gradient.addColorStop(1, 'rgba(0, 0, 0, 0.9)');
        ctx.fillStyle = gradient;
        ctx.fillRect(0, 0, canvas.width, canvas.height);
    }

    var NOISE_INTENSITY = 10.0;

    function drawNoise() {
        if (!CONFIG.noise) return;

        var imageData = ctx.createImageData(canvas.width, canvas.height);
        var data = imageData.data;
        var time = Date.now() * 0.001;
        var k = NOISE_INTENSITY;

        for (var i = 0; i < data.length; i += 4) {
            var idx = ((i / 4) + Math.floor(time * 10)) % noiseData.length;
            var noise = noiseData[Math.floor(idx)] * 8 * k;

            data[i] = noise * 0.2;
            data[i + 1] = noise;
            data[i + 2] = noise * 0.5;
            data[i + 3] = noise * 0.8;
        }

        ctx.putImageData(imageData, 0, 0);
    }

    function drawGlitch() {
        if (!CONFIG.glitch) return;

        var now = Date.now();

        if (!glitchActive && Math.random() < 0.002) {
            glitchActive = true;
            glitchDuration = 150 + Math.random() * 400;
            lastGlitchTime = now;
        }

        if (glitchActive) {
            if (now - lastGlitchTime > glitchDuration) {
                glitchActive = false;
                return;
            }

            var intensity = Math.random();

            for (var i = 0; i < 3; i++) {
                var y = Math.random() * canvas.height;
                var h = 2 + Math.random() * 8;
                var offset = (Math.random() - 0.5) * 20 * intensity;

                ctx.fillStyle = 'rgba(0, 255, 136, ' + (0.1 * intensity) + ')';
                ctx.fillRect(offset, y, canvas.width, h);

                ctx.fillStyle = 'rgba(255, 0, 100, ' + (0.05 * intensity) + ')';
                ctx.fillRect(-offset * 0.5, y + 1, canvas.width, h * 0.5);
            }
        }
    }

    function drawPhosphor() {
        if (!CONFIG.phosphor) return;

        var time = Date.now() * 0.022 + Math.random() * 10;
        var intensity = 0.05 + Math.sin(time) * 0.01;

        ctx.fillStyle = 'rgba(0, 255, 136, ' + intensity + ')';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
    }

    function drawFlicker() {
        if (!CONFIG.flicker) return;

        if (Math.random() < 0.02) {
            canvas.style.opacity = (0.35 + Math.random() * 0.1).toString();
        } else {
            canvas.style.opacity = '0.4';
        }
    }

    function drawMouseGlow() {
        if (!CONFIG.mouseglow) return;

        mouseX += (targetMouseX - mouseX) * 0.1;
        mouseY += (targetMouseY - mouseY) * 0.1;

        var gradient = ctx.createRadialGradient(mouseX, mouseY, 0, mouseX, mouseY, 150);
        gradient.addColorStop(0, 'rgba(0, 255, 136, 0.15)');
        gradient.addColorStop(0.3, 'rgba(0, 255, 136, 0.08)');
        gradient.addColorStop(0.6, 'rgba(0, 200, 100, 0.03)');
        gradient.addColorStop(1, 'rgba(0, 0, 0, 0)');
        ctx.fillStyle = gradient;
        ctx.fillRect(0, 0, canvas.width, canvas.height);

        ctx.beginPath();
        ctx.arc(mouseX, mouseY, 3, 0, Math.PI * 2);
        ctx.fillStyle = 'rgba(0, 255, 136, 0.8)';
        ctx.fill();

        ctx.beginPath();
        ctx.arc(mouseX, mouseY, 8 + Math.sin(Date.now() * 0.01) * 2, 0, Math.PI * 2);
        ctx.strokeStyle = 'rgba(0, 255, 136, 0.4)';
        ctx.lineWidth = 1;
        ctx.stroke();

        for (var i = ripples.length - 1; i >= 0; i--) {
            var r = ripples[i];
            r.radius += 4;
            r.opacity -= 0.02;

            if (r.opacity <= 0) {
                ripples.splice(i, 1);
                continue;
            }

            ctx.beginPath();
            ctx.arc(r.x, r.y, r.radius, 0, Math.PI * 2);
            ctx.strokeStyle = 'rgba(0, 255, 136, ' + r.opacity + ')';
            ctx.lineWidth = 2;
            ctx.stroke();
        }
    }

    function initMouseTracking() {
        document.addEventListener('mousemove', function(e) {
            targetMouseX = e.clientX;
            targetMouseY = e.clientY;
        });

        document.addEventListener('click', function(e) {
            if (!CONFIG.mouseglow) return;
            ripples.push({
                x: e.clientX,
                y: e.clientY,
                radius: 10,
                opacity: 0.6
            });
        });
    }

    function render() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);

        drawNoise();
        drawScanlines();
        drawPhosphor();
        drawGlitch();
        drawVignette();
        drawFlicker();
        drawMouseGlow();

        animationId = requestAnimationFrame(render);
    }

    function addTextGlow() {
        var style = document.createElement('style');
        style.textContent = [
            '.post-glow { animation: text-pulse 4s ease-in-out infinite; }',
            '@keyframes text-pulse {',
            '  0%, 100% { filter: drop-shadow(0 0 1px rgba(0,255,136,0.3)); }',
            '  50% { filter: drop-shadow(0 0 3px rgba(0,255,136,0.5)); }',
            '}',
            '.glitch-text {',
            '  position: relative;',
            '  z-index: 1;',
            '}',
            '.glitch-text::before, .glitch-text::after {',
            '  content: attr(data-text);',
            '  position: absolute;',
            '  top: 0; left: 0;',
            '  width: 100%; height: 100%;',
            '  opacity: 0;',
            '  pointer-events: none !important;',
            '  user-select: none;',
            '  z-index: -1;',
            '}',
            '.glitch-text:hover::before {',
            '  animation: glitch-1 0.3s infinite;',
            '  color: #0ff;',
            '  opacity: 0.6;',
            '}',
            '.glitch-text:hover::after {',
            '  animation: glitch-2 0.3s infinite;',
            '  color: #f0f;',
            '  opacity: 0.6;',
            '}',
            '@keyframes glitch-1 {',
            '  0% { transform: translate(0); }',
            '  20% { transform: translate(-2px, 1px); }',
            '  40% { transform: translate(2px, -1px); }',
            '  60% { transform: translate(-1px, 2px); }',
            '  80% { transform: translate(1px, -2px); }',
            '  100% { transform: translate(0); }',
            '}',
            '@keyframes glitch-2 {',
            '  0% { transform: translate(0); }',
            '  20% { transform: translate(2px, -1px); }',
            '  40% { transform: translate(-2px, 1px); }',
            '  60% { transform: translate(1px, -2px); }',
            '  80% { transform: translate(-1px, 2px); }',
            '  100% { transform: translate(0); }',
            '}'
        ].join('\n');
        document.head.appendChild(style);
    }

    function applyGlitchToTitles() {
        var titles = document.querySelectorAll('h1, .logo, .boards__title');
        titles.forEach(function(el) {
            if (!el.classList.contains('glitch-text')) {
                el.classList.add('glitch-text');
                el.setAttribute('data-text', el.textContent);
            }
        });
    }

    function addBootSequence() {
        if (sessionStorage.getItem('crt-booted')) return;

        var overlay = document.createElement('div');
        overlay.id = 'boot-overlay';
        overlay.style.cssText =
            'position:fixed;top:0;left:0;width:100%;height:100%;' +
            'background:#0c0c0c;z-index:999999;display:flex;' +
            'align-items:center;justify-content:center;flex-direction:column;' +
            'font-family:"Courier New",monospace;color:#00ff88;';

        var lines = [
            'SYSTEM BOOT v1.0.3',
            '==================',
            '',
            'Initializing display...',
            'Loading phosphor matrix...',
            'Calibrating electron gun...',
            'Checking memory: 640K OK',
            '',
            'READY.'
        ];

        var terminal = document.createElement('div');
        terminal.style.cssText = 'text-align:left;font-size:14px;line-height:1.6;';
        overlay.appendChild(terminal);

        document.body.appendChild(overlay);

        var cursor = document.createElement('span');
        cursor.className = 'cursor';
        cursor.textContent = '_';

        var currentSpan = document.createElement('span');
        terminal.appendChild(currentSpan);
        terminal.appendChild(cursor);

        var lineIndex = 0;
        var charIndex = 0;
        var lastTime = 0;
        var currentDelay = 0;

        function getDelay() {
            var base = 20 + Math.random() * 30;
            if (Math.random() < 0.08) base += 80 + Math.random() * 120;
            return base;
        }

        function typeChar(timestamp) {
            if (!lastTime) {
                lastTime = timestamp;
                currentDelay = getDelay();
            }

            if (lineIndex >= lines.length) {
                overlay.style.transition = 'opacity 0.3s';
                overlay.style.opacity = '0';
                setTimeout(function() {
                    overlay.remove();
                    sessionStorage.setItem('crt-booted', '1');
                }, 300);
                return;
            }

            var line = lines[lineIndex];

            if (timestamp - lastTime >= currentDelay) {
                if (charIndex < line.length) {
                    currentSpan.textContent += line[charIndex];
                    charIndex++;
                    currentDelay = getDelay();
                } else {
                    terminal.insertBefore(document.createElement('br'), cursor);
                    currentSpan = document.createElement('span');
                    terminal.insertBefore(currentSpan, cursor);
                    lineIndex++;
                    charIndex = 0;
                    currentDelay = 60 + Math.random() * 80;
                }
                lastTime = timestamp;
            }

            requestAnimationFrame(typeChar);
        }

        var cursorStyle = document.createElement('style');
        cursorStyle.textContent = '.cursor { animation: blink 0.5s infinite; } @keyframes blink { 0%,50% { opacity:1; } 51%,100% { opacity:0; } }';
        document.head.appendChild(cursorStyle);

        requestAnimationFrame(typeChar);
    }

    function createControlPanel() {
        var panel = document.createElement('div');
        panel.id = 'crt-controls';
        panel.innerHTML = [
            '<div style="font-size:10px;margin-bottom:5px;color:#00ff88;">CRT FX</div>',
            '<label><input type="checkbox" data-fx="scanlines"> Scanlines</label>',
            '<label><input type="checkbox" data-fx="noise"> Noise</label>',
            '<label><input type="checkbox" data-fx="glitch"> Glitch</label>',
            '<label><input type="checkbox" data-fx="phosphor"> Phosphor</label>',
            '<label><input type="checkbox" data-fx="vignette"> Vignette</label>',
            '<label><input type="checkbox" data-fx="flicker"> Flicker</label>',
            '<label><input type="checkbox" data-fx="mouseglow"> Mouse Glow</label>',
            '<button id="crt-toggle" style="margin-top:5px;width:100%;">OFF</button>'
        ].join('');

        panel.style.cssText =
            'position:fixed;bottom:10px;right:10px;background:#141414;' +
            'border:2px solid #2a2a2a;padding:10px;z-index:100000;' +
            'font-family:"Courier New",monospace;font-size:11px;color:#a8e6cf;' +
            'display:none;';

        document.body.appendChild(panel);

        panel.querySelectorAll('label').forEach(function(label) {
            label.style.cssText = 'display:block;margin:3px 0;cursor:pointer;';
        });

        panel.querySelectorAll('input').forEach(function(input) {
            input.checked = CONFIG[input.dataset.fx];
            input.addEventListener('change', function() {
                CONFIG[this.dataset.fx] = this.checked;
                saveConfig();
                updateCanvasVisibility();
            });
        });

        var toggleBtn = panel.querySelector('#crt-toggle');
        toggleBtn.textContent = 'ALL ON';
        toggleBtn.style.cssText =
            'background:#141414;border:1px solid #00ff88;color:#00ff88;' +
            'padding:3px;cursor:pointer;font-family:inherit;font-size:10px;';

        toggleBtn.addEventListener('click', function() {
            var inputs = panel.querySelectorAll('input[type="checkbox"]');
            var allOn = Array.from(inputs).every(function(i) { return i.checked; });
            inputs.forEach(function(input) {
                input.checked = !allOn;
                CONFIG[input.dataset.fx] = !allOn;
            });
            this.textContent = allOn ? 'ALL ON' : 'ALL OFF';
            saveConfig();
            updateCanvasVisibility();
        });

        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.shiftKey && e.key === 'C') {
                panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
            }
        });
    }

    function createHint() {
        var hint = document.createElement('div');
        hint.id = 'crt-hint';
        hint.innerHTML = 'CTRL+SHIFT+C<br><span style="font-size:8px;opacity:0.6;">CRT SETTINGS</span>';
        hint.style.cssText =
            'position:fixed;right:0;top:50%;transform:translateY(-50%) rotate(-90deg);' +
            'transform-origin:right center;' +
            'background:#141414;border:1px solid #2a2a2a;border-right:none;' +
            'padding:8px 12px;z-index:99998;' +
            'font-family:"Courier New",monospace;font-size:9px;color:#00ff88;' +
            'letter-spacing:2px;cursor:pointer;opacity:0.4;transition:opacity 0.2s;' +
            'text-align:center;';

        hint.addEventListener('mouseenter', function() {
            this.style.opacity = '0.9';
        });
        hint.addEventListener('mouseleave', function() {
            this.style.opacity = '0.4';
        });
        hint.addEventListener('click', function() {
            var panel = document.getElementById('crt-controls');
            if (panel) {
                panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
            }
        });

        document.body.appendChild(hint);
    }

    function init() {
        // addBootSequence();
        createCanvas();
        initMouseTracking();
        addTextGlow();
        applyGlitchToTitles();
        createControlPanel();
        createHint();
        updateCanvasVisibility();

        setTimeout(function() {
            render();
        }, 100);

        if (typeof $ !== 'undefined') {
            $(document).on('new_post', function() {
                applyGlitchToTitles();
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
