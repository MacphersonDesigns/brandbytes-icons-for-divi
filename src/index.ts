import { addAction } from '@wordpress/hooks';
import { registerFieldComponent } from '@divi/field-library';
import { registerModule } from '@divi/module-library';

import { customIconModule } from './components/custom-icon';
import { CustomIconLibraryPicker } from './components/custom-icon/icon-library-picker';
import './module-icons';

/**
 * The picker is a real Divi field component, so Divi owns attribute updates,
 * undo/redo, presets, and save serialization. Our component owns only the
 * icon-specific search and selection interface.
 */
registerFieldComponent({
  name: 'cifd/icon-library-picker',
  component: CustomIconLibraryPicker,
});

/**
 * Register only after Divi's module-library store exists. Registering earlier
 * can appear to work in one window while being absent from the builder iframe.
 */
addAction(
  'divi.moduleLibrary.registerModuleLibraryStore.after',
  'customIconsForDivi.registerCustomIconModule',
  () => {
    const { metadata, ...definition } = customIconModule;
    registerModule(metadata, definition);
  },
);
