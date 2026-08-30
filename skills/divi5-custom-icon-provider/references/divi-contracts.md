# Divi 5.6.x icon-provider contracts

Use this as a starting point, then re-check the installed version before
shipping. These are private Divi contracts and may change without notice.

## Installed source anchors

- PHP icon utility:
  `includes/builder-5/server/Packages/IconLibrary/IconFont/Utils.php`
- Builder icon catalog bundle:
  `includes/builder-5/visual-builder/build/icon-library.js`
- Native picker bundle:
  `includes/builder-5/visual-builder/build/field-library.js`
- Blurb metadata:
  `includes/builder-5/visual-builder/packages/module-library/src/components/blurb/module.json`

## Observed hooks and shapes

JavaScript icon-library filters:

```text
iconList
divi.iconLibrary.fontIconTypes
divi.iconLibrary.fontIconFilters
```

PHP font validation filter:

```text
divi_icon_library_font_icon_types
```

The native picker is `divi/icon-picker`. In Blurb, the icon value lives at:

```text
imageIcon.innerContent.icon
```

The visible native filter labels are currently hardcoded as `solid`, `line`,
`divi`, and `fa` in the field-library bundle. Adding a fifth filter slug alone
is therefore incomplete; a tested field wrapper is also required.

## Safety boundary

Never depend on a hook without a runtime contract detector. Verify the Divi
version, script handles, exported filter names, field component availability,
and the target module's saved attribute path. If any check fails, hide only the
Custom Icons bridge and leave the standalone module available.

## Required smoke assertions

1. Custom Icons appears once in the picker filter UI.
2. Search and empty states are announced accessibly.
3. Selection writes a stable plugin reference, not a URL or button label.
4. Color, size, placement, hover, and responsive values still apply.
5. Save/reload preserves the reference and preview.
6. Published/server output resolves the current attachment URL.
7. Disabling or failing contract detection removes only the bridge.
