<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

define('ABSPATH', __DIR__ . '/');
define('ZSR_OPTION', 'zsr_options');

$viewer_options = array('zsr_log_enable' => true, 'zsr_log_level' => 'info');
$viewer_store = array();
$viewer_hooks = array();
$viewer_role = 'administrator';

class ZSR_Viewer_Test_Database
{
    public $options = 'wp_options';
    public $competing_record = null;

    public function prepare($sql, ...$args)
    {
        return array($sql, $args);
    }

    public function get_var($query)
    {
        return isset($GLOBALS['viewer_store'][$query[1][0]]) ? $GLOBALS['viewer_store'][$query[1][0]] : null;
    }

    public function query($query)
    {
        if (strpos($query[0], 'INSERT IGNORE') === 0) {
            list($name, $value) = $query[1];
            if (isset($GLOBALS['viewer_store'][$name])) {
                return 0;
            }
            $GLOBALS['viewer_store'][$name] = $value;
            return 1;
        }
        list($value, $name, $previous) = $query[1];
        if ($this->competing_record !== null) {
            $records = json_decode($GLOBALS['viewer_store'][$name], true);
            $records[] = $this->competing_record;
            $GLOBALS['viewer_store'][$name] = json_encode($records);
            $this->competing_record = null;
            return 0;
        }
        if (!isset($GLOBALS['viewer_store'][$name]) || $GLOBALS['viewer_store'][$name] !== $previous) {
            return 0;
        }
        $GLOBALS['viewer_store'][$name] = $value;
        return 1;
    }
}

$wpdb = new ZSR_Viewer_Test_Database();

function get_option($name, $default = false)
{
    if ($name === ZSR_OPTION) {
        return $GLOBALS['viewer_options'];
    }
    return isset($GLOBALS['viewer_store'][$name]) ? $GLOBALS['viewer_store'][$name] : $default;
}

function delete_option($name)
{
    unset($GLOBALS['viewer_store'][$name]);
    return true;
}

function add_action($hook, $callback, $priority = 10)
{
    $GLOBALS['viewer_hooks'][$hook][$callback] = $callback;
}

function do_action($hook)
{
    foreach (isset($GLOBALS['viewer_hooks'][$hook]) ? $GLOBALS['viewer_hooks'][$hook] : array() as $callback) {
        call_user_func($callback);
    }
}

function current_user_can($capability) { return $GLOBALS['viewer_role'] === 'administrator' && $capability === 'manage_options'; }
function current_time($format) { return '2026-10-06 12:00:00'; }
function wp_generate_uuid4() { return 'viewer-request-id'; }
function get_current_blog_id() { return 1; }
function wp_cache_delete($key, $group) { return true; }
function __($text, $domain = '') { return $text; }
function esc_html($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }

require_once dirname(__DIR__) . '/inc/core/options.php';
require_once dirname(__DIR__) . '/inc/core/logger.php';
if (is_file(dirname(__DIR__) . '/inc/admin/log-viewer.php')) {
    require_once dirname(__DIR__) . '/inc/admin/log-viewer.php';
}

function viewer_assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function viewer_output()
{
    ob_start();
    zsr_render_admin_logs();
    return ob_get_clean();
}

$log_file = tempnam(sys_get_temp_dir(), 'zsr-viewer-');
ini_set('error_log', $log_file);
register_shutdown_function(function () use ($log_file) { @unlink($log_file); });

zsr_log('info', 'review.success', array('post_id' => 101, 'user_id' => 12));
do_action('shutdown');
viewer_assert(function_exists('zsr_render_admin_logs'), 'plugin settings exposes the diagnostic log viewer');
$output = viewer_output();
viewer_assert(strpos($output, 'review.success') !== false && strpos($output, '101') !== false, 'administrator can read recorded plugin activity in settings');

$viewer_role = 'guest';
viewer_assert(viewer_output() === '', 'anonymous visitors cannot read diagnostic records');
$viewer_role = 'author';
viewer_assert(viewer_output() === '', 'non-admin WordPress users cannot read diagnostic records');
$viewer_role = 'administrator';

zsr_log('warning', 'viewer.untrusted', array(
    'detail' => '<img src=x onerror=alert(1)>',
    'token' => 'token-SENTINEL',
    'nested' => array('password' => 'password-SENTINEL', 'email' => 'email-SENTINEL'),
    'post_content' => 'content-SENTINEL',
));
$output = viewer_output();
viewer_assert(strpos($output, '<img') === false && strpos($output, '&lt;img') !== false, 'record values are escaped rather than executable HTML');
viewer_assert(strpos($output, 'SENTINEL') === false && strpos($output, '[redacted]') !== false, 'sensitive fields never appear in the admin viewer');
viewer_assert(strpos(implode('', $viewer_store), 'SENTINEL') === false, 'sensitive values are redacted before persistence');

$viewer_options['zsr_log_enable'] = false;
viewer_assert(!zsr_log('error', 'disabled.sentinel'), 'disabled logger refuses new events');
$output = viewer_output();
viewer_assert(strpos($output, 'disabled.sentinel') === false && strpos($output, 'viewer.untrusted') !== false, 'turning logging off stops new records and preserves earlier diagnostics');
viewer_assert(strpos($output, '诊断日志已关闭') !== false, 'administrator can see when logging is disabled');
$viewer_options['zsr_log_enable'] = true;
$viewer_options['zsr_log_level'] = 'error';
zsr_log('info', 'filtered.sentinel');
viewer_assert(strpos(viewer_output(), 'filtered.sentinel') === false, 'log level filtering also applies to the admin viewer');
$viewer_options['zsr_log_level'] = 'info';

$wpdb->competing_record = array('event' => 'parallel.request', 'time' => '2026-10-06 12:00:01', 'level' => 'INFO', 'request_id' => 'parallel', 'context' => array());
zsr_log('info', 'current.request');
do_action('shutdown');
$output = viewer_output();
viewer_assert(strpos($output, 'parallel.request') !== false && strpos($output, 'current.request') !== false, 'concurrent request logging does not overwrite another request');

for ($index = 0; $index < 130; $index++) {
    zsr_log('info', 'batch.' . $index);
}
do_action('shutdown');
$output = viewer_output();
viewer_assert(substr_count($output, 'batch.') === 100 && strpos($output, 'batch.129') !== false && strpos($output, 'batch.29 ') === false, 'viewer retains at most the most recent 100 records');

for ($index = 0; $index < 100; $index++) {
    zsr_log('info', 'large.' . $index, array('first' => str_repeat('a', 250), 'second' => str_repeat('b', 250), 'third' => str_repeat('c', 250)));
}
do_action('shutdown');
viewer_assert(strlen(get_option('zsr_log_records')) <= 65536, 'persisted diagnostics remain within the 64 KiB limit');
viewer_assert(strpos(viewer_output(), 'large.99') !== false, 'byte-limited retention keeps the newest record');

zsr_log('error', 'bounded.context', array_fill(0, 30, array_fill(0, 30, str_repeat('x', 256))));
viewer_assert(strpos(viewer_output(), '[truncated]') !== false, 'oversized individual contexts are bounded before persistence');

zsr_log('info', 'before.uninstall');
define('WP_UNINSTALL_PLUGIN', true);
require dirname(__DIR__) . '/uninstall.php';
do_action('shutdown');
viewer_assert(strpos(viewer_output(), '暂无诊断日志') !== false, 'uninstall removes retained diagnostics without shutdown recreating them');

fwrite(STDOUT, "log viewer tests passed\n");
