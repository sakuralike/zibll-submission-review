<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Return the initial option set. Values are deliberately kept in one place so
 * migrations can distinguish defaults from user changes.
 *
 * @return array<string, mixed>
 */
function zsr_default_options()
{
    return array(
        'zsr_enable'                  => true,
        'zsr_enable_submit'           => true,
        'zsr_enable_review'           => true,
        'zsr_page_id'                 => 0,
        'zsr_page_slug'               => 'submissions',
        'zsr_menu_label'              => '我的投稿',
        'zsr_show_menu_item'          => true,
        'zsr_menu_position'           => '0',
        'zsr_cap_submit'              => array('logged' => true),
        'zsr_cap_review'              => array(
            'moderator'     => true,
            'plate_author'  => true,
            'cat_moderator' => true,
        ),
        'zsr_cap_review_others'       => array(
            'moderator'     => true,
            'plate_author'  => true,
            'cat_moderator' => true,
        ),
        'zsr_review_self_only'        => false,
        'zsr_actions'                 => array('approve', 'reject', 'return'),
        'zsr_approve_to_status'       => 'publish',
        'zsr_approve_keep_audit'      => false,
        'zsr_reject_to_status'        => 'pending',
        'zsr_return_to_status'        => 'draft',
        'zsr_reject_reason_required'  => true,
        'zsr_return_reason_required'  => false,
        'zsr_reason_maxlength'        => 200,
        'zsr_allow_self_review'       => false,
        'zsr_allow_re_review'         => true,
        'zsr_re_review_window'        => 0,
        'zsr_notify_author'           => true,
        'zsr_notify_channel'          => array('msg', 'email'),
        'zsr_notify_approver'         => false,
        'zsr_notify_on_return'        => true,
        'zsr_notify_include_content'  => true,
        'zsr_widget_enable'           => false,
        'zsr_widget_locked'           => array(),
        'zsr_widget_visitor_action'   => 'placeholder',
        'zsr_widget_admin_bypass'     => true,
        'zsr_widget_hide_title'       => true,
        'zsr_widget_exclude'          => array(),
    );
}

/**
 * Read the plugin option with defaults merged in.
 *
 * @return array<string, mixed>
 */
function zsr_get_options()
{
    $defaults = zsr_default_options();
    if (!function_exists('get_option')) {
        return $defaults;
    }

    $stored = get_option(ZSR_OPTION, array());
    if (!is_array($stored)) {
        $stored = array();
    }

    return array_replace($defaults, $stored);
}

/**
 * Read one plugin option.
 *
 * @param string $key
 * @param mixed  $fallback
 * @return mixed
 */
function zsr_get_option($key, $fallback = null)
{
    $options = zsr_get_options();
    return array_key_exists($key, $options) ? $options[$key] : $fallback;
}

/**
 * Install defaults without overwriting existing values.
 *
 * @return void
 */
function zsr_install_options()
{
    if (!function_exists('get_option') || !function_exists('update_option')) {
        return;
    }

    $stored = get_option(ZSR_OPTION, null);
    if (!is_array($stored)) {
        $stored = array();
    }
    $merged = array_replace(zsr_default_options(), $stored);
    update_option(ZSR_OPTION, $merged);

    if (function_exists('add_option')) {
        add_option('zsr_version', ZSR_VERSION, '', 'no');
        add_option('zsr_db_version', ZSR_DB_VERSION, '', 'no');
    }
    update_option('zsr_version', ZSR_VERSION);
    update_option('zsr_db_version', ZSR_DB_VERSION);
}

/**
 * Run the currently available migrations.
 *
 * @return void
 */
function zsr_maybe_upgrade()
{
    if (!function_exists('get_option') || !function_exists('update_option')) {
        return;
    }

    $db_version = (int) get_option('zsr_db_version', 0);
    if ($db_version < ZSR_DB_VERSION) {
        zsr_install_options();
        update_option('zsr_db_version', ZSR_DB_VERSION);
    }

    if (get_option('zsr_version', '') !== ZSR_VERSION) {
        update_option('zsr_version', ZSR_VERSION);
    }
}
