<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Return posts with review events made by the current reviewer.
 *
 * History is stored as a bounded post-meta array, so this low-frequency view
 * filters the bounded result set in PHP instead of pretending serialized meta
 * is a reliably indexed relational column.
 *
 * @param int $user_id
 * @param int $limit
 * @return array<int, array{post: WP_Post, event: array<string, mixed> }>
 */
function zsr_get_reviewer_history($user_id, $limit = 50)
{
    $limit = min(50, max(1, (int) $limit));
    if (!zsr_can_review($user_id) || !function_exists('get_posts')) {
        return array();
    }
    $posts = get_posts(array(
        'post_type'      => 'post',
        'post_status'    => array('draft', 'pending', 'publish', 'trash'),
        'posts_per_page' => min(200, max(1, (int) $limit * 4)),
        'meta_key'       => 'zsr_review_history',
        'orderby'        => 'modified',
        'order'          => 'DESC',
    ));
    $result = array();
    foreach ((array) $posts as $post) {
        foreach (zsr_get_review_history($post->ID) as $event) {
            if ((int) ($event['reviewer_id'] ?? 0) === (int) $user_id) {
                $result[] = array('post' => $post, 'event' => $event);
            }
        }
        if (count($result) >= $limit) {
            break;
        }
    }
    return array_slice($result, 0, max(1, (int) $limit));
}
