<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

if (!function_exists('esc_html')) {
    function esc_html($text) { return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false); }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text) { return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false); }
}

if (!function_exists('wp_kses_post')) {
    function wp_kses_post($text)
    {
        return strip_tags((string) $text, '<div><p><a><span><i><b><strong><em><small><br><h1><h2><h3><h4><section><article><ul><ol><li>');
    }
}

if (!function_exists('wp_kses')) {
    function wp_kses($text, $allowed_html)
    {
        return strip_tags((string) $text, '<' . implode('><', array_keys($allowed_html)) . '>');
    }
}

if (!function_exists('__')) {
    function __($text, $domain = 'default')
    {
        return function_exists('apply_filters') ? apply_filters('gettext', $text, $text, $domain) : $text;
    }
}

if (!function_exists('_e')) {
    function _e($text, $domain = 'default') { echo __($text, $domain); }
}

if (!function_exists('esc_html__')) {
    function esc_html__($text, $domain = 'default') { return esc_html(__($text, $domain)); }
}

if (!function_exists('esc_attr__')) {
    function esc_attr__($text, $domain = 'default') { return esc_attr(__($text, $domain)); }
}

if (!function_exists('esc_html_e')) {
    function esc_html_e($text, $domain = 'default') { echo esc_html__($text, $domain); }
}

if (!function_exists('esc_attr_e')) {
    function esc_attr_e($text, $domain = 'default') { echo esc_attr__($text, $domain); }
}
