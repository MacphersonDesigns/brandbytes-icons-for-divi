<?php
// Run: php tests/icon-upload-policy-test.php

define('CIFD_TESTING', true);

// The production class uses WordPress translation helpers. This tiny test shim
// preserves the returned message while keeping the policy unit test independent
// from a complete WordPress bootstrap.
if (!function_exists('__')) {
    function __($message) {
        return $message;
    }
}

require_once __DIR__ . '/../includes/class-icon-sanitizer.php';
require_once __DIR__ . '/../includes/admin/class-icon-upload-policy.php';

use CustomIconsForDivi\Admin\Icon_Upload_Policy;
use CustomIconsForDivi\Icon_Sanitizer;

$passed = 0;
$failures = 0;

function upload_policy_assert_true($condition, $message) {
    global $passed, $failures;
    if ($condition) {
        $passed++;
    } else {
        $failures++;
        echo "FAIL: $message\n";
    }
}

function upload_policy_temp_file($contents) {
    $path = tempnam(sys_get_temp_dir(), 'cifd-upload-test-');
    file_put_contents($path, $contents);
    return $path;
}

$allowed_policy = new Icon_Upload_Policy(new Icon_Sanitizer(), function ($capability) {
    return $capability === Icon_Upload_Policy::CAPABILITY;
});

$mimes = $allowed_policy->allow_svg_uploads(array('png' => 'image/png'));
upload_policy_assert_true(isset($mimes['svg']), 'trusted icon managers receive the SVG MIME permission');

$denied_policy = new Icon_Upload_Policy(new Icon_Sanitizer(), function () {
    return false;
});
$mimes = $denied_policy->allow_svg_uploads(array('png' => 'image/png'));
upload_policy_assert_true(!isset($mimes['svg']), 'users without the management capability do not receive SVG permission');

$malicious_path = upload_policy_temp_file(
    '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><path onclick="alert(2)" d="M0 0h10v10z"/></svg>'
);
$result = $allowed_policy->sanitize_svg_before_publish(array(
    'name' => 'client-icon.svg',
    'tmp_name' => $malicious_path,
    'type' => 'application/octet-stream',
    'error' => 0,
));
$published_contents = file_get_contents($malicious_path);
upload_policy_assert_true(empty($result['error']), 'a valid SVG is accepted after sanitation');
upload_policy_assert_true($result['type'] === 'image/svg+xml', 'the validated SVG receives the authoritative MIME type');
upload_policy_assert_true(strpos($published_contents, '<script') === false, 'script markup is gone before WordPress can publish the temp file');
upload_policy_assert_true(strpos($published_contents, 'onclick') === false, 'event handlers are gone before WordPress can publish the temp file');
upload_policy_assert_true(strpos($published_contents, '<path') !== false, 'safe icon geometry survives pre-publication sanitation');
unlink($malicious_path);

$invalid_path = upload_policy_temp_file('<svg><broken></svg>');
$result = $allowed_policy->sanitize_svg_before_publish(array(
    'name' => 'broken.svg',
    'tmp_name' => $invalid_path,
    'type' => 'image/svg+xml',
    'error' => 0,
));
upload_policy_assert_true(!empty($result['error']), 'malformed SVG is rejected before WordPress publishes it');
unlink($invalid_path);

$denied_path = upload_policy_temp_file('<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0h1v1z"/></svg>');
$result = $denied_policy->sanitize_svg_before_publish(array(
    'name' => 'denied.svg',
    'tmp_name' => $denied_path,
    'type' => 'image/svg+xml',
    'error' => 0,
));
upload_policy_assert_true(!empty($result['error']), 'an unauthorized SVG upload is rejected by the pre-publication boundary');
unlink($denied_path);

$png = array('name' => 'icon.png', 'tmp_name' => '/not/read.png', 'type' => 'image/png', 'error' => 0);
upload_policy_assert_true(
    $allowed_policy->sanitize_svg_before_publish($png) === $png,
    'non-SVG uploads pass through without being read or changed'
);

echo "\n$passed passed, $failures failed\n";
exit($failures > 0 ? 1 : 0);
