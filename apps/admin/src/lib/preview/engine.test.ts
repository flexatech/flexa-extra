import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import type {
  FieldChoice,
  FieldLogic,
  OptionSetAction,
  PriceRule,
} from '@/lib/schema/option-set';
import {
  actionApplies,
  choiceValue,
  evalFormula,
  formatMoney,
  getCurrency,
  hasValue,
  isValidFormula,
  isVisible,
  optionLabel,
  priceFor,
  priceHint,
  signedMoney,
  type Currency,
  type FormulaContext,
} from './engine';

/**
 * engine.ts is the builder preview's copy of the storefront pricing + logic
 * engine (assets/frontend/flexa-extra.js) and the server value rules
 * (includes/Frontend/FieldRenderer.php). These tests pin the shared behaviour so
 * a drift in any of the three shows up as a red test, not a silently wrong
 * preview. Cases mirror the storefront branch-for-branch.
 */

const USD: Currency = {
  symbol: '$',
  position: 'left',
  thousand_sep: ',',
  decimal_sep: '.',
  num_decimals: 2,
};

function logic(overrides: Partial<FieldLogic> = {}): FieldLogic {
  return {
    enabled: true,
    action: 'show',
    match: 'any',
    rules: [],
    ...overrides,
  };
}

function action(overrides: Partial<OptionSetAction> = {}): OptionSetAction {
  return {
    id: 'act_1',
    label: '',
    kind: 'fee',
    price: { type: 'fixed', amount: 0 },
    match: 'any',
    rules: [],
    ...overrides,
  };
}

function choice(overrides: Partial<FieldChoice> = {}): FieldChoice {
  return {
    id: 'opt_1',
    label: '',
    value: '',
    default: false,
    tooltip: '',
    color: '',
    image: '',
    price: { type: 'none', amount: 0 },
    ...overrides,
  };
}

describe('hasValue', () => {
  it('is false for empty string, null, undefined and empty array', () => {
    expect(hasValue('')).toBe(false);
    expect(hasValue(undefined)).toBe(false);
    expect(hasValue([])).toBe(false);
  });

  it('is true for a non-empty string or a filled array', () => {
    expect(hasValue('x')).toBe(true);
    expect(hasValue(['a'])).toBe(true);
    expect(hasValue('0')).toBe(true);
  });
});

describe('isVisible', () => {
  it('shows the field when there is no active logic', () => {
    expect(isVisible(undefined, {})).toBe(true);
    expect(isVisible(logic({ enabled: false, rules: [{ field: 'a', operator: 'is', value: '1' }] }), {})).toBe(true);
    expect(isVisible(logic({ rules: [] }), {})).toBe(true);
  });

  it('handles each operator', () => {
    const values = { color: 'red', size: '' };
    expect(isVisible(logic({ rules: [{ field: 'color', operator: 'is', value: 'red' }] }), values)).toBe(true);
    expect(isVisible(logic({ rules: [{ field: 'color', operator: 'is', value: 'blue' }] }), values)).toBe(false);
    expect(isVisible(logic({ rules: [{ field: 'color', operator: 'is_not', value: 'blue' }] }), values)).toBe(true);
    expect(isVisible(logic({ rules: [{ field: 'size', operator: 'empty', value: '' }] }), values)).toBe(true);
    expect(isVisible(logic({ rules: [{ field: 'color', operator: 'not_empty', value: '' }] }), values)).toBe(true);
  });

  it('matches a target inside an array value (multi-select)', () => {
    const values = { toppings: ['cheese', 'ham'] };
    expect(isVisible(logic({ rules: [{ field: 'toppings', operator: 'is', value: 'ham' }] }), values)).toBe(true);
    expect(isVisible(logic({ rules: [{ field: 'toppings', operator: 'is', value: 'olives' }] }), values)).toBe(false);
  });

  it('combines rules with any vs all', () => {
    const values = { a: '1', b: '2' };
    const rules: FieldLogic['rules'] = [
      { field: 'a', operator: 'is', value: '1' },
      { field: 'b', operator: 'is', value: 'x' },
    ];
    expect(isVisible(logic({ match: 'any', rules }), values)).toBe(true);
    expect(isVisible(logic({ match: 'all', rules }), values)).toBe(false);
  });

  it('inverts the result for a hide action', () => {
    const values = { a: '1' };
    const rules: FieldLogic['rules'] = [{ field: 'a', operator: 'is', value: '1' }];
    expect(isVisible(logic({ action: 'hide', rules }), values)).toBe(false);
    expect(isVisible(logic({ action: 'hide', rules: [{ field: 'a', operator: 'is', value: '9' }] }), values)).toBe(true);
  });
});

describe('actionApplies', () => {
  it('always applies when there are no rules', () => {
    expect(actionApplies(action({ rules: [] }), {})).toBe(true);
  });

  it('honours any vs all', () => {
    const values = { a: '1', b: '2' };
    const rules: FieldLogic['rules'] = [
      { field: 'a', operator: 'is', value: '1' },
      { field: 'b', operator: 'is', value: 'x' },
    ];
    expect(actionApplies(action({ match: 'any', rules }), values)).toBe(true);
    expect(actionApplies(action({ match: 'all', rules }), values)).toBe(false);
  });
});

describe('priceFor', () => {
  it('is zero for no rule, none, or a zero amount', () => {
    expect(priceFor(undefined, 100)).toBe(0);
    expect(priceFor({ type: 'none', amount: 5 }, 100)).toBe(0);
    expect(priceFor({ type: 'fixed', amount: 0 }, 100)).toBe(0);
  });

  it('returns the flat amount for a fixed rule', () => {
    expect(priceFor({ type: 'fixed', amount: 12.5 }, 100)).toBe(12.5);
    expect(priceFor({ type: 'fixed', amount: -3 }, 100)).toBe(-3);
  });

  it('computes a percentage of the product price', () => {
    expect(priceFor({ type: 'percent', amount: 10 }, 200)).toBe(20);
    expect(priceFor({ type: 'percent', amount: -25 }, 40)).toBe(-10);
  });
});

describe('choiceValue', () => {
  it('uses the choice value, falling back to its id', () => {
    expect(choiceValue(choice({ value: 'red', id: 'opt_1' }))).toBe('red');
    expect(choiceValue(choice({ value: '', id: 'opt_1' }))).toBe('opt_1');
  });
});

describe('optionLabel', () => {
  it('prefers label, then upper-cased colour, then value, then id', () => {
    expect(optionLabel(choice({ label: 'Large' }))).toBe('Large');
    expect(optionLabel(choice({ label: '', color: '#ff0000' }))).toBe('#FF0000');
    expect(optionLabel(choice({ label: '', color: '', value: 'raw' }))).toBe('raw');
    expect(optionLabel(choice({ label: '', color: '', value: '', id: 'opt_9' }))).toBe('opt_9');
  });
});

describe('formatMoney', () => {
  it('formats with symbol on the left and thousands grouping', () => {
    expect(formatMoney(1234.5, USD)).toBe('$1,234.50');
  });

  it('prefixes a minus for negative amounts', () => {
    expect(formatMoney(-1234.5, USD)).toBe('-$1,234.50');
  });

  it('respects each currency position', () => {
    expect(formatMoney(5, { ...USD, position: 'right', symbol: '€' })).toBe('5.00€');
    expect(formatMoney(5, { ...USD, position: 'left_space' })).toBe('$ 5.00');
    expect(formatMoney(5, { ...USD, position: 'right_space', symbol: 'kr' })).toBe('5.00 kr');
  });

  it('respects decimal count and custom separators', () => {
    expect(formatMoney(1234.5, { ...USD, num_decimals: 0 })).toBe('$1,235');
    expect(formatMoney(1234.56, { ...USD, thousand_sep: '.', decimal_sep: ',' })).toBe('$1.234,56');
  });

  it('falls back to two decimals when num_decimals is not a number', () => {
    expect(formatMoney(5, { ...USD, num_decimals: NaN })).toBe('$5.00');
  });
});

describe('signedMoney', () => {
  it('prefixes + for fees and keeps the minus for discounts', () => {
    expect(signedMoney(5, USD)).toBe('+$5.00');
    expect(signedMoney(-5, USD)).toBe('-$5.00');
  });
});

describe('priceHint', () => {
  it('is empty for no rule, none, or a zero amount', () => {
    expect(priceHint(undefined, USD)).toBe('');
    expect(priceHint({ type: 'none', amount: 5 }, USD)).toBe('');
    expect(priceHint({ type: 'fixed', amount: 0 }, USD)).toBe('');
  });

  it('shows a signed percentage without currency formatting', () => {
    expect(priceHint({ type: 'percent', amount: 10 }, USD)).toBe('+10%');
    expect(priceHint({ type: 'percent', amount: -10 }, USD)).toBe('-10%');
  });

  it('shows a signed money amount for a fixed rule', () => {
    expect(priceHint({ type: 'fixed', amount: 5 }, USD)).toBe('+$5.00');
    expect(priceHint({ type: 'fixed', amount: -5 }, USD)).toBe('-$5.00');
  });
});

describe('getCurrency', () => {
  // Node env has no `window`; getCurrency reads window.flexaExtra, so we give it
  // a bare window per test and tear it down after.
  beforeEach(() => {
    (globalThis as unknown as { window: Record<string, unknown> }).window = {};
  });
  afterEach(() => {
    delete (globalThis as unknown as { window?: unknown }).window;
  });

  it('reads the localized currency settings', () => {
    (window as unknown as { flexaExtra: { currency_settings: Currency } }).flexaExtra = {
      currency_settings: {
        symbol: '€',
        position: 'right',
        thousand_sep: '.',
        decimal_sep: ',',
        num_decimals: 0,
      },
    };
    expect(getCurrency()).toEqual({
      symbol: '€',
      position: 'right',
      thousand_sep: '.',
      decimal_sep: ',',
      num_decimals: 0,
    });
  });

  it('falls back to USD-style defaults when nothing is localized', () => {
    expect(getCurrency()).toEqual(USD);
  });
});

// Guard against a specific mirror mistake: none/zero must short-circuit to 0
// BEFORE the percent branch, so a percent rule with a zero amount stays free.
it('priceFor treats a zero-amount percent rule as free', () => {
  const rule: PriceRule = { type: 'percent', amount: 0 };
  expect(priceFor(rule, 500)).toBe(0);
});

describe('evalFormula', () => {
  const ctx = (over: Partial<FormulaContext> = {}): FormulaContext => ({
    base: 100,
    qty: 1,
    fields: {},
    ...over,
  });

  it('evaluates arithmetic with correct precedence and parentheses', () => {
    expect(evalFormula('2 + 3 * 4', ctx())).toBe(14);
    expect(evalFormula('(2 + 3) * 4', ctx())).toBe(20);
    expect(evalFormula('10 / 4', ctx())).toBe(2.5);
    expect(evalFormula('-5 + 2', ctx())).toBe(-3);
  });

  it('resolves base, qty and {field_id} variables', () => {
    expect(evalFormula('base * 0.1', ctx({ base: 250 }))).toBe(25);
    expect(evalFormula('base * qty', ctx({ base: 20, qty: 3 }))).toBe(60);
    expect(evalFormula('{width} * {height}', ctx({ fields: { width: 4, height: 5 } }))).toBe(20);
    // Missing / non-numeric field refs resolve to 0.
    expect(evalFormula('{missing} + 7', ctx())).toBe(7);
  });

  it('supports round(), min() and max()', () => {
    expect(evalFormula('round(2.345, 2)', ctx())).toBe(2.35);
    expect(evalFormula('round(2.5)', ctx())).toBe(3);
    expect(evalFormula('min(3, 8, 1)', ctx())).toBe(1);
    expect(evalFormula('max(2, base)', ctx({ base: 5 }))).toBe(5);
  });

  it('returns 0 on empty, malformed or unknown input (never throws)', () => {
    expect(evalFormula('', ctx())).toBe(0);
    expect(evalFormula(undefined, ctx())).toBe(0);
    expect(evalFormula('2 +', ctx())).toBe(0);
    expect(evalFormula('2 3', ctx())).toBe(0);
    expect(evalFormula('foo(2)', ctx())).toBe(0);
    expect(evalFormula('nope', ctx())).toBe(0);
    expect(evalFormula('1 / 0', ctx())).toBe(0); // Guarded division by zero.
  });

  it('isValidFormula flags parseable vs broken formulas', () => {
    expect(isValidFormula('base * 0.1 + {x}')).toBe(true);
    expect(isValidFormula('round(base, 2)')).toBe(true);
    expect(isValidFormula('')).toBe(false);
    expect(isValidFormula('2 +')).toBe(false);
    expect(isValidFormula('base *')).toBe(false);
    expect(isValidFormula('log(2)')).toBe(false);
  });
});

it('priceFor evaluates a formula price against its context', () => {
  const rule: PriceRule = { type: 'formula', amount: 0, formula: 'base * 0.2 + 3' };
  expect(priceFor(rule, 50, { base: 50, qty: 1, fields: {} })).toBe(13);
  // Falls back to a qty-1 context built from productPrice when none is passed.
  expect(priceFor(rule, 50)).toBe(13);
  // A broken formula is free, never an error.
  expect(priceFor({ type: 'formula', amount: 0, formula: 'base *' }, 50)).toBe(0);
});
