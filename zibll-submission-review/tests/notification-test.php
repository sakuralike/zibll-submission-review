<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

define('ABSPATH', __DIR__ . '/');
define('ZSR_OPTION', 'zsr_options');

$notify_options = array();
$notify_users = array();
$notify_messages = array();
$notify_mails = array();
$notify_logs = array();
$notify_message_result = 'success';
$notify_mail_result = 'success';
$notify_receive = true;
$notify_slash_calls = 0;
$notify_assertions = 0;
$notify_current_user = 12;

function get_option($key, $default = false)
{
    global $notify_options;
    return $key === ZSR_OPTION ? $notify_options : $default;
}

function get_userdata($id)
{
    global $notify_users;
    return $notify_users[$id] ?? false;
}

function get_current_user_id() { global $notify_current_user; return $notify_current_user; }
function current_time($format) { return '2026-10-05 12:00:00'; }
function is_wp_error($value) { return false; }
function get_bloginfo($key = '') { return '测试站点'; }
function strip_shortcodes($value) { return preg_replace('/\[\/?[a-zA-Z_][^\]]*\]/', '', $value); }
function is_email($value) { return filter_var($value, FILTER_VALIDATE_EMAIL); }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function esc_attr($value) { return esc_html($value); }
function esc_url($value) { return esc_html($value); }
function zsr_page_url() { return 'https://example.test/submissions'; }
function get_permalink($id) { return 'https://example.test/article/' . (is_object($id) ? $id->ID : $id); }

function add_query_arg($key, $value = null, $url = null)
{
    if (is_array($key)) {
        $args = $key;
        $url = $value;
    } else {
        $args = array($key => $value);
    }
    return $url . (strpos($url, '?') === false ? '?' : '&') . http_build_query($args);
}

function wp_strip_all_tags($value, $remove_breaks = false)
{
    $value = preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $value);
    $value = strip_tags($value);
    return trim($remove_breaks ? preg_replace('/[\r\n\t ]+/', ' ', $value) : $value);
}

function wp_slash($value)
{
    global $notify_slash_calls;
    $notify_slash_calls++;
    return is_array($value) ? array_map('wp_slash', $value) : (is_string($value) ? addslashes($value) : $value);
}

function wp_unslash($value)
{
    return is_array($value) ? array_map('wp_unslash', $value) : (is_string($value) ? stripslashes($value) : $value);
}

function zib_str_cut($value, $start = 0, $length = 100, $suffix = '...')
{
    $characters = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);
    return implode('', array_slice($characters, $start, $length)) . (count($characters) > $length ? $suffix : '');
}

function zib_msg_is_allow_receive($id, $type = '')
{
    global $notify_receive;
    return $notify_receive;
}

function wp_mail($to, $subject, $message, $headers = '', $attachments = array())
{
    global $notify_mails, $notify_mail_result;
    $notify_mails[] = compact('to', 'subject', 'message', 'headers');
    if ($notify_mail_result === 'exception') {
        throw new RuntimeException('EXCEPTION_PRIVATE_SENTINEL author-private@example.test');
    }
    if ($notify_mail_result === 'error') {
        throw new Error('ERROR_PRIVATE_SENTINEL author-private@example.test');
    }
    return $notify_mail_result !== 'false';
}

function zsr_log($level, $event, $context = array())
{
    global $notify_logs;
    $notify_logs[] = compact('level', 'event', 'context');
    return true;
}

require_once dirname(__DIR__) . '/inc/core/options.php';
require_once dirname(__DIR__) . '/inc/domain/notification-service.php';

function notify_assert($condition, $message)
{
    global $notify_assertions;
    $notify_assertions++;
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function notify_reset($overrides = array())
{
    global $notify_options, $notify_users, $notify_messages, $notify_mails, $notify_logs;
    global $notify_message_result, $notify_mail_result, $notify_receive, $notify_slash_calls;
    global $notify_current_user;
    $notify_options = array_replace(array(
        'zsr_notify_author' => true,
        'zsr_notify_channel' => array('msg', 'email'),
        'zsr_notify_on_return' => true,
        'zsr_notify_include_content' => true,
        'zsr_log_enable' => true,
        'zsr_log_level' => 'debug',
        'zsr_page_id' => 700,
    ), $overrides);
    $notify_users = array(99 => (object) array('ID' => 99, 'display_name' => '作者', 'user_email' => 'author-private@example.test'));
    $notify_messages = array();
    $notify_mails = array();
    $notify_logs = array();
    $notify_message_result = 'success';
    $notify_mail_result = 'success';
    $notify_receive = true;
    $notify_slash_calls = 0;
    $notify_current_user = 12;
}

function notify_post($status = 'publish')
{
    return (object) array(
        'ID' => 101,
        'post_type' => 'post',
        'post_status' => $status,
        'post_author' => 99,
        'post_title' => '稿件标题 <img src=x onerror="TITLE_PRIVATE_SENTINEL"> C:\\draft\\article',
        'post_content' => '<p>BODY_PRIVATE_SENTINEL 正文摘要 C:\\draft\\body</p><script>CONTENT_SCRIPT_PRIVATE_SENTINEL</script>',
        'post_date' => '2026-10-05 11:00:00',
        'post_modified' => '2026-10-05 12:00:00',
    );
}

function notify_transition($method = 'approve', $status = 'publish')
{
    return array(
        'ok' => true,
        'method' => $method,
        'from_status' => 'pending',
        'to_status' => $status,
        'message' => 'REASON_PRIVATE_SENTINEL 修改意见 C:\\draft\\reason <svg onload="REASON_XSS_PRIVATE_SENTINEL">',
    );
}

function notify_result($result, $channel, $status)
{
    notify_assert(is_array($result) && isset($result[$channel]), $channel . ' has channel outcome');
    notify_assert(($result[$channel]['status'] ?? '') === $status, $channel . ' outcome ' . $status);
    $code = $result[$channel]['code'] ?? '';
    notify_assert(is_string($code) && preg_match('/^[a-z][a-z0-9_.-]{0,63}$/D', $code), $channel . ' uses safe short code');
    if ($status === 'sent') {
        notify_assert($code === 'message_saved', 'message success reports persisted message');
    } elseif ($status === 'accepted') {
        notify_assert($code === 'mail_accepted', 'mail success reports acceptance, not delivery');
    }
}

function notify_safe_logs()
{
    global $notify_logs;
    notify_assert(count($notify_logs) > 0, 'notification outcomes are logged');
    $output = json_encode($notify_logs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    foreach (array('BODY_PRIVATE_SENTINEL', 'REASON_PRIVATE_SENTINEL', 'TITLE_PRIVATE_SENTINEL', 'EXCEPTION_PRIVATE_SENTINEL', 'ERROR_PRIVATE_SENTINEL', 'author-private@example.test') as $secret) {
        notify_assert(strpos($output, $secret) === false, 'logs exclude private data: ' . $secret);
    }
}

notify_reset();
notify_assert(!class_exists('ZibMsg'), 'unavailable message adapter test precedes fake class definition');
$result = zsr_notify_review_author(notify_post(), notify_transition(), 12);
notify_result($result, 'msg', 'failed');
notify_result($result, 'email', 'accepted');
notify_assert(count($notify_mails) === 1, 'missing message service does not prevent email');
notify_safe_logs();

if (!class_exists('ZibMsg')) {
    class ZibMsg
    {
        public static function add($payload)
        {
            global $notify_messages, $notify_message_result;
            $payload = wp_unslash($payload);
            $notify_messages[] = $payload;
            if ($notify_message_result === 'exception') {
                throw new RuntimeException('EXCEPTION_PRIVATE_SENTINEL author-private@example.test');
            }
            if ($notify_message_result === 'error') {
                throw new Error('ERROR_PRIVATE_SENTINEL author-private@example.test');
            }
            return $notify_message_result === 'false' ? false : $payload;
        }
    }
}

foreach (array('approve' => 'publish', 'reject' => 'pending', 'return' => 'draft') as $method => $status) {
    notify_reset();
    $_REQUEST = array('msg_s' => 'unchanged', 'msg' => 'REQUEST_PRIVATE_SENTINEL', 'nested' => array('token' => 'TOKEN_PRIVATE_SENTINEL'));
    $request_before = $_REQUEST;
    $result = zsr_notify_review_author(notify_post($status), notify_transition($method, $status), 12);
    notify_result($result, 'msg', 'sent');
    notify_result($result, 'email', 'accepted');
    notify_assert(count($notify_messages) === 1 && count($notify_mails) === 1, $method . ' sends each channel once');
    notify_assert((int) $notify_messages[0]['receive_user'] === 99 && $notify_messages[0]['type'] === 'posts', 'message addresses author and posts category');
    notify_assert($notify_mails[0]['to'] === 'author-private@example.test', 'email addresses author');
    notify_assert(stripos(implode('\n', (array) $notify_mails[0]['headers']), 'Content-Type: text/html') !== false, 'email uses explicit HTML headers');
    notify_assert($_REQUEST === $request_before, 'notification does not mutate request');
    notify_assert($notify_slash_calls > 0, 'message payload is slashed for theme adapter');
    $message = $notify_messages[0]['content'];
    notify_assert(strpos($message, 'C:\\draft\\reason') !== false, 'message preserves opinion backslashes after theme unslash');
    notify_assert(strpos($message, 'BODY_PRIVATE_SENTINEL') !== false, 'enabled content summary is included');
    notify_assert(strpos($message, 'REASON_PRIVATE_SENTINEL') !== false, 'opinion is included');
    foreach (array($notify_messages[0]['title'], $message, $notify_mails[0]['subject'], $notify_mails[0]['message']) as $rendered) {
        notify_assert(!preg_match('/<(?:script|svg|img)\b/i', $rendered), 'user content cannot inject executable HTML');
    }
    if ($status === 'publish') {
        notify_assert(strpos($message, 'https://example.test/article/101') !== false, 'published notification links to permalink');
    } else {
        notify_assert(strpos($message, 'https://example.test/submissions') !== false, 'nonpublic notification links to plugin page');
        notify_assert(strpos($message, 'https://example.test/article/101') === false, 'nonpublic notification avoids inaccessible permalink');
    }
    notify_safe_logs();
}

notify_reset();
$result = zsr_notify_review_author(notify_post('pending'), notify_transition('approve', 'pending'), 12);
notify_result($result, 'msg', 'sent');
$pending_notice = $notify_messages[0]['title'] . $notify_messages[0]['content'] . $notify_mails[0]['subject'] . $notify_mails[0]['message'];
notify_assert(strpos($pending_notice, '已发布') === false && strpos($pending_notice, '审核发布') === false, 'approval retaining pending status does not claim publication');
notify_assert(strpos($pending_notice, '审核') !== false, 'pending approval still names review outcome');

foreach (array('msg', 'email') as $selected) {
    notify_reset(array('zsr_notify_channel' => array($selected)));
    $result = zsr_notify_review_author(notify_post(), notify_transition(), 12);
    notify_assert(array_keys($result) === array($selected), 'only selected channel is returned');
    notify_assert(count($notify_messages) === ($selected === 'msg' ? 1 : 0), 'unselected message channel is not called');
    notify_assert(count($notify_mails) === ($selected === 'email' ? 1 : 0), 'unselected email channel is not called');
}

foreach (array(array('zsr_notify_author' => false), array('zsr_notify_author' => 'false'), array('zsr_notify_channel' => array()), array('zsr_notify_channel' => array('invalid'))) as $disabled) {
    notify_reset($disabled);
    $result = zsr_notify_review_author(notify_post(), notify_transition(), 12);
    notify_assert($result === array(), 'disabled notifications return no channel outcomes');
    notify_assert(!$notify_messages && !$notify_mails, 'disabled notifications produce no sends');
}

notify_reset(array('zsr_notify_on_return' => false));
$result = zsr_notify_review_author(notify_post('draft'), notify_transition('return', 'draft'), 12);
notify_assert($result === array() && !$notify_messages && !$notify_mails, 'return notification switch is enforced');
$result = zsr_notify_review_author(notify_post('pending'), notify_transition('reject', 'pending'), 12);
notify_result($result, 'msg', 'sent');
notify_result($result, 'email', 'accepted');

notify_reset();
$notify_current_user = 99;
$result = zsr_notify_review_author(notify_post('pending'), notify_transition('reject', 'pending'), 99);
notify_result($result, 'msg', 'sent');
notify_result($result, 'email', 'accepted');
notify_assert(count($notify_messages) === 1 && count($notify_mails) === 1, 'self review still notifies the author');

foreach (array(99, 0) as $author_id) {
    notify_reset();
    $notify_users = array();
    $post = notify_post();
    $post->post_author = $author_id;
    $result = zsr_notify_review_author($post, notify_transition(), 12);
    notify_result($result, 'msg', 'failed');
    notify_result($result, 'email', 'failed');
    notify_assert(!$notify_messages && !$notify_mails, 'missing author receives no notification attempts');
    notify_safe_logs();
}

notify_reset();
$notify_users[99]->user_email = 'invalid-email';
$result = zsr_notify_review_author(notify_post(), notify_transition(), 12);
notify_result($result, 'msg', 'sent');
notify_result($result, 'email', 'failed');
notify_assert(!$notify_mails, 'invalid email never reaches wp_mail');
notify_safe_logs();

foreach (array('false', 'exception', 'error') as $failure) {
    notify_reset();
    $notify_mail_result = $failure;
    $result = zsr_notify_review_author(notify_post(), notify_transition(), 12);
    notify_result($result, 'msg', 'sent');
    notify_result($result, 'email', 'failed');
    notify_assert(count($notify_mails) === 1 && count($notify_messages) === 1, 'mail failure does not retry or block messages');
    notify_safe_logs();

    notify_reset();
    $notify_message_result = $failure;
    $result = zsr_notify_review_author(notify_post(), notify_transition(), 12);
    notify_result($result, 'msg', 'failed');
    notify_result($result, 'email', 'accepted');
    notify_assert(count($notify_mails) === 1 && count($notify_messages) === 1, 'message failure does not retry or block mail');
    notify_safe_logs();
}

notify_reset();
$notify_receive = false;
$result = zsr_notify_review_author(notify_post(), notify_transition(), 12);
notify_result($result, 'msg', 'skipped');
notify_result($result, 'email', 'accepted');
notify_assert(!$notify_messages && count($notify_mails) === 1, 'theme message opt-out leaves email independent');
notify_safe_logs();

notify_reset(array('zsr_notify_include_content' => false));
$result = zsr_notify_review_author(notify_post('pending'), notify_transition('reject', 'pending'), 12);
notify_result($result, 'msg', 'sent');
notify_result($result, 'email', 'accepted');
foreach (array($notify_messages[0]['content'], $notify_mails[0]['message']) as $rendered) {
    notify_assert(strpos($rendered, 'BODY_PRIVATE_SENTINEL') === false, 'disabled summary omits article content');
    notify_assert(strpos($rendered, 'REASON_PRIVATE_SENTINEL') !== false, 'disabled summary preserves review opinion');
    notify_assert(strpos($rendered, '稿件标题') !== false, 'disabled summary preserves article title');
    notify_assert(strpos($rendered, 'https://example.test/submissions') !== false, 'disabled summary preserves author link');
}
notify_safe_logs();

fwrite(STDOUT, "notification tests passed ({$notify_assertions} assertions)\n");
