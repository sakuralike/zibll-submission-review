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
if (count($zsr_test_csf_sections) !== 5) {
    fwrite(STDERR, "FAIL: expected five CSF sections\n");
    exit(1);
}

$roles = $zsr_test_csf_sections[1][1]['fields'];
if ($roles[0]['type'] !== 'fieldset' || count($roles[0]['fields']) !== 8) {
    fwrite(STDERR, "FAIL: role fieldset shape\n");
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
