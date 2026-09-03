# Changelog

All notable changes to Flexa Extra are documented here. This project adheres to
[Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added
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
  screen creates a ready-made draft you can edit before publishing. Six starters
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
- **Duplicate option set**: one-click server-side copy (created as a draft).
- **Import / Export option sets**: export a single set or all sets to a portable
  JSON file, and import them back (accepts the export envelope, a single set, or a
  bare list). Import re-creates fresh sets through the schema sanitizer.

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
