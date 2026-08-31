---
title: FAQ & troubleshooting
slug: faq-troubleshooting
order: 100
category: Flexa Extra
---

# FAQ & troubleshooting

## Frequently asked questions

**Does Flexa Extra require WooCommerce?**
Yes. It stays dormant (with an admin notice) until WooCommerce is active, then
resumes automatically.

**Can a customer tamper with the surcharge?**
No. The extra price is recomputed on the server from your stored option
definitions when the item is added to the cart and on every cart-totals
recalculation. Posted values are sanitized against the field schema and the
client price is never trusted.

**Is it compatible with High-Performance Order Storage (HPOS)?**
Yes. The plugin declares HPOS compatibility; you can leave HPOS enabled.

**Does it work with the block Cart and Checkout?**
Yes. Selections and surcharges display in both the classic and block-based
Cart/Checkout with no template changes.

**Can one product have more than one Option Set?**
Yes. A product shows the fields from every enabled set that targets it, in
sequence. Build small, focused sets and layer them.

**What is the difference between a fixed and a percentage price?**
Fixed adds a flat amount (`+$5.00`). Percentage adds a share of the product
price (`+10%` of an $80 product = `+$8.00`). See [Pricing](./04-pricing.md).

**Can developers extend it?**
Yes — the plugin exposes actions and filters for field types, pricing, settings,
and REST. See the developer reference (`docs/HOOKS.md`) shipped with the plugin.

---

## Troubleshooting

### Fields don't appear on the product page

Check, in order:

1. **Master switch** — *Settings → General → Enabled* is on.
2. **Set status** — the Option Set is **enabled** (not disabled) in the Option
   Sets list.
3. **Assignment** — the set actually targets this product. Re-check the
   Assignment panel: *All products*, the manual list contains it, or its
   conditions match. See [Product assignment](./05-product-assignment.md).
4. **Position** — *Settings → Display → Position*. The fields may be rendering
   on the other side of the Add to Cart button than you expected.
5. **Product type / template** — a heavily customized product template may not
   fire the standard WooCommerce hooks the fields attach to.

### The price on the page looks wrong

- Remember the page total is a live preview; the **authoritative** amount is the
  one computed on add-to-cart. If the cart differs from the page, the cart is
  correct by design.
- Check the field/option **price type** — a value entered as *fixed* behaves
  very differently from *percent*.
- Confirm the field is **visible**. A field hidden by conditional logic adds no
  price. See [Conditional logic](./06-conditional-logic.md).

### A swatch shows a colour code instead of a name

The option has **no label**, so the cart falls back to its colour value (e.g.
`#1242AF`). Open the set, select the swatch field, and add a **label** to each
option (e.g. *Navy Blue*). The cart then shows a colour dot **and** the name.

### Styles look off in the cart

The plugin's storefront stylesheet is loaded on the product page and on the
cart/checkout automatically. If a custom template renders option fields on an
unusual page and they appear unstyled, enable *Settings → Advanced → Load
scripts on all pages* as a fallback.

### Validation blocks checkout

If a shopper cannot add to cart, a field is failing validation:

- A **required** field is empty.
- A **Text** field with an *email* / *URL* / *regex* format has an invalid
  value.
- A **Number** field is outside its **min**/**max**.

The message identifies which field. Adjust the value — or relax the field's
constraints in the builder if they are stricter than intended.

### Changes aren't showing on an existing cart

Cart line prices refresh on the next totals calculation. Updating the cart
quantity, revisiting the cart, or proceeding to checkout triggers a recompute,
after which your edited prices/definitions take effect.

---

## Getting more help

If something here does not resolve your issue, gather:

- WordPress, WooCommerce, and PHP versions,
- whether you use classic or block Cart/Checkout,
- the Option Set configuration (field types, prices, assignment), and
- steps to reproduce,

and share them with support so the behaviour can be traced quickly.
