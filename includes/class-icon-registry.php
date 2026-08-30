<?php
namespace CustomIconsForDivi;

if (!defined('ABSPATH') && !defined('CIFD_TESTING')) {
    exit;
}

class Icon_Registry {

    private $icons_option;
    private $next_id_option;
    private $codepoint_base;
    private $get_option;
    private $update_option;
    private $generate_uuid;

    public function __construct($icons_option, $next_id_option, $codepoint_base, $get_option = 'get_option', $update_option = 'update_option', $generate_uuid = null) {
        $this->icons_option = $icons_option;
        $this->next_id_option = $next_id_option;
        $this->codepoint_base = $codepoint_base;
        $this->get_option = $get_option;
        $this->update_option = $update_option;
        $this->generate_uuid = $generate_uuid;
    }

    public function all() {
        $icons = call_user_func($this->get_option, $this->icons_option, array());
        if (!is_array($icons)) {
            return array();
        }

        // A damaged option should not make every Divi render emit notices.
        // Invalid rows are ignored here and can later be surfaced by an admin
        // health check without weakening the read path used on public pages.
        return array_values(array_filter($icons, 'is_array'));
    }

    public function find($id) {
        foreach ($this->all() as $icon) {
            if (isset($icon['id']) && (int) $icon['id'] === (int) $id) {
                return $icon;
            }
        }
        return null;
    }

    public function find_by_uuid($uuid) {
        if (!is_string($uuid) || $uuid === '') {
            return null;
        }

        foreach ($this->all() as $icon) {
            if (isset($icon['uuid']) && hash_equals((string) $icon['uuid'], $uuid)) {
                return $icon;
            }
        }

        return null;
    }

    public function add($name, $url, $mime, $attachment_id = 0) {
        $icons = $this->all();
        $id = $this->next_id();

        $icons[] = array(
            'id'            => $id,
            'uuid'          => $this->new_unique_uuid($icons),
            'attachment_id' => (int) $attachment_id,
            'name'          => $name,
            'url'           => $url,
            'mime'          => $mime,
        );

        call_user_func($this->update_option, $this->icons_option, $icons);
        call_user_func($this->update_option, $this->next_id_option, $id + 1);

        return $id;
    }

    /**
     * Backfill stable metadata on records created by the 0.1 prototype.
     *
     * Why this lives beside the repository: both admin and Divi rendering use
     * the same normalized record contract. Keeping the migration here avoids
     * having two subtly different interpretations of an icon record.
     *
     * @param callable|null $url_to_attachment_id Resolves a legacy URL to a
     *                                             WordPress attachment id.
     * @return bool True when records were already current or saved correctly.
     */
    public function ensure_metadata($url_to_attachment_id = null) {
        $icons = $this->all();
        $changed = false;
        $seen_uuids = array();

        foreach ($icons as &$icon) {
            $uuid = isset($icon['uuid']) && is_string($icon['uuid']) ? $icon['uuid'] : '';
            if (!$this->is_uuid($uuid) || isset($seen_uuids[$uuid])) {
                $uuid = $this->new_unique_uuid($icons, $seen_uuids);
                $icon['uuid'] = $uuid;
                $changed = true;
            }
            $seen_uuids[$uuid] = true;

            if (!isset($icon['attachment_id'])) {
                $attachment_id = 0;
                if (is_callable($url_to_attachment_id) && !empty($icon['url'])) {
                    $attachment_id = (int) call_user_func($url_to_attachment_id, $icon['url']);
                }
                $icon['attachment_id'] = $attachment_id;
                $changed = true;
            } else {
                $normalized_attachment_id = (int) $icon['attachment_id'];
                if ($icon['attachment_id'] !== $normalized_attachment_id) {
                    $icon['attachment_id'] = $normalized_attachment_id;
                    $changed = true;
                }
            }
        }
        unset($icon);

        if (!$changed) {
            return true;
        }

        return (bool) call_user_func($this->update_option, $this->icons_option, $icons);
    }

    public function rename($id, $name) {
        $icons = $this->all();
        $found = false;

        foreach ($icons as &$icon) {
            if ((int) $icon['id'] === (int) $id) {
                $icon['name'] = $name;
                $found = true;
                break;
            }
        }
        unset($icon);

        if (!$found) {
            return false;
        }

        call_user_func($this->update_option, $this->icons_option, $icons);
        return true;
    }

    public function delete($id) {
        $icons = $this->all();
        $filtered = array_values(array_filter($icons, function ($icon) use ($id) {
            return (int) $icon['id'] !== (int) $id;
        }));

        if (count($filtered) === count($icons)) {
            return false;
        }

        call_user_func($this->update_option, $this->icons_option, $filtered);
        return true;
    }

    public function reorder(array $ordered_ids) {
        $icons = $this->all();
        $by_id = array();
        foreach ($icons as $icon) {
            $by_id[(int) $icon['id']] = $icon;
        }

        $reordered = array();
        foreach ($ordered_ids as $id) {
            if (isset($by_id[(int) $id])) {
                $reordered[] = $by_id[(int) $id];
                unset($by_id[(int) $id]);
            }
        }
        foreach ($by_id as $icon) {
            $reordered[] = $icon;
        }

        call_user_func($this->update_option, $this->icons_option, $reordered);
        return true;
    }

    public function codepoint_for($id) {
        return $this->codepoint_base + (int) $id;
    }

    public function codepoint_entity_for($id) {
        return '&#x' . dechex($this->codepoint_for($id)) . ';';
    }

    private function next_id() {
        $stored = call_user_func($this->get_option, $this->next_id_option, null);
        if (is_int($stored) || (is_string($stored) && ctype_digit($stored))) {
            return (int) $stored;
        }

        $max = 0;
        foreach ($this->all() as $icon) {
            $max = max($max, (int) $icon['id']);
        }
        return $max + 1;
    }

    private function new_unique_uuid(array $icons, array $reserved = array()) {
        $existing = $reserved;
        foreach ($icons as $icon) {
            if (is_array($icon) && isset($icon['uuid']) && is_string($icon['uuid'])) {
                $existing[$icon['uuid']] = true;
            }
        }

        // A UUID collision is extraordinarily unlikely, but identity must not
        // be ambiguous. Retry a bounded number of times and fail loudly rather
        // than silently assigning a duplicate reference.
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $uuid = $this->generate_uuid();
            if ($this->is_uuid($uuid) && !isset($existing[$uuid])) {
                return $uuid;
            }
        }

        throw new \RuntimeException('Custom Icons for Divi could not generate a unique icon UUID.');
    }

    private function generate_uuid() {
        if (is_callable($this->generate_uuid)) {
            return (string) call_user_func($this->generate_uuid);
        }

        if (function_exists('wp_generate_uuid4')) {
            return (string) wp_generate_uuid4();
        }

        throw new \RuntimeException('Custom Icons for Divi requires a UUID generator.');
    }

    private function is_uuid($value) {
        return is_string($value) && (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $value
        );
    }
}
