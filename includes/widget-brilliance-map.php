<?php
/**
 * 足迹地图 - 侧边栏小地图小工具 + Gutenberg 区块
 *
 * 静态展示：按设置页「初始视野」定中心与缩放，绘制国家轮廓 + 全部标点，
 * 无弹窗、无拖拽/缩放交互；点击整块跳转到大地图页面（URL 来自小工具/区块设置，
 * 留空则回落到地图设置页的「大地图页面 URL」或自动搜索含短代码的页面）。
 *
 * 标点数据经 Brilliance_Map::cached_payload() 读取（同一套 transient 缓存），
 * 因此地点/文章/设置变化时自动失效，无需单独维护。
 *
 * 同一套渲染逻辑由 brilliance_render_mini_map() 公用：
 * - WP_Widget：Brilliance_Map_Mini_Widget
 * - Gutenberg：brilliance/map（render_callback → brilliance_render_map_block）
 */

if (!defined('ABSPATH')) exit;

/**
 * 小地图前端资源（Leaflet + 复用大地图标点样式 + 小地图容器样式）。重复调用只入队一次。
 */
function brilliance_enqueue_mini_map_assets() {
    // Leaflet 已本地化到 assets/vendor/，不再依赖 jsDelivr
    $vendor = MIMOSA_THEME_URI . '/assets/vendor';
    wp_enqueue_style('bm-leaflet', $vendor . '/leaflet/leaflet.css', array(), mimosa_asset_version('assets/vendor/leaflet/leaflet.css'));
    wp_enqueue_style('bm-map', MIMOSA_THEME_URI . '/assets/css/bm-map.css', array('bm-leaflet'), mimosa_asset_version('assets/css/bm-map.css'));
    wp_enqueue_style('bm-mini-map', MIMOSA_THEME_URI . '/assets/css/bm-mini-map.css', array('bm-map'), mimosa_asset_version('assets/css/bm-mini-map.css'));

    wp_enqueue_script('bm-leaflet', $vendor . '/leaflet/leaflet.js', array(), mimosa_asset_version('assets/vendor/leaflet/leaflet.js'), true);
    wp_enqueue_script('bm-mini-map', MIMOSA_THEME_URI . '/assets/js/bm-mini-map.js', array('bm-leaflet'), mimosa_asset_version('assets/js/bm-mini-map.js'), true);
}

/**
 * 小工具是否出现在当前页面的侧边栏（经典小工具）。
 */
function brilliance_mini_map_is_active() {
    if (!function_exists('is_active_widget')) {
        return false;
    }
    return (bool) is_active_widget(false, false, 'brilliance_map_mini', true);
}

/** 当前单页内容是否含 brilliance/map 区块 */
function brilliance_content_has_map_block() {
    if (!is_singular() || !function_exists('has_block')) {
        return false;
    }
    $post = get_post();
    return $post ? has_block('brilliance/map', $post) : false;
}

/* 仅在实际挂载小工具/区块的页面加载资源（渲染函数内也会再调用一次，双保险） */
add_action('wp_enqueue_scripts', function () {
    if (is_admin()) {
        return;
    }
    if (brilliance_mini_map_is_active() || brilliance_content_has_map_block()) {
        brilliance_enqueue_mini_map_assets();
    }
});

/**
 * 渲染小地图 HTML（Widget 与 Gutenberg 区块共用）。
 *
 * @param array $args {
 *   map_url?: string 跳转目标（留空回落到地图设置页/自动搜索）
 *   class?:   string 附加容器 class
 * }
 * @return string 无地点或组件未就绪时返回空串
 */
function brilliance_render_mini_map($args = array()) {
    global $brilliance_map;
    if (!$brilliance_map) {
        return '';
    }

    $args = wp_parse_args($args, array('map_url' => '', 'class' => ''));

    $payload = $brilliance_map->cached_payload();
    $locations = isset($payload['locations']) ? $payload['locations'] : array();
    if (!$locations) {
        return '';
    }

    brilliance_enqueue_mini_map_assets();

    $settings = Brilliance_Map::get_settings();
    $view = Brilliance_Map::initial_view();

    $map_url = trim((string) $args['map_url']);
    if ($map_url === '') {
        $map_url = (string) Brilliance_Map::get_setting('map_page_url', '');
    }
    if ($map_url === '') {
        $map_url = $brilliance_map->map_page_url();
    }

    // 小地图只需展示字段，剔除描述/文章 ID 等，控制内联 JSON 体积
    $points = array();
    foreach ($locations as $loc) {
        $points[] = array(
            'id'         => (int) $loc['id'],
            'name'       => (string) $loc['name'],
            'lat'        => (float) $loc['lat'],
            'lng'        => (float) $loc['lng'],
            'postCount'  => (int) $loc['postCount'],
            'visitCount' => $loc['visitCount'] === null ? null : (int) $loc['visitCount'],
        );
    }

    $config = array(
        'center'       => apply_filters('bm_map_center', $view['center']),
        'zoom'         => (int) apply_filters('bm_map_zoom', $view['zoom']),
        'tileUrl'      => $settings['tile_url'] !== '' ? $settings['tile_url'] : apply_filters('bm_tile_url', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
        'geoJsonUrl'   => apply_filters('bm_geojson_url', MIMOSA_THEME_URI . '/assets/vendor/geojson/countries.geo.json'),
        'showMarkers'  => (bool) $settings['show_markers'],
        'showOutlines' => (bool) $settings['show_outlines'],
        'showBadge'    => (bool) $settings['show_post_badge'],
        'showTiles'    => (bool) $settings['show_tiles'],
    );

    $json = wp_json_encode(
        array('config' => $config, 'locations' => $points),
        JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
    );

    ob_start();
    ?>
    <div class="bm-mini-map-widget <?php echo esc_attr($args['class']); ?>">
        <?php if ($map_url): ?>
        <a class="bm-mini-map__link" href="<?php echo esc_url($map_url); ?>" title="<?php echo esc_attr__('查看大地图', 'mimosa'); ?>">
        <?php endif; ?>
            <div class="bm-mini-map" data-bm-mini="1">
                <script type="application/json" class="bm-mini-map__data"><?php echo $json; // phpcs:ignore WordPress.Security.EscapeOutput -- JSON_HEX_* 已转义 ?></script>
            </div>
        <?php if ($map_url): ?>
            <span class="bm-mini-map__more"><?php esc_html_e('查看大地图', 'mimosa'); ?></span>
        </a>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * WP_Widget：足迹小地图
 */
class Brilliance_Map_Mini_Widget extends WP_Widget {

    public function __construct() {
        parent::__construct(
            'brilliance_map_mini',
            __('足迹小地图', 'mimosa'),
            array(
                'description' => __('静态展示所有足迹标点，点击跳转大地图', 'mimosa'),
                'classname'   => 'brilliance-map-mini-widget',
            )
        );
    }

    public function widget($args, $instance) {
        $html = brilliance_render_mini_map(array(
            'map_url' => isset($instance['map_url']) ? $instance['map_url'] : '',
        ));
        if ($html === '') {
            return;
        }

        $title = apply_filters('widget_title', isset($instance['title']) ? $instance['title'] : '');
        if ($title === '') {
            $title = __('我的足迹', 'mimosa');
        }

        echo $args['before_widget'];
        echo $args['before_title'] . esc_html($title) . $args['after_title'];
        echo $html;
        echo $args['after_widget'];
    }

    public function form($instance) {
        $title = isset($instance['title']) ? $instance['title'] : '';
        $map_url = isset($instance['map_url']) ? $instance['map_url'] : '';
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>"><?php _e('标题：', 'mimosa'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('title')); ?>" type="text"
                   value="<?php echo esc_attr($title); ?>" placeholder="我的足迹">
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('map_url')); ?>"><?php _e('大地图 URL：', 'mimosa'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('map_url')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('map_url')); ?>" type="url"
                   value="<?php echo esc_attr($map_url); ?>" placeholder="留空则自动使用地图设置页配置">
        </p>
        <?php
    }

    public function update($new_instance, $old_instance) {
        $instance = $old_instance;
        $instance['title'] = sanitize_text_field(isset($new_instance['title']) ? $new_instance['title'] : '');
        $instance['map_url'] = esc_url_raw(isset($new_instance['map_url']) ? trim($new_instance['map_url']) : '');
        return $instance;
    }
}

/**
 * Gutenberg 区块渲染回调：brilliance/map
 */
function brilliance_render_map_block($attrs) {
    $attrs = is_array($attrs) ? $attrs : array();
    $title = isset($attrs['title']) ? sanitize_text_field($attrs['title']) : '';
    $map_url = isset($attrs['mapUrl']) ? esc_url_raw($attrs['mapUrl']) : '';

    $html = brilliance_render_mini_map(array('map_url' => $map_url));
    if ($html === '') {
        return '';
    }

    ob_start();
    if ($title !== '') {
        echo '<h2 class="widget-title">' . esc_html($title) . '</h2>';
    }
    echo '<div class="brilliance-block-mini-map">' . $html . '</div>';
    return ob_get_clean();
}
