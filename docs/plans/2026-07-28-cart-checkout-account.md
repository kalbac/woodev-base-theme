# Cart, checkout, account and receipt — the node-by-node gap and the plan

> Written s19 (28.07.2026) for [#42](https://github.com/kalbac/woodev-base-theme/issues/42).
> Source of truth: `docs/design/v2-mockup/woodev-base-identity.html` — `id="s-cart"` (lines
> 2364–2530), `id="s-checkout"` (2533–2669), `id="s-account"` (2672–2837), `id="s-receipt"`
> (2840–2883), the empty-cart panel inside `id="s-misc"` (3236–3253), and the mockup's own CSS
> at lines 369–376 (`.qty`) and 698–771 (every layout/panel class named below).
> Measured against **WooCommerce 10.9.4** as installed in
> `C:\Users\maksi\.wp-env\wp-env-woodev_base_theme-woo-de26a22e\woocommerce\`, read on disk.

## Method

Same as the #41 plan: walk the mockup node by node, record what WooCommerce's own markup gives
today, and give each node a verdict — **CSS**, **hook**, or **template override**. Override is
the last resort: it pins the theme to a Woo version and has to be re-audited on every major.

## The fork this issue runs into first: classic vs block

WooCommerce 10.x's `install_pages` creates **block-based** Cart and Checkout pages, and
[ADR-009](../adr/ADR-009-block-cart-checkout-styling.md) already measured what that costs: the
40+ inner blocks declare no design supports, `theme.json` reaches only the wrapper at (0,1,0),
and **not one byte of `woo.css` can match those pages**. `src/css/woo-blocks.css` (951 lines,
s15) covers the block branch to ADR-009's scope — controls, buttons, notices, panels, radii —
and that is where it stops by decision, not by omission.

**Everything in this plan targets the CLASSIC (shortcode) branch**, plus the three surfaces that
are classic on a default install regardless of the cart/checkout choice:

| Surface | Renderer on a default Woo 10.9.4 install | In scope here |
|---|---|---|
| `/cart/` | `woocommerce/cart` **block** | the classic `[woocommerce_cart]` branch only |
| `/checkout/` | `woocommerce/checkout` **block** | the classic `[woocommerce_checkout]` branch only |
| `/my-account/` (all endpoints) | `[woocommerce_my_account]` shortcode → `templates/myaccount/*` | **yes, fully** |
| `/checkout/order-received/{id}/` | `templates/checkout/thankyou.php` | **yes, fully** |
| empty cart | classic → `cart/cart-empty.php`; block → the block's own empty state | the classic branch only |

Why the classic branch is worth the work rather than a legacy afterthought: it is a first-party
supported option (the `[woocommerce_cart]`/`[woocommerce_checkout]` shortcodes and the
`woocommerce/classic-shortcode` block, `enum: ["cart","checkout"]`), it is the only branch that
holds progressive enhancement (the block checkout ships zero server-rendered `<input>`s —
ADR-009), and it is the branch every checkout-field and shipping-method extension hooks into.
The block branch stays at ADR-009's bound; this plan does not widen it.

## Measured before any CSS was written: `.woocommerce` is the BODY on one branch and a DIV on the other

`woo.css` scopes every rule under `.woocommerce`, and it is worth knowing exactly what that class is
on each page, because it is not the same element. Measured on the live `:8891` store:

| Page | Where the bare `woocommerce` class lives |
|---|---|
| `/shop/`, product archives, single product | the **`<body>`** — `wc_body_class()` adds it when `is_woocommerce()`. There is no `.woocommerce` div on the page at all |
| `/my-account/`, and any page carrying a Woo shortcode | a **`<div class="woocommerce">`** the shortcode wrapper emits, nested `div.wtb-layout > div.wtb-layout__content > article.wtb-entry > div.wtb-entry-content > div.woocommerce`. The body carries only `woocommerce-account woocommerce-page`, never the bare class (the same asymmetry ADR-009 recorded for the block checkout) |

**Consequence, and it is a trap rather than a curiosity: a rule that styles `.woocommerce`
*itself* — `display: grid`, a padding, a max-width — lands on `<body>` on every catalogue and
product page.** M1 wants the account page's shortcode wrapper to become a two-column grid; written
as a bare `.woocommerce { display: grid }` that also makes the shop's `<body>` a grid container.
Scope it to the wrapper that actually holds the account markup — `&:has( > .woocommerce-MyAccount-navigation )`
from inside the partial's own `.woocommerce { … }` block — rather than to a body class, so it also
holds when the shortcode sits on an arbitrary page (which is exactly what the F1 fixture does).

The notices wrapper is the first child of that div (`div.woocommerce > div.woocommerce-notices-wrapper`,
present and empty when there are no notices), so it needs `grid-column: 1 / -1` or it will occupy a
grid cell.

**Fixture consequence.** `tests/e2e-woo/global-setup.mjs` seeds the *block* Cart and Checkout and
asserts they carry block markup (`assertBlockPageExists`). Nothing in the store renders a classic
cart or checkout today, so **every cart/checkout node below is unverifiable against the current
fixture**. F1 lands first (see Task list).

## A. Cart — mockup `id="s-cart"`

Woo's real DOM, from `templates/cart/cart.php`: `woocommerce_before_cart` →
`form.woocommerce-cart-form` (containing `table.shop_table.shop_table_responsive.cart` whose
last row is `tr > td.actions` holding the **coupon form** and "Update cart") → `/form` →
`woocommerce_before_cart_collaterals` → `div.cart-collaterals` → `div.cart_totals` (heading,
totals table, `.wc-proceed-to-checkout`) → `woocommerce_after_cart`.

| # | Mockup node | What Woo gives today | Verdict |
|---|---|---|---|
| C1 | `.cart-layout` — `1fr 360px`, table left, order panel right | form and `.cart-collaterals` are **stacked siblings**; no grid parent exists | **hook + CSS** — open a `div.wtb-cart-layout` on `woocommerce_before_cart` and close it on `woocommerce_after_cart`. Those two hooks bracket exactly the form + collaterals, so the wrapper needs no template file |
| C2 | `woocommerce-info` "До бесплатной доставки не хватает 1 520 ₽" + «Дополнить заказ» | nothing | **out of scope** — reading a Free Shipping method's `min_amount` and doing cart-total arithmetic is store functionality, i.e. plugin territory for a wp.org theme. Stated rather than silently dropped; carded as an idea |
| C3 | 5 columns: remove · товар (thumb + name + variation) · цена · кол-во · сумма | 6 columns — Woo splits `td.product-thumbnail` out of `td.product-name`. Both cells are adjacent, so the rendered result is already "thumb then name" | **CSS** — narrow the thumbnail column, drop its inner padding, keep the header cell's `.screen-reader-text` |
| C4 | `.qty` — −/+ stepper around a mono number input | `global/quantity-input.php` renders `div.quantity > input.qty`. The theme's stepper buttons exist (`ProductPage::quantity_step_down/up`, hooked to `woocommerce_before/after_quantity_input_field`) but are **guarded to `is_product()`**, and their CSS is scoped `div.product form.cart .wtb-qty-step` | **hook + CSS** — widen the guard to the cart, widen the CSS scope. The stepper must dispatch a real `change` event so Woo's `cart.js` un-disables "Update cart" — verify on the page, not in the source |
| C5 | `.remove` — round icon button with an X glyph | `<a href="…" class="remove" aria-label="…">&times;</a>` — an HTML entity, not an icon; the round-button CSS already exists | **hook** — `woocommerce_cart_item_remove_link` filter swaps `&times;` for the theme's Lucide `x`, keeping the href, `class`, `aria-label` and every `data-*` attribute Woo puts there |
| C6 | «Продолжить покупки» / «Обновить корзину» cluster under the table; coupon **in the right panel** | coupon + "Update cart" share one `td.actions` inside the cart table; there is no "continue shopping" control | **hook + CSS**, with a **stated deviation**: the coupon stays in the actions row, styled as the mockup's `.coupon` row, and the actions row becomes a flex cluster with «Продолжить покупки» added on `woocommerce_cart_actions`. Moving the coupon into `.cart_totals` was rejected: its `apply_coupon` submit must post from inside `form.woocommerce-cart-form`, so relocating it means either overriding `cart.php` or shipping a duplicate field and `display:none`-ing Woo's — a real control hidden from sight is worse than a layout that differs |
| C7 | `.order-panel` — card, radius-xl, sticky | `.cart_totals` already gets exactly this (`woo.css`), sticky above 64rem | **done** |
| C8 | `.totals .row` / `.row.grand` | Woo's totals are a `<table>` of `tr.cart-subtotal` / `tr.order-total`; `woo.css` already flattens the padding and weights the total row | **done** — verify `tr.cart-discount` renders the discount in `--sale` (mockup line 2460) |
| C9 | full-width primary CTA «Оформить заказ» | `.wc-proceed-to-checkout .checkout-button` already `width: 100%` | **done** |
| C10 | lock icon + «Оплата на защищённой странице банка» under the CTA | nothing | **hook** — printed on `woocommerce_after_cart_totals` from a **Customizer setting defaulting to EMPTY**, the s17/s18 trust-badge pattern. No default copy: a payment claim the store cannot honour is worse than no line at all |
| C11 | mobile: header row hidden, each item a stacked card | Woo's `shop_table_responsive` + `woocommerce-smallscreen.css` already stack with `data-title` labels | **CSS** — match the mockup's rhythm; do not rebuild the mechanism |
| C12 | empty cart: 56px circle + bag icon, «В корзине пока пусто», lede, primary button | `wc_empty_cart_message` prints `p.cart-empty.woocommerce-info`, then `p.return-to-shop > a.button.wc-backward` | **hook** — `remove_action( 'woocommerce_cart_is_empty', 'wc_empty_cart_message', 10 )` and print ours in its place. Woo's own return-to-shop paragraph stays and is styled |

## B. Checkout — mockup `id="s-checkout"`

The mockup's four numbered sections (Контакты · Адрес · Доставка · Оплата) do not map onto Woo's
grouping, and `woo.css` already records the decision made in T6: Woo puts the shipping **method**
choice and the payment methods inside `#order_review`, not in the left column, and it splits
billing from shipping rather than merging both into one "contacts + address" block. The layout
below keeps Woo's real grouping (left = address forms, right = review + payment + place order)
and adopts the mockup's **numbered-heading treatment** for what actually is in the left column.

| # | Mockup node | What Woo gives today | Verdict |
|---|---|---|---|
| K1 | `.checkout-layout` — `1fr 400px` | `woo.css` already grids `form.checkout.woocommerce-checkout` and places `#customer_details` / `#order_review_heading` / `#order_review` | **done** |
| K2 | `.checkout-section > h3 .num` — numbered circle badge before each section title | three `<h3>`s exist in DOM order inside `#customer_details`: `.woocommerce-billing-fields h3`, `#ship-to-different-address`, `.woocommerce-additional-fields h3` | **CSS** — a CSS counter on `#customer_details` and a `::before` badge. No markup needed |
| K3 | info banner «Уже покупали у нас?» + button | `checkout/form-login.php` prints `.woocommerce-form-login-toggle` wrapping a `.woocommerce-info` with an `a.showlogin` | **CSS** — the existing notice recipe already covers the banner; the trailing link already floats right (`.woocommerce-info a { margin-left: auto }`). Style `a.showlogin` as a small button |
| K4 | notice icons (truck / user / check / info / alert) | suppressed on purpose — `woo.css` sets `content: none` on Woo's icon-font `::before` because the theme does not ship Woo's icon font | **CSS** — reinstate a real icon with a `mask-image` data URI per notice type (`--success` → check-circle, `--primary` → info, `--destructive` → alert). Cheap, and it is what the mockup draws |
| K5 | `.form-grid` — 2-column fields, `col-2` full-width rows | `woocommerce_form_field()` emits `p.form-row` with `.form-row-first` / `.form-row-last` / `.form-row-wide` | **CSS** — grid the `__field-wrapper` and map the three width classes onto it |
| K6 | `.payment-method` cards — radio, bold title, muted subtitle, price/icon right | `li.wc_payment_method` already gets the card + `:has(input:checked)` accent (`woo.css`). Woo hides `.payment_box` until the method is selected — the mockup shows every subtitle at once | **done**, with the collapsed-description behaviour left alone: it is Woo's functional default and unhiding it would need JS |
| K7 | shipping-method cards with name, ETA and price | `ul#shipping_method > li` inside `tr.shipping` in the review table's `tfoot` — bare radio + label | **CSS** — give each `li` the same card treatment as `.payment-method` |
| K8 | order panel: 40px plate thumb + name + `× N` + amount per row | `checkout/review-order.php`'s `td.product-name` has **no thumbnail** | **hook** — prepend the thumbnail through the `woocommerce_cart_item_name` filter on the checkout only. That filter also fires in `cart.php`, so the guard must hold in the `update_order_review` AJAX context too (`is_checkout()` alone may not — measure it) |
| K9 | terms checkbox + full-width «Подтвердить заказ» + lock note | `.woocommerce-terms-and-conditions-wrapper` and `#place_order` already styled | **done** + the same optional Customizer note as C10, printed on `woocommerce_review_order_after_submit` |
| K10 | `review-order.php`'s `<td>`s carry **no `data-title`** (unlike the cart's) | — | **CSS** — do not rely on `data-title` in the checkout panel's responsive rules |

## C. My account — mockup `id="s-account"`

| # | Mockup node | What Woo gives today | Verdict |
|---|---|---|---|
| M1 | `.account-layout` — `230px 1fr` | `nav.woocommerce-MyAccount-navigation` and `div.woocommerce-MyAccount-content` are stacked siblings inside the shortcode's `div.woocommerce`; the nav is styled and sticky but nothing grids the pair | **CSS** — grid the shortcode wrapper and give `.woocommerce-notices-wrapper` a full-width row (verify the wrapper's real position on the page first) |
| M2 | `.account-nav a svg` — a Lucide icon per section | `navigation.php` prints `<?php echo esc_html( $label ); ?>` — **escaped**, so no filter can inject markup into the label | **template override** — `woocommerce/myaccount/navigation.php`, reproducing both hooks and `wc_get_account_menu_item_classes()` verbatim and adding `Icons::svg()` keyed off the endpoint. The CSS-mask alternative was weighed and rejected: it would duplicate six Lucide paths as data URIs alongside the sprite that is meant to be their single source |
| M3 | greeting as a `woocommerce-message` banner with a «Отследить» button | `dashboard.php` prints two plain `<p>`s — a greeting and a "from your account dashboard you can…" paragraph the mockup does not have at all | **template override** — `woocommerce/myaccount/dashboard.php`. The mockup's dashboard is a different page, not a restyle of this one, and the alternative (hooking `woocommerce_account_dashboard` for the additions while `display:none`-ing the second paragraph) hides shipped content |
| M4 | `.dash-cards` — 3 metric cards | nothing | **template override** (same file as M3). Two of the mockup's three metrics are computable — orders in the last 12 months, orders currently in transit — and the third («бонусов на счету») is a loyalty programme, i.e. plugin territory. `wc_get_customer_total_spent()` gives an honest third card instead |
| M5 | «Последние заказы» table on the dashboard | Woo's dashboard has no order list | **template override** (same file as M3) — `wc_get_orders()` limited to 3, rendered as `table.shop_table` so it inherits every table rule already in `woo.css` |
| M6 | `.status-badge` pills — `completed` / `processing` / `pending` | the orders table prints the status as **plain localised text**; `woo.css` currently only bolds it, with a comment saying there is no class to hang a colour on. There is: `tr.woocommerce-orders-table__row--status-{status}` — and better, a per-column render hook | **hook** — `woocommerce_my_account_my_orders_column_order-status` renders the cell ourselves as `span.wtb-status-badge.is-{status}`, mapping Woo's statuses onto the mockup's three tones. This also retires the stale comment in `woo.css` |
| M7 | order card: breadcrumb · `Заказ № N` + status badge · `.receipt-meta` 4-up · items table with `tfoot` totals · two `.addr-card`s | `view-order.php` prints one `<p>` status sentence, then order notes, then `woocommerce_view_order` → `order/order-details.php` (+ `order-details-customer.php`) | **template override** — `woocommerce/myaccount/view-order.php` (25 lines). The hook-only route would have to abuse the `woocommerce_order_details_status` **string** filter to emit a meta grid; the override is the honest shape |
| M8 | `.addr-grid` / `.addr-card` — uppercase h4, address, «Изменить» button | `my-address.php` gives `div.woocommerce-Address > header.title > h2 + a.edit` inside `.woocommerce-Addresses.col2-set` | **CSS** — the card, the uppercase heading and the edit link as a secondary button |
| M9 | downloads table with a «Скачать» button per row | Woo's downloads table already carries per-row `a.button`; `woo.css` styles the file cell | **CSS** |
| M10 | account-details form as a `.form-grid` with a full-width password block | `form-edit-account.php` uses `.form-row-first` / `.form-row-last` / `.form-row-wide` plus a `<fieldset>` for the password change | **CSS** — the same width mapping as K5, plus `<fieldset>`/`<legend>` styling |
| M11 | login/register `.col2-set` split | already covered (`woo.css`, and the fixture enables registration for it) | **done** |

## D. Order received — mockup `id="s-receipt"`

| # | Mockup node | What Woo gives today | Verdict |
|---|---|---|---|
| R1 | `.receipt-hero` — 64px check circle, `h1` «Спасибо, заказ принят», lede with the email | `order-received.php` prints one `p.woocommerce-notice.woocommerce-notice--success.woocommerce-thankyou-order-received` — **classes nothing in the theme styles today**. The page's `<h1>` is the Checkout page's own title | **theme template + hook** — `page.php` passes `hide_entry_head` when `is_order_received_page()` (the same `$args` contract `front-page.php` already uses), and the hero is printed on `woocommerce_before_thankyou`, giving one `<h1>` on the page rather than two. The lede text comes from the `woocommerce_thankyou_order_received_text` filter, which is Woo's own hook for exactly that sentence |
| R2 | `.receipt-meta` — 4-up, uppercase label above a mono value | `ul.woocommerce-order-overview` with `<li>Label <strong>value</strong></li>`, up to 5 items (order, date, email, total, payment method); `woo.css` already renders it as a 4-column grid with the uppercase label and mono value | **CSS** — switch to `auto-fit` so the 5th item does not orphan a row, and confirm the mockup's rhythm |
| R3 | items table with a `tfoot` of Скидка / Доставка / Итого | `order/order-details.php` → `table.shop_table.order_details` with a real `tfoot`; the generic `table.shop_table` rules already cover it. Its `<td>`s carry **no `data-title`** | **CSS** — mind R3's missing `data-title` in the responsive rules |
| R4 | two `.addr-card`s | `order-details-customer.php` → `.woocommerce-column--billing-address` / `--shipping-address`, already carded by `woo.css`, but with a display-font `text-lg` heading where the mockup has a small uppercase `h4` | **CSS** |
| R5 | «Отследить заказ» / «Вернуться в каталог» button cluster | nothing | **hook** — on `woocommerce_thankyou` at a late priority: a primary link to the order's own `view-order` endpoint (only when the buyer is logged in and owns it) and an outline link to the shop |

## Overrides this plan adds, and the obligation that comes with them

Three, on top of the two the theme already ships (`content-product.php`,
`single-product/meta.php`):

| File | Lines in core | Why not a hook |
|---|---|---|
| `myaccount/navigation.php` | 40 | the label is `esc_html()`'d — no filter can put an icon in it (M2) |
| `myaccount/dashboard.php` | 34 | the mockup's dashboard is a different page, and the hook route needs `display:none` on shipped copy (M3–M5) |
| `myaccount/view-order.php` | 61 | the meta grid has no hook; the alternative abuses a string filter (M7) |

Each must reproduce every `do_action` and every filter the core file fires, in order, so a
third-party extension hooked into any of them keeps working. Re-audit all five overrides on
each WooCommerce major — that is the cost being accepted here.

## Breakpoints: the mockup's numbers are not adopted verbatim, and that is decided here

The mockup collapses `.cart-layout` / `.checkout-layout` / `.account-layout` to one column at
**860px**, turns `.account-nav` horizontal-scrolling there, un-sticks `.order-panel` there, and
hides `.shop_table thead` at **560px** (`woodev-base-identity.html` lines 921–931 and the
`@container vp` mirrors at 1057–1069).

This theme already ships **64rem (1024px)** as the breakpoint at which `.cart_totals` / `#order_review`
become sticky and the checkout becomes two-column, and **`min-width: 64rem`** is what the existing
e2e assertions are written against. Two grid systems for one job is the mistake `adapter/index.css`'s
own footer comment warns about, so the four surface partials use **64rem** for the column collapse and
the sticky/static switch, not 860px. The `thead` hiding stays with WooCommerce's own
`woocommerce-smallscreen.css` mechanism (768px, `shop_table_responsive` + `data-title`) rather than
being re-implemented at 560px — row C11.

State this in each partial's comment where it lands, so the next reader does not "fix" it back to the
mockup's number.

## Groundwork done before any surface rule was written

- **`src/css/woo.css` is now an index.** Its 2,503 rules moved verbatim to `src/css/woo/storefront.css`,
  and the four surfaces get one partial each: `woo/cart.css`, `woo/checkout.css`, `woo/account.css`,
  `woo/receipt.css`, imported in that order after `storefront.css`. **A later partial beats an earlier
  one at equal specificity**, which is what lets a surface file re-declare a shared rule
  (`table.shop_table`, the store notices, the generic Woo `.button`) for its own page without editing
  the shared one. Each partial opens its own `.woocommerce { … }` block, because `@import` is only
  valid at the top of a stylesheet and so cannot be pulled into an enclosing rule. Verified as a pure
  move: rebuilding produced a byte-identical bundle — same content hash in the filename
  (`woo-Ds4jgq5W.css`) and the same md5 (`8a204036063a3776a93ec745ce15433c`) — which, since comments
  are stripped in the production build, proves no rule, order or specificity changed.
- **Eleven Lucide icons vendored** (`scripts/copy-icons.mjs`, 19 → 30): `shopping-bag`, `lock`,
  `user`, `info`, `circle-check`, `triangle-alert`, `house`, `file-text`, `download`, `map-pin`,
  `log-out`.
- **T5 landed first**, because both surface tasks consume it: `cart_secure_note` and
  `checkout_secure_note`, both defaulting to empty, sharing the badge-line sanitizer with the s18
  product badges (renamed `sanitize_badge_line` / `parse_badge_line` — it serves four settings now,
  not two) and adding `lock` to the shared `FRONT_ICONS` whitelist. One defect was caught and fixed
  during implementation and is now pinned by a test: substituting `lock` for `check` *after* parsing
  cannot tell "no icon given" from "`check` given deliberately", so the default is threaded down into
  `sanitize_icon()` instead. Both directions mutation-verified.

## Task list

| # | Task | Depends on |
|---|---|---|
| F1 | **Fixture** — seed a `[woocommerce_cart]` and a `[woocommerce_checkout]` page (own slugs, so the block pages the existing specs assert on stay untouched), a downloadable product, and two orders for the seeded customer in different statuses (one `processing`, one `completed`) with billing + shipping addresses, so `/my-account/orders/`, `view-order` and the order-received URL all have real data | — |
| T1 | Cart — C1, C3–C6, C10–C12 | F1 |
| T2 | Checkout — K2–K5, K7, K8, K9-note, K10 | F1 |
| T3 | My account — M1–M10 | F1 |
| T4 | Receipt — R1–R5 | F1 |
| T5 | Customizer — the two optional secure-payment notes (C10, K9) as one setting each, sanitised, defaulting to empty | — |
| T6 | Gate — build, `tokens:check`, phpcs, phpstan L8, eslint, prettier, unit, integration, `e2e:woo`; then the Codex critic over the diff **and over the new tests**, then the re-critic | T1–T5 |

T1–T4 are parallelisable once F1 lands. Every visual claim is verified by reading **computed
style** on the rendered page, never markup — `docs/gotchas/source-order-only-wins-the-properties-you-redeclare.md`
is the reason, and `docs/gotchas/qa-gates-cover-less-than-they-claim.md` is the reason the tests
go to the critic too.

## Related

- [#42](https://github.com/kalbac/woodev-base-theme/issues/42) — the issue this closes
- [ADR-009](../adr/ADR-009-block-cart-checkout-styling.md) — bounds the block branch
- `docs/plans/2026-07-28-catalogue-and-product.md` — the #41 walk this copies the shape of
