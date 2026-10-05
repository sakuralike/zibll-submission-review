<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/i18n-helpers.php';

define('ABSPATH', __DIR__ . '/');
define('ZSR_OPTION', 'zsr_options');

$rn_options = array();
$rn_meta = array();
$rn_posts = array();
$rn_users = array();
$rn_hooks = array();
$rn_cache = array();
$rn_transients = array();
$rn_runtime = array();
$rn_lock_rows = array();
define('ZSR_REVIEW_LOCK_FIXTURE_ONLY', true);
require_once __DIR__ . '/review-lock-test.php';
$wpdb = new ZsrReviewLockTestDatabase($rn_lock_rows);

class WP_Error
{
    private $code;
    public function __construct($code = 'test_error', $message = '') { $this->code = $code; }
    public function get_error_code() { return $this->code; }
    public function get_error_message() { return 'Simulated failure'; }
}

class RnAjaxResponse extends Exception
{
    public $payload;
    public $http_status;
    public function __construct($payload, $http_status)
    {
        parent::__construct('AJAX response');
        $this->payload = $payload;
        $this->http_status = $http_status;
    }
}

function rn_assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function rn_meta_value($value)
{
    return is_array($value) || is_object($value) ? $value : (string) $value;
}

function get_option($key, $default = false) { global $rn_options; return array_key_exists($key, $rn_options) ? $rn_options[$key] : $default; }
function get_post_meta($id, $key, $single = false)
{
    global $rn_meta, $rn_runtime;
    $stored = isset($rn_meta[$id]) ? $rn_meta[$id] : array();
    if (!empty($rn_runtime['simulate_post_cache'])) {
        if (!isset($rn_runtime['meta_cache'][$id])) {
            $rn_runtime['meta_cache'][$id] = $stored;
        }
        $stored = $rn_runtime['meta_cache'][$id];
    }
    if (!array_key_exists($key, $stored)) {
        return $single ? '' : array();
    }
    $value = rn_meta_value($stored[$key]);
    return $single ? $value : array($value);
}
function update_post_meta($id, $key, $value)
{
    global $rn_meta, $rn_runtime;
    $value = wp_unslash($value);
    if ($rn_runtime['meta_failure'] === $key) {
        $rn_runtime['meta_failure'] = '';
        return false;
    }
    if (metadata_exists('post', $id, $key) && get_post_meta($id, $key, true) === rn_meta_value($value)) {
        return false;
    }
    $rn_meta[$id][$key] = $value;
    return true;
}
function delete_post_meta($id, $key) { global $rn_meta; unset($rn_meta[$id][$key]); return true; }
function metadata_exists($type, $id, $key) { global $rn_meta; return isset($rn_meta[$id]) && array_key_exists($key, $rn_meta[$id]); }
function get_post($id)
{
    global $rn_posts, $rn_runtime;
    $id = is_object($id) ? $id->ID : $id;
    if (!empty($rn_runtime['simulate_post_cache']) && isset($rn_runtime['post_cache'][$id])) {
        return clone $rn_runtime['post_cache'][$id];
    }
    $post = isset($rn_posts[$id]) ? clone $rn_posts[$id] : null;
    if (!empty($rn_runtime['simulate_post_cache']) && $post) {
        $rn_runtime['post_cache'][$id] = clone $post;
    }
    return $post;
}
function clean_post_cache($id)
{
    global $rn_runtime;
    unset($rn_runtime['post_cache'][$id], $rn_runtime['meta_cache'][$id]);
    $rn_runtime['post_cache_cleared'] = isset($rn_runtime['post_cache_cleared']) ? $rn_runtime['post_cache_cleared'] + 1 : 1;
}
function get_userdata($id) { global $rn_users; return isset($rn_users[$id]) ? clone $rn_users[$id] : false; }
function get_current_user_id() { return 12; }
function wp_get_current_user() { return get_userdata(get_current_user_id()); }
function current_time($format) { return '2026-10-05 12:00:00'; }
function absint($value) { return abs((int) $value); }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_-]/', '', strtolower($value)); }
function wp_strip_all_tags($value) { return strip_tags(preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', $value)); }
function apply_filters($hook, $translation, ...$args)
{
    return $hook === 'gettext' && $args[1] === 'zib-sub-review' && isset($GLOBALS['rn_translations'][$args[0]])
        ? $GLOBALS['rn_translations'][$args[0]] : $translation;
}
function strip_shortcodes($value) { return preg_replace('/\\[\\/?[a-zA-Z][^\\]]*\\]/', '', $value); }
function wp_unslash($value) { return is_array($value) ? array_map('wp_unslash', $value) : (is_string($value) ? stripslashes($value) : $value); }
function wp_slash($value) { return is_array($value) ? array_map('wp_slash', $value) : (is_string($value) ? addslashes($value) : $value); }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_attr($value) { return esc_html($value); }
function esc_url($value) { return esc_html($value); }
function get_the_modified_date($format, $post) { return $post->post_modified; }
function get_the_author_meta($key, $id) { $user = get_userdata($id); return $user ? $user->$key : ''; }
function wp_kses_post($value) { return strip_tags($value, '<p><a><strong><em>'); }
function wp_nonce_field($action, $name = '_wpnonce', $referer = true, $display = true)
{
    rn_assert($action === 'zsr_review', 'review form nonce uses review action');
    $field = '<input type="hidden" name="' . esc_attr($name) . '" value="rendered-review-nonce">';
    if ($display) {
        echo $field;
    }
    return $field;
}
function is_email($value) { return filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : false; }
function get_permalink($id) { return 'https://example.test/?p=' . (is_object($id) ? $id->ID : $id); }
function zsr_page_url() { return 'https://example.test/submissions/'; }
function add_query_arg($key, $value, $url = '')
{
    $args = is_array($key) ? $key : array($key => $value);
    $url = is_array($key) ? $value : $url;
    return $url . (strpos($url, '?') === false ? '?' : '&') . http_build_query($args);
}
function zib_str_cut($value, $start, $length, $suffix = '') { return function_exists('mb_substr') ? mb_substr($value, $start, $length, 'UTF-8') : substr($value, $start, $length); }
function zib_user_can($user_id, $capability) { return $user_id === 12 && in_array($capability, array('zsr_review', 'zsr_review_others'), true); }
function zib_ajax_verify_nonce($action, $name = '_wpnonce') { rn_assert($action === 'zsr_review' && $name === '_wpnonce', 'review nonce adapter contract'); }
function is_wp_error($value) { return $value instanceof WP_Error; }
function wp_generate_uuid4() { static $counter = 0; return 'notification-test-' . ++$counter; }
function wp_cache_add($key, $value, $group = '', $ttl = 0) { global $rn_cache; if (isset($rn_cache[$group][$key])) { return false; } $rn_cache[$group][$key] = $value; return true; }
function wp_cache_get($key, $group = '') { global $rn_cache; return isset($rn_cache[$group][$key]) ? $rn_cache[$group][$key] : false; }
function wp_cache_delete($key, $group = '') { global $rn_cache; unset($rn_cache[$group][$key]); return true; }
function get_transient($key) { global $rn_transients; return isset($rn_transients[$key]) ? $rn_transients[$key] : false; }
function set_transient($key, $value, $ttl = 0) { global $rn_transients; $rn_transients[$key] = $value; return true; }
function delete_transient($key) { global $rn_transients; unset($rn_transients[$key]); return true; }
function wp_send_json($payload, $status = 200) { throw new RnAjaxResponse($payload, $status); }
function zsr_log($level, $event, $context = array()) { global $rn_runtime; $rn_runtime['logs'][] = array($level, $event, $context); }
function _pz($key, $default = false) { return $default; }

function add_action($tag, $callback, $priority = 10, $accepted_args = 1)
{
    global $rn_hooks;
    $rn_hooks[$tag][$priority][$callback] = $accepted_args;
    return true;
}
function has_action($tag, $callback = false)
{
    global $rn_hooks;
    if (empty($rn_hooks[$tag])) {
        return false;
    }
    if ($callback === false) {
        return true;
    }
    ksort($rn_hooks[$tag]);
    foreach ($rn_hooks[$tag] as $priority => $callbacks) {
        if (array_key_exists($callback, $callbacks)) {
            return $priority;
        }
    }
    return false;
}
function remove_action($tag, $callback, $priority = 10)
{
    global $rn_hooks;
    if (!isset($rn_hooks[$tag][$priority][$callback])) {
        return false;
    }
    unset($rn_hooks[$tag][$priority][$callback]);
    if (!$rn_hooks[$tag][$priority]) {
        unset($rn_hooks[$tag][$priority]);
    }
    return true;
}
function do_action($tag, ...$args)
{
    global $rn_hooks;
    if (empty($rn_hooks[$tag])) {
        return;
    }
    ksort($rn_hooks[$tag]);
    foreach ($rn_hooks[$tag] as $priority => $callbacks) {
        foreach ($callbacks as $callback => $accepted_args) {
            if (isset($rn_hooks[$tag][$priority][$callback])) {
                call_user_func_array($callback, array_slice($args, 0, $accepted_args));
            }
        }
    }
}
function wp_update_post($values, $wp_error = false)
{
    global $rn_posts, $rn_runtime;
    $rn_runtime['updates']++;
    if ($rn_runtime['update_failure'] === 'throw') {
        throw new RuntimeException('Simulated database failure');
    }
    if ($rn_runtime['update_failure'] === 'error') {
        return new WP_Error('db_update_error');
    }
    if ($rn_runtime['update_failure'] === 'zero') {
        return 0;
    }
    $id = $values['ID'];
    $before = get_post($id);
    $rn_posts[$id]->post_status = $values['post_status'];
    $after = get_post($id);
    do_action('transition_post_status', $after->post_status, $before->post_status, $after);
    if ($after->post_status !== $before->post_status) {
        do_action($before->post_status . '_to_' . $after->post_status, $after);
    }
    do_action($after->post_status . '_' . $after->post_type, $id, $after, $before->post_status);
    return $id;
}

function zib_newmsg_pending_to_publish($post) { global $rn_runtime; $rn_runtime['native_msg']++; }
function zib_email_pending_to_publish($post) { global $rn_runtime; $rn_runtime['native_email']++; }
function rn_other_publish_hook($post) { global $rn_runtime; $rn_runtime['other_hook']++; }
function zib_msg_is_allow_receive($id, $type) { global $rn_runtime; rn_assert($type === 'posts', 'message preference category'); return $rn_runtime['receive_msg']; }
function rn_assert_notification_ready()
{
    $history = get_post_meta(101, 'zsr_review_history', true);
    rn_assert(is_array($history) && count($history) === 1, 'notifications run after history has been saved');
    rn_assert(get_post(101)->post_status === $history[0]['to_status'], 'notifications run after post status has been saved');
    rn_assert(get_post_meta(101, 'zsr_reviewed_by', true) === '12', 'WordPress scalar meta reads are strings');
}
class ZibMsg
{
    public static function add($values)
    {
        global $rn_runtime;
        rn_assert_notification_ready();
        $values = wp_unslash($values);
        $rn_runtime['messages'][] = $values;
        if ($rn_runtime['message_failure'] === 'throw') {
            throw new RuntimeException('Simulated message storage failure');
        }
        if ($rn_runtime['message_failure'] === 'error') {
            return new WP_Error('message_storage_error');
        }
        return $rn_runtime['message_failure'] === 'false' ? false : $values;
    }
}
function wp_mail($to, $subject, $body, $headers = '', $attachments = array())
{
    global $rn_runtime;
    rn_assert_notification_ready();
    $rn_runtime['emails'][] = array('to' => $to, 'subject' => $subject, 'body' => $body);
    if ($rn_runtime['mail_failure'] === 'throw') {
        throw new RuntimeException('Simulated mail transport failure');
    }
    return $rn_runtime['mail_failure'] !== 'false';
}

require_once dirname(__DIR__) . '/inc/core/options.php';
require_once dirname(__DIR__) . '/inc/core/capabilities.php';
require_once dirname(__DIR__) . '/inc/domain/state-machine.php';
require_once dirname(__DIR__) . '/inc/domain/audit-log.php';
require_once dirname(__DIR__) . '/inc/frontend/review-query.php';
require_once dirname(__DIR__) . '/inc/ajax/submit.php';
require_once dirname(__DIR__) . '/inc/domain/notification-service.php';
require_once dirname(__DIR__) . '/inc/ajax/review.php';

function rn_reset($settings = array())
{
    global $rn_options, $rn_meta, $rn_posts, $rn_users, $rn_hooks, $rn_cache, $rn_transients, $rn_runtime, $rn_lock_rows, $wpdb;
    $rn_lock_rows = array();
    $wpdb = new ZsrReviewLockTestDatabase($rn_lock_rows);
    $rn_options = array(ZSR_OPTION => $settings);
    $rn_meta = array(101 => array('zsr_state' => 'pending', 'unrelated_meta' => 'preserve'));
    $rn_posts = array(101 => (object) array(
        'ID' => 101,
        'post_type' => 'post',
        'post_status' => 'pending',
        'post_author' => 99,
        'post_title' => '待审核投稿 <script>title</script>',
        'post_content' => '<p>这是投稿摘要。</p><script>unsafe()</script>',
        'post_date' => '2026-10-04 09:00:00',
        'post_modified' => '2026-10-05 12:00:00',
    ));
    $rn_users = array(
        12 => (object) array('ID' => 12, 'display_name' => '审核人', 'user_email' => 'reviewer@example.test', 'roles' => array('editor')),
        99 => (object) array('ID' => 99, 'display_name' => '投稿人', 'user_email' => 'author@example.test', 'roles' => array('contributor')),
    );
    $rn_runtime = array(
        'meta_failure' => '', 'update_failure' => '', 'message_failure' => '', 'mail_failure' => '',
        'receive_msg' => true, 'messages' => array(), 'emails' => array(), 'logs' => array(),
        'updates' => 0, 'native_msg' => 0, 'native_email' => 0, 'other_hook' => 0,
    );
    $rn_cache = array();
    $rn_transients = array();
    $rn_hooks = array();
    add_action('pending_to_publish', 'zib_newmsg_pending_to_publish', 0);
    add_action('pending_to_publish', 'zib_email_pending_to_publish', 99);
    add_action('pending_to_publish', 'rn_other_publish_hook', 25);
    $_POST = array('action' => 'zsr_review', 'post_id' => '101', 'method' => 'approve', '_wpnonce' => 'review-nonce');
    $_REQUEST = array('action' => 'zsr_review', 'msg_s' => 0, 'untouched' => 'request sentinel');
}
function rn_run($method = 'approve')
{
    $_POST['method'] = $method;
    if (in_array($method, array('reject', 'return'), true)) {
        $_POST['msg'] = '请补充稿件出处';
    }
    $request = $_REQUEST;
    try {
        zsr_ajax_review();
    } catch (RnAjaxResponse $response) {
        rn_assert($_REQUEST === $request, 'review and notifications leave REQUEST unchanged');
        rn_assert($response->http_status === (empty($response->payload['error']) ? 200 : 400), 'AJAX HTTP status matches review result');
        return $response->payload;
    }
    rn_assert(false, 'review must produce an AJAX response');
}
function rn_assert_hooks_and_lock($label)
{
    global $rn_runtime, $rn_lock_rows;
    rn_assert(has_action('pending_to_publish', 'zib_newmsg_pending_to_publish') === 0, $label . ': message hook restored at priority zero');
    rn_assert(has_action('pending_to_publish', 'zib_email_pending_to_publish') === 99, $label . ': email hook restored at original priority');
    rn_assert(has_action('pending_to_publish', 'rn_other_publish_hook') === 25, $label . ': unrelated hook retained');
    rn_assert($rn_runtime['native_msg'] === 0 && $rn_runtime['native_email'] === 0, $label . ': native notification hooks did not send');
    rn_assert(!isset($rn_lock_rows['zsr_lock_101']), $label . ': database lock released');
    rn_assert(get_post_meta(101, 'unrelated_meta', true) === 'preserve', $label . ': unrelated metadata retained');
}
function rn_assert_saved($response, $state = 'approved', $status = 'publish')
{
    rn_assert(isset($response['error']) && $response['error'] === false, 'notification result does not fail the review');
    rn_assert(get_post(101)->post_status === $status, 'review target status retained');
    rn_assert(get_post_meta(101, 'zsr_state', true) === $state, 'review target state retained');
    rn_assert(count(zsr_get_review_history(101)) === 1, 'review history retained exactly once');
    rn_assert_hooks_and_lock($state);
}
function rn_assert_warning($response, $channel)
{
    rn_assert(strpos($response['msg'], '审核结果已保存') !== false, 'warning distinguishes saved review from notification failure');
    rn_assert(strpos($response['msg'], $channel) !== false, 'warning names unsuccessful channel');
    rn_assert(strpos($response['msg'], '重复审核') !== false, 'warning discourages duplicate review');
    rn_assert($response['ys'] === 'warning' && $response['reload'] === false, 'channel warning remains visible without page reload');
}

rn_reset();
$response = rn_run();
rn_assert_saved($response);
rn_assert($response['notifications']['msg']['status'] === 'sent' && $response['notifications']['email']['status'] === 'accepted', 'dual-channel success statuses');
rn_assert(count($rn_runtime['messages']) === 1 && count($rn_runtime['emails']) === 1, 'dual-channel notifications sent exactly once');
rn_assert((int) $rn_runtime['messages'][0]['receive_user'] === 99 && $rn_runtime['emails'][0]['to'] === 'author@example.test', 'notifications addressed to author');
rn_assert($rn_runtime['other_hook'] === 1, 'unrelated publish hook still ran');
$duplicate = rn_run();
rn_assert($duplicate['error'] === true && count($rn_runtime['messages']) === 1 && count($rn_runtime['emails']) === 1, 'duplicate approval never sends again');
do_action('pending_to_publish', get_post(101));
rn_assert($rn_runtime['native_msg'] === 1 && $rn_runtime['native_email'] === 1, 'restored native callbacks still function after review');

foreach (array(array('zsr_notify_author' => false), array('zsr_notify_channel' => array())) as $settings) {
    rn_reset($settings);
    $response = rn_run();
    rn_assert_saved($response);
    rn_assert($response['notifications'] === array(), 'disabled notifications have no channel results');
    rn_assert(!$rn_runtime['messages'] && !$rn_runtime['emails'], 'disabled notifications do not leak through native publish hooks');
}
foreach (array('msg', 'email') as $channel) {
    rn_reset(array('zsr_notify_channel' => array($channel)));
    $response = rn_run();
    rn_assert_saved($response);
    rn_assert(array_keys($response['notifications']) === array($channel), 'only selected channel reported');
    rn_assert(count($rn_runtime['messages']) === ($channel === 'msg' ? 1 : 0), 'message channel selection enforced');
    rn_assert(count($rn_runtime['emails']) === ($channel === 'email' ? 1 : 0), 'email channel selection enforced');
}

foreach (array('reject' => array('rejected', 'pending'), 'return' => array('returned', 'draft')) as $method => $target) {
    rn_reset();
    $response = rn_run($method);
    rn_assert_saved($response, $target[0], $target[1]);
    rn_assert(count($rn_runtime['messages']) === 1 && count($rn_runtime['emails']) === 1, $method . ': author notified once on each channel');
}
rn_reset(array('zsr_notify_on_return' => false));
$response = rn_run('return');
rn_assert_saved($response, 'returned', 'draft');
rn_assert($response['notifications'] === array() && !$rn_runtime['messages'] && !$rn_runtime['emails'], 'return notification switch respected');
rn_reset(array('zsr_approve_keep_audit' => true));
$response = rn_run();
rn_assert_saved($response, 'approved', 'pending');
rn_assert(count($rn_runtime['messages']) === 1 && count($rn_runtime['emails']) === 1, 'approval kept pending still notifies once');

foreach (array('zsr_reviewed_by', 'zsr_review_history') as $key) {
    rn_reset();
    $before = $rn_meta;
    $rn_runtime['meta_failure'] = $key;
    $response = rn_run();
    rn_assert($response['error'] === true, 'meta write failure returns review error');
    rn_assert($rn_meta === $before && get_post(101)->post_status === 'pending', 'meta write failure rolls review metadata back');
    rn_assert(!$rn_runtime['messages'] && !$rn_runtime['emails'] && $rn_runtime['updates'] === 0, 'meta write failure does not update status or notify');
    rn_assert_hooks_and_lock('meta write failure');
}
foreach (array('error', 'zero') as $failure) {
    rn_reset();
    $before = $rn_meta;
    $rn_runtime['update_failure'] = $failure;
    $response = rn_run();
    rn_assert($response['error'] === true, 'status write failure returns review error');
    rn_assert($rn_meta === $before && get_post(101)->post_status === 'pending', 'status write failure rolls metadata back');
    rn_assert(!$rn_runtime['messages'] && !$rn_runtime['emails'], 'status write failure does not notify');
    rn_assert_hooks_and_lock('status write ' . $failure);
}
rn_reset();
$rn_runtime['update_failure'] = 'throw';
$caught = false;
try {
    zsr_update_review_status(101, 'publish');
} catch (RuntimeException $error) {
    $caught = true;
}
rn_assert($caught, 'status helper preserves underlying exception');
rn_assert_hooks_and_lock('status helper exception');

foreach (array('false', 'error', 'throw') as $failure) {
    rn_reset();
    $rn_runtime['message_failure'] = $failure;
    $response = rn_run();
    rn_assert_saved($response);
    rn_assert($response['notifications']['msg']['status'] === 'failed' && $response['notifications']['email']['status'] === 'accepted', 'message failure does not prevent email');
    rn_assert(count($rn_runtime['messages']) === 1 && count($rn_runtime['emails']) === 1, 'message failure does not trigger retry or duplicate email');
    rn_assert_warning($response, '站内信');
}
foreach (array('false', 'throw') as $failure) {
    rn_reset();
    $rn_runtime['mail_failure'] = $failure;
    $response = rn_run();
    rn_assert_saved($response);
    rn_assert($response['notifications']['msg']['status'] === 'sent' && $response['notifications']['email']['status'] === 'failed', 'mail failure preserves message success');
    rn_assert(count($rn_runtime['messages']) === 1 && count($rn_runtime['emails']) === 1, 'mail failure does not trigger duplicate delivery');
    rn_assert_warning($response, '邮件');
}
rn_reset();
$rn_runtime['message_failure'] = 'false';
$rn_runtime['mail_failure'] = 'false';
$response = rn_run();
rn_assert_saved($response);
rn_assert($response['notifications']['msg']['status'] === 'failed' && $response['notifications']['email']['status'] === 'failed', 'dual-channel failure is reported without review rollback');
rn_assert_warning($response, '站内信');
rn_assert_warning($response, '邮件');

rn_reset();
$rn_runtime['receive_msg'] = false;
$response = rn_run();
rn_assert_saved($response);
rn_assert($response['notifications']['msg']['status'] === 'skipped' && $response['notifications']['email']['status'] === 'accepted', 'author message opt-out leaves email independent');
rn_assert(!$rn_runtime['messages'] && count($rn_runtime['emails']) === 1, 'author message opt-out prevents storage');
rn_assert_warning($response, '站内信');

rn_reset(array('zsr_allow_self_review' => true));
$rn_posts[101]->post_author = 12;
$response = rn_run('reject');
rn_assert_saved($response, 'rejected', 'pending');
rn_assert(count($rn_runtime['messages']) === 1 && count($rn_runtime['emails']) === 1, 'allowed self-review sends configured author notifications');
rn_assert((int) $rn_runtime['messages'][0]['receive_user'] === 12 && $rn_runtime['emails'][0]['to'] === 'reviewer@example.test', 'self-review addressed to self');
rn_reset();
$rn_posts[101]->post_author = 12;
$response = rn_run();
rn_assert($response['error'] === true && !$rn_runtime['messages'] && !$rn_runtime['emails'], 'forbidden self-review cannot notify');

rn_reset();
unset($rn_users[99]);
$response = rn_run();
rn_assert_saved($response);
rn_assert($response['notifications']['msg']['status'] === 'failed' && $response['notifications']['email']['status'] === 'failed', 'deleted author reported separately for each selected channel');
rn_assert(!$rn_runtime['messages'] && !$rn_runtime['emails'], 'deleted author does not receive delivery attempts');
rn_assert_warning($response, '站内信');
rn_assert_warning($response, '邮件');

rn_reset();
remove_action('pending_to_publish', 'zib_newmsg_pending_to_publish', 0);
remove_action('pending_to_publish', 'zib_email_pending_to_publish', 99);
$response = rn_run();
rn_assert($response['error'] === false, 'review works without native notification callbacks');
rn_assert(has_action('pending_to_publish', 'zib_newmsg_pending_to_publish') === false && has_action('pending_to_publish', 'zib_email_pending_to_publish') === false, 'absent native callbacks are not installed by review');

rn_reset();
$_GET = array('post_id' => '101');
ob_start();
include dirname(__DIR__) . '/templates/parts/view-review-detail.php';
$form = ob_get_clean();
rn_assert(strpos($form, 'class="zsr-review-form"') !== false, 'actual review detail template renders review form');
rn_assert(substr_count($form, 'name="_wpnonce" value="rendered-review-nonce"') === 1, 'actual review form includes returned nonce input exactly once');

foreach (array('publish', 'pending') as $concurrent_status) {
    rn_reset();
    $rn_runtime['simulate_post_cache'] = true;
    $wpdb->before['insert'] = function () use ($concurrent_status) {
        global $rn_posts, $rn_meta, $rn_runtime;
        rn_assert(isset($rn_runtime['post_cache'][101]) && $rn_runtime['post_cache'][101]->post_status === 'pending', 'first review read is cached before lock acquisition');
        rn_assert($rn_runtime['meta_cache'][101]['zsr_state'] === 'pending', 'first review meta read is cached before lock acquisition');
        $rn_posts[101]->post_status = $concurrent_status;
        $rn_meta[101]['zsr_state'] = 'approved';
    };
    $response = rn_run();
    rn_assert($response['error'] === true, 'review rechecks current database result after locking');
    rn_assert($rn_posts[101]->post_status === $concurrent_status && $rn_meta[101]['zsr_state'] === 'approved', 'stale reviewer preserves other request result');
    rn_assert($rn_runtime['post_cache_cleared'] === 1, 'post and meta caches are invalidated after lock acquisition');
    rn_assert(!$rn_runtime['messages'] && !$rn_runtime['emails'] && $rn_runtime['updates'] === 0, 'stale reviewer does not write status or send notifications');
    rn_assert_hooks_and_lock('stale cached review');
}

foreach (array(array('post_id' => array('101')), array('post_id' => '-101'), array('post_id' => '1e2'), array('method' => array('approve')), array('msg' => array('invalid'))) as $malformed) {
    rn_reset();
    $_POST = array_replace($_POST, array('method' => 'approve'), $malformed);
    try {
        zsr_ajax_review();
        rn_assert(false, 'malformed request must terminate');
    } catch (RnAjaxResponse $response) {
        rn_assert($response->payload['error'] === true && $response->http_status === 400, 'malformed review input is rejected');
        rn_assert($rn_runtime['updates'] === 0 && !$rn_runtime['messages'] && !$rn_runtime['emails'], 'malformed input cannot mutate content or notify');
        rn_assert(!$rn_lock_rows, 'malformed request never acquires a lock');
    }
}

rn_reset(array('zsr_notify_channel' => array('email')));
$rn_runtime['mail_failure'] = 'false';
$rn_translations = array(
    '内容已审核发布' => 'Approved',
    '邮件' => 'email',
    '；审核结果已保存，但%s通知未发送，请联系管理员排查，勿重复审核' => '; saved, but %s notification was not sent.',
);
$response = rn_run('approve');
rn_assert_saved($response);
rn_assert($response['msg'] === 'Approved; saved, but email notification was not sent.', 'translated review result preserves the whole notification-failure sentence');

fwrite(STDOUT, "review notification tests passed\n");
