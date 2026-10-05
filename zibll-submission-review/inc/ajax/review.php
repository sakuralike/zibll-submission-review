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
            if (function_exists('zsr_log')) {
                zsr_log('debug', 'review.lock_busy', array('post_id' => (int) $post_id));
            }
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
        if (function_exists('zsr_log')) {
            zsr_log('debug', 'review.lock_busy', array('post_id' => (int) $post_id));
        }
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
        if (function_exists('zsr_log')) {
            zsr_log('error', 'review.meta_write_failed', array(
                'post_id' => (int) $post_id,
                'meta_key' => (string) $key,
            ));
        }
        return false;
    }
    $verified = !function_exists('get_post_meta') || get_post_meta($post_id, $key, true) === $value;
    if (!$verified && function_exists('zsr_log')) {
        zsr_log('error', 'review.meta_verify_failed', array(
            'post_id' => (int) $post_id,
            'meta_key' => (string) $key,
        ));
    }
    return $verified;
}

/**
 * Execute one front-end review action.
 *
 * @return void
 */
function zsr_ajax_review()
{
    $started_at = microtime(true);
    zsr_verify_ajax_nonce('zsr_review');
    $user_id = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
    $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
    $method = isset($_POST['method']) ? sanitize_key($_POST['method']) : '';
    if (function_exists('zsr_log')) {
        zsr_log('info', 'review.start', array(
            'user_id' => $user_id,
            'post_id' => $post_id,
            'action'  => $method,
        ));
    }
    if (!zsr_can_review($user_id)) {
        if (function_exists('zsr_log')) {
            zsr_log('warning', 'review.denied', array(
                'user_id' => $user_id,
                'post_id' => $post_id,
                'reason_code' => 'permission',
                'duration_ms' => round((microtime(true) - $started_at) * 1000, 2),
            ));
        }
        zsr_ajax_response(false, '您没有审核稿件的权限');
    }

    $post = zsr_get_review_post($post_id, $user_id, true);
    if (!$post) {
        if (function_exists('zsr_log')) {
            zsr_log('warning', 'review.denied', array(
                'user_id' => $user_id,
                'post_id' => $post_id,
                'action' => $method,
                'reason_code' => 'post_unavailable',
                'duration_ms' => round((microtime(true) - $started_at) * 1000, 2),
            ));
        }
        zsr_ajax_response(false, '稿件不存在、已处理或您没有权限');
    }
    $lock_token = zsr_acquire_review_lock($post_id, $user_id);
    if (!$lock_token) {
        if (function_exists('zsr_log')) {
            zsr_log('warning', 'review.lock_rejected', array(
                'user_id' => $user_id,
                'post_id' => $post_id,
                'reason_code' => 'busy',
                'duration_ms' => round((microtime(true) - $started_at) * 1000, 2),
            ));
        }
        zsr_ajax_response(false, '该稿件正被其他审核人处理，请稍候再试');
    }

    // Re-read after locking so a stale browser cannot overwrite a newer result.
    $post = zsr_get_review_post($post_id, $user_id, true);
    if (!$post || $post->post_status !== 'pending') {
        if (function_exists('zsr_log')) {
            zsr_log('warning', 'review.denied', array(
                'user_id' => $user_id,
                'post_id' => $post_id,
                'action' => $method,
                'reason_code' => 'stale_post',
                'duration_ms' => round((microtime(true) - $started_at) * 1000, 2),
            ));
        }
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
        if (function_exists('zsr_log')) {
            zsr_log('warning', 'review.transition_rejected', array(
                'user_id' => $user_id,
                'post_id' => $post_id,
                'action' => $method,
                'reason_code' => isset($transition['code']) ? $transition['code'] : 'invalid_transition',
                'duration_ms' => round((microtime(true) - $started_at) * 1000, 2),
            ));
        }
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
        if (function_exists('zsr_log')) {
            zsr_log('error', 'review.meta_failed', array(
                'user_id' => $user_id,
                'post_id' => $post_id,
                'action' => $method,
                'reason_code' => 'meta_write_or_history',
                'rollback' => true,
                'duration_ms' => round((microtime(true) - $started_at) * 1000, 2),
            ));
        }
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
        if (function_exists('zsr_log')) {
            zsr_log('error', 'review.status_failed', array(
                'user_id' => $user_id,
                'post_id' => $post_id,
                'action' => $method,
                'reason_code' => is_wp_error($updated) && method_exists($updated, 'get_error_code')
                    ? $updated->get_error_code()
                    : 'empty_id',
                'rollback' => true,
                'duration_ms' => round((microtime(true) - $started_at) * 1000, 2),
            ));
        }
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
    if (function_exists('zsr_log')) {
        zsr_log('info', 'review.success', array(
            'user_id' => $user_id,
            'post_id' => $post_id,
            'action'  => $method,
            'from_status' => $transition['from_status'],
            'to_status' => $transition['to_status'],
            'duration_ms' => round((microtime(true) - $started_at) * 1000, 2),
        ));
    }
    zsr_ajax_response(true, $method === 'approve' ? '内容已审核发布' : ($method === 'reject' ? '已驳回此内容' : '已退回作者修改'), array('reload' => true, 'hide_modal' => true));
}

if (function_exists('add_action')) {
    add_action('wp_ajax_zsr_review', 'zsr_ajax_review');
}
