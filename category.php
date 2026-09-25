<?php
/**
 * 分类归档页
 */

get_header();

$category = get_queried_object();
$cat_id = $category->term_id;
$cat_name = $category->name;
$cat_description = $category->description;
$cat_count = $category->count;
?>

<main class="site-main" role="main">
    <div class="archive-category">
        <div class="archive-category__header">
            <h1 class="archive-category__title">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z"/>
                    <line x1="7" y1="7" x2="7.01" y2="7"/>
                </svg>
                <?php echo esc_html($cat_name); ?>
            </h1>
            <div class="archive-category__count"><?php echo intval($cat_count); ?> 篇文章</div>
        </div>
        
        <?php if ($cat_description): ?>
        <div class="archive-category__description">
            <?php echo wpautop(wp_kses_post($cat_description)); ?>
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
                echo '<div class="archive-empty">此分类下暂无文章</div>';
            }
            ?>
        </div>
        
        <?php
        // 分页
        the_posts_pagination(array(
            'mid_size' => 2,
            'prev_text' => '« 上一页',
            'next_text' => '下一页 »',
        ));
        ?>
    </div>
</main>

<?php
get_footer();
?>
