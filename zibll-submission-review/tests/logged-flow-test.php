<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/i18n-helpers.php';

define('ABSPATH', __DIR__ . '/');
define('ZSR_OPTION', 'zsr_options');

$zsr_flow_options = array(
    'zsr_log_enable' => true,
    'zsr_log_level'  => 'debug',
);
$zsr_flow_meta = array();
$zsr_flow_writes = 0;
$zsr_flow_actions = 0;

function get_option($key, $default = false)
{
    global $zsr_flow_options;
    return $key === ZSR_OPTION ? $zsr_flow_options : $default;
}

function get_current_user_id()
{
    return 12;
}

function wp_get_current_user() { return (object) array('ID' => 12, 'roles' => array('author')); }
function wp_roles() { return new class { public function get_names() { return array('author' => 'Author'); } }; }

function _pz($key, $default = false)
{
    return $default;
}

function zib_current_user_can($capability)
{
    return true;
}

function do_action()
{
    global $zsr_flow_actions;
    $zsr_flow_actions++;
}

function wp_insert_post($postarr, $error = false)
{
    global $zsr_flow_writes;
    $zsr_flow_writes++;
    return 501;
}

function get_post($post_id)
{
    return (object) array(
        'ID'          => (int) $post_id,
        'post_author' => 12,
        'post_type'   => 'post',
        'post_status' => 'pending',
    );
}

function update_post_meta($post_id, $key, $value)
{
    global $zsr_flow_meta;
    $zsr_flow_meta[$post_id][$key] = $value;
    return true;
}

function get_post_meta($post_id, $key, $single = false)
{
    global $zsr_flow_meta;
    return isset($zsr_flow_meta[$post_id][$key]) ? $zsr_flow_meta[$post_id][$key] : '';
}

function current_time($format)
{
    return '2026-10-05 12:00:00';
}

function wp_kses_post($value)
{
    return strip_tags($value, '<p><strong><em>');
}

function absint($value)
{
    return abs((int) $value);
}

function is_wp_error($value)
{
    return false;
}

function wp_send_json($payload, $status = 200)
{
    throw new RuntimeException(json_encode(array('payload' => $payload, 'status' => $status)));
}

require_once dirname(__DIR__) . '/inc/core/options.php';
require_once dirname(__DIR__) . '/inc/core/logger.php';
require_once dirname(__DIR__) . '/inc/core/capabilities.php';
require_once dirname(__DIR__) . '/inc/ajax/submit.php';

function logged_flow_assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$log_file = tempnam(sys_get_temp_dir(), 'zsr-flow-');
ini_set('log_errors', '1');
ini_set('error_log', $log_file);
$_POST = array(
    'post_title'   => '合规投稿标题',
    'post_content' => '<p>secret正文</p>',
    'category'     => array('3'),
    'tags'         => '测试',
);

try {
    zsr_handle_submission(false);
    logged_flow_assert(false, 'submission response should terminate');
} catch (RuntimeException $exception) {
    $response = json_decode($exception->getMessage(), true);
    logged_flow_assert($response['status'] === 400 && $response['payload']['error'] === true, 'missing native submission fails closed');
    logged_flow_assert(zsr_ajax_reason_code($response['payload']['msg']) === 'theme_submit_unavailable', 'missing native submission response code');
}

$output = file_get_contents($log_file);
logged_flow_assert(strpos($output, 'submit.start') !== false, 'submission start log');
logged_flow_assert(strpos($output, 'submit.theme_unavailable') !== false, 'missing native submission is logged');
logged_flow_assert(strpos($output, 'theme_submit_unavailable') !== false, 'stable native dependency reason code');
logged_flow_assert(strpos($output, 'zib_ajax_new_posts') !== false, 'missing function name is logged');
logged_flow_assert(strpos($output, 'submit.success') === false, 'no successful submission is logged');
logged_flow_assert(strpos($output, 'ajax.response') !== false, 'submission response log');
logged_flow_assert(strpos($output, 'secret正文') === false, 'submission body is not logged');
logged_flow_assert($zsr_flow_writes === 0 && $zsr_flow_actions === 0 && !$zsr_flow_meta, 'no direct post, metadata, or notification writes on missing native dependency');

@unlink($log_file);
fwrite(STDOUT, "logged flow tests passed\n");
