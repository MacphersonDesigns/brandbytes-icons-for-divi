# Custom Icons for Divi Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship a WordPress plugin that lets a site owner upload custom icons through a real Media-Library-backed admin screen and use them inside Divi 5's native icon picker for the Icon module, Blurb, Button, Toggle, and Icon List.

**Architecture:** Icons are stored in a single WP option with stable, never-reused ids mapped deterministically to Unicode Private-Use-Area codepoints. A builder-side script registers those codepoints into Divi 5's `iconList` hook so they appear in the real icon picker, and a `MutationObserver` swaps the picker/preview glyph for a real `<img>`. `render_block` filters plus a front-end script perform the same glyph-to-image swap on the published page.

**Tech Stack:** PHP 7.4+, vanilla JS + jQuery (matching Divi's own runtime dependencies), WP Media Library (`wp.media`), no external Composer packages.

**Spec:** `docs/superpowers/specs/2026-08-13-custom-icon-library-design.md`

## Global Constraints

- Divi 5 native only — no Divi 4 / compat-mode code paths.
- Allowed icon mime types: `image/svg+xml` (sanitized server-side before storage), `image/png`, `image/jpeg`, `image/webp`.
- Icon ids are monotonically increasing and never reused after deletion; codepoint = `0xE000 + id` (Unicode Private Use Area).
- Options used: `cifd_icons` (icon list), `cifd_next_id` (id counter).
- `uninstall.php` removes only the plugin's own options — never touches Media Library attachments.
- The admin Icon Library screen must pass an accessibility-lead review (keyboard operability, labeling, focus management) before Task 6 is considered done.

---

### Task 1: Plugin scaffold

**Files:**
- Create: `custom-icons-for-divi.php`
- Create: `uninstall.php`
- Create: `composer.json`
- Create: `.gitignore`
- Create: `README.md`

**Interfaces:**
- Produces: constants `CIFD_VERSION`, `CIFD_FILE`, `CIFD_DIR`, `CIFD_URL`, `CIFD_OPTION_ICONS`, `CIFD_OPTION_NEXT_ID`, `CIFD_CODEPOINT_BASE` (used by every later task). Namespace root `CustomIconsForDivi`.

- [ ] **Step 1: Write the plugin bootstrap file**

```php
<?php
/**
 * Plugin Name: Custom Icons for Divi
 * Description: Upload custom icons and use them in Divi 5's Icon module, Blurb, Button, Toggle, and Icon List.
 * Version: 0.1.0
 * Requires PHP: 7.4
 * Author: MacPherson Designs
 * License: GPL-2.0-or-later
 * Text Domain: custom-icons-for-divi
 */

namespace CustomIconsForDivi;

if (!defined('ABSPATH')) {
    exit;
}

define('CIFD_VERSION', '0.1.0');
define('CIFD_FILE', __FILE__);
define('CIFD_DIR', plugin_dir_path(__FILE__));
define('CIFD_URL', plugin_dir_url(__FILE__));
define('CIFD_OPTION_ICONS', 'cifd_icons');
define('CIFD_OPTION_NEXT_ID', 'cifd_next_id');
define('CIFD_CODEPOINT_BASE', 0xE000);
```

Save as `custom-icons-for-divi.php`. Task 10 appends the `require_once` list and the `bootstrap()` function to this same file once every class exists.

- [ ] **Step 2: Write the uninstall script**

```php
<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('cifd_icons');
delete_option('cifd_next_id');
```

Save as `uninstall.php`.

- [ ] **Step 3: Write composer.json, .gitignore, README.md**

`composer.json`:

```json
{
    "name": "macphersondesigns/custom-icons-for-divi",
    "description": "Upload custom icons and use them in Divi 5's Icon module, Blurb, Button, Toggle, and Icon List.",
    "type": "wordpress-plugin",
    "license": "GPL-2.0-or-later",
    "require": {
        "php": ">=7.4"
    },
    "config": {
        "sort-packages": true
    },
    "scripts": {
        "test": [
            "@php tests/icon-registry-test.php",
            "@php tests/icon-sanitizer-test.php"
        ]
    }
}
```

`.gitignore`:

```
.DS_Store
Thumbs.db
*.swp
*~
/vendor/
/node_modules/
*.log
```

`README.md`:

```markdown
# Custom Icons for Divi

Upload custom icons through a Media-Library-backed admin screen and use
them in Divi 5's Icon module, Blurb, Button, Toggle, and Icon List — just
like any built-in Divi icon.

## Development

Run tests: `composer test` (or `php tests/<file>.php` individually).

Symlink into a local WordPress install for builder testing:

    ln -s "$(pwd)" "/path/to/wp-content/plugins/custom-icons-for-divi"
```

- [ ] **Step 4: Verify syntax and commit**

Run: `php -l custom-icons-for-divi.php && php -l uninstall.php`
Expected: `No syntax errors detected` for both files.

```bash
git add custom-icons-for-divi.php uninstall.php composer.json .gitignore README.md
git commit -m "Scaffold plugin: bootstrap constants, uninstall, composer, readme"
```

---

### Task 2: Icon_Registry class (TDD)

**Files:**
- Create: `includes/class-icon-registry.php`
- Test: `tests/icon-registry-test.php`

**Interfaces:**
- Consumes: nothing from earlier tasks.
- Produces: `CustomIconsForDivi\Icon_Registry` with constructor
  `__construct($icons_option, $next_id_option, $codepoint_base, $get_option = 'get_option', $update_option = 'update_option')`
  and methods `all(): array`, `find($id): ?array`, `add($name, $url, $mime): int`,
  `rename($id, $name): bool`, `delete($id): bool`, `reorder(array $ordered_ids): bool`,
  `codepoint_for($id): int`, `codepoint_entity_for($id): string`. Used by Tasks 4, 7, 9, 10.

- [ ] **Step 1: Write the failing test**

Create `tests/icon-registry-test.php`:

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/icon-registry-test.php`
Expected: fatal error — `includes/class-icon-registry.php` does not exist yet.

- [ ] **Step 3: Write the implementation**

Create `includes/class-icon-registry.php`:

```php
<?php
namespace CustomIconsForDivi;

if (!defined('ABSPATH') && !defined('CIFD_TESTING')) {
    exit;
}

class Icon_Registry {

    private $icons_option;
    private $next_id_option;
    private $codepoint_base;
    private $get_option;
    private $update_option;

    public function __construct($icons_option, $next_id_option, $codepoint_base, $get_option = 'get_option', $update_option = 'update_option') {
        $this->icons_option = $icons_option;
        $this->next_id_option = $next_id_option;
        $this->codepoint_base = $codepoint_base;
        $this->get_option = $get_option;
        $this->update_option = $update_option;
    }

    public function all() {
        $icons = call_user_func($this->get_option, $this->icons_option, array());
        return is_array($icons) ? $icons : array();
    }

    public function find($id) {
        foreach ($this->all() as $icon) {
            if ((int) $icon['id'] === (int) $id) {
                return $icon;
            }
        }
        return null;
    }

    public function add($name, $url, $mime) {
        $icons = $this->all();
        $id = $this->next_id();

        $icons[] = array(
            'id'   => $id,
            'name' => $name,
            'url'  => $url,
            'mime' => $mime,
        );

        call_user_func($this->update_option, $this->icons_option, $icons);
        call_user_func($this->update_option, $this->next_id_option, $id + 1);

        return $id;
    }

    public function rename($id, $name) {
        $icons = $this->all();
        $found = false;

        foreach ($icons as &$icon) {
            if ((int) $icon['id'] === (int) $id) {
                $icon['name'] = $name;
                $found = true;
                break;
            }
        }
        unset($icon);

        if (!$found) {
            return false;
        }

        call_user_func($this->update_option, $this->icons_option, $icons);
        return true;
    }

    public function delete($id) {
        $icons = $this->all();
        $filtered = array_values(array_filter($icons, function ($icon) use ($id) {
            return (int) $icon['id'] !== (int) $id;
        }));

        if (count($filtered) === count($icons)) {
            return false;
        }

        call_user_func($this->update_option, $this->icons_option, $filtered);
        return true;
    }

    public function reorder(array $ordered_ids) {
        $icons = $this->all();
        $by_id = array();
        foreach ($icons as $icon) {
            $by_id[(int) $icon['id']] = $icon;
        }

        $reordered = array();
        foreach ($ordered_ids as $id) {
            if (isset($by_id[(int) $id])) {
                $reordered[] = $by_id[(int) $id];
                unset($by_id[(int) $id]);
            }
        }
        foreach ($by_id as $icon) {
            $reordered[] = $icon;
        }

        call_user_func($this->update_option, $this->icons_option, $reordered);
        return true;
    }

    public function codepoint_for($id) {
        return $this->codepoint_base + (int) $id;
    }

    public function codepoint_entity_for($id) {
        return '&#x' . dechex($this->codepoint_for($id)) . ';';
    }

    private function next_id() {
        $stored = call_user_func($this->get_option, $this->next_id_option, null);
        if (is_int($stored) || (is_string($stored) && ctype_digit($stored))) {
            return (int) $stored;
        }

        $max = 0;
        foreach ($this->all() as $icon) {
            $max = max($max, (int) $icon['id']);
        }
        return $max + 1;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/icon-registry-test.php`
Expected: `10 passed, 0 failed` (or similar — every assertion passes, 0 failures).

- [ ] **Step 5: Commit**

```bash
git add includes/class-icon-registry.php tests/icon-registry-test.php
git commit -m "Add Icon_Registry with stable-id/codepoint mapping and tests"
```

---

### Task 3: Icon_Sanitizer class (TDD)

**Files:**
- Create: `includes/class-icon-sanitizer.php`
- Test: `tests/icon-sanitizer-test.php`

**Interfaces:**
- Consumes: nothing from earlier tasks.
- Produces: `CustomIconsForDivi\Icon_Sanitizer` with method `sanitize($svg_markup): string|\WP_Error|\Exception`
  (returns the cleaned SVG markup as a string on success; a `WP_Error` when run inside WordPress, or an
  `Exception` in the plain-PHP test context, on failure). Used by Task 4.

- [ ] **Step 1: Write the failing test**

Create `tests/icon-sanitizer-test.php`:

```php
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

$with_js_uri = '<svg xmlns="http://www.w3.org/2000/svg"><a href="javascript:alert(1)"><path d="M0 0h10v10H0z"/></a></svg>';
$result = $sanitizer->sanitize($with_js_uri);
assert_true(strpos($result, 'javascript:') === false, 'javascript: URI is removed');

$with_doctype = "<?xml version=\"1.0\"?><!DOCTYPE svg [<!ENTITY xxe SYSTEM \"file:///etc/passwd\">]><svg xmlns=\"http://www.w3.org/2000/svg\"><text>&xxe;</text></svg>";
$result = $sanitizer->sanitize($with_doctype);
assert_true(is_object($result), 'a DOCTYPE/ENTITY declaration is rejected as an error, not passed through');

$not_svg = '<div>not an svg</div>';
$result = $sanitizer->sanitize($not_svg);
assert_true(is_object($result), 'a non-SVG root element is rejected as an error');

echo "\n$passed passed, $failures failed\n";
exit($failures > 0 ? 1 : 0);
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/icon-sanitizer-test.php`
Expected: fatal error — `includes/class-icon-sanitizer.php` does not exist yet.

- [ ] **Step 3: Write the implementation**

Create `includes/class-icon-sanitizer.php`:

```php
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
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/icon-sanitizer-test.php`
Expected: `9 passed, 0 failed` (or similar — every assertion passes, 0 failures).

- [ ] **Step 5: Commit**

```bash
git add includes/class-icon-sanitizer.php tests/icon-sanitizer-test.php
git commit -m "Add Icon_Sanitizer: allowlist-based SVG cleaning, tests"
```

---

### Task 4: Admin Icon_Library_Page (render + AJAX)

**Files:**
- Create: `includes/admin/class-icon-library-page.php`

**Interfaces:**
- Consumes: `Icon_Registry` (Task 2) — `all()`, `find()`, `add()`, `rename()`, `delete()`, `reorder()`.
  `Icon_Sanitizer` (Task 3) — `sanitize()`.
- Produces: `CustomIconsForDivi\Admin\Icon_Library_Page` with constructor
  `__construct(Icon_Registry $registry, Icon_Sanitizer $sanitizer)` and method `init(): void`.
  Registers AJAX actions `cifd_add_icon`, `cifd_rename_icon`, `cifd_delete_icon`, `cifd_reorder_icons`,
  and admin page slug `cifd-icon-library`, hook suffix `toplevel_page_cifd-icon-library`
  (consumed by Task 5's JS via the `cifdIconLibrary` localized object, and by Task 6's review).

- [ ] **Step 1: Write the class**

Create `includes/admin/class-icon-library-page.php`:

```php
<?php
namespace CustomIconsForDivi\Admin;

use CustomIconsForDivi\Icon_Registry;
use CustomIconsForDivi\Icon_Sanitizer;

if (!defined('ABSPATH')) {
    exit;
}

class Icon_Library_Page {

    const CAPABILITY = 'manage_options';
    const NONCE_ACTION = 'cifd_icon_library';

    private $registry;
    private $sanitizer;

    public function __construct(Icon_Registry $registry, Icon_Sanitizer $sanitizer) {
        $this->registry = $registry;
        $this->sanitizer = $sanitizer;
    }

    public function init() {
        add_action('admin_menu', array($this, 'register_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_ajax_cifd_add_icon', array($this, 'ajax_add_icon'));
        add_action('wp_ajax_cifd_rename_icon', array($this, 'ajax_rename_icon'));
        add_action('wp_ajax_cifd_delete_icon', array($this, 'ajax_delete_icon'));
        add_action('wp_ajax_cifd_reorder_icons', array($this, 'ajax_reorder_icons'));
    }

    public function register_page() {
        add_menu_page(
            'Custom Icons for Divi',
            'Custom Icons',
            self::CAPABILITY,
            'cifd-icon-library',
            array($this, 'render_page'),
            'dashicons-images-alt2'
        );
    }

    public function enqueue_assets($hook) {
        if ($hook !== 'toplevel_page_cifd-icon-library') {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_style('cifd-icon-library', CIFD_URL . 'includes/admin/assets/icon-library.css', array(), CIFD_VERSION);
        wp_enqueue_script('cifd-icon-library', CIFD_URL . 'includes/admin/assets/icon-library.js', array('jquery', 'jquery-ui-sortable', 'wp-util'), CIFD_VERSION, true);
        wp_localize_script('cifd-icon-library', 'cifdIconLibrary', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce(self::NONCE_ACTION),
            'icons'   => array_values($this->registry->all()),
            'strings' => array(
                'addIcon'         => __('Add Icon', 'custom-icons-for-divi'),
                'deleteConfirm'   => __('Delete this icon? Pages already using it will simply stop showing it.', 'custom-icons-for-divi'),
                'namePlaceholder' => __('Icon name', 'custom-icons-for-divi'),
            ),
        ));
    }

    public function render_page() {
        if (!current_user_can(self::CAPABILITY)) {
            return;
        }
        ?>
        <div class="wrap cifd-icon-library">
            <h1><?php esc_html_e('Custom Icons for Divi', 'custom-icons-for-divi'); ?></h1>
            <p>
                <?php esc_html_e('Upload icons here to use them in the Divi Builder icon picker: Icon module, Blurb, Button, Toggle, and Icon List.', 'custom-icons-for-divi'); ?>
            </p>

            <button type="button" class="button button-primary" id="cifd-add-icon">
                <?php esc_html_e('Add Icon', 'custom-icons-for-divi'); ?>
            </button>

            <ul id="cifd-icon-grid" class="cifd-icon-grid" aria-live="polite"></ul>
            <p id="cifd-icon-grid-empty" class="cifd-icon-grid-empty" hidden>
                <?php esc_html_e('No custom icons yet. Click "Add Icon" to upload one.', 'custom-icons-for-divi'); ?>
            </p>
        </div>
        <?php
    }

    public function ajax_add_icon() {
        $this->verify_request();

        $attachment_id = isset($_POST['attachment_id']) ? absint($_POST['attachment_id']) : 0;
        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';

        if (!$attachment_id || $name === '') {
            wp_send_json_error(array('message' => __('An attachment and a name are required.', 'custom-icons-for-divi')), 400);
        }

        $mime = get_post_mime_type($attachment_id);
        $file = get_attached_file($attachment_id);
        $url = wp_get_attachment_url($attachment_id);

        if (!$file || !$url) {
            wp_send_json_error(array('message' => __('Could not find that media file.', 'custom-icons-for-divi')), 404);
        }

        $allowed_mimes = array('image/svg+xml', 'image/png', 'image/jpeg', 'image/webp');
        if (!in_array($mime, $allowed_mimes, true)) {
            wp_send_json_error(array('message' => __('Only SVG, PNG, JPG, and WebP files are allowed.', 'custom-icons-for-divi')), 400);
        }

        if ($mime === 'image/svg+xml') {
            $svg = file_get_contents($file);
            $sanitized = $this->sanitizer->sanitize($svg);
            if (!is_string($sanitized)) {
                wp_send_json_error(array('message' => __('That SVG could not be safely sanitized and was rejected.', 'custom-icons-for-divi')), 400);
            }
            file_put_contents($file, $sanitized);
        }

        $id = $this->registry->add($name, $url, $mime);

        wp_send_json_success(array('icon' => $this->registry->find($id)));
    }

    public function ajax_rename_icon() {
        $this->verify_request();

        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';

        if (!$id || $name === '') {
            wp_send_json_error(array('message' => __('An id and a name are required.', 'custom-icons-for-divi')), 400);
        }

        if (!$this->registry->rename($id, $name)) {
            wp_send_json_error(array('message' => __('That icon could not be found.', 'custom-icons-for-divi')), 404);
        }

        wp_send_json_success();
    }

    public function ajax_delete_icon() {
        $this->verify_request();

        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        if (!$id || !$this->registry->delete($id)) {
            wp_send_json_error(array('message' => __('That icon could not be found.', 'custom-icons-for-divi')), 404);
        }

        wp_send_json_success();
    }

    public function ajax_reorder_icons() {
        $this->verify_request();

        $ids = isset($_POST['ids']) && is_array($_POST['ids']) ? array_map('absint', $_POST['ids']) : array();
        $this->registry->reorder($ids);

        wp_send_json_success();
    }

    private function verify_request() {
        if (!current_user_can(self::CAPABILITY) || !check_ajax_referer(self::NONCE_ACTION, 'nonce', false)) {
            wp_send_json_error(array('message' => __('You are not allowed to do that.', 'custom-icons-for-divi')), 403);
        }
    }
}
```

- [ ] **Step 2: Verify syntax and commit**

Run: `php -l includes/admin/class-icon-library-page.php`
Expected: `No syntax errors detected`.

```bash
git add includes/admin/class-icon-library-page.php
git commit -m "Add admin Icon_Library_Page: render + AJAX CRUD"
```

---

### Task 5: Admin JS/CSS assets

**Files:**
- Create: `includes/admin/assets/icon-library.js`
- Create: `includes/admin/assets/icon-library.css`

**Interfaces:**
- Consumes: the `cifdIconLibrary` object localized by Task 4 (`ajaxUrl`, `nonce`, `icons`, `strings`), and
  the DOM ids from Task 4's `render_page()`: `#cifd-add-icon`, `#cifd-icon-grid`, `#cifd-icon-grid-empty`.
- Produces: AJAX POST calls to `cifd_add_icon`, `cifd_rename_icon`, `cifd_delete_icon`, `cifd_reorder_icons`
  matching the handlers Task 4 registered.

- [ ] **Step 1: Write the JS**

Create `includes/admin/assets/icon-library.js`:

```js
(function ($) {
    'use strict';

    var settings = window.cifdIconLibrary || { icons: [], strings: {} };
    var $grid = $('#cifd-icon-grid');
    var $empty = $('#cifd-icon-grid-empty');
    var mediaFrame = null;

    function ajax(action, data) {
        return $.post(settings.ajaxUrl, $.extend({ action: action, nonce: settings.nonce }, data));
    }

    function renderGrid() {
        $grid.empty();
        var icons = settings.icons || [];
        $empty.prop('hidden', icons.length > 0);

        icons.forEach(function (icon) {
            var $item = $('<li>', { 'class': 'cifd-icon-card', 'data-id': icon.id });
            var $thumb = $('<img>', { src: icon.url, alt: '', 'class': 'cifd-icon-thumb' });
            var labelId = 'cifd-icon-name-' + icon.id;
            var $label = $('<label>', { 'for': labelId, text: settings.strings.namePlaceholder, 'class': 'screen-reader-text' });
            var $nameInput = $('<input>', { type: 'text', id: labelId, value: icon.name, 'class': 'cifd-icon-name' });
            var $deleteBtn = $('<button>', {
                type: 'button',
                'class': 'button-link cifd-icon-delete',
                text: 'Delete',
                'aria-label': 'Delete ' + icon.name
            });

            $nameInput.on('change', function () {
                var newName = $(this).val();
                ajax('cifd_rename_icon', { id: icon.id, name: newName }).done(function (response) {
                    if (response.success) {
                        icon.name = newName;
                        $deleteBtn.attr('aria-label', 'Delete ' + newName);
                    }
                });
            });

            $deleteBtn.on('click', function () {
                if (!window.confirm(settings.strings.deleteConfirm)) {
                    return;
                }
                ajax('cifd_delete_icon', { id: icon.id }).done(function (response) {
                    if (response.success) {
                        settings.icons = settings.icons.filter(function (i) { return i.id !== icon.id; });
                        $item.remove();
                        $empty.prop('hidden', settings.icons.length > 0);
                    }
                });
            });

            $item.append($thumb, $label, $nameInput, $deleteBtn);
            $grid.append($item);
        });
    }

    function addIcon() {
        if (mediaFrame) {
            mediaFrame.open();
            return;
        }

        mediaFrame = wp.media({
            title: settings.strings.addIcon,
            library: { type: ['image/svg+xml', 'image/png', 'image/jpeg', 'image/webp'] },
            multiple: false,
            button: { text: settings.strings.addIcon }
        });

        mediaFrame.on('select', function () {
            var attachment = mediaFrame.state().get('selection').first().toJSON();
            var name = window.prompt(settings.strings.namePlaceholder, attachment.title || attachment.filename);
            if (!name) {
                return;
            }

            ajax('cifd_add_icon', { attachment_id: attachment.id, name: name }).done(function (response) {
                if (response.success) {
                    settings.icons.push(response.data.icon);
                    renderGrid();
                } else {
                    window.alert(response.data && response.data.message);
                }
            });
        });

        mediaFrame.open();
    }

    $(function () {
        renderGrid();

        $('#cifd-add-icon').on('click', addIcon);

        if ($.fn.sortable) {
            $grid.sortable({
                update: function () {
                    var ids = $grid.find('.cifd-icon-card').map(function () {
                        return $(this).data('id');
                    }).get();
                    ajax('cifd_reorder_icons', { ids: ids });
                }
            });
        }
    });
})(jQuery);
```

- [ ] **Step 2: Write the CSS**

Create `includes/admin/assets/icon-library.css`:

```css
.cifd-icon-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 12px;
    margin: 16px 0;
    padding: 0;
    list-style: none;
}

.cifd-icon-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    padding: 12px;
    border: 1px solid #dcdcde;
    border-radius: 4px;
    background: #fff;
}

.cifd-icon-thumb {
    width: 48px;
    height: 48px;
    object-fit: contain;
}

.cifd-icon-name {
    width: 100%;
    text-align: center;
}

.cifd-icon-grid-empty {
    color: #646970;
    font-style: italic;
}
```

- [ ] **Step 3: Commit**

```bash
git add includes/admin/assets/icon-library.js includes/admin/assets/icon-library.css
git commit -m "Add admin Icon Library grid UI: upload, rename, delete, reorder"
```

---

### Task 6: Accessibility review & remediation of the admin screen

**Files:**
- Modify: `includes/admin/class-icon-library-page.php` (as needed)
- Modify: `includes/admin/assets/icon-library.js` (as needed)
- Modify: `includes/admin/assets/icon-library.css` (as needed)

**Interfaces:** none new — this task only refines Tasks 4–5's existing output.

- [ ] **Step 1: Delegate the review**

Use the Agent tool with `subagent_type: "accessibility-agents:accessibility-lead"`. Give it the three files
above and ask it to review the Icon Library admin screen for: keyboard operability of Add/rename/delete/
reorder, correct labeling of the name input and delete button, focus management after adding or deleting
an icon (focus should land somewhere sensible, not be lost), and drag-reorder having a non-drag-only
fallback (the `jquery-ui-sortable` interaction currently has no keyboard equivalent — the lead should
either recommend up/down reorder buttons as a keyboard-accessible alternative or another concrete fix).

- [ ] **Step 2: Apply the recommended fixes**

Edit the three files per the accessibility-lead's findings.

- [ ] **Step 3: Verify and commit**

Run: `php -l includes/admin/class-icon-library-page.php`
Expected: `No syntax errors detected`.

```bash
git add includes/admin/class-icon-library-page.php includes/admin/assets/icon-library.js includes/admin/assets/icon-library.css
git commit -m "Apply accessibility-lead review fixes to Icon Library admin screen"
```

---

### Task 7: Builder Icon_Picker_Injector (PHP)

**Files:**
- Create: `includes/builder/class-icon-picker-injector.php`

**Interfaces:**
- Consumes: `Icon_Registry` (Task 2) — `all()`, `codepoint_entity_for()`.
- Produces: `CustomIconsForDivi\Builder\Icon_Picker_Injector` with constructor
  `__construct(Icon_Registry $registry)` and method `init(): void`. Enqueues
  `includes/builder/assets/icon-picker.js` (Task 8) and localizes it as `window.cifdBuilderIcons`
  (an array of `{id, name, url, unicode, decodedUnicode, searchTerms, styles, fontWeight}`).

- [ ] **Step 1: Write the class**

Create `includes/builder/class-icon-picker-injector.php`:

```php
<?php
namespace CustomIconsForDivi\Builder;

use CustomIconsForDivi\Icon_Registry;

if (!defined('ABSPATH')) {
    exit;
}

class Icon_Picker_Injector {

    private $registry;

    public function __construct(Icon_Registry $registry) {
        $this->registry = $registry;
    }

    public function init() {
        add_action('divi_visual_builder_assets_before_enqueue_scripts', array($this, 'enqueue_in_builder_iframe'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_in_top_level_ui'));
    }

    public function enqueue_in_builder_iframe() {
        $this->enqueue('cifd-icon-picker-vb', array(array('divi-vendor-wp-hooks', 'wp-hooks')));
    }

    public function enqueue_in_top_level_ui() {
        $this->enqueue('cifd-icon-picker-ui', array());
    }

    private function enqueue($handle, $extra_deps) {
        if (!$this->is_divi5_builder_active()) {
            return;
        }

        $icons = $this->icons_for_js();
        if (empty($icons)) {
            return;
        }

        wp_register_script($handle, CIFD_URL . 'includes/builder/assets/icon-picker.js', array_merge(array('jquery'), $extra_deps), CIFD_VERSION, true);
        wp_localize_script($handle, 'cifdBuilderIcons', $icons);
        wp_enqueue_script($handle);
    }

    private function icons_for_js() {
        $icons = array();
        foreach ($this->registry->all() as $icon) {
            $entity = $this->registry->codepoint_entity_for($icon['id']);
            $icons[] = array(
                'id'             => $icon['id'],
                'name'           => $icon['name'],
                'url'            => $icon['url'],
                'unicode'        => $entity,
                'decodedUnicode' => html_entity_decode($entity, ENT_QUOTES, 'UTF-8'),
                'searchTerms'    => 'custom icon ' . $icon['name'],
                'styles'         => array('divi', 'solid'),
                'fontWeight'     => 400,
            );
        }
        return $icons;
    }

    private function is_divi5_builder_active() {
        if (!function_exists('et_builder_d5_enabled') || !et_builder_d5_enabled()) {
            return false;
        }
        if (!function_exists('et_core_is_fb_enabled') || !et_core_is_fb_enabled()) {
            return false;
        }
        return true;
    }
}
```

- [ ] **Step 2: Verify syntax and commit**

Run: `php -l includes/builder/class-icon-picker-injector.php`
Expected: `No syntax errors detected`.

```bash
git add includes/builder/class-icon-picker-injector.php
git commit -m "Add Icon_Picker_Injector: enqueue builder-side icon data"
```

---

### Task 8: Builder icon-picker.js

**Files:**
- Create: `includes/builder/assets/icon-picker.js`

**Interfaces:**
- Consumes: `window.cifdBuilderIcons` (array shape from Task 7).
- Produces: registers icons into Divi's `iconList` JS hook (`vendor.wp.hooks` / `wp.hooks`) and patches
  `<img>` thumbnails onto `.et-vb-icon-picker-list button`, `.et-pb-icon`, and `.et_pb_icon_wrap` elements
  whenever their text/attribute content matches one of our custom icon codepoints.

- [ ] **Step 1: Write the JS**

Create `includes/builder/assets/icon-picker.js`:

```js
(function () {
    'use strict';

    var icons = window.cifdBuilderIcons || [];
    if (!icons.length) {
        return;
    }

    var byValue = {};
    icons.forEach(function (icon) {
        byValue[icon.decodedUnicode] = icon;
        byValue[icon.unicode] = icon;
    });

    function findIconForText(text) {
        text = (text || '').trim();
        return byValue[text] || null;
    }

    function applyThumb(el, url) {
        var img = el.querySelector('img.cifd-icon-img');
        if (!img) {
            img = document.createElement('img');
            img.className = 'cifd-icon-img';
            img.alt = '';
            img.style.position = 'absolute';
            img.style.left = '50%';
            img.style.top = '50%';
            img.style.transform = 'translate(-50%, -50%)';
            img.style.width = '70%';
            img.style.height = '70%';
            img.style.objectFit = 'contain';
            el.style.position = 'relative';
            el.appendChild(img);
        }
        if (img.getAttribute('src') !== url) {
            img.src = url;
        }
    }

    function patchPickerButtons(root) {
        var buttons = root.querySelectorAll('.et-vb-icon-picker-list button');
        buttons.forEach(function (btn) {
            var icon = findIconForText(btn.textContent);
            if (icon) {
                applyThumb(btn, icon.url);
            }
        });
    }

    function patchPreviewIcons(root) {
        var els = root.querySelectorAll('.et-pb-icon, .et_pb_icon_wrap');
        els.forEach(function (el) {
            var icon = findIconForText(el.textContent) || findIconForText(el.getAttribute('data-icon'));
            if (!icon) {
                if (el.classList.contains('cifd-custom-icon')) {
                    var stale = el.querySelector('img.cifd-icon-img');
                    if (stale) {
                        stale.remove();
                    }
                    el.classList.remove('cifd-custom-icon');
                }
                return;
            }
            el.classList.add('cifd-custom-icon');
            applyThumb(el, icon.url);
        });
    }

    function patch(root) {
        root = root || document;
        patchPickerButtons(root);
        patchPreviewIcons(root);
    }

    function registerIconListFilter() {
        var hooks = (window.vendor && window.vendor.wp && window.vendor.wp.hooks) || (window.wp && window.wp.hooks);
        if (!hooks || !hooks.addFilter) {
            window.setTimeout(registerIconListFilter, 100);
            return;
        }
        if (window.__cifdIconListFilterAdded) {
            return;
        }
        window.__cifdIconListFilterAdded = true;

        hooks.addFilter('iconList', 'customIconsForDivi.customIcons', function (existing) {
            return (icons || []).concat(existing || []);
        });
    }

    function watchForChanges() {
        if (!window.MutationObserver || window.__cifdIconObserver) {
            return;
        }
        var pending = false;
        var schedule = function () {
            if (pending) {
                return;
            }
            pending = true;
            window.requestAnimationFrame(function () {
                pending = false;
                patch(document);
            });
        };

        window.__cifdIconObserver = new MutationObserver(schedule);
        window.__cifdIconObserver.observe(document.documentElement, {
            childList: true,
            subtree: true,
            characterData: true
        });
    }

    function boot() {
        registerIconListFilter();
        watchForChanges();
        patch(document);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
        boot();
    }
})();
```

- [ ] **Step 2: Commit**

```bash
git add includes/builder/assets/icon-picker.js
git commit -m "Add builder-side icon injection: iconList hook + thumbnail patching"
```

---

### Task 9: Frontend Icon_Renderer (render_block filters + swap script)

**Files:**
- Create: `includes/frontend/class-icon-renderer.php`

**Interfaces:**
- Consumes: `Icon_Registry` (Task 2) — `all()`, `codepoint_entity_for()`.
- Produces: `CustomIconsForDivi\Frontend\Icon_Renderer` with constructor
  `__construct(Icon_Registry $registry)` and method `init(): void`.

- [ ] **Step 1: Write the class**

Create `includes/frontend/class-icon-renderer.php`:

```php
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
```

- [ ] **Step 2: Verify syntax and commit**

Run: `php -l includes/frontend/class-icon-renderer.php`
Expected: `No syntax errors detected`.

```bash
git add includes/frontend/class-icon-renderer.php
git commit -m "Add Icon_Renderer: render_block filters + front-end glyph swap"
```

---

### Task 10: Wire-up, symlink, end-to-end verification

**Files:**
- Modify: `custom-icons-for-divi.php` (append requires + `bootstrap()`)

**Interfaces:**
- Consumes: every class produced by Tasks 2, 3, 4, 7, 9.

- [ ] **Step 1: Append the wiring to the bootstrap file**

Append to `custom-icons-for-divi.php` (after the `define()` block from Task 1):

```php

require_once CIFD_DIR . 'includes/class-icon-registry.php';
require_once CIFD_DIR . 'includes/class-icon-sanitizer.php';
require_once CIFD_DIR . 'includes/admin/class-icon-library-page.php';
require_once CIFD_DIR . 'includes/builder/class-icon-picker-injector.php';
require_once CIFD_DIR . 'includes/frontend/class-icon-renderer.php';

function bootstrap() {
    $registry = new Icon_Registry(CIFD_OPTION_ICONS, CIFD_OPTION_NEXT_ID, CIFD_CODEPOINT_BASE);

    if (is_admin()) {
        (new Admin\Icon_Library_Page($registry, new Icon_Sanitizer()))->init();
    }

    (new Builder\Icon_Picker_Injector($registry))->init();
    (new Frontend\Icon_Renderer($registry))->init();
}
add_action('plugins_loaded', __NAMESPACE__ . '\\bootstrap');
```

- [ ] **Step 2: Verify syntax and run the full test suite**

Run: `php -l custom-icons-for-divi.php && php tests/icon-registry-test.php && php tests/icon-sanitizer-test.php`
Expected: no syntax errors, both test files report `0 failed`.

- [ ] **Step 3: Symlink into the local test site**

```bash
ln -s "/Users/macphersondesigns/Wordpress Development/Plugins/custom-icons-for-divi" \
      "/Users/macphersondesigns/Local Sites/app-development/app/public/wp-content/plugins/custom-icons-for-divi"
```

Note: Divi 5 is not currently installed on `app-development.local` (only the `am-convention` plugin and
default WP themes are present). Install/activate the Divi theme (and Divi 5's Visual Builder) on that site
before continuing — this plugin has no effect without it.

- [ ] **Step 4: Manual end-to-end verification**

Using the local WP MCP tools available in this session (or the site's wp-admin directly):

1. Activate "Custom Icons for Divi" on `app-development.local`.
2. Go to the "Custom Icons" admin page, click "Add Icon", upload one SVG and one PNG test icon, name them.
3. Confirm both appear in the grid; rename one; delete one and confirm it's removed.
4. Re-add an icon. Open the Divi Visual Builder on a test page, add an Icon module, open its icon picker,
   confirm the custom icon appears and its thumbnail renders as the uploaded image (not a blank glyph).
5. Repeat the picker check for a Blurb, a Button, a Toggle, and an Icon List item.
6. Save the page, view it on the front-end, and confirm each module renders the custom icon image (not a
   missing glyph or the wrong icon).
7. Delete the icon from the admin screen, reload the already-published page, and confirm it degrades to
   showing nothing in that spot rather than breaking the page.

- [ ] **Step 5: Commit**

```bash
git add custom-icons-for-divi.php
git commit -m "Wire up plugin bootstrap: require and instantiate all components"
```
