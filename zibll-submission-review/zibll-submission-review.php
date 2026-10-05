<?php
/**
 * Plugin Name: 子比前台投稿审核插件
 * Description: 为 Zibll 子比主题提供前台投稿审核基础能力。
 * Version: 0.1.0
 * Requires at least: 5.0
 * Requires PHP: 7.0
 * Author: Zibll Submission Review
 * Text Domain: zib-sub-review
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

define('ZSR_VERSION', '0.1.0');
define('ZSR_DB_VERSION', 1);
define('ZSR_FILE', __FILE__);
define('ZSR_DIR', plugin_dir_path(__FILE__));
define('ZSR_URL', plugin_dir_url(__FILE__));
define('ZSR_OPTION', 'zsr_options');

require_once ZSR_DIR . 'inc/core/logger.php';
require_once ZSR_DIR . 'inc/core/dependencies.php';
require_once ZSR_DIR . 'inc/core/options.php';
require_once ZSR_DIR . 'inc/widget/sync.php';
require_once ZSR_DIR . 'inc/widget/enumerator.php';
require_once ZSR_DIR . 'inc/widget/locker.php';
require_once ZSR_DIR . 'inc/core/capabilities.php';
require_once ZSR_DIR . 'inc/domain/state-machine.php';
require_once ZSR_DIR . 'inc/admin/options.php';
require_once ZSR_DIR . 'inc/frontend/query.php';
require_once ZSR_DIR . 'inc/frontend/page-router.php';
require_once ZSR_DIR . 'inc/ajax/submit.php';
require_once ZSR_DIR . 'inc/domain/audit-log.php';
require_once ZSR_DIR . 'inc/domain/notification-service.php';
require_once ZSR_DIR . 'inc/frontend/review-query.php';
require_once ZSR_DIR . 'inc/frontend/history-query.php';
require_once ZSR_DIR . 'inc/ajax/review.php';
require_once ZSR_DIR . 'inc/core/bootstrap.php';

register_activation_hook(ZSR_FILE, 'zsr_activate');
register_deactivation_hook(ZSR_FILE, 'zsr_deactivate');

add_action('plugins_loaded', 'zsr_bootstrap', 20);
