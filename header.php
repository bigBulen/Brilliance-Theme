<!DOCTYPE html>
<html <?php language_attributes(); ?> data-theme="dark" class="<?php echo get_option('mimosa_stamp_invert', 'yes') === 'yes' ? 'mimosa-stamp-invert' : ''; ?>">
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php 
    // SEO Meta 标签
    if (function_exists('brilliance_output_seo_meta')) {
        brilliance_output_seo_meta();
    }
    wp_head(); 
    ?>
    <style id="mimosa-page-background-style">
        <?php
        $tmp_card_opacity = floatval(get_option('mimosa_card_opacity', 1));
        $tmp_card_opacity = max(0, min(1, $tmp_card_opacity));

        // 页面背景图：只设置了亮色/暗色其中一个时，两个模式复用该图
        $tmp_page_bg_dark_raw  = get_option('mimosa_page_bg_dark_image', '');
        $tmp_page_bg_light_raw = get_option('mimosa_page_bg_light_image', '');
        $tmp_page_bg_dark      = $tmp_page_bg_dark_raw !== '' ? $tmp_page_bg_dark_raw : $tmp_page_bg_light_raw;
        $tmp_page_bg_light     = $tmp_page_bg_light_raw !== '' ? $tmp_page_bg_light_raw : $tmp_page_bg_dark_raw;

        // 页面背景遮罩：套用 Banner 的遮罩透明度（暗色用黑、亮色用白）
        $tmp_overlay_dark  = max(0, min(1, floatval(get_option('mimosa_banner_overlay_dark', '0.45'))));
        $tmp_overlay_light = max(0, min(1, floatval(get_option('mimosa_banner_overlay_light', '0.25'))));
        $tmp_page_overlay_dark  = $tmp_page_bg_dark !== '' ? 'rgba(0,0,0,' . $tmp_overlay_dark . ')' : 'transparent';
        $tmp_page_overlay_light = $tmp_page_bg_light !== '' ? 'rgba(255,255,255,' . $tmp_overlay_light . ')' : 'transparent';
        ?>
        :root { --card-opacity: <?php echo esc_attr($tmp_card_opacity); ?>; --accent-rgb: <?php echo esc_attr(mimosa_hex_to_rgb(get_option('mimosa_accent_color', '#7c6af7'))); ?>; }
        [data-theme="dark"] { --page-bg-color: <?php echo esc_html(get_option('mimosa_page_bg_dark_color', '#0f0f13')); ?>; --page-bg-image: url('<?php echo esc_url($tmp_page_bg_dark); ?>'); --page-bg-overlay: <?php echo esc_attr($tmp_page_overlay_dark); ?>; }
        [data-theme="light"] { --page-bg-color: <?php echo esc_html(get_option('mimosa_page_bg_light_color', '#f5f5f8')); ?>; --page-bg-image: url('<?php echo esc_url($tmp_page_bg_light); ?>'); --page-bg-overlay: <?php echo esc_attr($tmp_page_overlay_light); ?>; }
        <?php // 设置了页面背景图片时隐藏悬浮贴纸（非 Banner 图） ?>
        <?php if ($tmp_page_bg_dark !== '') : ?>[data-theme="dark"] #mimosa-stamp-layer { display: none !important; }<?php endif; ?>
        <?php if ($tmp_page_bg_light !== '') : ?>[data-theme="light"] #mimosa-stamp-layer { display: none !important; }<?php endif; ?>
    </style>
    <script>
    // 主题色与暗/亮模式在 <head> 早期设置，避免闪烁
    (function () {
        var saved = localStorage.getItem('mimosa-theme');
        if (saved === 'light') {
            document.documentElement.setAttribute('data-theme', 'light');
        } else {
            document.documentElement.setAttribute('data-theme', 'dark');
        }
        var color = localStorage.getItem('mimosa-accent') || '<?php echo esc_js(get_option('mimosa_accent_color', '#7c6af7')); ?>';
        document.documentElement.style.setProperty('--accent', color);
        
        // 段落间距
        var spacing = '<?php echo esc_js(get_option('brilliance_paragraph_spacing', '1.5')); ?>';
        document.documentElement.style.setProperty('--paragraph-spacing', spacing + 'em');

        // 强调色 RGB（供 rgba(var(--accent-rgb), ...) 使用）
        var _c = String(color || '#7c6af7').replace('#', '');
        if (_c.length === 3) _c = _c.split('').map(function (cc) { return cc + cc; }).join('');
        if (_c.length === 6) {
            document.documentElement.style.setProperty('--accent-rgb', [_c.slice(0, 2), _c.slice(2, 4), _c.slice(4, 6)].map(function (x) { return parseInt(x, 16); }).join(', '));
        }

        // 个性化偏好（圆角 / 卡片透明度）
        var prefs = {};
        try { prefs = JSON.parse(localStorage.getItem('mimosa-personal') || '{}') || {}; } catch (e) { prefs = {}; }
        if (typeof prefs.radius !== 'number' || typeof prefs.cardOpacity !== 'number') {
            var cm = document.cookie.match(/(?:^|; )mimosa_personal=([^;]*)/);
            if (cm) { try { prefs = JSON.parse(decodeURIComponent(cm[1])) || prefs; } catch (e2) {} }
        }
        if (typeof prefs.radius === 'number' && prefs.radius >= 0 && prefs.radius <= 32) {
            document.documentElement.style.setProperty('--radius-card', Math.round(prefs.radius) + 'px');
            document.documentElement.style.setProperty('--radius-sm', Math.round(prefs.radius * 0.6) + 'px');
        }
        if (typeof prefs.cardOpacity === 'number') {
            document.documentElement.style.setProperty('--card-opacity', String(Math.max(0, Math.min(1, prefs.cardOpacity))));
        }

    })();
    </script>

</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<?php
// 首页副栏：设置开关 + 小工具区域非空时才渲染。
// 该条件同时用于：index.php 双栏、移动菜单展开区的副栏内容、容器加宽。
$mimosa_home_sidebar = (is_home() || is_front_page())
    && !get_query_var('apex_media_slug')
    && brilliance_home_sidebar_is_active();

// 详情页侧栏（文章/页面含目录时），用于加宽容器
$mimosa_detail_sidebar = is_singular(array('post', 'page'))
    && function_exists('mimosa_has_toc')
    && mimosa_has_toc();
?>

<!-- 顶部导航栏 -->
<header class="site-header js-site-header" role="banner">
    <div class="site-header__inner">
        <a class="site-header__logo" href="<?php echo esc_url(home_url('/')); ?>">
            <?php
            $logo_id = get_option('mimosa_logo_attachment_id');
            if ($logo_id) {
                echo wp_get_attachment_image($logo_id, 'full', false, array('class' => 'site-header__logo-img', 'alt' => get_bloginfo('name')));
            } else {
                echo '<span class="site-header__logo-text">' . esc_html(get_bloginfo('name')) . '</span>';
            }
            ?>
        </a>

        <nav class="site-header__nav" role="navigation" aria-label="主导航">
            <?php
            wp_nav_menu(array(
                'theme_location' => 'primary',
                'container'      => false,
                'menu_class'     => 'site-header__nav-list',
                'fallback_cb'    => 'mimosa_default_nav_menu',
            ));
            ?>
        </nav>

        <div class="site-header__actions">
            <?php if (get_option('mimosa_search_enable', 'yes') === 'yes'): ?>
            <button class="site-header__btn js-search-toggle" aria-label="搜索" type="button">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
                </svg>
            </button>
            <?php endif; ?>

            <button class="site-header__btn js-theme-toggle" aria-label="切换主题" type="button">
                <svg class="icon-moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/>
                </svg>
                <svg class="icon-sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="5"/>
                    <line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/>
                    <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
                    <line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/>
                    <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
                </svg>
            </button>

            <button class="site-header__btn site-header__mobile-menu-btn js-mobile-menu-toggle" aria-label="打开菜单" type="button">
                <span></span><span></span><span></span>
            </button>
        </div>
    </div>

    <!-- 移动端菜单 -->
    <div class="site-header__mobile-menu js-mobile-menu" aria-hidden="true">
        <h1>菜单栏</h1>
        <?php
        wp_nav_menu(array(
            'theme_location' => 'primary',
            'container'      => false,
            'menu_class'     => 'site-header__mobile-nav-list',
            'fallback_cb'    => 'mimosa_default_nav_menu',
        ));
        ?>

        <?php if ($mimosa_home_sidebar): ?>
        <!-- 首页副栏小工具内容（移动端在菜单展开区显示，桌面端由 CSS 隐藏） -->
        <div class="mobile-menu-widgets">
            <?php brilliance_render_site_notice(); ?>
            <?php dynamic_sidebar('home-sidebar'); ?>
        </div>
        <?php endif; ?>

        <?php if ($mimosa_detail_sidebar): ?>
        <!-- 详情页目录（移动端在菜单展开区显示，桌面端由 CSS 隐藏） -->
        <div class="mobile-menu-toc">
            <?php brilliance_render_detail_toc_card(true); ?>
        </div>
        <?php endif; ?>
    </div>
</header>

<!-- 搜索层 -->
<?php if (get_option('mimosa_search_enable', 'yes') === 'yes'): ?>
<div class="search-overlay js-search-overlay" aria-hidden="true" role="dialog" aria-label="搜索">
    <div class="search-overlay__inner">
        <form class="search-overlay__form" method="get" action="<?php echo esc_url(home_url('/')); ?>" role="search">
            <input class="search-overlay__input js-search-input"
                   type="search" name="s"
                   placeholder="搜索文章、说说…"
                   value="<?php echo esc_attr(get_search_query()); ?>"
                   autocomplete="off" aria-label="搜索">
            <button class="search-overlay__submit" type="submit" aria-label="提交搜索">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
                </svg>
            </button>
        </form>
        <button class="search-overlay__close js-search-close" type="button" aria-label="关闭搜索">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
        </button>
    </div>
</div>
<?php endif; ?>

<?php
// Banner 区域：仅首页显示（ACGN 详情页虽被 is_home() 命中，但要排除）
if ((is_home() || is_front_page()) && !get_query_var('apex_media_slug')):
    // Banner 背景图：只设置了亮色/暗色其中一个时，两个模式复用该图
    $bg_light_raw = get_option('mimosa_banner_bg_light', '');
    $bg_dark_raw  = get_option('mimosa_banner_bg_dark', '');
    $bg_light = $bg_light_raw !== '' ? $bg_light_raw : $bg_dark_raw;
    $bg_dark  = $bg_dark_raw !== '' ? $bg_dark_raw : $bg_light_raw;
    $avatar   = get_option('mimosa_avatar_url', '');
    
    // 赛博朋克双语
    $title_zh = get_option('mimosa_banner_title_zh', '') ?: get_bloginfo('name');
    $title_en = get_option('mimosa_banner_title_en', 'Welcome');
    $subtitle_zh = get_option('mimosa_banner_subtitle_zh', '') ?: get_bloginfo('description');
    $subtitle_en = get_option('mimosa_banner_subtitle_en', 'Blog');
    
    $intro        = get_option('mimosa_intro', '');
    $stamps_raw   = get_option('mimosa_banner_stamps', '');
    $stamps       = array_filter(array_map('trim', explode("\n", $stamps_raw)));
    $overlay_dark  = get_option('mimosa_banner_overlay_dark', '0.45');
    $overlay_light = get_option('mimosa_banner_overlay_light', '0.25');
    $ambient_color = get_option('mimosa_banner_ambient_color', '#79c9a0');
    $ambient_enabled = get_option('mimosa_banner_ambient_enable', 'yes') === 'yes';
    $ambient_mode  = get_option('mimosa_banner_ambient_mode', 'static');
    $banner_has_image = $bg_dark || $bg_light;
    $wind_enabled  = get_option('mimosa_banner_wind_enable', 'yes') === 'yes';
    $wind_count    = max(1, min(6, (int) get_option('mimosa_banner_wind_count', 3)));
?>
<section class="site-banner js-banner<?php echo $ambient_enabled ? ' site-banner--ambient-' . esc_attr($ambient_mode === 'dynamic' ? 'dynamic' : 'static') : ''; ?>"
         style="--banner-ambient: <?php echo esc_attr($ambient_color); ?>;"
         data-bg-dark="<?php echo esc_attr($bg_dark); ?>"
         data-bg-light="<?php echo esc_attr($bg_light); ?>"
         data-overlay-dark="<?php echo esc_attr($overlay_dark); ?>"
         data-overlay-light="<?php echo esc_attr($overlay_light); ?>"
         aria-label="站点欢迎区">
    
    <!-- 纯色背景 -->
    <div class="site-banner__solid-bg" aria-hidden="true"></div>
    
    <!-- 渐变光晕与噪点背景：启用时优先于 Banner 背景图 -->
    <?php if ($ambient_enabled) : ?>
    <div class="site-banner__ambient" aria-hidden="true">
        <span class="site-banner__ambient-orb site-banner__ambient-orb--one"></span>
        <span class="site-banner__ambient-orb site-banner__ambient-orb--two"></span>
    </div>
    <?php endif; ?>

    <!-- 风线 -->
    <?php if ($wind_enabled) : ?>
    <div class="site-banner__wind" aria-hidden="true">
        <?php for ($wind_index = 1; $wind_index <= $wind_count; $wind_index++) : ?>
        <span class="site-banner__wind-line site-banner__wind-line--<?php echo (int) $wind_index; ?>"></span>
        <?php endfor; ?>
    </div>
    <?php endif; ?>

    <!-- 图片背景：仅光晕关闭时作为 Banner 视觉层 -->
    <?php if (!$ambient_enabled && $banner_has_image): ?>
    <div class="site-banner__bg js-banner-bg" aria-hidden="true"></div>
    <div class="site-banner__overlay js-banner-overlay" aria-hidden="true"></div>
    <?php endif; ?>

    <div class="site-banner__content">
        <div class="site-banner__left">
            <?php if ($avatar): ?>
            <div class="site-banner__avatar-wrap">
                <img class="site-banner__avatar"
                     src="<?php echo esc_url($avatar); ?>"
                     alt="<?php echo esc_attr($title_zh); ?> 的头像"
                     width="120" height="120">
            </div>
            <?php endif; ?>
            
            <?php if ($intro): ?>
            <div class="site-banner__intro feed-card__excerpt"><?php echo wp_kses_post($intro); ?></div>
            <?php endif; ?>
        </div>

        <div class="site-banner__right">
            <h1 class="site-banner__title cyberpunk-translate"
                data-text="<?php echo esc_attr($title_zh); ?>"
                data-en="<?php echo esc_attr($title_en); ?>">
                <span class="banner-title-inner"><?php echo esc_html($title_zh); ?></span>
            </h1>
            <?php if ($subtitle_zh): ?>
            <p class="site-banner__subtitle cyberpunk-translate"
               data-text="<?php echo esc_attr($subtitle_zh); ?>"
               data-en="<?php echo esc_attr($subtitle_en); ?>">
                <span class="banner-subtitle-inner"><?php echo esc_html($subtitle_zh); ?></span>
            </p>
            <?php endif; ?>
        </div>
    </div>

    <button class="site-banner__scroll-hint js-banner-scroll" aria-label="向下滚动" type="button">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <polyline points="6 9 12 15 18 9"/>
        </svg>
    </button>
</section>

<!-- 赛博朋克翻译效果 -->
<style id="cyberpunk-banner-style">
    .cyberpunk-translate .banner-title-inner,
    .cyberpunk-translate .banner-subtitle-inner {
        visibility: hidden;
        text-shadow: 0 0 1em rgba(70, 142, 210, 0.55), 0 0 0.7em rgba(76, 163, 197, 0.52), 0 0 0.5em rgba(76, 179, 197, 0.35), 0 0 0.2em rgba(76, 195, 197, 0.29);

    }
    .cyberpunk-translate .char {
        display: inline-block;
        text-shadow: 0 0 1em rgba(70, 142, 210, 0.55), 0 0 0.7em rgba(76, 163, 197, 0.52), 0 0 0.5em rgba(76, 179, 197, 0.35), 0 0 0.2em rgba(76, 195, 197, 0.29);
    }
</style>
<script>
document.addEventListener("DOMContentLoaded", function () {
    var targets = document.querySelectorAll(".cyberpunk-translate");
    targets.forEach(function(el) {
        var zh = (el.dataset.text || "").trim();
        var en = (el.dataset.en || "").trim();
        if (!zh) {
            el.style.visibility = "visible";
            return;
        }
        
        var inner = el.querySelector(".banner-title-inner, .banner-subtitle-inner");
        if (!inner) return;
        
        inner.innerHTML = "";
        var charSpans = [];
        
        // 生成英文字符
        for (var i = 0; i < en.length; i++) {
            var span = document.createElement("span");
            span.className = "char";
            span.textContent = en[i] === " " ? "\u00A0" : en[i];
            inner.appendChild(span);
            charSpans.push(span);
        }
        
        inner.style.visibility = "visible";
        
        // 900ms 后开始翻译
        setTimeout(function() {
            var index = 0;
            var interval = setInterval(function() {
                if (index < zh.length) {
                    if (charSpans[index]) {
                        charSpans[index].textContent = zh[index] === " " ? "\u00A0" : zh[index];
                    } else {
                        var span = document.createElement("span");
                        span.className = "char";
                        span.textContent = zh[index] === " " ? "\u00A0" : zh[index];
                        inner.appendChild(span);
                        charSpans.push(span);
                    }
                } else if (index < charSpans.length) {
                    charSpans[index].remove();
                }
                index++;
                if (index >= Math.max(en.length, zh.length)) {
                    clearInterval(interval);
                    inner.innerHTML = "";
                    inner.textContent = zh;
                }
            }, 80);
        }, 900);
    });
});
</script>
<?php endif; ?>

<div class="site-page">
<div class="site-content-wrap">
    <div class="container<?php echo ($mimosa_home_sidebar || $mimosa_detail_sidebar) ? ' container--wide' : ''; ?>">
