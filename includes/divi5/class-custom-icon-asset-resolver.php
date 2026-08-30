<?php
namespace CustomIconsForDivi\Divi5;

if (!defined('ABSPATH') && !defined('CIFD_TESTING')) {
    exit;
}

/**
 * Resolves the stable value saved by a Divi module to the current icon asset.
 *
 * Divi content stores an icon UUID. It never stores the current domain, CDN,
 * uploads path, or array position. Keeping this translation outside the module
 * renderer makes it independently testable and reusable by future Blurb,
 * Button, Toggle, and Icon List adapters.
 */
class Custom_Icon_Asset_Resolver {

    private $catalog;
    private $attachment_url_resolver;

    public function __construct($catalog, $attachment_url_resolver = 'wp_get_attachment_url') {
        $this->catalog = $catalog;
        $this->attachment_url_resolver = $attachment_url_resolver;
    }

    public function resolve($uuid) {
        $icon = $this->catalog->find_by_uuid($uuid);
        if (!$icon) {
            return null;
        }

        $url = '';
        $attachment_id = isset($icon['attachment_id']) ? (int) $icon['attachment_id'] : 0;
        if ($attachment_id > 0 && is_callable($this->attachment_url_resolver)) {
            $resolved_url = call_user_func($this->attachment_url_resolver, $attachment_id);
            $url = is_string($resolved_url) ? $resolved_url : '';
        }

        // A URL fallback keeps a legacy 0.1 record renderable when WordPress
        // cannot relate it back to an attachment. New records always prefer
        // attachment identity so normal domain/CDN migrations remain safe.
        if ($url === '' && isset($icon['url']) && is_string($icon['url'])) {
            $url = $icon['url'];
        }

        if ($url === '') {
            return null;
        }

        return array(
            'uuid'          => $icon['uuid'],
            'name'          => isset($icon['name']) ? (string) $icon['name'] : '',
            'mime'          => isset($icon['mime']) ? (string) $icon['mime'] : '',
            'attachment_id' => $attachment_id,
            'url'           => $url,
            'source'        => isset($icon['source']) ? (string) $icon['source'] : 'uploaded',
            'library_id'    => isset($icon['library_id']) ? (string) $icon['library_id'] : 'custom-icons',
            'library'       => isset($icon['library']) ? (string) $icon['library'] : 'Custom Icons',
        );
    }
}
