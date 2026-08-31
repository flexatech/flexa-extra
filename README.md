# Flexa Extra — Extra Product Options for WooCommerce

A Flexa-lineage plugin that adds extra options and personalization fields to
WooCommerce products. This is the **foundation + admin shell** milestone:

- PHP bootstrap, REST API, settings store, and the `flexa_extra_option_set` CPT.
- React admin app (`apps/admin`) with a **vertical-tab Settings screen** modelled
  on the *Retailers Management for WooCommerce* admin.

The frontend pricing/render engine (fields on the product page, cart fees, etc.)
lands in a later milestone.

## Architecture (PHP — `Flexa\Extra\`)

```
flexa-extra.php                     bootstrap: constants, autoloader, plugin_init
includes/
  Initialize.php                    boots engine singletons
  I18n.php  Fallback.php
  Utils/SingletonTrait.php
  Register/                         ScriptName, RegisterFacade, RegisterProd, RegisterDev
  Engine/
    ActDeact.php                    activation, WC notice, HPOS compat
    RestAPI.php                     registers every REST controller
    Admin/Settings.php              admin menu + enqueue (mounts #flexa-extra-admin-root)
    Admin/CustomPostType.php        flexa_extra_option_set CPT
  Helpers/Helper.php                JS config + settings schema/sanitizer
  Controllers/                      BaseRestController, SettingsRestController, OptionSetsRestController
```

REST namespace: `flexa-extra/v1`
Hooks: `flexa_extra/…` · Option key: `flexa_extra_settings` · JS global: `window.flexaExtra`

## Building the admin app

The plugin loads `assets/dist/admin/js/main.js` in production. Build it:

```bash
cd apps/admin
pnpm install
pnpm build          # → ../../assets/dist/admin/{js/main.js,style.css}
```

### Live development (HMR)

1. Set `FLEXA_EXTRA_IS_DEVELOPMENT` to `true` in `flexa-extra.php`.
2. `cd apps/admin && pnpm dev` (serves on http://localhost:3000).
3. Open **WP Admin → Flexa Extra**.

Flip the constant back to `false` and run `pnpm build` for production.
