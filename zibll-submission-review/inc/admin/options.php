<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Return the repeated role fields used by the plugin settings page.
 *
 * @return array<int, array<string, mixed>>
 */
function zsr_role_fields()
{
    return array(
        array('id' => 'all', 'type' => 'switcher', 'title' => '所有人'),
        array('id' => 'logged', 'type' => 'switcher', 'title' => '已登录用户'),
        array('id' => 'level', 'type' => 'spinner', 'title' => '用户等级最低值', 'default' => 0, 'min' => 0),
        array('id' => 'vip', 'type' => 'spinner', 'title' => 'VIP 等级最低值', 'default' => 0, 'min' => 0),
        array('id' => 'auth', 'type' => 'switcher', 'title' => '认证用户'),
        array('id' => 'moderator', 'type' => 'switcher', 'title' => '版主'),
        array('id' => 'plate_author', 'type' => 'switcher', 'title' => '超级版主'),
        array('id' => 'cat_moderator', 'type' => 'switcher', 'title' => '分区版主'),
    );
}

/**
 * Register CSF settings, or a native settings registration when CSF is absent.
 *
 * @return void
 */
function zsr_register_admin_options()
{
    if (!function_exists('is_admin') || !is_admin()) {
        return;
    }

    if (class_exists('CSF')) {
        CSF::createOptions('zsr_options', array(
            'menu_title'      => '投稿审核设置',
            'menu_slug'       => 'zsr_options',
            'framework_title' => '子比前台投稿审核插件',
            'theme'           => 'light',
            'save_defaults'   => true,
        ));

        CSF::createSection('zsr_options', array(
            'id'     => 'zsr_general',
            'title'  => '基础设置',
            'fields' => array(
                array('id' => 'zsr_enable', 'type' => 'switcher', 'title' => '启用投稿审核功能', 'default' => true),
                array('id' => 'zsr_log_enable', 'type' => 'switcher', 'title' => '启用诊断日志', 'default' => false),
                array('id' => 'zsr_log_level', 'type' => 'select', 'title' => '诊断日志级别', 'options' => array('off' => '关闭', 'error' => '错误', 'warning' => '警告', 'info' => '信息', 'debug' => '调试'), 'default' => 'info'),
                array('id' => 'zsr_enable_submit', 'type' => 'switcher', 'title' => '启用前台投稿表单', 'default' => true),
                array('id' => 'zsr_enable_review', 'type' => 'switcher', 'title' => '启用前台审核台', 'default' => true),
                array('id' => 'zsr_page_slug', 'type' => 'text', 'title' => '前台页面别名', 'default' => 'submissions'),
                array('id' => 'zsr_menu_label', 'type' => 'text', 'title' => '菜单名称', 'default' => '我的投稿'),
                array('id' => 'zsr_show_menu_item', 'type' => 'switcher', 'title' => '显示菜单入口', 'default' => true),
            ),
        ));

        CSF::createSection('zsr_options', array(
            'id'     => 'zsr_roles',
            'title'  => '权限设置',
            'fields' => array(
                array('id' => 'zsr_cap_submit', 'type' => 'fieldset', 'title' => '提交稿件权限', 'fields' => zsr_role_fields()),
                array('id' => 'zsr_cap_review', 'type' => 'fieldset', 'title' => '前台审核权限', 'fields' => zsr_role_fields()),
                array('id' => 'zsr_cap_review_others', 'type' => 'fieldset', 'title' => '审核他人稿件权限', 'fields' => zsr_role_fields()),
                array('id' => 'zsr_review_self_only', 'type' => 'switcher', 'title' => '仅审核本人稿件', 'default' => false),
            ),
        ));

        CSF::createSection('zsr_options', array(
            'id'     => 'zsr_actions',
            'title'  => '审核动作',
            'fields' => array(
                array('id' => 'zsr_actions', 'type' => 'checkbox', 'title' => '启用的审核动作', 'options' => array('approve' => '通过', 'reject' => '驳回', 'return' => '退回'), 'default' => array('approve', 'reject', 'return')),
                array('id' => 'zsr_approve_to_status', 'type' => 'select', 'title' => '通过后状态', 'options' => array('publish' => '发布', 'pending' => '保持待审'), 'default' => 'publish'),
                array('id' => 'zsr_reject_to_status', 'type' => 'select', 'title' => '驳回后状态', 'options' => array('pending' => '待审', 'draft' => '草稿', 'trash' => '回收站'), 'default' => 'pending'),
                array('id' => 'zsr_return_to_status', 'type' => 'select', 'title' => '退回后状态', 'options' => array('draft' => '草稿', 'pending' => '待审'), 'default' => 'draft'),
                array('id' => 'zsr_reject_reason_required', 'type' => 'switcher', 'title' => '驳回意见必填', 'default' => true),
                array('id' => 'zsr_return_reason_required', 'type' => 'switcher', 'title' => '退回意见必填', 'default' => false),
                array('id' => 'zsr_reason_maxlength', 'type' => 'spinner', 'title' => '意见字数上限', 'default' => 200, 'min' => 1, 'max' => 2000),
                array('id' => 'zsr_allow_self_review', 'type' => 'switcher', 'title' => '允许审核自己的稿件', 'default' => false),
                array('id' => 'zsr_allow_re_review', 'type' => 'switcher', 'title' => '允许重复审核', 'default' => true),
                array('id' => 'zsr_re_review_window', 'type' => 'spinner', 'title' => '重复审核冷却小时数', 'default' => 0, 'min' => 0),
            ),
        ));

        CSF::createSection('zsr_options', array(
            'id'     => 'zsr_notify',
            'title'  => '通知设置',
            'fields' => array(
                array('id' => 'zsr_notify_author', 'type' => 'switcher', 'title' => '通知作者', 'default' => true),
                array('id' => 'zsr_notify_channel', 'type' => 'checkbox', 'title' => '通知通道', 'options' => array('msg' => '站内信', 'email' => '邮件'), 'default' => array('msg', 'email')),
                array('id' => 'zsr_notify_approver', 'type' => 'switcher', 'title' => '提醒审核人', 'default' => false),
                array('id' => 'zsr_notify_on_return', 'type' => 'switcher', 'title' => '退回时通知作者', 'default' => true),
                array('id' => 'zsr_notify_include_content', 'type' => 'switcher', 'title' => '通知包含摘要', 'default' => true),
            ),
        ));

        CSF::createSection('zsr_options', array(
            'id'     => 'zsr_widget',
            'title'  => '小工具控制',
            'fields' => array(
                array('id' => 'zsr_widget_enable', 'type' => 'switcher', 'title' => '启用登录可见控制', 'default' => false),
                array('id' => 'zsr_widget_locked', 'type' => 'multicheck', 'title' => '需登录后可见的小工具', 'options' => array(), 'default' => array()),
                array('id' => 'zsr_widget_visitor_action', 'type' => 'radio', 'title' => '访客行为', 'options' => array('placeholder' => '登录引导', 'hidden' => '隐藏', 'upgrade' => '升级引导'), 'default' => 'placeholder'),
                array('id' => 'zsr_widget_admin_bypass', 'type' => 'switcher', 'title' => '管理员旁路', 'default' => true),
                array('id' => 'zsr_widget_hide_title', 'type' => 'switcher', 'title' => '占位时隐藏原标题', 'default' => true),
                array('id' => 'zsr_widget_exclude', 'type' => 'multicheck', 'title' => '排除的小工具', 'options' => array(), 'default' => array()),
            ),
        ));

        add_filter('csf_zsr_options_save', 'zsr_normalize_options', 10, 1);
        add_action('csf_zsr_options_saved', 'zsr_after_options_saved', 10, 2);
        return;
    }

    if (function_exists('register_setting')) {
        register_setting('zsr_options', ZSR_OPTION, array('sanitize_callback' => 'zsr_save_options'));
    }
}

/**
 * Synchronize capabilities after CSF writes the option.
 *
 * @param array $data
 * @param mixed $instance
 * @return void
 */
function zsr_after_options_saved($data, $instance = null)
{
    zsr_sync_capabilities_from_options(zsr_normalize_options($data));
}
