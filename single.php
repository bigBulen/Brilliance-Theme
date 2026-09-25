<?php
/**
 * 文章详情页
 */

get_header();
$detail_has_toc = function_exists('mimosa_has_toc') && mimosa_has_toc();
?>

<div class="detail-layout<?php echo $detail_has_toc ? ' detail-layout--has-sidebar' : ''; ?>">
    <?php brilliance_render_detail_sidebar(); ?>

    <main class="site-main detail-main" role="main">
        <?php while (have_posts()): the_post(); ?>
        <?php get_template_part('template-parts/content-single'); ?>


        <?php if (is_singular('post') && mimosa_post_extra_enabled()): ?>
        <div class="post-extra-comment">
            <div class="post-extra-comment__head">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v18"/><path d="M3 7l9-4 9 4"/><path d="M3 7v10l9 4 9-4V7"/><polyline points="6 4.5 6 9.5 18 9.5 18 4.5"/></svg>
                <span><?php esc_html_e('版权说明', 'mimosa'); ?></span>
            </div>
            <div class="post-extra-comment__license"><?php echo wp_kses_post(mimosa_post_extra_license_text()); ?></div>
            <div class="post-extra-comment__origin"><?php esc_html_e('原文链接：', 'mimosa'); ?><a href="<?php echo esc_url(get_permalink()); ?>"><?php echo esc_html(get_permalink()); ?></a></div>
        </div>
        <?php endif; ?>

        <?php endwhile; ?>
    </main>
</div>

<?php get_footer(); ?>
