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
        'id', 'class', 'width', 'height', 'viewBox', 'xmlns',
        'x', 'y', 'x1', 'y1', 'x2', 'y2', 'cx', 'cy', 'r', 'rx', 'ry',
        'd', 'points', 'fill', 'fill-rule', 'fill-opacity', 'stroke',
        'stroke-width', 'stroke-linecap', 'stroke-linejoin', 'stroke-opacity',
        'stroke-dasharray', 'opacity', 'transform', 'offset', 'stop-color',
        'stop-opacity', 'gradientUnits', 'gradientTransform', 'preserveAspectRatio',
        'clip-path', 'mask', 'aria-hidden', 'role',
    );

    private static $url_safe_attrs = array('fill', 'stroke', 'clip-path', 'mask');

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
            // Use the qualified name (e.g. "xlink:href"), not $attr->name which
            // returns only the local part ("href") for namespaced attributes.
            // Matching on the local name would let namespaced attributes bypass
            // both the allowlist check and removeAttribute() (which matches by
            // qualified name), silently leaving dangerous attributes in place.
            $attr_name = strtolower($attr->nodeName);
            $attr_value = $attr->value;

            $is_event_handler = strpos($attr_name, 'on') === 0;

            // Harden javascript: URI check by stripping control characters and whitespace
            $stripped_value = preg_replace('/[\x00-\x1F\s]+/', '', $attr_value);
            $is_script_uri = (bool) (stripos($stripped_value, 'javascript:') === 0);

            $is_allowed = in_array($attr_name, self::$allowed_attrs, true);

            // Additional check for url() attributes - restrict to same-document fragments
            $is_url_attr = in_array($attr_name, self::$url_safe_attrs, true);
            $has_url_ref = stripos($attr_value, 'url(') !== false;
            $is_url_safe = true;
            if ($is_url_attr && $has_url_ref) {
                $is_url_safe = (bool) preg_match('/^url\(#[^)]+\)$/i', trim($attr_value));
            }

            if ($is_event_handler || $is_script_uri || !$is_allowed || !$is_url_safe) {
                $to_remove[] = $attr;
            }
        }
        foreach ($to_remove as $attr) {
            // removeAttributeNode() matches by node identity (namespace-agnostic),
            // unlike removeAttribute() which matches by qualified name and is a
            // no-op when passed only the local part of a namespaced attribute.
            $node->removeAttributeNode($attr);
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
