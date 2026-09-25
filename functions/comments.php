<?php
/**
 * Mimosa 评论系统核心函数
 */

if (!defined('ABSPATH')) exit;

/**
 * 获取评论设置
 */
function mimosa_get_comment_option($key, $default = '') {
    $options = array(
        'order' => 'desc', // desc=最新在上, asc=最旧在上
        'per_page' => 15,
        'fold_threshold' => 200, // 超过多少字折叠
        'reply_depth' => 3, // 最多几层嵌套
    );
    $value = get_option('mimosa_comment_' . $key, $options[$key] ?? $default);
    return $value;
}

/**
 * 获取排序后的评论（包含置顶逻辑）
 */
function mimosa_get_comments_sorted($post_id, $paged = 1) {
    $per_page = max(1, (int) mimosa_get_comment_option('per_page', 15));
    // 评论首页固定为最新评论优先，避免历史设置将默认页切回最早评论。
    $order = 'desc';
    
    // 获取所有根评论
    $root_comments = get_comments(array(
        'post_id' => $post_id,
        'parent' => 0,
        'status' => 'approve',
        'order' => strtoupper($order),
        'orderby' => 'comment_date_gmt',
    ));
    
    // 分离置顶和普通评论
    $pinned = array();
    $normal = array();
    
    foreach ($root_comments as $comment) {
        if (get_comment_meta($comment->comment_ID, 'pinned', true) === 'true') {
            $pinned[] = $comment;
        } else {
            $normal[] = $comment;
        }
    }
    
    // 置顶评论按时间排序（最新在前）
    usort($pinned, function($a, $b) {
        return strtotime($b->comment_date_gmt) - strtotime($a->comment_date_gmt);
    });
    
    $pinned_count = count($pinned);
    $first_page_normal = max(0, $per_page - $pinned_count);

    // 第一页放置全部置顶评论与剩余空间内的普通评论；普通评论维持当前排序。
    if ($paged === 1) {
        $page_comments = array_merge($pinned, array_slice($normal, 0, $first_page_normal));
    } else {
        $offset = $first_page_normal + ($paged - 2) * $per_page;
        $page_comments = array_slice($normal, $offset, $per_page);
    }

    $remaining_normal = max(0, count($normal) - $first_page_normal);
    $total_pages = count($pinned) + count($normal) > 0
        ? 1 + (int) ceil($remaining_normal / $per_page)
        : 1;

    return array(
        'comments' => $page_comments,
        'total' => count($pinned) + count($normal),
        'total_pages' => $total_pages,
        'pinned_count' => $pinned_count,
    );
}

/**
 * 递归获取评论树（包含回复）
 */
function mimosa_get_comment_tree($comment_id, $current_depth = 1) {
    $max_depth = (int) mimosa_get_comment_option('reply_depth', 3);
    $order = mimosa_get_comment_option('order', 'desc');
    
    $children = get_comments(array(
        'parent' => $comment_id,
        'status' => 'approve',
        'order' => strtoupper($order),
        'orderby' => 'comment_date_gmt',
    ));
    
    $tree = array();
    foreach ($children as $child) {
        $tree[] = array(
            'comment' => $child,
            'depth' => $current_depth,
            'children' => ($current_depth < $max_depth) ? mimosa_get_comment_tree($child->comment_ID, $current_depth + 1) : array(),
        );
    }
    
    return $tree;
}

/**
 * 渲染评论中的贴纸图片（!sticker[code]）
 */
function mimosa_render_comment_stickers($text) {
    if (!is_string($text) || strpos($text, '!sticker[') === false) {
        return $text;
    }
    return preg_replace_callback('/!sticker\[([^\]]+)\]/', function ($matches) {
        $code = $matches[1];
        global $mimosa_emotion_list;
        foreach ($mimosa_emotion_list as $group) {
            foreach ($group['list'] as $emotion) {
                if ($emotion['type'] === 'sticker' && $emotion['code'] === $code) {
                    return '<img src="' . esc_url($emotion['src']) . '" alt="' . esc_attr($code) . '" class="comment-sticker" style="max-width:120px;height:auto;vertical-align:middle;" loading="lazy">';
                }
            }
        }
        return $matches[0]; // 找不到就保持原样
    }, $text);
}

/**
 * 解析 Markdown（使用内置解析器）
 */
function mimosa_parse_comment_markdown($text) {
    $parser = new Mimosa_Markdown();
    $parsed = $parser->parse($text);
    
    // 解析表情包语法 !sticker[code]
    $parsed = mimosa_render_comment_stickers($parsed);
    
    return $parsed;
}

/**
 * 获取点赞数
 */
function mimosa_get_comment_upvotes($comment_id) {
    return (int) get_comment_meta($comment_id, 'upvotes', true);
}

/**
 * AJAX: 点赞评论
 */
function mimosa_ajax_upvote_comment() {
    check_ajax_referer('mimosa_comment_nonce', 'nonce');
    
    $comment_id = (int) ($_POST['comment_id'] ?? 0);
    if (!$comment_id || !get_comment($comment_id)) {
        wp_send_json_error('Invalid comment');
    }
    
    $current = (int) get_comment_meta($comment_id, 'upvotes', true);
    $new_count = $current + 1;
    update_comment_meta($comment_id, 'upvotes', $new_count);
    
    wp_send_json_success(array('count' => $new_count));
}
add_action('wp_ajax_mimosa_upvote_comment', 'mimosa_ajax_upvote_comment');
add_action('wp_ajax_nopriv_mimosa_upvote_comment', 'mimosa_ajax_upvote_comment');

/**
 * AJAX: 置顶/取消置顶评论（仅管理员）
 */
function mimosa_ajax_pin_comment() {
    check_ajax_referer('mimosa_comment_nonce', 'nonce');
    
    if (!current_user_can('moderate_comments')) {
        wp_send_json_error('Permission denied');
    }
    
    $comment_id = (int) ($_POST['comment_id'] ?? 0);
    $action = sanitize_key($_POST['pin_action'] ?? 'pin');
    
    if (!$comment_id || !get_comment($comment_id)) {
        wp_send_json_error('Invalid comment');
    }
    
    if ($action === 'pin') {
        update_comment_meta($comment_id, 'pinned', 'true');
        wp_send_json_success(array('status' => 'pinned'));
    } else {
        delete_comment_meta($comment_id, 'pinned');
        wp_send_json_success(array('status' => 'unpinned'));
    }
}
add_action('wp_ajax_mimosa_pin_comment', 'mimosa_ajax_pin_comment');

/**
 * AJAX: 提交评论
 */
function mimosa_ajax_submit_comment() {
    check_ajax_referer('mimosa_comment_nonce', 'nonce');
    
    $comment_post_ID = (int) ($_POST['comment_post_ID'] ?? 0);
    $comment_parent = (int) ($_POST['comment_parent'] ?? 0);
    $author = sanitize_text_field(wp_unslash($_POST['author'] ?? ''));
    $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
    $url = esc_url_raw(wp_unslash($_POST['url'] ?? ''));
    $content = trim(wp_unslash($_POST['comment'] ?? ''));
    $use_markdown = !empty($_POST['use_markdown']);
    
    if (!$comment_post_ID || !$content) {
        wp_send_json_error('缺少必填字段');
    }
    
    // 检查必填项
    $require = get_option('require_name_email');
    if ($require && (!$author || !$email)) {
        wp_send_json_error('请填写昵称和邮箱');
    }

    if (mimosa_captcha_enabled()) {
        $captcha_ticket = sanitize_text_field(wp_unslash($_POST['captcha_ticket'] ?? ''));
        if (!mimosa_captcha_consume_ticket($captcha_ticket)) {
            wp_send_json_error('请先完成角色发色验证');
        }
    }
    
    $commentdata = array(
        'comment_post_ID' => $comment_post_ID,
        'comment_parent' => $comment_parent,
        'comment_author' => $author,
        'comment_author_email' => $email,
        'comment_author_url' => $url,
        'comment_content' => $content,
        'comment_type' => 'comment',
        'comment_author_IP' => $_SERVER['REMOTE_ADDR'] ?? '',
        'comment_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'comment_date' => current_time('mysql'),
        'comment_approved' => 1,
    );
    
    $comment_id = wp_insert_comment($commentdata);
    
    if ($comment_id) {
        // 保存 Markdown 原文
        if ($use_markdown) {
            add_comment_meta($comment_id, 'comment_markdown_source', $content, true);
            add_comment_meta($comment_id, 'use_markdown', 'true', true);
        }
        
        // 触发邮件通知
        do_action('mimosa_comment_posted', $comment_id, $commentdata);
        
        wp_send_json_success(array(
            'comment_id' => $comment_id,
            'message' => '评论已发布',
        ));
    } else {
        wp_send_json_error('评论发布失败');
    }
}
add_action('wp_ajax_mimosa_submit_comment', 'mimosa_ajax_submit_comment');
add_action('wp_ajax_nopriv_mimosa_submit_comment', 'mimosa_ajax_submit_comment');

/**
 * 邮件通知：回复通知
 */
function mimosa_comment_email_notify($comment_id, $commentdata) {
    $comment = get_comment($comment_id);
    $parent_id = $comment->comment_parent;
    
    // 如果是回复评论，通知被回复者
    if ($parent_id) {
        $parent_comment = get_comment($parent_id);
        $parent_email = $parent_comment->comment_author_email;
        
        // 不给自己发邮件
        if ($parent_email && $parent_email !== $comment->comment_author_email) {
            $post = get_post($comment->comment_post_ID);
            $post_link = get_permalink($post->ID) . '#comment-' . $comment_id;
            
            $subject = sprintf('[%s] 您的评论收到了新回复', get_bloginfo('name'));
            
            $reply_author = trim((string) $parent_comment->comment_author) !== '' ? $parent_comment->comment_author : '访客';
            $reply_text = mimosa_render_comment_stickers(esc_html($comment->comment_content));

            $message  = '<div style="max-width:560px;margin:24px auto;font-family:Helvetica,Arial,sans-serif;color:#2d3142;line-height:1.6;">';
            $message .= '<div style="background:#2d3142;color:#ffffff;padding:16px 24px;border-radius:12px 12px 0 0;font-size:15px;">';
            $message .= esc_html(get_bloginfo('name')) . ' &middot; 评论回复通知';
            $message .= '</div>';
            $message .= '<div style="background:#ffffff;padding:24px;border-radius:0 0 12px 12px;-webkit-border-radius:0 0 12px 12px;border:1px solid #e6e8ee;border-top:none;">';
            $message .= '<p style="margin:0 0 12px;">您好 ' . esc_html($reply_author) . '，</p>';
            $message .= '<p style="margin:0 0 16px;color:#6c7080;">您在《' . esc_html($post->post_title) . '》下的评论收到了新回复：</p>';
            $message .= '<div style="border-left:3px solid #7c6af7;padding:12px 16px;background:#f6f6fb;border-radius:6px;margin-bottom:20px;">';
            $message .= nl2br($reply_text);
            $message .= '</div>';
            $message .= '<p style="margin:0;"><a href="' . esc_url($post_link) . '" style="display:inline-block;padding:10px 20px;background:#7c6af7;color:#ffffff;text-decoration:none;border-radius:8px;">查看回复 &rarr;</a></p>';
            $message .= '<p style="color:#a6abb8;font-size:12px;margin-top:24px;">这是一封自动发送的邮件，请勿直接回复。</p>';
            $message .= '</div></div>';

            $headers = array('Content-Type: text/html; charset=UTF-8');
            wp_mail($parent_email, $subject, $message, $headers);
        }
    }
    
    // 通知站长（如果设置了管理员邮箱）
    $admin_email = get_option('admin_email');
    if ($admin_email && $admin_email !== $comment->comment_author_email) {
        $post = get_post($comment->comment_post_ID);
        $post_link = get_permalink($post->ID) . '#comment-' . $comment_id;
        
        $subject = sprintf('[%s] 新评论通知', get_bloginfo('name'));
        
        $message = sprintf(
            "您的网站收到了新评论：\n\n作者：%s\n文章：《%s》\n内容：%s\n\n查看评论：%s\n\n",
            $comment->comment_author,
            $post->post_title,
            wp_strip_all_tags($comment->comment_content),
            $post_link
        );
        
        $headers = array('Content-Type: text/plain; charset=UTF-8');
        wp_mail($admin_email, $subject, $message, $headers);
    }
}
add_action('mimosa_comment_posted', 'mimosa_comment_email_notify', 10, 2);

/**
 * AJAX: 编辑评论
 */
function mimosa_ajax_edit_comment() {
    check_ajax_referer('mimosa_comment_nonce', 'nonce');
    
    $comment_id = (int) ($_POST['comment_id'] ?? 0);
    $new_content = trim(wp_unslash($_POST['comment'] ?? ''));
    
    if (!$comment_id || !$new_content) {
        wp_send_json_error('缺少必填字段');
    }
    
    $comment = get_comment($comment_id);
    if (!$comment) {
        wp_send_json_error('评论不存在');
    }
    
    // 权限检查：只有评论作者或管理员可以编辑
    $current_user = wp_get_current_user();
    $is_author = false;
    
    if ($current_user->ID > 0 && $comment->user_id > 0) {
        $is_author = $current_user->ID == $comment->user_id;
    } else {
        // 未登录用户，通过邮箱验证
        $commenter = wp_get_current_commenter();
        $is_author = $commenter['comment_author_email'] === $comment->comment_author_email;
    }
    
    if (!$is_author && !current_user_can('moderate_comments')) {
        wp_send_json_error('无权限编辑此评论');
    }
    
    // 保存编辑历史
    $history = get_comment_meta($comment_id, 'edit_history', true);
    if (!is_array($history)) {
        $history = array();
    }
    
    $history[] = array(
        'content' => $comment->comment_content,
        'time' => current_time('mysql'),
        'editor' => $current_user->ID > 0 ? $current_user->display_name : $comment->comment_author,
    );
    
    update_comment_meta($comment_id, 'edit_history', $history);
    
    // 更新评论内容
    $updated = wp_update_comment(array(
        'comment_ID' => $comment_id,
        'comment_content' => $new_content,
    ));
    
    // 如果使用 Markdown，更新原文
    $use_markdown = !empty($_POST['use_markdown']);
    if ($use_markdown) {
        update_comment_meta($comment_id, 'comment_markdown_source', $new_content);
        update_comment_meta($comment_id, 'use_markdown', 'true');
    }
    
    if ($updated) {
        wp_send_json_success(array(
            'message' => '评论已更新',
            'edit_count' => count($history),
        ));
    } else {
        wp_send_json_error('更新失败');
    }
}
add_action('wp_ajax_mimosa_edit_comment', 'mimosa_ajax_edit_comment');
add_action('wp_ajax_nopriv_mimosa_edit_comment', 'mimosa_ajax_edit_comment');

/**
 * AJAX: 获取编辑历史
 */
function mimosa_ajax_get_edit_history() {
    check_ajax_referer('mimosa_comment_nonce', 'nonce');
    
    $comment_id = (int) ($_POST['comment_id'] ?? 0);
    if (!$comment_id) {
        wp_send_json_error('无效的评论ID');
    }
    
    $history = get_comment_meta($comment_id, 'edit_history', true);
    if (!is_array($history) || empty($history)) {
        wp_send_json_error('暂无编辑历史');
    }
    
    wp_send_json_success(array('history' => $history));
}
add_action('wp_ajax_mimosa_get_edit_history', 'mimosa_ajax_get_edit_history');
add_action('wp_ajax_nopriv_mimosa_get_edit_history', 'mimosa_ajax_get_edit_history');

// 继续加载其他评论函数
require_once MIMOSA_THEME_DIR . '/functions/comments-render.php';
require_once MIMOSA_THEME_DIR . '/functions/comments-render-children.php';
require_once MIMOSA_THEME_DIR . '/functions/comments-form.php';
