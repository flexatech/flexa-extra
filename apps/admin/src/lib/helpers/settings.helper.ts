import { __ } from '@wordpress/i18n';

import { SettingsFormData } from '@/lib/schema/settings';

export const DEFAULT_SETTINGS: SettingsFormData = {
  general: {
    enabled: true,
    showExtraSubtotal: true,
    showTotalPrice: true,
    showPriceBreakdown: true,
    showValueInMiniCart: true,
  },
  display: {
    subtotalLabel: __('Extra subtotal:', 'flexa-extra'),
    totalPriceLabel: __('Total price:', 'flexa-extra'),
    position: 'before_add_to_cart',
  },
  style: {
    swatchSize: 'md',
    swatchShape: 'circle',
    showTooltips: true,
    buttonBg: '',
    buttonText: '',
    buttonActiveBg: '',
    buttonActiveText: '',
  },
  advanced: {
    hideZeroSubtotal: true,
    loadScriptsAllPages: false,
  },
};

export const DISPLAY_POSITIONS = [
  {
    label: __('Before Add to Cart button', 'flexa-extra'),
    value: 'before_add_to_cart',
  },
  {
    label: __('After Add to Cart button', 'flexa-extra'),
    value: 'after_add_to_cart',
  },
];

export const SWATCH_SIZES = [
  { label: __('Small', 'flexa-extra'), value: 'sm' },
  { label: __('Medium', 'flexa-extra'), value: 'md' },
  { label: __('Large', 'flexa-extra'), value: 'lg' },
];

export const SWATCH_SHAPES = [
  { label: __('Circle', 'flexa-extra'), value: 'circle' },
  { label: __('Rounded', 'flexa-extra'), value: 'rounded' },
  { label: __('Square', 'flexa-extra'), value: 'square' },
];
