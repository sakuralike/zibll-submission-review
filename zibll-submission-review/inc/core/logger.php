<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Return the configured diagnostic log level.
 *
 * The optional ZSR_LOG_LEVEL constant is useful when a site cannot open the
 * plugin settings page. The setting remains the normal runtime control.
 *
 * @return string
 */
function zsr_log_level()
{
    $level = defined('ZSR_LOG_LEVEL') ? constant('ZSR_LOG_LEVEL') : null;
    if ($level === null && function_exists('zsr_get_option')) {
        $level = zsr_get_option('zsr_log_level', 'error');
    }

    $level = strtolower(trim((string) $level));
    return in_array($level, array('off', 'error', 'warning', 'info', 'debug'), true)
        ? $level
        : 'error';
}

/**
 * Whether diagnostic logging is enabled for the current request.
 *
 * @return bool
 */
function zsr_log_enabled()
{
    if (defined('ZSR_LOG_LEVEL')) {
        return zsr_log_level() !== 'off';
    }
    if (!function_exists('zsr_get_option')) {
        return false;
    }
    return function_exists('zsr_bool')
        ? zsr_bool(zsr_get_option('zsr_log_enable', false))
        : in_array(zsr_get_option('zsr_log_enable', false), array(true, 1, '1', 'true', 'yes', 'on'), true);
}

/**
 * Whether a message at the requested level should be emitted.
 *
 * @param string $level
 * @return bool
 */
function zsr_should_log($level)
{
    $levels = array('off' => 0, 'error' => 1, 'warning' => 2, 'info' => 3, 'debug' => 4);
    $level = strtolower(trim((string) $level));
    if (!zsr_log_enabled()) {
        return false;
    }
    $configured = zsr_log_level();

    return isset($levels[$level], $levels[$configured])
        && $levels[$configured] > 0
        && $levels[$level] <= $levels[$configured];
}

/**
 * Return the correlation id shared by all records in one request.
 *
 * @return string
 */
function zsr_log_request_id()
{
    static $request_id = null;
    if ($request_id === null) {
        $request_id = function_exists('wp_generate_uuid4')
            ? (string) wp_generate_uuid4()
            : uniqid('zsr-', true);
    }
    return $request_id;
}

/**
 * Sanitize one value before it reaches the PHP/WordPress error log.
 *
 * @param mixed  $value
 * @param string $key
 * @param int    $depth
 * @return mixed
 */
function zsr_sanitize_log_value($value, $key = '', $depth = 0)
{
    $key = strtolower((string) $key);
    if (preg_match('/nonce|token|password|passwd|secret|authorization|cookie|api[_-]?key|private[_-]?key|post[_-]?content|^content$|^body$|^message$|^msg$|^reason$|reason_text|review_reason|credential|email|phone|^ip$/', $key)) {
        return '[redacted]';
    }

    if ($depth > 3) {
        return '[depth-limit]';
    }
    if (is_array($value)) {
        $result = array();
        $count = 0;
        foreach ($value as $child_key => $child_value) {
            if ($count++ >= 30) {
                $result['...'] = '[truncated]';
                break;
            }
            $result[(string) $child_key] = zsr_sanitize_log_value($child_value, (string) $child_key, $depth + 1);
        }
        return $result;
    }
    if (is_object($value)) {
        return '[object:' . get_class($value) . ']';
    }
    if (is_resource($value)) {
        return '[resource]';
    }
    if (is_string($value)) {
        $value = preg_replace('/[\x00-\x1F\x7F]/', ' ', $value);
        if (strlen($value) > 256) {
            $value = substr($value, 0, 256) . '...';
        }
        return $value;
    }

    return $value;
}

/**
 * Write one structured diagnostic record to the configured PHP error log.
 *
 * No request nonce, post body, credential or arbitrary user message is
 * written. Callers should pass identifiers and stable event names instead.
 *
 * @param string $level
 * @param string $event
 * @param array  $context
 * @return bool
 */
function zsr_log($level, $event, $context = array())
{
    if (!zsr_should_log($level) || !function_exists('error_log')) {
        return false;
    }

    $event = preg_replace('/[^a-zA-Z0-9_.-]/', '_', (string) $event);
    $event = substr($event !== '' ? $event : 'event', 0, 80);
    $record = array(
        'request_id' => substr(zsr_log_request_id(), 0, 64),
        'time'       => function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s'),
        'event'      => $event,
        'context'    => zsr_sanitize_log_value(is_array($context) ? $context : array('value' => $context)),
    );
    $flags = 0;
    if (defined('JSON_UNESCAPED_UNICODE')) {
        $flags |= JSON_UNESCAPED_UNICODE;
    }
    if (defined('JSON_UNESCAPED_SLASHES')) {
        $flags |= JSON_UNESCAPED_SLASHES;
    }
    if (defined('JSON_PARTIAL_OUTPUT_ON_ERROR')) {
        $flags |= JSON_PARTIAL_OUTPUT_ON_ERROR;
    }
    $json = function_exists('wp_json_encode')
        ? wp_json_encode($record, $flags)
        : json_encode($record, $flags);
    if (!is_string($json) || $json === '') {
        $json = '{"event":"' . $event . '"}';
    }

    return (bool) @error_log('[zsr][' . strtoupper((string) $level) . '] ' . $json);
}
