<?php

if (!defined('ABSPATH')) {
    exit;
}

$view = zsr_normalize_view(isset($_GET['view']) ? $_GET['view'] : 'my');
$user_id = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;

if (in_array($view, array('review', 'detail'), true) && !zsr_can_review($user_id)) {
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
        echo '<main class="container"><p>页面不存在。</p></main>';
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
<main class="container page-id-<?php echo esc_attr((string) zsr_page_id()); ?>">
    <div class="content-wrap">
        <div class="content-layout">
            <?php if (!$user_id) : ?>
                <article class="article main-bg theme-box box-body radius8 main-shadow">
                    <?php
                    if (function_exists('zib_get_user_singin_page_box')) {
                        echo zib_get_user_singin_page_box();
                    } else {
                        echo '<p class="muted-2-color">请登录后查看投稿。</p>';
                    }
                    ?>
                </article>
            <?php else : ?>
                <nav class="index-tab" aria-label="投稿与审核">
                    <a class="<?php echo $view === 'my' ? 'active' : ''; ?>" href="<?php echo esc_url(add_query_arg('view', 'my', zsr_page_url())); ?>">我的投稿</a>
                    <a class="<?php echo $view === 'submit' ? 'active' : ''; ?>" href="<?php echo esc_url(add_query_arg('view', 'submit', zsr_page_url())); ?>">提交稿件</a>
                    <?php if (zsr_can_review()) : ?>
                        <a class="<?php echo in_array($view, array('review', 'detail'), true) ? 'active' : ''; ?>" href="<?php echo esc_url(add_query_arg('view', 'review', zsr_page_url())); ?>">审核台</a>
                        <a class="<?php echo $view === 'history' ? 'active' : ''; ?>" href="<?php echo esc_url(add_query_arg('view', 'history', zsr_page_url())); ?>">审核记录</a>
                    <?php endif; ?>
                </nav>
                <?php
                if ($view === 'my') {
                    require ZSR_DIR . 'templates/parts/view-my.php';
                } elseif ($view === 'submit' || $view === 'edit') {
                    require ZSR_DIR . 'templates/parts/view-submit.php';
                } elseif ($view === 'review') {
                    require ZSR_DIR . 'templates/parts/view-review.php';
                } elseif ($view === 'detail') {
                    require ZSR_DIR . 'templates/parts/view-review-detail.php';
                } elseif ($view === 'history') {
                    require ZSR_DIR . 'templates/parts/view-history.php';
                } else {
                    require ZSR_DIR . 'templates/parts/view-placeholder.php';
                }
                ?>
            <?php endif; ?>
        </div>
    </div>
    <?php if (function_exists('get_sidebar')) { get_sidebar(); } ?>
</main>
<?php
if (function_exists('get_footer')) {
    get_footer();
}
