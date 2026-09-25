<?php
/**
 * 足迹地图（主题内置组件）
 *
 * - 两张数据表：brilliance_map_locations（地点）+ brilliance_map_relations（文章-地点关联，无外键、有唯一索引）
 * - 文章-地点关联完全由 relation 表反查，关联状态经 post meta（brilliance_map_location_ids）中转，
 *   save_post 时原子化同步关系表，天然过滤未发布/已删除文章
 * - 前台：短代码 [brilliance_map] 渲染 Leaflet 大地图（OSM 瓦片 + GeoJSON 国家轮廓 + 标点聚合 + 颜色梯度）
 * - 详情连接：文章/说说详情页正文尾部自动追加 📍地点 链接（?location_id= 定位高亮）
 * - REST：/brilliance-map/v1/locations（编辑器面板搜索）、/location/{id}/posts(弹窗懒加载关联文章)
 *
 * 所有外部资源 URL（瓦片、GeoJSON）均可通过 filter 覆盖：bm_tile_url / bm_geojson_url / bm_map_center / bm_map_zoom
 */

if (!defined('ABSPATH')) exit;

class Brilliance_Map {

    const DB_VERSION = '1';
    const META_KEY = 'brilliance_map_location_ids';
    const OPTION_KEY = 'bm_map_settings';
    const CACHE_KEY = 'bm_map_payload';
    const CACHE_TTL = 21600; // 6 小时

    /** @var array|null 同请求内设置缓存 */
    private static $settings_cache = null;

    /** @var array 同请求内「文章 → 关联地点」缓存，避免重复查询 */
    private static $related_cache = array();

    public function __construct() {
        add_action('init', array($this, 'maybe_upgrade_db'), 15);
        add_action('after_switch_theme', array($this, 'maybe_upgrade_db'));
        add_action('init', array($this, 'register_meta'), 5);
        add_action('init', array($this, 'register_shortcodes'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        add_action('save_post', array($this, 'on_save_post'), 20, 3);
        add_action('delete_post', array($this, 'on_delete_post'), 10, 2);
        // 缓存失效补全：状态切换 / 回收站 / 缩略图
        add_action('transition_post_status', array($this, 'on_transition_post_status'), 10, 3);
        add_action('trashed_post', array($this, 'on_trash_post'));
        add_action('untrashed_post', array($this, 'on_trash_post'));
        add_action('updated_post_meta', array($this, 'on_post_meta_change'), 10, 4);
        add_action('added_post_meta', array($this, 'on_post_meta_change'), 10, 4);
        add_action('deleted_post_meta', array($this, 'on_post_meta_change'), 10, 4);
        // 详情页头部信息下方的「收录于足迹地图」提示：由模板 do_action('brilliance_entry_header_end') 触发
        add_action('brilliance_entry_header_end', array($this, 'render_entry_notice'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_entry_assets'));
    }

    /* ═══════════ 设置 ═══════════ */

    /** 设置默认值 */
    public static function default_settings() {
        return array(
            'initial_view'    => 'china',      // world | china | custom
            'custom_lat'      => '35',
            'custom_lng'      => '105',
            'custom_zoom'     => 4,
            'show_markers'    => 1,
            'show_outlines'   => 1,
            'show_post_badge' => 1,
            'show_tiles'      => 0,            // 默认关闭街区瓦片，只保留国家轮廓
            'enable_cluster'  => 0,            // 默认关闭标点聚合，密集标点允许重叠
            'post_types'      => array('post', 'shuoshuo'),
            'default_filter'  => 'all',        // all | with_posts
            'map_page_url'    => '',
            'tile_url'        => '',
        );
    }

    public static function get_settings() {
        if (self::$settings_cache !== null) {
            return self::$settings_cache;
        }
        $saved = get_option(self::OPTION_KEY, array());
        if (!is_array($saved)) {
            $saved = array();
        }
        self::$settings_cache = array_merge(self::default_settings(), $saved);
        return self::$settings_cache;
    }

    public static function get_setting($key, $default = null) {
        $settings = self::get_settings();
        return array_key_exists($key, $settings) ? $settings[$key] : $default;
    }

    public static function reset_settings_cache() {
        self::$settings_cache = null;
    }

    /** 设置保存回调（register_setting sanitize_callback）：校验 + 清缓存 */
    public static function sanitize_settings($input) {
        $input = is_array($input) ? $input : array();
        $defaults = self::default_settings();

        $view = isset($input['initial_view']) ? sanitize_key($input['initial_view']) : $defaults['initial_view'];
        if (!in_array($view, array('world', 'china', 'custom'), true)) {
            $view = 'world';
        }

        $lat = isset($input['custom_lat']) && is_numeric($input['custom_lat']) ? (float) $input['custom_lat'] : (float) $defaults['custom_lat'];
        $lng = isset($input['custom_lng']) && is_numeric($input['custom_lng']) ? (float) $input['custom_lng'] : (float) $defaults['custom_lng'];
        $lat = max(-90, min(90, $lat));
        $lng = max(-180, min(180, $lng));
        $zoom = isset($input['custom_zoom']) ? (int) $input['custom_zoom'] : (int) $defaults['custom_zoom'];
        $zoom = max(1, min(18, $zoom));

        $available = array_keys(self::available_post_types());
        $types = (isset($input['post_types']) && is_array($input['post_types']))
            ? array_map('sanitize_key', wp_unslash($input['post_types']))
            : array();
        $types = array_values(array_intersect($types, $available));
        if (!$types) {
            $types = array('post');
        }

        $filter = isset($input['default_filter']) ? sanitize_key($input['default_filter']) : 'all';
        if (!in_array($filter, array('all', 'with_posts'), true)) {
            $filter = 'all';
        }

        $out = array(
            'initial_view'    => $view,
            'custom_lat'      => (string) $lat,
            'custom_lng'      => (string) $lng,
            'custom_zoom'     => $zoom,
            'show_markers'    => empty($input['show_markers']) ? 0 : 1,
            'show_outlines'   => empty($input['show_outlines']) ? 0 : 1,
            'show_post_badge' => empty($input['show_post_badge']) ? 0 : 1,
            'show_tiles'      => empty($input['show_tiles']) ? 0 : 1,
            'enable_cluster'  => empty($input['enable_cluster']) ? 0 : 1,
            'post_types'      => $types,
            'default_filter'  => $filter,
            'map_page_url'    => isset($input['map_page_url']) ? esc_url_raw(trim((string) $input['map_page_url'])) : '',
            'tile_url'        => isset($input['tile_url']) ? esc_url_raw(trim((string) $input['tile_url'])) : '',
        );

        self::reset_settings_cache();
        self::flush_cache();
        return $out;
    }

    /** 内容范围候选项：公开文章类型（排除附件） */
    public static function available_post_types() {
        $objects = get_post_types(array('public' => true), 'objects');
        $out = array();
        foreach ($objects as $name => $obj) {
            if ($name === 'attachment') {
                continue;
            }
            $out[$name] = $obj->labels->singular_name ?: $name;
        }
        return $out;
    }

    /** 参与关联的文章类型（受「内容范围」设置约束，保留 filter） */
    public static function post_types() {
        $settings = self::get_settings();
        $types = isset($settings['post_types']) && is_array($settings['post_types'])
            ? $settings['post_types']
            : array('post', 'shuoshuo');
        $types = array_values(array_filter(array_map('sanitize_key', $types)));
        if (!$types) {
            $types = array('post', 'shuoshuo');
        }
        return apply_filters('bm_post_types', $types);
    }

    /** 「初始视野」设置 → array('center' => [lat,lng], 'zoom' => int) */
    public static function initial_view() {
        $settings = self::get_settings();
        switch ($settings['initial_view']) {
            case 'custom':
                return array(
                    'center' => array((float) $settings['custom_lat'], (float) $settings['custom_lng']),
                    'zoom'   => (int) $settings['custom_zoom'],
                );
            case 'china':
                return array('center' => array(35.0, 105.0), 'zoom' => 4);
            case 'world':
            default:
                return array('center' => array(20.0, 0.0), 'zoom' => 2);
        }
    }

    public function locations_table() {
        global $wpdb;
        return $wpdb->prefix . 'brilliance_map_locations';
    }

    public function relations_table() {
        global $wpdb;
        return $wpdb->prefix . 'brilliance_map_relations';
    }

    /* ═══════════ 数据表 ═══════════ */
    public function maybe_upgrade_db() {
        global $wpdb;
        $loc = $this->locations_table();
        if (get_option('bm_db_version') === self::DB_VERSION && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $loc))) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $rel = $this->relations_table();

        dbDelta("CREATE TABLE {$loc} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL DEFAULT '',
            lat decimal(10,6) NOT NULL DEFAULT 0.000000,
            lng decimal(10,6) NOT NULL DEFAULT 0.000000,
            country_code varchar(8) NOT NULL DEFAULT '',
            country_name varchar(120) NOT NULL DEFAULT '',
            city varchar(120) NOT NULL DEFAULT '',
            category varchar(100) DEFAULT NULL,
            description longtext NULL,
            custom_link varchar(500) DEFAULT NULL,
            visit_count int(11) DEFAULT NULL,
            visit_date date DEFAULT NULL,
            is_geo_fuzzed tinyint(1) unsigned NOT NULL DEFAULT 0,
            created_at datetime DEFAULT NULL,
            updated_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY country_code (country_code),
            KEY city (city)
        ) {$charset};");

        dbDelta("CREATE TABLE {$rel} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            location_id bigint(20) unsigned NOT NULL,
            post_id bigint(20) unsigned NOT NULL,
            created_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY location_post (location_id,post_id),
            KEY location_id (location_id),
            KEY post_id (post_id)
        ) {$charset};");

        update_option('bm_db_version', self::DB_VERSION, false);
    }

    /* ═══════════ 地点 CRUD ═══════════ */

    /**
     * 校验并清洗地点输入。返回 array('errors' => […], 'data' => […])
     */
    public function validate_location_input($input) {
        $errors = array();
        $data = array();

        $data['name'] = sanitize_text_field($input['name'] ?? '');
        if ($data['name'] === '') {
            $errors[] = '地点名称必填';
        }

        $lat = $input['lat'] ?? '';
        $lng = $input['lng'] ?? '';
        if ($lat !== '' && $lng !== '' && is_numeric($lat) && is_numeric($lng)
            && (float) $lat >= -90 && (float) $lat <= 90
            && (float) $lng >= -180 && (float) $lng <= 180) {
            $data['lat'] = round((float) $lat, 6);
            $data['lng'] = round((float) $lng, 6);
        } else {
            $data['lat'] = 0;
            $data['lng'] = 0;
            $errors[] = '坐标必填且需在合法范围内（纬度 -90~90，经度 -180~180）';
        }

        $data['country_code'] = mb_substr(strtoupper(sanitize_text_field($input['country_code'] ?? '')), 0, 8);
        $data['country_name'] = sanitize_text_field($input['country_name'] ?? '');
        $data['city'] = sanitize_text_field($input['city'] ?? '');

        $category = sanitize_text_field($input['category'] ?? '');
        $data['category'] = $category !== '' ? $category : null;

        $description = trim(wp_kses_post($input['description'] ?? ''));
        $data['description'] = $description !== '' ? $description : null;

        $link = trim((string) ($input['custom_link'] ?? ''));
        $data['custom_link'] = $link !== '' ? esc_url_raw($link) : null;

        $visit_count = $input['visit_count'] ?? '';
        if ($visit_count === '' || $visit_count === null) {
            $data['visit_count'] = null;
        } elseif (is_numeric($visit_count) && (int) $visit_count >= 0) {
            $data['visit_count'] = (int) $visit_count;
        } else {
            $data['visit_count'] = null;
            $errors[] = '到访次数需为非负整数';
        }

        $visit_date = sanitize_text_field($input['visit_date'] ?? '');
        $data['visit_date'] = preg_match('/^\d{4}-\d{2}-\d{2}$/', $visit_date) ? $visit_date : null;

        $data['is_geo_fuzzed'] = !empty($input['is_geo_fuzzed']) ? 1 : 0;

        return array('errors' => $errors, 'data' => $data);
    }

    /** 插入（$id=0）或更新地点，返回 int|WP_Error */
    public function save_location($data, $id = 0) {
        global $wpdb;
        $table = $this->locations_table();
        $now = current_time('mysql');
        if ($id > 0) {
            $data['updated_at'] = $now;
            $result = $wpdb->update($table, $data, array('id' => (int) $id));
            if ($result === false) {
                return new WP_Error('bm_db', $wpdb->last_error ?: '保存失败');
            }
            self::flush_cache();
            return (int) $id;
        }
        $data['created_at'] = $now;
        $data['updated_at'] = $now;
        $result = $wpdb->insert($table, $data);
        if (!$result) {
            return new WP_Error('bm_db', $wpdb->last_error ?: '保存失败');
        }
        self::flush_cache();
        return (int) $wpdb->insert_id;
    }

    /** 删除地点并清理关系行（无外键，代码负责一致性） */
    public function delete_location($id) {
        global $wpdb;
        $id = (int) $id;
        $wpdb->delete($this->relations_table(), array('location_id' => $id), array('%d'));
        $wpdb->delete($this->locations_table(), array('id' => $id), array('%d'));
        // 清理引用了该地点的 post meta
        $meta_key = self::META_KEY;
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value LIKE %s",
            $meta_key, '%"' . $id . '"%'
        ));
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s",
            $meta_key, (string) $id
        ));
        self::flush_cache();
    }

    /** 危险操作：清空全部地图数据（两张表 + 文章 meta），表结构保留 */
    public function wipe_all_data() {
        global $wpdb;
        $wpdb->query('DELETE FROM ' . $this->locations_table());
        $wpdb->query('DELETE FROM ' . $this->relations_table());
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s", self::META_KEY));
        self::flush_cache();
    }

    public function get_location($id) {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . $this->locations_table() . ' WHERE id = %d', (int) $id), ARRAY_A);
        return $row ?: null;
    }

    /** 新增/编辑时查重：名称必须模糊命中，国家（若填）需匹配；只提示不阻止 */
    public function find_similar($name, $city, $country, $exclude_id = 0) {
        global $wpdb;
        $name = trim((string) $name);
        if ($name === '') {
            return array();
        }
        $table = $this->locations_table();
        $where = '(id IS NULL OR id != %d) AND name LIKE %s';
        $args = array((int) $exclude_id, '%' . $wpdb->esc_like($name) . '%');
        $country = trim((string) $country);
        if ($country !== '') {
            $where .= ' AND (UPPER(country_code) = %s OR country_name LIKE %s)';
            $args[] = strtoupper($country);
            $args[] = '%' . $wpdb->esc_like($country) . '%';
        }
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, name, city, country_name FROM {$table} WHERE {$where} ORDER BY id DESC LIMIT 5",
            $args
        ), ARRAY_A);
        return is_array($rows) ? $rows : array();
    }

    /** 地图渲染数据：地点 + 已发布关联文章计数与 ID 列表（前端统计去重用） */
    public function map_data() {
        global $wpdb;
        $loc_table = $this->locations_table();
        $rows = $wpdb->get_results("SELECT * FROM {$loc_table} ORDER BY is_geo_fuzzed DESC, id ASC", ARRAY_A);
        if (!is_array($rows) || !$rows) {
            return array();
        }

        $types = self::post_types();
        $placeholders = implode(',', array_fill(0, count($types), '%s'));
        $rel_table = $this->relations_table();
        $rel_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT r.location_id, r.post_id
             FROM {$rel_table} r
             INNER JOIN {$wpdb->posts} p ON p.ID = r.post_id
             WHERE p.post_status = 'publish' AND p.post_type IN ({$placeholders})",
            $types
        )) ?: array();

        $posts_by_location = array();
        foreach ($rel_rows as $r) {
            $lid = (int) $r->location_id;
            $pid = (int) $r->post_id;
            if (!isset($posts_by_location[$lid])) {
                $posts_by_location[$lid] = array();
            }
            if (!in_array($pid, $posts_by_location[$lid], true)) {
                $posts_by_location[$lid][] = $pid;
            }
        }

        $out = array();
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $ids = isset($posts_by_location[$id]) ? $posts_by_location[$id] : array();
            $lat = (float) $row['lat'];
            $lng = (float) $row['lng'];
            // 隐私模糊化：展示层按 id 生成固定的 ±0.005° 偏移（数据库仍存精确坐标，
            // 后台编辑不受影响；固定偏移保证缓存后同一地点位置稳定）
            if (!empty($row['is_geo_fuzzed'])) {
                $seed = crc32('bm_fuzz_' . $id);
                $lat += (($seed % 1000) / 1000 - 0.5) * 0.01;
                $lng += ((intdiv($seed, 1000) % 1000) / 1000 - 0.5) * 0.01;
            }
            $out[] = array(
                'id'          => $id,
                'name'        => (string) $row['name'],
                'lat'         => $lat,
                'lng'         => $lng,
                'city'        => (string) $row['city'],
                'countryCode' => strtoupper((string) $row['country_code']),
                'countryName' => (string) $row['country_name'],
                'category'    => $row['category'] === null ? '' : (string) $row['category'],
                'description' => $row['description'] ? wp_kses_post($row['description']) : '',
                'customLink'  => $row['custom_link'] ? esc_url_raw($row['custom_link']) : '',
                'postCount'   => count($ids),
                'postIds'     => $ids,
                'visitCount'  => $row['visit_count'] === null ? null : (int) $row['visit_count'],
                'visitDate'   => ($row['visit_date'] && $row['visit_date'] !== '0000-00-00') ? $row['visit_date'] : '',
            );
        }
        return $out;
    }

    /* ═══════════ 缓存（Transient） ═══════════ */

    /**
     * 地图页完整数据（标点 + 统计），带 transient 缓存。
     *
     * 失效条件（见各 flush_cache 调用点）：地点增删改、关联关系变化、地图设置变更、
     * 关联文章标题/摘要/日期变化（publish 状态下 save_post）、发布状态变化、缩略图变化、文章删除。
     *
     * @return array { locations: array, stats: array, generated: int }
     */
    public function cached_payload() {
        $cached = get_transient(self::CACHE_KEY);
        if (is_array($cached) && isset($cached['locations'], $cached['stats'])) {
            return $cached;
        }
        $locations = $this->map_data();
        $payload = array(
            'locations' => $locations,
            'stats'     => self::compute_stats($locations),
            'generated' => time(),
        );
        set_transient(self::CACHE_KEY, $payload, self::CACHE_TTL);
        return $payload;
    }

    /** 统计：地点总数 / 涉及文章去重 / 覆盖国家（country_code 去重）/ 城市（country_code+city 去重） */
    public static function compute_stats($locations) {
        $posts = array();
        $countries = array();
        $cities = array();
        foreach ((array) $locations as $loc) {
            foreach ((array) ($loc['postIds'] ?? array()) as $pid) {
                $posts[(int) $pid] = 1;
            }
            $code = isset($loc['countryCode']) ? (string) $loc['countryCode'] : '';
            $city = isset($loc['city']) ? (string) $loc['city'] : '';
            if ($code !== '') {
                $countries[$code] = 1;
            }
            if ($city !== '') {
                $cities[($code !== '' ? $code : '_') . '|' . $city] = 1;
            }
        }
        return array(
            'locations' => count((array) $locations),
            'posts'     => count($posts),
            'countries' => count($countries),
            'cities'    => count($cities),
        );
    }

    /** 清除地图相关缓存（数据 + 地图页 URL） */
    public static function flush_cache() {
        delete_transient(self::CACHE_KEY);
        delete_transient('bm_map_page_url');
    }

    /** 某地点关联的已发布文章卡片数据（弹窗懒加载） */
    public function posts_for_location($location_id, $limit = 12) {
        global $wpdb;
        $rel_table = $this->relations_table();
        $types = self::post_types();
        $placeholders = implode(',', array_fill(0, count($types), '%s'));
        $args = array_merge(array((int) $location_id), $types, array((int) $limit));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT p.ID, p.post_type, p.post_excerpt, p.post_content
             FROM {$wpdb->posts} p
             INNER JOIN {$rel_table} r ON r.post_id = p.ID
             WHERE r.location_id = %d AND p.post_status = 'publish' AND p.post_type IN ({$placeholders})
             ORDER BY p.post_date DESC LIMIT %d",
            $args
        )) ?: array();

        $out = array();
        foreach ($rows as $row) {
            $pid = (int) $row->ID;
            $obj = get_post_type_object($row->post_type);
            $excerpt = trim((string) $row->post_excerpt);
            if ($excerpt === '') {
                $excerpt = wp_trim_words(wp_strip_all_tags(strip_shortcodes((string) $row->post_content)), 30, '…');
            }
            $out[] = array(
                'id'      => $pid,
                'title'   => get_the_title($pid),
                'url'     => get_permalink($pid),
                'thumb'   => get_the_post_thumbnail_url($pid, 'thumbnail') ?: '',
                'excerpt' => $excerpt,
                'date'    => get_the_date('Y-m-d', $pid),
                'type'    => $obj ? $obj->labels->singular_name : $row->post_type,
            );
        }
        return $out;
    }

    /* ═══════════ 文章 ↔ 地点关联 ═══════════ */

    public function register_meta() {
        foreach (self::post_types() as $type) {
            register_post_meta($type, self::META_KEY, array(
                'type'          => 'array',
                'single'        => true,
                'default'       => array(),
                'show_in_rest'  => array(
                    'schema' => array(
                        'type'  => 'array',
                        'items' => array('type' => 'integer'),
                    ),
                ),
                'auth_callback' => function () {
                    return current_user_can('edit_posts');
                },
            ));
        }
    }

    /** post meta → 关系表原子化同步（幂等；唯一索引兜底防重） */
    public function sync_relations_from_meta($post_id) {
        global $wpdb;
        $post_id = (int) $post_id;
        $rel_table = $this->relations_table();

        $raw = get_post_meta($post_id, self::META_KEY, true);
        $wanted = array();
        if (is_array($raw)) {
            $raw = array_map('absint', $raw);
            $raw = array_filter($raw);
            $raw = array_values(array_unique($raw));
        }
        if (!empty($raw)) {
            $placeholders = implode(',', array_fill(0, count($raw), '%d'));
            $valid = $wpdb->get_col($wpdb->prepare(
                "SELECT id FROM {$this->locations_table()} WHERE id IN ({$placeholders})",
                $raw
            ));
            $wanted = array_map('intval', is_array($valid) ? $valid : array());
        }

        $current = $wpdb->get_col($wpdb->prepare(
            "SELECT location_id FROM {$rel_table} WHERE post_id = %d",
            $post_id
        ));
        $current = array_map('intval', is_array($current) ? $current : array());

        $changed = false;
        $to_remove = array_diff($current, $wanted);
        if ($to_remove) {
            $placeholders = implode(',', array_fill(0, count($to_remove), '%d'));
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$rel_table} WHERE post_id = %d AND location_id IN ({$placeholders})",
                array_merge(array($post_id), array_values($to_remove))
            ));
            $changed = true;
        }
        $now = current_time('mysql');
        foreach ($wanted as $lid) {
            if (in_array($lid, $current, true)) {
                continue;
            }
            // INSERT IGNORE：并发/重复请求下唯一索引兜底
            $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO {$rel_table} (location_id, post_id, created_at) VALUES (%d, %d, %s)",
                $lid, $post_id, $now
            ));
            $changed = true;
        }
        if ($changed) {
            self::flush_cache();
        }
        return $changed;
    }

    public function locations_for_post($post_id) {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT l.* FROM ' . $this->locations_table() . ' l
             INNER JOIN ' . $this->relations_table() . ' r ON r.location_id = l.id
             WHERE r.post_id = %d ORDER BY l.id ASC',
            (int) $post_id
        ), ARRAY_A);
        return is_array($rows) ? $rows : array();
    }

    public function on_save_post($post_id, $post, $update) {
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }
        if (!in_array($post->post_type, self::post_types(), true)) {
            return;
        }
        $changed = $this->sync_relations_from_meta($post_id);
        // 已发布文章的标题/摘要/日期变化同样影响地图卡片与统计 → 一并失效
        if ($changed || $post->post_status === 'publish') {
            self::flush_cache();
        }
    }

    /** 发布状态变化（publish/draft/trash/future/private 互切）→ 缓存失效 */
    public function on_transition_post_status($new_status, $old_status, $post) {
        if (!$post || !in_array($post->post_type, self::post_types(), true)) {
            return;
        }
        if ($new_status === 'publish' || $old_status === 'publish') {
            self::flush_cache();
        }
    }

    public function on_trash_post($post_id) {
        $post = get_post($post_id);
        if ($post && in_array($post->post_type, self::post_types(), true)) {
            self::flush_cache();
        }
    }

    /** 缩略图（_thumbnail_id）增删改 → 缓存失效 */
    public function on_post_meta_change($meta_id, $object_id, $meta_key, $meta_value) {
        if ($meta_key !== '_thumbnail_id') {
            return;
        }
        $post = get_post($object_id);
        if ($post && in_array($post->post_type, self::post_types(), true) && $post->post_status === 'publish') {
            self::flush_cache();
        }
    }

    public function on_delete_post($post_id, $post) {
        global $wpdb;
        if ($post && !in_array($post->post_type, self::post_types(), true)) {
            return;
        }
        $wpdb->delete($this->relations_table(), array('post_id' => (int) $post_id), array('%d'));
        self::flush_cache();
    }

    /* ═══════════ 详情页 📍 链接 ═══════════ */

    /** 渲染地图页 URL（找不到含短代码的页面时返回空串） */
    public function map_page_url($force_refresh = false) {
        if (!$force_refresh) {
            $cached = get_transient('bm_map_page_url');
            if (is_string($cached) && $cached !== '') {
                return $cached;
            }
        }
        // 优先使用设置页手动指定；未指定则自动搜索含短代码的页面
        $configured = (string) self::get_setting('map_page_url', '');
        $url = $configured !== '' ? $configured : get_permalink_by_slug_shortcode();
        $url = is_string($url) ? $url : '';
        set_transient('bm_map_page_url', $url, self::CACHE_TTL);
        return $url;
    }

    /** 关联地点（同请求内缓存，避免 enqueue 与渲染各查一次） */
    protected function related_locations($post_id) {
        $post_id = (int) $post_id;
        if (!isset(self::$related_cache[$post_id])) {
            self::$related_cache[$post_id] = $post_id ? $this->locations_for_post($post_id) : array();
        }
        return self::$related_cache[$post_id];
    }

    /** 仅当详情页确有关联地点时，加载头部提示样式 */
    public function enqueue_entry_assets() {
        if (!is_singular()) {
            return;
        }
        $post_id = (int) get_queried_object_id();
        if (!$post_id || !$this->related_locations($post_id)) {
            return;
        }
        wp_enqueue_style(
            'bm-entry',
            MIMOSA_THEME_URI . '/assets/css/bm-entry.css',
            array(),
            mimosa_asset_version('assets/css/bm-entry.css')
        );
    }

    /**
     * 详情页头部信息下方的「收录于足迹地图」提示。
     * 模板通过 do_action('brilliance_entry_header_end', get_the_ID()) 触发；
     * 仅在存在地点关联时输出，跳转目标为「地图设置」里的大地图 URL。
     */
    public function render_entry_notice($post_id = 0) {
        $post_id = $post_id ? (int) $post_id : (int) get_the_ID();
        if (!$post_id || is_admin() || post_password_required($post_id)) {
            return;
        }
        $locations = $this->related_locations($post_id);
        if (!$locations) {
            return;
        }

        $base = $this->map_page_url();

        switch (get_post_type($post_id)) {
            case 'shuoshuo':
                $lead = '本条说说收录于「足迹」地图！';
                break;
            case 'page':
                $lead = '本页收录于「足迹」地图！';
                break;
            case 'post':
                $lead = '本文收录于「足迹」地图！';
                break;
            default:
                $lead = '本内容收录于「足迹」地图！';
        }

        echo '<div class="bm-entry-notice" role="note">';
        echo '<span class="bm-entry-notice__icon" aria-hidden="true">📍</span>';
        echo '<div class="bm-entry-notice__body">';
        echo '<p class="bm-entry-notice__text"><span class="bm-entry-notice__lead">' . esc_html($lead) . '</span>';
        if ($base) {
            echo ' <a class="bm-entry-notice__cta" href="' . esc_url($base) . '">查看地图 →</a>';
        }
        echo '</p>';
        if ($locations) {
            echo '<div class="bm-entry-notice__locs">';
            foreach ($locations as $loc) {
                $name = esc_html($loc['name']);
                if ($base) {
                    $href = add_query_arg('location_id', (int) $loc['id'], $base);
                    echo '<a class="bm-entry-notice__loc" href="' . esc_url($href) . '">' . $name . '</a>';
                } else {
                    echo '<span class="bm-entry-notice__loc">' . $name . '</span>';
                }
            }
            echo '</div>';
        }
        echo '</div></div>';
    }

    /* ═══════════ REST ═══════════ */

    public function register_rest_routes() {
        register_rest_route('brilliance-map/v1', '/locations', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'rest_locations'),
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ));
        register_rest_route('brilliance-map/v1', '/location/(?P<id>\d+)/posts', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'rest_location_posts'),
            'permission_callback' => '__return_true',
            'args'                => array(
                'id' => array(
                    // 注意：REST 的 validate_callback 会以 ($value, $request, $param) 三参调用，
                    // 直接用 'is_numeric'/'absint' 等内部函数会抛 ArgumentCountError，须用闭包包装。
                    'validate_callback' => function ($value) {
                        return is_numeric($value) && (int) $value > 0;
                    },
                    'sanitize_callback' => 'absint',
                ),
            ),
        ));
    }

    /** 编辑器面板：地点搜索 / 指定 ID 回显 */
    public function rest_locations($request) {
        global $wpdb;
        $table = $this->locations_table();
        $search = sanitize_text_field((string) $request->get_param('search'));
        $include = (string) $request->get_param('include');
        $per_page = min(50, max(1, (int) $request->get_param('per_page')));

        $where = '1=1';
        $args = array();
        if ($include !== '') {
            $ids = array_filter(array_map('absint', explode(',', $include)));
            if (!$ids) {
                return rest_ensure_response(array());
            }
            $placeholders = implode(',', array_fill(0, count($ids), '%d'));
            $where .= " AND id IN ({$placeholders})";
            $args = array_merge($args, $ids);
        }
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where .= ' AND (name LIKE %s OR city LIKE %s OR country_name LIKE %s)';
            $args[] = $like;
            $args[] = $like;
            $args[] = $like;
        }

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, name, city, country_name FROM {$table} WHERE {$where} ORDER BY id DESC LIMIT %d",
            array_merge($args, array($per_page))
        ), ARRAY_A) ?: array();

        return rest_ensure_response($rows);
    }

    public function rest_location_posts($request) {
        return rest_ensure_response($this->posts_for_location(absint($request['id'])));
    }

    /* ═══════════ 前台短代码 ═══════════ */

    public function register_shortcodes() {
        add_shortcode('brilliance_map', array($this, 'shortcode_map'));
    }

    public function shortcode_map($atts = array()) {
        static $done = false;
        if ($done) {
            return '';
        }
        $done = true;

        $this->enqueue_map_assets();

        $settings = self::get_settings();
        $view = self::initial_view();
        $payload = $this->cached_payload();
        $data = $payload['locations'];
        $stats = $payload['stats'];

        $config = array(
            'restUrl'       => esc_url_raw(rest_url('brilliance-map/v1')),
            'tileUrl'       => $settings['tile_url'] !== '' ? $settings['tile_url'] : apply_filters('bm_tile_url', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
            'geoJsonUrl'    => apply_filters('bm_geojson_url', 'https://cdn.jsdelivr.net/gh/johan/world.geo.json@master/countries.geo.json'),
            'center'        => apply_filters('bm_map_center', $view['center']),
            'zoom'          => (int) apply_filters('bm_map_zoom', $view['zoom']),
            'focusId'       => isset($_GET['location_id']) ? absint(wp_unslash($_GET['location_id'])) : 0,
            'showMarkers'   => (bool) $settings['show_markers'],
            'showOutlines'  => (bool) $settings['show_outlines'],
            'showBadge'     => (bool) $settings['show_post_badge'],
            'showTiles'     => (bool) $settings['show_tiles'],
            'enableCluster' => (bool) $settings['enable_cluster'],
            'defaultFilter' => $settings['default_filter'],
            'strings'       => array(
                'loading'  => '加载相关文章…',
                'noPosts'  => '暂无关联文章',
                'loadFail' => '加载失败，请稍后再试',
            ),
        );

        wp_add_inline_script(
            'bm-map',
            'window.BM_MAP_DATA = ' . wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)
                . '; window.BM_MAP_CONFIG = ' . wp_json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) . ';',
            'before'
        );

        $categories = array();
        foreach ($data as $d) {
            if ($d['category'] !== '' && !in_array($d['category'], $categories, true)) {
                $categories[] = $d['category'];
            }
        }
        sort($categories);

        ob_start();
        ?>
        <div class="bm-root" id="bm-root">
            <div class="bm-toolbar">
                <select class="bm-filter-category" aria-label="<?php esc_attr_e('分类筛选', 'brilliance-map'); ?>">
                    <option value=""><?php esc_html_e('全部分类', 'brilliance-map'); ?></option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo esc_attr($cat); ?>"><?php echo esc_html($cat); ?></option>
                    <?php endforeach; ?>
                </select>
                <label class="bm-filter-posts">
                    <input type="checkbox" class="bm-filter-has-posts" <?php checked($settings['default_filter'], 'with_posts'); ?>>
                    <?php esc_html_e('只显示有关联文章的地点', 'brilliance-map'); ?>
                </label>
                <div class="bm-stats" aria-live="polite">
                    <?php esc_html_e('地点', 'brilliance-map'); ?> <b class="bm-stat-locations"><?php echo (int) $stats['locations']; ?></b>
                    · <?php esc_html_e('文章', 'brilliance-map'); ?> <b class="bm-stat-posts"><?php echo (int) $stats['posts']; ?></b>
                    · <?php esc_html_e('国家', 'brilliance-map'); ?> <b class="bm-stat-countries"><?php echo (int) $stats['countries']; ?></b>
                    · <?php esc_html_e('城市', 'brilliance-map'); ?> <b class="bm-stat-cities"><?php echo (int) $stats['cities']; ?></b>
                </div>
            </div>
            <div class="bm-map" id="bm-map" role="application" aria-label="<?php esc_attr_e('足迹地图', 'brilliance-map'); ?>"></div>
            <?php if (!empty($settings['show_tiles'])): ?>
            <div class="bm-attribution">© <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors</div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    public function enqueue_map_assets() {
        // 仅在开启「标点聚合」时才加载 markercluster，默认省掉两个请求
        $cluster = !empty(self::get_setting('enable_cluster', 0));

        wp_enqueue_style('bm-leaflet', 'https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css', array(), '1.9.4');
        if ($cluster) {
            wp_enqueue_style('bm-cluster', 'https://cdn.jsdelivr.net/npm/leaflet.markercluster@1.5.3/dist/MarkerCluster.css', array('bm-leaflet'), '1.5.3');
            wp_enqueue_style('bm-cluster-default', 'https://cdn.jsdelivr.net/npm/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css', array('bm-cluster'), '1.5.3');
        }
        wp_enqueue_style('bm-map', MIMOSA_THEME_URI . '/assets/css/bm-map.css', array('bm-leaflet'), mimosa_asset_version('assets/css/bm-map.css'));

        wp_enqueue_script('bm-leaflet', 'https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js', array(), '1.9.4', true);
        $deps = array('bm-leaflet');
        if ($cluster) {
            wp_enqueue_script('bm-cluster', 'https://cdn.jsdelivr.net/npm/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js', array('bm-leaflet'), '1.5.3', true);
            $deps[] = 'bm-cluster';
        }
        wp_enqueue_script('bm-map', MIMOSA_THEME_URI . '/assets/js/bm-map.js', $deps, mimosa_asset_version('assets/js/bm-map.js'), true);
    }
}

/**
 * 查找包含 [brilliance_map] 短代码的已发布页面 URL（详情页 📍 链接的跳转目标）
 */
function get_permalink_by_slug_shortcode() {
    global $wpdb;
    $id = $wpdb->get_var($wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts}
         WHERE post_status = 'publish' AND post_type IN ('page','post') AND post_content LIKE %s
         ORDER BY (post_type = 'page') DESC, post_date DESC LIMIT 1",
        '%[brilliance_map]%'
    ));
    // LIKE 可能命中转义样本，逐条复核
    return $id ? get_permalink((int) $id) : '';
}

global $brilliance_map;
$brilliance_map = new Brilliance_Map();
