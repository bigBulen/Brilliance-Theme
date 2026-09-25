<?php
/**
 * AC/GN 作品详情页 /acgn/{slug}
 */
if (!defined('ABSPATH')) exit;

global $apex_media_list;
$slug = get_query_var('apex_media_slug');
$media = ($apex_media_list instanceof Apex_Media_List) ? $apex_media_list->media_item_get_by_slug($slug) : null;

if (!$media) {
    status_header(404);
    nocache_headers();
    include get_query_template('404');
    exit;
}

// --- 数据预处理 ---
$tags = ($apex_media_list instanceof Apex_Media_List) ? $apex_media_list->media_item_get_tags($media['id']) : [];
$score_10 = ($apex_media_list instanceof Apex_Media_List) ? $apex_media_list->resolve_score_10_from_row($media) : 0;
$grade = ($apex_media_list instanceof Apex_Media_List) ? $apex_media_list->get_grade_from_score_10($score_10) : ['label' => '', 'desc' => ''];
$type_label = ($apex_media_list instanceof Apex_Media_List) ? $apex_media_list->map_media_type_to_label($media['type'] ?? '') : ($media['type'] ?? '');
$is_honmei = !empty($media['honmei']);
$honmei_grade = ($apex_media_list instanceof Apex_Media_List) ? $apex_media_list->get_honmei_grade() : array('label' => '本命作', 'desc' => '');

$finished = (!empty($media['finished_at']) && $media['finished_at'] !== '0000-00-00 00:00:00') ? substr($media['finished_at'], 0, 10) : '';
$season_text = $media['season_text'] ?? '';
$review_html = wpautop(wp_kses_post($media['review'] ?? ''));

$cover = '';
$att_id = isset($media['cover_attachment_id']) ? intval($media['cover_attachment_id']) : 0;
if ($att_id > 0) {
    $cover = wp_get_attachment_image_url($att_id, 'full');
}
if (empty($cover) && !empty($media['cover_source_url'])) {
    $cover = $media['cover_source_url'];
}
$bgm_url = $media['bgm_url'] ?? '';

// 类型映射
$type_flag = 'ANIME';
if (($media['type'] ?? '') === 'galgame') {
    $type_flag = 'Visual Novel';
    $finished_label = '通关时间';
    $season_label = '发售日期';
    $status_map = ['watched' => '已通关', 'watching' => '进行中', 'want' => '想玩'];
} elseif (($media['type'] ?? '') === 'reading') {
    $type_flag = 'READING';
    $finished_label = '读完时间';
    $season_label = '出版日期';
    $status_map = ['watched' => '已读', 'watching' => '在读', 'want' => '想读'];
} else {
    $finished_label = '完成时间';
    $season_label = '放送日期';
    $status_map = ['watched' => '已看完', 'watching' => '进行中', 'want' => '想看'];
}

$status = $media['status'] ?? '';
$status_label = $status_map[$status] ?? '';

$year = $media['year'] ?? '';
$quarter_raw = $media['quarter'] ?? '';
$quarter_map = ['winter' => '冬季', 'spring' => '春季', 'summer' => '夏季', 'autumn' => '秋季', 'fall' => '秋季'];
$quarter = $quarter_map[strtolower($quarter_raw)] ?? $quarter_raw;

$created_at = (!empty($media['created_at']) && $media['created_at'] !== '0000-00-00 00:00:00') ? substr($media['created_at'], 0, 10) : '';
$updated_at = (!empty($media['updated_at']) && $media['updated_at'] !== '0000-00-00 00:00:00') ? substr($media['updated_at'], 0, 10) : '';

// 评分色调映射
$score_tone = 'gray';
if ($score_10 >= 9.5) $score_tone = 'purple';
elseif ($score_10 >= 9.0) $score_tone = 'gold';
elseif ($score_10 >= 8.5) $score_tone = 'blue';
elseif ($score_10 >= 7.5) $score_tone = 'green';
elseif ($score_10 >= 6.5) $score_tone = 'teal';
elseif ($score_10 >= 5.5) $score_tone = 'amber';
elseif ($score_10 > 0) $score_tone = 'rose';

$list_url = home_url('/anime/');
if (($media['type'] ?? '') === 'galgame') $list_url = home_url('/galgame/');
elseif (($media['type'] ?? '') === 'reading') $list_url = home_url('/reading/');

get_header();
?>

<div class="brilliance_acgn_container">
    <!-- Hero Section: 封面与核心信息 -->
    <div class="brilliance_acgn_hero">
        <div class="brilliance_acgn_cover_wrapper">
            <?php if (!empty($cover)): ?>
                <img class="brilliance_acgn_cover_img" src="<?php echo esc_url($cover); ?>" alt="<?php echo esc_attr($media['title'] ?? 'Cover'); ?>" loading="lazy">
            <?php else: ?>
                <div class="brilliance_acgn_cover_placeholder">暂无封面</div>
            <?php endif; ?>
        </div>

        <div class="brilliance_acgn_info">
            <div class="brilliance_acgn_topline">
                <span class="brilliance_acgn_badge brilliance_acgn_badge--primary"><?php echo esc_html($type_flag); ?></span>
                <?php if ($is_honmei): ?>
                    <span class="brilliance_acgn_badge brilliance_acgn_badge--honmei">★ 本命作</span>
                <?php endif; ?>
                <?php if (!empty($status_label)): ?>
                    <span class="brilliance_acgn_status"><?php echo esc_html($status_label); ?></span>
                <?php endif; ?>
                <?php if (!empty($finished)): ?>
                    <span class="brilliance_acgn_pill"><?php echo esc_html($finished_label . '：' . $finished); ?></span>
                <?php endif; ?>
                <?php if (!empty($season_text)): ?>
                    <span class="brilliance_acgn_pill"><?php echo esc_html($season_label . '：' . $season_text); ?></span>
                <?php endif; ?>
            </div>

            <h1 class="brilliance_acgn_title"><?php echo esc_html($media['title'] ?? ''); ?></h1>
            <?php if (!empty($media['original_title'])): ?>
                <p class="brilliance_acgn_subtitle"><?php echo esc_html($media['original_title']); ?></p>
            <?php endif; ?>

            <div class="brilliance_acgn_meta_inline">
                <?php if (!empty($year)): ?>
                    <span class="brilliance_acgn_meta_chip"><?php echo esc_html($year); ?></span>
                <?php endif; ?>
                <?php if (!empty($quarter)): ?>
                    <span class="brilliance_acgn_meta_chip"><?php echo esc_html($quarter); ?></span>
                <?php endif; ?>
                <?php if (!empty($bgm_url)): ?>
                    <a class="brilliance_acgn_meta_chip brilliance_acgn_meta_chip--link" href="<?php echo esc_url($bgm_url); ?>" target="_blank" rel="noopener noreferrer">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                        外部条目
                    </a>
                <?php endif; ?>
            </div>

            <?php if ($score_10 > 0): ?>
                <div class="brilliance_acgn_score_block brilliance_acgn_score_block--<?php echo esc_attr($score_tone); ?>">
                    <p>个人评分</p>
                    <div class="brilliance_acgn_score_main">
                        <?php echo esc_html(number_format($score_10, 1)); ?><span class="brilliance_acgn_score_suffix">/10</span>
                    </div>
                    <?php if (!empty($grade['label'])): ?>
                        <span class="brilliance_acgn_score_chip"><?php echo esc_html($grade['label']); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($grade['desc'])): ?>
                        <p class="brilliance_acgn_score_desc"><?php echo esc_html($grade['desc']); ?></p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="brilliance_acgn_score_block brilliance_acgn_score_block--gray">
                    <div class="brilliance_acgn_score_main muted">暂无个人评分</div>
                </div>
            <?php endif; ?>

            <?php if ($is_honmei): ?>
                <div class="brilliance_acgn_honmei_block">
                    <div class="brilliance_acgn_honmei_head"><?php echo esc_html($honmei_grade['label']); ?></div>
                    <?php if (!empty($honmei_grade['desc'])): ?>
                        <p class="brilliance_acgn_honmei_desc"><?php echo esc_html($honmei_grade['desc']); ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="brilliance_acgn_meta_grid">
                <?php
                $meta_items = [
                    '类型' => $type_label ?: $type_flag,
                    $season_label => $season_text,
                    $finished_label => $finished,
                    '状态' => $status_label,
                    '年份' => $year,
                    '季度' => $quarter,
                    '条目添加' => $created_at,
                    '最后更新' => $updated_at
                ];
                foreach ($meta_items as $label => $value) {
                    if (!empty($value)) {
                        echo '<div class="brilliance_acgn_meta_item">';
                        echo '<span class="brilliance_acgn_meta_label">' . esc_html($label) . '</span>';
                        echo '<span class="brilliance_acgn_meta_value">' . esc_html($value) . '</span>';
                        echo '</div>';
                    }
                }
                ?>
            </div>

            <?php if (!empty($tags)): ?>
                <div class="brilliance_acgn_tags">
                    <?php foreach ($tags as $tag): ?>
                        <span class="brilliance_acgn_tag"><?php echo esc_html($tag['name']); ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="brilliance_acgn_actions">
                <a class="brilliance_acgn_btn brilliance_acgn_btn--primary" href="<?php echo esc_url($list_url); ?>">返回作品列表</a>
                <a class="brilliance_acgn_btn brilliance_acgn_btn--outline" href="<?php echo esc_url(home_url('/')); ?>">返回首页</a>
            </div>
        </div>
    </div>

    <!-- Body Section: 评价内容 -->
    <div class="brilliance_acgn_body">
        <h2 class="brilliance_acgn_section_title">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
            评价与简评
        </h2>
        <div class="brilliance_acgn_review_content">
            <?php echo $review_html ?: '<p class="brilliance_acgn_empty_state">暂无评价内容。</p>'; ?>
        </div>
    </div>

    <div class="media-detail__review" >
            条目详情页面暂不支持讨论，请返回条目列表发布讨论。
    </div>
</div>

<?php get_footer(); ?>