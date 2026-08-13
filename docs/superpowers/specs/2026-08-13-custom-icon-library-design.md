# Custom Icons for Divi — Design Spec

Date: 2026-08-13
Status: Approved for implementation planning

## Summary

A standalone WordPress plugin that lets a site owner upload their own icon
images (SVG/PNG/JPG/WebP) through a real media-library-backed admin screen,
and use them anywhere Divi 5's native icon picker appears — Icon module,
Blurb, Button, Toggle, and Icon List — exactly like a built-in Divi icon.

## Background / prior art

Divi Booster (`/Users/macphersondesigns/Desktop/divi-booster`) ships a
feature (internally "DB014", `core/fixes/014-add-new-icons/`) that solves
this same problem for both Divi 4 and Divi 5. Its Divi-5 code path proves a
working technique:

1. Register custom icons into Divi 5's client-side `iconList` filter hook
   (`window.vendor.wp.hooks`) so they appear in the native icon picker grid.
2. Assign each custom icon a fake unicode codepoint so Divi's own data
   model (which only knows how to store a codepoint per icon field) can
   represent it without any core changes.
3. Use a `MutationObserver` to patch a real `<img>` thumbnail over the
   picker's font-glyph button, and over live builder previews.
4. Use `render_block` filters (one per target block type) to detect the
   reserved codepoint in rendered HTML and inject markup a front-end
   script/CSS pass can find and swap for the real image.

This plugin reuses that technique (rewritten, not copy-pasted) but:

- Drops all Divi 4 / legacy-compat code paths — Divi 5 native only.
- Replaces DB014's storage (numbered text fields: `url0`, `url1`, ...,
  `urlmax`) with a real icon-library admin screen backed by the WP Media
  Library.
- Uses the Unicode Private Use Area for codepoint assignment instead of an
  arbitrary `800 + index` offset, and ties codepoints to a stable,
  never-reused icon id rather than array position, so deleting an icon
  can't shift another icon's codepoint.

## Goals

- Upload icons via the WP Media Library (SVG with server-side sanitization,
  plus PNG/JPG/WebP).
- Name, reorder, and delete icons from a dedicated admin screen.
- Selected icons appear and function inside Divi 5's native icon picker for:
  Icon module, Blurb, Button, Toggle, Icon List.
- Icons render correctly in the Visual Builder preview and on the published
  front-end.
- Deleting an icon that's still referenced by a module degrades gracefully
  (icon disappears; page doesn't break).

## Non-goals (v1)

- Divi 4 / Divi-5-compat-mode support.
- Real webfont generation (compiling uploaded SVGs into an actual `.woff`
  with true codepoints). Noted as a possible future upgrade if the
  glyph-swap approach proves too fragile against Divi core updates, but out
  of scope for v1.
- Icon categories/tagging/search beyond a simple named list.
- Multisite-specific icon libraries (v1 uses a single site-wide option).

## Architecture

```
custom-icons-for-divi/
  custom-icons-for-divi.php        Bootstrap: constants, autoload, init hooks
  uninstall.php                    Removes the plugin's own option(s) only
  includes/
    class-icon-registry.php        Data access: CRUD over the icon list option
    admin/
      class-icon-library-page.php  Admin screen: render + AJAX handlers
      assets/
        icon-library.css
        icon-library.js            wp.media integration, add/rename/delete/reorder
    builder/
      class-icon-picker-injector.php  Enqueues inline JS for VB + admin UI frame
      assets/
        icon-picker.js              Ported/trimmed DB014 injection logic
    frontend/
      class-icon-renderer.php      render_block filters per target block
  tests/
    IconRegistryTest.php           PHPUnit coverage for the registry class
```

Each class has one responsibility and depends only on the Icon Registry for
data — the admin screen, builder injector, and front-end renderer never talk
to each other directly.

## Data model

Single `wp_option`, e.g. `cifd_icons`, holding:

```php
array(
    array(
        'id'   => 1,              // stable, monotonically increasing, never reused
        'name' => 'Handshake',
        'url'  => 'https://.../handshake.svg',
        'mime' => 'image/svg+xml',
    ),
    // ...
)
```

A separate `cifd_next_id` counter (or a `max(id) + 1` computed on write)
ensures ids are never reused after deletion. Codepoint for an icon is
derived deterministically from its id: `0xE000 + id`, staying inside the
Unicode Private Use Area (U+E000–U+F8FF, ~6400 slots) to avoid any
collision with real Divi icon glyphs.

Display order (for the admin grid and picker) is a separate `order` array
of ids, so reordering doesn't touch ids/codepoints.

## Admin Icon Library screen

- New admin page (under the Divi menu, or a top-level "Custom Icons" menu
  item — decided during implementation based on what reads cleanest).
- Grid of icon cards: thumbnail, name (inline-editable text field), delete
  button, drag handle for reordering.
- "Add Icon" button opens the standard `wp.media` frame, filtered to
  image/svg+xml + image/png + image/jpeg + image/webp.
- Save actions go through `wp_ajax_*` handlers with nonce + capability
  (`manage_options` or a dedicated capability) checks; the option is
  updated atomically server-side.
- SVG uploads are sanitized server-side (strip `<script>`, event handler
  attributes, external references) before being accepted — SVG is the one
  format here that can carry executable content.

## Builder integration

- `class-icon-picker-injector.php` enqueues inline JS in two contexts,
  mirroring DB014's split: inside the Visual Builder app iframe
  (`divi_visual_builder_assets_before_enqueue_scripts`) and in the
  top-level admin/editor UI (`wp_enqueue_scripts`, gated to when Divi 5's
  builder is active).
- JS registers all icons (id, name, computed codepoint, url) into Divi's
  `iconList` hook via `wp.hooks.addFilter`.
- A `MutationObserver` watches the icon picker's DOM and swaps each custom
  icon's rendered glyph button for a real `<img>` thumbnail; a second
  observer does the same for live module previews in the canvas.
- Ported from DB014, trimmed to only the Divi 5 code paths (no
  `et_fb_enabled` / Divi-4 shortcode branches).

## Front-end rendering

- `class-icon-renderer.php` registers `render_block` filters for
  `divi/icon`, `divi/blurb`, generic button blocks, `divi/toggle`, and
  `divi/icon-list-item`.
- Each filter inspects the parsed block's attributes for our reserved
  codepoint range, and — if found — ensures the rendered HTML carries a
  `data-icon` attribute / entity the front-end script can locate.
- A small front-end script (footer-enqueued) + accompanying CSS finds those
  markers and swaps the glyph for the real `<img>`, sized to `1em` to track
  Divi's own icon sizing wherever possible, matching DB014's approach.

## Error handling

- Upload: reject disallowed mime types outright; SVGs are sanitized, not
  just mime-checked.
- Referenced-but-deleted icon: renders as empty/hidden rather than a broken
  image or fatal error.
- Missing/corrupted codepoint mapping: skipped silently; only logged via
  `error_log` when `WP_DEBUG` is true.
- Uninstalling the plugin removes only its own option data — never touches
  Media Library attachments (those are the user's uploaded files).

## Accessibility

The admin Icon Library screen is user-facing UI. Per this workspace's
accessibility workflow, its implementation (grid markup, upload button,
inline name editing, delete/reorder controls, focus management after
add/delete) will be reviewed via the accessibility-lead workflow before
being considered complete — covering keyboard operability, labeling, and
focus handling for the drag-reorder and delete interactions in particular.

## Testing plan

- **Local site**: symlink the plugin into
  `/Users/macphersondesigns/Local Sites/app-development/app/public/wp-content/plugins/`
  (same pattern already used for the `am-convention` plugin in that site).
  Divi isn't currently installed on that site — installing Divi 5 there is
  a prerequisite before builder-integration testing can start.
- **Manual**: upload SVG + raster test icons; verify each appears and
  functions in the Icon module, Blurb, Button, Toggle, and Icon List
  pickers; verify Visual Builder preview; verify published front-end
  render; verify deleting an in-use icon degrades gracefully.
- **PHPUnit**: `Icon_Registry` CRUD and id/codepoint-stability logic (pure
  PHP, no WordPress/Divi runtime dependency required beyond option
  get/update, which can be mocked or run under `wp-phpunit`).

## Open questions / future work

- Real webfont generation (Approach 3 from brainstorming) as a v2 if the
  glyph-swap technique proves fragile against future Divi core updates.
- Icon categories/search if the library grows large.
- Whether the admin screen lives under Divi's own menu or as a top-level
  menu item — deferred to implementation, low-risk to change later.
