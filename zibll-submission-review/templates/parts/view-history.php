<?php

if (!defined('ABSPATH')) {
    exit;
}

$items = zsr_get_reviewer_history(get_current_user_id(), 50);
?>
<article class="article main-bg theme-box box-body radius8 main-shadow zsr-panel">
    <h2 class="title-theme"><?php esc_html_e('审核记录', 'zib-sub-review'); ?></h2>
    <?php if (!$items) : ?>
        <p class="muted-2-color"><?php esc_html_e('暂无审核记录。', 'zib-sub-review'); ?></p>
    <?php else : ?>
        <div class="posts-mini-lists zsr-list">
            <?php foreach ($items as $item) : $review_post = $item['post']; $event = $item['event']; ?>
                <?php
                $event_url = '';
                if (in_array($review_post->post_status, array('pending', 'future'), true)) {
                    $event_url = add_query_arg(array('view' => 'detail', 'post_id' => $review_post->ID), zsr_page_url());
                } elseif ($review_post->post_status === 'publish') {
                    $event_url = get_permalink($review_post);
                }
                $event_status = sanitize_html_class((string) ($review_post->post_status ?: 'unknown'));
                ?>
                <div class="posts-mini">
                    <div class="posts-mini-con flex xx flex1 jsb">
                        <div class="zsr-item-copy">
                            <?php if ($event_url) : ?>
                                <a class="zsr-item-title" href="<?php echo esc_url($event_url); ?>"><?php echo esc_html($review_post->post_title ?: __('无标题', 'zib-sub-review')); ?></a>
                            <?php else : ?>
                                <span class="zsr-item-title"><?php echo esc_html($review_post->post_title ?: __('无标题', 'zib-sub-review')); ?></span>
                            <?php endif; ?>
                            <div class="muted-2-color em09"><?php echo esc_html($event['time'] ?? ''); ?> · <?php echo esc_html($event['from_status'] ?? ''); ?> → <?php echo esc_html($event['to_status'] ?? ''); ?></div>
                        </div>
                        <span class="badge zsr-status zsr-status-<?php echo esc_attr($event_status); ?>"><?php echo esc_html($event['method'] ?? ''); ?></span>
                    </div>
                    <?php if (!empty($event['msg'])) : ?><p class="muted-2-color em09 mt10"><?php echo esc_html($event['msg']); ?></p><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</article>
