import { useEffect, useMemo, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { useFormContext, useWatch } from 'react-hook-form';

import {
  CHOICE_TYPES,
  Field,
  FieldChoice,
  OptionSet,
  OptionSetAction,
} from '@/lib/schema/option-set';
import {
  Currency,
  PreviewValue,
  PreviewValues,
  actionApplies,
  choiceValue,
  formatMoney,
  getCurrency,
  hasValue,
  isVisible,
  optionLabel,
  priceFor,
  priceHint,
  signedMoney,
} from '@/lib/preview/engine';

/**
 * Live storefront preview for the builder. Reuses the storefront CSS (enqueued
 * on the builder page as `flexa-extra-frontend`) and the shared preview engine,
 * so what the merchant sees here matches the real product page: conditional
 * logic show/hide, per-option price hints, and the itemised total block react in
 * real time as they edit fields and interact with the mock controls.
 */

const isChoice = (type: string) => CHOICE_TYPES.includes(type as never);
const isMulti = (field: Field) => field.type === 'checkbox' || field.multiple === true;

/** Default selection for a field: checked options for choices, default for inputs. */
function defaultValue(field: Field): PreviewValue {
  if (isChoice(field.type) && field.options?.length) {
    const checked = field.options.filter((o) => o.default).map((o) => o.id);
    return isMulti(field) ? checked : (checked[0] ?? '');
  }
  return field.default ?? '';
}

/** Container classes + CSS custom properties, mirroring ProductRenderer. */
function containerStyle(): { className: string; style: React.CSSProperties } {
  const style = window.flexaExtra?.settings?.style;
  const size = style?.swatchSize ?? 'md';
  const shape = style?.swatchShape ?? 'circle';
  const sizes: Record<string, string> = { sm: '28px', md: '36px', lg: '48px' };
  const shapes: Record<string, string> = { circle: '50%', rounded: '8px', square: '0' };

  const vars: Record<string, string> = {
    '--fxe-swatch-size': sizes[size] ?? sizes.md,
    '--fxe-swatch-radius': shapes[shape] ?? shapes.circle,
  };
  const colorMap: [keyof NonNullable<typeof style>, string][] = [
    ['buttonBg', '--fxe-btn-bg'],
    ['buttonText', '--fxe-btn-text'],
    ['buttonActiveBg', '--fxe-btn-active-bg'],
    ['buttonActiveText', '--fxe-btn-active-text'],
  ];
  for (const [key, cssVar] of colorMap) {
    const val = style?.[key] as string | undefined;
    if (val) {
      vars[cssVar] = val;
    }
  }

  const classes = [
    'flexa-extra-fields',
    'is-ready',
    `flexa-extra-fields--swatch-${size}`,
    `flexa-extra-fields--shape-${shape}`,
  ];
  if (!style?.showTooltips) {
    classes.push('flexa-extra-fields--no-tooltips');
  }
  return { className: classes.join(' '), style: vars as React.CSSProperties };
}

export function PreviewPanel() {
  const { control } = useFormContext<OptionSet>();
  const fields = (useWatch({ control, name: 'fields' }) ?? []) as Field[];
  const actions = (useWatch({ control, name: 'actions' }) ?? []) as OptionSetAction[];

  const [productPrice, setProductPrice] = useState(100);
  const [overrides, setOverrides] = useState<Record<string, PreviewValue>>({});
  const cur = useMemo(getCurrency, []);

  // Drop overrides for fields that no longer exist so stale state can't leak.
  const idSignature = fields.map((f) => f.id).join(',');
  useEffect(() => {
    setOverrides((prev) => {
      const live = new Set(fields.map((f) => f.id));
      const next: Record<string, PreviewValue> = {};
      for (const key of Object.keys(prev)) {
        if (live.has(key)) {
          next[key] = prev[key];
        }
      }
      return Object.keys(next).length === Object.keys(prev).length ? prev : next;
    });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [idSignature]);

  const effective = (field: Field): PreviewValue =>
    field.id in overrides ? overrides[field.id] : defaultValue(field);

  const setValue = (id: string, value: PreviewValue) =>
    setOverrides((prev) => ({ ...prev, [id]: value }));

  // Logic values keyed by field id (choice → option value(s); input → raw).
  const values: PreviewValues = {};
  for (const field of fields) {
    const val = effective(field);
    if (isChoice(field.type) && field.options?.length) {
      const byId = (id: string) => {
        const opt = field.options?.find((o) => o.id === id);
        return opt ? choiceValue(opt) : '';
      };
      values[field.id] = Array.isArray(val) ? val.map(byId) : byId(val);
    } else {
      values[field.id] = val;
    }
  }

  // Formula-price context: the preview evaluates at quantity 1, with each
  // field's numeric value for {field_id} references (mirrors the storefront).
  const numericValues: Record<string, number> = {};
  for (const field of fields) {
    const v = values[field.id];
    const n = typeof v === 'string' ? parseFloat(v) : NaN;
    numericValues[field.id] = Number.isFinite(n) ? n : 0;
  }
  const ctx = { base: productPrice, qty: 1, fields: numericValues };

  // Itemised breakdown lines (mirrors flexa-extra.js recalculate()).
  const lines: { label: string; amount: number }[] = [];
  for (const field of fields) {
    if (!isVisible(field.logic, values)) {
      continue;
    }
    if (isChoice(field.type) && field.options?.length) {
      const val = effective(field);
      const selectedIds = Array.isArray(val) ? val : val ? [val] : [];
      for (const id of selectedIds) {
        const opt = field.options.find((o) => o.id === id);
        if (!opt) continue;
        const amount = priceFor(opt.price, productPrice, ctx);
        if (amount) {
          lines.push({ label: optionLabel(opt), amount });
        }
      }
    } else if (field.price && hasValue(values[field.id])) {
      const amount = priceFor(field.price, productPrice, ctx);
      if (amount) {
        lines.push({ label: field.label || field.id, amount });
      }
    }
  }
  for (const action of actions) {
    if (!actionApplies(action, values)) continue;
    const magnitude = Math.abs(priceFor(action.price, productPrice, ctx));
    if (!magnitude) continue;
    const isDiscount = action.kind === 'discount';
    lines.push({
      label: action.label || (isDiscount ? __('Discount', 'flexa-extra') : __('Fee', 'flexa-extra')),
      amount: isDiscount ? -magnitude : magnitude,
    });
  }
  const subtotal = lines.reduce((sum, l) => sum + l.amount, 0);

  const settings = window.flexaExtra?.settings;
  const general = settings?.general;
  const display = settings?.display;
  const showSubtotal = general?.showExtraSubtotal ?? true;
  const showTotal = general?.showTotalPrice ?? true;
  const showBreakdown = general?.showPriceBreakdown ?? false;
  const hideZero = settings?.advanced?.hideZeroSubtotal ?? false;
  const totalsHidden = hideZero && subtotal === 0;

  const box = containerStyle();

  return (
    <div className="bg-card border-border sticky top-4 rounded-lg border p-5">
      <div className="mb-4 flex items-center justify-between gap-3">
        <div>
          <p className="text-sm font-semibold">{__('Live preview', 'flexa-extra')}</p>
          <p className="text-muted-foreground text-xs">
            {__('How this looks on the product page', 'flexa-extra')}
          </p>
        </div>
        <label className="text-muted-foreground flex items-center gap-1.5 text-xs">
          {__('Product price', 'flexa-extra')}
          <input
            type="number"
            min={0}
            step="any"
            value={productPrice}
            onChange={(e) => setProductPrice(Number(e.target.value) || 0)}
            className="border-input h-7 w-20 rounded border bg-transparent px-2 text-xs"
          />
        </label>
      </div>

      {fields.length === 0 ? (
        <p className="text-muted-foreground rounded-md border border-dashed py-10 text-center text-sm">
          {__('Add fields to see the preview.', 'flexa-extra')}
        </p>
      ) : (
        <div className={box.className} style={box.style}>
          {fields.map((field) => {
            const visible = isVisible(field.logic, values);
            return (
              <div
                key={field.id}
                className={`flexa-extra-field flexa-extra-field--${field.type}${
                  field.id ? ` flexa-extra-field--id-${field.id}` : ''
                }${field.cssClass ? ` ${field.cssClass}` : ''}`}
                data-field-type={field.type}
                hidden={!visible}
              >
                <PreviewField
                  field={field}
                  value={effective(field)}
                  onChange={(v) => setValue(field.id, v)}
                  cur={cur}
                />
              </div>
            );
          })}

          {!totalsHidden && (showSubtotal || showTotal || showBreakdown) && (
            <div className="flexa-extra-totals">
              {showBreakdown && lines.length > 0 && (
                <div className="flexa-extra-breakdown" data-role="breakdown">
                  {lines.map((line, i) => (
                    <div className="flexa-extra-breakdown__row" key={i}>
                      <span className="flexa-extra-breakdown__label">{line.label}</span>
                      <span className="flexa-extra-breakdown__amount">
                        {signedMoney(line.amount, cur)}
                      </span>
                    </div>
                  ))}
                </div>
              )}
              {showSubtotal && (
                <div className="flexa-extra-totals__row">
                  <span>{display?.subtotalLabel || __('Extra subtotal', 'flexa-extra')}</span>
                  <span data-role="subtotal">{formatMoney(subtotal, cur)}</span>
                </div>
              )}
              {showTotal && (
                <div className="flexa-extra-totals__row flexa-extra-totals__row--total">
                  <span>{display?.totalPriceLabel || __('Total price', 'flexa-extra')}</span>
                  <span data-role="total">{formatMoney(productPrice + subtotal, cur)}</span>
                </div>
              )}
            </div>
          )}
        </div>
      )}
    </div>
  );
}

/** Label text + required marker, placed inside the storefront label element. */
function LabelText({ field }: { field: Field }) {
  return (
    <>
      {field.label}
      {field.required && (
        <abbr className="flexa-extra-required" title={__('Required', 'flexa-extra')}>
          {' '}
          *
        </abbr>
      )}
    </>
  );
}

function PreviewField({
  field,
  value,
  onChange,
  cur,
}: {
  field: Field;
  value: PreviewValue;
  onChange: (v: PreviewValue) => void;
  cur: Currency;
}) {
  if (field.type === 'heading') {
    return (
      <div className="flexa-extra-heading">
        <div className="flexa-extra-heading__title">{field.label}</div>
        {field.placeholder && <div className="flexa-extra-heading__desc">{field.placeholder}</div>}
      </div>
    );
  }

  if (isChoice(field.type) && field.options?.length) {
    return <ChoiceField field={field} value={value} onChange={onChange} cur={cur} />;
  }

  // Free-form input.
  const str = Array.isArray(value) ? '' : value;
  const hint = priceHint(field.price, cur);
  return (
    <label className="flexa-extra-field__label">
      <LabelText field={field} />
      {hint && <span className="flexa-extra-price">{hint}</span>}
      {field.type === 'textarea' ? (
        <textarea
          className="flexa-extra-control"
          placeholder={field.placeholder}
          value={str}
          onChange={(e) => onChange(e.target.value)}
        />
      ) : field.type === 'color_picker' ? (
        <span className="flexa-extra-colorpicker">
          <input
            type="color"
            value={str || '#000000'}
            onChange={(e) => onChange(e.target.value)}
          />
          <span
            className="flexa-extra-colorpicker__swatch"
            aria-hidden="true"
            style={{ background: str || '#000000' }}
          />
          <span className="flexa-extra-colorpicker__value" aria-hidden="true">
            {(str || '#000000').toUpperCase()}
          </span>
        </span>
      ) : (
        <input
          type={inputType(field.type)}
          className="flexa-extra-control"
          placeholder={field.placeholder}
          value={str}
          min={field.type === 'date_picker' ? (field.minDate || undefined) : (field.min ?? undefined)}
          max={field.type === 'date_picker' ? (field.maxDate || undefined) : (field.max ?? undefined)}
          step={field.step ?? undefined}
          onChange={(e) => onChange(e.target.value)}
        />
      )}
    </label>
  );
}

function inputType(type: string): string {
  switch (type) {
    case 'number':
      return 'number';
    case 'date_picker':
      return 'date';
    case 'color_picker':
      return 'color';
    default:
      return 'text';
  }
}

function ChoiceField({
  field,
  value,
  onChange,
  cur,
}: {
  field: Field;
  value: PreviewValue;
  onChange: (v: PreviewValue) => void;
  cur: Currency;
}) {
  const options = field.options ?? [];
  const multi = isMulti(field);
  const selectedIds = Array.isArray(value) ? value : value ? [value] : [];
  const max = field.maxSelect ?? null;

  if (field.type === 'dropdown') {
    return (
      <label className="flexa-extra-field__label">
        <LabelText field={field} />
        <select
          className="flexa-extra-control"
          value={multi ? selectedIds : (selectedIds[0] ?? '')}
          multiple={multi}
          onChange={(e) => {
            if (multi) {
              onChange(Array.from(e.target.selectedOptions).map((o) => o.value));
            } else {
              onChange(e.target.value);
            }
          }}
        >
          {!multi && <option value="">{field.placeholder || '—'}</option>}
          {options.map((opt) => (
            <option key={opt.id} value={opt.id}>
              {choiceOptionText(opt, cur)}
            </option>
          ))}
        </select>
      </label>
    );
  }

  const toggle = (id: string, checked: boolean) => {
    if (!multi) {
      onChange(id);
      return;
    }
    const next = checked ? [...selectedIds, id] : selectedIds.filter((x) => x !== id);
    onChange(next);
  };

  const rowClass =
    field.type === 'swatch'
      ? 'flexa-extra-swatch'
      : field.type === 'button'
        ? 'flexa-extra-button'
        : 'flexa-extra-choice';

  return (
    <fieldset className={`flexa-extra-choices flexa-extra-choices--${field.type}`}>
      {field.label && <legend className="flexa-extra-field__label">{field.label}</legend>}
      {options.map((opt) => {
        const checked = selectedIds.includes(opt.id);
        const capped = multi && !!max && !checked && selectedIds.length >= max;
        const hint = priceHint(opt.price, cur);
        return (
          <label className={rowClass} key={opt.id} title={opt.tooltip || opt.label}>
            <input
              type={multi ? 'checkbox' : 'radio'}
              name={field.id}
              value={opt.id}
              checked={checked}
              disabled={capped}
              onChange={(e) => toggle(opt.id, e.target.checked)}
            />
            {field.type === 'swatch' && (
              <span
                className="flexa-extra-swatch__chip"
                style={
                  opt.image
                    ? { backgroundImage: `url("${opt.image}")` }
                    : opt.color
                      ? { backgroundColor: opt.color }
                      : undefined
                }
              />
            )}
            {rowClass === 'flexa-extra-choice' && (
              <span className="flexa-extra-choice__control" aria-hidden="true" />
            )}
            <span className={`${rowClass}__label`}>{optionLabel(opt)}</span>
            {hint && <span className="flexa-extra-price">{hint}</span>}
          </label>
        );
      })}
    </fieldset>
  );
}

function choiceOptionText(opt: FieldChoice, cur: Currency): string {
  const hint = priceHint(opt.price, cur);
  return hint ? `${optionLabel(opt)} (${hint})` : optionLabel(opt);
}
