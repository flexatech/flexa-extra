import { __ } from '@wordpress/i18n';
import { Controller, type Path, useFormContext } from 'react-hook-form';

import { OptionSet } from '@/lib/schema/option-set';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';

interface Props {
  /** Path to the price object, e.g. `fields.0.price`. */
  name: string;
  label: string;
}

export function PriceEditor({ name, label }: Props) {
  const { control, register, watch } = useFormContext<OptionSet>();
  const symbol = window.flexaExtra?.currency_settings?.symbol ?? '$';

  const typePath = `${name}.type` as Path<OptionSet>;
  const amountPath = `${name}.amount` as Path<OptionSet>;
  const priceType = watch(typePath) as string | undefined;

  return (
    <div className="space-y-1.5">
      <label className="text-foreground block text-sm font-medium">{label}</label>
      <div className="flex gap-2">
        <Controller
          control={control}
          name={typePath}
          render={({ field }) => (
            <Select
              className="w-32 shrink-0"
              value={(field.value as string) ?? 'none'}
              onChange={field.onChange}
              options={[
                { label: __('No charge', 'flexa-extra'), value: 'none' },
                { label: __('Fixed', 'flexa-extra'), value: 'fixed' },
                { label: __('Percent', 'flexa-extra'), value: 'percent' },
              ]}
            />
          )}
        />
        {priceType && priceType !== 'none' && (
          <div className="relative flex-1">
            <span className="text-muted-foreground pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm">
              {priceType === 'percent' ? '%' : symbol}
            </span>
            <Input
              type="number"
              step="any"
              className="pl-7"
              {...register(amountPath, { valueAsNumber: true })}
            />
          </div>
        )}
      </div>
    </div>
  );
}
