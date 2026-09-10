import { SettingsFormData } from '@/lib/schema/settings';
import { FieldCatalogEntry } from '@/lib/fields/registry';
import { OnboardingState } from '@/lib/api/onboarding';

declare global {
  interface Window {
    flexaExtra: {
      plugin_url: string;
      rest_url: string;
      rest_nonce: string;
      rest_base: string;
      settings?: SettingsFormData;
      field_catalog?: FieldCatalogEntry[];
      onboarding?: OnboardingState;
      /** True when another swatches plugin is active and Flexa defers to it (Pha 5). */
      variation_swatches_deferred?: boolean;
      currency_settings: {
        currency: string;
        symbol: string;
        position: string;
        thousand_sep: string;
        decimal_sep: string;
        num_decimals: number;
      };
    };
    wp: Record<string, unknown>;
  }
}

export {};
