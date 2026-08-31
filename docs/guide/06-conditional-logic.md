---
title: Conditional logic
slug: conditional-logic
order: 70
category: Flexa Extra
---

# Conditional logic

Conditional logic shows or hides a field based on what the shopper selected in
**other** fields. It keeps forms short and relevant — a *Gift message* box, for
instance, only needs to appear once the shopper says *"Yes, this is a gift."*

You configure logic per field, in the builder Inspector's **Logic editor**.

## How a rule works

Each field can have a set of logic rules. A rule watches another field and
tests its value:

```
[ Action ]  this field  when  [ Match ]  of these rules pass:
   show                          any
   ─────────────────────────────────────────────────────────
   ▸ [ Gift wrap? ]  [ is ]      [ Yes ]
   ▸ [ Occasion ]    [ is not ]  [ None ]
```

![The Logic editor in the Inspector](./images/logic-editor.png)
*Screenshot: the Logic editor with the action (Show/Hide), the match (Any/All) selector, and one or more rules referencing other fields.*

Three parts control the outcome:

### Action — what to do

- **Show** — the field is hidden until the rules pass, then it appears.
- **Hide** — the field is visible until the rules pass, then it disappears.

### Match — how the rules combine

- **Any** — the action triggers if **at least one** rule passes (OR).
- **All** — the action triggers only if **every** rule passes (AND).

### Rules — the conditions

Each rule points at another field and applies an operator:

| Operator | Passes when the watched field… |
|----------|--------------------------------|
| **is** | equals the given value |
| **is not** | does not equal the given value |
| **empty** | has no value / nothing selected |
| **not empty** | has any value / something selected |

For choice fields, *is* / *is not* compare against an option's value, and for
multi-select fields *is* passes when the value is **among** the selected
options.

## It also applies to price

A hidden field contributes **nothing to the price**. If conditional logic hides
a field, its surcharge (and its options' surcharges) is removed from the extra
subtotal. This is enforced server-side too: the same visibility logic runs when
the cart price is recomputed, so a field the shopper never saw is never charged.

## Worked example

You want the engraving message to appear only when engraving is requested:

1. Add a **Radio** field *"Add engraving?"* with options **Yes** and **No**
   (default **No**).
2. Add a **Text** field *"Engraving message"*.
3. On *"Engraving message"*, open the **Logic editor** and set:
   - Action: **Show**
   - Match: **Any**
   - Rule: *"Add engraving?"* **is** **Yes**

Now the message box stays hidden until the shopper chooses **Yes**. If you gave
the message field a price, that price only applies while the field is visible.

## Tips

- Reference fields that come **earlier** in the set where possible, so the
  dependency reads naturally top-to-bottom.
- Prefer **Show** logic for optional add-ons (start hidden, reveal on demand)
  and **Hide** logic for mutually-exclusive fields (start visible, remove when
  another choice makes them irrelevant).
- Keep chains shallow. Deeply nested show/hide rules are hard to test — a few
  clear rules beat many interacting ones.
