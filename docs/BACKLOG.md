# Backlog

Work that is planned but not built yet. The authoritative phase plan lives in
`docs/IMPLEMENTATION_PLAN.md`; this file is the short, actionable to-do list
pulled from the open (`[ ]`) items there, so future sessions can pick up without
re-reading the whole plan.

Ground rules for every item (unchanged): PHPStan level 6 clean
(`composer analyse`), unit + integration tests, regenerate
`languages/flexa-extra.pot`, keep dev/prod parity, rebuild the admin bundle
(`pnpm build`) after touching `apps/admin`, and add field types only in
`includes/Fields/FieldType.php` (single source). Any pricing/logic change must
land in three places in lockstep: the PHP engine, the storefront
`assets/frontend/flexa-extra.js`, and the builder preview
`apps/admin/src/lib/preview/engine.ts` (now covered by
`engine.test.ts`, so a drift shows up as a red test).

## Phase 9: Advanced pricing engine

- **Dynamic per-unit** (`price × quantity`): multiply an option's price by the
  product quantity or by a number field's value. Touches
  `SelectionProcessor::process` (already has `$base`, add `$qty`) plus the JS mirror.
- **Character-count pricing**: charge by the length of a text/textarea value
  (engraving, custom labels).
- **Formula pricing, safe (differentiator vs Pro)**: a whitelisted expression
  evaluator over `+ - * / ( )` with variables `qty`, `base`, `{field_id}`. No
  `eval`: shunting-yard or AST per principle #4. New PHP evaluator + JS mirror +
  thorough unit tests, with a builder input and validation.
- Open the `priceSchema` enum (`dynamic`, `char_count`, `formula`) and keep zod,
  `OptionSetSchema`, and `price_amount` in sync.

## Phase 10: More field types + file upload

- **Light input types (high ROI)**: `email`, `url`, `tel`, `password`, `hidden`,
  `range`/slider, `time_picker`, `datetime`. Mostly native inputs plus validation,
  reusing the existing pipeline. Add in `Fields/FieldType.php` then flow through
  `registry.ts`, `FieldRenderer.php`, and the sanitizer.
- **Multi-select dropdown**: extend the existing `dropdown` with the `maxSelect`
  bound already in place.
- **File upload, done safely (decision 2026-09-08: bring it back)**: server-side
  upload with a nonce, a MIME allowlist, a size cap, stored as an attachment (or a
  directory outside the web root); the value flows into cart/order meta as a URL or
  attachment id. Needs its own security review and integration test. Single largest
  parity gap versus Pro.

## Phase 11: Logic / targeting + layout

- **More logic operators**: `contains`, `greater`, `less`. Extend `rulePasses`,
  the server mirror, and the zod enum together.
- **More assignment conditions**: user role, cart quantity, product variation
  (`Frontend/OptionSetResolver::condition_matches`).
- **Section layout**: `tabs` and `accordion` (a set-level setting that switches the
  container class plus a JS toggle), building on the existing Style tab.
- **Confirm / custom validators**: field-level regex already exists; add a
  "confirm field" pattern.

## Deferred from earlier phases

- **In-tab field position and template variants** (Phase 5): render inside a
  product tab. The catch is that inputs must sit inside `form.cart` to post, so this
  needs a template-aware placement rather than a plain content hook.
- **Domain value objects** `Domain\OptionSet` / `Field` / `FieldOption` (Phase 1):
  build them if a renderer or pricing path grows complex enough to want typed
  objects over arrays.

## Migration follow-ups

The importer (`includes/Migration/`) maps fields, prices, swatches and product
assignment. Not yet mapped, reported as warnings for now:

- **Field-level conditional logic** from YayExtra (references its own option-id
  graph) and ThemeHigh. Would need an id-remap pass during convert().
- **YayExtra show/hide "actions"** and price sub-actions. Some could become Flexa
  fees/discounts, but the model differs; needs a careful mapping.
- **ThemeHigh Pro pricing** (the free version stores no per-option price).
- More sources behind the `flexa_extra/migration/sources` filter (e.g. YITH,
  WooCommerce Product Add-ons) if there is demand.

## Housekeeping (optional)

- The admin bundle is a single ~605 kB chunk (Vite warns over 500 kB). Code-split
  with dynamic imports or `manualChunks` if load time becomes a concern.
