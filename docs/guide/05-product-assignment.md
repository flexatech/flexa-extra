---
title: Product assignment
slug: product-assignment
order: 60
category: Flexa Extra
---

# Product assignment

Assignment controls **which products** show an Option Set. You configure it in
the builder's **Assignment panel**. There are three modes.

![The Assignment panel with its three modes](./images/assignment-modes.png)
*Screenshot: the Assignment panel showing the mode selector — All products / Manual list / Conditions.*

## Mode 1 — All products

The set applies to **every** product in your shop. Use this for options you
want everywhere, such as a universal *"Add a gift note"* field.

## Mode 2 — Manual list

The set applies only to the **specific products you pick**. Search for and
select individual products; the set shows on those and nowhere else. Use this
for options that belong to a defined handful of items.

## Mode 3 — Conditions

The set applies to products that **match rules** you define. Each condition
compares a product attribute against a value; you combine several conditions
with a match mode.

![The condition builder with several rules](./images/assignment-conditions.png)
*Screenshot: the Conditions mode with two rules (e.g. Category is Apparel, Price greater than 100) and the Match any/all selector.*

### Condition types

| Type | Matches on | Typical use |
|------|-----------|-------------|
| **Category** | The product's category | "All products in *Apparel*" |
| **Tag** | The product's tag | "Anything tagged *personalized*" |
| **Product** | A specific product | Include/exclude an individual item |
| **Price** | The product's price | "Products over $100" |
| **Stock** | Stock status | "Only in-stock products" |

### Operators

Each condition uses an operator appropriate to its type:

| Operator | Meaning | Used with |
|----------|---------|-----------|
| **is** | Equal to the value | category, tag, product |
| **is not** | Not equal to the value | category, tag, product |
| **greater than** | Numeric greater-than | price |
| **less than** | Numeric less-than | price |
| **in stock** | Product is in stock | stock |
| **out of stock** | Product is out of stock | stock |

### Match mode: any vs. all

When you have more than one condition, choose how they combine:

- **Match any** — the product qualifies if it satisfies **at least one**
  condition (logical OR).
- **Match all** — the product qualifies only if it satisfies **every**
  condition (logical AND).

**Examples**

- *Category **is** Apparel* **and** *Price **greater than** 100* with **Match
  all** → only apparel priced above $100.
- *Tag **is** gift* **or** *Category **is** Gifts* with **Match any** → anything
  tagged *gift* or in the *Gifts* category.

## When multiple sets target the same product

A product shows the fields from **every enabled Option Set that targets it**.
The sets render in sequence, so you can layer small, purpose-built sets (e.g.
*Engraving* + *Gift options*) on the same product rather than duplicating fields.

## Assignment and pricing together

Assignment decides *whether* a set's fields appear; it does not by itself add
any charge. Prices come from the fields and options inside the set (see
[Pricing](./04-pricing.md)). A set assigned to a product but containing only
free options simply collects information at no cost.
