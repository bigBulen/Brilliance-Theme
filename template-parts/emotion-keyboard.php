<?php
/**
 * 表情包选择器模板
 */

if (!defined('ABSPATH')) exit;

global $mimosa_emotion_list;
if (empty($mimosa_emotion_list)) {
    require_once MIMOSA_THEME_DIR . '/stickers/emotions.php';
}

$emotion_list = apply_filters('mimosa_emotion_list', $mimosa_emotion_list);
?>

<div id="mimosa-emotion-keyboard" class="mimosa-emotion-keyboard" style="display:none;">
    <div class="mimosa-emotion-content">
        <?php foreach ($emotion_list as $group_index => $group): ?>
            <div class="mimosa-emotion-group <?php echo $group_index !== 0 ? 'is-hidden' : ''; ?>" data-group="<?php echo intval($group_index); ?>">
                <?php foreach ($group['list'] as $emotion): ?>
                    <?php if ($emotion['type'] === 'text'): ?>
                        <div class="mimosa-emotion-item mimosa-emotion-item--text" 
                             data-type="text" 
                             data-text="<?php echo esc_attr($emotion['text']); ?>"
                             title="<?php echo esc_attr($emotion['text']); ?>">
                            <?php echo esc_html($emotion['text']); ?>
                        </div>
                    <?php elseif ($emotion['type'] === 'sticker'): ?>
                        <div class="mimosa-emotion-item mimosa-emotion-item--sticker" 
                             data-type="sticker" 
                             data-code="<?php echo esc_attr($emotion['code']); ?>"
                             title="<?php echo esc_attr($emotion['code']); ?>">
                            <img src="<?php echo esc_url($emotion['src']); ?>" 
                                 alt="<?php echo esc_attr($emotion['code']); ?>" 
                                 loading="lazy">
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
                
                <?php if (!empty($group['description'])): ?>
                    <div class="mimosa-emotion-description">
                        <?php echo esc_html($group['description']); ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    
    <div class="mimosa-emotion-tabs">
        <?php foreach ($emotion_list as $group_index => $group): ?>
            <button type="button" 
                    class="mimosa-emotion-tab <?php echo $group_index === 0 ? 'is-active' : ''; ?>" 
                    data-group="<?php echo intval($group_index); ?>">
                <?php echo esc_html($group['groupname']); ?>
            </button>
        <?php endforeach; ?>
    </div>
</div>
