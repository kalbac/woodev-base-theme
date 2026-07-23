# M2a — WooCommerce storefront foundation — design

> Written s7, 23.07.2026. The first slice of M2 (spec §8). Scope and the two
> shaping decisions (slicing, override policy) settled with Maksim; the rest are
> engineering calls recorded here.

## What this slice is

M2 is the WooCommerce layer. It is cut into slices, each its own spec → plan →
PR:

- **M2a (this doc)** — the foundation every later slice needs, plus the
  *storefront*: shop/archive and single product. This is "a store you can look
  at", which is what the theme's product-demo use needs first.
- **M2b (later)** — cart, checkout, account, store notices, the Woo Customizer
  section.

The base theme must remain fully useful with WooCommerce absent; nothing in this
slice may run, enqueue, or error when the plugin is inactive.

## The two settled decisions

1. **Slicing:** foundation + storefront now; the checkout flow later. Checkout and
   account are WooCommerce's most fragile flows and do not belong in the same
   review as the storefront.
2. **Override policy — surgical minimum.** Spec §8's "hooks and CSS first,
   template overrides last resort" resolves to: override a template *only* where
   hooks and CSS genuinely cannot reach the required structure. Every override
   file records the WooCommerce version it was copied from and is audited on each
   supported Woo major. Everything else is `woocommerce_*` action/filter hooks
   and adapter CSS.

## Architecture

### The layer and its bootstrap

New namespace `Woodev\Theme\Base\Woo`. `Theme::boot()` gains a guarded line:

```php
if ( class_exists( 'WooCommerce' ) ) {
    ( new Woo\Woo() )->register();
}
```

`class_exists('WooCommerce')` is the canonical "is Woo active" check and is true
by the time `after_setup_theme` fires. With Woo absent the line is skipped and the
base theme is byte-for-byte its current self.

`Woo\Woo` is the layer's composition root, mirroring `Theme` for the base: it
instantiates and registers the sub-services (`Support`, `Assets` extension,
`Storefront` templates). Small classes, single responsibilities, no god-object.

### Declared support

In `after_setup_theme` (a `Woo\Support` service, or the layer root):

- `add_theme_support( 'woocommerce' )` — opts out of Woo's default wrapper and
  lets the theme own the page shell.
- `add_theme_support( 'wc-product-gallery-zoom' | '-lightbox' | '-slider' )` —
  the native gallery. Its JS (photoswipe/flexslider/zoom) is enqueued by Woo only
  on the single-product page.
- `WC requires at least` / `WC tested up to` headers in `style.css`, kept current.

### The page shell

Woo wraps its pages in its own `woocommerce_output_content_wrapper` /
`…_wrapper_end`. Declaring `woocommerce` support already removes Woo's default
`content-wrapper` theme-compat, but the theme must still route Woo content into
its own `.wtb-container` / `.wtb-layout` so the storefront inherits the base
container width and the optional sidebar. Done by hooking
`woocommerce_before_main_content` / `woocommerce_after_main_content` to emit our
wrappers (removing Woo's defaults first). No template override for this — it is
exactly what those hooks exist for.

### Conditional asset loading

One shared `woo` Vite entry, **not** eight pack-specific ones. Rationale: our Woo
styles are adapter CSS expressed against the design tokens
(`var(--primary)`, `var(--radius)`, …), and those tokens are already on `:root`
from whichever pack bundle the page loaded. A single `woo.css` layering on top
therefore works under any pack with no duplication — the same relationship the
base adapter already has to the packs. Eight Woo variants would double the entry
count to re-encode geometry the pack already supplies.

`woo.css` (and any Woo JS entry) is enqueued only on Woo contexts:
`is_woocommerce() || is_cart() || is_checkout() || is_account_page()`. This
extends the existing `Assets` manifest-resolver pattern with a second entry gated
by context; it does not replace the pack bundle, which still loads site-wide.

*(A note kept for the plan: `is_woocommerce()` is false on cart/checkout/account,
hence the explicit OR — a common mistake. Verify each against installed Woo.)*

## The storefront

Everything below is subject to reading the *installed* WooCommerce templates and
hook names during planning — Woo is not yet in the dev environment, and this
project does not guess third-party markup. The design is the policy and the
structure; the exact template paths and hook signatures are a plan-step
verification.

### Product loop (shop / archive)

- **The product card is the one likely override.** Woo's `content-product.php`
  renders `<li class="product">` with a fixed inner order; our storefront wants
  the §7 `.card` vocabulary. If hooks (`woocommerce_before_shop_loop_item`, the
  `_item_title`/`_price`/`add_to_cart` hooks) cannot assemble a `.card` structure
  from the outside — the likely case, since the `<li>` wrapper and its order are
  fixed — `content-product.php` is overridden to emit `.card > header/section/
  footer` reusing §7's classes: `.card` shell, `.badge` for Sale / Out-of-stock,
  `.btn` for add-to-cart. The file carries its source version.
- **The grid reuses §7.** The loop wrapper gets `.wtb-post-grid` (or a Woo alias
  of it), so product columns follow the same 1→2→3 / sidebar-capped rule the blog
  cards already established and e2e already knows how to assert. No new grid
  system.

### Single product

Hooks first, override only if forced:

- **Gallery** — native Woo gallery (the declared support), restyled via our
  wrappers and body classes, not overridden.
- **Summary** (title, price, add-to-cart, meta) — styled through
  `.woocommerce div.product` body classes and the summary hooks; `.btn` on the
  add-to-cart button, our price/typography tokens. Override the summary template
  only if its structure cannot be reached otherwise.
- **Product tabs** (Description / Additional information / Reviews) — **style
  Woo's own tab markup** to read as Basecoat tabs via CSS, rather than overriding
  `single-product/tabs/tabs.php` to inject Basecoat's tab markup. This keeps the
  surgical-override policy coherent and is where §7's deferred *tabs/accordion*
  actually land — as restyled native Woo, not a separate component. If Basecoat's
  tab interaction (keyboard, aria) cannot be achieved by restyling alone, that is
  a planning finding to surface, not a silent template override.

## Testing

| Level | Scope |
|---|---|
| Integration | The layer registers only when Woo is active (skip when the plugin is absent from the environment); the support declarations land in the registry; the conditional enqueue fires on a Woo context and not elsewhere. |
| e2e | Storefront rendered against a seeded demo store: the shop shows a card grid; a single product shows gallery + summary + tabs; add-to-cart puts an item in the cart (the cart *flow* is M2b — here only "the button works"). Light + dark. |

**Woo lives in its own e2e environment.** The base theme must be tested *without*
WooCommerce — that is a hard product requirement (§8, "degrade gracefully"). So
the main e2e run stays Woo-free, and a new `.wp-env.woo.json` environment (Woo
installed + activated, a seeded demo store) carries a separate `npm run e2e:woo`,
exactly the isolation pattern `.wp-env.dev-mode.json` already established. The
demo store is seeded idempotently by a Woo-specific global-setup: a few simple
products, one on-sale, one out-of-stock, so the badge and price states have real
content.

## Explicitly out of this slice

cart, checkout, account, store notices, and the Woo Customizer section — all M2b.
A visible add-to-cart button is in; what happens after the click is not.

## Risks

- **Woo is not in the dev environment yet.** Every template path, hook name and
  body class above is architecture-level and must be verified against the
  installed plugin as a first plan step. Do not code against remembered Woo
  markup.
- **The product-card override is a standing maintenance cost.** It is the price of
  a card-shaped storefront under the surgical policy; the audit-on-major process
  (§8) is what keeps it honest. If it turns out hooks *can* assemble the card
  without an override, prefer that — the override is the fallback, not the goal.
- **HPOS / block-based cart & checkout** are M2b concerns, but declaring
  `woocommerce` support and testing against a current Woo now surfaces any
  compatibility flag we need early.
- **Gallery JS** adds a single-product-only payload and an e2e surface; accepted
  as a standard storefront expectation.

## Related

- `docs/specs/2026-07-17-woodev-base-v1-design.md` §8 — the WooCommerce layer contract
- `docs/gotchas/basecoat-style-packs-standalone.md` — why one shared Woo bundle works across packs
- `docs/gotchas/wp-env-config-constants-persist.md` / `wp-env-installs-themes-without-activating-them.md` — standing up the Woo e2e environment
- `docs/gotchas/playwright-browser-newpage-skips-config.md` — the Woo e2e uses the `{ page }` fixture
