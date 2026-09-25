<?php
/** Timeline calendar core and frontend. */
if (!defined('ABSPATH')) exit;

class Mimosa_Timeline_Calendar {
    private $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'brilliance_calendar_events';
        add_shortcode('brilliance_calendar', array($this, 'shortcode')); // 新主名
        add_shortcode('argon_timeline', array($this, 'shortcode'));      // 兼容别名
        add_shortcode('mimosa_calendar', array($this, 'shortcode'));     // 兼容别名
        add_shortcode('mimosa_timeline', array($this, 'shortcode'));     // 老版别名（转发）
        add_action('wp_enqueue_scripts', array($this, 'frontend_assets'));
        add_action('admin_init', array($this, 'maybe_create_table'));
        add_action('init', array($this, 'maybe_create_table'), 5);
        add_action('after_switch_theme', array($this, 'maybe_create_table'));
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'admin_assets'));
        add_action('wp_ajax_timeline_save_event', array($this, 'ajax_save'));
        add_action('wp_ajax_timeline_delete_event', array($this, 'ajax_delete'));
        add_action('wp_ajax_timeline_get_event', array($this, 'ajax_get'));
    }

    public function maybe_create_table() {
        global $wpdb;
        $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $this->table)) === $this->table;
        if (get_option('mimosa_timeline_db_version') === '1.2' && $table_exists) return;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $sql = "CREATE TABLE {$this->table} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            description text,
            event_date date NOT NULL,
            event_date_end date DEFAULT NULL,
            date_type varchar(20) DEFAULT 'exact',
            event_time time DEFAULT NULL,
            category varchar(100) DEFAULT 'general',
            text_color varchar(7) DEFAULT '#333333',
            background_color varchar(7) DEFAULT '#f8f9fa',
            icon varchar(100) DEFAULT 'fas fa-calendar',
            status varchar(20) DEFAULT 'published',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY event_date (event_date),
            KEY status (status)
        ) " . $wpdb->get_charset_collate() . ';';
        dbDelta($sql);
        update_option('mimosa_timeline_db_version', '1.2', false);
    }

    private function has_timeline_shortcode() {
        if (!is_singular()) return false;
        $current = get_post();
        return $current instanceof WP_Post && (
            has_shortcode($current->post_content, 'brilliance_calendar') ||
            has_shortcode($current->post_content, 'argon_timeline') ||
            has_shortcode($current->post_content, 'mimosa_calendar') ||
            has_shortcode($current->post_content, 'mimosa_timeline')
        );
    }

    public function frontend_assets() {
        if (!$this->has_timeline_shortcode()) return;
        wp_enqueue_style('mimosa-timeline', MIMOSA_THEME_URI . '/assets/css/timeline-calendar.css', array('mimosa-main'), mimosa_asset_version('assets/css/timeline-calendar.css'));
        wp_enqueue_script('mimosa-timeline', MIMOSA_THEME_URI . '/assets/js/timeline-calendar.js', array('jquery'), mimosa_asset_version('assets/js/timeline-calendar.js'), true);
    }

    public function admin_assets($hook) {
        if (strpos($hook, 'timeline-calendar') === false) return;
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        wp_enqueue_style('mimosa-timeline-admin', MIMOSA_THEME_URI . '/assets/css/timeline-admin.css', array(), mimosa_asset_version('assets/css/timeline-admin.css'));
        wp_enqueue_script('mimosa-timeline-admin', MIMOSA_THEME_URI . '/assets/js/timeline-admin.js', array('jquery', 'wp-color-picker'), mimosa_asset_version('assets/js/timeline-admin.js'), true);
        wp_localize_script('mimosa-timeline-admin', 'timeline_ajax', array('ajax_url' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('timeline_nonce')));
    }

    public function admin_menu() {
        add_menu_page('时间轴日历', '时间轴日历', 'manage_options', 'timeline-calendar', array($this, 'admin_page'), 'dashicons-calendar-alt', 30);
    }

    public function shortcode($atts) {
        global $wpdb;
        $atts = shortcode_atts(array('limit' => -1, 'category' => '', 'status' => 'published'), $atts);
        $where = array('status = %s');
        $values = array(sanitize_key($atts['status']));
        if ($atts['category']) { $where[] = 'category = %s'; $values[] = sanitize_text_field($atts['category']); }
        $limit = (int) $atts['limit'] > 0 ? ' LIMIT ' . (int) $atts['limit'] : '';
        $sql = "SELECT * FROM {$this->table} WHERE " . implode(' AND ', $where) . " ORDER BY event_date ASC, event_time ASC, id ASC{$limit}";
        $events = $wpdb->get_results($wpdb->prepare($sql, $values));
        if (!$events) return '<div class="timeline-calendar-empty">暂无事件</div>';

        $groups = array();
        foreach ($events as $event) {
            $ts = strtotime($event->event_date);
            $groups[(int) date('Y', $ts)][(int) date('n', $ts)][] = $event;
        }
        ksort($groups, SORT_NUMERIC);
        $now_year = (int) current_time('Y');
        $now_month = (int) current_time('n');
        ob_start(); ?>
        <div class="argon-timeline-calendar" id="argon-timeline-calendar"><div class="timeline-container">
        <?php foreach ($groups as $year => $months) : ksort($months, SORT_NUMERIC); ?>
            <section class="timeline-year-group <?php echo $year < $now_year ? 'timeline-year-collapsed' : ''; ?>" data-year="<?php echo (int) $year; ?>">
                <button class="timeline-year-header" type="button"><?php echo (int) $year; ?>年<span class="timeline-toggle timeline-year-toggle"></span></button>
                <div class="timeline-year-events">
                <?php foreach ($months as $month => $month_events) : ?>
                    <section class="timeline-month-wrapper <?php echo ($year < $now_year || ($year === $now_year && $month < $now_month)) ? 'timeline-month-collapsed' : ''; ?>" id="timeline-month-<?php echo (int) $year; ?>-<?php echo (int) $month; ?>">
                        <button class="timeline-month-header" type="button"><?php echo (int) $month; ?>月<span class="timeline-toggle timeline-month-toggle"></span></button>
                        <div class="timeline-month-events">
                        <?php foreach ($month_events as $event) : ?>
                            <article class="timeline-item timeline-item-<?php echo esc_attr($event->date_type ?: 'exact'); ?>" data-category="<?php echo esc_attr($event->category); ?>">
                                <div class="timeline-marker"><i class="<?php echo esc_attr($event->icon ?: 'fas fa-calendar'); ?>"></i></div>
                                <div class="timeline-content" style="color:<?php echo esc_attr($event->text_color); ?>;background-color:<?php echo esc_attr($event->background_color); ?>">
                                    <div class="timeline-date"><?php echo wp_kses_post($this->date_label($event)); ?><?php if ($event->event_time) : ?><span class="timeline-time"><?php echo esc_html(substr($event->event_time, 0, 5)); ?></span><?php endif; ?></div>
                                    <h3 class="timeline-title" style="color:<?php echo esc_attr($event->text_color); ?>"><?php echo esc_html($event->title); ?></h3>
                                    <?php if ($event->description) : ?><div class="timeline-description"><?php echo wpautop(wp_kses_post($event->description)); ?></div><?php endif; ?>
                                    <div class="timeline-category category-<?php echo esc_attr($event->category); ?>"><?php echo esc_html($event->category); ?></div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
        </div></div>
        <?php return ob_get_clean();
    }

    private function date_label($event) {
        if ($event->date_type === 'month') return esc_html(date_i18n('n月', strtotime($event->event_date))) . '<span class="timeline-date-fuzzy">（日期待定）</span>';
        if ($event->date_type === 'range' && $event->event_date_end) return esc_html(date_i18n('n月j日', strtotime($event->event_date)) . ' - ' . date_i18n('n月j日', strtotime($event->event_date_end)));
        return esc_html(date_i18n('n月j日', strtotime($event->event_date)));
    }

    private function normalize_dates() {
        $type = sanitize_key(wp_unslash($_POST['date_type'] ?? 'exact'));
        if (!in_array($type, array('exact', 'month', 'range'), true)) $type = 'exact';
        if ($type === 'month') {
            $month = sanitize_text_field(wp_unslash($_POST['event_month'] ?? ''));
            return preg_match('/^\d{4}-\d{2}$/', $month) ? array('date_type' => $type, 'event_date' => $month . '-01', 'event_date_end' => null) : new WP_Error('date', '请选择有效月份');
        }
        if ($type === 'range') {
            $start = sanitize_text_field(wp_unslash($_POST['event_date_start'] ?? ''));
            $end = sanitize_text_field(wp_unslash($_POST['event_date_end'] ?? ''));
            return ($start && $end && strtotime($start) <= strtotime($end)) ? array('date_type' => $type, 'event_date' => $start, 'event_date_end' => $end) : new WP_Error('date', '请填写有效日期范围');
        }
        $date = sanitize_text_field(wp_unslash($_POST['event_date'] ?? ''));
        return $date ? array('date_type' => $type, 'event_date' => $date, 'event_date_end' => null) : new WP_Error('date', '请选择事件日期');
    }

    public function ajax_save() {
        check_ajax_referer('timeline_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('权限不足', 403);
        $dates = $this->normalize_dates();
        if (is_wp_error($dates)) wp_send_json_error($dates->get_error_message());
        global $wpdb;
        $status = sanitize_key(wp_unslash($_POST['status'] ?? 'published'));
        $data = array_merge($dates, array(
            'title' => sanitize_text_field(wp_unslash($_POST['title'] ?? '')),
            'description' => sanitize_textarea_field(wp_unslash($_POST['description'] ?? '')),
            'event_time' => sanitize_text_field(wp_unslash($_POST['event_time'] ?? '')) ?: null,
            'category' => sanitize_text_field(wp_unslash($_POST['category'] ?? 'general')),
            'text_color' => sanitize_hex_color(wp_unslash($_POST['text_color'] ?? '')) ?: '#333333',
            'background_color' => sanitize_hex_color(wp_unslash($_POST['background_color'] ?? '')) ?: '#f8f9fa',
            'icon' => sanitize_text_field(wp_unslash($_POST['icon'] ?? 'fas fa-calendar')),
            'status' => in_array($status, array('published', 'draft'), true) ? $status : 'published',
        ));
        if (!$data['title']) wp_send_json_error('请填写事件标题');
        $id = (int) ($_POST['event_id'] ?? 0);
        $result = $id ? $wpdb->update($this->table, $data, array('id' => $id)) : $wpdb->insert($this->table, $data);
        $result !== false ? wp_send_json_success('保存成功') : wp_send_json_error($wpdb->last_error ?: '保存失败');
    }

    public function ajax_delete() {
        check_ajax_referer('timeline_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('权限不足', 403);
        global $wpdb;
        $result = $wpdb->delete($this->table, array('id' => (int) ($_POST['event_id'] ?? 0)), array('%d'));
        $result !== false ? wp_send_json_success('删除成功') : wp_send_json_error('删除失败');
    }

    public function ajax_get() {
        check_ajax_referer('timeline_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('权限不足', 403);
        global $wpdb;
        $event = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", (int) ($_POST['event_id'] ?? 0)));
        $event ? wp_send_json_success($event) : wp_send_json_error('事件不存在');
    }

    public function admin_page() {
        require MIMOSA_THEME_DIR . '/includes/timeline-admin-page.php';
    }

    public function get_admin_events() {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM {$this->table} ORDER BY event_date ASC, event_time ASC, id ASC");
    }

    public function get_admin_categories() {
        global $wpdb;
        $rows = $wpdb->get_col("SELECT DISTINCT category FROM {$this->table} WHERE category IS NOT NULL AND category <> '' ORDER BY category ASC");
        return is_array($rows) ? $rows : array();
    }
}

new Mimosa_Timeline_Calendar();
