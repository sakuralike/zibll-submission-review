<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/i18n-helpers.php';

define('ABSPATH', __DIR__ . '/');
define('ZSR_OPTION', 'zsr_options');
define('ZSR_VERSION', '0.1.0');
define('ZSR_DB_VERSION', 1);

$zsr_test_options = array();
$zsr_test_theme_options = array('user_cap' => array('new_post_add' => array('logged' => true)));
$zsr_test_user = (object) array('ID' => 12, 'roles' => array('administrator'));
$zsr_test_ajax_hooks = array();

function add_action($hook, $callback, $priority = 10, $accepted_args = 1)
{
    $GLOBALS['zsr_test_ajax_hooks'][$hook] = $callback;
}

function wp_roles()
{
    return new class {
        public function get_names()
        {
            return array('administrator' => 'Administrator', 'editor' => 'Editor', 'author' => 'Author', 'contributor' => 'Contributor', 'subscriber' => 'Subscriber', 'proofreader' => 'Proofreader', 'moderator' => 'Custom moderator');
        }
    };
}

function wp_get_current_user() { return $GLOBALS['zsr_test_user']; }
function get_current_user_id() { return $GLOBALS['zsr_test_user']->ID; }
function current_user_can($capability) { return $capability === 'manage_options' && in_array('administrator', $GLOBALS['zsr_test_user']->roles, true); }

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

function zib_current_user_can($capability, ...$args)
{
    return $capability === 'zsr_review' || ($capability === 'new_post_edit' && isset($args[0]) && $args[0] === 55);
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
require_once dirname(__DIR__) . '/inc/ajax/submit.php';

foreach (array('zsr_submit', 'zsr_update', 'zsr_draft') as $action) {
    zsr_test_assert(!isset($zsr_test_ajax_hooks['wp_ajax_' . $action]), 'removed submission action is not registered: ' . $action);
    zsr_test_assert(!isset($zsr_test_ajax_hooks['wp_ajax_nopriv_' . $action]), 'removed submission action has no public endpoint: ' . $action);
}
zsr_test_assert(function_exists('zsr_ajax_response') && function_exists('zsr_verify_ajax_nonce'), 'shared review response and nonce helpers remain available');

$defaults = zsr_get_options();
zsr_test_assert($defaults['zsr_enable'] === true, 'default option');
zsr_test_assert($defaults['zsr_cap_submit'] === array('administrator', 'editor', 'author', 'contributor', 'subscriber', 'proofreader', 'moderator'), 'submission defaults include registered WordPress roles');
zsr_test_assert($defaults['zsr_cap_review'] === array('administrator'), 'review defaults are administrators only');
zsr_test_assert(!function_exists('zib_ajax_new_posts'), 'native submission API is absent from this test runtime');
zsr_test_assert(!isset(zsr_required_theme_functions()['zib_ajax_new_posts']), 'review feature does not depend on the native submission API');
zsr_test_assert(zsr_dependencies_ready() === true, 'review dependencies are ready without the native submission API');

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

$zsr_test_theme_options['user_cap']['zsr_review'] = array('moderator' => true);
zsr_test_assert(zsr_register_capabilities() === true, 'capability registration');
zsr_test_assert(!isset($zsr_test_theme_options['user_cap']['zsr_review']), 'legacy plugin theme permissions are removed');
zsr_test_assert(zsr_current_user_can('zsr_review') === true, 'capability wrapper');
$zsr_test_user->roles = array('editor');
zsr_test_assert(zsr_current_user_can('zsr_review') === false, 'unselected WordPress role cannot review despite theme permission');
$zsr_test_user->roles = array('administrator');

$normalized = zsr_normalize_options(array(
    'zsr_log_enable'          => '1',
    'zsr_log_level'           => 'invalid',
    'zsr_page_slug'            => '  My Post  ',
    'zsr_actions'              => array('invalid', 'reject', 'reject'),
    'zsr_cap_submit'           => array('proofreader', 'editor', 'unknown', 'editor'),
    'zsr_cap_review'           => array('moderator' => '1'),
    'zsr_cap_review_others'    => array('auth' => true),
    'zsr_review_self_only'     => '1',
    'zsr_reason_maxlength'     => 99999,
    'zsr_notify_channel'       => array('email', 'invalid'),
    'zsr_widget_visitor_action'=> 'invalid',
));
zsr_test_assert($normalized['zsr_log_enable'] === true, 'log enable normalization');
zsr_test_assert($normalized['zsr_log_level'] === 'info', 'log level fallback');
zsr_test_assert($normalized['zsr_page_slug'] === 'my-post', 'slug normalization');
zsr_test_assert($normalized['zsr_actions'] === array('reject'), 'action whitelist and dedupe');
zsr_test_assert($normalized['zsr_cap_submit'] === array('proofreader', 'editor'), 'registered WordPress role whitelist');
zsr_test_assert($normalized['zsr_cap_review'] === array('administrator'), 'legacy theme moderator is not granted review access');
zsr_test_assert($normalized['zsr_cap_review_others'] === array(), 'self only clears others capability');
zsr_test_assert($normalized['zsr_reason_maxlength'] === 2000, 'reason length bound');
zsr_test_assert($normalized['zsr_notify_channel'] === array('email'), 'notification channel whitelist');
zsr_test_assert($normalized['zsr_widget_visitor_action'] === 'hidden', 'widget action fallback hides guest output');

zsr_save_options($normalized);
zsr_test_assert(!isset($zsr_test_theme_options['user_cap']['zsr_submit']), 'WordPress role permissions are not written to theme capabilities');
zsr_test_assert(!isset($zsr_test_theme_options['user_cap']['zsr_review_others']), 'review permissions remain plugin-owned');
zsr_test_assert($zsr_test_theme_options['user_cap']['new_post_add'] === array('logged' => true), 'theme capability preserved');

$empty_submit = zsr_normalize_options(array('zsr_cap_submit' => array()));
zsr_test_assert($empty_submit['zsr_cap_submit'] === array(), 'empty role map remains empty');

zsr_save_options(array('zsr_cap_submit' => array('proofreader'), 'zsr_cap_review' => array('editor'), 'zsr_cap_review_others' => array('editor')));
$zsr_test_user->roles = array('proofreader');
zsr_test_assert(zsr_current_user_can('zsr_submit') === true, 'selected custom WordPress role can submit without theme plugin permission');
zsr_test_assert(zsr_current_user_can('zsr_review') === false, 'custom submit role does not gain review permission');
$zsr_test_user->roles = array('subscriber', 'editor');
zsr_test_assert(zsr_current_user_can('zsr_review') === true && zsr_current_user_can('zsr_review_others') === true, 'any selected WordPress role can authorize a multi-role user');
zsr_test_assert(zsr_current_user_can('zsr_submit') === false, 'review permission does not grant submission permission');
zsr_test_assert(zsr_current_user_can('zsr_manage') === false, 'reviewers cannot manage settings');
zsr_test_assert(zsr_current_user_can('new_post_edit', 55) === true && zsr_current_user_can('new_post_edit', 56) === false, 'native theme permission keeps post-specific arguments');
zsr_test_assert(zsr_current_user_can('zsr_unknown') === false, 'unknown plugin capability fails closed');
$zsr_test_user->ID = 0;
zsr_test_assert(zsr_current_user_can('zsr_review') === false && zsr_current_user_can('zsr_manage') === false, 'guest cannot receive plugin permissions');
$zsr_test_user->ID = 12;
$zsr_test_user->roles = array('administrator');
zsr_save_options(array('zsr_cap_submit' => '', 'zsr_cap_review' => array(), 'zsr_cap_review_others' => array()));
zsr_test_assert(zsr_current_user_can('zsr_submit') === false && zsr_current_user_can('zsr_review') === false && zsr_current_user_can('zsr_review_others') === false, 'empty checkboxes deny plugin actions even to an administrator');
zsr_test_assert(zsr_current_user_can('zsr_manage') === true, 'administrator retains settings management after clearing action roles');

update_option(ZSR_OPTION, array('zsr_cap_submit' => array('logged' => true), 'zsr_cap_review' => array('moderator' => true), 'zsr_cap_review_others' => array('all' => true), 'zsr_menu_label' => '保留设置'));
zsr_register_capabilities();
$migrated = get_option(ZSR_OPTION);
zsr_test_assert($migrated['zsr_cap_submit'] === array('administrator', 'editor', 'author', 'contributor', 'subscriber', 'proofreader', 'moderator'), 'legacy logged submission remains available to registered roles');
zsr_test_assert($migrated['zsr_cap_review'] === array('administrator') && $migrated['zsr_cap_review_others'] === array('administrator'), 'legacy review identities migrate to administrator only');
zsr_test_assert($migrated['zsr_menu_label'] === '保留设置', 'role migration preserves unrelated settings');
$zsr_test_user->roles = array('moderator');
zsr_test_assert(zsr_current_user_can('zsr_review') === false, 'legacy moderator map cannot authorize a custom WordPress role with the same slug');
zsr_register_capabilities();
zsr_test_assert(get_option(ZSR_OPTION) === $migrated, 'role migration is idempotent');
$restricted = zsr_normalize_options(array('zsr_cap_submit' => array('auth' => true)));
zsr_test_assert($restricted['zsr_cap_submit'] === array('administrator'), 'restricted legacy submission does not open access to all WordPress roles');

fwrite(STDOUT, "core tests passed\n");
