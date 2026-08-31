---
title: Managing option sets
slug: managing-option-sets
order: 30
category: Flexa Extra
---

# Managing option sets

An **Option Set** is a reusable group of fields you assign to products. This
article covers the Option Sets list and the builder screen. For the individual
field types see [Field types](./03-field-types.md); for choosing which products
a set applies to see [Product assignment](./05-product-assignment.md).

## The Option Sets list

Open **Flexa Extra → Option Sets**. Each row shows a set's name and its
status. From here you can:

- **Create** a new set.
- **Edit** an existing set (opens the builder).
- **Enable / disable** a set — a disabled set keeps all its configuration but
  stops appearing on the storefront.
- **Delete** a set you no longer need.

![The Option Sets list with several sets](./images/optionsets-list.png)
*Screenshot: the Option Sets list showing a few sets with their name, status badge, and row actions (edit / enable-disable / delete).*

> **Enabled vs. disabled.** Disabling is the safe way to temporarily remove a
> set from your shop without losing your work. A disabled set never renders on
> product pages and never adds surcharges.

## The builder

Creating or editing a set opens the **builder**, a three-pane workspace:

```
┌───────────────┬──────────────────────────┬──────────────────┐
│  Field        │        Canvas            │    Inspector     │
│  palette      │   (your fields, in order)│  (edit selected  │
│  (drag from)  │                          │   field)         │
└───────────────┴──────────────────────────┴──────────────────┘
        Header: set name · status toggle · Save
        Assignment panel: which products this set applies to
```

![The three-pane Option Set builder](./images/builder-overview.png)
*Screenshot: the full builder — field palette on the left, canvas with a few fields in the center, Inspector on the right, and the header with the name field, status toggle, and Save button.*

### 1. Header

At the top you set the **name** of the Option Set (used to identify it in the
list and shown as a group label where appropriate), toggle its **status**
(enabled/disabled), and **Save** your work. The save button is enabled once you
have unsaved changes.

### 2. Field palette

The left pane lists the available **field types**, grouped as *Input*,
*Choice*, and *Display*. Add a field by dragging it onto the canvas (or using
the add control). Each type is documented in [Field types](./03-field-types.md).

### 3. Canvas

The center pane shows the fields in this set, in the order shoppers will see
them. **Drag to reorder**. Click a field to select it and edit it in the
Inspector. This is also where you remove a field you no longer want.

### 4. Inspector

The right pane edits the **currently selected field**. Common settings appear
for every field (label, tooltip, whether it is required), and type-specific
controls appear below:

- **Choice editor** — manage the list of options for checkbox/radio/dropdown/
  swatch/button fields (label, value, colour or image for swatches, default
  selection, per-option price).
- **Price editor** — set a fixed or percentage surcharge on the field or an
  option.
- **Logic editor** — build show/hide rules based on other fields. See
  [Conditional logic](./06-conditional-logic.md).

![The Inspector editing a selected field](./images/builder-inspector.png)
*Screenshot: the Inspector pane with a choice field selected — common settings (label, tooltip, required) on top, and the choice/price editors below.*

### 5. Assignment panel

The assignment panel controls **which products** this Option Set applies to:
all products, a hand-picked list, or rule-based conditions. This is covered in
detail in [Product assignment](./05-product-assignment.md).

## Saving and what happens next

Click **Save** to store the set. Every field, option, price, logic rule, and
assignment rule is validated and normalized on the server as it is saved.

Once a set is **enabled** and **assigned** to products, its fields appear on
those product pages immediately. Because pricing is recomputed server-side,
changes you make later — renaming an option, adjusting a price, disabling the
set — are reflected on the storefront and on existing carts the next time
totals are calculated.

## Tips

- Keep each Option Set focused on one purpose (e.g. *"Engraving"*,
  *"Gift options"*). A product can receive several sets, so small, composable
  sets are easier to reuse than one giant set.
- Give options clear **labels**. A colour swatch without a label falls back to
  showing its colour value; a proper label like *"Navy Blue"* reads far better
  in the cart and on the order.
- Use **disable** rather than delete when you might want the set back — for
  seasonal promotions, for instance.
