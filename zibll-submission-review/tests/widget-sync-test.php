<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/i18n-helpers.php';

define('ABSPATH', __DIR__ . '/');
define('ZSR_OPTION', 'zsr_options');

$zsr_sync_options = array();
$zsr_sync_reads = 0;
$zsr_sync_fail = array();
$zsr_sync_writes = array();
$zsr_sync_logs = array();
$zsr_sync_admin = true;
$zsr_sync_admin_page = true;
$zsr_sync_assertions = 0;
$zsr_sync_throw_log = false;
$zsr_sync_hooks = array();

function add_action($hook, $callback, $priority = 10, $accepted_args = 1)
{
    $GLOBALS['zsr_sync_hooks'][$hook][$priority][] = array($callback, $accepted_args);
}

function do_action($hook, ...$args)
{
    $priorities = isset($GLOBALS['zsr_sync_hooks'][$hook]) ? $GLOBALS['zsr_sync_hooks'][$hook] : array();
    ksort($priorities);
    foreach ($priorities as $callbacks) {
        foreach ($callbacks as $callback) {
            call_user_func_array($callback[0], array_slice($args, 0, $callback[1]));
        }
    }
}

function get_option($key, $default = false)
{
    global $zsr_sync_options;
    $GLOBALS['zsr_sync_reads']++;
    return array_key_exists($key, $zsr_sync_options) ? $zsr_sync_options[$key] : $default;
}

function is_admin()
{
    return $GLOBALS['zsr_sync_admin_page'];
}

function update_option($key, $value, $autoload = null)
{
    global $zsr_sync_options, $zsr_sync_fail, $zsr_sync_writes;
    $zsr_sync_writes[] = array($key, $value, $autoload);
    if (in_array($key, $zsr_sync_fail, true) || (array_key_exists($key, $zsr_sync_options) && $zsr_sync_options[$key] === $value)) {
        return false;
    }
    $exists = array_key_exists($key, $zsr_sync_options);
    $previous = $exists ? $zsr_sync_options[$key] : null;
    $zsr_sync_options[$key] = $value;
    do_action(($exists ? 'update_option_' : 'add_option_') . $key, $exists ? $previous : $key, $value, $key);
    return true;
}

function current_user_can($capability)
{
    return $capability === 'manage_options' && $GLOBALS['zsr_sync_admin'];
}

function esc_html($text)
{
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

function zsr_log($level, $event, $context)
{
    $GLOBALS['zsr_sync_logs'][] = array($level, $event, $context);
    if ($GLOBALS['zsr_sync_throw_log']) {
        throw new RuntimeException('Test logger failure');
    }
}

function zsr_sync_assert($condition, $message)
{
    $GLOBALS['zsr_sync_assertions']++;
    if (!$condition) {
        fwrite(STDERR, 'FAIL: ' . $message . "\n");
        exit(1);
    }
}

require_once dirname(__DIR__) . '/inc/core/options.php';
require_once dirname(__DIR__) . '/inc/widget/sync.php';

$ids = array('text', 'custom-widget', 'zib_widget_posts', 'text');
zsr_sync_assert(zsr_normalize_widget_ids($ids) === array('text', 'custom-widget', 'zib_widget_posts'), 'checkbox lists are deduplicated in input order');
zsr_sync_assert(zsr_normalize_widget_ids(array('text' => '1', 'custom-widget' => true, 'zib_widget_posts' => 1)) === array('text', 'custom-widget', 'zib_widget_posts'), 'canonical maps are accepted');
foreach (array(null, false, true, 'text', 1, new stdClass()) as $input) {
    zsr_sync_assert(zsr_normalize_widget_ids($input) === array(), 'non-array inputs rejected');
}
zsr_sync_assert(zsr_normalize_widget_ids(array('text' => false, 'off' => '0', 'string_true' => 'true', 'nested' => array('text'), 'object' => new stdClass(), '<script>' => true, 'text<script>', 'UPPER', 'with space', '', 15, true, array('text'), new stdClass())) === array(), 'malformed maps, HTML and nested inputs rejected');
zsr_sync_assert(zsr_normalize_widget_ids(array('first', 'second' => '1', 'first', 'third' => true)) === array('first', 'second', 'third'), 'mixed list and map values normalize safely');
zsr_sync_assert(zsr_normalize_widget_ids(array('123', 456 => '1')) === array(), 'numeric-only ids cannot be confused with checkbox list indexes');

$zsr_sync_options[ZSR_OPTION] = array('zsr_widget_locked' => array('mirror_only'));
zsr_sync_assert(zsr_get_locked_widgets() === array(), 'runtime never falls back to the settings mirror');
$zsr_sync_options['zsr_widget_locked'] = array('source_only' => true, 'widget_ui_user' => '1', 'zib_widget_ui_search' => '1');
zsr_invalidate_widget_cache();
zsr_sync_assert(zsr_get_locked_widgets() === array('source_only' => '1'), 'runtime authority is independent and login/search types are always excluded');

$input = array('zsr_enable' => true, 'zsr_widget_locked' => array('text', 'module_temporarily_off', 'widget_ui_user', 'widget_ui_search', 'zib_widget_ui_user', 'zib_widget_ui_search', 'excluded'), 'zsr_widget_exclude' => array('excluded' => true));
$saved = zsr_sync_widget_options($input);
zsr_sync_assert($saved['zsr_widget_locked'] === array('text', 'module_temporarily_off'), 'save drops forced and user exclusions but does not require currently registered types');
zsr_sync_assert($saved['zsr_widget_exclude'] === array('excluded'), 'saved exclusions use CSF checkbox list format');
zsr_sync_assert($saved['zsr_enable'] === true, 'unrelated submitted options remain unchanged');
zsr_sync_assert(get_option('zsr_widget_locked') === array('text' => '1', 'module_temporarily_off' => '1'), 'independent option stores canonical map');
zsr_sync_assert($zsr_sync_writes[count($zsr_sync_writes) - 1][2] === false, 'independent configuration does not require autoload');
zsr_sync_assert(zsr_sync_widget_options($saved) === $saved, 'unchanged update_option false is successful');
zsr_sync_assert($zsr_sync_logs === array(), 'unchanged save does not emit a false error');

$zsr_sync_options['zibll_options'] = array('widget_locked' => array('foreign'), 'unrelated' => 'preserved');
$zsr_sync_options['zibll_options'] = array();
zsr_sync_assert(zsr_get_locked_widgets() === array('text' => '1', 'module_temporarily_off' => '1'), 'theme reset does not reset plugin widget locks');
zsr_sync_assert(zsr_sync_widget_options(array('zsr_widget_locked' => array(), 'zsr_widget_exclude' => array()))['zsr_widget_locked'] === array(), 'explicit empty selection clears locks');
zsr_sync_assert(get_option('zsr_widget_locked') === array(), 'empty selection clears the independent map');

$zsr_sync_options['zsr_widget_locked'] = array('old' => '1');
$zsr_sync_options[ZSR_OPTION] = array('zsr_widget_enable' => true, 'zsr_widget_locked' => array('stale'), 'zsr_widget_visitor_action' => 'hidden', 'zsr_widget_admin_bypass' => false, 'zsr_widget_hide_title' => false, 'zsr_widget_exclude' => array('old_exclusion'), 'zsr_menu_label' => '原投稿菜单');
zsr_invalidate_widget_cache();
$zsr_sync_fail = array('zsr_widget_locked');
$zsr_sync_logs = array();
$saved = zsr_sync_widget_options(array('zsr_widget_enable' => false, 'zsr_widget_locked' => array('new'), 'zsr_widget_visitor_action' => 'upgrade', 'zsr_widget_admin_bypass' => true, 'zsr_widget_hide_title' => true, 'zsr_widget_exclude' => array('new_exclusion'), 'zsr_menu_label' => '新投稿菜单'));
zsr_sync_assert($saved['zsr_widget_locked'] === array('old'), 'failed write keeps authoritative old selection in returned mirror');
foreach (array('zsr_widget_enable', 'zsr_widget_visitor_action', 'zsr_widget_admin_bypass', 'zsr_widget_hide_title', 'zsr_widget_exclude') as $key) {
    zsr_sync_assert($saved[$key] === $zsr_sync_options[ZSR_OPTION][$key], 'failed independent save restores previous widget field ' . $key);
}
zsr_sync_assert($saved['zsr_menu_label'] === '新投稿菜单', 'failed widget save still permits unrelated submission settings to change');
zsr_sync_assert(get_option('zsr_widget_locked') === array('old' => '1'), 'failed write preserves independent configuration');
zsr_sync_assert($zsr_sync_logs[0][0] === 'error' && $zsr_sync_logs[0][1] === 'widget.options_sync_failed', 'write failure logs a structured error');
zsr_sync_assert(strpos(json_encode($zsr_sync_logs), 'new') === false, 'failure log records counts instead of submitted raw values');
$zsr_sync_throw_log = true;
zsr_sync_assert(zsr_sync_widget_options(array('zsr_widget_locked' => array('new')))['zsr_widget_locked'] === array('old'), 'logger exception does not break safe save fallback');
$zsr_sync_throw_log = false;
$zsr_sync_options[ZSR_OPTION] = array();
$saved = zsr_sync_widget_options(array('zsr_widget_enable' => true, 'zsr_widget_locked' => array('new'), 'zsr_widget_visitor_action' => 'hidden', 'zsr_widget_admin_bypass' => false, 'zsr_widget_hide_title' => false, 'zsr_widget_exclude' => array('new_exclusion')));
$defaults = zsr_default_options();
foreach (array('zsr_widget_enable', 'zsr_widget_visitor_action', 'zsr_widget_admin_bypass', 'zsr_widget_hide_title', 'zsr_widget_exclude') as $key) {
    zsr_sync_assert($saved[$key] === $defaults[$key], 'failed write restores missing widget field from defaults: ' . $key);
}
zsr_sync_assert($saved['zsr_widget_locked'] === array('old'), 'default rollback still uses independent lock authority');

$_GET['page'] = 'zsr_options';
ob_start();
zsr_widget_sync_notice();
$notice = ob_get_clean();
zsr_sync_assert(strpos($notice, 'notice-error') !== false, 'admin settings page receives save failure notice');
$_GET['page'] = 'other_settings';
ob_start();
zsr_widget_sync_notice();
zsr_sync_assert(ob_get_clean() === '', 'notice does not leak onto unrelated admin pages');
$_GET['page'] = 'zsr_options';
$zsr_sync_admin = false;
ob_start();
zsr_widget_sync_notice();
zsr_sync_assert(ob_get_clean() === '', 'notice is only visible to manage_options users');
$writes = count($zsr_sync_writes);
zsr_sync_assert(zsr_reconcile_widget_options() === false && count($zsr_sync_writes) === $writes, 'non-administrator cannot reconcile settings');
$zsr_sync_admin = true;
$zsr_sync_fail = array();
$zsr_sync_admin_page = false;
$reads = $zsr_sync_reads;
$writes = count($zsr_sync_writes);
zsr_sync_assert(zsr_reconcile_widget_options() === false, 'administrator frontend requests do not reconcile settings');
zsr_sync_assert($zsr_sync_reads === $reads && count($zsr_sync_writes) === $writes, 'frontend reconciliation guard performs no option reads or writes');
$zsr_sync_admin_page = true;

$zsr_sync_options = array(ZSR_OPTION => array('zsr_widget_locked' => array('first', 'excluded', 'widget_ui_search'), 'zsr_widget_exclude' => array('excluded'), 'unrelated' => 'preserved'), 'zibll_options' => array('unrelated' => 'theme'));
zsr_invalidate_widget_cache();
$zsr_sync_logs = array();
zsr_sync_assert(zsr_reconcile_widget_options() === true, 'initial mirror migrates successfully');
zsr_sync_assert(get_option('zsr_widget_locked') === array('first' => '1'), 'first migration excludes login/search and explicit exclusions');
zsr_sync_assert(get_option(ZSR_OPTION)['zsr_widget_locked'] === array('first'), 'migration normalizes mirror');
zsr_sync_assert(get_option(ZSR_OPTION)['unrelated'] === 'preserved', 'migration preserves unrelated settings');
zsr_sync_assert(get_option('zibll_options') === array('unrelated' => 'theme'), 'migration does not modify theme options');
zsr_sync_assert($zsr_sync_logs[0][1] === 'widget.options_migrated', 'migration emits diagnostic event');

$zsr_sync_options[ZSR_OPTION]['zsr_widget_locked'] = array('stale_mirror');
$zsr_sync_logs = array();
zsr_sync_assert(zsr_reconcile_widget_options() === true, 'independent configuration reconciles stale mirror');
zsr_sync_assert(get_option('zsr_widget_locked') === array('first' => '1'), 'reconcile never trusts stale mirror over independent option');
zsr_sync_assert(get_option(ZSR_OPTION)['zsr_widget_locked'] === array('first'), 'mirror restored from independent option');
zsr_sync_assert($zsr_sync_logs[0][1] === 'widget.options_reconciled', 'reconcile emits diagnostic event');
ob_start();
zsr_widget_sync_notice();
zsr_sync_assert(strpos(ob_get_clean(), 'notice-warning') !== false, 'reconcile displays repair notice to administrator');
$writes = count($zsr_sync_writes);
zsr_sync_assert(zsr_reconcile_widget_options() === true && count($zsr_sync_writes) === $writes, 'aligned configuration does not cause needless writes');

$zsr_sync_options['zsr_widget_locked'] = array();
zsr_invalidate_widget_cache();
$zsr_sync_options[ZSR_OPTION]['zsr_widget_locked'] = array('stale_mirror');
zsr_sync_assert(zsr_reconcile_widget_options() === true && get_option(ZSR_OPTION)['zsr_widget_locked'] === array(), 'existing empty independent option is authoritative, not treated as missing');

$zsr_sync_options['zsr_widget_locked'] = array('source' => '1');
zsr_invalidate_widget_cache();
$zsr_sync_options[ZSR_OPTION]['zsr_widget_locked'] = array('stale');
$zsr_sync_fail = array(ZSR_OPTION);
zsr_sync_assert(zsr_reconcile_widget_options() === false, 'failed mirror correction is reported');
zsr_sync_assert(zsr_get_locked_widgets() === array('source' => '1'), 'failed mirror correction does not weaken runtime authority');
zsr_sync_assert($GLOBALS['zsr_widget_sync_feedback']['level'] === 'error', 'failed mirror correction leaves error notice');

$zsr_sync_options = array(ZSR_OPTION => array('zsr_widget_locked' => array('legacy')));
zsr_invalidate_widget_cache();
$zsr_sync_fail = array('zsr_widget_locked');
zsr_sync_assert(zsr_reconcile_widget_options() === false, 'failed initial migration is reported');
zsr_sync_assert(get_option(ZSR_OPTION)['zsr_widget_locked'] === array('legacy'), 'failed initial migration does not erase legacy mirror');
zsr_sync_assert(zsr_get_locked_widgets() === array(), 'failed initial migration never creates runtime mirror fallback');

$zsr_sync_options = array();
$zsr_sync_fail = array();
$writes = count($zsr_sync_writes);
zsr_sync_assert(zsr_reconcile_widget_options() === true && count($zsr_sync_writes) === $writes, 'no legacy mirror means no unrequested migration writes');
foreach ($zsr_sync_writes as $write) {
    zsr_sync_assert(in_array($write[0], array('zsr_widget_locked', ZSR_OPTION), true), 'all synchronization writes are restricted to plugin options');
}

fwrite(STDOUT, 'widget-sync tests passed (' . $zsr_sync_assertions . " assertions)\n");
