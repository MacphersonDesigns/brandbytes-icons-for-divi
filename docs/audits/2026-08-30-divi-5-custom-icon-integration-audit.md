# Divi 5 custom icon integration audit

Date: 2026-08-30  
Audited runtime: WordPress 7.1, PHP 8.2.29, Divi 5.6.2  
Plugin version: 0.2.0  
Fixture: About page, post ID 1002, default `divi/icon` module  
Status: Complete; implementation roadmap proposed

## Implementation update — version 0.2.0

The first supported-module foundation is now implemented behind a development
feature gate. The work does not change the audit's findings about the older
native picker bridge.

- `cifd/custom-icon` registers through Divi's dependency-tree hook with
  generated metadata, a React Visual Builder definition, and a PHP renderer.
- Icon records now receive stable UUIDs and WordPress attachment relationships;
  the audited URL-only record migrated without error.
- A dedicated resolver converts the saved UUID into the attachment's current
  URL and retains the old URL only as a controlled migration fallback.
- The live Divi 5.6.2 module chooser lists **Custom Icon** as its own module.
  The first browser pass found a JSX-runtime mismatch; switching to the classic
  React transform used by Elegant Themes' example removed the tile error.
- The module's Content panel now renders a searchable thumbnail picker from an
  authenticated UUID catalog. Selecting LED Party updated Divi's edit-post
  store, exposed an accessible pressed state, and updated the canvas preview.
- Search/no-results and Escape-to-clear were verified in the live panel. An
  unsaved PHP block-render probe returned the module class, selected UUID, and
  current HTTPS attachment URL.
- The option `cifd_enable_experimental_custom_icon_module` was returned to
  `false` after verification. Named libraries, safe ingestion, and the full
  save/reload/published-page fixture remain release gates.

## Executive decision

## Implementation update — Blurb bridge foundation

The first native bridge slice is now implemented behind the experimental gate.
Divi's resolved `divi/icon-picker` component is wrapped at the field-library
hook, preserving native values and adding a **Custom Icons** provider tab when
the authenticated catalog is nonempty. Blurb custom selections use the
existing `imageIcon.innerContent.desktop.value.icon` path with a plugin-owned
`type` and UUID.

Because Divi's PHP renderer removes unknown font values before output, the
plugin's scoped Blurb adapter reads the parsed block reference and recreates
the known image wrapper with a `currentColor` mask glyph. The saved About-page
fixture now renders the selected UUID and current HTTPS attachment URL through
`do_blocks()`. Builder tab interaction, design-control parity, and reload
persistence remain the next live acceptance gate.

The first Builder-canvas failure showed why the two render paths must be tested
separately: published PHP output was correct while the client-rendered Blurb
canvas omitted the icon. The bridge now wraps Divi's
`divi.module.elements.render` hook for Blurb only, reads `runtimeAttrs`, and
attaches the mask glyph within that module tree. Its CSS is available in both
Builder windows as well as the published stylesheet.

The next live failure isolated the remaining window boundary: the provider
bundle had been app-window-only even though the canvas render hook executes in
the top window. The package is now registered in both windows, and the adapter
accepts Divi's runtime, module-attribute, or plain attribute containers.

The plugin proves useful pieces—library administration, a stable icon identity
concept, a builder experiment, and renderer smoke tests—but its current native
picker technique is not production-capable in Divi 5.6.2.

The recommended product is a hybrid:

1. A supported Divi 5 **Custom Icon module** that offers the same practical
   design surface as Divi's default Icon module.
2. A separate **native-picker compatibility bridge** for core Icon, Blurb,
   Button, Toggle, and Icon List modules. It must be pinned to tested Divi
   versions and fail closed when internal contracts change.

The native bridge's intended editor-facing affordance is a **Custom Icons**
provider/filter inside the existing Search Icons control—not a separate SVG or
media field. Blurb is the first proof because its `Image & Icon` group exposes
the exact search, filter, grid, and icon design workflow the product should
preserve.

This order gives clients a dependable module while preserving a path toward the
native experience they want. The default Icon module on About is the permanent
integration fixture for the native bridge.

## Scope and evidence

The audit covered every tracked PHP, JavaScript, CSS, test, and documentation
file; the authenticated WordPress/Divi MCP abilities; the rendered About page;
the live Visual Builder iframe and icon picker; Divi's installed JavaScript and
PHP icon-library code; the plugin admin screen; and the current smoke tests.

Important live evidence:

- Divi setup reports 107 registered modules and no setup issue.
- About saves a default `divi/icon` block with builder version 5.6.2.
- Server output is the normal `.et_pb_icon_wrap > .et-pb-icon` structure.
- Divi's runtime icon catalog contains 1,989 core items and zero plugin items.
- The native filter list is `[solid, line, divi, fa]`, but the visible Solid
  Icons, Line Icons, Divi Icons, and Font Awesome labels are hardcoded in the
  field-library bundle rather than derived from a hooked provider registry.
- Applying the `iconList` hook manually after load returns the custom item. This
  proves the callback is registered but arrives after Divi memoizes its catalog.
- A picker button has an empty `textContent`; its value is held in attributes
  such as `data-icon` and `data-unicode`. The plugin currently reads only
  `textContent` for picker thumbnails.
- The saved LED Party icon record uses an absolute HTTP URL while the builder
  is HTTPS. This is direct evidence that URL storage is not migration-safe.

## How Divi 5 currently handles icons

## Direct Divi source inspection — 2026-08-30

The local Divi 5.6.2 installation is readable at:

```text
/Users/macphersondesigns/Local Sites/app-development/app/public/wp-content/themes/Divi
```

This source-level inspection confirms the runtime findings:

- `includes/builder-5/server/Packages/IconLibrary/IconFont/Utils.php` applies
  the PHP filter `divi_icon_library_font_icon_types`, then validates font icons
  against Divi's bundled `iconList.json`.
- `includes/builder-5/visual-builder/build/icon-library.js` applies the
  JavaScript filters `divi.iconLibrary.fontIconTypes` and
  `divi.iconLibrary.fontIconFilters` while building the exported catalog.
- `includes/builder-5/visual-builder/build/field-library.js` owns the native
  `divi/icon-picker` field. Its visible filter labels are a local map with only
  `solid`, `line`, `divi`, and `fa` keys.
- The generated Blurb metadata at
  `includes/builder-5/visual-builder/packages/module-library/src/components/blurb/module.json`
  binds the native control to `divi/icon-picker` at
  `imageIcon.innerContent.icon`.
- Divi's Blurb PHP renderer reads that value and calls the fixed-catalog
  `IconFont\\Utils::process_font_icon()` path, so a custom SVG cannot be made
  server-renderable by adding only a JavaScript catalog entry.

The next bridge should therefore load custom catalog data before Divi's
icon-library bundle evaluates, then wrap the native field component for the
tested Divi version so the Custom Icons label, search results, and selection
model remain coherent. Separate PHP adapters are still required for Blurb and
the other icon-aware modules.

The native picker and the server renderer are separate contracts:

```text
Divi icon-library JavaScript bundle
  -> builds and memoizes the picker catalog
  -> applies internal iconList/type/filter hooks while evaluating

Saved module attributes
  -> contain module-specific icon values

Divi PHP icon utility
  -> validates font icons against its bundled iconList.json
  -> returns no processed icon for an unknown custom glyph

Module renderer
  -> emits module-specific icon markup/data attributes
```

The installed JavaScript bundle applies `iconList`,
`divi.iconLibrary.fontIconTypes`, and `divi.iconLibrary.fontIconFilters`. The
installed PHP utility exposes `divi_icon_library_font_icon_types`, but still
looks up the glyph in its fixed catalog. A JavaScript-only provider therefore
cannot complete server rendering.

The filter screenshot exposes a real filter affordance, but it is not a fully
extensible library registry in Divi 5.6.2. The catalog/type/filter arrays are
hooked at icon-library evaluation time; the field-library dropdown separately
hardcodes four option keys and labels. A fifth custom filter therefore requires
a version-tested field wrapper as well as early catalog injection. Adding only
the slug would leave the UI without a label and is not a safe integration.

Saved attribute paths also differ by module:

| Module | Audited icon contract | Adapter responsibility |
| --- | --- | --- |
| Icon | `icon.innerContent` | Replace the icon wrapper content and retain module link/design output. |
| Blurb | `imageIcon.innerContent` | Respect `useIcon`, image/icon placement, color, and animation. |
| Button | button decoration icon fields | Preserve button text, placement, hover, responsive data, and link semantics. |
| Toggle | open and closed decoration icon fields | Render two independent icon states. |
| Icon List item | `icon.innerContent` | Resolve each item independently and retain list semantics. |

## Findings by priority

### Release blockers

| Finding | Why it matters | Required correction |
| --- | --- | --- |
| Picker filter registers after memoization | The custom icon is absent from the live picker. | Register data inline **before** Divi's icon-library bundle evaluates, through a narrowly scoped compatibility adapter. |
| Picker thumbnail lookup reads empty button text | Even a registered item cannot reliably receive its custom thumbnail. | Use the saved/data value, not visible text; avoid DOM thumbnail patching when a supported component path is available. |
| PHP rejects unknown glyphs | The current renderer scans HTML for a value Divi may already have dropped. | Parse the exact block attributes and render custom markup in a module-specific adapter. |
| Raw SVG upload is enabled globally | A privileged user can publish an unsanitized SVG through Media Library without completing the plugin workflow. A rejected or failed rewrite can leave the raw public file behind. | Sanitize before publication or create an atomically written, plugin-controlled sanitized derivative. Do not globally bless raw SVG uploads. Use a maintained sanitizer dependency and validate actual file contents. |
| Absolute icon URLs are stored | The audited HTTP record on an HTTPS site can break across protocol, domain, CDN, and migration changes. | Store attachment ID plus stable UUID; resolve the current URL at runtime. |
| AJAX failures are silent | PHP returns 4xx/5xx errors, which jQuery routes to `.fail()`, while callers implement only `.done()`. Users can believe a rejected edit was saved. | Centralize success/failure handling, visible errors, live announcements, rollback, focus recovery, and busy states. |

### High-priority engineering risks

- The picker script is emitted under two handles and relies on polling plus
  global guards. Script order is difficult to reason about and version-test.
- The global `render_block` button handler infers icon ownership from rendered
  HTML. It is not a reliable module contract.
- Regex rewriting of HTML is fragile. Prefer module-owned render output; when
  an existing tag must be adjusted, use WordPress's HTML Tag Processor.
- Front-end CSS and JavaScript are printed inline in `wp_head`/`wp_footer` for
  every page when the library is nonempty. Use versioned assets and conditionally
  enqueue only where a custom icon is present.
- `<img>` swapping discards Divi's native color and hover model. A monochrome
  CSS mask using `currentColor` keeps Divi's design controls meaningful.
- The option registry performs non-atomic read/modify/write operations and
  ignores update failures. Concurrent requests can lose changes or reuse IDs.
- `0xE000 + id` has no bound. With IDs starting at 1, only IDs 1–6,399 stay in
  the BMP Private Use Area ending at U+F8FF.
- Deleted IDs have no tombstone mapping. A previously saved glyph may become a
  missing-character box rather than reliably disappearing.
- Registry reads trust the option schema and attachment existence. Corrupt or
  stale records can produce warnings or broken images.
- There is no schema version, migration runner, Divi compatibility notice,
  library grouping, import/export contract, or configurable management
  capability.
- JavaScript action labels, status messages, and errors are partly hardcoded in
  English instead of passed through WordPress internationalization.

### Accessibility findings

The existing screen uses native buttons, keyboard move controls, decorative
thumbnail alt text, a polite status region, and deliberate focus recovery after
deletion. Those are good foundations. The release blockers are:

- add, rename, delete, and reorder failures bypass current feedback;
- the icon-name input has only a visually hidden label and no required-state
  indication;
- move success is announced before persistence, pointer sorting has no result
  announcement, and repeated identical messages may not create a new live
  region mutation;
- reaching a reorder boundary moves focus to the destructive Delete control;
- asynchronous actions expose no busy state and can be submitted repeatedly;
- `window.prompt` and `window.confirm` do not provide the predictable,
  field-associated validation experience needed for this workflow.

Replace the prompt with a labeled inline form or accessible WordPress dialog.
On persistence failure, restore the old value/order, announce the error, and
return focus to the affected control. At a movement boundary, focus the enabled
opposite move button or the name field—never Delete.

## Recommended modular architecture

```text
CustomIconsForDivi
  Domain/
    Icon, IconLibrary, IconReference, LibraryRepository
  Media/
    UploadPolicy, SvgSanitizer, SanitizedAssetStore
  DiviModule/
    CustomIconModule (official Divi 5 extension)
  DiviCompatibility/
    ContractDetector, PickerProvider
    IconAdapter, BlurbAdapter, ButtonAdapter
    ToggleAdapter, IconListItemAdapter
  Rendering/
    MaskRenderer, OriginalColorRenderer
  Admin/
    LibraryPage, accessible editor components, REST/AJAX controller
  Tests/
    unit, WordPress integration, Divi fixtures, browser verification
```

Each feature folder should own a README describing its contracts and tests.
The bootstrap should compose modules; it should not contain feature logic.

### Supported Custom Icon module

Mirror the core module where it benefits clients:

- Divi-style visual picker with thumbnail grid, search, named-library filters,
  selected state, and keyboard-safe focus behavior;
- link, alignment, icon color and size;
- responsive and hover values;
- spacing, border, shadow, filters, transform, animation, position, visibility,
  conditions, interactions, and advanced attributes where the Divi extension
  API supports them;
- monochrome mask and original-color image render modes;
- matching Visual Builder and PHP render output.

The picker catalog is populated by individual SVG uploads, multiple-SVG
uploads, and validated SVG-set ZIP imports. Set imports use an optional
versioned manifest for names, keywords, categories, and stable external IDs;
generic SVG ZIP is the initial portable format, with vendor-specific adapters
deferred until real client packages are available.

The default should be decorative output. If an unaccompanied icon conveys
meaning, the custom module may expose a deliberate accessible-label setting.
Icons inside already labeled controls must not duplicate the control's name.

### Native picker compatibility bridge

The bridge may use the internal hooks only inside a versioned adapter:

1. Detect the tested Divi version and required script handle.
2. Add the icon provider inline before the icon-library bundle evaluates.
3. Register a plugin-owned icon type and searchable catalog values.
4. Store a stable plugin value; never infer identity from a button's text.
5. Read the exact saved attribute path in each PHP adapter.
6. Emit CSS-mask markup using Divi's generated classes and `currentColor`.
7. Provide builder preview parity without a document-wide observer if
   possible; if a narrow observer is unavoidable, scope and regression-test it.
8. Disable only the bridge and show an actionable admin notice when the
   contract detector fails. The supported custom module must continue working.

## Data model

Recommended record shape:

```text
IconLibrary
  uuid, name, slug, status, schema_version

Icon
  uuid, library_uuid, attachment_id, sanitized_asset_id
  name, keywords, render_mode, immutable_codepoint, status
```

Use WordPress attachment IDs for assets and stable UUIDs for references. Named
libraries make client branding, export, and selective activation possible.
Keep deletion tombstones for saved references and never recycle a codepoint.
Resolve URLs at render time and mark missing attachments in the admin UI.

For a small site, a versioned option repository is acceptable if writes use a
safe concurrency strategy. For multiple client libraries and import/export, a
private WordPress post type plus attachment metadata provides clearer native
relationships. Either implementation must sit behind a repository interface so
storage can change without rewriting Divi adapters.

## Security requirements

- Keep library management private and capability checked; allow the capability
  to be filtered for agency roles.
- Verify nonces on every write and validate the record belongs to this plugin.
- Validate real file contents and dimensions, not only attachment MIME metadata.
- Sanitize SVG before a public URL exists, or quarantine/delete the raw source
  on every rejection and write failure.
- Prefer a maintained, reviewed SVG sanitizer over a growing local parser.
- Escape every URL, attribute, label, and serialized value at its output
  context.
- Import must reject unexpected files, path traversal, duplicate UUIDs, and
  unsupported schema versions.

## Test strategy and release gates

Current automated evidence:

- registry smoke test: 19 assertions pass;
- sanitizer smoke test: 19 assertions pass;
- renderer smoke test: 4 assertions pass through `composer test`;
- asset resolver smoke test: 5 assertions pass;
- Jest module-metadata/runtime-contract test: 4 assertions pass;
- production webpack bundle compiles successfully;
- all project PHP files lint;
- `npm audit --omit=dev` reports zero production vulnerabilities.

Current gaps:

- there are no WordPress or Divi integration tests;
- no test verifies filter timing, save/reload, server rendering, hover,
  responsive values, deleted references, concurrent writes, or AJAX failures;
- no interaction-level JavaScript test or CI workflow exists;
- four moderate advisories remain in the development-only dependency path
  from Elegant Themes' current `divi-types` package.

Minimum release matrix:

1. **Unit:** data validation, codepoint bounds/tombstones, sanitizer, render
   modes, attribute parsing, and migrations.
2. **WordPress integration:** permissions, nonces, uploads, atomic persistence,
   attachment migration, uninstall behavior, and HTTP error responses.
3. **Divi fixture:** About module picker search, select, save, reload, server
   render, builder preview, and published page.
4. **Module matrix:** Icon, Blurb, Button, both Toggle states, and each Icon List
   item; desktop/tablet/phone and hover values.
5. **Browser/accessibility:** keyboard-only add/rename/delete/reorder, focus
   recovery, busy state, visible errors, and live announcements.
6. **Compatibility:** every supported Divi version plus a deliberate unknown
   version that proves the bridge fails closed.

Automation cannot certify physical-keyboard or screen-reader behavior. Record
those as manual release checks after automated browser tests pass.

## Phased implementation roadmap

### Phase 0 — Contract snapshot and implementation plan

- Preserve the About fixture and record the current module schemas, render
  output, script handles, and compatibility assumptions as test fixtures.
- Convert this audit into micro-steps with tests and documentation gates.
- Keep the native bridge disabled while the supported module is built.

### Phase 1 — Production foundation

- Introduce domain/repository boundaries, schema versioning, attachment IDs,
  UUIDs, tombstones, and migrations.
- Replace global raw SVG exposure with a safe ingestion pipeline.
- Add complete error, busy, rollback, focus, and localization behavior.
- Add PHPUnit/WordPress integration tests and JavaScript tests.

### Phase 2 — Supported Custom Icon module

- Build the official Divi 5 module with matching builder/server rendering.
- Implement mask and original-color modes.
- Reproduce the useful default Icon module controls and document intentional
  differences.
- Ship this as the first client-ready capability.

### Phase 3 — Optional native bridge proof and adapters

- Prove inline-before picker registration and exact PHP attribute handling on
  the About fixture without a global HTML scanner.
- Promote the successful experiment into the versioned compatibility package.
- Add adapters one at a time: Blurb, Icon, Button, Toggle, Icon List. Blurb goes
  first because the captured fixture defines the desired Custom Icons filter
  experience.
- Require the full fixture matrix before enabling each adapter.
- Stop if the bridge requires an unbounded DOM or rendered-HTML heuristic.

### Phase 4 — Client library operations

- Add named libraries, keywords/categories, activation rules, validated
  import/export, duplicate handling, and migration reports.
- Add an optional reproducible WordPress environment for CI and onboarding.

## Documentation sources

- [Elegant Themes: The Icon Module in Divi 5](https://help.elegantthemes.com/en/articles/10315683-the-icon-module-in-divi-5)
- [Elegant Themes: The Icon List Module in Divi 5](https://help.elegantthemes.com/en/articles/11982062-the-icon-list-module-in-divi-5)
- [Elegant Themes: Divi 5 extension example modules](https://github.com/elegantthemes/d5-extension-example-modules)
- [Elegant Themes: Divi 5 core example modules](https://github.com/elegantthemes/d5-example-core-modules)

The audit did not find a documented official API for injecting a custom
provider into every native icon picker. The hook names described above come
from the installed Divi 5.6.2 source and must be treated as private contracts.
