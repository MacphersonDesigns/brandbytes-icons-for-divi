<?php
// Dependency-free tests for immutable bundled icons and merged catalog reads.

define('CIFD_TESTING', true);
require_once __DIR__ . '/../includes/class-icon-registry.php';
require_once __DIR__ . '/../includes/class-bundled-icon-library.php';
require_once __DIR__ . '/../includes/class-icon-catalog.php';
require_once __DIR__ . '/../includes/divi5/class-custom-icon-asset-resolver.php';

use CustomIconsForDivi\Bundled_Icon_Library;
use CustomIconsForDivi\Icon_Catalog;
use CustomIconsForDivi\Icon_Registry;
use CustomIconsForDivi\Divi5\Custom_Icon_Asset_Resolver;

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

$manifest = __DIR__ . '/../assets/icon-libraries/essentials/manifest.json';
$library = new Bundled_Icon_Library($manifest, 'https://example.test/plugin/assets/icon-libraries/essentials/');
$bundled = $library->all();

assert_true(count($bundled) === 16, 'Essentials manifest exposes sixteen starter icons');
assert_true($bundled[0]['library'] === 'Essentials', 'bundled record exposes its library name');
assert_true($bundled[0]['source'] === 'bundled', 'bundled record identifies its immutable source');
assert_true($bundled[0]['locked'] === true, 'bundled record cannot be edited as an upload');
assert_true(strpos($bundled[0]['url'], 'icons/arrow-up.svg') !== false, 'bundled URL resolves from the manifest base');
assert_true($library->find_by_uuid('c1fd0001-0000-4000-8000-000000000016')['slug'] === 'star', 'bundled UUID resolves directly');

$store = array();
$get = function ($name, $default = null) use (&$store) {
    return array_key_exists($name, $store) ? $store[$name] : $default;
};
$update = function ($name, $value) use (&$store) {
    $store[$name] = $value;
    return true;
};
$registry = new Icon_Registry('icons', 'next_id', 0xE000, $get, $update, function () {
    return '22222222-2222-4222-8222-222222222222';
});
$registry->add('Client Mark', 'https://example.test/uploads/client-mark.svg', 'image/svg+xml', 42);

$catalog = new Icon_Catalog($registry, array($library));
assert_true(count($catalog->all()) === 17, 'catalog merges bundled and uploaded records');
assert_true($catalog->find_by_uuid('22222222-2222-4222-8222-222222222222')['source'] === 'uploaded', 'uploaded UUID resolves through catalog');
assert_true($catalog->find_by_uuid('c1fd0001-0000-4000-8000-000000000011')['name'] === 'Home', 'bundled UUID resolves through catalog');
assert_true($catalog->find_by_uuid('not-a-uuid') === null, 'unknown catalog value resolves to null');
$resolver = new Custom_Icon_Asset_Resolver($catalog, function () { return false; });
$resolved_home = $resolver->resolve('c1fd0001-0000-4000-8000-000000000011');
assert_true($resolved_home['source'] === 'bundled', 'asset resolver retains bundled source');
assert_true(strpos($resolved_home['url'], 'icons/home.svg') !== false, 'asset resolver returns bundled SVG URL');

echo "\n$passed passed, $failures failed\n";
exit($failures > 0 ? 1 : 0);
