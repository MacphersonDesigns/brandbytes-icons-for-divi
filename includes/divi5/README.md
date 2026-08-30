# Divi 5 module integration

This feature owns the supported `cifd/custom-icon` module. It is separate from
the experimental bridge that modifies Divi's private native icon picker.

## Files

- `class-custom-icon-module-bootstrap.php` — feature gate, Divi dependency-tree
  hook, and builder/front-end asset registration.
- `class-custom-icon-module.php` — Divi PHP registration, server renderer, and
  responsive design styles.
- `class-custom-icon-asset-resolver.php` — converts a saved UUID into the
  current WordPress attachment URL.
- `../../src/components/custom-icon/` — Visual Builder twin of the module.
- `../../modules-json/custom-icon/` — generated metadata consumed by PHP.

## Feature gate

The foundation is intentionally disabled until the remaining release fixtures
and safe SVG ingestion are ready:

```php
update_option('cifd_enable_experimental_custom_icon_module', true);
```

Use that only in a development site. Production activation will replace this
temporary gate after save/reload, published rendering, named-library filtering,
and safe-ingestion tests pass.

The August 30 live tests enabled the option only long enough to confirm the
module chooser, the searchable LED Party thumbnail, UUID selection, selected
state, canvas preview, empty search behavior, and PHP server output. The option
was then restored to `false`, and the unsaved builder session was closed.

## Verify

```bash
npm run build
npm run test:js
composer test
```

The next slice adds the named-library schema/filter and save/reload/published
page fixture before moving to safe multi-file and ZIP ingestion.
