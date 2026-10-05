<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

define('ABSPATH', __DIR__ . '/');
define('ZSR_OPTION', 'zsr_options');
define('ZSR_VERSION', '0.1.0');
define('ZSR_DB_VERSION', 1);

$options = array();
$meta = array();
$review_lock_rows = array();
define('ZSR_REVIEW_LOCK_FIXTURE_ONLY', true);
require_once __DIR__ . '/review-lock-test.php';
$wpdb = new ZsrReviewLockTestDatabase($review_lock_rows);

function get_option($key, $default = false) { global $options; return array_key_exists($key, $options) ? $options[$key] : $default; }
function update_option($key, $value) { global $options; $options[$key] = $value; }
function get_post_meta($id, $key, $single = false) { global $meta; return $meta[$id][$key] ?? ($single ? '' : array()); }
function update_post_meta($id, $key, $value) { global $meta; $meta[$id][$key] = $value; return true; }
function delete_post_meta($id, $key) { global $meta; unset($meta[$id][$key]); return true; }
function current_time($format) { return '2026-10-05 12:00:00'; }
function wp_strip_all_tags($value) { return strip_tags($value); }
function wp_generate_uuid4() { return 'test-token'; }
function wp_insert_post($post, $error = false) { return 101; }
function is_wp_error($value) { return false; }
function get_post($id) { return (object) array('ID' => $id, 'post_type' => 'post', 'post_status' => 'pending', 'post_author' => 99); }
function get_current_user_id() { return 12; }
function wp_get_current_user() { return (object) array('display_name' => '审核人'); }

require_once dirname(__DIR__) . '/inc/core/options.php';
require_once dirname(__DIR__) . '/inc/core/capabilities.php';
require_once dirname(__DIR__) . '/inc/domain/state-machine.php';
require_once dirname(__DIR__) . '/inc/domain/audit-log.php';
require_once dirname(__DIR__) . '/inc/frontend/review-query.php';
require_once dirname(__DIR__) . '/inc/ajax/review.php';

function review_assert($condition, $message) { if (!$condition) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); } }

$bad = zsr_state_transition('pending', 'forged', 'approve', array(), array('post_type' => 'post', 'can_review' => true));
review_assert(!$bad['ok'] && $bad['code'] === 'invalid_source_state', 'forged state rejected');

$disabled = zsr_state_transition('pending', 'pending', 'approve', array('zsr_enable' => false), array('post_type' => 'post', 'can_review' => true));
review_assert($disabled['ok'], 'state machine remains domain-only');

$options[ZSR_OPTION] = array('zsr_enable' => false, 'zsr_enable_review' => true);
review_assert(zsr_can_review(12) === false, 'global review switch');
$options[ZSR_OPTION] = array('zsr_enable' => true, 'zsr_enable_review' => true, 'zsr_allow_self_review' => false, 'zsr_cap_review_others' => array());
review_assert(zsr_review_queue_query_args(12) === false, 'self-review-disabled empty queue');
$options = array();

$approve = zsr_state_transition('pending', 'pending', 'approve', array(), array('post_type' => 'post', 'can_review' => true, 'can_review_others' => true, 'is_other' => true));
review_assert($approve['ok'] && $approve['to_status'] === 'publish', 'approve transition');

$html = zsr_state_transition('pending', 'pending', 'reject', array(), array('post_type' => 'post', 'can_review' => true, 'can_review_others' => true, 'is_other' => true, 'message' => '<script>x</script>原因'));
review_assert($html['ok'] && strpos($html['message'], '<script>') === false, 'review message sanitized');

for ($i = 0; $i < 55; $i++) {
    zsr_append_review_history(101, 'reject', 'pending', 'pending', "意见 {$i}", $i + 1, "审核人 {$i}");
}
$history = zsr_get_review_history(101);
review_assert(count($history) === 50, 'history bound');
review_assert(($history[0]['reviewer_id'] ?? 0) === 1 && ($history[49]['reviewer_id'] ?? 0) === 55, 'history keeps first and latest');

$lock = zsr_acquire_review_lock(101, 12);
review_assert(is_string($lock), 'first request owns database lock');
review_assert(zsr_acquire_review_lock(101, 12) === false, 'same post lock rejects duplicate request');
zsr_release_review_lock(101, 'wrong-token');
review_assert($review_lock_rows['zsr_lock_101']['value'] === $lock, 'wrong owner cannot release lock');
zsr_release_review_lock(101, $lock);
review_assert(!isset($review_lock_rows['zsr_lock_101']), 'owner releases lock');

fwrite(STDOUT, "review tests passed\n");
