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
 * Merge plugin capability keys into Zibll's user_cap option idempotently.
 *
 * @return bool
 */
function zsr_register_capabilities()
{
    static $done = false;
    if ($done) {
        return true;
    }

    if (!function_exists('_pz') || !function_exists('_spz')) {
        return false;
    }

    $caps = _pz('user_cap', array());
    if (!is_array($caps)) {
        $caps = array();
    }

    $changed = false;
    foreach (zsr_default_capabilities() as $key => $default) {
        if (!array_key_exists($key, $caps)) {
            $caps[$key] = $default;
            $changed = true;
        }
    }

    if ($changed) {
        _spz('user_cap', $caps);
    }

    $done = true;
    return true;
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
