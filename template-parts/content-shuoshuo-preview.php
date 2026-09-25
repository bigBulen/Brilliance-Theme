<?php
/**
 * 说说预览卡片（首页列表）
 */

$post_id     = get_the_ID();
$content     = get_the_content();
$title       = get_the_title();
$date        = get_the_date('Y-m-d');
$datetime    = get_the_date('c');
$time_full   = get_the_date('Y-m-d H:i:s');
$permalink   = get_permalink();
$comment_num = get_comments_number($post_id);

// 截取预览内容，说说不截断，直接展示
$display_content = wpautop(wp_kses_post($content));

// 图片列表（说说中的图片单独展示）
preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $display_content, $img_matches);
$images = $img_matches[1] ?? array();

// 去掉内容中已有的 img 标签，避免重复渲染
$text_content = preg_replace('/<img[^>]+>/i', '', $display_content);
$text_content = trim($text_content);
?>

<article id="post-<?php the_ID(); ?>" <?php post_class('feed-card feed-card--shuoshuo'); ?>>
    <div class="shuoshuo-card">

        <?php
        // 作者信息
        $author_id = get_the_author_meta('ID');
        $author_name = get_the_author_meta('display_name');
        $avatar_url = get_avatar_url($author_id, array('size' => 36));
        ?>
        <div class="shuoshuo-card__avatar">
            <?php if ($avatar_url): ?>
            <img src="<?php echo esc_url($avatar_url); ?>"
                 alt="<?php echo esc_attr($author_name); ?>"
                 width="36" height="36"
                 loading="lazy">
            <?php else: ?>
            <div class="shuoshuo-card__avatar-placeholder" aria-hidden="true">
                <?php echo mb_substr($author_name, 0, 1); ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="shuoshuo-card__body">
            <div class="shuoshuo-card__header">
                <div class="shuoshuo-card__header-left">
                    <span class="shuoshuo-card__author"><?php echo esc_html($author_name); ?></span>
                    <span class="shuoshuo-card__type-badge">说说</span>
                </div>
                <a class="shuoshuo-card__time" href="<?php echo esc_url($permalink); ?>"
                   title="<?php echo esc_attr($time_full); ?>">
                    <time datetime="<?php echo esc_attr($datetime); ?>"><?php echo esc_html($date); ?></time>
                </a>
            </div>

            <div class="shuoshuo-card__content">
                <?php if (trim((string) $title) !== ''): ?>
                <div class="shuoshuo-card__title"><?php echo esc_html($title); ?></div>
                <?php endif; ?>

                <?php if (!empty($text_content)): ?>
                <div class="shuoshuo-card__text js-shuoshuo-fold-content" data-fold-threshold="<?php echo intval(get_option('mimosa_shuoshuo_fold_threshold', 250)); ?>">
                    <?php echo $text_content; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($images)): ?>
                <div class="shuoshuo-card__images shuoshuo-card__images--<?php echo count($images) >= 3 ? 'grid' : 'row'; ?>">
                    <?php foreach ($images as $img_url): ?>
                    <a class="shuoshuo-card__img-link js-img-preview"
                       href="<?php echo esc_url($img_url); ?>"
                       data-src="<?php echo esc_url($img_url); ?>">
                        <img src="<?php echo esc_url($img_url); ?>"
                             alt=""
                             loading="lazy"
                             class="shuoshuo-card__img">
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
                <a class="shuoshuo-card__permalink" href="<?php echo esc_url($permalink); ?>" aria-label="查看详情">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                </a>
            </div>
        </div>

    </div>
</article>
