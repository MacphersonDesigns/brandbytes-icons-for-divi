import { addFilter } from '@wordpress/hooks';
import * as customIcon from './icons/custom-icon';

addFilter(
  'divi.iconLibrary.icon.map',
  'customIconsForDivi.moduleIcon',
  (icons: Record<string, unknown>) => ({
    ...icons,
    [customIcon.name]: customIcon,
  }),
);
