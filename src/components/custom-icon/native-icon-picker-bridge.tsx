import React, {
  ComponentType,
  ReactElement,
  useEffect,
  useId,
  useState,
} from 'react';
import { __ } from '@wordpress/i18n';

import { CustomIconLibraryPicker } from './icon-library-picker';
import { BuilderCatalogItem, IconReferenceValue } from './types';

export interface NativePickerProps {
  onChange: (change: { inputValue: IconReferenceValue }) => void;
  readonly?: boolean;
  value?: IconReferenceValue & { type?: string };
  [key: string]: unknown;
}

export interface NativePickerBridgeProps extends NativePickerProps {
  NativePicker: ComponentType<NativePickerProps>;
}

const HasCustomCatalog = (): boolean => {
  const catalog = window.cifdCustomIconSettings?.catalog ?? {};
  return Object.keys(catalog).length > 0;
};

const IsCustomIconValue = (value?: IconReferenceValue & { type?: string }): boolean => (
  value?.type === 'cifd' && typeof value.uuid === 'string'
);

/**
 * Keeps Divi's original picker intact for its native icon values, while adding
 * a provider-like tab for plugin-owned SVG records. This is deliberately a
 * wrapper instead of a document-wide DOM patch: Divi still owns all field
 * updates, undo/redo, presets, and responsive serialization.
 */
export const NativeIconPickerBridge = ({
  NativePicker,
  ...props
}: NativePickerBridgeProps): ReactElement => {
  const isCustomValue = IsCustomIconValue(props.value);
  const [provider, setProvider] = useState<'native' | 'custom'>(
    isCustomValue ? 'custom' : 'native',
  );

  // Divi controls the field value, while this wrapper controls only which
  // provider is visible. Sync after a real value change so switching from a
  // custom icon back to a native icon cannot leave the sidebar on stale
  // Custom Icons state. The tab click itself does not change the value.
  useEffect(() => {
    setProvider(isCustomValue ? 'custom' : 'native');
  }, [isCustomValue, props.value?.type, props.value?.uuid]);

  if (!HasCustomCatalog()) {
    return <NativePicker {...props} />;
  }

  const SelectNative = (): void => {
    setProvider('native');
  };

  const SelectCustom = (): void => {
    setProvider('custom');
  };

  if (provider === 'custom') {
    return (
      <div className="cifd-native-picker-bridge">
        <div className="cifd-native-picker-bridge__tabs" role="tablist">
          <button
            aria-selected="false"
            className="cifd-native-picker-bridge__tab"
            onClick={SelectNative}
            role="tab"
            type="button"
          >
            {__('Divi Icons', 'custom-icons-for-divi')}
          </button>
          <button
            aria-selected="true"
            className="cifd-native-picker-bridge__tab is-active"
            onClick={SelectCustom}
            role="tab"
            type="button"
          >
            {__('Custom Icons', 'custom-icons-for-divi')}
          </button>
        </div>
        <CustomIconLibraryPicker
          onChange={(change) => {
            setProvider('custom');
            props.onChange({
              inputValue: {
                ...change.inputValue,
                type: 'cifd',
              },
            });
          }}
          readonly={props.readonly}
          value={props.value}
        />
      </div>
    );
  }

  return (
    <div className="cifd-native-picker-bridge">
      <div className="cifd-native-picker-bridge__tabs" role="tablist">
        <button
          aria-selected="true"
          className="cifd-native-picker-bridge__tab is-active"
          onClick={SelectNative}
          role="tab"
          type="button"
        >
          {__('Divi Icons', 'custom-icons-for-divi')}
        </button>
        <button
          aria-selected="false"
          className="cifd-native-picker-bridge__tab"
          onClick={SelectCustom}
          role="tab"
          type="button"
        >
          {__('Custom Icons', 'custom-icons-for-divi')}
        </button>
      </div>
      <NativePicker
        {...props}
        onChange={(change) => {
          // Record the user's provider choice immediately. Divi may update
          // the controlled value on the following render.
          setProvider('native');
          props.onChange(change);
        }}
      />
    </div>
  );
};

interface BlurbPreviewBridgeProps {
  element: ReactElement;
  moduleAttrs?: unknown;
}

const ReadPath = (source: unknown, path: string[]): unknown => {
  if (source && typeof (source as { getIn?: unknown }).getIn === 'function') {
    return (source as { getIn: (segments: string[]) => unknown }).getIn(path);
  }

  return path.reduce<unknown>((current, key) => {
    if (!current || typeof current !== 'object') {
      return undefined;
    }
    return (current as Record<string, unknown>)[key];
  }, source);
};

export const ReadCustomBlurbUuid = (attrs: unknown): string => {
  const path = ['imageIcon', 'innerContent', 'desktop', 'value', 'icon'];
  const candidates = CollectAttributeCandidates(attrs);

  for (const candidate of candidates) {
    const icon = ReadPath(candidate, path) as { type?: string; uuid?: string } | undefined;
    if (icon?.type === 'cifd' && typeof icon.uuid === 'string') {
      return icon.uuid;
    }
  }

  return '';
};

const CollectAttributeCandidates = (source: unknown): unknown[] => {
  const candidates: unknown[] = [];
  const queue: unknown[] = [source];
  const seen = new Set<unknown>();

  while (queue.length > 0) {
    const candidate = queue.shift();
    if (!candidate || seen.has(candidate)) {
      continue;
    }
    seen.add(candidate);

    if (Array.isArray(candidate)) {
      queue.push(...candidate);
      continue;
    }

    candidates.push(candidate);
    if (typeof candidate === 'object') {
      const record = candidate as Record<string, unknown>;
      queue.push(
        record.runtimeAttrs,
        record.runtimeModuleAttrs,
        record.moduleAttrs,
        record.attrs,
        record.props,
      );
    }
  }

  return candidates;
};

const ReadModuleOrderClass = (moduleInstance: unknown): string => {
  for (const candidate of CollectAttributeCandidates(moduleInstance)) {
    const orderClass = ReadPath(candidate, ['orderClass']);
    if (typeof orderClass === 'string' && /^[A-Za-z0-9_-]+$/.test(orderClass)) {
      return orderClass;
    }
  }
  return '';
};

const ResolveBuilderIcon = (uuid: string): BuilderCatalogItem | undefined => {
  const windows = new Set<Window>([window]);
  try {
    if (window.top) {
      windows.add(window.top);
      window.top.document.querySelectorAll('iframe').forEach((frame) => {
        try {
          if (frame.contentWindow) {
            windows.add(frame.contentWindow);
          }
        } catch {
          // Ignore frames outside the same-origin Builder contract.
        }
      });
    }
  } catch {
    // The local Builder is same-origin, but fail safely if that changes.
  }

  for (const candidateWindow of Array.from(windows)) {
    const icon = candidateWindow.cifdCustomIconSettings?.catalog?.[uuid];
    if (icon) {
      return icon;
    }
  }

  return undefined;
};

const FindPreviewRoot = (
  markerId: string,
  orderClass: string,
): HTMLElement | null => {
  const documents = new Set<Document>([document]);
  try {
    if (window.top) {
      const topDocument = window.top.document;
      documents.add(topDocument);

      // The render hook may run in the top window itself. Search its preview
      // frames in that case too; the earlier implementation only searched
      // frames when executing from a child window.
      topDocument.querySelectorAll('iframe').forEach((frame) => {
        try {
          if (frame.contentDocument) {
            documents.add(frame.contentDocument);
          }
        } catch {
          // Cross-origin frames are outside the Builder contract and are
          // intentionally ignored.
        }
      });
    }
  } catch {
    // A restricted window should not prevent the sidebar from functioning.
  }

  for (const ownerDocument of Array.from(documents)) {
    const markedRoot = ownerDocument.querySelector<HTMLElement>(
      `[data-cifd-blurb-preview="${markerId}"]`,
    );
    if (markedRoot) {
      return markedRoot;
    }

    // Divi's module element can be a React component that does not forward
    // arbitrary data attributes. Its generated orderClass is placed on the
    // real canvas node and is therefore the reliable fallback selector.
    if (orderClass) {
      const orderedRoot = ownerDocument.querySelector<HTMLElement>(`.${orderClass}`);
      if (orderedRoot) {
        return orderedRoot;
      }
    }
  }

  return null;
};

/**
 * Inject the selected glyph into Divi's client-rendered Blurb tree. The
 * server adapter covers published output; this small React adapter covers the
 * separate Builder canvas renderer, which does not call PHP block filters for
 * every attribute change.
 */
export const BlurbCanvasPreviewBridge = ({
  element,
  moduleAttrs,
}: BlurbPreviewBridgeProps): ReactElement => {
  const markerId = `cifd-blurb-preview-${useId().replace(/:/g, '')}`;
  const uuid = ReadCustomBlurbUuid(moduleAttrs);
  const orderClass = ReadModuleOrderClass(moduleAttrs);
  const icon = uuid ? ResolveBuilderIcon(uuid) : undefined;

  useEffect(() => {
    const root = FindPreviewRoot(markerId, orderClass);
    if (!root) {
      return undefined;
    }

    root.querySelectorAll('.cifd-native-blurb-glyph').forEach((node) => node.remove());
    if (!icon?.url) {
      return undefined;
    }

    const content = root.querySelector<HTMLElement>('.et_pb_blurb_content');
    if (!content) {
      return undefined;
    }

    let image = content.querySelector<HTMLElement>('.et_pb_main_blurb_image');
    if (!image) {
      image = root.ownerDocument.createElement('div');
      image.className = 'et_pb_main_blurb_image';
      content.prepend(image);
    }

    const ownerDocument = root.ownerDocument;
    const wrapper = ownerDocument.createElement('span');
    wrapper.className = 'et_pb_image_wrap';
    const glyph = ownerDocument.createElement('span');
    glyph.className = 'et-pb-icon cifd-native-blurb-glyph';
    glyph.setAttribute('aria-hidden', 'true');
    glyph.style.setProperty('--cifd-icon-url', `url("${icon.url}")`);
    wrapper.appendChild(glyph);
    image.replaceChildren(wrapper);

    return () => {
      root.querySelectorAll('.cifd-native-blurb-glyph').forEach((node) => node.remove());
    };
  }, [icon?.url, markerId, orderClass]);

  if (!React.isValidElement(element)) {
    return element;
  }

  return React.cloneElement(element, {
    'data-cifd-blurb-preview': markerId,
  });
};
