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

- [x] **Formula pricing, safe (differentiator vs Pro)** — shipped in 1.2.0. A
  hand-written recursive-descent evaluator (`includes/Pricing/FormulaEvaluator.php`,
  no `eval`) over `+ - * / ( )` with `round/min/max` and variables `base`, `qty`,
  `{field_id}`. `priceSchema` gained the `formula` type (+ `formula` string) in
  lockstep across zod, `OptionSetSchema`, `SelectionProcessor::price_amount`, the
  storefront `evalFormula`, and the preview `engine.ts`. `qty` is threaded from
  `PriceCalculator` / `CartHandler`; the result is the per-unit surcharge. This
  also covers **dynamic per-unit** (`base * qty` style) — `qty` is now a variable.
- **Character-count pricing**: charge by the length of a text/textarea value
  (engraving, custom labels). Not yet done — would add a `length({field})`
  function to the evaluator and expose text length in the field context.

## Phase 10: More field types + file upload

- **Light input types (high ROI)**: `email`, `url`, `tel`, `password`, `hidden`,
  `range`/slider, `time_picker`, `datetime`. Mostly native inputs plus validation,
  reusing the existing pipeline. Add in `Fields/FieldType.php` then flow through
  `registry.ts`, `FieldRenderer.php`, and the sanitizer. `time_picker` / `datetime`
  also close an Acowebs WCPA gap: it has dedicated `time` and `datetime-local`
  fields that Flexa currently maps to `text` / `date_picker` with a warning.
- **Multi-select dropdown**: extend the existing `dropdown` with the `maxSelect`
  bound already in place.
- **File upload, done safely (decision 2026-09-08: bring it back)**: server-side
  upload with a nonce, a MIME allowlist, a size cap, stored as an attachment (or a
  directory outside the web root); the value flows into cart/order meta as a URL or
  attachment id. Needs its own security review and integration test. Single largest
  parity gap versus Pro, and versus Acowebs WCPA free (which ships file/image upload
  in the free tier); `AcowebsWcpaSource` skips `file` fields on import today.

## Phase 11: Logic / targeting + layout

- **More logic operators**: `contains`, `greater`, `less`. Extend `rulePasses`,
  the server mirror, and the zod enum together.
- **More assignment conditions**: user role, cart quantity, product variation
  (`Frontend/OptionSetResolver::condition_matches`).
- **Section layout**: `tabs` and `accordion` (a set-level setting that switches the
  container class plus a JS toggle), building on the existing Style tab.
- **Multi-column / grid layout**: place several fields on one row with per-field
  column widths (e.g. first name + last name side by side). Flexa renders a single
  vertical column today; the builder would need a row/column model plus a
  responsive grid in `FieldRenderer.php`, the storefront CSS, and the preview.
  Acowebs WCPA stores fields as rows of column objects, so its forms currently
  flatten to one column on import (`Migration\AcowebsWcpaSource::convert`).
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
- [x] More sources behind the `flexa_extra/migration/sources` filter: **WooCommerce
  Product Add-Ons**, **Acowebs "Custom Product Addons" (WCPA)**, and **YITH
  WooCommerce Product Add-Ons** (free) shipped in 1.3.0. Acowebs was built and
  verified against a real installed form; WooCommerce Product Add-Ons and YITH have
  their `read_raw()` written against each plugin's documented storage format with a
  unit-tested `convert()`. The YITH reader targets the current custom tables
  (`{prefix}yith_wapo_blocks`/`_addons`); if a very different version is found,
  `is_available()` fails safe (the source just does not appear).
- Further sources (e.g. WPC Product Add-ons, Advanced Product Fields) if there is
  demand, added through the same filter.

## Housekeeping (optional)

- The admin bundle is a single ~605 kB chunk (Vite warns over 500 kB). Code-split
  with dynamic imports or `manualChunks` if load time becomes a concern.
