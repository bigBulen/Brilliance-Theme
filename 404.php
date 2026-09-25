<?php get_header(); ?>

<main class="site-main" role="main">
    <div class="error-404">
        <div class="error-404__inner">
            <div class="error-404__content">
                <h1 class="error-404__title">404</h1>
                <p class="error-404__subtitle"><?php esc_html_e('页面未找到', 'mimosa'); ?></p>
                <p class="error-404__description">
                    <?php esc_html_e('抱歉，您访问的页面不存在或已被删除。', 'mimosa'); ?>
                </p>
                
                <div class="error-404__search">
                    <?php get_search_form(); ?>
                </div>
                
                <div class="error-404__actions">
                    <a href="<?php echo esc_url(home_url('/')); ?>" class="button button--primary">
                        <?php esc_html_e('返回首页', 'mimosa'); ?>
                    </a>
                </div>
            </div>
            
            <?php if (is_active_sidebar('sidebar-1')) : ?>
                <aside class="error-404__sidebar">
                    <div class="widget-area">
                        <h2 class="widget-area__title"><?php esc_html_e('推荐内容', 'mimosa'); ?></h2>
                        <?php
                        $recent_posts = wp_get_recent_posts(array(
                            'numberposts' => 5,
                            'post_status' => 'publish'
                        ));
                        
                        if ($recent_posts) : ?>
                            <ul class="recent-posts">
                                <?php foreach ($recent_posts as $post) : ?>
                                    <li>
                                        <a href="<?php echo esc_url(get_permalink($post['ID'])); ?>">
                                            <?php echo esc_html($post['post_title']); ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </aside>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php get_footer(); ?>
