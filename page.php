<?php
/**
 * 页面详情
 */

get_header();
$detail_has_toc = function_exists('mimosa_has_toc') && mimosa_has_toc();
?>

<div class="detail-layout<?php echo $detail_has_toc ? ' detail-layout--has-sidebar' : ''; ?>">
    <?php brilliance_render_detail_sidebar(); ?>

    <main class="site-main detail-main" role="main">
        <?php while (have_posts()): the_post(); ?>
        <?php get_template_part('template-parts/content-page'); ?>
        <?php endwhile; ?>
    </main>
</div>

<?php get_footer(); ?>
