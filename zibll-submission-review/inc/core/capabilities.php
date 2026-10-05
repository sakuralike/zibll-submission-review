<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Return plugin capability definitions in the shape expected by Zibll.
 *
 * @return array<string, array<string, mixed>>
 */
function zsr_default_capabilities()
{
    return array(
        'zsr_submit'        => array('logged' => true),
        'zsr_review'        => array(
            'moderator'     => true,
            'plate_author'  => true,
            'cat_moderator' => true,
        ),
        'zsr_review_others' => array(
            'moderator'     => true,
            'plate_author'  => true,
            'cat_moderator' => true,
        ),
        'zsr_manage'        => array(),
    );
}

/**
 * Build the Zibll capability map from plugin settings.
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
 * Merge current plugin capability settings into the theme option.
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
    foreach (zsr_capabilities_from_options($options) as $key => $value) {
        if (!isset($caps[$key]) || $caps[$key] !== $value) {
            $caps[$key] = $value;
            $changed = true;
        }
    }

    if ($changed) {
        _spz('user_cap', $caps);
    }

    return true;
}

/**
 * Merge plugin capability keys into Zibll's user_cap option idempotently.
 *
 * @return bool
 */
function zsr_register_capabilities()
{
    if (!function_exists('_pz') || !function_exists('_spz')) {
        return false;
    }

    return zsr_sync_capabilities_from_options();
}

/**
 * Use the theme's capability resolver so super-admin and community-role rules
 * remain owned by Zibll.
 *
 * @param string $capability
 * @param mixed  ...$args
 * @return bool
 */
function zsr_current_user_can($capability, ...$args)
{
    if (!function_exists('zib_current_user_can')) {
        return false;
    }

    return (bool) call_user_func_array('zib_current_user_can', array_merge(array($capability), $args));
}
