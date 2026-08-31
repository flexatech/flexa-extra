import { __ } from '@wordpress/i18n';
import { Plus, X } from 'lucide-react';
import { Controller, type Path, useFieldArray, useFormContext, useWatch } from 'react-hook-form';

import { Field, OptionSet } from '@/lib/schema/option-set';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';

interface Props {
  fieldIndex: number;
  currentFieldId: string;
}

export function LogicEditor({ fieldIndex, currentFieldId }: Props) {
  const { control } = useFormContext<OptionSet>();
  const base = `fields.${fieldIndex}.logic` as const;

  const enabled = useWatch({ control, name: `${base}.enabled` }) as boolean | undefined;
  const allFields = (useWatch({ control, name: 'fields' }) ?? []) as Field[];
  const otherFields = allFields.filter((f) => f.id !== currentFieldId && f.type !== 'heading');

  const { fields, append, remove } = useFieldArray({ control, name: `${base}.rules`, keyName: '_rhfId' });

  const fieldOptions = otherFields.map((f) => ({
    label: f.label || __('(unnamed field)', 'flexa-extra'),
    value: f.id,
  }));

  return (
    <div className="space-y-3">
      <Controller
        control={control}
        name={`${base}.enabled` as const}
        render={({ field }) => (
          <label className="flex items-center justify-between">
            <span className="text-sm font-medium">{__('Conditional logic', 'flexa-extra')}</span>
            <Switch checked={!!field.value} onCheckedChange={field.onChange} />
          </label>
        )}
      />

      {enabled && (
        <div className="space-y-3">
          {otherFields.length === 0 ? (
            <p className="text-muted-foreground text-xs">
              {__('Add another field first to use it as a condition.', 'flexa-extra')}
            </p>
          ) : (
            <>
              <div className="flex items-center gap-2 text-xs">
                <Controller
                  control={control}
                  name={`${base}.action` as const}
                  render={({ field }) => (
                    <Select
                      className="h-8"
                      value={(field.value as string) ?? 'show'}
                      onChange={field.onChange}
                      options={[
                        { label: __('Show', 'flexa-extra'), value: 'show' },
                        { label: __('Hide', 'flexa-extra'), value: 'hide' },
                      ]}
                    />
                  )}
                />
                <span className="text-muted-foreground">{__('this field if', 'flexa-extra')}</span>
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
                <span className="text-muted-foreground">{__('match:', 'flexa-extra')}</span>
              </div>

              <div className="space-y-2">
                {fields.map((rule, i) => (
                  <RuleRow
                    key={rule._rhfId}
                    base={`${base}.rules.${i}`}
                    fieldOptions={fieldOptions}
                    onRemove={() => remove(i)}
                  />
                ))}
              </div>

              <Button
                type="button"
                variant="ghost"
                size="sm"
                className="h-7"
                onClick={() =>
                  append({ field: fieldOptions[0]?.value ?? '', operator: 'is', value: '' })
                }
              >
                <Plus className="h-3.5 w-3.5" />
                {__('Add rule', 'flexa-extra')}
              </Button>
            </>
          )}
        </div>
      )}
    </div>
  );
}

function RuleRow({
  base,
  fieldOptions,
  onRemove,
}: {
  base: string;
  fieldOptions: { label: string; value: string }[];
  onRemove: () => void;
}) {
  const { control, register } = useFormContext<OptionSet>();
  const fieldPath = `${base}.field` as Path<OptionSet>;
  const operatorPath = `${base}.operator` as Path<OptionSet>;
  const valuePath = `${base}.value` as Path<OptionSet>;
  const operator = useWatch({ control, name: operatorPath }) as string | undefined;
  const needsValue = operator === 'is' || operator === 'is_not';

  return (
    <div className="border-border bg-muted/30 flex items-center gap-1.5 rounded-md border p-2">
      <Controller
        control={control}
        name={fieldPath}
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
          {...register(valuePath)}
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
