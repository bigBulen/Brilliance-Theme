<?php
/**
 * 评论模板
 */

if (!defined('ABSPATH')) exit;
if (post_password_required()) return;

$post_id = get_the_ID();
$per_page = max(1, (int) mimosa_get_comment_option('per_page', 15));

// 评论页码：直接从 URL 解析（/comment-page-N/ 或 ?cpage=N）。
// 不用 get_query_var('cpage')：WP 开启评论分页时会给无 cpage 的请求注入默认评论页号，
// 导致首次进入文章却显示非第一页。
$request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
$paged = 1;
if (preg_match('#/comment-page-([0-9]{1,})/?#', $request_uri, $m)) {
    $paged = max(1, (int) $m[1]);
} elseif (isset($_GET['cpage']) && is_scalar($_GET['cpage'])) {
    $paged = max(1, intval($_GET['cpage']));
}

$comment_data = mimosa_get_comments_sorted($post_id, $paged);
$comments = $comment_data['comments'];
$total = $comment_data['total'];
$pinned_count = $comment_data['pinned_count'];
$total_pages = $comment_data['total_pages'];
$comment_count = get_comments_number($post_id);
?>

<h1>讨论区</h1>
<div id="comments" class="comments-section">

<!-- 评论审核提示START
<div class="comment-notice-box">
    <div class="notice-icon">ℹ️</div>
    <div class="notice-content">
        <strong>评论审核提示：</strong>
        近期垃圾评论泛滥，本站已启用严格的自动审查机制。若评论未能成功发送，可能是被误判为垃圾信息<del>（你是故意的还是不小心的）</del>。建议适当修改后重试，或直接给Mimosa发邮件。</br>
    </div>
</div>
 评论审核提示END -->

    <?php if ($comments): ?>
    <div class="comments-header">
        <h2 class="comments-title">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
            评论
        </h2>
        <span class="comments-count"><?php echo intval($comment_count); ?> 条</span>
    </div>

    <ol class="comment-list">
        <?php foreach ($comments as $comment): ?>
            <?php mimosa_render_comment($comment, $paged); ?>
        <?php endforeach; ?>
    </ol>

    <?php if ($total_pages > 1): ?>
    <nav class="comments-pagination" role="navigation" aria-label="评论分页">
        <?php
        global $wp_rewrite;
        $permalink_for_comments = get_permalink();
        if ($wp_rewrite instanceof WP_Rewrite && $wp_rewrite->using_permalinks()) {
            $comment_base = user_trailingslashit(trailingslashit($permalink_for_comments) . $wp_rewrite->comments_pagination_base . '-%#%', 'commentpaged');
        } else {
            $comment_base = add_query_arg('cpage', '%#%', $permalink_for_comments);
        }

        $pagination_args = array(
            'base' => $comment_base . '#comments',
            'format' => '',
            'current' => $paged,
            'total' => $total_pages,
            'prev_text' => '<span class="comments-pagination__icon" aria-hidden="true">‹</span><span class="screen-reader-text">上一页</span>',
            'next_text' => '<span class="screen-reader-text">下一页</span><span class="comments-pagination__icon" aria-hidden="true">›</span>',
        );
        echo paginate_links($pagination_args);
        ?>
    </nav>
    <?php endif; ?>

    <?php else: ?>
    <p class="comments-empty">暂无评论</p>
    <?php endif; ?>

    <?php if (comments_open()): ?>
    <?php mimosa_render_comment_form($post_id); ?>
    <?php else: ?>
    <p class="comments-closed">评论已关闭</p>
    <?php endif; ?>

</div>
