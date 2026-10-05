<?php

define('ABSPATH', __DIR__ . '/');
define('ZSR_OPTION', 'zsr_options');
define('ZSR_VERSION', '0.1.0');
define('ZSR_DB_VERSION', 1);

$zsr_test_options = array();
$zsr_test_theme_options = array('user_cap' => array('new_post_add' => array('logged' => true)));

function get_option($key, $default = false)
{
    global $zsr_test_options, $zsr_test_theme_options;
    if ($key === 'zibll_options') {
        return $zsr_test_theme_options;
    }
    return array_key_exists($key, $zsr_test_options) ? $zsr_test_options[$key] : $default;
}

function update_option($key, $value)
{
    global $zsr_test_options, $zsr_test_theme_options;
    if ($key === 'zibll_options') {
        $zsr_test_theme_options = $value;
        return true;
    }
    $zsr_test_options[$key] = $value;
    return true;
}

function add_option($key, $value)
{
    global $zsr_test_options;
    if (!array_key_exists($key, $zsr_test_options)) {
        $zsr_test_options[$key] = $value;
    }
    return true;
}

class ZsrTestTheme
{
    public function get_template()
    {
        return 'zibll';
    }

    public function get_stylesheet()
    {
        return 'zibll';
    }

    public function get($key)
    {
        return $key === 'Name' ? 'Zibll 子比主题' : '';
    }
}

function wp_get_theme()
{
    return new ZsrTestTheme();
}

function _pz($key, $default = array())
{
    global $zsr_test_theme_options;
    return isset($zsr_test_theme_options[$key]) ? $zsr_test_theme_options[$key] : $default;
}

function _spz($key, $value)
{
    global $zsr_test_theme_options;
    $zsr_test_theme_options[$key] = $value;
}

function zib_current_user_can($capability)
{
    return $capability === 'zsr_review';
}

function zsr_test_assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

require_once dirname(__DIR__) . '/inc/core/dependencies.php';
require_once dirname(__DIR__) . '/inc/core/options.php';
require_once dirname(__DIR__) . '/inc/core/capabilities.php';

$defaults = zsr_get_options();
zsr_test_assert($defaults['zsr_enable'] === true, 'default option');
zsr_test_assert(zsr_dependencies_ready() === true, 'required theme functions are available');

function zib_get_template_page_url($template, $args = array())
{
    return '/submissions/';
}

$report = zsr_dependency_report();
zsr_test_assert(empty($report['missing']), 'theme dependency report');

zsr_install_options();
zsr_test_assert(get_option('zsr_version') === '0.1.0', 'version option');
zsr_test_assert(get_option(ZSR_OPTION)['zsr_page_slug'] === 'submissions', 'installed defaults');

$stored = get_option(ZSR_OPTION);
$stored['zsr_menu_label'] = '自定义入口';
update_option(ZSR_OPTION, $stored);
zsr_install_options();
zsr_test_assert(get_option(ZSR_OPTION)['zsr_menu_label'] === '自定义入口', 'existing option preserved');

zsr_test_assert(zsr_register_capabilities() === true, 'capability registration');
zsr_test_assert(isset($zsr_test_theme_options['user_cap']['zsr_review']), 'review capability added');
zsr_test_assert(zsr_current_user_can('zsr_review') === true, 'capability wrapper');

fwrite(STDOUT, "core tests passed\n");
