---
title: Pricing
slug: pricing
order: 50
category: Flexa Extra
---

# Pricing

Flexa Extra can add a surcharge for the choices a shopper makes. This article
explains where prices live, the two price types, and how the total is
calculated and enforced.

## Where a price can live

There are two places to attach a price:

- **On an input field** (Text, Paragraph, Number) — a single price charged when
  the shopper provides a value. For example, *"Add gift wrapping"* as a text
  note that costs +$3.00 whenever it is filled in.
- **On an option inside a choice field** (Checkboxes, Radio, Dropdown, Swatch,
  Buttons) — each option carries its own price. For example, a *Size* dropdown
  where *Large* adds +$5.00 and *X-Large* adds +$8.00.

Display fields (Heading/description) never carry a price.

## The two price types

For each price you choose a **type**:

| Type | Meaning | Example |
|------|---------|---------|
| **None** | No surcharge. | Free option. |
| **Fixed** | A flat amount added. | `+ $5.00` |
| **Percent** | A percentage of the product price. | `+ 10%` of a $80 product = `+ $8.00` |

**Percentage** prices are calculated against the product's own price (the
variation price for a variable product). This keeps upsells proportional across
products of different values.

![The price editor in the Inspector](./images/pricing-editor.png)
*Screenshot: the Inspector's price editor for an option, showing the price-type selector (None / Fixed / Percent) and the amount field.*

## How the total is built

On the product page, as the shopper selects options and fills fields, Flexa
Extra adds up every applicable surcharge and shows:

- an **Extra subtotal** — the sum of all option/field surcharges, and
- a **Total price** — the product price plus the extra subtotal.

Both readouts are optional and their labels are configurable — see
[Settings](./07-settings.md).

For choice fields:

- **Single-select** (radio, dropdown, single swatch/buttons) adds the price of
  the one chosen option.
- **Multi-select** (checkboxes, multi swatch/buttons) adds the price of **each**
  chosen option.

Only **visible** fields count. If a field is hidden by
[conditional logic](./06-conditional-logic.md), its price is not applied.

## The price is enforced on the server

The live total on the product page is a convenience for the shopper — it is not
what determines the charge. When the item is added to the cart, and again every
time WooCommerce recalculates cart totals, Flexa Extra **recomputes the extra
price from scratch** using your saved field and option definitions:

1. It reads the (sanitized) selections stored on the cart item.
2. It re-resolves which fields apply and which are visible.
3. It re-adds every surcharge from the current definitions.
4. It sets the cart line price accordingly.

Consequences worth knowing:

- **Tamper-proof.** A modified price posted from the browser is ignored; only
  your server-side definitions matter.
- **Live to your edits.** If you change an option's price or disable a set,
  existing carts pick up the new result on their next totals pass.
- **Consistent everywhere.** The same calculation feeds the cart, the order,
  and any integrations reading the order.

## Worked example

A $80.00 product has an Option Set with:

- a *Colour* swatch where **Navy Blue** adds a **fixed** +$15.00, and
- an *Engraving* text field that adds a **fixed** +$5.00 when filled.

A shopper picks Navy Blue and types an engraving message:

```
Product price      $80.00
Colour: Navy Blue  +$15.00
Engraving          + $5.00
──────────────────────────
Extra subtotal      $20.00
Total price        $100.00
```

![The live Extra subtotal and Total price on the product page](./images/pricing-readout.png)
*Screenshot: the product page readout showing "Extra subtotal: $20.00" and "Total price: $100.00" after selections are made.*

The cart line shows $100.00, and the *Colour* / *Engraving* selections appear
as line-item meta with their surcharges. See
[Cart & checkout display](./08-cart-and-checkout-display.md).
