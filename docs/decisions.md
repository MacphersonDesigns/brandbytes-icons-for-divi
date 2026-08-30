# Architecture decisions

## 2026-08-30 — Explicitly compose Advanced settings for the extension

**Status:** Accepted after live Builder regression evidence.

### Decision

Keep `settings.advanced` set to `auto` in the Custom Icon metadata even though
the installed core Icon JSON omits it.

### Why this approach

Divi augments bundled module definitions through internal registration paths.
Third-party modules do not inherit every one of those defaults. Live testing
showed that removing the explicit opt-in left the Advanced tab completely
empty, despite correct `module.settings.advanced.elements` and `html`
attribute metadata. The visible Builder result is authoritative here.

## 2026-08-30 — Preserve Divi's complete edit-renderer prop contract

**Status:** Accepted after tracing the missing module insertion control.

### Decision

Custom module edit renderers pass the full `ModuleEditProps` object into
`ModuleContainer`, then override only plugin-owned renderer components and HTML
attributes. They must not reconstruct a small prop subset.

### Why this approach

Divi supplies interaction state such as `canAddModule`, `hasAddModuleButton`,
hover state, position, and permission results through the renderer props. The
module can appear visually correct while silently losing native controls if
those values are dropped. Forwarding the host contract also makes future Divi
interaction additions available without another plugin release.

## 2026-08-30 — Merge read-only bundled libraries through one catalog

**Status:** Accepted for version 0.4.0.

### Context

A new installation should demonstrate the module immediately, but inserting
starter files into the Media Library would clutter client content and make
plugin upgrades responsible for attachment lifecycle management. Uploaded
client icons still need editable WordPress attachment relationships.

### Decision

Ship the original **Essentials** SVG set as a versioned plugin manifest. A
read-only bundled provider validates and normalizes that manifest. The
`Icon_Catalog` service merges bundled providers with the existing uploaded
registry and gives the Builder and PHP resolver one stable UUID lookup API.
Bundled records are locked, never written to WordPress options, and win UUID
collisions so a site record cannot silently replace a plugin-owned identity.

### Why this approach

This keeps installation useful, upgrades deterministic, and Media Library data
clean. It also creates the reusable boundary needed for future client-library,
licensed-pack, or remote-provider adapters without coupling Divi rendering to
their storage mechanism.

## 2026-08-30 — Copy Divi Icon's link contract into the custom module

**Status:** Accepted for version 0.4.0.

### Decision

Use Divi's `divi/link` field group and the same anchor-root behavior as the
installed Divi 5.6.2 Icon module. The Builder and PHP renderer both apply URL,
target, title, custom attributes, and `noopener noreferrer` for new windows.

### Why this approach

Editors receive familiar controls and saved metadata, while Builder preview
and published markup follow the same semantic contract. Reusing Divi's field
shape is more maintainable than inventing a plugin-specific link panel.

## 2026-08-30 — Focus 0.3.0 on the supported Custom Icon module

**Status:** Accepted by the product owner after native Blurb canvas testing.

### Decision

Version 0.3.0 loads only the dedicated `cifd/custom-icon` module, its visual
library field, the icon administration screen, and the shared UUID/attachment
resolver. It does not bootstrap the legacy native picker injector, native
Blurb React bridge, or native-module PHP replacement renderer.

The compatibility source remains in the repository as research, but it is not
part of the active bundle or runtime. Product work returns to the supported
Divi module contract and prioritizes editor polish, save/reload reliability,
safe SVG ingestion, and named client libraries.

### Why this approach

The dedicated module already renders through Divi's supported third-party
module architecture. The native picker bridge depends on private, split-window
Builder behavior and did not produce reliable canvas parity. Keeping that
experiment active would make the dependable module harder to test and polish.

## 2026-08-30 — Match Divi's classic React JSX runtime

**Status:** Accepted after live builder verification.

### Context

The first production bundle used TypeScript's automatic JSX transform and
expected a `vendor.ReactJSXRuntime` global. Divi 5.6.2 supplies React and
ReactDOM globals, but not that JSX-runtime global. The module registered, while
its tile rendered Divi's error boundary.

### Decision

Compile builder components with `jsx: react`, keep explicit React imports, and
externalize only the globals Divi actually supplies. This matches Elegant
Themes' official extension example and keeps the plugin from bundling a second
React runtime. A live module-chooser test is required after future build-tool
changes because a successful webpack build cannot prove host-global
compatibility.

## 2026-08-30 — Use a supported module plus a compatibility bridge

**Status:** Accepted and confirmed by the product owner for the next
implementation plan.

### Context

Divi 5.6.2 exposes internal JavaScript filters for its icon list, types, and
filters. It does not expose a documented public API for adding a third-party
icon provider to every native picker. Its PHP icon utility also validates
values against a fixed bundled catalog. The current prototype reaches the
JavaScript hook after Divi has memoized the icon list and depends on rendered
HTML that the PHP utility may already have removed.

### Options considered

1. **Recommended: supported Custom Icon module first, native bridge second.**
   The module uses Divi's documented extension architecture and can reproduce
   the core Icon module's design controls. A separate compatibility package can
   extend native pickers when a tested Divi version is detected.
2. **Native-picker bridge only.** This creates the most seamless editing
   experience across core modules, but relies on private script handles, filter
   timing, saved attribute paths, and per-module render adapters. Every Divi
   update becomes a compatibility event.
3. **Generated icon webfont.** This preserves native font/color behavior, but
   it does not bypass Divi's fixed server catalog and adds font compilation,
   caching, and migration complexity.

### Decision

Build option 1. The supported module is the reliable client-facing product.
Treat the native bridge as an optional adapter with:

- an explicit tested-version matrix;
- an inline-before registration point for Divi's icon-library bundle;
- independent adapters for Icon, Blurb, Button, Toggle, and Icon List;
- server-side parsing of saved attributes rather than discovery in rendered
  HTML;
- a fail-closed admin notice when a required Divi contract changes; and
- About-page integration tests that prove picker, save, reload, render, and
  front-end behavior.

### Rendering decision

Use a CSS mask with `currentColor` as the default monochrome mode. This lets
Divi's color, size, hover, and responsive controls remain meaningful. Offer an
explicit original-color image mode for multicolor artwork, with documentation
that the Divi color control cannot recolor it.

### Data decision

Store WordPress attachment IDs and stable icon UUIDs, not attachment URLs.
Resolve URLs at runtime. Preserve deleted IDs as tombstones and never reuse a
codepoint. Group icons into named client libraries with a schema version and
migration path.

## 2026-08-30 — Make the visual library picker the module's primary field

**Status:** Accepted and confirmed by the product owner.

### Decision

The Custom Icon module will not use a plain Media Library URL field. Its main
content control will open a Divi-style visual picker containing all icons from
the active custom libraries. The picker will support thumbnail previews,
search, library filters, icon names, a clear selected state, and complete
keyboard/focus behavior.

The library manager will support three ingestion paths:

1. one SVG;
2. multiple selected SVG files; and
3. a ZIP-based SVG set with an optional `manifest.json` for authoritative
   names, keywords, and categories.

Generic SVG ZIP is the first set format because it is portable and does not
bind the product to a specific vendor. IcoMoon, Font Awesome kit exports, and
other provider formats can be isolated import adapters after real client
fixtures establish their exact contracts.

Every imported SVG passes through the same sanitization and normalization
pipeline. Archives have file-count, expanded-size, extension, and path safety
limits. Import is resumable/reportable, and one unsafe member does not hide the
status of the remaining files.

## 2026-08-30 — Treat custom icons as a provider in compatible core pickers

**Status:** Accepted as a post-module compatibility objective.

### Context

Divi's native Blurb control already communicates the right mental model: turn
on **Use Icon**, then search, filter, and choose from an icon grid. Adding a
second unrelated field beside that control would confuse editors and duplicate
the picker.

Live Divi 5.6.2 inspection later confirmed that the icon catalog, type array,
and filter-slug array have evaluation-time hooks, but the field-library bundle
hardcodes the four visible filter option keys and labels. A Custom Icons entry
therefore requires a versioned field-component wrapper in addition to early
catalog data; adding a filter slug alone is not sufficient.

### Decision

When the tested Divi contract allows it, the plugin will register **Custom
Icons** as a recognizable provider/filter inside the existing picker. Choosing
it limits results to active client libraries while preserving the normal search
and selection workflow. If the internal filter shape supports only a flat list,
the product uses one flat Custom Icons entry rather than forcing an artificial
nested submenu.

This bridge is not the same feature as Divi's SVG module. It supplies reusable,
named icon records to icon-aware controls and preserves icon semantics and
styling. Each supported core module also receives a matching PHP render adapter;
the picker integration is never enabled by itself.

The bridge remains optional and fail-closed. A Divi version that changes its
private picker or rendering contracts keeps the supported Custom Icon module,
but does not show a broken Custom Icons filter in core modules.

## 2026-08-30 — Use direct local Divi source for compatibility research

**Status:** Accepted for the bridge research phase.

### Context

The WordPress MCP connection can inspect runtime state and execute safe probes,
but it does not expose Divi's complete installed source tree. The local Divi
5.6.2 source is available at the app-development Local site and can be read
without modifying the theme.

### Decision

Use the local Divi source as the authoritative compatibility reference for the
bridge, while using MCP and the authenticated browser for runtime verification.
The source revealed the precise split: icon-library catalog filters are
hookable, but the native field-library filter-label map is hardcoded and the
PHP renderer validates against a fixed font catalog. This prevents us from
mistaking a successful JavaScript hook for a complete provider integration.

The plugin will never edit Divi's files. It will isolate version-specific
adapters in its own compatibility module, detect the installed contract, and
fail closed when the private field or renderer contract changes.
