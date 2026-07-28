# Catalogue and product page — the node-by-node gap and the plan

> Written s18 (28.07.2026) for [#41](https://github.com/kalbac/woodev-base-theme/issues/41).
> Source of truth: `docs/design/v2-mockup/woodev-base-identity.html`, `id="s-shop"` (lines 1899–2166)
> and `id="s-product"` (lines 2169–2361), plus the mockup's own CSS at lines 609–693.
> Measured against **WooCommerce 10.9.4** as installed in
> `C:\Users\maksi\.wp-env\wp-env-woodev_base_theme-woo-de26a22e\woocommerce\`.

## Method

The issue asks for the walk before the code, and for a verdict per node: **CSS**, **hook**, or
**template override** — override last, because it pins the theme to a Woo version and has to be
re-audited on every major. The table below is that walk. It was written against Woo's real
`includes/wc-template-hooks.php` and the real templates, not from memory.

Two things the baseline screenshots settle before anything is written (taken 28.07.2026 against
`b4c592c` on `:8891`, both saved out of tree):

- the catalogue is Woo's default `archive-product.php` with our card CSS on it — no rail, no
  chips, no catalogue header beyond the title, and the sale flash is Woo's full-width red bar
  rather than the mockup's pill;
- the product page's **tabs render stacked vertically**, i.e. the tablist is not laid out at all;
  there is no rating row, no savings badge, no stock pip, no quantity stepper, no `<dl>` meta and
  no gallery thumb column.

## What the store fixture does not have yet

`tests/e2e-woo/global-setup.mjs` seeds three products, no categories beyond `Uncategorized`, no
attributes, and no second page of results. Every catalogue node below is unverifiable against that
store. **F1 lands first**: a category tree, one global attribute with terms, and enough products to
fill a 3-column grid twice over.

## A. Catalogue (`archive-product.php`, `taxonomy-product-cat.php`)

| # | Mockup node | What Woo gives today | Verdict |
|---|---|---|---|
| A1 | `nav.breadcrumb` — `Главная / Каталог / Кухня`, `/` separator, the current crumb darker | `woocommerce_breadcrumb` on `woocommerce_before_main_content` 20 → `<nav class="woocommerce-breadcrumb">` with a `/` delimiter already; the last crumb is a bare text node | **hook** — `woocommerce_breadcrumb_defaults` wraps the delimiter in a span. **The `global/breadcrumb.php` override was planned and then dropped**: the only thing it bought was `aria-current` on the last crumb, which is a *non-interactive text node* — the ARIA practice puts `aria-current="page"` on the link for the current page, and there is no link here. The mockup's darker current crumb is reached instead by colouring the nav strongly and the anchors back to muted, which needs no template file and nothing to re-audit on a Woo major |
| A2 | `h1` + `.lede` description | `woocommerce_page_title()` in the archive template + `woocommerce_archive_description` | **CSS** |
| A3 | subcategory `.tag` chip row, right of the title | nothing — Woo renders child categories as *product-category tiles inside the loop*, not chips | **hook** — ours on `woocommerce_archive_description` 20, `get_terms()` children of the current term |
| A4 | `hr.divider` under the header | nothing | **CSS** |
| A5 | `.shop-layout` — `248px 1fr` grid, rail + results | `woocommerce_sidebar` is **removed** by `inc/Woo/Support.php` (v1 was full-width by design) | **hook** — restore the sidebar, wrap in a grid **only when the shop widget area has widgets**; full-width stays the default |
| A6 | `.filter-rail` — «Фильтры» head + «Сбросить»; subcategory checkboxes with counts; price min/max + «Применить»; colour swatches; availability | Woo core ships the widgets/blocks (Product Categories, Filter by Price, Filter by Attribute, Filter by Stock, Active Filters). It ships **no rail and no styling** | **hook + CSS** — register `sidebar-shop`, render the rail chrome, style Woo's own widget *and* block filter markup. Building a filter engine of our own is plugin territory and is not in scope |
| A7 | `.shop-toolbar` — result count + orderby `<select>` | `woocommerce_result_count` + `woocommerce_catalog_ordering` | **done** (`woo.css`) |
| A8 | `.chip-filters` — active filters as removable chips | `WC_Widget_Layered_Nav_Filters` / the Active Filters block emit a `<ul>` of `<a>`s | **CSS** |
| A9 | 3-up product grid inside the rail layout | `loop_shop_columns` default 4 | **hook** — 3 when the rail is active |
| A10 | card category eyebrow `.cat` | nothing | **template** — `woocommerce/content-product.php` already ours |
| A11 | sale badge reading `−24%`, top-left over the media | `woocommerce_show_product_loop_sale_flash` → `<span class="onsale">Sale!</span>`, currently a full-width red bar | **hook** — `woocommerce_sale_flash` filter for the percentage; **CSS** for the pill |
| A12 | `.wishlist` heart | nothing, and a wishlist is **plugin territory** | **out of scope**, stated rather than silently dropped |
| A13 | `.pagination` with `…` and a chevron on next | `woocommerce_pagination` → `paginate_links` with textual prev/next | **hook** (`woocommerce_pagination_args`) + **CSS** |
| A14 | mobile: «Фильтры» button, rail collapses | nothing | **markup + CSS** — `<details>`/`<summary>`, `display: contents` above the breakpoint, no JS |

## B. Product page (`single-product.php`)

| # | Mockup node | What Woo gives today | Verdict |
|---|---|---|---|
| B1 | breadcrumb **inside** the buy box, above the title | breadcrumb sits above the whole page on `woocommerce_before_main_content` | **hook** — on `is_product()`, move it to `woocommerce_single_product_summary` 1 |
| B2 | `.gallery` — 64px thumb column **left** of the main image | `woocommerce_show_product_images` → flexslider, thumbs **below** | **CSS** (grid reorder; already partly present) |
| B3 | sale badge in the buy box, `−24% до 31 марта` | `woocommerce_show_product_sale_flash` on `woocommerce_before_single_product_summary` 10, over the gallery | **hook** — move into the summary; percentage via the same `woocommerce_sale_flash` filter as A11 |
| B4 | `.rating-row` — stars, `4,8 · 126 отзывов`, `·`, SKU in mono | `woocommerce_template_single_rating` → `.woocommerce-product-rating`; the SKU lives in `.product_meta` far below | **hook + CSS** — append the SKU to the rating row |
| B5 | `.price-block` — `.now`, `<del>`, «Экономия N ₽» badge | `<p class="price"><del>…</del><ins>…</ins></p>` | **hook** — savings badge after the price; **CSS** |
| B6 | `.short-desc` | `woocommerce_template_single_excerpt` 20 | **CSS** |
| B7 | `.stock--in` with a pip and a real count | `single-product/stock.php` → `<p class="stock in-stock">12 in stock</p>`, *inside* the add-to-cart form | **CSS** for the pip; position stays Woo's |
| B8 | `.qty` — −/+ around the number input | `global/quantity-input.php` prints the input alone — but fires `woocommerce_before_quantity_input_field` and `woocommerce_after_quantity_input_field` | **hook** (both exist, verified in the installed template) + a small JS stepper |
| B9 | trust badges («Завтра, если заказать до 18:00», «Гарантия 2 года») | nothing | **Customizer** — two settings, default EMPTY, section self-suppresses (the front-page pattern) |
| B10 | `dl.product-meta` — Артикул / Категория / Метки | `single-product/meta.php` → `<div class="product_meta"><span class="sku_wrapper">…` | **override** — the `<dl>` cannot be reached by CSS (the label is a bare text node and `posted_in` interleaves `, ` text nodes with links, so anonymous grid items break it). 30-line template, `@version 9.7.0` |
| B11 | tabs — real `aria-selected`, arrow-key roving focus | `tabs/tabs.php` already emits `role="tablist"/"tab"/"tabpanel"`, `aria-controls`, `aria-labelledby` — **no `aria-selected`**, no key handling; and the tablist is unstyled, hence the vertical stack | **CSS** + a small progressive-enhancement JS module. No override |
| B12 | «Характеристики» spec table | `product-attributes.php` → `table.shop_attributes` under Additional Information | **CSS** |
| B13 | reviews list + form | native | **CSS** |
| B14 | «Похожие товары» with a «Вся категория» link | `woocommerce_output_related_products` → `<section class="related products"><h2>` | **CSS**; the link is **out of scope** (no defensible target for a multi-category product) |

## Order of work

```
F1  fixtures (categories, attribute, ~10 products)      ← blocks everything visual
├─ A  catalogue chrome      A1 A2 A3 A4 A10 A11 A13
├─ R  filter rail           A5 A6 A8 A9 A14
└─ P  product page          B1 … B14
G   gate: phpcs/phpstan/eslint/prettier/tokens, unit, integration, e2e:woo, browser pass
C   Codex critic inline on stdin at high effort, then re-critic the fixes
```

## Defects found by reading computed style, before writing a line of the new work

Both were live on `main`, both had survived every suite and every review pass, and both are the
same shape: **our rule declared the properties someone thought about, and a property nobody
declared decided the layout.**

| Where | What was measured | Cause |
|---|---|---|
| Product tabs, every product page | `getComputedStyle(ul.tabs).flexDirection === "column"` — the tab list rendered as a vertical stack of full-width rows | WooCommerce names its tab list `class="tabs wc-tabs"`, and `.tabs` is **also Basecoat's tabs component**, which contributes `display:flex` **and** `flex-direction:column`. `woo.css` re-declared `display:flex` (changing nothing, and masking the collision) and never touched the direction |
| Sale badge, every catalogue card | `.onsale` computed `right: 0px` alongside our `left: 0.75rem`, `width: 381px` — a full-width red bar instead of a pill | Woo's `.woocommerce ul.products li.product .onsale` sets `top/right/left/margin` at the *same specificity* as ours. Ours won on source order for the three properties it re-declared; `right: 0` survived, and an absolutely positioned box with both insets set and `width:auto` stretches between them |

Same family as [[../gotchas/porting-a-mockup-inherits-its-class-names-and-loses-its-use-site]], arriving
through WooCommerce's class names rather than the mockup's. Both fixes are pinned on **computed
style** in the Woo e2e, not on markup.

## The critic gate (s18)

Eight chunks plus four re-critics, run the documented way — whole prompt inline on **stdin**,
NO-TOOLS preamble, foreground, explicit `model_reasoning_effort="high"`
([`codex-critic-needs-inline-stdin-and-explicit-effort`](../gotchas/codex-critic-needs-inline-stdin-and-explicit-effort.md)).
Token counts are recorded because a cheap pass is indistinguishable from a real one by its
verdict alone: chunk 1's first run cost **8.5k** and was re-run at **21.9k**, where it came back
clean on its own terms.

| Chunk | Verdict | Tokens |
|---|---|---|
| `Catalogue.php` + `FilterRail.php` + `Woo.php` | 1 × P2, then CLEAN on re-run | 8.5k → 21.9k |
| `ProductPage.php` + `Support.php` + `Assets.php` + `meta.php` | CLEAN | 21.9k |
| templates + Customizer + `woo.js` | 1 × P2 | 23.1k |
| `woo.css` | **1 × P1** | 26.6k |
| e2e guards | **4 × P1** | 23.6k |
| PHP unit tests | 1 × P1 (mechanism disproved) + 1 × P2 | 25.9k |
| store fixture | **1 × P1** + 1 × P2 | 21.6k |
| remaining tests | CLEAN | 16.7k |
| re-critic ×4 (each round's fixes) | all CLEAN | 17.7k / 9.5k / 8.8k / 15.1k |

**The most valuable group was the four P1s against the guards themselves** — the assertions
meant to prove the work. One had been vacuous since it was written: the reset link's "not the
primary button" check compared a computed `backgroundColor` (`rgb(…)`) against the raw
`--primary` token text, two strings that can never be equal, so a solid primary button would
have passed the test written to catch exactly that. The sale-badge geometry checks passed for a
`display: none` badge (a 0×0 box is narrower than half a card), and the pagination "accessible
name" check asserted a non-empty `.sr-only` element rather than an accessible name.

**One finding was rejected on measurement, not on argument.** The critic said `ProductPageTest`
would fail run alone because it mocks `WC_Product` without loading the shared double. It does
not — 19/19 alone, and green under `--order-by=reverse`, because PHPUnit includes every test
file before running any test and Mockery defines a missing class itself. The *coupling* it
pointed at was real, so the double moved into the unit bootstrap anyway.

**The P1 in `woo.css` is the one no screenshot would have found**, because every screenshot
taken this session was of the shop page or a product, never a category archive at phone width.
`grid-template-columns: minmax(0, 1fr) auto` sizes the chip track to its max-content, and
`flex-wrap` does not constrain a track while it is being sized: at 320px the chips took 249px
and the title's track was left at **0px** — measured `gridTemplateColumns: "0px 249px"`. The
page did not overflow, so a `scrollWidth` assertion would have stayed green while the `<h1>`
wrapped one letter per line down the left edge.

## Rules carried in from the gotchas

- **Grep the built bundle before porting a class name.** `.tag`, `.chip`, `.stock`, `.body`,
  `.cat`, `.now`, `.save`, `.n`, `.sw` are all short enough to collide with Basecoat. Anything
  that collides goes into the `wtb-` namespace, and the pin is on **computed style**, not markup.
- **Screenshot early.** Both s17 defects were invisible to every suite and visible in the first
  screenshot.
- **`woo.css` stays un-layered and `.woocommerce`-scoped** — see its header. New selectors mirror
  Woo's own depth, they do not out-specify it by inventing extra ancestors.
- Every template override carries the upstream `@version` and a note saying what it changed.

## Related

- [[../gotchas/porting-a-mockup-inherits-its-class-names-and-loses-its-use-site]]
- [[../gotchas/qa-gates-cover-less-than-they-claim]]
- [ADR-009](../adr/ADR-009-block-cart-checkout-styling.md) — bounds what is reachable on the block cart/checkout (not this plan, but the neighbouring one)
