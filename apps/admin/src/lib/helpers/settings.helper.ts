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
    vswatchEnabled: true,
    vswatchSize: 'md',
    vswatchShape: 'circle',
    vswatchTooltip: true,
    vswatchShowLabel: false,
    vswatchLabelSeparator: ':',
    vswatchDefaultButton: false,
    vswatchPreloader: true,
    vswatchMaxVisible: 0,
    vswatchWidth: 0,
    vswatchHeight: 0,
    vswatchFontSize: 0,
    vswatchTickColor: '',
    vswatchCrossColor: '',
    vswatchImageSize: 'thumbnail',
    vswatchOosBehavior: 'blur',
    vswatchClearOnReselect: false,
    vswatchShowStock: false,
    vswatchShowOnArchive: false,
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

export const VSWATCH_OOS_BEHAVIORS = [
  { label: __('Blur (dim + strike)', 'flexa-extra'), value: 'blur' },
  { label: __('Hide', 'flexa-extra'), value: 'hide' },
  { label: __('Show as normal', 'flexa-extra'), value: 'none' },
];

export const VSWATCH_IMAGE_SIZES = [
  { label: 'Thumbnail', value: 'thumbnail' },
  { label: 'WooCommerce thumbnail', value: 'woocommerce_thumbnail' },
  { label: 'Medium', value: 'medium' },
  { label: 'Large', value: 'large' },
  { label: 'Full', value: 'full' },
];
