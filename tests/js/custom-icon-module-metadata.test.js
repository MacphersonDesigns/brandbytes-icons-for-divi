const metadata = require('../../src/components/custom-icon/module.json');
const defaults = require('../../src/components/custom-icon/module-default-render-attributes.json');
const fs = require('fs');
const path = require('path');

describe('Custom Icon module metadata', () => {
  test('registers a dedicated icon module rather than an SVG module', () => {
    expect(metadata.name).toBe('cifd/custom-icon');
    expect(metadata.title).toBe('Custom Icon');
    expect(metadata.moduleIcon).toBe('cifd/module-custom-icon');
  });

  test('stores a stable icon reference and exposes native-style design groups', () => {
    expect(defaults.iconReference.innerContent.desktop.value).toEqual({
      uuid: 'c1fd0001-0000-4000-8000-000000000011',
      target: 'off',
    });
    expect(
      metadata.attributes.iconReference.settings.innerContent.groups.icon.item.component.name,
    ).toBe('cifd/icon-library-picker');
    expect(metadata.settings.groups.contentIcon.panel).toBe('content');
    expect(metadata.settings.groups).toHaveProperty('designIconSize');
    expect(metadata.settings.groups).toHaveProperty('designIconAlignment');
    expect(metadata.attributes.icon.settings.advanced).toHaveProperty('color');
    expect(metadata.attributes.icon.settings.advanced).toHaveProperty('size');
    expect(metadata.attributes.icon.settings.advanced).toHaveProperty('align');
    expect(metadata.settings.groups).toHaveProperty('contentLink');
    expect(
      metadata.attributes.iconReference.settings.innerContent.groups.link.item.component.name,
    ).toBe('divi/link');
  });

  test('does not expose a generic media upload field', () => {
    expect(JSON.stringify(metadata)).not.toContain('divi/upload');
  });

  test('uses the Divi JavaScript StyleDeclarations value property', () => {
    const stylesSource = fs.readFileSync(
      path.join(__dirname, '../../src/components/custom-icon/styles.tsx'),
      'utf8',
    );

    expect(stylesSource).toContain('declarations.value as string');
    expect(stylesSource).not.toContain('declarations.value()');
  });

  test('registers only the dedicated module and does not alter native icon fields', () => {
    const indexSource = fs.readFileSync(
      path.join(__dirname, '../../src/index.ts'),
      'utf8',
    );

    expect(indexSource).toContain('registerFieldComponent');
    expect(indexSource).toContain('registerModule');
    expect(indexSource).not.toContain('divi.fieldLibrary.getFieldComponent');
    expect(indexSource).not.toContain('divi.module.elements.render');
  });

  test('ships polished picker and empty-state presentation', () => {
    const pickerSource = fs.readFileSync(
      path.join(__dirname, '../../src/components/custom-icon/icon-library-picker.tsx'),
      'utf8',
    );
    const editSource = fs.readFileSync(
      path.join(__dirname, '../../src/components/custom-icon/edit.tsx'),
      'utf8',
    );

    expect(pickerSource).toContain('cifd-icon-picker__selected-mark');
    expect(pickerSource).toContain('cifd-icon-picker__selection');
    expect(pickerSource).toContain('cifd-icon-picker__library-filter');
    expect(pickerSource).toContain('...icon.keywords');
    expect(editSource).toContain('cifd-custom-icon__builder-placeholder-copy');
    expect(editSource).toContain("tag={linkUrl ? 'a' : undefined}");
  });

  test('does not collapse Divi insertion controls from the module root', () => {
    const moduleStyles = fs.readFileSync(
      path.join(__dirname, '../../src/components/custom-icon/module.scss'),
      'utf8',
    );

    expect(moduleStyles).not.toMatch(/\.cifd_custom_icon\s*\{\s*line-height:\s*0/);
    expect(moduleStyles).toMatch(/\.et_pb_icon_wrap\s*\{[\s\S]*line-height:\s*0/);
  });

  test('forwards Divi runtime flags required by the module insertion control', () => {
    const editSource = fs.readFileSync(
      path.join(__dirname, '../../src/components/custom-icon/edit.tsx'),
      'utf8',
    );

    expect(editSource).toContain('<ModuleContainer\n      {...props}');
  });

  test('shrink-wraps and positions the module like Divi native Icon', () => {
    const moduleStyles = fs.readFileSync(
      path.join(__dirname, '../../src/components/custom-icon/module.scss'),
      'utf8',
    );
    const stylesSource = fs.readFileSync(
      path.join(__dirname, '../../src/components/custom-icon/styles.tsx'),
      'utf8',
    );
    const phpSource = fs.readFileSync(
      path.join(__dirname, '../../includes/divi5/class-custom-icon-module.php'),
      'utf8',
    );

    expect(moduleStyles).toContain('&.et_flex_module');
    expect(moduleStyles).toContain('align-self: center');
    expect(stylesSource).toContain("declarations.add('margin-left', '0')");
    expect(stylesSource).toContain("declarations.add('margin-right', '0')");
    expect(phpSource).toContain("$declarations->add('margin-left', '0')");
    expect(phpSource).toContain("$declarations->add('margin-right', '0')");
  });

  test('wires universal Design and Advanced groups with current Divi metadata', () => {
    const moduleAttribute = metadata.attributes.module;
    const expectedDecorationGroups = [
      'animation', 'attributes', 'background', 'border', 'boxShadow',
      'conditions', 'disabledOn', 'filters', 'interactions', 'layout', 'order',
      'overflow', 'position', 'scroll', 'sizing', 'spacing', 'sticky',
      'transform', 'transition', 'zIndex',
    ];
    const stylesSource = fs.readFileSync(
      path.join(__dirname, '../../src/components/custom-icon/styles.tsx'),
      'utf8',
    );
    const phpSource = fs.readFileSync(
      path.join(__dirname, '../../includes/divi5/class-custom-icon-module.php'),
      'utf8',
    );

    expect(moduleAttribute.styleComponentsProps.boxShadow.settings.overlay).toBe(true);
    expect(moduleAttribute.settings.decoration.background.component.name).toBe('divi/background');
    expect(moduleAttribute.settings.decoration.background.component.props.hidePanels).toContain('video');
    expect(moduleAttribute.settings.meta).toHaveProperty('meta');
    expect(moduleAttribute.settings.advanced).toHaveProperty('elements');
    expect(moduleAttribute.settings.advanced).toHaveProperty('html');
    expect(Object.keys(moduleAttribute.settings.decoration).sort()).toEqual(
      expectedDecorationGroups.sort(),
    );
    // Core modules receive internal defaults before registration. Third-party
    // modules must opt into automatic Advanced-panel composition explicitly.
    expect(metadata.settings.advanced).toBe('auto');
    expect(metadata.settings.groups).toHaveProperty('designIconSize');
    expect(metadata.settings.groups).toHaveProperty('designIconAlignment');
    expect(stylesSource).toContain("attrName: 'module'");
    expect(phpSource).toContain("'attrName'   => 'module'");
    expect(phpSource).toContain("'attrName' => 'module'");
    expect(phpSource).toContain('CssStyle::style');
  });

  test('inserts and resets to the bundled Home icon without layout shift', () => {
    const placeholderSource = fs.readFileSync(
      path.join(__dirname, '../../src/components/custom-icon/placeholder-content.ts'),
      'utf8',
    );
    const pickerSource = fs.readFileSync(
      path.join(__dirname, '../../src/components/custom-icon/icon-library-picker.tsx'),
      'utf8',
    );

    expect(placeholderSource).toContain('DEFAULT_ICON_UUID');
    expect(pickerSource).toContain('Reset to Home');
    expect(pickerSource).not.toContain("uuid: ''");
  });

  test('matches Divi Icon module link rendering on the server', () => {
    const phpSource = fs.readFileSync(
      path.join(__dirname, '../../includes/divi5/class-custom-icon-module.php'),
      'utf8',
    );

    expect(phpSource).toContain("$module_render_args['tag'] = 'a';");
    expect(phpSource).toContain("'noopener noreferrer'");
    expect(phpSource).toContain("'class' => 'et_pb_icon_wrap'");
  });

  test('rejects active link schemes in the Builder preview', () => {
    const editSource = fs.readFileSync(
      path.join(__dirname, '../../src/components/custom-icon/edit.tsx'),
      'utf8',
    );

    expect(editSource).toContain('NormalizeBuilderLinkUrl');
    expect(editSource).toContain("['http', 'https', 'mailto', 'tel'].includes(scheme)");
    expect(editSource).toContain('const linkUrl = NormalizeBuilderLinkUrl(reference?.url);');
  });

  test('provides the custom icon catalog to both Divi Builder windows', () => {
    const bootstrapSource = fs.readFileSync(
      path.join(__dirname, '../../includes/divi5/class-custom-icon-module-bootstrap.php'),
      'utf8',
    );

    expect(bootstrapSource).toContain(
      'divi_visual_builder_assets_after_enqueue_app_window_scripts',
    );
    expect(bootstrapSource).toContain(
      'divi_visual_builder_assets_after_enqueue_top_window_scripts',
    );
  });
});
