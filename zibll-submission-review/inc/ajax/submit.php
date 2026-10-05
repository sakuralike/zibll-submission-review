<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Send a response using the Zibll protocol when available.
 *
 * @param bool       $success
 * @param string     $message
 * @param array      $data
 * @return void
 */
function zsr_ajax_response($success, $message = '', $data = array())
{
    $payload = is_array($data) ? $data : array();
    if ($message !== '') {
        $payload['msg'] = $message;
    }

    $function = $success ? 'zib_send_json_success' : 'zib_send_json_error';
    if (function_exists($function)) {
        call_user_func($function, $payload);
    }

    $payload['error'] = !$success;
    if (!$success) {
        $payload['ys'] = 'danger';
    }
    if (function_exists('wp_send_json')) {
        wp_send_json($payload, $success ? 200 : 400);
    }

    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

/**
 * Verify an AJAX nonce using the theme adapter when it is available.
 *
 * @param string $action
 * @param string $name
 * @return void
 */
function zsr_verify_ajax_nonce($action, $name = '_wpnonce')
{
    if (function_exists('zib_ajax_verify_nonce')) {
        zib_ajax_verify_nonce($action, $name);
        return;
    }
    if (!function_exists('check_ajax_referer')) {
        zsr_ajax_response(false, '安全校验不可用，请联系管理员');
    }
    check_ajax_referer($action, $name);
}

/**
 * Normalize incoming post content without bypassing WordPress post APIs.
 *
 * @param mixed $value
 * @return string
 */
function zsr_post_content($value)
{
    $value = (string) $value;
    return function_exists('wp_kses_post') ? wp_kses_post($value) : strip_tags($value);
}

/**
 * Build a post payload for a new submission or an owned edit.
 *
 * @param int  $post_id
 * @param int  $user_id
 * @param bool $draft
 * @return array<string, mixed>
 */
function zsr_submission_postarr($post_id, $user_id, $draft)
{
    $title = isset($_POST['post_title']) ? zsr_text($_POST['post_title']) : '';
    $content = isset($_POST['post_content']) ? zsr_post_content($_POST['post_content']) : '';
    $categories = isset($_POST['category']) ? (array) $_POST['category'] : array();
    $categories = array_values(array_filter(array_map('absint', $categories)));
    $tags = isset($_POST['tags']) ? preg_split('/,|，|\n/', (string) $_POST['tags']) : array();
    $tags = array_values(array_filter(array_map('zsr_text', (array) $tags)));

    if ($title === '') {
        zsr_ajax_response(false, '请填写文章标题');
    }
    if ($content === '') {
        zsr_ajax_response(false, '还未填写任何内容');
    }

    $is_save = !$draft;
    if ($is_save) {
        $limit = function_exists('_pz') ? _pz('post_article_title_strlen_limit', array('min' => 5, 'max' => 30)) : array('min' => 5, 'max' => 30);
        $length = function_exists('mb_strlen') ? mb_strlen($title, 'UTF-8') : strlen($title);
        if ($length < (int) ($limit['min'] ?? 5) || $length > (int) ($limit['max'] ?? 30)) {
            zsr_ajax_response(false, '标题长度不符合要求');
        }
        if ((function_exists('mb_strlen') ? mb_strlen($content, 'UTF-8') : strlen($content)) < 10) {
            zsr_ajax_response(false, '文章内容过少');
        }
        if (empty($categories)) {
            zsr_ajax_response(false, '请选择文章分类');
        }
    }

    $postarr = array(
        'post_type'      => 'post',
        'post_title'     => $title,
        'post_content'   => $content,
        'post_status'    => $draft ? 'draft' : 'pending',
        'post_author'    => $user_id,
        'post_category'  => $categories,
        'comment_status' => 'open',
    );
    if (!empty($tags)) {
        $postarr['tags_input'] = $tags;
    }
    if ($post_id > 0) {
        $post = function_exists('get_post') ? get_post($post_id) : null;
        if (!$post || (int) $post->post_author !== $user_id || $post->post_type !== 'post') {
            zsr_ajax_response(false, '稿件不存在或没有编辑权限');
        }
        if (!in_array($post->post_status, array('draft', 'pending'), true)) {
            zsr_ajax_response(false, '当前稿件状态不允许编辑');
        }
        if ($draft && $post->post_status !== 'draft') {
            zsr_ajax_response(false, '待审核稿件不能保存为草稿');
        }
        $postarr['ID'] = $post_id;
    }

    return $postarr;
}

/**
 * Record plugin metadata after the theme has completed its native save flow.
 *
 * @param WP_Post $post
 * @return void
 */
function zsr_record_theme_submission($post)
{
    if (empty($post->ID) || $post->post_type !== 'post' || !function_exists('update_post_meta')) {
        return;
    }

    $state = $post->post_status === 'draft' ? 'draft' : ($post->post_status === 'pending' ? 'pending' : '');
    if ($state !== '') {
        update_post_meta($post->ID, 'zsr_state', $state);
        if ($state === 'pending' && function_exists('delete_post_meta')) {
            delete_post_meta($post->ID, 'zsr_reject_reason');
        }
    } else {
        delete_post_meta($post->ID, 'zsr_state');
    }
    update_post_meta($post->ID, 'zsr_submitted_at', current_time('mysql'));
    if ($post->post_status !== 'draft') {
        $count = (int) get_post_meta($post->ID, 'zsr_submit_count', true);
        update_post_meta($post->ID, 'zsr_submit_count', $count + 1);
    }
    update_post_meta($post->ID, 'zsr_version', 1);
}

/**
 * Hand the write to Zibll so its audit, media, payment and notification rules
 * remain the source of truth.
 *
 * @param bool $draft
 * @return void
 */
function zsr_delegate_submission_to_theme($draft)
{
    add_action('new_add_posts', 'zsr_record_theme_submission', 20);
    add_action('new_edit_posts', 'zsr_record_theme_submission', 20);
    $action = $draft ? 'posts_draft' : 'posts_save';
    $_POST['action'] = $action;
    $_REQUEST['action'] = $action;
    zib_ajax_new_posts();
}

/**
 * Save a plugin submission while preserving the theme insertion hook.
 *
 * @param bool $draft
 * @return void
 */
function zsr_handle_submission($draft)
{
    $user_id = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
    if ($user_id < 1) {
        zsr_ajax_response(false, '请登录后提交稿件');
    }
    if (!zsr_get_option('zsr_enable_submit', true)) {
        zsr_ajax_response(false, '前台投稿功能当前已关闭');
    }
    if (function_exists('_pz') && !_pz('post_article_s', true)) {
        zsr_ajax_response(false, '投稿功能已关闭');
    }
    if (function_exists('zib_is_close_sign') && zib_is_close_sign()) {
        zsr_ajax_response(false, '站点当前已关闭投稿入口');
    }
    if (function_exists('zib_user_is_ban') && zib_user_is_ban($user_id)) {
        zsr_ajax_response(false, '账号当前无法提交稿件');
    }
    if (!zsr_current_user_can('zsr_submit') || !zsr_current_user_can('new_post_add')) {
        zsr_ajax_response(false, '抱歉您的权限不足，暂时无法发布');
    }

    $post_id = isset($_POST['posts_id']) ? absint($_POST['posts_id']) : 0;
    if ($post_id && function_exists('zib_current_user_can') && !zsr_current_user_can('new_post_edit', $post_id)) {
        zsr_ajax_response(false, '抱歉您的权限不足，暂时无法编辑此文章');
    }

    if (function_exists('zib_ajax_new_posts')) {
        zsr_delegate_submission_to_theme($draft);
        return;
    }

    $postarr = zsr_submission_postarr($post_id, $user_id, $draft);
    do_action('zib_pre_insert_post', $postarr);
    $saved_id = wp_insert_post($postarr, true);
    if (is_wp_error($saved_id)) {
        zsr_ajax_response(false, $saved_id->get_error_message());
    }
    if (!$saved_id) {
        zsr_ajax_response(false, '文章保存失败，请稍后再试');
    }

    if (function_exists('update_post_meta')) {
        update_post_meta($saved_id, 'zsr_state', $draft ? 'draft' : 'pending');
        update_post_meta($saved_id, 'zsr_submitted_at', function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s'));
        $count = (int) get_post_meta($saved_id, 'zsr_submit_count', true);
        update_post_meta($saved_id, 'zsr_submit_count', $count + ($draft ? 0 : 1));
        update_post_meta($saved_id, 'zsr_version', 1);
    }

    $post = function_exists('get_post') ? get_post($saved_id) : null;
    if (!$draft && $post) {
        do_action('new_posts_pending', $post);
    }
    $message = $draft ? '草稿已保存' : '内容已提交，正在等待审核';
    zsr_ajax_response(true, $message, array('post_id' => (int) $saved_id));
}

function zsr_ajax_submit()
{
    zsr_verify_ajax_nonce('zsr_submit', '_wpnonce_submit');
    zsr_handle_submission(false);
}

function zsr_ajax_update()
{
    zsr_verify_ajax_nonce('zsr_update', '_wpnonce_update');
    zsr_handle_submission(false);
}

function zsr_ajax_draft()
{
    zsr_verify_ajax_nonce('zsr_draft', '_wpnonce_draft');
    zsr_handle_submission(true);
}

if (function_exists('add_action')) {
    add_action('wp_ajax_zsr_submit', 'zsr_ajax_submit');
    add_action('wp_ajax_zsr_update', 'zsr_ajax_update');
    add_action('wp_ajax_zsr_draft', 'zsr_ajax_draft');
}
