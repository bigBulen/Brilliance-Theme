<form role="search" method="get" class="search-form" action="<?php echo esc_url(home_url('/')); ?>">
    <label class="search-form__label">
        <span class="screen-reader-text"><?php echo esc_html_x('搜索：', 'label', 'mimosa'); ?></span>
        <input type="search" 
               class="search-form__input" 
               placeholder="<?php echo esc_attr_x('搜索...', 'placeholder', 'mimosa'); ?>" 
               value="<?php echo get_search_query(); ?>" 
               name="s" />
    </label>
    <button type="submit" class="search-form__submit">
        <?php echo esc_html_x('搜索', 'submit button', 'mimosa'); ?>
    </button>
</form>
