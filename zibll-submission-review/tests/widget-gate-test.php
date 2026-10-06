<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/i18n-helpers.php';

define('ABSPATH', __DIR__ . '/');
define('ZSR_OPTION', 'zsr_options');
$gate_mode = isset($argv[1]) ? $argv[1] : '';
if ($gate_mode !== '--without-version') {
    define('ZSR_VERSION', '0.1.0');
}
if ($gate_mode !== '--without-file') {
    define('ZSR_FILE', $gate_mode === '--missing-file' ? 'D:/kfhj/13-包缓存/php/zsr-widget-missing-' . getmypid() . '.php' : dirname(__DIR__) . '/zibll-submission-review.php');
}

$gate_options = array();
$gate_reads = array();
$gate_hooks = array();
$gate_filter_stack = array();
$gate_logged = false;
$gate_admin = false;
$gate_manage = false;
$gate_mobile = false;
$gate_login_html = '<a class="signin-loader" href="javascript:;">主题登录</a>';
$gate_login_calls = 0;
$gate_vip_calls = array();
$gate_card_calls = 0;
$gate_settings_reads = 0;
$gate_sidebar_reads = 0;
$gate_callbacks = array();
$gate_assertions = 0;
$wp_registered_widgets = array();
$wp_registered_sidebars = array();
$wp_widget_factory = (object) array('widgets' => array());

function get_option($key, $default = false)
{
    $GLOBALS['gate_reads'][] = $key;
    return array_key_exists($key, $GLOBALS['gate_options']) ? $GLOBALS['gate_options'][$key] : $default;
}

function is_user_logged_in() { return $GLOBALS['gate_logged']; }
function get_current_user_id() { return is_user_logged_in() ? 12 : 0; }
function is_admin() { return $GLOBALS['gate_admin']; }
function current_user_can($capability) { return $capability === 'manage_options' && $GLOBALS['gate_manage']; }
function wp_is_mobile() { return $GLOBALS['gate_mobile']; }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function esc_attr($value) { return esc_html($value); }
function esc_url($value) { return esc_html($value); }
function wp_strip_all_tags($value) { return trim(strip_tags((string) $value)); }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $value)); }
function wp_parse_args($value, $defaults = array()) { return array_merge($defaults, (array) $value); }
function zsr_log($level, $event, $context = array()) {}

function wp_get_sidebars_widgets()
{
    $GLOBALS['gate_sidebar_reads']++;
    $sidebars = array();
    foreach ($GLOBALS['wp_registered_widgets'] as $id => $registration) {
        $sidebars[$registration['fixture_sidebar']][] = $id;
    }
    return $sidebars;
}

function zsr_gate_callback_key($callback)
{
    if (is_array($callback)) {
        return (is_object($callback[0]) ? spl_object_hash($callback[0]) : $callback[0]) . ':' . $callback[1];
    }
    return is_object($callback) ? spl_object_hash($callback) : $callback;
}

function add_filter($hook, $callback, $priority = 10, $accepted_args = 1)
{
    $GLOBALS['gate_hooks'][$hook][$priority][zsr_gate_callback_key($callback)] = array($callback, $accepted_args);
    return true;
}

function add_action($hook, $callback, $priority = 10, $accepted_args = 1)
{
    return add_filter($hook, $callback, $priority, $accepted_args);
}

function apply_filters($hook, $value, ...$args)
{
    $GLOBALS['gate_filter_stack'][] = $hook;
    try {
        $callbacks = isset($GLOBALS['gate_hooks'][$hook]) ? $GLOBALS['gate_hooks'][$hook] : array();
        ksort($callbacks);
        foreach ($callbacks as $entries) {
            foreach ($entries as $entry) {
                $value = call_user_func_array($entry[0], array_slice(array_merge(array($value), $args), 0, $entry[1]));
            }
        }
        return $value;
    } finally {
        array_pop($GLOBALS['gate_filter_stack']);
    }
}

function current_filter() { return end($GLOBALS['gate_filter_stack']); }

function has_filter($hook, $callback = false)
{
    foreach (isset($GLOBALS['gate_hooks'][$hook]) ? $GLOBALS['gate_hooks'][$hook] : array() as $priority => $entries) {
        if ($callback === false || isset($entries[zsr_gate_callback_key($callback)])) {
            return $priority;
        }
    }
    return false;
}

function zib_widget_is_show($instance)
{
    $show = isset($instance['show_type']) ? $instance['show_type'] : 'all';
    if (($show === 'only_pc' && wp_is_mobile()) || ($show === 'only_sm' && !wp_is_mobile())) {
        return false;
    }
    return $show === 'only_pc' ? 'hidden-xs' : ($show === 'only_sm' ? 'visible-xs-block' : true);
}

function zib_get_user_singin_page_box($class = 'box-body', $hi = null)
{
    $GLOBALS['gate_login_calls']++;
    return $GLOBALS['gate_login_html'];
}

function zib_get_sign_url($tab = 'signin')
{
    return 'https://example.test/member-access?tab=' . $tab;
}

function zibpay_get_payvip_button($level = 1, $class = 'but jb-yellow', $text = null)
{
    $GLOBALS['gate_vip_calls'][] = array($level, $class, $text);
    return '<a class="pay-vip ' . esc_attr($class) . '" href="javascript:;" vip-level="' . esc_attr($level) . '">' . esc_html($text === null ? '立即开通' : $text) . '</a>';
}

function zib_get_user_card_box($args = array())
{
    $GLOBALS['gate_card_calls']++;
    return 'PRIVATE_USER_CARD_SENTINEL';
}

class WP_Widget
{
    public $id_base;
    public $name;
    public $id;
    public $number;
    public $settings;

    public function __construct($id_base, $settings)
    {
        $this->id_base = $id_base;
        $this->name = '测试 ' . $id_base;
        $this->settings = $settings + array('_multiwidget' => 1);
    }

    public function get_settings()
    {
        $GLOBALS['gate_settings_reads']++;
        return $this->settings;
    }

    public function _set($number)
    {
        $this->number = $number;
        $this->id = $this->id_base . '-' . $number;
    }

    public function display_callback($args, $widget_args = 1)
    {
        $GLOBALS['gate_callbacks'][] = array($this->id_base, $args, $widget_args);
        if (is_numeric($widget_args)) {
            $widget_args = array('number' => $widget_args);
        }
        $widget_args = wp_parse_args($widget_args, array('number' => -1));
        $this->_set($widget_args['number']);
        $settings = $this->get_settings();
        if (!isset($settings[$this->number])) {
            return;
        }
        $instance = apply_filters('widget_display_callback', $settings[$this->number], $this, $args);
        if ($instance === false) {
            return;
        }
        $this->widget($args, $instance);
    }

    public function widget($args, $instance)
    {
        $show = zib_widget_is_show($instance);
        if (!$show) {
            return;
        }
        $this->output($args, $instance, $show);
    }

    protected function output($args, $instance, $show)
    {
        echo $args['before_widget'];
        echo '<div class="original-widget ' . ($show === true ? '' : esc_attr($show)) . '">';
        echo $args['before_title'] . esc_html($instance['title']) . $args['after_title'];
        echo '<p>PRIVATE_WIDGET_BODY_' . esc_html($this->id) . '</p></div>';
        echo $args['after_widget'];
    }
}

class CSF_Widget extends WP_Widget
{
    public $unique;

    public function __construct($id_base, $settings)
    {
        parent::__construct($id_base, $settings);
        $this->unique = $id_base;
    }

    public static function show_class($instance)
    {
        if (!empty($instance['fixture_hidden'])) {
            return '';
        }
        $show = isset($instance['show_type']) ? $instance['show_type'] : 'all';
        return $show === 'only_pc' ? 'hidden-xs' : ($show === 'only_sm' ? 'visible-xs-block' : true);
    }

    public function is_show($args, $instance)
    {
        return apply_filters('widget_is_show_' . $this->unique, self::show_class($instance), $args, $instance);
    }

    public function widget($args, $instance)
    {
        $show = $this->is_show($args, $instance);
        if (!$show) {
            return;
        }
        $this->output($args, $instance, $show);
    }
}

require_once dirname(__DIR__) . '/inc/core/options.php';
require_once dirname(__DIR__) . '/inc/widget/sync.php';
require_once dirname(__DIR__) . '/inc/widget/enumerator.php';
require_once dirname(__DIR__) . '/inc/widget/locker.php';

function zsr_gate_assert($condition, $message)
{
    $GLOBALS['gate_assertions']++;
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $message);
    }
}

function zsr_gate_reset($options = array(), $locked = array())
{
    zsr_invalidate_widget_cache();
    $options = array_replace(array('zsr_widget_visitor_action' => 'placeholder'), $options);
    $GLOBALS['gate_options'] = array(ZSR_OPTION => $options, 'zsr_widget_locked' => $locked);
    $GLOBALS['gate_reads'] = array();
    $GLOBALS['gate_hooks'] = array();
    $GLOBALS['gate_filter_stack'] = array();
    $GLOBALS['gate_logged'] = false;
    $GLOBALS['gate_admin'] = false;
    $GLOBALS['gate_manage'] = false;
    $GLOBALS['gate_mobile'] = false;
    $GLOBALS['gate_login_html'] = '<a class="signin-loader" href="javascript:;">主题登录</a>';
    $GLOBALS['gate_login_calls'] = 0;
    $GLOBALS['gate_vip_calls'] = array();
    $GLOBALS['gate_card_calls'] = 0;
    $GLOBALS['gate_settings_reads'] = 0;
    $GLOBALS['gate_sidebar_reads'] = 0;
    $GLOBALS['gate_callbacks'] = array();
    $GLOBALS['zsr_widget_original_callbacks'] = array();
    $GLOBALS['wp_registered_widgets'] = array();
    $GLOBALS['wp_registered_sidebars'] = array();
    $GLOBALS['wp_widget_factory'] = (object) array('widgets' => array());
}

function zsr_gate_register($id_base, $csf = true, $sidebar = 'post_sidebar', $numbers = array(2, 5))
{
    $settings = array(
        2 => array('title' => '实例二 <script>privateTitle()</script> & "引号"', 'show_type' => 'all'),
        5 => array('title' => '实例五', 'show_type' => 'only_pc'),
    );
    foreach ($numbers as $number) {
        if (!isset($settings[$number])) {
            $settings[$number] = array('title' => '实例 ' . $number, 'show_type' => 'all');
        }
    }
    $widget = $csf ? new CSF_Widget($id_base, $settings) : new WP_Widget($id_base, $settings);
    $GLOBALS['wp_widget_factory']->widgets[$id_base] = $widget;
    $GLOBALS['wp_registered_sidebars'][$sidebar] = array('name' => $sidebar);
    foreach ($numbers as $number) {
        $GLOBALS['wp_registered_widgets'][$id_base . '-' . $number] = array(
            'id' => $id_base . '-' . $number,
            'name' => $widget->name,
            'callback' => array($widget, 'display_callback'),
            'params' => array(array('number' => $number)),
            'fixture_sidebar' => $sidebar,
        );
    }
    return $widget;
}

function zsr_gate_args($id, $sidebar = 'post_sidebar')
{
    $args = array(
        'id' => $sidebar,
        'name' => '测试侧栏',
        'widget_id' => $id,
        'widget_name' => '测试模块',
        'before_widget' => '<section id="' . esc_attr($id) . '" class="theme-widget">',
        'after_widget' => '</section>',
        'before_title' => '<h3 class="theme-title">',
        'after_title' => '</h3>',
    );
    if (strpos($sidebar, 'fluid') !== false) {
        $args['before_widget'] = '<div class="widget-container">' . $args['before_widget'];
        $args['after_widget'] .= '</div>';
    }
    return $args;
}

function zsr_gate_render($id, $sidebar = 'post_sidebar', $widget_args = null)
{
    $registration = $GLOBALS['wp_registered_widgets'][$id];
    $widget_args = $widget_args === null ? $registration['params'][0] : $widget_args;
    ob_start();
    try {
        call_user_func_array($registration['callback'], array(zsr_gate_args($id, $sidebar), $widget_args));
        return ob_get_contents();
    } finally {
        ob_end_clean();
    }
}

function zsr_gate_html($html, $id, $fluid = false, $csf = false)
{
    zsr_gate_assert($html !== '', 'placeholder is rendered for ' . $id);
    zsr_gate_assert(strpos($html, 'PRIVATE_WIDGET_BODY_') === false, 'original body is never rendered for ' . $id);
    if (!$csf) {
        zsr_gate_assert(substr_count($html, 'id="' . $id . '"') === 1, 'legacy sidebar wrapper appears once');
    }
    zsr_gate_assert(substr_count($html, 'class="widget-container"') === ($csf || $fluid ? 1 : 0), 'fluid wrapper is not duplicated');
    foreach (array('div', 'section', 'h3', 'a', 'p') as $tag) {
        zsr_gate_assert(preg_match_all('/<' . $tag . '(?:\s|>)/i', $html) === preg_match_all('/<\/' . $tag . '>/i', $html), $tag . ' tags are balanced');
    }
    $previous = libxml_use_internal_errors(true);
    $document = new DOMDocument();
    $loaded = $document->loadHTML('<!doctype html><html><body><div id="test-root">' . $html . '</div></body></html>');
    zsr_gate_assert($loaded, 'placeholder parses as HTML');
    $xpath = new DOMXPath($document);
    zsr_gate_assert($xpath->query('//*[@id="test-root"]//*[contains(concat(" ", normalize-space(@class), " "), " zsr-widget-placeholder ")]')->length === 1, 'exactly one placeholder remains inside the rendered tree');
    zsr_gate_assert($xpath->query('//script')->length === 0, 'title cannot inject a script');
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
}

if ($gate_mode !== '') {
    zsr_gate_reset(array('zsr_widget_enable' => true), array('plain_module' => '1'));
    zsr_gate_assert(zsr_widget_should_lock('plain_module') === false, 'missing plugin marker fails open: ' . $gate_mode);
    fwrite(STDOUT, "widget marker guard passed\n");
    exit(0);
}

foreach (array(true, false) as $csf) {
    foreach (array(array(4), array(4, 7)) as $selected) {
        foreach (array('hidden', 'placeholder', 'upgrade') as $mode) {
            $locked = array();
            foreach ($selected as $number) {
                $locked['zib_widget_ui_tab_post-' . $number] = '1';
            }
            zsr_gate_reset(array('zsr_widget_enable' => true, 'zsr_widget_visitor_action' => $mode), $locked);
            $widget = zsr_gate_register('zib_widget_ui_tab_post', $csf, 'index_bottom', range(2, 9));
            $instance_outputs = array();
            foreach (range(2, 9) as $number) {
                $id = 'zib_widget_ui_tab_post-' . $number;
                $instance_outputs[$id] = zsr_gate_render($id, 'index_bottom');
            }
            zsr_register_widget_gates();
            foreach (range(2, 9) as $number) {
                $id = 'zib_widget_ui_tab_post-' . $number;
                $html = zsr_gate_render($id, 'index_bottom');
                if (in_array($number, $selected, true)) {
                    zsr_gate_assert($mode === 'hidden' ? $html === '' : strpos($html, 'zsr-widget-placeholder') !== false, 'selected instance alone receives visitor action among eight: ' . $id . ' ' . $mode . ' ' . ($csf ? 'CSF' : 'legacy'));
                    zsr_gate_assert(strpos($html, 'PRIVATE_WIDGET_BODY_') === false, 'selected instance does not render private content: ' . $id);
                } else {
                    zsr_gate_assert($html === $instance_outputs[$id], 'unselected instance keeps its exact original output: ' . $id);
                }
            }
            zsr_gate_assert(zsr_widget_should_lock('zib_widget_ui_tab_post') === false, 'instance selection never becomes a whole-type lock when the ID is missing');
            if ($csf) {
                $args = zsr_gate_args('zib_widget_ui_tab_post-4', 'index_bottom');
                unset($args['widget_id']);
                ob_start();
                $show = apply_filters('widget_is_show_zib_widget_ui_tab_post', 'hidden-xs', $args, $widget->settings[4]);
                $html = ob_get_clean();
                zsr_gate_assert($show === 'hidden-xs' && $html === '', 'shared CSF hook preserves the original value if an instance ID is missing');
            }
            $GLOBALS['gate_logged'] = true;
            foreach (range(2, 9) as $number) {
                $id = 'zib_widget_ui_tab_post-' . $number;
                zsr_gate_assert(zsr_gate_render($id, 'index_bottom') === $instance_outputs[$id], 'login restores the exact original output for every instance: ' . $id);
            }
        }
    }
    foreach (array('selected_instances', 'legacy_type', 'excluded_type') as $selection) {
        $locked = $selection === 'legacy_type' ? array('zib_widget_ui_tab_post' => '1') : array('zib_widget_ui_tab_post-4' => '1', 'zib_widget_ui_tab_post-7' => '1');
        $excluded = $selection === 'excluded_type' ? 'zib_widget_ui_tab_post' : 'zib_widget_ui_tab_post-4';
        zsr_gate_reset(array('zsr_widget_enable' => true, 'zsr_widget_visitor_action' => 'hidden', 'zsr_widget_exclude' => array($excluded)), $locked);
        zsr_gate_register('zib_widget_ui_tab_post', $csf, 'index_bottom', range(2, 9));
        $instance_outputs = array();
        foreach (range(2, 9) as $number) {
            $id = 'zib_widget_ui_tab_post-' . $number;
            $instance_outputs[$id] = zsr_gate_render($id, 'index_bottom');
        }
        zsr_register_widget_gates();
        foreach (range(2, 9) as $number) {
            $id = 'zib_widget_ui_tab_post-' . $number;
            $html = zsr_gate_render($id, 'index_bottom');
            if ($selection === 'excluded_type' || $number === 4 || ($selection === 'selected_instances' && $number !== 7)) {
                zsr_gate_assert($html === $instance_outputs[$id], 'instance or legacy type exclusion wins without changing allowed output: ' . $selection . ' ' . $id);
            } else {
                zsr_gate_assert($html === '', 'remaining selected instances stay hidden beside the excluded instance: ' . $selection . ' ' . $id);
            }
        }
    }
}

foreach (array('widget_ui_user' => true, 'widget_ui_search' => false, 'zib_widget_ui_user' => true, 'zib_widget_ui_search' => true) as $id_base => $csf) {
    zsr_gate_reset(array('zsr_widget_enable' => true, 'zsr_widget_visitor_action' => 'hidden'), array($id_base => '1'));
    zsr_gate_register($id_base, $csf);
    zsr_register_widget_gates();
    zsr_gate_assert(zsr_gate_render($id_base . '-2') === '', 'selected user/search widget emits no HTML for visitors: ' . $id_base);
    $GLOBALS['gate_logged'] = true;
    zsr_gate_assert(strpos(zsr_gate_render($id_base . '-2'), 'PRIVATE_WIDGET_BODY_') !== false, 'selected user/search widget is restored after login: ' . $id_base);
    $GLOBALS['gate_logged'] = false;
    $GLOBALS['gate_options'][ZSR_OPTION]['zsr_widget_exclude'] = array($id_base);
    zsr_invalidate_widget_cache(ZSR_OPTION);
    zsr_gate_assert(strpos(zsr_gate_render($id_base . '-2'), 'PRIVATE_WIDGET_BODY_') !== false, 'explicit user/search exclusion restores the original widget: ' . $id_base);
    $GLOBALS['gate_options'][ZSR_OPTION]['zsr_widget_exclude'] = array();
    $GLOBALS['gate_options'][ZSR_OPTION]['zsr_widget_enable'] = false;
    zsr_invalidate_widget_cache(ZSR_OPTION);
    zsr_gate_assert(strpos(zsr_gate_render($id_base . '-2'), 'PRIVATE_WIDGET_BODY_') !== false, 'disabled guest control restores the original user/search widget: ' . $id_base);
}

foreach (array('disabled', 'admin', 'logged') as $bypass) {
    zsr_gate_reset($bypass === 'disabled' ? array() : array('zsr_widget_enable' => true), array('plain_module' => '1'));
    $widget = zsr_gate_register('plain_module', false);
    $GLOBALS['gate_admin'] = $bypass === 'admin';
    $GLOBALS['gate_logged'] = $bypass === 'logged';
    zsr_register_widget_gates();
    zsr_gate_assert($GLOBALS['wp_registered_widgets']['plain_module-2']['callback'] === array($widget, 'display_callback'), $bypass . ' leaves callbacks unchanged');
    zsr_gate_assert($GLOBALS['gate_hooks'] === array(), $bypass . ' does not install render hooks');
    zsr_gate_assert(!in_array('zsr_widget_locked', $GLOBALS['gate_reads'], true), $bypass . ' skips lock configuration reads');
    zsr_gate_assert($GLOBALS['gate_sidebar_reads'] === 0 && $GLOBALS['gate_settings_reads'] === 0, $bypass . ' skips widget enumeration and instance reads');
}

zsr_gate_reset(array('zsr_widget_enable' => true, 'zsr_widget_locked' => array('mirror_only')), array('independent_only' => '1'));
zsr_gate_assert(zsr_widget_should_lock('independent_only') === true, 'independent option controls access');
zsr_gate_assert(zsr_widget_should_lock('mirror_only') === false, 'settings mirror cannot lock widgets');
$GLOBALS['gate_options'][ZSR_OPTION]['zsr_widget_exclude'] = array('independent_only');
zsr_invalidate_widget_cache(ZSR_OPTION);
zsr_gate_assert(zsr_widget_should_lock('independent_only') === false, 'explicit exclusion wins');
foreach (array('widget_ui_search', 'widget_ui_user', 'zib_widget_ui_search', 'zib_widget_ui_user') as $id) {
    $GLOBALS['gate_options']['zsr_widget_locked'][$id] = '1';
    zsr_invalidate_widget_cache('zsr_widget_locked');
    zsr_gate_assert(zsr_widget_should_lock($id) === true, 'selected search and user widgets can be locked: ' . $id);
}
$GLOBALS['gate_options'][ZSR_OPTION]['zsr_widget_exclude'] = array();
zsr_invalidate_widget_cache(ZSR_OPTION);
$GLOBALS['gate_manage'] = true;
zsr_gate_assert(zsr_widget_should_lock('independent_only') === false, 'administrator bypass is respected');
$GLOBALS['gate_options'][ZSR_OPTION]['zsr_widget_admin_bypass'] = false;
zsr_invalidate_widget_cache(ZSR_OPTION);
zsr_gate_assert(zsr_widget_should_lock('independent_only') === true, 'administrator bypass can be disabled');

foreach (array(true, false) as $csf) {
    $id_base = $csf ? 'plain_module' : 'zib_widget_fake_csf';
    zsr_gate_reset(array('zsr_widget_enable' => true), array($id_base => '1'));
    $widget = zsr_gate_register($id_base, $csf);
    zsr_register_widget_gates();
    foreach (array(2, 5) as $number) {
        $id = $id_base . '-' . $number;
        $callback = $GLOBALS['wp_registered_widgets'][$id]['callback'];
        zsr_gate_assert($csf ? $callback === array($widget, 'display_callback') : $callback === 'zsr_locked_widget_callback', 'CSF classification follows object type, not prefix');
    }
    if ($csf) {
        $entry = $GLOBALS['gate_hooks']['widget_is_show_' . $id_base][9999]['zsr_filter_widget_visibility'];
        zsr_gate_assert($entry[1] === 3, 'CSF visibility hook has late priority and complete arguments');
    } else {
        zsr_gate_assert(isset($GLOBALS['zsr_widget_original_callbacks'][$id_base . '-2'], $GLOBALS['zsr_widget_original_callbacks'][$id_base . '-5']), 'legacy callbacks are preserved per instance');
    }
    zsr_register_widget_gates();
    foreach (array('post_sidebar', 'all_top_fluid') as $sidebar) {
        foreach (array(2, 5) as $number) {
            $id = $id_base . '-' . $number;
            $html = zsr_gate_render($id, $sidebar);
            zsr_gate_html($html, $id, strpos($sidebar, 'fluid') !== false, $csf);
            zsr_gate_assert(strpos($html, '实例二') === false && strpos($html, '实例五') === false, 'placeholder titles are hidden by default');
            zsr_gate_assert(strpos($html, 'signin-loader') !== false, 'placeholder uses the theme login helper');
            if ($number === 5) {
                zsr_gate_assert(strpos($html, 'hidden-xs') !== false, 'responsive visibility class survives placeholder output');
            }
        }
    }
    $GLOBALS['gate_options'][ZSR_OPTION]['zsr_widget_hide_title'] = false;
    zsr_invalidate_widget_cache(ZSR_OPTION);
    $html = zsr_gate_render($id_base . '-2');
    zsr_gate_html($html, $id_base . '-2', false, $csf);
    zsr_gate_assert(strpos($html, '<h3 class="theme-title">') !== false && strpos($html, '实例二') !== false, 'visible title uses sidebar title wrappers');
    zsr_gate_assert(strpos($html, '<script>') === false && strpos($html, '&amp;') !== false, 'visible title is escaped');
    $html = zsr_gate_render($id_base . '-5');
    zsr_gate_assert(strpos($html, '实例五') !== false && strpos($html, '实例二') === false, 'instance number retrieves the matching settings');
    $html = zsr_gate_render($id_base . '-5', 'post_sidebar', 5);
    zsr_gate_assert(strpos($html, '实例五') !== false && strpos($html, 'PRIVATE_WIDGET_BODY_') === false, 'numeric widget argument reads the matching instance');
    zsr_gate_assert(zsr_gate_render($id_base . '-2', 'post_sidebar', array('number' => 99)) === '', 'missing instance is not replaced by the multiwidget marker');
    $GLOBALS['gate_login_html'] = '<a href="javascript:;" class="signin-loader but">主题登录</a><a class="signup-loader but" href="javascript:;">主题注册</a>';
    $html = zsr_gate_render($id_base . '-2');
    zsr_gate_assert(strpos($html, 'href="https://example.test/member-access?tab=signin"') !== false, 'login guide uses the theme login page rather than a sanitized script URL');
    zsr_gate_assert(strpos($html, 'href="https://example.test/member-access?tab=signup"') !== false, 'registration guide uses the theme registration page');
    zsr_gate_assert(strpos($html, 'signin-loader') !== false && strpos($html, 'signup-loader') !== false, 'valid page links keep the theme modal triggers');
    zsr_gate_assert(strpos($html, 'javascript:') === false && strpos($html, 'href=";"') === false, 'login guide never emits a broken or executable navigation URL');
    $GLOBALS['gate_login_html'] = '<a class="signin-loader" href="javascript:;">主题登录</a>';
    $html = zsr_gate_render($id_base . '-2');
    zsr_gate_assert(strpos($html, 'signup-loader') === false, 'disabled theme registration is not reintroduced');
    $GLOBALS['gate_login_html'] = '';
    $html = zsr_gate_render($id_base . '-2');
    zsr_gate_assert(strpos($html, 'signin-loader') === false && strpos($html, 'wp-login.php') === false, 'closed theme login does not get a forced replacement link');
    $GLOBALS['gate_options'][ZSR_OPTION]['zsr_widget_visitor_action'] = 'upgrade';
    zsr_invalidate_widget_cache(ZSR_OPTION);
    $html = zsr_gate_render($id_base . '-2');
    zsr_gate_html($html, $id_base . '-2', false, $csf);
    zsr_gate_assert(count($GLOBALS['gate_vip_calls']) === 1 && strpos($html, 'pay-vip') !== false, 'upgrade uses the theme purchase helper');
    zsr_gate_assert($GLOBALS['gate_card_calls'] === 0 && strpos($html, 'PRIVATE_USER_CARD_SENTINEL') === false, 'upgrade never fabricates a user card');
    $GLOBALS['gate_options'][ZSR_OPTION]['zsr_widget_visitor_action'] = 'hidden';
    zsr_invalidate_widget_cache(ZSR_OPTION);
    zsr_gate_assert(zsr_gate_render($id_base . '-2', 'all_top_fluid') === '', 'hidden mode produces no placeholder or wrapper');
    $GLOBALS['gate_options'][ZSR_OPTION]['zsr_widget_visitor_action'] = 'placeholder';
    foreach (array('logged', 'disabled', 'admin', 'excluded', 'unlocked', 'administrator') as $bypass) {
        $GLOBALS['gate_logged'] = $bypass === 'logged';
        $GLOBALS['gate_admin'] = $bypass === 'admin';
        $GLOBALS['gate_manage'] = $bypass === 'administrator';
        $GLOBALS['gate_options'][ZSR_OPTION]['zsr_widget_enable'] = $bypass !== 'disabled';
        $GLOBALS['gate_options'][ZSR_OPTION]['zsr_widget_exclude'] = $bypass === 'excluded' ? array($id_base) : array();
        $GLOBALS['gate_options']['zsr_widget_locked'] = $bypass === 'unlocked' ? array() : array($id_base => '1');
        zsr_invalidate_widget_cache();
        $GLOBALS['gate_callbacks'] = array();
        $args = array('number' => 2, 'fixture_passthrough' => 'keep');
        $html = zsr_gate_render($id_base . '-2', 'all_top_fluid', $args);
        zsr_gate_assert(strpos($html, 'PRIVATE_WIDGET_BODY_' . $id_base . '-2') !== false, $bypass . ' releases an already registered gate');
        zsr_gate_assert(count($GLOBALS['gate_callbacks']) === 1 && $GLOBALS['gate_callbacks'][0][1] === zsr_gate_args($id_base . '-2', 'all_top_fluid') && $GLOBALS['gate_callbacks'][0][2] === $args, 'allowed callback keeps all original arguments');
    }
}

foreach (array(false, '', null, 0, '0') as $hidden) {
    zsr_gate_reset(array('zsr_widget_enable' => true), array('plain_module' => '1'));
    zsr_gate_register('plain_module');
    add_filter('widget_is_show_plain_module', function ($show) use ($hidden) { return $hidden; }, 9998, 3);
    zsr_register_widget_gates();
    zsr_gate_assert(zsr_gate_render('plain_module-2') === '', 'existing noaccessguard hidden value is preserved: ' . var_export($hidden, true));
    zsr_gate_assert($GLOBALS['gate_login_calls'] === 0, 'earlier hidden filter prevents placeholder work');
}

foreach (array(true, false) as $csf) {
    zsr_gate_reset(array('zsr_widget_enable' => true), array('plain_module' => '1'));
    zsr_gate_register('plain_module', $csf);
    add_filter('widget_display_callback', function ($instance, $object, $args) { return false; }, 10, 3);
    zsr_register_widget_gates();
    zsr_gate_assert(zsr_gate_render('plain_module-2') === '', 'WordPress widget_display_callback cancellation wins for both tracks');
    zsr_gate_assert($GLOBALS['gate_login_calls'] === 0, 'cancelled display does not invoke login rendering');
}

zsr_gate_reset(array('zsr_widget_enable' => true), array('plain_module' => '1'));
$widget = zsr_gate_register('plain_module');
$widget->settings[2]['fixture_hidden'] = true;
zsr_register_widget_gates();
zsr_gate_assert(zsr_gate_render('plain_module-2') === '', 'CSF intrinsic visibility hides the placeholder');
zsr_gate_reset(array('zsr_widget_enable' => true), array('plain_module' => '1'));
zsr_gate_register('plain_module', false);
$GLOBALS['gate_mobile'] = true;
zsr_register_widget_gates();
zsr_gate_assert(zsr_gate_render('plain_module-5') === '', 'legacy theme mobile visibility hides the placeholder');

zsr_gate_reset(array('zsr_widget_enable' => true), array('plain_module' => '1'));
$widget = zsr_gate_register('plain_module');
zsr_register_widget_gates();
$GLOBALS['gate_options']['zsr_widget_locked'] = array();
zsr_invalidate_widget_cache('zsr_widget_locked');
foreach (array(true, 'hidden-xs', 'visible-xs-block') as $show) {
    ob_start();
    $filtered = apply_filters('widget_is_show_plain_module', $show, zsr_gate_args('plain_module-2'), $widget->settings[2]);
    $output = ob_get_clean();
    zsr_gate_assert($filtered === $show && $output === '', 'unlocked visibility value passes through without coercion');
}

foreach (array(true, false) as $csf) {
    zsr_gate_reset(array('zsr_widget_enable' => true, 'zsr_widget_visitor_action' => 'upgrade'), array('plain_module' => '1'));
    zsr_gate_register('plain_module', $csf);
    $GLOBALS['gate_login_html'] = '';
    add_filter('gettext', function ($translation, $text, $domain) {
        $translations = array('此模块仅登录后可见。' => '<em>Login required</em>', '了解会员升级' => 'Membership upgrades');
        return $domain === 'zib-sub-review' && isset($translations[$text]) ? $translations[$text] : $translation;
    }, 10, 3);
    zsr_register_widget_gates();
    $html = zsr_gate_render('plain_module-2');
    zsr_gate_assert(strpos($html, '&lt;em&gt;Login required&lt;/em&gt;') !== false, 'registered widget callback translates and escapes the placeholder');
    zsr_gate_assert(strpos($html, 'Membership upgrades') !== false, 'registered widget callback translates the membership action');
}

foreach (array('--without-version', '--without-file', '--missing-file') as $mode) {
    $command = array(PHP_BINARY, __FILE__, $mode);
    if (PHP_VERSION_ID < 70400) {
        $command = implode(' ', array_map('escapeshellarg', $command));
    }
    $process = proc_open($command, array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
    zsr_gate_assert(is_resource($process), 'plugin marker subprocess starts');
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $status = proc_close($process);
    zsr_gate_assert($status === 0 && $errors === '' && strpos($output, 'widget marker guard passed') !== false, 'plugin marker guard: ' . $mode . ' ' . $errors);
}

fwrite(STDOUT, 'widget gate tests passed (' . $gate_assertions . " assertions)\n");
