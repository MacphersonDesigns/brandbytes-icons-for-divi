import { type Metadata, type ModuleLibrary } from '@divi/types';

import metadata from './module.json';
import defaultPrintedStyleAttributes from './module-default-printed-style-attributes.json';
import defaultRenderAttributes from './module-default-render-attributes.json';
import { CustomIconEdit } from './edit';
import { placeholderContent } from './placeholder-content';
import { CustomIconAttrs } from './types';

import './module.scss';
import './style.scss';

export const customIconModule: ModuleLibrary.Module.RegisterDefinition<CustomIconAttrs> = {
  metadata: metadata as Metadata.Values<CustomIconAttrs>,
  defaultAttrs: defaultRenderAttributes as Metadata.DefaultAttributes<CustomIconAttrs>,
  defaultPrintedStyleAttrs:
    defaultPrintedStyleAttributes as Metadata.DefaultAttributes<CustomIconAttrs>,
  placeholderContent,
  renderers: {
    edit: CustomIconEdit,
  },
};
