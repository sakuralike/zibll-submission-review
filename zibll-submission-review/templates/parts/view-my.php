<?php

if (!defined('ABSPATH')) {
    exit;
}

$paged = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
$query = zsr_get_my_submissions(get_current_user_id(), $paged);
?>
<article class="article main-bg theme-box box-body radius8 main-shadow">
    <h2 class="title-theme">我的投稿</h2>
    <?php if (!$query || !$query->have_posts()) : ?>
        <p class="muted-2-color">暂无投稿。</p>
    <?php else : ?>
        <div class="posts-mini-lists">
            <?php while ($query->have_posts()) : $query->the_post(); ?>
                <?php
                $status = get_post_status();
                $labels = array(
                    'draft'   => '草稿',
                    'pending' => '待审核',
                    'publish' => '已发布',
                    'trash'   => '已删除',
                );
                $label = isset($labels[$status]) ? $labels[$status] : '未知状态';
                ?>
                <div class="posts-mini">
                    <div class="posts-mini-con flex xx flex1 jsb">
                        <div>
                            <a href="<?php echo esc_url(get_permalink()); ?>"><?php echo esc_html(get_the_title() ?: '无标题'); ?></a>
                            <div class="muted-2-color em09"><?php echo esc_html(get_the_modified_date()); ?></div>
                        </div>
                        <span class="badge"><?php echo esc_html($label); ?></span>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
        <?php wp_reset_postdata(); ?>
    <?php endif; ?>
</article>
