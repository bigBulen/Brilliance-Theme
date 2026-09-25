<?php
/**
 * 工具函数
 */

if (!defined('ABSPATH')) exit;

// 格式化数字（K, M）
function mimosa_format_number($num) {
    if ($num >= 1000000) {
        return round($num / 1000000, 1) . 'M';
    } elseif ($num >= 1000) {
        return round($num / 1000, 1) . 'K';
    }
    return number_format($num);
}

// 获取文章缩略图
function mimosa_get_post_thumbnail($post_id = null, $size = 'mimosa-thumbnail') {
    if (!$post_id) {
        $post_id = get_the_ID();
    }
    
    if (has_post_thumbnail($post_id)) {
        return get_the_post_thumbnail_url($post_id, $size);
    }
    
    // 尝试从内容中提取第一张图片
    $content = get_post_field('post_content', $post_id);
    preg_match('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches);
    
    if (!empty($matches[1])) {
        return $matches[1];
    }
    
    // 返回默认图片或空
    return '';
}

// 获取文章摘要
function mimosa_get_excerpt($post_id = null, $length = 120) {
    if (!$post_id) {
        $post_id = get_the_ID();
    }
    
    $post = get_post($post_id);
    if (!$post) return '';
    
    // 如果有手动摘要，优先使用
    if (!empty($post->post_excerpt)) {
        $excerpt = $post->post_excerpt;
    } else {
        $excerpt = $post->post_content;
    }
    
    $excerpt = strip_shortcodes($excerpt);
    $excerpt = wp_strip_all_tags($excerpt);
    $excerpt = trim($excerpt);
    
    if (mb_strlen($excerpt) > $length) {
        $excerpt = mb_substr($excerpt, 0, $length) . '...';
    }
    
    return $excerpt;
}

// 字数统计（过滤代码块）
function mimosa_count_words($content) {
    if (empty($content)) return 0;
    $content = preg_replace('/```[\s\S]*?```/', '', $content);
    $content = preg_replace('/<pre[\s\S]*?<\/pre>/i', '', $content);
    $content = preg_replace('/<code[\s\S]*?<\/code>/i', '', $content);
    $content = strip_tags($content);
    return mb_strlen(trim($content));
}

// 预计阅读时间（分钟）
function mimosa_reading_time($content) {
    $content = strip_shortcodes($content);
    $content = strip_tags($content);
    
    // 中文字符
    preg_match_all('/[\x{4e00}-\x{9fa5}]/u', $content, $cn_matches);
    $cn_count = count($cn_matches[0]);
    
    // 英文单词
    $en_content = preg_replace('/[\x{4e00}-\x{9fa5}]/u', '', $content);
    $en_count = str_word_count($en_content);
    
    $minutes = ($cn_count / 300) + ($en_count / 160);
    
    if ($minutes < 1) return __('1 分钟内', 'mimosa');
    return ceil($minutes) . ' ' . __('分钟', 'mimosa');
}

// 分页链接（兼容所有平台）
function mimosa_paginate_links($max_num_pages = 0) {
    if ($max_num_pages == 0) {
        global $wp_query;
        $max_num_pages = $wp_query->max_num_pages;
    }
    
    if ($max_num_pages <= 1) return '';
    
    $paged = max(1, get_query_var('paged'), get_query_var('page'));
    
    $args = array(
        'base'      => str_replace(99999, '%#%', esc_url(get_pagenum_link(99999))),
        'format'    => '?paged=%#%',
        'current'   => $paged,
        'total'     => $max_num_pages,
        'prev_text' => '<i class="icon-chevron-left"></i>',
        'next_text' => '<i class="icon-chevron-right"></i>',
        'type'      => 'array',
        'show_all'  => false,
        'end_size'  => 1,
        'mid_size'  => 2,
    );
    
    $links = paginate_links($args);
    if (empty($links)) return '';
    
    $html = '<nav class="mimosa-pagination" aria-label="分页导航"><ul>';
    foreach ($links as $link) {
        if (strpos($link, 'current') !== false) {
            $html .= '<li class="active">' . $link . '</li>';
        } else {
            $html .= '<li>' . $link . '</li>';
        }
    }
    $html .= '</ul></nav>';
    
    return $html;
}

// 自定义分页（用于混排流）
function mimosa_custom_paginate($total, $per_page, $paged) {
    $total_pages = $per_page > 0 ? ceil($total / $per_page) : 1;
    if ($total_pages <= 1) return '';

    $base_url = get_pagenum_link(1);
    
    $html = '<nav class="mimosa-pagination" aria-label="分页导航"><ul>';
    
    if ($paged > 1) {
        $html .= '<li><a href="' . esc_url(get_pagenum_link($paged - 1)) . '" aria-label="上一页"><i class="icon-chevron-left"></i></a></li>';
    }
    
    $from = max(1, $paged - 2);
    $to   = min($total_pages, $paged + 2);
    
    if ($from > 1) {
        $html .= '<li><a href="' . esc_url(get_pagenum_link(1)) . '">1</a></li>';
        if ($from > 2) {
            $html .= '<li class="dots"><span>…</span></li>';
        }
    }
    
    for ($i = $from; $i <= $to; $i++) {
        if ($i == $paged) {
            $html .= '<li class="active"><span>' . $i . '</span></li>';
        } else {
            $html .= '<li><a href="' . esc_url(get_pagenum_link($i)) . '">' . $i . '</a></li>';
        }
    }
    
    if ($to < $total_pages) {
        if ($to < $total_pages - 1) {
            $html .= '<li class="dots"><span>…</span></li>';
        }
        $html .= '<li><a href="' . esc_url(get_pagenum_link($total_pages)) . '">' . $total_pages . '</a></li>';
    }
    
    if ($paged < $total_pages) {
        $html .= '<li><a href="' . esc_url(get_pagenum_link($paged + 1)) . '" aria-label="下一页"><i class="icon-chevron-right"></i></a></li>';
    }
    
    $html .= '</ul></nav>';
    return $html;
}

// Gravatar CDN（可后台自定义镜像源）
function mimosa_gravatar_cdn() {
    $cdn = trim((string) get_option('mimosa_gravatar_cdn', 'https://cravatar.com'));
    if ($cdn === '') {
        $cdn = 'https://secure.gravatar.com';
    }
    return rtrim($cdn, '/');
}

// 将任何 gravatar 官方域重写为自定义镜像（作用于 get_avatar / get_avatar_url）
function mimosa_rewrite_gravatar_url($value) {
    $cdn = mimosa_gravatar_cdn();
    if (!$value || !is_string($value)) {
        return $value;
    }
    return preg_replace('#https?://(?:[a-z0-9.-]+\.)*gravatar\.com(/avatar/)#i', $cdn . '$1', $value);
}
add_filter('get_avatar', 'mimosa_rewrite_gravatar_url', 20);
add_filter('get_avatar_url', 'mimosa_rewrite_gravatar_url', 20);

// Gravatar URL
function mimosa_get_avatar_url($email, $size = 80) {
    $hash = md5(strtolower(trim($email)));
    $default = urlencode('mm');
    return mimosa_gravatar_cdn() . "/avatar/{$hash}?s={$size}&d={$default}";
}

/* ── 站点公告（首页副栏第一个卡片，设置在设置页编辑） ── */
function brilliance_get_site_notice_text() {
    return trim((string) get_option('brilliance_site_notice', ''));
}

// 首页副栏是否激活：有可用小工具，或已设置公告内容
function brilliance_home_sidebar_is_active() {
    return get_option('brilliance_home_sidebar_enable', 'no') === 'yes'
        && (is_active_sidebar('home-sidebar') || brilliance_get_site_notice_text() !== '');
}

// 渲染站点公告卡片（公告非空才输出）
function brilliance_render_site_notice() {
    $text = brilliance_get_site_notice_text();
    if ($text === '') {
        return;
    }
    ?>
    <section class="widget ">
        <h2 class="widget-title"><?php _e('站点公告', 'mimosa'); ?></h2>
        <div class="notice-widget__content"><?php echo wp_kses_post($text); ?></div>
    </section>
    <?php
}

// 判断是否有目录（文章内含 h2/h3）
function mimosa_has_toc() {
    if (!is_single() && !is_page()) return false;
    if (post_password_required()) return false;
    
    $content = get_post()->post_content;
    return (bool) preg_match('/<h[23][^>]*>/i', $content);
}

/* ── 特色标题图：用图片替换详情页文字标题 ── */
function brilliance_get_feature_title_image($post_id = 0) {
    $post_id = $post_id ? (int) $post_id : (int) get_the_ID();
    if ($post_id <= 0) return '';
    $attachment_id = (int) get_post_meta($post_id, 'mimosa_feature_title_image_id', true);
    if ($attachment_id <= 0) return '';
    if (!wp_attachment_is_image($attachment_id)) return '';
    return wp_get_attachment_image_url($attachment_id, 'full');
}

/* ── 颜色工具：hex -> "r, g, b"（供 CSS rgba(var(--xxx-rgb))） ── */
function mimosa_hex_to_rgb($hex) {
    $hex = ltrim((string) $hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    if (strlen($hex) !== 6) return '124, 106, 247';
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    return $r . ', ' . $g . ', ' . $b;
}

/* ── 相似文章推荐 ── */
function mimosa_related_posts_enabled() {
    return get_option('mimosa_related_enable', 'yes') === 'yes';
}

function mimosa_get_related_posts($post_id, $count = 6) {
    $count = max(1, min(12, (int) $count));
    $basis = get_option('mimosa_related_basis', 'category');
    if ($basis === 'tag') {
        $terms = get_the_tags($post_id);
        $tax = 'post_tag';
    } else {
        $terms = get_the_category($post_id);
        $tax = 'category';
    }
    if (empty($terms) || is_wp_error($terms)) return array();
    $term_ids = wp_list_pluck($terms, 'term_id');

    return get_posts(array(
        'post__not_in'       => array((int) $post_id),
        'posts_per_page'     => $count,
        'orderby'            => 'date',
        'order'              => 'DESC',
        'no_found_rows'      => true,
        'ignore_sticky_posts'=> true,
        'tax_query'          => array(array(
            'taxonomy' => $tax,
            'field'    => 'term_id',
            'terms'    => array_map('intval', $term_ids),
        )),
    ));
}

function mimosa_render_related_posts($post_id) {
    if (!mimosa_related_posts_enabled()) return;
    $count = (int) get_option('mimosa_related_count', 6);
    $related = mimosa_get_related_posts($post_id, $count);
    if (empty($related)) return;
    ?>
    <section class="related-posts">
        <h2 class="related-posts__title">相关推荐</h2>
        <div class="related-posts__grid">
            <?php foreach ($related as $rp):
                $rp_url   = get_permalink($rp->ID);
                $rp_title = get_the_title($rp->ID);
                $rp_thumb = get_the_post_thumbnail_url($rp->ID, 'medium');
            ?>
            <a class="related-card" href="<?php echo esc_url($rp_url); ?>">
                <?php if ($rp_thumb): ?>
                <span class="related-card__bg" style="background-image:url('<?php echo esc_url($rp_thumb); ?>')"></span>
                <span class="related-card__cover"><img src="<?php echo esc_url($rp_thumb); ?>" alt="<?php echo esc_attr($rp_title); ?>" loading="lazy"></span>
                <?php endif; ?>
                <span class="related-card__title"><?php echo esc_html($rp_title); ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php
}

/* ── 文章底部说明卡片（许可协议等） ── */
function mimosa_post_extra_enabled() {
    return get_option('mimosa_post_extra_enable', 'yes') === 'yes';
}

function mimosa_post_extra_license_text() {
    $text = trim((string) get_option('mimosa_post_extra_license', ''));
    if ($text === '') {
        $text = '许可协议：<a href="https://creativecommons.org/licenses/by-sa/4.0/deed.zh" target="_blank" rel="noopener">CC BY-SA 4.0</a>';
    }
    return $text;
}

/* ── 详情页目录卡片（桌面页内侧栏 + 移动端菜单展开区 复用） ── */
function brilliance_render_detail_toc_card($for_mobile = false) {
    $box_class = $for_mobile ? 'index-box--mobile' : 'index-box';
    ?>
    <div class="detail-sidebar__card">
        <h2 class="detail-sidebar__title"><?php _e('目录', 'mimosa'); ?></h2>
        <div class="brilliance-toc-scroll">
            <div class="<?php echo esc_attr($box_class); ?>"></div>
        </div>
    </div>
    <?php
}

/* ── 详情页侧栏（桌面端页内，仅显示文章目录） ── */
function brilliance_render_detail_sidebar() {
    if (!function_exists('mimosa_has_toc') || !mimosa_has_toc()) {
        return;
    }
    ?>
    <aside class="detail-sidebar" role="complementary" aria-label="<?php esc_attr_e('文章目录', 'mimosa'); ?>">
        <?php brilliance_render_detail_toc_card(); ?>
    </aside>
    <?php
}

// 生成文章目录数据
function mimosa_get_toc() {
    if (!is_single() && !is_page()) return array();
    if (post_password_required()) return array();
    
    $content = apply_filters('the_content', get_post()->post_content);
    preg_match_all('/<h([23])[^>]*id=["\']([^"\']+)["\'][^>]*>(.*?)<\/h[23]>/is', $content, $matches);
    
    if (empty($matches[0])) {
        // 尝试无 id 的 heading
        preg_match_all('/<h([23])[^>]*>(.*?)<\/h[23]>/is', $content, $matches);
        $toc = array();
        foreach ($matches[0] as $i => $heading) {
            $level = intval($matches[1][$i]);
            $text  = strip_tags($matches[2][$i]);
            $toc[] = array(
                'level' => $level,
                'text'  => $text,
                'id'    => sanitize_title($text),
            );
        }
        return $toc;
    }
    
    $toc = array();
    foreach ($matches[0] as $i => $heading) {
        $level = intval($matches[1][$i]);
        $id    = $matches[2][$i];
        $text  = strip_tags($matches[3][$i]);
        $toc[] = array(
            'level' => $level,
            'text'  => $text,
            'id'    => $id,
        );
    }
    
    return $toc;
}

// 为文章内容中的标题自动添加 id 锚点
function mimosa_add_heading_ids($content) {
    return preg_replace_callback('/<h([23])([^>]*)>(.*?)<\/h[23]>/is', function ($m) {
        $level = $m[1];
        $attrs = $m[2];
        $text  = $m[3];
        
        if (preg_match('/id=["\'][^"\']+["\']/', $attrs)) {
            return $m[0];
        }
        
        $id = sanitize_title(strip_tags($text));
        return "<h{$level}{$attrs} id=\"{$id}\">{$text}</h{$level}>";
    }, $content);
}
add_filter('the_content', 'mimosa_add_heading_ids', 15);

// SEO description
function mimosa_get_seo_description() {
    global $post;
    if (is_singular()) {
        if (!empty($post->post_excerpt)) {
            return wp_trim_words($post->post_excerpt, 30, '...');
        }
        if (!post_password_required()) {
            $content = strip_tags($post->post_content);
            return mb_substr(trim($content), 0, 100) . '...';
        }
        return __('这是一个加密页面', 'mimosa');
    }
    return get_option('mimosa_seo_description', get_bloginfo('description'));
}

// 获取 SEO 图片（特色图 > 文章首图 > 默认图 > 网站图标）
function brilliance_get_seo_image() {
    global $post;
    
    // 单篇内容：特色图
    if (is_singular() && has_post_thumbnail()) {
        return get_the_post_thumbnail_url(null, 'full');
    }
    
    // 单篇内容：文章首图
    if (is_singular() && !empty($post->post_content)) {
        preg_match('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $post->post_content, $matches);
        if (!empty($matches[1])) {
            return $matches[1];
        }
    }
    
    // 默认图
    $default_image = get_option('brilliance_seo_default_image', '');
    if (!empty($default_image)) {
        return $default_image;
    }
    
    // 网站图标
    $site_icon = get_site_icon_url(512);
    if (!empty($site_icon)) {
        return $site_icon;
    }
    
    return '';
}

// 输出完整的 SEO meta 标签
function brilliance_output_seo_meta() {
    global $post;
    
    $site_name = get_option('brilliance_seo_site_name', get_bloginfo('name'));
    $description = mimosa_get_seo_description();
    $image = brilliance_get_seo_image();
    $url = is_singular() ? get_permalink() : home_url(add_query_arg(null, null));
    
    // 标题
    if (is_singular()) {
        $title = get_the_title();
        $full_title = $title . ' - ' . $site_name;
    } elseif (is_home() || is_front_page()) {
        $title = $site_name;
        $subtitle = get_bloginfo('description');
        $full_title = !empty($subtitle) ? $site_name . ' - ' . $subtitle : $site_name;
    } elseif (is_category()) {
        $title = single_cat_title('', false);
        $full_title = $title . ' - ' . $site_name;
    } elseif (is_tag()) {
        $title = single_tag_title('', false);
        $full_title = $title . ' - ' . $site_name;
    } elseif (is_archive()) {
        $title = get_the_archive_title();
        $full_title = $title . ' - ' . $site_name;
    } else {
        $title = $site_name;
        $full_title = $site_name;
    }
    
    // 基础 meta
    echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
    
    // Keywords
    $keywords = trim(get_option('brilliance_seo_keywords', ''));
    if ($keywords !== '') {
        echo '<meta name="keywords" content="' . esc_attr($keywords) . '">' . "\n";
    }
    
    // Open Graph
    echo '<!-- Open Graph -->' . "\n";
    echo '<meta property="og:type" content="' . (is_singular() ? 'article' : 'website') . '">' . "\n";
    echo '<meta property="og:title" content="' . esc_attr($title) . '">' . "\n";
    echo '<meta property="og:description" content="' . esc_attr($description) . '">' . "\n";
    echo '<meta property="og:url" content="' . esc_url($url) . '">' . "\n";
    echo '<meta property="og:site_name" content="' . esc_attr($site_name) . '">' . "\n";
    
    if (!empty($image)) {
        echo '<meta property="og:image" content="' . esc_url($image) . '">' . "\n";
    }
    
    if (is_singular() && isset($post->post_modified)) {
        echo '<meta property="og:updated_time" content="' . esc_attr(get_the_modified_date('c')) . '">' . "\n";
    }
    
    // Twitter Card
    $twitter_card = get_option('brilliance_seo_twitter_card', 'summary_large_image');
    $twitter_site = get_option('brilliance_seo_twitter_site', '');
    
    echo '<!-- Twitter Card -->' . "\n";
    echo '<meta name="twitter:card" content="' . esc_attr($twitter_card) . '">' . "\n";
    echo '<meta name="twitter:title" content="' . esc_attr($title) . '">' . "\n";
    echo '<meta name="twitter:description" content="' . esc_attr($description) . '">' . "\n";
    
    if (!empty($twitter_site)) {
        echo '<meta name="twitter:site" content="' . esc_attr($twitter_site) . '">' . "\n";
    }
    
    if (!empty($image)) {
        echo '<meta name="twitter:image" content="' . esc_url($image) . '">' . "\n";
    }
    
    // 文章特定 meta
    if (is_singular('post') || is_singular('shuoshuo')) {
        echo '<meta property="article:published_time" content="' . esc_attr(get_the_date('c')) . '">' . "\n";
        echo '<meta property="article:modified_time" content="' . esc_attr(get_the_modified_date('c')) . '">' . "\n";
        
        if (is_singular('post')) {
            $author = get_the_author();
            echo '<meta property="article:author" content="' . esc_attr($author) . '">' . "\n";
            
            $categories = get_the_category();
            if (!empty($categories)) {
                foreach ($categories as $category) {
                    echo '<meta property="article:section" content="' . esc_attr($category->name) . '">' . "\n";
                }
            }
            
            $tags = get_the_tags();
            if (!empty($tags)) {
                foreach ($tags as $tag) {
                    echo '<meta property="article:tag" content="' . esc_attr($tag->name) . '">' . "\n";
                }
            }
        }
    }
}

// 密码提示 meta box
function mimosa_register_password_hint_meta_box() {
    add_meta_box(
        'mimosa_password_hint',
        __('密码提示', 'mimosa'),
        'mimosa_render_password_hint_meta_box',
        'post',
        'side',
        'default'
    );
}
add_action('add_meta_boxes', 'mimosa_register_password_hint_meta_box');

function mimosa_render_password_hint_meta_box($post) {
    $value = get_post_meta($post->ID, 'password_hint', true);
    wp_nonce_field('mimosa_save_password_hint', 'mimosa_password_hint_nonce');
    echo '<textarea name="password_hint" style="width:100%;min-height:60px;">' . esc_textarea($value) . '</textarea>';
    echo '<p class="description">访客看到的密码提示文字。</p>';
}

function mimosa_save_password_hint_meta_box($post_id) {
    if (!isset($_POST['mimosa_password_hint_nonce'])) return;
    if (!wp_verify_nonce($_POST['mimosa_password_hint_nonce'], 'mimosa_save_password_hint')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (isset($_POST['password_hint'])) {
        update_post_meta($post_id, 'password_hint', sanitize_text_field(wp_unslash($_POST['password_hint'])));
    }
}
add_action('save_post', 'mimosa_save_password_hint_meta_box');

// RSS 中加入 shuoshuo
add_filter('pre_get_posts', function ($query) {
    if (!$query->is_main_query() || !$query->is_feed()) return $query;
    if (get_option('mimosa_rss_include_shuoshuo', 'yes') === 'yes') {
        $query->set('post_type', array('post', 'shuoshuo'));
    }
    return $query;
});

// 首页分页兼容：让 WP 主查询知道有分页
add_action('pre_get_posts', function ($q) {
    if (is_admin() || !$q->is_main_query()) return;
    if ($q->is_home()) {
        $q->set('post_type', array('post', 'shuoshuo'));
        $q->set('posts_per_page', 1);
        $q->set('ignore_sticky_posts', false);
    }
});

add_action('template_redirect', function () {
    if (is_home() && is_404()) {
        global $wp_query;
        $wp_query->is_404 = false;
        status_header(200);
    }
});
