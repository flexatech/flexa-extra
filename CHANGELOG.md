# Changelog

All notable changes to Flexa Extra are documented here. This project adheres to
[Semantic Versioning](https://semver.org/).

## [1.3.0] - 2026-09-10

### Added
- **Two more import sources.** The Import screen now recognizes **WooCommerce
  Product Add-Ons** (per-product `_product_addons` meta and `global_product_addon`
  groups) and the free **YITH WooCommerce Product Add-Ons & Extra Options** (its
  `{prefix}yith_wapo_blocks` / `{prefix}yith_wapo_addons` tables). Each becomes an
  option set, added switched off, routed through the same schema sanitizer as the
  other sources.
  - WooCommerce Product Add-Ons: `multiple_choice` maps to dropdown / radio /
    swatch by its display; `custom_text`/`custom_textarea` to text/textarea;
    `checkbox` to multi-select; `custom_price`/`input_multiplier` to a number
    field (with a note); `file_upload` is skipped. Per-option and field prices map
    by `price_type` (`percentage_based` → percentage, `flat_fee`/`quantity_based`
    → per-unit fixed, with a note that a one-time flat fee becomes per-unit).
    Image options resolve their attachment to a URL. Global groups keep their
    "all products" or category scope.
  - YITH WAPO (free): text, textarea, number, select, radio, checkbox, colour
    (→ swatch), colour picker, date, and label/HTML headings map across; file and
    product add-ons are skipped. The column-oriented option arrays are transposed
    into per-option rows, and per-option pricing (`increase` / `decrease` /
    `percentage` / `multiplied`) is honoured. Block product/category rules become
    Flexa Extra targeting.
  - Both are registered through the existing `flexa_extra/migration/sources`
    filter, so third-party sources plug in the same way. Converters are pure
    array-to-array maps with unit tests
    (`tests/Unit/WooProductAddonsSourceTest.php`, `YithWapoSourceTest.php`).

## [1.2.1] - 2026-09-10

### Changed
- **The Import screen only lists plugins that have something to import.** A source
  (YayExtra, ThemeHigh "Extra Product Options") is shown only when its plugin is
  present on this site (active or with its data left behind) and holds at least one
  option set, instead of always listing every supported plugin with an empty "No
  data found" row. A plugin that is installed but empty no longer appears, and an
  empty state explains when nothing importable is present.

## [1.2.0] - 2026-09-09

### Added
- **Formula prices.** A price can now be a safe arithmetic formula instead of a
  fixed amount or percentage, on field prices, per-option prices and
  fee/discount actions. Formulas use `base` (the item price), `qty` (the line
  quantity) and `{field_id}` (another field's numeric value), the operators
  `+ - * / ( )`, and the functions `round()`, `min()` and `max()`. The result is
  the per-unit surcharge, so `base * qty` is not needed for a plain per-unit
  charge (WooCommerce multiplies by quantity), but `qty` lets you write volume
  tiers such as `max(2, 10 - qty)`. Evaluation is a hand-written parser (never
  `eval`); a malformed formula is worth 0 and can never raise an error or a bogus
  charge. The builder validates the formula as you type, and the storefront and
  live preview mirror the server engine byte-for-behaviour
  (`includes/Pricing/FormulaEvaluator.php`).
- **Custom-styled checkbox, radio and colour controls on the storefront.**
  Checkboxes and radios now render as clean custom controls (square tick / round
  dot) with a subtle checked animation, instead of the browser's default widget,
  so they look the same across Chrome, Firefox, Safari and mobile. The colour
  field shows a themeable swatch with its hex value and opens the OS colour
  picker on click. The native inputs are kept (visually hidden for choices, an
  invisible overlay for colour), so values, validation, keyboard focus and form
  semantics are unchanged; all styling is scoped to `.flexa-extra-*` and needs no
  new dependency.
- **Localized calendar for the date field.** The server-rendered
  `<input type="date">` is progressively enhanced into a scoped, dependency-free
  calendar UI (vanilla JS in `assets/frontend/flexa-extra.js`). Month/weekday
  names and the first day of week come from `Intl.DateTimeFormat` using the site
  locale (`determine_locale()`, passed as BCP-47). Keyboard + touch accessible;
  CSS is fully namespaced. The native input stays the canonical control, so the
  submitted/stored value format (`YYYY-MM-DD`) is unchanged and it still works
  with JS disabled.
- **Date field constraints.** New `minDate`, `maxDate` and `disabledDates`
  options on the date field (builder Inspector), validated again server-side in
  `Cart\SelectionProcessor` at add-to-cart.
- **Date display format.** Selected dates render in the site date format
  (`get_option('date_format')`) with an optional per-field `dateFormat` override,
  applied consistently on the product page (calendar label) and in the cart/order
  line. `FieldRenderer::resolve_date_format()` / `format_date()` are the single
  source of truth (mirrored in JS by a small PHP-date-token formatter); the cart
  previously showed the raw ISO value. Stored value stays `YYYY-MM-DD`.
- **Per-field styling hooks.** Every field wrapper now carries
  `flexa-extra-field--<type>` and `flexa-extra-field--id-<field_id>` classes (in
  addition to the base `flexa-extra-field` and the `data-field-id` /
  `data-field-type` attributes), so a theme can target a field type or one
  specific field from CSS.
- **"CSS class" field in the builder Inspector.** Add your own space-separated
  class(es) to a field wrapper. Each token is sanitized with
  `sanitize_html_class()` on save. Mirrored in the live preview and documented in
  `docs/HOOKS.md`.

## [1.1.1] - 2026-09-08

### Fixed
- **YayExtra import brought nothing over.** The converter read each field's type
  from a `fieldType` key, but YayExtra stores it in `type` as a `{ value, label }`
  pair. Every field came back typed as nothing, was skipped, and each set was
  then dropped as having no importable fields. Field types, names (`nameOnCart`)
  and the `swatches` type are now read from the shape YayExtra actually writes,
  with the older flat `fieldType` still accepted.
- **Conditional product assignment is migrated** instead of falling back to "all
  products". YayExtra matches categories and tags by term name, so the names are
  resolved to term IDs while reading, and each "is one of" list is expanded into
  one targeting rule per term. Rules that cannot be mapped (product-name
  conditions, terms missing from the site) are reported per set as before.
- **Button fields no longer import as colour swatches.** YayExtra seeds every
  option with a default swatch colour even when the field is a plain button, and
  only the swatches field types ever render one; colour and image are now carried
  only for swatches, and per option according to its `swatchesType`.

## [1.1.0] - 2026-09-08

### Added
- **Import from other plugins**: a new Import screen brings option sets over from
  YayExtra and from ThemeHigh "Extra Product Options" (free). Each source is
  detected automatically with a count of what it would import. Everything is
  added switched off (published but inactive, like a duplicated set) and routed
  through the same schema sanitizer the builder uses, so it shows in the Option
  Sets list for review but nothing goes live until you turn it on. Field types,
  per-option prices,
  swatches and product assignment map across where they have an equivalent;
  anything that does not (file uploads, cross-plugin conditional logic) is
  reported per set so you know what to re-create by hand.
- **Headless read API**: two open, read-only REST routes for decoupled storefronts.
  `GET /flexa-extra/v1/public/config` returns the presentation config (currency,
  labels, style, i18n); `GET /flexa-extra/v1/public/product/{id}` returns the option
  sets for a product with their fields, prices, and logic, plus the same config, so a
  front end can render the configurator and compute the live subtotal in one request.
  It exposes only what the on-page JSON island already ships, respects the plugin's
  enabled toggle, and can be closed with the `flexa_extra/public_api/enabled` filter.
  Add-to-cart and the authoritative price recompute stay server-side.
- **Sales analytics**: an Analytics screen ranks which options and choices shoppers
  pick and the add-on revenue they bring, filterable by date range and order status.
  It reads a structured per-option report written to each order at checkout, is
  HPOS-safe, and counts only orders placed after the feature shipped.
- **Gutenberg configurator block**: place a "Flexa Extra Configurator" block on any
  page or post to show an option set with live pricing (for landing or "build your
  own" pages). It is display-only and does not add to the cart, since cart submission
  needs the product form.
- **Live preview in the builder**: a Preview panel renders your option set exactly as
  the storefront will, updating as you edit fields, prices, and conditions, so you can
  check show/hide logic and the running subtotal without leaving the editor.
- **First-run quick-start guide**: on activation you land on a short welcome that
  points at the template gallery, so the fastest path to a first option set is
  the default one. It reuses the normal builder (no separate wizard), is
  skippable at every step, and never shows again once finished or dismissed. A
  "Replay setup guide" control under Advanced settings brings it back on demand.
- **Itemized price breakdown**: the product page can list each selected option
  (and any conditional fee or discount) with its own price, updating live as the
  shopper picks, above the extra subtotal. Toggle it under General settings. The
  cart, checkout, and order line already itemize the same charges.
- **Template library**: a "Start from a template" picker on the Option Sets
  screen creates a ready-made set, switched off, that you can edit before turning
  it on. Six starters
  ship with the plugin: gift wrapping & message, engraving/personalization, size
  & colour, installation service, warranty/protection plan, and product add-ons.
  Each one is a normal option set, so nothing is locked once created.
- **Min / max choices per field**: a multi-select field (checkboxes, or a
  dropdown/swatch/button set to allow multiple) can require a minimum and cap a
  maximum number of picks. The product page shows a "choose N to M" hint and
  disables further checkboxes once the cap is hit; the bound is enforced again on
  the server at add-to-cart.
- **Edit options in cart**: an "Edit options" link on each cart line reopens the
  product page with the saved selections pre-filled. Submitting replaces that
  line instead of adding a second one; the quantity carries over. The replace is
  nonce-guarded and, as always, the price is recomputed on the server.
- **Conditional fees & discounts**: add set-level rules that apply a fixed or
  percentage fee (or discount) to the item when the shopper's selections match
  your conditions (or always, when a rule has no conditions). Recomputed
  server-side on add-to-cart; a discount never drops the line below zero. Managed
  from a new "Fees & discounts" tab in the builder.
- **Per-option stock**: give any choice option a limited quantity. Sold-out
  options render disabled on the product page, add-to-cart is blocked when a
  selection would oversell (counting what the cart already holds), and a paid
  order decrements the counter (restored on cancel/refund). An empty stock field
  means unlimited.
- **Date picker and colour picker** field types (free): native date/colour inputs
  with an optional default value, flowing through cart and order like any input.
- **Duplicate option set**: one-click server-side copy, created switched off.
- **Import / Export option sets**: export a single set or all sets to a portable
  JSON file, and import them back (accepts the export envelope, a single set, or a
  bare list). Import re-creates fresh sets through the schema sanitizer.

### Fixed
- Admin dropdowns no longer crowd their text against the arrow: selects now draw a
  consistent chevron with room reserved for it, at every width.

### Developer
- Migration sources are pluggable: add your own with the
  `flexa_extra/migration/sources` filter, and hook each imported set with
  `flexa_extra/migration/imported`. Converters are pure array-to-array maps with
  unit tests (`tests/Unit/YayExtraSourceTest.php`, `ThemeHighSourceTest.php`).
- Added a Vitest unit suite for the builder preview engine
  (`apps/admin/src/lib/preview/engine.ts`), pinning the pricing and conditional-logic
  behaviour it mirrors from the storefront. Run with `pnpm test` in `apps/admin`.
- Roadmap for unbuilt work moved to `docs/BACKLOG.md`.

## [1.0.0] - 2026-08-19

Initial release.

### Added
- **Option Set builder** (admin React app): drag-and-drop fields, per-type
  inspector, price editor (fixed/percent), conditional logic, and product
  assignment (all / manual / category·tag·product·price·stock conditions).
- **Field types** (free): text, textarea, number (min/max/step + email/URL/regex
  validation), checkbox, radio, dropdown, colour/image swatch, button, heading.
- **Storefront render engine**: server-rendered fields on the product page with a
  JSON island + vanilla JS for live subtotal/total, conditional show/hide, and a
  no-JS price fallback.
- **Pricing & cart engine**: authoritative server-side recompute of every
  surcharge (`Cart\SelectionProcessor`); selections stored on the cart item and
  persisted to order line-item meta; identical selections stack, distinct ones
  split into separate lines. Client-submitted prices are never trusted.
- **UX & style settings**: swatch size/shape, tooltip toggle, button colours via
  scoped CSS custom properties; accessible fieldset/legend groups, `aria-required`,
  keyboard focus, responsive layout, and reduced-motion support.
- **Developer hooks**: see `docs/HOOKS.md`.
- **Internationalization**: all strings translatable; `languages/flexa-extra.pot`
  provided (PHP + admin app).
- **Quality**: two-tier automated tests (DB-less unit + WP/WooCommerce integration)
  and a clean PHPStan level-6 pass (`composer analyse`).

### Security
- Option-set writes routed through a single schema sanitizer; REST routes all carry
  `manage_options` permission callbacks; hex colours and URLs sanitized; the JSON
  price island is emitted with `JSON_HEX_TAG | JSON_HEX_AMP`.

### Compatibility
- Declares WooCommerce High-Performance Order Storage (HPOS) compatibility.
