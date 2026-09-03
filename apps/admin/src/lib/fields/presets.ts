import { __ } from '@wordpress/i18n';

import { OptionSetInput } from '@/lib/api/option-sets';
import { createChoice, createField, emptyTargeting } from '@/lib/fields/registry';
import { Field, FieldChoice, FieldType, PriceRule } from '@/lib/schema/option-set';

/**
 * Starter templates for the option-set builder. Each preset is built from the
 * same field factories the builder uses, so every instance gets fresh unique
 * ids and a shape that round-trips through the PHP sanitizer unchanged. Picking
 * a preset creates a new draft the merchant can edit; nothing here is locked.
 */

export interface PresetDefinition {
  id: string;
  title: string;
  description: string;
  build: () => OptionSetInput;
}

const fixed = (amount: number): PriceRule => ({ type: 'fixed', amount });
const percent = (amount: number): PriceRule => ({ type: 'percent', amount });

function field(type: FieldType, overrides: Partial<Field>): Field {
  return { ...createField(type), ...overrides };
}

function choice(label: string, value: string, overrides: Partial<FieldChoice> = {}): FieldChoice {
  return { ...createChoice(label), value, ...overrides };
}

function optionSet(name: string, fields: Field[]): OptionSetInput {
  return { name, status: false, fields, targeting: emptyTargeting(), actions: [] };
}

/**
 * The presets are returned from a function (not a module constant) so the
 * `__()` calls resolve after the i18n locale is in place.
 */
export function getPresets(): PresetDefinition[] {
  return [
    {
      id: 'gift-wrapping',
      title: __('Gift wrapping & message', 'flexa-extra'),
      description: __(
        'A paid gift-wrap add-on plus an optional printed message.',
        'flexa-extra',
      ),
      build: () =>
        optionSet(__('Gift wrapping', 'flexa-extra'), [
          field('checkbox', {
            label: __('Gift wrapping', 'flexa-extra'),
            options: [
              choice(__('Wrap this as a gift', 'flexa-extra'), 'gift-wrap', { price: fixed(4.99) }),
            ],
          }),
          field('textarea', {
            label: __('Gift message', 'flexa-extra'),
            placeholder: __('Write a short message (optional)', 'flexa-extra'),
          }),
        ]),
    },
    {
      id: 'engraving',
      title: __('Engraving / personalization', 'flexa-extra'),
      description: __(
        'A flat-fee engraving text field with a font choice.',
        'flexa-extra',
      ),
      build: () =>
        optionSet(__('Engraving', 'flexa-extra'), [
          field('text', {
            label: __('Engraving text', 'flexa-extra'),
            placeholder: __('Up to 20 characters', 'flexa-extra'),
            price: fixed(9.99),
          }),
          field('dropdown', {
            label: __('Font style', 'flexa-extra'),
            options: [
              choice(__('Classic', 'flexa-extra'), 'classic', { default: true }),
              choice(__('Script', 'flexa-extra'), 'script'),
              choice(__('Modern', 'flexa-extra'), 'modern'),
            ],
          }),
        ]),
    },
    {
      id: 'size-colour',
      title: __('Size & colour', 'flexa-extra'),
      description: __(
        'A size button group and a colour swatch picker.',
        'flexa-extra',
      ),
      build: () =>
        optionSet(__('Size & colour', 'flexa-extra'), [
          field('button', {
            label: __('Size', 'flexa-extra'),
            options: [
              choice(__('S', 'flexa-extra'), 's'),
              choice(__('M', 'flexa-extra'), 'm', { default: true }),
              choice(__('L', 'flexa-extra'), 'l'),
              choice(__('XL', 'flexa-extra'), 'xl'),
            ],
          }),
          field('swatch', {
            label: __('Colour', 'flexa-extra'),
            options: [
              choice(__('Black', 'flexa-extra'), 'black', { color: '#000000', default: true }),
              choice(__('White', 'flexa-extra'), 'white', { color: '#ffffff' }),
              choice(__('Red', 'flexa-extra'), 'red', { color: '#e11d48' }),
              choice(__('Blue', 'flexa-extra'), 'blue', { color: '#2563eb' }),
            ],
          }),
        ]),
    },
    {
      id: 'installation',
      title: __('Installation service', 'flexa-extra'),
      description: __(
        'A single-choice service tier with per-tier fees.',
        'flexa-extra',
      ),
      build: () =>
        optionSet(__('Installation service', 'flexa-extra'), [
          field('radio', {
            label: __('Installation', 'flexa-extra'),
            options: [
              choice(__('No installation', 'flexa-extra'), 'none', { default: true }),
              choice(__('Standard installation', 'flexa-extra'), 'standard', { price: fixed(49) }),
              choice(__('Premium installation', 'flexa-extra'), 'premium', { price: fixed(99) }),
            ],
          }),
        ]),
    },
    {
      id: 'warranty',
      title: __('Warranty / protection plan', 'flexa-extra'),
      description: __(
        'An add-on plan priced as a percentage of the product.',
        'flexa-extra',
      ),
      build: () =>
        optionSet(__('Protection plan', 'flexa-extra'), [
          field('radio', {
            label: __('Protection plan', 'flexa-extra'),
            options: [
              choice(__('No thanks', 'flexa-extra'), 'none', { default: true }),
              choice(__('1-year protection', 'flexa-extra'), '1-year', { price: percent(10) }),
              choice(__('2-year protection', 'flexa-extra'), '2-year', { price: percent(18) }),
            ],
          }),
        ]),
    },
    {
      id: 'add-ons',
      title: __('Product add-ons', 'flexa-extra'),
      description: __(
        'A multi-select list of paid extras the shopper can bundle in.',
        'flexa-extra',
      ),
      build: () =>
        optionSet(__('Product add-ons', 'flexa-extra'), [
          field('checkbox', {
            label: __('Add extras', 'flexa-extra'),
            options: [
              choice(__('Extra battery pack', 'flexa-extra'), 'battery', { price: fixed(12) }),
              choice(__('Carry case', 'flexa-extra'), 'case', { price: fixed(18) }),
              choice(__('Extended cable', 'flexa-extra'), 'cable', { price: fixed(6) }),
            ],
          }),
        ]),
    },
  ];
}
