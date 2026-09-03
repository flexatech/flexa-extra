# Flexa Extra — Roadmap hoàn thiện plugin

> Kế hoạch phân pha, **core-first**, để đưa `flexa-extra` từ *foundation + admin shell*
> (đã xong) thành một plugin Extra Product Options hoàn chỉnh cho WooCommerce,
> ngang tính năng YayExtra Lite. Plugin là bản free hoàn chỉnh, không có tầng Pro/license.
>
> Tham chiếu phân tích nghiệp vụ gốc: `../yayextra/PHAN-TICH-CHUC-NANG.md`.
> Quy ước code: skill **flexa-plugin-conventions** (PHP/TS) + **flexa-plugin-ui** (admin).

---

## 0. Trạng thái hiện tại (đã hoàn thành — 2026-08-18)

- [x] Bootstrap `flexa-extra.php`: hằng số `FLEXA_EXTRA_*`, autoloader, guard WooCommerce.
- [x] `Initialize` boot các engine singleton; `SingletonTrait`, `I18n`.
- [x] Register layer (ScriptName / RegisterFacade / RegisterProd / RegisterDev) — nạp module JS.
- [x] `Engine\ActDeact`: activation, WC notice, khai báo HPOS, seed `flexa_extra_settings`.
- [x] `Engine\RestAPI`: đăng ký `SettingsRestController` + `OptionSetsRestController`.
- [x] `Engine\Admin\Settings`: menu + mount `#flexa-extra-admin-root`, localize `window.flexaExtra`.
- [x] `Engine\Admin\CustomPostType`: CPT ẩn `flexa_extra_option_set`.
- [x] `Helpers\Helper`: JS config + schema/defaults/sanitizer settings.
- [x] Controllers: `BaseRestController`, `SettingsRestController` (GET/POST merge partial),
      `OptionSetsRestController` (CRUD — hiện lưu meta `_flexa_extra_options/_products/_status`).
- [x] Admin app: react-router hash + react-hook-form + zod + TanStack Query + framer-motion +
      sonner + ky; **Settings tabs dọc** (General / Display / Advanced).
- [x] Trang **Option Sets** = placeholder (chưa có builder).

**Kết luận:** phần khung + settings đã chạy. Từ đây là *nghiệp vụ lõi*: field types →
builder → render frontend → tính giá → cart/order.

---

## Nguyên tắc xuyên suốt

1. **Data model trước UI.** Chốt schema field/option-set (PHP + zod) rồi mới dựng builder.
2. **Một nguồn sự thật cho field types** — mảng khai báo dùng chung cho: builder (admin),
   validate (REST), render (frontend). Không hardcode rải rác.
3. **Bảo mật là hạng mục, không phải afterthought**: nonce, sanitize theo schema, validate
   server-side lại toàn bộ giá tiền (không tin client), chống SQLi/file-upload.
4. **Không `eval`** cho formula pricing — parser sandbox (shunting-yard / AST an toàn).
5. Mỗi pha kết thúc phải `php -l` sạch, phpstan L6 sạch, `pnpm build` sạch.

---

## Pha 1 — Data model & Field Registry  *(nền cho tất cả)* — ✅ DONE (2026-08-18)

**Mục tiêu:** định nghĩa "một option set là gì", "một field là gì", validate được cả 2 phía.

- [x] `includes/Fields/FieldType.php` — registry các loại field (tất cả đều free):
      `text` (text/email/url/regex), `textarea`, `number`, `date_picker`, `color_picker`,
      `checkbox`, `radio`, `dropdown`, `swatch`, `button`, `heading` (display-only).
      Kèm `catalog()` cho palette builder + filters `flexa_extra/field/types|catalog`.
      (`file_upload`/`image_upload` đã bị loại bỏ hoàn toàn — tránh mặt bảo mật upload.)
- [x] Thuộc tính field: `label`, `name`, `required`, `placeholder`, `default`, `tooltip`,
      `min/max/step`, `price` (none | fixed | percent) trên field & trên từng choice, `logic` (điều kiện hiển thị).
- [x] `includes/Fields/OptionSetSchema.php` — sanitizer đầy đủ: fields + choices + price + logic + targeting
      (all/manual/conditions). Filter `flexa_extra/option_set/sanitize`.
- [x] TS mirror: `src/lib/schema/option-set.ts` (zod) + `src/lib/fields/registry.ts` (factory defaults) +
      cập nhật `types/localize.d.ts` (`field_catalog`).
- [x] `OptionSetsRestController` validate qua `OptionSetSchema::sanitize` (meta `_flexa_extra_fields/_targeting/_status`),
      fire `flexa_extra/option_set/saved`.
- [x] `Helper::get_js_config` expose `field_catalog`.
- [ ] *(Hoãn sang Pha 3)* Value object `Domain\OptionSet/Field/FieldOption` — dựng khi renderer cần đọc,
      tránh trừu tượng sớm; hiện schema-array đã đủ cho builder.

**Xong khi:** ✅ POST option-set JSON hợp lệ → lưu; field type lạ → bị loại; giá/logic/targeting chuẩn hóa.
Build + tsc + `php -l` đều sạch.

---

## Pha 2 — Option Set Builder (admin)  *(deliverable UI lớn)* — ✅ DONE (2026-08-19)

**Mục tiêu:** biến trang Option Sets placeholder thành trình dựng field trực quan.

- [x] Danh sách Option Sets: bảng (tên, số field, chế độ gán, trạng thái) + tạo/xóa/nhân bản
      (`OptionSets.tsx`, `lib/api/option-sets.ts`, `lib/queries/option-sets.ts`).
- [x] `src/pages/option-sets/builder/` — layout 3 cột: `FieldPalette` (trái) + `FieldCanvas` (giữa) +
      `Inspector` (phải). RHF + `FormProvider` quản toàn bộ option set; `zodResolver(optionSetSchema)`.
- [x] **@dnd-kit** kéo-thả sắp xếp field (`FieldCanvas` SortableContext, `keyName:'_rhfId'` tránh đè `id`).
- [x] Inspector theo từng loại field: label/key/required/placeholder/tooltip, text-format+regex,
      number min/max/step, `PriceEditor` (none/fixed/percent) trên field & từng choice, `ChoiceEditor`
      (nested useFieldArray, swatch color), `LogicEditor` (show/hide, any/all, rules tham chiếu field khác).
- [x] Tab **Product assignment** (`AssignmentPanel`): 3 chế độ (all / manual / conditions);
      điều kiện category/tag/product/price/stock, match any/all.
- [x] REST search sản phẩm/term: `ResourcesRestController` (`/search`, `/resolve`) +
      `ResourcePicker` (async debounce + chips) — cho manual & conditions.
- [x] Query keys `["option-sets"]` / `["option-set", id]`; mutation invalidate cả list & item.
- [ ] *(Hoãn sang Pha 3)* Live preview khối field trong builder — làm chung engine render frontend
      để tái dùng cùng một bộ render, tránh dựng preview hai lần.

**Xong khi:** ✅ tạo option set nhiều field, kéo-thả, đặt giá/logic, gán sản phẩm, lưu & load lại đúng.
Build + tsc + `php -l` đều sạch.

---

## Pha 3 — Frontend Render Engine  *(field lên trang sản phẩm)* — ✅ DONE (2026-08-19)

**Mục tiêu:** hiển thị field trên product page theo settings (position, labels).

**Chốt kiến trúc (đầu pha):** render markup bằng **PHP server-side** (SEO + fallback no-JS +
markup là *hợp đồng input* cho Pha 4), interactivity bằng **asset vanilla JS/CSS thuần** trong
`assets/frontend/` (enqueue trực tiếp, KHÔNG qua Vite/React — tránh nhồi React/framer-motion vào
mọi trang sản phẩm và tránh `cssCodeSplit:false` trộn Tailwind admin sang store). Định nghĩa
giá/logic đi kèm dưới dạng **JSON island** trong container để JS tính live từ dữ liệu server cấp.

- [x] `includes/Frontend/OptionSetResolver.php` — với 1 sản phẩm, tìm option set áp dụng
      (published + active + targeting all/manual/conditions, khớp category/tag/product/price/stock) +
      cache theo request. Filter `flexa_extra/resolver/applicable_sets`.
- [x] `includes/Frontend/FieldRenderer.php` — render từng field per-type (heading/text/textarea/number/
      checkbox/radio/dropdown/swatch/button), escape đầy đủ. **Hợp đồng input: `flexa_extra[<field_id>]`**
      (`[]` cho multi). Price hint server-side (no-JS).
- [x] `includes/Frontend/ProductRenderer.php` — hook `woocommerce_before/after_add_to_cart_button`
      theo `settings.display.position`; container + JSON island + khối totals; enqueue asset có điều kiện
      (`is_product()` hoặc `advanced.loadScriptsAllPages`), localize `window.flexaExtraFront`.
- [x] Khối **"Extra subtotal"** + **"Total price"** dùng label từ settings; ẩn khi = 0 nếu `hideZeroSubtotal`.
- [x] Asset frontend riêng `assets/frontend/flexa-extra.{js,css}` (vanilla, không dependency) —
      tính lại giá live client-side (fixed + % giá sản phẩm), format tiền theo currency settings.
- [x] Conditional logic chạy ở client (ẩn/hiện field realtime; field ẩn bị `disabled` để không post/không tính giá).
- [x] `ScriptName` + `RegisterFacade` đăng ký handle frontend;
      `Initialize` boot `ProductRenderer`.
- [ ] *(Hoãn sang Pha 5)* Live preview trong builder — renderer là PHP nên không tái dùng trực tiếp trong
      builder React; gom vào pha UX/Style.

**Xong khi:** ✅ field hiển thị đúng vị trí, đổi lựa chọn → subtotal/total cập nhật tức thì; `php -l` sạch.

---

## Pha 4 — Pricing & Cart Engine  *(tính tiền — nhạy cảm nhất)* — ✅ DONE (2026-08-19)

**Mục tiêu:** cộng phụ phí vào giá, đưa vào cart/checkout/order, **an toàn server-side**.

**Chốt kiến trúc:** một engine **`Cart/SelectionProcessor`** là nguồn sự thật server-side duy nhất —
đọc raw `flexa_extra[<field_id>]`, mirror conditional logic (field ẩn không validate/không tính giá),
validate, và **tính lại phụ phí từ định nghĩa field** (không bao giờ tin giá client). Validator +
CartHandler + PriceCalculator + order meta đều đi qua đây. Cart item chỉ lưu `selections` (đã sanitize)
+ `base` (giá tại thời điểm add, dùng cho %); giá + dòng hiển thị luôn **recompute**.

- [x] `includes/Cart/SelectionProcessor.php` — `process(product, raw, base?)` → `{selections, lines, total, errors}`.
      Validate required, email/url/regex (regex user-supplied bọc `@preg_match`, pattern lỗi → non-blocking),
      number min/max. Giá: fixed + % của base; choice cộng theo từng option chọn.
- [x] `includes/Cart/Input.php` — đọc `$_POST['flexa_extra']` (`wp_unslash`, sanitize per-field ở downstream).
- [x] `includes/Frontend/Validator.php` — `woocommerce_add_to_cart_validation`; lỗi → `wc_add_notice` + chặn add.
- [x] `includes/Cart/CartHandler.php` — `woocommerce_add_cart_item_data` (lưu selections + base + hash để tách/gộp dòng),
      `woocommerce_get_cart_item_from_session`, `woocommerce_get_item_data` (hiển thị lựa chọn + phụ phí ở cart/checkout),
      `woocommerce_checkout_create_order_line_item` (order item meta hiển thị + `_flexa_extra` ẩn cho tích hợp).
      Variable product: fields resolve theo parent, `base` = giá variation.
- [x] `includes/Cart/PriceCalculator.php` — `woocommerce_before_calculate_totals`; set giá **tuyệt đối** `base + extra`
      (không cộng dồn qua nhiều lượt), filter `flexa_extra/cart/item_extra`. Hỗ trợ fixed + % giá.
- [x] Lưu lựa chọn vào order item meta; hiển thị ở order/email/admin order (meta_data readable + hidden `_flexa_extra`).
- [x] Kiểm tra tồn kho theo option value — **2026-09-03**. `stock:?int` mỗi choice (null=vô hạn);
      `Cart\StockManager` chặn oversell khi add-to-cart (tính cả cart reservations), giảm ở
      `woocommerce_reduce_order_stock` + hoàn ở `woocommerce_restore_order_stock` (guard meta
      `_flexa_extra_stock_reduced`), counter nằm trong post meta `_flexa_extra_fields`. FieldRenderer
      disable option hết hàng. *(Edit-in-cart vẫn hoãn.)*
- [x] Hook mở rộng: `flexa_extra/cart/item_extra` (+ đã có `flexa_extra/resolver/applicable_sets`).

**Xong khi:** ✅ thêm vào giỏ có phụ phí đúng, giá không thể chỉnh từ client (server recompute), order lưu đủ; `php -l` sạch.

> **MVP bán hàng đạt được** (hết Pha 4): tạo option set → gán sản phẩm → khách chọn → phụ phí vào giỏ/checkout/order an toàn.

---

## Pha 5 — Trải nghiệm & Style hiển thị — ✅ DONE (2026-08-19)

**Chốt kiến trúc:** style hiển thị lái bằng **CSS custom properties** phát trên container
`.flexa-extra-fields` (không sinh stylesheet per-request); CSS tĩnh trong `assets/frontend/` đọc var
với fallback nên vẫn chạy standalone. Class modifier (`--swatch-{sm|md|lg}`, `--shape-{...}`,
`--no-tooltips`) mang các lựa chọn không phải màu.

- [x] Settings mở rộng nhóm `style`: swatch (`swatchSize` sm/md/lg, `swatchShape` circle/rounded/square),
      `showTooltips`, button colors (`buttonBg`/`buttonText`/`buttonActiveBg`/`buttonActiveText`).
      Sanitizer: enum clamp + `sanitize_hex_color` (rỗng/không hợp lệ → `''` = kế thừa màu theme).
      Mirror React: zod schema + `DEFAULT_SETTINGS` + tab **Style** mới (`StyleTab` + `ColorField` reset-về-default).
- [x] `ProductRenderer` phát CSS var + class modifier trên container; CSS đọc `var(--fxe-swatch-size/…)`.
- [x] **a11y:** choice group (radio/checkbox/swatch/button) render `<fieldset>`+`<legend>` (thay `<label for>`
      cho nhóm nhiều input); `aria-required` trên input/select/fieldset; tooltip `role=note`+`tabindex=0`+`aria-label`;
      `:focus-within` ring bàn phím trên các surface lựa chọn.
- [x] **Loading/reveal:** JS thêm class `is-ready` sau khi tính visibility/totals lần đầu → fade-in nhẹ,
      không nháy field bị logic ẩn. `prefers-reduced-motion` tắt animation/transition.
- [x] **Responsive** frontend: media query gom swatch/button, button full-width ở màn hẹp.
- [x] Test: unit `SettingsStyleTest` (enum clamp, hex, defaults, missing-group) → **38 tests**;
      integration thêm fieldset/a11y + style-var contract → **17 tests**.
- [ ] *(Hoãn)* Vị trí "trong tab" & template variant: input **phải nằm trong `form.cart`** mới post được
      (hook ngoài form như `before_add_to_cart_form` làm mất input). Tab cần JS di chuyển field khi submit →
      để Pha 6 cùng live-preview builder.
- [ ] *(Hoãn)* Live preview trong builder React (renderer là PHP) — gộp Pha 6.

---

## Pha 6 — Nâng cao (khác biệt hóa, vẫn free)

Ưu tiên theo 5 đề xuất trong bản phân tích yayextra:

- [ ] **Formula pricing**: field công thức tham chiếu field số khác (`{width}*{height}*unit`),
      parser sandbox không `eval`.
- [x] **Template library** — 2026-09-03. Nút "Start from a template" ở màn Option Sets mở modal
      (`PresetPicker`) chọn 1 trong 6 preset dựng sẵn (gift wrapping, engraving, size & colour,
      installation service, warranty, product add-ons). Preset định nghĩa client-side
      (`apps/admin/src/lib/fields/presets.ts`) dựng qua chính các factory `createField/createChoice`
      nên mỗi lần tạo có ID mới + shape hợp lệ; chọn xong POST qua `createOptionSet` (server vẫn
      `sanitize`) rồi điều hướng vào builder. Preset chỉ là option set nháp bình thường, không khoá gì.
- [x] **Itemized price breakdown** — 2026-09-03. Trang sản phẩm liệt kê từng option đã chọn
      (và fee/discount cấp set) kèm giá riêng, cập nhật live phía trên "extra subtotal". Bật/tắt qua
      setting `general.showPriceBreakdown` (mặc định bật). Server chỉ thêm khung `[data-role="breakdown"]`
      rỗng trong `render_totals`; `flexa-extra.js` `recalculate()` dựng mảng `lines[]` (label option/field
      + amount, mirror `SelectionProcessor` line) rồi render bằng `textContent` (an toàn XSS), subtotal =
      tổng lines. Cart/checkout/order vốn đã itemize sẵn (`CartHandler::format_line_value`). Không đổi REST.
- [x] **Onboarding / quick-start guide (nhẹ)** — 2026-09-03. Kế thừa quy ước `flexa-seo-aeo`
      nhưng lược nhẹ: option `flexa_extra_onboarding` (`Support\OnboardingState`: schema `status`
      pending|in_progress|completed|dismissed + timestamp server-stamp), REST GET/POST `/onboarding`
      (`OnboardingRestController`, `manage_options`), `Engine\Admin\ActivationRedirect` (transient
      một lần khi activate → redirect vào trang plugin; fallback notice + dismiss ở Plugins screen),
      state localize sẵn trong `get_js_config`. UI: `WelcomeOverlay` (khi `pending`) foreground
      template gallery, tái dùng `PresetPicker` + builder sẵn có (không dựng wizard song song);
      `QuickStartBanner` (khi `in_progress`) nhắc bước tiếp + "Done"→completed; nút "Replay setup
      guide" trong AdvancedTab. Skippable, không chặn luồng thường, không hiện lại khi đã xong.
      Unit +6 (`OnboardingStateTest`, tổng 63 xanh), phpstan sạch.
- [ ] **REST API công khai + cache** cho headless / tích hợp.
- [ ] **Live preview nâng cao + tính giá server-side realtime** (AJAX/REST) chống lệch giá.
- [ ] **Analytics add-on**: thống kê option nào bán chạy, doanh thu phụ phí.
- [x] ~~date picker, color picker~~ → đã làm thành field free (Pha 1 mở rộng, 2026-09-03).
- [x] ~~Import/Export option set (JSON)~~ + **Duplicate** option set (server-side, tạo bản nháp) — 2026-09-03.
      REST: `POST /option-sets/{id}/duplicate`, `POST /option-sets/import`; export là client-side tải JSON
      (envelope `{plugin,type,version,items}`). Import nhận envelope / 1 set / list; đều qua `OptionSetSchema::sanitize`.
- [x] **Conditional fee/discount (action) cấp option-set** — 2026-09-03. Mảng `actions[]` (meta
      `_flexa_extra_actions`): mỗi action `{kind: fee|discount, price: fixed|percent, match, rules[]}`,
      rule tái dùng operator của conditional logic (rules rỗng = luôn áp dụng). `SelectionProcessor`
      cộng/trừ vào total + tạo line `type:action`; `PriceCalculator` kẹp giá ≥ 0. Tab "Fees & discounts"
      trong builder (ActionsPanel). JS storefront tính realtime qua island `sets[].actions`.
- [x] **Edit options in cart** — 2026-09-03. `Cart\EditContext` (view detect từ `?flexa_edit=<key>` vào
      cart của chính shopper, không cần nonce; đọc selections để prefill). `FieldRenderer::render($field,
      $product, $selected)` prefill server-side (input + choice checked/selected, override default; `null` =
      không edit). `ProductRenderer` chèn hidden `flexa_edit` + `wp_nonce_field` trong form. `CartHandler`:
      link "Edit options" ở `woocommerce_cart_item_name` (classic cart), prefill quantity
      (`woocommerce_quantity_input_args`), đổi nút thành "Update cart", và **replace** trên
      `woocommerce_add_to_cart` (verify nonce): key khác → `remove_cart_item(old)`; key trùng (WC gộp) →
      `set_quantity` về đúng số lượng edit. `EditFlowTest` (4 test: prefill, replace, giữ 1 dòng khi trùng,
      bỏ qua khi thiếu nonce). Chưa hỗ trợ block cart + variation attribute prefill.
- [x] **Min/max số lựa chọn (multi-select)** — 2026-09-03. Field choice `multiple` mang `minSelect`/
      `maxSelect` (nullable int trong `_flexa_extra_fields`; `OptionSetSchema::sanitize_count`).
      `SelectionProcessor::validate_field` chặn khi count < min hoặc > max (chỉ khi đã chọn ≥1 hoặc field
      required — optional rỗng bỏ qua), dùng `_n`. `FieldRenderer::select_hint` hiện "Choose N to M
      options". `flexa-extra.js` disable checkbox chưa chọn khi chạm max. Admin: Inspector 2 ô Min/Max
      choices (hiện khi checkbox hoặc multiple=true), zod `minSelect/maxSelect`. `SelectFlowTest` (max
      chặn, min chặn, hint render) + unit `SelectionProcessorTest` (4) + `OptionSetSchemaTest`. **Xong
      toàn bộ parity YayExtra Lite.**

---

## Pha 7 — Chất lượng & phát hành — ✅ DONE (2026-08-19)

- [x] **Test tự động (làm sớm để dễ bảo trì):** hai tầng, xem `tests/README.md`.
      - **Unit (DB-less, WP/WC stubs, PHPUnit 11):** `FieldType`, `OptionSetSchema` (sanitizer),
        `SelectionProcessor` (pricing/validate/logic — gồm test "client không sửa được giá"),
        `OptionSetResolver` (targeting), `SettingsStyleTest`. `composer test` → **38 tests / 172 assertions**.
      - **Integration (WP + WooCommerce thật + test DB, PHPUnit 9 phar):** add-to-cart phí/validate/
        chống tamper, hiển thị cart + order meta, REST CRUD (routing/permission/sanitize), render field
        lên product page, a11y fieldset + style CSS vars. `composer test:integration` → **17 tests / 42 assertions**.
        Bootstrap tự cài WC tables; `bin/install-wp-tests.sh` (guard chống trỏ vào DB `local`).
      - **🐞 Bug production do integration test phát hiện & đã sửa:** CPT key `flexa_extra_option_set`
        dài 22 ký tự > giới hạn 20 của WordPress → `register_post_type` trả `WP_Error`, CPT **không bao giờ
        đăng ký** (option set không áp lên storefront). Đổi thành `flexa_extra_optset` (18). Đây là lý do
        mạnh để có integration test.
- [x] i18n: mọi chuỗi PHP bọc `flexa-extra` (41 call), admin React 192 `__()` literal; sinh
      `languages/flexa-extra.pot` = **215 chuỗi** (PHP + admin JS gộp từ bundle build vì make-pot không parse TS —
      quy trình ở `languages/README.md`). Script translations route qua MO file (RegisterFacade), không cần make-json.
- [x] Bảo mật review: server-authoritative (SelectionProcessor recompute, không tin giá client); mọi REST route
      có `permission_callback` = `manage_options`; escape/sanitize đầy đủ (OptionSetSchema: id→alnum, color→hex,
      image→esc_url_raw); không SQL thô/eval/superglobal (trừ `Input` unslash + sanitize downstream, documented);
      hardening: JSON island `wp_json_encode(... JSON_HEX_TAG|JSON_HEX_AMP)`. Không file upload / outbound HTTP.
- [x] **phpstan L6 toàn repo sạch** (`composer analyse`; `phpstan.neon` + `phpstan/constants.php` stub +
      WP/WC stubs). Sửa gốc: `SingletonTrait` typed, 14 class `final` (khử `new.static`), thêm return/param types,
      `fields` annotation → `list<mixed>` (giữ guard defensive có nghĩa), `set_price((string))`.
- [x] Compat: HPOS đã khai báo (`before_woocommerce_init` → `FeaturesUtil::declare_compatibility`);
      classic cart/checkout qua hook WC chuẩn.
- [x] Chạy skill **wp-plugin-review**: 21/21 check sạch phần security; chỉ 2 mục WP.org nhẹ đã vá
      (readme "Source code" section cho bundle minify; `.distignore` loại dev/analysis files gồm `phpstan/`).
- [x] `readme.txt` (WP.org), `docs/HOOKS.md` (11 hook: 3 action + 8 filter), `CHANGELOG.md`.

**Còn lại trước khi tag public:** phpcs reconciliation 1 lượt (short array, slash hook — giữ style scaffold);
kiểm thử block cart + theme phổ biến bằng tay. Pha 6 (nâng cao) chưa làm.

---

## Known toolchain debt (đừng học lại mỗi lần)

- phpcs có nợ scaffold-wide (doc comment, `@package`) — **match style scaffold**, dồn 1 lượt trước release.
- Hook dùng **dấu gạch chéo** (`flexa_extra/...`) là cố ý; đừng "sửa" thành underscore.
- `get_json_params()` cast `(array)` (WP stub trả `array`, guard `is_array` là dead code).
- `settings` save **partial merge over stored**; gửi cả blob sẽ đè mất field khác.
- ~~Chưa có `composer.json`~~ → **đã có** (`composer.json` + PSR-4 `Flexa\Extra\`→`includes/`, require-dev PHPUnit). Còn thiếu `phpstan.neon` (bật L6 CI ở release-gate).
- **Test:** 2 tầng — unit DB-less PHPUnit 11 (`composer test`) + integration WP+WC thật PHPUnit 9 phar (`composer test:integration`). **Hai bản PHPUnit là cố ý**: WP core test lib còn dùng API PHPUnit 9 (`parseTestMethodAnnotations`, bỏ ở v10+); unit giữ v11 sạch, integration chạy phar v9 tách process. Phar git-ignored (tải theo README). Test DB riêng `flexa_extra_test` (Local socket symlink `/tmp/flexa-mysql.sock`); KHÔNG đụng DB `local`.

---

## Bảng theo dõi tiến độ

| Pha | Hạng mục | Trạng thái |
|-----|----------|-----------|
| 0 | Foundation + admin shell | ✅ Done |
| 1 | Data model & Field Registry | ✅ Done |
| 2 | Option Set Builder (admin) | ✅ Done |
| 3 | Frontend Render Engine | ✅ Done |
| 4 | Pricing & Cart Engine | ✅ Done |
| 5 | UX & Style hiển thị | ✅ Done |
| 6 | Nâng cao (vẫn free) | ⬜ |
| 7 | Chất lượng & phát hành | ✅ Done |

**Đường tới hạng "dùng được thật" (MVP bán hàng):** hết **Pha 4**.
Pha 1→4 là core bắt buộc; Pha 5→7 làm plugin bóng bẩy & sẵn sàng release.
