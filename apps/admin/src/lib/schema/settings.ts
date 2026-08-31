import { z } from 'zod';

export const settingsFormSchema = z.object({
  general: z.object({
    enabled: z.boolean(),
    showExtraSubtotal: z.boolean(),
    showTotalPrice: z.boolean(),
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
  }),
  advanced: z.object({
    hideZeroSubtotal: z.boolean(),
    loadScriptsAllPages: z.boolean(),
  }),
});

export type SettingsFormData = z.infer<typeof settingsFormSchema>;
