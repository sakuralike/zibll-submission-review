<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/i18n-helpers.php';

define('ABSPATH', __DIR__ . '/');
define('ZSR_OPTION', 'zsr_options');

$zsr_catalog_options = array();
$zsr_catalog_sidebars = array();
$zsr_catalog_assertions = 0;

class WP_Widget
{
    public $id_base;
    public $name;
    public $settings = array();

    public function __construct($id_base, $name)
    {
        $this->id_base = $id_base;
        $this->name = $name;
    }

    public function display_callback()
    {
    }

    public function get_settings()
    {
        return $this->settings;
    }
}

class CSF_Widget extends WP_Widget
{
}

function get_option($key, $default = false)
{
    global $zsr_catalog_options;
    return array_key_exists($key, $zsr_catalog_options) ? $zsr_catalog_options[$key] : $default;
}

function wp_get_sidebars_widgets()
{
    return $GLOBALS['zsr_catalog_sidebars'];
}

function zib_get_widget_title()
{
    throw new RuntimeException('Theme rendering helpers must not enumerate widget names.');
}

function zsr_catalog_assert($condition, $message)
{
    $GLOBALS['zsr_catalog_assertions']++;
    if (!$condition) {
        fwrite(STDERR, 'FAIL: ' . $message . "\n");
        exit(1);
    }
}

require_once dirname(__DIR__) . '/inc/core/options.php';
require_once dirname(__DIR__) . '/inc/widget/sync.php';
require_once dirname(__DIR__) . '/inc/widget/enumerator.php';

$wp_widget_factory = null;
$wp_registered_widgets = null;
$wp_registered_sidebars = null;
zsr_catalog_assert(zsr_get_registered_widgets() === array(), 'missing widget factory is safe');
zsr_catalog_assert(zsr_get_enabled_widgets() === array(), 'missing sidebar registry is safe');

$tab_posts = new CSF_Widget('zib_widget_ui_tab_post', 'Zibll 多栏目文章(新)');
$tab_posts->settings = array(2 => array('title' => '&lt;b&gt;热门推荐&lt;/b&gt;'), 3 => array('title' => '最近更新'));
$wp_widget_factory = (object) array('widgets' => array($tab_posts));
$wp_registered_sidebars = array('index-bottom' => array('name' => '首页-主内容下面'));
$zsr_catalog_sidebars = array('index-bottom' => array(
    'zib_widget_ui_tab_post-2', 'zib_widget_ui_tab_post-3', 'zib_widget_ui_tab_post-4', 'zib_widget_ui_tab_post-5',
    'zib_widget_ui_tab_post-6', 'zib_widget_ui_tab_post-7', 'zib_widget_ui_tab_post-8', 'zib_widget_ui_tab_post-9',
));
$wp_registered_widgets = array();
foreach (range(2, 9) as $number) {
    $wp_registered_widgets['zib_widget_ui_tab_post-' . $number] = array('callback' => array($tab_posts, 'display_callback'), 'params' => array(array('number' => $number)));
}
$choices = zsr_widget_choices();
zsr_catalog_assert(array_keys($choices) === array(
    'zib_widget_ui_tab_post-2', 'zib_widget_ui_tab_post-3', 'zib_widget_ui_tab_post-4', 'zib_widget_ui_tab_post-5',
    'zib_widget_ui_tab_post-6', 'zib_widget_ui_tab_post-7', 'zib_widget_ui_tab_post-8', 'zib_widget_ui_tab_post-9',
), 'eight copies of the same widget expose eight independently selectable instance ids');
zsr_catalog_assert($choices['zib_widget_ui_tab_post-2'] === 'Zibll 多栏目文章(新)（zib_widget_ui_tab_post-2；首页-主内容下面，第1个同类实例；标题：热门推荐）', 'instance label identifies its plain-text title and position within its widget type and sidebar');
zsr_catalog_assert($choices['zib_widget_ui_tab_post-3'] === 'Zibll 多栏目文章(新)（zib_widget_ui_tab_post-3；首页-主内容下面，第2个同类实例；标题：最近更新）', 'second instance label has its own title and ordinal');
zsr_catalog_assert($choices['zib_widget_ui_tab_post-9'] === 'Zibll 多栏目文章(新)（zib_widget_ui_tab_post-9；首页-主内容下面，第8个同类实例；标题：未设置标题）', 'untitled instances remain distinguishable by id and their order');

$factory_text = new WP_Widget('text', 'Factory text');
$registered_text = new WP_Widget('text', 'Registered text');
$csf_posts = new CSF_Widget('local_posts', '主题文章');
$prefixed = new WP_Widget('zib_widget_plain', '普通第三方工具');
$inactive = new WP_Widget('inactive_widget', '未启用工具');
$wp_widget_factory = (object) array('widgets' => array($factory_text, $prefixed, $inactive, new stdClass()));
$wp_registered_widgets = array(
    'text-2' => array('name' => '注册的文本', 'callback' => array($registered_text, 'display_callback')),
    'text-3' => array('callback' => array($registered_text, 'display_callback')),
    'local_posts-2' => array('name' => '&lt;b&gt;主题文章&lt;/b&gt;', 'callback' => array($csf_posts, 'display_callback')),
    'zib_widget_plain-1' => array('callback' => array($prefixed, 'display_callback')),
    'inactive_widget-1' => array('callback' => array($inactive, 'display_callback')),
    'invalid-1' => array('callback' => array((object) array('id_base' => 'invalid', 'name' => 'Invalid'), 'display_callback')),
    'broken-1' => array('callback' => array($registered_text, 'missing_method')),
    'callable-1' => array('callback' => 'zib_get_widget_title'),
);
$registered = zsr_get_registered_widgets();
zsr_catalog_assert(count($registered) === 4, 'catalogue merges factory and registered callback objects without duplicates');
zsr_catalog_assert($registered['text']['object'] === $registered_text, 'registered callback takes precedence over stale factory');
zsr_catalog_assert($registered['text']['name'] === 'Registered text', 'registered callback name takes precedence over stale factory');
zsr_catalog_assert($registered['local_posts']['csf'] === true, 'local factory CSF object detected without id prefix');
zsr_catalog_assert($registered['local_posts']['name'] === '主题文章', 'registered names are plain text');
zsr_catalog_assert($registered['zib_widget_plain']['csf'] === false, 'a theme-like id does not imply CSF');
zsr_catalog_assert(!isset($registered['invalid'], $registered['broken'], $registered['callable']), 'invalid callback entries rejected');

$wp_registered_sidebars = array(
    'sidebar-main' => array('name' => '主侧栏'),
    'sidebar-footer' => array('name' => '&lt;b&gt;页脚&lt;/b&gt;'),
    'wp_inactive_widgets' => array('name' => '停用'),
    'orphaned_widgets' => array('name' => '遗留'),
    'orphaned_widgets_1' => array('name' => '遗留一'),
    'array_version' => array('name' => '错误侧栏'),
);
$zsr_catalog_sidebars = array(
    'sidebar-main' => array('text-2', 'text-2', 'text-3', 'local_posts-2', 'no-longer-registered', 'invalid-1', 'broken-1', 'callable-1', array('text-2')),
    'sidebar-footer' => array('text-2', 'zib_widget_plain-1'),
    'wp_inactive_widgets' => array('inactive_widget-1'),
    'orphaned_widgets' => array('inactive_widget-1'),
    'orphaned_widgets_1' => array('inactive_widget-1'),
    'array_version' => array('inactive_widget-1'),
    'removed-sidebar' => array('inactive_widget-1'),
);
$enabled = zsr_get_enabled_widgets();
zsr_catalog_assert(array_keys($enabled) === array('text', 'local_posts', 'zib_widget_plain'), 'only registered widgets in active registered sidebars are enabled');
zsr_catalog_assert($enabled['text']['count'] === 2, 'instance count deduplicates repeated assignments across sidebars');
zsr_catalog_assert($enabled['text']['sidebars']['sidebar-main'] === array('text-2', 'text-3'), 'instance list excludes duplicates');
zsr_catalog_assert($enabled['text']['sidebars']['sidebar-footer'] === array('text-2'), 'sidebar associations retained');
zsr_catalog_assert($enabled['local_posts']['count'] === 1, 'CSF registered outside global factory enumerated');
zsr_catalog_assert(!isset($enabled['inactive_widget']), 'inactive and orphaned-only widgets excluded');

$choices = zsr_widget_choices();
zsr_catalog_assert(array_keys($choices) === array('text-2', 'text-3', 'local_posts-2', 'zib_widget_plain-1'), 'all enabled widget instances offered before any locks are selected');
zsr_catalog_assert(strpos($choices['text-2'], 'text-2；主侧栏，第1个同类实例、页脚，第1个同类实例；标题：') !== false, 'repeated assignments produce one choice that retains both sidebar locations');
zsr_catalog_assert(strpos($choices['text-2'], '<') === false && strpos($choices['local_posts-2'], '<') === false, 'choice labels omit markup');

$zsr_catalog_options['zsr_widget_locked'] = array('text' => '1', 'inactive_widget' => '1', 'inactive_widget-1' => '1', 'removed_plugin' => '1', 'removed_plugin-9' => '1');
$zsr_catalog_options[ZSR_OPTION] = array('zsr_widget_exclude' => array('disabled_exclusion', 'disabled_exclusion-3'));
zsr_invalidate_widget_cache();
$choices = zsr_widget_choices();
zsr_catalog_assert(isset($choices['已启用'], $choices['已配置但未启用']), 'inactive configured types receive a separate checkbox group');
zsr_catalog_assert(isset($choices['已配置但未启用']['inactive_widget']), 'registered disabled type can retain its saved selection');
zsr_catalog_assert(isset($choices['已配置但未启用']['removed_plugin']), 'unregistered configured type can retain its saved selection');
zsr_catalog_assert(isset($choices['已配置但未启用']['disabled_exclusion']), 'disabled excluded type remains selectable');
zsr_catalog_assert(!isset($choices['已配置但未启用']['text']), 'active configured type is not duplicated');
zsr_catalog_assert(strpos($choices['已配置但未启用']['inactive_widget'], '未启用工具') === 0, 'inactive registered type retains human-readable name');
zsr_catalog_assert($choices['已配置但未启用']['inactive_widget-1'] === '未启用工具（inactive_widget-1）', 'a disabled configured instance keeps its type name and can still be unchecked');
zsr_catalog_assert(isset($choices['已配置但未启用']['removed_plugin-9'], $choices['已配置但未启用']['disabled_exclusion-3']), 'missing configured and excluded instances are retained as selectable entries');

$wp_registered_sidebars['new-sidebar'] = array('name' => '新侧栏');
$zsr_catalog_sidebars['new-sidebar'] = array('inactive_widget-1');
zsr_catalog_assert(isset(zsr_get_enabled_widgets()['inactive_widget']), 'catalogue reflects sidebar changes without stale static cache');
$choices = zsr_widget_choices();
zsr_catalog_assert(isset($choices['已启用']['inactive_widget-1']) && !isset($choices['已配置但未启用']['inactive_widget']), 're-enabled type returns as an instance option without an extra type checkbox');

$zsr_catalog_sidebars = array();
$choices = zsr_widget_choices();
zsr_catalog_assert(!isset($choices['已启用']) && isset($choices['已配置但未启用']), 'empty active group is not emitted to CSF checkbox');

fwrite(STDOUT, 'widget-catalog tests passed (' . $zsr_catalog_assertions . " assertions)\n");
