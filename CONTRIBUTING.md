# Contributing

Thank you for helping improve BrandBytes Icons for Divi.

## Development principles

- Keep each feature modular and document its data and rendering contracts.
- Preserve saved UUIDs, the `cifd/custom-icon` block name, and existing option
  keys unless a tested migration accompanies the change.
- Treat Divi's undocumented internals as version-specific compatibility code.
- A keyboard workflow is required; drag-and-drop may supplement it but cannot
  replace it.
- Never commit credentials, client SVG sets, private site exports, or licensed
  artwork without explicit redistribution rights.

## Local setup

Requirements: PHP 7.4+, Composer, Node 18+, and npm 9+.

```bash
composer install
npm ci
npm run build
composer test
npm test
```

Green currently means all PHP smoke assertions pass, all Jest metadata tests
pass, Webpack produces `assets/divi5/`, and PHP lint reports no syntax errors.

## Releasing

The plugin version number is duplicated in `package.json`, the `Version:`
header in `custom-icons-for-divi.php`, and the `Stable tag:` line in
`readme.txt`. Never hand-edit these. Always bump via:

```bash
npm run bump 0.4.9
```

Then update the `readme.txt` Changelog and `CHANGELOG.md`, commit, tag, and
push:

```bash
git commit -am "Release 0.4.9"
git tag v0.4.9
git push --tags
```

Pushing the tag triggers `.github/workflows/release.yml`, which verifies all
three version locations match the tag, builds the release ZIP via
`bin/build-release-zip.sh`, and publishes it as a GitHub Release. Sites
running the plugin see the update via the in-dashboard update checker
(`includes/class-updater.php`), which reads GitHub Releases directly — no
WordPress.org submission required for this to work.

## Pull requests

Keep pull requests small enough to verify independently. Include:

1. what changed and why;
2. exact manual reproduction or acceptance steps;
3. automated tests for security, persistence, or rendering behavior;
4. documentation and changelog updates in the same pull request;
5. screenshots for visible Builder or WordPress admin changes.

Do not include built assets without also including the matching source change.
Do not reformat unrelated files.

## Security changes

Security reports follow [SECURITY.md](SECURITY.md). Security fixes should address
one finding at a time and include a regression test that fails before the fix.

## Trademark

Divi is a trademark of Elegant Themes, Inc. This project is independently
developed and is not affiliated with, endorsed by, or sponsored by Elegant
Themes.
