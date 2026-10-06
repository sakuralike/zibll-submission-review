<?php

if (!defined('ABSPATH')) {
    exit;
}

function zsr_widget_lock_active()
{
    $options = zsr_get_widget_options();
    if (!zsr_bool($options['zsr_widget_enable'])) {
        return false;
    }
    if (!defined('ZSR_VERSION') || !defined('ZSR_FILE') || !is_file(ZSR_FILE)) {
        if (function_exists('zsr_log')) {
            zsr_log('warning', 'widget.unavailable', array('reason_code' => 'plugin_missing'));
        }
        return false;
    }
    return true;
}

function zsr_widget_should_lock($id_base, $widget_id = '')
{
    if (is_admin() || is_user_logged_in() || !zsr_widget_lock_active()) {
        return false;
    }
    $options = zsr_get_widget_options();
    if (zsr_bool($options['zsr_widget_admin_bypass']) && current_user_can('manage_options')) {
        return false;
    }
    if (isset($options['zsr_widget_excluded_map'][$id_base]) || ($widget_id !== '' && isset($options['zsr_widget_excluded_map'][$widget_id]))) {
        return false;
    }
    $locked = zsr_get_locked_widgets();
    return isset($locked[$id_base]) || ($widget_id !== '' && isset($locked[$widget_id]));
}

function zsr_register_widget_gates()
{
    if (is_admin() || is_user_logged_in() || !zsr_widget_lock_active()) {
        return;
    }
    $locked = zsr_get_locked_widgets();
    if (!$locked) {
        return;
    }
    global $wp_registered_widgets, $zsr_widget_original_callbacks;
    if (!is_array($zsr_widget_original_callbacks)) {
        $zsr_widget_original_callbacks = array();
    }
    foreach ((array) $wp_registered_widgets as $widget_id => $registered) {
        $callback = isset($registered['callback']) ? $registered['callback'] : null;
        if (!is_array($callback) || !isset($callback[0]) || !is_object($callback[0]) || !is_a($callback[0], 'WP_Widget') || empty($callback[0]->id_base)) {
            continue;
        }
        $widget = $callback[0];
        $id_base = $widget->id_base;
        if (!zsr_widget_should_lock($id_base, $widget_id)) {
            continue;
        }
        if (is_a($widget, 'CSF_Widget')) {
            $hook = 'widget_is_show_' . $id_base;
            if (has_filter($hook, 'zsr_filter_widget_visibility') === false) {
                add_filter($hook, 'zsr_filter_widget_visibility', 9999, 3);
            }
        } else {
            $zsr_widget_original_callbacks[$widget_id] = array('callback' => $callback, 'object' => $widget, 'id_base' => $id_base);
            $wp_registered_widgets[$widget_id]['callback'] = 'zsr_locked_widget_callback';
        }
    }
}

function zsr_filter_widget_visibility($show_class, $args, $instance)
{
    $id_base = substr(current_filter(), strlen('widget_is_show_'));
    $widget_id = isset($args['widget_id']) && is_string($args['widget_id']) ? $args['widget_id'] : '';
    if (!$show_class || !zsr_widget_should_lock($id_base, $widget_id)) {
        return $show_class;
    }
    zsr_render_locked_widget($args, $instance, $show_class, true);
    return false;
}

function zsr_locked_widget_callback($args, $widget_args = array())
{
    global $zsr_widget_original_callbacks;
    $widget_id = isset($args['widget_id']) ? $args['widget_id'] : '';
    if (!isset($zsr_widget_original_callbacks[$widget_id])) {
        return;
    }
    $original = $zsr_widget_original_callbacks[$widget_id];
    if (!zsr_widget_should_lock($original['id_base'], $widget_id)) {
        return call_user_func_array($original['callback'], func_get_args());
    }
    $widget = $original['object'];
    $number = is_numeric($widget_args) ? (int) $widget_args : (isset($widget_args['number']) ? (int) $widget_args['number'] : $widget->number);
    $settings = $widget->get_settings();
    if (!isset($settings[$number]) || !is_array($settings[$number])) {
        return;
    }
    $widget->_set($number);
    $instance = apply_filters('widget_display_callback', $settings[$number], $widget, $args);
    if ($instance === false || !is_array($instance)) {
        return;
    }
    $show_class = function_exists('zib_widget_is_show') ? zib_widget_is_show($instance) : true;
    if (!$show_class) {
        return;
    }
    zsr_render_locked_widget($args, $instance, $show_class, false);
}

function zsr_render_locked_widget($args, $instance, $show_class = true, $csf = false)
{
    $options = zsr_get_widget_options();
    $mode = $options['zsr_widget_visitor_action'];
    $log_level = defined('ZSR_LOG_LEVEL') ? strtolower(trim((string) ZSR_LOG_LEVEL))
        : (zsr_bool($options['zsr_log_enable']) ? strtolower(trim((string) $options['zsr_log_level'])) : 'off');
    if ($log_level === 'debug' && function_exists('zsr_log')) {
        zsr_log('debug', 'widget.blocked', array(
            'widget_id' => isset($args['widget_id']) ? $args['widget_id'] : '',
            'mode' => $mode,
            'track' => $csf ? 'csf' : 'legacy',
        ));
    }
    if ($mode === 'hidden') {
        return;
    }
    $classes = is_string($show_class) ? ' ' . $show_class : '';
    if ($csf) {
        echo '<div class="zib-widget-wrap zsr-widget-placeholder' . esc_attr($classes) . '"><div class="widget-container"><div class="zib-widget box-body">';
    } else {
        echo isset($args['before_widget']) ? wp_kses_post($args['before_widget']) : '<div class="zib-widget">';
        echo '<div class="zsr-widget-placeholder box-body' . esc_attr($classes) . '">';
    }
    if (!zsr_bool($options['zsr_widget_hide_title']) && !empty($instance['title']) && is_scalar($instance['title'])) {
        echo isset($args['before_title']) ? wp_kses_post($args['before_title']) : '<h3>';
        echo esc_html((string) $instance['title']);
        echo isset($args['after_title']) ? wp_kses_post($args['after_title']) : '</h3>';
    }
    if (function_exists('zib_get_user_singin_page_box')) {
        $guide = zib_get_user_singin_page_box('box-body', esc_html__('登录后可查看此模块', 'zib-sub-review'));
        echo $guide ? wp_kses_post($guide) : '<p>' . esc_html__('此模块仅登录后可见。', 'zib-sub-review') . '</p>';
    } else {
        echo '<p>' . esc_html__('此模块仅登录后可见。', 'zib-sub-review') . '<a href="' . esc_url(wp_login_url()) . '">' . esc_html__('登录', 'zib-sub-review') . '</a></p>';
    }
    if ($mode === 'upgrade' && function_exists('zibpay_get_payvip_button')
        && (!function_exists('zib_is_close_sign') || !zib_is_close_sign())) {
        $level = !function_exists('_pz') || _pz('pay_user_vip_1_s', true) ? 1 : (_pz('pay_user_vip_2_s', true) ? 2 : 0);
        if ($level) {
            echo wp_kses(zibpay_get_payvip_button($level, 'but jb-yellow', esc_html__('了解会员升级', 'zib-sub-review')), array('a' => array('class' => true, 'href' => true, 'vip-level' => true)));
        }
    }
    if ($csf) {
        echo '</div></div></div>';
    } else {
        echo '</div>';
        echo isset($args['after_widget']) ? wp_kses_post($args['after_widget']) : '</div>';
    }
}
