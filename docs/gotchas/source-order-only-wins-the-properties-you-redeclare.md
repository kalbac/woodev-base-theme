# Source order only wins you the properties you re-declare

> Found s18 (28.07.2026) — two defects, both live on `main`, both visible in the first screenshot and
> invisible to phpcs, phpstan, unit, integration and the whole existing e2e suite.

## The trap

`src/css/woo.css` is deliberately un-layered and mirrors WooCommerce's own selector depth, so that a
rule of ours ties Woo's specificity and then **wins on source order** (our bundle enqueues after
Woo's). That reasoning is correct and it is written at the top of the file. What it quietly assumes
is that "winning" is a property of the *rule*.

It is not. The cascade resolves **per declaration**. A rule that ties on specificity and wins on
source order wins **only the properties it actually declares**. Every property the losing rule sets
that yours does not mention survives untouched — and a rule you have already decided you beat is a
rule you stop reading.

Both s18 defects are that sentence.

## 1. The sale badge that was a full-width red bar

WooCommerce ships two rules for the loop's sale flash:

```css
.woocommerce span.onsale                    { min-width: 3.236em; text-align: center; … }
.woocommerce ul.products li.product .onsale { top: 0; right: 0; left: auto; margin: -0.5em -0.5em 0 0 }
```

Ours declared `position`, `top`, `left`, `margin`, `padding`, colours and type — and not `right`.
So Woo's `right: 0` survived next to our `left: 0.75rem`, and an absolutely positioned box with
**both** insets set and `width: auto` stretches to fill the distance between them:

```js
getComputedStyle(badge)  // { left: "12px", right: "0px", width: "381px" }
```

Every card in the catalogue carried a red bar across its top. It read as a deliberate ribbon, which
is exactly why three review passes and four green suites went past it. `min-width: 3.236em` from the
first rule survived for the same reason and is now also reset.

## 2. The product tabs that stacked vertically

WooCommerce names its tab list `class="tabs wc-tabs"`. `.tabs` is **also Basecoat's tabs
component**, which contributes precisely two declarations to that element:

```css
.tabs { display: flex }
:is(.tabs:has(> [role="tablist"][aria-orientation="horizontal"]),
    .tabs:not(:has(> [role="tablist"][aria-orientation="vertical"]))) { flex-direction: column }
```

Woo's `<ul>` has no `[aria-orientation]` child, so it matches the `:not()` branch. Our rule
re-declared `display: flex` — which changed nothing, and *masked the collision*, because it made the
rule look like it owned the element's layout — and never touched the direction. The tab list
rendered as a vertical stack of full-width rows on every product page.

This is [[porting-a-mockup-inherits-its-class-names-and-loses-its-use-site]] arriving through a
different door: there the colliding class name came from the mockup, here it comes from
**WooCommerce**, which is a dependency we do not control and did not choose the class names of.

## What to do instead

- **When you override a positioned or laid-out element, declare the whole set, not the half you
  care about.** For absolute positioning that means all four insets (or an explicit `inset`); for a
  flex container, `display` *and* `flex-direction`; for a box, `min-width`/`min-height` as well as
  `width`/`height`. A property you leave out is a property someone else chose.
- **A redundant-looking declaration is evidence, not noise.** `display: flex` next to an element
  that is already flex is the shape of a rule written by reading the mockup rather than the
  computed style. Ask what else that other stylesheet set.
- **Read the losing rule in full before deciding you beat it.** In DevTools the loser's overridden
  properties are struck through and its surviving ones are not — that difference is the whole bug.
- **Pin the fix on computed style.** `tests/e2e-woo/catalogue.spec.mjs` asserts
  `getComputedStyle(list).flexDirection === "row"` and that the badge is narrower than half its
  card. A markup assertion sees neither defect, and both had flawless markup.
- **Grep the built bundle for a class name before you assume it is yours** — this theme shares one
  namespace with Basecoat *and* with WooCommerce:
  `grep -o "[^}{,]*\.NAME\b[^{}]*{[^}]*}" woodev-base-theme/assets/dist/assets/style-*.css`

## Related

- [[porting-a-mockup-inherits-its-class-names-and-loses-its-use-site]] — the same collision family, with the class name coming from the mockup instead of from a dependency
- [[tailwind-v4-layer-precedence]] — the other way an outside declaration wins over ours, at the layer level rather than the property level
- [[qa-gates-cover-less-than-they-claim]] — the parent pattern: green results that measured something other than what was claimed
