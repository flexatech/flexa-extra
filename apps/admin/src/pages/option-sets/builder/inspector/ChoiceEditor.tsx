import { __ } from '@wordpress/i18n';
import { GripVertical, Plus, X } from 'lucide-react';
import { Controller, useFieldArray, useFormContext, useWatch } from 'react-hook-form';

import { Field, OptionSet } from '@/lib/schema/option-set';
import { createChoice } from '@/lib/fields/registry';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { PriceEditor } from './PriceEditor';

interface Props {
  fieldIndex: number;
}

export function ChoiceEditor({ fieldIndex }: Props) {
  const { control, register } = useFormContext<OptionSet>();
  const base = `fields.${fieldIndex}.options` as const;
  const { fields, append, remove } = useFieldArray({ control, name: base, keyName: '_rhfId' });

  const type = useWatch({ control, name: `fields.${fieldIndex}.type` }) as Field['type'];
  const isSwatch = type === 'swatch';

  return (
    <div className="space-y-2">
      <div className="flex items-center justify-between">
        <span className="text-sm font-medium">{__('Choices', 'flexa-extra')}</span>
        <Button
          type="button"
          variant="ghost"
          size="sm"
          onClick={() => append(createChoice())}
          className="h-7"
        >
          <Plus className="h-3.5 w-3.5" />
          {__('Add', 'flexa-extra')}
        </Button>
      </div>

      <div className="space-y-3">
        {fields.map((choice, i) => (
          <div key={choice._rhfId} className="border-border bg-muted/30 rounded-lg border p-2.5">
            <div className="flex items-center gap-2">
              <GripVertical className="text-muted-foreground h-4 w-4 shrink-0" />
              {isSwatch && (
                <Controller
                  control={control}
                  name={`${base}.${i}.color` as const}
                  render={({ field }) => (
                    <input
                      type="color"
                      value={field.value || '#000000'}
                      onChange={field.onChange}
                      className="border-border h-7 w-7 shrink-0 cursor-pointer rounded border p-0"
                      aria-label={__('Swatch color', 'flexa-extra')}
                    />
                  )}
                />
              )}
              <Input
                {...register(`${base}.${i}.label` as const)}
                placeholder={__('Choice label', 'flexa-extra')}
                className="h-8 flex-1"
              />
              <Button
                type="button"
                variant="ghost"
                size="icon"
                className="hover:text-destructive h-7 w-7 shrink-0"
                onClick={() => remove(i)}
              >
                <X className="h-4 w-4" />
              </Button>
            </div>
            <div className="mt-2 space-y-2 pl-6">
              <PriceEditor name={`${base}.${i}.price`} label={__('Extra charge', 'flexa-extra')} />
              <div className="flex items-center justify-between gap-2">
                <span className="text-muted-foreground text-xs">{__('Stock', 'flexa-extra')}</span>
                <Controller
                  control={control}
                  name={`${base}.${i}.stock` as const}
                  render={({ field }) => (
                    <Input
                      type="number"
                      min={0}
                      step={1}
                      inputMode="numeric"
                      value={field.value ?? ''}
                      onChange={(e) => {
                        const raw = e.target.value;
                        field.onChange(raw === '' ? null : Math.max(0, Math.floor(Number(raw))));
                      }}
                      placeholder={__('Unlimited', 'flexa-extra')}
                      className="h-8 w-28"
                      aria-label={__('Stock quantity', 'flexa-extra')}
                    />
                  )}
                />
              </div>
            </div>
          </div>
        ))}
        {fields.length === 0 && (
          <p className="text-muted-foreground text-xs">
            {__('Add at least one choice for shoppers to pick from.', 'flexa-extra')}
          </p>
        )}
      </div>
    </div>
  );
}
