<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Return the WordPress roles allowed for each plugin capability.
 *
 * @return array<string, array<string, mixed>>
 */
function zsr_default_capabilities()
{
    return array(
        'zsr_submit'        => array_keys(zsr_wordpress_role_choices()),
        'zsr_review'        => array('administrator'),
        'zsr_review_others' => array('administrator'),
        'zsr_manage'        => array(),
    );
}

/**
 * Build the plugin role permissions from settings.
 *
 * @param array<string, mixed>|null $options
 * @return array<string, array<string, mixed>>
 */
function zsr_capabilities_from_options($options = null)
{
    if ($options === null && function_exists('zsr_get_options')) {
        $options = zsr_get_options();
    }
    $options = is_array($options) ? $options : array();
    if (function_exists('zsr_normalize_options')) {
        $options = zsr_normalize_options($options);
    }
    $defaults = zsr_default_capabilities();

    return array(
        'zsr_submit'        => isset($options['zsr_cap_submit']) && is_array($options['zsr_cap_submit'])
            ? $options['zsr_cap_submit'] : $defaults['zsr_submit'],
        'zsr_review'        => isset($options['zsr_cap_review']) && is_array($options['zsr_cap_review'])
            ? $options['zsr_cap_review'] : $defaults['zsr_review'],
        'zsr_review_others' => isset($options['zsr_cap_review_others']) && is_array($options['zsr_cap_review_others'])
            ? $options['zsr_cap_review_others'] : $defaults['zsr_review_others'],
        'zsr_manage'        => $defaults['zsr_manage'],
    );
}

/**
 * Remove legacy plugin permissions without changing native theme capabilities.
 *
 * @param array<string, mixed>|null $options
 * @return bool
 */
function zsr_sync_capabilities_from_options($options = null)
{
    if (!function_exists('_pz') || !function_exists('_spz')) {
        return false;
    }

    $caps = _pz('user_cap', array());
    if (!is_array($caps)) {
        $caps = array();
    }
    $changed = false;
    foreach (array('zsr_submit', 'zsr_review', 'zsr_review_others', 'zsr_manage') as $key) {
        if (array_key_exists($key, $caps)) {
            unset($caps[$key]);
            $changed = true;
        }
    }

    if ($changed) {
        _spz('user_cap', $caps);
    }

    return true;
}

/**
 * Migrate stored plugin permissions and retire legacy theme capability keys.
 *
 * @return bool
 */
function zsr_register_capabilities()
{
    if (function_exists('get_option') && function_exists('update_option')) {
        $stored = get_option(ZSR_OPTION, array());
        $options = is_array($stored) ? $stored : array();
        $permissions = zsr_capabilities_from_options($options);
        foreach (array('zsr_submit' => 'zsr_cap_submit', 'zsr_review' => 'zsr_cap_review', 'zsr_review_others' => 'zsr_cap_review_others') as $capability => $key) {
            $options[$key] = $permissions[$capability];
        }
        if ($options !== $stored) {
            update_option(ZSR_OPTION, $options);
        }
    }
    return zsr_sync_capabilities_from_options();
}

/**
 * Resolve plugin capabilities by WordPress roles; retain native theme checks.
 *
 * @param string $capability
 * @param mixed  ...$args
 * @return bool
 */
function zsr_user_can($user_id, $capability, ...$args)
{
    $user_id = (int) $user_id;
    if ($user_id < 1) {
        return false;
    }
    $is_current = function_exists('get_current_user_id') && $user_id === (int) get_current_user_id();
    if (strpos((string) $capability, 'zsr_') === 0) {
        if ($capability === 'zsr_manage') {
            if (function_exists('user_can')) {
                return user_can($user_id, 'manage_options');
            }
            return $is_current && function_exists('current_user_can') && current_user_can('manage_options');
        }
        $permissions = zsr_capabilities_from_options();
        if (!isset($permissions[$capability])) {
            return false;
        }
        $user = $is_current && function_exists('wp_get_current_user') ? wp_get_current_user()
            : (function_exists('get_userdata') ? get_userdata($user_id) : false);
        return $user && isset($user->roles) && !empty(array_intersect((array) $user->roles, $permissions[$capability]));
    }
    return function_exists('zib_user_can') && (bool) call_user_func_array('zib_user_can', array_merge(array($user_id, $capability), $args));
}

function zsr_current_user_can($capability, ...$args)
{
    if (strpos((string) $capability, 'zsr_') === 0) {
        return zsr_user_can(function_exists('get_current_user_id') ? get_current_user_id() : 0, $capability, ...$args);
    }
    if (!function_exists('zib_current_user_can')) {
        return false;
    }

    return (bool) call_user_func_array('zib_current_user_can', array_merge(array($capability), $args));
}
