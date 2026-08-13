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
