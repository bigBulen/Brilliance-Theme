<?php
/**
 * ACGN 媒体作品预览卡片（首页混排）
 * 通过 set_query_var('mimosa_media_item', $media_item) 传入数据
 */

if (!defined('ABSPATH')) exit;

global $apex_media_list;

$media_item = get_query_var('mimosa_media_item');
if (empty($media_item) || !is_array($media_item)) {
    return;
}

$title      = $media_item['title'] ?? '';
$type       = $media_item['type'] ?? '';
$status     = $media_item['status'] ?? '';
$score_10   = ($apex_media_list instanceof Apex_Media_List)
    ? $apex_media_list->resolve_score_10_from_row($media_item)
    : 0;
$grade      = ($apex_media_list instanceof Apex_Media_List)
    ? $apex_media_list->get_grade_from_score_10($score_10)
    : array('label' => '', 'desc' => '');
$is_honmei  = !empty($media_item['honmei']);
$detail_url = ($apex_media_list instanceof Apex_Media_List)
    ? $apex_media_list->get_media_detail_url($media_item)
    : '#';

// 封面图
$cover   = '';
$att_id  = isset($media_item['cover_attachment_id']) ? intval($media_item['cover_attachment_id']) : 0;
if ($att_id > 0) {
    $cover = wp_get_attachment_image_url($att_id, 'medium');
}
if (empty($cover) && !empty($media_item['cover_source_url'])) {
    $cover = $media_item['cover_source_url'];
}

// 类型标签
$type_map = array('anime' => '番剧', 'galgame' => '视觉小说', 'reading' => '阅读');
$type_label = $type_map[$type] ?? $type;

// 状态标签
$status_map = array(
    'anime'   => array('watched' => '已看完', 'watching' => '在看', 'want' => '想看'),
    'galgame' => array('watched' => '已通关', 'watching' => '进行中', 'want' => '想玩'),
    'reading' => array('watched' => '已读完', 'watching' => '在读', 'want' => '想读'),
);
$status_label = $status_map[$type][$status] ?? '';

// 完成时间 / 季节
$finished = '';
if (!empty($media_item['finished_at']) && $media_item['finished_at'] !== '0000-00-00 00:00:00') {
    $finished = substr($media_item['finished_at'], 0, 10);
}
$season_text = $media_item['season_text'] ?? '';

// 完成/季节标签文字
$finished_label_map = array('anime' => '完成', 'galgame' => '通关', 'reading' => '读完');
$season_label_map   = array('anime' => '放送', 'galgame' => '发售', 'reading' => '出版');
$finished_label = $finished_label_map[$type] ?? '完成';
$season_label   = $season_label_map[$type] ?? '发售';

// 评分色调
$score_tone = 'gray';
if ($score_10 >= 9.5)     $score_tone = 'purple';
elseif ($score_10 >= 9.0) $score_tone = 'gold';
elseif ($score_10 >= 8.0) $score_tone = 'blue';
elseif ($score_10 >= 7.0) $score_tone = 'green';
elseif ($score_10 >= 6.0) $score_tone = 'teal';
elseif ($score_10 > 0)    $score_tone = 'rose';

// 评价摘要
$review_text    = wp_strip_all_tags($media_item['review'] ?? '');
$review_excerpt = mb_strlen($review_text) > 80
    ? mb_substr($review_text, 0, 80) . '…'
    : $review_text;
?>

<article class="feed-card feed-card--media">
    <a class="media-preview-card" href="<?php echo esc_url($detail_url); ?>">

        <div class="media-preview-card__cover">
            <?php if ($cover): ?>
            <img src="<?php echo esc_url($cover); ?>"
                 alt="<?php echo esc_attr($title); ?>"
                 loading="lazy"
                 class="media-preview-card__cover-img">
            <?php else: ?>
            <div class="media-preview-card__cover-placeholder" aria-hidden="true">
                <?php echo esc_html(mb_substr($title, 0, 1)); ?>
            </div>
            <?php endif; ?>

            <?php if ($is_honmei): ?>
            <div class="media-preview-card__honmei-ribbon">本命作</div>
            <?php endif; ?>

            <div class="media-preview-card__score-bar media-preview-card__score-bar--<?php echo esc_attr($is_honmei ? 'honmei' : $score_tone); ?>">
                <?php if ($is_honmei): ?>
                <?php if ($score_10 > 0): ?>
                <span class="media-preview-card__score-num"><?php echo esc_html(number_format($score_10, 1)); ?></span>
                <?php endif; ?>
                <span class="media-preview-card__score-grade"><?php echo esc_html($grade['label']);?></span><!--这里是对本命作的特定评级，但是延续本身的评级了。可以改成“本命作”-->
                <?php elseif ($score_10 > 0): ?>
                <span class="media-preview-card__score-num"><?php echo esc_html(number_format($score_10, 1)); ?></span>
                <?php if (!empty($grade['label'])): ?>
                <span class="media-preview-card__score-grade"><?php echo esc_html($grade['label']); ?></span>
                <?php endif; ?>
                <?php else: ?>
                <span class="media-preview-card__score-num">-</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="media-preview-card__info">
            <div class="media-preview-card__badges">
                <span class="media-preview-card__type-badge"><?php echo esc_html($type_label); ?></span>
                <?php if ($status_label): ?>
                <span class="media-preview-card__status-badge media-preview-card__status-badge--<?php echo esc_attr($status); ?>">
                    <?php echo esc_html($status_label); ?>
                </span>
                <?php endif; ?>
            </div>

            <h3 class="media-preview-card__title"><?php echo esc_html($title); ?></h3>

            <div class="media-preview-card__meta">
                <?php if ($season_text): ?>
                <span class="media-preview-card__meta-item"><?php echo esc_html($season_label . '：' . $season_text); ?></span>
                <?php endif; ?>
                <?php if ($finished): ?>
                <span class="media-preview-card__meta-item"><?php echo esc_html($finished_label . '：' . $finished); ?></span>
                <?php endif; ?>
            </div>

            <?php if ($review_excerpt): ?>
            <p class="media-preview-card__review"><?php echo esc_html($review_excerpt); ?></p>
            <?php endif; ?>

            <span class="media-preview-card__read-more">查看详情 →</span>
        </div>

    </a>
</article>
