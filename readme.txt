=== Flexa Extra – Product Options & Variation Swatches for WooCommerce ===
Contributors: flexatech
Tags: woocommerce, product options, variation swatches, product addons, personalization
Requires at least: 5.0
Tested up to: 7.1
Requires PHP: 7.4
WC requires at least: 6.0.0
WC tested up to: 11.0.0
Stable tag: 1.4.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Custom product options and variation swatches for WooCommerce: add fields with optional fees, and show attribute swatches, all priced server-side.

== Description ==

Flexa Extra lets you attach extra option fields to WooCommerce products so shoppers can personalize what they buy and you can charge for it. Build an **Option Set** once, assign it to products (all, a hand-picked list, or by category/tag/price/stock conditions), and the fields appear on the product page with live price updates.

Every price is recomputed on the server from your saved field definitions when the product is added to the cart (a client-submitted price is never trusted), so the amount a shopper pays always matches what you configured.

📌 [**DEMO**](https://templates.sitebefy.com/templates/maison-verte/product/riviera-coral/)

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
* Fixed amount, a percentage of the product price, or a safe arithmetic formula
* Formula prices use `base`, `qty` and other fields' values with `+ - * / ( )` and `round()`, `min()`, `max()` (no `eval`), for per-unit and volume-tiered pricing
* Conditional fees and discounts applied to the item when selections match a rule

= Inventory =

* Optional stock limit per choice option: sold-out options are disabled, over-selling is blocked at add-to-cart, and paid orders draw the stock down (restored on cancel or refund)

= Templates =

* Start from a template: pick a ready-made starter (gift wrapping, engraving, size & colour, installation service, warranty plan, product add-ons) and it creates an option set, switched off, that you can edit before turning it on

= Assignment & logic =

* Target all products, a manual list, or conditions (category, tag, product, price, stock)
* Conditional logic to show/hide fields based on other selections
* Duplicate an option set, and import or export sets as a JSON file
* Live preview in the builder: see the option set render (and price) exactly as the storefront will, updating as you edit

= Store insights =

* Analytics screen ranking which options and choices sell, and the add-on revenue they bring, filterable by date range and order status

= Variation swatches =

* Turn WooCommerce variation attribute dropdowns into colour, image or button swatches on variable products, with its own admin screen to assign a colour or image to each attribute term (no extra plugin required)
* The native variation `<select>` stays in place (hidden) and remains the source of truth: clicking a swatch drives WooCommerce's own variation logic, so price, stock and gallery image update exactly as before, unavailable combinations are dimmed, and keyboard selection works
* Size, shape, custom width/height, button font size, selected-ring and unavailable-strike colours, and an optional hover tooltip, all from the Style settings
* Show the selected value as an "Attribute: Value" line, render attributes with no swatch type as buttons, and show a loading spinner while the variation updates
* Choose how unavailable or out-of-stock swatches behave (dim and strike, hide, or show as normal), and optionally clear the choice by clicking the selected swatch again
* Limit how many swatches show per attribute with a "+N more" toggle, and show per-swatch stock ("N left" on low stock, "Out of stock" when a combination is sold out)
* Show swatches in the shop and category loop too: each links to the product with that option preselected
* Only applies to global attributes (`pa_*`) that have terms; custom product-level attributes have no term to attach a colour/image to. If a dedicated plugin like Woo Variation Swatches is active, Flexa Extra steps aside automatically to avoid double rendering

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

= Can I style individual fields with my own CSS? =

Yes. Each field is wrapped in a `<div>` with class hooks: `flexa-extra-field` on every wrapper, `flexa-extra-field--<type>` for all fields of a type, and `flexa-extra-field--id-<field_id>` for one specific field (plus `data-field-id` and `data-field-type` attributes). You can also add your own class per field in the builder: select the field and fill the "CSS class" box in the Inspector. See `docs/HOOKS.md` for examples.

= Can I import from another options plugin? =

Yes. The Import screen reads option sets from YayExtra, ThemeHigh "Extra Product Options" (free), WooCommerce Product Add-Ons, Acowebs "Custom Product Addons for WooCommerce", and YITH WooCommerce Product Add-Ons (free), and re-creates them in Flexa Extra, switched off. They appear in the Option Sets list for review; the storefront ignores them until you turn each one on. Field types, per-option prices, swatches and product assignment are mapped where an equivalent exists; anything that cannot be mapped 1:1 is listed per set so you can re-create it. Keep the source plugin active during the import so its data is readable.

= Can I use it with a headless or decoupled front end? =

Yes. Two open, read-only REST routes (`/wp-json/flexa-extra/v1/public/config` and `/public/product/{id}`) return the option sets, prices, and logic a front end needs to render the configurator and compute the live subtotal. They expose only what the on-page configurator already ships, and you can close the API with the `flexa_extra/public_api/enabled` filter. Add-to-cart and the final price are always recomputed server-side.

= How do the variation swatches work, and what are the limits? =

On a variable product, Flexa Extra can replace each attribute dropdown with colour, image or button swatches. It hides the native `<select>` but keeps it as the source of truth, so clicking a swatch uses WooCommerce's own variation logic for price, stock and the product gallery. Assign colours/images per attribute term under **Flexa Extra → Variation Swatches**. It works only with global attributes (`pa_*`) that have terms; custom product-level attributes have no term to attach to. If a dedicated swatches plugin (for example Woo Variation Swatches) is active, Flexa Extra defers to it automatically to avoid rendering swatches twice.

== Screenshots ==

1. All your option fields on one product page: swatches, buttons, radio and checkbox choices, priced live.
2. Add-ons that charge automatically: per-option fees, a colour dropdown, and a date picker on the product page.
3. One option set reused across products: the same gift options assigned wherever you need them.
4. Build option sets by drag and drop: pick a field from the palette, drop it in, and configure it in the inspector.
5. Conditional fees and discounts: apply a fixed or percentage amount when the shopper's selections match your rules.
6. Decide where each set applies: all products, a hand-picked list, or by category, tag, price and stock conditions.
7. Control what shoppers see: toggle the extra subtotal, total price, itemized breakdown, and mini-cart values.
8. Start from a ready-made template: six starters you can edit before turning the set on.
9. Set up variation swatches in the admin: give each attribute term a colour, image or button style, with tabs for Swatches, Settings and Analytics.
10. Swatches on the product page: colour, image and button swatches replace the variation dropdowns, with a Clear link to reset the choice.

== Source code for compiled JavaScript and CSS ==

The admin app ships as a compiled bundle in `assets/dist/admin/`. The
human-readable TypeScript source and its build config live in the public
repository at https://github.com/flexatech/flexa-extra (under `apps/admin/`)
and are built with pnpm + Vite:

1. `cd apps/admin`
2. `pnpm install`
3. `pnpm build`   (or `pnpm dev` for a watched dev build)

The storefront assets in `assets/frontend/` are plain, unminified JS/CSS and need
no build step.

== External services ==

This plugin does not connect to any external services. All data is stored locally in your WordPress database.

== Changelog ==

= 1.4.3 =
* Swatch analytics: a new Analytics tab under Variation Swatches shows which attribute values shoppers actually buy, with the times bought and revenue for each, and the swatch colour or image next to it. It reads your existing orders, so no extra tracking is added and past orders count too.
* Added two screenshots covering the Variation Swatches admin screen and swatches on the product page.

= 1.4.2 =
* Variation Swatches settings now use the same left-hand tab layout as the Product Options settings screen: General, Display, Appearance, Availability and Shop pages, instead of one long scrolling column. No settings or behaviour changed.

= 1.4.1 =
* Packaging: the distributed plugin now contains only the compiled admin bundle. The TypeScript build source is published in the public GitHub repository instead of being bundled in the download, so the zip is smaller. No functional changes.

= 1.4.0 =
* Variation swatches: turn WooCommerce variation attribute dropdowns into colour, image or button swatches on variable products, with a new **Variation Swatches** admin screen to assign a colour or image to each attribute term. The native `<select>` stays as the source of truth, so price, stock and gallery image update through WooCommerce's own logic; unavailable combinations are dimmed and keyboard selection works. Works with global attributes (`pa_*`) that have terms; if a dedicated swatches plugin like Woo Variation Swatches is active, Flexa Extra defers to it automatically.
* Swatch display options: show the selected value as an "Attribute: Value" line, render attributes with no swatch type as buttons, and show a loading spinner while the variation updates.
* Swatch appearance: custom width, height and button font size, plus selected-ring and unavailable-strike colours and a choice of registered image size for image swatches, on top of the existing size, shape and tooltip options.
* Swatch availability: choose how unavailable or out-of-stock swatches behave (dim and strike, hide, or show as normal), and optionally clear the choice by clicking the selected swatch again.
* Limit visible swatches: cap how many swatches show per attribute and fold the rest behind a "+N more" toggle (the selected swatch always stays visible).
* Stock per swatch: show "N left" on low-stock swatches and "Out of stock" when every matching variation is sold out, read from WooCommerce's own variation data.
* Shop and category swatches: optionally render swatches under products in the loop, each linking to the product with that option preselected.
* Flexa hub: the Flexa menu landing page lists only the modules that ship in this plugin for now (Product Options and Variation Swatches). The wider flexatech ecosystem is kept hidden (behind a filter) until more modules are ready; when shown, not-installed modules are dimmed so the active ones stand out.

= 1.3.0 =
* Import from more plugins: the Import screen now also reads option sets from **WooCommerce Product Add-Ons**, **Acowebs "Custom Product Addons for WooCommerce"**, and **YITH WooCommerce Product Add-Ons** (free), alongside the existing YayExtra and ThemeHigh sources. Field types, per-option prices (fixed, percentage, and increase/decrease), swatches and product/category assignment are mapped where an equivalent exists; anything that cannot be mapped 1:1 is listed per set. As before, imported sets are added switched off for you to review before turning them on.

= 1.2.1 =
* Import screen: only lists plugins that actually have option sets to import (present on the site, with at least one set), instead of always showing every supported plugin with an empty row.

= 1.2.0 =
* Formula prices: a price can be a safe arithmetic formula (on field prices, per-option prices and fee/discount rules) using `base`, `qty` and other fields' values, the operators `+ - * / ( )`, and `round()`, `min()`, `max()`. The result is the per-unit surcharge, so `qty` is for volume tiers like `max(2, 10 - qty)` rather than a plain per-unit charge. Evaluated with a hand-written parser (never `eval`); a bad formula is worth 0 and never errors. The builder validates as you type.
* Custom controls: checkboxes and radios now use clean custom-styled controls (with a subtle checked animation) instead of the raw browser widget, so they look consistent across browsers and themes. The colour field shows a swatch with its hex value and opens the colour picker on click. Selected values, validation, keyboard focus and form behaviour are unchanged, and the styling is scoped so it will not clash with your theme.
* Date picker: the date field now opens a lightweight, localized calendar (month names, weekday order and first day of week follow your site language) instead of the raw browser control. Works with keyboard and touch, and the calendar styling is scoped so it will not clash with your theme.
* Date field options: set an earliest and latest selectable date and a list of blocked dates. These are validated again on the server at add-to-cart. The stored value format (YYYY-MM-DD) is unchanged.
* Date display format: chosen dates now show in your site's date format (Settings, General) on the product page and in the cart/order, with an optional per-field override. Previously the cart showed the raw YYYY-MM-DD value.
* Per-field styling hooks: every field wrapper now carries `flexa-extra-field--<type>` and `flexa-extra-field--id-<field_id>` classes so you can target a field type or one specific field from CSS.
* New "CSS class" box in the field Inspector to add your own class(es) to a field wrapper.

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

= 1.4.3 =
Adds a Swatch Analytics tab showing which variation attribute values sell, read from your existing orders. No breaking changes.

= 1.4.2 =
Cosmetic update: the Variation Swatches settings screen now uses the same tabbed layout as Product Options. No functional changes.

= 1.4.1 =
Packaging-only update: the download no longer bundles the admin build source (now on GitHub), making the zip smaller. No functional changes.

= 1.4.0 =
Adds variation swatches for variable products (colour/image/button) with a new admin screen to assign colours and images to attribute terms. No breaking changes.

= 1.3.0 =
Adds three more import sources: WooCommerce Product Add-Ons, Acowebs "Custom Product Addons", and YITH WooCommerce Product Add-Ons (free). No breaking changes.

= 1.2.1 =
The Import screen now lists only the plugins that actually have option sets to import, instead of every supported plugin. No breaking changes.

= 1.2.0 =
Adds formula prices (safe arithmetic over base, quantity and other fields), custom-styled checkbox/radio/colour controls, a localized calendar for the date field (with earliest/latest and blocked dates), and per-field CSS class hooks. No breaking changes; stored values are unchanged.

= 1.1.1 =
Fixes the YayExtra importer, which previously brought nothing over and ignored product assignment. Recommended if you import from YayExtra.

= 1.1.0 =
Big feature release: import from YayExtra/ThemeHigh, analytics, headless read API, a Gutenberg block, live builder preview, conditional fees/discounts, per-option stock, and more. No breaking changes.

= 1.0.1 =
Hardens swatch image output against CSS injection. Recommended update.

= 1.0.0 =
Initial release.
