<?php

define('ABSPATH', __DIR__ . '/');
define('ZSR_OPTION', 'zsr_options');
define('ZSR_VERSION', '0.1.0');
define('ZSR_DIR', dirname(__DIR__) . DIRECTORY_SEPARATOR);

function get_option($key, $default = false)
{
    return $default;
}

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
zsr_frontend_assert(zsr_normalize_view('../../wp-config') === 'my', 'unknown view fallback');
zsr_frontend_assert(zsr_normalize_view('') === 'my', 'empty view fallback');

$args = zsr_my_submissions_query_args(12, 0, 100);
zsr_frontend_assert($args['post_type'] === 'post', 'post type restriction');
zsr_frontend_assert($args['author'] === 12, 'author restriction');
zsr_frontend_assert($args['paged'] === 1 && $args['posts_per_page'] === 50, 'pagination bounds');
zsr_frontend_assert($args['post_status'] === array('draft', 'pending', 'publish', 'trash'), 'status visibility');

$empty = zsr_my_submissions_query_args(0, 1, 20);
zsr_frontend_assert($empty['author'] === 0, 'zero user is represented for caller guard');

fwrite(STDOUT, "frontend tests passed\n");
