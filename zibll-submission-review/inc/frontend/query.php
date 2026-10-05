<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Build the query contract for the current user's submissions.
 *
 * @param int $user_id
 * @param int $paged
 * @param int $per_page
 * @return array<string, mixed>
 */
function zsr_my_submissions_query_args($user_id, $paged = 1, $per_page = 20)
{
    return array(
        'post_type'              => 'post',
        'post_status'            => array('draft', 'pending', 'publish', 'trash'),
        'author'                 => max(0, (int) $user_id),
        'posts_per_page'         => max(1, min(50, (int) $per_page)),
        'paged'                  => max(1, (int) $paged),
        'orderby'                => 'modified',
        'order'                  => 'DESC',
        'ignore_sticky_posts'    => true,
        'no_found_rows'          => false,
        'update_post_meta_cache' => true,
        'update_post_term_cache' => true,
    );
}

/**
 * Query the current user's submissions without changing global query state.
 *
 * @param int $user_id
 * @param int $paged
 * @param int $per_page
 * @return WP_Query|false
 */
function zsr_get_my_submissions($user_id, $paged = 1, $per_page = 20)
{
    if (!class_exists('WP_Query') || (int) $user_id < 1) {
        return false;
    }

    return new WP_Query(zsr_my_submissions_query_args($user_id, $paged, $per_page));
}
