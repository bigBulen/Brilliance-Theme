<?php
/**
 * 页面内容模板
 */

$post_id = get_the_ID();
?>

<article id="post-<?php the_ID(); ?>" <?php post_class('page-single'); ?>>
    
    <div class="page-single__container">
        <div class="page-single__card">
        <?php $feature_title_url = brilliance_get_feature_title_image($post_id); ?>
        <!-- 页面头部（设置特色标题图时以图片替换文字标题） -->
        <header class="page-single__header<?php echo $feature_title_url ? ' page-single__header--feature-title' : ''; ?>">
            <?php if ($feature_title_url): ?>
            <h1 class="page-single__title screen-reader-text"><?php the_title(); ?></h1>
            <img class="page-single__feature-title" src="<?php echo esc_url($feature_title_url); ?>" alt="<?php echo esc_attr(get_the_title()); ?>">
            <?php else: ?>
            <h1 class="page-single__title"><?php the_title(); ?></h1>
            <?php endif; ?>

            <?php do_action('brilliance_entry_header_end', $post_id); ?>
        </header>
        
        <!-- 页面正文 -->
        <div class="page-single__content">
            <?php the_content(); ?>
        </div>
        
        <!-- 评论区 -->
        </div>
        <?php if (comments_open() || get_comments_number()): ?>
        <div class="page-single__comments">
            <?php comments_template(); ?>
        </div>
        <?php endif; ?>
    </div>
    
</article>
