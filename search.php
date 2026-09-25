<?php get_header(); ?>
<main class="site-main" role="main">
    <div class="search-page">
        <header class="search-page__header">
            <h1 class="search-page__title">
                搜索：<span class="search-page__keyword"><?php echo esc_html(get_search_query()); ?></span>
            </h1>
            <?php global $wp_query; ?>
            <p class="search-page__count">
                <?php
                $count = $wp_query->found_posts;
                if ($count > 0) {
                    printf('找到 <strong>%d</strong> 条结果', $count);
                } else {
                    echo '未找到相关内容';
                }
                ?>
            </p>
        </header>

        <?php if (have_posts()): ?>
        <div class="feed">
            <?php while (have_posts()): the_post();
                $post_type = get_post_type();
                if ($post_type === 'shuoshuo') {
                    get_template_part('template-parts/content-shuoshuo-preview');
                } else {
                    get_template_part('template-parts/content-preview');
                }
            endwhile; ?>
        </div>
        <?php echo mimosa_paginate_links(); ?>
        <?php else: ?>
        <div class="search-page__empty">
            <p>没有找到与「<?php echo esc_html(get_search_query()); ?>」相关的内容。</p>
            <p>建议检查拼写，或尝试更短的关键词。</p>
        </div>
        <?php endif; ?>
    </div>
</main>
<?php get_footer(); ?>
