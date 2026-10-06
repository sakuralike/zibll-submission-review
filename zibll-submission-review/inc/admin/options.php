<?php

if (!defined('ABSPATH')) {
    exit;
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
            'menu_title'      => __('投稿审核设置', 'zib-sub-review'),
            'menu_slug'       => 'zsr_options',
            'framework_title' => __('子比前台投稿审核插件', 'zib-sub-review'),
            'theme'           => 'light',
            'save_defaults'   => true,
        ));

        CSF::createSection('zsr_options', array(
            'id'     => 'zsr_general',
            'title'  => __('基础设置', 'zib-sub-review'),
            'fields' => array(
                array('id' => 'zsr_enable', 'type' => 'switcher', 'title' => __('启用投稿审核功能', 'zib-sub-review'), 'default' => true),
                array('id' => 'zsr_log_enable', 'type' => 'switcher', 'title' => __('启用诊断日志', 'zib-sub-review'), 'default' => false),
                array('id' => 'zsr_log_level', 'type' => 'select', 'title' => __('诊断日志级别', 'zib-sub-review'), 'options' => array('off' => __('关闭', 'zib-sub-review'), 'error' => __('错误', 'zib-sub-review'), 'warning' => __('警告', 'zib-sub-review'), 'info' => __('信息', 'zib-sub-review'), 'debug' => __('调试', 'zib-sub-review')), 'default' => 'info'),
                array('id' => 'zsr_enable_submit', 'type' => 'switcher', 'title' => __('启用前台投稿表单', 'zib-sub-review'), 'default' => true),
                array('id' => 'zsr_enable_review', 'type' => 'switcher', 'title' => __('启用前台审核台', 'zib-sub-review'), 'default' => true),
                array('id' => 'zsr_page_slug', 'type' => 'text', 'title' => __('前台页面别名', 'zib-sub-review'), 'default' => 'submissions'),
                array('id' => 'zsr_menu_label', 'type' => 'text', 'title' => __('菜单名称', 'zib-sub-review'), 'default' => __('我的投稿', 'zib-sub-review')),
                array('id' => 'zsr_show_menu_item', 'type' => 'switcher', 'title' => __('显示菜单入口', 'zib-sub-review'), 'default' => true),
            ),
        ));

        CSF::createSection('zsr_options', array(
            'id'     => 'zsr_roles',
            'title'  => __('权限设置', 'zib-sub-review'),
            'fields' => array(
                array('id' => 'zsr_cap_submit', 'type' => 'checkbox', 'title' => __('提交稿件用户组', 'zib-sub-review'), 'options' => zsr_wordpress_role_choices(), 'default' => array_keys(zsr_wordpress_role_choices())),
                array('id' => 'zsr_cap_review', 'type' => 'checkbox', 'title' => __('前台审核用户组', 'zib-sub-review'), 'options' => zsr_wordpress_role_choices(), 'default' => array('administrator')),
                array('id' => 'zsr_cap_review_others', 'type' => 'checkbox', 'title' => __('审核他人稿件用户组', 'zib-sub-review'), 'options' => zsr_wordpress_role_choices(), 'default' => array('administrator')),
                array('id' => 'zsr_review_self_only', 'type' => 'switcher', 'title' => __('仅审核本人稿件', 'zib-sub-review'), 'default' => false),
            ),
        ));

        CSF::createSection('zsr_options', array(
            'id'     => 'zsr_actions',
            'title'  => __('审核动作', 'zib-sub-review'),
            'fields' => array(
                array('id' => 'zsr_actions', 'type' => 'checkbox', 'title' => __('启用的审核动作', 'zib-sub-review'), 'options' => array('approve' => __('通过', 'zib-sub-review'), 'reject' => __('驳回', 'zib-sub-review'), 'return' => __('退回', 'zib-sub-review')), 'default' => array('approve', 'reject', 'return')),
                array('id' => 'zsr_approve_to_status', 'type' => 'select', 'title' => __('通过后状态', 'zib-sub-review'), 'options' => array('publish' => __('发布', 'zib-sub-review'), 'pending' => __('保持待审', 'zib-sub-review')), 'default' => 'publish'),
                array('id' => 'zsr_reject_to_status', 'type' => 'select', 'title' => __('驳回后状态', 'zib-sub-review'), 'options' => array('pending' => __('待审', 'zib-sub-review'), 'draft' => __('草稿', 'zib-sub-review'), 'trash' => __('回收站', 'zib-sub-review')), 'default' => 'pending'),
                array('id' => 'zsr_return_to_status', 'type' => 'select', 'title' => __('退回后状态', 'zib-sub-review'), 'options' => array('draft' => __('草稿', 'zib-sub-review'), 'pending' => __('待审', 'zib-sub-review')), 'default' => 'draft'),
                array('id' => 'zsr_reject_reason_required', 'type' => 'switcher', 'title' => __('驳回意见必填', 'zib-sub-review'), 'default' => true),
                array('id' => 'zsr_return_reason_required', 'type' => 'switcher', 'title' => __('退回意见必填', 'zib-sub-review'), 'default' => false),
                array('id' => 'zsr_reason_maxlength', 'type' => 'spinner', 'title' => __('意见字数上限', 'zib-sub-review'), 'default' => 200, 'min' => 1, 'max' => 2000),
                array('id' => 'zsr_allow_self_review', 'type' => 'switcher', 'title' => __('允许审核自己的稿件', 'zib-sub-review'), 'default' => false),
                array('id' => 'zsr_allow_re_review', 'type' => 'switcher', 'title' => __('允许重复审核', 'zib-sub-review'), 'default' => true),
                array('id' => 'zsr_re_review_window', 'type' => 'spinner', 'title' => __('重复审核冷却小时数', 'zib-sub-review'), 'default' => 0, 'min' => 0),
            ),
        ));

        CSF::createSection('zsr_options', array(
            'id'     => 'zsr_notify',
            'title'  => __('通知设置', 'zib-sub-review'),
            'fields' => array(
                array('id' => 'zsr_notify_author', 'type' => 'switcher', 'title' => __('通知作者', 'zib-sub-review'), 'default' => true),
                array('id' => 'zsr_notify_channel', 'type' => 'checkbox', 'title' => __('通知通道', 'zib-sub-review'), 'options' => array('msg' => __('站内信', 'zib-sub-review'), 'email' => __('邮件', 'zib-sub-review')), 'default' => array('msg', 'email')),
                array('id' => 'zsr_notify_approver', 'type' => 'switcher', 'title' => __('提醒审核人', 'zib-sub-review'), 'default' => false),
                array('id' => 'zsr_notify_on_return', 'type' => 'switcher', 'title' => __('退回时通知作者', 'zib-sub-review'), 'default' => true),
                array('id' => 'zsr_notify_include_content', 'type' => 'switcher', 'title' => __('通知包含摘要', 'zib-sub-review'), 'default' => true),
            ),
        ));

        CSF::createSection('zsr_options', array(
            'id'     => 'zsr_widget',
            'title'  => __('小工具控制', 'zib-sub-review'),
            'fields' => array(
                array('id' => 'zsr_widget_enable', 'type' => 'switcher', 'title' => __('启用登录可见控制', 'zib-sub-review'), 'default' => false),
                array('id' => 'zsr_widget_locked', 'type' => 'checkbox', 'title' => __('需登录后可见的小工具', 'zib-sub-review'), 'options' => 'zsr_widget_choices', 'default' => array()),
                array('id' => 'zsr_widget_visitor_action', 'type' => 'radio', 'title' => __('访客行为', 'zib-sub-review'), 'options' => array('placeholder' => __('登录引导', 'zib-sub-review'), 'hidden' => __('隐藏', 'zib-sub-review'), 'upgrade' => __('升级引导', 'zib-sub-review')), 'default' => 'hidden'),
                array('id' => 'zsr_widget_admin_bypass', 'type' => 'switcher', 'title' => __('管理员旁路', 'zib-sub-review'), 'default' => true),
                array('id' => 'zsr_widget_hide_title', 'type' => 'switcher', 'title' => __('占位时隐藏原标题', 'zib-sub-review'), 'default' => true),
                array('id' => 'zsr_widget_exclude', 'type' => 'checkbox', 'title' => __('排除的小工具', 'zib-sub-review'), 'options' => 'zsr_widget_choices', 'default' => array()),
                array('id' => 'zsr_guest_hidden_menu_items', 'type' => 'checkbox', 'title' => __('游客隐藏的顶部菜单项', 'zib-sub-review'), 'options' => 'zsr_header_menu_choices', 'default' => array(), 'desc' => __('选中项及其子菜单仅登录后显示；不影响其他位置的菜单。', 'zib-sub-review')),
            ),
        ));

        CSF::createSection('zsr_options', array(
            'id'     => 'zsr_logs',
            'title'  => __('诊断日志', 'zib-sub-review'),
            'fields' => array(
                array('type' => 'callback', 'title' => __('最近诊断日志', 'zib-sub-review'), 'function' => 'zsr_render_admin_logs'),
            ),
        ));

        add_filter('csf_zsr_options_save', 'zsr_prepare_options_for_save', 10, 2);
        add_action('csf_zsr_options_saved', 'zsr_after_options_saved', 10, 2);
        return;
    }

    if (function_exists('register_setting')) {
        register_setting('zsr_options', ZSR_OPTION, array('sanitize_callback' => 'zsr_prepare_options_for_save'));
        add_action('add_option_' . ZSR_OPTION, 'zsr_after_native_options_saved', 10, 2);
        add_action('update_option_' . ZSR_OPTION, 'zsr_after_native_options_saved', 10, 2);
    }
}

function zsr_after_native_options_saved($previous, $value)
{
    zsr_after_options_saved($value);
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
