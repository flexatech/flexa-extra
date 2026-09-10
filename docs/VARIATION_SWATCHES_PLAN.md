# Flexa Extra — Roadmap "Variation Swatches" (bản đầy đủ, độc lập)

> Trạng thái: **Pha 0–5 CODE XONG** (v1.4.0). Còn: test:integration cần WP test DB; wp-plugin-review + verify thủ công storefront do user chạy. Lập: 2026-09-10.
> Mục tiêu: color/image/button swatch cho biến thể WooCommerce, **UI riêng trong
> flexa-extra** để gán màu/ảnh cho term + settings size/shape/tooltip, **không phụ thuộc
> plugin nào khác** (chạy standalone).
> Tham chiếu tái hiện: `woo-variation-swatches` v2.4.0. Quy ước: skill
> **flexa-plugin-conventions** (PHP/TS) + **flexa-plugin-ui** (admin).

---

## 0. Phạm vi & nguyên tắc

**Có gì trong bản đầy đủ:**
- 3 kiểu swatch: **color**, **image**, **button**.
- **UI riêng** trong admin app: chọn kiểu swatch cho từng global attribute (`pa_*`) + gán
  màu/ảnh cho từng term. Không cần cài plugin ngoài.
- **Settings size/shape/tooltip** riêng cho variation swatches (kế thừa Style tab đã có).
- Overlay không xâm lấn: giấu `<select>` biến thể gốc, vẽ swatch, click → set select ẩn →
  WooCommerce lo giá/tồn/ảnh. **Không đụng Cart/Pricing engine của flexa-extra.**

**Nguyên tắc (bám IMPLEMENTATION_PLAN.md):**
1. **Data model trước UI.** Chốt option store + term meta + schema (PHP + zod) rồi mới dựng UI.
2. **Một nguồn sự thật** cho map `attribute → swatch_type` (dùng chung admin/REST/render).
3. **Không sửa bảng core WC** (`woocommerce_attribute_taxonomies`) → gỡ sạch, không xung đột.
   Kiểu swatch lưu ở option riêng; màu/ảnh lưu ở **term meta** (key tương thích woo-variation-swatches).
4. **Standalone nhưng vẫn nhường:** nếu `woo-variation-swatches` active thì tự tắt (Pha 5).
5. **Escaping/nonce/cap** đầy đủ; a11y `role=radiogroup/radio`.

**Không đụng tới:** `CartHandler`, `PriceCalculator`, `SelectionProcessor`, order meta,
option-set builder. Đây là module song song.

---

## 1. Cách woo-variation-swatches hoạt động (để tái hiện)

- **Data:** term meta `product_attribute_color` (hex), `product_attribute_image` (attachment ID).
  Kiểu swatch cả thuộc tính ở cột `attribute_type` (`select|color|image|button|radio`).
- **Render:** filter `woocommerce_dropdown_variation_attribute_options_html` (prio 20, 2 args);
  giấu select gốc (`display:none`) + xuất `<ul>` swatch.
- **JS:** gắn `.variations_form`; click `<li>` → `select.val(v).trigger('change')`; nghe
  `woocommerce_variation_has_changed` đồng bộ selected/disabled; select ẩn là nguồn sự thật.

Ta tái hiện đúng mô hình này trong namespace `Flexa\Extra\Variations`.

---

## 2. Kiến trúc tổng thể

```
includes/Variations/
  AttributeType.php     # map pa_xxx => off|color|image|button  (option flexa_extra_variation_swatches)
  TermMeta.php          # đọc/ghi màu (hex) + ảnh (attachment id) cho term; key tương thích WVS
  SwatchRenderer.php    # hook dropdown filter, render <ul>, guard defer, enqueue có điều kiện
  Settings.php          # đọc size/shape/tooltip từ flexa_extra_settings (style.vswatch*)
includes/Controllers/
  VariationSwatchesRestController.php   # GET/POST attributes + term meta
assets/frontend/
  flexa-extra-variations.js
  flexa-extra-variations.css
apps/admin/src/pages/variation-swatches/   # trang React mới
```

Boot: `includes/Initialize.php` thêm:
```php
\Flexa\Extra\Variations\SwatchRenderer::get_instance();
```
`SwatchRenderer::__construct()` kiểm tra guard **trước** khi add_filter/enqueue (Pha 5) →
nếu phải nhường thì không hook gì, gần như zero-cost.

---

## Pha 0 — Data model & foundation

Mục tiêu: chốt nơi lưu, đọc-ghi thuần, test được DB-less phần convert/sanitize.

- [ ] **Option store** `flexa_extra_variation_swatches` = mảng `[ taxonomy => 'color'|'image'|'button' ]`.
      `AttributeType`: `get_type(string $taxonomy): string` (default `'off'`), `set_type()`,
      `all(): array`. Sanitize enum chặt.
- [ ] **Term meta layer** `TermMeta`:
  - `get_color(int $term_id): string` — đọc `product_attribute_color`, `sanitize_hex_color`,
    fallback `''`.
  - `get_image_id(int $term_id): int` / `get_image_url(int $term_id, string $size): string`.
  - `set_color()`, `set_image_id()`. **Dùng đúng key `product_attribute_color/_image`** để
    tương thích ngược woo-variation-swatches (chuyển đổi 2 chiều mượt).
- [ ] **Settings schema mở rộng** — thêm vào `style` (PHP `Helpers\Helper` + zod
      `apps/admin/src/lib/schema/settings.ts`):
  - `vswatchSize: 'sm'|'md'|'lg'` (default `md`)
  - `vswatchShape: 'circle'|'rounded'|'square'` (default `circle`)
  - `vswatchTooltip: boolean` (default `true`)
      (tách riêng khỏi `swatchSize/swatchShape` của option-set để 2 hệ độc lập; hoặc tái dùng
      nếu muốn — quyết định lúc code, mặc định tách để rõ ràng.)
- [ ] Unit (DB-less nếu tách được thuần): `AttributeType` sanitize enum; `TermMeta` sanitize hex.

**Ra khỏi Pha 0:** có tầng dữ liệu đọc-ghi, chưa render, chưa UI.

---

## Pha 1 — REST API

Mục tiêu: admin đọc/ghi được cấu hình.

- [x] `Controllers/VariationSwatchesRestController` (kế thừa `BaseRestController`):
  - `GET  /variation-swatches/attributes` — list global attribute (`wc_get_attribute_taxonomies()`),
    mỗi item: `{ taxonomy, label, type, terms: [{ id, name, color, image: {id,url} }] }`.
  - `POST /variation-swatches/attribute` — body `{ taxonomy, type }` → ghi option.
  - `POST /variation-swatches/term` — body `{ term_id, color?, image_id? }` → ghi term meta.
- [x] Đăng ký qua action `flexa_extra/rest/register_routes` (đã có trong `Engine\RestAPI`).
- [x] `permission_callback`: `current_user_can('manage_woocommerce')` + nonce như controller khác.
- [x] Integration test: round-trip GET→POST→GET.


## Pha 2 — Admin UI (React)

Mục tiêu: trang cấu hình riêng, không đụng option-set builder.

- [x] **Route** `apps/admin/src/router.tsx`: thêm `{ path: 'variation-swatches', element: <VariationSwatchesPage/> }`
      vào `baseRoutes` (cạnh `analytics`/`import`).
- [x] **Nav item** `apps/admin/src/components/layout/Header.tsx`: thêm link "Variation swatches".
- [x] **Trang** `pages/variation-swatches/`:
  - List attribute → mỗi attribute có select kiểu swatch (`off|color|image|button`).
  - Khi `color`: bảng term + `ColorField` (đã có) per-term.
  - Khi `image`: bảng term + media picker (`wp.media`) per-term, preview thumbnail.
  - Khi `button`: chỉ dùng label term, không cần gán gì thêm.
  - Lưu qua TanStack Query mutations → REST Pha 1; toast `sonner` như trang khác.
- [x] **Settings**: thêm control size/shape/tooltip vào `pages/settings/tabs/StyleTab.tsx`
      (section "Variation swatches"), bind field `style.vswatch*`.
- [x] Nếu module bị guard tắt (Pha 5): trang hiện **notice** "woo-variation-swatches đang bật…".
- [x] `pnpm build` cập nhật bundle admin (bắt buộc sau khi sửa `apps/admin`).


## Pha 3 — Frontend render (PHP)

Mục tiêu: swatch hiện trên trang variable product, click đổi được biến thể.

- [x] `SwatchRenderer::render_dropdown(string $html, array $args): string` hook
      `woocommerce_dropdown_variation_attribute_options_html` (prio 20, 2 args):
  1. `$type = AttributeType::get_type($args['attribute'])`; `off` → trả `$html`.
  2. Giữ nguyên `$html` select gốc, thêm class `flexa-extra-raw-select` + `style=display:none`
     (regex/`str_replace` tối thiểu, không dựng lại select để giữ đúng option/value của WC).
  3. Xuất `<ul class="flexa-extra-vswatch flexa-extra-vswatch--{type} flexa-extra-vswatch--{shape}"
     role="radiogroup" data-attribute="{taxonomy}">`, mỗi term `<li data-value data-title role="radio">`:
     - **color:** `<span class="...__chip" style="background-color:{hex}">`
     - **image:** `<img class="...__chip" src="{url}" width height alt>` (fallback ảnh variation nếu term không có ảnh)
     - **button:** `<span class="...__label">{term name}</span>`
  4. Inline CSS custom props `--fxe-vswatch-size/-radius` từ settings (như ProductRenderer làm với option swatch).
  5. Escaping: `esc_attr/esc_url/sanitize_hex_color`; tôn trọng `general.enabled`.
- [x] **Enqueue có điều kiện**: chỉ trên single variable product + module không bị guard; đăng ký
      handle mới trong `Register/ScriptName.php` + `RegisterFacade.php`.
- [x] Filter mở rộng (ghi vào `docs/HOOKS.md`):
  - `flexa_extra/variation_swatches/enabled` (bool)
  - `flexa_extra/variation_swatches/item_html` (string, $term, $type)
- [x] Integration test: variable product thật → filter trả markup swatch đúng số term.


## Pha 4 — Frontend JS + CSS

Mục tiêu: đồng bộ 2 chiều với variation form, trạng thái selected/disabled, tooltip.

- [x] `assets/frontend/flexa-extra-variations.js` (JS thuần — storefront không React):
  1. Mỗi `.variations_form`: nối `.flexa-extra-vswatch` với `select.flexa-extra-raw-select` cùng attribute.
  2. Click / Space / Enter trên `<li>` (không `is-disabled`) → set `select.value` +
     `dispatchEvent(new Event('change',{bubbles:true}))`.
  3. Nghe `woocommerce_variation_has_changed` / `check_variations` → cập nhật `is-selected`
     và `is-disabled` **đọc lại từ `select.options[i].disabled`** (để WC là nguồn sự thật, giảm lệch logic).
  4. `reset_data` / reset form → clear `is-selected`.
  5. Tooltip (nếu `style.vswatchTooltip`): `data-title` + CSS `::after`, không lib.
- [x] `assets/frontend/flexa-extra-variations.css`: chip color/image/button, size/shape qua
      custom props, trạng thái `is-selected`/`is-disabled`, tooltip, `prefers-reduced-motion`,
      responsive. Bám token/độ scoping của `flexa-extra.css`.
- [ ] Test thủ công (skill **verify**): chọn swatch → giá/ảnh/tồn của WC đổi đúng; combo không
      khả dụng bị mờ; reset hoạt động; bàn phím OK.

---

## Pha 5 — Guard nhường + i18n + docs + test + release

- [x] **Guard defer** (đã chốt) trong `SwatchRenderer::__construct()`:
```php
private function should_defer(): bool {
    $active = function_exists('woo_variation_swatches')
        || defined('WOO_VARIATION_SWATCHES_PLUGIN_VERSION')
        || class_exists('Woo_Variation_Swatches');
    return (bool) apply_filters('flexa_extra/variation_swatches/defer_to_wvs', $active);
}
```
      `true` → không add_filter, không enqueue; admin hiện notice. Vì term meta dùng chung key,
      tắt plugin kia đi là flexa-extra render tiếp được ngay.
- [x] **i18n**: mọi chuỗi admin + notice qua `__()`; cập nhật `.pot` (admin từ built bundle). → 483 msgid.
- [x] **Docs**: `docs/HOOKS.md` (hooks mới); `readme.txt` (mô tả + giới hạn: chỉ global
      attribute `pa_*` có term); `CHANGELOG.md`; bump version.
- [ ] **Tests**:
  - Unit: `AttributeType` enum, `TermMeta` sanitize hex.
  - Integration: render trên variable product; guard defer khi giả lập WVS active.
  - vitest: nếu tách logic combo sang module JS test được.
- [ ] **Chất lượng**: phpstan L6 sạch (`composer analyse`); `composer test` + `test:integration`
      xanh; skill **wp-plugin-review** trước release.

---

## 3. Rủi ro & giới hạn (ghi rõ trong readme)

1. **Bám markup/JS variation form của WC** — đọc lại trạng thái từ `select` thay vì tự tính
   để giảm vỡ khi WC đổi.
2. **Chỉ hỗ trợ global attribute `pa_*` có term.** Custom product-level attribute không có
   term → không có chỗ gán màu/ảnh (giống woo-variation-swatches). Nêu rõ giới hạn.
3. **Theme tự vẽ variation UI** có thể xung đột → cần test vài theme phổ biến.
4. **HPOS**: không liên quan (không đọc/ghi order meta).

---

## 4. Thứ tự phụ thuộc & ước lượng

```
Pha 0 (data) → Pha 1 (REST) → Pha 2 (admin UI)
                    └────────→ Pha 3 (render PHP) → Pha 4 (JS/CSS)
                                         Pha 5 (guard/i18n/docs/test) — xuyên suốt, chốt cuối
```

| Pha | Nội dung | Ước lượng |
|---|---|---|
| 0 | Data model + settings schema | ~0.5 ngày |
| 1 | REST controller | ~0.5 ngày |
| 2 | Admin React + build | ~1 ngày |
| 3 | Render PHP overlay | ~0.5 ngày |
| 4 | JS/CSS đồng bộ variation form | ~0.75 ngày (dễ vỡ nhất) |
| 5 | Guard + i18n + docs + test | ~0.75 ngày |

**Tổng ~3.5–4 ngày.** Có thể ship dần: Pha 0-1-3-4 cho ra bản dùng được (đọc term meta có
sẵn), Pha 2 (UI gán màu/ảnh) và Pha 5 (đánh bóng) theo sau.

---

## 5. Checklist file (khi được duyệt code)

- [ ] `includes/Variations/AttributeType.php`
- [ ] `includes/Variations/TermMeta.php`
- [ ] `includes/Variations/SwatchRenderer.php`
- [ ] `includes/Variations/Settings.php` (hoặc gộp vào Helper)
- [ ] `includes/Controllers/VariationSwatchesRestController.php` + đăng ký route
- [ ] Boot trong `includes/Initialize.php`
- [ ] `includes/Register/ScriptName.php` + `RegisterFacade.php` (handle mới, enqueue có điều kiện)
- [ ] `assets/frontend/flexa-extra-variations.{js,css}`
- [ ] `apps/admin/src/router.tsx` + `components/layout/Header.tsx` + `pages/variation-swatches/*`
- [ ] `apps/admin/src/lib/schema/settings.ts` + `pages/settings/tabs/StyleTab.tsx` (size/shape/tooltip)
- [ ] Helper PHP settings schema/defaults/sanitizer (style.vswatch*)
- [ ] `docs/HOOKS.md`, `readme.txt`, `CHANGELOG.md`, `.pot`, version bump
- [ ] Tests unit + integration; phpstan L6; wp-plugin-review
