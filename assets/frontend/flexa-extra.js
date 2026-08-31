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
        (data.sets || []).forEach(function (set) {
            (set.fields || []).forEach(function (f) { fields.push(f); });
        });

        var totalsEl = container.querySelector('.flexa-extra-totals');
        var subtotalEl = container.querySelector('[data-role="subtotal"]');
        var totalEl = container.querySelector('[data-role="total"]');

        function recalculate() {
            // 1. Snapshot raw values for logic evaluation.
            var values = {};
            fields.forEach(function (field) {
                values[field.id] = readValue(fieldWrap(container, field.id));
            });

            // 2. Visibility + price accumulation.
            var subtotal = 0;
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
                        if (opt) {
                            subtotal += priceFor(opt.price, productPrice);
                        }
                    });
                } else if (field.price && hasValue(values[field.id])) {
                    subtotal += priceFor(field.price, productPrice);
                }
            });

            render(subtotal);
        }

        function render(subtotal) {
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
