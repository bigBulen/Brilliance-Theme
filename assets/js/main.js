/**
 * Mimosa Theme - main.js
 * 主题前端交互逻辑
 */

(function($) {
    'use strict';

    // ── 主题切换（暗色/浅色）
    function initThemeToggle() {
        const $btn = $('.js-theme-toggle');
        const $html = $('html');

        $btn.on('click', function() {
            const current = $html.attr('data-theme');
            const next = current === 'dark' ? 'light' : 'dark';
            $html.attr('data-theme', next);
            localStorage.setItem('mimosa-theme', next);
            updateBanner();
        });
    }

    // ── Banner 背景和遮罩更新
    function updateBanner() {
        const $banner = $('.js-banner');
        if (!$banner.length) return;

        const theme = $('html').attr('data-theme');
        const bgUrl = theme === 'dark'
            ? $banner.data('bg-dark')
            : $banner.data('bg-light');
        const overlayAlpha = theme === 'dark'
            ? $banner.data('overlay-dark')
            : $banner.data('overlay-light');

        const $bg = $banner.find('.js-banner-bg');
        const $overlay = $banner.find('.js-banner-overlay');

        if (bgUrl && $bg.length) {
            $bg.css('background-image', 'url(' + bgUrl + ')');
        }

        if (overlayAlpha !== undefined && $overlay.length) {
            var alpha = parseFloat(overlayAlpha);
            if (!isNaN(alpha)) {
                var overlayRgb = theme === 'dark' ? '0,0,0' : '255,255,255';
                $overlay.css('background', 'rgba(' + overlayRgb + ',' + alpha + ')');
            }
        }
    }

    // ── 搜索层开关
    function initSearchOverlay() {
        const $overlay = $('.js-search-overlay');
        const $openBtn = $('.js-search-toggle');
        const $closeBtn = $('.js-search-close');
        const $input = $('.js-search-input');

        $openBtn.on('click', function() {
            $overlay.addClass('is-open').attr('aria-hidden', 'false');
            setTimeout(function() { $input.focus(); }, 100);
        });

        $closeBtn.on('click', function() {
            $overlay.removeClass('is-open').attr('aria-hidden', 'true');
        });

        $overlay.on('click', function(e) {
            if ($(e.target).is($overlay)) {
                $overlay.removeClass('is-open').attr('aria-hidden', 'true');
            }
        });

        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' && $overlay.hasClass('is-open')) {
                $overlay.removeClass('is-open').attr('aria-hidden', 'true');
            }
        });
    }

    // ── 多级菜单
    function initMenus() {
        const $mobileButton = $('.js-mobile-menu-toggle');
        const $mobileMenu = $('.js-mobile-menu');
        const $desktopMenu = $('.site-header__nav-list');

        function closeMobileMenu() {
            $mobileMenu.removeClass('is-open').attr('aria-hidden', 'true');
            $mobileButton.removeClass('is-open').attr('aria-expanded', 'false');
            $mobileMenu.find('.menu-item-has-children').removeClass('is-open').children('.sub-menu').stop(true, true).slideUp(0);
        }

        function closeDesktopMenus() {
            $desktopMenu.find('.menu-item-has-children').removeClass('is-open');
        }

        $mobileButton.on('click', function(event) {
            event.stopPropagation();
            if ($mobileMenu.hasClass('is-open')) {
                closeMobileMenu();
            } else {
                $mobileMenu.addClass('is-open').attr('aria-hidden', 'false');
                $mobileButton.addClass('is-open').attr('aria-expanded', 'true');
            }
        });

        $('.site-header__nav-list .menu-item-has-children > a').on('click', function(event) {
            event.preventDefault();
            event.stopPropagation();
            const $item = $(this).parent();
            const willOpen = !$item.hasClass('is-open');
            $item.siblings('.menu-item-has-children').removeClass('is-open');
            $item.toggleClass('is-open', willOpen);
        });

        $('.site-header__mobile-nav-list .menu-item-has-children > a').on('click', function(event) {
            event.preventDefault();
            event.stopPropagation();
            const $item = $(this).parent();
            const $subMenu = $item.children('.sub-menu');
            const willOpen = !$item.hasClass('is-open');

            $item.siblings('.menu-item-has-children').removeClass('is-open').children('.sub-menu').stop(true, true).slideUp(220);
            $item.toggleClass('is-open', willOpen);
            $subMenu.stop(true, true).slideToggle(220);
        });

        $(document).on('click', function(event) {
            if (!$(event.target).closest('.site-header__nav, .js-mobile-menu, .js-mobile-menu-toggle').length) {
                closeDesktopMenus();
                closeMobileMenu();
            }
        });

        $(document).on('keydown', function(event) {
            if (event.key === 'Escape') {
                closeDesktopMenus();
                closeMobileMenu();
            }
        });
    }

    // ── Banner 滚动提示
    function initBanner() {
        const $banner = $('.js-banner');
        const $scrollBtn = $('.js-banner-scroll');

        updateBanner();

        $scrollBtn.on('click', function() {
            const bannerBottom = $banner[0] ? $banner[0].getBoundingClientRect().bottom + window.scrollY : 0;
            window.scrollTo({ top: Math.round(bannerBottom), behavior: 'smooth' });
        });
    }

    // ── 说说折叠
    function initShuoshuoFold() {
        $('.js-shuoshuo-fold-content').each(function() {
            const $content = $(this);
            const threshold = parseInt($content.data('fold-threshold')) || 250;
            const textLength = $content.text().trim().length;
            
            if (textLength > threshold) {
                $content.addClass('is-folded');
                
                const $toggle = $('<button class="shuoshuo-fold-toggle" type="button">展开</button>');
                $content.after($toggle);
                
                $toggle.on('click', function() {
                    if ($content.hasClass('is-folded')) {
                        $content.removeClass('is-folded');
                        $toggle.text('收起');
                    } else {
                        $content.addClass('is-folded');
                        $toggle.text('展开');
                    }
                });
            }
        });
    }

    // ── 平滑滚动（修复中文 hash 选择器报错）
    function initSmoothScroll() {
        $('a[href*="#"]').on('click', function(e) {
            const href = $(this).attr('href');
            if (!href || href === '#') return;
            
            const parts = href.split('#');
            if (parts.length !== 2) return;
            
            const hash = parts[1];
            if (!hash) return;

            // 仅处理「同页锚点」：跨页链接（如评论分页 /post/?cpage=2#comments）放行给浏览器正常跳转
            if (parts[0] !== '') {
                try {
                    const linkURL = new URL(parts[0], window.location.href);
                    if (linkURL.pathname !== window.location.pathname ||
                        linkURL.search !== window.location.search) {
                        return;
                    }
                } catch (err) {
                    return;
                }
            }

            try {
                const $target = $('[id="' + decodeURIComponent(hash) + '"]');
                if ($target.length) {
                    e.preventDefault();
                    $('html, body').animate({
                        scrollTop: $target.offset().top - 80
                    }, 600);
                }
            } catch (err) {
                // 忽略选择器错误
            }
        });
    }

    // ── ACGN 折叠展开
    function initMediaCollapse() {
        $(document).on('click', '.js-media-expand-btn', function() {
            const group = $(this).data('group');
            $('.js-media-collapsed-item[data-group="' + group + '"]').addClass('show');
            $(this).closest('.js-media-collapse-bar').fadeOut(300);
        });
    }

    // ── 文章目录高亮（滚动时）
    function initTocHighlight() {
        const $toc = $('.js-toc');
        if (!$toc.length) return;

        const $links = $toc.find('.js-toc-link');
        const headings = [];

        $links.each(function() {
            const href = $(this).attr('href');
            if (href && href.startsWith('#')) {
                try {
                    const $target = $(href);
                    if ($target.length) {
                        headings.push({ link: this, target: $target[0] });
                    }
                } catch (e) {
                    // 忽略无效选择器
                }
            }
        });

        if (headings.length === 0) return;

        let ticking = false;

        function updateActive() {
            const scrollTop = $(window).scrollTop();
            const offset = 100;
            let activeIndex = -1;

            for (let i = 0; i < headings.length; i++) {
                const top = $(headings[i].target).offset().top;
                if (scrollTop + offset >= top) {
                    activeIndex = i;
                } else {
                    break;
                }
            }

            $links.removeClass('is-active');
            if (activeIndex >= 0) {
                $(headings[activeIndex].link).addClass('is-active');
            }

            ticking = false;
        }

        $(window).on('scroll', function() {
            if (!ticking) {
                window.requestAnimationFrame(updateActive);
                ticking = true;
            }
        });

        updateActive();
    }

    // ── 目录折叠按钮
    function initTocToggle() {
        $('.js-toc-toggle').on('click', function() {
            const $toc = $('.js-toc');
            const $nav = $('.js-toc-nav');
            $toc.toggleClass('is-collapsed');
            $nav.slideToggle(300);
        });
    }

    // ── 图片懒加载简易版（Intersection Observer）
    function initLazyLoad() {
        if (!('IntersectionObserver' in window)) return;

        const images = document.querySelectorAll('img[loading="lazy"]');
        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    if (img.dataset.src) {
                        img.src = img.dataset.src;
                        delete img.dataset.src;
                    }
                    observer.unobserve(img);
                }
            });
        });

        images.forEach(function(img) { observer.observe(img); });
    }

    // ── ACGN 列表状态筛选
    function initAcgnFilters() {
        $('.acgn-list').each(function() {
            const $list = $(this);
            const $buttons = $list.find('.acgn-list__filters button');
            const $cards = $list.find('.acgn-list-card');

            $buttons.on('click', function() {
                const status = $(this).data('status');
                $buttons.removeClass('is-active');
                $(this).addClass('is-active');

                $cards.each(function() {
                    const visible = status === 'all' || $(this).data('status') === status;
                    this.hidden = !visible;
                });
            });
        });
    }

    // ── 全局提示条
    window.MimosaToast = function(message, type, duration) {
        type = type || 'info';
        duration = duration === undefined ? 3600 : duration;
        var $stack = $('.mimosa-toast-stack');
        if (!$stack.length) {
            $stack = $('<div class="mimosa-toast-stack" aria-live="polite" aria-atomic="true"></div>');
            $('body').append($stack);
        }
        var $toast = $('<div class="mimosa-toast mimosa-toast--' + type + '" role="status"><div class="mimosa-toast__message"></div><button class="mimosa-toast__close" type="button" aria-label="关闭">×</button></div>');
        $toast.find('.mimosa-toast__message').text(message);
        $toast.on('click', '.mimosa-toast__close', function() { $toast.remove(); });
        $stack.append($toast);
        requestAnimationFrame(function() { $toast.addClass('is-visible'); });
        if (duration > 0) {
            setTimeout(function() {
                $toast.removeClass('is-visible');
                setTimeout(function() { $toast.remove(); }, 200);
            }, duration);
        }
    };

    // ── 动态印章背景
    function initStamps() {
        if (!window.mimosaConfig || !mimosaConfig.stamps) return;
        var urls = mimosaConfig.stamps.urls || [];
        var count = mimosaConfig.stamps.count || 6;   // 每个屏幕区域可见数量
        var size = mimosaConfig.stamps.size || 280;
        var opacity = mimosaConfig.stamps.opacity !== undefined ? mimosaConfig.stamps.opacity : 0.04;
        var PARALLAX = 0.4;                            // 视差位移系数（<1，比页面慢）

        if (!urls.length || count <= 0) return;

        // 移动端：减少数量、分散分布
        var isMobile = window.innerWidth < 768;
        if (isMobile) count = Math.max(3, Math.round(count / 2));

        // 贴纸只允许出现在页面内容区（.site-page）内，不覆盖 Banner 区域
        var pageEl = document.querySelector('.site-page');
        var $layer = $('#mimosa-stamp-layer');
        if (!$layer.length) {
            $layer = $('<div id="mimosa-stamp-layer"></div>');
            // 放进 .site-page 内，使其位于页面内容之下（卡片不透明度可作用于贴纸）
            ($(pageEl).length ? $(pageEl) : $('body')).prepend($layer);
        }

        var stamps = [];
        function randomUrl() { return urls[Math.floor(Math.random() * urls.length)]; }

        function buildStamp(i) {
            var st = {
                baseY: (i + Math.random()) / count,   // 0..1，一屏内均匀分层
                leftFrac: 4 + (((i * 47) % 82) + Math.random() * 8), // 交错铺开
                prevRaw: null,
                url: randomUrl(),
                el: document.createElement('div')
            };
            st.el.className = 'mimosa-stamp';
            var img = document.createElement('img');
            img.src = st.url;
            img.alt = '';
            img.loading = 'lazy';
            st.el.appendChild(img);
            st.el.style.width = size + 'px';
            st.el.style.opacity = opacity;
            st.el.style.left = st.leftFrac + '%';
            st.el.style.setProperty('--stamp-rot', (Math.round(Math.random() * 30 - 15)) + 'deg');
            st.el.style.setProperty('--stamp-dur', (8 + Math.random() * 8) + 's');
            $layer.append(st.el);
            stamps.push(st);
            return st;
        }

        for (var i = 0; i < count; i++) buildStamp(i);

        // 防重叠：两两斥力松弛，减少贴纸遮挡
        function resolveOverlaps() {
            var vpW = window.innerWidth, vpH = window.innerHeight;
            var minGap = 14;
            var needY = (size + minGap) / vpH;
            var needX = (size + minGap) / vpW * 100;
            for (var iter = 0; iter < 45; iter++) {
                for (var a = 0; a < stamps.length; a++) {
                    for (var b = a + 1; b < stamps.length; b++) {
                        var sa = stamps[a], sb = stamps[b];
                        var dy = Math.abs(sa.baseY - sb.baseY);
                        var dyc = Math.min(dy, 1 - dy);
                        var dx = Math.abs(sa.leftFrac - sb.leftFrac);
                        if (dyc < needY && dx < needX) {
                            var push = (needY - dyc) * 0.5 + 0.002;
                            var da = (sa.baseY - sb.baseY + 1) % 1;
                            var dir = da <= 0.5 ? 1 : -1;
                            sa.baseY = ((sa.baseY + push * dir) % 1 + 1) % 1;
                            sb.baseY = ((sb.baseY - push * dir) % 1 + 1) % 1;
                            var nud = 0.3;
                            if (sa.leftFrac <= sb.leftFrac) { sa.leftFrac -= nud; sb.leftFrac += nud; }
                            else { sa.leftFrac += nud; sb.leftFrac -= nud; }
                        }
                    }
                }
                for (var k = 0; k < stamps.length; k++) {
                    stamps[k].leftFrac = Math.max(2, Math.min(94, stamps[k].leftFrac));
                }
            }
        }
        resolveOverlaps();

        var ticking = false;
        function refresh() {
            var band = window.innerHeight;
            var scrollY = window.scrollY;

            // 把固定层裁到 .site-page 顶边以下：Banner 区域内的贴纸被裁掉，
            // 只在页面内容区显示（页面内容上升时贴纸逐渐露出）。
            if (pageEl && $layer[0]) {
                var pageTop = pageEl.getBoundingClientRect().top;
                var clipTop = Math.max(0, Math.min(band, pageTop));
                $layer[0].style.clipPath = 'inset(' + clipTop + 'px 0px 0px 0px)';
            }

            for (var j = 0; j < stamps.length; j++) {
                var st = stamps[j];
                var raw = st.baseY * band - scrollY * PARALLAX;
                var y = ((raw % band) + band) % band;
                st.el.style.top = y + 'px';
                // 换行（从顶部逸出）时换图/角度，避免重复感
                if (st.prevRaw !== null && st.prevRaw >= 0 && raw < -size) {
                    st.url = randomUrl();
                    st.el.querySelector('img').src = st.url;
                    st.el.style.setProperty('--stamp-rot', (Math.round(Math.random() * 30 - 15)) + 'deg');
                }
                st.prevRaw = raw;
            }
        }

        $(window).on('scroll resize', function () {
            if (!ticking) {
                ticking = true;
                window.requestAnimationFrame(function () { refresh(); ticking = false; });
            }
        });
        refresh();
    }

    // ── 页眉滚动悬浮（仅桌面端）
    function initHeaderScroll() {
        var $header = $('.js-site-header');
        if (!$header.length) return;
        var ticking = false;
        function update() {
            if (window.innerWidth < 769) {
                $header.removeClass('is-scrolled');
                ticking = false;
                return;
            }
            $header.toggleClass('is-scrolled', window.scrollY > 12);
            ticking = false;
        }
        $(window).on('scroll', function () {
            if (!ticking) { ticking = true; window.requestAnimationFrame(update); }
        });
        $(window).on('resize', update);
        update();
    }

    // ── 管理员侧边快捷栏
    function initAdminFab() {
        var $fab = $('.js-mimosa-admin-fab');
        if (!$fab.length) return;
        var $panel = $fab.next('.mimosa-admin-panel');
        $fab.on('click', function () {
            var open = $panel.hasClass('is-open');
            $panel.toggleClass('is-open', !open);
            $fab.toggleClass('is-active', !open).attr('aria-expanded', String(!open));
        });
        $(document).on('click', function (e) {
            if (!$(e.target).closest('.mimosa-admin-fab, .mimosa-admin-panel').length) {
                $panel.removeClass('is-open');
                $fab.removeClass('is-active').attr('aria-expanded', 'false');
            }
        });
    }

    // ── 初始化
    $(document).ready(function() {
        initThemeToggle();
        initHeaderScroll();
        updateBanner();
        initSearchOverlay();
        initMenus();
        initBanner();
        initShuoshuoFold();
        initSmoothScroll();
        initMediaCollapse();
        initTocHighlight();
        initTocToggle();
        initLazyLoad();
        initAcgnFilters();
        initStamps();
        initAdminFab();
    });

})(jQuery);
