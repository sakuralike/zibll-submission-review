<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Read the append-only review history for a post.
 *
 * @param int $post_id
 * @return array<int, array<string, mixed>>
 */
function zsr_get_review_history($post_id)
{
    if (!function_exists('get_post_meta')) {
        return array();
    }

    $history = get_post_meta((int) $post_id, 'zsr_review_history', true);
    return is_array($history) ? array_values($history) : array();
}

/**
 * Append a bounded, sanitized review event.
 *
 * @param int                  $post_id
 * @param string               $method
 * @param string               $from_status
 * @param string               $to_status
 * @param string               $message
 * @param int                  $reviewer_id
 * @param string               $reviewer_name
 * @return array<int, array<string, mixed>>
 */
function zsr_append_review_history($post_id, $method, $from_status, $to_status, $message, $reviewer_id, $reviewer_name)
{
    $history = zsr_get_review_history($post_id);
    $clean_message = function_exists('wp_strip_all_tags')
        ? wp_strip_all_tags((string) $message)
        : strip_tags((string) $message);
    $clean_message = trim($clean_message);
    $event = array(
        'time'          => function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s'),
        'reviewer_id'   => (int) $reviewer_id,
        'reviewer_name' => (string) $reviewer_name,
        'method'        => (string) $method,
        'from_status'   => (string) $from_status,
        'to_status'     => (string) $to_status,
        'msg'           => $clean_message,
    );
    $history[] = $event;
    if (count($history) > 50) {
        $history = array_merge(array($history[0]), array_slice($history, -49));
    }

    if (function_exists('update_post_meta')) {
        if (!update_post_meta((int) $post_id, 'zsr_review_history', function_exists('wp_slash') ? wp_slash($history) : $history)) {
            return false;
        }
        if (function_exists('get_post_meta') && get_post_meta((int) $post_id, 'zsr_review_history', true) !== $history) {
            return false;
        }
    }
    return $history;
}

/**
 * Check whether a reviewer has already handled a post.
 *
 * @param int $post_id
 * @param int $reviewer_id
 * @return bool
 */
function zsr_reviewer_has_history($post_id, $reviewer_id)
{
    foreach (zsr_get_review_history($post_id) as $event) {
        if ((int) ($event['reviewer_id'] ?? 0) === (int) $reviewer_id) {
            return true;
        }
    }
    return false;
}
