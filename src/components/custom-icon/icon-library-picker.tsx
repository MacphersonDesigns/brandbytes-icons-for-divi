import React, {
  ChangeEvent,
  KeyboardEvent,
  ReactElement,
  useId,
  useMemo,
  useState,
} from 'react';
import { __, sprintf } from '@wordpress/i18n';

import { BuilderCatalogItem, IconReferenceValue } from './types';
import { DEFAULT_ICON_UUID } from './constants';

interface PickerChangeValue {
  inputValue: IconReferenceValue;
}

interface CustomIconLibraryPickerProps {
  onChange: (value: PickerChangeValue) => void;
  readonly?: boolean;
  value?: IconReferenceValue;
}

const NormalizeSearchText = (value: string): string => value
  .trim()
  .toLocaleLowerCase();

/**
 * Divi field component for reusable icon records.
 *
 * The first production-shaped slice intentionally uses native browser buttons
 * and an inline grid. That gives keyboard and screen-reader users a complete
 * path immediately. Roving-arrow navigation can be added later only after the
 * composite-widget behavior is tested; Tab navigation must never disappear as
 * a side effect of visual polish.
 */
export const CustomIconLibraryPicker = ({
  onChange,
  readonly = false,
  value,
}: CustomIconLibraryPickerProps): ReactElement => {
  const searchId = useId();
  const statusId = useId();
  const [query, setQuery] = useState('');
  const [activeLibrary, setActiveLibrary] = useState('all');
  const selectedUuid = value?.uuid || DEFAULT_ICON_UUID;
  const settings = window.cifdCustomIconSettings;
  const catalog = useMemo(
    () => Object.values(settings?.catalog ?? {}),
    [settings?.catalogRev],
  );
  const libraries = useMemo(() => Array.from(new Map(
    catalog.map((icon) => [icon.libraryId, icon.library]),
  ).entries()), [catalog]);
  const normalizedQuery = NormalizeSearchText(query);
  const results = useMemo(() => catalog.filter((icon) => {
    if (activeLibrary !== 'all' && icon.libraryId !== activeLibrary) {
      return false;
    }
    if (!normalizedQuery) {
      return true;
    }

    return NormalizeSearchText([
      icon.name,
      icon.library,
      icon.category,
      ...icon.keywords,
      icon.mime,
    ].join(' ')).includes(normalizedQuery);
  }), [activeLibrary, catalog, normalizedQuery]);
  const selectedIcon = catalog.find((icon) => icon.uuid === selectedUuid);

  const SelectIcon = (icon: BuilderCatalogItem): void => {
    if (!readonly) {
      onChange({ inputValue: { ...value, uuid: icon.uuid } });
    }
  };

  const HandleIconKeyDown = (
    event: KeyboardEvent<HTMLButtonElement>,
  ): void => {
    // Native buttons already activate on Enter and Space. Escape is useful
    // here because the grid lives inline: it clears the search and restores
    // the complete result set without unexpectedly closing Divi's panel.
    if (event.key === 'Escape' && query) {
      event.preventDefault();
      setQuery('');
      document.getElementById(searchId)?.focus();
    }
  };

  return (
    <div className="cifd-icon-picker" data-catalog-revision={settings?.catalogRev}>
      <div className="cifd-icon-picker__heading">
        <div>
          <span className="cifd-icon-picker__eyebrow">
            {__('Site icon library', 'custom-icons-for-divi')}
          </span>
          <strong className="cifd-icon-picker__title">
            {__('Choose an icon', 'custom-icons-for-divi')}
          </strong>
        </div>
        <span className="cifd-icon-picker__count" aria-hidden="true">
          {catalog.length}
        </span>
      </div>
      {libraries.length > 1 && (
        <div
          aria-label={__('Filter by icon library', 'custom-icons-for-divi')}
          className="cifd-icon-picker__libraries"
          role="group"
        >
          <button
            aria-pressed={activeLibrary === 'all'}
            className="cifd-icon-picker__library-filter"
            onClick={() => setActiveLibrary('all')}
            type="button"
          >
            {__('All', 'custom-icons-for-divi')}
          </button>
          {libraries.map(([libraryId, libraryName]) => (
            <button
              aria-pressed={activeLibrary === libraryId}
              className="cifd-icon-picker__library-filter"
              key={libraryId}
              onClick={() => setActiveLibrary(libraryId)}
              type="button"
            >
              {libraryName}
            </button>
          ))}
        </div>
      )}
      <label className="cifd-icon-picker__search-label" htmlFor={searchId}>
        {__('Search custom icons', 'custom-icons-for-divi')}
      </label>
      <div className="cifd-icon-picker__search-shell">
        <svg aria-hidden="true" className="cifd-icon-picker__search-icon" viewBox="0 0 20 20">
          <circle cx="8.5" cy="8.5" r="5.25" />
          <path d="m12.5 12.5 4 4" />
        </svg>
        <input
          aria-describedby={statusId}
          className="cifd-icon-picker__search"
          disabled={readonly}
          id={searchId}
          onChange={(event: ChangeEvent<HTMLInputElement>) => setQuery(event.target.value)}
          placeholder={__('Search custom icons', 'custom-icons-for-divi')}
          type="search"
          value={query}
        />
      </div>

      <p aria-live="polite" className="cifd-icon-picker__status" id={statusId}>
        {sprintf(
          /* translators: %d is the number of matching custom icons. */
          __('%d custom icons available.', 'custom-icons-for-divi'),
          results.length,
        )}
      </p>

      {results.length > 0 ? (
        <div
          aria-label={__('Custom icon choices', 'custom-icons-for-divi')}
          className="cifd-icon-picker__grid"
          role="group"
        >
          {results.map((icon) => {
            const selected = selectedUuid === icon.uuid;
            return (
              <button
                aria-label={icon.name}
                aria-pressed={selected}
                className="cifd-icon-picker__choice"
                data-selected={selected ? 'true' : 'false'}
                disabled={readonly}
                key={icon.uuid}
                onClick={() => SelectIcon(icon)}
                onKeyDown={HandleIconKeyDown}
                title={icon.name}
                type="button"
              >
                <span className="cifd-icon-picker__thumbnail-stage">
                  <span
                    aria-hidden="true"
                    className="cifd-icon-picker__thumbnail"
                    style={{ '--cifd-picker-icon-url': `url("${icon.url}")` } as React.CSSProperties}
                  />
                  {selected && (
                    <span aria-hidden="true" className="cifd-icon-picker__selected-mark">✓</span>
                  )}
                </span>
                <span className="cifd-icon-picker__name">{icon.name}</span>
                <span className="cifd-icon-picker__library">{icon.library}</span>
              </button>
            );
          })}
        </div>
      ) : (
        <div className="cifd-icon-picker__empty">
          <p>
            {catalog.length > 0
              ? __('No custom icons match your search.', 'custom-icons-for-divi')
              : __('No custom icons have been uploaded yet.', 'custom-icons-for-divi')}
          </p>
          {settings?.manageUrl && (
            <a href={settings.manageUrl} rel="noreferrer" target="_blank">
              {__('Manage custom icons', 'custom-icons-for-divi')}
            </a>
          )}
        </div>
      )}

      {selectedIcon && !readonly && (
        <div className="cifd-icon-picker__selection">
          <span className="cifd-icon-picker__selection-copy">
            <small>{__('Selected icon', 'custom-icons-for-divi')}</small>
            <strong>{selectedIcon.name}</strong>
          </span>
          <button
            className="cifd-icon-picker__clear"
            onClick={() => onChange({ inputValue: { ...value, uuid: DEFAULT_ICON_UUID } })}
            type="button"
          >
            {__('Reset to Home', 'custom-icons-for-divi')}
          </button>
        </div>
      )}
    </div>
  );
};
