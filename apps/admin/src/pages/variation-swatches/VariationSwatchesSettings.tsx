import { useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import { zodResolver } from '@hookform/resolvers/zod';
import { motion } from 'framer-motion';
import {
  Circle,
  Eye,
  Image,
  Info,
  List,
  MousePointerClick,
  MoveHorizontal,
  MoveVertical,
  PackageOpen,
  Palette,
  Power,
  RefreshCw,
  RotateCcw,
  Ruler,
  Save,
  Slash,
  Store,
  Tag,
  Type,
} from 'lucide-react';
import { Controller, FormProvider, useForm } from 'react-hook-form';

import {
  DEFAULT_SETTINGS,
  SWATCH_SHAPES,
  SWATCH_SIZES,
  VSWATCH_IMAGE_SIZES,
  VSWATCH_OOS_BEHAVIORS,
} from '@/lib/helpers/settings.helper';
import { useSaveSettingsMutation, useSettingsQuery } from '@/lib/queries/settings';
import { SettingsFormData, settingsFormSchema } from '@/lib/schema/settings';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { ColorField } from '@/components/settings/ColorField';
import { SettingRow } from '@/components/settings/SettingRow';
import { TabCard } from '@/components/settings/TabCard';

/**
 * Settings for the variation-swatch module only. It loads the full settings
 * object and submits it whole (so other groups are preserved), but surfaces
 * just the swatch controls.
 */
export default function VariationSwatchesSettings() {
  const { data: settings } = useSettingsQuery();
  const saveSettings = useSaveSettingsMutation();

  const form = useForm<SettingsFormData>({
    resolver: zodResolver(settingsFormSchema),
    defaultValues: settings ?? DEFAULT_SETTINGS,
  });

  useEffect(() => {
    form.reset(settings ?? DEFAULT_SETTINGS);
  }, [settings, form]);

  const { control } = form;
  const onSubmit = (data: SettingsFormData) => saveSettings.mutate(data);
  const saving = saveSettings.isPending;

  return (
    <FormProvider {...form}>
      <form
        id="flexa-extra-vswatch-settings-form"
        onSubmit={form.handleSubmit(onSubmit)}
        className="mx-auto mt-8 max-w-3xl space-y-6 px-6 pb-16"
      >
        <div className="flex items-center justify-between gap-4">
          <div>
            <h1 className="text-2xl font-bold tracking-tight">{__('Settings', 'flexa-extra')}</h1>
            <p className="text-muted-foreground mt-1 text-sm">
              {__('Control how variation swatches look and behave on the storefront.', 'flexa-extra')}
            </p>
          </div>
          <Button type="submit" disabled={saving} size="lg">
            {saving ? (
              <>
                <motion.div
                  animate={{ rotate: 360 }}
                  transition={{ duration: 1, repeat: Infinity, ease: 'linear' }}
                >
                  <RotateCcw className="h-4 w-4" />
                </motion.div>
                {__('Saving...', 'flexa-extra')}
              </>
            ) : (
              <>
                <Save className="h-4 w-4" />
                {__('Save Settings', 'flexa-extra')}
              </>
            )}
          </Button>
        </div>

        {/* General */}
        <TabCard
          icon={Palette}
          title={__('Variation swatches', 'flexa-extra')}
          description={__('Applies to the color / image / button swatches on variable product attributes.', 'flexa-extra')}
        >
          <Controller
            control={control}
            name="style.vswatchEnabled"
            render={({ field }) => (
              <SettingRow
                icon={Power}
                label={__('Enable variation swatches', 'flexa-extra')}
                description={__('Turn attribute dropdowns into swatches on product pages.', 'flexa-extra')}
                control={<Switch checked={field.value} onCheckedChange={field.onChange} />}
              />
            )}
          />
          <Controller
            control={control}
            name="style.vswatchSize"
            render={({ field }) => (
              <SettingRow
                icon={Ruler}
                label={__('Swatch size', 'flexa-extra')}
                description={__('Preset size of variation attribute swatches.', 'flexa-extra')}
                control={<Select options={SWATCH_SIZES} className="w-40" {...field} />}
              />
            )}
          />
          <Controller
            control={control}
            name="style.vswatchShape"
            render={({ field }) => (
              <SettingRow
                icon={Circle}
                label={__('Swatch shape', 'flexa-extra')}
                description={__('Corner rounding of variation attribute swatches.', 'flexa-extra')}
                control={<Select options={SWATCH_SHAPES} className="w-40" {...field} />}
              />
            )}
          />
          <Controller
            control={control}
            name="style.vswatchTooltip"
            render={({ field }) => (
              <SettingRow
                icon={Info}
                label={__('Tooltips', 'flexa-extra')}
                description={__('Show the term name on hover as a tooltip.', 'flexa-extra')}
                control={<Switch checked={field.value} onCheckedChange={field.onChange} />}
              />
            )}
          />
        </TabCard>

        {/* Display */}
        <TabCard
          icon={Eye}
          title={__('Display', 'flexa-extra')}
          description={__('Labels and how attributes appear on the product page.', 'flexa-extra')}
        >
          <Controller
            control={control}
            name="style.vswatchShowLabel"
            render={({ field }) => (
              <SettingRow
                icon={Tag}
                label={__('Show selected value', 'flexa-extra')}
                description={__('Display an "Attribute: Value" line under the swatches.', 'flexa-extra')}
                control={<Switch checked={field.value} onCheckedChange={field.onChange} />}
              />
            )}
          />
          <Controller
            control={control}
            name="style.vswatchLabelSeparator"
            render={({ field }) => (
              <SettingRow
                icon={Type}
                label={__('Label separator', 'flexa-extra')}
                description={__('Character between the attribute name and the value.', 'flexa-extra')}
                control={<Input className="w-24" maxLength={4} {...field} />}
              />
            )}
          />
          <Controller
            control={control}
            name="style.vswatchDefaultButton"
            render={({ field }) => (
              <SettingRow
                icon={MousePointerClick}
                label={__('Default to button', 'flexa-extra')}
                description={__('Show attributes with no swatch type as buttons instead of the native dropdown.', 'flexa-extra')}
                control={<Switch checked={field.value} onCheckedChange={field.onChange} />}
              />
            )}
          />
          <Controller
            control={control}
            name="style.vswatchPreloader"
            render={({ field }) => (
              <SettingRow
                icon={RefreshCw}
                label={__('Loading spinner', 'flexa-extra')}
                description={__('Show a spinner while the variation updates.', 'flexa-extra')}
                control={<Switch checked={field.value} onCheckedChange={field.onChange} />}
              />
            )}
          />
          <Controller
            control={control}
            name="style.vswatchMaxVisible"
            render={({ field }) => (
              <SettingRow
                icon={List}
                label={__('Limit visible swatches', 'flexa-extra')}
                description={__('Show a "+N more" toggle after this many swatches per attribute. 0 = show all.', 'flexa-extra')}
                control={
                  <Input
                    type="number"
                    min={0}
                    max={100}
                    className="w-24"
                    value={field.value}
                    onChange={(e) => field.onChange(Number(e.target.value) || 0)}
                  />
                }
              />
            )}
          />
        </TabCard>

        {/* Appearance */}
        <TabCard
          icon={Ruler}
          title={__('Appearance', 'flexa-extra')}
          description={__('Fine-tune sizes and colors. Leave sizes at 0 to use the preset above.', 'flexa-extra')}
        >
          <Controller
            control={control}
            name="style.vswatchWidth"
            render={({ field }) => (
              <SettingRow
                icon={MoveHorizontal}
                label={__('Custom width (px)', 'flexa-extra')}
                description={__('Override swatch width. 0 = use preset size.', 'flexa-extra')}
                control={
                  <Input
                    type="number"
                    min={0}
                    max={200}
                    className="w-24"
                    value={field.value}
                    onChange={(e) => field.onChange(Number(e.target.value) || 0)}
                  />
                }
              />
            )}
          />
          <Controller
            control={control}
            name="style.vswatchHeight"
            render={({ field }) => (
              <SettingRow
                icon={MoveVertical}
                label={__('Custom height (px)', 'flexa-extra')}
                description={__('Override swatch height. 0 = use preset size.', 'flexa-extra')}
                control={
                  <Input
                    type="number"
                    min={0}
                    max={200}
                    className="w-24"
                    value={field.value}
                    onChange={(e) => field.onChange(Number(e.target.value) || 0)}
                  />
                }
              />
            )}
          />
          <Controller
            control={control}
            name="style.vswatchFontSize"
            render={({ field }) => (
              <SettingRow
                icon={Type}
                label={__('Button font size (px)', 'flexa-extra')}
                description={__('Font size for button swatches. 0 = inherit.', 'flexa-extra')}
                control={
                  <Input
                    type="number"
                    min={0}
                    max={100}
                    className="w-24"
                    value={field.value}
                    onChange={(e) => field.onChange(Number(e.target.value) || 0)}
                  />
                }
              />
            )}
          />
          <Controller
            control={control}
            name="style.vswatchTickColor"
            render={({ field }) => (
              <SettingRow
                icon={MousePointerClick}
                label={__('Selected color', 'flexa-extra')}
                description={__('Ring color of the selected swatch. Empty = theme color.', 'flexa-extra')}
                control={<ColorField value={field.value} onChange={field.onChange} />}
              />
            )}
          />
          <Controller
            control={control}
            name="style.vswatchCrossColor"
            render={({ field }) => (
              <SettingRow
                icon={Slash}
                label={__('Unavailable strike color', 'flexa-extra')}
                description={__('Color of the strike on unavailable swatches. Empty = default.', 'flexa-extra')}
                control={<ColorField value={field.value} onChange={field.onChange} />}
              />
            )}
          />
          <Controller
            control={control}
            name="style.vswatchImageSize"
            render={({ field }) => (
              <SettingRow
                icon={Image}
                label={__('Image size', 'flexa-extra')}
                description={__('Registered image size used for image swatches.', 'flexa-extra')}
                control={<Select options={VSWATCH_IMAGE_SIZES} className="w-52" {...field} />}
              />
            )}
          />
        </TabCard>

        {/* Availability */}
        <TabCard
          icon={Slash}
          title={__('Availability', 'flexa-extra')}
          description={__('How unavailable and out-of-stock combinations behave.', 'flexa-extra')}
        >
          <Controller
            control={control}
            name="style.vswatchOosBehavior"
            render={({ field }) => (
              <SettingRow
                icon={Slash}
                label={__('Unavailable swatches', 'flexa-extra')}
                description={__('What to do with swatches that are not selectable.', 'flexa-extra')}
                control={<Select options={VSWATCH_OOS_BEHAVIORS} className="w-52" {...field} />}
              />
            )}
          />
          <Controller
            control={control}
            name="style.vswatchClearOnReselect"
            render={({ field }) => (
              <SettingRow
                icon={RefreshCw}
                label={__('Clear on reselect', 'flexa-extra')}
                description={__('Click the selected swatch again to clear the choice.', 'flexa-extra')}
                control={<Switch checked={field.value} onCheckedChange={field.onChange} />}
              />
            )}
          />
          <Controller
            control={control}
            name="style.vswatchShowStock"
            render={({ field }) => (
              <SettingRow
                icon={PackageOpen}
                label={__('Show stock per swatch', 'flexa-extra')}
                description={__('Show "N left" on low-stock swatches and "Out of stock" when every matching variation is sold out. Needs the variation data on the page (products with many variations may not show it).', 'flexa-extra')}
                control={<Switch checked={field.value} onCheckedChange={field.onChange} />}
              />
            )}
          />
        </TabCard>

        {/* Shop pages */}
        <TabCard
          icon={Store}
          title={__('Shop pages', 'flexa-extra')}
          description={__('Show swatches in the shop and category product loops.', 'flexa-extra')}
        >
          <Controller
            control={control}
            name="style.vswatchShowOnArchive"
            render={({ field }) => (
              <SettingRow
                icon={Store}
                label={__('Show on shop / archive', 'flexa-extra')}
                description={__('Render swatches under products in the loop. Each links to the product with that option preselected.', 'flexa-extra')}
                control={<Switch checked={field.value} onCheckedChange={field.onChange} />}
              />
            )}
          />
        </TabCard>
      </form>
    </FormProvider>
  );
}
