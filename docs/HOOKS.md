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

## Notes

* The storefront input contract is `flexa_extra[<field_id>]` (with a `[]` suffix
  for multi-select). Anything that reads the posted selection should follow it.
* Prices are always recomputed server-side by `Cart\SelectionProcessor`; never
  rely on a client-submitted amount.
