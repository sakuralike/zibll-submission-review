<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register the first-stage lifecycle hooks.
 *
 * @return void
 */
function zsr_bootstrap()
{
    static $bootstrapped = false;
    if ($bootstrapped) {
        return;
    }
    $bootstrapped = true;

    if (function_exists('load_plugin_textdomain')) {
        load_plugin_textdomain('zib-sub-review', false, dirname(plugin_basename(ZSR_FILE)) . '/languages');
    }

    add_action('after_setup_theme', 'zsr_on_theme_ready', 20);
    add_action('after_setup_theme', 'zsr_register_admin_options', 20);
    add_filter('template_include', 'zsr_template_include', 20);
    add_action('template_redirect', 'zsr_frontend_page_setup', 6);
    add_action('wp_enqueue_scripts', 'zsr_enqueue_frontend_assets');
    add_action('admin_init', 'zsr_maybe_upgrade', 5);
    add_action('admin_init', 'zsr_register_capabilities', 6);
    add_action('admin_notices', 'zsr_admin_dependency_notice');
}

/**
 * Run safe initialization after the active theme has loaded.
 *
 * @return void
 */
function zsr_on_theme_ready()
{
    zsr_maybe_upgrade();
    zsr_register_capabilities();
    zsr_ensure_frontend_page();
}

/**
 * Activation is intentionally idempotent and non-destructive.
 *
 * @return void
 */
function zsr_activate()
{
    zsr_install_options();

    if (function_exists('update_option') && !zsr_is_zibll_theme()) {
        update_option('zsr_activation_blocked', 1);
    } elseif (function_exists('delete_option')) {
        delete_option('zsr_activation_blocked');
    }

    if (function_exists('flush_rewrite_rules')) {
        flush_rewrite_rules(false);
    }
}

/**
 * Deactivation leaves content and configuration intact.
 *
 * @return void
 */
function zsr_deactivate()
{
    if (function_exists('flush_rewrite_rules')) {
        flush_rewrite_rules(false);
    }
}

/**
 * Show actionable dependency information without producing a front-end fatal.
 *
 * @return void
 */
function zsr_admin_dependency_notice()
{
    if (!function_exists('current_user_can') || !current_user_can('manage_options')) {
        return;
    }

    $report = zsr_dependency_report();
    if (empty($report['missing']) && empty($report['optional_missing'])) {
        return;
    }

    $items = array();
    foreach ($report['missing'] as $label) {
        $items[] = $label;
    }
    foreach ($report['optional_missing'] as $label) {
        $items[] = $label;
    }

    $message = '子比前台投稿审核插件当前处于兼容提示模式：' . implode('；', $items);
    if (function_exists('esc_html')) {
        $message = esc_html($message);
    }
    echo '<div class="notice notice-warning"><p>' . $message . '</p></div>';
}
