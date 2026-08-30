import { __ } from '@wordpress/i18n';
import metadata from './module.json';

const customCssFields = metadata.customCssFields as Record<
  'iconElement',
  { subName: string; selectorSuffix: string; label: string }
>;

customCssFields.iconElement.label = __(
  'Icon Element',
  'custom-icons-for-divi',
);

export const cssFields = { ...customCssFields };
