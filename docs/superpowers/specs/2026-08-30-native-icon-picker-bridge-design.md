# Native icon-picker bridge: design

Date: 2026-08-30
Status: Approved design, pending implementation plan
Supersedes the "archived" status of `docs/features/native-icon-picker-bridge.md`
for the scope described here. That document's per-module contract table and
accessibility contract remain the reference; this spec adds the 5.11.1
re-verification findings, the architecture decision on what to keep/rewrite/
discard from the archived prototype, and the rollout plan.

## Goal

Make the plugin's site-owned icon library selectable from inside Divi 5's
native modules that already have their own icon picker — Blurb, Icon, Button,
Toggle, and Icon List Item — in addition to the existing dedicated Custom Icon
module. Success bar: full live-canvas parity in the Visual Builder (pick a
custom icon in a native module's sidebar and see it render immediately in the
canvas), not just correct save/reload/front-end output.

## Context

An earlier attempt (documented in `docs/features/native-icon-picker-bridge.md`
and `docs/decisions.md`, dated against Divi 5.6.2) built a partial Blurb-only
bridge and was shelved because it "did not maintain reliable Builder canvas
parity." The plugin currently ships only the dedicated `cifd/custom-icon`
module; the archived bridge code (`includes/builder/`, `includes/frontend/`,
`src/components/custom-icon/native-icon-picker-bridge.tsx`) is present in the
repository but not loaded.

The site's installed Divi version is now 5.11.1, five minor versions past the
version the archived research targeted. Because this bridge depends entirely
on undocumented, private Divi internals, the first step was re-verifying the
old findings against the current source rather than assuming they still hold.

## Re-verification findings (Divi 5.11.1, read directly from the installed
theme source at the app-development Local site)

- `ET\Builder\Packages\IconLibrary\IconFont\Utils::process_font_icon()` still
  returns `null` for any icon `type` outside `['divi', 'fa']` (filterable via
  `divi_icon_library_font_icon_types`). Divi's native font-rendering path
  genuinely cannot render a plugin-owned icon — confirmed still true, not
  fixed or relaxed in newer Divi.
- The built `field-library.js` bundle still contains the four hardcoded
  filter-dropdown labels (`Solid Icons`, `Line Icons`, `Divi Icons`,
  `Font Awesome`). Adding a fifth filter key/hook is still not sufficient to
  produce a usable menu entry; wrapping the resolved icon-picker field to add
  a sibling "Custom Icons" tab is still the only viable UI path.
- Saved-attribute shapes are unchanged from the archived research:
  - Blurb: `imageIcon.innerContent.{bp}.value.icon`
  - Icon module: `icon.innerContent.{bp}.value`
  - Icon List Item: `icon.innerContent.{bp}.value`
  - Button (shared `ButtonComponent`, covers every button-bearing module):
    `button.{bp}.value.icon.settings`
  - Toggle: not individually re-verified line-by-line in this pass; expected
    to match the Icon-module shape (`icon.innerContent.{bp}.value`) based on
    Toggle's module directory structure, to be confirmed during its adapter's
    implementation.
- `{bp}` is `desktop` | `tablet` | `phone`. The archived Blurb PHP adapter
  (`includes/frontend/class-icon-renderer.php`) only reads `desktop` — a real
  bug, not a simplification, since Divi attributes are responsive-capable.

## Architecture decision: keep / rewrite / discard

**Keep and generalize:** the UUID-based `{"type": "cifd", "uuid": "..."}`
attribute contract, the existing `Custom_Icon_Asset_Resolver` (UUID → current
attachment URL), and the read pattern proven in the archived Blurb adapter.
These become one shared `Native_Icon_Attribute_Reader` (new class under
`includes/divi5/`) that reads a `{type, uuid}` value from a given attribute
path across all three breakpoints, used by every module adapter instead of
per-module copy-pasted parsing. Fixing the desktop-only bug happens here, once,
for all modules.

**Rewrite:** Toggle, Icon List Item, and Button. The archived adapters for
these three use an older codepoint/entity-scanning approach against rendered
HTML (`find_custom_entity_in_content`, `find_all_custom_entities_in_order`),
including an explicitly documented order-based guessing heuristic for Button
that can mis-assign icons when a block mixes icon and non-icon buttons. These
are replaced with adapters built directly on the verified attribute paths
above, matching the reliability of the Blurb/Custom-Icon-module pattern.

**Discard:** `includes/builder/class-icon-picker-injector.php` and
`includes/builder/assets/icon-picker.js`. This is a superseded, older
codepoint-based prototype (`cifdBuilderIcons` localized script, hooked to
`divi_visual_builder_assets_before_enqueue_scripts`). The UUID-to-SVG catalog
it tries to provide is already supplied by
`includes/divi5/class-custom-icon-module-bootstrap.php`. Delete rather than
adapt.

**Extend:** `src/components/custom-icon/native-icon-picker-bridge.tsx`'s
`NativeIconPickerBridge` component (tab-switching UI, provider-state sync) is
usable groundwork, but:
1. its ARIA tab markup is incomplete (see Accessibility below) and needs
   rework before shipping;
2. nothing in the archived code actually intercepts Divi's rendered icon-field
   component per module to insert this wrapper — that registration point was
   never found or proven. This is the one genuine open technical unknown (see
   Rollout below).

## Rollout order and gating (Approach A)

Build one shared field-wrapper mechanism and one shared PHP attribute-reader,
but fully prove the pattern on a single module before repeating it:

1. **Blurb.** First target because it already has the most prior art (a
   working PHP read path, a start on the JS wrapper, and documented canvas
   markers). Phase 0 of implementation is a spike: prove that Divi 5.11.1
   exposes a way to intercept/wrap the rendered icon-picker field for the
   `imageIcon` option. If no stable interception point exists, this is
   reported back explicitly — not silently downgraded to a partial feature —
   and the plan is revisited before any further module work.
2. **Icon module** — same shape as Icon List Item, proves the pattern
   generalizes beyond Blurb's more complex nested attribute path.
3. **Button** — shared `ButtonComponent`, so this one adapter covers every
   button-bearing module (Button module, and any module with a button option
   group) at once.
4. **Toggle** — attribute path to be confirmed against source during this
   step.
5. **Icon List Item.**

Each module must pass its full proof-of-concept gate (already defined in
`docs/features/native-icon-picker-bridge.md`'s "Proof-of-concept gate"
section: picker, live canvas, save, reload, front-end render, and the
bridge-disabled/unsupported-Divi-version regression check) before the next
module starts.

## Compatibility gate

The bridge checks the active Divi version against an explicit tested-version
allowlist (starting with `5.11.1` once Blurb passes its gate) before
registering any filter, catalog data, or field wrapper. Outside that list it
registers nothing — no partial UI, no broken filter — and shows one
dismissible admin notice identifying the untested version and confirming the
dedicated Custom Icon module is unaffected. A new Divi version is added to the
allowlist only after each already-shipped module re-passes its
proof-of-concept gate on that version.

## Accessibility contract

This changes/adds Visual Builder UI, so the accessibility contract is binding,
not aspirational:

- The tab UI is reworked to a proper WAI-ARIA tabs pattern: `role="tablist"`
  container, `role="tab"` triggers with `aria-selected` and `aria-controls`,
  associated `role="tabpanel"` content, and left/right arrow-key navigation
  with a single roving `tabindex`. The archived markup (bare buttons with
  `aria-selected` but no `aria-controls`/`tabpanel`/arrow-key handling) does
  not meet this and is corrected as part of this work, not carried over as-is.
- Filter/result-count changes announce through a visually-hidden live region,
  reusing the existing Custom Icon module picker's pattern.
- Custom-icon thumbnails remain decorative (`aria-hidden`); each choice's
  accessible name comes from the icon record's name.
- Existing Divi picker keyboard behavior for the native ("Divi Icons") tab is
  preserved untouched, not rebuilt.
- If the bridge cannot preserve focus, accessible names, or selection
  semantics on a given Divi version, it fails closed for that version (see
  Compatibility gate).

## Testing

- PHP unit tests per module adapter: given a saved `{type: 'cifd', uuid}`
  value at each of the three breakpoints, assert the rendered output resolves
  to the correct current attachment URL. This is the regression test for the
  desktop-only bug class found in the archived Blurb adapter.
- Jest tests for the field-wrapper: tab/ARIA structure, provider-switching
  state logic (native ↔ custom), and that native Divi values are left
  untouched when the custom tab was never opened.
- Manual, live-Builder verification is required and cannot be automated for:
  canvas live-preview parity, and save → leave Builder → reload → confirm
  persistence. This is an existing, accepted constraint
  (`docs/decisions.md`: "A live Builder session is required for manual visual
  acceptance; automated tests cannot certify the user's already-open
  iframe."), not a gap introduced by this plan. Each module's manual
  proof-of-concept checklist is the acceptance gate.

## Documentation to update as modules ship

- `docs/features/native-icon-picker-bridge.md`: status changes from
  "Archived" to active; add the 5.11.1 re-verification findings and the
  Phase-0 spike outcome.
- `docs/decisions.md`: new entry once the field-interception spike resolves,
  recording the mechanism found (or the fallback taken if none exists).
- `CHANGELOG.md` and `docs/feature-registry.md`: updated per module as each
  passes its proof-of-concept gate.
- `README.md`: module list and project-status section updated once at least
  Blurb ships.

## Explicit non-goals for this design

- Divi 4 is out of scope (unchanged from existing project scope).
- No change to the dedicated Custom Icon module's own contract, UUID scheme,
  or option keys.
- No attempt to simulate a nested nav/submenu under "Custom Icons" if Divi's
  filter model only supports a flat list — one flat "Custom Icons" entry with
  search covering all active libraries is the accepted fallback, per the
  existing decision in `docs/features/native-icon-picker-bridge.md`.
