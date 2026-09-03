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
