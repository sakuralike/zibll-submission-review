<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
if (getenv('ZSR_INTEGRATION_TEST') !== '1' || getenv('WORDPRESS_DB_NAME') !== 'zsr_phase7') {
    fwrite(STDERR, "Refusing to run outside the explicitly marked zsr_phase7 integration database.\n");
    exit(78);
}
$integration_root = realpath((string) getenv('ZSR_WP_ROOT'));
if ($integration_root !== '/var/www/html' || !is_file($integration_root . '/wp-load.php')) {
    fwrite(STDERR, "Refusing to run outside the isolated WordPress container.\n");
    exit(78);
}

define('WP_INSTALLING', true);
define('DOING_AJAX', true);
$_SERVER['HTTP_HOST'] = 'zsr-integration.test';
$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/wp-admin/admin-ajax.php';
$integration_mail_attempts = 0;
$integration_assertions = 0;

function wp_mail($to, $subject, $message, $headers = '', $attachments = array())
{
    global $integration_mail_attempts;
    $integration_mail_attempts++;
    return false;
}
function _pz($key, $default = false)
{
    $options = get_option('zibll_options', array());
    return is_array($options) && array_key_exists($key, $options) ? $options[$key] : $default;
}
function _spz($key, $value)
{
    $options = get_option('zibll_options', array());
    $options = is_array($options) ? $options : array();
    $options[$key] = $value;
    return update_option('zibll_options', $options);
}
function zib_user_can($user_id, $capability)
{
    return in_array($capability, array('zsr_submit', 'zsr_review', 'zsr_review_others', 'zsr_manage'), true) && user_can($user_id, 'manage_options');
}
function zib_current_user_can($capability)
{
    return zib_user_can(get_current_user_id(), $capability);
}
function zib_get_template_page_url($template, $args = array())
{
    return add_query_arg($args, home_url('/submissions/'));
}
function zib_ajax_new_posts()
{
    throw new RuntimeException('Licensed Zibll submission is not implemented by this integration adapter.');
}
function zib_get_user_singin_page_box($class = 'box-body', $title = null)
{
    return '<p class="signin-loader">Login</p><script>unsafe()</script>';
}
function zibpay_get_payvip_button($level = 1, $class = '', $text = null)
{
    return '<a class="pay-vip" href="javascript:;" vip-level="' . (int) $level . '">Upgrade</a>';
}
function integration_assert($condition, $message)
{
    global $integration_assertions;
    $integration_assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
function integration_wait($condition, $message, $seconds = 20)
{
    $deadline = microtime(true) + $seconds;
    do {
        clearstatcache();
        if ($condition()) {
            return;
        }
        usleep(20000);
    } while (microtime(true) < $deadline);
    throw new RuntimeException($message);
}
class ZsrIntegrationAjaxEnd extends RuntimeException
{
}
function integration_review($post_id, $method, $message = '')
{
    global $integration_http_status;
    $integration_http_status = null;
    $_POST = wp_slash(array('action' => 'zsr_review', 'post_id' => (string) $post_id, 'method' => $method, 'msg' => $message, '_wpnonce' => wp_create_nonce('zsr_review')));
    $_REQUEST = $_POST;
    ob_start();
    try {
        zsr_ajax_review();
        ob_end_clean();
        throw new RuntimeException('AJAX review returned without a WordPress JSON response.');
    } catch (ZsrIntegrationAjaxEnd $response) {
        $json = ob_get_clean();
        $payload = json_decode($json, true);
        integration_assert(is_array($payload) && isset($payload['error']) && is_bool($payload['error']), 'AJAX uses the expected JSON error protocol');
        integration_assert($integration_http_status === ($payload['error'] ? 400 : 200), 'AJAX HTTP status matches the JSON result');
        integration_assert(isset($payload['msg']) && is_string($payload['msg']), 'AJAX response includes a readable message');
        return $payload;
    } catch (Throwable $error) {
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        throw $error;
    }
}
function integration_post($author, $run, $suffix)
{
    $post_id = wp_insert_post(array(
        'post_type' => 'post', 'post_status' => 'pending', 'post_author' => $author,
        'post_title' => 'ZSR integration ' . $run . ' ' . $suffix,
        'post_content' => '<p>Isolated integration content ' . esc_html($run . '-' . $suffix) . '</p>',
    ), true);
    integration_assert(!is_wp_error($post_id) && $post_id > 0, 'real WordPress post insertion succeeds');
    update_post_meta($post_id, 'zsr_state', 'pending');
    update_post_meta($post_id, '_zsr_integration_run', $run);
    update_post_meta($post_id, 'unrelated_metadata', array('preserve' => $run));
    return (int) $post_id;
}

try {
    require $integration_root . '/wp-load.php';
    integration_assert(defined('DB_NAME') && DB_NAME === 'zsr_phase7' && $wpdb->get_var('SELECT DATABASE()') === 'zsr_phase7', 'loaded WordPress must use only the isolated database');
    $worker = isset($argv[1]) && $argv[1] === '--lock-worker';
    if (!is_blog_installed()) {
        integration_assert(!$worker, 'workers must not install WordPress');
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $installed = wp_install('ZSR isolated integration', 'zsr_phase7_admin', 'admin@zsr-integration.test', false, '', bin2hex(random_bytes(24)));
        integration_assert(is_array($installed) && !empty($installed['user_id']), 'WordPress test installation succeeds');
    }
    wp_installing(false);
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    $plugin = 'zibll-submission-review/zibll-submission-review.php';
    if ($worker) {
        require_once dirname(__DIR__) . '/zibll-submission-review.php';
        $barrier = isset($argv[2]) ? realpath($argv[2]) : false;
        $temporary_root = rtrim((string) realpath(sys_get_temp_dir()), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        integration_assert($barrier && strpos($barrier, $temporary_root . 'zsr-wp-integration-') === 0 && dirname($barrier) === rtrim($temporary_root, DIRECTORY_SEPARATOR), 'worker barrier must be an isolated temporary directory');
        $post_id = isset($argv[3]) ? $argv[3] : '';
        integration_assert(ctype_digit($post_id) && (int) $post_id > 0 && (string) (int) $post_id === $post_id, 'worker post ID must be canonical');
        $pid = getmypid();
        touch($barrier . '/ready-' . $pid);
        integration_wait(function () use ($barrier) { return is_file($barrier . '/start'); }, 'worker start barrier timed out');
        $token = zsr_acquire_review_lock($post_id, $pid);
        file_put_contents($barrier . '/result-' . $pid, json_encode(array('acquired' => is_string($token))), LOCK_EX);
        if ($token !== false) {
            integration_wait(function () use ($barrier) { return is_file($barrier . '/release'); }, 'worker release barrier timed out');
            integration_assert(zsr_release_review_lock($post_id, $token) === true, 'winning worker releases its own lock');
        }
        exit(0);
    }

    if (is_plugin_active($plugin)) {
        deactivate_plugins($plugin, true);
    }
    $activated = activate_plugin($plugin, '', false, false);
    integration_assert(!is_wp_error($activated) && is_plugin_active($plugin), 'plugin activates with real WordPress lifecycle');
    integration_assert(is_array(get_option(ZSR_OPTION)) && get_option('zsr_version') === ZSR_VERSION && (int) get_option('zsr_db_version') === ZSR_DB_VERSION, 'activation initializes real options');
    integration_assert(!zsr_is_zibll_theme() && (int) get_option('zsr_activation_blocked') === 1, 'unlicensed theme boundary remains explicitly reported');
    zsr_bootstrap();
    zsr_register_capabilities();
    $run = substr(str_replace('-', '', wp_generate_uuid4()), 0, 12);
    $reviewer_name = "O'Neil C:\\draft\\reviewer";
    $reviewer = wp_insert_user(array('user_login' => 'zsr_reviewer_' . $run, 'user_pass' => bin2hex(random_bytes(24)), 'user_email' => 'reviewer-' . $run . '@zsr-integration.test', 'role' => 'administrator', 'display_name' => wp_slash($reviewer_name)));
    $author = wp_insert_user(array('user_login' => 'zsr_author_' . $run, 'user_pass' => bin2hex(random_bytes(24)), 'user_email' => 'author-' . $run . '@zsr-integration.test', 'role' => 'contributor'));
    integration_assert(!is_wp_error($reviewer) && !is_wp_error($author), 'isolated reviewer and author are created');
    integration_assert(get_userdata($reviewer)->display_name === $reviewer_name, 'reviewer name includes preserved quote and backslashes');
    wp_set_current_user($reviewer);
    $settings = array_replace(zsr_default_options(), array('zsr_notify_author' => false));
    zsr_save_options($settings);
    $page_id = zsr_ensure_frontend_page();
    integration_assert($page_id > 0 && get_post_meta($page_id, '_zsr_created_page', true) === '1', 'plugin page ownership is stored by real WordPress');

    add_filter('wp_die_ajax_handler', function () {
        return function ($message = '', $title = '', $args = array()) { throw new ZsrIntegrationAjaxEnd((string) $message); };
    });
    add_filter('status_header', function ($header, $code) {
        global $integration_http_status;
        $integration_http_status = $code;
        return $header;
    }, 10, 2);
    $mail_baseline = $integration_mail_attempts;
    $approve_id = integration_post($author, $run, 'approve');
    update_post_meta($approve_id, 'zsr_reviewed_by', $reviewer);
    integration_assert(get_post_meta($approve_id, 'zsr_reviewed_by', true) === (string) $reviewer, 'real numeric metadata is read back as a string');
    integration_assert(zsr_update_review_meta_checked($approve_id, 'zsr_reviewed_by', (int) $reviewer), 'checked metadata accepts real numeric-string equivalence');
    $approved = integration_review($approve_id, 'approve');
    integration_assert($approved['error'] === false && get_post_status($approve_id) === 'publish' && get_post_meta($approve_id, 'zsr_state', true) === 'approved', 'AJAX approval persists status and metadata');
    integration_assert(get_post_meta($approve_id, 'zsr_reviewed_by', true) === (string) $reviewer && count(zsr_get_review_history($approve_id)) === 1, 'AJAX approval persists reviewer and one history entry');
    integration_assert(get_post_meta($approve_id, 'zsr_reviewer_name', true) === $reviewer_name && zsr_get_review_history($approve_id)[0]['reviewer_name'] === $reviewer_name, 'reviewer metadata and history retain quotes and backslashes');
    integration_assert(isset($approved['notifications']) && $approved['notifications'] === array(), 'disabled notifications have no delivery results');
    $duplicate = integration_review($approve_id, 'approve');
    integration_assert($duplicate['error'] === true && count(zsr_get_review_history($approve_id)) === 1, 'completed post rejects duplicate approval');

    $reject_id = integration_post($author, $run, 'reject');
    $review_reason = "请补充 C:\\draft\\article 与 O'Neil 的稿件出处";
    $rejected = integration_review($reject_id, 'reject', $review_reason);
    integration_assert($rejected['error'] === false && get_post_status($reject_id) === 'pending' && get_post_meta($reject_id, 'zsr_state', true) === 'rejected', 'AJAX rejection persists pending and rejected state');
    integration_assert(get_post_meta($reject_id, 'zsr_reject_reason', true) === $review_reason && zsr_get_review_history($reject_id)[0]['msg'] === $review_reason, 'AJAX rejection persists quotes and backslashes in reason and history');
    $rejected_again = integration_review($reject_id, 'reject', $review_reason . ' second');
    integration_assert($rejected_again['error'] === false && count(zsr_get_review_history($reject_id)) === 2 && zsr_get_review_history($reject_id)[0]['msg'] === $review_reason, 'appending history preserves existing quote and backslash values');
    integration_assert($integration_mail_attempts === $mail_baseline, 'disabled notifications never attempt email');

    $rollback_id = integration_post($author, $run, 'rollback');
    update_post_meta($rollback_id, 'zsr_reviewer_name', wp_slash($reviewer_name));
    update_post_meta($rollback_id, 'zsr_reject_reason', wp_slash($review_reason));
    $rollback_before = get_post_meta($rollback_id);
    $fail_meta = function ($check, $object_id, $meta_key) use ($rollback_id) {
        return (int) $object_id === $rollback_id && $meta_key === 'zsr_reviewed_by' ? false : $check;
    };
    add_filter('update_post_metadata', $fail_meta, 10, 3);
    $rollback = integration_review($rollback_id, 'approve');
    remove_filter('update_post_metadata', $fail_meta, 10);
    integration_assert($rollback['error'] === true && get_post_status($rollback_id) === 'pending', 'injected metadata failure does not publish');
    integration_assert(get_post_meta($rollback_id) === $rollback_before, 'failed review rolls all existing and newly written metadata back');
    integration_assert($wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", 'zsr_lock_' . $rollback_id)) === null, 'failed review releases its real database lock');

    $cache_post = integration_post($author, $run, 'stale-cache');
    $prime_cache = function ($sql) use ($cache_post) {
        global $wpdb;
        if (strpos($sql, 'INSERT IGNORE INTO ' . $wpdb->options) === 0 && strpos($sql, "'zsr_lock_" . $cache_post . "'") !== false) {
            $wpdb->update($wpdb->posts, array('post_status' => 'publish'), array('ID' => $cache_post), array('%s'), array('%d'));
            $wpdb->update($wpdb->postmeta, array('meta_value' => 'approved'), array('post_id' => $cache_post, 'meta_key' => 'zsr_state'), array('%s'), array('%d', '%s'));
        }
        return $sql;
    };
    add_filter('query', $prime_cache);
    $stale = integration_review($cache_post, 'approve');
    remove_filter('query', $prime_cache);
    integration_assert($stale['error'] === true && get_post_status($cache_post) === 'publish' && get_post_meta($cache_post, 'zsr_state', true) === 'approved', 'lock acquisition invalidates real stale post and metadata caches');
    integration_assert(zsr_get_review_history($cache_post) === array(), 'stale request does not append another review');

    $lock_post = integration_post($author, $run, 'lock');
    $owner = zsr_acquire_review_lock($lock_post, $reviewer);
    integration_assert(is_string($owner) && zsr_acquire_review_lock($lock_post, $author) === false, 'real unique option constraint rejects occupied lock');
    integration_assert(zsr_release_review_lock($lock_post, '') === false && zsr_release_review_lock($lock_post, 'wrong-owner') === false, 'empty and wrong tokens cannot delete real lock');
    integration_assert(zsr_release_review_lock($lock_post, $owner), 'real lock holder can release');
    $expired = (time() - 1) . ':' . $reviewer . ':expired-integration-owner';
    add_option('zsr_lock_' . $lock_post, $expired, '', 'no');
    $replacement = zsr_acquire_review_lock($lock_post, $author);
    integration_assert(is_string($replacement) && zsr_release_review_lock($lock_post, $expired) === false, 'expired lock is replaced without old-owner deletion');
    integration_assert($wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", 'zsr_lock_' . $lock_post)) === $replacement, 'replacement owner remains stored');
    integration_assert(zsr_release_review_lock($lock_post, $replacement), 'replacement owner can release');

    integration_assert(function_exists('proc_open'), 'independent PHP process execution is available');
    $barrier = sys_get_temp_dir() . '/zsr-wp-integration-' . $run;
    integration_assert(mkdir($barrier, 0700), 'isolated concurrency barrier is created');
    $children = array();
    for ($index = 0; $index < 4; $index++) {
        $pipes = array();
        $process = proc_open(array(PHP_BINARY, __FILE__, '--lock-worker', $barrier, (string) $lock_post), array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
        integration_assert(is_resource($process), 'independent WordPress worker starts');
        fclose($pipes[0]);
        $children[] = array($process, $pipes);
    }
    integration_wait(function () use ($barrier) { return count(glob($barrier . '/ready-*')) === 4; }, 'WordPress workers did not reach readiness');
    touch($barrier . '/start');
    integration_wait(function () use ($barrier) { return count(glob($barrier . '/result-*')) === 4; }, 'WordPress workers did not all attempt the database lock');
    $winners = 0;
    foreach (glob($barrier . '/result-*') as $result_file) {
        $result = json_decode(file_get_contents($result_file), true);
        integration_assert(is_array($result) && isset($result['acquired']) && is_bool($result['acquired']), 'worker emits only its acquisition result');
        $winners += $result['acquired'] ? 1 : 0;
    }
    touch($barrier . '/release');
    foreach ($children as $child) {
        $output = stream_get_contents($child[1][1]);
        $errors = stream_get_contents($child[1][2]);
        fclose($child[1][1]);
        fclose($child[1][2]);
        integration_assert(proc_close($child[0]) === 0 && trim($output) === '' && trim($errors) === '', 'independent WordPress worker exits successfully without token output');
    }
    integration_assert($winners === 1, 'four separate PHP connections produce exactly one lock winner');
    integration_assert($wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", 'zsr_lock_' . $lock_post)) === null, 'winning process releases lock after all attempts finish');

    integration_assert(getenv('ZSR_INTEGRATION_TEST') === '1' && DB_NAME === 'zsr_phase7' && $wpdb->get_var('SELECT DATABASE()') === 'zsr_phase7', 'bulk queue fixtures require the isolated test database');
    $queue_seed_started = microtime(true);
    $queue_prefix = 'zsr-queue-' . $run . '-';
    $queue_epoch = time() - 8000;
    for ($batch = 0; $batch < 10; $batch++) {
        $queue_rows = array();
        for ($offset = 0; $offset < 500; $offset++) {
            $sequence = $batch * 500 + $offset;
            $modified = gmdate('Y-m-d H:i:s', $queue_epoch + $sequence);
            $queue_rows[] = $wpdb->prepare(
                "(%d, %s, %s, %s, %s, '', 'pending', '', '', %s, %s, '', 'post', %s)",
                $author, $modified, $modified, 'Isolated queue integration fixture ' . $run,
                'ZSR queue integration ' . $run . ' ' . $sequence, $modified, $modified, $queue_prefix . $sequence
            );
        }
        $inserted = $wpdb->query("INSERT INTO {$wpdb->posts} (post_author, post_date, post_date_gmt, post_content, post_title, post_excerpt, post_status, to_ping, pinged, post_modified, post_modified_gmt, post_content_filtered, post_type, post_name) VALUES " . implode(',', $queue_rows));
        integration_assert($inserted === 500, 'each isolated queue fixture batch inserts exactly 500 posts');
    }
    $queue_meta_inserted = $wpdb->query($wpdb->prepare(
        "INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value) SELECT ID, %s, %s FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s AND post_name LIKE %s",
        'zsr_state', 'pending', 'post', 'pending', $wpdb->esc_like($queue_prefix) . '%'
    ));
    integration_assert($queue_meta_inserted === 5000, 'all 5000 newly seeded posts receive pending review state');
    wp_cache_set_posts_last_changed();
    $queue_seed_ms = round((microtime(true) - $queue_seed_started) * 1000, 2);
    $queue_metrics = array();
    foreach (array('first' => array(1, 50, 1, 50), 'bounded' => array(999999, 999999, 50, 50), 'minimum' => array(-999, -999, 1, 1)) as $queue_case => $requested) {
        $queue_started = microtime(true);
        $queue_queries_before = $wpdb->num_queries;
        $review_queue = zsr_get_review_queue($reviewer, $requested[0], $requested[1]);
        $queue_query_count = $wpdb->num_queries - $queue_queries_before;
        $queue_metrics[$queue_case] = array('queries' => $queue_query_count, 'milliseconds' => round((microtime(true) - $queue_started) * 1000, 2));
        integration_assert($review_queue instanceof WP_Query && (int) $review_queue->get('paged') === $requested[2] && (int) $review_queue->get('posts_per_page') === $requested[3], 'large review queue enforces requested page and per-page bounds for ' . $queue_case);
        integration_assert($review_queue->post_count === $requested[3] && (int) $review_queue->found_posts >= 5000 && (int) $review_queue->max_num_pages >= 100, 'large review queue returns a full bounded page and total navigation count for ' . $queue_case);
        integration_assert($queue_query_count > 0 && $queue_query_count <= 8, 'review queue ' . $queue_case . ' query budget exceeded: measured ' . $queue_query_count . ', allowed 8');
        foreach ($review_queue->posts as $queue_post) {
            integration_assert($queue_post->post_status === 'pending' && in_array(get_post_meta($queue_post->ID, 'zsr_state', true), array('pending', 'rejected'), true), 'large review queue contains only reviewable pending posts');
        }
    }
    $saved_get = $_GET;
    $_GET = array('paged' => '999999', 'view' => 'review');
    $queue_render_started = microtime(true);
    $queue_render_queries_before = $wpdb->num_queries;
    ob_start();
    include dirname(__DIR__) . '/templates/parts/view-review.php';
    $queue_html = ob_get_clean();
    $queue_render_queries = $wpdb->num_queries - $queue_render_queries_before;
    $queue_metrics['template'] = array('queries' => $queue_render_queries, 'milliseconds' => round((microtime(true) - $queue_render_started) * 1000, 2));
    $_GET = $saved_get;
    integration_assert(substr_count($queue_html, 'class="posts-mini"') === 20, 'large review queue template displays only its default twenty posts');
    integration_assert(strpos($queue_html, 'theme-pagination') !== false && strpos($queue_html, '超过前 50 页') !== false, 'large review queue template renders pagination and the fifty-page overflow notice');
    integration_assert(preg_match('/aria-current="page"[^>]*>50<\/span>/', $queue_html) === 1, 'oversized template page request renders page fifty');
    preg_match_all('/href="([^"]+)"/', $queue_html, $queue_links);
    $queue_navigation_links = 0;
    foreach ($queue_links[1] as $queue_link) {
        $queue_link_query = parse_url(html_entity_decode($queue_link, ENT_QUOTES, 'UTF-8'), PHP_URL_QUERY);
        $queue_link_args = array();
        parse_str(is_string($queue_link_query) ? $queue_link_query : '', $queue_link_args);
        if (isset($queue_link_args['paged'])) {
            $queue_navigation_links++;
            integration_assert(ctype_digit((string) $queue_link_args['paged']) && (int) $queue_link_args['paged'] >= 1 && (int) $queue_link_args['paged'] <= 50, 'rendered review navigation never links beyond page fifty');
        }
    }
    integration_assert($queue_navigation_links > 0, 'large review queue has usable numbered navigation links');
    integration_assert($queue_render_queries <= 8, 'review queue template query budget exceeded: measured ' . $queue_render_queries . ', allowed 8');

    update_option('zsr_widget_locked', array('widget_text' => '1'));
    integration_assert(zsr_get_locked_widgets() === array('widget_text' => '1'), 'widget cache reads real option value');
    update_option('zsr_widget_locked', array('widget_html' => '1'));
    integration_assert(zsr_get_locked_widgets() === array('widget_html' => '1'), 'real update_option hook invalidates widget lock cache');
    delete_option('zsr_widget_locked');
    integration_assert(zsr_get_locked_widgets() === array(), 'real delete_option hook invalidates widget lock cache');
    add_option('zsr_widget_locked', array('widget_text' => '1'), '', 'no');
    integration_assert(zsr_get_locked_widgets() === array('widget_text' => '1'), 'real add_option hook invalidates widget lock cache');
    integration_assert(zsr_get_widget_options()['zsr_widget_visitor_action'] === 'placeholder', 'widget options cache loads initial settings');
    $settings = get_option(ZSR_OPTION);
    $settings['zsr_widget_visitor_action'] = 'hidden';
    update_option(ZSR_OPTION, $settings);
    integration_assert(zsr_get_widget_options()['zsr_widget_visitor_action'] === 'hidden', 'real settings update invalidates widget option cache');

    class ZsrIntegrationOutputWidget extends WP_Widget
    {
        public function __construct() { parent::__construct('zsr_integration_output', 'Output check'); }
        public function widget($args, $instance) { echo 'PRIVATE_WIDGET_CONTENT'; }
    }
    update_option('widget_zsr_integration_output', array(2 => array('title' => 'Output check'), '_multiwidget' => 1));
    $output_widget = new ZsrIntegrationOutputWidget();
    $output_widget->_register();
    register_sidebar(array('id' => 'zsr-integration-output', 'name' => 'Output check', 'before_widget' => '<section class="probe">', 'after_widget' => '</section>', 'before_title' => '<h3>', 'after_title' => '</h3>'));
    $output_sidebar = function ($sidebars) { $sidebars['zsr-integration-output'] = array('zsr_integration_output-2'); return $sidebars; };
    add_filter('sidebars_widgets', $output_sidebar);
    $output_options = get_option(ZSR_OPTION);
    $output_options['zsr_widget_enable'] = true;
    $output_options['zsr_widget_visitor_action'] = 'upgrade';
    update_option(ZSR_OPTION, $output_options);
    update_option('zsr_widget_locked', array('zsr_integration_output' => '1'));
    $theme_before_output = get_option('zibll_options');
    update_option('zibll_options', array_replace($theme_before_output, array('pay_user_vip_1_s' => false, 'pay_user_vip_2_s' => true)));
    $user_before_output = get_current_user_id();
    wp_set_current_user(0);
    zsr_register_widget_gates();
    ob_start();
    dynamic_sidebar('zsr-integration-output');
    $widget_output = ob_get_clean();
    integration_assert(strpos($widget_output, 'PRIVATE_WIDGET_CONTENT') === false, 'guest widget output never includes the original content');
    integration_assert(strpos($widget_output, '<script') === false && strpos($widget_output, 'signin-loader') !== false, 'real WordPress KSES removes script while preserving login controls');
    integration_assert(strpos($widget_output, 'vip-level="2"') !== false, 'safe native upgrade output preserves selected VIP level');
    wp_set_current_user($user_before_output);
    update_option('zibll_options', $theme_before_output);
    remove_filter('sidebars_widgets', $output_sidebar);
    delete_option('widget_zsr_integration_output');

    $protected_page = wp_insert_post(array('post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Unowned integration ' . $run, 'post_content' => 'Must remain intact'), true);
    integration_assert(!is_wp_error($protected_page), 'unowned page is created');
    $protected_content = get_post($approve_id)->post_content;
    $protected_meta = get_post_meta($approve_id);
    add_option('zsr_lock_' . $lock_post, $expired, '', 'no');
    add_option('zsr_lock_not_a_post', 'unrelated-lock-like-option', '', 'no');
    add_option('zsr_integration_preserved', $run, '', 'no');
    deactivate_plugins($plugin, true);
    define('WP_UNINSTALL_PLUGIN', $plugin);
    require dirname(__DIR__) . '/uninstall.php';
    integration_assert(get_option(ZSR_OPTION, null) === null && get_option('zsr_widget_locked', null) === null && get_option('zsr_version', null) === null, 'uninstall removes plugin-owned settings');
    integration_assert(get_post_status($page_id) === 'trash' && get_post_status($protected_page) === 'publish', 'uninstall trashes only plugin-owned pages');
    integration_assert(get_post($approve_id)->post_content === $protected_content && get_post_meta($approve_id) === $protected_meta, 'uninstall preserves author content and review metadata');
    integration_assert($wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", 'zsr_lock_' . $lock_post)) === null, 'uninstall removes real numeric lock row');
    integration_assert(get_option('zsr_lock_not_a_post') === 'unrelated-lock-like-option' && get_option('zsr_integration_preserved') !== false, 'uninstall retains unrelated and lookalike options');
    integration_assert($integration_mail_attempts === $mail_baseline, 'integration review and uninstall send no email');

    fwrite(STDOUT, 'WordPress ' . get_bloginfo('version') . '; PHP ' . PHP_VERSION . '; database ' . $wpdb->get_var('SELECT VERSION()') . "\n");
    fwrite(STDOUT, "Scope: real WordPress core/database with theme API adapters; not licensed Zibll or browser acceptance.\n");
    fwrite(STDOUT, "Concurrent PHP workers: 4; lock winners: {$winners}\n");
    fwrite(STDOUT, "Queue dataset: 5000 new pending posts; seed time: {$queue_seed_ms} ms\n");
    foreach ($queue_metrics as $queue_case => $queue_metric) {
        fwrite(STDOUT, 'Queue ' . $queue_case . ': ' . $queue_metric['queries'] . ' database queries; ' . $queue_metric['milliseconds'] . " ms (isolated PHP execution, not site TTFB)\n");
    }
    fwrite(STDOUT, "WordPress integration passed ({$integration_assertions} assertions); isolated test records retained.\n");
} catch (Throwable $error) {
    fwrite(STDERR, 'WordPress integration failed: ' . $error->getMessage() . "\n");
    exit(1);
}
