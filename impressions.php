<?php
/**
 * 印象集「Toward Our Dream」独立页面模板
 *
 * 不套用主题 header/footer/sidebar：仅输出星空 + 作品封面画布与右上角操作按钮。
 */
if (!defined('ABSPATH')) exit;

$impressions_css_src = MIMOSA_THEME_URI . '/assets/css/impressions.css';
$impressions_js_src  = MIMOSA_THEME_URI . '/assets/js/impressions.js';
$impressions_css_ver = mimosa_asset_version('assets/css/impressions.css');
$impressions_js_ver  = mimosa_asset_version('assets/js/impressions.js');

$impressions_config = array(
    'apiUrl'     => rest_url('acgn/v1/impressions'),
    'homeUrl'    => home_url('/'),
    'homeLabel'  => '返回主站',
    'centerText' => trim((string) get_option('mimosa_impressions_center_text', 'Toward our Dream')),
);
?><!DOCTYPE html>
<html lang="<?php echo esc_attr(get_bloginfo('language')); ?>">
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="robots" content="noindex,follow">
    <title><?php echo esc_html($impressions_config['centerText'] ?: __('印象集', 'mimosa')); ?> · <?php bloginfo('name'); ?></title>
    <link rel="stylesheet" href="<?php echo esc_url($impressions_css_src); ?>?ver=<?php echo esc_attr($impressions_css_ver); ?>">
</head>
<body>
    <canvas id="impressions-canvas" aria-hidden="true"></canvas>

    <div class="impressions-actions">
        <button id="impressions-recenter" class="impressions-btn impressions-btn--icon" type="button" aria-label="回到视角中心" title="回到视角中心">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="7"></circle><line x1="12" y1="1" x2="12" y2="5"></line><line x1="12" y1="19" x2="12" y2="23"></line><line x1="1" y1="12" x2="5" y2="12"></line><line x1="19" y1="12" x2="23" y2="12"></line></svg>
        </button>
        <button id="impressions-home" class="impressions-btn" type="button" aria-label="<?php echo esc_attr($impressions_config['homeLabel']); ?>">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            <span><?php echo esc_html($impressions_config['homeLabel']); ?></span>
        </button>
    </div>

    <div id="impressions-hint" class="impressions-hint" aria-hidden="true">拖动查看 · 移动鼠标探索</div>

    <script>
    window.IMPRESSIONS_CONFIG = <?php echo wp_json_encode($impressions_config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    </script>
    <script src="<?php echo esc_url($impressions_js_src); ?>?ver=<?php echo esc_attr($impressions_js_ver); ?>"></script>


    <!-- 其它的页面外挂HTML -->
    <!-- 小播放器信息 -->
    <script>var meting_api='https://loneapex.cn/meting-api/?server=:server&type=:type&id=:id&auth=:auth&r=:r';</script>
    <link rel="stylesheet" href="<?php echo get_template_directory_uri(); ?>/assets/Aplayer/APlayer.min.css">
    <script src="<?php echo get_template_directory_uri(); ?>/assets/Aplayer/APlayer.min.js"></script>
    <script src="<?php echo get_template_directory_uri(); ?>/assets/Aplayer/Meting.min.js"></script>
    <meting-js
        server="netease"
        type="playlist"
        id="8159389492"
        fixed="true"
        mini="true"
        order="list"
        loop="all"
        preload="false"
        list-folded="true"
        lrc-type="1"
    ></meting-js>

</body>
</html>