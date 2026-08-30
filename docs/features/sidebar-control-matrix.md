# Custom Icon sidebar control matrix

Status: Contract-aligned with installed Divi 5.6.2; live visual acceptance ongoing  
Owner: `cifd/custom-icon`

## Why this matrix exists

A control appearing in Divi's sidebar does not prove it affects the canvas or
published page. Each supported control needs a saved attribute contract plus a
Builder and server-render path. This matrix is the acceptance checklist for
changes to `src/components/custom-icon/module.json`.

Unlike Divi's bundled modules, a third-party module must explicitly declare
`settings.advanced: "auto"` to ask the Builder to compose the Advanced panel.
The built-in Icon JSON can omit that flag because Divi augments its own module
definitions internally; copying that omission leaves an extension tab empty.

## Content controls

| Control | Saved attribute | Builder and front-end path |
| --- | --- | --- |
| Icon library | `iconReference.innerContent` | Custom field updates the UUID; Builder catalog and PHP asset resolver resolve the current SVG. |
| Link | `iconReference.innerContent` | Divi `divi/link` field; Builder and PHP change the module root to an anchor. |
| Link custom attributes | `module.decoration.attributes`, target `iconLink` | Divi attribute utilities merge them into the anchor. |

## Design controls

| Group | Metadata/render owner |
| --- | --- |
| Icon color and size | `icon.advanced`; explicit `divi/common` styles in TypeScript and PHP. |
| Icon alignment | `icon.advanced.align`; matching native margin/text declarations in TypeScript and PHP. |
| Background | Universal `module.decoration.background`; Divi `elements.style()` in Builder and PHP. Video and unsupported image-position controls are hidden to match native Icon. |
| Border | Universal `module.decoration.border`; Divi `elements.style()`. |
| Box Shadow | Universal `module.decoration.boxShadow`; Divi `elements.style()` plus the required overlay component setting. |
| Filters | Universal `module.decoration.filters`; Divi `elements.style()`. |
| Transform | Universal `module.decoration.transform`; Divi `elements.style()`. |
| Animation | Universal `module.decoration.animation`; Divi `elements.style()` and module script data. |
| Spacing | Universal `module.decoration.spacing`; Divi `elements.style()` with important spacing parity. |
| Sizing | Universal `module.decoration.sizing`; Divi `elements.style()` and native flex sizing. |
| Layout, overflow, position, order, and z-index | Matching universal module attributes; Divi `elements.style()`. |

## Advanced controls

| Group | Connection |
| --- | --- |
| CSS ID and classes | Registered by `module.settings.advanced.html`, saved at `module.advanced.htmlAttributes`, and consumed by `ModuleContainer` and PHP `Module::render()`. |
| Custom CSS | `customCssFields.iconElement`; emitted by `CssStyle` in Builder and PHP. |
| Attributes | `module.decoration.attributes`; class/attribute utilities and the `iconLink` target. |
| Conditions and visibility | `conditions` and `disabledOn`; universal element styles/classnames. |
| Scroll effects and sticky | `scroll` and `sticky`; universal element styles plus script data. |
| Interactions | `interactions`; Divi module script data. |
| Transitions | `transition`; universal element styles. |

## Regression rule

Do not add a sidebar option unless all applicable columns are known: saved
attribute, Builder behavior, PHP/front-end behavior, and a regression test.
Automated contract tests do not replace visual hover, responsive, sticky,
animation, and interaction checks in the live Builder.
