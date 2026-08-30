# Custom Icon Divi 5 module

This is the Visual Builder half of `cifd/custom-icon`. Its PHP twin is
`includes/divi5/class-custom-icon-module.php`.

## Current foundation

- Registers a recognizable Custom Icon module and module-library icon.
- Saves icon identity under `iconReference.innerContent.*.value.uuid`.
- Mirrors Divi's icon color, size, alignment, and universal module settings.
- Uses a CSS mask with `currentColor` for monochrome icon rendering.
- Shows a polished builder-only empty state when no icon is selected.
- Presents a three-column, searchable library with icon/library labels,
  selected-state checkmark, count, clear action, and reduced-motion support.
- Filters the merged catalog by named library; **Essentials** is available on
  first enablement and uploaded records remain grouped as **Custom Icons**.
- Uses Divi's native Link field contract and renders the module root as an
  anchor when a URL is supplied, in both Builder and PHP output.
- Inserts the bundled Home icon by default; the picker resets to Home instead
  of creating a layout-shifting empty module.
- Forwards Divi's full edit-renderer prop contract into `ModuleContainer`, so
  native hover, selection, permission, and module-insertion controls work.
- Uses the native Icon module's flex-item and alignment behavior, keeping the
  X-ray bounding box fitted to the icon instead of stretched across its column.
- Uses the installed Divi Icon module's universal metadata contract for
  Background, Border, Box Shadow, Filters, Transform, Animation, Spacing,
  Sizing, and Advanced element/HTML controls.
- Keeps those controls mapped in
  `docs/features/sidebar-control-matrix.md`; new sidebar groups require a saved
  attribute, Builder path, PHP/front-end path, and regression assertion.

The module remains feature-gated while save/reload and safe SVG ingestion are
completed. Do not add a temporary URL or generic upload field; the stable UUID
and attachment resolver remain the supported storage contract.

## Build and test

```bash
npm run build
npm run test:js
composer test
```
