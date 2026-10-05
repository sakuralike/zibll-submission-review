<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/i18n-helpers.php';

define('ABSPATH', __DIR__ . '/');
define('ZSR_OPTION', 'zsr_options');

set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

$ss_checks = 0;
$ss_runtime = array();

class SsResponse extends RuntimeException
{
    public $payload;
    public $status;

    public function __construct($payload, $status)
    {
        parent::__construct('AJAX response');
        $this->payload = $payload;
        $this->status = $status;
    }
}

function ss_assert($condition, $message)
{
    global $ss_checks;
    $ss_checks++;
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function ss_reset()
{
    global $ss_runtime;
    $ss_runtime = array(
        'user_id' => 12,
        'options' => array(),
        'denied_caps' => array(),
        'theme_enabled' => true,
        'closed' => false,
        'banned' => false,
        'native_calls' => array(),
        'writes' => 0,
        'hooks' => array(),
        'logs' => array(),
        'posts' => array(
            101 => (object) array('ID' => 101, 'post_author' => 12, 'post_type' => 'post', 'post_status' => 'draft', 'post_title' => 'Draft <title>', 'post_content' => '<p>Draft content</p>'),
            102 => (object) array('ID' => 102, 'post_author' => 12, 'post_type' => 'post', 'post_status' => 'pending', 'post_title' => 'Pending title', 'post_content' => '<p>Pending content</p>'),
        ),
    );
    $_GET = array();
    $_POST = array();
    $_REQUEST = array();
}

function get_option($key, $default = false) { global $ss_runtime; return $key === ZSR_OPTION ? $ss_runtime['options'] : $default; }
function get_current_user_id() { global $ss_runtime; return $ss_runtime['user_id']; }
function _pz($key, $default = false) { global $ss_runtime; return $key === 'post_article_s' ? $ss_runtime['theme_enabled'] : $default; }
function zib_current_user_can($capability, ...$args) { global $ss_runtime; return !in_array($capability, $ss_runtime['denied_caps'], true); }
function zib_is_close_sign() { global $ss_runtime; return $ss_runtime['closed']; }
function zib_user_is_ban($user_id) { global $ss_runtime; return $ss_runtime['banned']; }
function get_post($post_id) { global $ss_runtime; return isset($ss_runtime['posts'][$post_id]) ? $ss_runtime['posts'][$post_id] : null; }
function absint($value) { return abs((int) $value); }
function wp_send_json($payload, $status = 200) { throw new SsResponse($payload, $status); }
function wp_insert_post($postarr, $error = false) { global $ss_runtime; $ss_runtime['writes']++; return 501; }
function update_post_meta($post_id, $key, $value) { global $ss_runtime; $ss_runtime['writes']++; return true; }
function do_action() { global $ss_runtime; $ss_runtime['writes']++; }
function add_action($hook, $callback, $priority = 10) { global $ss_runtime; $ss_runtime['hooks'][$hook] = $callback; }
function zsr_log($level, $event, $context = array()) { global $ss_runtime; $ss_runtime['logs'][] = array('level' => $level, 'event' => $event, 'context' => $context); }
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_textarea($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function wp_dropdown_categories($args) { echo '<select name="category[]"><option value="3">Category</option></select>'; }
function wp_editor($content, $id, $options) { echo '<textarea name="' . esc_attr($options['textarea_name']) . '">' . esc_textarea($content) . '</textarea>'; }
function wp_nonce_field($action, $name = '_wpnonce', $referer = true, $display = true)
{
    $input = '<input type="hidden" name="' . esc_attr($name) . '" value="' . esc_attr($action . '-nonce') . '">';
    if ($display) {
        echo $input;
    }
    return $input;
}
function zib_ajax_verify_nonce($action, $name = '_wpnonce')
{
    if (!isset($_REQUEST[$name]) || !hash_equals($action . '-nonce', $_REQUEST[$name])) {
        zsr_ajax_response(false, '安全校验失败，请刷新页面后重试');
    }
}
function zib_ajax_new_posts()
{
    global $ss_runtime;
    $ss_runtime['native_calls'][] = $_POST;
    ss_assert($_REQUEST['action'] === $_POST['action'], 'native request and post action are consistent');
    ss_assert($_POST['_wpnonce'] === $_POST['action'] . '-nonce', 'native nonce remains available');
    ss_assert(isset($ss_runtime['hooks']['new_add_posts'], $ss_runtime['hooks']['new_edit_posts']), 'native save metadata hooks remain registered');
    wp_send_json(array('native' => true, 'error' => false));
}

ss_reset();
require_once dirname(__DIR__) . '/inc/core/options.php';
require_once dirname(__DIR__) . '/inc/core/capabilities.php';
require_once dirname(__DIR__) . '/inc/ajax/submit.php';

function ss_form($view = 'submit', $post_id = 0)
{
    $_GET = $post_id ? array('post_id' => $post_id) : array();
    ob_start();
    include dirname(__DIR__) . '/templates/parts/view-submit.php';
    return ob_get_clean();
}

function ss_run($action, $changes = array(), $missing_nonce = false)
{
    static $form = null;
    if ($form === null) {
        $form = ss_form();
    }
    preg_match_all('/<input[^>]*name="([^"]+)"[^>]*value="([^"]*)"[^>]*>/', $form, $matches, PREG_SET_ORDER);
    $fields = array();
    foreach ($matches as $match) {
        $fields[$match[1]] = html_entity_decode($match[2], ENT_QUOTES, 'UTF-8');
    }
    $nonce_field = '_wpnonce_' . substr($action, 4);
    $fields['_wpnonce'] = $fields[$action === 'zsr_draft' ? '_wpnonce_posts_draft' : '_wpnonce_posts_save'];
    $_POST = array_replace($fields, array(
        'action' => $action,
        'posts_id' => 0,
        'post_title' => '合规投稿标题',
        'post_content' => '<p>未被插件重写的正文与媒体内容。</p>',
        'category' => array('3'),
        'tags' => '标签',
        'pay' => array('sentinel' => 'theme-owned'),
    ), $changes);
    if ($missing_nonce) {
        unset($_POST[$nonce_field]);
    }
    $_REQUEST = $_POST;
    try {
        call_user_func('zsr_ajax_' . substr($action, 4));
    } catch (SsResponse $response) {
        return $response;
    }
    ss_assert(false, 'AJAX endpoint must return a response');
}

function ss_denied($action, $changes, $reason, $missing_nonce = false)
{
    global $ss_runtime;
    $response = ss_run($action, $changes, $missing_nonce);
    ss_assert($response->status === 400 && $response->payload['error'] === true, $reason . ' is rejected');
    ss_assert(zsr_ajax_reason_code($response->payload['msg']) === $reason, $reason . ' has a stable response code');
    ss_assert(!$ss_runtime['native_calls'] && $ss_runtime['writes'] === 0, $reason . ' cannot write or delegate');
}

foreach (array('submit', 'edit') as $view) {
    $form = ss_form($view, $view === 'edit' ? 101 : 0);
    foreach (array('zsr_submit' => '_wpnonce_submit', 'zsr_update' => '_wpnonce_update', 'zsr_draft' => '_wpnonce_draft', 'posts_save' => '_wpnonce_posts_save', 'posts_draft' => '_wpnonce_posts_draft') as $action => $name) {
        ss_assert(substr_count($form, 'name="' . $name . '" value="' . $action . '-nonce"') === 1, $view . ' form outputs ' . $name . ' exactly once');
    }
}

foreach (array(array('zsr_submit', 0), array('zsr_draft', 0), array('zsr_update', 101), array('zsr_draft', 101), array('zsr_update', 102)) as $case) {
    ss_reset();
    $response = ss_run($case[0], array('posts_id' => (string) $case[1]));
    ss_assert($response->status === 200 && !empty($response->payload['native']), $case[0] . ' reaches native flow for post ' . $case[1]);
    ss_assert(count($ss_runtime['native_calls']) === 1 && $ss_runtime['writes'] === 0, 'plugin never writes instead of native handler');
    $native = $ss_runtime['native_calls'][0];
    ss_assert($native['action'] === ($case[0] === 'zsr_draft' ? 'posts_draft' : 'posts_save'), 'correct native action selected');
    ss_assert($native['posts_id'] === $case[1], 'native post id is the checked id');
    ss_assert($native['post_content'] === '<p>未被插件重写的正文与媒体内容。</p>' && $native['pay'] === array('sentinel' => 'theme-owned'), 'theme content and payment payloads remain unchanged');
}

foreach (array('zsr_submit', 'zsr_update', 'zsr_draft') as $action) {
    ss_reset();
    ss_denied($action, array(), 'nonce_invalid', true);
    ss_reset();
    ss_denied($action, array('_wpnonce_' . substr($action, 4) => 'wrong-nonce'), 'nonce_invalid');
    ss_reset();
    ss_denied($action, array('_wpnonce_' . substr($action, 4) => array('malformed')), 'nonce_invalid');
    foreach (array('zsr_enable', 'zsr_enable_submit') as $option) {
        ss_reset();
        $ss_runtime['options'][$option] = false;
        ss_denied($action, array(), 'plugin_submit_disabled');
    }
    ss_reset();
    $ss_runtime['user_id'] = 0;
    ss_denied($action, array(), 'not_logged_in');
}

foreach (array('zsr_submit', 'new_post_add', 'new_post_edit') as $capability) {
    ss_reset();
    $ss_runtime['denied_caps'][] = $capability;
    ss_denied('zsr_update', array('posts_id' => 101), $capability === 'new_post_edit' ? 'edit_capability_denied' : 'submit_capability_denied');
}

ss_reset();
$ss_runtime['posts'][101]->post_author = 99;
ss_denied('zsr_update', array('posts_id' => 101), 'post_edit_denied');
ss_reset();
ss_denied('zsr_update', array('posts_id' => 999), 'post_edit_denied');

foreach (array('page', 'revision', 'attachment') as $type) {
    ss_reset();
    $ss_runtime['posts'][101]->post_type = $type;
    ss_denied('zsr_update', array('posts_id' => 101), 'post_edit_denied');
}
foreach (array('publish', 'trash', 'future', 'private', 'auto-draft') as $status) {
    ss_reset();
    $ss_runtime['posts'][101]->post_status = $status;
    ss_denied('zsr_update', array('posts_id' => 101), 'post_status_denied');
}
ss_reset();
ss_denied('zsr_draft', array('posts_id' => 102), 'pending_draft_forbidden');

foreach (array(
    array('posts_id' => array('101')),
    array('posts_id' => '101junk'),
    array('posts_id' => '-101'),
    array('post_title' => array('title')),
    array('post_content' => array('content')),
    array('tags' => array('tag')),
    array('category' => array(array('3'))),
) as $changes) {
    ss_reset();
    ss_denied('zsr_submit', $changes, 'request_invalid');
}

foreach (array('theme_enabled' => 'theme_submit_disabled', 'closed' => 'site_submit_disabled', 'banned' => 'user_banned') as $flag => $reason) {
    ss_reset();
    $ss_runtime[$flag] = $flag !== 'theme_enabled';
    ss_denied('zsr_submit', array(), $reason);
}

ss_reset();
$response = ss_run('zsr_submit', array('tags' => null));
ss_assert($response->status === 200 && $ss_runtime['native_calls'][0]['tags'] === '', 'missing tags default avoids native undefined input warning');

restore_error_handler();
fwrite(STDOUT, "submission security tests passed ({$ss_checks} assertions)\n");
