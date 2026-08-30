# Visual icon library picker and SVG-set import

Status: Bundled library filters implemented; uploaded named libraries and import pending  
Owner: Custom Icon module and Icon Library features

## Outcome

An editor choosing an icon in the Custom Icon module should get the useful part
of Divi's default icon experience: a fast visual grid where the icon itself is
the primary information. The editor should never need to paste a URL, remember
a filename, or understand SVG markup.

The feature is deliberately distinct from Divi's SVG module. An SVG module
places one piece of vector artwork. This library models reusable icons with
stable identity, searchable metadata, client-library membership, icon render
modes, and safe reuse across many modules.

## Module picker experience

### Implemented through version 0.4.0

The feature-gated Custom Icon module now receives an authenticated builder
catalog keyed by UUID and renders a native-button thumbnail grid in its Content
settings. Live Divi 5.6.2 verification proved search, a no-results state,
selection via `aria-pressed`, clearing, Escape-to-clear with search focus, UUID
updates in Divi's attribute store, and immediate canvas preview. The selected
LED Party SVG also passed an unsaved PHP `do_blocks()` render probe using its
current HTTPS attachment URL.

Version 0.4.0 adds a read-only **Essentials** provider containing 16 original
SVG icons. These ship inside the plugin, appear immediately, and do not create
Media Library clutter. A merged catalog presents those records alongside the
option-backed uploaded registry, whose records truthfully remain grouped under
**Custom Icons** until the editable named-library schema is implemented.

The picker now derives an **All libraries** filter and one accessible pressed
button per provider. Search covers icon name, library, category, keywords, and
MIME type. Selection continues to save only the stable UUID; changing or
clearing an icon preserves unrelated module values such as its link settings.
The bundled Home icon is the insertion default. The selection action says
**Reset to Home** rather than clearing to a taller empty placeholder, so the
module retains a stable canvas footprint while the editor experiments.

The module's **Icon** field opens a dialog or popover with:

- a search field that matches name, keywords, and library name;
- an **All libraries** view plus one filter for each active client library;
- a responsive thumbnail grid with icon name available visually or on demand;
- a strong selected state that does not rely on color alone;
- a useful empty state and a link to library management for authorized users;
- recent selection retained while the module settings remain open; and
- immediate Visual Builder preview after selection.

The saved module value is an icon UUID, not a URL or array position. The module
resolves the current sanitized asset through the library repository.

### Keyboard and assistive technology contract

- Opening the picker moves focus to its heading or search field.
- Tab reaches every control in a predictable order.
- Results are real buttons with accessible names; selection is exposed through
  `aria-pressed` or an equivalent native pattern.
- Arrow-key grid navigation may be added only if it follows a tested composite
  widget pattern. Normal Tab navigation remains available unless the grid fully
  implements roving tabindex.
- Escape closes the picker, and close/selection returns focus to the Icon field
  trigger.
- Search result counts, loading, import completion, and failures are announced
  without stealing focus.
- Virtualization or lazy loading must retain stable accessible names and focus.

## Library ingestion

### 1. Single SVG

Use for a one-off brand or interface icon. The SVG is validated, sanitized,
normalized, previewed, named, and assigned to a selected client library before
its public derivative is created.

### 2. Multiple SVG files

Use for a small set selected from the file chooser or dropped into the library
manager. Each file receives an independent progress/result row. The user can
edit derived names and metadata before completing the import.

### 3. SVG-set ZIP

Use for complete libraries. Version 1 accepts:

```text
client-icons.zip
  manifest.json       # optional
  icons/
    home.svg
    services.svg
    contact.svg
```

Without a manifest, the importer derives a human-readable name from each
filename. With a manifest, it can accept stable external IDs, names, keywords,
categories, and the relative SVG filename. The manifest schema must be
versioned and documented before implementation.

Provider-specific packages such as IcoMoon or Font Awesome exports are not
implicitly trusted. They can be added later through isolated adapters that
convert into the same internal import records.

## Import safety and guardrails

- Reject archive path traversal, symlinks, nested archives, executables, and
  unexpected file types.
- Limit compressed size, expanded size, SVG count, per-file size, nesting
  depth, and processing time.
- Validate content as SVG/XML rather than trusting its extension or MIME value.
- Sanitize into a private temporary location, then publish the safe derivative
  atomically; never expose the raw upload URL.
- Reject external resources, scripts, event handlers, unsafe URLs, and entity
  declarations.
- Hash normalized safe content for duplicate detection while keeping names and
  library membership editable.
- Require capability and nonce checks for starting, resuming, cancelling, and
  committing an import.
- Record who imported the set, its source filename, timestamps, totals, and
  per-file outcomes without storing sensitive local paths.

## Partial failures

Importing 100 icons should not become an all-or-nothing mystery. The importer
should stage valid results, identify rejected and duplicate files, and let the
administrator review the report before committing the valid subset. Cancelling
before commit removes staged derivatives. A failed final commit leaves the
previous library unchanged.

Expected completion summary:

```text
96 icons ready
2 duplicates skipped
1 unsafe SVG rejected
1 malformed SVG rejected
```

The summary is visible, announced as a status message, and downloadable as a
CSV or JSON report.

## Performance requirements

- Store generated preview metadata so the picker does not parse full SVG files
  every time it opens.
- Fetch results in pages and debounce search.
- Load thumbnail assets lazily while keeping button labels immediately
  available.
- Cache library/catalog responses by schema revision and invalidate them after
  successful library changes.
- Do not enqueue admin/import code on normal front-end pages.

## Verification gates

- Import one SVG, several SVGs, and a representative ZIP set.
- Search by name, keyword, and library; verify empty and no-result states.
- Select an icon, save the page, reload the builder, and verify the same UUID
  and preview remain selected.
- Test monochrome and original-color output on desktop, tablet, phone, and
  hover states.
- Exercise duplicate, malformed, unsafe, oversized, and path-traversal fixtures.
- Complete the picker and import flows with keyboard only, then perform manual
  screen-reader checks after automated semantics/focus tests pass.

## Relationship to native Divi pickers

The supported Custom Icon module owns this full picker and is the production
baseline. A separate compatibility bridge may expose the same catalog through
a **Custom Icons** provider/filter in native icon-aware modules. That bridge is
specified in [`native-icon-picker-bridge.md`](native-icon-picker-bridge.md) and
must never weaken or block the supported module.
