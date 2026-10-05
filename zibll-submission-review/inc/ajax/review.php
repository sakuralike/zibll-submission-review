<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Acquire the short reviewer lock.
 *
 * @param int $post_id
 * @param int $user_id
 * @return bool
 */
function zsr_acquire_review_lock($post_id, $user_id)
{
    $key = 'zsr_lock_' . (int) $post_id;
    $token = (string) $user_id . ':' . (function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('', true));
    if (function_exists('wp_cache_add')) {
        if (!wp_cache_add($key, $token, 'zsr', 60)) {
            return false;
        }
        if (function_exists('set_transient')) {
            set_transient($key, $token, 60);
        }
        return $token;
    }
    if (!function_exists('get_transient') || !function_exists('set_transient')) {
        return $token;
    }
    $existing = get_transient($key);
    if ($existing) {
        return false;
    }
    set_transient($key, $token, 60);
    return get_transient($key) === $token ? $token : false;
}

function zsr_release_review_lock($post_id, $token = '')
{
    $key = 'zsr_lock_' . (int) $post_id;
    if (function_exists('wp_cache_get') && function_exists('wp_cache_delete') && (!$token || wp_cache_get($key, 'zsr') === $token)) {
        wp_cache_delete($key, 'zsr');
    }
    if (function_exists('delete_transient') && (!$token || !function_exists('get_transient') || get_transient('zsr_lock_' . (int) $post_id) === $token)) {
        delete_transient($key);
    }
}

/**
 * Write one review meta value and verify the stored value.
 *
 * @param int    $post_id
 * @param string $key
 * @param mixed  $value
 * @return bool
 */
function zsr_update_review_meta_checked($post_id, $key, $value)
{
    if (function_exists('get_post_meta') && get_post_meta($post_id, $key, true) === $value) {
        return true;
    }
    if (!function_exists('update_post_meta') || !update_post_meta($post_id, $key, $value)) {
        return false;
    }
    return !function_exists('get_post_meta') || get_post_meta($post_id, $key, true) === $value;
}

/**
 * Execute one front-end review action.
 *
 * @return void
 */
function zsr_ajax_review()
{
    zsr_verify_ajax_nonce('zsr_review');
    $user_id = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
    if (!zsr_can_review($user_id)) {
        zsr_ajax_response(false, '您没有审核稿件的权限');
    }

    $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
    $method = isset($_POST['method']) ? sanitize_key($_POST['method']) : '';
    $post = zsr_get_review_post($post_id, $user_id, true);
    if (!$post) {
        zsr_ajax_response(false, '稿件不存在、已处理或您没有权限');
    }
    $lock_token = zsr_acquire_review_lock($post_id, $user_id);
    if (!$lock_token) {
        zsr_ajax_response(false, '该稿件正被其他审核人处理，请稍候再试');
    }

    // Re-read after locking so a stale browser cannot overwrite a newer result.
    $post = zsr_get_review_post($post_id, $user_id, true);
    if (!$post || $post->post_status !== 'pending') {
        zsr_release_review_lock($post_id, $lock_token);
        zsr_ajax_response(false, '该稿件不处于待审核状态，请刷新后重试');
    }
    $state = function_exists('get_post_meta') ? get_post_meta($post_id, 'zsr_state', true) : 'pending';
    $history_exists = zsr_reviewer_has_history($post_id, $user_id);
    $context = array(
        'post_type'          => $post->post_type,
        'can_review'         => true,
        'can_review_others'  => zsr_can_review_others($user_id),
        'is_self'            => (int) $post->post_author === $user_id,
        'is_other'           => (int) $post->post_author !== $user_id,
            'already_reviewed'   => $history_exists,
            'last_reviewed_at'   => function_exists('get_post_meta') ? get_post_meta($post_id, 'zsr_reviewed_at', true) : '',
        'message'            => isset($_POST['msg']) ? $_POST['msg'] : '',
    );
    $transition = zsr_state_transition($post->post_status, $state, $method, zsr_get_options(), $context);
    if (!$transition['ok']) {
        zsr_release_review_lock($post_id, $lock_token);
        zsr_ajax_response(false, $transition['message']);
    }

    $old_meta = array(
        'zsr_state'          => get_post_meta($post_id, 'zsr_state', true),
        'zsr_reviewed_at'    => get_post_meta($post_id, 'zsr_reviewed_at', true),
        'zsr_reviewed_by'    => get_post_meta($post_id, 'zsr_reviewed_by', true),
        'zsr_reviewer_name'  => get_post_meta($post_id, 'zsr_reviewer_name', true),
        'zsr_reject_reason'  => get_post_meta($post_id, 'zsr_reject_reason', true),
        'zsr_review_history' => get_post_meta($post_id, 'zsr_review_history', true),
    );
    $old_meta_exists = array();
    foreach ($old_meta as $key => $value) {
        $old_meta_exists[$key] = $value !== '' && $value !== false;
    }

    $reviewer_name = function_exists('wp_get_current_user') ? wp_get_current_user()->display_name : (string) $user_id;
    $now = function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s');
    $meta_ok = zsr_update_review_meta_checked($post_id, 'zsr_state', $transition['to_state']);
    $meta_ok = $meta_ok && zsr_update_review_meta_checked($post_id, 'zsr_reviewed_at', $now);
    $meta_ok = $meta_ok && zsr_update_review_meta_checked($post_id, 'zsr_reviewed_by', $user_id);
    $meta_ok = $meta_ok && zsr_update_review_meta_checked($post_id, 'zsr_reviewer_name', $reviewer_name);
    if (in_array($method, array('reject', 'return'), true)) {
        $meta_ok = $meta_ok && zsr_update_review_meta_checked($post_id, 'zsr_reject_reason', $transition['message']);
    } elseif (function_exists('delete_post_meta')) {
        delete_post_meta($post_id, 'zsr_reject_reason');
    }
    $new_history = zsr_append_review_history(
        $post_id,
        $method,
        $transition['from_status'],
        $transition['to_status'],
        $transition['message'],
        $user_id,
        $reviewer_name
    );
    $meta_ok = $meta_ok && $new_history !== false;
    if (!$meta_ok) {
        foreach ($old_meta as $key => $value) {
            if ($old_meta_exists[$key]) {
                update_post_meta($post_id, $key, $value);
            } elseif (function_exists('delete_post_meta')) {
                delete_post_meta($post_id, $key);
            }
        }
        zsr_release_review_lock($post_id, $lock_token);
        zsr_ajax_response(false, '审核记录保存失败，请刷新后重试');
    }

    $updated = wp_update_post(array('ID' => $post_id, 'post_status' => $transition['to_status']), true);
    if (is_wp_error($updated) || !$updated) {
        foreach ($old_meta as $key => $value) {
            if ($old_meta_exists[$key]) {
                update_post_meta($post_id, $key, $value);
            } elseif (function_exists('delete_post_meta')) {
                delete_post_meta($post_id, $key);
            }
        }
        zsr_release_review_lock($post_id, $lock_token);
        zsr_ajax_response(false, '审核状态保存失败，请刷新后重试');
    }
    zsr_release_review_lock($post_id, $lock_token);
    zsr_ajax_response(true, $method === 'approve' ? '内容已审核发布' : ($method === 'reject' ? '已驳回此内容' : '已退回作者修改'), array('reload' => true, 'hide_modal' => true));
}

if (function_exists('add_action')) {
    add_action('wp_ajax_zsr_review', 'zsr_ajax_review');
}
