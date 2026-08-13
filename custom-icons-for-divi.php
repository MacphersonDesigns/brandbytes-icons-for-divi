<?php
/**
 * Plugin Name: Custom Icons for Divi
 * Description: Upload custom icons and use them in Divi 5's Icon module, Blurb, Button, Toggle, and Icon List.
 * Version: 0.1.0
 * Requires PHP: 7.4
 * Author: MacPherson Designs
 * License: GPL-2.0-or-later
 * Text Domain: custom-icons-for-divi
 */

namespace CustomIconsForDivi;

if (!defined('ABSPATH')) {
    exit;
}

define('CIFD_VERSION', '0.1.0');
define('CIFD_FILE', __FILE__);
define('CIFD_DIR', plugin_dir_path(__FILE__));
define('CIFD_URL', plugin_dir_url(__FILE__));
define('CIFD_OPTION_ICONS', 'cifd_icons');
define('CIFD_OPTION_NEXT_ID', 'cifd_next_id');
define('CIFD_CODEPOINT_BASE', 0xE000);

require_once CIFD_DIR . 'includes/class-icon-registry.php';
require_once CIFD_DIR . 'includes/class-icon-sanitizer.php';
require_once CIFD_DIR . 'includes/admin/class-icon-library-page.php';
require_once CIFD_DIR . 'includes/builder/class-icon-picker-injector.php';
require_once CIFD_DIR . 'includes/frontend/class-icon-renderer.php';

function bootstrap() {
    $registry = new Icon_Registry(CIFD_OPTION_ICONS, CIFD_OPTION_NEXT_ID, CIFD_CODEPOINT_BASE);

    if (is_admin()) {
        (new Admin\Icon_Library_Page($registry, new Icon_Sanitizer()))->init();
    }

    (new Builder\Icon_Picker_Injector($registry))->init();
    (new Frontend\Icon_Renderer($registry))->init();
}
add_action('plugins_loaded', __NAMESPACE__ . '\\bootstrap');
