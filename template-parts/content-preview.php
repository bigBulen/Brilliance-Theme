<?php
/**
 * 文章预览卡片（首页列表）
 */

$post_id   = get_the_ID();
$thumbnail = mimosa_get_post_thumbnail($post_id);
$excerpt   = mimosa_get_excerpt($post_id, 80);
$is_sticky = is_sticky($post_id);
$has_password = !empty(get_post_field('post_password', $post_id));
$categories = get_the_category($post_id);
$read_time  = mimosa_reading_time(get_post_field('post_content', $post_id));
$word_count = mimosa_count_words(get_post_field('post_content', $post_id));
?>

<article id="post-<?php the_ID(); ?>" <?php post_class('feed-card feed-card--post'); ?>>
    <a class="feed-card__link" href="<?php the_permalink(); ?>" aria-label="<?php the_title_attribute(); ?>">

        <?php if ($thumbnail): ?>
        <div class="feed-card__thumb">
            <img class="feed-card__thumb-img"
                 src="<?php echo esc_url($thumbnail); ?>"
                 alt="<?php the_title_attribute(); ?>"
                 loading="lazy">
        </div>
        <?php endif; ?>

        <div class="feed-card__body">
            <div class="feed-card__meta-row">
                <?php if ($is_sticky): ?>
                <span class="feed-card__badge feed-card__badge--sticky">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M16 4v8l2 4H6l2-4V4h8z"/><path d="M12 20v-4"/></svg>
                    置顶
                </span>
                <?php endif; ?>
                <?php if ($has_password): ?>
                <span class="feed-card__badge feed-card__badge--locked">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                    加密
                </span>
                <?php endif; ?>
                <?php if (!empty($categories)): ?>
                <span class="feed-card__cat"><?php echo esc_html($categories[0]->name); ?></span>
                <?php endif; ?>
                <time class="feed-card__time" datetime="<?php echo get_the_date('c'); ?>">
                    <?php echo get_the_date('Y-m-d'); ?>
                </time>
            </div>

            <h2 class="feed-card__title">
                <?php the_title(); ?>
            </h2>

            <?php if (!$has_password && !empty($excerpt)): ?>
            <p class="feed-card__excerpt"><?php echo esc_html($excerpt); ?></p>
            <?php elseif ($has_password): ?>
            <p class="feed-card__excerpt feed-card__excerpt--locked">
                <?php
                $hint = get_post_meta($post_id, 'password_hint', true);
                if ($hint) {
                    echo esc_html($hint);
                } else {
                    _e('此文章受密码保护', 'mimosa');
                }
                ?>
            </p>
            <?php else: ?>
            <p class="feed-card__excerpt feed-card__excerpt--none"><?php _e('无摘要', 'mimosa'); ?></p>
            <?php endif; ?>

            <div class="feed-card__footer">
                <span class="feed-card__reading">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    <?php echo esc_html($read_time); ?>
                </span>
                <span class="feed-card__words"><?php echo esc_html(number_format($word_count)); ?> 字</span>
                <span class="feed-card__comments">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
                    <?php echo esc_html(get_comments_number($post_id)); ?>
                </span>
            </div>
        </div>

    </a>
</article>
