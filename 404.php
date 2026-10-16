<?php
/**
 * 404 页面
 *
 * 主题设置「站点与 SEO → 404 页面」可开关：
 * - 启用（默认）：显示美化版 404（大号 404、搜索框、返回按钮、猜你想看）
 * - 关闭：退回简版提示
 *
 * 访问不存在的页面/越界页码时，WordPress 会走此模板。
 */

get_header();

$use_custom_404 = get_option('brilliance_404_enable', 'yes') === 'yes';
?>

<main class="site-main" role="main">
    <?php if ($use_custom_404) : ?>

    <div class="error-404">
        <div class="error-404__card">
            <div class="error-404__code" aria-hidden="true">404</div>
            <h1 class="error-404__title"><?php esc_html_e('页面未找到', 'mimosa'); ?></h1>
            <p class="error-404__description">
                <?php esc_html_e('抱歉，你访问的页面不存在、已被删除，或者链接有误。', 'mimosa'); ?>
            </p>

            <div class="error-404__search">
                <?php get_search_form(); ?>
            </div>

            <div class="error-404__actions">
                <a href="<?php echo esc_url(home_url('/')); ?>" class="button button--primary">
                    <?php esc_html_e('返回首页', 'mimosa'); ?>
                </a>
                <a href="<?php echo esc_url(home_url('/')); ?>" class="button"
                   onclick="if (document.referrer) { history.back(); return false; }">
                    <?php esc_html_e('返回上一页', 'mimosa'); ?>
                </a>
            </div>
        </div>

        <?php
        $recent_posts = wp_get_recent_posts(array(
            'numberposts' => 6,
            'post_status' => 'publish',
        ));
        if (!empty($recent_posts)) :
        ?>
        <section class="error-404__recent">
            <h2 class="error-404__recent-title"><?php esc_html_e('猜你想看', 'mimosa'); ?></h2>
            <div class="error-404__recent-grid">
                <?php foreach ($recent_posts as $recent) : ?>
                    <a class="error-404__recent-card" href="<?php echo esc_url(get_permalink($recent['ID'])); ?>">
                        <?php echo esc_html($recent['post_title']); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>
    </div>

    <?php else : ?>

    <div class="error-404 error-404--plain">
        <h1 class="error-404__title">404</h1>
        <p class="error-404__description">
            <?php esc_html_e('页面未找到。', 'mimosa'); ?>
            <a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('返回首页', 'mimosa'); ?></a>
        </p>
    </div>

    <?php endif; ?>
</main>

<?php get_footer(); ?>
