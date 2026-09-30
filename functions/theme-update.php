<?php
/**
 * 主题更新检测（GitHub Releases，AJAX 非阻塞）
 *
 * 只在后台「Brilliance 主题设置」页触发；查询结果带 transient 缓存，
 * 页面先渲染占位，再由 admin-ajax 异步取回并填充，避免后台卡顿。
 */

if (!defined('ABSPATH')) exit;

// 仓库（owner/repo）。改这里即可切换检测目标。
if (!defined('BRILLIANCE_GITHUB_REPO')) {
    define('BRILLIANCE_GITHUB_REPO', 'bigBulen/Brilliance-Theme');
}

/**
 * 当前主题版本（来自 style.css 的 Version:）
 */
function brilliance_theme_version() {
    $v = wp_get_theme()->get('Version');
    return $v ? $v : '1.0.0';
}

/**
 * 查询 GitHub 最新 release（带缓存）
 *
 * @param bool $force 忽略缓存强制查询
 * @return array state = update|latest|none|error
 */
function brilliance_check_github_update($force = false) {
    $cache_key = 'brilliance_github_release';
    if (!$force) {
        $cached = get_transient($cache_key);
        if (is_array($cached)) return $cached;
    }

    $current = brilliance_theme_version();
    $result  = array(
        'state'          => 'error',
        'current'        => $current,
        'remote_version' => '',
        'tag'            => '',
        'url'            => 'https://github.com/' . BRILLIANCE_GITHUB_REPO . '/releases',
        'name'           => '',
        'published_at'   => '',
    );

    $response = wp_remote_get(
        'https://api.github.com/repos/' . BRILLIANCE_GITHUB_REPO . '/releases/latest',
        array(
            'timeout' => 8,
            'headers' => array(
                'User-Agent' => 'Brilliance-Theme/' . $current,
                'Accept'     => 'application/vnd.github+json',
            ),
        )
    );

    if (is_wp_error($response)) {
        set_transient($cache_key, $result, HOUR_IN_SECONDS); // 失败短缓存，便于稍后重试
        return $result;
    }

    $code = (int) wp_remote_retrieve_response_code($response);

    if ($code === 404) {
        // 仓库还没有 Release
        $result['state'] = 'none';
        set_transient($cache_key, $result, 6 * HOUR_IN_SECONDS);
        return $result;
    }
    if ($code !== 200) {
        // 403 限流等 → 无法获取
        set_transient($cache_key, $result, HOUR_IN_SECONDS);
        return $result;
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);
    if (!is_array($data) || empty($data['tag_name'])) {
        set_transient($cache_key, $result, HOUR_IN_SECONDS);
        return $result;
    }

    $remote = ltrim((string) $data['tag_name'], 'vV');
    $result['remote_version'] = $remote;
    $result['tag']            = (string) $data['tag_name'];
    $result['url']            = !empty($data['html_url']) ? (string) $data['html_url'] : $result['url'];
    $result['name']           = isset($data['name']) ? (string) $data['name'] : '';
    $result['published_at']   = isset($data['published_at']) ? (string) $data['published_at'] : '';
    $result['state']          = version_compare($remote, $current, '>') ? 'update' : 'latest';

    set_transient($cache_key, $result, 6 * HOUR_IN_SECONDS);
    return $result;
}

/**
 * 根据状态生成提醒 HTML
 */
function brilliance_update_notice_html($info) {
    $current = (is_array($info) && isset($info['current'])) ? $info['current'] : brilliance_theme_version();
    $refresh = ' <a href="#" class="brilliance-update-recheck" style="margin-left:6px;">重新检查</a>';
    $state   = (is_array($info) && isset($info['state'])) ? $info['state'] : 'error';

    switch ($state) {
        case 'update':
            return '<div class="notice notice-success"><p><strong>发现新版本！</strong> 当前 ' . esc_html($current)
                . '，最新 ' . esc_html($info['remote_version']) . '。 '
                . '<a href="' . esc_url($info['url']) . '" target="_blank" rel="noopener">查看更新</a>'
                . $refresh . '</p></div>';
        case 'latest':
            return '<div class="notice notice-info"><p>已是最新版本（' . esc_html($current) . '）。' . $refresh . '</p></div>';
        case 'none':
            return '<div class="notice notice-info"><p>已是最新版本（' . esc_html($current) . '；仓库尚无 Release）。' . $refresh . '</p></div>';
        default:
            return '<div class="notice notice-warning"><p>无法获取更新信息，请稍后重试。' . $refresh . '</p></div>';
    }
}

/**
 * AJAX：返回提醒 HTML
 */
function brilliance_ajax_check_update() {
    check_ajax_referer('brilliance_check_update', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('无权限');
    }
    $force = !empty($_POST['force']);
    $info  = brilliance_check_github_update($force);
    wp_send_json_success(array('html' => brilliance_update_notice_html($info)));
}
add_action('wp_ajax_brilliance_check_update', 'brilliance_ajax_check_update');

/**
 * 在「Brilliance 主题设置」页顶部输出占位 + 异步加载脚本
 */
function brilliance_render_update_notice() {
    if (!current_user_can('manage_options')) return;
    $nonce = wp_create_nonce('brilliance_check_update');
    ?>
    <div id="brilliance-update-notice" data-nonce="<?php echo esc_attr($nonce); ?>" data-ajax="<?php echo esc_url(admin_url('admin-ajax.php')); ?>">
        <div class="notice notice-info"><p>正在检查更新…</p></div>
    </div>
    <script>
    (function () {
        var box = document.getElementById('brilliance-update-notice');
        if (!box) return;
        var ajax = box.getAttribute('data-ajax') || (typeof ajaxurl !== 'undefined' ? ajaxurl : '');
        if (!ajax) return;
        var busy = false;
        var loadingHtml = '<div class="notice notice-info"><p>正在检查更新…</p></div>';
        var errorHtml = '<div class="notice notice-warning"><p>无法获取更新信息，请稍后重试。</p></div>';
        function load(force) {
            if (busy) return;
            busy = true;
            box.innerHTML = loadingHtml;
            var data = new FormData();
            data.append('action', 'brilliance_check_update');
            data.append('nonce', box.getAttribute('data-nonce'));
            if (force) data.append('force', '1');
            fetch(ajax, { method: 'POST', credentials: 'same-origin', body: data })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    busy = false;
                    box.innerHTML = (res && res.success && res.data && res.data.html) ? res.data.html : errorHtml;
                })
                .catch(function () { busy = false; box.innerHTML = errorHtml; });
        }
        box.addEventListener('click', function (e) {
            var a = e.target && e.target.closest ? e.target.closest('.brilliance-update-recheck') : null;
            if (!a) return;
            e.preventDefault();
            load(true);
        });
        load(false);
    })();
    </script>
    <?php
}
