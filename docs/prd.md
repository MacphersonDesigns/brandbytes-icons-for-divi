# Product requirements

## Product

Custom Icons for Divi gives an agency a safe, reusable way to install and
manage client-owned icon libraries inside Divi 5 Builder without sacrificing
the design controls clients already understand.

This is intentionally an **icon system**, not a generic SVG placement feature.
Editors should recognize uploaded artwork as reusable icons with names,
libraries, search metadata, selection state, and icon-specific design behavior.
Divi's built-in SVG module does not satisfy that product requirement.

## Problem

Divi's default icon modules provide excellent color, size, alignment,
responsive, hover, and advanced controls, but the audited Divi 5.6.2 runtime
does not expose a documented end-to-end provider API for arbitrary client icon
libraries. JavaScript picker injection alone cannot satisfy Divi's separate PHP
rendering contract.

## Primary users

- Agency administrators who install, organize, update, and migrate a client's
  branded icons.
- Divi editors who select those icons without handling SVG code or leaving the
  Visual Builder.
- Developers who need a versioned compatibility boundary when Divi changes.

## Required client experience

1. An administrator creates or imports a named client library.
2. Uploaded SVG assets are sanitized before any public copy can be served.
3. The editor inserts a Custom Icon module in Divi 5 and opens a visual picker
   showing thumbnail previews of every icon in the active client libraries.
4. The editor searches or filters the grid, selects an icon, and immediately
   sees it in the Visual Builder preview.
5. The editor can use familiar Divi controls for color, size, alignment,
   spacing, responsive values, hover, links, and supported advanced settings.
6. Builder preview, saved content, server rendering, and the published page
   agree after reload.
7. A missing or deleted icon produces a controlled fallback and an actionable
   admin warning; it never breaks the page.

## Functional requirements

- Multiple named icon libraries with stable UUIDs and site-local activation.
- A bundled starter library that provides useful icons immediately without
  creating WordPress Media Library records.
- A Divi-style visual icon picker with thumbnail grid, search, named-library
  filters, selected state, icon names, and complete keyboard operation.
- Individual SVG upload, multiple-SVG upload, and validated ZIP-based SVG-set
  import with an optional metadata manifest.
- WordPress attachment relationships rather than stored absolute URLs.
- Monochrome CSS-mask rendering with `currentColor` by default.
- Explicit original-color mode for multicolor SVG/raster assets.
- Searchable names and keywords.
- Accessible add, rename, delete, reorder, error, and progress behavior.
- Versioned schema migrations and deletion tombstones.
- A supported Divi 5 Custom Icon module as the production entry point.
- An optional **Custom Icons** provider/filter within the existing Search Icons
  experience of native Icon, Blurb, Button, Toggle, and Icon List modules,
  behind a tested, fail-closed compatibility layer.
- Validated import/export after the core schema is stable.
- Import progress, per-file results, safe partial-failure handling, duplicate
  detection, and a downloadable completion report.

## Non-functional requirements

- Private by default; every write requires an appropriate capability and nonce.
- No globally published unsanitized SVG uploads.
- No document-wide DOM observer or rendered-HTML guessing in the supported
  module path.
- Modular domain, media, Divi module, compatibility, rendering, admin, and test
  boundaries with a README for each implemented feature folder.
- Automated unit, WordPress integration, Divi fixture, and browser tests, plus
  documented manual keyboard and screen-reader release checks.
- A reproducible WordPress environment for CI and onboarding before the first
  production release.

## First production release acceptance criteria

- The supported Custom Icon module completes select, save, reload, server
  render, builder preview, and front-end tests on every supported Divi version.
- Divi design settings tested for desktop, tablet, phone, and hover render
  consistently in the builder and on the front end.
- Unsafe SVG fixtures are rejected before publication; safe fixtures render in
  both mask and original-color modes.
- Failed admin operations show visible and announced errors, roll back stale
  UI state, and recover keyboard focus safely.
- A keyboard-only editor can open the picker, search, move through results,
  select an icon, close the picker, and return focus to its trigger.
- A representative SVG set imports without losing icon names; unsafe and
  malformed members are rejected individually and listed in the import report.
- Missing attachments and deleted references have tested fallbacks.
- Documentation, changelog, schema version, and compatibility matrix match the
  released code.

## Deferred until after the supported module

- Enabling client icons inside core Divi pickers. The first proof targets the
  default Blurb module shown in the About-page fixture; later adapters cover
  Icon, Button, Toggle, and Icon List.
- Cross-site/cloud library synchronization.
- On-demand icon webfont generation.
- Role-specific library visibility beyond the initial management capability.
