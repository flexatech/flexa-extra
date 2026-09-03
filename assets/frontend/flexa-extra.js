/**
 * Flexa Extra — storefront pricing + conditional-logic engine.
 *
 * Dependency-free. Reads the JSON island rendered inside each
 * `.flexa-extra-fields` container (server-provided field definitions) and:
 *   - evaluates each field's conditional logic (show/hide),
 *   - sums the extra subtotal from selected option / input prices,
 *   - updates the "Extra subtotal" / "Total price" block live.
 *
 * The server recomputes the price authoritatively on add-to-cart (Pha 4); this
 * is display only.
 */
(function () {
    'use strict';

    var config = window.flexaExtraFront || {};
    var currency = config.currency || {
        symbol: '$',
        position: 'left',
        thousand_sep: ',',
        decimal_sep: '.',
        num_decimals: 2,
    };
    var settings = config.settings || {};
    var i18n = config.i18n || {};

    function formatMoney(amount) {
        var negative = amount < 0;
        var decimals = parseInt(currency.num_decimals, 10);
        if (isNaN(decimals)) {
            decimals = 2;
        }
        var fixed = Math.abs(amount).toFixed(decimals);
        var parts = fixed.split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, currency.thousand_sep);
        var num = parts.join(currency.decimal_sep);
        var sym = currency.symbol;
        var out;
        switch (currency.position) {
            case 'right':
                out = num + sym;
                break;
            case 'left_space':
                out = sym + ' ' + num;
                break;
            case 'right_space':
                out = num + ' ' + sym;
                break;
            case 'left':
            default:
                out = sym + num;
        }
        return (negative ? '-' : '') + out;
    }

    function fieldWrap(container, id) {
        return container.querySelector('.flexa-extra-field[data-field-id="' + id + '"]');
    }

    /** Raw value(s) currently entered in a field, ignoring visibility. */
    function readValue(wrap) {
        if (!wrap) {
            return '';
        }
        var multiSelect = wrap.querySelector('select[multiple]');
        if (multiSelect) {
            return Array.prototype.slice
                .call(multiSelect.selectedOptions)
                .map(function (o) { return o.value; });
        }
        var select = wrap.querySelector('select');
        if (select) {
            return select.value;
        }
        var checks = wrap.querySelectorAll('input[type="checkbox"]');
        if (checks.length) {
            var values = [];
            checks.forEach(function (c) {
                if (c.checked) {
                    values.push(c.value);
                }
            });
            return values;
        }
        var radios = wrap.querySelectorAll('input[type="radio"]');
        if (radios.length) {
            var checked = wrap.querySelector('input[type="radio"]:checked');
            return checked ? checked.value : '';
        }
        var text = wrap.querySelector('input, textarea');
        return text ? text.value : '';
    }

    /** Option ids selected in a choice field (for price lookup). */
    function selectedOptionIds(wrap) {
        var ids = [];
        if (!wrap) {
            return ids;
        }
        wrap.querySelectorAll('input[type="radio"]:checked, input[type="checkbox"]:checked').forEach(function (input) {
            if (input.dataset.priceOption) {
                ids.push(input.dataset.priceOption);
            }
        });
        wrap.querySelectorAll('select').forEach(function (select) {
            Array.prototype.slice.call(select.selectedOptions).forEach(function (opt) {
                if (opt.dataset.priceOption && opt.value !== '') {
                    ids.push(opt.dataset.priceOption);
                }
            });
        });
        return ids;
    }

    function hasValue(value) {
        if (Array.isArray(value)) {
            return value.length > 0;
        }
        return value !== '' && value !== null && value !== undefined;
    }

    /** A positive integer selection bound, or 0 when unset/invalid. */
    function toCount(value) {
        var n = parseInt(value, 10);
        return isNaN(n) || n <= 0 ? 0 : n;
    }

    function valueMatches(value, target) {
        if (Array.isArray(value)) {
            return value.indexOf(target) !== -1;
        }
        return String(value) === String(target);
    }

    function rulePasses(rule, values) {
        var current = values[rule.field];
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

    function isVisible(logic, values) {
        if (!logic || !logic.enabled || !logic.rules || !logic.rules.length) {
            return true;
        }
        var results = logic.rules.map(function (rule) { return rulePasses(rule, values); });
        var combined = logic.match === 'all'
            ? results.every(Boolean)
            : results.some(Boolean);
        return logic.action === 'hide' ? !combined : combined;
    }

    /** Whether a set-level action's rules match (empty rules always apply). */
    function actionApplies(action, values) {
        var rules = action.rules || [];
        if (!rules.length) {
            return true;
        }
        var results = rules.map(function (rule) { return rulePasses(rule, values); });
        return action.match === 'all' ? results.every(Boolean) : results.some(Boolean);
    }

    function priceFor(price, productPrice) {
        if (!price || price.type === 'none' || !price.amount) {
            return 0;
        }
        if (price.type === 'percent') {
            return (productPrice * parseFloat(price.amount)) / 100;
        }
        return parseFloat(price.amount);
    }

    function optionById(field, id) {
        if (!field.options) {
            return null;
        }
        for (var i = 0; i < field.options.length; i++) {
            if (String(field.options[i].id) === String(id)) {
                return field.options[i];
            }
        }
        return null;
    }

    /**
     * Readable label for a breakdown row. Mirrors the server: prefer the option
     * label, fall back to the swatch colour, then the raw value/id.
     */
    function optionLabel(field, opt) {
        if (opt.label) {
            return opt.label;
        }
        if (opt.color) {
            return String(opt.color).toUpperCase();
        }
        return String(opt.value || opt.id || '');
    }

    /** A breakdown amount with an explicit sign (+ for fees, - for discounts). */
    function signedMoney(amount) {
        return (amount < 0 ? '' : '+') + formatMoney(amount);
    }

    function initContainer(container) {
        var island = container.querySelector('.flexa-extra-data');
        if (!island) {
            return;
        }
        var data;
        try {
            data = JSON.parse(island.textContent || '{}');
        } catch (e) {
            return;
        }

        var productPrice = parseFloat(data.productPrice) || 0;
        var fields = [];
        var actions = [];
        (data.sets || []).forEach(function (set) {
            (set.fields || []).forEach(function (f) { fields.push(f); });
            (set.actions || []).forEach(function (a) { actions.push(a); });
        });

        var totalsEl = container.querySelector('.flexa-extra-totals');
        var subtotalEl = container.querySelector('[data-role="subtotal"]');
        var totalEl = container.querySelector('[data-role="total"]');
        var breakdownEl = container.querySelector('[data-role="breakdown"]');

        function recalculate() {
            // 1. Snapshot raw values for logic evaluation.
            var values = {};
            fields.forEach(function (field) {
                values[field.id] = readValue(fieldWrap(container, field.id));
            });

            // 2. Visibility + itemized price lines.
            var lines = [];
            fields.forEach(function (field) {
                var wrap = fieldWrap(container, field.id);
                if (!wrap) {
                    return;
                }
                var visible = isVisible(field.logic, values);
                wrap.hidden = !visible;
                wrap.querySelectorAll('input, select, textarea').forEach(function (el) {
                    el.disabled = !visible;
                });
                if (!visible) {
                    return;
                }

                if (field.options) {
                    selectedOptionIds(wrap).forEach(function (id) {
                        var opt = optionById(field, id);
                        if (!opt) {
                            return;
                        }
                        var amount = priceFor(opt.price, productPrice);
                        if (amount) {
                            lines.push({ label: optionLabel(field, opt), amount: amount });
                        }
                    });
                } else if (field.price && hasValue(values[field.id])) {
                    var fieldAmount = priceFor(field.price, productPrice);
                    if (fieldAmount) {
                        lines.push({ label: field.label || field.id, amount: fieldAmount });
                    }
                }
            });

            // 2b. Enforce a max-selection cap on multi-checkbox groups: once the
            // limit is reached, the remaining unchecked boxes are disabled.
            fields.forEach(function (field) {
                var max = toCount(field.maxSelect);
                if (!max) {
                    return;
                }
                var wrap = fieldWrap(container, field.id);
                if (!wrap || wrap.hidden) {
                    return;
                }
                var boxes = wrap.querySelectorAll('input[type="checkbox"]');
                if (!boxes.length) {
                    return;
                }
                var checked = 0;
                boxes.forEach(function (b) { if (b.checked) { checked += 1; } });
                boxes.forEach(function (b) {
                    if (!b.checked) {
                        b.disabled = checked >= max;
                    }
                });
            });

            // 3. Set-level fee / discount actions.
            actions.forEach(function (action) {
                if (!actionApplies(action, values)) {
                    return;
                }
                var magnitude = Math.abs(priceFor(action.price, productPrice));
                if (!magnitude) {
                    return;
                }
                var isDiscount = action.kind === 'discount';
                var signed = isDiscount ? -magnitude : magnitude;
                var label = action.label || (isDiscount ? (i18n.discount || 'Discount') : (i18n.fee || 'Fee'));
                lines.push({ label: label, amount: signed });
            });

            var subtotal = lines.reduce(function (sum, line) { return sum + line.amount; }, 0);
            render(subtotal, lines);
        }

        function renderBreakdown(lines) {
            if (!breakdownEl) {
                return;
            }
            breakdownEl.textContent = '';
            breakdownEl.hidden = lines.length === 0;
            lines.forEach(function (line) {
                var row = document.createElement('div');
                row.className = 'flexa-extra-breakdown__row';

                var label = document.createElement('span');
                label.className = 'flexa-extra-breakdown__label';
                label.textContent = line.label;

                var amount = document.createElement('span');
                amount.className = 'flexa-extra-breakdown__amount';
                amount.textContent = signedMoney(line.amount);

                row.appendChild(label);
                row.appendChild(amount);
                breakdownEl.appendChild(row);
            });
        }

        function render(subtotal, lines) {
            renderBreakdown(lines);
            if (subtotalEl) {
                subtotalEl.textContent = formatMoney(subtotal);
            }
            if (totalEl) {
                totalEl.textContent = formatMoney(productPrice + subtotal);
            }
            if (totalsEl) {
                var hide = settings.hideZeroSubtotal && subtotal === 0;
                totalsEl.hidden = !!hide;
            }
        }

        container.addEventListener('change', recalculate);
        container.addEventListener('input', recalculate);
        recalculate();

        // Reveal once initial visibility/totals are computed (no flash of fields
        // that conditional logic immediately hides).
        container.classList.add('is-ready');
    }

    function boot() {
        document.querySelectorAll('.flexa-extra-fields').forEach(initContainer);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
