<?php

if (!defined('ABSPATH')) {
    exit;
}

$post_id = isset($_GET['post_id']) ? absint($_GET['post_id']) : 0;
$review_post = zsr_get_review_post($post_id, get_current_user_id(), true);
?>
<article class="article main-bg theme-box box-body radius8 main-shadow zsr-panel">
    <?php if (!$review_post) : ?>
        <p class="muted-2-color"><?php esc_html_e('稿件不存在、已处理或您没有查看权限。', 'zib-sub-review'); ?></p>
    <?php else : ?>
        <h2 class="title-theme"><?php echo esc_html($review_post->post_title ?: __('无标题', 'zib-sub-review')); ?></h2>
        <?php
        $review_status = get_post_status($review_post);
        $review_state = get_post_meta($review_post->ID, 'zsr_state', true);
        $review_labels = array(
            'pending' => __('待审核', 'zib-sub-review'),
            'future'  => __('待发布（定时）', 'zib-sub-review'),
        );
        $review_label = isset($review_labels[$review_status]) ? $review_labels[$review_status] : __('待审核', 'zib-sub-review');
        if ($review_status === 'pending' && $review_state === 'approved') {
            $review_label = __('待发布', 'zib-sub-review');
        }
        $review_status_class = sanitize_html_class((string) $review_status);
        ?>
        <p class="badge zsr-status zsr-status-<?php echo esc_attr($review_status_class); ?> mb10"><?php esc_html_e('状态：', 'zib-sub-review'); ?><?php echo esc_html($review_label); ?></p>
        <div class="muted-2-color em09 mb20"><?php echo esc_html(get_the_modified_date('', $review_post)); ?> · <?php echo esc_html(get_the_author_meta('display_name', $review_post->post_author)); ?></div>
        <details class="zsr-detail-group" open>
            <summary><?php esc_html_e('文章内容', 'zib-sub-review'); ?></summary>
            <div class="wp-posts-content zsr-detail-body"><?php echo wp_kses_post($review_post->post_content); ?></div>
        </details>
        <div class="zsr-review-details" data-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" data-post-id="<?php echo esc_attr((string) $review_post->ID); ?>" data-nonce="<?php echo esc_attr(wp_create_nonce('zsr_review_details')); ?>" data-error="<?php esc_attr_e('文章属性加载失败，请刷新页面重试。', 'zib-sub-review'); ?>" aria-busy="true">
            <p class="muted-2-color" role="status"><?php esc_html_e('正在读取完整文章属性…', 'zib-sub-review'); ?></p>
        </div>
        <form class="zsr-review-form" method="post" ajax-submit>
            <textarea class="form-control mb10" name="msg" rows="4" maxlength="<?php echo esc_attr((string) zsr_get_option('zsr_reason_maxlength', 200)); ?>" placeholder="<?php esc_attr_e('驳回或退回时填写意见', 'zib-sub-review'); ?>"></textarea>
            <input type="hidden" name="post_id" value="<?php echo esc_attr((string) $review_post->ID); ?>">
            <?php wp_nonce_field('zsr_review', '_wpnonce', false); ?>
            <div class="but-average zsr-review-actions">
                <?php $actions = (array) zsr_get_option('zsr_actions', array('approve', 'reject', 'return')); ?>
                <?php if (in_array('approve', $actions, true)) : ?><button type="button" class="but wp-ajax-submit" form-action="zsr_review" data-review-method="approve"><?php esc_html_e('通过', 'zib-sub-review'); ?></button><?php endif; ?>
                <?php if (in_array('reject', $actions, true)) : ?><button type="button" class="but c-red wp-ajax-submit" form-action="zsr_review" data-review-method="reject"><?php esc_html_e('驳回', 'zib-sub-review'); ?></button><?php endif; ?>
                <?php if (in_array('return', $actions, true)) : ?><button type="button" class="but c-yellow wp-ajax-submit" form-action="zsr_review" data-review-method="return"><?php esc_html_e('退回', 'zib-sub-review'); ?></button><?php endif; ?>
            </div>
        </form>
        <?php $history = zsr_get_review_history($review_post->ID); ?>
        <?php if ($history) : ?>
            <details class="zsr-detail-group mt20"><summary><?php esc_html_e('审核记录', 'zib-sub-review'); ?></summary><div class="zsr-detail-body">
                <?php foreach (array_reverse($history) as $event) : ?>
                    <p class="muted-2-color em09"><?php echo esc_html($event['time'] ?? ''); ?> · <?php echo esc_html($event['reviewer_name'] ?? ''); ?> · <?php echo esc_html($event['method'] ?? ''); ?> · <?php echo esc_html(($event['from_status'] ?? '') . ' → ' . ($event['to_status'] ?? '')); ?><?php if (!empty($event['msg'])) : ?>：<?php echo esc_html($event['msg']); ?><?php endif; ?></p>
                <?php endforeach; ?>
            </div></details>
        <?php endif; ?>
    <?php endif; ?>
</article>
