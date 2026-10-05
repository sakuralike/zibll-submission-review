<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

define('ABSPATH', __DIR__ . '/');
define('ZSR_OPTION', 'zsr_options');

$zsr_logger_options = array(
    'zsr_log_enable' => false,
    'zsr_log_level'  => 'info',
);

function get_option($key, $default = false)
{
    global $zsr_logger_options;
    return $key === ZSR_OPTION ? $zsr_logger_options : $default;
}

function current_time($format)
{
    return '2026-10-05 12:00:00';
}

function wp_generate_uuid4()
{
    return 'logger-request-id';
}

require_once dirname(__DIR__) . '/inc/core/options.php';
require_once dirname(__DIR__) . '/inc/core/logger.php';

function logger_assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$log_file = tempnam(sys_get_temp_dir(), 'zsr-log-');
ini_set('log_errors', '1');
ini_set('error_log', $log_file);

logger_assert(!zsr_log('info', 'disabled', array('user_id' => 12)), 'disabled logging is silent');
logger_assert(file_get_contents($log_file) === '', 'disabled log file remains empty');

$zsr_logger_options['zsr_log_enable'] = true;
logger_assert(!zsr_log('debug', 'filtered', array()), 'debug is filtered at info level');
logger_assert(file_get_contents($log_file) === '', 'debug is filtered at info level');

logger_assert(zsr_log('info', 'review.success', array(
    'user_id'      => 12,
    'post_id'      => 101,
    'nonce'        => 'nonce-SENTINEL',
    'post_content' => '<p>secret正文</p>',
    'reason'       => 'reason-SENTINEL',
    'reason_code'  => 'meta_failed',
    'token'        => 'token-SENTINEL',
)), 'info log emitted');
$output = file_get_contents($log_file);
logger_assert(strpos($output, 'review.success') !== false, 'event is present');
logger_assert(strpos($output, 'logger-request-id') !== false, 'request id is present');
logger_assert(strpos($output, '"user_id":12') !== false, 'safe context is present');
logger_assert(strpos($output, 'reason_code') !== false, 'diagnostic code is preserved');
logger_assert(strpos($output, 'SENTINEL') === false && strpos($output, 'secret正文') === false, 'sensitive context is redacted');
logger_assert(zsr_log_request_id() === zsr_log_request_id(), 'request id is stable');

$zsr_logger_options['zsr_log_level'] = 'debug';
logger_assert(zsr_log('debug', 'debug.detail', array('post_id' => 101)), 'debug log emitted at debug level');
logger_assert(strpos(file_get_contents($log_file), 'debug.detail') !== false, 'debug event is present');

@unlink($log_file);
fwrite(STDOUT, "logger tests passed\n");
