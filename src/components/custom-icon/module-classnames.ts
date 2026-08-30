import { ModuleClassnamesParams } from '@divi/module';
import { CustomIconAttrs } from './types';

export const moduleClassnames = (
  _params: ModuleClassnamesParams<CustomIconAttrs>,
): void => {
  // Reserved for module-state class names. Keeping the callback explicit makes
  // the Visual Builder and PHP Module::render contracts symmetrical.
};
