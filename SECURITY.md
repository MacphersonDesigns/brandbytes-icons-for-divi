# Security policy

## Supported versions

This project is still in pre-1.0 development. Security fixes are applied to the
latest release line only.

| Version | Supported |
| --- | --- |
| Latest 0.x release | Yes |
| Older 0.x releases | No |

## Reporting a vulnerability

Please do not open a public issue for a suspected vulnerability.

Use the repository's **Private vulnerability reporting** feature under its
Security tab. Include:

- the affected plugin and Divi versions;
- the minimum WordPress role or capability needed;
- reproduction steps and a minimal proof of concept;
- expected and observed behavior;
- any suggested remediation, if known.

The maintainers will acknowledge a complete report within five business days.
Because this is a pre-1.0 project, remediation timing depends on severity and
whether the report affects a published release or development-only code.

## Security boundaries

- Icon-library changes require `manage_options` and a valid WordPress nonce.
- SVG uploads are sanitized in PHP's private upload temporary location before
  WordPress can publish the file or create a Media Library attachment.
- The SVG sanitizer rejects active content and applies explicit size and
  complexity limits.
- The plugin does not expose an unauthenticated custom REST or AJAX write API.
- Anything shipped in the Builder JavaScript bundle is public and must never
  contain credentials.

## Scope notes

Divi and WordPress core vulnerabilities should be reported to their respective
vendors. Reports are in scope here when this plugin introduces or meaningfully
amplifies the issue.
