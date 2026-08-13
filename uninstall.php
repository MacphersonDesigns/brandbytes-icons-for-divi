<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('cifd_icons');
delete_option('cifd_next_id');
