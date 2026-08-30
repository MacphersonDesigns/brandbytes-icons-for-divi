# Native Divi icon-picker bridge

Status: Active as of the 2026-08-30 Phase 0 spike (see below). Superseded the
"Archived" status this document previously carried under 0.3.0.
First fixture: About page, default Blurb module, `Image & Icon` settings group

## Phase 0 spike result (2026-08-30, Divi 5.11.1): interception mechanism found

Live-verified in the actual Divi 5.11.1 Visual Builder on the About-page Blurb
fixture (not just read from source): Divi's field-library resolves every
field's rendered component through a filterable pipeline —

```js
mN = applyFilters("divi.fieldLibrary.fieldComponentMap", hN)   // base map, one-time
getFieldComponent = (fieldName) => {
  const resolved = (fieldName in mN) ? mN[fieldName].component : fallback;
  return applyFilters("divi.fieldLibrary.getFieldComponent", resolved, fieldName);
};
```

`window.vendor.wp.hooks` (Divi's own vendored copy of `@wordpress/hooks`,
registered under the script handle `divi-vendor-wp-hooks`, distinct from
WordPress core's `wp-hooks`) is exposed as a real global inside the Builder's
`et-vb-app-frame` iframe. A plugin script running in that same frame can call:

```js
window.vendor.wp.hooks.addFilter(
  'divi.fieldLibrary.getFieldComponent',
  'custom-icons-for-divi/native-bridge',
  (Component, fieldName) => fieldName !== 'divi/icon-picker'
    ? Component
    : WrapWithCustomIconsTab(Component)
);
```

This was proven live, not just in source: registering this filter from outside
Divi's own bundle (via the DevTools console, simulating a plugin script) and
then opening Blurb's Content → Image & Icon panel caused our wrapper component
to actually render in place of the native picker — confirmed visually (a
marker rendered inside the real sidebar, screenshotted) and via a hit-counter
proving the filter fires exactly when the `divi/icon-picker` field mounts,
with the original native picker component passed through as an argument
(matching the `NativePicker` prop shape `native-icon-picker-bridge.tsx`
already assumed).

Two important implications:

1. **No race condition to lose.** `getFieldComponent` is a live filter call,
   not a one-time-at-bundle-eval registration like `fieldComponentMap`. Our
   `addFilter` call just needs to have executed before the user opens that
   field in the sidebar — which is well after any script-loading race with
   `field-library.js`'s own evaluation.
2. **We get the original component for free.** Because our filter receives
   the already-resolved native component as its first argument, wrapping it
   (rendering it inside our own component alongside a "Custom Icons" tab) is
   direct composition, not reconstruction from scratch.

Per-module note: this specific field name (`divi/icon-picker`) is Blurb's.
Icon, Icon List Item, Button, and Toggle need their own field name(s)
confirmed the same way (read from the module's own `module.json`, field
`component.name`) before repeating this pattern — not assumed to be identical.

## Outcome

The product owner returned the active implementation to the supported Custom
Icon module after the Blurb bridge failed to maintain reliable Builder canvas
parity. Version 0.3.0 does not register the native picker wrapper, Blurb canvas
filter, or native-module PHP renderer. The material below is retained as
research for a future, separately versioned compatibility package.

The current development fixture has valid saved markup and the 0.2.5 bundle
contains the cross-document canvas lookup needed by Divi's split builder
windows. A fresh Builder session is still required for manual visual
acceptance; automated tests cannot certify the user's already-open iframe.

Provider state is synchronized from the controlled Divi value, while native
and custom selection callbacks also record the user's active provider
immediately. This prevents a native icon selection from visually updating the
canvas while the sidebar reopens on the stale Custom Icons tab.

The catalog data is injected into both Divi Builder windows. This is required
because the app window renders the picker, while the top window runs the Blurb
canvas hook and must resolve the same UUID to its current SVG URL.

The canvas adapter reads `moduleAttrs` and `runtimeModuleAttrs` from the React
element passed to `divi.module.elements.render`, as verified in the installed
Divi 5.6.2 source. It retains the renderer instance as a secondary source and
recognizes Divi's canvas class name `et_pb_blurb`.

## Current implementation slice

The first Blurb adapter is now implemented behind the existing experimental
gate:

- The plugin wraps Divi's resolved `divi/icon-picker` field rather than
  rewriting picker DOM after render.
- Native Divi values continue through the original picker. When the catalog is
  available, a sibling **Custom Icons** provider tab opens the plugin's
  searchable, keyboard-safe thumbnail picker.
- Custom selections are stored at Blurb's existing icon path with
  `{"type":"cifd","uuid":"..."}`. The UUID is the only durable identity;
  the asset URL is resolved from the attachment on each render.
- Divi's PHP renderer still drops unknown font values, so the scoped Blurb
  render adapter recreates the known image wrapper and emits a
  `currentColor` CSS-mask glyph using the resolved HTTPS URL.

Automated checks pass, including a saved About-page fixture probe. The
remaining acceptance work is an authenticated Visual Builder interaction test
for tab switching, design controls, and reload persistence.

### Builder canvas visibility correction

The saved Blurb markup can be correct while its canvas glyph remains invisible
if the mask CSS is loaded only in the settings app or only through `wp_head`.
The builder stylesheet now loads in both Divi windows, and the shared module
CSS contains the `cifd-custom-icon-glyph` mask rule. This keeps the provider
controls and canvas preview on the same asset/style contract.

The Builder canvas also has a separate React render path. The bridge now wraps
Divi's `divi.module.elements.render` hook for Blurb only, reads the live
`runtimeAttrs` value, and attaches/removes the selected glyph inside that
module's own render tree. This avoids a document-wide observer and lets the
preview respond immediately when the sidebar selection changes.

The bridge bundle is registered in both Divi Builder windows. The picker is
resolved in the app/sidebar window, while the canvas element hook runs in the
top window; loading only one side produces a working sidebar with an unchanged
canvas.

When an editor opens a native Divi icon picker in a supported module, the
picker offers a recognizable **Custom Icons** provider/filter alongside Divi's
normal icon choices. Search, selection, responsive settings, and the module's
existing icon design controls continue to work in place.

This is a bonus integration layer. The supported Custom Icon module remains the
stable production feature if Divi changes its private picker contracts.

## Target editor experience

The Blurb control shown in the audit fixture currently presents:

```text
Image & Icon
  Use Icon: On
  Icon
    [ Search Icons                 ] [ Filter ]
    [ native icon grid                         ]
```

The desired extension is:

```text
Filter
  All Icons
  Divi
  Font Awesome
  Custom Icons

Custom Icons selected
  [ Search custom icons           ] [ Filter ]
  [ client-library icon grid                  ]
```

If Divi's current filter model supports named subfilters safely, active library
names may appear below Custom Icons. If it supports only flat filter entries,
the bridge exposes one Custom Icons entry and search covers all active client
libraries. We will not simulate nested menus with brittle document-wide DOM
rewriting.

## Why this is not the SVG module

- An SVG module places an individual vector asset.
- A custom icon has a stable UUID, name, keywords, library, render mode, and
  reusable relationship to multiple module instances.
- Icon-aware modules provide specific color, size, placement, hover, and state
  behavior that generic SVG placement does not communicate.
- Editors recognize the artwork as an icon because it lives in the module's
  existing Icon field and picker.

## Divi 5 compatibility contract

The audited Divi 5.6.2 JavaScript bundle applies internal catalog/type/filter
hooks while creating a memoized icon list. The bridge therefore needs a single
version-specific adapter that:

1. verifies the tested Divi version and required icon-library script handle;
2. registers the custom catalog, icon type, and Custom Icons filter before the
   bundle evaluates;
3. stores a stable plugin-owned selection value without relying on button text;
4. provides builder preview output for the selected record;
5. gives each supported module a PHP adapter that reads its exact saved
   attribute and renders the same icon; and
6. suppresses the Custom Icons filter and shows an admin compatibility notice
   if any required contract fails.

Relevant internal hooks observed in the installed version:

- `iconList`
- `divi.iconLibrary.fontIconTypes`
- `divi.iconLibrary.fontIconFilters`
- `divi_icon_library_font_icon_types` in PHP

These are private Divi contracts, not a documented public provider API. Their
names belong only inside the compatibility package.

### What the visible Filter menu actually loads from

The live `window.divi.iconLibrary` export contains 1,989 records. Each record
has `name`, `searchTerms`, `unicode`, `decodedUnicode`, `fontWeight`, and a
`styles` array. The public runtime arrays are:

```text
fontIconTypes   = [divi, fa]
fontIconFilters = [solid, line, divi, fa]
```

The catalog, type array, and filter-slug array are passed through the hooks
listed above when the icon-library bundle evaluates. However, Divi 5.6.2's
field-library bundle separately hardcodes the visible dropdown map and labels:

```text
solid -> Solid Icons
line  -> Line Icons
divi  -> Divi Icons
fa    -> Font Awesome
```

This means loading a fifth slug is not enough to create a usable **Custom
Icons** menu entry; the UI attempts to read a label from a map that has no
custom key. The compatibility adapter therefore needs both early catalog/type
registration and a narrowly scoped, version-tested wrapper around Divi's icon
field component. Rewriting dropdown DOM after render is rejected because it
would repeat the timing and accessibility problems of the old prototype.

## Per-module adapters

| Order | Module | Saved contract | Required proof |
| --- | --- | --- | --- |
| 1 | Blurb | `imageIcon.innerContent` | Custom Icons filter, search, select, save/reload, builder preview, server render, placement, color, size, hover. |
| 2 | Icon | `icon.innerContent` | Preserve link, alignment, responsive design, and front-end output. |
| 3 | Button | decoration icon fields | Preserve button name, icon placement, hover, and link semantics. |
| 4 | Toggle | open/closed icon fields | Resolve and render both states independently. |
| 5 | Icon List item | `icon.innerContent` | Resolve each list item independently without changing list semantics. |

An adapter is not enabled merely because its picker shows the icon. Builder
preview and PHP/front-end rendering must pass before the module enters the
supported matrix.

## Accessibility contract

- Custom Icons is exposed with a textual accessible name and a selected state.
- Filtering updates the result count through a non-disruptive status message.
- Custom thumbnails are decorative inside icon buttons; each button's name
  comes from the icon record.
- Existing Divi picker keyboard behavior is preserved rather than overridden.
- If the bridge cannot preserve focus, names, or selection semantics on a Divi
  version, it fails closed for that version.

## Proof-of-concept gate

On the About-page Blurb fixture:

1. Open `Image & Icon`, enable **Use Icon**, and open Filter.
2. Confirm **Custom Icons** appears exactly once.
3. Select it and search for the imported LED Party icon.
4. Select the icon and confirm immediate builder preview.
5. Change color, size, placement, hover, and responsive settings.
6. Save, leave the builder, reload it, and confirm selection and preview.
7. Verify server-rendered and published markup without the global glyph scanner.
8. Repeat with the bridge disabled and an intentionally unsupported Divi
   version to prove the Custom Icon module continues working.
