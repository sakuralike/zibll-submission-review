<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Whether a user may review posts submitted by other users.
 *
 * @param int $user_id
 * @return bool
 */
function zsr_can_review_others($user_id = 0)
{
    $user_id = $user_id ?: (function_exists('get_current_user_id') ? get_current_user_id() : 0);
    if ($user_id < 1) {
        return false;
    }
    if (function_exists('zib_user_can')) {
        return (bool) zib_user_can($user_id, 'zsr_review_others');
    }
    return $user_id === (function_exists('get_current_user_id') ? (int) get_current_user_id() : 0)
        && zsr_current_user_can('zsr_review_others');
}

/**
 * Whether a user can access the review area.
 *
 * @param int $user_id
 * @return bool
 */
function zsr_can_review($user_id = 0)
{
    $user_id = $user_id ?: (function_exists('get_current_user_id') ? get_current_user_id() : 0);
    if ($user_id < 1 || !zsr_get_option('zsr_enable', true) || !zsr_get_option('zsr_enable_review', true)) {
        return false;
    }
    if (function_exists('zib_user_can')) {
        return (bool) zib_user_can($user_id, 'zsr_review');
    }
    return $user_id === (function_exists('get_current_user_id') ? (int) get_current_user_id() : 0)
        && zsr_current_user_can('zsr_review');
}

/**
 * Build the pending review queue query.
 *
 * @param int $user_id
 * @param int $paged
 * @param int $per_page
 * @return array<string, mixed>|false
 */
function zsr_review_queue_query_args($user_id, $paged = 1, $per_page = 20)
{
    if (!zsr_can_review($user_id)) {
        return false;
    }
    if (!zsr_can_review_others($user_id) && !zsr_get_option('zsr_allow_self_review', false)) {
        return false;
    }
    $args = array(
        'post_type'              => 'post',
        'post_status'            => array('pending'),
        'meta_query'             => array(
            array(
                'key'     => 'zsr_state',
                'value'   => array('pending', 'rejected'),
                'compare' => 'IN',
            ),
        ),
        'posts_per_page'         => max(1, min(50, (int) $per_page)),
        'paged'                  => max(1, (int) $paged),
        'orderby'                => 'modified',
        'order'                  => 'ASC',
        'ignore_sticky_posts'    => true,
        'update_post_meta_cache' => true,
        'update_post_term_cache' => true,
    );
    if (!zsr_can_review_others($user_id)) {
        $args['author'] = (int) $user_id;
    }
    return $args;
}

/**
 * Return a pending review queue.
 *
 * @param int $user_id
 * @param int $paged
 * @param int $per_page
 * @return WP_Query|false
 */
function zsr_get_review_queue($user_id, $paged = 1, $per_page = 20)
{
    $args = zsr_review_queue_query_args($user_id, $paged, $per_page);
    if ($args === false || !class_exists('WP_Query')) {
        return false;
    }
    return new WP_Query($args);
}

/**
 * Load a post that the current reviewer may inspect.
 *
 * @param int $post_id
 * @param int $user_id
 * @param bool $pending_only
 * @return WP_Post|false
 */
function zsr_get_review_post($post_id, $user_id = 0, $pending_only = true)
{
    $user_id = $user_id ?: (function_exists('get_current_user_id') ? get_current_user_id() : 0);
    if (!zsr_can_review($user_id) || !function_exists('get_post')) {
        return false;
    }
    $post = get_post((int) $post_id);
    if (!$post || $post->post_type !== 'post') {
        return false;
    }
    if ($pending_only && $post->post_status !== 'pending') {
        return false;
    }
    $is_self = (int) $post->post_author === (int) $user_id;
    if (!$is_self && !zsr_can_review_others($user_id)) {
        return false;
    }
    if ($is_self && !zsr_get_option('zsr_allow_self_review', false)) {
        return false;
    }
    $state = function_exists('get_post_meta') ? get_post_meta($post_id, 'zsr_state', true) : '';
    if (!in_array($state, array('pending', 'rejected'), true)) {
        return false;
    }
    return $post;
}
