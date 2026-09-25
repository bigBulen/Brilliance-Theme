<?php
/**
 * 字数统计相关函数
 */

if (!defined('ABSPATH')) exit;

/**
 * 过滤代码并统计字数
 */
function mimosa_count_filtered_words($content) {
    if (empty($content)) return 0;

    // 过滤代码块
    $content = preg_replace('/```.*?```/s', '', $content);
    $content = preg_replace('/<pre.*?>.*?<\\/pre>/is', '', $content);
    $content = preg_replace('/<code.*?>.*?<\\/code>/is', '', $content);
    $content = preg_replace('/`[^`]+`/', '', $content);
    $content = preg_replace('/\\[code\\].*?\\[\\/code\\]/is', '', $content);

    // 去 HTML
    $content = strip_tags($content);

    return mb_strlen(trim($content));
}

/**
 * 全站字数统计（文章 + 说说 + ACGN 评测）
 */
function mimosa_get_site_word_count() {
    global $wpdb;

    // 结果缓存
    $cache_key = 'mimosa_site_word_count_filtered';
    $cached = get_transient($cache_key);
    if ($cached !== false) {
        return $cached;
    }

    $total_words = 0;

    // 1. WordPress 文章统计
    $post_types_raw = get_option('argon_mimosa_custom_post_types', 'post,shuoshuo');
    $post_types = array_map('trim', explode(',', $post_types_raw));
    if (empty($post_types)) $post_types = ['post'];

    $placeholders = implode(',', array_fill(0, count($post_types), '%s'));

    $post_ids = $wpdb->get_col(
        $wpdb->prepare("
            SELECT ID 
            FROM {$wpdb->posts}
            WHERE post_status = 'publish'
              AND post_type IN ($placeholders)
        ", $post_types)
    );

    foreach ($post_ids as $post_id) {
        $content = get_post_field('post_content', $post_id);
        $total_words += mimosa_count_filtered_words($content);
    }

    // 2. APEX MEDIA review 统计
    $table = $wpdb->prefix . 'brilliance_acgn_items';
    $reviews = $wpdb->get_col("
        SELECT review 
        FROM {$table}
        WHERE review IS NOT NULL 
          AND review != ''
          AND status IN ('watched', 'finished', 'watching')
    ");

    foreach ($reviews as $review) {
        $total_words += mimosa_count_filtered_words($review);
    }

    // 3. 书籍映射
    $book_json = get_option('argon_mimosa_book_map', '{}');
    $book_map = json_decode($book_json, true);
    if (!is_array($book_map)) $book_map = [];

    $closest_diff = PHP_INT_MAX;
    $closest_book = '一本中篇小说';

    foreach ($book_map as $word_count => $book_name) {
        $diff = abs($total_words - (int)$word_count);
        if ($diff < $closest_diff) {
            $closest_diff = $diff;
            $closest_book = $book_name;
        }
    }

    // 未配置书籍映射字典时，提示去主题设置添加
    $book_map_hint = empty($book_map) ? '（映射表为空，请到主题设置-「字数统计（页脚）」中添加书籍字数映射表）' : '';

    $result = '本站已发布的文章字数为 '
            . number_format($total_words) 
            . ' 字，已经接近'
            . esc_html($closest_book) 
            . '的篇幅了！'
            . $book_map_hint;

    // 缓存 12 小时
    set_transient($cache_key, $result, 12 * HOUR_IN_SECONDS);

    return $result;
}
