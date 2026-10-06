<?php

if (!defined('ABSPATH')) {
    exit;
}

$paged = isset($_GET['paged']) ? min(50, max(1, (int) $_GET['paged'])) : 1;
$query = zsr_get_review_queue(get_current_user_id(), $paged);
?>
<article class="article main-bg theme-box box-body radius8 main-shadow zsr-panel">
    <h2 class="title-theme"><?php esc_html_e('审核台', 'zib-sub-review'); ?></h2>
    <?php if (!$query || !$query->have_posts()) : ?>
        <p class="muted-2-color"><?php esc_html_e('暂无待审核稿件。', 'zib-sub-review'); ?></p>
    <?php else : ?>
        <div class="posts-mini-lists zsr-list">
            <?php while ($query->have_posts()) : $query->the_post(); ?>
                <?php
                $post_status = get_post_status();
                $state = get_post_meta(get_the_ID(), 'zsr_state', true);
                $status_labels = array(
                    'pending' => __('待审核', 'zib-sub-review'),
                    'future'  => __('待发布（定时）', 'zib-sub-review'),
                );
                $status_label = isset($status_labels[$post_status]) ? $status_labels[$post_status] : __('待审核', 'zib-sub-review');
                if ($post_status === 'pending' && $state === 'approved') {
                    $status_label = __('待发布', 'zib-sub-review');
                }
                $status_class = sanitize_html_class((string) $post_status);
                ?>
                <div class="posts-mini">
                    <div class="posts-mini-con flex xx flex1 jsb">
                        <div class="zsr-item-copy">
                            <a class="zsr-item-title" href="<?php echo esc_url(add_query_arg(array('view' => 'detail', 'post_id' => get_the_ID()), zsr_page_url())); ?>"><?php echo esc_html(get_the_title() ?: __('无标题', 'zib-sub-review')); ?></a>
                            <div class="muted-2-color em09"><?php echo esc_html(get_the_modified_date()); ?> · <?php echo esc_html(get_the_author()); ?></div>
                        </div>
                        <span class="badge zsr-status zsr-status-<?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_label); ?></span>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
        <?php wp_reset_postdata(); ?>
        <?php if ((int) $query->max_num_pages > 50) : ?>
            <p class="muted-2-color em09"><?php esc_html_e('待审核稿件较多，超过前 50 页的内容请到 WordPress 后台处理。', 'zib-sub-review'); ?></p>
        <?php endif; ?>
        <?php if (function_exists('paginate_links') && $query->max_num_pages > 1) : ?>
            <div class="theme-pagination mt20"><?php echo wp_kses_post(paginate_links(array('current' => $paged, 'total' => min(50, (int) $query->max_num_pages), 'type' => 'list'))); ?></div>
        <?php endif; ?>
    <?php endif; ?>
</article>
