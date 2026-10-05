<?php

if (!defined('ABSPATH')) {
    exit;
}

function zsr_widget_object_details($widget, $name = '')
{
    if (!is_object($widget) || !is_a($widget, 'WP_Widget') || !isset($widget->id_base) || !is_string($widget->id_base) || !preg_match('/^(?![0-9]+$)[a-z0-9_-]+$/D', $widget->id_base)) {
        return null;
    }

    if (!is_string($name) || trim($name) === '') {
        $name = isset($widget->name) && is_string($widget->name) ? $widget->name : $widget->id_base;
    }
    $name = trim(strip_tags(html_entity_decode($name, ENT_QUOTES, 'UTF-8')));

    return array('name' => $name !== '' ? $name : $widget->id_base, 'object' => $widget, 'csf' => is_a($widget, 'CSF_Widget'));
}

function zsr_get_registered_widgets()
{
    global $wp_widget_factory, $wp_registered_widgets;

    $widgets = array();
    if (is_object($wp_widget_factory) && isset($wp_widget_factory->widgets) && is_array($wp_widget_factory->widgets)) {
        foreach ($wp_widget_factory->widgets as $widget) {
            $details = zsr_widget_object_details($widget);
            if ($details !== null) {
                $widgets[$widget->id_base] = $details;
            }
        }
    }

    foreach (is_array($wp_registered_widgets) ? $wp_registered_widgets : array() as $registration) {
        if (!is_array($registration) || !isset($registration['callback']) || !is_array($registration['callback']) || !isset($registration['callback'][0]) || !is_callable($registration['callback'])) {
            continue;
        }
        $widget = $registration['callback'][0];
        $details = zsr_widget_object_details($widget, isset($registration['name']) ? $registration['name'] : '');
        if ($details !== null) {
            $widgets[$widget->id_base] = $details;
        }
    }

    return $widgets;
}

function zsr_get_enabled_widgets()
{
    global $wp_registered_sidebars, $wp_registered_widgets;

    $enabled = array();
    if (!function_exists('wp_get_sidebars_widgets')) {
        return $enabled;
    }
    $sidebars = wp_get_sidebars_widgets();
    if (!is_array($sidebars) || !is_array($wp_registered_sidebars) || !is_array($wp_registered_widgets)) {
        return $enabled;
    }
    $widgets = zsr_get_registered_widgets();
    $counted = array();

    foreach ($sidebars as $sidebar_id => $instances) {
        if (!is_string($sidebar_id) || in_array($sidebar_id, array('wp_inactive_widgets', 'array_version'), true) || strpos($sidebar_id, 'orphaned_widgets') === 0 || !isset($wp_registered_sidebars[$sidebar_id]) || !is_array($wp_registered_sidebars[$sidebar_id]) || !is_array($instances)) {
            continue;
        }
        foreach ($instances as $widget_id) {
            if (!is_string($widget_id) || !isset($wp_registered_widgets[$widget_id]['callback']) || !is_array($wp_registered_widgets[$widget_id]['callback']) || !isset($wp_registered_widgets[$widget_id]['callback'][0]) || !is_callable($wp_registered_widgets[$widget_id]['callback'])) {
                continue;
            }
            $widget = $wp_registered_widgets[$widget_id]['callback'][0];
            $details = zsr_widget_object_details($widget);
            if ($details === null || !isset($widgets[$widget->id_base])) {
                continue;
            }
            $id = $widget->id_base;
            if (!isset($enabled[$id])) {
                $enabled[$id] = array('name' => $widgets[$id]['name'], 'sidebars' => array(), 'count' => 0);
                $counted[$id] = array();
            }
            if (!isset($enabled[$id]['sidebars'][$sidebar_id])) {
                $enabled[$id]['sidebars'][$sidebar_id] = array();
            }
            if (!in_array($widget_id, $enabled[$id]['sidebars'][$sidebar_id], true)) {
                $enabled[$id]['sidebars'][$sidebar_id][] = $widget_id;
            }
            $counted[$id][$widget_id] = true;
            $enabled[$id]['count'] = count($counted[$id]);
        }
    }

    return $enabled;
}

function zsr_widget_choices()
{
    global $wp_registered_sidebars;

    $choices = array();
    foreach (zsr_get_enabled_widgets() as $id => $widget) {
        $names = array();
        foreach (array_keys($widget['sidebars']) as $sidebar_id) {
            $name = isset($wp_registered_sidebars[$sidebar_id]['name']) && is_string($wp_registered_sidebars[$sidebar_id]['name']) ? $wp_registered_sidebars[$sidebar_id]['name'] : $sidebar_id;
            $name = trim(strip_tags(html_entity_decode($name, ENT_QUOTES, 'UTF-8')));
            $names[] = $name !== '' ? $name : $sidebar_id;
        }
        $choices[$id] = $widget['name'] . '（' . $id . '；' . implode('、', array_unique($names)) . '；' . $widget['count'] . '实例）';
    }

    $configured = array_keys(zsr_get_locked_widgets());
    if (function_exists('zsr_get_option')) {
        $configured = array_unique(array_merge($configured, zsr_normalize_widget_ids(zsr_get_option('zsr_widget_exclude', array()))));
    }
    $registered = zsr_get_registered_widgets();
    $inactive = array();
    foreach ($configured as $id) {
        if (!isset($choices[$id])) {
            $name = isset($registered[$id]) ? $registered[$id]['name'] : $id;
            $inactive[$id] = $name . '（' . $id . '）';
        }
    }

    if (!$inactive) {
        return $choices;
    }

    $grouped = $choices ? array('已启用' => $choices) : array();
    $grouped['已配置但未启用'] = $inactive;
    return $grouped;
}
