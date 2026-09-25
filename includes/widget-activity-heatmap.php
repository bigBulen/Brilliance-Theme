<?php
/**
 * 站点活跃热力图小工具
 *
 * GitHub 风格热力图：按天展示近一年「文章 / 说说 / 评论 / ACGN」的活跃度。
 * - 每个单元格 = 一天，内含最多 4 条色条（文章=紫、说说=绿、评论=橙、ACGN=蓝），数量越大色条越实。
 * - ACGN 以「通关时间」finished_at 为准，没有通关时间的不计入。
 * - 列序为倒序：最新的一天在最左，无需滑动即可看到最新活跃。
 * - 顶部带年份行与月份行；一月、首列、末列各标注年份。
 * - 数据查询结果缓存 6 小时（transient）。
 * - 颜色全部走 CSS 变量，暗/亮模式在 assets/css/activity-heatmap.css 中适配。
 */

if (!defined('ABSPATH')) exit;

/**
 * 小工具资源加载（样式）。重复调用只入队一次。
 */
function brilliance_enqueue_heatmap_assets() {
    wp_enqueue_style(
        'brilliance-activity-heatmap',
        MIMOSA_THEME_URI . '/assets/css/activity-heatmap.css',
        array(),
        mimosa_asset_version('assets/css/activity-heatmap.css')
    );
}

/**
 * 获取近 N 天活跃度数据（带缓存）。
 *
 * @return array {
 *   day:    array[], 每项 { date, post, shuo, comment, acgn }
 *   totals: array { post, shuo, comment, acgn }
 * }
 */
function brilliance_get_activity_heatmap($days = 365) {
    global $wpdb;

    $days = max(30, min(730, (int) $days));
    $cache_key = 'brilliance_activity_heatmap_v2_' . $days;
    $cached = get_transient($cache_key);
    if ($cached !== false) {
        return $cached;
    }

    $today_ts = current_time('timestamp');
    $start_ts = strtotime('-' . ($days - 1) . ' days', $today_ts);
    $start_sql = date('Y-m-d', $start_ts) . ' 00:00:00';

    $query_daily_counts = function ($sql) use ($wpdb, $start_sql) {
        $map = array();
        $rows = $wpdb->get_results($wpdb->prepare($sql, $start_sql));
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $map[$row->d] = (int) $row->c;
            }
        }
        return $map;
    };

    // 文章（post）
    $post_map = $query_daily_counts("
        SELECT DATE(post_date) AS d, COUNT(*) AS c
        FROM {$wpdb->posts}
        WHERE post_status = 'publish'
          AND post_type = 'post'
          AND post_date >= %s
        GROUP BY DATE(post_date)
    ");

    // 说说（shuoshuo）
    $shuo_map = $query_daily_counts("
        SELECT DATE(post_date) AS d, COUNT(*) AS c
        FROM {$wpdb->posts}
        WHERE post_status = 'publish'
          AND post_type = 'shuoshuo'
          AND post_date >= %s
        GROUP BY DATE(post_date)
    ");

    // 评论（仅已通过审核的普通评论）
    $comment_map = $query_daily_counts("
        SELECT DATE(comment_date) AS d, COUNT(*) AS c
        FROM {$wpdb->comments}
        WHERE comment_approved = '1'
          AND comment_type IN ('', 'comment')
          AND comment_date >= %s
        GROUP BY DATE(comment_date)
    ");

    // ACGN（以通关时间 finished_at 为准；无通关时间的不计入）
    $acgn_map = array();
    $acgn_table = $wpdb->prefix . 'brilliance_acgn_items';
    $acgn_table_exists = $wpdb->get_var("SHOW TABLES LIKE '" . esc_sql($acgn_table) . "'");
    if ($acgn_table_exists === $acgn_table) {
        $acgn_map = $query_daily_counts("
            SELECT DATE(finished_at) AS d, COUNT(*) AS c
            FROM {$acgn_table}
            WHERE finished_at IS NOT NULL
              AND finished_at != '0000-00-00 00:00:00'
              AND finished_at >= %s
            GROUP BY DATE(finished_at)
        ");
    }

    $data = array(
        'day' => array(),
        'totals' => array('post' => 0, 'shuo' => 0, 'comment' => 0, 'acgn' => 0),
    );

    for ($ts = $start_ts; $ts <= $today_ts; $ts += DAY_IN_SECONDS) {
        $d = date('Y-m-d', $ts);
        $post = isset($post_map[$d]) ? $post_map[$d] : 0;
        $shuo = isset($shuo_map[$d]) ? $shuo_map[$d] : 0;
        $comment = isset($comment_map[$d]) ? $comment_map[$d] : 0;
        $acgn = isset($acgn_map[$d]) ? $acgn_map[$d] : 0;

        $data['day'][] = array(
            'date' => $d,
            'post' => $post,
            'shuo' => $shuo,
            'comment' => $comment,
            'acgn' => $acgn,
        );

        $data['totals']['post'] += $post;
        $data['totals']['shuo'] += $shuo;
        $data['totals']['comment'] += $comment;
        $data['totals']['acgn'] += $acgn;
    }

    set_transient($cache_key, $data, 6 * HOUR_IN_SECONDS);

    return $data;
}

/**
 * 数量 -> 色条不透明度（GitHub 式分级）。
 */
function brilliance_heatmap_bar_opacity($count) {
    if ($count <= 0) return 0;
    if ($count === 1) return 0.5;
    if ($count === 2) return 0.7;
    if ($count <= 4) return 0.85;
    return 1;
}

/**
 * 渲染热力图 HTML。
 */
function brilliance_render_activity_heatmap() {
    $data = brilliance_get_activity_heatmap(365);
    $days = $data['day'];
    $totals = $data['totals'];

    if (empty($days)) {
        echo '<p class="ah-note">暂无活跃数据</p>';
        return;
    }

    // 按周分组（周日为一周起点，GitHub 布局），左侧留 padding 单元格对齐星期。
    $first_ts = strtotime($days[0]['date']);
    $weeks = array();
    $week = array();
    $start_dow = (int) date('w', $first_ts);
    for ($pad = 0; $pad < $start_dow; $pad++) {
        $week[] = null;
    }
    foreach ($days as $day) {
        $week[] = $day;
        if (count($week) === 7) {
            $weeks[] = $week;
            $week = array();
        }
    }
    if (!empty($week)) {
        while (count($week) < 7) {
            $week[] = null; // 补齐到 7 天，保证末尾这一周也占满 7 行
        }
        $weeks[] = $week;
    }

    // 倒序：最新的一周在最左，初始即显示最新活跃。
    $weeks = array_reverse($weeks);
    $total_weeks = count($weeks);

    // 顶部年份行 + 月份行：一月、首列、末列标注年份
    $month_labels = array();
    $year_labels = array();
    $last_month = -1;
    $last_year = -1;
    foreach ($weeks as $idx => $w) {
        $day_year = null;
        $day_month = null;
        foreach ($w as $day) {
            if ($day !== null) {
                $day_year = (int) substr($day['date'], 0, 4);
                $day_month = (int) substr($day['date'], 5, 2);
                break;
            }
        }
        $is_first = ($idx === 0);
        $is_last = ($idx === $total_weeks - 1);

        // 月份标签
        $m_label = '';
        if ($day_month !== null && $day_month !== $last_month) {
            $m_label = $day_month . '月';
            $last_month = $day_month;
        }
        $month_labels[] = $m_label;

        // 年份标签
        $y_label = '';
        if ($day_year !== null) {
            if ($is_first || $is_last || $day_year !== $last_year) {
                $y_label = (string) $day_year;
            }
            $last_year = $day_year;
        }
        $year_labels[] = $y_label;
    }

    $dims = array(
        'post'    => array('key' => 'post', 'label' => '文章'),
        'shuo'    => array('key' => 'shuo', 'label' => '说说'),
        'comment' => array('key' => 'comment', 'label' => '评论'),
        'acgn'    => array('key' => 'acgn', 'label' => 'ACGN'),
    );
    ?>
    <div class="ah-widget">
        <div class="ah-scroll">
            <div class="ah-body">
                <div class="ah-dow" aria-hidden="true">
                    <span>一</span><span>三</span><span>五</span>
                </div>
                <div class="ah-main">
                    <div class="ah-year" aria-hidden="true">
                        <?php foreach ($year_labels as $label): ?>
                        <span><?php echo esc_html($label); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <div class="ah-months" aria-hidden="true">
                        <?php foreach ($month_labels as $label): ?>
                        <span><?php echo esc_html($label); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <div class="ah-grid" role="img" aria-label="近一年站点活跃度热力图">
                        <?php
                        foreach ($weeks as $w) {
                            foreach ($w as $day) {
                                if ($day === null) {
                                    echo '<span class="ah-cell ah-cell--pad"></span>';
                                    continue;
                                }
                                $tip = $day['date'] . ' · 文章 ' . $day['post']
                                     . ' · 说说 ' . $day['shuo']
                                     . ' · 评论 ' . $day['comment']
                                     . ' · ACGN ' . $day['acgn'];
                                echo '<span class="ah-cell" title="' . esc_attr($tip) . '">';
                                foreach ($dims as $dim) {
                                    $value = $day[$dim['key']];
                                    if ($value <= 0) {
                                        continue;
                                    }
                                    echo '<i class="ah-bar ah-bar--' . esc_attr($dim['key'])
                                       . '" style="--o:' . esc_attr(brilliance_heatmap_bar_opacity($value)) . '"></i>';
                                }
                                echo '</span>';
                            }
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="ah-legend">
            <?php foreach ($dims as $dim_key => $dim): ?>
            <span class="ah-legend__item">
                <i class="ah-legend__swatch ah-bar--<?php echo esc_attr($dim_key); ?>"></i>
                <?php echo esc_html($dim['label']); ?>
                <b><?php echo intval($totals[$dim_key]); ?></b>
            </span>
            <?php endforeach; ?>
        </div>

        <p class="ah-note">仅统计近 365 天 · 颜色区分维度，深浅表示当日数量</p>
    </div>
    <?php
}

/**
 * WP_Widget：站点活跃热力图
 */
class Brilliance_Activity_Heatmap_Widget extends WP_Widget {

    public function __construct() {
        parent::__construct(
            'brilliance_activity_heatmap',
            __('站点活跃热力图', 'mimosa'),
            array(
                'description' => __('GitHub 风格，展示近一年文章、说说、评论、ACGN 的活跃度', 'mimosa'),
                'classname'   => 'brilliance-heatmap-widget',
            )
        );
    }

    public function widget($args, $instance) {
        brilliance_enqueue_heatmap_assets();

        echo $args['before_widget'];

        $title = apply_filters('widget_title', isset($instance['title']) ? $instance['title'] : '');
        if ($title === '') {
            $title = __('站点活跃度', 'mimosa');
        }
        echo $args['before_title'] . esc_html($title) . $args['after_title'];

        brilliance_render_activity_heatmap();

        echo $args['after_widget'];
    }

    public function form($instance) {
        $title = isset($instance['title']) ? $instance['title'] : '';
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>"><?php _e('标题：', 'mimosa'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('title')); ?>" type="text"
                   value="<?php echo esc_attr($title); ?>" placeholder="站点活跃度">
        </p>
        <?php
    }

    public function update($new_instance, $old_instance) {
        $instance = $old_instance;
        $instance['title'] = sanitize_text_field(isset($new_instance['title']) ? $new_instance['title'] : '');
        return $instance;
    }
}

/* ── Gutenberg 动态区块渲染回调 ── */
function brilliance_render_activity_heatmap_block($attrs) {
    brilliance_enqueue_heatmap_assets();
    ob_start();
    echo '<h2 class="widget-title ">';
    _e('站点活跃度', 'mimosa');
    echo '</h2>';

    echo '<div class="brilliance-block-heatmap">';
    brilliance_render_activity_heatmap();
    echo '</div>';
    return ob_get_clean();
}
