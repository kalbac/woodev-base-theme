# ADR-009: Styling the block-based Cart and Checkout

- **Status:** Accepted (25.07.2026)
- **Deciders:** Maksim (the "must be supported" decision, end of s12) + Claude (s13, approach)
- **Scope:** `woocommerce/cart` and `woocommerce/checkout` block trees. Mini-cart,
  Product Collection and the other Woo blocks are out of scope here.
- **Closes the research half of** [#19](https://github.com/kalbac/woodev-base-theme/issues/19)

## Context

WooCommerce 10.x's `install_pages` creates **block-based** Cart and Checkout pages. s12
found that a large part of `src/css/woo.css` targets the classic `.form-row` /
`input.input-text` / `.shop_table` shapes a default install never renders, and Maksim
decided the theme must support the block versions.

Every claim below was measured against the installed package (WooCommerce **10.9.4**) and
the live seeded store on `:8891`, not recalled — s12 lost hours twice to confident
justifications that turned out false.

### What the measurements found

**1. The Cart and Checkout block trees declare no design supports at all.**
Of the 68 `woocommerce/*cart*` / `*checkout*` blocks, every one in the Cart and Checkout
trees — including `woocommerce/checkout` itself and all 40+ inner blocks — ships
`supports` without `color`, `typography`, `spacing` or `__experimentalBorder`. Only
`mini-cart*` and `cart-link` (out of scope) declare any. There is therefore **no
block-supports route** to styling these blocks.

**2. `theme.json` → `styles.blocks` does reach them, but only the wrapper.**
Verified by patching `theme.json` with a probe entry and reading the rendered page: WP
emits

```css
:root :where(.wp-block-woocommerce-checkout){background-color:…;color:…;padding-top:…}
```

and it **applies** — the probe's background, colour and padding all took effect on the
wrapper. It reaches nothing inside: the place-order button stayed Woo's grey and the text
input stayed white. Note the selector's specificity is only **(0,1,0)**, since `:where()`
contributes nothing — any two-class `.wc-block-*` rule beats it.

**3. Our stylesheets already load last, so the classic cascade model carries over.**
On both the live `/cart/` and `/checkout/`, the head order ends:
`wc-blocks-style` → `wc-blocks-style-cart|checkout` → `global-styles` →
`woocommerce-general` → **`woodev-base-style`** → **`woodev-base-woo`**. Our sheets are
last, so an un-layered rule of equal specificity wins. The one late `<style>` on checkout
(`wc-custom-place-order-button-styles`) is a single `display:none` for the *classic*
`#place_order` and is no threat.

**4. Text and typography already inherit correctly; the broken set is small and specific.**
`cart.css` and `checkout.css` lean heavily on `currentColor` (66 / 100 uses) and `inherit`
(69 / 175), so the wrapper, section titles, labels, totals rows and panels already render
in our tokens and our fonts — the checkout's section titles come out in Golos Text — in
**both** schemes. What is hardcoded and therefore wrong:

| Surface | Today (both schemes) | Should follow |
|---|---|---|
| Text input, select, combobox | `#fff` bg, `rgb(43,45,47)` text, radius `4px` | `--background`/`--foreground`/`--border`, `--radius` |
| Checkbox / radio | `#fff` bg, radius `4px` | same |
| Place-order & other buttons | `rgb(50,55,60)` bg — **but see the amendment below: not from WooCommerce** | `--primary` / `--primary-foreground` |
| Notice banners | `#fff0f0` bg, `#2f2f2f` text | `--destructive` / `--success` / `--warning` roles |
| Radii generally | `0px` / `4px` | `--radius` (currently `10px`) |

**The dark scheme is where this reads as broken, not merely off-brand:** a checkout text
input stays white with near-black text on a `oklch(13% …)` page.

> **Amendment (s14, while implementing B3).** The button row above measured the right
> value and named the wrong source. `#32373c` — which is exactly `rgb(50,55,60)` —
> appears **zero** times in the pinned WooCommerce 10.9.4 `cart.css`, `checkout.css`
> and `packages-style.css`. It comes from **WordPress core's own `theme.json`**
> (`styles.elements.button.color.background`), which `WP_Theme_JSON` merges beneath
> our `theme.json` and emits as the un-layered `global-styles` block already named in
> finding 3's head order; WooCommerce's JS is merely what attaches the
> `.wp-element-button` class to these buttons. Two consequences. First, the override
> has to target `.wp-element-button`, not a `.wc-block-*` class — which is what the
> implementation does, still scoped under the block wrapper. Second, and beyond this
> ADR's scope: the same core default paints **every** `.wp-element-button` on the
> site, so core's own Button block is off-identity on any page until our `theme.json`
> declares `styles.elements.button`. Tracked separately, next to
> [#25](https://github.com/kalbac/woodev-base-theme/issues/25). Note this does not
> touch decision 5, which is about `styles.blocks`, not `styles.elements`.

**5. Not one byte of `woo.css` can apply to these pages.**
Verified against the **built** artifact, not the source: all 184 top-level blocks in
`assets/dist/assets/woo-*.css` require a `.woocommerce` ancestor, and the block Checkout
page contains **no element with a bare `woocommerce` class** — its body classes are
`woocommerce-checkout woocommerce-page woocommerce-no-js`. The file also contains zero
`.wc-block-*` and zero `.wp-block-woocommerce-*` selectors. So the gap is structural, not
just a matter of the classic selectors missing.

**6. The classic path is still first-party supported.**
WooCommerce 10.9.4 still registers the `woocommerce_cart` / `woocommerce_checkout`
shortcodes *and* ships a `woocommerce/classic-shortcode` block whose `shortcode` attribute
is `enum: ["cart","checkout"]` — the official way back. The ~460 lines (24% of `woo.css`)
covering classic cart/checkout are therefore **not dead code**; they serve stores that took
that route.

**7. `.wc-block-*` class churn, measured.**
Comparing 9.4.0 → 10.9.4 (6 majors, ~10 months): **94%** of `checkout.css` classes and
**85%** of `cart.css` classes survived. That is a lower bound — a class leaving one
stylesheet does not mean it left the markup. Stable enough to target, not stable enough to
treat as a contract.

**8. The block Checkout is entirely client-rendered.**
Its server response carries one `wc-block-checkout` class, an `is-loading` skeleton and
**zero `<input>` elements**; hydration produces 94 distinct `wc-block-*` classes.

## Decision

**Style the Cart and Checkout blocks with class-level CSS in a new, separately scoped and
separately loaded stylesheet — `src/css/woo-blocks.css` — and keep the classic branch.**

1. **New top-level scope, not an addition to `woo.css`.** Rules are scoped to the
   WP-generated wrapper classes `.wp-block-woocommerce-cart` / `.wp-block-woocommerce-checkout`.
   These derive from the block *name*, not from internal markup, which makes them the most
   stable selector available. `woo.css`'s `.woocommerce { … }` nest cannot be reused —
   finding 5.
2. **Its own Vite entry, conditionally enqueued** via `has_block()` for the two block
   names. Unlike product loops — which can appear on any page, and are why `woo.css` is
   enqueued unconditionally — Cart and Checkout are `"multiple": false` blocks that live on
   their own two pages, so conditional loading is both accurate and cheap. This keeps the
   ~30 KB `woo.css` from growing on every request.
3. **Un-layered, matching `woo.css`.** Our sheet loads after Woo's (finding 3), so an
   un-layered rule of equal specificity wins. Specificity is *mirrored*, never inflated
   with `!important`, and every override is checked against the vendor rule in the real
   `checkout.css` / `cart.css` — the s12 lesson that a repair can otherwise exist only in
   a comment.
4. **Style the hardcoded set only.** Do not restate what already inherits (finding 4).
   The work list is the table above.
5. **`theme.json` gets no `styles.blocks` entry.** It reaches only the wrapper, whose
   colour and font already inherit correctly, so an entry would restate the default at
   (0,1,0) and buy nothing. YAGNI.
6. **Keep the classic cart/checkout CSS.** It is a supported second branch (finding 6),
   not legacy. Both branches are maintained; neither is the "real" one.

### What is explicitly not decided here

- **Progressive enhancement cannot hold on the block Checkout.** AGENTS.md requires pages
  to work as server-rendered HTML, and finding 8 says WooCommerce renders nothing without
  JS. This is WooCommerce's architecture, not a gap in our implementation; the theme
  cannot fix it. The classic branch remains the PE-friendly option for stores that need
  one. Recorded here so nobody later reads our PE rule as a claim about block checkout.
- **Our `theme.json` presets are static light values** that follow neither the Customizer
  palette nor the dark scheme. Blocks consume presets only for three font sizes (all with
  fallbacks) and two colours, so the practical impact is negligible today — but it is a
  latent inconsistency with ADR-008's "one identity", and it will matter the moment we
  care about the block editor's own rendering. Tracked separately, not solved here.

## Consequences

- **Reversibility 🟡.** A separate stylesheet with its own scope and its own enqueue is
  removable in one file plus one registration; nothing in the classic path depends on it.
- **A version-coupled surface, by necessity.** We target internal `.wc-block-*` classes
  because WooCommerce offers no alternative for these blocks. With ~85–94% class survival
  per 6 majors, this needs a review pass on major WooCommerce upgrades. The e2e suite is
  what makes that review cheap: an override whose class disappeared shows up as a failed
  computed-style assertion, not as a silent regression.
- **e2e must wait for hydration.** Assertions on the block Checkout cannot read the server
  HTML (finding 8); they must wait for real fields to exist and then assert computed
  style, in both schemes. This differs from every existing storefront assertion.
- **Two branches to keep honest.** Classic and block cart/checkout are both live. The plan
  must not let a fix land on one and silently skip the other.

## Related

- [[ADR-008-single-visual-identity]] — the identity these surfaces must join
- [[ADR-004-basecoat-npm-adapter]]
- [#19](https://github.com/kalbac/woodev-base-theme/issues/19) — the brief this answers
- `docs/CURRENT-STATE.md` — the s12 scope finding that opened this
