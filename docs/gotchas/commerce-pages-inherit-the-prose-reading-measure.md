# The classic cart, checkout, account and receipt are ordinary PAGES, so they inherit the prose reading measure

> Found s19 (28.07.2026), by looking at a screenshot. It had been true since the identity port and
> nothing measured it.

## The trap

`src/css/adapter/content.css` caps the content wrapper at the reading measure:

```css
.wtb-entry-content {
  max-width: var(--measure); /* 68ch */
}
```

That is correct, and it is the whole reason the rule exists — long-form prose is unreadable at 1248px.

But WooCommerce's cart, checkout, My Account and order-received screens are **not WooCommerce
templates**. They are ordinary WordPress pages carrying a shortcode, so they render through
`page.php` → `template-parts/content/content.php` → `.wtb-entry-content`, and they inherited the cap.

Measured at a 1280px viewport, before the fix:

| Element | Width |
|---|---|
| `article.wtb-entry` | 1248px |
| `.wtb-entry-content` (with the whole cart inside it) | **693.6px** |

So a `1fr 360px` cart layout and a `1fr 400px` checkout were handed roughly half the room the design
assumes. The cart table's six columns could not fit in the 1fr track and overflowed *under* the
totals card (later in DOM, so painted on top) — the columns past "Product" were simply missing from
the screenshot.

**The mockup settles it, which is what makes this a fix and not a preference.**
`docs/design/v2-mockup/woodev-base-identity.html` applies `--measure` to `.article` and to two
explicit `.prose` blocks — lines 812, 2235, 2257, 3023 — and nothing else. Every commerce section is
inside `.wrap` / `.wrap-wide`, i.e. `--container` / `--container-wide`. Capping them at the reading
measure was the divergence.

## The fix, and why the selector is so specific

```css
.wtb-entry-content:has(> .woocommerce > form.woocommerce-cart-form),
.wtb-entry-content:has(> .woocommerce > .wtb-cart-layout),
.wtb-entry-content:has(> .woocommerce > form.checkout),
.wtb-entry-content:has(> .woocommerce > .woocommerce-MyAccount-navigation),
.wtb-entry-content:has(> .woocommerce > .woocommerce-order) {
  max-width: none;
}
```

The obvious `:has(> .woocommerce)` is **wrong**: a blog post that embeds a `[products]` loop also has
`div.woocommerce` as a direct child of its content wrapper, so that version would strip the reading
measure off that post's prose — a regression traded for a fix. Each branch above names a marker only
a full commerce surface emits. Verified with a control: the `[products]` shortcode page still
computes `max-width: 693.6px` while all four commerce screens compute `none`.

It lives in `src/css/woo/storefront.css`, not next to the rule it overrides, for two reasons. Spec §8
— the base theme must stay fully useful with WooCommerce absent, so `adapter/**` never names Woo
classes. And the woo bundle is **un-layered** while the capping rule sits in `@layer adapter`, so it
wins on layer order alone and needs no specificity inflation.

## The general lesson

**A rule scoped to "the content wrapper" reaches every page template that uses it, including the ones
a plugin renders into.** Before adding anything to `.wtb-entry-content`, ask which pages are pages —
on a WooCommerce site that is the cart, the checkout, every account endpoint and the receipt, none of
which are prose.

The same audit found `content.php` printing a publication date and an empty byline on those screens
("WTB Classic Cart / JULY 28, 2026 BY"), for the same root cause: a post-shaped rule applied to
everything that is not a post. Both were invisible to phpcs, phpstan, 500+ unit tests, the
integration suite and the whole e2e suite.

## Related

- [[woo-clearfix-pseudo-elements-become-grid-items]] — the other s19 layout defect a screenshot found and no assertion did
- [[qa-gates-cover-less-than-they-claim]] — why nothing caught a 694px-wide cart
- [[source-order-only-wins-the-properties-you-redeclare]] — the cascade family this sits next to
