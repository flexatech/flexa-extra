---
title: Introduction
slug: introduction
order: 10
category: Flexa Extra
---

# Introduction

Flexa Extra lets you attach **extra option fields** to WooCommerce products so
shoppers can personalize what they buy — and so you can charge for those
choices. A monogram on a wallet, gift wrapping, an engraving message, a colour
choice with a small upcharge: all of these are extra product options.

## Core concepts

Three ideas are enough to understand the whole plugin.

### Option Set

An **Option Set** is a reusable group of fields. You build it once, then assign
it to one or many products. A single product page can show the fields from
every Option Set that targets it.

Example: an Option Set named *"Gift options"* might contain a *Gift wrap?*
choice, a *Gift message* text field, and a *Delivery date note*. Assign it to
your whole "Gifts" category and every product there gains those three fields.

### Field

A **Field** is one input inside an Option Set — a text box, a dropdown, a set
of colour swatches, and so on. Each field has a label, an optional tooltip, and
type-specific settings (a text field can validate an email; a number field can
enforce min/max; a choice field lists selectable options).

Fields fall into three families:

- **Input fields** collect a free-form value: Text, Paragraph, Number.
- **Choice fields** let the shopper pick from a list: Checkboxes, Radio
  buttons, Dropdown, Colour/image swatch, Buttons.
- **Display fields** show information only and collect nothing: Heading /
  description.

### Price

A field — or an individual option within a choice field — can add a **price**.
The surcharge is either a **fixed amount** (e.g. +$5.00) or a **percentage** of
the product price (e.g. +10%). As the shopper makes selections, the product
page shows a running *extra subtotal* and *total price*, and the cart reflects
the surcharge on add-to-cart.

## The golden rule: the server decides the price

The storefront shows a live price for a good shopping experience, but that
number is never trusted. When a product is added to the cart — and on every
recalculation of cart totals afterward — Flexa Extra **recomputes the extra
price on the server** from your stored field definitions. A shopper who
tampers with the page cannot change what they are charged. This is why the
amount in the cart always matches what you configured, and why removing or
re-pricing an option later is reflected on existing carts automatically.

## Where you work

Everything is managed from a single **Flexa Extra** menu in the WordPress
admin. Inside it you will find:

- The **Option Sets** list — create, edit, enable/disable, and delete sets.
- The **Option Set builder** — a drag-and-drop screen for adding fields,
  configuring them, and choosing which products the set applies to.
- **Settings** — global behaviour, display labels, swatch/button styling, and
  advanced options.

![The Flexa Extra admin menu and Option Sets screen](./images/intro-admin-menu.png)
*Screenshot: the WordPress admin sidebar with the Flexa Extra menu expanded, and the Option Sets list open.*

The next article covers installation; after that, [Managing option
sets](./02-managing-option-sets.md) walks through building your first set.
