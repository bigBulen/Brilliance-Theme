<?php
if (!defined('ABSPATH') || !current_user_can('manage_options')) exit;
$item = is_array($editing_item) ? $editing_item : array();
?>
<div class="wrap">
    <h1>ACGN 作品管理</h1>
    <?php if (isset($_GET['updated'])) : ?><div class="notice notice-<?php echo $_GET['updated'] ? 'success' : 'error'; ?> is-dismissible"><p><?php echo $_GET['updated'] ? '作品已保存。' : '保存失败，请检查数据库。'; ?></p></div><?php endif; ?>

    <div style="display:grid;grid-template-columns:minmax(360px,1fr) minmax(520px,1.7fr);gap:24px;align-items:start">
        <section class="card" style="max-width:none">
            <h2><?php echo $item ? '编辑作品' : '添加作品'; ?></h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="apex_media_save_item">
                <input type="hidden" name="media_id" value="<?php echo (int) ($item['id'] ?? 0); ?>">
                <?php wp_nonce_field('apex_media_save_item'); ?>
                <table class="form-table" role="presentation"><tbody>
                    <tr><th><label for="media-title">标题</label></th><td><input class="regular-text" id="media-title" name="media_title" required value="<?php echo esc_attr($item['title'] ?? ''); ?>"></td></tr>
                    <tr><th><label for="media-slug">Slug</label></th><td><input class="regular-text" id="media-slug" name="media_slug" value="<?php echo esc_attr($item['slug'] ?? ''); ?>"><p class="description">留空时按标题生成，并自动避免重复。</p></td></tr>
                    <tr><th><label for="media-type">类型</label></th><td><select id="media-type" name="media_type"><option value="anime" <?php selected($item['type'] ?? '', 'anime'); ?>>番剧</option><option value="galgame" <?php selected($item['type'] ?? '', 'galgame'); ?>>视觉小说</option><option value="reading" <?php selected($item['type'] ?? '', 'reading'); ?>>阅读</option></select></td></tr>
                    <tr><th><label for="media-status">状态</label></th><td><select id="media-status" name="media_status"><option value="want" <?php selected($item['status'] ?? '', 'want'); ?>>想看/想玩/想读</option><option value="watching" <?php selected($item['status'] ?? '', 'watching'); ?>>进行中</option><option value="watched" <?php selected($item['status'] ?? '', 'watched'); ?>>已完成</option></select></td></tr>
                    <tr><th><label for="media-score">评分</label></th><td><input id="media-score" name="media_score_10" type="number" min="0" max="10" step="0.1" value="<?php echo esc_attr(isset($item['score_10']) ? $item['score_10'] : ''); ?>"></td></tr>
                    <tr><th>本命作</th><td><label><input type="checkbox" name="media_honmei" value="1" <?php checked(!empty($item['honmei'])); ?>> 标记为本命作</label><p class="description">完全对上个人电波的作品，综合质量不一定最好，却能完全折服内心。评分排序时置顶显示。</p></td></tr>
                    <tr><th><label for="media-year">年份/季度</label></th><td><input id="media-year" name="media_year" type="number" min="1900" max="2200" value="<?php echo esc_attr($item['year'] ?? ''); ?>"> <select name="media_quarter"><option value="">未设置</option><option value="spring" <?php selected($item['quarter'] ?? '', 'spring'); ?>>春季</option><option value="summer" <?php selected($item['quarter'] ?? '', 'summer'); ?>>夏季</option><option value="autumn" <?php selected($item['quarter'] ?? '', 'autumn'); ?>>秋季</option><option value="winter" <?php selected($item['quarter'] ?? '', 'winter'); ?>>冬季</option></select></td></tr>
                    <tr><th><label for="media-season">放送/发售</label></th><td><input class="regular-text" id="media-season" name="media_season_text" value="<?php echo esc_attr($item['season_text'] ?? ''); ?>"></td></tr>
                    <tr><th><label for="media-finished">完成日期</label></th><td><input id="media-finished" name="media_finished_at" type="date" value="<?php echo esc_attr(!empty($item['finished_at']) && $item['finished_at'] !== '0000-00-00 00:00:00' ? substr($item['finished_at'], 0, 10) : ''); ?>"></td></tr>
                    <tr><th><label for="media-bgm">资料地址</label></th><td><input class="large-text" id="media-bgm" name="media_bgm_url" type="url" value="<?php echo esc_attr($item['bgm_url'] ?? ''); ?>"></td></tr>
                    <tr><th><label for="media-cover">封面地址</label></th><td><input class="large-text" id="media-cover" name="media_cover_source_url" type="url" value="<?php echo esc_attr($item['cover_source_url'] ?? ''); ?>"></td></tr>
                    <tr><th><label for="media-review">评价</label></th><td><textarea class="large-text" id="media-review" name="media_review" rows="8"><?php echo esc_textarea($item['review'] ?? ''); ?></textarea><p class="description">显示在作品条目中的评价。支持BBcode与部分HTML</p></td></tr>
                    <tr><th><label for="media-impression">印象语</label></th><td><input class="large-text" id="media-impression" name="media_impression" maxlength="40" value="<?php echo esc_attr($item['impression_text'] ?? ''); ?>"><p class="description">一句话记忆（最多 40 字，建议少于14字）。留空则该作品不会出现在「印象集」页面中。</p></td></tr>
                    <tr><th>首页混排</th><td><label><input type="checkbox" name="media_show_on_home_feed" value="1" <?php checked(!empty($item['show_on_home_feed'])); ?>> 在首页展示作品卡片</label></td></tr>
                </tbody></table>
                <?php submit_button($item ? '保存修改' : '添加作品'); ?>
                <?php if ($item) : ?><a class="button" href="<?php echo esc_url(add_query_arg('page', 'apex-media', admin_url('admin.php'))); ?>">取消编辑</a><?php endif; ?>
            </form>
        </section>

        <section>
            <h2>现有作品 <span class="count">（<?php echo count($admin_items); ?>）</span></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead><tr><th>标题</th><th style="width:80px">类型</th><th style="width:80px">状态</th><th style="width:70px">评分</th><th style="width:90px">首页</th><th style="width:150px">操作</th></tr></thead>
                <tbody>
                <?php if (!$admin_items) : ?><tr><td colspan="6">暂无作品。</td></tr><?php endif; ?>
                <?php foreach ($admin_items as $row) :
                    $edit_url = add_query_arg(array('page' => 'apex-media', 'media_id' => (int) $row['id']), admin_url('admin.php'));
                    $delete_url = wp_nonce_url(add_query_arg(array('action' => 'apex_media_delete_item', 'media_id' => (int) $row['id']), admin_url('admin-post.php')), 'apex_media_delete_' . (int) $row['id']);
                    ?>
                    <tr><td><strong><?php echo esc_html($row['title']); ?></strong><?php if (!empty($row['honmei'])): ?> <span class="apex-admin-honmei" title="本命作">（★本命作）</span><?php endif; ?><br><code><?php echo esc_html($row['slug']); ?></code></td><td><?php echo esc_html($this->map_media_type_to_label($row['type'])); ?></td><td><?php echo esc_html($row['status']); ?></td><td><?php echo esc_html(number_format($this->resolve_score_10_from_row($row), 1)); ?></td><td><?php echo empty($row['show_on_home_feed']) ? '否' : '是'; ?></td><td><a class="button button-small" href="<?php echo esc_url($edit_url); ?>">编辑</a> <a class="button button-small" href="<?php echo esc_url($delete_url); ?>" onclick="return confirm('确定删除这个作品？');">删除</a></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    </div>
</div>
