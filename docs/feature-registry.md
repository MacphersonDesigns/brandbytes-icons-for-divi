# Feature registry

Status values:

- **Implemented:** source exists.
- **Verified:** exercised successfully in the audited environment.
- **Blocked:** source exists but does not work end to end.
- **Planned:** approved direction, not implemented.

| Feature | Status | Evidence or next gate |
| --- | --- | --- |
| Admin icon list | Verified prototype | Live screen loads the saved LED Party record. |
| Add/rename/delete/reorder endpoints | Implemented | Nonce and `manage_options` checks exist; failure UX and concurrency handling remain blocked. |
| Keyboard move controls | Implemented with blocker | Operable, but boundary focus can jump to Delete and persistence failures are announced as success. |
| SVG sanitization | Implemented with security blocker | Allowlist tests pass; raw SVG is globally exposed through Media Library before plugin sanitization. |
| Raster icon support | Implemented | PNG, JPEG, and WebP are accepted by the admin workflow. |
| Divi native picker registration | Deferred and inactive | Experimental source is retained for research, but 0.4.0 does not register native field hooks. |
| Picker thumbnail replacement | Archived prototype | The legacy native-picker experiment is not loaded. The dedicated module owns its visual picker. |
| Native-module Builder preview | Deferred and inactive | The Blurb canvas bridge is outside the active 0.4.0 product boundary. |
| Native-module server rendering | Deferred and inactive | Experimental adapters remain in source but are not bootstrapped. |
| Native-module front-end replacement | Deferred and inactive | Experimental replacement code is retained only as compatibility research. |
| Supported Custom Icon module | Verified foundation, gated | `cifd/custom-icon` registers, inserts without error, writes a selected UUID, previews it in the builder, and server-renders the current attachment URL. Save/reload and published-page acceptance remain pending. |
| Divi-style custom icon picker | Implemented polished slice | Three-column grid, full-catalog search, library filters, count, library labels, accessible pressed state, visible checkmark, selection summary, clear action, Escape-to-clear, empty state, and reduced-motion support. Live 0.4.0 visual acceptance remains. |
| Bundled Essentials library | Implemented | Sixteen original SVG icons load through a read-only manifest provider without creating Media Library records. Live Builder acceptance remains. |
| Merged icon catalog | Implemented and unit-verified | Bundled and uploaded records resolve through one UUID contract; bundled UUIDs win collisions deterministically. |
| Divi-native module link | Implemented and contract-tested | Uses Divi's `divi/link` metadata shape, anchor-root rendering, title, custom attributes, and secure new-window relation. Live Builder and published-page acceptance remain. |
| Native Design/Advanced controls | Implemented and contract-tested | Universal module metadata now matches Divi 5.6.2 Icon, including Box Shadow overlay, Background restrictions, and Advanced element/HTML groups. Live visual acceptance remains. |
| Core-picker Custom Icons provider/filter | Deferred compatibility research | Not loaded in 0.4.0. Revisit only as a separately versioned compatibility package. |
| Stable icon UUID and attachment relationship | Verified foundation | The live URL-only record migrated idempotently; the module resolver uses UUID to find the current attachment URL. |
| Individual SVG upload | Planned replacement | Move sanitization before publication; the new attachment relationship is ready, but current ingestion remains unsafe. |
| Multiple-SVG upload | Planned | Queue with progress, duplicate detection, and per-file results. |
| SVG-set ZIP import | Planned | Generic SVG archive plus optional manifest; enforce path, type, count, and expanded-size limits. |
| Original-color / multicolor SVG rendering mode | Planned | PRD scopes an explicit original-color mode alongside the default `currentColor` mask, but no render-mode control or per-icon color metadata exists yet. |
| Uploaded icon Media Library visibility | Planned | Admin-uploaded icons currently create ordinary Media Library attachments, unlike the Essentials bundled library; ties into the SVG sanitization security blocker above. |
| Native Icon adapter | Planned | First compatibility proof on About page. |
| Blurb adapter | Archived and inactive | Proof-of-concept source remains documented, but no Blurb hooks or render adapters load in 0.4.0. |
| Button adapter | Planned | Parse button decoration icon values and preserve link text semantics. |
| Toggle adapter | Planned | Handle open and closed icon values independently. |
| Icon List adapter | Planned | Parse each `divi/icon-list-item` icon value independently. |
| Named client libraries | Planned | Versioned data model with attachment IDs, UUIDs, keywords, and activation rules. |
| Export | Planned | Define a validated portable library archive after the storage migration. |
| Automated Divi integration tests | Partially verified | Jest protects metadata/runtime contracts; PHP protects resolution/render helpers; an authenticated browser fixture proves chooser registration, field rendering, selection, UUID state, canvas preview, and an unsaved server-render probe. Save/reload/front-end automation remains planned. |
| Reproducible WordPress test environment | Planned | Add `wp-env` or Docker Compose without replacing the existing Local workflow. |
| Reusable Divi icon-provider skill | Implemented | `skills/divi5-custom-icon-provider/` and packaged `dist/divi5-custom-icon-provider.skill`; reuse it for future Divi icon-provider audits and adapters. |
