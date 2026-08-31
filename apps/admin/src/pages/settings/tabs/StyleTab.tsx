import { __ } from '@wordpress/i18n';
import { Circle, MousePointerClick, Palette, Ruler, Square, Info } from 'lucide-react';
import { Controller, useFormContext } from 'react-hook-form';

import { SWATCH_SHAPES, SWATCH_SIZES } from '@/lib/helpers/settings.helper';
import { SettingsFormData } from '@/lib/schema/settings';
import { Select } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { ColorField } from '@/components/settings/ColorField';
import { SettingRow } from '@/components/settings/SettingRow';
import { TabCard } from '@/components/settings/TabCard';

export default function StyleTab() {
  const { control } = useFormContext<SettingsFormData>();

  return (
    <TabCard
      icon={Palette}
      title={__('Style', 'flexa-extra')}
      description={__('Fine-tune how swatches, buttons and tooltips look on the storefront.', 'flexa-extra')}
    >
      <Controller
        control={control}
        name="style.swatchSize"
        render={({ field }) => (
          <SettingRow
            icon={Ruler}
            label={__('Swatch size', 'flexa-extra')}
            description={__('Size of color / image swatch chips.', 'flexa-extra')}
            control={<Select options={SWATCH_SIZES} className="w-40" {...field} />}
          />
        )}
      />
      <Controller
        control={control}
        name="style.swatchShape"
        render={({ field }) => (
          <SettingRow
            icon={Circle}
            label={__('Swatch shape', 'flexa-extra')}
            description={__('Corner rounding of swatch chips.', 'flexa-extra')}
            control={<Select options={SWATCH_SHAPES} className="w-40" {...field} />}
          />
        )}
      />
      <Controller
        control={control}
        name="style.showTooltips"
        render={({ field }) => (
          <SettingRow
            icon={Info}
            label={__('Show tooltips', 'flexa-extra')}
            description={__('Display the help markers next to field labels.', 'flexa-extra')}
            control={<Switch checked={field.value} onCheckedChange={field.onChange} />}
          />
        )}
      />
      <Controller
        control={control}
        name="style.buttonBg"
        render={({ field }) => (
          <SettingRow
            icon={Square}
            label={__('Button background', 'flexa-extra')}
            description={__('Background of unselected option buttons.', 'flexa-extra')}
            control={<ColorField value={field.value} onChange={field.onChange} />}
          />
        )}
      />
      <Controller
        control={control}
        name="style.buttonText"
        render={({ field }) => (
          <SettingRow
            icon={Square}
            label={__('Button text', 'flexa-extra')}
            description={__('Text color of unselected option buttons.', 'flexa-extra')}
            control={<ColorField value={field.value} onChange={field.onChange} />}
          />
        )}
      />
      <Controller
        control={control}
        name="style.buttonActiveBg"
        render={({ field }) => (
          <SettingRow
            icon={MousePointerClick}
            label={__('Selected button background', 'flexa-extra')}
            description={__('Background of the selected option button.', 'flexa-extra')}
            control={<ColorField value={field.value} onChange={field.onChange} />}
          />
        )}
      />
      <Controller
        control={control}
        name="style.buttonActiveText"
        render={({ field }) => (
          <SettingRow
            icon={MousePointerClick}
            label={__('Selected button text', 'flexa-extra')}
            description={__('Text color of the selected option button.', 'flexa-extra')}
            control={<ColorField value={field.value} onChange={field.onChange} />}
          />
        )}
      />
    </TabCard>
  );
}
