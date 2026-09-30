<?php

if (!defined('ABSPATH')) {
    exit;
}

define('MIMOSA_THEME_DIR', get_template_directory());
define('MIMOSA_THEME_URI', get_template_directory_uri());

function mimosa_asset_version($relative_path) {
    $file = MIMOSA_THEME_DIR . '/' . ltrim($relative_path, '/\\');
    return is_file($file)
        ? (string) filemtime($file)
        : (wp_get_theme()->get('Version') ?: '1.0.0');
}

require_once MIMOSA_THEME_DIR . '/functions/utils.php';
require_once MIMOSA_THEME_DIR . '/functions/wordcount.php'; // 字数统计
require_once MIMOSA_THEME_DIR . '/includes/Markdown.php'; // Markdown 解析
require_once MIMOSA_THEME_DIR . '/stickers/emotions.php'; // 表情包配置
require_once MIMOSA_THEME_DIR . '/includes/apex-media-list.php';
require_once MIMOSA_THEME_DIR . '/includes/timeline-calendar.php';
require_once MIMOSA_THEME_DIR . '/includes/feature-pages.php';
require_once MIMOSA_THEME_DIR . '/includes/impressions.php';
require_once MIMOSA_THEME_DIR . '/includes/brilliance-map.php';
if (is_admin()) {
    require_once MIMOSA_THEME_DIR . '/includes/brilliance-map-admin.php';
    require_once MIMOSA_THEME_DIR . '/functions/theme-update.php'; // 后台主题更新检测（GitHub Releases）
}
require_once MIMOSA_THEME_DIR . '/functions/feed.php';
require_once MIMOSA_THEME_DIR . '/functions/comments.php';
require_once MIMOSA_THEME_DIR . '/functions/comment-captcha.php';
require_once MIMOSA_THEME_DIR . '/includes/reactions.php';
require_once MIMOSA_THEME_DIR . '/includes/settings.php';
require_once MIMOSA_THEME_DIR . '/includes/friendlinks-admin.php';
require_once MIMOSA_THEME_DIR . '/includes/short-code.php';
require_once MIMOSA_THEME_DIR . '/includes/widget-activity-heatmap.php';
require_once MIMOSA_THEME_DIR . '/includes/widget-status-monitor.php';
require_once MIMOSA_THEME_DIR . '/includes/widget-brilliance-map.php';
remove_action('init', 'argon_init_gutenberg_blocks');
remove_filter('block_categories_all', 'argon_add_gutenberg_category', 10);




function brilliance_render_post_reference_block($attrs) {
    $post_id = isset($attrs['postId']) ? absint($attrs['postId']) : 0;
    if (!$post_id) return '';
    $post = get_post($post_id);
    if (!$post || $post->post_status !== 'publish') return '';

    $title = get_the_title($post_id);
    $url = get_permalink($post_id);
    $thumb = get_the_post_thumbnail_url($post_id, 'medium');
    $date = get_the_date('Y-m-d', $post_id);
    $cats = get_the_category($post_id);
    $cat_name = !empty($cats) ? $cats[0]->name : '';

    ob_start();
    ?>
    <div class="post-reference-card">
        <a class="post-reference-card__link" href="<?php echo esc_url($url); ?>">
            <?php if ($thumb): ?>
            <span class="post-reference-card__thumb"><img src="<?php echo esc_url($thumb); ?>" alt="<?php echo esc_attr($title); ?>" loading="lazy"></span>
            <?php endif; ?>
            <span class="post-reference-card__body">
                <span class="post-reference-card__title"><?php echo esc_html($title); ?></span>
                <span class="post-reference-card__meta"><?php echo esc_html(trim(($cat_name ? $cat_name . ' · ' : '') . $date)); ?></span>
            </span>
        </a>
    </div>
    <?php
    return ob_get_clean();
}

function brilliance_register_editor_blocks() {
    wp_register_script(
        'brilliance-editor-blocks',
        MIMOSA_THEME_URI . '/assets/js/brilliance-editor-blocks.js',
        array('wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-rich-text', 'wp-server-side-render'),
        mimosa_asset_version('assets/js/brilliance-editor-blocks.js'),
        true
    );

    register_block_type('brilliance/acgn', array(
        'editor_script' => 'brilliance-editor-blocks',
        'render_callback' => 'brilliance_render_acgn_block',
        'attributes' => array(
            'id' => array('type' => 'number', 'default' => 0),
        ),
    ));

    register_block_type('brilliance/activity-heatmap', array(
        'editor_script' => 'brilliance-editor-blocks',
        'render_callback' => 'brilliance_render_activity_heatmap_block',
    ));

    register_block_type('brilliance/status-monitor', array(
        'editor_script' => 'brilliance-editor-blocks',
        'render_callback' => 'brilliance_render_status_monitor_block',
    ));

    register_block_type('brilliance/post-reference', array(
        'editor_script' => 'brilliance-editor-blocks',
        'render_callback' => 'brilliance_render_post_reference_block',
        'attributes' => array(
            'postId' => array('type' => 'number', 'default' => 0),
        ),
    ));

    register_block_type('brilliance/map', array(
        'editor_script' => 'brilliance-editor-blocks',
        'render_callback' => 'brilliance_render_map_block',
        'attributes' => array(
            'title'  => array('type' => 'string', 'default' => ''),
            'mapUrl' => array('type' => 'string', 'default' => ''),
        ),
    ));

    add_action('enqueue_block_editor_assets', function () {
        wp_enqueue_script('brilliance-editor-blocks');
        wp_enqueue_style(
            'brilliance-shortcodes-editor',
            MIMOSA_THEME_URI . '/assets/css/shortcodes.css',
            array(),
            mimosa_asset_version('assets/css/shortcodes.css')
        );
        wp_add_inline_style(
            'brilliance-shortcodes-editor',
            '.editor-styles-wrapper mark, .editor-styles-wrapper .wp-block-mark { background: transparent !important; color: var(--wp--preset--color--primary, #7c6af7) !important; padding: 0; } .editor-styles-wrapper .brilliance-block-placeholder { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 6px; padding: 28px 18px; border: 1px dashed #888; border-radius: 10px; background: rgba(127,127,127,0.05); color: #888; text-align: center; } .editor-styles-wrapper .brilliance-block-placeholder__title { font-weight: 600; font-size: 13px; color: #666; } .editor-styles-wrapper .brilliance-block-placeholder__desc { font-size: 12px; color: #999; }'
        );
    });
}
add_action('init', 'brilliance_register_editor_blocks');

/* ── 特色标题图：post meta 注册 + 编辑器侧栏面板 ── */
function brilliance_register_feature_title() {
    foreach (array('post', 'page') as $post_type) {
        register_post_meta($post_type, 'mimosa_feature_title_image_id', array(
            'type'          => 'integer',
            'single'        => true,
            'default'       => 0,
            'show_in_rest'      => true,
            'sanitize_callback' => 'absint',
            'auth_callback' => function () {
                return current_user_can('edit_posts');
            },
        ));
    }
}
add_action('init', 'brilliance_register_feature_title');

function brilliance_clear_feature_title_meta_after_rest_save($post, $request, $creating) {
    $meta = $request->get_param('meta');
    if (!is_array($meta) || !array_key_exists('mimosa_feature_title_image_id', $meta)) {
        return;
    }

    if ((int) $meta['mimosa_feature_title_image_id'] === 0) {
        delete_post_meta($post->ID, 'mimosa_feature_title_image_id');
    }
}
add_action('rest_after_insert_post', 'brilliance_clear_feature_title_meta_after_rest_save', 10, 3);
add_action('rest_after_insert_page', 'brilliance_clear_feature_title_meta_after_rest_save', 10, 3);

function brilliance_enqueue_feature_title_editor() {
    wp_enqueue_script(
        'brilliance-feature-title',
        MIMOSA_THEME_URI . '/assets/js/brilliance-feature-title.js',
        array('wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-data', 'wp-i18n'),
        mimosa_asset_version('assets/js/brilliance-feature-title.js'),
        true
    );
}
add_action('enqueue_block_editor_assets', 'brilliance_enqueue_feature_title_editor');

function brilliance_add_block_category($categories, $editor_context) {
    foreach ($categories as $category) {
        if (isset($category['slug']) && $category['slug'] === 'brilliance') {
            return $categories;
        }
    }

    $categories[] = array(
        'slug'  => 'brilliance',
        'title' => 'Brilliance',
        'icon'  => 'star-filled',
    );
    return $categories;
}
add_filter('block_categories_all', 'brilliance_add_block_category', 10, 2);

function brilliance_render_acgn_block($attributes) {
    return brilliance_shortcode_acgn(array(0 => absint($attributes['id'] ?? 0)), '');
}

// ACGN 短代码 - 显示 ACGN 卡片
add_shortcode('acgn','brilliance_shortcode_acgn');
function brilliance_shortcode_acgn($attr,$content=""){
	global $apex_media_list;
	
	$id = isset($attr[0]) ? intval($attr[0]) : 0;
	if ($id <= 0 && !empty($content)) {
		$id = intval(trim($content));
	}
	
	if ($id <= 0 || !($apex_media_list instanceof Apex_Media_List)) {
		return '<div class="brilliance-acgn-card"><p style="color:var(--text-3);padding:1rem;">ACGN 内容未找到</p></div>';
	}
	
	$media = $apex_media_list->get_media_by_id($id);
	if (!$media) {
		return '<div class="brilliance-acgn-card"><p style="color:var(--text-3);padding:1rem;">ACGN #' . $id . ' 不存在</p></div>';
	}
	
	// 使用现有的 ACGN 卡片模板。
	set_query_var('mimosa_media_item', $media);
	ob_start();
	echo '<div class="brilliance-acgn-card">';
	include get_template_directory() . '/template-parts/content-media-preview.php';
	echo '</div>';
	set_query_var('mimosa_media_item', null);
	return ob_get_clean();
}

function mimosa_setup() {
    load_theme_textdomain('mimosa', MIMOSA_THEME_DIR . '/languages');
    
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('automatic-feed-links');
    add_theme_support('html5', array(
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
        'style',
        'script'
    ));
    
    add_theme_support('post-formats', array(
        'aside',
        'image',
        'video',
        'quote',
        'link',
        'gallery',
        'audio'
    ));
    
    add_theme_support('custom-logo', array(
        'height'      => 60,
        'width'       => 200,
        'flex-height' => true,
        'flex-width'  => true,
    ));
    
    add_theme_support('customize-selective-refresh-widgets');
    
    register_nav_menus(array(
        'primary' => esc_html__('主导航菜单', 'mimosa'),
        'footer'  => esc_html__('页脚菜单', 'mimosa'),
    ));
    
    add_image_size('mimosa-thumbnail', 800, 450, true);
    add_image_size('mimosa-large', 1200, 675, true);
}
add_action('after_setup_theme', 'mimosa_setup');

add_action('after_setup_theme', 'mimosa_remove_admin_bar');
function mimosa_remove_admin_bar() {
    show_admin_bar(false);
}

// 管理员前端侧边悬浮快捷栏（替代被隐藏的原生 admin bar）
function mimosa_admin_fab() {
    if (is_admin() || !current_user_can('manage_options')) {
        return;
    }

    $links = array();
    if (is_singular()) {
        $edit_link = get_edit_post_link();
        if ($edit_link) {
            $links[] = array('label' => '编辑当前', 'url' => $edit_link);
        }
    }
    $comments = wp_count_comments();
    $awaiting = isset($comments->moderated) ? intval($comments->moderated) : 0;

    $links[] = array('label' => '写文章', 'url' => admin_url('post-new.php'));
    $links[] = array('label' => '评论' . ($awaiting ? '（' . $awaiting . '）' : ''), 'url' => admin_url('edit-comments.php'));
    $links[] = array('label' => '外观', 'url' => admin_url('customize.php'));
    $links[] = array('label' => '后台', 'url' => admin_url());
    $links[] = array('label' => '退出', 'url' => wp_logout_url(home_url('/')));
    ?>
    <button type="button" class="mimosa-admin-fab js-mimosa-admin-fab" aria-label="管理员快捷操作" aria-expanded="false">
        <span class="fa fa-edit"></span>
    </button>
    <nav class="mimosa-admin-panel" aria-label="管理员快捷操作">
        <?php foreach ($links as $link): ?>
        <a class="mimosa-admin-panel__item" href="<?php echo esc_url($link['url']); ?>"><?php echo esc_html($link['label']); ?></a>
        <?php endforeach; ?>
    </nav>
    <?php
}
add_action('wp_footer', 'mimosa_admin_fab');

function mimosa_content_width() {
    $GLOBALS['content_width'] = apply_filters('mimosa_content_width', 1200);
}
add_action('after_setup_theme', 'mimosa_content_width', 0);

function mimosa_register_shuoshuo_post_type() {
    $labels = array(
        'name'                  => _x('说说', 'Post type general name', 'mimosa'),
        'singular_name'         => _x('说说', 'Post type singular name', 'mimosa'),
        'menu_name'             => _x('说说', 'Admin Menu text', 'mimosa'),
        'name_admin_bar'        => _x('说说', 'Add New on Toolbar', 'mimosa'),
        'add_new'               => __('发布说说', 'mimosa'),
        'add_new_item'          => __('发布新说说', 'mimosa'),
        'new_item'              => __('新说说', 'mimosa'),
        'edit_item'             => __('编辑说说', 'mimosa'),
        'view_item'             => __('查看说说', 'mimosa'),
        'all_items'             => __('所有说说', 'mimosa'),
        'search_items'          => __('搜索说说', 'mimosa'),
        'not_found'             => __('未找到说说', 'mimosa'),
        'not_found_in_trash'    => __('回收站中没有说说', 'mimosa'),
    );
    
    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'query_var'          => true,
        'rewrite'            => array(
            'slug'       => 'shuoshuo',
            'with_front' => false,
        ),
        'capability_type'    => 'post',
        'has_archive'        => true,
        'hierarchical'       => false,
        'menu_position'      => 5,
        'menu_icon'          => 'dashicons-format-status',
        'supports'           => array('title', 'editor', 'author', 'thumbnail', 'comments'),
        'show_in_rest'       => true,
    );
    
    register_post_type('shuoshuo', $args);
}
add_action('init', 'mimosa_register_shuoshuo_post_type');

function mimosa_widgets_init() {
    register_sidebar(array(
        'name'          => esc_html__('主侧边栏', 'mimosa'),
        'id'            => 'sidebar-1',
        'description'   => esc_html__('显示在文章和页面侧边的小工具区域', 'mimosa'),
        'before_widget' => '<section id="%1$s" class="widget %2$s">',
        'after_widget'  => '</section>',
        'before_title'  => '<h2 class="widget-title">',
        'after_title'   => '</h2>',
    ));
    
    register_sidebar(array(
        'name'          => esc_html__('页脚区域 1', 'mimosa'),
        'id'            => 'footer-1',
        'description'   => esc_html__('页脚第一栏小工具区域', 'mimosa'),
        'before_widget' => '<section id="%1$s" class="widget %2$s">',
        'after_widget'  => '</section>',
        'before_title'  => '<h2 class="widget-title">',
        'after_title'   => '</h2>',
    ));
    
    register_sidebar(array(
        'name'          => esc_html__('页脚区域 2', 'mimosa'),
        'id'            => 'footer-2',
        'description'   => esc_html__('页脚第二栏小工具区域', 'mimosa'),
        'before_widget' => '<section id="%1$s" class="widget %2$s">',
        'after_widget'  => '</section>',
        'before_title'  => '<h2 class="widget-title">',
        'after_title'   => '</h2>',
    ));
    
    register_sidebar(array(
        'name'          => esc_html__('页脚区域 3', 'mimosa'),
        'id'            => 'footer-3',
        'description'   => esc_html__('页脚第三栏小工具区域', 'mimosa'),
        'before_widget' => '<section id="%1$s" class="widget %2$s">',
        'after_widget'  => '</section>',
        'before_title'  => '<h2 class="widget-title">',
        'after_title'   => '</h2>',
    ));
}
add_action('widgets_init', 'mimosa_widgets_init');

// 首页副栏小工具区域（配合主题设置“首页布局”使用）
function brilliance_register_home_sidebar() {
    register_sidebar(array(
        'name'          => esc_html__('首页副栏', 'mimosa'),
        'id'            => 'home-sidebar',
        'description'   => esc_html__('显示在首页副栏的小工具区域（需在主题设置中启用首页副栏）', 'mimosa'),
        'before_widget' => '<section id="%1$s" class="widget %2$s">',
        'after_widget'  => '</section>',
        'before_title'  => '<h2 class="widget-title">',
        'after_title'   => '</h2>',
    ));
}
add_action('widgets_init', 'brilliance_register_home_sidebar');

// 主题自带小工具注册
function brilliance_register_widgets() {
    register_widget('Brilliance_Activity_Heatmap_Widget');
    register_widget('Brilliance_Status_Monitor_Widget');
    register_widget('Brilliance_Map_Mini_Widget');
}
add_action('widgets_init', 'brilliance_register_widgets');

// 规范化代码块 class，确保 Prism 能识别 Gutenberg、经典编辑器及无语言代码块。
function brilliance_add_prism_classes($content) {
    return preg_replace_callback(
        '/<pre\b([^>]*)>\s*<code\b([^>]*)>/i',
        static function ($matches) {
            $pre_attrs = $matches[1];
            $code_attrs = $matches[2];

            $append_class = static function ($attrs, $class_name) {
                if (preg_match('/\bclass\s*=\s*(["\'])(.*?)\1/i', $attrs, $class_match)) {
                    $classes = preg_split('/\s+/', trim($class_match[2]));
                    if (!in_array($class_name, $classes, true)) {
                        $classes[] = $class_name;
                    }
                    return preg_replace(
                        '/\bclass\s*=\s*(["\'])(.*?)\1/i',
                        'class="' . esc_attr(implode(' ', array_filter($classes))) . '"',
                        $attrs,
                        1
                    );
                }
                return $attrs . ' class="' . esc_attr($class_name) . '"';
            };

            $pre_attrs = $append_class($pre_attrs, 'line-numbers');
            $language_attrs = $pre_attrs . ' ' . $code_attrs;
            if (preg_match('/\blang(?:uage)?-([a-z0-9_-]+)/i', $language_attrs, $language_match)) {
                $language_class = 'language-' . strtolower($language_match[1]);
                $pre_attrs = $append_class($pre_attrs, $language_class);
                $code_attrs = $append_class($code_attrs, $language_class);
            } else {
                $pre_attrs = $append_class($pre_attrs, 'language-none');
                $code_attrs = $append_class($code_attrs, 'language-none');
            }

            return '<pre' . $pre_attrs . '><code' . $code_attrs . '>';
        },
        $content
    );
}
add_filter('the_content', 'brilliance_add_prism_classes', 20);

// 正文图片按插入时选定的尺寸加载，不让浏览器从响应式候选里改用 768px 等缩略图。
// 核心在渲染时才追加 srcset/sizes，这里阻止追加。
function brilliance_disable_content_image_srcset($add, $image, $context) {
    return $context === 'the_content' ? false : $add;
}
add_filter('wp_img_tag_add_srcset_and_sizes_attr', 'brilliance_disable_content_image_srcset', 10, 3);

// 兜底：清除已写入文章内容的 srcset/sizes（旧文章或第三方插件写入的情况）。
function brilliance_strip_content_image_srcset($content) {
    if (strpos($content, 'srcset') === false && strpos($content, 'sizes') === false) {
        return $content;
    }

    // 只处理 <img>，保留 <picture><source> 的艺术化裁剪。
    return preg_replace_callback(
        '/<img\b[^>]*>/i',
        static function ($matches) {
            return preg_replace(
                array(
                    '/\s+srcset\s*=\s*(["\']).*?\1/is',
                    '/\s+sizes\s*=\s*(["\']).*?\1/is',
                ),
                '',
                $matches[0]
            );
        },
        $content
    );
}
add_filter('the_content', 'brilliance_strip_content_image_srcset', 25);

function mimosa_scripts() {
    // 主样式表（所有页面都加载）
    wp_enqueue_style(
        'mimosa-main',
        MIMOSA_THEME_URI . '/assets/css/main.css',
        array(),
        mimosa_asset_version('assets/css/main.css')
    );
    wp_enqueue_style(
        'mimosa-stamps',
        MIMOSA_THEME_URI . '/assets/css/stamps.css',
        array('mimosa-main'),
        mimosa_asset_version('assets/css/stamps.css')
    );
    wp_enqueue_style(
        'mimosa-feedback-captcha',
        MIMOSA_THEME_URI . '/assets/css/feedback-and-captcha.css',
        array('mimosa-main'),
        mimosa_asset_version('assets/css/feedback-and-captcha.css')
    );
    wp_enqueue_style(
        'brilliance-forms',
        MIMOSA_THEME_URI . '/assets/css/forms.css',
        array('mimosa-main'),
        mimosa_asset_version('assets/css/forms.css')
    );

    // 短代码样式
    wp_enqueue_style(
        'brilliance-shortcodes',
        MIMOSA_THEME_URI . '/assets/css/shortcodes.css',
        array('mimosa-main'),
        mimosa_asset_version('assets/css/shortcodes.css')
    );

    // 文章详情页样式
    if (is_singular(array('post', 'page', 'shuoshuo'))) {
        wp_enqueue_style(
            'mimosa-article',
            MIMOSA_THEME_URI . '/assets/css/article.css',
            array('mimosa-main'),
            mimosa_asset_version('assets/css/article.css')
        );
    }

    // Mermaid 图表渲染（懒加载，检测到 language-mermaid 才加载库）
    if (!is_admin()) {
        wp_enqueue_script(
            'brilliance-mermaid',
            MIMOSA_THEME_URI . '/assets/js/mermaid.js',
            array(),
            mimosa_asset_version('assets/js/mermaid.js'),
            true
        );
        // 库文件已本地化，通过 script 变量告知前端加载地址（不再依赖 jsDelivr）
        wp_localize_script('brilliance-mermaid', 'brillianceMermaid', array(
            'src' => MIMOSA_THEME_URI . '/assets/vendor/mermaid/mermaid.min.js',
        ));
    }

    // Prism 在所有前台页面可用，兼容首页摘要、短代码和动态内容中的代码块。
    // 资源已本地化到 assets/vendor/prism/（含 components/ 语言包），不再依赖 jsDelivr。
    if (!is_admin()) {
        $prism_uri = MIMOSA_THEME_URI . '/assets/vendor/prism';
        wp_enqueue_style(
            'brilliance-prism',
            $prism_uri . '/themes/prism-tomorrow.min.css',
            array(),
            mimosa_asset_version('assets/vendor/prism/themes/prism-tomorrow.min.css')
        );
        wp_enqueue_style(
            'brilliance-prism-line-numbers',
            $prism_uri . '/plugins/line-numbers/prism-line-numbers.min.css',
            array('brilliance-prism'),
            mimosa_asset_version('assets/vendor/prism/plugins/line-numbers/prism-line-numbers.min.css')
        );
        wp_enqueue_style(
            'brilliance-prism-custom',
            MIMOSA_THEME_URI . '/assets/css/prism-custom.css',
            array('brilliance-prism-line-numbers'),
            mimosa_asset_version('/assets/css/prism-custom.css')
        );
        
        wp_enqueue_script(
            'brilliance-prism',
            $prism_uri . '/prism.js',
            array(),
            mimosa_asset_version('assets/vendor/prism/prism.js'),
            true
        );
        wp_enqueue_script(
            'brilliance-prism-autoloader',
            $prism_uri . '/plugins/autoloader/prism-autoloader.min.js',
            array('brilliance-prism'),
            mimosa_asset_version('assets/vendor/prism/plugins/autoloader/prism-autoloader.min.js'),
            true
        );
        wp_enqueue_script(
            'brilliance-prism-line-numbers',
            $prism_uri . '/plugins/line-numbers/prism-line-numbers.min.js',
            array('brilliance-prism-autoloader'),
            mimosa_asset_version('assets/vendor/prism/plugins/line-numbers/prism-line-numbers.min.js'),
            true
        );
        wp_enqueue_script(
            'brilliance-prism-toolbar',
            $prism_uri . '/plugins/toolbar/prism-toolbar.min.js',
            array('brilliance-prism-line-numbers'),
            mimosa_asset_version('assets/vendor/prism/plugins/toolbar/prism-toolbar.min.js'),
            true
        );
        wp_enqueue_script(
            'brilliance-prism-copy',
            $prism_uri . '/plugins/copy-to-clipboard/prism-copy-to-clipboard.min.js',
            array('brilliance-prism-toolbar'),
            mimosa_asset_version('assets/vendor/prism/plugins/copy-to-clipboard/prism-copy-to-clipboard.min.js'),
            true
        );
        wp_add_inline_script(
            'brilliance-prism-copy',
            "(function () { function initPrism() { if (!window.Prism) return; if (Prism.plugins && Prism.plugins.autoloader) { Prism.plugins.autoloader.languages_path = '" . esc_js($prism_uri . '/components/') . "'; } Prism.highlightAllUnder(document); window.mimosaHighlightCode = function (root) { Prism.highlightAllUnder(root || document); }; } if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', initPrism, { once: true }); } else { initPrism(); } }());"
        );
    }

    // 首页副栏：条件满足时预加载热力图小工具样式（小工具自身也会入队兜底）
    if ((is_home() || is_front_page())
        && get_option('brilliance_home_sidebar_enable', 'no') === 'yes'
        && is_active_sidebar('home-sidebar')) {
        brilliance_enqueue_heatmap_assets();
        brilliance_enqueue_status_monitor_assets();
    }

    // 文章目录仅在文章、页面和说说详情加载。
    if (is_singular(array('post', 'page', 'shuoshuo'))) {
        wp_enqueue_style(
            'brilliance-headindex',
            MIMOSA_THEME_URI . '/assets/vendor/headindex/headindex.css',
            array('mimosa-article'),
            mimosa_asset_version('assets/vendor/headindex/headindex.css')
        );
        wp_enqueue_script(
            'brilliance-headindex',
            MIMOSA_THEME_URI . '/assets/vendor/headindex/headindex.js',
            array('jquery'),
            mimosa_asset_version('assets/vendor/headindex/headindex.js'),
            true
        );
        // 初始化目录生成：仅桌面 .index-box 实例化，再复制到移动菜单避免双实例冲突
        wp_add_inline_script(
            'brilliance-headindex',
            'jQuery(function($){var b=$(".detail-sidebar .index-box");if(!b.length)return;b.headIndex({articleWrapSelector:".article-wrap, .page-single__content",indexScrollBoxSelector:".detail-sidebar .brilliance-toc-scroll"});var m=$(".index-box--mobile");if(m.length){m.html(b.html());m.on("click",".index-link",function(e){e.preventDefault();var href=$(this).attr("href");if(!href||href==="#")return;var hash=href.split("#")[1];if(!hash)return;var t=$("[id=\""+decodeURIComponent(hash)+"\"]");if(t.length){$(".js-mobile-menu").removeClass("is-open");$("html,body").animate({scrollTop:t.offset().top-70},400);}});}});'
        );
    }

    // Chart.js （按需加载）
    wp_enqueue_script(
        'mimosa-chart',
        MIMOSA_THEME_URI . '/assets/js/chart.js',
        array(),
        mimosa_asset_version('assets/js/chart.js'),
        false
    );
    wp_enqueue_script(
        'mimosa-prefabricated-format',
        MIMOSA_THEME_URI . '/assets/js/prefabricated-format.js',
        array(),
        mimosa_asset_version('assets/js/prefabricated-format.js'),
        true
    );

    // 主 JS（所有页面都加载）
    wp_enqueue_script(
        'mimosa-main',
        MIMOSA_THEME_URI . '/assets/js/main.js',
        array('jquery'),
        mimosa_asset_version('assets/js/main.js'),
        true
    );

    // 个性化设置面板 + 返回顶部（所有页面都加载）
    wp_enqueue_script(
        'mimosa-personalization',
        MIMOSA_THEME_URI . '/assets/js/personalization.js',
        array(),
        mimosa_asset_version('assets/js/personalization.js'),
        true
    );
    wp_localize_script('mimosa-personalization', 'mimosaPersonalDefaults', array(
        'accent'      => get_option('mimosa_accent_color', '#7c6af7'),
        'radius'      => 14,
        'cardOpacity' => max(0, min(1, floatval(get_option('mimosa_card_opacity', 1)))),
    ));


    // 短代码交互 JS
    wp_enqueue_script(
        'brilliance-shortcodes',
        MIMOSA_THEME_URI . '/assets/js/shortcodes.js',
        array('jquery', 'mimosa-main'),
        mimosa_asset_version('assets/js/shortcodes.js'),
        true
    );
    
    // WordPress 原生评论回复脚本
    if (is_singular() && comments_open() && get_option('thread_comments')) {
        wp_enqueue_script('comment-reply');
    }
    
    // 单篇内容页的评论交互与通用图片灯箱。
    // 即使评论关闭，文章和说说中的图片仍需支持放大预览。
    if (is_singular() || is_home() || is_front_page()) {
        wp_enqueue_style('mimosa-comments', MIMOSA_THEME_URI . '/assets/css/comments.css', array('mimosa-main'), mimosa_asset_version('assets/css/comments.css'));
        wp_enqueue_style('mimosa-comments-enhancements', MIMOSA_THEME_URI . '/assets/css/comments-enhancements.css', array('mimosa-comments'), mimosa_asset_version('assets/css/comments-enhancements.css'));
        wp_enqueue_script('mimosa-comments', MIMOSA_THEME_URI . '/assets/js/comments.js', array('jquery', 'mimosa-main'), mimosa_asset_version('assets/js/comments.js'), true);
        wp_localize_script('mimosa-comments', 'mimosaComments', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mimosa_comment_nonce'),
            'isAdmin' => current_user_can('moderate_comments'),
            'captchaEnabled' => mimosa_captcha_enabled(),
        ));
    }
    
    // 全局配置
    wp_localize_script('mimosa-main', 'mimosaConfig', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'themeUrl' => MIMOSA_THEME_URI,
        'stamps' => array(
            'urls' => array_values(array_filter(array_map('trim', explode("\n", get_option('mimosa_banner_stamps', ''))))),
            'count' => absint(get_option('mimosa_stamps_count', 6)),
            'size' => absint(get_option('mimosa_stamps_size', 280)),
            'opacity' => floatval(get_option('mimosa_stamps_opacity', 0.04)),
        ),
    ));

    if (is_singular() && (comments_open() || get_comments_number())) {
        wp_enqueue_script(
            'mimosa-comment-captcha',
            MIMOSA_THEME_URI . '/assets/js/comment-captcha.js',
            array('jquery', 'mimosa-comments'),
            mimosa_asset_version('assets/js/comment-captcha.js'),
            true
        );
        wp_localize_script('mimosa-comment-captcha', 'mimosaCaptcha', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mimosa_comment_nonce'),
        ));
    }
}
add_action('wp_enqueue_scripts', 'mimosa_scripts');

function get_sidebar_option($context = 'default') {
    $sidebar_settings = get_theme_mod('mimosa_sidebar_settings', array());
    
    if (isset($sidebar_settings[$context])) {
        return $sidebar_settings[$context];
    }
    
    return true;
}

function mimosa_excerpt_length($length) {
    return 120;
}
add_filter('excerpt_length', 'mimosa_excerpt_length', 999);

function mimosa_excerpt_more($more) {
    return '...';
}
add_filter('excerpt_more', 'mimosa_excerpt_more');

function mimosa_get_reading_time($post_id = null) {
    if (!$post_id) {
        $post_id = get_the_ID();
    }
    
    $content = get_post_field('post_content', $post_id);
    $word_count = str_word_count(strip_tags($content));
    $reading_time = ceil($word_count / 200);
    
    return max(1, $reading_time);
}

function mimosa_get_post_views($post_id = null) {
    if (!$post_id) {
        $post_id = get_the_ID();
    }
    
    $views = get_post_meta($post_id, 'post_views_count', true);
    return $views ? intval($views) : 0;
}

function mimosa_set_post_views($post_id = null) {
    if (!$post_id) {
        $post_id = get_the_ID();
    }
    
    if (is_singular() && !is_preview()) {
        $views = mimosa_get_post_views($post_id);
        update_post_meta($post_id, 'post_views_count', $views + 1);
    }
}

function mimosa_track_post_views() {
    if (is_single()) {
        mimosa_set_post_views();
    }
}
add_action('wp_head', 'mimosa_track_post_views');

function mimosa_get_post_thumbnail_url($size = 'mimosa-thumbnail') {
    if (has_post_thumbnail()) {
        return get_the_post_thumbnail_url(null, $size);
    }
    
    return MIMOSA_THEME_URI . '/assets/images/default-thumbnail.jpg';
}

function mimosa_post_meta() {
    $categories = get_the_category();
    $category_output = '';
    
    if (!empty($categories)) {
        $category_output = '<span class="post-meta__item post-meta__category">';
        $category_output .= '<a href="' . esc_url(get_category_link($categories[0]->term_id)) . '">';
        $category_output .= esc_html($categories[0]->name);
        $category_output .= '</a>';
        $category_output .= '</span>';
    }
    
    $output = '<div class="post-meta">';
    $output .= $category_output;
    $output .= '<span class="post-meta__item post-meta__date">';
    $output .= '<time datetime="' . esc_attr(get_the_date('c')) . '">';
    $output .= esc_html(get_the_date());
    $output .= '</time>';
    $output .= '</span>';
    $output .= '<span class="post-meta__item post-meta__author">';
    $output .= '<a href="' . esc_url(get_author_posts_url(get_the_author_meta('ID'))) . '">';
    $output .= esc_html(get_the_author());
    $output .= '</a>';
    $output .= '</span>';
    $output .= '</div>';
    
    echo $output;
}

function mimosa_pagination($args = array()) {
    global $wp_query;
    
    $defaults = array(
        'mid_size'  => 2,
        'prev_text' => esc_html__('« 上一页', 'mimosa'),
        'next_text' => esc_html__('下一页 »', 'mimosa'),
    );
    
    $args = wp_parse_args($args, $defaults);
    
    $pagination = paginate_links(array_merge($args, array(
        'current' => max(1, get_query_var('paged')),
        'total'   => $wp_query->max_num_pages,
        'type'    => 'array',
    )));
    
    if ($pagination) {
        echo '<nav class="pagination">';
        echo '<ul class="pagination__list">';
        foreach ($pagination as $page) {
            echo '<li class="pagination__item">' . $page . '</li>';
        }
        echo '</ul>';
        echo '</nav>';
    }
}

function mimosa_default_nav_menu($args = array()) {
    $items = array(
        home_url('/') => __('首页', 'mimosa'),
        home_url('/anime/') => __('番剧', 'mimosa'),
        home_url('/galgame/') => __('视觉小说', 'mimosa'),
        home_url('/reading/') => __('阅读', 'mimosa'),
        home_url('/calendar/') => __('日历', 'mimosa'),
        home_url('/timeline/') => __('时间轴', 'mimosa'),
        home_url('/friends/') => __('友链', 'mimosa'),
    );
    $menu_class = is_object($args) && !empty($args->menu_class)
        ? $args->menu_class
        : 'site-header__nav-list';

    echo '<ul class="' . esc_attr($menu_class) . '">';
    foreach ($items as $url => $label) {
        echo '<li><a href="' . esc_url($url) . '">' . esc_html($label) . '</a></li>';
    }
    echo '</ul>';
}

function mimosa_customize_register($wp_customize) {
    $wp_customize->add_section('mimosa_general_settings', array(
        'title'    => __('主题设置', 'mimosa'),
        'priority' => 30,
    ));
    
    $wp_customize->add_setting('mimosa_footer_text', array(
        'default'           => '',
        'sanitize_callback' => 'wp_kses_post',
    ));
    
    $wp_customize->add_control('mimosa_footer_text', array(
        'label'   => __('页脚文本', 'mimosa'),
        'section' => 'mimosa_general_settings',
        'type'    => 'textarea',
    ));
}
add_action('customize_register', 'mimosa_customize_register');


function add_font_awesome() {
    // 本地化 Font Awesome 4.7.0（含 fonts/），不再依赖 cdnjs。
    wp_enqueue_style(
        'font-awesome',
        MIMOSA_THEME_URI . '/assets/vendor/font-awesome/css/font-awesome.min.css',
        array(),
        mimosa_asset_version('assets/vendor/font-awesome/css/font-awesome.min.css')
    );
}
add_action( 'wp_enqueue_scripts', 'add_font_awesome' );


