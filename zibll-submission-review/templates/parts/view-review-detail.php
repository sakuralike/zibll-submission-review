<?php

if (!defined('ABSPATH')) {
    exit;
}

$post_id = isset($_GET['post_id']) ? absint($_GET['post_id']) : 0;
$post = zsr_get_review_post($post_id, get_current_user_id(), true);
?>
<article class="article main-bg theme-box box-body radius8 main-shadow">
    <?php if (!$post) : ?>
        <p class="muted-2-color">稿件不存在、已处理或您没有查看权限。</p>
    <?php else : ?>
        <h2 class="title-theme"><?php echo esc_html($post->post_title ?: '无标题'); ?></h2>
        <p class="badge mb10">状态：<?php echo esc_html(get_post_meta($post->ID, 'zsr_state', true) ?: 'pending'); ?></p>
        <div class="muted-2-color em09 mb20"><?php echo esc_html(get_the_modified_date('', $post)); ?> · <?php echo esc_html(get_the_author_meta('display_name', $post->post_author)); ?></div>
        <div class="wp-posts-content mb20"><?php echo wp_kses_post($post->post_content); ?></div>
        <form class="zsr-review-form" method="post" ajax-submit>
            <textarea class="form-control mb10" name="msg" rows="4" maxlength="<?php echo esc_attr((string) zsr_get_option('zsr_reason_maxlength', 200)); ?>" placeholder="驳回或退回时填写意见"></textarea>
            <input type="hidden" name="post_id" value="<?php echo esc_attr((string) $post->ID); ?>">
            <?php wp_nonce_field('zsr_review', '_wpnonce', false, false); ?>
            <div class="but-average">
                <?php $actions = (array) zsr_get_option('zsr_actions', array('approve', 'reject', 'return')); ?>
                <?php if (in_array('approve', $actions, true)) : ?><button type="button" class="but wp-ajax-submit" form-action="zsr_review" data-review-method="approve">通过</button><?php endif; ?>
                <?php if (in_array('reject', $actions, true)) : ?><button type="button" class="but c-red wp-ajax-submit" form-action="zsr_review" data-review-method="reject">驳回</button><?php endif; ?>
                <?php if (in_array('return', $actions, true)) : ?><button type="button" class="but c-yellow wp-ajax-submit" form-action="zsr_review" data-review-method="return">退回</button><?php endif; ?>
            </div>
        </form>
        <?php $history = zsr_get_review_history($post->ID); ?>
        <?php if ($history) : ?>
            <div class="mt20"><h3 class="title-theme">审核记录</h3>
                <?php foreach (array_reverse($history) as $event) : ?>
                    <p class="muted-2-color em09"><?php echo esc_html($event['time'] ?? ''); ?> · <?php echo esc_html($event['reviewer_name'] ?? ''); ?> · <?php echo esc_html($event['method'] ?? ''); ?> · <?php echo esc_html(($event['from_status'] ?? '') . ' → ' . ($event['to_status'] ?? '')); ?><?php if (!empty($event['msg'])) : ?>：<?php echo esc_html($event['msg']); ?><?php endif; ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</article>
