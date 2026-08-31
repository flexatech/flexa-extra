---
title: Cart & checkout display
slug: cart-checkout-display
order: 90
category: Flexa Extra
---

# Cart & checkout display

Once a shopper adds a personalized product to the cart, their selections travel
with the item all the way to the order. This article describes what appears
where, and how swatches and surcharges are presented.

## On the product page

As the shopper fills fields and picks options:

- Fields render **before or after** the Add to Cart button (per your
  [Display settings](./07-settings.md)).
- Choice fields show their controls (checkboxes, radios, dropdown, swatches, or
  buttons) styled by your [Style settings](./07-settings.md).
- The **Extra subtotal** and **Total price** readouts update live.
- Required fields and validation (email/URL/regex/number range) are enforced
  before the item can be added.

## In the cart and checkout

Each personalized line item lists its selections as **line-item meta** beneath
the product name — one row per field, showing the field label, the chosen
value, and any surcharge. For example:

```
Demo Smart Watch
  Engraving:  Happy Birthday   +$5.00
  Colour:     ● Navy Blue      +$15.00
```

Details:

- **Swatch selections show a colour dot or image thumbnail**, followed by the
  option's label. Hover the chip to see its exact colour value.
- **Surcharges appear as a subtle pill** (e.g. `+$15.00`) next to the value.
- If a swatch option has **no label**, the chip still shows its colour and the
  value falls back to the colour code — another reason to always label options
  (see [Field types](./03-field-types.md)).

![Personalized selections in the cart](./images/cart-line-item.png)
*Screenshot: a cart line item with its selections beneath the product name — a colour dot + label, and a surcharge pill (e.g. +$15.00).*

This works in **both** the classic (shortcode) cart/checkout and the newer
**block-based** Cart and Checkout. The styling is applied automatically on those
pages; no template editing is required.

### Mini-cart

If **Show value in mini-cart** is enabled (General settings), the selected
values are included in the mini-cart summary as well.

## On the order

When the order is placed, each selection is saved to the order line item, so it
appears on:

- the **order-received / thank-you** page,
- the customer's **order emails**,
- the **My Account → Orders** detail view, and
- the **admin order** screen.

A hidden, machine-readable copy of the raw selections is also stored on the line
item for integrations that need structured data.

![Selections on the order details / email](./images/order-selections.png)
*Screenshot: the order-received or order-email view showing the personalized selections and their surcharges under the line item.*

> In order emails, colour dots may render from their inline colour, but the
> readable label/value is always present — so even in a plain-text-ish email the
> selection is clear.

## Why the numbers always match

Because Flexa Extra recomputes surcharges server-side (see
[Pricing](./04-pricing.md)), the amounts shown in the cart, at checkout, and on
the order are guaranteed to reflect your current field definitions — not
whatever the browser last displayed. If you adjust a price after an item is in a
cart, the cart updates on its next totals calculation.
