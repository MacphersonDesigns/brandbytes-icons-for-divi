<?php
namespace CustomIconsForDivi;

if (!defined('ABSPATH') && !defined('CIFD_TESTING')) {
    exit;
}

class Icon_Sanitizer {

    private static $allowed_tags = array(
        'svg', 'g', 'path', 'circle', 'ellipse', 'line', 'polygon', 'polyline',
        'rect', 'text', 'tspan', 'defs', 'clipPath', 'linearGradient',
        'radialGradient', 'stop', 'title', 'desc', 'use', 'symbol', 'mask', 'a',
    );

    private static $allowed_attrs = array(
        'id', 'class', 'style', 'width', 'height', 'viewBox', 'xmlns',
        'x', 'y', 'x1', 'y1', 'x2', 'y2', 'cx', 'cy', 'r', 'rx', 'ry',
        'd', 'points', 'fill', 'fill-rule', 'fill-opacity', 'stroke',
        'stroke-width', 'stroke-linecap', 'stroke-linejoin', 'stroke-opacity',
        'stroke-dasharray', 'opacity', 'transform', 'offset', 'stop-color',
        'stop-opacity', 'gradientUnits', 'gradientTransform', 'preserveAspectRatio',
        'clip-path', 'mask', 'aria-hidden', 'role',
    );

    public function sanitize($svg_markup) {
        if (!is_string($svg_markup) || trim($svg_markup) === '') {
            return $this->error('empty SVG file.');
        }

        if (preg_match('/<!DOCTYPE|<!ENTITY/i', $svg_markup)) {
            return $this->error('SVG files with a DOCTYPE or ENTITY declaration are not allowed.');
        }

        if (PHP_VERSION_ID < 80000 && function_exists('libxml_disable_entity_loader')) {
            libxml_disable_entity_loader(true);
        }

        $previous_errors = libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        $doc->resolveExternals = false;
        $doc->substituteEntities = false;
        $loaded = $doc->loadXML($svg_markup, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous_errors);

        if (!$loaded) {
            return $this->error('file is not valid XML/SVG.');
        }

        $root = $doc->documentElement;
        if (!$root || strtolower($root->tagName) !== 'svg') {
            return $this->error('root element must be <svg>.');
        }

        $this->clean_node($root);

        return $doc->saveXML($root);
    }

    private function clean_node(\DOMElement $node) {
        $tag = strtolower($node->tagName);

        if (!in_array($tag, self::$allowed_tags, true)) {
            $node->parentNode->removeChild($node);
            return;
        }

        $to_remove = array();
        foreach ($node->attributes as $attr) {
            $attr_name = strtolower($attr->name);
            $attr_value = $attr->value;

            $is_event_handler = strpos($attr_name, 'on') === 0;
            $is_script_uri = (bool) preg_match('/^\s*javascript:/i', $attr_value);
            $is_allowed = in_array($attr_name, self::$allowed_attrs, true);

            if ($is_event_handler || $is_script_uri || !$is_allowed) {
                $to_remove[] = $attr->name;
            }
        }
        foreach ($to_remove as $name) {
            $node->removeAttribute($name);
        }

        $children = array();
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }
        foreach ($children as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                $this->clean_node($child);
            } elseif ($child->nodeType === XML_COMMENT_NODE || $child->nodeType === XML_PI_NODE) {
                $node->removeChild($child);
            }
        }
    }

    private function error($message) {
        if (class_exists('WP_Error')) {
            return new \WP_Error('cifd_invalid_svg', 'Custom Icons for Divi: ' . $message);
        }
        return new \Exception($message);
    }
}
