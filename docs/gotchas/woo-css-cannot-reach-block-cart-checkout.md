# `woo.css` cannot reach the block cart and checkout at all

**Area:** WooCommerce / CSS · **Found:** s13 (26.07.2026), WooCommerce 10.9.4

## What happens

WooCommerce 10.x's `install_pages` creates **block-based** Cart and Checkout pages. s12
recorded this as "a large part of `woo.css` targets classic `.form-row` / `.shop_table`
shapes a default install never renders". That understates it by a lot.

Every rule in `src/css/woo.css` is nested inside one top-level `.woocommerce { … }` block.
Verified against the **built** bundle, not the source: all 184 top-level rules in
`assets/dist/assets/woo-*.css` require a `.woocommerce` ancestor or element.

The block checkout page has **no element carrying a bare `woocommerce` class**. Its body
classes are `woocommerce-checkout woocommerce-page woocommerce-no-js` — none of which is
`woocommerce` — and the block itself renders as
`<div class="wp-block-woocommerce-checkout alignwide wc-block-checkout is-loading">`.

So the stylesheet is enqueued, downloaded, parsed, and **entirely inert**. Not "mostly
inert", not "missing a few selectors" — zero rules can match.

## Why it is easy to get wrong

The obvious check — grepping `woo.css` for `.form-row` and concluding "only the classic
form styling is dead" — finds a real but much smaller problem and stops there. The scoping
question never comes up, because the nest is one line at the top of a 1900-line file.

## What to do

- Block surfaces need a **new top-level scope**: `.wp-block-woocommerce-cart` /
  `.wp-block-woocommerce-checkout`. These come from the block NAME, not from internal
  markup, which makes them the most stable selector WooCommerce offers here.
- Do not try to extend the existing nest. There is nothing to hang it on.
- The classic branch is still live and still supported (`woocommerce/classic-shortcode`
  block, `enum: ["cart","checkout"]`), so the classic rules are not dead code.

## Related facts worth carrying

- `/checkout/` **302s to `/cart/`** when the cart is empty, so a test that just navigates
  there silently asserts against the cart page.
- The block checkout is **entirely client-rendered**: the server response carries one
  `wc-block-checkout` class, an `is-loading` skeleton and **zero `<input>` elements**;
  hydration produces 94 distinct `wc-block-*` classes. e2e must wait for real fields.
- The Cart and Checkout block trees declare **no design supports at all** (no `color`,
  `typography`, `spacing`, `border`) across all 40+ inner blocks.
- `theme.json` → `styles.blocks["woocommerce/checkout"]` **does** emit and apply, but only
  to the wrapper, at specificity **(0,1,0)** (`:root :where(.wp-block-…)`). Verified by
  patching `theme.json` and reading computed style: the wrapper changed, the place-order
  button and the text input did not.

## Related

- [[ADR-009-block-cart-checkout-styling]] — the decision and every measurement behind it
- [[tailwind-content-detection-generates-utilities-from-anything]] — the other case where
  a source grep proved something the built file disproved
- `docs/plans/2026-07-25-block-cart-checkout.md`
