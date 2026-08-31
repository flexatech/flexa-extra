import {
  CHOICE_TYPES,
  Field,
  FieldChoice,
  FieldType,
  INPUT_TYPES,
  PriceRule,
  Targeting,
} from '@/lib/schema/option-set';

/**
 * Factory helpers that produce schema-valid defaults for the builder. Mirrors
 * the per-type branches in the PHP sanitizer so a freshly created field round-
 * trips through the server unchanged.
 */

export interface FieldCatalogEntry {
  type: FieldType;
  label: string;
  group: 'input' | 'choice' | 'display';
  pro: boolean;
}

/** Catalog localized by PHP (`window.flexaExtra.field_catalog`). */
export function getFieldCatalog(): FieldCatalogEntry[] {
  return (window.flexaExtra?.field_catalog ?? []) as FieldCatalogEntry[];
}

export const isChoiceType = (type: FieldType): boolean => CHOICE_TYPES.includes(type);
export const isInputType = (type: FieldType): boolean => INPUT_TYPES.includes(type);

export const noPrice = (): PriceRule => ({ type: 'none', amount: 0 });

let seq = 0;
/** Client-side id for new fields/choices; the server keeps whatever we send. */
export function uid(prefix: string): string {
  seq += 1;
  return `${prefix}_${seq}_${Math.floor(performance.now())}`;
}

export function createChoice(label = ''): FieldChoice {
  return {
    id: uid('opt'),
    label,
    value: '',
    default: false,
    tooltip: '',
    color: '',
    image: '',
    price: noPrice(),
  };
}

export function createField(type: FieldType): Field {
  const base: Field = {
    id: uid('fld'),
    type,
    label: '',
    name: '',
    required: false,
    placeholder: '',
    tooltip: '',
    default: '',
    logic: { enabled: false, action: 'show', match: 'any', rules: [] },
  };

  if (type === 'text') {
    return { ...base, textFormat: 'text', regex: '', price: noPrice() };
  }
  if (type === 'textarea') {
    return { ...base, price: noPrice() };
  }
  if (type === 'number') {
    return { ...base, min: null, max: null, step: null, price: noPrice() };
  }
  if (isChoiceType(type)) {
    return {
      ...base,
      multiple: type === 'checkbox',
      options: [createChoice()],
    };
  }
  return base;
}

export function emptyTargeting(): Targeting {
  return { mode: 'all', productIds: [], match: 'any', conditions: [] };
}
