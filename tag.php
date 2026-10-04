<?php
/**
 * 标签归档页
 */

get_header();

$tag = get_queried_object();
$tag_name = $tag->name;
$tag_count = $tag->count;
$tag_description = $tag->description ?: '';
?>

<main class="site-main" role="main">
    <div class="archive-category">
        <div class="archive-category__header">
            <h1 class="archive-category__title">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="9" y1="9" x2="15" y2="15"></line>
                    <circle cx="9" cy="9" r="7"></circle>
                    <path d="M13 9a3.95 3.95 0 01-3 3"></path>
                </svg>
                <?php echo esc_html($tag_name); ?>
            </h1>
            <div class="archive-category__count"><?php echo intval($tag_count); ?> 篇文章</div>
        </div>

        <?php if ($tag_description): ?>
        <div class="archive-category__description">
            <?php echo wpautop(wp_kses_post($tag_description)); ?>
        </div>
        <?php endif; ?>

        <div class="feed">
            <?php
            if (have_posts()) {
                while (have_posts()) {
                    the_post();
                    get_template_part('template-parts/content', 'preview');
                }
            } else {
                echo '<div class="archive-empty">此标签下暂无文章</div>';
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