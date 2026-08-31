import { __ } from '@wordpress/i18n';
import { MapPin, Receipt, SlidersHorizontal, Tag } from 'lucide-react';
import { Controller, useFormContext } from 'react-hook-form';

import { DISPLAY_POSITIONS } from '@/lib/helpers/settings.helper';
import { SettingsFormData } from '@/lib/schema/settings';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { SettingRow } from '@/components/settings/SettingRow';
import { TabCard } from '@/components/settings/TabCard';

export default function DisplayTab() {
  const { control } = useFormContext<SettingsFormData>();

  return (
    <TabCard
      icon={SlidersHorizontal}
      title={__('Display', 'flexa-extra')}
      description={__('Customize labels and where options appear on the product page.', 'flexa-extra')}
    >
      <Controller
        control={control}
        name="display.subtotalLabel"
        render={({ field }) => (
          <SettingRow
            icon={Receipt}
            label={__('Extra subtotal label', 'flexa-extra')}
            description={__('Text shown before the extras subtotal.', 'flexa-extra')}
            control={<Input {...field} className="w-56" />}
          />
        )}
      />
      <Controller
        control={control}
        name="display.totalPriceLabel"
        render={({ field }) => (
          <SettingRow
            icon={Tag}
            label={__('Total price label', 'flexa-extra')}
            description={__('Text shown before the total price.', 'flexa-extra')}
            control={<Input {...field} className="w-56" />}
          />
        )}
      />
      <Controller
        control={control}
        name="display.position"
        render={({ field }) => (
          <SettingRow
            icon={MapPin}
            label={__('Options position', 'flexa-extra')}
            description={__('Where the option fields render on the product page.', 'flexa-extra')}
            control={<Select options={DISPLAY_POSITIONS} className="w-56" {...field} />}
          />
        )}
      />
    </TabCard>
  );
}
