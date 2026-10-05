<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Views that can be selected through the page query string.
 *
 * @return array<int, string>
 */
function zsr_allowed_views()
{
    return array('my', 'submit', 'detail', 'edit', 'review', 'history');
}

/**
 * Normalize an incoming view to the allow-list.
 *
 * @param mixed $value
 * @return string
 */
function zsr_normalize_view($value)
{
    $value = function_exists('sanitize_key') ? sanitize_key($value) : strtolower((string) $value);
    return in_array($value, zsr_allowed_views(), true) ? $value : 'my';
}

/**
 * Return the plugin page ID.
 *
 * @return int
 */
function zsr_page_id()
{
    return max(0, (int) zsr_get_option('zsr_page_id', 0));
}

/**
 * Store the page ID without replacing unrelated settings.
 *
 * @param int $page_id
 * @return void
 */
function zsr_set_page_id($page_id)
{
    if (!function_exists('update_option')) {
        return;
    }

    $options = zsr_get_options();
    $options['zsr_page_id'] = max(0, (int) $page_id);
    update_option(ZSR_OPTION, zsr_normalize_options($options));
    update_option('zsr_page_id', max(0, (int) $page_id));
}

/**
 * Find or create the page owned by this plugin.
 *
 * @return int
 */
function zsr_ensure_frontend_page()
{
    if (!function_exists('get_option') || !function_exists('wp_insert_post')) {
        return 0;
    }
    if (!zsr_get_option('zsr_enable', true)) {
        return 0;
    }

    $configured_id = zsr_page_id();
    if ($configured_id && function_exists('get_post')) {
        $configured_page = get_post($configured_id);
        $owned = function_exists('get_post_meta') && get_post_meta($configured_id, '_zsr_created_page', true) === '1';
        if ($configured_page && $owned) {
            return $configured_id;
        }
        zsr_set_page_id(0);
    }

    if (function_exists('get_posts')) {
        $owned = get_posts(array(
            'post_type'      => 'page',
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_key'       => '_zsr_created_page',
            'meta_value'     => '1',
        ));
        if (!empty($owned[0])) {
            zsr_set_page_id((int) $owned[0]);
            return (int) $owned[0];
        }
    }

    $slug = (string) zsr_get_option('zsr_page_slug', 'submissions');
    $title = (string) zsr_get_option('zsr_menu_label', '我的投稿');
    $page_author = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
    if ($page_author < 1) {
        $page_author = 1;
    }
    $page_id = wp_insert_post(array(
        'post_title'  => $title !== '' ? $title : '我的投稿',
        'post_name'   => $slug !== '' ? $slug : 'submissions',
        'post_status' => 'publish',
        'post_type'   => 'page',
        'post_author' => $page_author,
    ), true);
    if (is_wp_error($page_id) || !$page_id) {
        return 0;
    }

    if (function_exists('update_post_meta')) {
        update_post_meta($page_id, '_zsr_created_page', '1');
        update_post_meta($page_id, '_zsr_page_version', ZSR_VERSION);
    }
    zsr_set_page_id((int) $page_id);
    return (int) $page_id;
}

/**
 * Whether the current request targets the plugin-owned page.
 *
 * @return bool
 */
function zsr_is_our_page()
{
    $page_id = zsr_page_id();
    if (!$page_id) {
        return false;
    }
    if (function_exists('is_page')) {
        return is_page($page_id);
    }
    return function_exists('get_queried_object_id') && (int) get_queried_object_id() === $page_id;
}

/**
 * Return the plugin page URL when WordPress can resolve it.
 *
 * @return string
 */
function zsr_page_url()
{
    $page_id = zsr_page_id();
    return $page_id && function_exists('get_permalink') ? (string) get_permalink($page_id) : '';
}

/**
 * Load the plugin-owned template instead of relying on the theme template path.
 *
 * @param string $template
 * @return string
 */
function zsr_template_include($template)
{
    if (!zsr_get_option('zsr_enable', true) || !zsr_is_our_page()) {
        return $template;
    }

    $plugin_template = ZSR_DIR . 'templates/zsr-submissions.php';
    return is_readable($plugin_template) ? $plugin_template : $template;
}

/**
 * Apply page-only request behavior.
 *
 * @return void
 */
function zsr_frontend_page_setup()
{
    if (!zsr_is_our_page()) {
        return;
    }

    if (function_exists('add_filter')) {
        add_filter('wp_robots', 'zsr_noindex_robots');
    }
}

/**
 * Keep the private workflow page out of search indexes.
 *
 * @param array $robots
 * @return array
 */
function zsr_noindex_robots($robots)
{
    $robots['noindex'] = true;
    $robots['nofollow'] = true;
    return $robots;
}
