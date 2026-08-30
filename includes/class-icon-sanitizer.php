<?php
namespace CustomIconsForDivi;

if (!defined('ABSPATH') && !defined('CIFD_TESTING')) {
    exit;
}

class Icon_Sanitizer {

    /** One icon should remain small enough for predictable admin processing. */
    const MAX_FILE_BYTES = 1048576;

    /** Prevent valid XML from becoming an excessive DOM tree. */
    const MAX_ELEMENT_COUNT = 5000;

    /** Bound recursion before clean_node() walks the accepted tree. */
    const MAX_NESTING_DEPTH = 64;

    /** Path and transform attributes can otherwise hide huge parsing workloads. */
    const MAX_ATTRIBUTE_BYTES = 100000;

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

        // Check raw bytes before XML parsing allocates a DOM tree. This is the
        // first and cheapest protection against oversized or accidental files.
        if (strlen($svg_markup) > self::MAX_FILE_BYTES) {
            return $this->error('SVG file exceeds the 1 MB safety limit.');
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

        $resource_error = $this->validate_resource_limits($root);
        if ($resource_error !== null) {
            return $this->error($resource_error);
        }

        $this->clean_node($root);

        return $doc->saveXML($root);
    }

    /**
     * Validate tree complexity iteratively before the recursive sanitizer walk.
     *
     * The stack stores each element with its depth, avoiding recursion while we
     * decide whether recursion is safe. Attribute limits cover very large path
     * and transform strings even when the overall file stays below 1 MB.
     */
    private function validate_resource_limits(\DOMElement $root) {
        $stack = array(array($root, 1));
        $element_count = 0;

        while (!empty($stack)) {
            list($element, $depth) = array_pop($stack);
            $element_count++;

            if ($element_count > self::MAX_ELEMENT_COUNT) {
                return 'SVG contains too many elements.';
            }
            if ($depth > self::MAX_NESTING_DEPTH) {
                return 'SVG nesting is too deep.';
            }

            foreach ($element->attributes as $attribute) {
                if (strlen($attribute->value) > self::MAX_ATTRIBUTE_BYTES) {
                    return 'SVG contains an attribute that is too large.';
                }
            }

            foreach ($element->childNodes as $child) {
                if ($child->nodeType === XML_ELEMENT_NODE) {
                    $stack[] = array($child, $depth + 1);
                }
            }
        }

        return null;
    }

    private function clean_node(\DOMElement $node) {
        // SVG/XML element and attribute names are case-sensitive by spec, and
        // self::$allowed_tags / self::$allowed_attrs intentionally preserve
        // the SVG-spec-correct mixed case (e.g. "clipPath", "viewBox"). Do not
        // lowercase before comparing against those allowlists.
        $tag = $node->tagName;

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
            // Keep the exact case for allowlist comparisons (SVG attribute
            // names like "viewBox" are case-sensitive); a separate lowercase
            // copy is used only for the case-insensitive event-handler check.
            $attr_name = $attr->nodeName;
            $attr_name_lower = strtolower($attr_name);
            $attr_value = $attr->value;

            $is_event_handler = strpos($attr_name_lower, 'on') === 0;

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
