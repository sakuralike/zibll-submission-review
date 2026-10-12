<?php

if (!defined('ABSPATH')) {
    exit;
}

$view = zsr_normalize_view(isset($_GET['view']) ? $_GET['view'] : 'review');
$user_id = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;

if (!$user_id || !zsr_can_review($user_id)) {
    if (function_exists('status_header')) {
        status_header(404);
    }
    if (function_exists('nocache_headers')) {
        nocache_headers();
    }
    if (function_exists('get_header')) {
        get_header();
    }
    if (function_exists('get_template_part')) {
        get_template_part('template/content-404');
    } else {
        echo '<main class="container"><p>' . esc_html__('页面不存在。', 'zib-sub-review') . '</p></main>';
    }
    if (function_exists('get_footer')) {
        get_footer();
    }
    exit;
}

if (function_exists('get_header')) {
    get_header();
}
?>
<main class="container zsr-submissions page-id-<?php echo esc_attr((string) zsr_page_id()); ?>">
    <div class="content-wrap">
        <div class="content-layout">
                <nav class="index-tab zsr-nav" aria-label="<?php esc_attr_e('内容审核', 'zib-sub-review'); ?>">
                    <a class="<?php echo in_array($view, array('review', 'detail'), true) ? 'active' : ''; ?>" href="<?php echo esc_url(add_query_arg('view', 'review', zsr_page_url())); ?>"><?php esc_html_e('审核台', 'zib-sub-review'); ?></a>
                    <a class="<?php echo $view === 'history' ? 'active' : ''; ?>" href="<?php echo esc_url(add_query_arg('view', 'history', zsr_page_url())); ?>"><?php esc_html_e('审核记录', 'zib-sub-review'); ?></a>
                </nav>
                <?php
                if ($view === 'review') {
                    require ZSR_DIR . 'templates/parts/view-review.php';
                } elseif ($view === 'detail') {
                    require ZSR_DIR . 'templates/parts/view-review-detail.php';
                } elseif ($view === 'history') {
                    require ZSR_DIR . 'templates/parts/view-history.php';
                } else {
                    require ZSR_DIR . 'templates/parts/view-review.php';
                }
                ?>
        </div>
    </div>
    <?php if (function_exists('get_sidebar')) { get_sidebar(); } ?>
</main>
<?php
if (function_exists('get_footer')) {
    get_footer();
}
