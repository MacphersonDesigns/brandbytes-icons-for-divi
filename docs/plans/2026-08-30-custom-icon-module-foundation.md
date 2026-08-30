# Custom Icon module foundation plan

Date: 2026-08-30  
Status: Implemented and verified through visual selection; save/reload is the next review gate

## Outcome

Create the smallest supported Divi 5 module slice that proves our own icon
module can register, build, resolve stable icon identity, and render through
both sides of Divi's extension architecture. Keep it disabled on normal sites
until editors can select an icon through the planned visual library picker.

## Why this approach

Divi 5 extensions have two cooperating halves: a React/TypeScript definition
for the Visual Builder and a PHP definition for server/front-end rendering.
Building both now prevents the earlier failure mode where a JavaScript picker
accepted a value that PHP could not render. A temporary option gate lets us
test the real builder without presenting an incomplete module to clients.

## Micro-step 1 — Stable icon identity

What changed:

- Icon records now store a stable UUID and WordPress attachment ID.
- Existing URL-only records are upgraded by an idempotent migration.
- A resolver maps UUIDs to the attachment's current URL and treats the old URL
  only as a legacy fallback.

Where:

- `includes/class-icon-registry.php`
- `includes/admin/class-icon-library-page.php`
- `includes/divi5/class-custom-icon-asset-resolver.php`
- `tests/icon-registry-test.php`
- `tests/custom-icon-asset-resolver-test.php`

Verify:

```bash
composer test
```

Expected: 19 registry and 5 resolver assertions pass, along with the existing
sanitizer and renderer suites.

## Micro-step 2 — Divi module registration and rendering

What changed:

- Registered `cifd/custom-icon` through Divi's dependency tree.
- Added generated module metadata and a PHP render callback.
- Added initial color, size, alignment, responsive, and universal Divi design
  groups.
- Added a CSS-mask glyph using `currentColor`, preserving familiar icon color
  controls without treating the asset as arbitrary SVG content.

Where:

- `includes/divi5/class-custom-icon-module-bootstrap.php`
- `includes/divi5/class-custom-icon-module.php`
- `modules-json/custom-icon/`
- `assets/divi5/`

Verify:

```bash
npm run build
composer test
```

Expected: webpack compiles and all PHP smoke tests pass.

## Micro-step 3 — Visual Builder twin

What changed:

- Added the React/TypeScript module definition and builder placeholder.
- Added a recognizable Custom Icon module-library glyph.
- Added Jest assertions that prevent the module from regressing into a generic
  media/SVG upload control.
- Aligned JSX compilation with the classic React runtime exposed by Divi 5.6.2.

Where:

- `src/components/custom-icon/`
- `src/icons/custom-icon/`
- `src/index.ts`
- `src/module-icons.ts`
- `tsconfig.json`
- `webpack.config.js`
- `tests/js/custom-icon-module-metadata.test.js`

Verify:

```bash
npm run build
npm run test:js
```

Expected: webpack compiles and all 4 Jest assertions pass.

## Micro-step 4 — Live Divi fixture

What changed:

- Temporarily enabled the development option on the Local WordPress fixture.
- Loaded the About page in the authenticated Divi Visual Builder.
- Confirmed the plugin bundle loaded in the builder iframe.
- Confirmed **Custom Icon** appears as a standalone module in the Insert Module
  chooser without a console error.
- Closed the unsaved builder session and restored the option to `false`.

No page content was saved during this verification.

## Micro-step 5 — Searchable visual picker and server parity

What changed:

- Registered `cifd/icon-library-picker` through Divi's field-library API.
- Injected the authenticated UUID catalog before the module bundle executes.
- Added search, native-button thumbnails, selected state, clearing, an empty
  state, and a management link for authorized users.
- Corrected the live JavaScript style-declaration contract and preserved the
  mask URL in context-escaped PHP output.

Live verification:

- The Content panel displayed the LED Party icon as a named choice.
- Selection wrote the expected UUID into Divi's edit-post store and immediately
  produced one visible custom glyph in the canvas.
- Search produced a no-results state; Escape cleared search and retained focus.
- An unsaved PHP block render contained the module class, UUID, and current
  HTTPS attachment URL.

No builder content was saved. The feature option was restored to `false`.

## Review gate and next slice

The foundation now proves registration, host-runtime compatibility, visual
selection, builder state, and an unsaved server render. It does not yet make
the module client-ready. The next slice must add:

1. named-library records and library filtering;
2. save/reload persistence on a disposable Divi fixture;
3. published-page parity for a selected icon;
4. automated interaction coverage plus manual keyboard verification; and
5. safe pre-publication SVG ingestion before the feature gate is removed.

The experimental option stays off until those checks pass. ZIP/set ingestion
and the native Blurb provider remain later, separate modules so they cannot
destabilize this supported baseline.
