# M2a — WooCommerce storefront foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Bootstrap the WooCommerce layer and build the storefront (shop/archive + single product with the gallery), so a seeded demo store renders as Basecoat cards and a styled product page.

**Architecture:** A `Woodev\Theme\Base\Woo` layer, booted only when Woo is active, that degrades to nothing when it is not. The storefront is hooks + CSS, with exactly one template override (the product card). Assets load a single shared `woo` bundle on Woo contexts only. A separate wp-env environment carries Woo so the base theme keeps being tested without it.

**Tech Stack:** WooCommerce 10.9 (requires WP 6.9 — see note), PHP 8.1, Vite, Tailwind v4 adapter, PHPUnit, Playwright.

**Spec:** `docs/specs/2026-07-23-m2a-woo-storefront-design.md` — read it first.

---

## Verified Woo contracts — read from the installed WooCommerce 10.9.4, do not re-derive or guess

These were read from the plugin files in the container; they are facts, not guesses:

1. **WooCommerce 10.9.4 declares `Requires at least: 6.9`.** The theme's own floor stays 6.8 for the base; the Woo layer effectively inherits Woo's floor. Set `WC requires at least` / `WC tested up to` in `style.css` to match what is tested; do NOT lower the theme's `Requires at least` below 6.8.
2. **Product loop** — `templates/content-product.php` (`@version 9.4.0`) renders a fixed `<li <?php wc_product_class(); ?>>` wrapper. Everything inside is hooks:
   - `woocommerce_before_shop_loop_item` → `woocommerce_template_loop_product_link_open` (10) — opens a single `<a>` wrapping the whole item.
   - `woocommerce_before_shop_loop_item_title` → `woocommerce_show_product_loop_sale_flash` (10), `woocommerce_template_loop_product_thumbnail` (10).
   - `woocommerce_shop_loop_item_title` → `woocommerce_template_loop_product_title` (10).
   - `woocommerce_after_shop_loop_item_title` → `woocommerce_template_loop_rating` (5), `woocommerce_template_loop_price` (10).
   - `woocommerce_after_shop_loop_item` → `woocommerce_template_loop_product_link_close` (5), `woocommerce_template_loop_add_to_cart` (10).
   The single-`<a>`-wraps-everything default is why a `.card > header/section/footer` structure needs an override — the wrapper element and its order are fixed. **This is the one override.**
3. **Loop container** — the loop outputs `<ul class="products columns-N">` (from `loop/loop-start.php`, filterable via `woocommerce_product_loop_start`). Grid styling attaches to `ul.products` in CSS; **no override**.
4. **Single product** — `templates/content-single-product.php` (`@version 3.6.0`) is entirely hook-driven: `.summary.entry-summary` holds title/price/add-to-cart/meta via `woocommerce_single_product_summary`; the gallery is `woocommerce_before_single_product_summary` → `woocommerce_show_product_images` (20); tabs are `woocommerce_after_single_product_summary` → `woocommerce_output_product_data_tabs` (10). **No override** — CSS + hooks only.
5. **Product tabs** — `templates/single-product/tabs/tabs.php` (`@version 9.8.0`) emits `.woocommerce-tabs.wc-tabs-wrapper` › `ul.tabs.wc-tabs[role=tablist]` with `li[role=presentation] > a[role=tab][aria-controls]`, and `div.woocommerce-Tabs-panel[role=tabpanel][aria-labelledby]`. **The aria wiring and Woo's own `wc-tabs` JS are already present** — restyle via CSS to read as Basecoat tabs; do NOT override the template or reimplement the interaction.
6. **Page shell** — `woocommerce_before_main_content` carries `woocommerce_output_content_wrapper` (10) and `woocommerce_breadcrumb` (20); `woocommerce_after_main_content` carries `woocommerce_output_content_wrapper_end` (10). Remove the two wrapper actions and add ours to route Woo pages into `.wtb-container`/`.wtb-layout`.
7. **`is_woocommerce()` is false on cart/checkout/account.** The enqueue guard must be `is_woocommerce() || is_cart() || is_checkout() || is_account_page()`.

## File structure

| Path | Status | Responsibility |
|---|---|---|
| `.wp-env.woo.json` | create | wp-env env with Woo installed + activated, port 8891 |
| `tests/e2e-woo/global-setup.mjs` | create | Activate theme + Woo, run store setup, seed demo products |
| `tests/e2e-woo/storefront.spec.mjs` | create | Storefront e2e |
| `playwright.woo.config.mjs` | create | Woo e2e runner |
| `woodev-base-theme/inc/Woo/Woo.php` | create | Layer composition root |
| `woodev-base-theme/inc/Woo/Support.php` | create | Declared support + page-shell hooks |
| `woodev-base-theme/inc/Woo/Assets.php` | create | Conditional `woo` bundle enqueue |
| `woodev-base-theme/inc/Theme.php` | modify | Guarded boot of the Woo layer |
| `woodev-base-theme/woocommerce/content-product.php` | create | The one override — product card |
| `src/css/woo.css` | create | Storefront adapter CSS (grid, card, summary, tabs) |
| `vite.config.mjs` | modify | Add the `woo` Rollup input |
| `tests/php/Unit/Woo/*` | create | Unit tests for the layer |
| `tests/integration/Integration/Woo/*` | create | Integration tests (skipped when Woo absent) |
| `package.json` | modify | `wp:woo:*`, `e2e:woo`, `test:integration` already covers Woo integration via the tests env if Woo is there — see Task 6 |

---

## Task 1: The Woo e2e environment and demo store

**Files:** `.wp-env.woo.json`, `tests/e2e-woo/global-setup.mjs`, `playwright.woo.config.mjs`, `package.json`

- [ ] **Step 1: The env config**

`.wp-env.woo.json`:

```json
{
  "core": null,
  "phpVersion": "8.1",
  "themes": ["./woodev-base-theme"],
  "plugins": ["https://downloads.wordpress.org/plugin/woocommerce.zip"],
  "testsEnvironment": false,
  "port": 8891
}
```

wp-env installs the plugin but does not activate it (same trap as themes —
`docs/gotchas/wp-env-installs-themes-without-activating-them.md`); activation is
the global-setup's job.

- [ ] **Step 2: The global-setup**

`tests/e2e-woo/global-setup.mjs` — model on `tests/e2e/global-setup.mjs`'s
`execSync`+`npx wp-env run cli --config=.wp-env.woo.json wp …` style. It must,
idempotently:

- activate `woodev-base-theme`, then **assert** it is active (throw otherwise);
- activate `woocommerce`, then assert active;
- run Woo's install so pages exist: `wp wc --user=admin tool run install_pages` (or `wp option update woocommerce_db_version` + the shop-page setup — verify the exact wc-cli command against the installed Woo, `wp wc --help`);
- seed products with `wp wc product create --user=admin`: at least three simple products, one with a `sale_price` below `regular_price`, one with `stock_status=outofstock`. Give them known slugs so the spec can target them.

Because Woo state persists in the container, make every create idempotent (delete
by known slug first, like the blog global-setup does for posts).

- [ ] **Step 3: The Playwright config**

`playwright.woo.config.mjs`: `testDir: tests/e2e-woo`, `baseURL: http://localhost:8891`, `globalSetup: ./tests/e2e-woo/global-setup.mjs`, the `{ page }` fixture only. No `webServer` (production assets, not dev). Add `npm run e2e:woo` and `wp:woo:start`/`wp:woo:stop` scripts.

- [ ] **Step 4: Bring it up and confirm the store renders**

`npm run wp:woo:start`, run the global-setup once (or a throwaway `npx playwright test --config=playwright.woo.config.mjs --list`), then `curl -s -o /dev/null -w "%{http_code}" http://localhost:8891/shop/` → expect 200. Confirm `wp --config=.wp-env.woo.json plugin list` shows woocommerce active.

- [ ] **Step 5: Commit** — `test: woo e2e environment and seeded demo store`.

---

## Task 2: The layer bootstrap and declared support

**Files:** `inc/Woo/Woo.php`, `inc/Woo/Support.php`, `inc/Theme.php`, `tests/php/Unit/Woo/SupportTest.php`, `tests/integration/Integration/Woo/BootstrapTest.php`

- [ ] **Step 1: Failing unit test for Support**

`tests/php/Unit/Woo/SupportTest.php`, Brain\Monkey, mirroring `tests/php/Unit/SetupTest.php`. Assert `Support::setup()` calls `add_theme_support('woocommerce')` and the three gallery supports (`wc-product-gallery-zoom`, `-lightbox`, `-slider`). Assert `register()` hooks `setup` on `after_setup_theme` and the wrapper methods on the two content hooks (Task 3 adds the wrapper bodies; here just pin the registration).

- [ ] **Step 2: Run, watch it fail** — `composer test:unit` → class not found.

- [ ] **Step 3: Implement `Support`**

`inc/Woo/Support.php`, `namespace Woodev\Theme\Base\Woo;`, `declare(strict_types=1)`:

```php
final class Support {
	public function register(): void {
		add_action( 'after_setup_theme', [ $this, 'setup' ] );
	}

	public function setup(): void {
		add_theme_support( 'woocommerce' );
		add_theme_support( 'wc-product-gallery-zoom' );
		add_theme_support( 'wc-product-gallery-lightbox' );
		add_theme_support( 'wc-product-gallery-slider' );
	}
}
```

- [ ] **Step 4: Implement the layer root**

`inc/Woo/Woo.php`:

```php
final class Woo {
	public function register(): void {
		( new Support() )->register();
		( new Assets() )->register();   // Task 4
	}
}
```

Add `Assets` to the register call only once Task 4 exists; for this task, register just `Support` and add `Assets` in Task 4 (keep the commit compiling).

- [ ] **Step 5: Guard the boot in `Theme::boot()`**

```php
if ( class_exists( 'WooCommerce' ) ) {
	( new Woo\Woo() )->register();
}
```

- [ ] **Step 6: Integration test — the layer boots only with Woo**

`tests/integration/Integration/Woo/BootstrapTest.php`: if `! class_exists('WooCommerce')` → `markTestSkipped` (the base tests env has no Woo). When Woo is present, assert `current_theme_supports('woocommerce')` and `current_theme_supports('wc-product-gallery-zoom')` are true. (This suite only turns green in a Woo-carrying environment; the skip keeps the base tests env honest.)

- [ ] **Step 7: Run unit + integration** — `composer test:unit` green; `npm run test:integration` green (the Woo integration test skips, base env has no Woo).

- [ ] **Step 8: Mutation** — remove `add_theme_support('woocommerce')` from `Support::setup()`; the unit test must go red. Revert.

- [ ] **Step 9: phpcs, commit** — `feat(woo): layer bootstrap and declared support`.

---

## Task 3: The page shell

**Files:** `inc/Woo/Support.php`, `tests/php/Unit/Woo/SupportTest.php`

- [ ] **Step 1: Extend the failing test** — assert `register()` removes `woocommerce_output_content_wrapper`/`…_end` and adds the theme's own openers/closers on `woocommerce_before_main_content` / `woocommerce_after_main_content`.

- [ ] **Step 2: Implement in `Support`**

In `register()`:

```php
remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );
add_action( 'woocommerce_before_main_content', [ $this, 'open_wrapper' ], 10 );
add_action( 'woocommerce_after_main_content', [ $this, 'close_wrapper' ], 10 );
```

`open_wrapper()` echoes the theme's shell — the same `.wtb-container` / `.wtb-layout` / `.wtb-layout__content` a base template opens, so the storefront inherits container width and (where `Layout::has_sidebar()` applies) the sidebar column. `close_wrapper()` closes them. Escape any of our own strings; there is no dynamic content here beyond static wrapper markup.

Decide, and note in the code, whether Woo pages get the sidebar: `Layout::has_sidebar()` currently keys on blog/archive/single contexts and will be false on shop — that is the right default for v1 (a full-width storefront). If the shop should offer the sidebar, that is a `Layout` change to surface, not to slip in here.

- [ ] **Step 3: Run + mutation** — remove the `open_wrapper` add_action, unit test red. Revert.

- [ ] **Step 4: Browser check** — deferred to Task 7 (needs the store). Note it there.

- [ ] **Step 5: phpcs, commit** — `feat(woo): route storefront into the theme container`.

---

## Task 4: Conditional asset loading

**Files:** `src/css/woo.css`, `vite.config.mjs`, `inc/Woo/Assets.php`, `tests/php/Unit/Woo/AssetsTest.php`

- [ ] **Step 1: Create a minimal `src/css/woo.css`** so the build has an entry (Task 5 fills it):

```css
@import 'tailwindcss';
@source "../../woodev-base-theme/**/*.php";
/* Storefront adapter styles (spec §8). Tokens come from the active pack bundle
 * already on the page, so this layers on top and needs no pack of its own. */
```

Confirm the `@source`/tailwind setup matches how the pack entries are structured (`scripts/lib/packs-lib.mjs`) — the storefront CSS scans the same PHP for classes.

- [ ] **Step 2: Add the Vite input** — in `vite.config.mjs` `rollupOptions.input`, add `woo: 'src/css/woo.css'` alongside `app` and the pack entries.

- [ ] **Step 3: Failing unit test** — `tests/php/Unit/Woo/AssetsTest.php`, mirroring the base `AssetsTest`: mock `is_woocommerce()`/`is_cart()`/`is_checkout()`/`is_account_page()` and a manifest; assert the `woo` entry is enqueued on a Woo context and NOT enqueued off it. Use `RunInSeparateProcess` where a constant/global would leak.

- [ ] **Step 4: Implement `Woo\Assets`**

Hooks `wp_enqueue_scripts` (after the base Assets, default priority is fine). On `is_woocommerce() || is_cart() || is_checkout() || is_account_page()`, resolve the `woo` entry from the manifest via the base `Assets::read_manifest()` / `Assets::entry_file()` (already static) and `wp_enqueue_style( 'woodev-base-woo', … )`. Off a Woo context, enqueue nothing.

- [ ] **Step 5: Run + both mutations** — (a) force the context guard false → the "enqueues on shop" test red; (b) force it true → the "not enqueued off Woo" test red. Revert each.

- [ ] **Step 6: Wire `Assets` into `Woo::register()`** (the line deferred in Task 2).

- [ ] **Step 7: build, phpcs, commit** — `feat(woo): load the storefront bundle only on Woo pages`.

---

## Task 5: The product card override and storefront CSS

**Files:** `woodev-base-theme/woocommerce/content-product.php`, `src/css/woo.css`

- [ ] **Step 1: The override**

Copy `content-product.php` verbatim first (from the container path in the verified-contracts section), then restructure the `<li>` body into the `.card` vocabulary while calling the SAME hooks so ratings/price/sale-flash/add-to-cart keep working. Keep the `@version 9.4.0` line and add a note that this file is audited on each Woo major.

Shape (verify the exact hook calls against the copied original):

```php
<li <?php wc_product_class( 'wtb-product-card card', $product ); ?>>
	<?php do_action( 'woocommerce_before_shop_loop_item' ); // link open ?>
	<?php do_action( 'woocommerce_before_shop_loop_item_title' ); // sale flash + thumbnail ?>
	<header>
		<?php do_action( 'woocommerce_shop_loop_item_title' ); // title ?>
		<?php do_action( 'woocommerce_after_shop_loop_item_title' ); // rating + price ?>
	</header>
	<footer>
		<?php do_action( 'woocommerce_after_shop_loop_item' ); // link close + add to cart ?>
	</footer>
</li>
```

Note: the default `link_open`/`link_close` hooks wrap content in an `<a>` that spans from before-item to after-item-title-ish; confirm the wrapping does not cross the `<header>`/`<footer>` boundary in a way that produces invalid nesting. If it does, the clean fix is to move the link open/close via CSS-friendly hook re-priorities or to let the card be the link — surface this as a finding rather than shipping invalid markup. **Do not fight it with a regex or string rewrite.**

- [ ] **Step 2: Storefront CSS in `src/css/woo.css`**

- `ul.products` → the grid: reuse the `.wtb-post-grid` rule set (same 1→2→3 / sidebar cap) by applying those declarations to `ul.products`, or by adding `.wtb-post-grid` to the loop via the `woocommerce_product_loop_start` filter in `Support` — pick one and note why. CSS-only (styling `ul.products`) is fewer moving parts and matches the no-override policy for the container.
- `.wtb-product-card` → the §7 card look (it already carries `.card`, so mostly it inherits; add product-specific bits: price/rating spacing, the sale `.badge`).
- Sale flash → map Woo's `.onsale` span to a `.badge[data-variant]` look. Out-of-stock → a badge too.
- Single-product summary → `.summary` spacing, `.price` typography, `.btn` on `.single_add_to_cart_button`.
- Product tabs → style `.woocommerce-tabs`/`ul.wc-tabs`/`.wc-tab` to read as Basecoat tabs; the aria and Woo JS are already there, so this is presentation only.

Everything expressed against pack tokens (`var(--primary)`, `var(--radius)`, `var(--muted-foreground)`, …). Nothing here may need to beat a utility (adapter loses to utilities — `docs/gotchas/tailwind-v4-layer-precedence.md`); if a state override must win, it goes outside all layers like the base `states.css`.

- [ ] **Step 3: build** — `npm run build` exit 0.

- [ ] **Step 4: Browser (needs the store, :8891)** — reported in Task 7.

- [ ] **Step 5: phpcs, commit** — `feat(woo): product card override and storefront styles`.

---

## Task 6: e2e

**Files:** `tests/e2e-woo/storefront.spec.mjs`, plus any integration additions

- [ ] **Step 1: `storefront.spec.mjs`** against :8891 (`{ page }` fixture). Cover:
  1. `/shop/` renders `ul.products` as a grid (computed `grid-template-columns` track count > 1 at desktop) with `.wtb-product-card.card` items carrying `header`/`footer`.
  2. The on-sale product shows a sale `.badge`; the out-of-stock product shows its state.
  3. A single product page shows the gallery (`.woocommerce-product-gallery`), the summary with a `.btn` add-to-cart, and the tabs (`.woocommerce-tabs` with `[role=tablist]`).
  4. Clicking add-to-cart increments the cart (assert the cart count / a fragment updates — the cart *page* is M2b; here only that the button works).
  5. Dark scheme: the product card restyles (computed `background-color` differs), same runtime-toggle pattern as `components.spec.mjs`.

- [ ] **Step 2: Run** — `npm run e2e:woo` green.

- [ ] **Step 3: Mutation** — remove the `ul.products` grid rule from `woo.css`, rebuild, the grid-track assertion goes red. Revert, rebuild, green.

- [ ] **Step 4: Confirm base isolation** — `npm run e2e` (the Woo-free :8888 run) still green and unaffected; the base theme must not have changed behaviour. (~25 min.)

- [ ] **Step 5: Commit** — `test(woo): storefront e2e against the seeded store`.

---

## Task 7: Browser verification, gate, critic, PR

- [ ] **Step 1: Look at the storefront** at :8891 and report what you SAW (AGENTS.md — UI claims need browser evidence): the shop grid of cards, a single product with gallery + summary + tabs, light and dark. Screenshots or concrete description.

- [ ] **Step 2: Full gate**

```bash
composer phpcs
composer phpstan
composer test:unit
npm run test:js
npm run build
npm run test:integration
npm run test:integration:dev
npm run e2e
npm run e2e:dev
npm run e2e:woo
```

Record every count.

- [ ] **Step 3: Codex critic** — two focused chunks (PHP layer + override, then CSS + e2e), default profile `-c 'mcp_servers={}'`, foreground, prompt <15 KB, smoke-test first, tell it not to read `.claude/skills/**`, name out-of-chunk guards. Then re-critic the fixes; if a third round narrows on one unit, change the approach.

- [ ] **Step 4: Push, open PR, stop. Merging is Maksim's call.**

---

## Definition of done

1. Storefront renders as cards + styled single product; browser-verified.
2. The layer boots only with Woo active; the base theme unchanged with Woo absent (base e2e green).
3. Exactly one template override, carrying its source version, with an audit note.
4. Woo bundle loads only on Woo contexts; mutation-verified both directions.
5. Unit + integration (skipping cleanly without Woo) + Woo e2e green; grid and enqueue guards mutation-verified.
6. phpcs, phpstan L8, build green; i18n + escaping on every new surface.
7. Codex critic passed with a re-critic.
8. cart/checkout/account/store-notices and the Woo Customizer section remain out — M2b.
