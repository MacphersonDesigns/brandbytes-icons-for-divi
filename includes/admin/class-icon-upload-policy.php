<?php
namespace CustomIconsForDivi\Admin;

use CustomIconsForDivi\Icon_Sanitizer;

if (!defined('ABSPATH') && !defined('CIFD_TESTING')) {
    exit;
}

/**
 * Owns the security boundary between a raw SVG upload and WordPress uploads.
 *
 * Why this is separate from Icon_Library_Page: WordPress writes Media Library
 * files before the later "Add Icon" AJAX request runs. Sanitizing in the upload
 * prefilter guarantees that only safe SVG markup can receive a public uploads
 * URL, even when the user closes the media modal without registering the icon.
 */
class Icon_Upload_Policy {

    const CAPABILITY = 'manage_options';

    private $sanitizer;
    private $capability_checker;

    public function __construct(Icon_Sanitizer $sanitizer, $capability_checker = 'current_user_can') {
        $this->sanitizer = $sanitizer;
        $this->capability_checker = $capability_checker;
    }

    public function init() {
        add_filter('upload_mimes', array($this, 'allow_svg_uploads'));
        add_filter('wp_handle_upload_prefilter', array($this, 'sanitize_svg_before_publish'));
    }

    /**
     * SVG remains restricted to the same trusted role that manages libraries.
     */
    public function allow_svg_uploads($mimes) {
        if ($this->can_manage_icons()) {
            $mimes['svg'] = 'image/svg+xml';
        }

        return $mimes;
    }

    /**
     * Sanitize the private PHP upload temporary file before WordPress moves it.
     *
     * Returning an `error` value is WordPress's supported prefilter rejection
     * contract. WordPress will not publish or create an attachment for the raw
     * file when any validation, sanitation, or atomic replacement step fails.
     */
    public function sanitize_svg_before_publish($file) {
        if (!$this->is_svg_upload($file)) {
            return $file;
        }

        if (!$this->can_manage_icons()) {
            $file['error'] = __('You are not allowed to upload SVG icons.', 'custom-icons-for-divi');
            return $file;
        }

        $temporary_path = isset($file['tmp_name']) && is_string($file['tmp_name'])
            ? $file['tmp_name']
            : '';

        if ($temporary_path === '' || !is_file($temporary_path) || !is_readable($temporary_path)) {
            $file['error'] = __('The SVG upload could not be read safely.', 'custom-icons-for-divi');
            return $file;
        }

        $raw_svg = file_get_contents($temporary_path);
        $safe_svg = $this->sanitizer->sanitize($raw_svg);
        if (!is_string($safe_svg)) {
            $file['error'] = __('That SVG could not be safely sanitized and was rejected.', 'custom-icons-for-divi');
            return $file;
        }

        // Write beside the PHP upload temp file, then rename over it. Keeping
        // both files on the same filesystem makes rename atomic: WordPress sees
        // either the original private temp file or the complete safe file, never
        // a partially written SVG. A failure rejects the upload before publish.
        $safe_temporary_path = tempnam(dirname($temporary_path), 'cifd-safe-svg-');
        if ($safe_temporary_path === false) {
            $file['error'] = __('A safe temporary SVG file could not be created.', 'custom-icons-for-divi');
            return $file;
        }

        $written = file_put_contents($safe_temporary_path, $safe_svg, LOCK_EX);
        if ($written !== strlen($safe_svg) || !rename($safe_temporary_path, $temporary_path)) {
            if (is_file($safe_temporary_path)) {
                unlink($safe_temporary_path);
            }
            $file['error'] = __('The sanitized SVG could not be prepared for publication.', 'custom-icons-for-divi');
            return $file;
        }

        // Do not trust the browser-provided MIME value. Successful XML/SVG
        // validation above is the authority for this now-sanitized temp file.
        $file['type'] = 'image/svg+xml';
        return $file;
    }

    private function is_svg_upload($file) {
        $name = isset($file['name']) && is_string($file['name']) ? $file['name'] : '';
        return strtolower((string) pathinfo($name, PATHINFO_EXTENSION)) === 'svg';
    }

    private function can_manage_icons() {
        return is_callable($this->capability_checker)
            && (bool) call_user_func($this->capability_checker, self::CAPABILITY);
    }
}
