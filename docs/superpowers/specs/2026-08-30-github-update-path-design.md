# GitHub-based plugin update path: design

Date: 2026-08-30
Status: Approved design, pending implementation plan

## Goal

Let sites running this plugin get native in-dashboard WordPress update
notices ("Update available" + one-click update, same as a WordPress.org
plugin) sourced from GitHub Releases on
`https://github.com/MacphersonDesigns/brandbytes-icons-for-divi`, so the
author can keep distributing and testing new versions on real sites while
developing, before the plugin is ever submitted to the WordPress.org plugin
directory.

## Context

The plugin currently has no update-checker code and no release automation.
`bin/build-release-zip.sh` builds a clean, dev-file-free zip locally (via
`.distignore`) but nothing publishes it anywhere, and there are no git tags
or GitHub Releases yet. The plugin's version number is duplicated in three
unsynchronized places: `package.json`, the `Version:` header in
`custom-icons-for-divi.php`, and the `Stable tag:` line in `readme.txt`. The
GitHub repo is public, so update checks and release-asset downloads need no
authentication token.

## Non-goals

- Submitting to the WordPress.org plugin directory (explicitly deferred by
  the user).
- Supporting private-repo authentication (repo is public).
- Beta/pre-release update channels. Only tagged, fully-built releases are
  ever offered as updates; this is not addressed further because the
  underlying library's default behavior (stable releases only) already
  matches what's wanted here.

## Architecture

Three independent pieces, each usable on its own but designed to work
together as one release flow:

1. **Update checker** (runtime, ships to every site) — a vendored copy of
   [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker)
   (PUC) v5, wired up in the main plugin file, configured to read GitHub
   Release assets rather than raw tag source archives.
2. **Release automation** (CI, author-side only) — a new GitHub Actions
   workflow that builds the release zip and publishes it as a GitHub Release
   whenever a version tag is pushed.
3. **Version bump script** (local dev tool) — a script that rewrites all
   three version locations together, so tags, the plugin header, and
   `readme.txt` can't drift out of sync.

### Data flow

```
dev runs `npm run bump 0.4.9`
  -> package.json, plugin header, readme.txt all become 0.4.9
dev commits, tags `v0.4.9`, pushes tag
  -> release.yml workflow runs:
       - verifies tag == package.json == header == readme.txt Stable tag
       - composer test && npm test && npm run build (via build-release-zip.sh)
       - creates GitHub Release "v0.4.9", attaches the built zip
existing site's wp-admin update check (WP's normal cron-driven cycle)
  -> PUC queries the GitHub Releases API for the repo
  -> finds v0.4.9 > installed 0.4.8
  -> downloads the *release asset* zip (not GitHub's tag source archive)
  -> WordPress shows "Update available", installs it like any other plugin
```

## Components

### 1. Update checker

- Add `plugin-update-checker/plugin-update-checker` via Composer
  (`composer require yahnis-elsts/plugin-update-checker`), vendored into
  `vendor/`.
- `vendor/` is added to `.gitignore` (not committed to the dev repo, since
  Composer installs it) but explicitly **excluded from `.distignore`** —
  the shipped release zip must contain it, since client sites run the
  checker from their own copy of the plugin, not from this repo.
- In `custom-icons-for-divi.php`, after the plugin header and existing
  bootstrap, add:

  ```php
  require_once __DIR__ . '/vendor/plugin-update-checker/plugin-update-checker.php';

  $cifd_update_checker = Puc_v5_Factory::buildUpdateChecker(
      'https://github.com/MacphersonDesigns/brandbytes-icons-for-divi',
      __FILE__,
      'custom-icons-for-divi'
  );
  $cifd_update_checker->getVcsApi()->enableReleaseAssets();
  ```

  `enableReleaseAssets()` is required: without it, PUC defaults to GitHub's
  auto-generated tag source archive, which would ship `node_modules`, `src`,
  `tests`, and everything else `.distignore` normally strips, straight to
  client sites.
- The third argument (`'custom-icons-for-divi'`) must exactly match the
  plugin's existing folder/slug, consistent with the constraint already
  documented in `bin/build-release-zip.sh` about not disrupting activation
  state on existing installs.
- No admin UI or settings screen is added — WordPress's built-in Plugins
  page and update notices are the entire user-facing surface, matching a
  WordPress.org-hosted plugin's normal behavior.

### 2. Release automation

New file `.github/workflows/release.yml`, triggered on push of a tag
matching `v*`:

1. Checkout, set up PHP (matching the CI workflow's PHP matrix baseline)
   and Composer, set up Node 20 with npm cache (matching `ci.yml`).
2. **Version-sync guard**: strip the leading `v` from
   `${{ github.ref_name }}` and fail the job with a clear error if it does
   not exactly match all of:
   - `package.json` `"version"`
   - `custom-icons-for-divi.php` `Version:` header
   - `readme.txt` `Stable tag:`

   This is a shell/Node check run as its own step, before any build work,
   so a version mismatch fails fast and cheaply.
3. Run `bin/build-release-zip.sh` (already runs `composer test`, `npm test`,
   and `npm run build` before packaging, so the existing quality gate is
   reused unchanged rather than duplicated in the workflow).
4. Create a GitHub Release for the pushed tag and attach the built
   `build/custom-icons-for-divi-<version>.zip` as a release asset (e.g. via
   `softprops/action-gh-release`). Release title/body can default to the
   tag name; changelog content continues to live in `readme.txt` /
   `CHANGELOG.md` as it does today — this workflow does not generate
   release notes.

If any step fails (tests, version mismatch, build), no Release is created,
so a broken build can never reach the update checker.

### 3. Version bump script

`bin/bump-version.sh <new-version>`, exposed as `npm run bump <new-version>`
(via a package.json script that forwards its argument), rewrites in place:

- `package.json` → `"version": "<new-version>"`
- `custom-icons-for-divi.php` → `Version: <new-version>` (the header line
  only)
- `readme.txt` → `Stable tag: <new-version>` (the header line only)

It does **not** touch `readme.txt`'s `== Changelog ==` section or
`CHANGELOG.md` — changelog entries stay a manual, deliberate step. The
script performs a plain in-place substitution keyed on each file's known
label (`"version":`, `Version:`, `Stable tag:`) and exits non-zero if any of
the three files doesn't contain the expected label, so it fails loudly
rather than silently doing nothing if a file's format ever changes.

A short note gets added to `CONTRIBUTING.md` (which already documents
contributor-facing process) instructing that version bumps always go
through `npm run bump <version>`, never hand-edited, and that a release is
cut by bumping, committing, tagging `v<version>`, and pushing the tag.

## Error handling / edge cases

- **Tag pushed without a matching version bump**: `release.yml`'s
  version-sync guard fails the workflow before any build or release is
  created. No broken release reaches users.
- **Tests or build fail on a tagged commit**: `bin/build-release-zip.sh`
  exits non-zero, the workflow step fails, no Release is created.
- **Re-pushing the same tag**: not specifically handled; standard GitHub
  Actions/Release behavior applies (a duplicate tag push re-runs the
  workflow; creating a Release that already exists is treated as a normal
  `gh`/action failure). Not a concern given tags are only ever created via
  the bump-then-tag flow.
- **Client site with no internet access to github.com**: PUC's update check
  simply fails silently for that cycle (same failure mode as WordPress.org
  update checks failing), no error surfaced beyond the normal "couldn't
  check for updates" state WordPress already handles.

## Testing

- Manual end-to-end verification (this is CI/release tooling, not
  user-facing application code, so no new automated test suite is added):
  1. Run `npm run bump 0.4.9`, confirm all three files updated correctly.
  2. Commit, tag `v0.4.9`, push tag; confirm `release.yml` runs green and a
     GitHub Release with the correct zip asset appears.
  3. Install the previous version (0.4.8) on a real WordPress test site,
     confirm the Plugins page shows an "Update available" notice pointing
     at 0.4.9 with a working one-click update.
  4. Intentionally bump only `package.json` (skip the header/readme) and
     tag it, confirming the workflow's version-sync guard fails the run.
- Existing `composer test` and `npm test` suites already run inside
  `bin/build-release-zip.sh` and therefore inside the new release workflow;
  no changes needed there.
