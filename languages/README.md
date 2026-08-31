# Translations

`flexa-extra.pot` is the translation template for the whole plugin — both the PHP
side and the admin React app.

## Regenerating the .pot

The admin app is written in TypeScript/TSX, which `wp i18n make-pot` cannot parse.
Its strings must be extracted from the **built** bundle, so build first:

```sh
# 1. Build the admin app (from apps/admin/)
pnpm build

# 2. Extract JS strings from the built bundle
wp i18n make-pot assets/dist/admin /tmp/flexa-extra-js.pot --domain=flexa-extra --skip-audit

# 3. Extract PHP strings and merge the JS ones (TS source excluded — it yields nothing)
wp i18n make-pot . languages/flexa-extra.pot \
  --domain=flexa-extra \
  --merge=/tmp/flexa-extra-js.pot \
  --exclude=vendor,node_modules,tests,bin,assets/dist,apps \
  --package-name="Flexa Extra"
```

## Runtime

Script translations are served from the plugin's MO file via the
`load_script_translations` filter in `Register\RegisterFacade`, so translators only
need to ship `.po`/`.mo` files — no separate `make-json` step is required.
