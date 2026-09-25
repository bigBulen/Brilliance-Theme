<?php
/**
 * 标准归档页（日期等通用归档）
 *
 * 走主查询 have_posts()，与分类/标签/作者页一致的卡片流；
 * 不再渲染「全站文章+说说时间轴」（该功能已由 [brilliance_index] 短代码承载）。
 */

get_header();

$archive_title       = get_the_archive_title();
$archive_description = get_the_archive_description();
$archive_count       = isset($GLOBALS['wp_query']) ? (int) $GLOBALS['wp_query']->found_posts : 0;
?>

<main class="site-main" role="main">
    <div class="archive-category">
        <div class="archive-category__header">
            <h1 class="archive-category__title">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                </svg>
                <?php echo wp_kses_post($archive_title); ?>
            </h1>
            <div class="archive-category__count"><?php echo intval($archive_count); ?> 篇文章</div>
        </div>

        <?php if ($archive_description): ?>
        <div class="archive-category__description">
            <?php echo wpautop(wp_kses_post($archive_description)); ?>
        </div>
        <?php endif; ?>

        <div class="feed">
            <?php
            if (have_posts()) {
                while (have_posts()) {
                    the_post();
                    get_template_part('template-parts/content', 'post-preview');
                }
            } else {
                echo '<div class="archive-empty">暂无文章</div>';
            }
            ?>
        </div>

        <?php
        the_posts_pagination(array(
            'mid_size' => 2,
            'prev_text' => '« 上一页',
            'next_text' => '下一页 »',
        ));
        ?>
    </div>
</main>

<?php get_footer(); ?>