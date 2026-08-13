<?php
// Plain PHP test runner - no WordPress or PHPUnit required.
// Run: php tests/icon-registry-test.php

define('CIFD_TESTING', true);
require_once __DIR__ . '/../includes/class-icon-registry.php';

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

function make_registry(&$store) {
    $get = function ($name, $default = null) use (&$store) {
        return array_key_exists($name, $store) ? $store[$name] : $default;
    };
    $update = function ($name, $value) use (&$store) {
        $store[$name] = $value;
        return true;
    };
    return new Icon_Registry('icons', 'next_id', 0xE000, $get, $update);
}

$store = array();
$registry = make_registry($store);
$id = $registry->add('Handshake', 'https://example.com/handshake.svg', 'image/svg+xml');
assert_true($id === 1, 'first icon gets id 1');
assert_true(count($registry->all()) === 1, 'registry has one icon after add');

assert_true($registry->codepoint_for(1) === 0xE000 + 1, 'codepoint_for(1) is base + 1');
assert_true($registry->codepoint_entity_for(1) === '&#x' . dechex(0xE000 + 1) . ';', 'codepoint_entity_for matches hex entity');

$second_id = $registry->add('Star', 'https://example.com/star.svg', 'image/svg+xml');
assert_true($second_id === 2, 'second icon gets id 2');
$registry->delete($second_id);
$third_id = $registry->add('Heart', 'https://example.com/heart.svg', 'image/svg+xml');
assert_true($third_id === 3, 'id counter does not reuse a deleted id');

$registry->rename(1, 'Handshake Icon');
$icon = $registry->find(1);
assert_true($icon['name'] === 'Handshake Icon', 'rename updates the name');
assert_true($icon['url'] === 'https://example.com/handshake.svg', 'rename leaves url untouched');

$before = $registry->all();
$result = $registry->delete(999);
assert_true($result === false, 'deleting an unknown id returns false');
assert_true($registry->all() === $before, 'deleting an unknown id does not change storage');

$registry->reorder(array(3, 1));
$ids_in_order = array_map(function ($icon) { return $icon['id']; }, $registry->all());
assert_true($ids_in_order === array(3, 1), 'reorder places icons in the requested order');

$store2 = array();
$registry2 = make_registry($store2);
$registry2->add('A', 'https://example.com/a.svg', 'image/svg+xml');
$registry2->add('B', 'https://example.com/b.svg', 'image/svg+xml');
$registry2->reorder(array(2));
$ids2 = array_map(function ($icon) { return $icon['id']; }, $registry2->all());
assert_true($ids2 === array(2, 1), 'reorder with a partial id list appends the missing icon rather than dropping it');

echo "\n$passed passed, $failures failed\n";
exit($failures > 0 ? 1 : 0);
