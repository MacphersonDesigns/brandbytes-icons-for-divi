# Security and public-release readiness audit

Date: 2026-08-30  
Audited plugin version: 0.4.7  
Scope: PHP runtime, WordPress administration actions, SVG ingestion, Divi 5
Builder code, build dependencies, repository history, generated assets, and
public-repository hygiene.

## Executive summary

The plugin has a sound security foundation for continued private development:
all four state-changing AJAX actions require both `manage_options` and a valid
nonce, output is escaped at the primary render boundaries, the Builder catalog
is emitted only while its authenticated Builder script is enqueued, and the
repository scan found no credentials or private keys.

Version 0.4.8 resolves the primary SVG upload blocker identified by this audit.
SVGs are now sanitized in PHP's private temporary upload location and replaced
atomically before WordPress can publish the file or create an attachment.

No critical vulnerabilities were found. The original findings and their
current remediation status are documented below.

## Positive controls verified

- `includes/admin/class-icon-library-page.php:184-187` centralizes the
  `manage_options` capability check and AJAX nonce verification for add, rename,
  delete, and reorder operations.
- There are no `wp_ajax_nopriv_*` handlers or public custom REST routes.
- `includes/class-icon-sanitizer.php:33-47` rejects DTD/entity declarations,
  disables external entity resolution, and parses with `LIBXML_NONET`.
- `includes/class-icon-sanitizer.php:63-126` uses element and attribute
  allowlists, strips event handlers and namespaced links, restricts `url()` to
  same-document fragments, and removes comments and processing instructions.
- `includes/divi5/class-custom-icon-module.php:62-75` escapes the icon UUID and
  mask URL at their HTML attribute boundary.
- `includes/divi5/class-custom-icon-module.php:103-123` passes saved links
  through Divi shortcode resolution and `esc_url()`, and adds
  `noopener noreferrer` to new-window links.
- `includes/divi5/class-custom-icon-module-bootstrap.php:120-151` sanitizes the
  Builder catalog and uses WordPress JSON encoding with all HTML-sensitive
  characters escaped.
- The source and history scans found no passwords, tokens, API keys, private
  keys, or authorization headers. References to `app-development.local` and a
  local Divi source path are development metadata, not credentials.

## Findings

### CIFD-SEC-001

- **Severity:** Medium; resolved in 0.4.8
- **Location:** `includes/admin/class-icon-library-page.php:31-45` and
  `includes/admin/class-icon-library-page.php:126-142`
- **Rule:** Uploaded active content must be made safe before it receives a
  public URL.
- **Evidence:** The `upload_mimes` filter allows SVG files for every
  `manage_options` user. Sanitization happens later, only inside
  `ajax_add_icon()`, after WordPress has already written the original file into
  the public uploads directory.
- **Impact:** A privileged user can upload a malicious SVG and leave it at a
  same-origin public URL by cancelling the icon workflow, selecting another
  file, or encountering an AJAX failure. Directly opened SVGs can be active
  browser content. The required capability reduces exploitability but does not
  make a raw public SVG safe, particularly on sites with multiple
  administrators.
- **Fix:** Remove the global raw-SVG permission. Recommended architecture:
  accept SVGs through a plugin-owned upload/import controller, write them to a
  private temporary file, validate and sanitize there, then atomically publish
  only the sanitized derivative. If Media Library integration is retained,
  sanitize in the upload prefilter before WordPress publishes the attachment and
  reject the upload on any sanitizer or write failure.
- **Resolution:** `includes/admin/class-icon-upload-policy.php` now sanitizes
  and atomically replaces the private upload temp file through
  `wp_handle_upload_prefilter`. Any failure rejects the upload before publish.
- **Mitigation:** Until fixed, use the plugin only with trusted administrators
  and do not advertise general SVG Media Library support.
- **False-positive notes:** WordPress administrators often possess other
  powerful capabilities. That limits privilege escalation, but it does not
  remove the stored-content risk or meet public-plugin secure-upload
  expectations.

### CIFD-SEC-002

- **Severity:** Low; partially resolved in 0.4.8
- **Location:** `includes/class-icon-sanitizer.php:28-126`
- **Rule:** Complex attacker-influenced SVG should have resource limits and use
  a maintained sanitizer when possible.
- **Evidence:** The custom sanitizer has a careful allowlist but no explicit
  byte-size, node-count, depth, path-complexity, or processing-time limit. Its
  security behavior is maintained entirely in this repository.
- **Impact:** A trusted administrator could accidentally upload an extremely
  complex document that consumes memory or CPU. Future multi-file or ZIP import
  increases this risk substantially.
- **Fix:** Enforce per-file byte, XML depth/node, path-data, icon-count, expanded
  archive-size, and execution-time limits. Evaluate a maintained WordPress SVG
  sanitizer such as `enshrined/svg-sanitize`, wrapped behind the existing
  sanitizer interface so it remains replaceable and testable.
- **Resolution:** 0.4.8 enforces file-byte, element-count, nesting-depth, and
  per-attribute limits. Archive-wide limits and evaluation of a maintained
  sanitizer remain requirements for the future SVG-set importer.
- **Mitigation:** Keep imports single-file and administrator-only until those
  limits exist.
- **False-positive notes:** WordPress and PHP upload limits provide partial byte
  limits, but they do not bound XML complexity or future archive expansion.

### CIFD-SEC-003

- **Severity:** Low; resolved in 0.4.8
- **Location:** `src/components/custom-icon/edit.tsx:20-47`
- **Rule:** Saved URL values should be normalized before being assigned to a
  live Builder anchor.
- **Evidence:** The PHP renderer applies `esc_url()`, while the React Builder
  preview assigns the trimmed saved value directly to `href`.
- **Impact:** A malformed or active-scheme URL inserted into block attributes by
  a privileged editor or external content migration could become navigable in
  the Builder even though the published PHP output rejects it.
- **Fix:** Add a shared client URL-normalization helper that permits Divi's
  supported relative, `http:`, `https:`, `mailto:`, and `tel:` forms and rejects
  active schemes before populating `htmlAttrs.href`.
- **Resolution:** `NormalizeBuilderLinkUrl()` now enforces that scheme allowlist
  before Builder anchor rendering.
- **Mitigation:** Divi's URL field and editor privileges reduce exposure; avoid
  clicking unknown migrated links inside the Builder.
- **False-positive notes:** Normal UI-entered links are expected to be benign,
  and the public server-rendered page already applies WordPress URL escaping.

### CIFD-SEC-004

- **Severity:** Low; data integrity
- **Location:** `includes/class-icon-registry.php:61-76` and
  `includes/class-icon-registry.php:129-183`
- **Rule:** Persistent writes should detect failure and avoid lost concurrent
  updates.
- **Evidence:** Registry mutations use option read-modify-write sequences,
  generally do not inspect `update_option()` results, and allocate the next
  numeric ID in a separate option write.
- **Impact:** Two near-simultaneous administrator actions can overwrite one
  another or reuse an ID. A database write failure can be reported to the UI as
  success.
- **Fix:** Move writes behind a repository transaction/lock strategy, store a
  schema revision, and propagate persistence failures to the AJAX response.
- **Mitigation:** Avoid simultaneous library editing by multiple administrators.
- **False-positive notes:** The current single-user development workflow makes
  collisions unlikely, but a public plugin cannot assume it permanently.

## Dependency findings

`npm audit` reported four moderate advisories inherited through the development
only `@divi/types` dependency: vulnerable `showdown` and `uuid` versions arrive
through `@wordpress/blocks`. These packages are build/type dependencies and are
not bundled into the 14.5 KB production Builder script; Webpack marks React,
WordPress, and Divi runtime packages as externals.

The audit's suggested remediation is a forced downgrade from the currently used
Divi types contract. Do not apply that downgrade blindly. Track the upstream
package and re-run `npm audit` during releases. There are no production npm
dependencies and no high or critical npm advisories in this audit.

## Public repository readiness

The source scan did not find sensitive values, so the code can become public
after the following gates are completed:

1. Fix CIFD-SEC-001 before publishing a release ZIP.
2. Rotate the WordPress Application Password that was shared during the private
   development conversation. It is not present in this repository, but shared
   credentials should still be treated as exposed.
3. Commit or deliberately exclude the current large untracked working tree.
   The repository history currently stops at the prototype while most of the
   0.4.7 implementation is uncommitted.
4. Add a root `LICENSE` file. Declaring GPL headers and Composer metadata is not
   a substitute for shipping the license text.
5. Document the provenance and copyright owner of every SVG in
   `assets/icon-libraries/essentials/`. A manifest license field alone does not
   prove the project has the right to redistribute the artwork.
6. Add `SECURITY.md`, `CONTRIBUTING.md`, a support policy, compatibility table,
   trademark/non-affiliation language for Divi/Elegant Themes, and a reproducible
   release-ZIP build.
7. Add CI using `npm ci`, the JavaScript and PHP tests, PHP linting, module JSON
   validation, a secret scan, and a dependency audit.
8. Produce and install-test a clean ZIP that excludes `node_modules`, local
   development documents that are not intended for users, source maps, and
   internal skill packages unless intentionally distributed.

## Verification evidence

- Production Webpack build: passed.
- Jest metadata suite: 13/13 passed.
- PHP suites: 61/61 assertions passed.
- PHP syntax lint: passed for all PHP files.
- Composer validation: valid in strict mode.
- `git diff --check`: passed.
- Secret-pattern scan: no credentials found; only intentional security test
  strings and development-host references matched.
- npm advisory scan: zero critical, zero high, four moderate development-tree
  advisories.

## Recommended ownership and naming

Use the two brands at different layers:

- **Product family:** BrandBytes. Custom icon libraries are reusable brand
  assets, and this creates a natural future family for fonts, colors, design
  tokens, and client design systems.
- **Publisher/developer:** Code & Canvas. It clearly communicates the combined
  design and engineering practice behind the plugin.

A coherent public identity would be **BrandBytes Icons for Divi**, published by
**Code & Canvas**. The repository can live in a Code & Canvas organization while
the PHP namespace, option prefix, block name, and persisted UUID contract remain
stable. Do not rename technical identifiers merely for cosmetic branding; use a
documented migration only if a technical rename becomes necessary.

This split gives BrandBytes room to become a recognizable product suite without
making Code & Canvas disappear as the accountable software publisher.
