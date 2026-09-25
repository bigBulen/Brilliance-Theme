<?php
/**
 * 服务器状态监测小工具 / 动态区块
 *
 * - 数据采集：CPU / 内存 / 硬盘 / 网络上下行 / 站点延迟 / SSL 证书到期
 * - 采集方式：WP-Cron 定时任务（默认每 5 分钟）跑一次，结果存 option，
 *   前端只读缓存，不实时执行系统调用。
 * - 采集失败的项目留 null，渲染时优雅降级（显示 —）。
 * - Linux 走 /proc 读取；非 Linux 仅保留硬盘（disk_*_space）+ 延迟 + SSL。
 */

if (!defined('ABSPATH')) exit;

/* ── 资源加载 ── */
function brilliance_enqueue_status_monitor_assets() {
    wp_enqueue_style(
        'brilliance-status-monitor',
        MIMOSA_THEME_URI . '/assets/css/status-monitor.css',
        array(),
        mimosa_asset_version('assets/css/status-monitor.css')
    );
}

/* ── 工具：从 /proc/meminfo 取某 key 的数值 ── */
function _brilliance_proc_kv($content, $key) {
    if (preg_match('/' . $key . ':\s+(\d+)/', $content, $m)) {
        return (int) $m[1];
    }
    return 0;
}

/* ── 工具：字节格式化 ── */
function brilliance_format_bytes($bytes, $per_sec = false) {
    if ($bytes === null) return '—';
    $bytes = (float) $bytes;
    if ($bytes < 0) $bytes = 0;
    $units = array('B', 'KB', 'MB', 'GB', 'TB', 'PB');
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    $dec = $i === 0 ? 0 : 1;
    return round($bytes, $dec) . ' ' . $units[$i] . ($per_sec ? '/s' : '');
}

/* ── 工具：读取 /proc（open_basedir 限制 file_get_contents 时回退 shell） ── */
function _brilliance_read_proc($path) {
    $content = @file_get_contents($path);
    if ($content !== false) return $content;

    if (function_exists('shell_exec')) {
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        if (!in_array('shell_exec', $disabled, true)) {
            $out = @shell_exec('cat ' . escapeshellarg($path));
            if (is_string($out) && $out !== '') return $out;
        }
    }
    return false;
}

/* ── Linux：读取 /proc/stat 的 CPU 时间片 ── */
function _brilliance_read_cpu() {
    $stat = _brilliance_read_proc('/proc/stat');
    if ($stat === false) return null;
    $lines = explode("\n", $stat);
    $parts = preg_split('/\s+/', trim(isset($lines[0]) ? $lines[0] : ''));
    if (count($parts) < 8) return null;
    $idle = (int) $parts[4];
    $total = 0;
    for ($i = 1; $i <= 7; $i++) {
        $total += isset($parts[$i]) ? (int) $parts[$i] : 0;
    }
    return array('idle' => $idle, 'total' => $total);
}

/* ── Linux：读取 /proc/net/dev 的收发字节合计（跳过 lo） ── */
function _brilliance_read_net() {
    $net = _brilliance_read_proc('/proc/net/dev');
    if ($net === false) return null;
    $rx = 0; $tx = 0; $found = false;
    foreach (explode("\n", $net) as $line) {
        if (!preg_match('/^\s*(\S+):\s*/', $line, $h)) continue;
        if ($h[1] === 'lo') continue;
        if (preg_match('/:\s*(\d+)\s+\d+\s+\d+\s+\d+\s+\d+\s+\d+\s+\d+\s+\d+\s+(\d+)/', $line, $m)) {
            $rx += (int) $m[1];
            $tx += (int) $m[2];
            $found = true;
        }
    }
    return $found ? array('rx' => $rx, 'tx' => $tx) : null;
}

/* ── 等级判定 ── */
function brilliance_status_level_percent($pct) {
    if ($pct === null) return 'none';
    if ($pct < 60) return 'good';
    if ($pct < 85) return 'warn';
    return 'bad';
}
function brilliance_status_level_latency($ms) {
    if ($ms === null) return 'none';
    if ($ms < 150) return 'good';
    if ($ms < 400) return 'warn';
    return 'bad';
}
function brilliance_status_level_ssl($days) {
    if ($days === null) return 'none';
    if ($days > 30) return 'good';
    if ($days > 7) return 'warn';
    return 'bad';
}

/* ── 采集全部指标 ── */
function brilliance_collect_status_metrics() {
    $metrics = array(
        'os'            => function_exists('php_uname') ? php_uname('s') : PHP_OS,
        'host'          => wp_parse_url(home_url('/'), PHP_URL_HOST),
        'cpu'           => null,
        'memory'        => null,
        'disk'          => null,
        'net_up'        => null,
        'net_down'      => null,
        'latency_ms'    => null,
        'ssl_days_left' => null,
        'ssl_valid'     => null,
        'ssl_expiry'    => null,
        'updated'       => current_time('mysql'),
        'updated_ts'    => time(),
    );

    // 硬盘（跨平台；根目录失败时回退站点目录，兼容 open_basedir）
    $disk_paths = (PHP_OS_FAMILY === 'Windows') ? array('C:\\', 'C:/') : array('/', ABSPATH);
    foreach ($disk_paths as $disk_path) {
        $disk_total = @disk_total_space($disk_path);
        $disk_free = @disk_free_space($disk_path);
        if ($disk_total !== false && $disk_free !== false && $disk_total > 0) {
            $used = $disk_total - $disk_free;
            $metrics['disk'] = array(
                'used'    => $used,
                'total'   => $disk_total,
                'percent' => round($used / $disk_total * 100, 1),
            );
            break;
        }
    }

    // Linux：内存 + CPU + 网络（单次采集即可得到数值，无需等待下次 cron）
    if (PHP_OS_FAMILY === 'Linux') {
        // 内存（老内核无 MemAvailable 则回退 MemFree + Buffers + Cached）
        $mem = _brilliance_read_proc('/proc/meminfo');
        if ($mem !== false) {
            $mem_total = _brilliance_proc_kv($mem, 'MemTotal');
            $mem_avail = _brilliance_proc_kv($mem, 'MemAvailable');
            if ($mem_avail <= 0) {
                $mem_avail = _brilliance_proc_kv($mem, 'MemFree')
                    + _brilliance_proc_kv($mem, 'Buffers')
                    + _brilliance_proc_kv($mem, 'Cached');
            }
            if ($mem_total > 0) {
                $used_kb = max(0, $mem_total - $mem_avail);
                $metrics['memory'] = array(
                    'used'    => $used_kb * 1024,
                    'total'   => $mem_total * 1024,
                    'percent' => round($used_kb / $mem_total * 100, 1),
                );
            }
        }

        // CPU + 网络：1 秒双采样求差值
        $cpu_a = _brilliance_read_cpu();
        $net_a = _brilliance_read_net();
        usleep(1000000); // 1s
        $cpu_b = _brilliance_read_cpu();
        $net_b = _brilliance_read_net();

        if (is_array($cpu_a) && is_array($cpu_b)) {
            $d_total = $cpu_b['total'] - $cpu_a['total'];
            $d_idle  = $cpu_b['idle'] - $cpu_a['idle'];
            if ($d_total > 0 && $d_idle >= 0) {
                $metrics['cpu'] = max(0, min(100, round((1 - $d_idle / $d_total) * 100, 1)));
            }
        }

        if (is_array($net_a) && is_array($net_b)) {
            $metrics['net_down'] = max(0, (int) round($net_b['rx'] - $net_a['rx']));
            $metrics['net_up']   = max(0, (int) round($net_b['tx'] - $net_a['tx']));
        }
    }

    // 站点延迟：请求首页并计时
    $home = home_url('/');
    $start = microtime(true);
    $resp = wp_remote_get($home, array(
        'timeout'   => 8,
        'sslverify' => is_ssl(),
    ));
    $elapsed = (microtime(true) - $start) * 1000;
    if (!is_wp_error($resp)) {
        $metrics['latency_ms'] = (int) round($elapsed);
    }

    // SSL 证书到期
    $host = $metrics['host'];
    if ($host && function_exists('stream_socket_client') && function_exists('openssl_x509_parse')) {
        $ctx = stream_context_create(array('ssl' => array(
            'capture_peer_cert'  => true,
            'verify_peer'        => false,
            'verify_peer_name'   => false,
        )));
        $errno = 0; $errstr = '';
        $socket = @stream_socket_client('ssl://' . $host . ':443', $errno, $errstr, 5, STREAM_CLIENT_CONNECT, $ctx);
        if ($socket) {
            $params = stream_context_get_params($socket);
            if (!empty($params['options']['ssl']['peer_certificate'])) {
                $cert = openssl_x509_parse($params['options']['ssl']['peer_certificate'], false);
                if (is_array($cert) && !empty($cert['validTo_time_t'])) {
                    $days = (int) floor(($cert['validTo_time_t'] - time()) / 86400);
                    $metrics['ssl_days_left'] = $days;
                    $metrics['ssl_valid']     = $days >= 0;
                    $metrics['ssl_expiry']    = gmdate('Y-m-d', $cert['validTo_time_t']);
                }
            }
            fclose($socket);
        }
    }

    return $metrics;
}

/* ── 上下文判断：是否运行在 WP-CLI / CLI（无站点级 open_basedir 限制） ── */
function brilliance_is_cli_context() {
    return (defined('WP_CLI') && WP_CLI) || PHP_SAPI === 'cli';
}

/* ── Cron：采集并写缓存 ── */
function brilliance_status_monitor_cron_cb() {
    // cli 模式且非 CLI 上下文：跳过，避免 php-fpm（WP-Cron）用 NULL 覆盖 CLI 采集的数据
    if (get_option('brilliance_status_collect_mode', 'wpcron') === 'cli' && !brilliance_is_cli_context()) {
        return;
    }
    $data = brilliance_collect_status_metrics();
    update_option('brilliance_status_monitor_data', $data, false);
}
add_action('brilliance_status_monitor_collect', 'brilliance_status_monitor_cron_cb');

/* ── 自定义 cron 间隔：5 分钟 ── */
function brilliance_add_cron_interval($schedules) {
    $schedules['brilliance_every_5min'] = array(
        'interval' => 300,
        'display'  => __('每 5 分钟', 'mimosa'),
    );
    return $schedules;
}
add_filter('cron_schedules', 'brilliance_add_cron_interval');

/* ── 主题切换时注册/清理 cron（cli 模式则停用 WP-Cron） ── */
function brilliance_register_status_cron() {
    if (get_option('brilliance_status_collect_mode', 'wpcron') === 'cli') {
        // cli 模式：由系统 crontab 调 WP-CLI 采集，此处停掉 WP-Cron，防止 php-fpm 覆盖
        wp_clear_scheduled_hook('brilliance_status_monitor_collect');
        return;
    }
    if (!wp_next_scheduled('brilliance_status_monitor_collect')) {
        wp_schedule_event(time() + 60, 'brilliance_every_5min', 'brilliance_status_monitor_collect');
    }
}
add_action('after_switch_theme', 'brilliance_register_status_cron');
add_action('admin_init', 'brilliance_register_status_cron');

function brilliance_clear_status_cron() {
    wp_clear_scheduled_hook('brilliance_status_monitor_collect');
}
add_action('switch_theme', 'brilliance_clear_status_cron');

/* ── WP-CLI 命令：wp brilliance status-collect（cli 模式由系统 crontab 调用） ── */
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('brilliance status-collect', function () {
        brilliance_status_monitor_cron_cb();
        $data = get_option('brilliance_status_monitor_data', array());
        if (!empty($data['cpu']) || !empty($data['net_down'])) {
            WP_CLI::success('状态监测采集完成');
            if (isset($data['cpu'])) WP_CLI::log('  cpu: ' . $data['cpu'] . '%');
            if (isset($data['memory']['percent'])) WP_CLI::log('  mem: ' . $data['memory']['percent'] . '%' .
                ' (' . brilliance_format_bytes($data['memory']['used']) . '/' . brilliance_format_bytes($data['memory']['total']) . ')');
            if (isset($data['disk']['percent'])) WP_CLI::log('  disk: ' . $data['disk']['percent'] . '%');
            if (isset($data['net_up'])) WP_CLI::log('  net: ↑' . brilliance_format_bytes($data['net_up'], true) . ' ↓' . brilliance_format_bytes($data['net_down'], true));
            if (isset($data['latency_ms'])) WP_CLI::log('  latency: ' . $data['latency_ms'] . 'ms');
        } else {
            WP_CLI::warning('采集完成但 CPU/内存/网络仍为空，请确认该用户能读 /proc');
        }
    });
}

/* ── 渲染卡片（小工具与区块共用） ── */
function brilliance_render_status_monitor() {
    $data = get_option('brilliance_status_monitor_data', array());
    $is_empty = empty($data) || empty($data['updated_ts']);

    if ($is_empty) {
        echo '<div class="sm-widget"><p class="sm-note">数据采集中，请稍后刷新页面。</p></div>';
        return;
    }

    $cpu = isset($data['cpu']) ? $data['cpu'] : null;
    $mem = isset($data['memory']) ? $data['memory'] : null;
    $disk = isset($data['disk']) ? $data['disk'] : null;
    $net_up = isset($data['net_up']) ? $data['net_up'] : null;
    $net_down = isset($data['net_down']) ? $data['net_down'] : null;
    $latency = isset($data['latency_ms']) ? $data['latency_ms'] : null;
    $ssl_days = isset($data['ssl_days_left']) ? $data['ssl_days_left'] : null;
    $ssl_expiry = isset($data['ssl_expiry']) ? $data['ssl_expiry'] : null;
    $updated = isset($data['updated']) ? $data['updated'] : '';

    $cpu_level = brilliance_status_level_percent($cpu);
    $mem_level = brilliance_status_level_percent($mem ? $mem['percent'] : null);
    $disk_level = brilliance_status_level_percent($disk ? $disk['percent'] : null);
    $lat_level = brilliance_status_level_latency($latency);
    $ssl_level = brilliance_status_level_ssl($ssl_days);
    ?>
    <div class="sm-widget">
        <div class="sm-grid">
            <div class="sm-item" data-level="<?php echo esc_attr($cpu_level); ?>">
                <div class="sm-item__head">
                    <span class="sm-item__label">CPU</span>
                    <span class="sm-item__val"><?php echo $cpu !== null ? esc_html($cpu) . '%' : '—'; ?></span>
                </div>
                <div class="sm-bar"><span class="sm-bar__fill" style="--p:<?php echo esc_attr($cpu !== null ? $cpu : 0); ?>"></span></div>
            </div>

            <div class="sm-item" data-level="<?php echo esc_attr($mem_level); ?>">
                <div class="sm-item__head">
                    <span class="sm-item__label">内存</span>
                    <span class="sm-item__val">
                        <?php if ($mem): ?>
                            <?php echo esc_html(round($mem['percent'], 1) . '%'); ?>
                            <small class="sm-item__sub"><?php echo esc_html(brilliance_format_bytes($mem['used']) . ' / ' . brilliance_format_bytes($mem['total'])); ?></small>
                        <?php else: ?>—<?php endif; ?>
                    </span>
                </div>
                <div class="sm-bar"><span class="sm-bar__fill" style="--p:<?php echo esc_attr($mem ? $mem['percent'] : 0); ?>"></span></div>
            </div>

            <div class="sm-item" data-level="<?php echo esc_attr($disk_level); ?>">
                <div class="sm-item__head">
                    <span class="sm-item__label">硬盘</span>
                    <span class="sm-item__val">
                        <?php if ($disk): ?>
                            <?php echo esc_html(round($disk['percent'], 1) . '%'); ?>
                            <small class="sm-item__sub"><?php echo esc_html(brilliance_format_bytes($disk['used']) . ' / ' . brilliance_format_bytes($disk['total'])); ?></small>
                        <?php else: ?>—<?php endif; ?>
                    </span>
                </div>
                <div class="sm-bar"><span class="sm-bar__fill" style="--p:<?php echo esc_attr($disk ? $disk['percent'] : 0); ?>"></span></div>
            </div>
        </div>

        <div class="sm-row">
            <span class="sm-chip sm-chip--net">
                <i class="sm-arrow sm-arrow--up" aria-hidden="true">↑</i>
                <span class="sm-chip__label">上行</span>
                <b><?php echo esc_html(brilliance_format_bytes($net_up, true)); ?></b>
            </span>
            <span class="sm-chip sm-chip--net">
                <i class="sm-arrow sm-arrow--down" aria-hidden="true">↓</i>
                <span class="sm-chip__label">下行</span>
                <b><?php echo esc_html(brilliance_format_bytes($net_down, true)); ?></b>
            </span>
        </div>

        <div class="sm-row">
            <span class="sm-chip" data-level="<?php echo esc_attr($lat_level); ?>">
                <span class="sm-chip__label">延迟</span>
                <b><?php echo $latency !== null ? esc_html($latency) . ' ms' : '—'; ?></b>
            </span>
            <span class="sm-chip" data-level="<?php echo esc_attr($ssl_level); ?>">
                <span class="sm-chip__label">SSL</span>
                <b>
                    <?php if ($ssl_days !== null): ?>
                        <?php if ($ssl_days >= 0): ?>剩余 <?php echo esc_html($ssl_days); ?> 天<?php else: ?>已过期<?php endif; ?>
                    <?php else: ?>—<?php endif; ?>
                </b>
                <?php if ($ssl_expiry): ?><small class="sm-chip__sub"><?php echo esc_html($ssl_expiry); ?></small><?php endif; ?>
            </span>
        </div>

        <p class="sm-note">数据每 5 分钟采样 · 更新于 <?php echo esc_html($updated); ?></p>
        <p class="sm-note1">本站为依赖服务器的动态站点，</p>
        <p class="sm-note1">你看到的每一行字，都是服务器现算出来的。</p>
    </div>
    <?php
}

/* ── Gutenberg 动态区块渲染回调 ── */
function brilliance_render_status_monitor_block($attrs) {
    brilliance_enqueue_status_monitor_assets();
    ob_start();
    echo '<h2 class="widget-title ">';
    _e('服务器状态监测', 'mimosa');
    echo '</h2>';
    echo '<div class="brilliance-block-status-monitor">';
    brilliance_render_status_monitor();
    echo '</div>';
    return ob_get_clean();
}

/* ── WP_Widget：服务器状态监测 ── */
class Brilliance_Status_Monitor_Widget extends WP_Widget {

    public function __construct() {
        parent::__construct(
            'brilliance_status_monitor',
            __('服务器状态监测', 'mimosa'),
            array(
                'description' => __('展示 CPU / 内存 / 硬盘 / 网络 / 延迟 / SSL 证书状态（定时任务缓存）', 'mimosa'),
                'classname'   => 'brilliance-status-monitor-widget',
            )
        );
    }

    public function widget($args, $instance) {
        brilliance_enqueue_status_monitor_assets();
        echo $args['before_widget'];
        $title = apply_filters('widget_title', isset($instance['title']) ? $instance['title'] : '');
        if ($title === '') {
            $title = __('服务器状态', 'mimosa');
        }
        echo $args['before_title'] . esc_html($title) . $args['after_title'];
        brilliance_render_status_monitor();
        echo $args['after_widget'];
    }

    public function form($instance) {
        $title = isset($instance['title']) ? $instance['title'] : '';
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>"><?php _e('标题：', 'mimosa'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('title')); ?>" type="text"
                   value="<?php echo esc_attr($title); ?>" placeholder="服务器状态">
        </p>
        <?php
    }

    public function update($new_instance, $old_instance) {
        $instance = $old_instance;
        $instance['title'] = sanitize_text_field(isset($new_instance['title']) ? $new_instance['title'] : '');
        return $instance;
    }
}
