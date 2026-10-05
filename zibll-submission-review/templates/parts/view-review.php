<?php

if (!defined('ABSPATH')) {
    exit;
}

$paged = isset($_GET['paged']) ? min(50, max(1, (int) $_GET['paged'])) : 1;
$query = zsr_get_review_queue(get_current_user_id(), $paged);
?>
<article class="article main-bg theme-box box-body radius8 main-shadow">
    <h2 class="title-theme"><?php esc_html_e('审核台', 'zib-sub-review'); ?></h2>
    <?php if (!$query || !$query->have_posts()) : ?>
        <p class="muted-2-color"><?php esc_html_e('暂无待审核稿件。', 'zib-sub-review'); ?></p>
    <?php else : ?>
        <div class="posts-mini-lists">
            <?php while ($query->have_posts()) : $query->the_post(); ?>
                <div class="posts-mini">
                    <div class="posts-mini-con flex xx flex1 jsb">
                        <div>
                            <a href="<?php echo esc_url(add_query_arg(array('view' => 'detail', 'post_id' => get_the_ID()), zsr_page_url())); ?>"><?php echo esc_html(get_the_title() ?: __('无标题', 'zib-sub-review')); ?></a>
                            <div class="muted-2-color em09"><?php echo esc_html(get_the_modified_date()); ?> · <?php echo esc_html(get_the_author()); ?></div>
                        </div>
                            <?php $state = get_post_meta(get_the_ID(), 'zsr_state', true); ?>
                            <span class="badge"><?php echo esc_html($state === 'rejected' ? __('已驳回', 'zib-sub-review') : __('待审核', 'zib-sub-review')); ?></span>
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
