=== Flexa Extra – Extra Product Options For WooCommerce ===
Contributors: flexatech
Tags: woocommerce, product options, extra product options, product addons, personalization
Requires at least: 5.0
Tested up to: 7.1
Requires PHP: 7.4
WC requires at least: 6.0.0
WC tested up to: 11.0.0
Stable tag: 1.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Extra product options for WooCommerce: text, choices, swatches, buttons, date and colour pickers, plus optional per-option fees priced server-side.

== Description ==

Flexa Extra lets you attach extra option fields to WooCommerce products so shoppers can personalize what they buy and you can charge for it. Build an **Option Set** once, assign it to products (all, a hand-picked list, or by category/tag/price/stock conditions), and the fields appear on the product page with live price updates.

Every price is recomputed on the server from your saved field definitions when the product is added to the cart — a client-submitted price is never trusted — so the amount a shopper pays always matches what you configured.

= Field types (free) =

* Text, textarea, number (with min/max/step and email/URL/regex validation)
* Date picker and colour picker
* Checkbox, radio, dropdown
* Colour/image swatches
* Button group
* Heading / description block
* Minimum and maximum number of choices on any multi-select field

= Pricing =

* Per-field or per-option surcharge
* Fixed amount or a percentage of the product price
* Conditional fees and discounts applied to the item when selections match a rule

= Inventory =

* Optional stock limit per choice option: sold-out options are disabled, over-selling is blocked at add-to-cart, and paid orders draw the stock down (restored on cancel or refund)

= Templates =

* Start from a template: pick a ready-made starter (gift wrapping, engraving, size & colour, installation service, warranty plan, product add-ons) and it creates an option set, switched off, that you can edit before turning it on

= Assignment & logic =

* Target all products, a manual list, or conditions (category, tag, product, price, stock)
* Conditional logic to show/hide fields based on other selections
* Duplicate an option set, and import or export sets as a JSON file
* Import your existing option sets from YayExtra or ThemeHigh "Extra Product Options" (added switched off for review)
* Live preview in the builder: see the option set render (and price) exactly as the storefront will, updating as you edit

= Store insights =

* Analytics screen ranking which options and choices sell, and the add-on revenue they bring, filterable by date range and order status

= Headless & integrations =

* Gutenberg "Flexa Extra Configurator" block to show an option set with live pricing on any page or post (display only, does not add to cart)
* Public read-only REST API for decoupled/headless storefronts: fetch a product's option sets, prices, and logic to render the same configurator and subtotal elsewhere. Open by default, closable with a filter

= Cart =

* Edit a line's options straight from the cart: the product page reopens with the
  saved selections filled in, and re-adding replaces the line instead of stacking a
  duplicate

= Display & accessibility =

* Position fields before or after the add-to-cart button
* Live "extra subtotal" and "total price" readouts
* Itemized price breakdown: each selected option and conditional fee/discount listed with its own price, updating live
* Swatch size/shape and button colour styling
* Accessible markup (fieldset/legend groups, `aria-required`, keyboard focus), responsive, and reduced-motion aware

== Installation ==

1. Upload the plugin to `/wp-content/plugins/flexa-extra` or install it through the Plugins screen.
2. Activate it. WooCommerce must be active.
3. On activation a short quick-start guide points you at the template gallery so you can build your first Option Set in a couple of minutes. It is skippable, and you can replay it later from Advanced settings.
4. Open **Flexa Extra** in the admin menu to configure settings and build Option Sets.

== Frequently Asked Questions ==

= Does it require WooCommerce? =

Yes. Flexa Extra stays inactive (with an admin notice) until WooCommerce is active.

= Can a customer tamper with the surcharge? =

No. The extra price is recomputed server-side from your stored option definitions on add-to-cart and on every cart totals pass; the posted values are sanitized against the field schema.

= Is it compatible with High-Performance Order Storage (HPOS)? =

Yes. The plugin declares HPOS compatibility.

= Can developers extend it? =

Yes, see `docs/HOOKS.md` for the available actions and filters.

= Can I import from another options plugin? =

Yes. The Import screen reads option sets from YayExtra and from ThemeHigh "Extra Product Options" (free) and re-creates them in Flexa Extra, switched off. They appear in the Option Sets list for review; the storefront ignores them until you turn each one on. Field types, per-option prices, swatches and product assignment are mapped where an equivalent exists; anything that cannot be mapped 1:1 is listed per set so you can re-create it. Keep the source plugin active during the import so its data is readable.

= Can I use it with a headless or decoupled front end? =

Yes. Two open, read-only REST routes (`/wp-json/flexa-extra/v1/public/config` and `/public/product/{id}`) return the option sets, prices, and logic a front end needs to render the configurator and compute the live subtotal. They expose only what the on-page configurator already ships, and you can close the API with the `flexa_extra/public_api/enabled` filter. Add-to-cart and the final price are always recomputed server-side.

== Screenshots ==

1. All your option fields on one product page: swatches, buttons, radio and checkbox choices, priced live.
2. Add-ons that charge automatically: per-option fees, a colour dropdown, and a date picker on the product page.
3. One option set reused across products: the same gift options assigned wherever you need them.
4. Build option sets by drag and drop: pick a field from the palette, drop it in, and configure it in the inspector.
5. Conditional fees and discounts: apply a fixed or percentage amount when the shopper's selections match your rules.
6. Decide where each set applies: all products, a hand-picked list, or by category, tag, price and stock conditions.
7. Control what shoppers see: toggle the extra subtotal, total price, itemized breakdown, and mini-cart values.
8. Start from a ready-made template: six starters you can edit before turning the set on.

== Source code for compiled JavaScript and CSS ==

The admin app ships as a compiled bundle in `assets/dist/admin/`. The
human-readable source is included in this package under `apps/admin/src/`
(with its build config) and is built with pnpm + Vite:

1. `cd apps/admin`
2. `pnpm install`
3. `pnpm build`   (or `pnpm dev` for a watched dev build)

The storefront assets in `assets/frontend/` are plain, unminified JS/CSS and need
no build step.

== External services ==

This plugin does not connect to any external services. All data is stored locally in your WordPress database.

== Changelog ==

= 1.1.1 =
* Fixed: YayExtra import brought nothing over. Field types (and per-option names) are stored by YayExtra as `{ value, label }` pairs, not the flat `fieldType` key the converter expected, so every field was skipped and each set dropped as empty. The real saved shape is now read, with the older flat form still accepted.
* Fixed: conditional product assignment is now migrated from YayExtra instead of falling back to "all products". Category and tag names are resolved to term IDs and each "is one of" list becomes one targeting rule per term; anything that cannot be mapped is reported per set.
* Fixed: plain button fields no longer import as colour swatches. Colour and image are now carried only for swatch field types, per option according to its swatch type.

= 1.1.0 =
* Import option sets from YayExtra and ThemeHigh "Extra Product Options" (free): auto-detected, added switched off through the schema sanitizer, with a per-set report of anything that could not be mapped 1:1.
* Headless read API: two open, read-only REST routes (`/public/config` and `/public/product/{id}`) so a decoupled front end can render the configurator and compute the subtotal. Closable with the `flexa_extra/public_api/enabled` filter.
* Sales analytics screen: rank options/choices by picks and add-on revenue, filterable by date range and order status (HPOS-safe).
* Gutenberg "Flexa Extra Configurator" block: show an option set with live pricing on any page or post (display only).
* Live preview in the builder: render and price an option set as the storefront will, updating as you edit.
* Conditional fees and discounts: set-level rules that add a fixed or percentage fee or discount when selections match (a discount never drops the line below zero).
* Per-option stock: give a choice a limited quantity; sold-out options are disabled, over-selling is blocked, and paid orders draw stock down (restored on cancel or refund).
* Min and max choices per multi-select field, enforced again on the server at add-to-cart.
* Edit options in cart: reopen the product page with the saved selections and replace the line instead of stacking a duplicate.
* Template library: start a new option set from a ready-made starter.
* Itemized price breakdown on the product page, listing each option and fee or discount with its own price.
* First-run quick-start guide, replayable from Advanced settings.
* Date picker and colour picker field types.
* Duplicate an option set, and import or export sets as JSON.
* Fixed: admin dropdown arrows no longer crowd the text.

= 1.0.1 =
* Security: escape swatch image URLs for the CSS `url()` context on the product page and in the cart, preventing CSS injection through crafted URLs.
* Removed the "Tested up to" line from the main plugin file so compatibility is declared only in readme.txt.

= 1.0.0 =
* Initial release: option-set builder with text, number, date picker, colour picker, choice, swatch and button fields; storefront render engine; server-authoritative pricing/cart engine; UX & style settings; and a two-tier automated test suite.

== Upgrade Notice ==

= 1.1.1 =
Fixes the YayExtra importer, which previously brought nothing over and ignored product assignment. Recommended if you import from YayExtra.

= 1.1.0 =
Big feature release: import from YayExtra/ThemeHigh, analytics, headless read API, a Gutenberg block, live builder preview, conditional fees/discounts, per-option stock, and more. No breaking changes.

= 1.0.1 =
Hardens swatch image output against CSS injection. Recommended update.

= 1.0.0 =
Initial release.
