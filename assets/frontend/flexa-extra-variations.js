/**
 * Flexa Extra — variation swatches (storefront).
 *
 * Overlays color / image / button swatches on WooCommerce variation dropdowns.
 * The native <select> (hidden by the renderer, marked `data-flexa-raw`) stays
 * the single source of truth: a swatch click sets the select's value and fires
 * its change event, so WooCommerce's own variation form handles price, stock
 * and gallery image. We only mirror WC's state back onto the swatches.
 *
 * Depends on jQuery so we can listen to WooCommerce's custom jQuery events
 * (woocommerce_variation_has_changed, check_variations, reset_data) and trigger
 * the select change through jQuery's delegated handler on the form.
 */
(function ($) {
    'use strict';

    /** Push a value into the hidden select and notify WooCommerce. */
    function selectValue(select, value) {
        select.value = value;
        // Native event bubbles to the form's delegated jQuery handler…
        select.dispatchEvent(new Event('change', { bubbles: true }));
        // …and a jQuery trigger as a belt-and-suspenders for older themes.
        if ($) {
            $(select).trigger('change');
        }
    }

    /** Mirror the select's value + per-option disabled state onto the swatches. */
    function sync(select, list, label) {
        var current = select.value;
        var disabled = {};
        for (var i = 0; i < select.options.length; i++) {
            var opt = select.options[i];
            if (opt.value) {
                disabled[opt.value] = opt.disabled;
            }
        }

        var selectedTitle = '';
        var items = list.querySelectorAll('.flexa-extra-vswatch__item');
        for (var j = 0; j < items.length; j++) {
            var li = items[j];
            var value = li.getAttribute('data-value');
            var isSelected = value === current && current !== '';
            var isDisabled = !!disabled[value];
            if (isSelected) {
                selectedTitle = li.getAttribute('data-title') || '';
            }

            li.classList.toggle('is-selected', isSelected);
            li.classList.toggle('is-disabled', isDisabled);
            li.setAttribute('aria-checked', isSelected ? 'true' : 'false');
            li.setAttribute('aria-disabled', isDisabled ? 'true' : 'false');
            li.setAttribute('tabindex', isDisabled ? '-1' : '0');
        }

        // Keep the optional "Attribute: Value" line in sync with the selection.
        if (label) {
            label.textContent = selectedTitle;
        }
    }

    /** Move focus/selection to another enabled item (keyboard arrow nav). */
    function focusSibling(items, fromIndex, dir) {
        var count = items.length;
        for (var step = 1; step <= count; step++) {
            var idx = (fromIndex + dir * step + count * step) % count;
            var candidate = items[idx];
            // Skip disabled and currently-hidden (collapsed overflow) swatches.
            if (!candidate.classList.contains('is-disabled') && candidate.offsetParent !== null) {
                candidate.focus();
                return candidate;
            }
        }
        return null;
    }

    function bindWrap(wrap) {
        var select = wrap.querySelector('select[data-flexa-raw]');
        var list = wrap.querySelector('.flexa-extra-vswatch');
        if (!select || !list) {
            return null;
        }

        var items = list.querySelectorAll('.flexa-extra-vswatch__item');
        var label = wrap.querySelector('.flexa-extra-vswatch__selected-value');
        var clearOnReselect = list.getAttribute('data-clear-reselect') === '1';
        var preloader = list.getAttribute('data-preloader') === '1';

        var pick = function (li) {
            if (preloader) {
                wrap.classList.add('is-loading');
            }
            // Click the already-selected swatch again to clear it, when enabled.
            if (clearOnReselect && li.classList.contains('is-selected')) {
                selectValue(select, '');
                return;
            }
            selectValue(select, li.getAttribute('data-value'));
        };

        // "+N more" toggle: reveal the folded swatches and hide itself.
        var more = list.querySelector('.flexa-extra-vswatch__more');
        var expand = function () {
            list.classList.add('is-expanded');
            if (more) {
                more.setAttribute('aria-expanded', 'true');
            }
        };
        if (more) {
            more.addEventListener('click', function (event) {
                event.preventDefault();
                expand();
            });
            more.addEventListener('keydown', function (event) {
                var key = event.key;
                if (key === ' ' || key === 'Enter' || key === 'Spacebar') {
                    event.preventDefault();
                    expand();
                }
            });
        }

        list.addEventListener('click', function (event) {
            var li = event.target.closest('.flexa-extra-vswatch__item');
            if (!li || li.classList.contains('is-disabled')) {
                return;
            }
            pick(li);
        });

        list.addEventListener('keydown', function (event) {
            var li = event.target.closest('.flexa-extra-vswatch__item');
            if (!li) {
                return;
            }

            var key = event.key;
            if (key === ' ' || key === 'Enter' || key === 'Spacebar') {
                event.preventDefault();
                if (!li.classList.contains('is-disabled')) {
                    pick(li);
                }
                return;
            }

            var dir = 0;
            if (key === 'ArrowRight' || key === 'ArrowDown') {
                dir = 1;
            } else if (key === 'ArrowLeft' || key === 'ArrowUp') {
                dir = -1;
            }
            if (dir !== 0) {
                event.preventDefault();
                var arr = Array.prototype.slice.call(items);
                var picked = focusSibling(items, arr.indexOf(li), dir);
                if (picked) {
                    selectValue(select, picked.getAttribute('data-value'));
                }
            }
        });

        return {
            select: select,
            list: list,
            label: label,
            wrap: wrap,
            // Canonical `attribute_pa_color` key, matching the variation JSON.
            attrKey: select.getAttribute('name') || '',
            stock: list.getAttribute('data-stock') === '1'
        };
    }

    /**
     * Fill each swatch's stock node ("N left" / "Out of stock") from the matching
     * WooCommerce variation. Needs the `product_variations` JSON the form carries
     * for products under the AJAX threshold; otherwise it does nothing (graceful).
     */
    function syncStock(form, pairs, variations, config) {
        if (!config || !Array.isArray(variations) || !variations.length) {
            return;
        }

        // Every chosen attribute on the form (swatch or plain select).
        var selections = {};
        var selects = form.querySelectorAll('select[name^="attribute_"]');
        for (var s = 0; s < selects.length; s++) {
            selections[selects[s].getAttribute('name')] = selects[s].value || '';
        }

        var low = config.lowStockAmount || 0;
        var i18n = config.i18n || {};

        var matchesFor = function (attrKey, value) {
            return variations.filter(function (v) {
                var a = v.attributes || {};
                var probe = attrKey ? { key: attrKey, value: value } : null;
                // The candidate term for this attribute must match (or be "any").
                if (probe) {
                    var have = a[probe.key];
                    if (have !== undefined && have !== '' && have !== probe.value) {
                        return false;
                    }
                }
                // Every other already-selected attribute must match (or be "any").
                for (var key in selections) {
                    if (key === attrKey || selections[key] === '') {
                        continue;
                    }
                    var hv = a[key];
                    if (hv !== undefined && hv !== '' && hv !== selections[key]) {
                        return false;
                    }
                }
                return true;
            });
        };

        for (var p = 0; p < pairs.length; p++) {
            if (!pairs[p].stock) {
                continue;
            }
            var attrKey = pairs[p].attrKey;
            var items = pairs[p].list.querySelectorAll('.flexa-extra-vswatch__item');
            for (var j = 0; j < items.length; j++) {
                var node = items[j].querySelector('.flexa-extra-vswatch__stock');
                if (!node) {
                    continue;
                }
                var matches = matchesFor(attrKey, items[j].getAttribute('data-value'));
                var inStock = matches.filter(function (v) { return v.is_in_stock; });

                var text = '';
                var oos = false;
                if (matches.length && !inStock.length) {
                    text = i18n.outOfStock || '';
                    oos = true;
                } else if (inStock.length === 1) {
                    var q = parseInt(inStock[0].max_qty, 10);
                    if (!isNaN(q) && q > 0 && low > 0 && q <= low && i18n.left) {
                        text = i18n.left.replace('%d', q);
                    }
                }

                node.textContent = text;
                node.classList.toggle('flexa-extra-vswatch__stock--oos', oos);
            }
        }
    }

    function initForm(form) {
        var wraps = form.querySelectorAll('.flexa-extra-vswatch-wrap');
        var pairs = [];
        for (var i = 0; i < wraps.length; i++) {
            var pair = bindWrap(wraps[i]);
            if (pair) {
                pairs.push(pair);
            }
        }
        if (!pairs.length) {
            return;
        }

        var config = window.flexaExtraVswatch || null;
        // WooCommerce localizes the full variation set on the form when the product
        // has few enough variations; jQuery.data parses it (or false) for us.
        var variations = $ ? $(form).data('product_variations') : null;

        var syncAll = function () {
            for (var k = 0; k < pairs.length; k++) {
                sync(pairs[k].select, pairs[k].list, pairs[k].label);
                // WooCommerce has settled: drop any pending preloader state.
                pairs[k].wrap.classList.remove('is-loading');
            }
            syncStock(form, pairs, variations, config);
        };

        // WooCommerce re-evaluates availability and selection on these events.
        if ($) {
            $(form).on(
                'woocommerce_variation_has_changed check_variations woocommerce_update_variation_values reset_data',
                syncAll
            );
        }

        syncAll();
    }

    function init() {
        var forms = document.querySelectorAll('.variations_form');
        for (var i = 0; i < forms.length; i++) {
            initForm(forms[i]);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})(window.jQuery);
