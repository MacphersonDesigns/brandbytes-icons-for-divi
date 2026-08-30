import { ModuleEditProps } from '@divi/module-library';
import { InternalAttrs, type Element } from '@divi/types';

export interface IconReferenceValue {
  uuid?: string;
  url?: string;
  target?: 'on' | 'off';
  title?: string;
}

export interface BuilderCatalogItem {
  uuid: string;
  name: string;
  url: string;
  mime: string;
  libraryId: string;
  library: string;
  category: string;
  keywords: string[];
  source: 'bundled' | 'uploaded';
}

export interface BuilderCatalogSettings {
  catalog: Record<string, BuilderCatalogItem>;
  manageUrl: string;
  catalogRev: string;
}

declare global {
  interface Window {
    cifdCustomIconSettings?: BuilderCatalogSettings;
  }
}

export interface CustomIconAttrs extends InternalAttrs {
  module?: {
    meta?: Element.Meta.Attributes;
    advanced?: {
      // The metadata group is named `html`, while Divi's persisted attribute
      // path remains `module.advanced.htmlAttributes`.
      htmlAttributes?: Element.Advanced.IdClasses.Attributes;
    };
    decoration?: Element.Decoration.PickedAttributes<
      | 'animation'
      | 'background'
      | 'border'
      | 'boxShadow'
      | 'disabledOn'
      | 'filters'
      | 'overflow'
      | 'position'
      | 'scroll'
      | 'sizing'
      | 'spacing'
      | 'sticky'
      | 'transform'
      | 'transition'
      | 'zIndex'
    >;
  };
  iconReference?: {
    innerContent?: Record<string, { value?: IconReferenceValue }>;
  };
  icon?: {
    advanced?: {
      color?: Element.Decoration.Color.Attributes;
      size?: unknown;
      align?: unknown;
    };
  };
  iconLink?: Record<string, unknown>;
}

export type CustomIconEditProps = ModuleEditProps<CustomIconAttrs>;
