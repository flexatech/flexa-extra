---
title: Flexa Extra — User Guide
slug: flexa-extra-user-guide
order: 0
category: Flexa Extra
---

# Flexa Extra — User Guide

Flexa Extra adds customizable **extra product options** to WooCommerce
products: text and number inputs, checkboxes, radios, dropdowns, colour/image
swatches, and button groups — each able to carry its own price. Build an
**Option Set** once, assign it to the products you want, and the fields appear
on the product page with live price updates. Every surcharge is recomputed on
the server, so the amount a shopper pays always matches what you configured.

![Flexa Extra option fields on a WooCommerce product page](./images/overview-product-page.png)
*Screenshot: a product page showing Flexa Extra fields (a swatch, a text field) with the live Extra subtotal / Total price readout.*

This guide is organized as a set of articles. Read them in order for a full
walkthrough, or jump straight to the topic you need.

| # | Article | What it covers |
|---|---------|----------------|
| 00 | [Introduction](./00-introduction.md) | Core concepts, terminology, requirements |
| 01 | [Installation & activation](./01-installation.md) | Installing, activating, first look |
| 02 | [Managing option sets](./02-managing-option-sets.md) | The builder, saving, enabling/disabling |
| 03 | [Field types](./03-field-types.md) | Every field type and its settings |
| 04 | [Pricing](./04-pricing.md) | Fixed & percentage surcharges, how totals work |
| 05 | [Product assignment](./05-product-assignment.md) | Targeting all products, a list, or by conditions |
| 06 | [Conditional logic](./06-conditional-logic.md) | Showing/hiding fields based on other choices |
| 07 | [Settings](./07-settings.md) | General, Display, Style, and Advanced options |
| 08 | [Cart & checkout display](./08-cart-and-checkout-display.md) | How selections appear through checkout, orders, emails |
| 09 | [FAQ & troubleshooting](./09-faq-and-troubleshooting.md) | Common questions and fixes |

## At a glance

- **Field types (free):** Text, Paragraph, Number, Checkboxes, Radio buttons,
  Dropdown, Colour/image swatch, Buttons, Heading/description.
- **Pricing:** per-field or per-option, fixed amount or a percentage of the
  product price.
- **Assignment:** all products, a manual list, or rule-based conditions
  (category, tag, product, price, stock).
- **Conditional logic:** show or hide any field based on what the shopper
  picked in other fields.
- **Server-authoritative:** the extra price is always recalculated from your
  saved definitions — a tampered client value can never change what is charged.
- **Accessible & responsive:** semantic fieldsets, `aria-required`, keyboard
  focus states, reduced-motion aware.

## Requirements

- WordPress 5.0 or newer
- WooCommerce 6.0 or newer
- PHP 7.4 or newer

Compatible with High-Performance Order Storage (HPOS) and with both the classic
and block-based Cart and Checkout.
