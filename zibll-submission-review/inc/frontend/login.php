<?php

if (!defined('ABSPATH')) {
    exit;
}

function zsr_get_login_guide($class = 'box-body', $message = null)
{
    if (!function_exists('zib_get_user_singin_page_box')) {
        return '';
    }
    $guide = zib_get_user_singin_page_box($class, $message);
    if (!$guide) {
        return '';
    }
    $guide = preg_replace_callback('/<a\b[^>]*>/i', function ($match) {
        if (!preg_match('/\sclass=([\'"])(.*?)\1/i', $match[0], $attributes)) {
            return $match[0];
        }
        $classes = preg_split('/\s+/', trim($attributes[2]));
        $tab = in_array('signin-loader', $classes, true) ? 'signin' : (in_array('signup-loader', $classes, true) ? 'signup' : '');
        if (!$tab) {
            return $match[0];
        }
        $url = function_exists('zib_get_sign_url') ? zib_get_sign_url($tab) : ($tab === 'signup' ? wp_registration_url() : wp_login_url());
        $tag = preg_replace('/\shref=([\'"]).*?\1/i', '', $match[0]);
        return substr($tag, 0, -1) . ' href="' . esc_url($url) . '">';
    }, $guide);
    add_action('wp_footer', 'zsr_login_guide_script', 30);
    return '<div class="zsr-login-guide">' . wp_kses_post($guide) . '</div>';
}

function zsr_login_guide_script()
{
    ?>
    <script>
    document.addEventListener('click', function (event) {
        var link = event.target.closest && event.target.closest('.zsr-login-guide a.signin-loader,.zsr-login-guide a.signup-loader');
        if (!link || !window.jQuery || !window._win || !_win.bd) {
            return;
        }
        var modal = jQuery('#u_sign').data('bs.modal');
        if (_win.sign_type !== 'page' && modal && modal.isShown) {
            event.preventDefault();
        }
    });
    </script>
    <?php
}
