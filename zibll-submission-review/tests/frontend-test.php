<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

define('ABSPATH', __DIR__ . '/');
define('ZSR_OPTION', 'zsr_options');
define('ZSR_VERSION', '0.1.0');
define('ZSR_DIR', dirname(__DIR__) . DIRECTORY_SEPARATOR);
define('ZSR_URL', 'https://example.test/plugin/');

$frontend_options = array();
$frontend_page = false;
$frontend_scripts = array();
$frontend_styles = array();

function get_option($key, $default = false)
{
    return $key === ZSR_OPTION ? $GLOBALS['frontend_options'] : $default;
}

function is_page($id) { return $GLOBALS['frontend_page']; }
function wp_enqueue_script($handle, $url, $dependencies, $version, $footer) { $GLOBALS['frontend_scripts'][] = func_get_args(); }
function wp_enqueue_style($handle, $url, $dependencies, $version, $media = 'all') { $GLOBALS['frontend_styles'][] = func_get_args(); }

require_once dirname(__DIR__) . '/inc/core/options.php';
require_once dirname(__DIR__) . '/inc/frontend/query.php';
require_once dirname(__DIR__) . '/inc/frontend/page-router.php';

function zsr_frontend_assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

zsr_frontend_assert(zsr_normalize_view('my') === 'my', 'known view');
zsr_frontend_assert(zsr_normalize_view('review') === 'review', 'review view');
zsr_frontend_assert(zsr_normalize_view('submit') === 'my' && zsr_normalize_view('edit') === 'my', 'removed submission views fall back to the article list');
zsr_frontend_assert(zsr_normalize_view('../../wp-config') === 'my', 'unknown view fallback');
zsr_frontend_assert(zsr_normalize_view('') === 'my', 'empty view fallback');
zsr_frontend_assert(zsr_normalize_view(array('review')) === 'my', 'array query view is rejected without warnings');
zsr_frontend_assert(zsr_normalize_view(new stdClass()) === 'my', 'object view is rejected without conversion');

$args = zsr_my_submissions_query_args(12, 0, 100);
zsr_frontend_assert($args['post_type'] === 'post', 'post type restriction');
zsr_frontend_assert($args['author'] === 12, 'author restriction');
zsr_frontend_assert($args['paged'] === 1 && $args['posts_per_page'] === 50, 'pagination bounds');
zsr_frontend_assert($args['post_status'] === array('draft', 'pending', 'future', 'publish', 'trash'), 'status visibility');

$empty = zsr_my_submissions_query_args(0, 1, 20);
zsr_frontend_assert($empty['author'] === 0, 'zero user is represented for caller guard');

$frontend_options = array('zsr_page_id' => 101, 'zsr_enable' => true);
zsr_enqueue_frontend_assets();
zsr_frontend_assert($frontend_scripts === array() && $frontend_styles === array(), 'non-plugin pages load no plugin asset');
$frontend_page = true;
$frontend_options['zsr_enable'] = false;
zsr_enqueue_frontend_assets();
zsr_frontend_assert($frontend_scripts === array() && $frontend_styles === array(), 'disabled workflow loads no plugin asset');
$frontend_options['zsr_enable'] = true;
zsr_enqueue_frontend_assets();
zsr_frontend_assert(count($frontend_scripts) === 1 && $frontend_scripts[0][2] === array('jquery'), 'plugin page uses only existing jQuery dependency');
zsr_frontend_assert(count($frontend_styles) === 1 && $frontend_styles[0][0] === 'zsr-frontend-style', 'plugin page loads scoped frontend style');
zsr_frontend_assert(filesize(ZSR_DIR . 'assets/js/zsr-frontend.js') <= 12 * 1024, 'frontend JavaScript stays within 12 KiB budget');

fwrite(STDOUT, "frontend tests passed\n");
