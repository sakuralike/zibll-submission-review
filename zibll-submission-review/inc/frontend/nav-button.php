<?php

if (!defined('ABSPATH')) {
    exit;
}

function zsr_filter_nav_radius_button($buttons, $user_id = 0)
{
    $user_id = (int) $user_id;
    if ($user_id < 1 || !zsr_can_review($user_id)) {
        return $buttons;
    }

    $url = zsr_page_url();
    if (!$url) {
        return $buttons;
    }

    $url = add_query_arg('view', 'review', $url);
    $button = '<a class="but nowave jb-blue radius ml10 zsr-review-nav-button" href="' . esc_url($url) . '"><i class="fa fa-fw fa-check" aria-hidden="true"></i>' . esc_html__('审核', 'zib-sub-review') . '</a>';
    return (string) $buttons . $button;
}

if (function_exists('add_filter')) {
    add_filter('zib_nav_radius_button', 'zsr_filter_nav_radius_button', 20, 2);
}
