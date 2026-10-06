<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/i18n-helpers.php';

define('ABSPATH', __DIR__ . '/');
define('ZSR_OPTION', 'zsr_options');

$zsr_test_csf_options = array();
$zsr_test_csf_sections = array();
$zsr_test_hooks = array();

function wp_roles()
{
    return new class {
        public function get_names()
        {
            return array('administrator' => 'Administrator', 'editor' => 'Editor', 'author' => 'Author', 'contributor' => 'Contributor', 'subscriber' => 'Subscriber', 'proofreader' => 'Proofreader');
        }
    };
}

function is_admin()
{
    return true;
}

function add_filter($hook, $callback, $priority = 10, $accepted_args = 1)
{
    global $zsr_test_hooks;
    $zsr_test_hooks[] = array('filter', $hook, $callback, $accepted_args);
}

function add_action($hook, $callback, $priority = 10, $accepted_args = 1)
{
    global $zsr_test_hooks;
    $zsr_test_hooks[] = array('action', $hook, $callback, $accepted_args);
}

function register_setting($group, $name, $args = array())
{
    global $zsr_test_hooks;
    $zsr_test_hooks[] = array('setting', $group, $name, $args);
}

class CSF
{
    public static function createOptions($prefix, $args)
    {
        global $zsr_test_csf_options;
        $zsr_test_csf_options[$prefix] = $args;
    }

    public static function createSection($prefix, $args)
    {
        global $zsr_test_csf_sections;
        $zsr_test_csf_sections[] = array($prefix, $args);
    }
}

require_once dirname(__DIR__) . '/inc/core/options.php';
require_once dirname(__DIR__) . '/inc/core/capabilities.php';
require_once dirname(__DIR__) . '/inc/admin/options.php';

zsr_register_admin_options();

if (!isset($zsr_test_csf_options['zsr_options'])) {
    fwrite(STDERR, "FAIL: CSF options were not registered\n");
    exit(1);
}
if (count($zsr_test_csf_sections) !== 6) {
    fwrite(STDERR, "FAIL: settings must include the diagnostic log section\n");
    exit(1);
}

$general_fields = array_column($zsr_test_csf_sections[0][1]['fields'], null, 'id');
$roles = $zsr_test_csf_sections[1][1]['fields'];
$role_fields = array_column($roles, null, 'id');
if (isset($general_fields['zsr_enable_submit']) || isset($role_fields['zsr_cap_submit'])) {
    fwrite(STDERR, "FAIL: removed submission feature has no settings fields\n");
    exit(1);
}
foreach (array_slice($roles, 0, 2) as $field) {
    if ($field['type'] !== 'checkbox' || array_keys($field['options']) !== array('administrator', 'editor', 'author', 'contributor', 'subscriber', 'proofreader')) {
        fwrite(STDERR, "FAIL: permission settings list registered WordPress roles including custom roles\n");
        exit(1);
    }
}

$widget_fields = array_column($zsr_test_csf_sections[4][1]['fields'], null, 'id');
if ($widget_fields['zsr_widget_visitor_action']['default'] !== 'hidden'
    || !isset($widget_fields['zsr_guest_hidden_menu_items'])
    || $widget_fields['zsr_guest_hidden_menu_items']['options'] !== 'zsr_header_menu_choices') {
    fwrite(STDERR, "FAIL: guest controls default to hidden and list header menu items\n");
    exit(1);
}
if ($zsr_test_csf_sections[5][1]['fields'][0]['function'] !== 'zsr_render_admin_logs') {
    fwrite(STDERR, "FAIL: diagnostic logs have a visible settings renderer\n");
    exit(1);
}

$hook_names = array_map(function ($hook) {
    return $hook[1];
}, $zsr_test_hooks);
if (!in_array('csf_zsr_options_save', $hook_names, true) || !in_array('csf_zsr_options_saved', $hook_names, true)) {
    fwrite(STDERR, "FAIL: CSF save hooks\n");
    exit(1);
}

fwrite(STDOUT, "admin-options tests passed\n");
