<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

define('ABSPATH', __DIR__ . '/');
require_once __DIR__ . '/i18n-helpers.php';

$menu_logged_in = false;
$menu_admin = false;
$menu_options = array('zsr_guest_hidden_menu_items' => array(10));
$menu_locations = array('topmenu' => 7, 'mobilemenu' => 8, 'footer' => 9);
$menu_fixture = array(
    (object) array('ID' => 12, 'menu_item_parent' => 11, 'title' => 'Grandchild', 'classes' => array()),
    (object) array('ID' => 1, 'menu_item_parent' => 0, 'title' => 'Public', 'classes' => array()),
    (object) array('ID' => 11, 'menu_item_parent' => 10, 'title' => 'Child', 'classes' => array('menu-item-has-children')),
    (object) array('ID' => 10, 'menu_item_parent' => 0, 'title' => 'Private', 'classes' => array('menu-item-has-children')),
);

function is_admin() { return $GLOBALS['menu_admin']; }
function is_user_logged_in() { return $GLOBALS['menu_logged_in']; }
function zsr_get_option($key, $default = null) { return isset($GLOBALS['menu_options'][$key]) ? $GLOBALS['menu_options'][$key] : $default; }
function get_nav_menu_locations() { return $GLOBALS['menu_locations']; }
function wp_get_nav_menu_items($id) { return $id === 7 ? $GLOBALS['menu_fixture'] : array(); }
function menu_assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, 'FAIL: ' . $message . "\n");
        exit(1);
    }
}
function menu_filtered($location)
{
    $items = array_map(function ($item) { return clone $item; }, $GLOBALS['menu_fixture']);
    return function_exists('zsr_filter_guest_menu_items')
        ? zsr_filter_guest_menu_items($items, (object) array('theme_location' => $location)) : $items;
}

$module = dirname(__DIR__) . '/inc/frontend/menu-visibility.php';
if (is_file($module)) {
    require_once $module;
}
menu_assert(array_column(menu_filtered('topmenu'), 'ID') === array(1), 'guest header excludes the selected item and all descendants');
menu_assert(array_column(menu_filtered('mobilemenu'), 'ID') === array(1), 'mobile header applies the same guest visibility rule');
menu_assert(count(menu_filtered('footer')) === 4, 'unrelated menus are not filtered');
$menu_logged_in = true;
menu_assert(count(menu_filtered('topmenu')) === 4, 'logged-in users retain all header items');
$menu_logged_in = false;
$menu_admin = true;
menu_assert(count(menu_filtered('topmenu')) === 4, 'menu management retains all items');
$menu_admin = false;
$menu_options['zsr_guest_hidden_menu_items'] = array(11);
$visible = menu_filtered('topmenu');
menu_assert(array_column($visible, 'ID') === array(1, 10), 'hiding a child preserves its public parent');
menu_assert(!in_array('menu-item-has-children', $visible[1]->classes, true), 'an emptied submenu does not leave a dropdown marker');
$menu_options['zsr_guest_hidden_menu_items'] = array();
menu_assert(count(menu_filtered('topmenu')) === 4, 'clearing the selection restores the header');
menu_assert(zsr_normalize_menu_item_ids(array('10', 10, -2, 0, true, array(4), '1x')) === array(10), 'only positive menu item IDs are stored');
$choices = zsr_header_menu_choices();
menu_assert(count($choices) === 4 && strpos($choices[10], 'Private') !== false, 'settings list header menu items without unrelated menus');

fwrite(STDOUT, "navigation visibility tests passed\n");
