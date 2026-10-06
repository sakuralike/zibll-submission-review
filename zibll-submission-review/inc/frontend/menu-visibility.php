<?php

if (!defined('ABSPATH')) {
    exit;
}

function zsr_normalize_menu_item_ids($value)
{
    $ids = array();
    foreach (is_array($value) ? $value : array() as $id) {
        if ((is_int($id) || is_string($id)) && preg_match('/^[1-9][0-9]*$/D', (string) $id) && (int) $id > 0) {
            $ids[(int) $id] = (int) $id;
        }
    }
    return array_values($ids);
}

function zsr_filter_guest_menu_items($items, $args)
{
    $location = is_object($args) && isset($args->theme_location) ? $args->theme_location : '';
    if (!is_array($items) || is_admin() || is_user_logged_in() || !in_array($location, array('topmenu', 'mobilemenu'), true)) {
        return $items;
    }
    $hidden = array_fill_keys(zsr_normalize_menu_item_ids(zsr_get_option('zsr_guest_hidden_menu_items', array())), true);
    if (!$hidden) {
        return $items;
    }
    do {
        $changed = false;
        foreach ($items as $item) {
            if (isset($hidden[(int) $item->menu_item_parent]) && !isset($hidden[(int) $item->ID])) {
                $hidden[(int) $item->ID] = true;
                $changed = true;
            }
        }
    } while ($changed);

    $parents = array();
    $remaining_parents = array();
    $visible = array();
    foreach ($items as $item) {
        $parents[(int) $item->menu_item_parent] = true;
        if (!isset($hidden[(int) $item->ID])) {
            $visible[] = $item;
            $remaining_parents[(int) $item->menu_item_parent] = true;
        }
    }
    foreach ($visible as $item) {
        if (isset($parents[(int) $item->ID]) && !isset($remaining_parents[(int) $item->ID]) && isset($item->classes)) {
            $item->classes = array_values(array_diff((array) $item->classes, array('menu-item-has-children')));
        }
    }
    return $visible;
}

function zsr_header_menu_choices()
{
    $choices = array();
    if (!function_exists('get_nav_menu_locations') || !function_exists('wp_get_nav_menu_items')) {
        return $choices;
    }
    $locations = get_nav_menu_locations();
    $labels = array('topmenu' => __('顶部导航', 'zib-sub-review'), 'mobilemenu' => __('移动端导航', 'zib-sub-review'));
    foreach ($labels as $location => $label) {
        if (empty($locations[$location])) {
            continue;
        }
        foreach ((array) wp_get_nav_menu_items($locations[$location]) as $item) {
            if (is_object($item) && !isset($choices[$item->ID])) {
                $choices[$item->ID] = $label . ' / ' . strip_tags((string) $item->title) . ' (#' . (int) $item->ID . ')';
            }
        }
    }
    return $choices;
}
