import { useEffect, useRef, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { AlertTriangle, ExternalLink, Loader2, Palette } from 'lucide-react';

import { AttributeSwatch, SwatchType, TermSwatch } from '@/lib/api/variation-swatches';
import {
  useSaveAttributeTypeMutation,
  useSaveTermMutation,
  useVariationAttributesQuery,
} from '@/lib/queries/variation-swatches';
import { ColorField } from '@/components/settings/ColorField';
import { Select } from '@/components/ui/select';
import { ImageField } from './ImageField';

const TYPE_OPTIONS = [
  { label: __('Off (default dropdown)', 'flexa-extra'), value: 'off' },
  { label: __('Color', 'flexa-extra'), value: 'color' },
  { label: __('Image', 'flexa-extra'), value: 'image' },
  { label: __('Button', 'flexa-extra'), value: 'button' },
];

export default function VariationSwatches() {
  if (window.flexaExtra?.variation_swatches_deferred) {
    return <DeferredNotice />;
  }

  return <VariationSwatchesManager />;
}

function DeferredNotice() {
  return (
    <div className="mx-auto mt-8 max-w-3xl px-6">
      <div className="border-border bg-amber-50 text-amber-900 flex items-start gap-3 rounded-lg border p-4 text-sm dark:bg-amber-950/30 dark:text-amber-200">
        <AlertTriangle className="mt-0.5 h-5 w-5 flex-none" />
        <div>
          <p className="font-semibold">{__('Variation swatches are handled by another plugin', 'flexa-extra')}</p>
          <p className="mt-1">
            {__(
              'Another variation-swatches plugin is active, so Flexa Extra stays out of the way to avoid double rendering. Deactivate it to manage swatches here.',
              'flexa-extra',
            )}
          </p>
        </div>
      </div>
    </div>
  );
}

function VariationSwatchesManager() {
  const { data, isLoading, isError, error } = useVariationAttributesQuery();
  const attributes = data ?? [];

  return (
    <div className="mx-auto mt-8 max-w-5xl space-y-6 px-6 pb-16">
      <div>
        <h1 className="flex items-center gap-2 text-xl font-semibold">
          <Palette className="h-5 w-5" />
          {__('Variation swatches', 'flexa-extra')}
        </h1>
        <p className="text-muted-foreground mt-1 text-sm">
          {__('Turn product attribute dropdowns into color, image or button swatches.', 'flexa-extra')}
        </p>
      </div>

      {isError ? (
        <div className="border-destructive/40 bg-destructive/5 text-destructive rounded-lg border p-4 text-sm">
          {(error as Error)?.message ?? __('Failed to load attributes.', 'flexa-extra')}
        </div>
      ) : isLoading ? (
        <div className="mt-20 flex justify-center">
          <Loader2 className="text-muted-foreground h-6 w-6 animate-spin" />
        </div>
      ) : attributes.length === 0 ? (
        <div className="border-border rounded-lg border border-dashed py-16 text-center">
          <p className="text-muted-foreground text-sm">
            {__('No global product attributes yet.', 'flexa-extra')}
          </p>
          <a
            href="edit.php?post_type=product&page=product_attributes"
            className="bg-primary text-primary-foreground hover:bg-primary/90 mt-4 inline-flex items-center gap-2 rounded-md px-4 py-2 text-sm font-medium transition-colors"
          >
            <ExternalLink className="h-4 w-4" />
            {__('Create a product attribute', 'flexa-extra')}
          </a>
        </div>
      ) : (
        <div className="space-y-4">
          {attributes.map((attr) => (
            <AttributeCard key={attr.taxonomy} attr={attr} />
          ))}
        </div>
      )}
    </div>
  );
}

function AttributeCard({ attr }: { attr: AttributeSwatch }) {
  const saveType = useSaveAttributeTypeMutation();

  return (
    <div className="border-border bg-card rounded-lg border">
      <div className="flex items-center justify-between gap-4 p-4">
        <div>
          <h2 className="font-medium">{attr.label}</h2>
          <p className="text-muted-foreground text-xs">{attr.taxonomy}</p>
        </div>
        <Select
          className="w-52"
          options={TYPE_OPTIONS}
          value={attr.type}
          onChange={(e) =>
            saveType.mutate({ taxonomy: attr.taxonomy, type: e.target.value as SwatchType })
          }
        />
      </div>

      {attr.type !== 'off' && (
        <div className="border-border border-t">
          {attr.type === 'button' ? (
            <p className="text-muted-foreground p-4 text-sm">
              {__('Buttons use the term name. Nothing to assign per term.', 'flexa-extra')}
            </p>
          ) : attr.terms.length === 0 ? (
            <p className="text-muted-foreground p-4 text-sm">
              {__('This attribute has no terms yet.', 'flexa-extra')}
            </p>
          ) : (
            <ul className="divide-border divide-y">
              {attr.terms.map((term) => (
                <li key={term.id} className="flex items-center justify-between gap-4 px-4 py-3">
                  <span className="text-sm">{term.name}</span>
                  {attr.type === 'color' ? (
                    <TermColor term={term} />
                  ) : (
                    <TermImage term={term} />
                  )}
                </li>
              ))}
            </ul>
          )}
        </div>
      )}
    </div>
  );
}

function TermColor({ term }: { term: TermSwatch }) {
  const saveTerm = useSaveTermMutation();
  const [value, setValue] = useState(term.color);
  const timer = useRef<ReturnType<typeof setTimeout> | null>(null);

  // Keep the local control in sync if the cache is refreshed elsewhere.
  useEffect(() => setValue(term.color), [term.color]);

  // The native color input fires rapidly while dragging; debounce the write.
  const onChange = (color: string) => {
    setValue(color);
    if (timer.current) {
      clearTimeout(timer.current);
    }
    timer.current = setTimeout(() => {
      saveTerm.mutate({ term_id: term.id, color });
    }, 400);
  };

  return <ColorField value={value} onChange={onChange} />;
}

function TermImage({ term }: { term: TermSwatch }) {
  const saveTerm = useSaveTermMutation();

  return (
    <ImageField
      url={term.image.url}
      onPick={(id) => saveTerm.mutate({ term_id: term.id, image_id: id })}
      onClear={() => saveTerm.mutate({ term_id: term.id, image_id: 0 })}
    />
  );
}
