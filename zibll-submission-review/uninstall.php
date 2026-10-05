<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

if (function_exists('get_option') && function_exists('update_option')) {
    $theme_options = get_option('zibll_options', array());
    if (is_array($theme_options) && isset($theme_options['user_cap']) && is_array($theme_options['user_cap'])) {
        foreach (array('zsr_submit', 'zsr_review', 'zsr_review_others', 'zsr_manage') as $key) {
            unset($theme_options['user_cap'][$key]);
        }
        update_option('zibll_options', $theme_options);
    }
}

if (function_exists('get_posts') && function_exists('wp_trash_post')) {
    $pages = get_posts(array(
        'post_type'      => 'page',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_key'       => '_zsr_created_page',
        'meta_value'     => '1',
    ));
    foreach ((array) $pages as $page_id) {
        wp_trash_post((int) $page_id);
    }
}

if (function_exists('delete_option')) {
    foreach (array(
        'zsr_options',
        'zsr_widget_locked',
        'zsr_version',
        'zsr_db_version',
        'zsr_page_id',
        'zsr_activation_blocked',
    ) as $option) {
        delete_option($option);
    }
}
