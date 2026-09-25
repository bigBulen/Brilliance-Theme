<?php
/**
 * 说说详情页
 */
get_header();
?>
<main class="site-main" role="main">
    <?php while (have_posts()): the_post(); ?>
    <?php get_template_part('template-parts/content-shuoshuo'); ?>
    <?php endwhile; ?>
</main>
<?php get_footer(); ?>
