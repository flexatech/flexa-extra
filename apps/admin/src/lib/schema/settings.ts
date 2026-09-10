import { z } from 'zod';

export const settingsFormSchema = z.object({
  general: z.object({
    enabled: z.boolean(),
    showExtraSubtotal: z.boolean(),
    showTotalPrice: z.boolean(),
    showPriceBreakdown: z.boolean(),
    showValueInMiniCart: z.boolean(),
  }),
  display: z.object({
    subtotalLabel: z.string(),
    totalPriceLabel: z.string(),
    position: z.enum(['before_add_to_cart', 'after_add_to_cart']),
  }),
  style: z.object({
    swatchSize: z.enum(['sm', 'md', 'lg']),
    swatchShape: z.enum(['circle', 'rounded', 'square']),
    showTooltips: z.boolean(),
    buttonBg: z.string(),
    buttonText: z.string(),
    buttonActiveBg: z.string(),
    buttonActiveText: z.string(),
    vswatchEnabled: z.boolean(),
    vswatchSize: z.enum(['sm', 'md', 'lg']),
    vswatchShape: z.enum(['circle', 'rounded', 'square']),
    vswatchTooltip: z.boolean(),
    vswatchShowLabel: z.boolean(),
    vswatchLabelSeparator: z.string(),
    vswatchDefaultButton: z.boolean(),
    vswatchPreloader: z.boolean(),
    vswatchMaxVisible: z.number().int().min(0).max(100),
    vswatchWidth: z.number().int().min(0).max(200),
    vswatchHeight: z.number().int().min(0).max(200),
    vswatchFontSize: z.number().int().min(0).max(100),
    vswatchTickColor: z.string(),
    vswatchCrossColor: z.string(),
    vswatchImageSize: z.string(),
    vswatchOosBehavior: z.enum(['blur', 'hide', 'none']),
    vswatchClearOnReselect: z.boolean(),
    vswatchShowStock: z.boolean(),
    vswatchShowOnArchive: z.boolean(),
  }),
  advanced: z.object({
    hideZeroSubtotal: z.boolean(),
    loadScriptsAllPages: z.boolean(),
  }),
});

export type SettingsFormData = z.infer<typeof settingsFormSchema>;
