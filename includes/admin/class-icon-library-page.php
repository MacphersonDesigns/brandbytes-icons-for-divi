<?php
namespace CustomIconsForDivi\Admin;

use CustomIconsForDivi\Icon_Registry;
use CustomIconsForDivi\Icon_Sanitizer;

if (!defined('ABSPATH')) {
    exit;
}

class Icon_Library_Page {

    const CAPABILITY = 'manage_options';
    const NONCE_ACTION = 'cifd_icon_library';

    private $registry;
    private $sanitizer;

    public function __construct(Icon_Registry $registry, Icon_Sanitizer $sanitizer) {
        $this->registry = $registry;
        $this->sanitizer = $sanitizer;
    }

    public function init() {
        add_action('admin_menu', array($this, 'register_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_ajax_cifd_add_icon', array($this, 'ajax_add_icon'));
        add_action('wp_ajax_cifd_rename_icon', array($this, 'ajax_rename_icon'));
        add_action('wp_ajax_cifd_delete_icon', array($this, 'ajax_delete_icon'));
        add_action('wp_ajax_cifd_reorder_icons', array($this, 'ajax_reorder_icons'));
    }

    public function register_page() {
        add_menu_page(
            'Custom Icons for Divi',
            'Custom Icons',
            self::CAPABILITY,
            'cifd-icon-library',
            array($this, 'render_page'),
            'dashicons-images-alt2'
        );
    }

    public function enqueue_assets($hook) {
        if ($hook !== 'toplevel_page_cifd-icon-library') {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_style('cifd-icon-library', CIFD_URL . 'includes/admin/assets/icon-library.css', array(), CIFD_VERSION);
        wp_enqueue_script('cifd-icon-library', CIFD_URL . 'includes/admin/assets/icon-library.js', array('jquery', 'jquery-ui-sortable', 'wp-util'), CIFD_VERSION, true);
        wp_localize_script('cifd-icon-library', 'cifdIconLibrary', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce(self::NONCE_ACTION),
            'icons'   => array_values($this->registry->all()),
            'strings' => array(
                'addIcon'         => __('Add Icon', 'custom-icons-for-divi'),
                'deleteConfirm'   => __('Delete this icon? Pages already using it will simply stop showing it.', 'custom-icons-for-divi'),
                'namePlaceholder' => __('Icon name', 'custom-icons-for-divi'),
            ),
        ));
    }

    public function render_page() {
        if (!current_user_can(self::CAPABILITY)) {
            return;
        }
        ?>
        <div class="wrap cifd-icon-library">
            <h1><?php esc_html_e('Custom Icons for Divi', 'custom-icons-for-divi'); ?></h1>
            <p>
                <?php esc_html_e('Upload icons here to use them in the Divi Builder icon picker: Icon module, Blurb, Button, Toggle, and Icon List.', 'custom-icons-for-divi'); ?>
            </p>

            <button type="button" class="button button-primary" id="cifd-add-icon">
                <?php esc_html_e('Add Icon', 'custom-icons-for-divi'); ?>
            </button>

            <ul id="cifd-icon-grid" class="cifd-icon-grid"></ul>
            <p id="cifd-icon-grid-empty" class="cifd-icon-grid-empty" hidden>
                <?php esc_html_e('No custom icons yet. Click "Add Icon" to upload one.', 'custom-icons-for-divi'); ?>
            </p>
            <div id="cifd-icon-status" class="screen-reader-text" role="status" aria-live="polite"></div>
        </div>
        <?php
    }

    public function ajax_add_icon() {
        $this->verify_request();

        $attachment_id = isset($_POST['attachment_id']) ? absint($_POST['attachment_id']) : 0;
        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';

        if (!$attachment_id || $name === '') {
            wp_send_json_error(array('message' => __('An attachment and a name are required.', 'custom-icons-for-divi')), 400);
        }

        $mime = get_post_mime_type($attachment_id);
        $file = get_attached_file($attachment_id);
        $url = wp_get_attachment_url($attachment_id);

        if (!$file || !$url) {
            wp_send_json_error(array('message' => __('Could not find that media file.', 'custom-icons-for-divi')), 404);
        }

        $allowed_mimes = array('image/svg+xml', 'image/png', 'image/jpeg', 'image/webp');
        if (!in_array($mime, $allowed_mimes, true)) {
            wp_send_json_error(array('message' => __('Only SVG, PNG, JPG, and WebP files are allowed.', 'custom-icons-for-divi')), 400);
        }

        if ($mime === 'image/svg+xml') {
            $svg = file_get_contents($file);
            $sanitized = $this->sanitizer->sanitize($svg);
            if (!is_string($sanitized)) {
                wp_send_json_error(array('message' => __('That SVG could not be safely sanitized and was rejected.', 'custom-icons-for-divi')), 400);
            }
            $written = file_put_contents($file, $sanitized);
            if ($written === false) {
                // Do not proceed: on write failure, $file on disk still holds
                // the original, unsanitized SVG from before this overwrite
                // attempt. Registering the icon here would silently void the
                // sanitizer's guarantee and serve the raw upload instead.
                wp_send_json_error(array('message' => __('The sanitized SVG could not be saved to disk. Check file permissions and try again.', 'custom-icons-for-divi')), 500);
            }
        }

        $id = $this->registry->add($name, $url, $mime, $attachment_id);

        wp_send_json_success(array('icon' => $this->registry->find($id)));
    }

    public function ajax_rename_icon() {
        $this->verify_request();

        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';

        if (!$id || $name === '') {
            wp_send_json_error(array('message' => __('An id and a name are required.', 'custom-icons-for-divi')), 400);
        }

        if (!$this->registry->rename($id, $name)) {
            wp_send_json_error(array('message' => __('That icon could not be found.', 'custom-icons-for-divi')), 404);
        }

        wp_send_json_success();
    }

    public function ajax_delete_icon() {
        $this->verify_request();

        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        if (!$id || !$this->registry->delete($id)) {
            wp_send_json_error(array('message' => __('That icon could not be found.', 'custom-icons-for-divi')), 404);
        }

        wp_send_json_success();
    }

    public function ajax_reorder_icons() {
        $this->verify_request();

        $ids = isset($_POST['ids']) && is_array($_POST['ids']) ? array_map('absint', $_POST['ids']) : array();
        $this->registry->reorder($ids);

        wp_send_json_success();
    }

    private function verify_request() {
        if (!current_user_can(self::CAPABILITY) || !check_ajax_referer(self::NONCE_ACTION, 'nonce', false)) {
            wp_send_json_error(array('message' => __('You are not allowed to do that.', 'custom-icons-for-divi')), 403);
        }
    }
}
