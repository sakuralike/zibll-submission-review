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
        'zsr_log_enable'              => false,
        'zsr_log_level'               => 'info',
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
 * Convert a value coming from CSF or a native settings form to a boolean.
 *
 * @param mixed $value
 * @return bool
 */
function zsr_bool($value)
{
    if (is_string($value)) {
        return in_array(strtolower(trim($value)), array('1', 'true', 'yes', 'on'), true);
    }

    return !empty($value);
}

/**
 * Sanitize a text value without requiring the WordPress bootstrap in tests.
 *
 * @param mixed $value
 * @return string
 */
function zsr_text($value)
{
    $value = (string) $value;
    if (function_exists('sanitize_text_field')) {
        return sanitize_text_field($value);
    }

    return trim(strip_tags($value));
}

/**
 * Normalize a Zibll identity/capability map to the supported role keys.
 *
 * @param mixed $value
 * @param array $fallback
 * @return array<string, mixed>
 */
function zsr_normalize_roles($value, $fallback = array())
{
    $allowed = array('all', 'logged', 'level', 'vip', 'auth', 'moderator', 'plate_author', 'cat_moderator');
    $provided = is_array($value);
    $value = $provided ? $value : array();
    $result = array();

    foreach ($allowed as $role) {
        if (!array_key_exists($role, $value)) {
            continue;
        }

        if (in_array($role, array('level', 'vip'), true)) {
            $threshold = (int) $value[$role];
            if ($threshold > 0) {
                $result[$role] = $threshold;
            }
        } elseif (zsr_bool($value[$role])) {
            $result[$role] = true;
        }
    }

    return !$provided && !empty($fallback) ? zsr_normalize_roles($fallback) : $result;
}

/**
 * Normalize and validate settings before they reach CSF or WordPress options.
 *
 * @param mixed $input
 * @return array<string, mixed>
 */
function zsr_normalize_options($input)
{
    $defaults = zsr_default_options();
    $input = is_array($input) ? $input : array();
    $options = array_replace($defaults, $input);

    foreach (array(
        'zsr_enable',
        'zsr_log_enable',
        'zsr_enable_submit',
        'zsr_enable_review',
        'zsr_show_menu_item',
        'zsr_approve_keep_audit',
        'zsr_reject_reason_required',
        'zsr_return_reason_required',
        'zsr_allow_self_review',
        'zsr_allow_re_review',
        'zsr_notify_author',
        'zsr_notify_approver',
        'zsr_notify_on_return',
        'zsr_notify_include_content',
        'zsr_widget_enable',
        'zsr_widget_admin_bypass',
        'zsr_widget_hide_title',
        'zsr_review_self_only',
    ) as $key) {
        $options[$key] = zsr_bool($options[$key]);
    }

    $options['zsr_page_id'] = max(0, (int) $options['zsr_page_id']);
    $options['zsr_page_slug'] = function_exists('sanitize_title')
        ? sanitize_title($options['zsr_page_slug'])
        : preg_replace('/[^a-z0-9_-]+/i', '-', strtolower(zsr_text($options['zsr_page_slug'])));
    $options['zsr_page_slug'] = trim((string) $options['zsr_page_slug'], '-_');
    if ($options['zsr_page_slug'] === '') {
        $options['zsr_page_slug'] = 'submissions';
    }
    $options['zsr_menu_label'] = zsr_text($options['zsr_menu_label']);
    $options['zsr_menu_position'] = (string) max(0, (int) $options['zsr_menu_position']);
    $options['zsr_log_level'] = in_array(strtolower((string) $options['zsr_log_level']), array('off', 'error', 'warning', 'info', 'debug'), true)
        ? strtolower((string) $options['zsr_log_level'])
        : $defaults['zsr_log_level'];

    $options['zsr_cap_submit'] = zsr_normalize_roles(
        $options['zsr_cap_submit'],
        $defaults['zsr_cap_submit']
    );
    $options['zsr_cap_review'] = zsr_normalize_roles(
        $options['zsr_cap_review'],
        $defaults['zsr_cap_review']
    );
    $options['zsr_cap_review_others'] = zsr_normalize_roles(
        $options['zsr_cap_review_others'],
        $defaults['zsr_cap_review_others']
    );
    if ($options['zsr_review_self_only']) {
        $options['zsr_cap_review_others'] = array();
        $options['zsr_allow_self_review'] = true;
    }

    $actions = is_array($options['zsr_actions']) ? $options['zsr_actions'] : array();
    $options['zsr_actions'] = array_values(array_unique(array_intersect(
        array('approve', 'reject', 'return'),
        array_map('strval', $actions)
    )));
    if (empty($options['zsr_actions'])) {
        $options['zsr_actions'] = $defaults['zsr_actions'];
    }

    $status_values = array('publish', 'pending', 'draft', 'trash');
    $options['zsr_approve_to_status'] = in_array($options['zsr_approve_to_status'], array('publish', 'pending'), true)
        ? $options['zsr_approve_to_status']
        : $defaults['zsr_approve_to_status'];
    $options['zsr_reject_to_status'] = in_array($options['zsr_reject_to_status'], $status_values, true)
        ? $options['zsr_reject_to_status']
        : $defaults['zsr_reject_to_status'];
    $options['zsr_return_to_status'] = in_array($options['zsr_return_to_status'], array('draft', 'pending'), true)
        ? $options['zsr_return_to_status']
        : $defaults['zsr_return_to_status'];
    $options['zsr_reason_maxlength'] = min(2000, max(1, (int) $options['zsr_reason_maxlength']));
    $options['zsr_re_review_window'] = max(0, (int) $options['zsr_re_review_window']);

    $channels = is_array($options['zsr_notify_channel']) ? $options['zsr_notify_channel'] : array();
    $options['zsr_notify_channel'] = array_values(array_unique(array_intersect(
        array('msg', 'email'),
        array_map('strval', $channels)
    )));

    $visitor_actions = array('placeholder', 'hidden', 'upgrade');
    $options['zsr_widget_visitor_action'] = in_array($options['zsr_widget_visitor_action'], $visitor_actions, true)
        ? $options['zsr_widget_visitor_action']
        : $defaults['zsr_widget_visitor_action'];
    $options['zsr_widget_locked'] = is_array($options['zsr_widget_locked']) ? $options['zsr_widget_locked'] : array();
    $options['zsr_widget_exclude'] = is_array($options['zsr_widget_exclude']) ? $options['zsr_widget_exclude'] : array();

    return $options;
}

/**
 * Persist normalized settings and immediately synchronize Zibll capabilities.
 *
 * @param mixed $input
 * @return array<string, mixed>
 */
function zsr_save_options($input)
{
    $options = zsr_normalize_options($input);
    if (function_exists('update_option')) {
        update_option(ZSR_OPTION, $options);
    }
    if (function_exists('zsr_sync_capabilities_from_options')) {
        zsr_sync_capabilities_from_options($options);
    }
    if (function_exists('zsr_log')) {
        zsr_log('info', 'options.saved', array(
            'log_enable' => !empty($options['zsr_log_enable']),
            'log_level'  => $options['zsr_log_level'],
        ));
    }

    return $options;
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
        if (function_exists('zsr_log')) {
            zsr_log('info', 'options.migrated', array('from' => $db_version, 'to' => ZSR_DB_VERSION));
        }
    }

    if (get_option('zsr_version', '') !== ZSR_VERSION) {
        update_option('zsr_version', ZSR_VERSION);
    }
}
