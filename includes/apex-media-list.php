<?php
/**
 * ACGN compatibility layer for the existing apex_media_* tables.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Apex_Media_List {
    const DB_VERSION = '8';
    const OPTION_DB_VERSION = 'apex_media_db_version';
    const TABLE_ITEMS = 'brilliance_acgn_items';
    const TABLE_TAGS = 'brilliance_acgn_tags';
    const TABLE_ITEM_TAGS = 'brilliance_acgn_item_tags';

    public function __construct() {
        // 新主名（brilliance_ 前缀）
        add_shortcode('brilliance_anime_list', array($this, 'shortcode_anime'));
        add_shortcode('brilliance_galgame_list', array($this, 'shortcode_galgame'));
        add_shortcode('brilliance_reading_list', array($this, 'shortcode_reading'));
        // 兼容旧名（apex_ 前缀），保证已有文章继续有效
        add_shortcode('apex_anime_list', array($this, 'shortcode_anime'));
        add_shortcode('apex_galgame_list', array($this, 'shortcode_galgame'));
        add_shortcode('apex_reading_list', array($this, 'shortcode_reading'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('admin_init', array($this, 'maybe_upgrade_db'));
        add_action('after_switch_theme', array($this, 'maybe_upgrade_db'));
        add_action('admin_menu', array($this, 'register_admin_page'));
        add_action('admin_post_apex_media_save_item', array($this, 'handle_admin_save'));
        add_action('admin_post_apex_media_delete_item', array($this, 'handle_admin_delete'));
        add_action('init', array($this, 'register_rewrites'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        add_filter('query_vars', array($this, 'register_query_vars'));
        add_filter('template_include', array($this, 'detail_template'));
    }

    private function items_table() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_ITEMS;
    }

    private function tags_table() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_TAGS;
    }

    private function item_tags_table() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_ITEM_TAGS;
    }

    public function maybe_upgrade_db() {
        global $wpdb;
        $items_table = $this->items_table();
        $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $items_table)) === $items_table;
        $columns = $table_exists ? $wpdb->get_col("SHOW COLUMNS FROM {$items_table}", 0) : array();
        $required = array('slug', 'score_10', 'bg_source_url', 'show_on_home_feed', 'finished_at', 'honmei', 'impression_text');
        if (get_option(self::OPTION_DB_VERSION) === self::DB_VERSION && !array_diff($required, (array) $columns)) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $tags_table = $this->tags_table();
        $item_tags_table = $this->item_tags_table();
        dbDelta("CREATE TABLE {$items_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            type varchar(16) NOT NULL DEFAULT '',
            title varchar(255) NOT NULL DEFAULT '',
            slug varchar(160) NULL,
            status varchar(16) NOT NULL DEFAULT '',
            bgm_url text NULL,
            cover_source_url text NULL,
            cover_attachment_id bigint(20) unsigned NULL,
            bg_source_url text NULL,
            bg_attachment_id bigint(20) unsigned NULL,
            score_100 smallint(5) unsigned NULL,
            score_10 decimal(4,2) NULL,
            score_base decimal(4,2) NULL,
            season_text varchar(64) NOT NULL DEFAULT '',
            year smallint(5) unsigned NULL,
            quarter varchar(8) NOT NULL DEFAULT '',
            review longtext NULL,
            finished_at datetime NULL,
            show_on_home tinyint(1) unsigned NOT NULL DEFAULT 0,
            show_on_home_feed tinyint(1) unsigned NOT NULL DEFAULT 0,
            home_rank int(11) NULL,
            legacy_post_id bigint(20) unsigned NULL,
            honmei tinyint(1) unsigned NOT NULL DEFAULT 0,
            impression_text varchar(100) NULL,
            created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY  (id),
            UNIQUE KEY slug (slug),
            KEY type_status (type,status),
            KEY home_feed (show_on_home_feed,finished_at,updated_at),
            KEY year_quarter (year,quarter)
        ) {$charset};");
        dbDelta("CREATE TABLE {$tags_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            slug varchar(64) NOT NULL DEFAULT '',
            name varchar(128) NOT NULL DEFAULT '',
            PRIMARY KEY  (id),
            UNIQUE KEY slug (slug)
        ) {$charset};");
        dbDelta("CREATE TABLE {$item_tags_table} (
            item_id bigint(20) unsigned NOT NULL,
            tag_id bigint(20) unsigned NOT NULL,
            PRIMARY KEY  (item_id,tag_id),
            KEY tag_id (tag_id)
        ) {$charset};");
        update_option(self::OPTION_DB_VERSION, self::DB_VERSION, false);
    }

    public function enqueue_assets() {
        if (!$this->is_frontend_asset_page()) {
            return;
        }
        wp_enqueue_style(
            'apex-media-list',
            MIMOSA_THEME_URI . '/assets/css/apex-media-list.css',
            array('mimosa-main'),
            mimosa_asset_version('assets/css/apex-media-list.css')
        );
        wp_enqueue_script(
            'apex-media-list',
            MIMOSA_THEME_URI . '/assets/js/apex-media-list.js',
            array(),
            mimosa_asset_version('assets/js/apex-media-list.js'),
            true
        );
    }

    private function is_frontend_asset_page() {
        if (is_home() || is_front_page() || get_query_var('apex_media_slug')) {
            return true;
        }
        if (!is_singular()) {
            return false;
        }
        $current_post = get_post();
        if (!($current_post instanceof WP_Post)) {
            return false;
        }
        return has_shortcode($current_post->post_content, 'brilliance_anime_list')
            || has_shortcode($current_post->post_content, 'brilliance_galgame_list')
            || has_shortcode($current_post->post_content, 'brilliance_reading_list')
            || has_shortcode($current_post->post_content, 'apex_anime_list')
            || has_shortcode($current_post->post_content, 'apex_galgame_list')
            || has_shortcode($current_post->post_content, 'apex_reading_list');
    }

    public function register_rewrites() {
        add_rewrite_rule('^acgn/([^/]+)/?$', 'index.php?apex_media_slug=$matches[1]', 'top');
    }

    public function register_query_vars($vars) {
        $vars[] = 'apex_media_slug';
        return $vars;
    }

    public function detail_template($template) {
        if (get_query_var('apex_media_slug')) {
            $detail = locate_template('media-detail.php');
            if ($detail) {
                return $detail;
            }
        }
        return $template;
    }

    public function media_item_get_by_slug($slug) {
        global $wpdb;
        $slug = sanitize_title($slug);
        if (!$slug) {
            return null;
        }
        $row = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . $this->items_table() . ' WHERE slug = %s LIMIT 1', $slug),
            ARRAY_A
        );
        return $row ?: null;
    }

    public function media_item_get_tags($item_id) {
        global $wpdb;
        $sql = $wpdb->prepare(
            'SELECT t.* FROM ' . $this->tags_table() . ' t INNER JOIN ' . $this->item_tags_table() . ' it ON it.tag_id = t.id WHERE it.item_id = %d ORDER BY t.name ASC',
            (int) $item_id
        );
        return $wpdb->get_results($sql, ARRAY_A) ?: array();
    }

    public function media_query($args = array()) {
        global $wpdb;
        $args = wp_parse_args($args, array(
            'types' => array(),
            'status' => array(),
            'show_on_home_feed' => null,
            'order_by' => 'finished_at',
            'order' => 'DESC',
            'limit' => 0,
            'offset' => 0,
            'search' => '',
        ));

        $where = array('1=1');
        $values = array();
        if ($args['types']) {
            $types = array_values(array_filter(array_map('sanitize_key', (array) $args['types'])));
            if ($types) {
                $where[] = 'type IN (' . implode(',', array_fill(0, count($types), '%s')) . ')';
                $values = array_merge($values, $types);
            }
        }
        if ($args['status']) {
            $statuses = array_values(array_filter(array_map('sanitize_key', (array) $args['status'])));
            if ($statuses) {
                $where[] = 'status IN (' . implode(',', array_fill(0, count($statuses), '%s')) . ')';
                $values = array_merge($values, $statuses);
            }
        }
        if ($args['show_on_home_feed'] !== null) {
            $where[] = 'show_on_home_feed = %d';
            $values[] = $args['show_on_home_feed'] ? 1 : 0;
        }
        if ($args['search']) {
            $term = '%' . $wpdb->esc_like($args['search']) . '%';
            $where[] = '(title LIKE %s OR review LIKE %s)';
            $values[] = $term;
            $values[] = $term;
        }

        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        $release_order = "CASE WHEN year IS NULL OR year = 0 THEN 1 ELSE 0 END ASC, year {$order}, CASE quarter WHEN 'winter' THEN 4 WHEN 'autumn' THEN 3 WHEN 'summer' THEN 2 WHEN 'spring' THEN 1 ELSE 0 END {$order}";
        $order_by = $args['order_by'] === 'score'
            ? 'honmei DESC, score_10'
            : ($args['order_by'] === 'created_at'
                ? 'created_at'
                : ($args['order_by'] === 'release'
                    ? $release_order
                    : 'COALESCE(finished_at, updated_at, created_at)'));
        $sql = 'SELECT * FROM ' . $this->items_table() . ' WHERE ' . implode(' AND ', $where) . " ORDER BY {$order_by} {$order}, id {$order}";
        if ((int) $args['limit'] > 0) {
            $sql .= ' LIMIT %d OFFSET %d';
            $values[] = (int) $args['limit'];
            $values[] = max(0, (int) $args['offset']);
        }
        if ($values) {
            $sql = $wpdb->prepare($sql, $values);
        }
        return $wpdb->get_results($sql, ARRAY_A) ?: array();
    }

    public function media_query_count($args = array()) {
        $args['limit'] = 0;
        return count($this->media_query($args));
    }

    public function get_media_by_id($item_id) {
        global $wpdb;
        $item_id = absint($item_id);
        if (!$item_id) {
            return null;
        }

        $item = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . $this->items_table() . ' WHERE id = %d LIMIT 1', $item_id),
            ARRAY_A
        );

        return is_array($item) ? $item : null;
    }

    public function resolve_score_10_from_row(array $row) {
        if (isset($row['score_10']) && $row['score_10'] !== null && $row['score_10'] !== '') {
            return (float) $row['score_10'];
        }
        return isset($row['score_100']) ? round(((float) $row['score_100']) / 10, 1) : 0;
    }

    public function resolve_finished_timestamp(array $row) {
        foreach (array('finished_at', 'updated_at', 'created_at') as $field) {
            if (!empty($row[$field]) && $row[$field] !== '0000-00-00 00:00:00') {
                $timestamp = strtotime($row[$field]);
                if ($timestamp) {
                    return $timestamp;
                }
            }
        }
        return 0;
    }

    public function get_media_detail_url(array $row) {
        $slug = !empty($row['slug']) ? $row['slug'] : (!empty($row['title']) ? $row['title'] : 'media-' . (int) $row['id']);
        return home_url('/acgn/' . sanitize_title($slug) . '/');
    }

    public function map_media_type_to_label($type) {
        $labels = array('anime' => '番剧', 'galgame' => '视觉小说', 'reading' => '阅读');
        return isset($labels[$type]) ? $labels[$type] : $type;
    }

    public function get_grade_from_score_10($score) {
        if ($score >= 9.9) return array('label' => '殿堂级', 'desc' => '少数能长期停留在记忆中的作品。');
        if ($score >= 9.3) return array('label' => '神作', 'desc' => '在叙事、表达或情绪层面达到极高水准。');
        if ($score >= 8.3) return array('label' => '优秀', 'desc' => '明显高于平均水平，有鲜明亮点。');
        if ($score >= 7.4) return array('label' => '佳作', 'desc' => '完成度扎实，表达清晰。');
        if ($score >= 6.9) return array('label' => '还行', 'desc' => '有可取之处，也有明显局限。');
        if ($score >= 6.0) return array('label' => '一般', 'desc' => '整体印象有限，适合特定受众。');
        if ($score > 0) return array('label' => '不推荐', 'desc' => '整体表现未达到推荐标准。');
        return array('label' => '', 'desc' => '');
    }

    /**
     * 本命作评级（与分数评级并行的特殊评级）
     */
    public function get_honmei_grade() {
        return array(
            'label' => '本命作',
            'desc'  => '综合质量不一定最好，却最能折服我的内心，完全对上我电波的作品。',
        );
    }

    /**
     * 印象集：返回所有已填写印象语的作品（契约固定，前端只认这份 JSON）
     */
    public function get_impressions_data() {
        global $wpdb;
        $rows = $wpdb->get_results(
            "SELECT * FROM " . $this->items_table() . "
             WHERE impression_text IS NOT NULL AND impression_text != ''
             ORDER BY honmei DESC, score_10 DESC, COALESCE(finished_at, updated_at, created_at) DESC",
            ARRAY_A
        );
        if (!is_array($rows)) {
            $rows = array();
        }

        $result = array();
        foreach ($rows as $row) {
            $type = isset($row['type']) ? (string) $row['type'] : '';
            list($cover, $cover_large) = $this->resolve_cover_urls($row);
            $result[] = array(
                'id'         => (int) $row['id'],
                'title'      => isset($row['title']) ? (string) $row['title'] : '',
                'type'       => $type,
                'typeLabel'  => $this->map_media_type_to_label($type),
                'cover'      => $cover,
                'coverLarge' => $cover_large !== '' ? $cover_large : $cover,
                'url'        => $this->get_media_detail_url($row),
                'score'      => $this->resolve_score_10_from_row($row),
                'isFavorite' => !empty($row['honmei']),
                'impression' => isset($row['impression_text']) ? (string) $row['impression_text'] : '',
            );
        }
        return $result;
    }

    public function register_rest_routes() {
        register_rest_route('acgn/v1', '/impressions', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'rest_handler_impressions'),
            'permission_callback' => '__return_true',
        ));
    }

    public function rest_handler_impressions() {
        return new WP_REST_Response($this->get_impressions_data(), 200);
    }

    // 返回 [小图(球体), 大图(卡片态)] 两档封面 URL
    private function resolve_cover_urls(array $row) {
        $att_id = isset($row['cover_attachment_id']) ? (int) $row['cover_attachment_id'] : 0;
        $source = isset($row['cover_source_url']) ? (string) $row['cover_source_url'] : '';
        $thumb = '';
        $large = '';
        if ($att_id > 0) {
            $medium = wp_get_attachment_image_url($att_id, 'medium');
            $full   = wp_get_attachment_image_url($att_id, 'large') ?: wp_get_attachment_image_url($att_id, 'full');
            $thumb  = $medium ?: ($full ?: '');
            $large  = $full   ?: ($medium ?: '');
        }
        if ($thumb === '') $thumb = $source;
        if ($large === '') $large = $source;
        return array($thumb, $large);
    }

    public function shortcode_anime($atts) {
        return $this->render_list('anime', $atts);
    }

    public function shortcode_galgame($atts) {
        return $this->render_list('galgame', $atts);
    }

    public function shortcode_reading($atts) {
        return $this->render_list('reading', $atts);
    }

    public function register_admin_page() {
        add_menu_page('ACGN 作品', 'ACGN 作品', 'manage_options', 'apex-media', array($this, 'render_admin_page'), 'dashicons-format-video', 31);
    }

    public function render_admin_page() {
        if (!current_user_can('manage_options')) wp_die(esc_html__('权限不足', 'mimosa'));
        $editing_item = null;
        if (!empty($_GET['media_id'])) {
            global $wpdb;
            $editing_item = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . $this->items_table() . ' WHERE id = %d', (int) $_GET['media_id']), ARRAY_A);
        }
        $admin_items = $this->media_query(array('order_by' => 'created_at', 'limit' => 0));
        require MIMOSA_THEME_DIR . '/includes/apex-media-admin-page.php';
    }

    private function unique_slug($requested_slug, $title, $item_id) {
        global $wpdb;
        $base = sanitize_title($requested_slug ?: $title);
        if (!$base) $base = 'media-' . ($item_id ?: wp_generate_uuid4());
        $slug = $base;
        $suffix = 2;
        while ((int) $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . $this->items_table() . ' WHERE slug = %s AND id <> %d LIMIT 1', $slug, $item_id))) {
            $slug = $base . '-' . $suffix++;
        }
        return $slug;
    }

    private function is_external_url($url) {
        $url_host = wp_parse_url($url, PHP_URL_HOST);
        $site_host = wp_parse_url(home_url('/'), PHP_URL_HOST);
        if (!$url_host) {
            return false;
        }

        return strtolower($url_host) !== strtolower((string) $site_host);
    }

    private function find_sideloaded_attachment($url) {
        global $wpdb;
        $attachment_id = attachment_url_to_postid($url);
        if ($attachment_id) {
            return (int) $attachment_id;
        }

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_source_url' AND meta_value = %s LIMIT 1",
            $url
        ));
    }

    // 侧载外链封面到媒体库；限制下载时间并按来源 URL 复用已有附件。
    private function sideload_cover_attachment_from_url($url) {
        global $wpdb;

        $url = esc_url_raw($url);
        if (!$url || !$this->is_external_url($url)) {
            return 0;
        }

        $existing_attachment = $this->find_sideloaded_attachment($url);
        if ($existing_attachment) {
            return $existing_attachment;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $temporary_file = null;
        $timeout_filter = static function ($timeout) { return min(5, (int) $timeout); };
        add_filter('http_request_timeout', $timeout_filter);
        try {
            $temporary_file = download_url($url, 5);
        } catch (Throwable $error) {
            $temporary_file = null;
        } finally {
            remove_filter('http_request_timeout', $timeout_filter);
        }

        if (!$temporary_file || is_wp_error($temporary_file) || !is_file($temporary_file)) {
            return 0;
        }

        $file_hash = hash_file('sha256', $temporary_file);
        $duplicate_attachment = $file_hash ? (int) $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_mimosa_cover_hash' AND meta_value = %s LIMIT 1",
            $file_hash
        )) : 0;
        if ($duplicate_attachment) {
            @unlink($temporary_file);
            return $duplicate_attachment;
        }

        $file = array(
            'name' => sanitize_file_name(wp_basename((string) wp_parse_url($url, PHP_URL_PATH)) ?: 'cover-image'),
            'tmp_name' => $temporary_file,
        );
        try {
            $attachment_id = media_handle_sideload($file, 0);
        } catch (Throwable $error) {
            $attachment_id = 0;
        }
        if (is_wp_error($attachment_id) || !$attachment_id) {
            @unlink($temporary_file);
            return 0;
        }

        update_post_meta((int) $attachment_id, '_source_url', $url);
        if ($file_hash) {
            update_post_meta((int) $attachment_id, '_mimosa_cover_hash', $file_hash);
        }
        return (int) $attachment_id;
    }

    public function handle_admin_save() {
        if (!current_user_can('manage_options')) wp_die(esc_html__('权限不足', 'mimosa'));
        check_admin_referer('apex_media_save_item');
        global $wpdb;
        $item_id = (int) ($_POST['media_id'] ?? 0);
        $type = sanitize_key(wp_unslash($_POST['media_type'] ?? 'anime'));
        $status = sanitize_key(wp_unslash($_POST['media_status'] ?? 'want'));
        $quarter = sanitize_key(wp_unslash($_POST['media_quarter'] ?? ''));
        if (!in_array($type, array('anime', 'galgame', 'reading'), true)) $type = 'anime';
        if (!in_array($status, array('want', 'watching', 'watched'), true)) $status = 'want';
        if (!in_array($quarter, array('', 'spring', 'summer', 'autumn', 'winter'), true)) $quarter = '';
        $title = sanitize_text_field(wp_unslash($_POST['media_title'] ?? ''));
        if (!$title) wp_die(esc_html__('标题不能为空', 'mimosa'));
        $score = isset($_POST['media_score_10']) && $_POST['media_score_10'] !== '' ? (float) $_POST['media_score_10'] : null;
        if ($score !== null) $score = max(0, min(10, $score));
        $finished = sanitize_text_field(wp_unslash($_POST['media_finished_at'] ?? ''));
        if ($finished && strlen($finished) === 10) $finished .= ' 00:00:00';
        $cover_source_url = esc_url_raw(wp_unslash($_POST['media_cover_source_url'] ?? ''));
        $existing_cover = $item_id ? $wpdb->get_row($wpdb->prepare(
            'SELECT cover_source_url, cover_attachment_id FROM ' . $this->items_table() . ' WHERE id = %d LIMIT 1',
            $item_id
        ), ARRAY_A) : null;
        $existing_cover_url = is_array($existing_cover) ? (string) ($existing_cover['cover_source_url'] ?? '') : '';
        $existing_cover_attachment_id = is_array($existing_cover) ? (int) ($existing_cover['cover_attachment_id'] ?? 0) : 0;
        $sideload_enabled = get_option('mimosa_acgn_cover_sideload_enable', 'yes') === 'yes';

        if (!$cover_source_url) {
            $cover_attachment_id = $existing_cover_attachment_id;
        } elseif (!$this->is_external_url($cover_source_url)) {
            $cover_attachment_id = (int) attachment_url_to_postid($cover_source_url);
        } elseif ($cover_source_url === $existing_cover_url && $existing_cover_attachment_id) {
            $cover_attachment_id = $existing_cover_attachment_id;
        } elseif ($sideload_enabled) {
            $cover_attachment_id = $this->sideload_cover_attachment_from_url($cover_source_url);
        } else {
            $cover_attachment_id = 0;
        }
        $impression = trim(sanitize_text_field(wp_unslash($_POST['media_impression'] ?? '')));
        $now = current_time('mysql');
        $data = array(
            'type' => $type, 'title' => $title,
            'slug' => $this->unique_slug(wp_unslash($_POST['media_slug'] ?? ''), $title, $item_id),
            'status' => $status, 'bgm_url' => esc_url_raw(wp_unslash($_POST['media_bgm_url'] ?? '')),
            'cover_source_url' => $cover_source_url,
            'cover_attachment_id' => $cover_attachment_id,
            'score_10' => $score, 'season_text' => sanitize_text_field(wp_unslash($_POST['media_season_text'] ?? '')),
            'year' => max(0, (int) ($_POST['media_year'] ?? 0)) ?: null, 'quarter' => $quarter,
            'review' => wp_kses_post(wp_unslash($_POST['media_review'] ?? '')), 'finished_at' => $finished ?: null,
            'show_on_home_feed' => empty($_POST['media_show_on_home_feed']) ? 0 : 1,
            'honmei' => empty($_POST['media_honmei']) ? 0 : 1,
            'impression_text' => ($impression !== '') ? $impression : null, 'updated_at' => $now,
        );
        if ($item_id) {
            $result = $wpdb->update($this->items_table(), $data, array('id' => $item_id));
        } else {
            $data['created_at'] = $now;
            $result = $wpdb->insert($this->items_table(), $data);
            $item_id = (int) $wpdb->insert_id;
        }
        if ($item_id) wp_cache_delete('apex_media_item_' . $item_id, 'apex_media');
        $redirect = add_query_arg(array('page' => 'apex-media', 'updated' => $result !== false ? 1 : 0), admin_url('admin.php'));
        wp_safe_redirect($redirect);
        exit;
    }

    public function handle_admin_delete() {
        if (!current_user_can('manage_options')) wp_die(esc_html__('权限不足', 'mimosa'));
        $item_id = (int) ($_GET['media_id'] ?? 0);
        check_admin_referer('apex_media_delete_' . $item_id);
        global $wpdb;
        $wpdb->delete($this->item_tags_table(), array('item_id' => $item_id), array('%d'));
        $wpdb->delete($this->items_table(), array('id' => $item_id), array('%d'));
        wp_cache_delete('apex_media_item_' . $item_id, 'apex_media');
        wp_safe_redirect(add_query_arg('page', 'apex-media', admin_url('admin.php')));
        exit;
    }



    private function render_list($type, $atts) {
        $atts = shortcode_atts(array('status' => 'all', 'order' => 'default', 'per_page' => 0, 'show_tabs' => 'true'), $atts);
        $order_by = $atts['order'] === 'score' ? 'score' : ($atts['order'] === 'time' ? 'release' : 'created_at');
        $items = $this->media_query(array(
            'types' => array($type),
            'status' => $atts['status'] === 'all' ? array() : array($atts['status']),
            'order_by' => $order_by,
            'limit' => (int) $atts['per_page'],
        ));
        $type_label = $this->map_media_type_to_label($type);
        $uid = 'apex-media-' . wp_unique_id();

        ob_start();
        ?>
        <?php
            $total_items = count($items);
            $finished_count = 0;
            $scores = [];
            $score_distribution = array_fill(0, 11, 0); // 初始化 0-10 分的分布数组

            // 兼容外部传入的 media_type，若未传入则从 $atts 推断或默认为 anime
            $media_type = $media_type ?? ($atts['type'] ?? 'anime');

            foreach ($items as $item) {
                // 统计已完成/已通关数量
                $status = $item['status'] ?? '';
                if ($status === 'watched') {
                    $finished_count++;
                }

                // 提取分数 (兼容 score_10 和 score 字段)
                $score = isset($item['score_10']) ? floatval($item['score_10']) : (isset($item['score']) ? floatval($item['score']) : 0);
                if ($score > 0) {
                    $scores[] = $score;
                    $rounded = intval(round($score));
                    if ($rounded >= 0 && $rounded <= 10) {
                        $score_distribution[$rounded]++;
                    }
                }
            }

            // 计算均值和中位数
            $avg_score = '';
            $median_score = '';
            if (!empty($scores)) {
                $avg_score = number_format(array_sum($scores) / count($scores), 1);
                sort($scores);
                $count_scores = count($scores);
                $mid = floor($count_scores / 2);
                if ($count_scores % 2 == 0) {
                    $median_score = number_format(($scores[$mid - 1] + $scores[$mid]) / 2, 1);
                } else {
                    $median_score = number_format($scores[$mid], 1);
                }
            }

            // 动态文案适配
            $is_galgame = ($media_type === 'galgame');
            $list_title = $is_galgame ? '视觉小说列表' : ($type_label . '列表');
            $finished_text = $is_galgame ? '已通关' : '已观看';

        ?>
        <section class="apex-media-list" id="<?php echo esc_attr($uid); ?>" data-type="<?php echo esc_attr($type); ?>">
            <style>
                .apex-baseline-line { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
                .apex-separator { width: 1px; height: 16px; background-color: #ccc; margin: 0 8px; }
                /* 适配暗色模式的分隔线 */
                [data-theme="dark"] .apex-separator { background-color: #475569; }
            </style>

            <div class="apex-media-list__panel">

                <?php if ($avg_score !== ''): ?>
                <!-- 有评分时：显示完整统计与图表 -->
                <div class="apex-baseline">
                    <div class="apex-baseline-line">
                        <span class="apex-baseline-title"><?php echo esc_html($list_title); ?></span>
                    </div>
                    <div class="apex-baseline-line">
                        <span class="apex-baseline-title">均值：</span>
                        <span class="apex-baseline-value"><?php echo esc_html($avg_score); ?></span>
                        <?php if ($median_score !== ''): ?>
                        <span class="apex-separator"></span>
                        <span class="apex-baseline-title">中位数：</span>
                        <span class="apex-baseline-value"><?php echo esc_html($median_score); ?></span>
                        <?php endif; ?>
                    </div>

                    <!-- 图表显示 -->
                    <div class="apex-baseline-line" style="width: 100%; margin-top: 16px; margin-bottom: 8px;">
                        <canvas id="<?php echo esc_attr($uid . '-chart'); ?>" height="150"></canvas>
                    </div>

                    <div class="apex-baseline-line apex-baseline-sub">
                        <span>
                            共收集 <?php echo intval($total_items); ?> 个作品条目，其中 <?php echo intval($finished_count); ?> 部作品<?php echo esc_html($finished_text); ?>
                        </span>
                    </div>
                </div>

                <script>
                (function(){
                    // 容错：确保 Chart.js 已加载
                    if (typeof Chart === 'undefined') {
                        console.warn('Chart.js is not loaded. Please include Chart.js to render the score distribution chart.');
                        return;
                    }

                    const ctx = document.getElementById('<?php echo esc_js($uid . "-chart"); ?>').getContext('2d');

                    // 动态获取当前主题颜色，完美适配亮/暗色模式
                    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
                    const textColor = isDark ? '#94a3b8' : '#64748b';
                    const gridColor = isDark ? '#334155' : '#e2e8f0';
                    const lineColor = isDark ? '#818cf8' : '#4f46e5'; // 暗色下使用更亮的靛蓝
                    const fillColor = isDark ? 'rgba(129, 140, 248, 0.1)' : 'rgba(79, 70, 229, 0.1)';

                    const chart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: <?php echo json_encode(range(0, 10)); ?>,
                            datasets: [{
                                label: '数量：',
                                data: <?php echo json_encode(array_values($score_distribution)); ?>,
                                fill: true,
                                backgroundColor: fillColor,
                                borderColor: lineColor,
                                tension: 0.3,
                                pointBackgroundColor: lineColor,
                                pointRadius: 4,
                                pointHoverRadius: 6,
                                borderWidth: 2
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                x: {
                                    title: { display: true, text: '分数（四舍五入）', color: textColor, font: { weight: 'bold' } },
                                    ticks: { stepSize: 1, color: textColor },
                                    grid: { color: gridColor, drawBorder: false }
                                },
                                y: {
                                    title: { display: true, text: '数量', color: textColor, font: { weight: 'bold' } },
                                    beginAtZero: true,
                                    precision: 0,
                                    ticks: { color: textColor, stepSize: 1 },
                                    grid: { color: gridColor, drawBorder: false }
                                }
                            },
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    backgroundColor: isDark ? '#1e293b' : '#ffffff',
                                    titleColor: isDark ? '#f1f5f9' : '#1a1d21',
                                    bodyColor: isDark ? '#94a3b8' : '#64748b',
                                    borderColor: isDark ? '#334155' : '#e2e8f0',
                                    borderWidth: 1
                                }
                            }
                        }
                    });
                })();
                </script>

                <?php else: ?>
                <!-- 无评分时：降级显示简单的基线 -->
                <div class="apex-baseline">
                    <div class="apex-baseline-line">
                        <span class="apex-baseline-title"><?php echo esc_html($list_title); ?></span>
                        <span class="apex-separator"></span>
                        <span class="apex-baseline-sub">共 <?php echo intval($total_items); ?> 个作品条目</span>
                    </div>
                </div>
                <?php endif; ?>

                <!-- 工具栏 -->
                <div class="apex-media-toolbar">
                    <?php if ($atts['show_tabs'] !== 'false') : ?>
                        <div class="apex-media-tabs" role="tablist" aria-label="状态筛选">
                            <button class="tab active" type="button" data-status="all">全部</button>
                            <button class="tab brilliance_acgn_btn brilliance_acgn_btn--outline" type="button" data-status="want">想看</button>
                            <button class="tab brilliance_acgn_btn brilliance_acgn_btn--outline" type="button" data-status="watching">进行中</button>
                            <button class="tab brilliance_acgn_btn brilliance_acgn_btn--outline" type="button" data-status="watched">已完成</button>
                            <button class="btn-filter brilliance_acgn_btn brilliance_acgn_btn--outline" type="button">精确查找</button>
                        </div>
                    <?php endif; ?>

                    <div class="apex-media-actions">
                        <div class="apex-media-sort">
                            <label for="<?php echo esc_attr($uid); ?>-sort">排序</label>
                            <select class="apex-sort-select brilliance_acgn_btn brilliance_acgn_btn--outline" id="<?php echo esc_attr($uid); ?>-sort">
                                <option value="default" <?php selected($atts['order'], 'default'); ?>>添加时间</option>
                                <option value="score" <?php selected($atts['order'], 'score'); ?>>评分</option>
                                <option value="time" <?php selected($atts['order'], 'time'); ?>>发售/放映时间</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            <div class="apex-media-grid">
                <?php foreach ($items as $item) :
                    $score = $this->resolve_score_10_from_row($item);
                    $created_timestamp = !empty($item['created_at']) ? strtotime($item['created_at']) : 0;
                    $updated_timestamp = !empty($item['updated_at']) ? strtotime($item['updated_at']) : 0;
                    $finished_timestamp = $this->resolve_finished_timestamp($item);
                    $quarter_order = array_search($item['quarter'] ?? '', array('', 'spring', 'summer', 'autumn', 'winter'), true);
                    $quarter_order = $quarter_order === false ? 0 : $quarter_order;
                    $review = preg_replace('/\s+/u', ' ', wp_strip_all_tags($item['review'] ?? ''));
                    $review_search_excerpt = mb_substr(trim((string) $review), 0, 160);
                ?>
                <div class="apex-card<?php echo !empty($item['honmei']) ? ' apex-card--honmei' : ''; ?>"
                     data-status="<?php echo esc_attr($item['status'] ?? ''); ?>"
                     data-score="<?php echo esc_attr($score); ?>"
                     data-honmei="<?php echo esc_attr(!empty($item['honmei']) ? 1 : 0); ?>"
                     data-time="<?php echo esc_attr($finished_timestamp); ?>"
                     data-updated="<?php echo esc_attr($updated_timestamp); ?>"
                     data-created="<?php echo esc_attr($created_timestamp); ?>"
                     data-year="<?php echo esc_attr((int) ($item['year'] ?? 0)); ?>"
                     data-quarter="<?php echo esc_attr($item['quarter'] ?? ''); ?>"
                     data-qorder="<?php echo esc_attr($quarter_order); ?>"
                     data-title="<?php echo esc_attr($item['title']); ?>"
                     data-review="<?php echo esc_attr($review_search_excerpt); ?>"
                     >
                    <?php
                    set_query_var('mimosa_media_item', $item);
                    get_template_part('template-parts/content-media-preview');
                    set_query_var('mimosa_media_item', null);
                    ?>
                </div>
                <?php endforeach; ?>
                <?php if (!$items) : ?><div class="apex-empty">暂无条目</div><?php endif; ?>
            </div>

                        <div class="apex-filter-modal" aria-hidden="true" role="dialog" aria-label="精确查找">
                            <div class="apex-modal-mask"></div>
                            <div class="apex-modal-dialog card">
                                <div class="apex-modal-header"><span class="apex-modal-title">精确查找</span><button class="apex-modal-close" type="button" aria-label="关闭">×</button></div>
                                <div class="apex-modal-body">
                                    <div class="filter-field"><label>关键词</label><input type="text" class="filter-keyword" placeholder="按标题或评价筛选"></div>
                                    <div class="filter-field"><label>年份</label><select class="filter-year"><option value="">全部年份</option></select></div>
                                    <div class="filter-field"><label>季度</label><select class="filter-quarter"><option value="">全部季度</option><option value="spring">春季</option><option value="summer">夏季</option><option value="autumn">秋季</option><option value="winter">冬季</option></select></div>
                                </div>
                                <div class="apex-modal-footer"><button class="btn-filter-reset brilliance_acgn_btn brilliance_acgn_btn--outline" type="button">重置</button><button class="btn-filter-apply" type="button">应用</button></div>
                            </div>
                        </div>
                    </section>
                    <?php
                    return ob_get_clean();
                }
            }

            global $apex_media_list;
            $apex_media_list = new Apex_Media_List();
