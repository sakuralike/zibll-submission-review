<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/i18n-helpers.php';

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
function get_userdata($user_id) { return in_array($user_id, array(12, 13, 99), true) ? (object) array('ID' => $user_id, 'display_name' => '审核人', 'roles' => array($user_id === 12 ? 'administrator' : 'editor')) : false; }
function wp_get_current_user() { return get_userdata(get_current_user_id()); }
function wp_roles() { return new class { public function get_names() { return array('administrator' => 'Administrator', 'editor' => 'Editor'); } }; }
function zib_user_can($user_id, $capability) { return false; }

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

zsr_save_options(array('zsr_cap_review' => array('editor'), 'zsr_cap_review_others' => array('editor')));
review_assert(zsr_can_review(13) === true && zsr_can_review_others(13) === true, 'selected WordPress role opens the real review entry despite native theme denial');
review_assert(zsr_can_review(12) === false && zsr_can_review(777) === false, 'review entry denies unselected and nonexistent users');
$queue = zsr_review_queue_query_args(13);
review_assert(is_array($queue) && !isset($queue['author']) && !isset($queue['meta_query']), 'selected reviewer can query every pending post without plugin metadata');
zsr_save_options(array('zsr_cap_review' => array('editor'), 'zsr_cap_review_others' => array('editor'), 'zsr_review_self_only' => true));
review_assert(zsr_can_review(13) === true && zsr_can_review_others(13) === false, 'self-only setting overrides a selected other-review role');
$queue = zsr_review_queue_query_args(13);
review_assert(is_array($queue) && $queue['author'] === 13, 'self-only review queue remains scoped to the selected user');
$meta[101]['zsr_state'] = 'pending';
review_assert(zsr_get_review_post(101, 13) === false && zsr_get_review_post(101, 99)->ID === 101, 'self-only detail entry denies other authors and allows own submissions');
zsr_save_options(array('zsr_cap_review' => array('editor'), 'zsr_cap_review_others' => array('editor')));
$meta[101]['zsr_state'] = '';
review_assert(zsr_get_review_post(101, 13)->ID === 101, 'native pending post without plugin metadata is reviewable');
$meta[101]['zsr_state'] = 'approved';
review_assert(zsr_get_review_post(101, 13)->ID === 101, 'approved pending post remains reviewable until publication');
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
