<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

define('ABSPATH', __DIR__ . '/');
define('ZSR_DIR', dirname(__DIR__) . DIRECTORY_SEPARATOR);
$login_guide = '<div><a href="javascript:;" class="signin-loader but">登录</a><a class="signup-loader but" href="javascript:;">注册</a><script>alert(1)</script></div>';
$login_hooks = array();

function zsr_normalize_view($view) { return 'my'; }
function get_current_user_id() { return 0; }
function zsr_page_id() { return 101; }
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_url($value) { return esc_attr($value); }
function esc_html__($value, $domain) { return esc_attr($value); }
function zib_get_user_singin_page_box($class = 'box-body', $hi = null) { return $GLOBALS['login_guide']; }
function zib_get_sign_url($tab = 'signin') { return 'https://example.test/custom-access?tab=' . $tab; }
function add_action($hook, $callback, $priority = 10) { $GLOBALS['login_hooks'][$hook][$priority][$callback] = true; }
function wp_kses_post($html) { return str_replace('javascript:', '', preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html)); }

$helper = ZSR_DIR . 'inc/frontend/login.php';
if (is_file($helper)) {
    require_once $helper;
}

function login_assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function login_page()
{
    ob_start();
    include ZSR_DIR . 'templates/zsr-submissions.php';
    return ob_get_clean();
}

$html = login_page();
login_assert(strpos($html, 'href="https://example.test/custom-access?tab=signin"') !== false, 'guest submissions login opens the actual theme login route');
login_assert(strpos($html, 'href="https://example.test/custom-access?tab=signup"') !== false, 'guest submissions registration opens the actual theme registration route');
login_assert(strpos($html, 'href=";"') === false && strpos($html, 'javascript:') === false, 'guest submissions emits no broken or executable href');
login_assert(strpos($html, '<script') === false, 'theme markup is still sanitized');
login_assert(strpos($html, 'signin-loader') !== false && strpos($html, 'signup-loader') !== false, 'theme modal triggers are preserved');

$login_guide = '<a class="signin-loader but" href="javascript:;">登录</a>';
$html = login_page();
login_assert(strpos($html, 'signup-loader') === false, 'disabled theme registration stays disabled');

$login_guide = '';
$html = login_page();
login_assert(strpos($html, 'signin-loader') === false && strpos($html, 'wp-login.php') === false, 'closed theme login does not receive a forced link');

fwrite(STDOUT, "frontend login tests passed\n");
