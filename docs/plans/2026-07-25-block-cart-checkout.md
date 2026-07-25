# Plan — support the block-based Cart and Checkout

> Written s13, 25.07.2026. Branch: `feat/m2a-woo-storefront` (continues).
> Decision: [ADR-009](../adr/ADR-009-block-cart-checkout-styling.md).
> Brief: [#19](https://github.com/kalbac/woodev-base-theme/issues/19).
> Measured against **WooCommerce 10.9.4** and the seeded store on `:8891`.

## What we are building

A second, separately scoped stylesheet that brings WooCommerce's block Cart and Checkout
into the identity, plus the e2e that keeps it honest across WooCommerce upgrades. The
classic branch stays exactly as it is.

**What is already correct and must not be restated** (measured, ADR-009 finding 4): the
wrapper, section titles, field labels, totals rows, panels and product names already
inherit our tokens and our fonts in **both** schemes, because the block CSS leans on
`currentColor` and `inherit`. Writing rules for those would be noise that later reads as
intent.

**The actual work list** — the hardcoded surfaces, identical in light and dark today:

| # | Surface | Today | Target |
|---|---|---|---|
| 1 | text input / select / combobox | `#fff` bg, `rgb(43,45,47)` text, radius `4px` | `--background` / `--foreground` / `--border`, `--radius` |
| 2 | checkbox, radio | `#fff` bg, radius `4px` | same, plus our focus ring |
| 3 | place-order and other block buttons | `rgb(50,55,60)` bg | `--primary` / `--primary-foreground` |
| 4 | notice banners | `#fff0f0` bg, `#2f2f2f` text | `--destructive` / `--success` / `--warning` |
| 5 | radii generally | `0px` / `4px` | `--radius` |

Item 1–2 in the dark scheme is the one genuine **defect**, not a styling preference: a
white field with near-black text on an `oklch(13% …)` page.

## Ground rules for every task here

1. **Assert against `assets/dist`, never the source.** s12's most expensive mistake.
2. **Mirror specificity against the real vendor rule.** Open the pinned
   `assets/client/blocks/{cart,checkout}.css` from the WooCommerce 10.9.4 package and read
   the rule being overridden before writing the override. Never reason about specificity
   without the vendor rule in front of you — s12 shipped a "repair" that existed only in a
   comment. **No `!important`.**
3. **Both schemes, every time.** A rule that fixes light and leaves dark is half a fix.
4. **The e2e must fail with the fix reverted.** Mutation-verify each new assertion — this
   is what caught the comment-only repair. An assertion that passes both ways is worse than
   none, because it reads as coverage.
5. **Two branches stay honest.** Anything claimed for the block path gets checked against
   the classic path too, or the claim says which branch it covers.

## B0 — Fixtures: a cart and a checkout an e2e can actually reach (blocker, do first)

The block Checkout is entirely client-rendered — its server response has **zero `<input>`
elements** and an `is-loading` skeleton (ADR-009 finding 8). `/checkout/` also **302s to
`/cart/` when the cart is empty**, so a spec that just navigates there tests the cart page
by accident.

- Extend `tests/e2e-woo/global-setup.mjs` to guarantee the two block pages exist with the
  expected block content — do not assume `install_pages` ran, assert it.
- Add a helper that puts a product in the cart (`/?add-to-cart=<id>` is enough — no JS
  needed) and then waits for **real hydration**, i.e. for an actual field to exist, not for
  `networkidle` alone.
- **Seeding must be idempotent.** The existing gallery seeding is not (see B7); do not copy
  that pattern.

## B1 — The stylesheet, its entry, and its conditional enqueue

- `src/css/woo-blocks.css`, un-layered like `woo.css`, every rule scoped under
  `.wp-block-woocommerce-cart` / `.wp-block-woocommerce-checkout`.
- New Vite input; the manifest resolver in `inc/Assets.php` already handles extra entries.
- New `inc/Woo/BlockAssets.php`: enqueue only when the page actually contains one of the
  two blocks (`has_block()`). This is **deliberately different** from `woo.css`'s
  unconditional enqueue, and the docblock must say why: a product loop can appear on any
  page via shortcode or block, but Cart and Checkout are `"multiple": false` blocks on
  their own two pages.
- Tests: unit for the enqueue decision, integration that the handle is present on a page
  containing the block and absent on one that does not.
- **Verify in the built output** that the new bundle carries no Tailwind preflight — the
  s12 trap. `woo.css` currently builds to ~30.7 KB; a sudden ~45 KB means an `@import
  'tailwindcss'` crept in.

## B2 — Form controls (the dark-scheme defect)

Inputs, selects, comboboxes, checkboxes, radios. Focus states must survive **forced-colors
mode** — s12 shipped two P0s in exactly this area, both inside a fix: an invalid field lost
its focus indicator, and the repair for it then vanished under Windows High Contrast
because `box-shadow` is dropped there and `outline: none` suppressed the fallback. Use a
transparent `outline` plus a `forced-colors` block, the same shape `adapter/forms.css`
settled on.

## B3 — Buttons · B4 — Notices · B5 — Panels, totals and radii

Parallelisable once B1 lands. B4 must cover all four notice roles (error, success, info,
warning), not just the error banner that happens to be easy to trigger — s12's notice test
mounted only `.woocommerce-error` and therefore passed with its fix reverted.

## B6 — e2e for the block surfaces

`tests/e2e-woo/blocks.spec.mjs`. Both schemes, computed style, after hydration:

- a checkout text input's background follows `--background` in dark, not `#fff`;
- the place-order button follows `--primary`;
- radii follow `--radius`;
- a notice banner uses the destructive role;
- the cart page's totals and buttons match.

Every assertion mutation-verified. Where a check cannot be made honest, say so in the spec
rather than asserting something weaker that looks equivalent.

## B7 — Pay the recorded test debt while we are in this file

These are recorded in `next-session-promt.md` and are **not** shipped defects — they are
assertions that pass with their fix reverted, so they over-claim:

- `storefront.spec.mjs` gallery test: passes with the `li` specificity and `overflow` fixes
  reverted, because `flex-wrap: wrap` alone rescues it.
- the notice test: mounts only `.woocommerce-error`, so `border-top: 0` and `content: none`
  can both be reverted.
- the select test: passes with the gradient repair reverted (Woo's own SVG also yields a
  non-`none` `background-image`) and measures the Select2-hidden 1px native control instead
  of the visible surface.
- `global-setup.mjs` gallery seeding is **not idempotent** — the product is deleted and
  recreated with a new ID before cleanup, so every re-run orphans five media attachments.
  Its comment claims the opposite; fix the code and the comment.
- **Missing coverage for a shipped fix:** a page containing `[products]` must receive
  `woo.css` **and** the `data-cta` attribute. Needs a seeded page plus computed-style and
  attribute assertions.

## B8 — `prefers-reduced-motion` on the storefront

The CSS-reachable ones left: `blockUI.blockOverlay::before` / `.loader::before` spin, and
the Woo placeholder's opacity transition. The rest are jQuery animations
(`slideUp`/`slideDown`/`fadeTo`, the 1s scroll-to-notices) that CSS cannot stop — **say so
in the comment** rather than implying coverage.

## B9 — Gate

Full battery, one suite at a time (two wp-env configs contend badly for Docker), then Codex
critic **and** re-critic on the fixes. Four sessions running, every re-critic pass has
found defects inside the fixes — budget for that rather than hoping.

## Order

B0 → B1 → (B2 ∥ B3 ∥ B4 ∥ B5) → B6 → B9.
B7 and B8 are independent of the block work and can run alongside from the start.

## Related

- [[ADR-009-block-cart-checkout-styling]] — the decision and the measurements behind it
- [[ADR-008-single-visual-identity]] — the identity these surfaces join
- `docs/plans/2026-07-25-visual-identity.md` — T6, the classic storefront this extends
