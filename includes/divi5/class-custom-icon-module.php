<?php
namespace CustomIconsForDivi\Divi5;

if (!defined('ABSPATH')) {
    exit;
}

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElements;
use ET\Builder\Packages\Module\Module;
use ET\Builder\Packages\Module\Options\Attributes\AttributeUtils;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\Packages\Module\Options\Element\ElementClassnames;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use ET\Builder\Packages\StyleLibrary\Utils\StyleDeclarations;
use WP_Block;
use WP_Block_Type_Registry;

/**
 * Divi 5 Custom Icon module foundation.
 *
 * This class owns the PHP/server half of the module. Its Visual Builder twin
 * lives in src/components/custom-icon. Both sides read the same module.json
 * contract and render the same class names.
 */
class Custom_Icon_Module implements DependencyInterface {

    /** Stable UUID of the bundled Home icon used by new and cleared modules. */
    private const DEFAULT_ICON_UUID = 'c1fd0001-0000-4000-8000-000000000011';

    private $resolver;

    public function __construct(Custom_Icon_Asset_Resolver $resolver) {
        $this->resolver = $resolver;
    }

    public function load(): void {
        $module_json_folder_path = CIFD_DIR . 'modules-json/custom-icon/';

        add_action('init', function () use ($module_json_folder_path) {
            ModuleRegistration::register_module($module_json_folder_path, array(
                'render_callback' => array($this, 'render_callback'),
            ));
        });
    }

    public function render_callback(array $attrs, string $child_modules_content, WP_Block $block, ModuleElements $elements): string {
        $uuid = $this->selected_uuid($attrs);
        $icon = $this->resolver->resolve($uuid);
        $icon_inner = $attrs['iconReference']['innerContent']['desktop']['value'] ?? array();
        $link = HTMLUtility::resolve_url_shortcodes($icon_inner['url'] ?? '');
        $target = ('on' === ($icon_inner['target'] ?? '')) ? '_blank' : '';
        $title = is_string($icon_inner['title'] ?? null) ? $icon_inner['title'] : '';

        $glyph_class = HTMLUtility::classnames(array(
            'cifd-custom-icon__glyph'             => true,
            'cifd-custom-icon__glyph--is-missing' => !$icon,
        ));

        if ($icon) {
            // Divi's HTMLUtility deliberately strips URL values from inline
            // CSS custom properties. Build this one small tag explicitly and
            // escape each context so the server output keeps the mask URL.
            $glyph = sprintf(
                '<span class="%1$s" aria-hidden="true" data-cifd-icon-uuid="%2$s" style="%3$s"></span>',
                esc_attr($glyph_class),
                esc_attr($icon['uuid']),
                esc_attr('--cifd-icon-url: url("' . esc_url($icon['url']) . '");')
            );
        } else {
            $glyph = sprintf(
                '<span class="%1$s" aria-hidden="true"></span>',
                esc_attr($glyph_class)
            );
        }

        $icon_wrapper = HTMLUtility::render(array(
            'tag'               => 'span',
            'attributes'        => array('class' => 'et_pb_icon_wrap'),
            'childrenSanitizer' => 'et_core_esc_previously',
            'children'          => $glyph,
        ));

        $module_render_args = array(
            'orderIndex'          => $block->parsed_block['orderIndex'],
            'storeInstance'       => $block->parsed_block['storeInstance'],
            'attrs'               => $attrs,
            'elements'            => $elements,
            'id'                  => $block->parsed_block['id'],
            'name'                => $block->block_type->name,
            'moduleCategory'      => $block->block_type->category,
            'moduleClassName'     => 'cifd_custom_icon',
            'classnamesFunction'  => array(self::class, 'module_classnames'),
            'stylesComponent'     => array(self::class, 'module_styles'),
            'scriptDataComponent' => array(self::class, 'module_script_data'),
            'children'            => $elements->style_components(array(
                'attrName' => 'module',
            )) . $icon_wrapper . $child_modules_content,
        );

        // Match Divi's core Icon module: when a link exists, the module root
        // itself becomes the anchor so its complete visual box is clickable.
        if (is_string($link) && '' !== $link) {
            $html_attrs = array('href' => esc_url($link));
            if ('' !== $target) {
                $html_attrs['target'] = $target;
                $html_attrs['rel'] = 'noopener noreferrer';
            }
            if ('' !== $title) {
                $html_attrs['title'] = esc_attr($title);
            }

            $custom_attributes = $attrs['module']['decoration']['attributes'] ?? array();
            if (!empty($custom_attributes)) {
                $separated = AttributeUtils::separate_attributes_by_target_element($custom_attributes);
                $html_attrs = array_merge($html_attrs, $separated['iconLink'] ?? array());
            }

            $module_render_args['tag'] = 'a';
            $module_render_args['htmlAttrs'] = $html_attrs;
        }

        return Module::render($module_render_args);
    }

    public static function module_classnames(array $args): void {
        $args['classnamesInstance']->add(ElementClassnames::classnames(array(
            'attrs' => array_merge(
                array('link' => $args['attrs']['iconReference']['innerContent'] ?? array()),
                $args['attrs']['module']['decoration'] ?? array()
            ),
        )));
    }

    public static function module_script_data(array $args): void {
        $args['elements']->script_data(array(
            'attrName' => 'module',
        ));
    }

    public static function module_styles(array $args): void {
        $attrs = $args['attrs'] ?? array();
        $elements = $args['elements'];
        $settings = $args['settings'] ?? array();

        Style::add(array(
            'id'            => $args['id'],
            'name'          => $args['name'],
            'orderIndex'    => $args['orderIndex'],
            'storeInstance' => $args['storeInstance'],
            'styles'        => array(
                $elements->style(array(
                    'attrName'   => 'module',
                    'styleProps' => array(
                        'disabledOn' => array(
                            'disabledModuleVisibility' => $settings['disabledModuleVisibility'] ?? null,
                        ),
                    ),
                )),
                $elements->style(array(
                    'attrName'   => 'icon',
                    'styleProps' => array(
                        'advancedStyles' => array(
                            array(
                                'componentName' => 'divi/common',
                                'props'         => array(
                                    'selector'            => $args['orderClass'],
                                    'attr'                => $attrs['icon']['advanced']['align'] ?? array(),
                                    'declarationFunction' => array(self::class, 'alignment_style_declaration'),
                                ),
                            ),
                            array(
                                'componentName' => 'divi/common',
                                'props'         => array(
                                    'attr'     => $attrs['icon']['advanced']['color'] ?? array(),
                                    'property' => 'color',
                                ),
                            ),
                            array(
                                'componentName' => 'divi/common',
                                'props'         => array(
                                    'attr'     => $attrs['icon']['advanced']['size'] ?? array(),
                                    'property' => 'font-size',
                                ),
                            ),
                        ),
                    ),
                )),
                CssStyle::style(array(
                    'selector'  => $args['orderClass'],
                    'attr'      => $attrs['css'] ?? array(),
                    'cssFields' => self::custom_css(),
                )),
            ),
        ));
    }

    public static function alignment_style_declaration(array $params): string {
        $declarations = new StyleDeclarations(array('returnType' => 'string'));
        $alignment = $params['attrValue'] ?? '';
        if ('left' === $alignment) {
            $declarations->add('text-align', 'left');
            $declarations->add('margin-left', '0');
        } elseif ('center' === $alignment) {
            $declarations->add('text-align', 'center');
        } elseif ('right' === $alignment) {
            $declarations->add('text-align', 'right');
            $declarations->add('margin-right', '0');
        }
        return $declarations->value();
    }

    public static function custom_css(): array {
        $block_type = WP_Block_Type_Registry::get_instance()->get_registered('cifd/custom-icon');
        return $block_type ? $block_type->customCssFields : array();
    }

    private function selected_uuid(array $attrs): string {
        $value = $attrs['iconReference']['innerContent']['desktop']['value'] ?? array();
        if (!is_array($value) || empty($value['uuid']) || !is_string($value['uuid'])) {
            return self::DEFAULT_ICON_UUID;
        }

        return $value['uuid'];
    }
}
