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
            '<li$1><span class="et-pb-icon" data-icon="' . esc_attr($entity) . '">' . esc_html($entity) . '</span>',
            $block_content,
            1
        );
    }

    public function render_button($block_content) {
        if (!is_string($block_content) || strpos($block_content, 'et_pb_button') === false) {
            return $block_content;
        }
        $entity = $this->find_custom_entity_in_content($block_content);
        if (!$entity) {
            return $block_content;
        }
        return preg_replace_callback(
            '/<a([^>]*class="[^"]*\bet_pb_button\b[^"]*"[^>]*)>/i',
            function ($matches) use ($entity) {
                $tag = '<a' . $matches[1] . '>';
                if (strpos($tag, 'data-icon=') !== false) {
                    return $tag;
                }
                return rtrim($tag, '>') . ' data-icon="' . esc_attr($entity) . '">';
            },
            $block_content,
            1
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
