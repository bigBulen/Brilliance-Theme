<?php
/**
 * 特色短代码注册
 *
 * 说明：
 * - 已废除旧的「自动建页」逻辑（mimosa_ensure_feature_pages），
 *   不再在主题切换时自动创建固定 slug 的页面；请自行在页面内插入下方短代码。
 * - 为兼容旧内容，旧短代码名均保留为别名。
 */

if (!defined('ABSPATH')) {
    exit;
}

/* ── 友链：[brilliance_friends]（别名 [mimosa_friends]） ── */
function mimosa_render_friends_shortcode($atts = array()) {
    $atts = shortcode_atts(array(
        'orderby' => 'name',
        'order' => 'ASC',
    ), $atts, 'brilliance_friends');

    $friendlinks = get_bookmarks(array(
        'orderby' => sanitize_key($atts['orderby']),
        'order' => strtoupper($atts['order']) === 'DESC' ? 'DESC' : 'ASC',
    ));

    if (empty($friendlinks)) {
        return '<p class="friends-empty">暂无友链</p>';
    }

    ob_start(); ?>
    <div class="friends-grid">
        <?php foreach ($friendlinks as $link) :
            $name = $link->link_name;
            $url = $link->link_url;
            $description = $link->link_description;
            $avatar = $link->link_image;
        ?>
        <a class="friend-card" href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer">
            <div class="friend-card__avatar">
                <?php if ($avatar) : ?>
                <img src="<?php echo esc_url($avatar); ?>" alt="<?php echo esc_attr($name); ?>" width="48" height="48" loading="lazy">
                <?php else : ?>
                <div class="friend-card__avatar-fallback" aria-hidden="true"><?php echo esc_html(mb_substr($name, 0, 1)); ?></div>
                <?php endif; ?>
            </div>
            <div class="friend-card__info">
                <span class="friend-card__name"><?php echo esc_html($name); ?></span>
                <?php if ($description) : ?><span class="friend-card__desc"><?php echo esc_html($description); ?></span><?php endif; ?>
            </div>
            <span class="friend-card__arrow" aria-hidden="true">→</span>
        </a>
        <?php endforeach; ?>
    </div>
    <?php return ob_get_clean();
}
add_shortcode('brilliance_friends', 'mimosa_render_friends_shortcode');
add_shortcode('mimosa_friends', 'mimosa_render_friends_shortcode');

/* ── 归档/索引：[brilliance_index] ── */
function brilliance_index_shortcode($atts = array()) {
    $all_posts = get_posts(array(
        'post_type'           => array('post', 'shuoshuo'),
        'post_status'         => 'publish',
        'posts_per_page'      => -1,
        'orderby'             => array('date' => 'DESC', 'ID' => 'DESC'),
        'ignore_sticky_posts' => true,
        'no_found_rows'       => true,
    ));

    $groups = array();
    foreach ($all_posts as $post) {
        if (!($post instanceof WP_Post)) continue;
        $timestamp = (int) get_post_timestamp($post, 'date');
        if (!$timestamp) continue;
        $groups[date('Y', $timestamp)][date('m', $timestamp)][] = $post;
    }

    ob_start(); ?>
    <div class="archive-page">
        <header class="archive-page__header">
            <div><p class="acgn-list__eyebrow">PUBLISHED CONTENT</p><h2 class="archive-page__title">内容时间轴</h2></div>
            <p class="archive-page__count">共 <strong><?php echo count($all_posts); ?></strong> 篇内容</p>
        </header>
        <div class="archive-timeline">
            <?php foreach ($groups as $year => $months): ?>
                <section class="archive-year">
                    <h2 class="archive-year__title"><span class="archive-year__num"><?php echo esc_html($year); ?></span></h2>
                    <div class="archive-year__months">
                        <?php foreach ($months as $month => $month_posts): ?>
                            <section class="archive-month">
                                <h3 class="archive-month__label"><?php echo (int) $month; ?> 月 <span class="archive-month__count"><?php echo count($month_posts); ?></span></h3>
                                <ul class="archive-month__list">
                                    <?php foreach ($month_posts as $post):
                                        $is_shuoshuo = $post->post_type === 'shuoshuo';
                                        $timestamp = (int) get_post_timestamp($post, 'date');
                                        $title = get_the_title($post);
                                        if ($is_shuoshuo) {
                                            $shuo_title = trim((string) $title);
                                            if ($shuo_title === '') {
                                                $plain_content = trim(wp_strip_all_tags(strip_shortcodes($post->post_content)));
                                                $title = $plain_content ? wp_html_excerpt($plain_content, 30, '…') : '（无内容）';
                                            } else {
                                                $title = $shuo_title;
                                            }
                                        }
                                    ?>
                                        <li class="archive-item archive-item--<?php echo esc_attr($post->post_type); ?>">
                                            <time class="archive-item__date" datetime="<?php echo esc_attr(date('c', $timestamp)); ?>"><?php echo esc_html(date('m-d', $timestamp)); ?></time>
                                            <span class="archive-item__dot" aria-hidden="true"></span>
                                            <a class="archive-item__link" href="<?php echo esc_url(get_permalink($post)); ?>">
                                                <?php if ($is_shuoshuo): ?><span class="archive-item__type-tag">说说</span><?php else: ?><span class="archive-item__type-tag">文章</span><?php endif; ?>
                                                <?php echo esc_html($title); ?>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </section>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>
    </div>
    <?php return ob_get_clean();
}
add_shortcode('brilliance_index', 'brilliance_index_shortcode');