---
name: divi5-custom-icon-provider
description: Research, design, implement, and verify reusable custom icon providers for Divi 5, including Visual Builder fields, native icon-picker compatibility, SVG library catalogs, per-module PHP render adapters, and fail-closed version detection. Use when extending Divi 5 icon controls, adding client-owned SVG libraries, integrating Blurb/Icon/Button/Toggle/Icon List icons, or auditing a Divi update for icon-provider compatibility.
---

# Divi 5 Custom Icon Provider

## Operating rules

1. Inspect the installed Divi version and source contracts before coding. Use
   local Divi files for implementation facts and MCP/browser probes for runtime
   facts; never edit Divi's files.
2. Decide between a supported standalone module and a native-picker bridge.
   Build the standalone module as the reliable path; treat native picker work
   as an optional, versioned compatibility adapter.
3. Register builder data before Divi's icon-library bundle evaluates. Do not
   infer icon identity from button text or rendered HTML.
4. Store stable plugin-owned icon IDs plus attachment identity. Resolve URLs at
   render time so migrations, CDNs, and protocol changes remain safe.
5. Treat each core module as a separate contract. Start with Blurb, then prove
   Icon, Button, Toggle, and Icon List independently.
6. Keep SVG ingestion sanitized, bounded, and private-by-default. Reject unsafe
   archive paths and report partial failures clearly.
7. Preserve Divi's design semantics with `currentColor` mask rendering for
   monochrome icons; make original-color rendering an explicit separate mode.
8. Fail closed when a private Divi contract changes. The supported standalone
   module must continue working even when the bridge is disabled.

## Verification loop

For every bridge change:

1. Inspect source and record the exact hook, field name, saved attribute path,
   and PHP renderer behavior in the project audit.
2. Add a focused unit/integration test for catalog identity or rendering.
3. Build the Visual Builder bundle and run PHP/JS tests.
4. Run an authenticated builder smoke test: open the target module, filter to
   Custom Icons, search, select, change a design value, reload, and inspect
   server output without saving exploratory content.
5. Test the unsupported-version path and confirm only the bridge is suppressed.
6. Update the audit, decision log, feature registry, and changelog in the same
   change set.

Read [references/divi-contracts.md](references/divi-contracts.md) when exact
Divi 5.6.x hooks, file paths, or module attribute paths are needed.
