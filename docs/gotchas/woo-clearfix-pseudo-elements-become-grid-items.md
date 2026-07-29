# WooCommerce's clearfix pseudo-elements become grid items the moment you grid their parent

> Hit **three times** on three different elements — s10 (`ul.products`), s15 (`.col2-set`), s19
> (`.woocommerce` itself and `ul.order_details`). Every time the symptom was "the columns render
> stacked" and every time the markup was flawless.

## The trap

WooCommerce clearfixes a lot of its own containers with the classic float hack:

```css
.woocommerce ul.products::after,  .woocommerce ul.products::before  { content: " "; display: table }
.woocommerce .col2-set::after,    .woocommerce .col2-set::before    { content: " "; display: table }
.woocommerce-account .woocommerce::after, .woocommerce-account .woocommerce::before { content: " "; display: table }
.woocommerce ul.order_details::after, .woocommerce ul.order_details::before { content: " "; display: table }
```

A generated pseudo-element with `content` set is a real box. **Grid does not skip boxes it did not
expect**: turn the parent into `display: grid` and both pseudo-elements become GRID ITEMS, in DOM
order — `::before` first, the real children next, `::after` last.

With two explicit column tracks and default row-flow auto-placement that shifts everything by one
cell:

| Cell | What lands there |
|---|---|
| row 1, col 1 | `::before` (empty) |
| row 1, col 2 | the first real child |
| row 2, col 1 | the second real child |
| row 2, col 2 | `::after` (empty) |

So on the My Account page the navigation rendered top-right and the content bottom-left — *stacked*,
in a layout whose `grid-template-columns` was measurably correct. On the receipt, a 5-item
`repeat(auto-fit, …)` overview split into four-in-a-row plus one orphan on its own line.

## Why it keeps getting missed

- The parent's own computed `gridTemplateColumns` is right, so a test asserting the track list passes.
- The children's classes are right, so a markup assertion passes.
- `element.children` does not include pseudo-elements, so a "count the columns" check passes.
- It is only visible in the rendered box positions — or in a screenshot.

## What to do

**Before gridding any WooCommerce container, kill its clearfix.** `display: none`, not
`content: none`: removing the box is what takes it out of the grid.

```css
.col2-set::before,
.col2-set::after {
  display: none;
}
```

Match the vendor's specificity. On the account wrapper the vendor rule is
`.woocommerce-account .woocommerce::before` — **(0,2,1)**, both pseudo-elements scoped by the body
class (there is no unscoped `.woocommerce::after` rule; s19 wrote one into a comment and the critic
caught it). `.woocommerce:has(> .woocommerce-MyAccount-navigation)::before` ties that at (0,2,1),
because `:has()` contributes no specificity of its own — it takes the specificity of the most
specific selector in its argument list — and then wins on source order.

**Pin it with a geometry assertion, not a track-list one.** The honest probe is that the real
children share a row:

```js
const tops = [...el.children].map((c) => Math.round(c.getBoundingClientRect().top));
expect(new Set(tops).size).toBe(1);
```

**And grep the vendor CSS for `::before` on the element before you grid it**, rather than
discovering this a fourth time:

```
grep -o "[^{}]*SELECTOR::\(before\|after\)[^{}]*{[^}]*}" \
  ~/.wp-env/wp-env-*/woocommerce/assets/css/woocommerce.css
```

## Related

- [[source-order-only-wins-the-properties-you-redeclare]] — the sibling failure: your rule ties the vendor's and still loses every property it forgot to declare
- [[qa-gates-cover-less-than-they-claim]] — why the track-list assertion passed while the layout was broken
- [[commerce-pages-inherit-the-prose-reading-measure]] — the other s19 layout defect that only a rendered page showed
