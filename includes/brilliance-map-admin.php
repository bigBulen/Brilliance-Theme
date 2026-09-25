<?php
/**
 * 足迹地图 - 后台管理
 *
 * - 地点管理：列表（搜索/国家/城市筛选/分页）、新增/编辑表单、删除、查重提示
 * - 编辑页下方展示当前反查到的关联文章（只读；关联关系在文章编辑页设置）
 * - 古腾堡「关联地点」面板资源入队
 */

if (!defined('ABSPATH')) exit;

class Brilliance_Map_Admin {

    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'menu'));
        add_action('admin_init', array(__CLASS__, 'register_settings'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'assets'));
        add_action('wp_ajax_bm_dup_check', array(__CLASS__, 'ajax_dup_check'));
        add_action('wp_ajax_bm_delete_location', array(__CLASS__, 'ajax_delete_location'));
        add_action('wp_ajax_bm_save_location', array(__CLASS__, 'ajax_save_location'));
        add_action('enqueue_block_editor_assets', array(__CLASS__, 'editor_assets'));
        add_action('admin_post_bm_flush_cache', array(__CLASS__, 'handle_flush_cache'));
        add_action('admin_post_bm_wipe_data', array(__CLASS__, 'handle_wipe_data'));
    }

    public static function menu() {
        $page = add_menu_page(
            '足迹地图', '足迹地图', 'manage_options', 'brilliance-map',
            array(__CLASS__, 'render_page'), 'dashicons-location-alt', 31
        );
        add_submenu_page('brilliance-map', '地点管理', '地点管理', 'manage_options', 'brilliance-map', array(__CLASS__, 'render_page'));
        add_submenu_page('brilliance-map', '新增地点', '新增地点', 'manage_options', 'brilliance-map-add', array(__CLASS__, 'render_add_page'));
        add_submenu_page('brilliance-map', '地图设置', '地图设置', 'manage_options', 'brilliance-map-settings', array(__CLASS__, 'render_settings_page'));
        // 编辑页为隐藏子页（不出现在菜单）
        add_submenu_page(null, '编辑地点', '编辑地点', 'manage_options', 'brilliance-map-edit', array(__CLASS__, 'render_edit_page'));
    }

    public static function register_settings() {
        register_setting('bm_map_settings_group', Brilliance_Map::OPTION_KEY, array(
            'type'              => 'array',
            'sanitize_callback' => array('Brilliance_Map', 'sanitize_settings'),
            'default'           => Brilliance_Map::default_settings(),
        ));
    }

    public static function assets($hook) {
        if (strpos($hook, 'brilliance-map') === false) {
            return;
        }
        wp_enqueue_style('bm-admin', MIMOSA_THEME_URI . '/assets/css/bm-admin.css', array(), mimosa_asset_version('assets/css/bm-admin.css'));
        wp_enqueue_script('bm-admin', MIMOSA_THEME_URI . '/assets/js/bm-admin.js', array(), mimosa_asset_version('assets/js/bm-admin.js'), true);
        wp_localize_script('bm-admin', 'BM_ADMIN', array(
            'ajaxUrl'  => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('bm_admin'),
            'dupTitle' => '发现疑似重复地点',
            'listUrl'  => admin_url('admin.php?page=brilliance-map'),
        ));
    }

    public static function editor_assets() {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen && !in_array($screen->post_type, Brilliance_Map::post_types(), true)) {
            return;
        }
        wp_enqueue_style('bm-editor-panel', MIMOSA_THEME_URI . '/assets/css/bm-editor-panel.css', array(), mimosa_asset_version('assets/css/bm-editor-panel.css'));
        wp_enqueue_script('bm-editor-panel', MIMOSA_THEME_URI . '/assets/js/bm-editor-panel.js', array('wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-i18n', 'wp-api-fetch'), mimosa_asset_version('assets/js/bm-editor-panel.js'), true);
        wp_localize_script('bm-editor-panel', 'BM_EDITOR', array(
            'metaKey'   => Brilliance_Map::META_KEY,
            'postTypes' => Brilliance_Map::post_types(),
            'restBase'  => esc_url_raw(rest_url('brilliance-map/v1')),
        ));
    }

    /* ═══════════ 列表页 ═══════════ */

    public static function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die('权限不足');
        }
        global $wpdb, $brilliance_map;
        $table = $brilliance_map->locations_table();
        $rel_table = $brilliance_map->relations_table();

        $s     = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $fc    = isset($_GET['fc']) ? sanitize_text_field(wp_unslash($_GET['fc'])) : '';
        $fcity = isset($_GET['fcity']) ? sanitize_text_field(wp_unslash($_GET['fcity'])) : '';
        $paged = max(1, isset($_GET['paged']) ? absint($_GET['paged']) : 1);
        $per   = 20;

        $where = '1=1';
        $args  = array();
        if ($s !== '') {
            $like = '%' . $wpdb->esc_like($s) . '%';
            $where .= ' AND (name LIKE %s OR city LIKE %s OR country_name LIKE %s OR description LIKE %s)';
            $args[] = $like; $args[] = $like; $args[] = $like; $args[] = $like;
        }
        if ($fc !== '') {
            $where .= ' AND (UPPER(country_code) = %s OR country_name LIKE %s)';
            $args[] = strtoupper($fc);
            $args[] = '%' . $wpdb->esc_like($fc) . '%';
        }
        if ($fcity !== '') {
            $where .= ' AND city LIKE %s';
            $args[] = '%' . $wpdb->esc_like($fcity) . '%';
        }

        $total = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE {$where}", $args));
        $offset = ($paged - 1) * $per;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE {$where} ORDER BY updated_at DESC, id DESC LIMIT %d OFFSET %d",
            array_merge($args, array($per, $offset))
        ), ARRAY_A) ?: array();

        // 关联文章数（只统计已发布且在启用类型内）
        $types = Brilliance_Map::post_types();
        $placeholders = implode(',', array_fill(0, count($types), '%s'));
        $count_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT r.location_id, COUNT(DISTINCT r.post_id) AS c
             FROM {$rel_table} r INNER JOIN {$wpdb->posts} p ON p.ID = r.post_id
             WHERE p.post_status = 'publish' AND p.post_type IN ({$placeholders})
             GROUP BY r.location_id",
            $types
        )) ?: array();
        $counts = array();
        foreach ($count_rows as $cr) {
            $counts[(int) $cr->location_id] = (int) $cr->c;
        }

        // 筛选下拉选项
        $countries = $wpdb->get_results("SELECT DISTINCT country_code, country_name FROM {$table} WHERE country_code != '' OR country_name != '' ORDER BY country_name ASC") ?: array();
        $cities = $wpdb->get_col("SELECT DISTINCT city FROM {$table} WHERE city != '' ORDER BY city ASC") ?: array();

        $total_pages = max(1, (int) ceil($total / $per));
        ?>
        <div class="wrap bm-admin">
            <h1 class="wp-heading-inline">足迹地图 · 地点管理</h1>
            <a href="<?php echo esc_url(admin_url('admin.php?page=brilliance-map-add')); ?>" class="page-title-action">新增地点</a>
            <hr class="wp-header-end">

            <?php if (!empty($_GET['bm_notice'])): ?>
                <div class="notice notice-success is-dismissible"><p><?php echo esc_html(sanitize_text_field(wp_unslash($_GET['bm_notice']))); ?></p></div>
            <?php endif; ?>

            <form method="get" class="bm-filters">
                <input type="hidden" name="page" value="brilliance-map">
                <p class="search-box">
                    <label class="screen-reader-text" for="bm-search">搜索地点</label>
                    <input type="search" id="bm-search" name="s" value="<?php echo esc_attr($s); ?>" placeholder="名称/城市/国家/描述">
                    <input type="submit" class="button" value="搜索">
                </p>
                <select name="fc">
                    <option value="">全部国家</option>
                    <?php foreach ($countries as $c): ?>
                        <?php $val = $c->country_code !== '' ? $c->country_code : $c->country_name; ?>
                        <option value="<?php echo esc_attr($val); ?>" <?php selected($fc, $val); ?>><?php echo esc_html(($c->country_name ?: $c->country_code)); ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="fcity">
                    <option value="">全部城市</option>
                    <?php foreach ($cities as $city): ?>
                        <option value="<?php echo esc_attr($city); ?>" <?php selected($fcity, $city); ?>><?php echo esc_html($city); ?></option>
                    <?php endforeach; ?>
                </select>
            </form>

            <div class="bm-admin__meta">共 <?php echo (int) $total; ?> 个地点</div>

            <table class="widefat striped bm-table">
                <thead>
                    <tr>
                        <th>ID</th><th>名称</th><th>城市</th><th>国家</th><th>分类</th>
                        <th>关联文章</th><th>到访</th><th>更新</th><th>操作</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="9">暂无地点，点击右上角「新增地点」。</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $row): $lid = (int) $row['id']; ?>
                    <tr>
                        <td><?php echo $lid; ?></td>
                        <td><strong><?php echo esc_html($row['name']); ?></strong></td>
                        <td><?php echo esc_html($row['city']); ?></td>
                        <td><?php echo esc_html(trim($row['country_name'] . ($row['country_code'] ? ' (' . $row['country_code'] . ')' : ''))); ?></td>
                        <td><?php echo esc_html((string) $row['category']); ?></td>
                        <td><?php echo isset($counts[$lid]) ? (int) $counts[$lid] : 0; ?></td>
                        <td><?php echo $row['visit_count'] !== null ? '×' . (int) $row['visit_count'] : '—'; ?><?php echo $row['visit_date'] && $row['visit_date'] !== '0000-00-00' ? '<br><small>' . esc_html($row['visit_date']) . '</small>' : ''; ?></td>
                        <td><small><?php echo esc_html($row['updated_at'] ? substr((string) $row['updated_at'], 0, 10) : '—'); ?></small></td>
                        <td>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=brilliance-map-edit&id=' . $lid)); ?>">编辑</a> ·
                            <a href="#" class="bm-delete" data-id="<?php echo $lid; ?>" data-name="<?php echo esc_attr($row['name']); ?>" style="color:#b32d2e;">删除</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($total_pages > 1): ?>
                <div class="tablenav"><div class="tablenav-pages">
                    <?php
                    echo paginate_links(array(
                        'base'    => add_query_arg('paged', '%#%'),
                        'format'  => '',
                        'current' => $paged,
                        'total'   => $total_pages,
                    ));
                    ?>
                </div></div>
            <?php endif; ?>
        </div>
        <?php
    }

    /* ═══════════ 新增 / 编辑表单页 ═══════════ */

    public static function render_add_page() {
        if (!current_user_can('manage_options')) {
            wp_die('权限不足');
        }
        self::render_form(array(), 'add');
    }

    public static function render_edit_page() {
        if (!current_user_can('manage_options')) {
            wp_die('权限不足');
        }
        global $brilliance_map;
        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        $item = $id ? $brilliance_map->get_location($id) : null;
        if (!$item) {
            wp_die('地点不存在');
        }
        self::render_form($item, 'edit');
    }

    private static function render_form($item_raw, $mode) {
        global $brilliance_map, $wpdb;

        $errors = array();
        $item = array_merge(array(
            'id' => 0, 'name' => '', 'lat' => '', 'lng' => '',
            'country_code' => '', 'country_name' => '', 'city' => '', 'category' => '',
            'description' => '', 'custom_link' => '', 'visit_count' => '', 'visit_date' => '',
            'is_geo_fuzzed' => 0,
        ), is_array($item_raw) ? $item_raw : array());

        // 表单提交（同步处理后重定向，避免刷新重复提交）
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bm_form_nonce']) && wp_verify_nonce(sanitize_key(wp_unslash($_POST['bm_form_nonce'])), 'bm_save_location')) {
            $input = wp_unslash($_POST);
            $result = $brilliance_map->validate_location_input($input);
            if ($result['errors']) {
                $errors = $result['errors'];
                $item = array_merge($item, $input);
            } else {
                $id = $mode === 'edit' ? absint($input['id']) : 0;
                $saved = $brilliance_map->save_location($result['data'], $id);
                if (is_wp_error($saved)) {
                    $errors[] = $saved->get_error_message();
                    $item = array_merge($item, $input);
                } else {
                    $back = $mode === 'edit'
                        ? admin_url('admin.php?page=brilliance-map-edit&id=' . $saved . '&bm_notice=' . rawurlencode('地点已保存'))
                        : admin_url('admin.php?page=brilliance-map&bm_notice=' . rawurlencode('地点已创建'));
                    wp_safe_redirect($back);
                    exit;
                }
            }
        }

        $is_edit = $mode === 'edit' && (int) $item['id'] > 0;

        // 关联文章（只读，反查关系表）
        $related = $is_edit ? $brilliance_map->posts_for_location((int) $item['id'], 100) : array();

        // 已录入的分类（datalist 建议）
        $categories = $wpdb->get_col('SELECT DISTINCT category FROM ' . $brilliance_map->locations_table() . " WHERE category IS NOT NULL AND category != '' ORDER BY category ASC") ?: array();
        ?>
        <div class="wrap bm-admin bm-form-wrap">
            <h1><?php echo $is_edit ? '编辑地点 #' . (int) $item['id'] : '新增地点'; ?></h1>

            <?php foreach ($errors as $err): ?>
                <div class="notice notice-error"><p><?php echo esc_html($err); ?></p></div>
            <?php endforeach; ?>

            <form method="post" id="bm-location-form" class="bm-form">
                <?php wp_nonce_field('bm_save_location', 'bm_form_nonce'); ?>
                <input type="hidden" name="id" value="<?php echo esc_attr((int) $item['id']); ?>">

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="bm_name">名称 <span class="bm-req">*</span></label></th>
                        <td>
                            <input type="text" id="bm_name" name="name" class="regular-text" value="<?php echo esc_attr($item['name']); ?>" required>
                            <div id="bm-dup-hint"></div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bm_lat">坐标 <span class="bm-req">*</span></label></th>
                        <td class="bm-coords">
                            <input type="text" id="bm_lat" name="lat" class="small-text" value="<?php echo esc_attr($item['lat']); ?>" placeholder="纬度，如 39.904690" inputmode="decimal">
                            <input type="text" id="bm_lng" name="lng" class="small-text" value="<?php echo esc_attr($item['lng']); ?>" placeholder="经度，如 116.407170" inputmode="decimal">
                            <p class="description">WGS84 十进制度；纬度 -90~90，经度 -180~180。</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bm_country_code">国家代码</label></th>
                        <td>
                            <input type="text" id="bm_country_code" name="country_code" class="small-text" maxlength="8" value="<?php echo esc_attr($item['country_code']); ?>" placeholder="如 CN">
                            <p class="description">ISO 3166-1 alpha-2 大写代码，统计国家数量的唯一判定依据，请尽量规范填写。</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bm_country_name">国家名称</label></th>
                        <td>
                            <input type="text" id="bm_country_name" name="country_name" class="regular-text" value="<?php echo esc_attr($item['country_name']); ?>">
                            <p class="description">请统一使用规范中文名（如「中国」），避免「中国 / China / 中华人民共和国」混用；仅作展示。</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bm_city">城市</label></th>
                        <td><input type="text" id="bm_city" name="city" class="regular-text" value="<?php echo esc_attr($item['city']); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bm_category">分类</label></th>
                        <td>
                            <input type="text" id="bm_category" name="category" class="regular-text" list="bm-category-list" value="<?php echo esc_attr($item['category']); ?>" placeholder="如：旅行 / 求学 / 演出">
                            <datalist id="bm-category-list">
                                <?php foreach ($categories as $c): ?>
                                    <option value="<?php echo esc_attr($c); ?>"></option>
                                <?php endforeach; ?>
                            </datalist>
                            <p class="description">自由文本，可从已有分类中选择。</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bm_description">描述</label></th>
                        <td>
                            <textarea id="bm_description" name="description" rows="5" class="large-text code"><?php echo esc_textarea($item['description']); ?></textarea>
                            <p class="description">地图弹窗中展示，支持基础 HTML。</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bm_custom_link">自定义跳转链接</label></th>
                        <td>
                            <input type="url" id="bm_custom_link" name="custom_link" class="large-text" value="<?php echo esc_attr($item['custom_link']); ?>" placeholder="https://">
                            <p class="description">设置后，点击标点直接跳转此链接而不再弹出信息框（二选一）。</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bm_visit_count">到访次数</label></th>
                        <td><input type="number" id="bm_visit_count" name="visit_count" class="small-text" min="0" step="1" value="<?php echo esc_attr($item['visit_count']); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bm_visit_date">到访日期</label></th>
                        <td><input type="date" id="bm_visit_date" name="visit_date" value="<?php echo esc_attr($item['visit_date']); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row">坐标模糊化</th>
                        <td>
                            <label><input type="checkbox" name="is_geo_fuzzed" value="1" <?php checked((int) $item['is_geo_fuzzed'], 1); ?>> 前台展示时对该点坐标做模糊化（预留功能，第二阶段启用）</label>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary"><?php echo $is_edit ? '保存修改' : '创建地点'; ?></button>
                    <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=brilliance-map')); ?>">返回列表</a>
                </p>
            </form>

            <?php if ($is_edit): ?>
                <div class="bm-related">
                    <h2>关联文章（只读）</h2>
                    <p class="description">关联关系在文章/说说编辑页的「关联地点」面板中设置；此处仅展示反查结果。</p>
                    <?php if (!$related): ?>
                        <p>暂无关联的已发布文章。</p>
                    <?php else: ?>
                        <table class="widefat striped">
                            <thead><tr><th>标题</th><th>类型</th><th>日期</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($related as $p): ?>
                                <tr>
                                    <td><?php echo esc_html($p['title']); ?></td>
                                    <td><?php echo esc_html($p['type']); ?></td>
                                    <td><?php echo esc_html($p['date']); ?></td>
                                    <td><a href="<?php echo esc_url($p['url']); ?>" target="_blank" rel="noopener">查看</a></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /* ═══════════ 地图设置页 ═══════════ */

    public static function render_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die('权限不足');
        }
        global $brilliance_map;

        $settings  = Brilliance_Map::get_settings();
        $payload   = $brilliance_map->cached_payload();
        $stats     = $payload['stats'];
        $generated = (int) $payload['generated'];
        $available = Brilliance_Map::available_post_types();
        $enabled   = (array) $settings['post_types'];
        $auto_url  = $brilliance_map->map_page_url();
        ?>
        <div class="wrap bm-admin bm-settings">
            <h1>足迹地图 · 地图设置</h1>
            <hr class="wp-header-end">

            <?php if (!empty($_GET['bm_notice'])): ?>
                <div class="notice notice-success is-dismissible"><p><?php echo esc_html(sanitize_text_field(wp_unslash($_GET['bm_notice']))); ?></p></div>
            <?php endif; ?>
            <?php if (isset($_GET['settings-updated'])): ?>
                <div class="notice notice-success is-dismissible"><p>设置已保存，地图缓存已刷新。</p></div>
            <?php endif; ?>

            <div class="bm-stats-panel">
                <h2>统计信息（只读）</h2>
                <ul class="bm-stats-panel__list">
                    <li><span>已记录地点</span><b><?php echo (int) $stats['locations']; ?></b> 个</li>
                    <li><span>涉及文章（去重）</span><b><?php echo (int) $stats['posts']; ?></b> 篇</li>
                    <li><span>覆盖国家（按 country_code 去重）</span><b><?php echo (int) $stats['countries']; ?></b> 个</li>
                    <li><span>覆盖城市（按 country_code + city 去重）</span><b><?php echo (int) $stats['cities']; ?></b> 个</li>
                </ul>
                <p class="description">
                    缓存生成于 <?php echo esc_html($generated ? wp_date('Y-m-d H:i:s', $generated) : '—'); ?>，
                    过期 <?php echo esc_html(Brilliance_Map::CACHE_TTL / 3600); ?> 小时；数据变更会自动失效。
                </p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="bm_flush_cache">
                    <?php wp_nonce_field('bm_flush_cache'); ?>
                    <button type="submit" class="button">立即清除地图缓存</button>
                </form>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields('bm_map_settings_group'); ?>

                <h2>初始视野</h2>
                <p class="description">仅决定打开地图时看到什么，不限制用户后续平移与缩放。</p>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">默认视野</th>
                        <td>
                            <?php
                            $views = array(
                                'world'  => '世界',
                                'china'  => '中国',
                                'custom' => '自定义',
                            );
                            foreach ($views as $val => $label):
                            ?>
                                <label class="bm-radio">
                                    <input type="radio" name="<?php echo esc_attr(Brilliance_Map::OPTION_KEY); ?>[initial_view]" value="<?php echo esc_attr($val); ?>" <?php checked($settings['initial_view'], $val); ?>>
                                    <?php echo esc_html($label); ?>
                                </label>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">自定义中心与缩放</th>
                        <td class="bm-coords">
                            <label>纬度 <input type="text" name="<?php echo esc_attr(Brilliance_Map::OPTION_KEY); ?>[custom_lat]" class="small-text" value="<?php echo esc_attr($settings['custom_lat']); ?>"></label>
                            <label>经度 <input type="text" name="<?php echo esc_attr(Brilliance_Map::OPTION_KEY); ?>[custom_lng]" class="small-text" value="<?php echo esc_attr($settings['custom_lng']); ?>"></label>
                            <label>缩放 <input type="number" name="<?php echo esc_attr(Brilliance_Map::OPTION_KEY); ?>[custom_zoom]" class="small-text" min="1" max="18" step="1" value="<?php echo esc_attr($settings['custom_zoom']); ?>"></label>
                            <p class="description">选择「自定义」时生效；缩放级别 1（最远）~ 18（最近）。</p>
                        </td>
                    </tr>
                </table>

                <h2>显示选项</h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">前台元素</th>
                        <td>
                            <label class="bm-check"><input type="checkbox" name="<?php echo esc_attr(Brilliance_Map::OPTION_KEY); ?>[show_markers]" value="1" <?php checked((int) $settings['show_markers'], 1); ?>> 显示地点标记</label><br>
                            <label class="bm-check"><input type="checkbox" name="<?php echo esc_attr(Brilliance_Map::OPTION_KEY); ?>[show_outlines]" value="1" <?php checked((int) $settings['show_outlines'], 1); ?>> 显示国家轮廓背景</label><br>
                            <label class="bm-check"><input type="checkbox" name="<?php echo esc_attr(Brilliance_Map::OPTION_KEY); ?>[show_post_badge]" value="1" <?php checked((int) $settings['show_post_badge'], 1); ?>> 显示相关文章数量徽标</label><br>
                            <label class="bm-check"><input type="checkbox" name="<?php echo esc_attr(Brilliance_Map::OPTION_KEY); ?>[show_tiles]" value="1" <?php checked((int) $settings['show_tiles'], 1); ?>> 显示街区瓦片（OSM 底图）</label>
                            <p class="description">关闭后不加载任何街道瓦片，仅保留国家轮廓与标点（推荐，地图更干净、请求更少）；开启后使用下方「瓦片地址」配置的底图。</p>
                            <label class="bm-check"><input type="checkbox" name="<?php echo esc_attr(Brilliance_Map::OPTION_KEY); ?>[enable_cluster]" value="1" <?php checked((int) $settings['enable_cluster'], 1); ?>> 标点聚合（密集时合并为数字气泡）</label>
                            <p class="description">关闭时标点密集处允许重叠；地名标签会自动避让，仅密集区域显示部分名称，放大分散后逐步显示。</p>
                        </td>
                    </tr>
                </table>

                <h2>内容范围</h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">启用的文章类型</th>
                        <td>
                            <?php foreach ($available as $name => $label): ?>
                                <label class="bm-check">
                                    <input type="checkbox" name="<?php echo esc_attr(Brilliance_Map::OPTION_KEY); ?>[post_types][]" value="<?php echo esc_attr($name); ?>" <?php checked(in_array($name, $enabled, true)); ?>>
                                    <?php echo esc_html($label . '（' . $name . '）'); ?>
                                </label><br>
                            <?php endforeach; ?>
                            <p class="description">只有在此勾选的文章类型才会参与关联、计数与弹窗展示。至少保留一项。</p>
                        </td>
                    </tr>
                </table>

                <h2>筛选选项</h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">前台默认状态</th>
                        <td>
                            <label class="bm-radio"><input type="radio" name="<?php echo esc_attr(Brilliance_Map::OPTION_KEY); ?>[default_filter]" value="all" <?php checked($settings['default_filter'], 'all'); ?>> 全部地点</label>
                            <label class="bm-radio"><input type="radio" name="<?php echo esc_attr(Brilliance_Map::OPTION_KEY); ?>[default_filter]" value="with_posts" <?php checked($settings['default_filter'], 'with_posts'); ?>> 只显示有关联文章的地点</label>
                            <p class="description">仅决定前台初始状态，用户仍可在地图工具栏随时切换。</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bm_map_page_url">大地图页面 URL</label></th>
                        <td>
                            <input type="url" id="bm_map_page_url" name="<?php echo esc_attr(Brilliance_Map::OPTION_KEY); ?>[map_page_url]" class="large-text" value="<?php echo esc_attr($settings['map_page_url']); ?>" placeholder="<?php echo esc_attr($auto_url ?: 'https://example.com/footprint/'); ?>">
                            <p class="description">详情页 📍 链接与侧边栏小地图的跳转目标。留空则自动搜索包含 <code>[brilliance_map]</code> 短代码的页面。</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bm_tile_url">瓦片地址</label></th>
                        <td>
                            <input type="text" id="bm_tile_url" name="<?php echo esc_attr(Brilliance_Map::OPTION_KEY); ?>[tile_url]" class="large-text" value="<?php echo esc_attr($settings['tile_url']); ?>" placeholder="https://tile.openstreetmap.org/{z}/{x}/{y}.png">
                            <p class="description">仅在上方勾选「显示街区瓦片」时生效。留空使用默认 OpenStreetMap 官方瓦片；可换成自建/第三方服务，请遵守相应使用政策。</p>
                        </td>
                    </tr>
                </table>

                <?php submit_button('保存设置'); ?>
            </form>

            <div class="bm-danger">
                <h2>危险操作</h2>
                <p class="description">清空所有地图数据：删除全部地点与文章-地点关联（数据表结构保留），不可恢复。</p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="bm-wipe-form">
                    <input type="hidden" name="action" value="bm_wipe_data">
                    <?php wp_nonce_field('bm_wipe_data'); ?>
                    <button type="submit" class="button button-link-delete bm-wipe-data" data-name="清空所有地图数据">清空所有地图数据</button>
                </form>
            </div>
        </div>
        <?php
    }

    public static function handle_flush_cache() {
        if (!current_user_can('manage_options')) {
            wp_die('权限不足');
        }
        check_admin_referer('bm_flush_cache');
        Brilliance_Map::flush_cache();
        wp_safe_redirect(add_query_arg('bm_notice', rawurlencode('地图缓存已清除'), admin_url('admin.php?page=brilliance-map-settings')));
        exit;
    }

    public static function handle_wipe_data() {
        if (!current_user_can('manage_options')) {
            wp_die('权限不足');
        }
        check_admin_referer('bm_wipe_data');
        global $brilliance_map;
        $brilliance_map->wipe_all_data();
        wp_safe_redirect(add_query_arg('bm_notice', rawurlencode('已清空所有地图数据'), admin_url('admin.php?page=brilliance-map-settings')));
        exit;
    }

    /* ═══════════ AJAX ═══════════ */

    public static function ajax_dup_check() {
        check_ajax_referer('bm_admin', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(null, 403);
        }
        global $brilliance_map;
        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $city = isset($_POST['city']) ? sanitize_text_field(wp_unslash($_POST['city'])) : '';
        $country_code = isset($_POST['country_code']) ? sanitize_text_field(wp_unslash($_POST['country_code'])) : '';
        $exclude = isset($_POST['exclude_id']) ? absint(wp_unslash($_POST['exclude_id'])) : 0;

        $rows = $brilliance_map->find_similar($name, $city, $country_code, $exclude);
        $base = admin_url('admin.php?page=brilliance-map-edit');
        $out = array();
        foreach ($rows as $row) {
            $out[] = array(
                'id'      => (int) $row['id'],
                'name'    => $row['name'],
                'city'    => $row['city'],
                'country' => $row['country_name'],
                'url'     => add_query_arg('id', (int) $row['id'], $base),
            );
        }
        wp_send_json_success($out);
    }

    public static function ajax_delete_location() {
        check_ajax_referer('bm_admin', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(null, 403);
        }
        global $brilliance_map;
        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        if (!$id) {
            wp_send_json_error('缺少 ID');
        }
        $brilliance_map->delete_location($id);
        wp_send_json_success(array('deleted' => $id));
    }

    /** 供后台表单复用校验/保存（同页面 POST 已在 render_form 中处理，此处保留 AJAX 通道备用） */
    public static function ajax_save_location() {
        check_ajax_referer('bm_admin', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(null, 403);
        }
        global $brilliance_map;
        $input = wp_unslash($_POST);
        $result = $brilliance_map->validate_location_input($input);
        if ($result['errors']) {
            wp_send_json_error(implode('；', $result['errors']));
        }
        $id = isset($input['id']) ? absint($input['id']) : 0;
        $saved = $brilliance_map->save_location($result['data'], $id);
        if (is_wp_error($saved)) {
            wp_send_json_error($saved->get_error_message());
        }
        wp_send_json_success(array('id' => $saved));
    }
}

Brilliance_Map_Admin::init();
