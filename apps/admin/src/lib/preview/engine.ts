import type {
  FieldChoice,
  FieldLogic,
  OptionSetAction,
  PriceRule,
} from '@/lib/schema/option-set';

/**
 * Pure pricing + conditional-logic primitives for the builder's live preview.
 *
 * These MIRROR the storefront engine in `assets/frontend/flexa-extra.js`
 * (isVisible / rulePasses / actionApplies / priceFor / formatMoney) and the
 * server sanitizer's value rules in `includes/Frontend/FieldRenderer.php`
 * (choice_value / price_hint). Keep the three in lockstep: any change to how the
 * storefront resolves visibility, value, or price must land here too, or the
 * preview will lie about what the shopper sees.
 */

export type PreviewValue = string | string[];
export type PreviewValues = Record<string, PreviewValue>;

export interface Currency {
  symbol: string;
  position: string;
  thousand_sep: string;
  decimal_sep: string;
  num_decimals: number;
}

interface LogicRuleLike {
  field: string;
  operator: string;
  value: string;
}

export function hasValue(value: PreviewValue | undefined): boolean {
  if (Array.isArray(value)) {
    return value.length > 0;
  }
  return value !== '' && value !== null && value !== undefined;
}

function valueMatches(value: PreviewValue | undefined, target: string): boolean {
  if (Array.isArray(value)) {
    return value.indexOf(target) !== -1;
  }
  return String(value ?? '') === String(target);
}

function rulePasses(rule: LogicRuleLike, values: PreviewValues): boolean {
  const current = values[rule.field];
  switch (rule.operator) {
    case 'empty':
      return !hasValue(current);
    case 'not_empty':
      return hasValue(current);
    case 'is_not':
      return !valueMatches(current, rule.value);
    case 'is':
    default:
      return valueMatches(current, rule.value);
  }
}

/** Whether a field is shown given the current values (mirrors storefront). */
export function isVisible(logic: FieldLogic | undefined, values: PreviewValues): boolean {
  if (!logic || !logic.enabled || !logic.rules || !logic.rules.length) {
    return true;
  }
  const results = logic.rules.map((rule) => rulePasses(rule, values));
  const combined = logic.match === 'all' ? results.every(Boolean) : results.some(Boolean);
  return logic.action === 'hide' ? !combined : combined;
}

/** Whether a set-level fee/discount action's rules match (empty = always). */
export function actionApplies(action: OptionSetAction, values: PreviewValues): boolean {
  const rules = action.rules ?? [];
  if (!rules.length) {
    return true;
  }
  const results = rules.map((rule) => rulePasses(rule, values));
  return action.match === 'all' ? results.every(Boolean) : results.some(Boolean);
}

/** Resolve a price rule to a currency amount against the sample product price. */
export function priceFor(price: PriceRule | undefined, productPrice: number): number {
  if (!price || price.type === 'none' || !price.amount) {
    return 0;
  }
  if (price.type === 'percent') {
    return (productPrice * price.amount) / 100;
  }
  return price.amount;
}

/** The input `value` a choice carries: its own value, else its id (fallback). */
export function choiceValue(choice: FieldChoice): string {
  return choice.value !== '' ? choice.value : choice.id;
}

/** Readable breakdown label for a choice: label → colour → value/id. */
export function optionLabel(choice: FieldChoice): string {
  if (choice.label) {
    return choice.label;
  }
  if (choice.color) {
    return String(choice.color).toUpperCase();
  }
  return String(choice.value || choice.id || '');
}

/** Currency config localized by PHP, with safe fallbacks. */
export function getCurrency(): Currency {
  const c = window.flexaExtra?.currency_settings;
  return {
    symbol: c?.symbol ?? '$',
    position: c?.position ?? 'left',
    thousand_sep: c?.thousand_sep ?? ',',
    decimal_sep: c?.decimal_sep ?? '.',
    num_decimals: c?.num_decimals ?? 2,
  };
}

export function formatMoney(amount: number, currency: Currency): string {
  const negative = amount < 0;
  let decimals = Number(currency.num_decimals);
  if (Number.isNaN(decimals)) {
    decimals = 2;
  }
  const fixed = Math.abs(amount).toFixed(decimals);
  const parts = fixed.split('.');
  parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, currency.thousand_sep);
  const num = parts.join(currency.decimal_sep);
  const sym = currency.symbol;
  let out: string;
  switch (currency.position) {
    case 'right':
      out = num + sym;
      break;
    case 'left_space':
      out = sym + ' ' + num;
      break;
    case 'right_space':
      out = num + ' ' + sym;
      break;
    case 'left':
    default:
      out = sym + num;
  }
  return (negative ? '-' : '') + out;
}

/** A breakdown amount with an explicit sign (+ for fees, - for discounts). */
export function signedMoney(amount: number, currency: Currency): string {
  return (amount < 0 ? '' : '+') + formatMoney(amount, currency);
}

/** Short price hint next to a choice/input, e.g. "+$5.00" or "-10%". */
export function priceHint(price: PriceRule | undefined, currency: Currency): string {
  if (!price || price.type === 'none' || !price.amount) {
    return '';
  }
  const sign = price.amount < 0 ? '-' : '+';
  if (price.type === 'percent') {
    return sign + Math.abs(price.amount) + '%';
  }
  return sign + formatMoney(Math.abs(price.amount), currency);
}
