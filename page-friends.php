<?php
/**
 * 友链页模板
 * Template Name: 友链
 */

get_header();
?>

<main class="site-main" role="main">
    <div class="page-friends">
        <?php while (have_posts()) : the_post(); ?>
        <header class="page-friends__header">
            <h1 class="page-friends__title"><?php the_title(); ?></h1>
            <?php if (trim(get_the_content())) : ?>
            <div class="page-friends__intro"><?php the_content(); ?></div>
            <?php endif; ?>
        </header>
        <?php endwhile; ?>

        <?php echo do_shortcode('[mimosa_friends]'); ?>
    </div>
</main>

<?php get_footer(); ?>