=== Flexa Extra - Extra Product Options for WooCommerce ===
Contributors: flexatech
Tags: woocommerce, product options, extra product options, product addons, personalization
Requires at least: 5.0
Tested up to: 7.1
Requires PHP: 7.4
WC requires at least: 6.0.0
WC tested up to: 11.0.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add customizable extra options and personalization fields to WooCommerce products — text, choices, swatches, buttons — with optional per-option fees.

== Description ==

Flexa Extra lets you attach extra option fields to WooCommerce products so shoppers can personalize what they buy and you can charge for it. Build an **Option Set** once, assign it to products (all, a hand-picked list, or by category/tag/price/stock conditions), and the fields appear on the product page with live price updates.

Every price is recomputed on the server from your saved field definitions when the product is added to the cart — a client-submitted price is never trusted — so the amount a shopper pays always matches what you configured.

= Field types (free) =

* Text, textarea, number (with min/max/step and email/URL/regex validation)
* Checkbox, radio, dropdown
* Colour/image swatches
* Button group
* Heading / description block

= Pricing =

* Per-field or per-option surcharge
* Fixed amount or a percentage of the product price

= Assignment & logic =

* Target all products, a manual list, or conditions (category, tag, product, price, stock)
* Conditional logic to show/hide fields based on other selections

= Display & accessibility =

* Position fields before or after the add-to-cart button
* Live "extra subtotal" and "total price" readouts
* Swatch size/shape and button colour styling
* Accessible markup (fieldset/legend groups, `aria-required`, keyboard focus), responsive, and reduced-motion aware

== Installation ==

1. Upload the plugin to `/wp-content/plugins/flexa-extra` or install it through the Plugins screen.
2. Activate it. WooCommerce must be active.
3. Open **Flexa Extra** in the admin menu to configure settings and build Option Sets.

== Frequently Asked Questions ==

= Does it require WooCommerce? =

Yes. Flexa Extra stays inactive (with an admin notice) until WooCommerce is active.

= Can a customer tamper with the surcharge? =

No. The extra price is recomputed server-side from your stored option definitions on add-to-cart and on every cart totals pass; the posted values are sanitized against the field schema.

= Is it compatible with High-Performance Order Storage (HPOS)? =

Yes. The plugin declares HPOS compatibility.

= Can developers extend it? =

Yes — see `docs/HOOKS.md` for the available actions and filters.

== Source code for compiled JavaScript and CSS ==

The admin app ships as a compiled bundle in `assets/dist/admin/`. The
human-readable source is included in this package under `apps/admin/src/`
(with its build config) and is built with pnpm + Vite:

1. `cd apps/admin`
2. `pnpm install`
3. `pnpm build`   (or `pnpm dev` for a watched dev build)

The storefront assets in `assets/frontend/` are plain, unminified JS/CSS and need
no build step.

== Changelog ==

= 1.0.0 =
* Initial release: option-set builder, storefront render engine, server-authoritative pricing/cart engine, UX & style settings, and a two-tier automated test suite.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
