<?php
/**
 * 印象集「Toward Our Dream」路由
 *
 * - 用主题设置中的自定义 slug 注册一个独立访问路径。
 * - 命中后加载 impressions.php 模板（不套用主题 header/footer）。
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('mimosa_impressions_slug')) {
    function mimosa_impressions_slug() {
        $slug = sanitize_title((string) get_option('mimosa_impressions_slug', 'toward-our-dream'));
        return $slug !== '' ? $slug : 'toward-our-dream';
    }
}

if (!function_exists('mimosa_impressions_rewrites')) {
    function mimosa_impressions_rewrites() {
        add_rewrite_rule(
            '^' . preg_quote(mimosa_impressions_slug(), '/') . '/?$',
            'index.php?mimosa_impressions=1',
            'top'
        );
    }
}
add_action('init', 'mimosa_impressions_rewrites');

if (!function_exists('mimosa_impressions_query_vars')) {
    function mimosa_impressions_query_vars($vars) {
        $vars[] = 'mimosa_impressions';
        return $vars;
    }
}
add_filter('query_vars', 'mimosa_impressions_query_vars');

if (!function_exists('mimosa_impressions_template')) {
    function mimosa_impressions_template($template) {
        if (get_query_var('mimosa_impressions')) {
            $impressions_template = locate_template('impressions.php');
            if ($impressions_template) {
                return $impressions_template;
            }
        }
        return $template;
    }
}
add_filter('template_include', 'mimosa_impressions_template');

// 修改 slug 后清掉缓存的 rewrite 规则，下次请求自动重建
if (!function_exists('mimosa_impressions_flush_on_slug_change')) {
    function mimosa_impressions_flush_on_slug_change($old_value, $new_value) {
        if ($old_value !== $new_value) {
            delete_option('rewrite_rules');
        }
    }
}
add_action('update_option_mimosa_impressions_slug', 'mimosa_impressions_flush_on_slug_change', 10, 2);

// 首次引入印象集路由时刷新一次重写规则（版本守卫：部署后第一次加载自动清缓存，之后不再重复执行）
if (!function_exists('mimosa_impressions_flush_guard')) {
    function mimosa_impressions_flush_guard() {
        if (get_option('mimosa_impressions_rules_version') === '1') {
            return;
        }
        delete_option('rewrite_rules');
        update_option('mimosa_impressions_rules_version', '1', false);
    }
}
add_action('init', 'mimosa_impressions_flush_guard', 1);



