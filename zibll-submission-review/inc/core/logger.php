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
    if (!zsr_should_log($level)) {
        return false;
    }

    $event = preg_replace('/[^a-zA-Z0-9_.-]/', '_', (string) $event);
    $event = substr($event !== '' ? $event : 'event', 0, 80);
    $record = array(
        'request_id' => substr(zsr_log_request_id(), 0, 64),
        'time'       => function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s'),
        'level'      => strtoupper(trim((string) $level)),
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

    $queued = zsr_queue_log_record($record);
    $written = function_exists('error_log') && @error_log('[zsr][' . strtoupper((string) $level) . '] ' . $json);
    return $queued || $written;
}

function zsr_queue_log_record($record)
{
    global $wpdb;
    if (!empty($GLOBALS['zsr_log_flushing']) || !is_object($wpdb) || empty($wpdb->options)
        || !is_callable(array($wpdb, 'get_var')) || !is_callable(array($wpdb, 'prepare'))
        || !is_callable(array($wpdb, 'query')) || !function_exists('add_action')) {
        return false;
    }

    if (strlen((string) json_encode($record)) > 4096) {
        $record['context'] = array('detail' => '[truncated]');
    }
    $site_id = function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 1;
    $GLOBALS['zsr_log_pending'][$site_id][] = $record;
    $GLOBALS['zsr_log_pending'][$site_id] = array_slice($GLOBALS['zsr_log_pending'][$site_id], -100);
    add_action('shutdown', 'zsr_flush_log_records', PHP_INT_MAX);
    return true;
}

function zsr_flush_log_records()
{
    global $wpdb;
    if (empty($GLOBALS['zsr_log_pending']) || !empty($GLOBALS['zsr_log_flushing'])) {
        return;
    }

    $pending = $GLOBALS['zsr_log_pending'];
    $GLOBALS['zsr_log_pending'] = array();
    $GLOBALS['zsr_log_flushing'] = true;
    try {
        foreach ($pending as $site_id => $batch) {
            $current_site = function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 1;
            $switched = $current_site !== (int) $site_id;
            if ($switched && (!function_exists('switch_to_blog') || !function_exists('restore_current_blog'))) {
                continue;
            }
            if ($switched) {
                switch_to_blog($site_id);
            }
            try {
                for ($attempt = 0; $attempt < 3; $attempt++) {
                    $previous = $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1", 'zsr_log_records'));
                    $records = is_string($previous) ? json_decode($previous, true) : array();
                    $records = array_slice(array_merge(is_array($records) ? $records : array(), $batch), -100);
                    do {
                        $json = json_encode($records, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
                        if (strlen((string) $json) <= 65536) {
                            break;
                        }
                        array_shift($records);
                    } while ($records);
                    if (!is_string($json) || $json === $previous) {
                        break;
                    }

                    $query = $previous === null
                        ? $wpdb->prepare("INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", 'zsr_log_records', $json)
                        : $wpdb->prepare("UPDATE {$wpdb->options} SET option_value = %s, autoload = 'no' WHERE option_name = %s AND BINARY option_value = BINARY %s", $json, 'zsr_log_records', $previous);
                    $changed = $wpdb->query($query);
                    if ($changed === false) {
                        break;
                    }
                    if ($changed > 0) {
                        if (function_exists('wp_cache_delete')) {
                            wp_cache_delete('zsr_log_records', 'options');
                            wp_cache_delete('notoptions', 'options');
                        }
                        break;
                    }
                }
            } finally {
                if ($switched) {
                    restore_current_blog();
                }
            }
        }
    } finally {
        $GLOBALS['zsr_log_flushing'] = false;
    }
}
