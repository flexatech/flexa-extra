import { __ } from '@wordpress/i18n';
import { MousePointer2 } from 'lucide-react';
import { Controller, useFormContext, useWatch } from 'react-hook-form';

import { Field, OptionSet } from '@/lib/schema/option-set';
import { isChoiceType, isInputType } from '@/lib/fields/registry';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Labeled } from './Labeled';
import { PriceEditor } from './inspector/PriceEditor';
import { ChoiceEditor } from './inspector/ChoiceEditor';
import { LogicEditor } from './inspector/LogicEditor';

interface Props {
  selectedId: string | null;
  onDeleted: () => void;
}

export function Inspector({ selectedId }: Props) {
  const { control, register } = useFormContext<OptionSet>();
  const fields = (useWatch({ control, name: 'fields' }) ?? []) as Field[];
  const index = fields.findIndex((f) => f.id === selectedId);
  const field = index >= 0 ? fields[index] : undefined;

  if (!field) {
    return (
      <aside className="border-border bg-card text-muted-foreground flex h-fit min-h-[320px] flex-col items-center justify-center rounded-xl border p-6 text-center">
        <MousePointer2 className="mb-3 h-7 w-7" />
        <p className="text-sm font-medium">{__('No field selected', 'flexa-extra')}</p>
        <p className="mt-1 text-xs">{__('Select a field to edit its settings.', 'flexa-extra')}</p>
      </aside>
    );
  }

  const type = field.type;
  const base = `fields.${index}` as const;

  return (
    <aside className="border-border bg-card h-fit space-y-5 rounded-xl border p-4">
      <div>
        <p className="text-muted-foreground text-xs font-semibold uppercase tracking-wide">
          {type} {__('field', 'flexa-extra')}
        </p>
      </div>

      <Labeled label={__('Label', 'flexa-extra')}>
        <Input {...register(`${base}.label` as const)} />
      </Labeled>

      <Labeled
        label={__('Field key', 'flexa-extra')}
        hint={__('Unique key stored with the order. Lowercase, no spaces.', 'flexa-extra')}
      >
        <Input {...register(`${base}.name` as const)} placeholder="e.g. gift_message" />
      </Labeled>

      {type !== 'heading' && (
        <Controller
          control={control}
          name={`${base}.required` as const}
          render={({ field: f }) => (
            <label className="flex items-center justify-between">
              <span className="text-sm font-medium">{__('Required', 'flexa-extra')}</span>
              <Switch checked={!!f.value} onCheckedChange={f.onChange} />
            </label>
          )}
        />
      )}

      {isInputType(type) && (
        <Labeled label={__('Placeholder', 'flexa-extra')}>
          <Input {...register(`${base}.placeholder` as const)} />
        </Labeled>
      )}

      <Labeled label={__('Tooltip', 'flexa-extra')}>
        <Input {...register(`${base}.tooltip` as const)} />
      </Labeled>

      <Labeled
        label={__('CSS class', 'flexa-extra')}
        hint={__('Extra class(es) added to the field wrapper for custom styling.', 'flexa-extra')}
      >
        <Input {...register(`${base}.cssClass` as const)} placeholder="e.g. my-field highlight" />
      </Labeled>

      {type === 'text' && (
        <>
          <Labeled label={__('Text format', 'flexa-extra')}>
            <Controller
              control={control}
              name={`${base}.textFormat` as const}
              render={({ field: f }) => (
                <Select
                  className="w-full"
                  value={f.value ?? 'text'}
                  onChange={f.onChange}
                  options={[
                    { label: __('Plain text', 'flexa-extra'), value: 'text' },
                    { label: __('Email', 'flexa-extra'), value: 'email' },
                    { label: __('URL', 'flexa-extra'), value: 'url' },
                    { label: __('Custom regex', 'flexa-extra'), value: 'regex' },
                  ]}
                />
              )}
            />
          </Labeled>
          {field.textFormat === 'regex' && (
            <Labeled label={__('Regex pattern', 'flexa-extra')}>
              <Input {...register(`${base}.regex` as const)} placeholder="^[A-Z]{3}$" />
            </Labeled>
          )}
        </>
      )}

      {type === 'number' && (
        <div className="grid grid-cols-3 gap-2">
          <Labeled label={__('Min', 'flexa-extra')}>
            <Input
              type="number"
              {...register(`${base}.min` as const, { setValueAs: toNullableNumber })}
            />
          </Labeled>
          <Labeled label={__('Max', 'flexa-extra')}>
            <Input
              type="number"
              {...register(`${base}.max` as const, { setValueAs: toNullableNumber })}
            />
          </Labeled>
          <Labeled label={__('Step', 'flexa-extra')}>
            <Input
              type="number"
              {...register(`${base}.step` as const, { setValueAs: toNullableNumber })}
            />
          </Labeled>
        </div>
      )}

      {type === 'date_picker' && (
        <>
          <div className="grid grid-cols-2 gap-2">
            <Labeled label={__('Earliest date', 'flexa-extra')}>
              <Input type="date" {...register(`${base}.minDate` as const)} />
            </Labeled>
            <Labeled label={__('Latest date', 'flexa-extra')}>
              <Input type="date" {...register(`${base}.maxDate` as const)} />
            </Labeled>
          </div>
          <Labeled
            label={__('Disabled dates', 'flexa-extra')}
            hint={__('One date per line (YYYY-MM-DD). These dates cannot be selected.', 'flexa-extra')}
          >
            <Controller
              control={control}
              name={`${base}.disabledDates` as const}
              render={({ field: f }) => (
                <textarea
                  className="border-input bg-background w-full rounded-md border px-3 py-2 text-sm"
                  rows={3}
                  placeholder={'2026-12-25\n2027-01-01'}
                  value={(f.value ?? []).join('\n')}
                  onChange={(e) =>
                    f.onChange(
                      e.target.value
                        .split(/[\s,]+/)
                        .map((s) => s.trim())
                        .filter(Boolean),
                    )
                  }
                />
              )}
            />
          </Labeled>
          <Labeled
            label={__('Date format', 'flexa-extra')}
            hint={__('PHP date format (e.g. F j, Y). Leave blank to use the site date format.', 'flexa-extra')}
          >
            <Input {...register(`${base}.dateFormat` as const)} placeholder="F j, Y" />
          </Labeled>
        </>
      )}

      {isInputType(type) && (
        <Labeled label={__('Default value', 'flexa-extra')}>
          <Input
            type={type === 'date_picker' ? 'date' : type === 'color_picker' ? 'color' : 'text'}
            {...register(`${base}.default` as const)}
          />
        </Labeled>
      )}

      {isInputType(type) && (
        <div className="border-border border-t pt-4">
          <PriceEditor name={`${base}.price`} label={__('Field price', 'flexa-extra')} />
        </div>
      )}

      {isChoiceType(type) && (
        <div className="border-border space-y-4 border-t pt-4">
          {type !== 'checkbox' && (
            <Controller
              control={control}
              name={`${base}.multiple` as const}
              render={({ field: f }) => (
                <label className="flex items-center justify-between">
                  <span className="text-sm font-medium">
                    {__('Allow multiple', 'flexa-extra')}
                  </span>
                  <Switch checked={!!f.value} onCheckedChange={f.onChange} />
                </label>
              )}
            />
          )}
          {(type === 'checkbox' || field.multiple === true) && (
            <div className="grid grid-cols-2 gap-2">
              <Labeled
                label={__('Min choices', 'flexa-extra')}
                hint={__('Leave blank for no minimum.', 'flexa-extra')}
              >
                <Input
                  type="number"
                  min={0}
                  {...register(`${base}.minSelect` as const, { setValueAs: toNullableNumber })}
                />
              </Labeled>
              <Labeled
                label={__('Max choices', 'flexa-extra')}
                hint={__('Leave blank for no maximum.', 'flexa-extra')}
              >
                <Input
                  type="number"
                  min={0}
                  {...register(`${base}.maxSelect` as const, { setValueAs: toNullableNumber })}
                />
              </Labeled>
            </div>
          )}
          <ChoiceEditor fieldIndex={index} />
        </div>
      )}

      <div className="border-border border-t pt-4">
        <LogicEditor fieldIndex={index} currentFieldId={field.id} />
      </div>
    </aside>
  );
}

function toNullableNumber(value: string): number | null {
  if (value === '' || value === null || value === undefined) return null;
  const n = Number(value);
  return Number.isNaN(n) ? null : n;
}
