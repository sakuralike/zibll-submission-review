<?php

if (!defined('ABSPATH')) {
    exit;
}

$is_edit = isset($view) && $view === 'edit';
$post_id = $is_edit && isset($_GET['post_id']) ? absint($_GET['post_id']) : 0;
$edit_post = $post_id ? get_post($post_id) : null;
$can_edit = !$is_edit || (
    $edit_post
    && $edit_post->post_type === 'post'
    && (int) $edit_post->post_author === get_current_user_id()
    && in_array($edit_post->post_status, array('draft', 'pending'), true)
);
$title = $can_edit && $edit_post ? $edit_post->post_title : '';
$content = $can_edit && $edit_post ? $edit_post->post_content : '';
$category_ids = $can_edit && $edit_post && function_exists('wp_get_post_categories')
    ? wp_get_post_categories($post_id) : array();
$tag_names = $can_edit && $edit_post && function_exists('wp_get_post_tags')
    ? wp_get_post_tags($post_id, array('fields' => 'names')) : array();
$save_action = $is_edit ? 'zsr_update' : 'zsr_submit';
?>
<article class="article main-bg theme-box box-body radius8 main-shadow">
    <h2 class="title-theme"><?php echo $is_edit ? '编辑稿件' : '提交稿件'; ?></h2>
    <?php if (!zsr_get_option('zsr_enable_submit', true)) : ?>
        <p class="muted-2-color">前台投稿功能当前已关闭。</p>
    <?php elseif (!$can_edit) : ?>
        <p class="muted-2-color">稿件不存在或没有编辑权限。</p>
    <?php elseif (function_exists('wp_nonce_field')) : ?>
        <form class="zsr-form" method="post" ajax-submit>
            <div class="form-group">
                <label>标题</label>
                <input class="form-control" type="text" name="post_title" required maxlength="200" value="<?php echo esc_attr($title); ?>">
            </div>
            <div class="form-group">
                <label>分类</label>
                <?php wp_dropdown_categories(array('name' => 'category[]', 'hide_empty' => false, 'class' => 'form-control', 'selected' => !empty($category_ids[0]) ? $category_ids[0] : 0)); ?>
            </div>
            <div class="form-group">
                <label>正文</label>
                <?php if (function_exists('wp_editor')) : ?>
                    <?php wp_editor($content, 'zsr_post_content', array('textarea_name' => 'post_content', 'media_buttons' => true, 'textarea_rows' => 12)); ?>
                <?php else : ?>
                    <textarea class="form-control" name="post_content" rows="12" required><?php echo esc_textarea($content); ?></textarea>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label>标签</label>
                <input class="form-control" type="text" name="tags" value="<?php echo esc_attr(implode(', ', (array) $tag_names)); ?>">
            </div>
            <input type="hidden" name="posts_id" value="<?php echo esc_attr((string) $post_id); ?>">
            <?php wp_nonce_field('zsr_submit', '_wpnonce_submit', false, false); ?>
            <?php wp_nonce_field('zsr_update', '_wpnonce_update', false, false); ?>
            <?php wp_nonce_field('zsr_draft', '_wpnonce_draft', false, false); ?>
            <?php wp_nonce_field('posts_save', '_wpnonce_posts_save', false, false); ?>
            <?php wp_nonce_field('posts_draft', '_wpnonce_posts_draft', false, false); ?>
            <input type="hidden" name="_wpnonce" value="">
            <div class="but-average">
                <?php if (!$edit_post || $edit_post->post_status === 'draft') : ?>
                    <button class="but wp-ajax-submit" type="button" form-action="zsr_draft">保存草稿</button>
                <?php endif; ?>
                <button class="but wp-ajax-submit" type="button" form-action="<?php echo esc_attr($save_action); ?>">提交审核</button>
            </div>
        </form>
    <?php else : ?>
        <p class="muted-2-color">主题投稿入口暂不可用。</p>
    <?php endif; ?>
</article>
