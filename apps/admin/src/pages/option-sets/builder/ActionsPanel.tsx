import { __ } from '@wordpress/i18n';
import { Plus, X } from 'lucide-react';
import { Controller, type Path, useFieldArray, useFormContext, useWatch } from 'react-hook-form';

import { Field, OptionSet } from '@/lib/schema/option-set';
import { createAction } from '@/lib/fields/registry';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { PriceEditor } from './inspector/PriceEditor';

export function ActionsPanel() {
  const { control } = useFormContext<OptionSet>();
  const { fields, append, remove } = useFieldArray({ control, name: 'actions', keyName: '_rhfId' });

  const allFields = (useWatch({ control, name: 'fields' }) ?? []) as Field[];
  const fieldOptions = allFields
    .filter((f) => f.type !== 'heading')
    .map((f) => ({ label: f.label || __('(unnamed field)', 'flexa-extra'), value: f.id }));

  return (
    <div className="border-border bg-card mx-auto max-w-2xl space-y-6 rounded-xl border p-6">
      <div className="flex items-start justify-between gap-4">
        <div>
          <h2 className="text-lg font-semibold">{__('Fees & discounts', 'flexa-extra')}</h2>
          <p className="text-muted-foreground mt-1 text-sm">
            {__(
              'Add a fee or discount to the item when the shopper’s selections match your conditions.',
              'flexa-extra',
            )}
          </p>
        </div>
        <Button type="button" variant="outline" size="sm" onClick={() => append(createAction())}>
          <Plus className="h-4 w-4" />
          {__('Add rule', 'flexa-extra')}
        </Button>
      </div>

      {fields.length === 0 ? (
        <p className="text-muted-foreground rounded-lg border border-dashed p-6 text-center text-sm">
          {__('No fees or discounts yet. Add one to adjust the price based on selections.', 'flexa-extra')}
        </p>
      ) : (
        <div className="space-y-4">
          {fields.map((action, i) => (
            <ActionCard
              key={action._rhfId}
              index={i}
              fieldOptions={fieldOptions}
              onRemove={() => remove(i)}
            />
          ))}
        </div>
      )}
    </div>
  );
}

function ActionCard({
  index,
  fieldOptions,
  onRemove,
}: {
  index: number;
  fieldOptions: { label: string; value: string }[];
  onRemove: () => void;
}) {
  const { control, register } = useFormContext<OptionSet>();
  const base = `actions.${index}` as const;
  const { fields, append, remove } = useFieldArray({
    control,
    name: `${base}.rules`,
    keyName: '_rhfId',
  });

  return (
    <div className="border-border bg-muted/20 space-y-3 rounded-lg border p-4">
      <div className="flex items-center gap-2">
        <Controller
          control={control}
          name={`${base}.kind` as const}
          render={({ field }) => (
            <Select
              className="h-9 w-32 shrink-0"
              value={(field.value as string) ?? 'fee'}
              onChange={field.onChange}
              options={[
                { label: __('Fee', 'flexa-extra'), value: 'fee' },
                { label: __('Discount', 'flexa-extra'), value: 'discount' },
              ]}
            />
          )}
        />
        <Input
          {...register(`${base}.label` as const)}
          placeholder={__('Label (shown in cart)', 'flexa-extra')}
          className="h-9 flex-1"
        />
        <Button
          type="button"
          variant="ghost"
          size="icon"
          className="hover:text-destructive h-8 w-8 shrink-0"
          onClick={onRemove}
        >
          <X className="h-4 w-4" />
        </Button>
      </div>

      <PriceEditor name={`${base}.price`} label={__('Amount', 'flexa-extra')} />

      <div className="space-y-2 border-t pt-3">
        {fieldOptions.length === 0 ? (
          <p className="text-muted-foreground text-xs">
            {__('Add a field first to build a condition.', 'flexa-extra')}
          </p>
        ) : (
          <>
            <div className="flex items-center gap-2 text-xs">
              <span className="text-muted-foreground">{__('Apply when', 'flexa-extra')}</span>
              <Controller
                control={control}
                name={`${base}.match` as const}
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
              <span className="text-muted-foreground">
                {__('of these conditions match:', 'flexa-extra')}
              </span>
            </div>

            {fields.length === 0 && (
              <p className="text-muted-foreground text-xs italic">
                {__('No conditions: this always applies.', 'flexa-extra')}
              </p>
            )}

            <div className="space-y-2">
              {fields.map((rule, r) => (
                <ActionRuleRow
                  key={rule._rhfId}
                  base={`${base}.rules.${r}`}
                  fieldOptions={fieldOptions}
                  onRemove={() => remove(r)}
                />
              ))}
            </div>

            <Button
              type="button"
              variant="ghost"
              size="sm"
              className="h-7"
              onClick={() => append({ field: fieldOptions[0]?.value ?? '', operator: 'is', value: '' })}
            >
              <Plus className="h-3.5 w-3.5" />
              {__('Add condition', 'flexa-extra')}
            </Button>
          </>
        )}
      </div>
    </div>
  );
}

function ActionRuleRow({
  base,
  fieldOptions,
  onRemove,
}: {
  base: string;
  fieldOptions: { label: string; value: string }[];
  onRemove: () => void;
}) {
  const { control, register } = useFormContext<OptionSet>();
  const operatorPath = `${base}.operator` as Path<OptionSet>;
  const operator = useWatch({ control, name: operatorPath }) as string | undefined;
  const needsValue = operator === 'is' || operator === 'is_not';

  return (
    <div className="border-border bg-background flex items-center gap-1.5 rounded-md border p-2">
      <Controller
        control={control}
        name={`${base}.field` as Path<OptionSet>}
        render={({ field }) => (
          <Select
            className="h-8 min-w-0 flex-1"
            value={(field.value as string) ?? ''}
            onChange={field.onChange}
            options={fieldOptions}
          />
        )}
      />
      <Controller
        control={control}
        name={operatorPath}
        render={({ field }) => (
          <Select
            className="h-8 w-24 shrink-0"
            value={(field.value as string) ?? 'is'}
            onChange={field.onChange}
            options={[
              { label: __('is', 'flexa-extra'), value: 'is' },
              { label: __('is not', 'flexa-extra'), value: 'is_not' },
              { label: __('empty', 'flexa-extra'), value: 'empty' },
              { label: __('not empty', 'flexa-extra'), value: 'not_empty' },
            ]}
          />
        )}
      />
      {needsValue && (
        <Input
          {...register(`${base}.value` as Path<OptionSet>)}
          placeholder={__('value', 'flexa-extra')}
          className="h-8 w-24 shrink-0"
        />
      )}
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
  );
}
