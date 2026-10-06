<?php

if (!defined('ABSPATH')) {
    exit;
}

function zsr_review_details_escape($value)
{
    if (function_exists('esc_html')) {
        return esc_html((string) $value);
    }
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function zsr_review_details_attr($value)
{
    if (function_exists('esc_attr')) {
        return esc_attr((string) $value);
    }
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function zsr_review_details_label($value)
{
    return zsr_review_details_escape(__((string) $value, 'zib-sub-review'));
}

function zsr_review_details_url($value)
{
    $value = trim((string) $value);
    if (!preg_match('/^https?:\/\//i', $value)) {
        return zsr_review_details_escape($value);
    }

    $safe_url = function_exists('esc_url') ? esc_url($value) : zsr_review_details_escape($value);
    if (!$safe_url) {
        return zsr_review_details_escape($value);
    }

    return '<a href="' . $safe_url . '" target="_blank" rel="noopener noreferrer">' . zsr_review_details_escape($value) . '</a>';
}

function zsr_review_details_is_url($value)
{
    return is_string($value) && (bool) preg_match('/^https?:\/\//i', trim($value));
}

function zsr_review_details_scalar($value)
{
    if (is_object($value) || is_resource($value)) {
        return zsr_review_details_label('无法显示此字段类型');
    }
    if (is_bool($value)) {
        return zsr_review_details_label($value ? '是' : '否');
    }
    if ($value === null) {
        return zsr_review_details_label('空');
    }
    if (is_string($value) && trim($value) === '') {
        return zsr_review_details_label('空');
    }
    if (zsr_review_details_is_url($value)) {
        return zsr_review_details_url($value);
    }
    return zsr_review_details_escape($value);
}

function zsr_review_details_array($value, $depth = 0)
{
    if ($depth > 12) {
        return zsr_review_details_label('字段嵌套过深，请在后台查看');
    }
    if (!is_array($value) || !$value) {
        return zsr_review_details_label('空');
    }

    $items = array();
    foreach ($value as $key => $item) {
        $key_label = zsr_review_details_escape(is_string($key) ? $key : (string) $key);
        $item_html = is_array($item) ? zsr_review_details_array($item, $depth + 1) : zsr_review_details_scalar($item);
        $items[]   = '<div class="zsr-detail-value-item"><span class="zsr-detail-value-key">' . $key_label . '</span><span class="zsr-detail-value-value">' . $item_html . '</span></div>';
    }
    return '<div class="zsr-detail-value-list">' . implode('', $items) . '</div>';
}

function zsr_review_details_value($value)
{
    return is_array($value) ? zsr_review_details_array($value) : zsr_review_details_scalar($value);
}

function zsr_review_details_dl($rows)
{
    $html = '<dl class="zsr-detail-fields">';
    foreach ((array) $rows as $row) {
        if (!is_array($row) || (!array_key_exists('label', $row) && !array_key_exists('label_html', $row))) {
            continue;
        }
        $html .= '<div class="zsr-detail-field"><dt>' . (isset($row['label_html']) ? $row['label_html'] : zsr_review_details_label($row['label'])) . '</dt><dd>' . (isset($row['value_html']) ? $row['value_html'] : zsr_review_details_value($row['value'] ?? '')) . '</dd></div>';
    }
    return $html . '</dl>';
}

function zsr_review_details_group($title, $body, $open = false, $class = '')
{
    if ($body === '') {
        return '';
    }
    $class_attr = 'zsr-detail-group' . ($class ? ' ' . zsr_review_details_attr($class) : '');
    return '<details class="' . $class_attr . '"' . ($open ? ' open' : '') . '><summary>' . zsr_review_details_label($title) . '</summary><div class="zsr-detail-group-body">' . $body . '</div></details>';
}

function zsr_review_details_missing($field = array())
{
    $id = isset($field['id']) ? (string) $field['id'] : '';
    if ($id === 'pay_cuont') {
        return zsr_review_details_label('未设置');
    }
    if (array_key_exists('default', $field) && $field['default'] !== '' && $field['default'] !== array()) {
        return zsr_review_details_label('未设置') . ' <span class="zsr-detail-default">(' . zsr_review_details_label('默认') . ': ' . zsr_review_details_value($field['default']) . ')</span>';
    }
    return zsr_review_details_label('未设置');
}

function zsr_review_details_option_label($field, $value)
{
    if (is_object($value) || is_resource($value)) {
        return zsr_review_details_scalar($value);
    }
    $options = isset($field['options']) ? $field['options'] : array();
    if (is_string($options) && function_exists($options)) {
        $options = call_user_func($options);
    }
    if (!is_array($options)) {
        return zsr_review_details_value($value);
    }
    if (is_array($value)) {
        $mapped = array();
        foreach ($value as $item) {
            $mapped[] = zsr_review_details_option_label($field, $item);
        }
        return implode(', ', $mapped);
    }
    $key = (string) $value;
    foreach ($options as $option_key => $option_label) {
        if ((string) $option_key === $key) {
            return zsr_review_details_escape(is_scalar($option_label) ? $option_label : $option_key);
        }
    }
    return zsr_review_details_value($value);
}

function zsr_review_details_field_label($field)
{
    $title    = isset($field['title']) ? trim((string) $field['title']) : '';
    $subtitle = isset($field['subtitle']) ? trim((string) $field['subtitle']) : '';
    $label    = $title !== '' ? $title : ($subtitle !== '' ? $subtitle : (isset($field['label']) ? (string) $field['label'] : (string) ($field['id'] ?? '')));
    $label = trim(strip_tags($label));
    return $label !== '' ? zsr_review_details_escape($label) : zsr_review_details_label('未命名字段');
}

function zsr_review_details_schema_children($fields, $value)
{
    $rows = array();
    foreach ((array) $fields as $field) {
        if (!is_array($field) || empty($field['id']) || in_array($field['type'] ?? '', array('content', 'submessage', 'heading', 'notice'), true)) {
            continue;
        }
        $id     = (string) $field['id'];
        $exists = is_array($value) && array_key_exists($id, $value);
        $child  = $exists ? $value[$id] : null;
        $rows[] = array('label_html' => zsr_review_details_field_label($field), 'value_html' => $exists ? zsr_review_details_schema_value($field, $child) : zsr_review_details_missing($field));
    }
    return zsr_review_details_dl($rows);
}

function zsr_review_details_schema_value($field, $value)
{
    $type = isset($field['type']) ? (string) $field['type'] : '';
    if ($type === 'group') {
        if (!is_array($value) || !$value) {
            return zsr_review_details_label('空');
        }
        $html  = array();
        $index = 1;
        foreach (array_values($value) as $item) {
            if (!is_array($item)) {
                $html[] = '<div class="zsr-detail-group-item">' . zsr_review_details_value($item) . '</div>';
            } else {
                $html[] = zsr_review_details_group(zsr_review_details_field_label($field) . ' #' . $index, zsr_review_details_schema_children($field['fields'] ?? array(), $item), false, 'zsr-detail-nested');
            }
            $index++;
        }
        return implode('', $html);
    }
    if ($type === 'fieldset') {
        return zsr_review_details_schema_children($field['fields'] ?? array(), is_array($value) ? $value : array());
    }
    if ($type === 'accordion') {
        $html = array();
        foreach ((array) ($field['accordions'] ?? array()) as $accordion) {
            if (!is_array($accordion)) {
                continue;
            }
            $html[] = zsr_review_details_group($accordion['title'] ?? zsr_review_details_label('折叠项'), zsr_review_details_schema_children($accordion['fields'] ?? array(), is_array($value) ? $value : array()), false, 'zsr-detail-nested');
        }
        return implode('', $html);
    }
    if ($type === 'link' && is_array($value)) {
        $rows = array();
        foreach (array('url' => '链接地址', 'text' => '链接文字', 'target' => '打开方式') as $key => $label) {
            if (array_key_exists($key, $value)) {
                $rows[] = array('label' => $label, 'value' => $value[$key]);
            }
        }
        return zsr_review_details_dl($rows);
    }
    if ($type === 'gallery') {
        return zsr_review_details_gallery($value);
    }
    if (in_array($type, array('radio', 'select', 'button_set', 'image_select'), true)) {
        return zsr_review_details_option_label($field, $value);
    }
    if ($type === 'switcher' || ($type === 'checkbox' && empty($field['options']))) {
        return zsr_review_details_label(in_array($value, array(true, 1, '1', 'true', 'on'), true) ? '开启' : '关闭');
    }
    if ($type === 'checkbox' && !empty($field['options'])) {
        return zsr_review_details_option_label($field, $value);
    }
    return zsr_review_details_value($value);
}

function zsr_review_details_schema_field_rows($fields, $data)
{
    $rows = array();
    $ids  = array();
    foreach ((array) $fields as $field) {
        if (!is_array($field) || empty($field['id']) || in_array($field['type'] ?? '', array('content', 'submessage', 'heading', 'notice'), true)) {
            continue;
        }
        $id     = (string) $field['id'];
        $ids[$id] = true;
        $exists = is_array($data) && array_key_exists($id, $data);
        $rows[] = array(
            'label_html' => zsr_review_details_field_label($field),
            'value_html' => $exists ? zsr_review_details_schema_value($field, $data[$id]) : zsr_review_details_missing($field),
        );
    }
    return array($rows, $ids);
}



function zsr_review_details_pay_schema()
{
    if (!function_exists('zibpay_post_mate_csf_fields')) {
        return array();
    }
    $old_get = $_GET;
    try {
        unset($_GET['post']);
        $fields   = zibpay_post_mate_csf_fields();
        $sections = array(array('fields' => $fields));
        if (function_exists('apply_filters')) {
            $filtered = apply_filters('csf_posts_zibpay_sections', $sections, null);
            if (is_array($filtered)) {
                $sections = $filtered;
            }
        }
    } finally {
        $_GET = $old_get;
    }

    $result = array();
    $seen   = array();
    foreach ((array) $sections as $section) {
        $section_fields = is_array($section) && array_key_exists('fields', $section) ? $section['fields'] : array($section);
        foreach ((array) $section_fields as $field) {
            if (!is_array($field) || empty($field['id']) || isset($seen[$field['id']])) {
                continue;
            }
            $seen[$field['id']] = true;
            $result[]           = $field;
        }
    }
    return $result;
}



function zsr_review_details_theme_meta($post_id, $key)
{
    $aggregate_keys = array('follow_pay_args', 'score_data', 'vote_option', 'pay_hide_part', 'vote_data', 'add_posts_pending_msg', 'add_posts_publish_msg', 'score_detail', 'description', 'keywords', 'title', 'xzh_tui_back', 'layout_bg', 'layout_max_width', 'page_content_style', 'page_header_style', 'article_maxheight_xz', 'no_article-navs', 'show_layout', 'subtitle', 'featured_video_title', 'featured_video_episode', 'featured_slide', 'featured_video', 'cover_image', 'thumbnail_url');
    if (function_exists('zib_get_option_meta_keys')) {
        $aggregate_keys = array_merge($aggregate_keys, (array) zib_get_option_meta_keys('post_meta'));
    }
    $aggregate = in_array($key, $aggregate_keys, true);
    if ($aggregate) {
        $container = function_exists('get_post_meta') ? get_post_meta($post_id, 'zib_other_data', true) : array();
        if (is_array($container) && array_key_exists($key, $container)) {
            $value = function_exists('zib_get_post_meta') ? zib_get_post_meta($post_id, $key, true) : $container[$key];
            return array(true, $value);
        }
        return array(false, '');
    }
    $exists = function_exists('metadata_exists') ? metadata_exists('post', $post_id, $key) : false;
    $value  = function_exists('get_post_meta') ? get_post_meta($post_id, $key, true) : '';
    if (!$exists && $value !== '' && $value !== null) {
        $exists = true;
    }
    return array((bool) $exists, $value);
}

function zsr_review_details_theme_row($post_id, $id, $label, $formatter = null)
{
    list($exists, $value) = zsr_review_details_theme_meta($post_id, $id);
    if (!$exists) {
        return array('label' => $label, 'value_html' => zsr_review_details_label('未设置'));
    }
    $value_html = is_callable($formatter) ? call_user_func($formatter, $value) : zsr_review_details_value($value);
    return array('label' => $label, 'value_html' => $value_html);
}

function zsr_review_details_theme_fields($post_id)
{
    $rows = array();
    $rows[] = zsr_review_details_theme_row($post_id, 'thumbnail_url', '外链特色图像');
    $rows[] = zsr_review_details_theme_row($post_id, 'cover_image', '封面图');
    $rows[] = zsr_review_details_theme_row($post_id, 'featured_slide', '特色幻灯片', 'zsr_review_details_gallery');
    $rows[] = zsr_review_details_theme_row($post_id, 'featured_video', '特色视频');
    $rows[] = zsr_review_details_theme_row($post_id, 'featured_video_title', '特色视频标题');
    $rows[] = zsr_review_details_theme_row($post_id, 'featured_video_episode', '特色视频剧集', function ($value) {
        if (!is_array($value) || !$value) {
            return zsr_review_details_label('空');
        }
        $items = array();
        foreach (array_values($value) as $index => $item) {
            $items[] = zsr_review_details_group('第' . ($index + 1) . '项', zsr_review_details_dl(array(
                array('label' => '剧集标题', 'value' => $item['title'] ?? ''),
                array('label' => '视频地址', 'value_html' => zsr_review_details_value($item['url'] ?? '')),
            )), false, 'zsr-detail-nested');
        }
        return implode('', $items);
    });
    $rows[] = zsr_review_details_theme_row($post_id, 'subtitle', '副标题');
    $rows[] = zsr_review_details_theme_row($post_id, 'views', '阅读量');
    $rows[] = zsr_review_details_theme_row($post_id, 'like', '点赞数');
    $rows[] = zsr_review_details_theme_row($post_id, 'show_layout', '显示布局', function ($value) {
        $map = array('false' => '跟随主题', '' => '跟随主题', 'no_sidebar' => '无侧边栏', 'sidebar_left' => '侧边栏靠左', 'sidebar_right' => '侧边栏靠右');
        return zsr_review_details_label($map[(string) $value] ?? (string) $value);
    });
    $rows[] = zsr_review_details_theme_row($post_id, 'no_article-navs', '不显示目录树');
    $rows[] = zsr_review_details_theme_row($post_id, 'article_maxheight_xz', '限制内容最大高度');
    return $rows;
}

function zsr_review_details_gallery($value)
{
    if (!is_array($value) && !is_scalar($value)) {
        return zsr_review_details_scalar($value);
    }
    $items = array();
    $ids = is_array($value) ? $value : preg_split('/\s*,\s*/', (string) $value, -1, PREG_SPLIT_NO_EMPTY);
    foreach ($ids as $id) {
        if (!is_scalar($id) || !ctype_digit((string) $id) || (int) $id < 1) {
            continue;
        }
        $url = wp_get_attachment_url((int) $id);
        $items[] = '#' . (int) $id . ($url ? ' ' . zsr_review_details_url($url) : '');
    }
    return $items ? implode('<br>', $items) : zsr_review_details_label('空');
}

function zsr_review_details_taxonomy_rows($post)
{
    $rows = array();
    if (!function_exists('get_object_taxonomies')) {
        return $rows;
    }
    $taxonomies = get_object_taxonomies($post->post_type, 'objects');
    if (!is_array($taxonomies)) {
        return $rows;
    }
    foreach ($taxonomies as $taxonomy => $object) {
        if (!is_object($object) || empty($object->show_ui)) {
            continue;
        }
        $terms = function_exists('get_the_terms') ? get_the_terms($post->ID, $taxonomy) : array();
        if (function_exists('is_wp_error') && is_wp_error($terms)) {
            $terms = array();
        }
        $values = array();
        foreach ((array) $terms as $term) {
            if (!is_object($term)) {
                continue;
            }
            $name = isset($term->name) ? (string) $term->name : '';
            $slug = isset($term->slug) ? ' (' . (string) $term->slug . ')' : '';
            $values[] = $name . $slug;
        }
        $rows[] = array('label' => isset($object->label) ? (string) $object->label : (string) $taxonomy, 'value_html' => $values ? implode(', ', array_map('zsr_review_details_escape', $values)) : zsr_review_details_label('未设置'));
    }
    return $rows;
}

function zsr_review_details_custom_filter_rows($post_id)
{
    $fields = array();
    if (class_exists('post_custom_filter') && method_exists('post_custom_filter', 'csf_fields')) {
        $fields = post_custom_filter::csf_fields();
    } elseif (function_exists('zib_get_custom_filter_args')) {
        foreach ((array) zib_get_custom_filter_args() as $filter) {
            $fields[] = array('id' => $filter['key'] ?? '', 'title' => $filter['name'] ?? '', 'options' => $filter['vals'] ?? array(), 'type' => 'select');
        }
    }
    $rows = array();
    foreach ((array) $fields as $field) {
        if (!is_array($field) || empty($field['id'])) {
            continue;
        }
        $key    = (string) $field['id'];
        $exists = function_exists('metadata_exists') ? metadata_exists('post', $post_id, $key) : false;
        $value  = function_exists('get_post_meta') ? get_post_meta($post_id, $key, true) : '';
        if (!$exists && $value !== '' && $value !== null) {
            $exists = true;
        }
        $rows[] = array('label_html' => zsr_review_details_field_label($field), 'value_html' => $exists ? zsr_review_details_option_label($field, $value) : zsr_review_details_label('未设置'));
    }
    return $rows;
}

function zsr_review_details_core_section($post)
{
    $status_map = array('pending' => '待审核', 'future' => '待发布', 'publish' => '已发布', 'draft' => '草稿', 'private' => '私密', 'trash' => '回收站');
    $format_map = array('standard' => '标准', 'image' => '图像', 'gallery' => '画廊', 'video' => '视频');
    $author     = function_exists('get_userdata') ? get_userdata((int) ($post->post_author ?? 0)) : false;
    $author_txt = $author && isset($author->display_name) ? $author->display_name . ' (#' . (int) ($post->post_author ?? 0) . ')' : '#' . (int) ($post->post_author ?? 0);
    $rows       = array(
        array('label' => '文章 ID', 'value' => (int) ($post->ID ?? 0)),
        array('label' => '文章类型', 'value' => $post->post_type ?? ''),
        array('label' => '状态', 'value_html' => zsr_review_details_label($status_map[(string) ($post->post_status ?? '')] ?? (string) ($post->post_status ?? ''))),
        array('label' => '作者', 'value' => $author_txt),
        array('label' => '创建时间', 'value' => $post->post_date ?? ''),
        array('label' => '修改时间', 'value' => $post->post_modified ?? ''),
        array('label' => '计划时间', 'value' => ($post->post_status ?? '') === 'future' ? ($post->post_date ?? '') : '未设置'),
        array('label' => '文章别名', 'value' => $post->post_name ?? ''),
        array('label' => '文章链接', 'value_html' => function_exists('get_permalink') ? zsr_review_details_url(get_permalink($post->ID)) : zsr_review_details_label('不可用')),
        array('label' => '文章密码', 'value_html' => !empty($post->post_password) ? zsr_review_details_label('已设置') : zsr_review_details_label('未设置')),
        array('label' => '置顶', 'value_html' => function_exists('is_sticky') ? zsr_review_details_scalar(is_sticky($post->ID)) : zsr_review_details_label('未设置')),
        array('label' => '文章格式', 'value_html' => zsr_review_details_label($format_map[(string) (function_exists('get_post_format') ? (get_post_format($post->ID) ?: 'standard') : 'standard')] ?? '标准')),
        array('label' => '评论', 'value_html' => zsr_review_details_label(($post->comment_status ?? '') === 'open' ? '开启' : '关闭')),
        array('label' => '评论数量', 'value' => (int) ($post->comment_count ?? 0)),
        array('label' => '引用', 'value_html' => zsr_review_details_label(($post->ping_status ?? '') === 'open' ? '开启' : '关闭')),
        array('label' => '父文章', 'value' => (int) ($post->post_parent ?? 0)),
        array('label' => '菜单顺序', 'value' => (int) ($post->menu_order ?? 0)),
        array('label' => '发送引用', 'value' => $post->to_ping ?? ''),
        array('label' => '已发送引用', 'value' => $post->pinged ?? ''),
    );
    if (!empty($post->post_mime_type)) {
        $rows[] = array('label' => '媒体类型', 'value' => $post->post_mime_type);
    }
    $content_rows = array(
        array('label' => '标题', 'value' => $post->post_title ?? ''),
        array('label' => '正文', 'value' => $post->post_content ?? ''),
        array('label' => '摘要', 'value' => $post->post_excerpt ?? ''),
    );
    return zsr_review_details_group('文章基本信息', zsr_review_details_dl($rows) . zsr_review_details_group('标题与正文', zsr_review_details_dl($content_rows), false, 'zsr-detail-nested'), true);
}

function zsr_review_details_html($post)
{
    if (!is_object($post) || empty($post->ID)) {
        return '<p class="muted-2-color">' . zsr_review_details_label('文章信息不可用') . '</p>';
    }
    $post_id = (int) $post->ID;
    $html    = array(zsr_review_details_core_section($post));

    $taxonomy_rows = zsr_review_details_taxonomy_rows($post);
    $html[]        = zsr_review_details_group('分类、标签与专题', zsr_review_details_dl($taxonomy_rows), false);

    $media_rows = zsr_review_details_theme_fields($post_id);
    $thumb_id   = function_exists('get_post_thumbnail_id') ? (int) get_post_thumbnail_id($post_id) : 0;
    $thumb_url  = function_exists('get_the_post_thumbnail_url') ? get_the_post_thumbnail_url($post_id, 'full') : '';
    $media_rows[] = array('label' => '特色图像附件 ID', 'value' => $thumb_id ?: 0);
    $media_rows[] = array('label' => '特色图像地址', 'value_html' => $thumb_url ? zsr_review_details_url($thumb_url) : zsr_review_details_label('未设置'));
    $html[]       = zsr_review_details_group('媒体与文章扩展', zsr_review_details_dl($media_rows), false);

    $seo_rows = array();
    foreach (array('title' => 'SEO 标题', 'keywords' => 'SEO 关键词', 'description' => 'SEO 描述') as $id => $label) {
        $seo_rows[] = zsr_review_details_theme_row($post_id, $id, $label);
    }
    $html[] = zsr_review_details_group('SEO', zsr_review_details_dl($seo_rows), false);

    list($push_exists, $push_info) = zsr_review_details_theme_meta($post_id, 'xzh_tui_back');
    if ($push_exists && is_array($push_info)) {
        $push_rows = array();
        foreach (array('normal_push' => '普通收录', 'daily_push' => '快速收录', 'update_time' => '更新时间') as $key => $label) {
            if (array_key_exists($key, $push_info)) {
                $push_rows[] = array('label' => $label, 'value' => $push_info[$key]);
            }
        }
        $html[] = zsr_review_details_group('百度资源提交', zsr_review_details_dl($push_rows));
    }

    $custom_rows = zsr_review_details_custom_filter_rows($post_id);
    $html[]      = zsr_review_details_group('高级筛选', $custom_rows ? zsr_review_details_dl($custom_rows) : '<p class="muted-2-color">' . zsr_review_details_label('当前主题未提供高级筛选字段。') . '</p>', false);

    $pay_data = function_exists('get_post_meta') ? get_post_meta($post_id, 'posts_zibpay', true) : array();
    $pay_data = is_array($pay_data) ? $pay_data : array();
    if (isset($pay_data['pay_download'])) {
        if (!is_array($pay_data['pay_download']) && !empty($pay_data['pay_download']) && function_exists('zibpay_get_post_down_array')) {
            $pay_data['pay_download'] = zibpay_get_post_down_array($pay_data);
        }
    }
    $pay_fields = zsr_review_details_pay_schema();
    if ($pay_fields) {
        list($pay_rows, $pay_ids) = zsr_review_details_schema_field_rows($pay_fields, $pay_data);
        $pay_body = zsr_review_details_dl($pay_rows);
    } else {
        $pay_body = '<p class="muted-2-color">' . zsr_review_details_label('当前主题的付费字段定义不可用，请联系管理员检查主题加载。') . '</p>';
    }
    $html[] = zsr_review_details_group('付费功能', $pay_body, false);

    return implode('', array_filter($html));
}

function zsr_ajax_review_details()
{
    nocache_headers();
    $user_id = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
    if ($user_id < 1 || (function_exists('is_user_logged_in') && !is_user_logged_in())) {
        if (function_exists('wp_send_json_error')) {
            wp_send_json_error(array('msg' => zsr_review_details_label('请先登录')), 403);
        }
        return;
    }
    if (!isset($_REQUEST['_wpnonce']) || !is_string($_REQUEST['_wpnonce'])
        || !check_ajax_referer('zsr_review_details', '_wpnonce', false)) {
        if (function_exists('wp_send_json_error')) {
            wp_send_json_error(array('msg' => zsr_review_details_label('安全校验失败，请刷新页面后重试')), 403);
        }
        return;
    }

    $post_id = isset($_POST['post_id']) ? $_POST['post_id'] : null;
    if ((!is_int($post_id) && !is_string($post_id)) || !preg_match('/^[1-9][0-9]*$/D', (string) $post_id)
        || (string) (int) $post_id !== (string) $post_id) {
        if (function_exists('wp_send_json_error')) {
            wp_send_json_error(array('msg' => zsr_review_details_label('请求参数格式无效')), 400);
        }
        return;
    }
    if (!function_exists('zsr_get_review_post')) {
        if (function_exists('wp_send_json_error')) {
            wp_send_json_error(array('msg' => zsr_review_details_label('审核接口不可用')), 403);
        }
        return;
    }
    $post = zsr_get_review_post((int) $post_id, $user_id, true);
    if (!$post) {
        if (function_exists('wp_send_json_error')) {
            wp_send_json_error(array('msg' => zsr_review_details_label('稿件不存在、已处理或您没有查看权限')), 403);
        }
        return;
    }
    if (function_exists('nocache_headers')) {
        nocache_headers();
    }
    if (function_exists('wp_send_json_success')) {
        wp_send_json_success(array('html' => zsr_review_details_html($post)));
    }
}
