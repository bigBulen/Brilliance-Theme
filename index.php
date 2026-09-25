<?php
/**
 * 首页模板
 * 混排展示：文章、说说、ACGN作品（折叠）
 *
 * 支持可选的首页副栏（设置页可开关、可设左/右）：
 * - 副栏小工具区域为 home-sidebar（"首页副栏"）
 * - 副栏关闭或无小工具时保持单栏
 * - 移动端副栏隐藏，其内容改在顶部菜单展开区显示（见 header.php）
 */

get_header();

$home_sidebar_enabled = brilliance_home_sidebar_is_active();
$home_sidebar_side = get_option('brilliance_home_sidebar_position', 'right') === 'left' ? 'left' : 'right';

$home_layout_class = 'home-layout';
if ($home_sidebar_enabled) {
    $home_layout_class .= ' home-layout--has-sidebar home-layout--sidebar-' . $home_sidebar_side;
}
?>

<div class="<?php echo esc_attr($home_layout_class); ?>">
    <main class="site-main" role="main">
        <div class="feed">
            <?php
            $feed = mimosa_get_home_feed();

            if (empty($feed['items'])) {
                ?>
                <div class="feed-empty">
                    <p><?php _e('暂无内容', 'mimosa'); ?></p>
                </div>
                <?php
            } else {
                mimosa_render_home_feed($feed);

                // 分页
                echo mimosa_custom_paginate($feed['total'], $feed['per_page'], $feed['paged']);
            }
            ?>
        </div>
    </main>

    <?php if ($home_sidebar_enabled): ?>
    <!-- 首页副栏（移动端 display:none，内容在菜单展开区重复渲染） -->
    <aside class="home-sidebar" role="complementary" aria-label="<?php esc_attr_e('副栏', 'mimosa'); ?>">
        <?php brilliance_render_site_notice(); ?>
        <?php dynamic_sidebar('home-sidebar'); ?>
    </aside>
    <?php endif; ?>
</div>

<?php get_footer(); ?>
