---
title: Installation & activation
slug: installation
order: 20
category: Flexa Extra
---

# Installation & activation

## Requirements

Before installing, make sure your site meets the minimums:

| Component   | Minimum version |
|-------------|-----------------|
| WordPress   | 5.0             |
| WooCommerce | 6.0             |
| PHP         | 7.4             |

WooCommerce **must be active**. Flexa Extra depends on it and will stay dormant
without it (see below).

## Install the plugin

You can install Flexa Extra like any WordPress plugin:

**From a zip file**

1. In the WordPress admin go to **Plugins → Add New → Upload Plugin**.
2. Choose the `flexa-extra.zip` file and click **Install Now**.
3. Click **Activate**.

![Uploading the Flexa Extra plugin zip](./images/install-upload.png)
*Screenshot: the Plugins → Add New → Upload Plugin screen with flexa-extra.zip selected.*

**Manually (FTP/SSH)**

1. Unzip the package.
2. Upload the `flexa-extra` folder to `wp-content/plugins/`.
3. Go to **Plugins** in the admin and click **Activate** under *Flexa Extra*.

## Activation behaviour

On activation Flexa Extra checks that WooCommerce is present:

- **If WooCommerce is active**, the plugin activates normally and a **Flexa
  Extra** menu appears in the admin sidebar.
- **If WooCommerce is not active**, Flexa Extra does not run its features and
  shows an admin notice explaining that WooCommerce is required. Once you
  activate WooCommerce, Flexa Extra resumes automatically — no need to
  reactivate it.

## First look

Open **Flexa Extra** from the admin menu. You land on the **Option Sets** list.
On a fresh install this list is empty, with a button to create your first
Option Set.

![The empty Option Sets list on a fresh install](./images/install-first-look.png)
*Screenshot: the empty-state Option Sets screen with the "Create your first Option Set" button.*

Two areas are always available from here:

- **Option Sets** — where you create and manage sets of fields.
- **Settings** — global options for how fields behave and look. It is worth a
  quick visit before you build: confirm that the plugin is **enabled** under
  the *General* tab (it is by default). See [Settings](./07-settings.md).

## HPOS and block checkout

No extra configuration is needed for:

- **High-Performance Order Storage (HPOS)** — Flexa Extra declares
  compatibility, so you can leave HPOS on.
- **Block-based Cart and Checkout** — selections and their surcharges display
  correctly in both the classic shortcode cart/checkout and the newer block
  versions.

You are ready to build. Continue with [Managing option
sets](./02-managing-option-sets.md).
