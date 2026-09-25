<?php get_header(); ?>

<main class="site-main" role="main">
    <?php if (have_posts()) : ?>
        <header class="page-header author-header">
            <div class="author-header__inner">
                <div class="author-avatar">
                    <?php echo get_avatar(get_the_author_meta('ID'), 120); ?>
                </div>
                
                <div class="author-info">
                    <h1 class="page-title author-title">
                        <?php echo esc_html(get_the_author()); ?>
                    </h1>
                    
                    <?php if (get_the_author_meta('description')) : ?>
                        <div class="author-description">
                            <?php echo wp_kses_post(get_the_author_meta('description')); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="author-meta">
                        <span class="author-posts-count">
                            <?php
                            printf(
                                esc_html(_n('发布了 %s 篇文章', '发布了 %s 篇文章', get_the_author_posts(), 'mimosa')),
                                number_format_i18n(get_the_author_posts())
                            );
                            ?>
                        </span>
                        
                        <?php if (get_the_author_meta('url')) : ?>
                            <a href="<?php echo esc_url(get_the_author_meta('url')); ?>" class="author-website" target="_blank" rel="nofollow noopener">
                                <?php esc_html_e('访问网站', 'mimosa'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </header>

        <div class="posts-container">
            <div class="posts-list">
                <?php while (have_posts()) : the_post(); ?>
                    <?php
                    $post_type = get_post_type();
                    
                    if ($post_type === 'shuoshuo') {
                        get_template_part('template-parts/content', 'shuoshuo-preview');
                    } else {
                        $post_format = get_post_format() ?: 'standard';
                        
                        if (in_array($post_format, array('image', 'gallery', 'video', 'audio'))) {
                            get_template_part('template-parts/content', 'media-preview');
                        } else {
                            get_template_part('template-parts/content', 'preview');
                        }
                    }
                    ?>
                <?php endwhile; ?>
            </div>

        </div>

        <?php
        the_posts_pagination(array(
            'mid_size'  => 2,
            'prev_text' => esc_html__('← 上一页', 'mimosa'),
            'next_text' => esc_html__('下一页 →', 'mimosa'),
        ));
        ?>

    <?php else : ?>
        <div class="no-results">
            <h1 class="page-title"><?php esc_html_e('该作者还没有发布任何内容', 'mimosa'); ?></h1>
        </div>
    <?php endif; ?>
</main>

<?php get_footer(); ?>
