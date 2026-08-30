<?php
/**
 * Plugin Name: BrandBytes Icons for Divi
 * Description: Manage reusable custom icon libraries for Divi 5.
 * Version: 0.4.8
 * Requires PHP: 7.4
 * Author: Code & Canvas
 * License: GPL-2.0-or-later
 * Text Domain: custom-icons-for-divi
 */

namespace CustomIconsForDivi;

if (!defined('ABSPATH')) {
    exit;
}

define('CIFD_VERSION', '0.4.8');
define('CIFD_FILE', __FILE__);
define('CIFD_DIR', plugin_dir_path(__FILE__));
define('CIFD_URL', plugin_dir_url(__FILE__));
define('CIFD_OPTION_ICONS', 'cifd_icons');
define('CIFD_OPTION_NEXT_ID', 'cifd_next_id');
define('CIFD_CODEPOINT_BASE', 0xE000);
define('CIFD_OPTION_EXPERIMENTAL_CUSTOM_ICON_MODULE', 'cifd_enable_experimental_custom_icon_module');

require_once CIFD_DIR . 'includes/class-icon-registry.php';
require_once CIFD_DIR . 'includes/class-bundled-icon-library.php';
require_once CIFD_DIR . 'includes/class-icon-catalog.php';
require_once CIFD_DIR . 'includes/class-icon-sanitizer.php';
require_once CIFD_DIR . 'includes/admin/class-icon-upload-policy.php';
require_once CIFD_DIR . 'includes/admin/class-icon-library-page.php';
require_once CIFD_DIR . 'includes/divi5/class-custom-icon-module-bootstrap.php';

function bootstrap() {
    $registry = new Icon_Registry(CIFD_OPTION_ICONS, CIFD_OPTION_NEXT_ID, CIFD_CODEPOINT_BASE);
    $essentials = new Bundled_Icon_Library(
        CIFD_DIR . 'assets/icon-libraries/essentials/manifest.json',
        CIFD_URL . 'assets/icon-libraries/essentials/'
    );
    $catalog = new Icon_Catalog($registry, array($essentials));

    // Older prototype records stored only an absolute URL. Add stable identity
    // and the WordPress attachment relationship before any module reads them.
    // The migration is deliberately idempotent: after the first successful
    // update, later requests perform no database write.
    $registry->ensure_metadata('attachment_url_to_postid');

    if (is_admin()) {
        $sanitizer = new Icon_Sanitizer();
        (new Admin\Icon_Upload_Policy($sanitizer))->init();
        (new Admin\Icon_Library_Page($registry, $sanitizer))->init();
    }

    (new Divi5\Custom_Icon_Module_Bootstrap($catalog))->init();
}
add_action('plugins_loaded', __NAMESPACE__ . '\\bootstrap');
