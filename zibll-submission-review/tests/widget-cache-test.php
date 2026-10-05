<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

define('ABSPATH', __DIR__ . '/');
define('ZSR_OPTION', 'zsr_options');
define('ZSR_VERSION', '0.1.0');
$cache_mode = isset($argv[1]) ? $argv[1] : '';
if ($cache_mode === '--file-guard') {
    $temporary = DIRECTORY_SEPARATOR === '\\' ? 'D:/kfhj/13-包缓存/php' : sys_get_temp_dir();
    if (!is_dir($temporary)) {
        throw new RuntimeException('Expected D-drive PHP test directory is missing.');
    }
    $marker = tempnam($temporary, 'zsr-widget-cache-');
    if ($marker === false || realpath(dirname($marker)) !== realpath($temporary)) {
        throw new RuntimeException('Unable to create a D-drive plugin marker fixture.');
    }
    define('ZSR_FILE', $marker);
} else {
    define('ZSR_FILE', dirname(__DIR__) . '/zibll-submission-review.php');
}

$cache_sites = array(1 => array(), 2 => array());
$cache_site = 1;
$cache_stack = array();
$cache_hooks = array();
$cache_reads = array();
$cache_failed = array();
$cache_logged = false;
$cache_admin = false;
$cache_manage = false;
$cache_assertions = 0;
$cache_logs = array();

function add_action($hook, $callback, $priority = 10, $accepted_args = 1)
{
    $GLOBALS['cache_hooks'][$hook][$priority][] = array($callback, $accepted_args);
}

function do_action($hook, ...$args)
{
    $priorities = isset($GLOBALS['cache_hooks'][$hook]) ? $GLOBALS['cache_hooks'][$hook] : array();
    ksort($priorities);
    foreach ($priorities as $callbacks) {
        foreach ($callbacks as $callback) {
            call_user_func_array($callback[0], array_slice($args, 0, $callback[1]));
        }
    }
}

function get_current_blog_id() { return $GLOBALS['cache_site']; }
function is_admin() { return $GLOBALS['cache_admin']; }
function is_user_logged_in() { return $GLOBALS['cache_logged']; }
function current_user_can($capability) { return $capability === 'manage_options' && $GLOBALS['cache_manage']; }
function zsr_log($level, $event, $context = array()) { $GLOBALS['cache_logs'][] = array($level, $event, $context); }

function get_option($key, $fallback = false)
{
    $site = get_current_blog_id();
    if (!isset($GLOBALS['cache_reads'][$site][$key])) {
        $GLOBALS['cache_reads'][$site][$key] = 0;
    }
    $GLOBALS['cache_reads'][$site][$key]++;
    return array_key_exists($key, $GLOBALS['cache_sites'][$site]) ? $GLOBALS['cache_sites'][$site][$key] : $fallback;
}

function add_option($key, $value, $deprecated = '', $autoload = null)
{
    $site = get_current_blog_id();
    if (array_key_exists($key, $GLOBALS['cache_sites'][$site]) || in_array($key, $GLOBALS['cache_failed'], true)) {
        return false;
    }
    do_action('add_option', $key, $value);
    $GLOBALS['cache_sites'][$site][$key] = $value;
    do_action('add_option_' . $key, $key, $value);
    do_action('added_option', $key, $value);
    return true;
}

function update_option($key, $value, $autoload = null)
{
    $site = get_current_blog_id();
    if (!array_key_exists($key, $GLOBALS['cache_sites'][$site])) {
        return add_option($key, $value, '', $autoload);
    }
    $previous = $GLOBALS['cache_sites'][$site][$key];
    if ($previous === $value || in_array($key, $GLOBALS['cache_failed'], true)) {
        return false;
    }
    do_action('update_option', $key, $previous, $value);
    $GLOBALS['cache_sites'][$site][$key] = $value;
    do_action('update_option_' . $key, $previous, $value, $key);
    do_action('updated_option', $key, $previous, $value);
    return true;
}

function delete_option($key)
{
    $site = get_current_blog_id();
    if (!array_key_exists($key, $GLOBALS['cache_sites'][$site])) {
        return false;
    }
    do_action('delete_option', $key);
    if (in_array($key, $GLOBALS['cache_failed'], true)) {
        return false;
    }
    unset($GLOBALS['cache_sites'][$site][$key]);
    do_action('delete_option_' . $key, $key);
    do_action('deleted_option', $key);
    return true;
}

function switch_to_blog($site)
{
    $previous = get_current_blog_id();
    $GLOBALS['cache_stack'][] = $previous;
    $GLOBALS['cache_site'] = $site;
    do_action('switch_blog', $site, $previous, 'switch');
}

function restore_current_blog()
{
    $previous = get_current_blog_id();
    $GLOBALS['cache_site'] = array_pop($GLOBALS['cache_stack']);
    do_action('switch_blog', get_current_blog_id(), $previous, 'restore');
}

function zsr_cache_assert($condition, $message)
{
    $GLOBALS['cache_assertions']++;
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $message);
    }
}

require_once dirname(__DIR__) . '/inc/core/options.php';
require_once dirname(__DIR__) . '/inc/widget/sync.php';
require_once dirname(__DIR__) . '/inc/widget/locker.php';

if ($cache_mode === '--file-guard') {
    try {
        add_option(ZSR_OPTION, array('zsr_widget_enable' => true));
        add_option('zsr_widget_locked', array('guarded' => '1'));
        zsr_cache_assert(zsr_widget_should_lock('guarded'), 'existing plugin marker permits locking');
        zsr_cache_assert(rename($marker, $marker . '.moved'), 'test plugin marker moves within its temporary directory');
        clearstatcache(true, $marker);
        zsr_cache_assert(!zsr_widget_should_lock('guarded'), 'cached settings never mask a deleted plugin marker');
        zsr_cache_assert(rename($marker . '.moved', $marker), 'test plugin marker can be restored');
        clearstatcache(true, $marker);
        zsr_cache_assert(zsr_widget_should_lock('guarded'), 'restored plugin marker is evaluated again');
        fwrite(STDOUT, "widget dynamic file guard passed\n");
    } finally {
        foreach (array($marker, $marker . '.moved') as $path) {
            if (is_file($path)) { unlink($path); }
        }
    }
    exit(0);
}

zsr_register_widget_gates();
for ($index = 0; $index < 5000; $index++) {
    zsr_cache_assert(!zsr_widget_should_lock('locked_' . $index), 'default-disabled gate does not lock');
}
zsr_cache_assert($cache_reads === array(1 => array(ZSR_OPTION => 1)), 'disabled gate reads settings once and never reads the lock table');
foreach (array_keys($cache_hooks) as $hook) {
    zsr_cache_assert(strpos($hook, 'widget_is_show_') !== 0 && $hook !== 'widget_display_callback', 'disabled gate installs no rendering filter');
}

$options = array('zsr_widget_enable' => true, 'zsr_widget_visitor_action' => 'hidden', 'zsr_widget_locked' => array('mirror_only'), 'zsr_widget_exclude' => array());
$locked = array();
for ($index = 0; $index < 5000; $index++) {
    $locked['locked_' . $index] = '1';
    if ($index % 4 === 0) {
        $options['zsr_widget_exclude'][] = 'locked_' . $index;
    }
}
$locked['widget_ui_search'] = '1';
zsr_cache_assert(add_option(ZSR_OPTION, $options), 'settings addition succeeds');
zsr_cache_assert(!zsr_widget_should_lock('locked_1'), 'enabled gate observes absent independent lock option');
zsr_cache_assert(add_option('zsr_widget_locked', $locked), 'lock table addition succeeds');
$cache_reads = array();
zsr_invalidate_widget_cache();
$started = microtime(true);
for ($index = 0; $index < 5000; $index++) {
    zsr_cache_assert(zsr_widget_should_lock('locked_' . $index) === ($index % 4 !== 0), 'large lock and exclusion maps match expected access');
}
$cold_ms = (microtime(true) - $started) * 1000;
zsr_cache_assert($cache_reads === array(1 => array(ZSR_OPTION => 1, 'zsr_widget_locked' => 1)), '5000 decisions perform one settings read and one lock-table read');
$started = microtime(true);
for ($index = 0; $index < 5000; $index++) {
    zsr_cache_assert(zsr_widget_should_lock('locked_' . $index) === ($index % 4 !== 0), 'warm decisions keep the same result');
}
$warm_ms = (microtime(true) - $started) * 1000;
zsr_cache_assert($cache_reads === array(1 => array(ZSR_OPTION => 1, 'zsr_widget_locked' => 1)), 'warm decisions do not reread or rebuild settings');
ob_start();
for ($index = 0; $index < 5000; $index++) {
    zsr_render_locked_widget(array('widget_id' => 'locked_' . $index), array());
}
zsr_cache_assert(ob_get_clean() === '' && $cache_logs === array(), 'hidden rendering with diagnostics disabled does no logging work');
zsr_cache_assert($cache_reads === array(1 => array(ZSR_OPTION => 1, 'zsr_widget_locked' => 1)), '5000 hidden renders share cached presentation settings');
zsr_cache_assert(!zsr_widget_should_lock('mirror_only') && !zsr_widget_should_lock('widget_ui_search'), 'cached map preserves independent authority and forced exclusions');

$reads = $cache_reads;
zsr_cache_assert(!update_option(ZSR_OPTION, $options) && !update_option('zsr_widget_locked', $locked), 'unchanged saves return false');
zsr_cache_assert(zsr_widget_should_lock('locked_1') && $cache_reads === $reads, 'unchanged saves preserve warm cache');
$cache_failed = array(ZSR_OPTION, 'zsr_widget_locked');
zsr_cache_assert(!update_option(ZSR_OPTION, array('zsr_widget_enable' => false)) && !delete_option(ZSR_OPTION), 'settings update and delete failures are simulated');
zsr_cache_assert(!update_option('zsr_widget_locked', array()) && !delete_option('zsr_widget_locked'), 'independent update and delete failures are simulated');
zsr_cache_assert(zsr_widget_should_lock('locked_1') && $cache_reads === $reads, 'failed writes preserve old cached decisions without extra reads');
$cache_failed = array();

$seen_before_save = null;
$seen_after_save = null;
add_action('update_option', function ($key) use (&$seen_before_save) {
    if ($key === 'zsr_widget_locked') { $seen_before_save = zsr_widget_should_lock('replacement'); }
});
add_action('update_option_zsr_widget_locked', function () use (&$seen_after_save) {
    $seen_after_save = zsr_widget_should_lock('replacement');
}, 1, 0);
zsr_cache_assert(update_option('zsr_widget_locked', array('replacement' => '1')), 'independent option updates successfully');
zsr_cache_assert($seen_before_save === false && $seen_after_save === true, 'cache only invalidates after persistence and before subsequent save observers');
zsr_cache_assert(zsr_widget_should_lock('replacement') && !zsr_widget_should_lock('locked_1'), 'successful independent update takes effect immediately');
$options['zsr_widget_exclude'] = array('replacement');
zsr_cache_assert(update_option(ZSR_OPTION, $options) && !zsr_widget_should_lock('replacement'), 'successful settings update refreshes exclusion lookup');
$options['zsr_widget_exclude'] = array();
update_option(ZSR_OPTION, $options);
zsr_cache_assert(zsr_widget_should_lock('replacement'), 'removing an exclusion immediately restores locking');

$cache_logged = true;
zsr_cache_assert(!zsr_widget_should_lock('replacement'), 'login identity is not cached');
$cache_logged = false;
zsr_cache_assert(zsr_widget_should_lock('replacement'), 'logout identity is not cached');
$cache_admin = true;
zsr_cache_assert(!zsr_widget_should_lock('replacement'), 'admin request state is not cached');
$cache_admin = false;
$cache_manage = true;
zsr_cache_assert(!zsr_widget_should_lock('replacement'), 'current permission is not cached');
$options['zsr_widget_admin_bypass'] = false;
update_option(ZSR_OPTION, $options);
zsr_cache_assert(zsr_widget_should_lock('replacement'), 'administrator bypass update applies within request');
$cache_manage = false;
$options['zsr_widget_enable'] = false;
update_option(ZSR_OPTION, $options);
$reads = $cache_reads[1]['zsr_widget_locked'];
zsr_cache_assert(!zsr_widget_should_lock('replacement') && $cache_reads[1]['zsr_widget_locked'] === $reads, 'disable applies immediately without reading the lock table');
$options['zsr_widget_enable'] = true;
update_option(ZSR_OPTION, $options);
zsr_cache_assert(zsr_widget_should_lock('replacement'), 're-enable applies immediately');

switch_to_blog(2);
zsr_cache_assert(!zsr_widget_should_lock('replacement'), 'new site uses its own disabled defaults');
add_option(ZSR_OPTION, array('zsr_widget_enable' => true));
zsr_cache_assert(!zsr_widget_should_lock('site_two'), 'new site does not borrow the first site lock map');
add_option('zsr_widget_locked', array('site_two' => '1'));
zsr_cache_assert(zsr_widget_should_lock('site_two') && !zsr_widget_should_lock('replacement'), 'new site caches its own lock map');
restore_current_blog();
zsr_cache_assert(zsr_widget_should_lock('replacement') && !zsr_widget_should_lock('site_two'), 'restore switches to the first site cache');
switch_to_blog(2);
update_option('zsr_widget_locked', array('updated_two' => '1'));
zsr_cache_assert(zsr_widget_should_lock('updated_two') && !zsr_widget_should_lock('site_two'), 'switched site invalidation targets the active blog');
restore_current_blog();
zsr_cache_assert(zsr_widget_should_lock('replacement'), 'other blog mutation never invalidates the first site decisions');

zsr_cache_assert(delete_option('zsr_widget_locked') && !zsr_widget_should_lock('replacement'), 'successful independent delete clears cached locks');
$cache_failed = array('zsr_widget_locked');
zsr_cache_assert(!add_option('zsr_widget_locked', array('new_lock' => '1')) && !zsr_widget_should_lock('new_lock'), 'failed add keeps empty cached locks');
$cache_failed = array();
add_option('zsr_widget_locked', array('new_lock' => '1'));
zsr_cache_assert(zsr_widget_should_lock('new_lock'), 'successful add invalidates cached absence');
zsr_cache_assert(delete_option(ZSR_OPTION) && !zsr_widget_should_lock('new_lock'), 'settings deletion restores disabled defaults');
$cache_failed = array(ZSR_OPTION);
zsr_cache_assert(!add_option(ZSR_OPTION, $options) && !zsr_widget_should_lock('new_lock'), 'failed settings add leaves disabled defaults');
$cache_failed = array();
add_option(ZSR_OPTION, $options);
zsr_cache_assert(zsr_widget_should_lock('new_lock'), 'settings addition replaces cached defaults');

$saved = zsr_sync_widget_options(array_replace($options, array('zsr_widget_locked' => array('synced'))));
zsr_cache_assert(zsr_widget_should_lock('synced') && !zsr_widget_should_lock('new_lock'), 'internal synchronization invalidates cached locks');
$cache_failed = array('zsr_widget_locked');
$saved = zsr_sync_widget_options(array_replace($options, array('zsr_widget_locked' => array('failed_sync'))));
zsr_cache_assert($saved['zsr_widget_locked'] === array('synced') && zsr_widget_should_lock('synced') && !zsr_widget_should_lock('failed_sync'), 'failed internal synchronization preserves authoritative cached map');
$cache_failed = array();
$cache_admin = true;
$cache_manage = true;
$cached_options = zsr_get_widget_options();
zsr_cache_assert($cached_options['zsr_widget_locked'] === array('mirror_only'), 'stale mirror is visible before repair');
zsr_cache_assert(zsr_reconcile_widget_options(), 'admin reconciliation succeeds');
zsr_cache_assert(zsr_get_widget_options()['zsr_widget_locked'] === array('synced'), 'admin reconciliation invalidates the settings cache');
$cache_admin = false;
$cache_manage = false;
zsr_cache_assert(zsr_widget_should_lock('synced'), 'admin reconciliation preserves runtime decisions');

$options['zsr_log_enable'] = true;
$options['zsr_log_level'] = 'info';
update_option(ZSR_OPTION, $options);
$logs = count($cache_logs);
zsr_render_locked_widget(array('widget_id' => 'synced'), array());
zsr_cache_assert(count($cache_logs) === $logs, 'non-debug diagnostics skip per-widget logger work');
$options['zsr_log_level'] = 'debug';
update_option(ZSR_OPTION, $options);
zsr_render_locked_widget(array('widget_id' => 'synced'), array());
zsr_cache_assert(count($cache_logs) === $logs + 1 && $cache_logs[$logs][1] === 'widget.blocked', 'enabling debug logging in the same request still emits widget details');

$command = array(PHP_BINARY, __FILE__, '--file-guard');
if (PHP_VERSION_ID < 70400) {
    $command = implode(' ', array_map('escapeshellarg', $command));
}
$process = proc_open($command, array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
zsr_cache_assert(is_resource($process), 'dynamic file-guard subprocess starts');
fclose($pipes[0]);
$output = stream_get_contents($pipes[1]);
$errors = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$status = proc_close($process);
zsr_cache_assert($status === 0 && $errors === '' && strpos($output, 'widget dynamic file guard passed') !== false, 'dynamic plugin marker remains uncached: ' . $errors);

fwrite(STDOUT, 'widget cache tests passed (' . $cache_assertions . " assertions)\n");
fwrite(STDOUT, sprintf("Synthetic PHP benchmark only: 5000 decisions, cold %.3f ms, warm %.3f ms; settings reads=1, independent lock reads=1 across both passes; not WordPress database/browser/TTFB evidence.\n", $cold_ms, $warm_ms));
