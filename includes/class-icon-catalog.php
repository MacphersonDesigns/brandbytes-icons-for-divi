<?php
namespace CustomIconsForDivi;

if (!defined('ABSPATH') && !defined('CIFD_TESTING')) {
    exit;
}

/**
 * Read-only catalog consumed by Divi Builder and server rendering.
 *
 * Uploaded records and bundled records remain separate storage providers. The
 * catalog is the narrow seam that lets the picker treat them uniformly without
 * teaching the editable registry how to rename or delete immutable assets.
 */
class Icon_Catalog {

    private $registry;
    private $bundled_libraries;

    public function __construct(Icon_Registry $registry, array $bundled_libraries = array()) {
        $this->registry = $registry;
        $this->bundled_libraries = $bundled_libraries;
    }

    public function all() {
        $by_uuid = array();

        // Bundled UUIDs are reserved by the plugin. If damaged uploaded data
        // collides, the reviewed bundled asset wins deterministically.
        foreach ($this->bundled_libraries as $library) {
            if (!is_object($library) || !method_exists($library, 'all')) {
                continue;
            }
            foreach ($library->all() as $icon) {
                if (is_array($icon) && !empty($icon['uuid'])) {
                    $by_uuid[(string) $icon['uuid']] = $icon;
                }
            }
        }

        foreach ($this->registry->all() as $icon) {
            if (!is_array($icon) || empty($icon['uuid']) || isset($by_uuid[(string) $icon['uuid']])) {
                continue;
            }
            $icon['source'] = 'uploaded';
            $icon['library_id'] = $icon['library_id'] ?? 'custom-icons';
            $icon['library'] = $icon['library'] ?? 'Custom Icons';
            $icon['keywords'] = is_array($icon['keywords'] ?? null) ? $icon['keywords'] : array();
            $icon['category'] = $icon['category'] ?? '';
            $icon['locked'] = false;
            $by_uuid[(string) $icon['uuid']] = $icon;
        }

        return array_values($by_uuid);
    }

    public function find_by_uuid($uuid) {
        if (!is_string($uuid) || '' === $uuid) {
            return null;
        }

        foreach ($this->all() as $icon) {
            if (hash_equals((string) $icon['uuid'], $uuid)) {
                return $icon;
            }
        }

        return null;
    }
}
