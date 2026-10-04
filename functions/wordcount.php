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
    // 功能开关：关闭时直接返回空，页脚不显示
    if (get_option('brilliance_wordcount_enable', 'yes') !== 'yes') {
        return '';
    }

    // 书籍映射：为空则不显示字数统计（并跳过耗时的统计计算）
    $book_json = get_option('argon_mimosa_book_map', '{}');
    $book_map = json_decode($book_json, true);
    if (!is_array($book_map)) $book_map = [];
    if (empty($book_map)) {
        return '';
    }

    global $wpdb;

    // 只缓存耗时的「字数统计」结果（int）；书籍映射每次实时读取，
    // 这样后台改映射表立即生效，不会被 12 小时缓存挡住。
    $cache_key = 'mimosa_site_word_count_filtered';
    $total_words = get_transient($cache_key);

    if (!is_numeric($total_words)) {
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

        // 缓存 12 小时
        set_transient($cache_key, $total_words, 12 * HOUR_IN_SECONDS);
    } else {
        $total_words = (int) $total_words;
    }

    // 取最接近的书籍（书籍映射已在上方读取）
    $closest_diff = PHP_INT_MAX;
    $closest_book = '一本中篇小说';

    foreach ($book_map as $word_count => $book_name) {
        $diff = abs($total_words - (int)$word_count);
        if ($diff < $closest_diff) {
            $closest_diff = $diff;
            $closest_book = $book_name;
        }
    }

    return '本站已发布的文章字数为 '
            . number_format($total_words) 
            . ' 字，已经接近'
            . esc_html($closest_book) 
            . '的篇幅了！';
}

/**
 * 清空字数统计缓存（后台设置页「刷新缓存」按钮，AJAX）
 */
function mimosa_clear_wordcount_cache() {
    check_ajax_referer('brilliance_clear_wordcount_cache', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('无权限');
    }
    delete_transient('mimosa_site_word_count_filtered');
    wp_send_json_success('字数统计缓存已刷新');
}
add_action('wp_ajax_brilliance_clear_wordcount_cache', 'mimosa_clear_wordcount_cache');

/**
 * 「统计的文章类型」变更时自动清空缓存，避免旧结果在 12 小时内残留
 */
function mimosa_wordcount_maybe_clear_cache($old_value, $new_value) {
    if ($old_value !== $new_value) {
        delete_transient('mimosa_site_word_count_filtered');
    }
}
add_action('update_option_argon_mimosa_custom_post_types', 'mimosa_wordcount_maybe_clear_cache', 10, 2);
