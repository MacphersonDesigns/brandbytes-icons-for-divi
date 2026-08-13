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

    public function __construct($icons_option, $next_id_option, $codepoint_base, $get_option = 'get_option', $update_option = 'update_option') {
        $this->icons_option = $icons_option;
        $this->next_id_option = $next_id_option;
        $this->codepoint_base = $codepoint_base;
        $this->get_option = $get_option;
        $this->update_option = $update_option;
    }

    public function all() {
        $icons = call_user_func($this->get_option, $this->icons_option, array());
        return is_array($icons) ? $icons : array();
    }

    public function find($id) {
        foreach ($this->all() as $icon) {
            if ((int) $icon['id'] === (int) $id) {
                return $icon;
            }
        }
        return null;
    }

    public function add($name, $url, $mime) {
        $icons = $this->all();
        $id = $this->next_id();

        $icons[] = array(
            'id'   => $id,
            'name' => $name,
            'url'  => $url,
            'mime' => $mime,
        );

        call_user_func($this->update_option, $this->icons_option, $icons);
        call_user_func($this->update_option, $this->next_id_option, $id + 1);

        return $id;
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
}
