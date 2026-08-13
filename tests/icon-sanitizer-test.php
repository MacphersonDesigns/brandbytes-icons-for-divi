<?php
// Run: php tests/icon-sanitizer-test.php

define('CIFD_TESTING', true);
require_once __DIR__ . '/../includes/class-icon-sanitizer.php';

use CustomIconsForDivi\Icon_Sanitizer;

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

$sanitizer = new Icon_Sanitizer();

$clean = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M0 0h24v24H0z" fill="#000"/></svg>';
$result = $sanitizer->sanitize($clean);
assert_true(is_string($result), 'a clean SVG sanitizes to a string');
assert_true(strpos($result, '<path') !== false, 'a clean SVG keeps its <path> element');

$with_script = '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0h10v10H0z"/><script>alert(1)</script></svg>';
$result = $sanitizer->sanitize($with_script);
assert_true(strpos($result, '<script') === false, '<script> element is removed');
assert_true(strpos($result, 'alert') === false, 'script content is removed along with the tag');

$with_handler = '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0h10v10H0z" onclick="alert(1)"/></svg>';
$result = $sanitizer->sanitize($with_handler);
assert_true(strpos($result, 'onclick') === false, 'onclick attribute is removed');
assert_true(strpos($result, '<path') !== false, 'the element itself is kept once the handler is stripped');

$with_external_url = '<svg xmlns="http://www.w3.org/2000/svg"><rect fill="url(https://evil.example/x)"/></svg>';
$result = $sanitizer->sanitize($with_external_url);
assert_true(strpos($result, 'evil.example') === false, 'external URL in fill attribute is stripped');
assert_true(strpos($result, '<rect') !== false, 'element is preserved after URL removal');

$with_fragment_url = '<svg xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="gradient1"><stop/></linearGradient></defs><rect fill="url(#gradient1)"/></svg>';
$result = $sanitizer->sanitize($with_fragment_url);
assert_true(strpos($result, 'url(#gradient1)') !== false, 'same-document fragment URL in fill is preserved');

$with_doctype = "<?xml version=\"1.0\"?><!DOCTYPE svg [<!ENTITY xxe SYSTEM \"file:///etc/passwd\">]><svg xmlns=\"http://www.w3.org/2000/svg\"><text>&xxe;</text></svg>";
$result = $sanitizer->sanitize($with_doctype);
assert_true(is_object($result), 'a DOCTYPE/ENTITY declaration is rejected as an error, not passed through');

$not_svg = '<div>not an svg</div>';
$result = $sanitizer->sanitize($not_svg);
assert_true(is_object($result), 'a non-SVG root element is rejected as an error');

echo "\n$passed passed, $failures failed\n";
exit($failures > 0 ? 1 : 0);
