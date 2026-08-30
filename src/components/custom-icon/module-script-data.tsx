import React, { Fragment, ReactElement } from 'react';
import { ModuleScriptDataProps } from '@divi/module';
import { CustomIconAttrs } from './types';

export const ModuleScriptData = ({
  elements,
}: ModuleScriptDataProps<CustomIconAttrs>): ReactElement => (
  <Fragment>{elements.scriptData({ attrName: 'module' })}</Fragment>
);
