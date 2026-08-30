<?php
// Dependency-free test for UUID-to-attachment resolution.
// Run: php tests/custom-icon-asset-resolver-test.php

define('CIFD_TESTING', true);
require_once __DIR__ . '/../includes/class-icon-registry.php';
require_once __DIR__ . '/../includes/divi5/class-custom-icon-asset-resolver.php';

use CustomIconsForDivi\Divi5\Custom_Icon_Asset_Resolver;
use CustomIconsForDivi\Icon_Registry;

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

$store = array();
$get = function ($name, $default = null) use (&$store) {
    return array_key_exists($name, $store) ? $store[$name] : $default;
};
$update = function ($name, $value) use (&$store) {
    $store[$name] = $value;
    return true;
};
$generate_uuid = function () {
    return '11111111-1111-4111-8111-111111111111';
};

$registry = new Icon_Registry('icons', 'next_id', 0xE000, $get, $update, $generate_uuid);
$registry->add('Client Mark', 'https://legacy.example/client-mark.svg', 'image/svg+xml', 52);
$uuid = $registry->find(1)['uuid'];

$resolver = new Custom_Icon_Asset_Resolver($registry, function ($attachment_id) {
    return $attachment_id === 52 ? 'https://current.example/uploads/client-mark.svg' : false;
});
$resolved = $resolver->resolve($uuid);

assert_true($resolved['uuid'] === $uuid, 'resolver retains the saved stable UUID');
assert_true($resolved['attachment_id'] === 52, 'resolver retains the attachment relationship');
assert_true($resolved['url'] === 'https://current.example/uploads/client-mark.svg', 'current attachment URL wins over legacy URL');
assert_true($resolver->resolve('22222222-2222-4222-8222-222222222222') === null, 'unknown UUID resolves to null');

$fallback_resolver = new Custom_Icon_Asset_Resolver($registry, function () {
    return false;
});
assert_true($fallback_resolver->resolve($uuid)['url'] === 'https://legacy.example/client-mark.svg', 'legacy URL remains a controlled compatibility fallback');

echo "\n$passed passed, $failures failed\n";
exit($failures > 0 ? 1 : 0);
