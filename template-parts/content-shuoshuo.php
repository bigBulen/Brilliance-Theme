<?php
/**
 * 说说单条详情内容模板
 */

$post_id     = get_the_ID();
$content     = get_the_content();
$shuo_title = get_the_title();
$date        = get_the_date('Y-m-d H:i:s');
$datetime    = get_the_date('c');
// 头像使用发表作者的邮箱 Gravatar（经主题 Gravatar CDN 过滤），无邮箱时回退到站点头像
$author_email = get_the_author_meta('user_email');
$avatar_url   = $author_email ? get_avatar_url($author_email, array('size' => 112)) : '';
if (!$avatar_url) {
    $avatar_url = get_option('mimosa_avatar_url', '');
}
$author_name = get_bloginfo('name');
$comment_num = get_comments_number($post_id);

// 图片与说明：从过滤后的正文中提取图片块（figure + figcaption）
$html = apply_filters('the_content', $content);
$image_pairs = array();

if (preg_match_all('/<figure[^>]*>.*?<\/figure>/is', $html, $fig_matches)) {
    foreach ($fig_matches[0] as $fig_html) {
        if (!preg_match('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $fig_html, $img_m)) {
            continue;
        }
        $img_caption = '';
        if (preg_match('/<figcaption[^>]*>(.*?)<\/figcaption>/is', $fig_html, $cap_m)) {
            $img_caption = trim(strip_tags($cap_m[1]));
        }
        $image_pairs[] = array('url' => $img_m[1], 'caption' => $img_caption);
    }
}

// 游离（不在 figure 内）的 img 也收集（无说明）
$html_no_fig = preg_replace('/<figure[^>]*>.*?<\/figure>/is', '', $html);
if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $html_no_fig, $bare_matches)) {
    foreach ($bare_matches[1] as $bare_src) {
        $image_pairs[] = array('url' => $bare_src, 'caption' => '');
    }
}

// 纯文本：剥离图片块与图片，避免说明文字混入正文。
// 仅移除「含图片」的 figure，保留表格/嵌入等非图片 figure，避免表格、视频被一并删掉。
$display_content = wpautop(wp_kses_post($content));
$display_content = preg_replace_callback('/<figure[^>]*>.*?<\/figure>/is', function ($m) {
    return preg_match('/<img[^>]+>/i', $m[0]) ? '' : $m[0];
}, $display_content);
$display_content = preg_replace('/<img[^>]+>/i', '', $display_content);
$display_content = preg_replace('/<p[^>]*>\s*<\/p>/', '', $display_content);
$text_content    = trim($display_content);
?>

<article id="post-<?php the_ID(); ?>" <?php post_class('shuoshuo-detail'); ?>>

    <div class="shuoshuo-detail__card">
        <div class="shuoshuo-detail__avatar">
            <?php if ($avatar_url): ?>
            <img src="<?php echo esc_url($avatar_url); ?>"
                 alt="<?php echo esc_attr($author_name); ?>"
                 width="48" height="48" loading="lazy">
            <?php else: ?>
            <div class="shuoshuo-card__avatar-placeholder">
                <?php echo esc_html(mb_substr($author_name, 0, 1)); ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="shuoshuo-detail__body">
            <div class="shuoshuo-detail__meta">
                <span class="shuoshuo-detail__author"><?php echo esc_html($author_name); ?></span>
                <time class="shuoshuo-detail__time" datetime="<?php echo esc_attr($datetime); ?>">
                    <?php echo esc_html($date); ?>
                </time>
            </div>

            <?php do_action('brilliance_entry_header_end', $post_id); ?>

            <?php if (trim((string) $shuo_title) !== ''): ?>
            <div class="shuoshuo-detail__title"><?php echo esc_html($shuo_title); ?></div>
            <?php endif; ?>

            <?php if (!empty($text_content)): ?>
            <div class="shuoshuo-detail__text">
                <?php echo $text_content; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($image_pairs)): ?>
            <div class="shuoshuo-detail__images shuoshuo-card__images--<?php echo count($image_pairs) >= 3 ? 'grid' : 'row'; ?>">
                <?php foreach ($image_pairs as $image_pair):
                    $img_url = $image_pair['url'];
                    $img_caption = $image_pair['caption'];
                ?>
                <a class="shuoshuo-card__img-link js-img-preview"
                   href="<?php echo esc_url($img_url); ?>"
                   data-src="<?php echo esc_url($img_url); ?>">
                    <img src="<?php echo esc_url($img_url); ?>"
                         alt="" loading="lazy" class="shuoshuo-card__img">
                    <?php if ($img_caption !== ''): ?>
                    <span class="shuoshuo-card__img-caption"><?php echo esc_html($img_caption); ?></span>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if (function_exists('mimosa_reactions_render')): ?>
            <div class="shuoshuo-detail__reactions">
                <?php mimosa_reactions_render($post_id); ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 评论 -->
    <div class="shuoshuo-detail__comments" id="comments">
        <?php
        if (comments_open() || get_comments_number()) {
            comments_template();
        }
        ?>
    </div>

</article>
