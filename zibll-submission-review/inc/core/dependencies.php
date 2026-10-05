<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Return the theme functions that are required by the first implementation stage.
 *
 * @return array<string, string>
 */
function zsr_required_theme_functions()
{
    return array(
        '_pz'                    => '读取 Zibll 设置',
        '_spz'                   => '写入 Zibll 设置',
        'zib_current_user_can'   => 'Zibll 权限判定',
        'zib_get_template_page_url' => 'Zibll 页面 URL/模板入口',
    );
}

/**
 * Detect whether the active theme is Zibll.
 *
 * @return bool
 */
function zsr_is_zibll_theme()
{
    if (!function_exists('wp_get_theme')) {
        return false;
    }

    $theme = wp_get_theme();
    $template = strtolower((string) $theme->get_template());
    $stylesheet = strtolower((string) $theme->get_stylesheet());
    $name = strtolower((string) $theme->get('Name'));

    return $template === 'zibll'
        || $stylesheet === 'zibll'
        || strpos($name, 'zibll') !== false
        || strpos($name, '子比') !== false;
}

/**
 * Build a dependency report without changing site state.
 *
 * @return array{theme_ok: bool, missing: array<string, string>, optional_missing: array<string, string>}
 */
function zsr_dependency_report()
{
    $missing = array();
    $optional_missing = array();

    if (!zsr_is_zibll_theme()) {
        $missing['theme'] = '当前启用主题不是 Zibll 子比主题。';
    }

    foreach (zsr_required_theme_functions() as $function => $label) {
        if (!function_exists($function)) {
            $missing[$function] = $label;
        }
    }

    if (!class_exists('CSF')) {
        $optional_missing['CSF'] = 'Codestar Framework 设置类尚未可用。';
    }

    return array(
        'theme_ok'         => zsr_is_zibll_theme(),
        'missing'          => $missing,
        'optional_missing' => $optional_missing,
    );
}

/**
 * Return true only when the runtime dependencies required by the core are ready.
 *
 * @return bool
 */
function zsr_dependencies_ready()
{
    $report = zsr_dependency_report();
    return $report['theme_ok'] && empty($report['missing']);
}
