/**
 * Brilliance 个性化设置面板 + 返回顶部
 *
 * 调整全局圆角、卡片透明度、主题色（预设色盘 + 自定义取色），
 * 持久化到 localStorage 与 cookie，早期（header 内联脚本）读取以避免闪屏。
 */
(function () {
    'use strict';

    var root = document.documentElement;
    var STORE = 'mimosa-personal';
    var COOKIE = 'mimosa_personal';

    var DEFAULT_RADIUS = 14;
    var PRESETS = ['#7c6af7', '#f43f5e', '#f59e0b', '#22c55e', '#06b6d4', '#3b82f6', '#e11d48', '#8b5cf6'];

    function readPrefs() {
        var prefs = {};
        try { prefs = JSON.parse(localStorage.getItem(STORE) || '{}') || {}; } catch (e) { prefs = {}; }
        if (typeof prefs.radius !== 'number') {
            var r = parseFloat(getComputedStyle(root).getPropertyValue('--radius-card'));
            prefs.radius = isNaN(r) ? DEFAULT_RADIUS : r;
        }
        if (typeof prefs.cardOpacity !== 'number') {
            var o = parseFloat(getComputedStyle(root).getPropertyValue('--card-opacity'));
            prefs.cardOpacity = isNaN(o) ? 1 : o;
        }
        return prefs;
    }

    function save(prefs) {
        try { localStorage.setItem(STORE, JSON.stringify(prefs)); } catch (e) {}
        try {
            document.cookie = COOKIE + '=' + encodeURIComponent(JSON.stringify(prefs)) + '; path=/; max-age=31536000; samesite=lax';
        } catch (e) {}
    }

    function apply(prefs) {
        root.style.setProperty('--radius-card', Math.round(prefs.radius) + 'px');
        root.style.setProperty('--radius-sm', Math.round(prefs.radius * 0.6) + 'px');
        root.style.setProperty('--card-opacity', String(Math.max(0, Math.min(1, prefs.cardOpacity))));
    }

    function applyAccent(hex) {
        root.style.setProperty('--accent', hex);
        var c = String(hex || '').replace('#', '');
        if (c.length === 3) c = c.split('').map(function (x) { return x + x; }).join('');
        if (c.length === 6) {
            root.style.setProperty('--accent-rgb', [c.slice(0, 2), c.slice(2, 4), c.slice(4, 6)].map(function (x) { return parseInt(x, 16); }).join(', '));
        }
        try { localStorage.setItem('mimosa-accent', hex); } catch (e) {}
        try { document.cookie = 'mimosa_accent=' + encodeURIComponent(hex) + '; path=/; max-age=31536000; samesite=lax'; } catch (e) {}
    }

    function el(tag, cls) {
        var node = document.createElement(tag);
        if (cls) node.className = cls;
        return node;
    }

    function currentAccent() {
        var a = getComputedStyle(root).getPropertyValue('--accent').trim();
        return a || '#7c6af7';
    }

    function build() {
        var prefs = readPrefs();

        // ── 返回顶部 ──
        var backTop = el('button', 'mimosa-back-top');
        backTop.type = 'button';
        backTop.setAttribute('aria-label', '返回顶部');
        backTop.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="18 15 12 9 6 15"/></svg>';
        document.body.appendChild(backTop);
        backTop.addEventListener('click', function () { window.scrollTo({ top: 0, behavior: 'smooth' }); });

        // ── 个性化开关 ──
        var toggle = el('button', 'mimosa-personal-toggle');
        toggle.type = 'button';
        toggle.setAttribute('aria-label', '个性化设置');
        toggle.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3.2"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 11-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 11-4 0v-.09a1.65 1.65 0 00-1-1.51 1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 11-2.83-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 110-4h.09a1.65 1.65 0 001.51-1 1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 112.83-2.83l.06.06a1.65 1.65 0 001.82.33h.01a1.65 1.65 0 001-1.51V3a2 2 0 114 0v.09a1.65 1.65 0 001 1.51h.01a1.65 1.65 0 001.82-.33l.06-.06a2 2 0 112.83 2.83l-.06.06a1.65 1.65 0 00-.33 1.82v.01a1.65 1.65 0 001.51 1H21a2 2 0 110 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>';
        document.body.appendChild(toggle);

        // ── 面板 ──
        var panel = el('div', 'mimosa-personal-panel');
        var accent = currentAccent();

        var panelInner = '';
        panelInner += '<div class="mimosa-personal-panel__head">个性化</div>';

        panelInner += '<div class="mimosa-personal-field"><label>圆角大小</label>';
        panelInner += '<input class="mimosa-personal-range js-radius" type="range" min="0" max="32" step="1" value="' + Math.round(prefs.radius) + '">';
        panelInner += '<span class="mimosa-personal-val js-radius-val">' + Math.round(prefs.radius) + 'px</span></div>';

        panelInner += '<div class="mimosa-personal-field"><label>卡片透明度</label>';
        panelInner += '<input class="mimosa-personal-range js-opacity" type="range" min="0.3" max="1" step="0.02" value="' + prefs.cardOpacity + '">';
        panelInner += '<span class="mimosa-personal-val js-opacity-val">' + Math.round(prefs.cardOpacity * 100) + '%</span></div>';

        panelInner += '<div class="mimosa-personal-field"><label>主题色</label><div class="mimosa-personal-swatches">';
        PRESETS.forEach(function (hex) {
            panelInner += '<button type="button" class="mimosa-personal-swatch" data-color="' + hex + '" style="background:' + hex + '" aria-label="' + hex + '"></button>';
        });
        panelInner += '<label class="mimosa-personal-custom" title="自定义颜色"><input class="js-accent-color" type="color" value="' + accent + '"></label>';
        panelInner += '</div></div>';
        panelInner += '<button type="button" class="mimosa-personal-reset js-reset">恢复博客预设</button>';

        panel.innerHTML = panelInner;
        document.body.appendChild(panel);

        // 事件
        toggle.addEventListener('click', function () {
            panel.classList.toggle('is-open');
            toggle.classList.toggle('is-active');
        });

        var radiusInput = panel.querySelector('.js-radius');
        var opacityInput = panel.querySelector('.js-opacity');
        radiusInput.addEventListener('input', function () {
            prefs.radius = parseFloat(radiusInput.value);
            panel.querySelector('.js-radius-val').textContent = Math.round(prefs.radius) + 'px';
            apply(prefs);
            save(prefs);
        });
        opacityInput.addEventListener('input', function () {
            prefs.cardOpacity = parseFloat(opacityInput.value);
            panel.querySelector('.js-opacity-val').textContent = Math.round(prefs.cardOpacity * 100) + '%';
            apply(prefs);
            save(prefs);
        });

        panel.querySelectorAll('.mimosa-personal-swatch').forEach(function (sw) {
            sw.addEventListener('click', function () {
                applyAccent(sw.getAttribute('data-color'));
                panel.querySelector('.js-accent-color').value = sw.getAttribute('data-color');
                markActiveSwatch(panel, sw);
            });
        });
        var colorInput = panel.querySelector('.js-accent-color');
        colorInput.addEventListener('input', function () {
            applyAccent(colorInput.value);
            markActiveSwatch(panel, null);
        });

        // 恢复博客预设
        var defaults = window.mimosaPersonalDefaults || {};
        panel.querySelector('.js-reset').addEventListener('click', function () {
            var dAccent = defaults.accent || '#7c6af7';
            var dRadius = parseFloat(defaults.radius) || 14;
            var dOpacity = defaults.cardOpacity !== undefined ? parseFloat(defaults.cardOpacity) : 1;

            applyAccent(dAccent);
            root.style.setProperty('--radius-card', Math.round(dRadius) + 'px');
            root.style.setProperty('--radius-sm', Math.round(dRadius * 0.6) + 'px');
            root.style.setProperty('--card-opacity', String(Math.max(0, Math.min(1, dOpacity))));

            try { localStorage.removeItem(STORE); localStorage.removeItem('mimosa-accent'); } catch (e) {}
            try {
                document.cookie = COOKIE + '=; path=/; max-age=0';
                document.cookie = 'mimosa_accent=; path=/; max-age=0';
            } catch (e) {}

            prefs.radius = dRadius;
            prefs.cardOpacity = dOpacity;
            radiusInput.value = dRadius;
            opacityInput.value = dOpacity;
            panel.querySelector('.js-radius-val').textContent = Math.round(dRadius) + 'px';
            panel.querySelector('.js-opacity-val').textContent = Math.round(dOpacity * 100) + '%';
            colorInput.value = dAccent;
            markActiveSwatch(panel, null);
        });

        // 回到顶部显隐
        var ticking = false;
        function onScroll() {
            if (ticking) return;
            ticking = true;
            window.requestAnimationFrame(function () {
                if (window.scrollY > 400) backTop.classList.add('is-visible');
                else backTop.classList.remove('is-visible');
                ticking = false;
            });
        }
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
    }

    function markActiveSwatch(panel, active) {
        panel.querySelectorAll('.mimosa-personal-swatch').forEach(function (s) {
            var on = active && s.getAttribute('data-color').toLowerCase() === active.getAttribute('data-color').toLowerCase();
            s.classList.toggle('is-active', on);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', build);
    } else {
        build();
    }
})();