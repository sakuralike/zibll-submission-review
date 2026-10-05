<?php

if (!defined('ABSPATH')) {
    exit;
}

function zsr_normalize_widget_ids($input)
{
    $result = array();
    $seen = array();
    if (!is_array($input)) {
        return $result;
    }

    foreach ($input as $key => $value) {
        $id = is_int($key) ? $value : (in_array($value, array(true, 1, '1'), true) ? $key : null);
        if (is_string($id) && preg_match('/^(?![0-9]+$)[a-z0-9_-]+$/D', $id) && !isset($seen[$id])) {
            $seen[$id] = true;
            $result[] = $id;
        }
    }

    return $result;
}

function zsr_widget_forced_exclusions()
{
    return array('widget_ui_search', 'widget_ui_user', 'zib_widget_ui_user', 'zib_widget_ui_search');
}

function zsr_get_locked_widgets()
{
    $cache = &zsr_widget_request_cache();
    if (!isset($cache['zsr_widget_locked'])) {
        $stored = function_exists('get_option') ? get_option('zsr_widget_locked', array()) : array();
        $locked = array_fill_keys(zsr_normalize_widget_ids($stored), '1');
        foreach (zsr_widget_forced_exclusions() as $id) {
            unset($locked[$id]);
        }
        $cache['zsr_widget_locked'] = $locked;
    }
    return $cache['zsr_widget_locked'];
}

function &zsr_widget_request_cache()
{
    static $sites = array();
    static $registered = false;
    if (!$registered && function_exists('add_action')) {
        foreach (array(ZSR_OPTION, 'zsr_widget_locked') as $option) {
            add_action('update_option_' . $option, 'zsr_widget_cache_after_update', 0, 3);
            add_action('add_option_' . $option, 'zsr_invalidate_widget_cache', 0, 1);
            add_action('delete_option_' . $option, 'zsr_invalidate_widget_cache', 0, 1);
        }
        $registered = true;
    }
    $site = function_exists('get_current_blog_id') ? get_current_blog_id() : 0;
    if (!isset($sites[$site])) {
        $sites[$site] = array();
    }
    return $sites[$site];
}

function zsr_invalidate_widget_cache($option = null)
{
    if ($option !== null && $option !== ZSR_OPTION && $option !== 'zsr_widget_locked') {
        return;
    }
    $cache = &zsr_widget_request_cache();
    if ($option === null) {
        $cache = array();
    } else {
        unset($cache[$option]);
    }
}

function zsr_widget_cache_after_update($previous, $value, $option)
{
    zsr_invalidate_widget_cache($option);
}

function zsr_get_widget_options()
{
    $cache = &zsr_widget_request_cache();
    if (!isset($cache[ZSR_OPTION])) {
        $options = zsr_get_options();
        $options['zsr_widget_excluded_map'] = array_fill_keys(array_merge(zsr_widget_forced_exclusions(), zsr_normalize_widget_ids($options['zsr_widget_exclude'])), true);
        $cache[ZSR_OPTION] = $options;
    }
    return $cache[ZSR_OPTION];
}

function zsr_widget_sync_feedback($level, $event, $message, $context = array())
{
    if ($message !== '') {
        $GLOBALS['zsr_widget_sync_feedback'] = array('level' => $level, 'message' => $message);
    }
    if (function_exists('zsr_log')) {
        try {
            zsr_log($level, $event, $context);
        } catch (Throwable $error) {
        }
    }
}

function zsr_sync_widget_options($options)
{
    $options = is_array($options) ? $options : array();
    $exclude = zsr_normalize_widget_ids(isset($options['zsr_widget_exclude']) ? $options['zsr_widget_exclude'] : array());
    $ids = zsr_normalize_widget_ids(isset($options['zsr_widget_locked']) ? $options['zsr_widget_locked'] : array());
    $ids = array_values(array_diff($ids, zsr_widget_forced_exclusions(), $exclude));
    $locked = array_fill_keys($ids, '1');
    $options['zsr_widget_exclude'] = $exclude;
    $options['zsr_widget_locked'] = $ids;
    $saved = false;

    if (function_exists('get_option') && function_exists('update_option')) {
        try {
            update_option('zsr_widget_locked', $locked, false);
            $saved = get_option('zsr_widget_locked', null) === $locked;
        } catch (Throwable $error) {
            $saved = false;
        }
    }

    if (!$saved) {
        $previous = zsr_get_options();
        foreach (array('zsr_widget_enable', 'zsr_widget_locked', 'zsr_widget_visitor_action', 'zsr_widget_admin_bypass', 'zsr_widget_hide_title', 'zsr_widget_exclude') as $key) {
            $options[$key] = $previous[$key];
        }
        $options['zsr_widget_locked'] = array_keys(zsr_get_locked_widgets());
        zsr_widget_sync_feedback('error', 'widget.options_sync_failed', __('小工具可见性配置保存失败，已保留原有设置，请检查诊断日志后重试。', 'zib-sub-review'), array('stage' => 'independent', 'locked_count' => count($ids)));
    }

    return $options;
}

function zsr_reconcile_widget_options()
{
    if ((function_exists('is_admin') && !is_admin()) || !function_exists('get_option') || !function_exists('update_option') || !function_exists('current_user_can') || !current_user_can('manage_options')) {
        return false;
    }

    $options = get_option(ZSR_OPTION, array());
    $options = is_array($options) ? $options : array();
    $missing = new stdClass();
    $stored = get_option('zsr_widget_locked', $missing);
    $migrated = $stored === $missing;

    if ($migrated) {
        if (!array_key_exists('zsr_widget_locked', $options)) {
            return true;
        }
        $synced = zsr_sync_widget_options($options);
        if (get_option('zsr_widget_locked', $missing) === $missing) {
            return false;
        }
        $locked = array_keys(zsr_get_locked_widgets());
        $options['zsr_widget_exclude'] = $synced['zsr_widget_exclude'];
    } else {
        $locked = array_keys(zsr_get_locked_widgets());
    }

    $previous = isset($options['zsr_widget_locked']) ? $options['zsr_widget_locked'] : null;
    if ($previous === $locked && !$migrated) {
        return true;
    }

    $options['zsr_widget_locked'] = $locked;
    $saved = false;
    try {
        update_option(ZSR_OPTION, $options);
        $saved = get_option(ZSR_OPTION, null) === $options;
    } catch (Throwable $error) {
        $saved = false;
    }

    if (!$saved) {
        zsr_widget_sync_feedback('error', 'widget.options_sync_failed', __('小工具可见性配置显示同步失败，实际控制仍使用独立配置，请检查诊断日志后重试。', 'zib-sub-review'), array('stage' => 'mirror', 'locked_count' => count($locked)));
        return false;
    }

    if ($migrated) {
        zsr_widget_sync_feedback('info', 'widget.options_migrated', '', array('locked_count' => count($locked)));
    } else {
        zsr_widget_sync_feedback('warning', 'widget.options_reconciled', __('检测到小工具可见性设置不一致，已按独立配置恢复页面设置。', 'zib-sub-review'), array('locked_count' => count($locked)));
    }

    return true;
}

function zsr_widget_sync_notice()
{
    if (!function_exists('current_user_can') || !current_user_can('manage_options') || !isset($_GET['page']) || $_GET['page'] !== 'zsr_options' || empty($GLOBALS['zsr_widget_sync_feedback'])) {
        return;
    }

    $feedback = $GLOBALS['zsr_widget_sync_feedback'];
    $class = $feedback['level'] === 'error' ? 'notice-error' : 'notice-warning';
    echo '<div class="notice ' . esc_attr($class) . '"><p>' . esc_html($feedback['message']) . '</p></div>';
}
