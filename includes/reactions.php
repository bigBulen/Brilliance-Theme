<?php
/**
 * Lightweight post/shuoshuo reactions.
 */

if (!defined('ABSPATH')) exit;

function mimosa_reactions_enabled() {
    return get_option('mimosa_reactions_enable', 'no') === 'yes';
}

function mimosa_reactions_default_emojis() {
    return array('❤️', '😂', '😭', '😮', '🤔', '👍', '👀', '🎉');
}

function mimosa_reactions_get_emojis() {
    $raw = trim((string) get_option('mimosa_reactions_quick_emojis', ''));
    if ($raw === '') {
        return mimosa_reactions_default_emojis();
    }

    $items = preg_split('/[\s,，]+/u', $raw, -1, PREG_SPLIT_NO_EMPTY);
    $items = array_values(array_unique(array_map('trim', $items)));

    return $items ?: mimosa_reactions_default_emojis();
}

function mimosa_reactions_table_name() {
    global $wpdb;
    return $wpdb->prefix . 'brilliance_reactions';
}

function mimosa_reactions_install() {
    global $wpdb;

    $table = mimosa_reactions_table_name();
    $charset_collate = $wpdb->get_charset_collate();

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $sql = "CREATE TABLE {$table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        post_id bigint(20) unsigned NOT NULL,
        post_type varchar(32) NOT NULL DEFAULT '',
        visitor_id varchar(80) NOT NULL DEFAULT '',
        emoji varchar(32) NOT NULL DEFAULT '',
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY reaction_once (post_id, visitor_id, emoji),
        KEY post_lookup (post_id, post_type),
        KEY visitor_lookup (visitor_id)
    ) {$charset_collate};";

    dbDelta($sql);
    update_option('mimosa_reactions_db_version', '1');
}
add_action('after_switch_theme', 'mimosa_reactions_install');

function mimosa_reactions_maybe_install() {
    if (get_option('mimosa_reactions_db_version') !== '1') {
        mimosa_reactions_install();
    }
}
add_action('init', 'mimosa_reactions_maybe_install');

function mimosa_reactions_valid_post($post_id) {
    $post = get_post($post_id);
    if (!$post || $post->post_status !== 'publish') {
        return false;
    }

    return in_array($post->post_type, array('post', 'shuoshuo'), true);
}

function mimosa_reactions_get_counts($post_id) {
    global $wpdb;

    $post_id = absint($post_id);
    if (!$post_id) {
        return array();
    }

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            'SELECT emoji, COUNT(*) AS total FROM ' . mimosa_reactions_table_name() . ' WHERE post_id = %d GROUP BY emoji',
            $post_id
        ),
        ARRAY_A
    );

    $counts = array();
    foreach ((array) $rows as $row) {
        $counts[$row['emoji']] = absint($row['total']);
    }

    return $counts;
}

function mimosa_reactions_ordered_counts($post_id) {
    $counts = mimosa_reactions_get_counts($post_id);
    $ordered = array();

    foreach (mimosa_reactions_get_emojis() as $emoji) {
        if (!empty($counts[$emoji])) {
            $ordered[] = array(
                'emoji' => $emoji,
                'count' => $counts[$emoji],
            );
            unset($counts[$emoji]);
        }
    }

    foreach ($counts as $emoji => $count) {
        if ($count > 0) {
            $ordered[] = array(
                'emoji' => $emoji,
                'count' => $count,
            );
        }
    }

    return $ordered;
}

function mimosa_reactions_rest_counts($post_id) {
    return array(
        'postId' => absint($post_id),
        'emojis' => mimosa_reactions_get_emojis(),
        'counts' => mimosa_reactions_ordered_counts($post_id),
    );
}

function mimosa_reactions_register_rest_routes() {
    register_rest_route('mimosa/v1', '/reactions/(?P<id>\d+)', array(
        array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => 'mimosa_reactions_rest_get',
            'permission_callback' => '__return_true',
            'args' => array(
                'id' => array('sanitize_callback' => 'absint'),
            ),
        ),
        array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => 'mimosa_reactions_rest_add',
            'permission_callback' => '__return_true',
            'args' => array(
                'id' => array('sanitize_callback' => 'absint'),
            ),
        ),
    ));
}

add_action('rest_api_init', 'mimosa_reactions_register_rest_routes');

function mimosa_reactions_enqueue_assets() {
    if (!mimosa_reactions_enabled() || !is_singular(array('post', 'shuoshuo'))) {
        return;
    }

    wp_enqueue_style(
        'mimosa-reactions',
        MIMOSA_THEME_URI . '/assets/css/reactions.css',
        array('mimosa-main'),
        mimosa_asset_version('/assets/css/reactions.css')
    );
    wp_enqueue_script(
        'mimosa-reactions',
        MIMOSA_THEME_URI . '/assets/js/reactions.js',
        array('jquery', 'mimosa-main'),
        mimosa_asset_version('/assets/js/reactions.js'),
        true
    );
    wp_localize_script('mimosa-reactions', 'mimosaReactions', array(
        'restUrl' => rest_url('mimosa/v1/reactions'),
    ));
}
add_action('wp_enqueue_scripts', 'mimosa_reactions_enqueue_assets');

function mimosa_reactions_rest_get(WP_REST_Request $request) {
    $post_id = absint($request['id']);
    if (!mimosa_reactions_enabled() || !mimosa_reactions_valid_post($post_id)) {
        return new WP_Error('mimosa_reactions_unavailable', 'Reactions are unavailable.', array('status' => 404));
    }

    return rest_ensure_response(mimosa_reactions_rest_counts($post_id));
}

function mimosa_reactions_rest_add(WP_REST_Request $request) {
    global $wpdb;

    $post_id = absint($request['id']);
    if (!mimosa_reactions_enabled() || !mimosa_reactions_valid_post($post_id)) {
        return new WP_Error('mimosa_reactions_unavailable', 'Reactions are unavailable.', array('status' => 404));
    }

    $emoji = trim((string) $request->get_param('emoji'));
    if (!in_array($emoji, mimosa_reactions_get_emojis(), true)) {
        return new WP_Error('mimosa_reactions_bad_emoji', 'Invalid emoji.', array('status' => 400));
    }

    $visitor_id = trim((string) $request->get_param('visitorId'));
    if (!preg_match('/^[A-Za-z0-9_-]{16,80}$/', $visitor_id)) {
        return new WP_Error('mimosa_reactions_bad_visitor', 'Invalid visitor.', array('status' => 400));
    }

    $post_type = get_post_type($post_id);
    $wpdb->query(
        $wpdb->prepare(
            'INSERT IGNORE INTO ' . mimosa_reactions_table_name() . ' (post_id, post_type, visitor_id, emoji, created_at) VALUES (%d, %s, %s, %s, %s)',
            $post_id,
            $post_type,
            $visitor_id,
            $emoji,
            current_time('mysql')
        )
    );

    return rest_ensure_response(mimosa_reactions_rest_counts($post_id));
}

function mimosa_reactions_render($post_id = null) {
    if (!mimosa_reactions_enabled()) {
        return;
    }

    $post_id = $post_id ? absint($post_id) : get_the_ID();
    if (!mimosa_reactions_valid_post($post_id)) {
        return;
    }

    $counts = mimosa_reactions_ordered_counts($post_id);
    $emojis = mimosa_reactions_get_emojis();
    ?>

    <div class="mimosa-reactions" data-post-id="<?php echo esc_attr($post_id); ?>" data-endpoint="<?php echo esc_url(rest_url('mimosa/v1/reactions/' . $post_id)); ?>">
        <div class="mimosa-reactions__list" aria-live="polite">
            <?php if (!empty($counts)): ?>
                <?php foreach ($counts as $item): ?>
                <span class="mimosa-reactions__pill" data-emoji="<?php echo esc_attr($item['emoji']); ?>">
                    <span class="mimosa-reactions__emoji"><?php echo esc_html($item['emoji']); ?></span>
                    <span class="mimosa-reactions__count"><?php echo esc_html($item['count']); ?></span>
                </span>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php if (empty($counts)): ?>
            <small class="mimosa-reactions__empty">添加一个reaction吧！</small>
        <?php endif; ?>
        <div class="mimosa-reactions__picker-wrap">

            <button class="mimosa-reactions__trigger js-mimosa-reaction-trigger" type="button" aria-label="添加表态" aria-expanded="false">+</button>
            <div class="mimosa-reactions__popover" hidden>
                <?php foreach ($emojis as $emoji): ?>
                <button class="mimosa-reactions__choice js-mimosa-reaction-choice" type="button" data-emoji="<?php echo esc_attr($emoji); ?>" aria-label="添加 <?php echo esc_attr($emoji); ?> 表态">
                    <?php echo esc_html($emoji); ?>
                </button>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php
}
