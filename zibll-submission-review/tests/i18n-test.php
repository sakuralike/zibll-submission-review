<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

define('ABSPATH', __DIR__ . '/');
define('ZSR_OPTION', 'zsr_options');
define('ZSR_VERSION', '0.1.0');
define('ZSR_DB_VERSION', 1);

$i18n_translations = array();
$i18n_user = 0;
$i18n_options = array('zsr_log_enable' => true, 'zsr_log_level' => 'debug');
$i18n_other_options = array();
$i18n_assertions = 0;

function apply_filters($hook, $value, ...$args)
{
    global $i18n_translations;
    return $hook === 'gettext' && $args[1] === 'zib-sub-review' && isset($i18n_translations[$args[0]])
        ? $i18n_translations[$args[0]] : $value;
}
function get_option($key, $default = false) { return $key === ZSR_OPTION ? $GLOBALS['i18n_options'] : ($GLOBALS['i18n_other_options'][$key] ?? $default); }
function update_option($key, $value, $autoload = null)
{
    if (!empty($GLOBALS['i18n_fail_updates'])) { return false; }
    if ($key === ZSR_OPTION) { $GLOBALS['i18n_options'] = $value; } else { $GLOBALS['i18n_other_options'][$key] = $value; }
    return true;
}
function get_current_user_id() { return $GLOBALS['i18n_user']; }
function wp_roles() { return new class { public function get_names() { return array('administrator' => 'Administrator'); } }; }
function translate_user_role($name) { return $name === 'Administrator' ? 'Site administrator' : $name; }
function wp_send_json($payload, $status = 200) { throw new RuntimeException(json_encode(array('status' => $status, 'payload' => $payload))); }
function check_ajax_referer($action, $field = false, $stop = true) { return 1; }
function absint($value) { return abs((int) $value); }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_-]/', '', strtolower($value)); }
function is_admin() { return true; }
function current_user_can($capability) { return $capability === 'manage_options'; }
function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {}
function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {}
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_attr($value) { return esc_html($value); }
function esc_url($value) { return esc_html($value); }
function wp_strip_all_tags($value) { return strip_tags($value); }
function strip_shortcodes($value) { return $value; }
function get_permalink($post_id) { return 'https://example.test/?p=' . $post_id; }
function get_userdata($user_id) { return (object) array('ID' => $user_id, 'user_email' => 'author@example.test'); }
function is_email($email) { return filter_var($email, FILTER_VALIDATE_EMAIL); }
function wp_mail($recipient, $title, $content, $headers) { $GLOBALS['i18n_mail'] = array('title' => $title, 'content' => $content); return true; }
function wp_insert_post($post, $error = false)
{
    $id = count($GLOBALS['i18n_posts'] ?? array()) + 100;
    $GLOBALS['i18n_posts'][$id] = (object) array_merge($post, array('ID' => $id));
    return $id;
}
function get_post($post_id) { return $GLOBALS['i18n_posts'][$post_id] ?? null; }
function get_post_meta($post_id, $key, $single = true) { return $GLOBALS['i18n_post_meta'][$post_id][$key] ?? ''; }
function update_post_meta($post_id, $key, $value) { $GLOBALS['i18n_post_meta'][$post_id][$key] = $value; return true; }
function is_wp_error($value) { return false; }
class CSF
{
    public static $options = array();
    public static $sections = array();
    public static function createOptions($key, $options) { self::$options[$key] = $options; }
    public static function createSection($key, $section) { self::$sections[] = $section; }
}
class WP_Widget
{
    public $id_base = 'example';
    public $name = 'Example widget';
    public function display_callback() {}
}
function wp_get_sidebars_widgets() { return array('sidebar' => array('example-2')); }

require_once __DIR__ . '/i18n-helpers.php';
require_once dirname(__DIR__) . '/inc/core/options.php';
require_once dirname(__DIR__) . '/inc/core/logger.php';
require_once dirname(__DIR__) . '/inc/core/capabilities.php';
require_once dirname(__DIR__) . '/inc/core/dependencies.php';
require_once dirname(__DIR__) . '/inc/core/bootstrap.php';
require_once dirname(__DIR__) . '/inc/frontend/review-query.php';
require_once dirname(__DIR__) . '/inc/frontend/query.php';
require_once dirname(__DIR__) . '/inc/frontend/page-router.php';
require_once dirname(__DIR__) . '/inc/frontend/history-query.php';
require_once dirname(__DIR__) . '/inc/ajax/submit.php';
require_once dirname(__DIR__) . '/inc/ajax/review.php';
require_once dirname(__DIR__) . '/inc/admin/options.php';
require_once dirname(__DIR__) . '/inc/domain/notification-service.php';
require_once dirname(__DIR__) . '/inc/widget/sync.php';
require_once dirname(__DIR__) . '/inc/widget/enumerator.php';

function i18n_assert($condition, $message)
{
    $GLOBALS['i18n_assertions']++;
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function i18n_response($callback)
{
    try {
        call_user_func($callback);
    } catch (RuntimeException $error) {
        return json_decode($error->getMessage(), true);
    }
    i18n_assert(false, 'AJAX request must produce a JSON response');
}

$i18n_log = tempnam(sys_get_temp_dir(), 'zsr-i18n-');
ini_set('log_errors', '1');
ini_set('error_log', $i18n_log);
register_shutdown_function(function () use ($i18n_log) { @unlink($i18n_log); });
$_POST = array();
$response = i18n_response('zsr_ajax_submit');
i18n_assert($response['status'] === 400 && $response['payload']['msg'] === '请登录后提交稿件', 'default submission response stays Chinese');
$i18n_translations['请登录后提交稿件'] = 'Please log in before submitting';
$response = i18n_response('zsr_ajax_submit');
i18n_assert($response['payload']['msg'] === 'Please log in before submitting', 'submission JSON honors the WordPress gettext filter');
$records = array_filter(explode("\n", file_get_contents($i18n_log)));
$last_record = end($records);
i18n_assert(strpos($last_record, '"reason_code":"not_logged_in"') !== false, 'translated submission keeps the stable diagnostic reason code');

$_POST = array('post_id' => '101', 'method' => 'approve');
$response = i18n_response('zsr_ajax_review');
i18n_assert($response['status'] === 400 && $response['payload']['msg'] === '您没有审核稿件的权限', 'default review response stays Chinese');
$i18n_translations['您没有审核稿件的权限'] = 'You cannot review submissions';
$response = i18n_response('zsr_ajax_review');
i18n_assert($response['payload']['msg'] === 'You cannot review submissions', 'review JSON honors the WordPress gettext filter');
$records = array_filter(explode("\n", file_get_contents($i18n_log)));
$last_record = end($records);
i18n_assert(strpos($last_record, '"reason_code":"review_capability_denied"') !== false, 'translated review keeps the stable diagnostic reason code');

$i18n_translations['投稿审核设置'] = 'Submission review settings';
$i18n_translations['启用投稿审核功能'] = 'Enable submission reviews';
$i18n_translations['所有人'] = 'Everyone';
zsr_register_admin_options();
i18n_assert(CSF::$options['zsr_options']['menu_title'] === 'Submission review settings', 'registered settings menu honors gettext');
i18n_assert(CSF::$sections[0]['fields'][0]['title'] === 'Enable submission reviews', 'registered settings fields honor gettext');
i18n_assert(CSF::$sections[1]['fields'][0]['options']['administrator'] === 'Site administrator', 'registered role choices honor WordPress role translation');

$i18n_options['zsr_enable_submit'] = false;
$view = 'submit';
ob_start();
require dirname(__DIR__) . '/templates/parts/view-submit.php';
$html = ob_get_clean();
i18n_assert(strpos($html, '>提交稿件</h2>') !== false, 'default submission page stays Chinese');
$i18n_translations['提交稿件'] = 'Submit <script>unsafe</script>';
$i18n_translations['前台投稿功能当前已关闭。'] = 'Submission is unavailable.';
ob_start();
require dirname(__DIR__) . '/templates/parts/view-submit.php';
$html = ob_get_clean();
i18n_assert(strpos($html, 'Submit &lt;script&gt;unsafe&lt;/script&gt;') !== false && strpos($html, 'Submission is unavailable.') !== false, 'submission page translates and escapes visible text');

$i18n_translations['审核请求参数无效'] = 'Invalid review request';
$_POST = array('post_id' => '101', 'method' => array('approve'));
$response = i18n_response('zsr_ajax_review');
i18n_assert($response['status'] === 400 && $response['payload']['msg'] === 'Invalid review request', 'invalid review request uses translated JSON message');
$records = array_filter(explode("\n", file_get_contents($i18n_log)));
$last_record = end($records);
i18n_assert(strpos($last_record, '"reason_code":"invalid_review_input"') !== false, 'translated request error keeps the stable reason code');

$i18n_options['zsr_notify_channel'] = array('email');
$post = (object) array('ID' => 101, 'post_author' => 13, 'post_title' => '用户标题', 'post_content' => '用户正文');
$transition = array('ok' => true, 'method' => 'approve', 'to_status' => 'publish', 'message' => '用户意见');
zsr_notify_review_author($post, $transition, 12);
i18n_assert($i18n_mail['title'] === '您发布的稿件已通过审核：[用户标题]', 'default outgoing mail subject stays Chinese');
$i18n_translations['您发布的稿件已通过审核：[%s]'] = '[%s] was approved';
$i18n_translations['审核意见：'] = 'Review feedback: ';
$i18n_translations['内容摘要：'] = 'Excerpt:';
$i18n_translations['查看稿件'] = '<script>View submission</script>';
$i18n_translations['用户标题'] = 'Do not translate user input';
zsr_notify_review_author($post, $transition, 12);
i18n_assert($i18n_mail['title'] === '[用户标题] was approved', 'outgoing review mail translates the complete subject without changing user title');
i18n_assert(strpos($i18n_mail['content'], 'Review feedback: 用户意见') !== false && strpos($i18n_mail['content'], 'Excerpt:') !== false && strpos($i18n_mail['content'], '用户正文') !== false, 'mail labels translate without altering author or reviewer input');
i18n_assert(strpos($i18n_mail['content'], '&lt;script&gt;View submission&lt;/script&gt;') !== false, 'translated mail link text stays escaped');

$i18n_translations['审核台'] = 'Review queue';
$i18n_translations['暂无待审核稿件。'] = 'No submissions to review.';
ob_start();
require dirname(__DIR__) . '/templates/parts/view-review.php';
$html = ob_get_clean();
i18n_assert(strpos($html, 'Review queue') !== false && strpos($html, 'No submissions to review.') !== false, 'review page translates its heading and empty state');

$i18n_translations['暂无投稿。'] = 'No submissions yet.';
ob_start();
require dirname(__DIR__) . '/templates/parts/view-my.php';
$html = ob_get_clean();
i18n_assert(strpos($html, 'No submissions yet.') !== false, 'view-my.php translates visible empty-state text');

$i18n_translations['暂无审核记录。'] = 'No review history yet.';
ob_start();
require dirname(__DIR__) . '/templates/parts/view-history.php';
$html = ob_get_clean();
i18n_assert(strpos($html, 'No review history yet.') !== false, 'view-history.php translates visible empty-state text');

$i18n_translations['该视图将在对应功能阶段启用。'] = 'This view is not available yet.';
ob_start();
require dirname(__DIR__) . '/templates/parts/view-placeholder.php';
$html = ob_get_clean();
i18n_assert(strpos($html, 'This view is not available yet.') !== false, 'view-placeholder.php translates visible empty-state text');

$i18n_translations['稿件不存在、已处理或您没有查看权限。'] = 'Submission is unavailable.';
ob_start();
require dirname(__DIR__) . '/templates/parts/view-review-detail.php';
$html = ob_get_clean();
i18n_assert(strpos($html, 'Submission is unavailable.') !== false, 'view-review-detail.php translates visible empty-state text');

$i18n_translations['请登录后查看投稿。'] = 'Log in to view submissions.';
ob_start();
require dirname(__DIR__) . '/templates/zsr-submissions.php';
$html = ob_get_clean();
i18n_assert(strpos($html, 'Log in to view submissions.') !== false, 'main submission page translates the guest login message');

$i18n_translations['子比前台投稿审核插件当前处于兼容提示模式：%s'] = 'Compatibility notice: %s';
$i18n_translations['当前启用主题不是 Zibll 子比主题。'] = 'Active theme is not Zibll.';
$i18n_translations['读取 Zibll 设置'] = 'Read Zibll settings';
ob_start();
zsr_admin_dependency_notice();
$html = ob_get_clean();
i18n_assert(strpos($html, 'Compatibility notice: ') !== false && strpos($html, 'Active theme is not Zibll.') !== false && strpos($html, 'Read Zibll settings') !== false, 'admin dependency notice translates the notice and missing capabilities');

$i18n_fail_updates = true;
$i18n_translations['小工具可见性配置保存失败，已保留原有设置，请检查诊断日志后重试。'] = 'Widget settings could not be saved.';
zsr_prepare_options_for_save(array('zsr_widget_locked' => array('example')));
$_GET['page'] = 'zsr_options';
ob_start();
zsr_widget_sync_notice();
$html = ob_get_clean();
i18n_assert(strpos($html, 'Widget settings could not be saved.') !== false, 'failed widget settings save displays a translated notice');
$i18n_fail_updates = false;

$widget = new WP_Widget();
$wp_widget_factory = (object) array('widgets' => array($widget));
$wp_registered_widgets = array('example-2' => array('callback' => array($widget, 'display_callback')));
$wp_registered_sidebars = array('sidebar' => array('name' => 'Main sidebar'));
$i18n_translations['%1$s（%2$s；%3$s；%4$d实例）'] = '%1$s (%2$s; %3$s; instances: %4$d)';
$choices = call_user_func(CSF::$sections[4]['fields'][1]['options']);
i18n_assert($choices['example'] === 'Example widget (example; Main sidebar; instances: 1)', 'registered widget choices callback translates labels and preserves widget/sidebar names');

$i18n_options = array();
$i18n_translations['我的投稿'] = 'My submissions';
zsr_install_options();
$page_id = zsr_ensure_frontend_page();
i18n_assert(get_post($page_id)->post_title === 'My submissions', 'first installation creates the page using a translated default title');

$i18n_translations['我的投稿'] = 'Changed locale title';
i18n_assert(zsr_ensure_frontend_page() === $page_id && get_post($page_id)->post_title === 'My submissions', 'locale changes do not rename an existing owned page');
$i18n_options = array('zsr_menu_label' => '我的投稿');
zsr_install_options();
$page_id = zsr_ensure_frontend_page();
i18n_assert(get_post($page_id)->post_title === '我的投稿', 'an explicitly saved menu label is preserved even when it matches a translatable default');
$i18n_options = array('zsr_menu_label' => '');
$page_id = zsr_ensure_frontend_page();
i18n_assert(get_post($page_id)->post_title === 'Changed locale title', 'a new page with an empty saved label uses the translated fallback');

fwrite(STDOUT, "i18n tests passed ({$i18n_assertions} assertions)\n");
