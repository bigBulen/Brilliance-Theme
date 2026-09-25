<?php
/**
 * 文章详情内容模板
 */

$post_id = get_the_ID();
$categories = get_the_category();
$tags = get_the_tags();
$thumbnail_url = get_the_post_thumbnail_url($post_id, 'full');
?>

<article id="post-<?php the_ID(); ?>" <?php post_class('article-single'); ?>>
    
    <!-- 文章头图 -->
    <?php if ($thumbnail_url): ?>
    <div class="article-single__hero">
        <img src="<?php echo esc_url($thumbnail_url); ?>" alt="<?php echo esc_attr(get_the_title()); ?>" loading="eager">
    </div>
    <?php endif; ?>
    
    <div class="article-single__container">
        <div class="article-single__card">
        <?php $feature_title_url = brilliance_get_feature_title_image($post_id); ?>
        <!-- 文章头部（设置特色标题图时以图片替换文字标题） -->
        <header class="article-single__header<?php echo $feature_title_url ? ' article-single__header--feature-title' : ''; ?>">
            <?php if ($feature_title_url): ?>
            <h1 class="article-single__title screen-reader-text"><?php the_title(); ?></h1>
            <img class="article-single__feature-title" src="<?php echo esc_url($feature_title_url); ?>" alt="<?php echo esc_attr(get_the_title()); ?>">
            <?php else: ?>
            <h1 class="article-single__title"><?php the_title(); ?></h1>
            <?php endif; ?>
            
            <div class="article-single__meta">
                <?php if (!empty($categories)): ?>
                <span class="article-meta-item article-meta-item--category">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                    <a href="<?php echo esc_url(get_category_link($categories[0]->term_id)); ?>">
                        <?php echo esc_html($categories[0]->name); ?>
                    </a>
                </span>
                <?php endif; ?>
                
                <span class="article-meta-item article-meta-item--date">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    <time datetime="<?php echo esc_attr(get_the_date('c')); ?>">
                        <?php echo esc_html(get_the_date('Y-m-d')); ?>
                    </time>
                </span>
                
                <span class="article-meta-item article-meta-item--author">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    <a href="<?php echo esc_url(get_author_posts_url(get_the_author_meta('ID'))); ?>">
                        <?php echo esc_html(get_the_author()); ?>
                    </a>
                </span>
                
                <span class="article-meta-item article-meta-item--views">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    <?php echo intval(mimosa_get_post_views($post_id)); ?> 阅读
                </span>
                
                <span class="article-meta-item article-meta-item--comments">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
                    <?php echo intval(get_comments_number()); ?> 评论
                </span>
            </div>

            <?php do_action('brilliance_entry_header_end', $post_id); ?>
        </header>
        
        <!-- 文章正文 -->
        <div class="article-single__content article-wrap">
            <?php the_content(); ?>
        </div>
        


        <!-- 文章标签 -->
        <?php if ($tags): ?>
        <footer class="article-single__footer">
            <div class="article-single__tags">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                <?php foreach ($tags as $tag): ?>
                <a href="<?php echo esc_url(get_tag_link($tag->term_id)); ?>" class="article-tag">
                    <?php echo esc_html($tag->name); ?>
                </a>
                <?php endforeach; ?>
            </div>
        </footer>
        <?php endif; ?>

        <!-- 表态 -->
        <?php if (function_exists('mimosa_reactions_render')): ?>
                <div class="article-single__reactions">
                    <?php mimosa_reactions_render($post_id); ?>
                </div>
                <?php endif; ?>
        </div>
        
        <?php if (function_exists('mimosa_render_related_posts')) mimosa_render_related_posts($post_id); ?>

        <!-- 评论区 -->
        <div class="article-single__comments">
            <?php
            if (comments_open() || get_comments_number()) {
                comments_template();
            }
            ?>
        </div>
    </div>
    
</article>
