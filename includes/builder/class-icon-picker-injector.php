<?php
namespace CustomIconsForDivi\Builder;

use CustomIconsForDivi\Icon_Registry;

if (!defined('ABSPATH')) {
    exit;
}

class Icon_Picker_Injector {

    private $registry;

    public function __construct(Icon_Registry $registry) {
        $this->registry = $registry;
    }

    public function init() {
        add_action('divi_visual_builder_assets_before_enqueue_scripts', array($this, 'enqueue_in_builder_iframe'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_in_top_level_ui'));
    }

    public function enqueue_in_builder_iframe() {
        // No hard script dependency on Divi/WP hooks handles here: icon-picker.js
        // polls for window.vendor.wp.hooks / window.wp.hooks itself with a retry
        // loop, and a missing or renamed dependency handle would silently break
        // this enqueue entirely (WP_Dependencies drops the whole chain when any
        // listed dependency isn't registered).
        $this->enqueue('cifd-icon-picker-vb', array());
    }

    public function enqueue_in_top_level_ui() {
        $this->enqueue('cifd-icon-picker-ui', array());
    }

    private function enqueue($handle, $extra_deps) {
        if (!$this->is_divi5_builder_active()) {
            return;
        }

        $icons = $this->icons_for_js();
        if (empty($icons)) {
            return;
        }

        wp_register_script($handle, CIFD_URL . 'includes/builder/assets/icon-picker.js', array_merge(array('jquery'), $extra_deps), CIFD_VERSION, true);
        wp_localize_script($handle, 'cifdBuilderIcons', $icons);
        wp_enqueue_script($handle);
    }

    private function icons_for_js() {
        $icons = array();
        foreach ($this->registry->all() as $icon) {
            $entity = $this->registry->codepoint_entity_for($icon['id']);
            $icons[] = array(
                'id'             => $icon['id'],
                'name'           => $icon['name'],
                'url'            => $icon['url'],
                'unicode'        => $entity,
                'decodedUnicode' => html_entity_decode($entity, ENT_QUOTES, 'UTF-8'),
                'searchTerms'    => 'custom icon ' . $icon['name'],
                'styles'         => array('divi', 'solid'),
                'fontWeight'     => 400,
            );
        }
        return $icons;
    }

    private function is_divi5_builder_active() {
        if (!function_exists('et_builder_d5_enabled') || !et_builder_d5_enabled()) {
            return false;
        }
        if (!function_exists('et_core_is_fb_enabled') || !et_core_is_fb_enabled()) {
            return false;
        }
        return true;
    }
}
