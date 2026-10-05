<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

define('ABSPATH', __DIR__ . '/');
define('ZSR_OPTION', 'zsr_options');

function get_option($key, $default = false) { return $default; }
function get_current_user_id() { return 12; }
function do_action() {}
function wp_insert_post($postarr, $error = false) { return 101; }
$zsr_test_meta = array();
function update_post_meta($post_id, $key, $value) { global $zsr_test_meta; $zsr_test_meta[$post_id][$key] = $value; }
function delete_post_meta($post_id, $key) { global $zsr_test_meta; unset($zsr_test_meta[$post_id][$key]); }
function get_post_meta($post_id, $key) { global $zsr_test_meta; return isset($zsr_test_meta[$post_id][$key]) ? $zsr_test_meta[$post_id][$key] : 0; }
function get_post($id) { return (object) array('ID' => $id, 'post_author' => 12, 'post_type' => 'post', 'post_status' => 'draft'); }
function is_wp_error() { return false; }
function current_time() { return '2026-10-05 12:00:00'; }
function wp_kses_post($value) { return strip_tags($value, '<p><a><strong><em><ul><ol><li>'); }
function absint($value) { return abs((int) $value); }
function wp_send_json() {}
function wp_nonce_field() { return ''; }
function add_action() {}
function zib_ajax_new_posts()
{
    if ($_POST['action'] !== 'posts_save' || $_REQUEST['action'] !== 'posts_save' || $_REQUEST['_wpnonce'] !== 'native') {
        fwrite(STDERR, "FAIL: native theme action handoff\n");
        exit(1);
    }
}

require_once dirname(__DIR__) . '/inc/core/options.php';
require_once dirname(__DIR__) . '/inc/core/capabilities.php';
require_once dirname(__DIR__) . '/inc/ajax/submit.php';

$_POST = array(
    'post_title'   => '一个合规标题',
    'post_content' => '<p>这是一段满足最小长度的投稿正文。</p><script>alert(1)</script>',
    'category'     => array('3', '0', 'bad'),
    'tags'         => "主题, 插件\n审核",
);
$arr = zsr_submission_postarr(0, 12, false);
if ($arr['post_type'] !== 'post' || $arr['post_status'] !== 'pending' || $arr['post_author'] !== 12) {
    fwrite(STDERR, "FAIL: post payload\n");
    exit(1);
}
if (strpos($arr['post_content'], '<script>') !== false || $arr['post_category'] !== array(3)) {
    fwrite(STDERR, "FAIL: input normalization\n");
    exit(1);
}

zsr_record_theme_submission((object) array('ID' => 101, 'post_type' => 'post', 'post_status' => 'pending'));
if ($zsr_test_meta[101]['zsr_state'] !== 'pending' || $zsr_test_meta[101]['zsr_submit_count'] !== 1) {
    fwrite(STDERR, "FAIL: theme submission metadata\n");
    exit(1);
}

$_POST['action'] = 'zsr_submit';
$_REQUEST['action'] = 'zsr_submit';
$_REQUEST['_wpnonce'] = 'native';
zsr_delegate_submission_to_theme(false);

fwrite(STDOUT, "ajax-submit tests passed\n");
