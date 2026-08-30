# BrandBytes Icons for Divi

BrandBytes Icons for Divi is a Code & Canvas plugin for managing a
site-owned icon library. It includes a WordPress Media Library-backed admin
screen, SVG sanitization, an audited legacy picker experiment, and the
feature-gated foundation of a dedicated Divi 5 Custom Icon module.

## Project status

**Foundation — not production-ready.** A live audit against Divi 5.6.2 found
that the current icon-list filter runs too late for Divi's memoized picker and
that Divi's PHP renderer rejects glyphs that are absent from its bundled icon
catalog. The custom icon therefore does not currently appear or render through
the native Divi Icon module end to end.

Version 0.4.8 focuses exclusively on the independently testable Custom Icon
module and its polished visual picker. The module registers through Divi's dependency tree,
appears as **Custom Icon** in the live Visual Builder module chooser, shows the
site catalog as a searchable and library-filterable thumbnail grid, owns a PHP
renderer, supports Divi-style links, and stores a stable icon UUID instead of a
URL. A bundled **Essentials** library supplies 16 original interface icons as
soon as the module is enabled, without creating Media Library attachments.
New modules render its Home icon immediately, avoiding an empty-state layout
shift before the editor opens the picker. The
module stays disabled by default until the save/reload/front-end fixture and
save/reload and front-end release fixtures are complete. SVG uploads are now
sanitized before WordPress publishes them, with explicit file and XML
complexity limits.

The recommended path is:

1. Ship a supported Divi 5 **Custom Icon module** that mirrors the default Icon
   module's design controls.
2. Revisit a separately versioned, fail-closed compatibility bridge for native
   Icon, Blurb, Button, Toggle, and Icon List pickers only after the About-page
   proof of concept passes builder, saved-content, server-render, and front-end
   tests.

Read the [product requirements](docs/prd.md), the full
[Divi 5 integration audit](docs/audits/2026-08-30-divi-5-custom-icon-integration-audit.md),
the [architecture decisions](docs/decisions.md), and the
[feature registry](docs/feature-registry.md). The current implementation slice
and its review boundary are recorded in the
[module foundation plan](docs/plans/2026-08-30-custom-icon-module-foundation.md).
The maintained [sidebar control matrix](docs/features/sidebar-control-matrix.md)
records how each Content, Design, and Advanced group reaches Builder and
front-end rendering.

The reusable agent workflow created during this audit is packaged at
[`dist/divi5-custom-icon-provider.skill`](dist/divi5-custom-icon-provider.skill).
Its source lives under `skills/divi5-custom-icon-provider/` and captures the
Divi contract-inspection, implementation, compatibility, and verification
loop for future icon-provider work.

The Custom Icon module's primary editor experience is a
[Divi-style visual library picker](docs/features/icon-library-picker.md), fed by
individual SVG uploads, multi-file uploads, or validated SVG-set archives.
An optional [native icon-picker bridge](docs/features/native-icon-picker-bridge.md)
will make the same catalog available through a **Custom Icons** provider/filter
inside compatible core modules such as Blurb.

## Compatibility

| Requirement | Minimum | Verified against |
| --- | --- | --- |
| PHP | 7.4 | 7.4, 8.2 (CI matrix) |
| Divi | 5.x | 5.6.2 (live audit fixture) |
| WordPress | Whatever your installed Divi 5 release supports | Not independently verified; this project does not pin its own WordPress floor |

This project has not been tested against Divi 4 and does not register a module
there. Divi is a trademark of Elegant Themes, Inc.; this project is
independently developed and not affiliated with, endorsed by, or sponsored by
Elegant Themes.

## Current modules

- `includes/class-icon-registry.php` — option-backed icon records, stable UUIDs,
  attachment relationships, and legacy-record migration.
- `includes/class-bundled-icon-library.php` — read-only, validated manifest
  provider for plugin-owned starter libraries.
- `includes/class-icon-catalog.php` — merged read contract for bundled and
  administrator-uploaded icons.
- `includes/class-icon-sanitizer.php` — bounded SVG allowlist sanitizer.
- `includes/admin/` — secure pre-publication upload policy and Media
  Library-backed icon administration.
- `includes/builder/` — archived native-picker experiment; not loaded in 0.4.7.
- `includes/frontend/` — archived native render adapters; not loaded in 0.4.7.
- `includes/divi5/` — feature-gated Divi registration, asset resolution, and
  server rendering for `cifd/custom-icon`.
- `src/components/custom-icon/` — React/TypeScript Visual Builder module.
- `modules-json/custom-icon/` — generated Divi module metadata.
- `tests/` — dependency-free PHP smoke tests and Jest metadata tests.

## Development

Requirements: PHP 7.4+, Composer, Node 18+, and npm 9+.

```bash
composer install
npm install
npm run build
npm run test:js
composer test
```

Expected result: webpack compiles successfully; 14 Jest tests pass; and the PHP
suites report 19 registry, 12 bundled-catalog, 23 sanitizer, 10 upload-policy,
6 renderer, and 5 asset-resolver assertions with zero failures.

Lint all PHP files:

```bash
find . -name '*.php' -not -path './vendor/*' -not -path './node_modules/*' -print0 | xargs -0 -n1 php -l
```

For builder testing, activate the plugin in a WordPress site running Divi 5.
The live audit fixture is the site's About page. The experimental module can be
enabled on a development site only:

```php
update_option('cifd_enable_experimental_custom_icon_module', true);
```

Return the option to `false` after testing. The audited Local development site
currently keeps it enabled for module-polish acceptance testing.

Dependency audit note: `npm audit --omit=dev` reports zero production
vulnerabilities. The full development tree currently reports four moderate
advisories inherited through Elegant Themes' published `divi-types` package.
The suggested forced downgrade is not applied because it changes that required
Divi contract.

## Docker note

This documentation-only audit used the existing Local WordPress site and its
authenticated MCP connection, so it did not add another stateful environment.
A reproducible `wp-env` or Docker Compose integration harness is a roadmap item
before production release.

## Historical documents

The August 13 design and implementation plan under `docs/superpowers/` record
the original prototype approach. They are retained for context but are marked
superseded and must not be treated as the current implementation contract.

## License and trademark

BrandBytes Icons for Divi is licensed under GPL-2.0-or-later. See
[LICENSE](LICENSE). The bundled Essentials artwork is documented in its
[library README](assets/icon-libraries/essentials/README.md).

Divi is a trademark of Elegant Themes, Inc. This independent project is not
affiliated with, endorsed by, or sponsored by Elegant Themes.
