<?php
/**
 * 说说归档页
 */
get_header();

$paged    = max(1, get_query_var('paged'));
$per_page = max(1, (int) get_option('posts_per_page', 10));

$query = new WP_Query(array(
    'post_type'      => 'shuoshuo',
    'post_status'    => 'publish',
    'posts_per_page' => $per_page,
    'paged'          => $paged,
    'orderby'        => 'date',
    'order'          => 'DESC',
));
?>

<main class="site-main" role="main">
    <div class="shuoshuo-archive">
        <header class="shuoshuo-archive__header">
            <h1 class="shuoshuo-archive__title">碎碎念</h1>
            <p class="shuoshuo-archive__desc">日常的只言片语</p>
        </header>

        <?php if ($query->have_posts()): ?>
        <div class="shuoshuo-archive__list">
            <?php while ($query->have_posts()): $query->the_post(); ?>
            <?php
            $post_id     = get_the_ID();
            $content     = get_the_content();
            $date        = get_the_date('Y-m-d H:i');
            $datetime    = get_the_date('c');
            $permalink   = get_permalink();
            $avatar_url  = get_option('mimosa_avatar_url', '');
            $author_name = get_bloginfo('name');
            $comment_num = get_comments_number($post_id);

            preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i',
                apply_filters('the_content', $content), $img_matches);
            $images          = $img_matches[1] ?? array();
            $display_content = wpautop(wp_kses_post($content));
            $text_content    = trim(preg_replace('/<img[^>]+>/i', '', $display_content));
            ?>
            <article id="post-<?php the_ID(); ?>" <?php post_class('shuoshuo-archive__item'); ?>>
                <div class="shuoshuo-card">
                    <div class="shuoshuo-card__avatar">
                        <?php if ($avatar_url): ?>
                        <img src="<?php echo esc_url($avatar_url); ?>"
                             alt="<?php echo esc_attr($author_name); ?>"
                             width="36" height="36" loading="lazy">
                        <?php else: ?>
                        <div class="shuoshuo-card__avatar-placeholder">
                            <?php echo esc_html(mb_substr($author_name, 0, 1)); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="shuoshuo-card__body">
                        <div class="shuoshuo-card__header">
                            <span class="shuoshuo-card__author"><?php echo esc_html($author_name); ?></span>
                            <a class="shuoshuo-card__time" href="<?php echo esc_url($permalink); ?>"
                               title="<?php echo esc_attr($date); ?>">
                                <time datetime="<?php echo esc_attr($datetime); ?>"><?php echo esc_html($date); ?></time>
                            </a>
                        </div>
                        <div class="shuoshuo-card__content">
                            <?php if (!empty($text_content)): ?>
                            <div class="shuoshuo-card__text"><?php echo $text_content; ?></div>
                            <?php endif; ?>
                            <?php if (!empty($images)): ?>
                            <div class="shuoshuo-card__images shuoshuo-card__images--<?php echo count($images) >= 3 ? 'grid' : 'row'; ?>">
                                <?php foreach ($images as $img_url): ?>
                                <a class="shuoshuo-card__img-link js-img-preview"
                                   href="<?php echo esc_url($img_url); ?>"
                                   data-src="<?php echo esc_url($img_url); ?>">
                                    <img src="<?php echo esc_url($img_url); ?>"
                                         alt="" loading="lazy" class="shuoshuo-card__img">
                                </a>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="shuoshuo-card__footer">
                            <a class="shuoshuo-card__detail-link" href="<?php echo esc_url($permalink); ?>">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
                                <?php echo intval($comment_num); ?> 条评论
                            </a>
                        </div>
                    </div>
                </div>
            </article>
            <?php endwhile; ?>
        </div>

        <?php echo mimosa_paginate_links($query->max_num_pages); ?>

        <?php else: ?>
        <p class="shuoshuo-archive__empty">暂无说说</p>
        <?php endif; ?>

        <?php wp_reset_postdata(); ?>
    </div>
</main>

<?php get_footer(); ?>
