# Changelog

All notable changes to Flexa Extra are documented here. This project adheres to
[Semantic Versioning](https://semver.org/).

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
