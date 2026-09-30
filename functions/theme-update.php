<?php
/**
 * 主题更新检测（GitHub Releases，AJAX 非阻塞）
 *
 * 只在后台「Brilliance 主题设置」页触发；查询结果带 transient 缓存。
 * 容器由前端脚本独占：服务端只输出一个空容器，脚本异步取回数据后
 * 用 replaceChildren 渲染唯一的提醒，不会残留「正在检查更新…」占位。
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
 * AJAX：返回更新状态数据（JSON）
 */
function brilliance_ajax_check_update() {
    check_ajax_referer('brilliance_check_update', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('无权限');
    }
    $force = !empty($_POST['force']);
    $info  = brilliance_check_github_update($force);
    wp_send_json_success($info);
}
add_action('wp_ajax_brilliance_check_update', 'brilliance_ajax_check_update');

/**
 * 在「Brilliance 主题设置」页顶部输出空容器 + 异步加载脚本
 */
function brilliance_render_update_notice() {
    if (!current_user_can('manage_options')) return;
    $nonce = wp_create_nonce('brilliance_check_update');
    ?>
    <div id="brilliance-update-notice"
         data-nonce="<?php echo esc_attr($nonce); ?>"
         data-ajax="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"></div>
    <script>
    (function () {
        function boot() {
            var box = document.getElementById('brilliance-update-notice');
            if (!box) return;
            var ajax = box.getAttribute('data-ajax') || (typeof ajaxurl !== 'undefined' ? ajaxurl : '');
            if (!ajax) return;
            var busy = false;
            var settled = false;
            var timer = null;

            // 显式清空容器后再插入唯一节点，确保旧内容（占位/上一次结果）被彻底替换
            function set(node) {
                while (box.firstChild) {
                    box.removeChild(box.firstChild);
                }
                box.appendChild(node);
            }

            function makeNotice(kindLower, text) {
                var d = document.createElement('div');
                d.className = 'notice notice-' + kindLower;
                var p = document.createElement('p');
                p.appendChild(document.createTextNode(text));
                d.appendChild(p);
                return d;
            }

            function addRefresh(p) {
                p.appendChild(document.createTextNode(' '));
                var a = document.createElement('a');
                a.href = '#';
                a.className = 'brilliance-update-recheck';
                a.textContent = '重新检查';
                p.appendChild(a);
            }

            function build(info) {
                info = info || {};
                var state = info.state || 'error';
                var current = info.current || '';
                var d = document.createElement('div');
                d.className = 'notice notice-' + (state === 'update' ? 'success' : (state === 'error' ? 'warning' : 'info'));
                var p = document.createElement('p');

                if (state === 'update') {
                    var strong = document.createElement('strong');
                    strong.textContent = '发现新版本！';
                    p.appendChild(strong);
                    p.appendChild(document.createTextNode(' 当前 ' + current + '，最新 ' + (info.remote_version || '') + '。 '));
                    var a = document.createElement('a');
                    a.href = info.url || '#';
                    a.target = '_blank';
                    a.rel = 'noopener';
                    a.textContent = '查看更新';
                    p.appendChild(a);
                } else if (state === 'latest') {
                    p.appendChild(document.createTextNode('已是最新版本（' + current + '）。'));
                } else if (state === 'none') {
                    p.appendChild(document.createTextNode('已是最新版本（' + current + '；仓库尚无 Release）。'));
                } else {
                    p.appendChild(document.createTextNode('无法获取更新信息，请稍后重试。'));
                }
                addRefresh(p);
                d.appendChild(p);
                return d;
            }

            // 兜底：清掉页面上任何残留的「正在检查更新…」占位（含重复/历史节点）
            function removeStrayLoading() {
                var nodes = document.querySelectorAll('.notice');
                for (var i = 0; i < nodes.length; i++) {
                    var n = nodes[i];
                    var t = (n.textContent || '').replace(/\s+/g, '');
                    if (t.indexOf('正在检查更新') === 0 && n.parentNode) {
                        n.parentNode.removeChild(n);
                    }
                }
            }

            // 唯一收尾点：成功 / 失败 / 超时都只渲染一次结果
            function finish(info) {
                if (settled) return;
                settled = true;
                busy = false;
                if (timer) { clearTimeout(timer); timer = null; }
                var node;
                try {
                    node = build(info);
                } catch (e) {
                    node = makeNotice('warning', '无法获取更新信息，请稍后重试。');
                }
                set(node);
                removeStrayLoading();
            }

            function load(force) {
                if (busy) return;
                busy = true;
                settled = false;
                set(makeNotice('info', '正在检查更新…'));
                if (timer) { clearTimeout(timer); }
                timer = setTimeout(function () { finish({ state: 'error' }); }, 12000);
                var data = new FormData();
                data.append('action', 'brilliance_check_update');
                data.append('nonce', box.getAttribute('data-nonce'));
                if (force) data.append('force', '1');
                fetch(ajax, { method: 'POST', credentials: 'same-origin', body: data })
                    .then(function (r) { return r.json(); })
                    .then(function (res) { finish(res && res.success ? res.data : { state: 'error' }); })
                    .catch(function () { finish({ state: 'error' }); });
            }

            box.addEventListener('click', function (e) {
                var a = e.target && e.target.closest ? e.target.closest('.brilliance-update-recheck') : null;
                if (!a) return;
                e.preventDefault();
                load(true);
            });

            load(false);
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', boot);
        } else {
            boot();
        }
    })();
    </script>
    <?php
}
