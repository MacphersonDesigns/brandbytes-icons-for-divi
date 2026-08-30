<?php
namespace CustomIconsForDivi;

if (!defined('ABSPATH') && !defined('CIFD_TESTING')) {
    exit;
}

/**
 * Reads one immutable icon library shipped with the plugin.
 *
 * Bundled icons intentionally do not become Media Library attachments. That
 * keeps activation idempotent, avoids duplicate uploads after reinstall, and
 * lets plugin updates add icons without rewriting site content. The manifest
 * owns stable UUIDs; modules save only those UUIDs.
 */
class Bundled_Icon_Library {

    private $manifest_path;
    private $base_url;
    private $records;

    public function __construct($manifest_path, $base_url) {
        $this->manifest_path = (string) $manifest_path;
        $this->base_url = rtrim((string) $base_url, '/') . '/';
    }

    /**
     * Return normalized, read-only records using the same shape as uploads.
     */
    public function all() {
        if (is_array($this->records)) {
            return $this->records;
        }

        $this->records = array();
        if (!is_readable($this->manifest_path)) {
            return $this->records;
        }

        $decoded = json_decode((string) file_get_contents($this->manifest_path), true);
        if (!is_array($decoded) || 1 !== (int) ($decoded['schemaVersion'] ?? 0)) {
            return $this->records;
        }

        $library_id = $this->safe_slug($decoded['id'] ?? '');
        $library_name = $this->safe_text($decoded['name'] ?? '');
        $library_version = $this->safe_text($decoded['version'] ?? '');
        $license = $this->safe_text($decoded['license'] ?? '');
        if ('' === $library_id || '' === $library_name || !is_array($decoded['icons'] ?? null)) {
            return $this->records;
        }

        foreach ($decoded['icons'] as $icon) {
            $record = $this->normalize_icon($icon, $library_id, $library_name, $library_version, $license);
            if ($record) {
                $this->records[] = $record;
            }
        }

        return $this->records;
    }

    public function find_by_uuid($uuid) {
        if (!is_string($uuid) || '' === $uuid) {
            return null;
        }

        foreach ($this->all() as $icon) {
            if (hash_equals($icon['uuid'], $uuid)) {
                return $icon;
            }
        }

        return null;
    }

    private function normalize_icon($icon, $library_id, $library_name, $library_version, $license) {
        if (!is_array($icon)) {
            return null;
        }

        $uuid = $this->safe_uuid($icon['uuid'] ?? '');
        $slug = $this->safe_slug($icon['slug'] ?? '');
        $name = $this->safe_text($icon['name'] ?? '');
        $category = $this->safe_text($icon['category'] ?? '');
        $file = isset($icon['file']) && is_string($icon['file']) ? $icon['file'] : '';

        // The URL is assembled only from a conservative relative path. This
        // prevents a future malformed manifest from becoming a URL injection
        // path in either the Builder or the published mask style.
        if ('' === $uuid || '' === $slug || '' === $name || !$this->is_safe_relative_svg($file)) {
            return null;
        }

        $keywords = array();
        foreach (is_array($icon['keywords'] ?? null) ? $icon['keywords'] : array() as $keyword) {
            $normalized = $this->safe_text($keyword);
            if ('' !== $normalized) {
                $keywords[] = $normalized;
            }
        }

        return array(
            'id'              => 'bundled:' . $library_id . ':' . $slug,
            'uuid'            => $uuid,
            'attachment_id'   => 0,
            'name'            => $name,
            'slug'            => $slug,
            'url'             => $this->base_url . $file,
            'mime'            => 'image/svg+xml',
            'source'          => 'bundled',
            'library_id'      => $library_id,
            'library'         => $library_name,
            'library_version' => $library_version,
            'license'         => $license,
            'category'        => $category,
            'keywords'        => array_values(array_unique($keywords)),
            'locked'          => true,
        );
    }

    private function is_safe_relative_svg($path) {
        return is_string($path)
            && (bool) preg_match('#^icons/[a-z0-9][a-z0-9-]*\.svg$#', $path)
            && false === strpos($path, '..');
    }

    private function safe_uuid($value) {
        $value = is_string($value) ? strtolower($value) : '';
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $value)
            ? $value
            : '';
    }

    private function safe_slug($value) {
        $value = is_string($value) ? strtolower(trim($value)) : '';
        return (bool) preg_match('/^[a-z0-9][a-z0-9-]*$/', $value) ? $value : '';
    }

    private function safe_text($value) {
        if (!is_scalar($value)) {
            return '';
        }
        return trim(strip_tags((string) $value));
    }
}
