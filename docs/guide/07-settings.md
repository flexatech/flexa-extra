---
title: Settings
slug: settings
order: 80
category: Flexa Extra
---

# Settings

Global behaviour and appearance live under **Flexa Extra → Settings**, which is
organized into four tabs: **General**, **Display**, **Style**, and
**Advanced**. Settings apply across every Option Set. Save changes with the
button in the settings header.

![The Flexa Extra Settings screen](./images/settings-overview.png)
*Screenshot: the Settings screen with the General / Display / Style / Advanced tab navigation and the Save button in the header.*

> Settings are saved as a partial update — only what you change is written, so
> saving one tab never resets the others.

---

## General

Core on/off behaviour.

| Setting | Default | What it does |
|---------|---------|--------------|
| **Enabled** | On | Master switch. When off, Flexa Extra renders no fields and adds no surcharges anywhere. |
| **Show extra subtotal** | On | Shows the running *extra subtotal* readout on the product page. |
| **Show total price** | On | Shows the *total price* (product + extras) readout on the product page. |
| **Show value in mini-cart** | On | Includes the selected option values in the mini-cart. |

---

## Display

Labels and placement of the price readouts.

| Setting | Default | What it does |
|---------|---------|--------------|
| **Extra subtotal label** | `Extra subtotal:` | Text shown before the extra-subtotal amount. |
| **Total price label** | `Total price:` | Text shown before the total-price amount. |
| **Position** | Before add-to-cart | Where the fields render on the product page: **before** or **after** the Add to Cart button. |

Both labels are free text and are translatable.

---

## Style

Appearance of swatch and button choice fields. These apply to every Option Set
so your storefront stays consistent.

| Setting | Default | Options / notes |
|---------|---------|-----------------|
| **Swatch size** | Medium | Small · Medium · Large |
| **Swatch shape** | Circle | Circle · Rounded · Square |
| **Show tooltips** | On | Show option tooltips on hover/focus. |
| **Button background** | Inherit | Normal button background colour. Empty = inherit the theme. |
| **Button text** | Inherit | Normal button text colour. |
| **Button active background** | Inherit | Background of a selected button. |
| **Button active text** | Inherit | Text colour of a selected button. |

Colour settings take a hex value (e.g. `#4f46e5`). Leaving a colour **empty**
means "inherit from the theme", which is the safest default if you are unsure —
your buttons then match your site's styling automatically.

![The Style tab with swatch and button controls](./images/settings-style.png)
*Screenshot: the Style tab showing the swatch size/shape selectors and the button colour pickers.*

---

## Advanced

Fine-tuning and performance.

| Setting | Default | What it does |
|---------|---------|--------------|
| **Hide zero subtotal** | On | Hides the extra-subtotal readout while it is $0.00 (nothing chargeable is selected yet), so it only appears once it is meaningful. |
| **Load scripts on all pages** | Off | By default the storefront assets load only where they are needed (the product page, and the cart/checkout for line-item styling). Turn this on only if a custom template renders option fields somewhere unusual and they are missing their styles/scripts. |

> **Leave "Load scripts on all pages" off** unless you have a specific reason.
> It exists for edge-case templates; enabling it site-wide adds assets to pages
> that do not need them.

---

## What to check first

If fields are not appearing or behaving as expected, the settings to verify are:

1. **General → Enabled** is on.
2. The Option Set itself is **enabled** and **assigned** to the product (see
   [Managing option sets](./02-managing-option-sets.md) and
   [Product assignment](./05-product-assignment.md)).
3. **Display → Position** matches where you expect the fields on the page.

More diagnostics are in [FAQ &
troubleshooting](./09-faq-and-troubleshooting.md).
