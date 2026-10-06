<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/i18n-helpers.php';

define('ABSPATH', __DIR__ . '/');
define('ZSR_OPTION', 'zsr_options');

$ql_calls = array();
$ql_history_calls = 0;
$ql_options = array();

function get_option($key, $default = false) { global $ql_options; return $key === ZSR_OPTION ? $ql_options : $default; }
function get_current_user_id() { return 12; }
function wp_get_current_user() { return (object) array('ID' => 12, 'roles' => array('administrator')); }
function get_userdata($user_id) { return $user_id === 12 ? wp_get_current_user() : false; }
function wp_roles() { return new class { public function get_names() { return array('administrator' => 'Administrator'); } }; }
function zib_user_can($user_id, $capability) { return $user_id === 12; }
function get_posts($args) {
    global $ql_calls;
    $ql_calls[] = $args;
    return array((object) array('ID' => 101));
}
function zsr_get_review_history($post_id) {
    global $ql_history_calls;
    $ql_history_calls++;
    return array_fill(0, 50, array('reviewer_id' => 12));
}
function get_post_meta($id, $key, $single = false) { return 'pending'; }
function wp_reset_postdata() {}
function paginate_links($args) { $GLOBALS['ql_pagination'] = $args; return '<a href="?paged=2">2</a>'; }
function wp_kses_post($html) { return $html; }

class WP_Query
{
    public $found_posts = 5000;
    public $max_num_pages = 250;
    public function __construct($args) { $GLOBALS['ql_calls'][] = $args; }
    public function have_posts() { static $calls = 0; return $calls++ === 0; }
}

require_once dirname(__DIR__) . '/inc/core/options.php';
require_once dirname(__DIR__) . '/inc/core/capabilities.php';
require_once dirname(__DIR__) . '/inc/frontend/review-query.php';
require_once dirname(__DIR__) . '/inc/frontend/history-query.php';

function ql_assert($condition, $message) {
    if (!$condition) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
}

foreach (array(-100, 0, 1, 49, 50, 51, PHP_INT_MAX) as $page) {
    $args = zsr_review_queue_query_args(12, $page, 1000);
    ql_assert($args['paged'] >= 1 && $args['paged'] <= 50 && $args['posts_per_page'] === 50, 'review query has bounded pages and batch size');
    ql_assert($args['meta_query'][0]['value'] === array('pending', 'rejected'), 'review query excludes unmanaged posts');
    ql_assert($args['update_post_meta_cache'] && $args['update_post_term_cache'], 'review query primes metadata');
}
ql_assert(zsr_get_review_queue(99, 1) === false && $ql_calls === array(), 'unauthorized user never issues a review query');
foreach (array(-1, 0, 1, 50, 5000, PHP_INT_MAX) as $limit) {
    $items = zsr_get_reviewer_history(12, $limit);
    $query = end($ql_calls);
    ql_assert(count($items) === min(50, max(1, $limit)), 'history results bounded to 50 events');
    ql_assert($query['posts_per_page'] > 0 && $query['posts_per_page'] <= 200, 'history query has bounded candidate size');
}
$_GET = array('paged' => 2);
ob_start();
include dirname(__DIR__) . '/templates/parts/view-review.php';
$html = ob_get_clean();
ql_assert(strpos($html, '50 页') !== false, 'large result set explains page ceiling');
ql_assert(strpos($html, 'theme-pagination') !== false, 'large result set retains navigation within first 50 pages');
ql_assert($GLOBALS['ql_pagination']['total'] === 50 && $GLOBALS['ql_pagination']['current'] === 2, 'pagination uses bounded total');
fwrite(STDOUT, "query limit tests passed (5000-post query fixture)\n");
