<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Normalize the state stored beside WordPress post_status.
 *
 * @param string $post_status
 * @param string $state
 * @return string
 */
function zsr_normalize_state($post_status, $state = '')
{
    $state = (string) $state;
    if (in_array($state, array('draft', 'pending', 'rejected', 'returned', 'approved'), true)) {
        return $state;
    }
    if ($state !== '') {
        return '';
    }

    if ($post_status === 'draft') {
        return 'draft';
    }
    if (in_array($post_status, array('pending', 'future'), true)) {
        return 'pending';
    }
    if ($post_status === 'publish') {
        return 'approved';
    }

    return $state;
}

/**
 * Return a bounded multibyte length.
 *
 * @param string $value
 * @return int
 */
function zsr_string_length($value)
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

/**
 * Calculate a review transition without touching WordPress state.
 *
 * Permission and object checks are passed in through $context so this function
 * remains deterministic and can be tested without a WordPress bootstrap.
 *
 * @param string $from_status
 * @param string $from_state
 * @param string $method
 * @param array  $settings
 * @param array  $context
 * @return array<string, mixed>
 */
function zsr_state_transition($from_status, $from_state, $method, $settings = array(), $context = array())
{
    $defaults = array(
        'zsr_actions'                => array('approve', 'reject', 'return'),
        'zsr_approve_to_status'      => 'publish',
        'zsr_approve_keep_audit'     => false,
        'zsr_reject_to_status'       => 'pending',
        'zsr_return_to_status'       => 'draft',
        'zsr_reject_reason_required' => true,
        'zsr_return_reason_required' => false,
        'zsr_reason_maxlength'       => 200,
        'zsr_allow_self_review'      => false,
        'zsr_allow_re_review'        => true,
    );
    $settings = array_replace($defaults, is_array($settings) ? $settings : array());
    if (function_exists('zsr_normalize_options')) {
        $settings = zsr_normalize_options($settings);
    }
    $context = is_array($context) ? $context : array();
    $from_status = (string) $from_status;
    $raw_state = (string) $from_state;
    $from_state = zsr_normalize_state($from_status, $from_state);
    $method = strtolower(trim((string) $method));

    if (!in_array($from_status, array('pending', 'future'), true)) {
        return zsr_transition_error('invalid_source_status', __('该稿件不处于待审核状态，请刷新后重试。', 'zib-sub-review'));
    }
    if ($from_state === '') {
        return zsr_transition_error('invalid_source_state', __('该稿件状态组合不允许审核。', 'zib-sub-review'));
    }

    if (isset($context['post_type']) && $context['post_type'] !== 'post') {
        return zsr_transition_error('invalid_post_type', __('只能审核 post 类型的稿件。', 'zib-sub-review'));
    }
    if (array_key_exists('can_review', $context) && !$context['can_review']) {
        return zsr_transition_error('forbidden', __('您没有审核此稿件的权限。', 'zib-sub-review'));
    }
    if (!empty($context['is_self']) && !$settings['zsr_allow_self_review']) {
        return zsr_transition_error('self_review_forbidden', __('不允许审核自己的稿件。', 'zib-sub-review'));
    }
    if (!empty($context['is_other']) && array_key_exists('can_review_others', $context) && !$context['can_review_others']) {
        return zsr_transition_error('others_forbidden', __('您没有审核他人稿件的权限。', 'zib-sub-review'));
    }
    if (!in_array($method, array('approve', 'reject', 'return'), true)) {
        return zsr_transition_error('invalid_method', __('无效的审核动作。', 'zib-sub-review'));
    }
    if (!in_array($method, (array) $settings['zsr_actions'], true)) {
        return zsr_transition_error('action_disabled', __('该审核动作未被启用。', 'zib-sub-review'));
    }
    if (!$settings['zsr_allow_re_review'] && !empty($context['already_reviewed'])) {
        $window = max(0, (int) $settings['zsr_re_review_window']);
        $reviewed_at = isset($context['last_reviewed_at']) ? strtotime((string) $context['last_reviewed_at']) : false;
        if ($window === 0 || !$reviewed_at || (time() - $reviewed_at) < ($window * 3600)) {
            return zsr_transition_error('repeat_review_forbidden', __('该稿件已由您处理过。', 'zib-sub-review'));
        }
    }

    $message = isset($context['message']) ? trim((string) $context['message']) : '';
    $message = function_exists('wp_strip_all_tags') ? wp_strip_all_tags($message) : strip_tags($message);
    $requires_message = ($method === 'reject' && $settings['zsr_reject_reason_required'])
        || ($method === 'return' && $settings['zsr_return_reason_required']);
    if ($requires_message && $message === '') {
        return zsr_transition_error('message_required', $method === 'reject' ? __('请填写驳回原因或修改建议。', 'zib-sub-review') : __('请填写退回意见。', 'zib-sub-review'));
    }

    $max_length = max(1, (int) $settings['zsr_reason_maxlength']);
    if (zsr_string_length($message) > $max_length) {
        $message = function_exists('mb_substr')
            ? mb_substr($message, 0, $max_length, 'UTF-8')
            : substr($message, 0, $max_length);
    }

    $target_status = $from_status;
    $target_state = $from_state;
    if ($method === 'approve') {
        $target_status = in_array($settings['zsr_approve_to_status'], array('publish', 'pending'), true)
            ? $settings['zsr_approve_to_status']
            : 'publish';
        $target_state = 'approved';
        if ($settings['zsr_approve_keep_audit']) {
            $target_status = 'pending';
        }
        if ($from_status === 'future' && $target_status === 'publish') {
            $target_status = 'future';
        }
    } elseif ($method === 'reject') {
        $target_status = in_array($settings['zsr_reject_to_status'], array('pending', 'draft', 'trash'), true)
            ? $settings['zsr_reject_to_status']
            : 'pending';
        $target_state = 'rejected';
    } else {
        $target_status = in_array($settings['zsr_return_to_status'], array('draft', 'pending'), true)
            ? $settings['zsr_return_to_status']
            : 'draft';
        $target_state = 'returned';
    }

    return array(
        'ok'            => true,
        'method'        => $method,
        'from_status'   => $from_status,
        'from_state'    => $from_state,
        'to_status'     => $target_status,
        'to_state'      => $target_state,
        'message'       => $message,
        'status_changed'=> $target_status !== $from_status,
    );
}

/**
 * @param string $code
 * @param string $message
 * @return array<string, mixed>
 */
function zsr_transition_error($code, $message)
{
    return array(
        'ok'      => false,
        'code'    => $code,
        'message' => $message,
    );
}
