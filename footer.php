        </div><!-- .container -->
    </div><!-- .site-content-wrap -->

<footer class="site-footer" role="contentinfo">
    <div class="site-footer__inner">

        <?php
        $custom_html = get_option('mimosa_footer_custom_html', '');
        if ($custom_html):
        ?>
        <div class="site-footer__custom">
            <?php
            // 该字段仅管理员可编辑（设置页 require manage_options），保存时已按权限过滤，
            // 这里原样输出，允许内联 <script> / <style> / 自定义元素（如 <meting-js>）等正常执行。
            echo $custom_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            ?>
        </div>
        <?php endif; ?>

        <?php
        $word_count = function_exists('mimosa_get_site_word_count')
            ? mimosa_get_site_word_count()
            : '';
        if ($word_count):
        ?>
        <p class="site-footer__words"><?php echo esc_html($word_count); ?></p>
        <?php endif; ?>

        <div>Theme <a href="https://github.com/bigBulen/Brilliance-Theme" target="_blank">Brilliance</a> By Mimosa233</div>

    </div>



</footer>

</div><!-- .site-page -->





<script>
    console.log('brilliance - 一切准备就绪。');
</script>

<!-- 改写默认评论输入url提示 -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. 找到你的输入框，请修改 '.your-input-class' 为实际的 class 或 #id
    var inputField = document.querySelector('#comment-url');

    if (inputField) {
        // 2. 监听 invalid 事件（当验证失败时触发）
        inputField.addEventListener('invalid', function(e) {
            // 3. 设置自定义的提示文字
            if (this.value === '') {
                this.setCustomValidity('请输入有效的 URL 地址');
            } else {
                // 如果是因为 pattern 不匹配等其他原因，也可以在这里定义
                this.setCustomValidity('URL 格式不正确，开头须为 http(s)://');
            }
        });

        // 4. 监听 input 事件，当用户开始输入时清除自定义错误，否则错误会一直卡住
        inputField.addEventListener('input', function() {
            this.setCustomValidity('');
        });
    }
});
</script>

<?php wp_footer(); ?>
</body>
</html>
