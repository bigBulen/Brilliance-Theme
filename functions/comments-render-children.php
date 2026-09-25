<?php
/**
 * 评论渲染函数 - 第二部分：递归回复
 */

if (!defined('ABSPATH')) exit;

/**
 * 递归渲染回复
 */
function mimosa_render_comment_children($children) {
    $max_depth = (int) mimosa_get_comment_option('reply_depth', 3);
    foreach ($children as $child_data) {
        $comment = $child_data['comment'];
        $depth = $child_data['depth'];
        $comment_id = $comment->comment_ID;
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

        $parent_comment = null;
        if ($comment->comment_parent) {
            $parent_comment = get_comment($comment->comment_parent);
        }
        ?>
        <li id="comment-<?php echo intval($comment_id); ?>" class="comment-item comment-item--reply comment-depth-<?php echo intval($depth); ?> <?php echo $is_author ? 'is-author' : ''; ?>">
            <div class="comment-body">
                <div class="comment-avatar"><?php echo get_avatar($comment, 36); ?></div>
                <div class="comment-main">
                    <div class="comment-meta">
                        <?php if (!empty($comment->comment_author_url)): ?>
                        <a class="comment-author" href="<?php echo esc_url($comment->comment_author_url); ?>" target="_blank" rel="nofollow ugc noopener noreferrer"><?php echo esc_html($comment_author_display); ?></a>
                        <?php else: ?>
                        <span class="comment-author"><?php echo esc_html($comment_author_display); ?></span>
                        <?php endif; ?>
                        <?php if ($is_author): ?><span class="comment-badge comment-badge--author">作者</span><?php endif; ?>
                        <?php if ($parent_comment): ?><span class="comment-reply-to">回复 @<?php echo esc_html(trim((string) $parent_comment->comment_author) !== '' ? $parent_comment->comment_author : '匿名'); ?></span><?php endif; ?>
                        <time class="comment-date" datetime="<?php echo esc_attr(get_comment_date('c', $comment_id)); ?>"><?php echo esc_html(get_comment_date('Y-m-d H:i', $comment_id)); ?></time>
                    </div>
                    <div class="comment-content <?php echo $should_fold ? 'is-foldable' : ''; ?>"><?php echo $parsed_content; ?></div>
                    <?php if ($should_fold): ?>
                    <button class="comment-fold-toggle js-comment-fold" type="button">
                        <span>展开</span><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>
                    <?php endif; ?>
                    <div class="comment-actions">
                        <button class="comment-action comment-action--upvote js-comment-upvote" data-comment-id="<?php echo intval($comment_id); ?>" data-upvoted="false" type="button">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 9V5a3 3 0 00-3-3l-4 9v11h11.28a2 2 0 002-1.7l1.38-9a2 2 0 00-2-2.3zM7 22H4a2 2 0 01-2-2v-7a2 2 0 012-2h3"/></svg>
                            <span class="comment-upvote-count"><?php echo intval($upvotes); ?></span>
                        </button>
                        <?php if ($depth < $max_depth): ?>
                        <button class="comment-action comment-action--reply js-comment-reply" data-comment-id="<?php echo intval($comment_id); ?>" data-comment-author="<?php echo esc_attr($comment_author_display); ?>" type="button">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>回复
                        </button>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($child_data['children']) && $depth < $max_depth): ?>
                    <ol class="comment-children"><?php mimosa_render_comment_children($child_data['children']); ?></ol>
                    <?php endif; ?>
                </div>
            </div>
        </li>
        <?php
    }
}
