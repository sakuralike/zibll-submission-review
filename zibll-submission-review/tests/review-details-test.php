<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

define('ABSPATH', __DIR__ . '/');
define('ZSR_DIR', dirname(__DIR__) . DIRECTORY_SEPARATOR);

require_once __DIR__ . '/i18n-helpers.php';

$rd_meta = array(
    101 => array(
        'posts_zibpay' => array(
            'pay_type'    => '2',
            'pay_price'   => '0',
            'pay_details' => '<script>alert(1)</script>',
            'pay_download' => array(
                array('link' => 'https://download.example.test/file.zip', 'more' => '提取码 abc'),
            ),
            'attributes' => array(
                array('key' => '版本', 'value' => '1.0'),
            ),
        ),
        'custom_filter_one' => array('a'),
        '_edit_lock'        => 'private-lock',
        'zsr_state'         => 'pending',
    ),
);
$rd_aggregate = array(
    101 => array(
        'cover_image'            => 'https://cdn.example.test/cover.jpg',
        'featured_video_episode' => array(array('title' => '第一集', 'url' => 'https://video.example.test/1.mp4')),
        'subtitle'               => '副标题',
        'title'                  => 'SEO 标题',
        'keywords'               => '关键词',
        'description'            => '<b>SEO 描述</b>',
        'show_layout'            => 'no_sidebar',
        'no_article-navs'        => false,
        'article_maxheight_xz'   => '',
        'views'                  => 0,
        'like'                   => 0,
    ),
);
$rd_terms = array(
    'category' => array((object) array('term_id' => 7, 'name' => '分类一', 'slug' => 'cat-one')),
    'topics'   => array((object) array('term_id' => 9, 'name' => '专题一', 'slug' => 'topic-one')),
);
$rd_users = array(12 => (object) array('ID' => 12, 'display_name' => '审核作者'));
$rd_options = array('custom_filter' => array(array('key' => 'custom_filter_one', 'name' => '筛选', 'vals' => array(array('key' => 'a', 'name' => '选项 A')))));
$rd_current_user = 12;
$rd_nonce_ok = true;
$rd_nonce_calls = array();
$rd_json_response = null;
$rd_mutated = false;

function __($text, $domain = 'default') { return $text; }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function esc_attr($value) { return esc_html($value); }
function esc_url($value) { return esc_html($value); }
function esc_html__($value, $domain = 'default') { return esc_html(__($value, $domain)); }
function wp_json_encode($value) { return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); }
function get_post_meta($post_id, $key, $single = false) { global $rd_meta, $rd_aggregate; if ($key === 'zib_other_data') { return $rd_aggregate[$post_id] ?? array(); } return $rd_meta[$post_id][$key] ?? ($single ? '' : array()); }
function metadata_exists($type, $post_id, $key) { global $rd_meta; return isset($rd_meta[$post_id]) && array_key_exists($key, $rd_meta[$post_id]); }
function zib_get_post_meta($post_id, $key, $single = false) { global $rd_aggregate; return $rd_aggregate[$post_id][$key] ?? ''; }
function zib_get_option_meta_keys($type) { return $type === 'post_meta' ? array('cover_image', 'featured_video_episode', 'subtitle', 'title', 'keywords', 'description', 'show_layout', 'no_article-navs', 'article_maxheight_xz', 'views', 'like') : array(); }
function _pz($key, $default = false) { global $rd_options; return array_key_exists($key, $rd_options) ? $rd_options[$key] : $default; }
function get_userdata($user_id) { global $rd_users; return $rd_users[$user_id] ?? false; }
function get_permalink($post_id) { return 'https://example.test/?p=' . (int) $post_id; }
function get_post_status($post) { return is_object($post) ? $post->post_status : ''; }
function get_post_format($post_id) { return 'standard'; }
function is_sticky($post_id) { return true; }
function get_post_thumbnail_id($post_id) { return 55; }
function get_the_post_thumbnail_url($post_id, $size = 'full') { return 'https://cdn.example.test/thumb.jpg'; }
function get_object_taxonomies($post_type, $output = 'names') {
    return $output === 'objects' ? array(
        'category' => (object) array('name' => 'category', 'label' => '分类', 'show_ui' => true),
        'topics' => (object) array('name' => 'topics', 'label' => '专题', 'show_ui' => true),
        'private_tax' => (object) array('name' => 'private_tax', 'label' => 'Private', 'show_ui' => false),
    ) : array('category', 'topics');
}
function get_the_terms($post_id, $taxonomy) { global $rd_terms; return $rd_terms[$taxonomy] ?? array(); }
function wp_get_post_terms($post_id, $taxonomy, $args = array()) { return get_the_terms($post_id, $taxonomy); }
function wp_get_attachment_url($id) { return 'https://cdn.example.test/attachment-' . (int) $id . '.jpg'; }
function apply_filters($hook, $value, ...$args) { return $value; }

class post_custom_filter
{
    public static function csf_fields()
    {
        return array(array('id' => 'custom_filter_one', 'title' => '筛选', 'type' => 'select', 'options' => array('a' => '选项 A')));
    }
}

function zibpay_post_mate_csf_fields()
{
    if (!empty($GLOBALS['rd_schema_missing'])) {
        return array();
    }
    global $rd_mutated;
    if (isset($_GET['post'])) {
        $rd_mutated = true;
    }
    return array(
        array('id' => 'pay_type', 'title' => '付费模式', 'type' => 'radio', 'default' => 'no', 'options' => array('no' => '关闭', '2' => '付费下载')),
        array('id' => 'pay_price', 'title' => '执行价', 'type' => 'number', 'default' => '9.99'),
        array('id' => 'pay_cuont', 'title' => '销量浮动', 'type' => 'number', 'default' => 999),
        array('id' => 'pay_details', 'title' => '更多详情', 'type' => 'textarea', 'default' => '默认详情'),
        array('id' => 'pay_download', 'title' => '资源下载', 'type' => 'group', 'fields' => array(
            array('id' => 'link', 'title' => '下载地址', 'type' => 'upload'),
            array('id' => 'more', 'title' => '资源备注', 'type' => 'textarea'),
        )),
        array('id' => 'attributes', 'title' => '资源属性', 'type' => 'group', 'fields' => array(
            array('id' => 'key', 'title' => '属性名称', 'type' => 'text'),
            array('id' => 'value', 'title' => '属性内容', 'type' => 'text'),
        )),
    );
}

function is_user_logged_in() { global $rd_current_user; return $rd_current_user > 0; }
function get_current_user_id() { global $rd_current_user; return $rd_current_user; }
function check_ajax_referer($action, $field = false, $stop = true) { global $rd_nonce_ok, $rd_nonce_calls; $rd_nonce_calls[] = array($action, $field); return $rd_nonce_ok ? 1 : false; }
function nocache_headers() { $GLOBALS['rd_nocache'] = true; }
function wp_send_json_success($data = null, $status = null) { global $rd_json_response; $rd_json_response = array('success' => true, 'data' => $data, 'status' => $status); throw new RuntimeException('json'); }
function wp_send_json_error($data = null, $status = null) { global $rd_json_response; $rd_json_response = array('success' => false, 'data' => $data, 'status' => $status); throw new RuntimeException('json'); }
function zsr_get_review_post($post_id, $user_id = 0, $pending_only = true) { return ((int) $post_id === 101 && (int) $user_id === 12) ? $GLOBALS['rd_post'] : false; }

$rd_post = (object) array(
    'ID' => 101,
    'post_type' => 'post',
    'post_status' => 'pending',
    'post_author' => 12,
    'post_title' => '<script>alert(1)</script>标题',
    'post_content' => '<script>alert(2)</script>正文',
    'post_excerpt' => '<b>摘要</b>',
    'post_name' => 'review-post',
    'post_date' => '2026-10-06 09:00:00',
    'post_modified' => '2026-10-06 10:00:00',
    'post_date_gmt' => '2026-10-06 01:00:00',
    'post_modified_gmt' => '2026-10-06 02:00:00',
    'post_password' => 'secret',
    'comment_status' => 'open',
    'ping_status' => 'closed',
    'post_parent' => 0,
    'menu_order' => 0,
    'guid' => 'https://example.test/?p=101',
);

require_once ZSR_DIR . 'inc/frontend/review-details.php';

function rd_assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$_GET['post'] = '101';
$previous_get = $_GET;
$html = zsr_review_details_html($rd_post);
rd_assert(strpos($html, '<details class="zsr-detail-group">') !== false, 'details groups are rendered');
rd_assert(strpos($html, '&lt;script&gt;alert(1)&lt;/script&gt;标题') !== false, 'core title is escaped');
rd_assert(strpos($html, '&lt;script&gt;alert(2)&lt;/script&gt;正文') !== false, 'post content is escaped');
rd_assert(strpos($html, '状态') !== false && strpos($html, '待审核') !== false, 'status is shown');
rd_assert(strpos($html, '文章密码') !== false && strpos($html, '已设置') !== false && strpos($html, 'secret') === false, 'password is not exposed');
rd_assert(strpos($html, '付费下载') !== false && strpos($html, '0') !== false, 'zero saved pay value is preserved');
rd_assert(strpos($html, '默认详情') === false, 'saved pay details do not get replaced by schema default');
rd_assert(strpos($html, '999') === false, 'random-like missing sales default is not displayed');
rd_assert(strpos($html, 'https://download.example.test/file.zip') !== false, 'download URL is shown as a safe link');
rd_assert(strpos($html, '&lt;script&gt;alert(1)&lt;/script&gt;') !== false, 'pay HTML is escaped as text');
rd_assert(strpos($html, '专题一') !== false && strpos($html, '选项 A') !== false, 'taxonomy and custom filter values are shown');
rd_assert(strpos($html, '<img') === false, 'media is not eagerly loaded');
rd_assert(!$rd_mutated && $_GET === $previous_get, 'pay schema lookup restores request state without mutation');

$rd_nonce_ok = true;
$_REQUEST['_wpnonce'] = 'nonce';
$_POST = array('post_id' => '101', '_wpnonce' => 'nonce');
try {
    zsr_ajax_review_details();
} catch (RuntimeException $exception) {
    rd_assert($exception->getMessage() === 'json', 'authorized details request returns JSON');
}
rd_assert($rd_json_response['success'] === true && $rd_json_response['status'] === null, 'authorized details response succeeds');
rd_assert($GLOBALS['rd_nocache'] === true, 'authorized details response disables caching');
rd_assert($rd_nonce_calls[0] === array('zsr_review_details', '_wpnonce'), 'details endpoint verifies its own nonce');

$_POST = array('post_id' => array('101'), '_wpnonce' => 'nonce');
try { zsr_ajax_review_details(); } catch (RuntimeException $exception) {}
rd_assert($rd_json_response['success'] === false && $rd_json_response['status'] === 400, 'non-scalar post id is rejected');

foreach (array('-1', '0', '9999999999999999999999999999') as $invalid_id) {
    $_POST = array('post_id' => $invalid_id);
    try { zsr_ajax_review_details(); } catch (RuntimeException $exception) {}
    rd_assert($rd_json_response['success'] === false && $rd_json_response['status'] === 400, 'invalid post ID cannot wrap to another article');
}
$_POST = array('post_id' => '101');
foreach (array('', array('nonce')) as $invalid_nonce) {
    $_REQUEST['_wpnonce'] = $invalid_nonce;
    $rd_nonce_ok = false;
    try { zsr_ajax_review_details(); } catch (RuntimeException $exception) {}
    rd_assert($rd_json_response['success'] === false && $rd_json_response['status'] === 403, 'malformed or invalid nonce is rejected');
}
$_REQUEST['_wpnonce'] = 'nonce';
$rd_nonce_ok = true;
$rd_current_user = 13;
try { zsr_ajax_review_details(); } catch (RuntimeException $exception) {}
rd_assert($rd_json_response['success'] === false && $rd_json_response['status'] === 403, 'other user cannot read private review attributes');
$rd_current_user = 12;
$_POST = array('post_id' => '102');
try { zsr_ajax_review_details(); } catch (RuntimeException $exception) {}
rd_assert($rd_json_response['success'] === false && $rd_json_response['status'] === 403, 'unavailable post cannot reveal private review attributes');

$rd_current_user = 0;
$_POST = array('post_id' => '101', '_wpnonce' => 'nonce');
try { zsr_ajax_review_details(); } catch (RuntimeException $exception) {}
rd_assert($rd_json_response['success'] === false && $rd_json_response['status'] === 403, 'logged-out details request is rejected');

$rd_meta[101]['posts_zibpay']['pay_price'] = '';
$empty_html = zsr_review_details_html($rd_post);
rd_assert(strpos($empty_html, '<dt>执行价</dt><dd>空</dd>') !== false, 'saved empty price is not replaced by its default');
$rd_meta[101]['posts_zibpay']['pay_price'] = new stdClass();
$object_html = zsr_review_details_html($rd_post);
rd_assert(strpos($object_html, '无法显示此字段类型') !== false, 'unexpected object metadata cannot crash rendering');
rd_assert(strpos($html, 'private-lock') === false, 'internal editing locks are not disclosed');
rd_assert(strpos($html, '<dt>执行价</dt><dd>0</dd>') !== false, 'numeric zero is rendered for the price field');
rd_assert(strpos($html, '<dt>不显示目录树</dt><dd>否</dd>') !== false, 'saved false aggregate metadata is preserved');
$rd_schema_missing = true;
$unavailable_html = zsr_review_details_html($rd_post);
rd_assert(strpos($unavailable_html, '付费字段定义不可用') !== false, 'missing theme schema reports unavailable without calling missing helpers');
rd_assert(strpos($unavailable_html, 'download.example.test') === false, 'unavailable theme schema does not dump unknown payment metadata');

fwrite(STDOUT, "review details tests passed\n");
