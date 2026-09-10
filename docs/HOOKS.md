# Flexa Extra — Developer Hooks

All hooks use the slash-separated `flexa_extra/` prefix. Signatures below match the
source in `includes/`.

## Actions

### `flexa_extra/rest/register_routes`
Fires after the built-in REST controllers are registered, on `rest_api_init`.
Register your own controllers here.

```php
add_action( 'flexa_extra/rest/register_routes', function () {
    ( new My_Controller() )->register_routes();
} );
```

### `flexa_extra/option_set/saved`
Fires after an option set is created or updated and its meta is written.

| Param | Type | Description |
|-------|------|-------------|
| `$post_id` | `int` | Option-set post ID |
| `$data` | `array{name:string,status:bool,fields:array,targeting:array}` | Sanitized payload |

### `flexa_extra/migration/imported`
Fires after a set imported from another plugin is created (published but inactive).

| Param | Type | Description |
|-------|------|-------------|
| `$post_id` | `int` | New option-set post ID |
| `$data` | `array{name:string,status:bool,fields:array,targeting:array,actions:array}` | Sanitized payload |

### `flexa_extra/settings/updated`
Fires after the plugin settings option is saved.

| Param | Type | Description |
|-------|------|-------------|
| `$new` | `array` | Sanitized new settings |
| `$old` | `array` | Previous settings |

## Filters

### `flexa_extra/default_settings`
Filter the default settings array (`Helper::get_default_settings()`).

`apply_filters( 'flexa_extra/default_settings', array $defaults )`

### `flexa_extra/sanitize_settings`
Filter the sanitized settings before they are returned/stored.

`apply_filters( 'flexa_extra/sanitize_settings', array $sanitized, array $input )`

### `flexa_extra/js_config`
Filter the config object localized to the admin app (`window.flexaExtra`).

`apply_filters( 'flexa_extra/js_config', array $config )`

### `flexa_extra/field/types`
Filter the registered field-type slugs.

`apply_filters( 'flexa_extra/field/types', array $types )`

### `flexa_extra/field/catalog`
Filter the field catalog (metadata shown in the builder palette).

`apply_filters( 'flexa_extra/field/catalog', array $catalog )`

### `flexa_extra/option_set/sanitize`
Filter the sanitized option-set payload before it is persisted. Use this to add
and validate custom field keys.

`apply_filters( 'flexa_extra/option_set/sanitize', array $result, array $input )`

### `flexa_extra/migration/sources`
Filter the list of import sources shown on the Import screen. Add your own by
returning an extra instance of `Flexa\Extra\Migration\AbstractMigrationSource`.

`apply_filters( 'flexa_extra/migration/sources', array $sources )`

### `flexa_extra/hub/show_ecosystem`
Whether the Flexa hub page lists the wider flexatech ecosystem (our other
plugins) as install suggestions. Off by default while only the in-plugin modules
(Product Options, Variation Swatches) are shown; return `true` to bring the full
grid back.

`apply_filters( 'flexa_extra/hub/show_ecosystem', bool $show )`

### `flexa/dashboard/modules`
Filter the Flexa module catalog shown on the hub. Add or override entries keyed
by module slug (see `Settings::hub_modules()` for the recognised fields). This
runs on both the in-plugin-only and the full-ecosystem views.

`apply_filters( 'flexa/dashboard/modules', array $modules )`

### `flexa_extra/resolver/applicable_sets`
Filter which option sets apply to a product before rendering. Return value is
cached per product for the request.

| Param | Type | Description |
|-------|------|-------------|
| `$applicable` | `array<array{id:int,name:string,fields:array}>` | Matching sets |
| `$product` | `WC_Product` | The product being viewed |

### `flexa_extra/cart/item_extra`
Filter the recomputed surcharge for a cart item before it is applied to the price.
Runs on every `woocommerce_before_calculate_totals` pass.

| Param | Type | Description |
|-------|------|-------------|
| `$extra` | `float` | Computed surcharge (`base + extra` is set absolutely) |
| `$cart_item` | `array` | The cart item |
| `$result` | `array{selections:array,lines:array,total:float,errors:array}` | Full processor result |

```php
add_filter( 'flexa_extra/cart/item_extra', function ( $extra, $cart_item, $result ) {
    // e.g. round the surcharge up to the nearest whole unit.
    return ceil( $extra );
}, 10, 3 );
```

### `flexa_extra/variation_swatches/enabled`
Turn the variation-swatch overlay on or off. Defaults to `general.enabled`. The
"defer to another swatches plugin" guard hangs off this filter.

`apply_filters( 'flexa_extra/variation_swatches/enabled', bool $enabled )`

### `flexa_extra/variation_swatches/item_html`
Filter the inner markup of a single variation swatch (the color chip, image or
button label) before it is wrapped in its `<li>`.

| Param | Type | Description |
|-------|------|-------------|
| `$inner` | `string` | Inner HTML (chip / image / label) |
| `$term` | `WP_Term` | The attribute term |
| `$type` | `string` | Swatch type: `color`, `image` or `button` |

```php
add_filter( 'flexa_extra/variation_swatches/item_html', function ( $inner, $term, $type ) {
    return $inner;
}, 10, 3 );
```

### `flexa_extra/variation_swatches/archive_hook` and `/archive_hook_priority`
Change where shop / category swatches are rendered in the product loop. Defaults
to the `woocommerce_after_shop_loop_item_title` action at priority `20`.

```php
add_filter( 'flexa_extra/variation_swatches/archive_hook', fn() => 'woocommerce_after_shop_loop_item' );
add_filter( 'flexa_extra/variation_swatches/archive_hook_priority', fn() => 15 );
```

### Variation swatch CSS variables

The swatch list carries inline custom properties driven by the Variation Swatches
settings. Any of these can be overridden in your own stylesheet:

| Property | Purpose | Default |
|----------|---------|---------|
| `--fxe-vswatch-size` | Preset swatch box size | `36px` |
| `--fxe-vswatch-w` / `--fxe-vswatch-h` | Custom width / height (px override) | falls back to size |
| `--fxe-vswatch-radius` | Corner radius of colour / image chips | `50%` |
| `--fxe-vswatch-pill-radius` | Corner radius of button pills | `999px` |
| `--fxe-vswatch-font` | Button swatch font size | `0.85em` |
| `--fxe-vswatch-tick` | Selected-swatch ring colour | theme `currentColor` |
| `--fxe-vswatch-cross` | Strike colour on unavailable swatches | `rgba(0,0,0,0.4)` |
| `--fxe-vswatch-selected-bg` | Background of the selected button pill | `#1f2327` |

The list element also exposes `data-oos` (`blur` / `hide` / `none`) for the
unavailable-swatch behaviour.

When "Limit visible swatches" is set, swatches past the limit carry
`flexa-extra-vswatch__item--overflow` and stay hidden until the
`flexa-extra-vswatch__more` toggle ("+N more") is activated, which adds
`is-expanded` to the list. Both the overflow items and the toggle chip are
styleable from your own stylesheet.

When "Show stock per swatch" is on, the list gets the `flexa-extra-vswatch--stock`
class and each swatch holds a `flexa-extra-vswatch__stock` node that the storefront
script fills from the matching variation: "N left" on low stock (at or below
WooCommerce's low-stock threshold) or "Out of stock" when every matching variation
is sold out. The node gains `flexa-extra-vswatch__stock--oos` in the sold-out case.
This reads the variation data WooCommerce prints on the form, so products with more
variations than the AJAX threshold do not show it.

```css
/* Brand-colour the selected button */
.flexa-extra-vswatch--button { --fxe-vswatch-selected-bg: #2563eb; }
```

## Styling

Every field is wrapped in a `<div>` that carries stable class hooks so you can
target fields from your theme or a custom stylesheet:

| Class | Applies to |
|-------|------------|
| `flexa-extra-field` | Every field wrapper. |
| `flexa-extra-field--<type>` | All fields of a type (`radio`, `dropdown`, `swatch`, `number`, ...). |
| `flexa-extra-field--id-<field_id>` | One specific field. |

The wrapper also exposes `data-field-id` and `data-field-type` attributes.

You can add your own class(es) per field in the builder: select a field and fill
the **CSS class** box in the Inspector (space-separated for multiple). They are
appended to the wrapper as-is (each token sanitized with `sanitize_html_class()`).

```css
/* All swatch fields */
.flexa-extra-field--swatch { margin-top: 1.5rem; }

/* One field by its id */
.flexa-extra-field--id-fld_ab12cd { border: 1px solid #ddd; padding: 1rem; }

/* A custom class typed in the Inspector */
.flexa-extra-field.highlight { background: #fffbe6; }
```

### Custom controls (checkbox, radio, colour)

Checkboxes and radios keep the native `<input>` (visually hidden) and draw the
visible control as a sibling `<span class="flexa-extra-choice__control">`; the
colour field wraps the native `<input type="color">` in
`.flexa-extra-colorpicker` with a `__swatch` and `__value` span. Restyle them
without touching markup, for example to recolour the checked state:

```css
/* Checked checkbox/radio fill */
.flexa-extra-choice input:checked + .flexa-extra-choice__control {
    border-color: #0a7c3f;
    background: #0a7c3f;
}

/* Colour field swatch size */
.flexa-extra-colorpicker__swatch { width: 2em; height: 2em; }
```

## Notes

* The storefront input contract is `flexa_extra[<field_id>]` (with a `[]` suffix
  for multi-select). Anything that reads the posted selection should follow it.
* Prices are always recomputed server-side by `Cart\SelectionProcessor`; never
  rely on a client-submitted amount.
