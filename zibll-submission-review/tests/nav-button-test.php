<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

define('ABSPATH', __DIR__ . '/');
define('ZSR_OPTION', 'zsr_options');

$nav_options = array('zsr_enable' => true, 'zsr_enable_review' => true, 'zsr_page_id' => 101, 'zsr_cap_review' => array('editor'));
$nav_hooks = array();
function get_option($key, $default = false) { global $nav_options; return $key === ZSR_OPTION ? $nav_options : $default; }
function wp_roles() { return new class { public function get_names() { return array('administrator' => 'Administrator', 'editor' => 'Editor', 'subscriber' => 'Subscriber'); } }; }
function get_current_user_id() { return 13; }
function get_userdata($id) { return $id === 13 ? (object) array('ID' => 13, 'roles' => array('editor')) : ($id === 14 ? (object) array('ID' => 14, 'roles' => array('subscriber')) : false); }
function wp_get_current_user() { return get_userdata(get_current_user_id()); }
function get_permalink($id) { return 'https://example.test/submissions/'; }
function add_query_arg($key, $value, $url = '') { return rtrim($url, '/') . '?'. rawurlencode($key) . '=' . rawurlencode($value); }
function esc_url($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_html__($value, $domain) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) { global $nav_hooks; $nav_hooks[] = array($hook, $callback, $priority, $accepted_args); }
require_once dirname(__DIR__) . '/inc/core/options.php';
require_once dirname(__DIR__) . '/inc/core/capabilities.php';
require_once dirname(__DIR__) . '/inc/frontend/review-query.php';
require_once dirname(__DIR__) . '/inc/frontend/page-router.php';
require_once dirname(__DIR__) . '/inc/frontend/nav-button.php';
function nav_assert($condition, $message) { if (!$condition) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); } }
$hooks = array_filter($nav_hooks, function ($hook) { return $hook[0] === 'zib_nav_radius_button'; });
nav_assert(count($hooks) === 1, 'registers the Zibll radius-button extension point');
$callback = array_values($hooks)[0][1];
$review = call_user_func($callback, '<a class="toggle-radius">模式</a>', 13);
nav_assert(strpos($review, 'zsr-review-nav-button') !== false && strpos($review, 'view=review') !== false, 'configured reviewer receives the review button');
$subscriber = call_user_func($callback, 'BASE', 14);
nav_assert($subscriber === 'BASE', 'unconfigured WordPress role receives no review button');
$guest = call_user_func($callback, 'BASE', 0);
nav_assert($guest === 'BASE', 'guest receives no review button');
fwrite(STDOUT, "nav button tests passed\n");
