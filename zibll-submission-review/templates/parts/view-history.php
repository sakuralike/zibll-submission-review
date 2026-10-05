<?php

if (!defined('ABSPATH')) {
    exit;
}

$items = zsr_get_reviewer_history(get_current_user_id(), 50);
?>
<article class="article main-bg theme-box box-body radius8 main-shadow">
    <h2 class="title-theme">审核记录</h2>
    <?php if (!$items) : ?>
        <p class="muted-2-color">暂无审核记录。</p>
    <?php else : ?>
        <div class="posts-mini-lists">
            <?php foreach ($items as $item) : $post = $item['post']; $event = $item['event']; ?>
                <?php $event_url = $post->post_status === 'pending' ? add_query_arg(array('view' => 'detail', 'post_id' => $post->ID), zsr_page_url()) : get_permalink($post); ?>
                <div class="posts-mini">
                    <div class="posts-mini-con flex xx flex1 jsb">
                        <div>
                            <a href="<?php echo esc_url($event_url); ?>"><?php echo esc_html($post->post_title ?: '无标题'); ?></a>
                            <div class="muted-2-color em09"><?php echo esc_html($event['time'] ?? ''); ?> · <?php echo esc_html($event['from_status'] ?? ''); ?> → <?php echo esc_html($event['to_status'] ?? ''); ?></div>
                        </div>
                        <span class="badge"><?php echo esc_html($event['method'] ?? ''); ?></span>
                    </div>
                    <?php if (!empty($event['msg'])) : ?><p class="muted-2-color em09 mt10"><?php echo esc_html($event['msg']); ?></p><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</article>
