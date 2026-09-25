/**
 * Brilliance Mermaid 渲染
 *
 * 识别 `code.language-mermaid`（Gutenberg 代码块语言选 mermaid / Markdown ```mermaid 围栏），
 * 懒加载 mermaid（jsDelivr），前端渲染为 SVG。支持暗/亮模式配色自动切换。
 */
(function () {
    'use strict';

    var loaded = false;
    var loading = false;
    var renderTimer = null;

    function currentTheme() {
        return document.documentElement.getAttribute('data-theme') === 'light' ? 'default' : 'dark';
    }

    function hasMermaidBlock() {
        return !!document.querySelector('pre code.language-mermaid');
    }

    function loadMermaid(cb) {
        if (window.mermaid) { cb(); return; }
        if (loading) return;
        loading = true;
        var s = document.createElement('script');
        s.src = 'https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.min.js';
        s.onload = function () { loading = false; if (window.mermaid) cb(); };
        s.onerror = function () { loading = false; };
        document.head.appendChild(s);
    }

    function initMermaid() {
        if (!window.mermaid) return;
        window.mermaid.initialize({
            startOnLoad: false,
            theme: currentTheme(),
            securityLevel: 'loose',
            fontFamily: '"PingFang SC","Microsoft YaHei",system-ui,sans-serif'
        });
        renderAll();
    }

    function renderAll() {
        if (!window.mermaid || typeof window.mermaid.render !== 'function') return;

        // 清空旧渲染，让原代码块恢复
        Array.prototype.forEach.call(document.querySelectorAll('.mermaid-container'), function (el) {
            el.remove();
        });
        Array.prototype.forEach.call(document.querySelectorAll('pre[data-mermaid-source]'), function (pre) {
            pre.style.display = '';
        });

        var nodes = Array.prototype.slice.call(document.querySelectorAll('pre code.language-mermaid'));
        var seq = 0;
        nodes.forEach(function (code) {
            var pre = code.parentNode;
            if (!pre || pre.getAttribute('data-mermaid-rendered') === 'pending') return;
            var source = (code.textContent || '').trim();
            if (!source) return;
            pre.setAttribute('data-mermaid-source', source);
            pre.setAttribute('data-mermaid-rendered', 'pending');

            var id = 'mermaid-' + (++seq) + '-' + Date.now();
            try {
                window.mermaid.render(id, source).then(function (res) {
                    pre.removeAttribute('data-mermaid-rendered');
                    var wrap = document.createElement('div');
                    wrap.className = 'mermaid-container';
                    wrap.innerHTML = res.svg;
                    pre.parentNode.insertBefore(wrap, pre.nextSibling);
                    pre.style.display = 'none';
                }).catch(function () {
                    pre.removeAttribute('data-mermaid-rendered');
                    // 渲染失败：保留原始代码
                });
            } catch (e) {
                pre.removeAttribute('data-mermaid-rendered');
            }
        });
    }

    function boot() {
        if (!hasMermaidBlock()) return;
        loadMermaid(initMermaid);
    }

    // 主题切换时重渲染（暗/亮配色适配）
    var observer = new MutationObserver(function () {
        if (renderTimer) clearTimeout(renderTimer);
        renderTimer = setTimeout(function () {
            if (!hasMermaidBlock()) return;
            loadMermaid(initMermaid);
        }, 250);
    });
    observer.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();