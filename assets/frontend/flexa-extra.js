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

    /** The product form's quantity, exposed to formula prices as `qty`. */
    function productForm(container) {
        return container.closest ? (container.closest('form.cart') || container.closest('form')) : null;
    }

    function readQuantity(container) {
        var form = productForm(container);
        var el = form ? form.querySelector('input.qty, input[name="quantity"]') : null;
        var q = el ? parseFloat(el.value) : 1;
        return isFinite(q) && q >= 1 ? q : 1;
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

    // Round half away from zero, matching PHP's default round() so the storefront
    // preview of a formula price lines up with the authoritative server amount.
    function phpRound(value, precision) {
        var factor = Math.pow(10, precision || 0);
        var x = value * factor;
        var r = x >= 0 ? Math.floor(x + 0.5) : Math.ceil(x - 0.5);
        return r / factor;
    }

    function tokenizeFormula(text) {
        var tokens = [];
        var len = text.length;
        var i = 0;
        while (i < len) {
            var ch = text.charAt(i);
            if (ch === ' ' || ch === '\t' || ch === '\n' || ch === '\r') { i++; continue; }
            if ((ch >= '0' && ch <= '9') || ch === '.') {
                var num = '';
                while (i < len && ((text.charAt(i) >= '0' && text.charAt(i) <= '9') || text.charAt(i) === '.')) {
                    num += text.charAt(i); i++;
                }
                if (!/^(\d+\.?\d*|\.\d+)$/.test(num)) { throw new Error('number'); }
                tokens.push({ type: 'number', value: num });
                continue;
            }
            if ((ch >= 'a' && ch <= 'z') || (ch >= 'A' && ch <= 'Z') || ch === '_') {
                var ident = '';
                while (i < len) {
                    var c = text.charAt(i);
                    if ((c >= 'a' && c <= 'z') || (c >= 'A' && c <= 'Z') || (c >= '0' && c <= '9') || c === '_') {
                        ident += c; i++;
                    } else { break; }
                }
                tokens.push({ type: 'ident', value: ident.toLowerCase() });
                continue;
            }
            if (ch === '{') {
                var ref = ''; i++;
                while (i < len && text.charAt(i) !== '}') { ref += text.charAt(i); i++; }
                if (i >= len) { throw new Error('field ref'); }
                i++;
                tokens.push({ type: 'field', value: ref.replace(/^\s+|\s+$/g, '') });
                continue;
            }
            if ('+-*/(),'.indexOf(ch) !== -1) { tokens.push({ type: 'op', value: ch }); i++; continue; }
            throw new Error('char');
        }
        return tokens;
    }

    // Safe recursive-descent evaluator. Mirrors PHP Pricing\FormulaEvaluator and
    // the builder preview engine; returns 0 on any parse error. No eval().
    function evalFormula(formula, ctx) {
        var text = (formula == null ? '' : String(formula)).replace(/^\s+|\s+$/g, '');
        if (!text) { return 0; }
        var base = ctx && isFinite(ctx.base) ? ctx.base : 0;
        var qty = ctx && isFinite(ctx.qty) ? ctx.qty : 1;
        var fields = (ctx && ctx.fields) || {};

        var tokens;
        try { tokens = tokenizeFormula(text); } catch (e) { return 0; }
        var pos = 0;

        function peek() { return pos < tokens.length ? tokens[pos] : null; }
        function isOp(v) { var t = peek(); return !!t && t.type === 'op' && t.value === v; }

        function parseExpression() {
            var value = parseTerm();
            while (isOp('+') || isOp('-')) {
                var op = tokens[pos].value; pos++;
                var rhs = parseTerm();
                value = op === '+' ? value + rhs : value - rhs;
            }
            return value;
        }
        function parseTerm() {
            var value = parseFactor();
            while (isOp('*') || isOp('/')) {
                var op = tokens[pos].value; pos++;
                var rhs = parseFactor();
                if (op === '*') { value *= rhs; } else { value = rhs === 0 ? 0 : value / rhs; }
            }
            return value;
        }
        function parseFactor() {
            if (isOp('-')) { pos++; return -parseFactor(); }
            if (isOp('+')) { pos++; return parseFactor(); }
            return parsePrimary();
        }
        function parsePrimary() {
            var tok = peek();
            if (!tok) { throw new Error('end'); }
            if (tok.type === 'number') { pos++; return parseFloat(tok.value); }
            if (tok.type === 'field') {
                pos++;
                var f = fields[tok.value];
                return typeof f === 'number' && isFinite(f) ? f : 0;
            }
            if (tok.type === 'op' && tok.value === '(') {
                pos++;
                var v = parseExpression();
                if (!isOp(')')) { throw new Error('paren'); }
                pos++;
                return v;
            }
            if (tok.type === 'ident') {
                var name = tok.value; pos++;
                if (isOp('(')) { return callFunction(name, parseArguments()); }
                if (name === 'base') { return base; }
                if (name === 'qty') { return qty; }
                throw new Error('ident');
            }
            throw new Error('token');
        }
        function parseArguments() {
            pos++; // '('
            var args = [];
            if (isOp(')')) { pos++; return args; }
            args.push(parseExpression());
            while (isOp(',')) { pos++; args.push(parseExpression()); }
            if (!isOp(')')) { throw new Error('call'); }
            pos++;
            return args;
        }
        function callFunction(name, args) {
            if (name === 'round') {
                if (!args.length) { throw new Error('round'); }
                return phpRound(args[0], args.length > 1 ? Math.round(args[1]) : 0);
            }
            if (name === 'min') { if (!args.length) { throw new Error('min'); } return Math.min.apply(null, args); }
            if (name === 'max') { if (!args.length) { throw new Error('max'); } return Math.max.apply(null, args); }
            throw new Error('func');
        }

        try {
            var result = parseExpression();
            if (pos !== tokens.length) { return 0; }
            return isFinite(result) ? result : 0;
        } catch (e) {
            return 0;
        }
    }

    function priceFor(price, productPrice, ctx) {
        if (!price || price.type === 'none') {
            return 0;
        }
        if (price.type === 'formula') {
            return evalFormula(price.formula, ctx || { base: productPrice, qty: 1, fields: {} });
        }
        if (!price.amount) {
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

    // ---- Date picker ------------------------------------------------------
    // Progressive enhancement of the server-rendered <input type="date">. The
    // native input stays the canonical control (posts YYYY-MM-DD, works with JS
    // off); here we hide it and drive it from a scoped, localized calendar UI.
    // No dependencies: month/weekday names and week start come from Intl.

    var LOCALE = config.locale || undefined;

    function pad2(n) {
        return (n < 10 ? '0' : '') + n;
    }

    function parseISO(str) {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(str || '')) {
            return null;
        }
        var p = String(str).split('-');
        var d = new Date(Number(p[0]), Number(p[1]) - 1, Number(p[2]));
        return isNaN(d.getTime()) ? null : d;
    }

    function toISO(d) {
        return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate());
    }

    function sameDay(a, b) {
        return !!a && !!b
            && a.getFullYear() === b.getFullYear()
            && a.getMonth() === b.getMonth()
            && a.getDate() === b.getDate();
    }

    /** Locale's first weekday as a JS getDay() index (0=Sun..6=Sat). */
    function firstWeekday() {
        try {
            var loc = new Intl.Locale(LOCALE || navigator.language);
            var info = loc.weekInfo || (typeof loc.getWeekInfo === 'function' ? loc.getWeekInfo() : null);
            if (info && info.firstDay) {
                // Intl reports 1..7 (Mon..Sun); map to JS 0..6 (Sun..Sat).
                return info.firstDay % 7;
            }
        } catch (e) { /* older engine: fall back to Sunday */ }
        return 0;
    }

    function ordinalSuffix(n) {
        var s = ['th', 'st', 'nd', 'rd'];
        var v = n % 100;
        return s[(v - 20) % 10] || s[v] || s[0];
    }

    /**
     * Format a Date using a PHP date() format string, mirroring how the server
     * (wp_date) renders the same value in the cart. Month/weekday names follow
     * the site locale via Intl; a leading backslash escapes the next char.
     */
    function phpDate(date, format) {
        var day = date.getDate();
        var out = '';
        for (var i = 0; i < format.length; i++) {
            var ch = format.charAt(i);
            if (ch === '\\') { out += format.charAt(++i); continue; }
            switch (ch) {
                case 'd': out += pad2(day); break;
                case 'j': out += day; break;
                case 'D': out += new Intl.DateTimeFormat(LOCALE, { weekday: 'short' }).format(date); break;
                case 'l': out += new Intl.DateTimeFormat(LOCALE, { weekday: 'long' }).format(date); break;
                case 'S': out += ordinalSuffix(day); break;
                case 'm': out += pad2(date.getMonth() + 1); break;
                case 'n': out += (date.getMonth() + 1); break;
                case 'M': out += new Intl.DateTimeFormat(LOCALE, { month: 'short' }).format(date); break;
                case 'F': out += new Intl.DateTimeFormat(LOCALE, { month: 'long' }).format(date); break;
                case 'Y': out += date.getFullYear(); break;
                case 'y': out += pad2(date.getFullYear() % 100); break;
                default: out += ch;
            }
        }
        return out;
    }

    function enhanceColorPicker(wrap) {
        if (wrap.getAttribute('data-flexa-color-ready')) {
            return;
        }
        wrap.setAttribute('data-flexa-color-ready', '1');
        var input = wrap.querySelector('input[type="color"]');
        var swatch = wrap.querySelector('.flexa-extra-colorpicker__swatch');
        var value = wrap.querySelector('.flexa-extra-colorpicker__value');
        if (!input) {
            return;
        }
        function sync() {
            var hex = (input.value || '#000000').toUpperCase();
            if (swatch) {
                swatch.style.background = hex;
            }
            if (value) {
                value.textContent = hex;
            }
        }
        input.addEventListener('input', sync);
        input.addEventListener('change', sync);
        sync();
    }

    function enhanceDatePicker(input) {
        if (input.getAttribute('data-flexa-date-ready')) {
            return;
        }
        input.setAttribute('data-flexa-date-ready', '1');

        var minDate = parseISO(input.getAttribute('min'));
        var maxDate = parseISO(input.getAttribute('max'));
        var disabled = {};
        (input.getAttribute('data-disabled-dates') || '').split(',').forEach(function (s) {
            s = s.trim();
            if (s) { disabled[s] = true; }
        });

        var monthYearFmt = new Intl.DateTimeFormat(LOCALE, { month: 'long', year: 'numeric' });
        var displayFmt = new Intl.DateTimeFormat(LOCALE, { year: 'numeric', month: 'long', day: 'numeric' });
        var weekdayFmt = new Intl.DateTimeFormat(LOCALE, { weekday: 'short' });
        var startDow = firstWeekday();
        // Server-resolved display format (field override or site date format).
        var dateFormat = input.getAttribute('data-date-format') || '';

        var wrapper = document.createElement('div');
        wrapper.className = 'flexa-extra-datepicker';

        var trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'flexa-extra-control flexa-extra-datepicker__trigger';
        trigger.setAttribute('aria-haspopup', 'dialog');
        trigger.setAttribute('aria-expanded', 'false');

        var triggerLabel = document.createElement('span');
        triggerLabel.className = 'flexa-extra-datepicker__value';
        trigger.appendChild(triggerLabel);

        var icon = document.createElement('span');
        icon.className = 'flexa-extra-datepicker__icon';
        icon.setAttribute('aria-hidden', 'true');
        icon.innerHTML = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4.5" width="18" height="16" rx="2"></rect><line x1="3" y1="9" x2="21" y2="9"></line><line x1="8" y1="2.5" x2="8" y2="6"></line><line x1="16" y1="2.5" x2="16" y2="6"></line></svg>';
        trigger.appendChild(icon);

        var popup = document.createElement('div');
        popup.className = 'flexa-extra-datepicker__popup';
        popup.setAttribute('role', 'dialog');
        popup.setAttribute('aria-label', i18n.chooseDate || 'Choose date');
        popup.hidden = true;

        input.parentNode.insertBefore(wrapper, input);
        wrapper.appendChild(input);
        input.type = 'hidden';
        wrapper.appendChild(trigger);
        wrapper.appendChild(popup);

        // Point the field's <label for> at the trigger so it stays clickable.
        var fieldEl = wrapper.closest ? wrapper.closest('.flexa-extra-field') : null;
        if (fieldEl && input.id) {
            var lbl = fieldEl.querySelector('label[for="' + input.id + '"]');
            if (lbl) {
                trigger.id = input.id + '-trigger';
                lbl.setAttribute('for', trigger.id);
            }
        }

        var viewDate;

        function selectedDate() {
            return parseISO(input.value);
        }

        function clampToRange(d) {
            if (minDate && d < minDate) { return minDate; }
            if (maxDate && d > maxDate) { return maxDate; }
            return d;
        }

        function isDisabled(d) {
            if (minDate && d < minDate) { return true; }
            if (maxDate && d > maxDate) { return true; }
            return !!disabled[toISO(d)];
        }

        function updateTriggerLabel() {
            var sel = selectedDate();
            if (sel) {
                triggerLabel.textContent = dateFormat ? phpDate(sel, dateFormat) : displayFmt.format(sel);
                trigger.classList.remove('is-placeholder');
            } else {
                triggerLabel.textContent = i18n.chooseDate || 'Choose date';
                trigger.classList.add('is-placeholder');
            }
        }

        function fireChange() {
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }

        function commit(d) {
            input.value = toISO(d);
            fireChange();
            updateTriggerLabel();
            close();
            trigger.focus();
        }

        function navButton(label, glyph, handler) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'flexa-extra-datepicker__nav';
            b.setAttribute('aria-label', label);
            b.textContent = glyph;
            b.addEventListener('click', handler);
            return b;
        }

        function dayButtons() {
            return Array.prototype.slice.call(
                popup.querySelectorAll('.flexa-extra-datepicker__day[data-date]')
            );
        }

        function focusGrid() {
            var buttons = dayButtons();
            var target = null;
            buttons.forEach(function (b) {
                if (!target && b.classList.contains('is-selected') && !b.disabled) { target = b; }
            });
            if (!target) {
                buttons.forEach(function (b) { if (!target && !b.disabled) { target = b; } });
            }
            if (target) { target.focus(); }
        }

        function onGridKeydown(e) {
            var delta = 0;
            switch (e.key) {
                case 'ArrowLeft': delta = -1; break;
                case 'ArrowRight': delta = 1; break;
                case 'ArrowUp': delta = -7; break;
                case 'ArrowDown': delta = 7; break;
                default: return;
            }
            e.preventDefault();
            var curDate = parseISO(document.activeElement.getAttribute('data-date'));
            if (!curDate) { return; }
            var next = new Date(curDate.getFullYear(), curDate.getMonth(), curDate.getDate() + delta);
            if (next.getMonth() !== viewDate.getMonth() || next.getFullYear() !== viewDate.getFullYear()) {
                viewDate = new Date(next.getFullYear(), next.getMonth(), 1);
                render();
            }
            var target = popup.querySelector('.flexa-extra-datepicker__day[data-date="' + toISO(next) + '"]');
            if (target && !target.disabled) { target.focus(); }
        }

        function render() {
            popup.textContent = '';
            var year = viewDate.getFullYear();
            var month = viewDate.getMonth();

            var header = document.createElement('div');
            header.className = 'flexa-extra-datepicker__header';
            header.appendChild(navButton(i18n.previousMonth || 'Previous month', '‹', function () {
                viewDate = new Date(year, month - 1, 1);
                render();
                focusGrid();
            }));
            var title = document.createElement('span');
            title.className = 'flexa-extra-datepicker__title';
            title.setAttribute('aria-live', 'polite');
            title.textContent = monthYearFmt.format(viewDate);
            header.appendChild(title);
            header.appendChild(navButton(i18n.nextMonth || 'Next month', '›', function () {
                viewDate = new Date(year, month + 1, 1);
                render();
                focusGrid();
            }));
            popup.appendChild(header);

            var dow = document.createElement('div');
            dow.className = 'flexa-extra-datepicker__weekdays';
            for (var i = 0; i < 7; i++) {
                // 2023-01-01 is a Sunday; offset by the locale's first weekday.
                var ref = new Date(2023, 0, 1 + ((startDow + i) % 7));
                var wd = document.createElement('span');
                wd.className = 'flexa-extra-datepicker__weekday';
                wd.textContent = weekdayFmt.format(ref);
                dow.appendChild(wd);
            }
            popup.appendChild(dow);

            var grid = document.createElement('div');
            grid.className = 'flexa-extra-datepicker__grid';
            grid.setAttribute('role', 'grid');
            grid.addEventListener('keydown', onGridKeydown);

            var lead = (new Date(year, month, 1).getDay() - startDow + 7) % 7;
            var daysInMonth = new Date(year, month + 1, 0).getDate();
            var sel = selectedDate();
            var today = new Date();

            for (var b = 0; b < lead; b++) {
                var blank = document.createElement('span');
                blank.className = 'flexa-extra-datepicker__day is-empty';
                grid.appendChild(blank);
            }

            for (var day = 1; day <= daysInMonth; day++) {
                var date = new Date(year, month, day);
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'flexa-extra-datepicker__day';
                btn.textContent = String(day);
                btn.setAttribute('role', 'gridcell');
                btn.setAttribute('data-date', toISO(date));
                btn.setAttribute('aria-label', displayFmt.format(date));
                if (isDisabled(date)) {
                    btn.disabled = true;
                    btn.setAttribute('aria-disabled', 'true');
                }
                if (sameDay(sel, date)) {
                    btn.classList.add('is-selected');
                    btn.setAttribute('aria-selected', 'true');
                }
                if (sameDay(today, date)) {
                    btn.classList.add('is-today');
                }
                (function (d) {
                    btn.addEventListener('click', function () {
                        if (!isDisabled(d)) { commit(d); }
                    });
                })(date);
                grid.appendChild(btn);
            }
            popup.appendChild(grid);

            var footer = document.createElement('div');
            footer.className = 'flexa-extra-datepicker__footer';
            var clearBtn = document.createElement('button');
            clearBtn.type = 'button';
            clearBtn.className = 'flexa-extra-datepicker__clear';
            clearBtn.textContent = i18n.clearDate || 'Clear';
            clearBtn.addEventListener('click', function () {
                input.value = '';
                fireChange();
                updateTriggerLabel();
                close();
                trigger.focus();
            });
            footer.appendChild(clearBtn);
            popup.appendChild(footer);
        }

        function onDocClick(e) {
            if (!wrapper.contains(e.target)) { close(); }
        }

        function onDocKeydown(e) {
            if (e.key === 'Escape') { close(); trigger.focus(); }
        }

        function open() {
            var start = clampToRange(selectedDate() || new Date());
            viewDate = new Date(start.getFullYear(), start.getMonth(), 1);
            render();
            popup.hidden = false;
            trigger.setAttribute('aria-expanded', 'true');
            document.addEventListener('click', onDocClick, true);
            document.addEventListener('keydown', onDocKeydown, true);
            focusGrid();
        }

        function close() {
            if (popup.hidden) { return; }
            popup.hidden = true;
            trigger.setAttribute('aria-expanded', 'false');
            document.removeEventListener('click', onDocClick, true);
            document.removeEventListener('keydown', onDocKeydown, true);
        }

        trigger.addEventListener('click', function () {
            if (popup.hidden) { open(); } else { close(); }
        });

        updateTriggerLabel();
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

        // Enhance native date inputs into the scoped calendar UI.
        container.querySelectorAll('input[data-flexa-date]').forEach(enhanceDatePicker);

        // Keep the color swatch + hex label in sync with the native color input.
        container.querySelectorAll('[data-flexa-color]').forEach(enhanceColorPicker);

        function recalculate() {
            // 1. Snapshot raw values for logic evaluation.
            var values = {};
            fields.forEach(function (field) {
                values[field.id] = readValue(fieldWrap(container, field.id));
            });

            // Formula-price context: base, line quantity, and each field's numeric
            // value for {field_id} references. Mirrors SelectionProcessor.
            var numericValues = {};
            fields.forEach(function (field) {
                var v = values[field.id];
                var n = (typeof v === 'string' || typeof v === 'number') ? parseFloat(v) : NaN;
                numericValues[field.id] = isFinite(n) ? n : 0;
            });
            var ctx = { base: productPrice, qty: readQuantity(container), fields: numericValues };

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
                        var amount = priceFor(opt.price, productPrice, ctx);
                        if (amount) {
                            lines.push({ label: optionLabel(field, opt), amount: amount });
                        }
                    });
                } else if (field.price && hasValue(values[field.id])) {
                    var fieldAmount = priceFor(field.price, productPrice, ctx);
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
                var magnitude = Math.abs(priceFor(action.price, productPrice, ctx));
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

        // A formula price can reference `qty`, so recompute when the product
        // form's quantity changes (the input lives outside our container).
        var qtyInput = productForm(container);
        qtyInput = qtyInput ? qtyInput.querySelector('input.qty, input[name="quantity"]') : null;
        if (qtyInput) {
            qtyInput.addEventListener('change', recalculate);
            qtyInput.addEventListener('input', recalculate);
        }

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
