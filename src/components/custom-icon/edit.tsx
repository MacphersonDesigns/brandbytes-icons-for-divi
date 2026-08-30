import React, { CSSProperties, ReactElement } from 'react';
import { __ } from '@wordpress/i18n';
import { ModuleContainer } from '@divi/module';
import { getAttrByMode } from '@divi/module-utils';

import { CustomIconEditProps, IconReferenceValue } from './types';
import { DEFAULT_ICON_UUID } from './constants';
import { moduleClassnames } from './module-classnames';
import { ModuleScriptData } from './module-script-data';
import { ModuleStyles } from './styles';

/**
 * Match the server renderer's safe-scheme boundary inside the live Builder.
 *
 * Relative URLs are preserved because Divi and WordPress support them. When a
 * value declares a scheme explicitly, only the normal web and contact schemes
 * accepted by this module are allowed. This prevents migrated or hand-edited
 * block data from turning the Builder preview into a javascript: navigation.
 */
export const NormalizeBuilderLinkUrl = (value: unknown): string => {
  if (typeof value !== 'string') {
    return '';
  }

  const normalized = value.trim();
  const scheme = normalized.match(/^([a-z][a-z\d+.-]*):/i)?.[1]?.toLowerCase();
  if (scheme && !['http', 'https', 'mailto', 'tel'].includes(scheme)) {
    return '';
  }

  return normalized;
};

export const CustomIconEdit = (props: CustomIconEditProps): ReactElement => {
  const { attrs, elements, id, name } = props;
  const reference = getAttrByMode(
    attrs?.iconReference?.innerContent,
  ) as IconReferenceValue | undefined;
  // Older fixtures may contain an explicitly cleared UUID. Treat that state
  // as the supported Home default so reopening them does not jump to a taller
  // empty-state placeholder.
  const uuid = reference?.uuid || DEFAULT_ICON_UUID;
  const linkUrl = NormalizeBuilderLinkUrl(reference?.url);
  const linkTarget = reference?.target === 'on' ? '_blank' : undefined;
  const linkTitle = typeof reference?.title === 'string' ? reference.title : '';
  const icon = uuid
    ? window.cifdCustomIconSettings?.catalog?.[uuid]
    : undefined;

  const maskStyle = icon
    ? ({ '--cifd-icon-url': `url("${icon.url}")` } as CSSProperties)
    : undefined;

  return (
    <ModuleContainer
      {...props}
      attrs={attrs}
      elements={elements}
      id={id}
      name={name}
      stylesComponent={ModuleStyles}
      classnamesFunction={moduleClassnames}
      scriptDataComponent={ModuleScriptData}
      tag={linkUrl ? 'a' : undefined}
      htmlAttrs={linkUrl ? {
        href: linkUrl,
        ...(linkTarget ? { target: linkTarget, rel: 'noopener noreferrer' } : {}),
        ...(linkTitle ? { title: linkTitle } : {}),
      } : undefined}
    >
      {elements.styleComponents({ attrName: 'module' })}
      <span className="et_pb_icon_wrap">
        <span
          aria-hidden="true"
          className={[
            'cifd-custom-icon__glyph',
            icon ? '' : 'cifd-custom-icon__glyph--is-missing',
          ].filter(Boolean).join(' ')}
          data-cifd-icon-uuid={uuid || undefined}
          style={maskStyle}
        />
      </span>
      {!icon && (
        <span className="cifd-custom-icon__builder-placeholder" role="status">
          <svg
            aria-hidden="true"
            className="cifd-custom-icon__builder-placeholder-mark"
            viewBox="0 0 32 32"
          >
            <rect height="8" rx="2" width="8" x="5" y="5" />
            <rect height="8" rx="2" width="8" x="19" y="5" />
            <rect height="8" rx="2" width="8" x="5" y="19" />
            <path d="M23 18v10M18 23h10" />
          </svg>
          <span className="cifd-custom-icon__builder-placeholder-copy">
            <strong>{__('Choose a custom icon', 'custom-icons-for-divi')}</strong>
            <small>{__('Open the Content panel to browse this site’s library.', 'custom-icons-for-divi')}</small>
          </span>
        </span>
      )}
    </ModuleContainer>
  );
};
