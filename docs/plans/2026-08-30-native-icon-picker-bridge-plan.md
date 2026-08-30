# Native icon-picker bridge: implementation plan

Date: 2026-08-30
Spec: docs/superpowers/specs/2026-08-30-native-icon-picker-bridge-design.md

## Objective

Make the plugin's UUID-based custom icon library selectable from inside
native Divi 5 modules that already have their own icon picker (Blurb, Icon,
Button, Toggle, Icon List Item), with full live-canvas parity in the Visual
Builder, not just correct save/reload/front-end output. Work proceeds
module-by-module, gated: Blurb ships completely (including a proof-of-concept
gate) before Icon starts, and so on, because a shared field-interception
mechanism this entire feature depends on has not yet been proven to exist in
Divi 5.11.1.

Only Phase 0 and the Blurb module are planned in full task-level detail below.
Icon, Button, Toggle, and Icon List Item follow the repeatable template in
Stream 4 instead of individually pre-planned tasks, because their exact
approach depends on what Phase 0 and Blurb discover.

## Workstreams

### Stream 1 — Spike & shared infrastructure

- **Task: Prove or disprove the field-interception mechanism (Phase 0 spike).**
  Investigate how Divi 5.11.1 lets a third party intercept/wrap the rendered
  icon-picker field component for a specific module option (starting with
  Blurb's `imageIcon`). Look for a field-type registry, a `divi.fieldLibrary`-
  style filter, a React context/HOC wrapping point, or any other stable public
  or de-facto-stable mechanism, using the installed theme source at the
  app-development Local site as ground truth.
  - Definition of done: either (a) a minimal working proof, verified live in
    the Divi Builder, that successfully renders custom content inside/beside
    Blurb's icon field, with the exact mechanism documented; or (b) an
    explicit written finding that no stable interception point exists,
    including what was tried and why it failed.
  - Validation: live-Builder manual proof (screenshot or short recording);
    finding recorded in `docs/features/native-icon-picker-bridge.md` and a new
    `docs/decisions.md` entry regardless of outcome.
  - **Gate: if (b), stop here and report back before any further work in this
    plan proceeds.** Do not continue to Stream 2 on a degraded/silent fallback.

- **Task: Build `Native_Icon_Attribute_Reader`.**
  New `includes/divi5/class-native-icon-attribute-reader.php`. Given an
  attribute array and a path, reads a `{type: 'cifd', uuid: string}` value
  independently at `desktop`, `tablet`, and `phone`, returning `null` when
  absent, malformed, or not a `cifd` type.
  - Definition of done: `tests/native-icon-attribute-reader-test.php` covers
    value present per breakpoint, value absent, malformed value (missing
    `uuid` or `type`), and non-`cifd` type ignored.
  - Validation: `composer test` green.

- **Task: Delete the superseded codepoint-based picker injector.**
  Remove `includes/builder/class-icon-picker-injector.php`,
  `includes/builder/assets/icon-picker.js`, the now-empty `includes/builder/`
  directory, and any `require_once`/instantiation of `Icon_Picker_Injector` in
  `custom-icons-for-divi.php`.
  - Definition of done: `grep -r Icon_Picker_Injector` returns nothing.
  - Validation: PHP lint clean; `composer test` and `npm test` still green.

- **Task: Build the version-pinned compatibility gate.**
  New `includes/divi5/class-native-bridge-compatibility-gate.php`. Checks the
  active Divi version against an explicit allowlist (starts as `['5.11.1']`
  once Blurb passes its gate — empty until then) before any bridge
  registration runs. Outside the allowlist: register nothing, show one
  dismissible admin notice naming the untested version and confirming the
  Custom Icon module is unaffected.
  - Definition of done: unit test for supported/unsupported version strings;
    manual check that the notice renders and is dismissible on an
    intentionally-mismatched version.
  - Validation: `composer test` green; manual admin-screen check.

### Stream 2 — Blurb module, end to end

*(Depends on Stream 1's spike resolving positively.)*

- **Task: Blurb PHP render adapter.**
  Replace the ad-hoc `imageIcon` handling in
  `includes/frontend/class-icon-renderer.php` with a dedicated adapter built
  on `Native_Icon_Attribute_Reader`, fixing the desktop-only bug in the
  archived code.
  - Definition of done: unit tests for all three breakpoints, plus a test
    confirming an absent/non-`cifd` value falls through to Divi's native
    rendering untouched.
  - Validation: `composer test` green.

- **Task: Field-wrapper integration for Blurb.**
  Wire `NativeIconPickerBridge` into Blurb's `imageIcon` field using the
  mechanism proven in Phase 0. Rework the tab markup to a full WAI-ARIA
  tabs pattern: `role="tablist"`/`tab`/`tabpanel`, `aria-selected`,
  `aria-controls`, single roving `tabindex`, left/right arrow-key navigation.
  Filter/result-count changes announce via a visually-hidden live region
  (reuse the Custom Icon module picker's existing pattern). Custom-icon
  thumbnails stay `aria-hidden`; accessible names come from icon record names.
  - Definition of done: Jest tests for tab ARIA structure, arrow-key
    navigation, and native/custom provider-switch state logic.
  - Validation: `npm test` green; manual keyboard-only and screen-reader
    walkthrough (Tab into tablist, arrow keys switch tabs, Enter/Space
    activates, tab name and selected state and result count are announced).

- **Task: Canvas live-preview parity.**
  Build the canvas DOM-marker adapter (based on the archived
  `BlurbCanvasPreviewBridge` pattern) so picking a custom icon updates the
  Builder canvas immediately, without a save/reload round-trip.
  - Definition of done: manual live-Builder proof (screenshot or short
    recording) showing selection → immediate canvas update, tested with at
    least two different custom icons and a switch back to a native Divi icon.

- **Task: Save/reload/front-end proof.**
  - Definition of done: manually save a page with a Blurb using a custom
    icon, leave the Builder, reload it, confirm selection and canvas preview
    persist; view the published front-end page and confirm correct rendering
    at all three breakpoints.

- **Task: Compatibility-gate + regression check.**
  - Definition of done: manually verify that simulating an unsupported Divi
    version disables the Blurb bridge cleanly (no broken picker UI) while the
    dedicated Custom Icon module continues to work normally.

**Blurb proof-of-concept gate (must fully pass before Stream 4 starts):**
picker renders, live canvas parity, save/reload persistence, correct
front-end render at all breakpoints, clean fail-closed behavior on an
unsupported version, and the accessibility walkthrough above.

### Stream 3 — Accessibility (cross-cutting)

Not a separate deliverable — this is the validation gate embedded in Stream
2's field-wrapper task, called out on its own because it is binding, not
optional, per project-wide accessibility requirements. The reworked ARIA tab
component becomes the shared component every subsequent module reuses
unchanged, so getting it right once here means Icon/Button/Toggle/Icon-List-
Item inherit it for free.

### Stream 4 — Repeatable per-module rollout (Icon, Button, Toggle, Icon List Item)

Once Blurb's proof-of-concept gate fully passes, repeat this template for
each remaining module, one at a time, in this order: Icon, Button, Toggle,
Icon List Item. Do not start the next module until the current one's gate is
fully checked off.

1. Verify the module's exact saved-attribute path against the Divi 5.11.1
   source directly (already confirmed for Icon: `icon.innerContent.{bp}.value`;
   Icon List Item: `icon.innerContent.{bp}.value`; Button:
   `button.{bp}.value.icon.settings`, shared `ButtonComponent` — verify during
   implementation whether this one adapter actually covers every
   button-bearing module or only the dedicated Button module; Toggle: path
   unconfirmed, verify first before writing any code).
2. Build the module's PHP render adapter on `Native_Icon_Attribute_Reader`,
   replacing the corresponding legacy codepoint-scanning code in
   `includes/frontend/class-icon-renderer.php` for Toggle/Icon List
   Item/Button.
3. Wire the field wrapper using the mechanism proven in Phase 0 (adapting for
   any per-module differences in how the option exposes its field).
4. Reuse the existing ARIA tab component as-is — no new accessibility work
   unless the module's field structure genuinely requires a variant.
5. Full proof-of-concept gate: picker, live canvas parity, save/reload,
   front-end render at all breakpoints, fail-closed regression check.
6. Unit/Jest tests mirroring Blurb's.

Definition of done per module: full proof-of-concept gate passes and is
recorded in `docs/features/native-icon-picker-bridge.md`.

### Stream 5 — Documentation

- Update `docs/features/native-icon-picker-bridge.md` from "Archived" to
  active status with the Phase 0 outcome, landing in the same commit as
  Stream 1's spike task.
- Add a `docs/decisions.md` entry once Phase 0 resolves (either direction).
- Update `CHANGELOG.md` and `docs/feature-registry.md` in the same commit as
  each module's gate passing — not batched at the end.
- Update `README.md`'s module list and status section once Blurb ships.

## Risks & Mitigations

- **No stable field-interception mechanism exists in Divi 5.11.1.** This is
  the single largest risk to the whole feature. Mitigation: Phase 0 is
  sequenced first and explicitly gates everything else; a negative finding is
  reported as such, not silently downgraded to a lesser feature.
- **Divi ships an update mid-implementation.** Mitigation: the compatibility
  gate is version-pinned from the start; a new version is added to the
  allowlist only after every already-shipped module re-passes its gate on it.
- **Button's shared `ButtonComponent` may not generalize to every
  button-bearing module.** Mitigation: verified explicitly during Button's own
  implementation step (Stream 4, item 1), not assumed from the PHP source
  read alone.
- **The canvas DOM-marker mechanism is inherently fragile** against future
  Divi canvas re-render internals. Mitigation: the manual proof-of-concept
  gate is the actual acceptance bar (automated tests cannot certify a live
  Builder iframe); the mechanism's assumptions get documented inline for
  future maintainers.
- **Toggle's attribute path is assumed, not verified.** Mitigation: Stream 4's
  template requires verification against source as its first step, before any
  Toggle code is written.
