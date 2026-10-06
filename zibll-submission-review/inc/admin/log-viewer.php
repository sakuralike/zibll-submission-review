<?php

if (!defined('ABSPATH')) {
    exit;
}

function zsr_render_admin_logs()
{
    if (!function_exists('current_user_can') || !current_user_can('manage_options')) {
        return;
    }

    zsr_flush_log_records();
    $records = json_decode((string) get_option('zsr_log_records', '[]'), true);
    $records = is_array($records) ? array_slice($records, -100) : array();
    echo '<div class="zsr-diagnostic-logs">';
    echo '<p>' . esc_html(__('最近的诊断日志（最多 100 条，刷新页面更新）。', 'zib-sub-review')) . '</p>';
    if (!zsr_log_enabled() || zsr_log_level() === 'off') {
        echo '<p>' . esc_html(__('诊断日志已关闭；下方仅显示之前保留的记录。', 'zib-sub-review')) . '</p>';
    }
    if (!$records) {
        echo '<p>' . esc_html(__('暂无诊断日志。启用诊断日志并操作插件后，刷新此页查看。', 'zib-sub-review')) . '</p></div>';
        return;
    }

    echo '<pre style="max-height:400px;overflow:auto;white-space:pre-wrap">';
    foreach (array_reverse($records) as $record) {
        if (!is_array($record)) {
            continue;
        }
        $context = isset($record['context']) && is_array($record['context']) ? zsr_sanitize_log_value($record['context']) : array();
        $line = '[' . (isset($record['time']) ? $record['time'] : '') . '] '
            . '[' . (isset($record['level']) ? $record['level'] : '') . '] '
            . (isset($record['event']) ? $record['event'] : '') . ' '
            . (isset($record['request_id']) ? $record['request_id'] : '') . ' '
            . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
        echo esc_html($line) . "\n";
    }
    echo '</pre></div>';
}
