import { __ } from '@wordpress/i18n';
import { Plus, X } from 'lucide-react';
import { Controller, useFieldArray, useFormContext, useWatch } from 'react-hook-form';

import { OptionSet } from '@/lib/schema/option-set';
import { ResourceType } from '@/lib/api/resources';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { cn } from '@/lib/utils';
import { ResourcePicker } from './inspector/ResourcePicker';

const MODES = [
  { value: 'all', label: __('All products', 'flexa-extra'), hint: __('Applies everywhere.', 'flexa-extra') },
  { value: 'manual', label: __('Specific products', 'flexa-extra'), hint: __('Pick products by hand.', 'flexa-extra') },
  { value: 'conditions', label: __('By condition', 'flexa-extra'), hint: __('Match by category, tag, price…', 'flexa-extra') },
] as const;

export function AssignmentPanel() {
  const { control } = useFormContext<OptionSet>();
  const mode = useWatch({ control, name: 'targeting.mode' }) as string;

  return (
    <div className="border-border bg-card mx-auto max-w-2xl space-y-6 rounded-xl border p-6">
      <div>
        <h2 className="text-lg font-semibold">{__('Where should this apply?', 'flexa-extra')}</h2>
        <p className="text-muted-foreground mt-1 text-sm">
          {__('Choose which products show these extra options.', 'flexa-extra')}
        </p>
      </div>

      <Controller
        control={control}
        name="targeting.mode"
        render={({ field }) => (
          <div className="grid grid-cols-3 gap-3">
            {MODES.map((m) => (
              <button
                key={m.value}
                type="button"
                onClick={() => field.onChange(m.value)}
                className={cn(
                  'rounded-lg border p-3 text-left transition-colors',
                  field.value === m.value
                    ? 'border-primary ring-primary/20 ring-2'
                    : 'border-border hover:border-primary/50',
                )}
              >
                <p className="text-sm font-medium">{m.label}</p>
                <p className="text-muted-foreground mt-0.5 text-xs">{m.hint}</p>
              </button>
            ))}
          </div>
        )}
      />

      {mode === 'manual' && (
        <div className="space-y-2">
          <label className="text-sm font-medium">{__('Products', 'flexa-extra')}</label>
          <Controller
            control={control}
            name="targeting.productIds"
            render={({ field }) => (
              <ResourcePicker
                type="product"
                value={(field.value as number[]) ?? []}
                onChange={field.onChange}
                placeholder={__('Search products…', 'flexa-extra')}
              />
            )}
          />
        </div>
      )}

      {mode === 'conditions' && <ConditionsEditor />}
    </div>
  );
}

function ConditionsEditor() {
  const { control } = useFormContext<OptionSet>();
  const { fields, append, remove } = useFieldArray({
    control,
    name: 'targeting.conditions',
    keyName: '_rhfId',
  });

  return (
    <div className="space-y-3">
      <div className="flex items-center gap-2 text-sm">
        <span className="text-muted-foreground">{__('Match', 'flexa-extra')}</span>
        <Controller
          control={control}
          name="targeting.match"
          render={({ field }) => (
            <Select
              className="h-8"
              value={(field.value as string) ?? 'any'}
              onChange={field.onChange}
              options={[
                { label: __('any', 'flexa-extra'), value: 'any' },
                { label: __('all', 'flexa-extra'), value: 'all' },
              ]}
            />
          )}
        />
        <span className="text-muted-foreground">{__('of the conditions below', 'flexa-extra')}</span>
      </div>

      <div className="space-y-2">
        {fields.map((cond, i) => (
          <ConditionRow key={cond._rhfId} index={i} onRemove={() => remove(i)} />
        ))}
        {fields.length === 0 && (
          <p className="text-muted-foreground text-xs">
            {__('No conditions yet — this set would not apply anywhere.', 'flexa-extra')}
          </p>
        )}
      </div>

      <Button
        type="button"
        variant="outline"
        size="sm"
        onClick={() => append({ type: 'category', operator: 'is', value: '' })}
      >
        <Plus className="h-3.5 w-3.5" />
        {__('Add condition', 'flexa-extra')}
      </Button>
    </div>
  );
}

function ConditionRow({ index, onRemove }: { index: number; onRemove: () => void }) {
  const { control } = useFormContext<OptionSet>();
  const base = `targeting.conditions.${index}` as const;
  const type = useWatch({ control, name: `${base}.type` }) as string;

  const resourceType: ResourceType | null =
    type === 'category' ? 'category' : type === 'tag' ? 'tag' : type === 'product' ? 'product' : null;

  const operatorOptions =
    type === 'price'
      ? [
          { label: __('greater than', 'flexa-extra'), value: 'gt' },
          { label: __('less than', 'flexa-extra'), value: 'lt' },
        ]
      : type === 'stock'
        ? [
            { label: __('in stock', 'flexa-extra'), value: 'in_stock' },
            { label: __('out of stock', 'flexa-extra'), value: 'out_of_stock' },
          ]
        : [
            { label: __('is', 'flexa-extra'), value: 'is' },
            { label: __('is not', 'flexa-extra'), value: 'is_not' },
          ];

  return (
    <div className="border-border bg-muted/30 space-y-2 rounded-lg border p-3">
      <div className="flex items-center gap-1.5">
        <Controller
          control={control}
          name={`${base}.type` as const}
          render={({ field }) => (
            <Select
              className="h-8 w-32 shrink-0"
              value={(field.value as string) ?? 'category'}
              onChange={field.onChange}
              options={[
                { label: __('Category', 'flexa-extra'), value: 'category' },
                { label: __('Tag', 'flexa-extra'), value: 'tag' },
                { label: __('Product', 'flexa-extra'), value: 'product' },
                { label: __('Price', 'flexa-extra'), value: 'price' },
                { label: __('Stock', 'flexa-extra'), value: 'stock' },
              ]}
            />
          )}
        />
        <Controller
          control={control}
          name={`${base}.operator` as const}
          render={({ field }) => (
            <Select
              className="h-8 min-w-0 flex-1"
              value={(field.value as string) ?? operatorOptions[0].value}
              onChange={field.onChange}
              options={operatorOptions}
            />
          )}
        />
        <Button
          type="button"
          variant="ghost"
          size="icon"
          className="hover:text-destructive h-7 w-7 shrink-0"
          onClick={onRemove}
        >
          <X className="h-4 w-4" />
        </Button>
      </div>

      {resourceType && (
        <Controller
          control={control}
          name={`${base}.value` as const}
          render={({ field }) => (
            <ResourcePicker
              type={resourceType}
              value={parseIds(field.value as string)}
              onChange={(ids) => field.onChange(ids.length ? String(ids[ids.length - 1]) : '')}
              placeholder={__('Search…', 'flexa-extra')}
            />
          )}
        />
      )}

      {type === 'price' && (
        <Controller
          control={control}
          name={`${base}.value` as const}
          render={({ field }) => (
            <Input
              type="number"
              step="any"
              value={(field.value as string) ?? ''}
              onChange={(e) => field.onChange(e.target.value)}
              placeholder={__('Amount', 'flexa-extra')}
            />
          )}
        />
      )}
    </div>
  );
}

function parseIds(value: string): number[] {
  const n = Number(value);
  return value && !Number.isNaN(n) ? [n] : [];
}
