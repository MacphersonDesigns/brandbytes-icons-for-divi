<?php
namespace CustomIconsForDivi;

if (!defined('ABSPATH') && !defined('CIFD_TESTING')) {
    exit;
}

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

/**
 * Wires WordPress's native update UI to GitHub Releases so sites can get
 * update notices before this plugin is on the WordPress.org directory.
 */
class Updater {

    const REPOSITORY_URL = 'https://github.com/MacphersonDesigns/brandbytes-icons-for-divi';
    const PLUGIN_SLUG = 'custom-icons-for-divi';

    public function init() {
        if (!class_exists(PucFactory::class)) {
            return;
        }

        $update_checker = PucFactory::buildUpdateChecker(
            self::REPOSITORY_URL,
            CIFD_FILE,
            self::PLUGIN_SLUG
        );

        $vcs_api = $update_checker->getVcsApi();
        if ($vcs_api !== null && method_exists($vcs_api, 'enableReleaseAssets')) {
            // Fetch the built release .zip attached to the GitHub Release
            // rather than GitHub's raw tag source archive, which would
            // include node_modules, src, tests, and other dev-only files.
            $vcs_api->enableReleaseAssets();
        }
    }
}
