---
title: Field types
slug: field-types
order: 40
category: Flexa Extra
---

# Field types

Every field in an Option Set has a **type**. Types fall into three families:
**input** (free-form value), **choice** (pick from a list), and **display**
(information only). This article documents each free type and its settings.

![The field palette grouped by family](./images/fieldtypes-palette.png)
*Screenshot: the builder's field palette showing the Input, Choice, and Display groups with every available field type.*

## Settings common to all fields

When you select a field in the builder Inspector, these settings are available
regardless of type:

| Setting | Purpose |
|---------|---------|
| **Label** | The field's visible name. Also used as the meta label in the cart and on the order. |
| **Tooltip** | Optional helper text/hint shown alongside the field. |
| **Placeholder** | Hint text inside an empty input (input fields). |
| **Required** | If on, the shopper must provide a value before adding to cart. |
| **Default** | A pre-filled value (input fields) or pre-selected option (choice fields). |
| **Logic** | Show/hide rules based on other fields — see [Conditional logic](./06-conditional-logic.md). |

---

## Input fields

Input fields collect one free-form value.

### Text

A single-line text box. Use it for names, monograms, short messages.

Text fields add a **format / validation** option:

- **Text** (default) — any text.
- **Email** — the value must be a valid email address.
- **URL** — the value must be a valid URL.
- **Regex** — the value must match a regular expression you supply.

If a value fails validation, the shopper is told and cannot proceed until it is
corrected. (An invalid regex you enter as the admin never blocks checkout — a
broken pattern is treated as "no constraint".)

A text field can carry a **price** on the field itself (charged when the
shopper enters any value). See [Pricing](./04-pricing.md).

### Paragraph

A multi-line text area, for longer messages or notes. Like Text, it can carry a
field-level price.

### Number

A numeric input with optional constraints:

- **Min** — the smallest allowed value.
- **Max** — the largest allowed value.
- **Step** — the increment (e.g. `0.5`).

Values outside min/max are rejected with a message. A number field can carry a
field-level price.

### Date picker

A native date input. The shopper picks a calendar date, stored and shown on the
order in `YYYY-MM-DD` form. Set a **Default** date in the Inspector to pre-fill
it. Like the other input fields, it can carry a field-level price.

### Colour picker

A native colour input. The shopper picks a colour, stored and shown on the order
as a hex value (for example `#3366ff`). Set a **Default** colour in the
Inspector. It can also carry a field-level price.

---

## Choice fields

Choice fields present a list of **options**. Each option has its own label,
value, optional default state, tooltip, and — importantly — its **own price**.
Options are managed in the Inspector's choice editor.

![The choice editor managing options](./images/fieldtypes-choice-editor.png)
*Screenshot: the Inspector's choice editor with several options listed — each row showing label, value, price, and (for swatches) a colour/image control.*

### Checkboxes

A list of independent checkboxes. The shopper can select **any number** of
options (including none). Each checked option adds its price.

### Radio buttons

A list where the shopper picks **exactly one** option. Selecting an option adds
that option's price.

### Dropdown

The same "pick one" behaviour as radio buttons, presented as a compact
`<select>` menu. Good for long option lists.

### Colour / image swatch

A visual "pick" field. Each option shows a **colour dot** or a small **image**
instead of (or alongside) a text label:

- **Colour** — set a hex colour per option; the swatch renders that colour.
- **Image** — set an image URL per option; the swatch renders that thumbnail.

Swatches can be **single-select** or **multi-select**. Swatch appearance (size
and shape) is controlled globally under [Settings → Style](./07-settings.md).

> **Always label your swatch options.** If an option has no label, the cart and
> order fall back to showing the raw colour value (e.g. `#1242AF`). A label like
> *"Navy Blue"* displays a colour dot **and** the readable name.

![Colour swatches on the product page](./images/fieldtypes-swatch.png)
*Screenshot: a colour/image swatch field on the storefront, with one swatch selected and its label shown.*

### Buttons

The same "pick" behaviour as radio/dropdown, presented as a row of clickable
buttons. Buttons can be single- or multi-select. Button colours (normal and
selected/active states) are controlled globally under
[Settings → Style](./07-settings.md).

---

## Display fields

### Heading / description

A non-interactive block used to title a section or add explanatory text between
fields. It collects no value and carries no price — it is purely for structure
and clarity on the product page.

---

Continue with [Pricing](./04-pricing.md) to learn how field and option prices
combine into the shopper's total.
