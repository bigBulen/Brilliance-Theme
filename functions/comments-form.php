<?php
/**
 * 评论表单渲染
 */

if (!defined('ABSPATH')) exit;

/**
 * 渲染评论表单
 */
function mimosa_render_comment_form($post_id) {
    $commenter = wp_get_current_commenter();
    $req = get_option('require_name_email');
    $aria_req = ($req ? " aria-required='true' required" : '');
    ?>
    <div id="comment-form-wrap" class="comment-form-wrap">
        <h3 class="comment-form-title">发表评论</h3>
        <form id="comment-form" class="comment-form" method="post" action="<?php echo esc_url(site_url('/wp-comments-post.php')); ?>">
            <div class="comment-form-field">
                <label for="comment-content" class="screen-reader-text">评论内容</label>
                <textarea id="comment-content" name="comment" rows="5" placeholder="评论内容（支持 Markdown、BBcode）<?php echo $req ? ' *' : ''; ?>"<?php echo $aria_req; ?>></textarea>
            </div>
            <div class="comment-form-row">
                <div class="comment-form-field">
                    <label for="comment-author" class="screen-reader-text">昵称</label>
                    <input id="comment-author" name="author" type="text" placeholder="昵称<?php echo $req ? ' *' : ''; ?>" value="<?php echo esc_attr($commenter['comment_author']); ?>"<?php echo $aria_req; ?>>
                </div>
                <div class="comment-form-field">
                    <label for="comment-email" class="screen-reader-text">邮箱</label>
                    <input id="comment-email" name="email" type="email" placeholder="邮箱<?php echo $req ? ' *' : ''; ?>" value="<?php echo esc_attr($commenter['comment_author_email']); ?>"<?php echo $aria_req; ?>>
                </div>
                <div class="comment-form-field">
                    <label for="comment-url" class="screen-reader-text">网站</label>
                    <input id="comment-url" name="url" type="url" placeholder="网站（选填）" value="<?php echo esc_attr($commenter['comment_author_url']); ?>">
                </div>
            </div>
            <div class="comment-form-footer">
                <div class="comment-form-options">
                    <label class="comment-form-checkbox">
                        <input type="checkbox" id="comment-use-markdown" name="use_markdown" value="1" checked>
                        <span>使用 Markdown</span>
                    </label>
                    <?php if (mimosa_captcha_enabled()): ?>
                    <button type="button" class="comment-captcha-btn js-comment-captcha" aria-haspopup="dialog">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/></svg>
                        <span>进行人机验证</span>
                    </button>
                    <input type="hidden" id="comment-captcha-ticket" name="captcha_ticket" value="">
                    <?php endif; ?>
                    <button type="button" class="comment-emotion-btn js-comment-emotion-btn" title="表情包">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>
                    </button>
                </div>
                <button type="submit" class="comment-submit-btn">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                    <span>发送评论</span>
                </button>
            </div>
            <input type="hidden" name="comment_post_ID" value="<?php echo intval($post_id); ?>">
            <input type="hidden" name="comment_parent" id="comment-parent-id" value="0">
            <?php wp_nonce_field('mimosa_comment_nonce', 'mimosa_comment_nonce_field'); ?>
        </form>
        <div id="comment-reply-info" class="comment-reply-info" style="display:none;">
            <div class="comment-reply-info__bar">
                <span class="comment-reply-info__text">回复 <strong id="comment-reply-author"></strong> 的评论</span>
                <button type="button" class="comment-reply-cancel" id="comment-reply-cancel">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <blockquote id="comment-reply-quote" class="comment-reply-quote"></blockquote>
        </div>
        <?php get_template_part('template-parts/emotion-keyboard'); ?>

        <div class="comment-bbcode-help">
            <button type="button" class="comment-bbcode-help__toggle js-bbcode-help-toggle" aria-expanded="false">
                <span>支持的 BBCode</span>
                <svg class="comment-bbcode-help__chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
            <div class="comment-bbcode-help__list">
                <div class="comment-bbcode-help__item"><code>[annotate]术语||注释[/annotate]</code><span>悬停术语注释</span></div>
                <div class="comment-bbcode-help__item"><code>[blur]模糊文本[/blur]</code><span>高斯模糊</span></div>
                <div class="comment-bbcode-help__item"><code>[black]黑幕文本[/black]</code><span>黑幕遮挡</span></div>
                <div class="comment-bbcode-help__item"><code>[img]图片地址[/img]</code><span>内嵌图片</span></div>
                <div class="comment-bbcode-help__item"><code>[b]粗体[/b]  [i]斜体[/i]  [s]删除线[/s]  [u]下划线[/u]</code><span>文字样式</span></div>
                <div class="comment-bbcode-help__item"><code>[url=链接地址]文字[/url]</code><span>超链接</span></div>
                <div class="comment-bbcode-help__item"><code>[quote]引用内容[/quote]</code><span>引用块</span></div>
                <div class="comment-bbcode-help__item"><code>[h1]~[h5]标题[/h1]~[/h5]</code><span>多级标题</span></div>
            </div>
        </div>
    </div>
    <?php
}
