<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Convert a user-facing AJAX message to a stable diagnostic code.
 *
 * @param string $message
 * @return string
 */
function zsr_ajax_reason_code($message)
{
    static $codes = array(
        '请登录后提交稿件' => 'not_logged_in',
        '前台投稿功能当前已关闭' => 'plugin_submit_disabled',
        '投稿功能已关闭' => 'theme_submit_disabled',
        '站点当前已关闭投稿入口' => 'site_submit_disabled',
        '账号当前无法提交稿件' => 'user_banned',
        '抱歉您的权限不足，暂时无法发布' => 'submit_capability_denied',
        '抱歉您的权限不足，暂时无法编辑此文章' => 'edit_capability_denied',
        '请填写文章标题' => 'title_required',
        '还未填写任何内容' => 'content_required',
        '标题长度不符合要求' => 'title_length',
        '文章内容过少' => 'content_too_short',
        '请选择文章分类' => 'category_required',
        '稿件不存在或没有编辑权限' => 'post_edit_denied',
        '当前稿件状态不允许编辑' => 'post_status_denied',
        '待审核稿件不能保存为草稿' => 'pending_draft_forbidden',
        '文章保存失败，请稍后再试' => 'insert_empty',
        '请求参数格式无效' => 'request_invalid',
        '主题投稿接口不可用，请联系管理员' => 'theme_submit_unavailable',
        '安全校验失败，请刷新页面后重试' => 'nonce_invalid',
        '安全校验不可用，请联系管理员' => 'nonce_unavailable',
        '您没有审核稿件的权限' => 'review_capability_denied',
        '稿件不存在、已处理或您没有权限' => 'review_post_unavailable',
        '该稿件正被其他审核人处理，请稍候再试' => 'review_lock_busy',
        '该稿件不处于待审核状态，请刷新后重试' => 'review_stale_post',
        '审核记录保存失败，请刷新后重试' => 'review_meta_failed',
        '审核请求参数无效' => 'invalid_review_input',
        '审核状态保存失败，请刷新后重试' => 'review_status_failed',
        '内容已审核发布' => 'review_approved',
        '已驳回此内容' => 'review_rejected',
        '已退回作者修改' => 'review_returned',
    );
    $message = (string) $message;
    return isset($codes[$message]) ? $codes[$message] : ($message === '' ? 'empty' : 'unspecified');
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
    if (function_exists('zsr_log')) {
        zsr_log($success ? 'info' : 'warning', 'ajax.response', array(
            'success'   => (bool) $success,
            'post_id'   => isset($payload['post_id']) ? (int) $payload['post_id'] : 0,
            'data_keys' => array_keys($payload),
            'reason_code' => zsr_ajax_reason_code($message),
        ));
    }
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
    if (isset($_REQUEST[$name]) && !is_scalar($_REQUEST[$name])) {
        zsr_ajax_response(false, '安全校验失败，请刷新页面后重试');
    }
    if (function_exists('zsr_log')) {
        zsr_log('debug', 'ajax.nonce_check', array('action' => $action, 'field' => $name));
    }
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
        if (function_exists('zsr_log')) {
            zsr_log('warning', 'submit.meta_skipped', array(
                'post_id' => !empty($post->ID) ? (int) $post->ID : 0,
                'reason_code' => 'invalid_post_or_api',
            ));
        }
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
    if (function_exists('zsr_log')) {
        zsr_log('info', 'submit.meta_recorded', array(
            'post_id' => (int) $post->ID,
            'state'   => $state,
            'status'  => (string) $post->post_status,
        ));
    }
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
    if (function_exists('zsr_log')) {
        zsr_log('info', 'submit.theme_delegate', array(
            'action' => $action,
            'draft'  => (bool) $draft,
        ));
    }
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
    $started_at = microtime(true);
    $user_id = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
    $raw_post_id = isset($_POST['posts_id']) ? $_POST['posts_id'] : 0;
    $post_id = is_scalar($raw_post_id) ? absint($raw_post_id) : 0;
    if (function_exists('zsr_log')) {
        zsr_log('info', 'submit.start', array(
            'user_id' => $user_id,
            'post_id' => $post_id,
            'draft'   => (bool) $draft,
        ));
    }
    if ($user_id < 1) {
        zsr_ajax_response(false, '请登录后提交稿件');
    }
    if (!zsr_get_option('zsr_enable', true) || !zsr_get_option('zsr_enable_submit', true)) {
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

    if (!is_scalar($raw_post_id) || !preg_match('/^[0-9]*$/D', (string) $raw_post_id)) {
        zsr_ajax_response(false, '请求参数格式无效');
    }
    foreach (array('post_title', 'post_content', 'tags') as $field) {
        if (isset($_POST[$field]) && !is_scalar($_POST[$field])) {
            zsr_ajax_response(false, '请求参数格式无效');
        }
    }
    if (isset($_POST['category'])) {
        foreach ((array) $_POST['category'] as $category) {
            if (!is_scalar($category)) {
                zsr_ajax_response(false, '请求参数格式无效');
            }
        }
    }
    if ($post_id > 0) {
        $post = function_exists('get_post') ? get_post($post_id) : null;
        if (!$post || $post->post_type !== 'post' || (int) $post->post_author !== $user_id) {
            zsr_ajax_response(false, '稿件不存在或没有编辑权限');
        }
        if (!in_array($post->post_status, array('draft', 'pending'), true)) {
            zsr_ajax_response(false, '当前稿件状态不允许编辑');
        }
        if ($draft && $post->post_status !== 'draft') {
            zsr_ajax_response(false, '待审核稿件不能保存为草稿');
        }
        if (!zsr_current_user_can('new_post_edit', $post_id)) {
            zsr_ajax_response(false, '抱歉您的权限不足，暂时无法编辑此文章');
        }
    }
    if (!function_exists('zib_ajax_new_posts')) {
        if (function_exists('zsr_log')) {
            zsr_log('error', 'submit.theme_unavailable', array(
                'user_id' => $user_id,
                'post_id' => $post_id,
                'reason_code' => 'theme_submit_unavailable',
                'function' => 'zib_ajax_new_posts',
                'duration_ms' => round((microtime(true) - $started_at) * 1000, 2),
            ));
        }
        zsr_ajax_response(false, '主题投稿接口不可用，请联系管理员');
    }
    $_POST['posts_id'] = $post_id;
    if (!isset($_POST['tags'])) {
        $_POST['tags'] = '';
    }
    zsr_delegate_submission_to_theme($draft);
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
