<?php
/**
 * 评论渲染函数 - 第一部分：根评论
 */

if (!defined('ABSPATH')) exit;

/**
 * 渲染单条根评论
 */
function mimosa_render_comment($comment, $paged = 1) {
    $comment_id = $comment->comment_ID;
    $is_pinned = get_comment_meta($comment_id, 'pinned', true) === 'true' && $paged === 1;
    $is_admin = current_user_can('moderate_comments');
    $is_author = get_comment($comment_id)->user_id > 0 && get_comment($comment_id)->user_id == get_post_field('post_author', $comment->comment_post_ID);
    $upvotes = mimosa_get_comment_upvotes($comment_id);
    $comment_author_display = trim((string) $comment->comment_author) !== '' ? $comment->comment_author : '匿名';
    
    // 1. 获取 Markdown 源内容
    $markdown_source = get_comment_meta($comment_id, 'comment_markdown_source', true);

    // 2. 判断是否启用 Markdown
    $use_markdown = get_comment_meta($comment_id, 'use_markdown', true) !== 'false';

    // 3. 处理内容并应用过滤器（关键步骤：确保插件钩子被执行）
    if ($markdown_source && $use_markdown) {
        // 如果是 Markdown 评论，先解析 Markdown
        $parsed_content = mimosa_parse_comment_markdown($markdown_source);
        // 解析后再应用过滤器，这样你的地理位置 HTML 才能被追加进去
        $parsed_content = apply_filters('comment_text', $parsed_content, $comment);
        $parsed_content = wp_kses_post($parsed_content);
    } else {
        // 如果是普通评论，直接应用过滤器（包含 wpautop 等）
        $parsed_content = apply_filters('comment_text', $comment->comment_content, $comment);
        // 确保基本的安全过滤和段落格式化
        $parsed_content = wpautop(wp_kses_post($parsed_content));
    }

    // 4. 计算折叠逻辑
    $fold_threshold = (int) mimosa_get_comment_option('fold_threshold', 200);
    $content_length = mb_strlen(strip_tags($parsed_content));
    $should_fold = $content_length > $fold_threshold;

    
    ?>
    <li id="comment-<?php echo intval($comment_id); ?>" class="comment-item <?php echo $is_pinned ? 'is-pinned' : ''; ?> <?php echo $is_author ? 'is-author' : ''; ?>">
        <div class="comment-body">
            <div class="comment-avatar"><?php echo get_avatar($comment, 48); ?></div>
            <div class="comment-main">
                <div class="comment-meta">
                    <?php if (!empty($comment->comment_author_url)): ?>
                    <a class="comment-author" href="<?php echo esc_url($comment->comment_author_url); ?>" target="_blank" rel="nofollow ugc noopener noreferrer"><?php echo esc_html($comment_author_display); ?></a>
                    <?php else: ?>
                    <span class="comment-author"><?php echo esc_html($comment_author_display); ?></span>
                    <?php endif; ?>
                    <?php if ($is_author): ?><span class="comment-badge comment-badge--author">作者</span><?php endif; ?>
                    <?php if ($is_pinned): ?><span class="comment-badge comment-badge--pinned">置顶</span><?php endif; ?>
                    <time class="comment-date" datetime="<?php echo esc_attr(get_comment_date('c', $comment_id)); ?>"><?php echo esc_html(get_comment_date('Y-m-d H:i', $comment_id)); ?></time>
                </div>
                <div class="comment-content <?php echo $should_fold ? 'is-foldable' : ''; ?>" data-full-height="auto"><?php echo $parsed_content; ?></div>
                <?php if ($should_fold): ?>
                <button class="comment-fold-toggle js-comment-fold" type="button" data-text-expand="展开" data-text-collapse="收起">
                    <span>展开</span><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
                <?php endif; ?>
                <div class="comment-actions">
                    <button class="comment-action comment-action--upvote js-comment-upvote" data-comment-id="<?php echo intval($comment_id); ?>" data-upvoted="false" type="button">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 9V5a3 3 0 00-3-3l-4 9v11h11.28a2 2 0 002-1.7l1.38-9a2 2 0 00-2-2.3zM7 22H4a2 2 0 01-2-2v-7a2 2 0 012-2h3"/></svg>
                        <span class="comment-upvote-count"><?php echo intval($upvotes); ?></span>
                    </button>
                    <button class="comment-action comment-action--reply js-comment-reply" data-comment-id="<?php echo intval($comment_id); ?>" data-comment-author="<?php echo esc_attr($comment_author_display); ?>" type="button">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>回复
                    </button>
                    <?php
                    // 编辑按钮（作者或管理员可见）
                    $current_user = wp_get_current_user();
                    $commenter = wp_get_current_commenter();
                    $can_edit = false;
                    if ($current_user->ID > 0 && $comment->user_id > 0) {
                        $can_edit = $current_user->ID == $comment->user_id;
                    } elseif (!empty($commenter['comment_author_email'])) {
                        $can_edit = $commenter['comment_author_email'] === $comment->comment_author_email;
                    }
                    if ($can_edit || current_user_can('moderate_comments')):
                    ?>
                    <button class="comment-action comment-action--edit js-comment-edit" data-comment-id="<?php echo intval($comment_id); ?>" type="button">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>编辑
                    </button>
                    <?php
                    $edit_history = get_comment_meta($comment_id, 'edit_history', true);
                    if (is_array($edit_history) && !empty($edit_history)):
                    ?>
                    <button class="comment-action comment-action--history js-comment-history" data-comment-id="<?php echo intval($comment_id); ?>" type="button">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>历史(<?php echo count($edit_history); ?>)
                    </button>
                    <?php endif; ?>
                    <?php endif; ?>
                    <?php if ($is_admin): ?>
                    <button class="comment-action comment-action--pin js-comment-pin" data-comment-id="<?php echo intval($comment_id); ?>" data-pinned="<?php echo $is_pinned ? 'true' : 'false'; ?>" type="button">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 4v8l2 4H6l2-4V4h8z"/><path d="M12 20v-4"/></svg>
                        <?php echo $is_pinned ? '取消置顶' : '置顶'; ?>
                    </button>
                    <?php endif; ?>
                </div>
                <?php $children = mimosa_get_comment_tree($comment_id, 1); if ($children): ?>
                <ol class="comment-children"><?php mimosa_render_comment_children($children); ?></ol>
                <?php endif; ?>
            </div>
        </div>
    </li>
    <?php
}
