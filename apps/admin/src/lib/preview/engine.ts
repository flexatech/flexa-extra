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

export interface FormulaContext {
  base: number;
  qty: number;
  fields: Record<string, number>;
}

interface FormulaToken {
  type: 'number' | 'ident' | 'field' | 'op';
  value: string;
}

/** Round half away from zero, matching PHP round() and the storefront. */
function phpRound(value: number, precision: number): number {
  const factor = Math.pow(10, precision || 0);
  const x = value * factor;
  const r = x >= 0 ? Math.floor(x + 0.5) : Math.ceil(x - 0.5);
  return r / factor;
}

function tokenizeFormula(text: string): FormulaToken[] {
  const tokens: FormulaToken[] = [];
  const len = text.length;
  let i = 0;
  while (i < len) {
    const ch = text.charAt(i);
    if (ch === ' ' || ch === '\t' || ch === '\n' || ch === '\r') {
      i++;
      continue;
    }
    if ((ch >= '0' && ch <= '9') || ch === '.') {
      let num = '';
      while (i < len && ((text.charAt(i) >= '0' && text.charAt(i) <= '9') || text.charAt(i) === '.')) {
        num += text.charAt(i);
        i++;
      }
      if (!/^(\d+\.?\d*|\.\d+)$/.test(num)) {
        throw new Error('number');
      }
      tokens.push({ type: 'number', value: num });
      continue;
    }
    if ((ch >= 'a' && ch <= 'z') || (ch >= 'A' && ch <= 'Z') || ch === '_') {
      let ident = '';
      while (i < len) {
        const c = text.charAt(i);
        if ((c >= 'a' && c <= 'z') || (c >= 'A' && c <= 'Z') || (c >= '0' && c <= '9') || c === '_') {
          ident += c;
          i++;
        } else {
          break;
        }
      }
      tokens.push({ type: 'ident', value: ident.toLowerCase() });
      continue;
    }
    if (ch === '{') {
      let ref = '';
      i++;
      while (i < len && text.charAt(i) !== '}') {
        ref += text.charAt(i);
        i++;
      }
      if (i >= len) {
        throw new Error('field ref');
      }
      i++;
      tokens.push({ type: 'field', value: ref.trim() });
      continue;
    }
    if ('+-*/(),'.indexOf(ch) !== -1) {
      tokens.push({ type: 'op', value: ch });
      i++;
      continue;
    }
    throw new Error('char');
  }
  return tokens;
}

/** Parse + evaluate an already-tokenized formula. Throws on any malformed input. */
function parseTokens(tokens: FormulaToken[], base: number, qty: number, fields: Record<string, number>): number {
  let pos = 0;

  const peek = (): FormulaToken | null => (pos < tokens.length ? tokens[pos] : null);
  const isOp = (v: string): boolean => {
    const t = peek();
    return !!t && t.type === 'op' && t.value === v;
  };

  const parseExpression = (): number => {
    let value = parseTerm();
    while (isOp('+') || isOp('-')) {
      const op = tokens[pos].value;
      pos++;
      const rhs = parseTerm();
      value = op === '+' ? value + rhs : value - rhs;
    }
    return value;
  };
  const parseTerm = (): number => {
    let value = parseFactor();
    while (isOp('*') || isOp('/')) {
      const op = tokens[pos].value;
      pos++;
      const rhs = parseFactor();
      if (op === '*') {
        value *= rhs;
      } else {
        value = rhs === 0 ? 0 : value / rhs;
      }
    }
    return value;
  };
  const parseFactor = (): number => {
    if (isOp('-')) {
      pos++;
      return -parseFactor();
    }
    if (isOp('+')) {
      pos++;
      return parseFactor();
    }
    return parsePrimary();
  };
  const parsePrimary = (): number => {
    const tok = peek();
    if (!tok) {
      throw new Error('end');
    }
    if (tok.type === 'number') {
      pos++;
      return parseFloat(tok.value);
    }
    if (tok.type === 'field') {
      pos++;
      const f = fields[tok.value];
      return typeof f === 'number' && Number.isFinite(f) ? f : 0;
    }
    if (tok.type === 'op' && tok.value === '(') {
      pos++;
      const v = parseExpression();
      if (!isOp(')')) {
        throw new Error('paren');
      }
      pos++;
      return v;
    }
    if (tok.type === 'ident') {
      const name = tok.value;
      pos++;
      if (isOp('(')) {
        return callFunction(name, parseArguments());
      }
      if (name === 'base') {
        return base;
      }
      if (name === 'qty') {
        return qty;
      }
      throw new Error('ident');
    }
    throw new Error('token');
  };
  const parseArguments = (): number[] => {
    pos++; // '('
    const args: number[] = [];
    if (isOp(')')) {
      pos++;
      return args;
    }
    args.push(parseExpression());
    while (isOp(',')) {
      pos++;
      args.push(parseExpression());
    }
    if (!isOp(')')) {
      throw new Error('call');
    }
    pos++;
    return args;
  };
  const callFunction = (name: string, args: number[]): number => {
    if (name === 'round') {
      if (!args.length) {
        throw new Error('round');
      }
      return phpRound(args[0], args.length > 1 ? Math.round(args[1]) : 0);
    }
    if (name === 'min') {
      if (!args.length) {
        throw new Error('min');
      }
      return Math.min(...args);
    }
    if (name === 'max') {
      if (!args.length) {
        throw new Error('max');
      }
      return Math.max(...args);
    }
    throw new Error('func');
  };

  const result = parseExpression();
  if (pos !== tokens.length) {
    throw new Error('trailing');
  }
  return result;
}

/**
 * Safe recursive-descent formula evaluator. Mirrors PHP `Pricing\FormulaEvaluator`
 * and the storefront `evalFormula`; returns 0 on any parse error. Never eval().
 */
export function evalFormula(formula: string | undefined, ctx: FormulaContext): number {
  const text = (formula == null ? '' : String(formula)).trim();
  if (!text) {
    return 0;
  }
  const base = Number.isFinite(ctx.base) ? ctx.base : 0;
  const qty = Number.isFinite(ctx.qty) ? ctx.qty : 1;
  const fields = ctx.fields || {};
  try {
    const result = parseTokens(tokenizeFormula(text), base, qty, fields);
    return Number.isFinite(result) ? result : 0;
  } catch {
    return 0;
  }
}

/** Builder helper: is a formula syntactically valid? (Empty counts as invalid.) */
export function isValidFormula(formula: string | undefined): boolean {
  const text = (formula == null ? '' : String(formula)).trim();
  if (!text) {
    return false;
  }
  try {
    parseTokens(tokenizeFormula(text), 0, 1, {});
    return true;
  } catch {
    return false;
  }
}

/** Resolve a price rule to a currency amount against the sample product price. */
export function priceFor(
  price: PriceRule | undefined,
  productPrice: number,
  ctx?: FormulaContext
): number {
  if (!price || price.type === 'none') {
    return 0;
  }
  if (price.type === 'formula') {
    return evalFormula(price.formula, ctx ?? { base: productPrice, qty: 1, fields: {} });
  }
  if (!price.amount) {
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
