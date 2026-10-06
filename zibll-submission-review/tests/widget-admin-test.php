<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/i18n-helpers.php';

define('ABSPATH', __DIR__ . '/');
define('ZSR_OPTION', 'zsr_options');

$zsr_admin_assertions = 0;
$zsr_admin_hooks = array();
$zsr_admin_options = array();
$zsr_admin_fail = array();
$zsr_admin_errors = array();
$zsr_admin_sidebar_reads = 0;
$zsr_admin_sections = array();
$zsr_admin_csf_options = array();
$zsr_admin_sidebars = array();
$zsr_admin_theme = array();
$zsr_admin_native = in_array('--native', isset($argv) ? $argv : array(), true);
$zsr_admin_write_depth = 0;
$zsr_admin_max_write_depth = 0;
$zsr_admin_settings = array();

function zsr_admin_assert($condition, $message)
{
    $GLOBALS['zsr_admin_assertions']++;
    if (!$condition) {
        fwrite(STDERR, 'FAIL: ' . $message . "\n");
        exit(1);
    }
}

function is_admin() { return true; }
function current_user_can($capability) { return $capability === 'manage_options'; }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function wp_roles()
{
    return new class {
        public function get_names() { return array('administrator' => 'Administrator', 'contributor' => 'Contributor'); }
    };
}

function add_filter($hook, $callback, $priority = 10, $accepted_args = 1)
{
    $GLOBALS['zsr_admin_hooks'][$hook][$priority][] = array($callback, $accepted_args);
}

function add_action($hook, $callback, $priority = 10, $accepted_args = 1)
{
    add_filter($hook, $callback, $priority, $accepted_args);
}

function apply_filters($hook, $value, ...$args)
{
    $callbacks = isset($GLOBALS['zsr_admin_hooks'][$hook]) ? $GLOBALS['zsr_admin_hooks'][$hook] : array();
    ksort($callbacks);
    foreach ($callbacks as $priority) {
        foreach ($priority as $callback) {
            $value = call_user_func_array($callback[0], array_slice(array_merge(array($value), $args), 0, $callback[1]));
        }
    }
    return $value;
}

function do_action($hook, ...$args)
{
    $callbacks = isset($GLOBALS['zsr_admin_hooks'][$hook]) ? $GLOBALS['zsr_admin_hooks'][$hook] : array();
    ksort($callbacks);
    foreach ($callbacks as $priority) {
        foreach ($priority as $callback) {
            call_user_func_array($callback[0], array_slice($args, 0, $callback[1]));
        }
    }
}

function get_option($name, $default = false)
{
    return array_key_exists($name, $GLOBALS['zsr_admin_options']) ? $GLOBALS['zsr_admin_options'][$name] : $default;
}

function update_option($name, $value, $autoload = null)
{
    $GLOBALS['zsr_admin_write_depth']++;
    $GLOBALS['zsr_admin_max_write_depth'] = max($GLOBALS['zsr_admin_max_write_depth'], $GLOBALS['zsr_admin_write_depth']);
    zsr_admin_assert($GLOBALS['zsr_admin_write_depth'] <= 2, 'option sanitization cannot recursively save its own option');
    $value = apply_filters('sanitize_option_' . $name, $value, $name, $value);
    if (in_array($name, $GLOBALS['zsr_admin_fail'], true)) { $GLOBALS['zsr_admin_write_depth']--; return false; }
    $exists = array_key_exists($name, $GLOBALS['zsr_admin_options']);
    $previous = get_option($name, null);
    $changed = get_option($name, null) !== $value;
    $GLOBALS['zsr_admin_options'][$name] = $value;
    if ($changed) {
        do_action(($exists ? 'update_option_' : 'add_option_') . $name, $exists ? $previous : $name, $value, $name);
    }
    $GLOBALS['zsr_admin_write_depth']--;
    return $changed;
}

function register_setting($group, $name, $args = array())
{
    $GLOBALS['zsr_admin_settings'][$name] = $args;
    add_filter('sanitize_option_' . $name, $args['sanitize_callback']);
}

function _pz($key, $default = null)
{
    return isset($GLOBALS['zsr_admin_theme'][$key]) ? $GLOBALS['zsr_admin_theme'][$key] : $default;
}

function _spz($key, $value) { $GLOBALS['zsr_admin_theme'][$key] = $value; }

function add_settings_error($setting, $code, $message, $type = 'error')
{
    $GLOBALS['zsr_admin_errors'][] = array($setting, $code, $message, $type);
}

function wp_get_sidebars_widgets()
{
    $GLOBALS['zsr_admin_sidebar_reads']++;
    return $GLOBALS['zsr_admin_sidebars'];
}

if (!$zsr_admin_native) {
    class CSF
    {
        public static function createOptions($prefix, $args) { $GLOBALS['zsr_admin_csf_options'][$prefix] = $args; }
        public static function createSection($prefix, $args) { $GLOBALS['zsr_admin_sections'][$args['id']] = $args; }
    }
}

class WP_Widget
{
    public $id_base;
    public $name;
    public function __construct($id_base, $name) { $this->id_base = $id_base; $this->name = $name; }
    public function display_callback($args, $widget_args = 1) {}
}

class CSF_Widget extends WP_Widget {}

function zsr_admin_csf_save($submitted, $instance)
{
    $data = array();
    foreach ($GLOBALS['zsr_admin_sections'] as $section) {
        foreach ($section['fields'] as $field) {
            if (empty($field['id'])) {
                continue;
            }
            $data[$field['id']] = isset($submitted[$field['id']]) ? $submitted[$field['id']] : '';
        }
    }
    $data = apply_filters('csf_zsr_options_save', $data, $instance);
    update_option(ZSR_OPTION, $data);
    do_action('csf_zsr_options_saved', $data, $instance);
    if ($instance->notice === '') { $instance->notice = 'Settings saved.'; }
    return $data;
}

require_once dirname(__DIR__) . '/inc/core/options.php';
require_once dirname(__DIR__) . '/inc/core/capabilities.php';
require_once dirname(__DIR__) . '/inc/widget/sync.php';
require_once dirname(__DIR__) . '/inc/widget/enumerator.php';
require_once dirname(__DIR__) . '/inc/admin/options.php';
require_once dirname(__DIR__) . '/inc/core/bootstrap.php';

if ($zsr_admin_native) {
    zsr_register_admin_options();
    zsr_admin_assert($zsr_admin_settings[ZSR_OPTION]['sanitize_callback'] === 'zsr_prepare_options_for_save', 'native registration uses a non-recursive sanitizer');
    foreach (array('add_option_', 'update_option_') as $prefix) {
        zsr_admin_assert($zsr_admin_hooks[$prefix . ZSR_OPTION][10] === array(array('zsr_after_native_options_saved', 2)), $prefix . ' synchronizes capabilities after persistence');
    }
    $submitted = array_replace(zsr_default_options(), array('zsr_widget_enable' => true, 'zsr_widget_locked' => array('native_locked'), 'zsr_widget_visitor_action' => 'upgrade'));
    $zsr_admin_theme['user_cap'] = array('zsr_submit' => array('logged' => true), 'native_permission' => array('level' => 2));
    update_option(ZSR_OPTION, $submitted);
    zsr_admin_assert(get_option('zsr_widget_locked') === array('native_locked' => '1') && get_option(ZSR_OPTION)['zsr_widget_locked'] === array('native_locked'), 'native add-option sanitization synchronizes map and mirror');
    zsr_admin_assert($zsr_admin_theme['user_cap'] === array('native_permission' => array('level' => 2)), 'native add-option hook removes legacy plugin capabilities without changing theme permissions');
    $submitted['zsr_widget_locked'] = array('changed_locked');
    $submitted['zsr_cap_submit'] = array('contributor');
    update_option(ZSR_OPTION, $submitted);
    zsr_admin_assert(get_option('zsr_widget_locked') === array('changed_locked' => '1'), 'native update-option sanitization synchronizes map');
    zsr_admin_assert(get_option(ZSR_OPTION)['zsr_cap_submit'] === array('contributor') && $zsr_admin_theme['user_cap'] === array('native_permission' => array('level' => 2)), 'native save persists WordPress role permissions without publishing them as theme capabilities');
    $previous = get_option(ZSR_OPTION);
    $submitted = array_replace($previous, array('zsr_widget_enable' => false, 'zsr_widget_locked' => array('new_locked'), 'zsr_widget_visitor_action' => 'hidden', 'zsr_widget_admin_bypass' => false, 'zsr_widget_hide_title' => false, 'zsr_widget_exclude' => array('changed_locked')));
    $zsr_admin_fail = array('zsr_widget_locked');
    update_option(ZSR_OPTION, $submitted);
    foreach (array('zsr_widget_enable', 'zsr_widget_locked', 'zsr_widget_visitor_action', 'zsr_widget_admin_bypass', 'zsr_widget_hide_title', 'zsr_widget_exclude') as $key) {
        zsr_admin_assert(get_option(ZSR_OPTION)[$key] === $previous[$key], 'native failure restores ' . $key);
    }
    zsr_admin_assert(count($zsr_admin_errors) === 1 && $zsr_admin_errors[0][0] === ZSR_OPTION && $zsr_admin_errors[0][1] === 'zsr_widget_sync' && $zsr_admin_errors[0][3] === 'error', 'native failure produces scoped Settings API error');
    zsr_admin_assert(strpos($zsr_admin_errors[0][2], 'new_locked') === false, 'native notice does not disclose submitted identifiers');
    zsr_admin_assert($zsr_admin_max_write_depth === 2 && $zsr_admin_write_depth === 0, 'native save terminates without recursive primary-option writes');
    fwrite(STDOUT, 'widget-admin native tests passed (' . $zsr_admin_assertions . " assertions)\n");
    exit(0);
}

zsr_bootstrap();
zsr_admin_assert($zsr_admin_hooks['after_setup_theme'][19][0][0] === 'zsr_reconcile_widget_options', 'reconciliation runs before CSF initialization');
zsr_admin_assert(in_array(array('zsr_register_admin_options', 1), $zsr_admin_hooks['after_setup_theme'][20], true), 'CSF registration remains at priority 20');
zsr_admin_assert($zsr_admin_hooks['widgets_init'][999][0][0] === 'zsr_register_widget_gates', 'gates register after widget factories');
zsr_admin_assert(in_array(array('zsr_widget_sync_notice', 1), $zsr_admin_hooks['admin_notices'][10], true), 'synchronization notice is registered');
zsr_register_admin_options();
zsr_admin_assert(count($zsr_admin_sections) === 6, 'settings include the diagnostic log section');
zsr_admin_assert($zsr_admin_csf_options['zsr_options']['save_defaults'] === true, 'CSF default persistence remains enabled');
zsr_admin_assert($zsr_admin_sidebar_reads === 0, 'enumeration is deferred until field rendering');
zsr_admin_assert($zsr_admin_hooks['csf_zsr_options_save'][10] === array(array('zsr_prepare_options_for_save', 2)), 'CSF save receives real normalizer and instance');

$fields = array();
foreach ($zsr_admin_sections['zsr_widget']['fields'] as $field) { $fields[$field['id']] = $field; }
foreach (array('zsr_widget_locked', 'zsr_widget_exclude') as $id) {
    zsr_admin_assert($fields[$id]['type'] === 'checkbox', $id . ' uses supported CSF checkbox');
    zsr_admin_assert($fields[$id]['options'] === 'zsr_widget_choices' && is_callable($fields[$id]['options']), $id . ' resolves dynamic choices');
    zsr_admin_assert($fields[$id]['default'] === array(), $id . ' defaults to no selection');
}

$csf = new CSF_Widget('zib_widget_ui_main_post', '主内容');
$legacy = new WP_Widget('widget_ui_mini_posts', '迷你文章');
$inactive = new WP_Widget('old_locked', '旧小工具');
$wp_widget_factory = (object) array('widgets' => array('legacy-class' => $legacy, 'inactive-class' => $inactive));
$wp_registered_widgets = array(
    'zib_widget_ui_main_post-2' => array('callback' => array($csf, 'display_callback'), 'name' => '主内容'),
    'zib_widget_ui_main_post-3' => array('callback' => array($csf, 'display_callback'), 'name' => '主内容'),
    'widget_ui_mini_posts-4' => array('callback' => array($legacy, 'display_callback'), 'name' => '迷你文章'),
    'old_locked-5' => array('callback' => array($inactive, 'display_callback'), 'name' => '旧小工具'),
);
$wp_registered_sidebars = array('home' => array('name' => '首页'), 'sidebar' => array('name' => '文章侧栏'));
$zsr_admin_sidebars = array('home' => array('zib_widget_ui_main_post-2'), 'sidebar' => array('zib_widget_ui_main_post-3', 'widget_ui_mini_posts-4'), 'wp_inactive_widgets' => array('old_locked-5'));
$zsr_admin_options[ZSR_OPTION] = array_replace(zsr_default_options(), array('zsr_widget_exclude' => array('old_excluded')));
$zsr_admin_options['zsr_widget_locked'] = array('old_locked' => '1');
zsr_invalidate_widget_cache();
foreach (array('zsr_widget_locked', 'zsr_widget_exclude') as $id) {
    $choices = call_user_func($fields[$id]['options']);
    zsr_admin_assert(isset($choices['已启用']['zib_widget_ui_main_post'], $choices['已启用']['widget_ui_mini_posts']), $id . ' includes CSF callback objects and legacy active widgets');
    $label = $choices['已启用']['zib_widget_ui_main_post'];
    zsr_admin_assert(strpos($label, '主内容') !== false && strpos($label, 'zib_widget_ui_main_post') !== false && strpos($label, '首页') !== false && strpos($label, '文章侧栏') !== false && strpos($label, '2实例') !== false, $id . ' includes name, id, sidebars and count');
    zsr_admin_assert(!isset($choices['已启用']['old_locked']) && isset($choices['已配置但未启用']['old_locked'], $choices['已配置但未启用']['old_excluded']), $id . ' retains inactive selections separately');
}
zsr_admin_assert($zsr_admin_sidebar_reads === 2, 'CSF callbacks perform live active-widget enumeration');

$instance = (object) array('notice' => '', 'errors' => array());
$submitted = array_replace(zsr_default_options(), array('zsr_widget_enable' => '1', 'zsr_widget_locked' => array('zib_widget_ui_main_post', 'old_locked', 'widget_ui_search', 'widget_ui_user', 'widget_ui_mini_posts', '../invalid'), 'zsr_widget_exclude' => array('widget_ui_mini_posts'), 'zsr_widget_visitor_action' => 'hidden'));
$saved = zsr_admin_csf_save($submitted, $instance);
$expected_locked = array('zib_widget_ui_main_post' => '1', 'old_locked' => '1', 'widget_ui_search' => '1', 'widget_ui_user' => '1');
zsr_admin_assert(get_option('zsr_widget_locked') === $expected_locked, 'CSF save writes canonical map without excluded or invalid ids');
zsr_admin_assert($saved['zsr_widget_locked'] === array_keys($expected_locked) && get_option(ZSR_OPTION) === $saved, 'CSF mirror persists a checkbox list');
zsr_admin_assert($saved['zsr_widget_enable'] === true && $saved['zsr_widget_visitor_action'] === 'hidden', 'CSF save normalizes widget settings');
zsr_admin_assert($instance->notice === 'Settings saved.' && $instance->errors === array(), 'successful CSF save retains normal success feedback');
$submitted = $saved;
$submitted['zsr_menu_label'] = '投稿入口';
$instance = (object) array('notice' => '', 'errors' => array());
$saved = zsr_admin_csf_save($submitted, $instance);
zsr_admin_assert($saved['zsr_menu_label'] === '投稿入口' && get_option('zsr_widget_locked') === $expected_locked, 'saving another section preserves active and inactive selections');
foreach (array_keys($fields) as $key) { zsr_admin_assert(array_key_exists($key, $saved), 'CSF save retains ' . $key); }

$previous = $saved;
$submitted = array_replace($saved, array('zsr_widget_enable' => false, 'zsr_widget_locked' => array('replacement'), 'zsr_widget_visitor_action' => 'upgrade', 'zsr_widget_admin_bypass' => false, 'zsr_widget_hide_title' => false, 'zsr_widget_exclude' => array('old_locked')));
$zsr_admin_fail = array('zsr_widget_locked');
$instance = (object) array('notice' => '', 'errors' => array('unrelated' => 'existing validation'));
$saved = zsr_admin_csf_save($submitted, $instance);
foreach (array_keys($fields) as $key) { zsr_admin_assert($saved[$key] === $previous[$key], 'failed independent write restores ' . $key); }
zsr_admin_assert(get_option('zsr_widget_locked') === $expected_locked, 'failed save preserves live independent map');
zsr_admin_assert($instance->notice !== '' && $instance->notice !== 'Settings saved.' && isset($instance->errors['zsr_widget_locked']), 'failure replaces success and adds a CSF field error');
zsr_admin_assert($instance->errors['zsr_widget_locked'] === $instance->notice && $instance->errors['unrelated'] === 'existing validation', 'CSF feedback preserves existing errors');
zsr_admin_assert(strpos($instance->notice, 'replacement') === false && strpos($instance->notice, 'old_locked') === false, 'failure feedback does not echo identifiers');
zsr_admin_assert($zsr_admin_errors === array(), 'CSF failure emits no duplicate native error');
$zsr_admin_fail = array();
$submitted = $saved;
$submitted['zsr_widget_locked'] = '';
$instance = (object) array('notice' => '', 'errors' => array());
$saved = zsr_admin_csf_save($submitted, $instance);
zsr_admin_assert($saved['zsr_widget_locked'] === array() && get_option('zsr_widget_locked') === array(), 'unchecking everything clears canonical option and mirror');
zsr_admin_assert(!isset($GLOBALS['zsr_widget_sync_feedback']) && $instance->notice === 'Settings saved.', 'success clears stale sync feedback');

$defaults = array();
foreach ($zsr_admin_sections as $section) {
    foreach ($section['fields'] as $field) {
        if (!empty($field['id'])) {
            $defaults[$field['id']] = isset($field['default']) ? $field['default'] : '';
        }
    }
}
$instance = (object) array('notice' => '', 'errors' => array());
$saved = zsr_admin_csf_save($defaults, $instance);
foreach (array_keys($fields) as $key) { zsr_admin_assert($saved[$key] === zsr_default_options()[$key], 'CSF default normalizes ' . $key); }
zsr_admin_assert(get_option('zsr_widget_locked') === array(), 'default save leaves no independent locks');
$zsr_admin_options['zsr_widget_locked'] = array('old_locked' => '1');
zsr_invalidate_widget_cache();
do_action('csf_zsr_options_saved', $defaults, $instance);
zsr_admin_assert(get_option('zsr_widget_locked') === array('old_locked' => '1'), 'CSF saved-only default hook cannot erase canonical locks');
zsr_reconcile_widget_options();
zsr_admin_assert(get_option(ZSR_OPTION)['zsr_widget_locked'] === array('old_locked'), 'pre-CSF reconciliation restores canonical choices');

$submitted = array_replace(get_option(ZSR_OPTION), array('zsr_widget_locked' => array('native_direct'), 'zsr_widget_exclude' => array()));
$saved = zsr_save_options($submitted);
zsr_admin_assert(get_option('zsr_widget_locked') === array('native_direct' => '1') && get_option(ZSR_OPTION) === $saved, 'direct options save synchronizes canonical map and mirror');
$zsr_admin_fail = array('zsr_widget_locked');
$submitted['zsr_widget_locked'] = array('rejected_direct');
$saved = zsr_save_options($submitted);
zsr_admin_assert($saved['zsr_widget_locked'] === array('native_direct') && count($zsr_admin_errors) === 1, 'direct save failure preserves canonical state and emits native error');
$_GET['page'] = 'zsr_options';
ob_start();
zsr_widget_sync_notice();
$notice = ob_get_clean();
zsr_admin_assert(strpos($notice, 'notice-error') !== false, 'admin notice displays the synchronization failure');

fwrite(STDOUT, 'widget-admin tests passed (' . $zsr_admin_assertions . " assertions)\n");
