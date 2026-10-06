<?php

if (!defined('ABSPATH')) {
    exit;
}

$paged = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
$query = zsr_get_my_submissions(get_current_user_id(), $paged);
?>
<article class="article main-bg theme-box box-body radius8 main-shadow zsr-panel">
    <h2 class="title-theme"><?php esc_html_e('我的投稿', 'zib-sub-review'); ?></h2>
    <?php if (!$query || !$query->have_posts()) : ?>
        <p class="muted-2-color"><?php esc_html_e('暂无投稿。', 'zib-sub-review'); ?></p>
    <?php else : ?>
        <div class="posts-mini-lists zsr-list">
            <?php while ($query->have_posts()) : $query->the_post(); ?>
                <?php
                $status = get_post_status();
                $post_id = get_the_ID();
                $item_url = $status === 'publish' ? get_permalink() : '';
                $state = get_post_meta($post_id, 'zsr_state', true);
                $labels = array(
                    'draft'   => __('草稿', 'zib-sub-review'),
                    'pending' => __('待审核', 'zib-sub-review'),
                    'future'  => __('待发布（定时）', 'zib-sub-review'),
                    'publish' => __('已发布', 'zib-sub-review'),
                    'trash'   => __('已删除', 'zib-sub-review'),
                );
                $label = isset($labels[$status]) ? $labels[$status] : __('未知状态', 'zib-sub-review');
                if ($status === 'pending' && $state === 'approved') {
                    $label = __('待发布', 'zib-sub-review');
                } elseif ($status === 'draft' && $state === 'returned') {
                    $label = __('已退回', 'zib-sub-review');
                }
                $status_class = sanitize_html_class((string) ($status === 'draft' && $state === 'returned' ? 'returned' : $status));
                ?>
                <div class="posts-mini">
                    <div class="posts-mini-con flex xx flex1 jsb">
                        <div class="zsr-item-copy">
                            <?php if ($item_url) : ?>
                                <a class="zsr-item-title" href="<?php echo esc_url($item_url); ?>"><?php echo esc_html(get_the_title() ?: __('无标题', 'zib-sub-review')); ?></a>
                            <?php else : ?>
                                <span class="zsr-item-title"><?php echo esc_html(get_the_title() ?: __('无标题', 'zib-sub-review')); ?></span>
                            <?php endif; ?>
                            <div class="muted-2-color em09"><?php echo esc_html(get_the_modified_date()); ?></div>
                        </div>
                        <span class="badge zsr-status zsr-status-<?php echo esc_attr($status_class); ?>"><?php echo esc_html($label); ?></span>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
        <?php wp_reset_postdata(); ?>
    <?php endif; ?>
</article>
