<?php

if (!defined('ABSPATH')) {
    exit;
}

function zsr_review_notification_content($post, $transition, $include_content)
{
    $titles = array(
        'approve' => __('您发布的稿件已通过审核：[%s]', 'zib-sub-review'),
        'reject' => __('您发布的稿件已被驳回：[%s]', 'zib-sub-review'),
        'return' => __('您发布的稿件被退回修改：[%s]', 'zib-sub-review'),
    );
    $template = $titles[$transition['method']];
    if ($transition['method'] === 'approve' && $transition['to_status'] === 'pending') {
        $template = __('您发布的稿件已通过审核，等待发布：[%s]', 'zib-sub-review');
    }
    $post_title = trim(str_replace(array("\r", "\n"), ' ', wp_strip_all_tags($post->post_title)));
    $title = sprintf($template, $post_title);
    $content = '<p>' . esc_html($title) . '</p>';
    if ($transition['message'] !== '') {
        $content .= '<p>' . esc_html__('审核意见：', 'zib-sub-review') . nl2br(esc_html($transition['message'])) . '</p>';
    }
    if ($include_content) {
        $excerpt = trim(wp_strip_all_tags(strip_shortcodes($post->post_content)));
        if (function_exists('zib_str_cut')) {
            $excerpt = zib_str_cut($excerpt, 0, 200, '...');
        } elseif (function_exists('mb_substr')) {
            $excerpt = mb_substr($excerpt, 0, 200, 'UTF-8');
        } elseif (preg_match('/^.{0,200}/us', $excerpt, $match)) {
            $excerpt = $match[0];
        } else {
            $excerpt = '';
        }
        $content .= '<p>' . esc_html__('内容摘要：', 'zib-sub-review') . '</p><div>' . esc_html($excerpt) . '</div>';
    }
    $url = $transition['to_status'] === 'publish' ? get_permalink($post->ID) : zsr_page_url();
    if ($url && in_array($transition['to_status'], array('draft', 'pending'), true)) {
        $url = add_query_arg(array('view' => 'edit', 'post_id' => (int) $post->ID), $url);
    } elseif ($url && $transition['to_status'] !== 'publish') {
        $url = add_query_arg('view', 'my', $url);
    }
    if ($url) {
        $content .= '<p><a href="' . esc_url($url) . '">' . esc_html__('查看稿件', 'zib-sub-review') . '</a></p>';
    }
    return array('title' => $title, 'content' => $content);
}

function zsr_send_review_message($author, $notification)
{
    if (!is_callable(array('ZibMsg', 'add'))) {
        return array('status' => 'failed', 'code' => 'message_unavailable');
    }
    if ((function_exists('_pz') && !_pz('message_s', true))
        || (function_exists('zib_msg_is_allow_receive') && !zib_msg_is_allow_receive($author->ID, 'posts'))) {
        return array('status' => 'skipped', 'code' => 'message_disabled_by_recipient');
    }
    $result = ZibMsg::add(wp_slash(array(
        'send_user'    => 'admin',
        'receive_user' => (int) $author->ID,
        'type'         => 'posts',
        'title'        => esc_html($notification['title']),
        'content'      => $notification['content'],
        'meta'         => '',
        'other'        => '',
    )));
    return is_array($result) && $result
        ? array('status' => 'sent', 'code' => 'message_saved')
        : array('status' => 'failed', 'code' => 'message_write_failed');
}

function zsr_send_review_email($author, $notification)
{
    if (!function_exists('wp_mail')) {
        return array('status' => 'failed', 'code' => 'mail_unavailable');
    }
    if (!is_email($author->user_email)) {
        return array('status' => 'failed', 'code' => 'invalid_email');
    }
    $result = wp_mail($author->user_email, $notification['title'], $notification['content'], array('Content-Type: text/html; charset=UTF-8'));
    return $result === true
        ? array('status' => 'accepted', 'code' => 'mail_accepted')
        : array('status' => 'failed', 'code' => 'mail_failed');
}

function zsr_notify_review_author($post, $transition, $reviewer_id)
{
    $options = zsr_normalize_options(zsr_get_options());
    if (empty($transition['ok']) || !in_array($transition['method'], array('approve', 'reject', 'return'), true)
        || !$options['zsr_notify_author'] || !$options['zsr_notify_channel']
        || ($transition['method'] === 'return' && !$options['zsr_notify_on_return'])) {
        return array();
    }

    $results = array();
    foreach ($options['zsr_notify_channel'] as $channel) {
        $started_at = microtime(true);
        try {
            $author = get_userdata((int) $post->post_author);
            if (!$author || empty($author->ID)) {
                $result = array('status' => 'failed', 'code' => 'author_missing');
            } else {
                $notification = zsr_review_notification_content($post, $transition, $options['zsr_notify_include_content']);
                $result = $channel === 'msg'
                    ? zsr_send_review_message($author, $notification)
                    : zsr_send_review_email($author, $notification);
            }
        } catch (Throwable $error) {
            $result = array('status' => 'failed', 'code' => 'channel_exception');
        }
        $results[$channel] = $result;
        if (function_exists('zsr_log')) {
            $level = $result['status'] === 'failed' ? 'error' : ($result['status'] === 'skipped' ? 'warning' : 'info');
            zsr_log($level, 'notify.result', array(
                'post_id'     => (int) $post->ID,
                'user_id'     => (int) $post->post_author,
                'reviewer_id' => (int) $reviewer_id,
                'action'      => $transition['method'],
                'channel'     => $channel,
                'status'      => $result['status'],
                'reason_code' => $result['code'],
                'duration_ms' => round((microtime(true) - $started_at) * 1000, 2),
            ));
        }
    }
    return $results;
}

function zsr_update_review_status($post_id, $status)
{
    $removed = array();
    foreach (array('zib_newmsg_pending_to_publish', 'zib_email_pending_to_publish') as $callback) {
        $priority = has_action('pending_to_publish', $callback);
        if ($priority !== false && remove_action('pending_to_publish', $callback, $priority)) {
            $removed[$callback] = $priority;
        }
    }
    try {
        return wp_update_post(array('ID' => $post_id, 'post_status' => $status), true);
    } finally {
        foreach ($removed as $callback => $priority) {
            add_action('pending_to_publish', $callback, $priority);
        }
    }
}
