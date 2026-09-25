<?php
/**
 * Character-based comment verification.
 */

if (!defined('ABSPATH')) {
    exit;
}

function mimosa_captcha_enabled() {
    return get_option('mimosa_captcha_enable', 'yes') === 'yes';
}

function mimosa_captcha_client_key() {
    $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
    $agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
    return hash('sha256', $ip . '|' . $agent . '|' . wp_salt('auth'));
}

function mimosa_captcha_characters() {
    static $characters = null;
    if ($characters !== null) {
        return $characters;
    }

    $file = MIMOSA_THEME_DIR . '/b-captcha/character.json';
    $json = is_readable($file) ? file_get_contents($file) : false;
    $data = $json ? json_decode($json, true) : array();
    $characters = is_array($data) ? array_values(array_filter($data, function ($character) {
        return isset($character['id'], $character['image'], $character['name'], $character['work'], $character['hair']);
    })) : array();
    return $characters;
}

function mimosa_captcha_ticket($client_key) {
    $ticket = wp_generate_password(32, false, false);
    set_transient('mimosa_captcha_ticket_' . $ticket, $client_key, 15 * MINUTE_IN_SECONDS);
    return $ticket;
}

function mimosa_ajax_get_comment_captcha() {
    check_ajax_referer('mimosa_comment_nonce', 'nonce');
    $characters = mimosa_captcha_characters();
    if (count($characters) < 6) {
        wp_send_json_error('验证码角色数据不足');
    }

    $target = $characters[array_rand($characters)];
    $choices = array($target);
    $others = array_values(array_filter($characters, function ($character) use ($target) {
        return $character['hair'] !== $target['hair'];
    }));
    shuffle($others);
    $choices = array_merge($choices, array_slice($others, 0, 5));
    if (count($choices) < 6) {
        wp_send_json_error('验证码题目生成失败');
    }
    shuffle($choices);

    $challenge = wp_generate_password(24, false, false);
    set_transient('mimosa_captcha_challenge_' . $challenge, array(
        'answer_id' => (int) $target['id'],
        'client_key' => mimosa_captcha_client_key(),
    ), 10 * MINUTE_IN_SECONDS);

    $items = array_map(function ($character) {
        return array(
            'id' => (int) $character['id'],
            'image' => MIMOSA_THEME_URI . '/b-captcha/' . rawurlencode($character['image']),
            'name' => $character['name'],
            'work' => $character['work'],
        );
    }, $choices);

    wp_send_json_success(array(
        'challenge' => $challenge,
        'hair' => $target['hair'],
        'items' => $items,
    ));
}
add_action('wp_ajax_mimosa_get_comment_captcha', 'mimosa_ajax_get_comment_captcha');
add_action('wp_ajax_nopriv_mimosa_get_comment_captcha', 'mimosa_ajax_get_comment_captcha');

function mimosa_ajax_verify_comment_captcha() {
    check_ajax_referer('mimosa_comment_nonce', 'nonce');
    $challenge = sanitize_text_field(wp_unslash($_POST['challenge'] ?? ''));
    $choice = absint($_POST['choice'] ?? 0);
    $stored = get_transient('mimosa_captcha_challenge_' . $challenge);

    if (!$challenge || !is_array($stored) || !hash_equals($stored['client_key'], mimosa_captcha_client_key())) {
        wp_send_json_error('验证题已过期，请重新开始');
    }

    delete_transient('mimosa_captcha_challenge_' . $challenge);
    if ((int) $stored['answer_id'] !== $choice) {
        wp_send_json_success(array('passed' => false));
    }

    wp_send_json_success(array(
        'passed' => true,
        'ticket' => mimosa_captcha_ticket(mimosa_captcha_client_key()),
    ));
}
add_action('wp_ajax_mimosa_verify_comment_captcha', 'mimosa_ajax_verify_comment_captcha');
add_action('wp_ajax_nopriv_mimosa_verify_comment_captcha', 'mimosa_ajax_verify_comment_captcha');

function mimosa_ajax_skip_comment_captcha() {
    check_ajax_referer('mimosa_comment_nonce', 'nonce');
    wp_send_json_success(array('ticket' => mimosa_captcha_ticket(mimosa_captcha_client_key())));
}
add_action('wp_ajax_mimosa_skip_comment_captcha', 'mimosa_ajax_skip_comment_captcha');
add_action('wp_ajax_nopriv_mimosa_skip_comment_captcha', 'mimosa_ajax_skip_comment_captcha');

function mimosa_captcha_consume_ticket($ticket) {
    $ticket = sanitize_text_field($ticket);
    $key = 'mimosa_captcha_ticket_' . $ticket;
    $client_key = get_transient($key);
    if (!$client_key || !hash_equals($client_key, mimosa_captcha_client_key())) {
        return false;
    }
    delete_transient($key);
    return true;
}
