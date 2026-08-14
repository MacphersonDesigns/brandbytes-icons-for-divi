<?php
namespace CustomIconsForDivi\Frontend;

use CustomIconsForDivi\Icon_Registry;

if (!defined('ABSPATH')) {
    exit;
}

class Icon_Renderer {

    private $registry;

    public function __construct(Icon_Registry $registry) {
        $this->registry = $registry;
    }

    public function init() {
        add_filter('render_block_divi/icon', array($this, 'render_icon_module'), 13, 2);
        add_filter('render_block_divi/blurb', array($this, 'render_blurb'), 13, 2);
        add_filter('render_block_divi/toggle', array($this, 'render_toggle'), 13, 2);
        add_filter('render_block_divi/icon-list-item', array($this, 'render_icon_list_item'), 13, 2);
        add_filter('render_block', array($this, 'render_button'), 13, 2);

        add_action('wp_footer', array($this, 'output_swap_script'), 99);
        add_action('wp_head', array($this, 'output_swap_css'), 99);
    }

    public function render_icon_module($block_content) {
        return $this->inject_data_icon($block_content, '/<span([^>]*class="[^"]*\bet_pb_icon_wrap\b[^"]*"[^>]*)>(.*?)<\/span>/is');
    }

    public function render_blurb($block_content) {
        return $this->inject_data_icon($block_content, '/<span([^>]*class="[^"]*\bet-pb-icon\b[^"]*"[^>]*)>(.*?)<\/span>/is');
    }

    public function render_toggle($block_content) {
        if (!is_string($block_content)) {
            return $block_content;
        }
        $entity = $this->find_custom_entity_in_content($block_content);
        if (!$entity) {
            return $block_content;
        }
        return preg_replace_callback(
            '/class="([^"]*\bet_pb_toggle_title\b[^"]*)"/',
            function ($matches) use ($entity) {
                return 'class="' . $matches[1] . '" data-icon="' . esc_attr($entity) . '"';
            },
            $block_content,
            1
        );
    }

    public function render_icon_list_item($block_content) {
        if (!is_string($block_content)) {
            return $block_content;
        }
        $entity = $this->find_custom_entity_in_content($block_content);
        if (!$entity || strpos($block_content, 'et-pb-icon') !== false) {
            return $block_content;
        }
        return preg_replace(
            '/<li([^>]*class="[^"]*\bet_pb_icon_list_item\b[^"]*"[^>]*)>/',
            '<li$1><span class="et-pb-icon" data-icon="' . esc_attr($entity) . '">' . $entity . '</span>',
            $block_content,
            1
        );
    }

    public function render_button($block_content, $parsed_block) {
        if (!is_string($block_content) || strpos($block_content, 'et_pb_button') === false) {
            return $block_content;
        }

        // render_button is registered on the global, unscoped `render_block`
        // filter (unlike the other render_* methods, which are scoped to a
        // single block name), so it also fires for ancestor container blocks
        // (row/column/section) *after* all their children have rendered. For
        // those, $block_content is the concatenated HTML of every descendant
        // block, not just one button. Skip any block that has innerBlocks —
        // i.e. any container — so this only ever runs against the actual leaf
        // block that produced the button markup, not a container holding
        // multiple buttons where only some have a custom icon assigned. As a
        // second layer of defense, entities are also assigned positionally
        // (document order) below, so even if a single leaf block's content
        // ever contained more than one button, each icon is correlated with
        // the button it actually appeared alongside rather than every button
        // grabbing the registry's first match.
        if (!empty($parsed_block['innerBlocks'])) {
            return $block_content;
        }

        $entities = $this->find_all_custom_entities_in_order($block_content);
        if (empty($entities)) {
            return $block_content;
        }

        $index = 0;
        return preg_replace_callback(
            '/<a([^>]*class="[^"]*\bet_pb_button\b[^"]*"[^>]*)>/i',
            function ($matches) use ($entities, &$index) {
                $tag = '<a' . $matches[1] . '>';
                if (strpos($tag, 'data-icon=') !== false) {
                    return $tag;
                }
                if (!isset($entities[$index])) {
                    return $tag;
                }
                $entity = $entities[$index];
                $index++;
                return rtrim($tag, '>') . ' data-icon="' . esc_attr($entity) . '">';
            },
            $block_content
        );
    }

    private function inject_data_icon($block_content, $span_pattern) {
        if (!is_string($block_content)) {
            return $block_content;
        }
        $entity = $this->find_custom_entity_in_content($block_content);
        if (!$entity) {
            return $block_content;
        }
        return preg_replace_callback(
            $span_pattern,
            function ($matches) use ($entity) {
                $tag_open = '<span' . $matches[1] . '>';
                if (strpos($tag_open, 'data-icon=') === false) {
                    $tag_open = rtrim($tag_open, '>') . ' data-icon="' . esc_attr($entity) . '">';
                }
                return $tag_open . $matches[2] . '</span>';
            },
            $block_content,
            1
        );
    }

    private function find_custom_entity_in_content($content) {
        foreach ($this->registry->all() as $icon) {
            $entity = $this->registry->codepoint_entity_for($icon['id']);
            $decoded = html_entity_decode($entity, ENT_QUOTES, 'UTF-8');
            if (strpos($content, $entity) !== false || strpos($content, $decoded) !== false) {
                return $entity;
            }
        }
        return null;
    }

    private function find_all_custom_entities_in_order($content) {
        $known = array();
        foreach ($this->registry->all() as $icon) {
            $entity = $this->registry->codepoint_entity_for($icon['id']);
            $decoded = html_entity_decode($entity, ENT_QUOTES, 'UTF-8');
            $known[$entity] = $entity;
            $known[$decoded] = $entity;
        }
        if (empty($known)) {
            return array();
        }

        $pattern = '/' . implode('|', array_map(function ($k) {
            return preg_quote($k, '/');
        }, array_keys($known))) . '/';

        if (!preg_match_all($pattern, $content, $matches)) {
            return array();
        }

        return array_map(function ($match) use ($known) {
            return $known[$match];
        }, $matches[0]);
    }

    public function output_swap_css() {
        if (empty($this->registry->all())) {
            return;
        }
        ?>
        <style>
            [data-icon].cifd-swapped { color: transparent !important; }
            [data-icon] img.cifd-icon-img { height: 1em; width: auto; display: inline-block; vertical-align: middle; }
        </style>
        <?php
    }

    public function output_swap_script() {
        $map = array();
        foreach ($this->registry->all() as $icon) {
            $entity = $this->registry->codepoint_entity_for($icon['id']);
            $map[html_entity_decode($entity, ENT_QUOTES, 'UTF-8')] = $icon['url'];
        }
        if (empty($map)) {
            return;
        }
        ?>
        <script>
        (function () {
            var iconsByGlyph = <?php echo wp_json_encode($map); ?>;

            function swap(root) {
                root.querySelectorAll('[data-icon]').forEach(function (el) {
                    var url = iconsByGlyph[el.getAttribute('data-icon')];
                    if (!url) {
                        return;
                    }
                    var img = el.querySelector('img.cifd-icon-img');
                    if (!img) {
                        img = document.createElement('img');
                        img.className = 'cifd-icon-img';
                        img.alt = '';
                        el.appendChild(img);
                    }
                    img.src = url;
                    el.classList.add('cifd-swapped');
                });
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function () { swap(document); });
            } else {
                swap(document);
            }
        })();
        </script>
        <?php
    }
}
