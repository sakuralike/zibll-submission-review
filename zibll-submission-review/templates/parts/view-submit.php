<?php

if (!defined('ABSPATH')) {
    exit;
}
?>
<article class="article main-bg theme-box box-body radius8 main-shadow">
    <h2 class="title-theme">提交稿件</h2>
    <?php if (!zsr_get_option('zsr_enable_submit', true)) : ?>
        <p class="muted-2-color">前台投稿功能当前已关闭。</p>
    <?php elseif (function_exists('zib_get_template_page_url')) : ?>
        <p><a class="but" href="<?php echo esc_url(zib_get_template_page_url('pages/newposts.php')); ?>">打开主题投稿页</a></p>
    <?php else : ?>
        <p class="muted-2-color">主题投稿入口暂不可用。</p>
    <?php endif; ?>
</article>
