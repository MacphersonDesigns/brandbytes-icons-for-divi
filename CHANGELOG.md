# Changelog

All notable changes to this project will be documented in this file.

## Unreleased

- Added a security and public-release readiness audit covering SVG ingestion,
  WordPress authorization boundaries, Builder rendering, dependencies, secret
  hygiene, licensing, and repository publication gates.
- Identified raw SVG publication before sanitization as the remaining security
  release blocker; no critical vulnerabilities or committed secrets were found.

## 0.4.9 - 2026-08-30

- Added a GitHub-based update path (`includes/class-updater.php`) using
  Plugin Update Checker, reading built release assets from GitHub Releases so
  sites get native in-dashboard update notices ahead of a WordPress.org
  directory submission.
- Added `.github/workflows/release.yml`: pushing a `v*.*.*` tag verifies the
  tag matches `package.json`, the plugin header, and `readme.txt`, builds the
  release zip, and publishes it as a GitHub Release.
- Added `bin/bump-version.sh` (`npm run bump <version>`) to keep those three
  version locations in sync.

## 0.4.8 - 2026-08-30

- Added a dedicated SVG upload policy that sanitizes PHP's private temporary
  upload file before WordPress can publish it or create an attachment.
- Added atomic sanitized-file replacement and fail-closed upload errors.
- Added sanitizer limits for file size, element count, nesting depth, and
  attribute length, with regression coverage for each boundary.
- Rejected active URL schemes in the live Builder preview while preserving
  normal relative, web, email, and telephone links.
- Added the complete GPL v2 license, security and contribution policies,
  Essentials artwork provenance, and GitHub Actions build/test checks.
- Adopted BrandBytes as the product family and Code & Canvas as publisher while
  preserving all existing `cifd` persisted identifiers.

## 0.4.7 - 2026-08-30

- Restored explicit `advanced: auto` panel composition for the third-party
  module after live Builder acceptance showed an empty Advanced tab.
- Documented that Divi's bundled modules receive internal registration
  augmentation that external modules cannot assume.
- Updated regression coverage to require the external-module Advanced opt-in.

## 0.4.6 - 2026-08-30

- Completed a Content, Design, and Advanced sidebar contract audit against the
  installed Divi 5.6.2 Icon module.
- Matched native icon-specific group slugs and removed the obsolete top-level
  Advanced auto-registration flag.
- Added regression coverage for every universal decoration group and its
  Builder style, PHP style/script, and Custom CSS pipelines.
- Added a maintained sidebar control matrix documenting ownership and render
  paths for future module changes.

## 0.4.5 - 2026-08-30

- Updated the universal module metadata to Divi 5.6.2's current Icon contract,
  including Box Shadow overlay rendering, Background field restrictions, and
  current Advanced element/HTML groups.
- Added regression coverage for the Design and Advanced sidebar wiring rather
  than testing only the icon-specific color, size, and alignment controls.

## 0.4.4 - 2026-08-30

- Matched Divi's native Icon flex-item behavior so the Custom Icon module
  shrink-wraps its glyph instead of stretching across the full column.
- Matched Divi's left/right alignment declarations with the corresponding
  zeroed margin, keeping the compact module box positionable in flex layouts.

## 0.4.3 - 2026-08-30

- Forwarded Divi's complete edit-renderer props into `ModuleContainer`,
  restoring runtime flags such as `canAddModule` and `hasAddModuleButton` that
  control the dark module-level insertion button.
- Added a regression test proving the renderer does not discard Divi's Builder
  interaction contract.

## 0.4.2 - 2026-08-30

- Made the bundled Home icon the default for newly inserted Custom Icon
  modules and legacy explicitly cleared values.
- Replaced the layout-shifting empty clear action with **Reset to Home**, while
  preserving link and other unrelated module values.

## 0.4.1 - 2026-08-30

- Scoped zero line-height to the icon artwork wrapper instead of the Divi
  module root, preventing Builder module-level insertion controls from being
  collapsed while preserving the icon's tight inline box.

## 0.4.0 - 2026-08-30

- Added a bundled **Essentials** library with 16 original, consistently drawn
  SVG icons that are available immediately and do not create Media Library
  records.
- Added a validated, read-only manifest provider and a merged catalog service
  so bundled and uploaded libraries share one UUID lookup contract.
- Added accessible library filters and richer name, library, category, keyword,
  and MIME search to the Custom Icon module picker.
- Added Divi-native Link settings, including URL, same/new-window behavior,
  title, custom link attributes, anchor-root rendering, and secure
  `noopener noreferrer` handling.
- Matched Divi's Icon module wrapper and link metadata contracts in both the
  React Builder renderer and PHP server renderer.
- Added bundled-catalog and expanded module-contract regression coverage.

### Archived native-picker research from before 0.3.0

- Audited the installed Divi 5.6.2 source tree directly and documented the
  catalog, field-library, Blurb metadata, and PHP renderer contracts required
  for a native Custom Icons bridge.
- Confirmed that Divi's JavaScript icon filters are not sufficient by
  themselves: the visible native filter labels are hardcoded and server-side
  font rendering uses a fixed catalog.
- Added the reusable `divi5-custom-icon-provider` skill and packaged it under
  `dist/` for future Divi icon-provider audits and implementations.
- Added the first Blurb bridge slice: a native picker wrapper with a Custom
  Icons provider tab, stable `type`/UUID values, and a scoped PHP mask renderer
  for saved custom references.
- Fixed Builder canvas visibility by shipping the Blurb mask CSS in the shared
  module stylesheet and loading the Builder stylesheet in both Divi windows.
- Added a Blurb-only React canvas render adapter using Divi's
  `divi.module.elements.render` hook, so live custom-icon selection updates the
  Builder tree as well as the published PHP output.
- Registered the Builder bundle in both Divi windows after isolating the
  app/sidebar-versus-canvas render boundary.
- Made the Blurb canvas finder cross-window aware, including same-origin Divi
  preview iframes, so the React preview adapter can locate its marked module
  root when the settings app and canvas live in different documents.
- Repaired the development About-page fixture with WordPress-safe slashing;
  literal `u003c` text was serialized content, not an HTML tag-order problem.
- Fixed native-provider tab state so choosing a built-in Divi icon cannot
  bounce the sidebar back to the previously selected Custom Icons record.

## 0.3.0 - 2026-08-30

- Narrowed the active Builder integration to the supported `cifd/custom-icon`
  module; native Icon and Blurb picker/render bridges are no longer loaded.
- Redesigned the module picker with a Divi-aligned three-column library grid,
  count badge, search treatment, library labels, selected checkmark, selection
  summary, refined clear action, and reduced-motion support.
- Replaced the plain canvas placeholder with an accessible, polished empty
  state that directs editors to the module's Content panel.
- Kept the experimental native bridge source and research documentation
  archived for a future versioned compatibility package.

## 0.2.5 - 2026-08-30

- Reads live Blurb attributes from the React element props that Divi 5.6.2
  passes to `divi.module.elements.render`, including `runtimeModuleAttrs`.
- Recognizes Divi's `et_pb_blurb` canvas identifier and resolves the icon
  catalog across the top and app windows.

## 0.2.4 - 2026-08-30

- Provides the UUID-to-SVG catalog in both Divi Builder windows so the
  canvas-side Blurb adapter can resolve custom selections.
- Falls back to Divi's stable module `orderClass` when a React component does
  not forward the bridge's diagnostic data attribute to the canvas DOM.

## 0.2.3 - 2026-08-30

- Reads Blurb values from Divi's runtime, module, and canonical attribute bags
  so a custom icon remains visible in the Builder canvas after provider
  switching.

## 0.2.2 - 2026-08-30

- Synchronizes provider state after native icon selection and preserves the
  selected built-in icon instead of restoring stale Custom Icons state.

## 0.2.1 - 2026-08-30

- Development compatibility build with cache-busted Builder assets for the
  Blurb canvas preview adapter.

## 0.2.0 - 2026-08-30

### Added

- Added the feature-gated `cifd/custom-icon` Divi 5 module foundation with
  official dependency-tree registration, Visual Builder assets, generated
  module metadata, responsive design controls, and a PHP server renderer.
- Added stable UUID and WordPress attachment relationships to icon records,
  including an idempotent migration for the earlier URL-only records.
- Added a dedicated asset resolver so builder, server renderer, and future
  native-module adapters share one UUID-to-current-attachment contract.
- Added a Divi-registered Custom Icon field with a searchable thumbnail grid,
  accessible names and selected state, no-results management link, clear
  control, and immediate UUID-backed canvas preview.
- Added authenticated builder catalog data that resolves current attachment
  URLs without changing the UUID saved in module content.
- Added webpack/TypeScript/Sass build tooling, Jest metadata coverage, resolver
  tests, and the previously omitted renderer test to `composer test`.

### Fixed

- Compiled the Visual Builder bundle with Divi's classic React JSX runtime.
  This removes the `ReactJSXRuntime` failure found when the live Divi 5.6.2
  module chooser first rendered the Custom Icon tile.
- Corrected the JavaScript `StyleDeclarations.value` property contract found
  during live module insertion.
- Preserved the CSS mask URL in PHP output after Divi's HTML utility stripped
  URL-valued custom properties from the glyph's inline style.

### Documentation

- Added the August 30, 2026 Divi 5.6.2 integration, security, accessibility,
  data-model, and test audit.
- Added product requirements and release acceptance criteria for client-owned
  icon libraries and the supported Custom Icon module.
- Defined the Custom Icon module's visual picker and SVG-set import experience,
  including search, library filtering, keyboard behavior, duplicate handling,
  progress reporting, and safe ZIP ingestion.
- Distinguished the Custom Icon module from Divi's generic SVG module and
  specified an optional Custom Icons provider/filter inside compatible native
  icon pickers, beginning with the About-page Blurb fixture.
- Recorded the decision to build a supported Custom Icon module first and keep
  native-picker integration behind a versioned compatibility boundary.
- Added a truthful feature registry separating implemented prototype behavior
  from verified behavior and release blockers.
- Marked the original August 13 design and implementation plan as historical
  and superseded.
- Updated the root README with current project status and complete smoke-test
  commands.
- Added the implementation plan, module README, live verification result, and
  the remaining save/reload and ingestion review gates.
- Documented that Divi 5.6.2's four native filter labels are hardcoded in the
  field-library bundle even though its catalog/type/filter arrays are hooked.

### Known limitations

- The Custom Icon module is disabled by default until its save/reload contract,
  complete front-end fixture, safe SVG ingestion, and named-library filters
  pass.
- The earlier native-picker and post-render bridge remain experimental and are
  not a production integration path.
- Four moderate npm advisories remain in the development-only dependency tree
  inherited through Elegant Themes' `divi-types`; production dependencies
  report zero vulnerabilities.
