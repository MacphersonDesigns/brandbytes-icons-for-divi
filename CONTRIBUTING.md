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
