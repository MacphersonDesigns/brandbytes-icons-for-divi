<?php
namespace CustomIconsForDivi\Divi5;

use CustomIconsForDivi\Icon_Catalog;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Connects the plugin to Divi 5 without loading Divi classes too early.
 *
 * The module is intentionally feature-gated during its foundation slice. The
 * server and builder contracts can be tested now, while normal editors cannot
 * insert a module that does not yet have its visual library picker.
 */
class Custom_Icon_Module_Bootstrap {

    private $catalog;

    public function __construct(Icon_Catalog $catalog) {
        $this->catalog = $catalog;
    }

    public function init() {
        if (!$this->is_enabled()) {
            return;
        }

        add_action('divi_module_library_modules_dependency_tree', array($this, 'register_dependency'));
        add_action('divi_visual_builder_assets_before_enqueue_scripts', array($this, 'register_builder_assets'));
        add_action('divi_visual_builder_assets_after_enqueue_app_window_scripts', array($this, 'add_builder_catalog_data'));
        add_action('divi_visual_builder_assets_after_enqueue_top_window_scripts', array($this, 'add_builder_catalog_data'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_styles'));
    }

    public function register_dependency($dependency_tree) {
        require_once CIFD_DIR . 'includes/divi5/class-custom-icon-asset-resolver.php';
        require_once CIFD_DIR . 'includes/divi5/class-custom-icon-module.php';

        $resolver = new Custom_Icon_Asset_Resolver($this->catalog);
        $dependency_tree->add_dependency(new Custom_Icon_Module($resolver));
    }

    public function register_builder_assets() {
        if (!function_exists('et_builder_d5_enabled') || !et_builder_d5_enabled()) {
            return;
        }

        $script_path = CIFD_DIR . 'assets/divi5/bundle.js';
        if (!file_exists($script_path)) {
            return;
        }

        \ET\Builder\VisualBuilder\Assets\PackageBuildManager::register_package_build(array(
            'name'    => 'cifd-custom-icon-module-builder-script',
            'version' => CIFD_VERSION,
            'script'  => array(
                'src'                => CIFD_URL . 'assets/divi5/bundle.js',
                'deps'               => array(
                    'divi-module-library',
                    'divi-field-library',
                    'divi-vendor-wp-hooks',
                ),
                // Divi can render the module field and canvas in separate
                // windows. The same supported module bundle is available in
                // both without registering any native-field compatibility hook.
                'enqueue_top_window' => true,
                'enqueue_app_window' => true,
            ),
        ));

        $style_path = CIFD_DIR . 'assets/divi5/vb-bundle.css';
        if (file_exists($style_path)) {
            \ET\Builder\VisualBuilder\Assets\PackageBuildManager::register_package_build(array(
                'name'    => 'cifd-custom-icon-module-builder-style',
                'version' => CIFD_VERSION,
                'style'   => array(
                    'src'                => CIFD_URL . 'assets/divi5/vb-bundle.css',
                    'deps'               => array(),
                    // The picker and canvas may live in separate Divi windows,
                    // so both receive the module's Builder-only presentation.
                    'enqueue_top_window' => true,
                    'enqueue_app_window' => true,
                ),
            ));
        }
    }

    /**
     * Give both Builder windows the same small, read-only icon catalog before
     * our bundle executes. The app window owns the picker; the top window owns
     * the live canvas hook. The saved value remains a UUID, while URLs are
     * preview data resolved from the current attachment on every builder load.
     *
     * This is intentionally not a public REST endpoint yet. The catalog is
     * emitted only on an authenticated Visual Builder request, which keeps the
     * first picker slice small while preserving a clean future API boundary.
     */
    public function add_builder_catalog_data() {
        $handle = 'cifd-custom-icon-module-builder-script';
        if (!wp_script_is($handle, 'enqueued')) {
            return;
        }

        require_once CIFD_DIR . 'includes/divi5/class-custom-icon-asset-resolver.php';
        $resolver = new Custom_Icon_Asset_Resolver($this->catalog);
        $catalog = array();

        foreach ($this->catalog->all() as $record) {
            if (empty($record['uuid']) || !is_string($record['uuid'])) {
                continue;
            }

            $resolved = $resolver->resolve($record['uuid']);
            if (!$resolved) {
                continue;
            }

            $catalog[$record['uuid']] = array(
                'uuid'    => $record['uuid'],
                'name'    => sanitize_text_field($record['name'] ?? __('Untitled icon', 'custom-icons-for-divi')),
                'url'     => esc_url_raw($resolved['url']),
                'mime'    => sanitize_mime_type($record['mime'] ?? ''),
                // Named libraries arrive in the next storage migration. A
                // truthful default lets the picker expose its filter contract
                // now without inventing client-specific grouping data.
                'libraryId' => sanitize_key($record['library_id'] ?? 'custom-icons'),
                'library'   => sanitize_text_field($record['library'] ?? __('Custom Icons', 'custom-icons-for-divi')),
                'category'  => sanitize_text_field($record['category'] ?? ''),
                'keywords'  => array_values(array_map('sanitize_text_field', is_array($record['keywords'] ?? null) ? $record['keywords'] : array())),
                'source'    => in_array($record['source'] ?? '', array('bundled', 'uploaded'), true)
                    ? $record['source']
                    : 'uploaded',
            );
        }

        $settings = array(
            'catalog'    => $catalog,
            'manageUrl'  => current_user_can('manage_options')
                ? admin_url('upload.php?page=custom-icons-for-divi')
                : '',
            'catalogRev' => md5((string) wp_json_encode($catalog)),
        );

        $script = 'window.cifdCustomIconSettings = ' . wp_json_encode(
            $settings,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        ) . ';';

        wp_add_inline_script($handle, $script, 'before');
    }

    public function enqueue_frontend_styles() {
        $style_path = CIFD_DIR . 'assets/divi5/bundle.css';
        if (!file_exists($style_path)) {
            return;
        }

        wp_enqueue_style(
            'cifd-custom-icon-module',
            CIFD_URL . 'assets/divi5/bundle.css',
            array(),
            CIFD_VERSION
        );
    }

    private function is_enabled() {
        $enabled = (bool) get_option(CIFD_OPTION_EXPERIMENTAL_CUSTOM_ICON_MODULE, false);

        /**
         * Allows an integration test or local development environment to turn
         * on the unfinished module without changing production content.
         */
        return (bool) apply_filters('cifd_enable_experimental_custom_icon_module', $enabled);
    }
}
