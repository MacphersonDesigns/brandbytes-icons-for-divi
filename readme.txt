=== BrandBytes Icons for Divi ===
Contributors: (add your wordpress.org username here)
Tags: divi, icons, svg, icon library, divi builder
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.4.9
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manage a reusable, site-owned SVG icon library and use it from a dedicated Custom Icon module in the Divi 5 Builder.

== Description ==

BrandBytes Icons for Divi lets you build a reusable, site-owned icon library
and use it from a dedicated **Custom Icon** module in the Divi 5 Visual
Builder, instead of relying only on Divi's bundled icon font.

**This plugin requires the Divi Builder or Divi theme, version 5.x, sold
separately by Elegant Themes. It does not include Divi and is not affiliated
with, endorsed by, or sponsored by Elegant Themes. Divi is a trademark of
Elegant Themes, Inc.**

= Features =

* A dedicated **Custom Icon** module that appears in the Divi 5 Visual
  Builder module chooser, alongside Divi's native Icon module.
* A searchable, library-filterable visual icon picker with keyboard support.
* A bundled **Essentials** library of 16 original interface icons, available
  immediately with no Media Library uploads required.
* Upload your own SVG icons through a secure, sanitized upload pipeline: SVGs
  are sanitized in PHP's private temporary upload location and only published
  after sanitization succeeds.
* Divi-style link settings on the icon module: URL, same/new-window behavior,
  title, and custom link attributes, with `noopener noreferrer` applied
  automatically to new-window links.
* Icons are referenced by a stable UUID rather than a URL, so replacing the
  underlying SVG file does not break existing pages.

= Security =

* All admin actions that change the icon library require the `manage_options`
  capability and a valid WordPress nonce.
* Uploaded SVGs are parsed with external entity resolution disabled and DTD
  declarations rejected, then reduced to an element/attribute allowlist before
  being written to the Media Library.
* The sanitizer enforces file-size, element-count, nesting-depth, and
  attribute-length limits.
* No data is sent to any third-party service. The plugin does not phone home.

See the full [security policy](https://github.com/MacphersonDesigns/brandbytes-icons-for-divi/blob/main/SECURITY.md)
on GitHub for how to report a vulnerability.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/` or install it through the
   WordPress admin's **Plugins → Add New** screen.
2. Activate the plugin through the **Plugins** screen.
3. Make sure Divi 5.x is installed and active.
4. Open the Divi Visual Builder and add the **Custom Icon** module from the
   module chooser, or manage your icon library from the plugin's admin screen.

== Frequently Asked Questions ==

= Does this plugin work without Divi? =

No. The Custom Icon module and its Visual Builder picker require Divi 5.x.
The icon-library admin screen itself does not require Divi, but has no
purpose without it.

= Does this plugin work with Divi 4? =

It has not been tested against Divi 4 and does not register a module there.

= Is uploaded SVG content safe? =

Uploaded SVGs are sanitized before publication using an element/attribute
allowlist and explicit resource limits, and are rejected outright if
sanitization fails. See the Security section above.

= Where do I report a bug or request a feature? =

On the [GitHub repository](https://github.com/MacphersonDesigns/brandbytes-icons-for-divi).

== Screenshots ==

1. The Custom Icon module's visual picker inside the Divi 5 Visual Builder.
2. The icon-library admin screen.

== Changelog ==

= 0.4.9 =
* Added a GitHub-based update path: sites now get an in-dashboard update
  notice and one-click update sourced from GitHub Releases, ahead of a
  WordPress.org directory submission.

= 0.4.8 =
* Sanitize SVGs in PHP's private upload temp file before WordPress can
  publish them or create an attachment, closing the primary pre-release
  security finding.
* Enforce sanitizer limits for file size, element count, nesting depth, and
  attribute length.
* Reject active URL schemes in the live Builder link preview.
* Add GPL v2 license, security and contribution policies, and Essentials
  artwork provenance.

= 0.4.0 - 0.4.7 =
* Added the bundled Essentials icon library and merged bundled/uploaded icon
  catalog.
* Added the dedicated `cifd/custom-icon` Divi 5 module: Visual Builder
  picker, Divi-native link settings, and matching PHP/React rendering
  contracts.
* Matched Divi's native Icon module Content, Design, and Advanced sidebar
  behavior.

See the [full changelog](https://github.com/MacphersonDesigns/brandbytes-icons-for-divi/blob/main/CHANGELOG.md)
on GitHub for the complete version history.

== Upgrade Notice ==

= 0.4.8 =
Resolves a security finding around uploaded SVG files. Upgrading is
recommended for all sites.
