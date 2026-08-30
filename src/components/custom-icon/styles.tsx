import React, { ReactElement } from 'react';
import { CssStyle, StyleContainer, StylesProps } from '@divi/module';
import { StyleDeclarations } from '@divi/style-library';

import { cssFields } from './custom-css';
import { CustomIconAttrs } from './types';

const alignmentDeclaration = ({ attrValue }: { attrValue?: string }): string => {
  const declarations = new StyleDeclarations({ returnType: 'string' });
  if (attrValue === 'left') {
    declarations.add('text-align', 'left');
    declarations.add('margin-left', '0');
  } else if (attrValue === 'center') {
    declarations.add('text-align', 'center');
  } else if (attrValue === 'right') {
    declarations.add('text-align', 'right');
    declarations.add('margin-right', '0');
  }
  return declarations.value as string;
};

export const ModuleStyles = ({
  attrs,
  elements,
  settings,
  orderClass,
  mode,
  state,
  noStyleTag,
}: StylesProps<CustomIconAttrs>): ReactElement => (
  <StyleContainer mode={mode} state={state} noStyleTag={noStyleTag}>
    {elements.style({
      attrName: 'module',
      styleProps: {
        disabledOn: {
          disabledModuleVisibility: settings?.disabledModuleVisibility,
        },
      },
    })}
    {elements.style({
      attrName: 'icon',
      styleProps: {
        advancedStyles: [
          {
            componentName: 'divi/common',
            props: {
              selector: orderClass,
              attr: attrs?.icon?.advanced?.align,
              declarationFunction: alignmentDeclaration,
            },
          },
          {
            componentName: 'divi/common',
            props: {
              attr: attrs?.icon?.advanced?.color,
              property: 'color',
            },
          },
          {
            componentName: 'divi/common',
            props: {
              attr: attrs?.icon?.advanced?.size,
              property: 'font-size',
            },
          },
        ],
      },
    })}
    <CssStyle selector={orderClass} attr={attrs.css} cssFields={cssFields} />
  </StyleContainer>
);
