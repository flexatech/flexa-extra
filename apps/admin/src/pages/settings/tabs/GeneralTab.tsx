import { __ } from '@wordpress/i18n';
import { ListTree, Power, Receipt, ShoppingCart, Sigma } from 'lucide-react';
import { Controller, useFormContext } from 'react-hook-form';

import { SettingsFormData } from '@/lib/schema/settings';
import { Switch } from '@/components/ui/switch';
import { SettingRow } from '@/components/settings/SettingRow';
import { TabCard } from '@/components/settings/TabCard';

export default function GeneralTab() {
  const { control } = useFormContext<SettingsFormData>();

  return (
    <TabCard
      icon={Power}
      title={__('General', 'flexa-extra')}
      description={__('Enable the plugin and choose what pricing information to show.', 'flexa-extra')}
    >
      <Controller
        control={control}
        name="general.enabled"
        render={({ field }) => (
          <SettingRow
            icon={Power}
            label={__('Enable extra options', 'flexa-extra')}
            description={__('Render assigned option sets on product pages.', 'flexa-extra')}
            control={<Switch checked={field.value} onCheckedChange={field.onChange} />}
          />
        )}
      />
      <Controller
        control={control}
        name="general.showExtraSubtotal"
        render={({ field }) => (
          <SettingRow
            icon={Receipt}
            label={__('Show extra subtotal', 'flexa-extra')}
            description={__('Display the summed price of selected options.', 'flexa-extra')}
            control={<Switch checked={field.value} onCheckedChange={field.onChange} />}
          />
        )}
      />
      <Controller
        control={control}
        name="general.showTotalPrice"
        render={({ field }) => (
          <SettingRow
            icon={Sigma}
            label={__('Show total price', 'flexa-extra')}
            description={__('Display product price plus extras.', 'flexa-extra')}
            control={<Switch checked={field.value} onCheckedChange={field.onChange} />}
          />
        )}
      />
      <Controller
        control={control}
        name="general.showPriceBreakdown"
        render={({ field }) => (
          <SettingRow
            icon={ListTree}
            label={__('Show price breakdown', 'flexa-extra')}
            description={__('List each selected option and its price on the product page.', 'flexa-extra')}
            control={<Switch checked={field.value} onCheckedChange={field.onChange} />}
          />
        )}
      />
      <Controller
        control={control}
        name="general.showValueInMiniCart"
        render={({ field }) => (
          <SettingRow
            icon={ShoppingCart}
            label={__('Show values in mini cart', 'flexa-extra')}
            description={__('List chosen option values in the cart widget.', 'flexa-extra')}
            control={<Switch checked={field.value} onCheckedChange={field.onChange} />}
          />
        )}
      />
    </TabCard>
  );
}
