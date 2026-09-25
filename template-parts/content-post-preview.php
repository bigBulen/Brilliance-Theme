<?php
/**
 * 文章预览卡片（用于首页、归档、分类等列表）
 */

$post_id = get_the_ID();
$categories = get_the_category();
$tags = get_the_tags();
$thumbnail_url = get_the_post_thumbnail_url($post_id, 'medium');
$excerpt = get_the_excerpt();
$is_sticky = is_sticky();
?>

<article id="post-<?php the_ID(); ?>" <?php post_class('feed-card feed-card--post'); ?>>
    <a href="<?php the_permalink(); ?>" class="feed-card__link">
        <?php if ($thumbnail_url): ?>
        <div class="feed-card__thumb">
            <img src="<?php echo esc_url($thumbnail_url); ?>" 
                 alt="<?php echo esc_attr(get_the_title()); ?>" 
                 class="feed-card__thumb-img"
                 loading="lazy">
        </div>
        <?php endif; ?>
        
        <div class="feed-card__body">
            <div class="feed-card__meta">
                <div class="feed-card__meta-row">
                    <?php if ($is_sticky): ?>
                    <span class="feed-card__badge feed-card__badge--sticky">
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor"><path d="M16 4v8l2 4H6l2-4V4h8z"/><path d="M12 20v-4"/></svg>
                        置顶
                    </span>
                    <?php endif; ?>
                    
                    <?php if (!empty($categories)): ?>
                    <a href="<?php echo esc_url(get_category_link($categories[0]->term_id)); ?>" class="feed-card__cat">
                        <?php echo esc_html($categories[0]->name); ?>
                    </a>
                    <?php endif; ?>
                    
                    <time class="feed-card__time" datetime="<?php echo esc_attr(get_the_date('c')); ?>">
                        <?php echo esc_html(get_the_date('Y-m-d')); ?>
                    </time>
                </div>
            </div>
            
            <h2 class="feed-card__title"><?php the_title(); ?></h2>
            
            <?php if ($excerpt): ?>
            <div class="feed-card__excerpt"><?php echo esc_html($excerpt); ?></div>
            <?php endif; ?>
            
            <div class="feed-card__footer">
                <div class="feed-card__tags">
                    <?php if (!empty($tags)): ?>
                        <?php foreach (array_slice($tags, 0, 3) as $tag): ?>
                        <span class="feed-card__tag">
                            <?php echo esc_html($tag->name); ?>
                        </span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <div class="feed-card__stats">
                    <span class="feed-card__reading">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        <?php echo intval(mimosa_get_reading_time($post_id)); ?> 分钟
                    </span>
                    
                    <span class="feed-card__comments">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
                        <?php echo intval(get_comments_number()); ?>
                    </span>
                </div>
            </div>
        </div>
    </a>
</article>
