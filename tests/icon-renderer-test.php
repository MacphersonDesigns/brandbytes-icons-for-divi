<?php
// Plain PHP test runner - no WordPress or PHPUnit required.
// Run: php tests/icon-renderer-test.php
//
// Covers Icon_Renderer::find_all_custom_entities_in_order() in isolation.
// That method is pure string logic (registry lookups + preg_match_all) with
// no WordPress function calls of its own, so it can be exercised the same
// way tests/icon-registry-test.php exercises Icon_Registry: a plain script
// with an in-memory get_option/update_option stand-in, no WP bootstrap.
//
// Icon_Renderer's own file guards on `!defined('ABSPATH')` (not the
// CIFD_TESTING flag the other two classes use), so a dummy ABSPATH constant
// is defined below purely to get past that guard at require-time. init(),
// which is what actually registers WordPress hooks, is never called here.

define('ABSPATH', __DIR__ . '/');
define('CIFD_TESTING', true);
require_once __DIR__ . '/../includes/class-icon-registry.php';
require_once __DIR__ . '/../includes/frontend/class-icon-renderer.php';

use CustomIconsForDivi\Icon_Registry;
use CustomIconsForDivi\Frontend\Icon_Renderer;

$passed = 0;
$failures = 0;

function assert_true($condition, $message) {
    global $passed, $failures;
    if ($condition) {
        $passed++;
    } else {
        $failures++;
        echo "FAIL: $message\n";
    }
}

function make_registry(&$store) {
    $get = function ($name, $default = null) use (&$store) {
        return array_key_exists($name, $store) ? $store[$name] : $default;
    };
    $update = function ($name, $value) use (&$store) {
        $store[$name] = $value;
        return true;
    };
    $uuid_counter = 0;
    $generate_uuid = function () use (&$uuid_counter) {
        $uuid_counter++;
        return sprintf('00000000-0000-4000-8000-%012d', $uuid_counter);
    };
    return new Icon_Registry('icons', 'next_id', 0xE000, $get, $update, $generate_uuid);
}

// find_all_custom_entities_in_order() is private; reach it via reflection
// the same way the class itself would call it internally.
// setAccessible() is required to invoke a private method on the plugin's
// minimum supported PHP version (7.4). PHP 8.1+ can invoke private methods
// via reflection without it, and PHP 8.5 deprecates the call as a no-op, so
// it's only made on versions that actually need it.
function reflect_private_method($class, $method_name) {
    $method = new ReflectionMethod($class, $method_name);
    if (PHP_VERSION_ID < 80100) {
        $method->setAccessible(true);
    }
    return $method;
}

function call_find_all_in_order(Icon_Renderer $renderer, $content) {
    $method = reflect_private_method(Icon_Renderer::class, 'find_all_custom_entities_in_order');
    return $method->invoke($renderer, $content);
}

function call_find_custom_blurb_uuid(Icon_Renderer $renderer, $parsed_block) {
    $method = reflect_private_method(Icon_Renderer::class, 'find_custom_blurb_uuid');
    return $method->invoke($renderer, $parsed_block);
}

$store = array();
$registry = make_registry($store);
$id_a = $registry->add('Icon A', 'https://example.com/a.svg', 'image/svg+xml');
$id_b = $registry->add('Icon B', 'https://example.com/b.svg', 'image/svg+xml');
$uuid_a = $registry->all()[0]['uuid'];
$entity_a = $registry->codepoint_entity_for($id_a);
$entity_b = $registry->codepoint_entity_for($id_b);
$renderer = new Icon_Renderer($registry);

$custom_blurb_block = array(
    'attrs' => array(
        'imageIcon' => array(
            'innerContent' => array(
                'desktop' => array(
                    'value' => array(
                        'icon' => array('type' => 'cifd', 'uuid' => $uuid_a),
                    ),
                ),
            ),
        ),
    ),
);
assert_true(
    call_find_custom_blurb_uuid($renderer, $custom_blurb_block) === $uuid_a,
    'Blurb adapter reads the plugin UUID from imageIcon.innerContent.desktop.value.icon'
);
assert_true(
    call_find_custom_blurb_uuid($renderer, array()) === '',
    'Blurb adapter ignores blocks without a plugin-owned icon reference'
);

// (a) Two distinct icon entities appear in a specific order (B before A,
// the opposite of registry/add order) -> returned in that same document
// order, not registry order.
$content_ordered = '<a class="et_pb_button">' . $entity_b . '</a> text <a class="et_pb_button">' . $entity_a . '</a>';
$result = call_find_all_in_order($renderer, $content_ordered);
assert_true($result === array($entity_b, $entity_a), 'two distinct entities are returned in document order, not registry order');

// (b) No custom icons anywhere in the content -> empty array.
$content_none = '<a class="et_pb_button">no icons here</a>';
$result = call_find_all_in_order($renderer, $content_none);
assert_true($result === array(), 'content with no custom icon entities returns an empty array');

// (c) The same icon's entity appears twice in the content -> returned
// twice, in order (matters for the positional consumption in
// render_button: each occurrence must be available to be consumed
// independently by a separate button).
$content_repeated = '<a class="et_pb_button">' . $entity_a . '</a> text <a class="et_pb_button">' . $entity_a . '</a>';
$result = call_find_all_in_order($renderer, $content_repeated);
assert_true($result === array($entity_a, $entity_a), 'the same entity appearing twice in content is returned twice, not deduplicated');

// Also verify the decoded (raw UTF-8 glyph) form of an entity is recognized
// and normalized back to its canonical encoded form, consistent with
// find_custom_entity_in_content()'s existing behavior.
$decoded_a = html_entity_decode($entity_a, ENT_QUOTES, 'UTF-8');
$content_decoded = '<a class="et_pb_button">' . $decoded_a . '</a>';
$result = call_find_all_in_order($renderer, $content_decoded);
assert_true($result === array($entity_a), 'a decoded (raw glyph) occurrence of an entity is recognized and normalized to its encoded form');

echo "\n$passed passed, $failures failed\n";
exit($failures > 0 ? 1 : 0);
