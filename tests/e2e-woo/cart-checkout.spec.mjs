// tests/e2e-woo/cart-checkout.spec.mjs
//
// The classic cart and checkout (#42, plan section A rows C1, C3, C4-CSS, C6,
// C10, C11, C12 and section B rows K2, K3, K4, K5, K7, K8-CSS, K10). CSS-only
// scope: src/css/woo/cart.css and src/css/woo/checkout.css — this file guards
// THOSE rules, never the PHP/template contract, which is already merged.
//
// Every assertion reads COMPUTED STYLE or GEOMETRY, never markup — the whole
// point per docs/gotchas/source-order-only-wins-the-properties-you-redeclare.md
// — and every comparison is measurement-against-measurement, never against a
// raw token/selector string, per
// docs/gotchas/qa-gates-cover-less-than-they-claim.md. Conventions follow
// catalogue.spec.mjs / account-receipt.spec.mjs: the { page } fixture only
// (never browser.newPage() — docs/gotchas/playwright-browser-newpage-skips-config.md).
//
// `classicPages.cart`/`.checkout` render on pages that are deliberately NOT
// the pages `is_cart()`/`is_checkout()` see (fixtures.mjs's own docblock) —
// that is expected here, not a defect to chase.
//
// Every test starts from a KNOWN cart, not whatever the shared customer
// account accumulated: `resetCartWith()` empties it through the real
// remove-link flow first, then adds exactly what the test needs. An earlier
// version of this file called `login()` + `addToCart()` per test with no
// reset, which is flaky by construction — the account had accumulated items
// from manual probe runs across the session, and at least one failure
// (the qty-stepper increment test asserting the input starts at `"1"`) was
// almost certainly this, not a CSS defect.
//
// Defects found by reading probe screenshots and computed style while
// writing this file, none visible from the markup alone, all now fixed in
// src/css/woo/cart.css and re-verified below:
//
//   1. C1 — `woocommerce_output_all_notices()` is hooked to
//      `woocommerce_before_cart` at priority 10, AFTER
//      inc/Woo/Cart.php's `open_layout()` at priority 5, so
//      `.woocommerce-notices-wrapper` (printed unconditionally, empty or
//      not) landed as a THIRD child of `.wtb-cart-layout` and was
//      auto-placed into a grid cell, pushing the table and the totals panel
//      apart onto a second, mostly-empty row.
//   2. C1/C7 — Woo's own `.cart-collaterals .cart_totals{float:right;
//      width:48%}` survives the parent becoming a grid item (a grid item
//      ignores `float` but not an explicit `width`) — the exact trap
//      storefront.css's own `.col2-set .col-1/.col-2` comment documents,
//      landing on a different selector this plan's own C1 row had already
//      flagged and which the first pass missed implementing.
//   3. C6 — a first version set `display: flex` directly on `td.actions` to
//      cluster the coupon/buttons. A table cell cannot be simultaneously
//      `display: table-cell` at the outer level and `display: flex` at the
//      inner one, so the cell was stripped from table layout participation
//      and shrank to its content's size (205.78px) instead of spanning its
//      `colspan="6"` (692.59px) — confirmed by measuring the cell width
//      directly. Rewritten with floats, which a table cell contains without
//      a clearfix.
//
// A fourth, non-CSS defect surfaced only once this file itself was reviewed:
// the FIRST version of the K2 assertions read
// `getComputedStyle(el, '::before').content` expecting `'"1"'`/`'"2"'` and
// got back the literal string `"counter(wtb-checkout-section)"` instead. Per
// the CSS Content spec, a counter's COMPUTED value is the unresolved
// `counter()` function notation — the actual digit is a paint-time detail
// with no CSSOM-readable path. That assertion could not have passed on
// correct CSS OR broken CSS; it was checking something JS cannot see. Fixed
// below by asserting what the badge's box CAN prove (size, colour, radius)
// and citing the screenshot that shows the digit itself
// (`probe-k2-out/checkout-badges.png`, captured alongside this fix).
//
// Every assertion group below states which mutation was used to confirm it
// fails red before the fix, and is restored (not left as a comment guess).
import { expect, test } from '@playwright/test';

import { loadFixtures } from './fixtures.mjs';

// Playwright's 30s default is not enough for this file, and the reason is
// latency, not anything under test: every test logs in through the real classic
// form (two navigations) and most then reset the cart by clicking through remove
// links, and on this wp-env container a page load costs 8-12s once it has been
// up a while (a documented degradation — see next-session-promt.md's environment
// notes). The same bump for the same measured reason is in
// account-receipt.spec.mjs, where a correct four-navigation test failed
// reproducibly at exactly 30s. Raised here rather than in
// `playwright.woo.config.mjs` so the other suites keep the tighter default.
test.describe.configure({ timeout: 120_000 });

const CUSTOMER = { username: 'wtb-e2e-customer', password: 'WtbE2eCustomer!1' };
const fixtures = loadFixtures();
const CART_URL = `/?page_id=${fixtures.classicPages.cart}`;
const CHECKOUT_URL = `/?page_id=${fixtures.classicPages.checkout}`;

/**
 * Log in as the seeded customer through the real classic login form.
 *
 * Identical to account-receipt.spec.mjs's own `login()` — there is no
 * different, more-robust pattern to adopt from storefront.spec.mjs (it never
 * logs a customer in at all). The one retry below is standard e2e hardening
 * for a real network round-trip, not a workaround for a known CSS/JS defect:
 * a wp-env container under load from a long sequential run can occasionally
 * miss one login. If it fails twice, that is a real finding, not
 * flakiness, and this throws rather than swallowing it.
 */
async function login(page) {
  for (let attempt = 1; attempt <= 2; attempt += 1) {
    await page.goto('/my-account/', { waitUntil: 'domcontentloaded' });

    // ALREADY LOGGED IN is the common case, not the exception, and getting
    // this wrong cost a full 16-minute run: six tests failed with
    // `page.fill: waiting for locator('#username')` timing out, every one of
    // them a test that reached `login()` twice (directly, and again through
    // `resetCartWith()`). On the second call `/my-account/` renders the
    // DASHBOARD — there is no `#username` on it at all — so the helper sat
    // waiting for a field that will never exist while the session it was
    // asked to establish was already established. Checking first makes the
    // helper idempotent, which is what every caller assumed it was.
    if ((await page.locator('.woocommerce-MyAccount-navigation').count()) > 0) {
      return;
    }

    await page.fill('#username', CUSTOMER.username);
    await page.fill('#password', CUSTOMER.password);
    await page.click('button[name="login"]');
    await page.waitForLoadState('domcontentloaded');
    try {
      await expect(page.locator('.woocommerce-MyAccount-navigation')).toBeVisible({
        timeout: 10_000,
      });
      return;
    } catch (error) {
      if (attempt === 2) throw error;
    }
  }
}

/** Add a product to the logged-in customer's cart via the classic add-to-cart query var. */
async function addToCart(page, productId, quantity = 1) {
  await page.goto(`/?add-to-cart=${productId}&quantity=${quantity}`, {
    waitUntil: 'domcontentloaded',
  });
}

/**
 * Empty the cart through the REAL remove-link flow — clicking each
 * `a.remove` in turn — rather than a guessed query var. `?empty-cart=1` was
 * tried first and is NOT a real WooCommerce mechanism (unverified against
 * source, and it measured as a no-op: `.wtb-cart-empty` never appeared).
 * `expect(...).toHaveCount()` polls rather than a fixed sleep, which is what
 * Woo's own AJAX removal (cart.js) needs — the row leaves the DOM
 * asynchronously, not on click.
 */
async function emptyCart(page) {
  // RE-NAVIGATE each iteration rather than clicking through a list captured
  // once. The first version counted the links, then clicked and waited for the
  // count to drop, and it failed reproducibly on the LAST item: 33 polls, the
  // locator resolving to 1 element every time. Woo's remove control is a plain
  // link carrying a per-request `_wpnonce`, and `cart.js` may or may not
  // intercept it with AJAX depending on what is enabled — so a click can either
  // navigate or mutate in place, and a stale page's nonce can be rejected
  // outright. Reloading the cart at the top of every pass makes the DOM the
  // single source of truth for "is it empty yet" and hands each click a fresh
  // nonce, at the cost of one extra page load per item.
  for (let guard = 0; guard < 25; guard += 1) {
    await page.goto(CART_URL, { waitUntil: 'domcontentloaded' });
    const removeLinks = page.locator('td.product-remove a.remove');

    if ((await removeLinks.count()) === 0) {
      return;
    }

    await removeLinks.first().click();
    // Resolves immediately when the click was handled in place rather than by
    // navigating, so this covers both mechanisms without a fixed sleep.
    await page.waitForLoadState('domcontentloaded');
  }

  throw new Error(
    '[cart-checkout] the cart still had items after 25 removal passes — this is a real finding, ' +
      'not flakiness: either the remove link stopped working or something is re-adding items.',
  );
}

/**
 * The one entry point every test below uses to reach a KNOWN cart state:
 * log in, empty whatever the shared account accumulated, then add exactly
 * the items this test needs. `items` is `[{ id, qty }]`; an empty array
 * leaves the cart genuinely empty (C12's own case).
 */
async function resetCartWith(page, items) {
  await login(page);
  await emptyCart(page);
  for (const { id, qty } of items) {
    await addToCart(page, id, qty ?? 1);
  }
}

test.describe('cart layout (C1)', () => {
  test('the table and totals panel sit in two tracks on desktop, one below 64rem, and the notices banner never steals a column', async ({
    page,
  }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await resetCartWith(page, [{ id: fixtures.products.simple }]);
    await page.goto(CART_URL, { waitUntil: 'domcontentloaded' });

    // MUTATION (confirmed red, then restored): commented out
    // `.wtb-cart-layout > .woocommerce-notices-wrapper { grid-column: 1 / -1 }`
    // in cart.css. The notices banner (printed unconditionally by
    // `woocommerce_output_all_notices()`, priority 10 on `woocommerce_before_cart`,
    // after `open_layout()`'s priority 5) then auto-placed into row 1's first
    // track and the assertion below — `formLeft` equal to the layout's own
    // left edge — failed: the form was pushed into row 1's SECOND track
    // instead, measuring flush with the layout's right half.
    const desktop = await page.evaluate(() => {
      const layout = document.querySelector('.wtb-cart-layout');
      const notices = layout.querySelector(':scope > .woocommerce-notices-wrapper');
      const form = layout.querySelector(':scope > form.woocommerce-cart-form');
      const collaterals = layout.querySelector(':scope > .cart-collaterals');
      const layoutBox = layout.getBoundingClientRect();
      return {
        columnCount: getComputedStyle(layout).gridTemplateColumns.trim().split(/\s+/).length,
        noticesWidth: Math.round(notices.getBoundingClientRect().width),
        layoutWidth: Math.round(layoutBox.width),
        formLeft: Math.round(form.getBoundingClientRect().left),
        layoutLeft: Math.round(layoutBox.left),
        formTop: Math.round(form.getBoundingClientRect().top),
        collateralsTop: Math.round(collaterals.getBoundingClientRect().top),
      };
    });

    expect(desktop.columnCount).toBe(2);
    // The notices banner spans the FULL layout width, not one track.
    expect(desktop.noticesWidth).toBe(desktop.layoutWidth);
    // The form sits at the layout's own left edge (track 1), not shifted
    // into track 2.
    expect(desktop.formLeft).toBe(desktop.layoutLeft);
    // Form and totals panel share the SAME row — the notices-wrapper bug
    // pushed them onto two different rows.
    expect(desktop.formTop).toBe(desktop.collateralsTop);

    await page.setViewportSize({ width: 800, height: 900 });
    await page.reload({ waitUntil: 'domcontentloaded' });
    const narrow = await page.evaluate(
      () => getComputedStyle(document.querySelector('.wtb-cart-layout')).gridTemplateColumns,
    );
    expect(narrow.trim().split(/\s+/).length).toBe(1);
  });

  test('the totals card fills its own column instead of Woo float/width', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await resetCartWith(page, [{ id: fixtures.products.simple }]);
    await page.goto(CART_URL, { waitUntil: 'domcontentloaded' });

    // MUTATION (confirmed red, then restored): commented out
    // `.cart-collaterals .cart_totals { float: none; width: auto }` in
    // cart.css. Woo's own `.cart-collaterals .cart_totals{float:right;
    // width:48%}` then resumed governing the width, and the ratio below
    // dropped to ~0.48 (measured 0.48 exactly) instead of being within a
    // few px of 1.
    const ratio = await page.evaluate(() => {
      const collaterals = document.querySelector('.cart-collaterals');
      const totals = document.querySelector('.cart_totals');
      return totals.getBoundingClientRect().width / collaterals.getBoundingClientRect().width;
    });

    expect(ratio).toBeGreaterThan(0.95);
  });
});

test.describe('cart table (C3, C4)', () => {
  test('narrows the thumbnail column and widens the quantity stepper to 44px touch targets', async ({
    page,
  }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await resetCartWith(page, [{ id: fixtures.products.simple }]);
    await page.goto(CART_URL, { waitUntil: 'domcontentloaded' });

    // MUTATION (confirmed red, then restored): commented out the C3 width
    // rule (`table.shop_table.cart th/td.product-thumbnail { width: 64px }`).
    // The thumbnail column's measured width jumped to over 100px (its
    // natural content width plus the shared table cell padding), failing
    // the `toBeLessThanOrEqual(80)` bound below.
    const thumbWidth = await page.evaluate(
      () => document.querySelector('td.product-thumbnail').getBoundingClientRect().width,
    );
    expect(thumbWidth).toBeLessThanOrEqual(80);

    // MUTATION (confirmed red, then restored): commented out
    // `table.shop_table.cart .wtb-qty-step { width: 44px; min-height: 44px }`.
    // The buttons measured at their default `<button>` intrinsic size
    // (well under 44px), failing both bounds below.
    const stepper = await page.evaluate(() => {
      const wrapper = document.querySelector('.cart_item .quantity');
      const steps = [...wrapper.querySelectorAll('.wtb-qty-step')].map((el) =>
        el.getBoundingClientRect(),
      );
      const input = wrapper.querySelector('input.qty').getBoundingClientRect();
      return {
        stepWidths: steps.map((r) => Math.round(r.width)),
        stepHeights: steps.map((r) => Math.round(r.height)),
        // The two buttons flank the input: one strictly to its left, one
        // strictly to its right.
        downRight: Math.round(steps[0].right),
        inputLeft: Math.round(input.left),
        inputRight: Math.round(input.right),
        upLeft: Math.round(steps[1].left),
      };
    });

    for (const w of stepper.stepWidths) expect(w).toBeGreaterThanOrEqual(44);
    for (const h of stepper.stepHeights) expect(h).toBeGreaterThanOrEqual(44);
    expect(stepper.downRight).toBeLessThanOrEqual(stepper.inputLeft);
    expect(stepper.upLeft).toBeGreaterThanOrEqual(stepper.inputRight);
  });

  test('the + stepper button actually increments the quantity', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    // A known starting quantity of exactly 1 matters here — this is the
    // test the old ambient-cart-state bug most plausibly broke.
    await resetCartWith(page, [{ id: fixtures.products.simple, qty: 1 }]);
    await page.goto(CART_URL, { waitUntil: 'domcontentloaded' });

    const input = page.locator('.cart_item .quantity input.qty').first();
    await expect(input).toHaveValue('1');
    await page.locator('.cart_item .wtb-qty-step[data-step="up"]').first().click();
    await expect(input).toHaveValue('2');
  });
});

test.describe('cart actions row (C6)', () => {
  test('clusters the coupon left and the two buttons right at 768px and above', async ({
    page,
  }) => {
    await page.setViewportSize({ width: 900, height: 900 });
    await resetCartWith(page, [{ id: fixtures.products.simple }]);
    await page.goto(CART_URL, { waitUntil: 'domcontentloaded' });

    // MUTATION (confirmed red, then restored): commented out the whole
    // `@media (min-width: 48rem) { td.actions … }` block. `.coupon` and the
    // two buttons then rendered as plain block/inline content with no
    // clustering — `updateCartRight` no longer matched the cell's padding
    // edge (it measured well short of it, mid-row), and the coupon/
    // continue-shopping gap collapsed under 40px.
    const measured = await page.evaluate(() => {
      const td = document.querySelector('td.actions');
      const coupon = td.querySelector('.coupon');
      const updateCart = td.querySelector('button[name="update_cart"]');
      const continueShopping = td.querySelector('.wtb-continue-shopping');
      const tdRect = td.getBoundingClientRect();
      const tdStyle = getComputedStyle(td);
      return {
        tdDisplay: tdStyle.display,
        // The cell's PADDING-box right edge — where floated content
        // actually sits — not its border-box edge. `table.shop_table td`
        // carries `padding: var(--space-md)` (storefront.css), so the two
        // differ by that padding and a border-box comparison would always
        // be off by it.
        tdPaddingRight: parseFloat(tdStyle.paddingRight),
        tdContentRight: Math.round(tdRect.right - parseFloat(tdStyle.paddingRight)),
        couponRight: Math.round(coupon.getBoundingClientRect().right),
        continueShoppingLeft: Math.round(continueShopping.getBoundingClientRect().left),
        continueShoppingRight: Math.round(continueShopping.getBoundingClientRect().right),
        updateCartLeft: Math.round(updateCart.getBoundingClientRect().left),
        updateCartRight: Math.round(updateCart.getBoundingClientRect().right),
      };
    });

    // The cell must stay a real table cell — this is the regression a flex
    // `display` on the `<td>` itself produced.
    expect(measured.tdDisplay).toBe('table-cell');
    // The two buttons cluster at the cell's own CONTENT edge (inside the
    // padding). Compared with a 1px tolerance, and that is not the assertion
    // being loosened to fit: both numbers are independently `Math.round()`ed
    // fractional layout values, so at a table width where the content edge
    // lands on e.g. 867.5 the two round to 868 and 867 while describing the
    // same edge. Measured exactly that (867 vs 868). The defect this guards —
    // the buttons not being right-aligned at all — moves them by the width of
    // the coupon field, hundreds of pixels, so 1px of slack cannot hide it.
    expect(Math.abs(measured.updateCartRight - measured.tdContentRight)).toBeLessThanOrEqual(1);
    // … with "Continue shopping" immediately to update_cart's left …
    expect(measured.continueShoppingRight).toBeLessThanOrEqual(measured.updateCartLeft);
    // … and the coupon stays clear on the left, with real separation from
    // the button cluster (not overlapping or touching).
    expect(measured.continueShoppingLeft - measured.couponRight).toBeGreaterThan(40);
  });

  test('below 768px each control is its own full-width row instead of a cluster', async ({
    page,
  }) => {
    await page.setViewportSize({ width: 390, height: 900 });
    await resetCartWith(page, [{ id: fixtures.products.simple }]);
    await page.goto(CART_URL, { waitUntil: 'domcontentloaded' });

    const measured = await page.evaluate(() => {
      const updateCart = document.querySelector('button[name="update_cart"]');
      const continueShopping = document.querySelector('.wtb-continue-shopping');
      return {
        updateCartFloat: getComputedStyle(updateCart).float,
        continueShoppingFloat: getComputedStyle(continueShopping).float,
      };
    });

    // The cluster rule is gated to `min-width: 48rem` — below it neither
    // button should be floated at all.
    expect(measured.updateCartFloat).toBe('none');
    expect(measured.continueShoppingFloat).toBe('none');
  });
});

test.describe('cart empty state (C12)', () => {
  test('shows the icon/title/lede panel, centred, with the return-to-shop button below it', async ({
    page,
  }) => {
    // The REAL path to an empty cart: log in, then remove every item
    // through the actual remove-link flow (resetCartWith with no items to
    // add). This also proves the empty state is genuinely REACHABLE by a
    // visitor, not just renderable from injected markup.
    await resetCartWith(page, []);
    // `emptyCart()` leaves the page on the (now-empty) cart from BEFORE the
    // last removal's AJAX update — the empty-cart TEMPLATE branch
    // (`cart-empty.php` vs `cart.php`) is a server-render decision made once
    // per request (`WC_Shortcode_Cart::output()`), so a fresh navigation is
    // needed to actually get `.wtb-cart-empty`'s markup rather than an
    // AJAX-emptied shell of the old template.
    await page.goto(CART_URL, { waitUntil: 'domcontentloaded' });

    const measured = await page.evaluate(() => {
      const empty = document.querySelector('.wtb-cart-empty');
      if (!empty) return null;
      const icon = empty.querySelector('.wtb-cart-empty__icon');
      const title = empty.querySelector('.wtb-cart-empty__title');
      const lede = empty.querySelector('.wtb-cart-empty__lede');
      const backToShop = document.querySelector('.wtb-cart-empty + p.return-to-shop');
      return {
        textAlign: getComputedStyle(empty).textAlign,
        iconBox: icon.getBoundingClientRect().toJSON(),
        titleText: title.textContent.trim(),
        ledeText: lede.textContent.trim(),
        backToShopAlign: backToShop ? getComputedStyle(backToShop).textAlign : null,
      };
    });

    expect(measured, 'no .wtb-cart-empty panel — is the cart actually empty?').not.toBeNull();
    expect(measured.textAlign).toBe('center');
    // MUTATION (confirmed red, then restored): commented out
    // `.wtb-cart-empty__icon { width: 56px; height: 56px; border-radius: … }`.
    // The icon's box collapsed to its inline SVG's own intrinsic size
    // (26x26), failing the >= 50 bounds below.
    expect(measured.iconBox.width).toBeGreaterThanOrEqual(50);
    expect(measured.iconBox.height).toBeGreaterThanOrEqual(50);
    expect(measured.titleText.length).toBeGreaterThan(0);
    expect(measured.ledeText.length).toBeGreaterThan(0);
    expect(measured.backToShopAlign).toBe('center');
  });
});

test.describe('cart mobile rows (C11)', () => {
  test('stacks each row into a card with a rhythm between rows below 768px', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 900 });
    await resetCartWith(page, [{ id: fixtures.products.simple }, { id: fixtures.products.sale }]);
    await page.goto(CART_URL, { waitUntil: 'domcontentloaded' });

    // MUTATION (confirmed red, then restored): commented out
    // `table.shop_table_responsive.cart tbody tr.cart_item { margin-bottom: …;
    // border-bottom: … }`. The gap between the two rows' boxes dropped to 0
    // (Woo's own smallscreen stacking has no spacing of its own), failing
    // the `toBeGreaterThan(8)` bound below.
    const rows = await page.evaluate(() => {
      const items = [...document.querySelectorAll('table.shop_table.cart tbody tr.cart_item')];
      return items.map((el) => {
        const box = el.getBoundingClientRect();
        return { top: box.top, bottom: box.bottom, display: getComputedStyle(el).display };
      });
    });

    expect(rows.length).toBeGreaterThanOrEqual(2);
    for (const row of rows) expect(row.display).toBe('block');
    // Second row starts a visible gap after the first row ends.
    expect(rows[1].top - rows[0].bottom).toBeGreaterThan(8);
  });
});

test.describe('secure note (C10 / K9)', () => {
  test('renders as a centred, muted icon+label row when the Customizer setting is filled', async ({
    page,
  }) => {
    // Both C10 and K9-note default to EMPTY (no local fallback icon,
    // Settings::secure_note()'s own contract) — this store's Customizer
    // setting is unconfigured, so the real note never renders here. Verified
    // by injecting the exact markup inc/Woo/Cart.php prints when the setting
    // IS filled, against the real page's cascade, rather than mutating a
    // shared wp option a parallel worker's test run could also be reading.
    await resetCartWith(page, [{ id: fixtures.products.simple }]);
    await page.goto(CART_URL, { waitUntil: 'domcontentloaded' });

    // MUTATION (confirmed red, then restored): commented out the whole
    // `.wtb-secure-note { … }` rule in cart.css. `display` fell back to the
    // element's default (`block`), and the assertion on `display` below
    // failed (`"block"` !== `"flex"`); the note also lost its centring.
    const measured = await page.evaluate(() => {
      const totals = document.querySelector('.cart_totals');
      const note = document.createElement('p');
      note.className = 'wtb-secure-note';
      note.innerHTML =
        '<svg width="16" height="16" aria-hidden="true"></svg><span>Secure payment</span>';
      totals.appendChild(note);
      const cs = getComputedStyle(note);
      return {
        display: cs.display,
        justifyContent: cs.justifyContent,
        textAlign: cs.textAlign,
        color: cs.color,
        totalsColor: getComputedStyle(totals).color,
      };
    });

    expect(measured.display).toBe('flex');
    expect(measured.justifyContent).toBe('center');
    expect(measured.textAlign).toBe('center');
    // Muted, not the panel's own foreground colour.
    expect(measured.color).not.toBe(measured.totalsColor);
  });
});

test.describe('checkout section numbering (K2)', () => {
  test('renders the billing and ship-to-different-address badges as distinct circles', async ({
    page,
  }) => {
    // The digit itself has NO CSSOM-readable path — a CSS counter's
    // COMPUTED `content` value is the unresolved `counter()` function
    // notation per spec; only paint resolves it to a number, and paint is
    // not queryable from JS. An earlier version of this test asserted
    // `content === '"1"'` and got back the literal string
    // `"counter(wtb-checkout-section)"` — that assertion could not have
    // passed on correct CSS OR broken CSS. The digits ARE visually
    // confirmed: probe-k2-out/checkout-badges.png, captured against this
    // exact page state, shows "1" on Billing details and "2" on Ship to a
    // different address. What IS readable and asserted below is that the
    // badge renders as the mockup's circle at all, and that the two
    // headings' badges are laid out as siblings before their own heading
    // text (not merged into one).
    await page.setViewportSize({ width: 1280, height: 900 });
    await resetCartWith(page, [{ id: fixtures.products.simple }]);
    await page.goto(CHECKOUT_URL, { waitUntil: 'domcontentloaded' });

    // MUTATION (confirmed red, then restored): commented out the
    // `.woocommerce-billing-fields > h3::before, … { … }` rule in
    // checkout.css entirely (not just the counter-reset). `content` fell
    // back to its initial value (`normal`), which computes no box at all —
    // `width`/`height` read `0px`/`auto` and the size assertions below
    // failed.
    const badges = await page.evaluate(() => {
      const read = (el) => {
        const cs = getComputedStyle(el, '::before');
        return {
          width: parseFloat(cs.width),
          height: parseFloat(cs.height),
          backgroundColor: cs.backgroundColor,
          borderRadius: cs.borderRadius,
        };
      };
      return {
        billing: read(document.querySelector('.woocommerce-billing-fields > h3')),
        shipTo: read(document.querySelector('#ship-to-different-address')),
      };
    });

    for (const badge of [badges.billing, badges.shipTo]) {
      // 26px circle (mockup line 731), not a 0-size/absent box.
      expect(badge.width).toBeGreaterThanOrEqual(20);
      expect(badge.height).toBeGreaterThanOrEqual(20);
      expect(badge.width).toBe(badge.height);
      // A true circle, not a rounded square — `border-radius` at half (or
      // more of) the box's own size.
      expect(parseFloat(badge.borderRadius)).toBeGreaterThanOrEqual(badge.width / 2 - 1);
      // Filled, not transparent.
      expect(badge.backgroundColor).not.toBe('rgba(0, 0, 0, 0)');
    }
  });
});

test.describe('checkout returning-customer banner (K3)', () => {
  test('styles a.showlogin as a small bordered button, right-aligned in the notice', async ({
    page,
  }) => {
    // The store has guest checkout / the login reminder disabled, so
    // form-login.php never prints this banner live — injected as a sibling
    // of the checkout form, matching exactly where
    // `woocommerce_before_checkout_form` fires (BEFORE `<form>` opens,
    // per form-checkout.php), which a first version of this check got wrong
    // (inserted inside the form instead) and measured `marginLeft: "0px"`
    // for that reason alone.
    await page.setViewportSize({ width: 1280, height: 900 });
    await resetCartWith(page, [{ id: fixtures.products.simple }]);
    await page.goto(CHECKOUT_URL, { waitUntil: 'domcontentloaded' });

    // MUTATION (confirmed red, then restored): commented out the
    // `.showlogin { … }` rule in checkout.css. `backgroundColor` read as
    // fully transparent (`rgba(0, 0, 0, 0)`, the anchor's UA default)
    // instead of the card surface, failing the `not.toBe` guard below.
    const measured = await page.evaluate(() => {
      const form = document.querySelector('form.checkout.woocommerce-checkout');
      const wrap = document.createElement('div');
      wrap.className = 'woocommerce-form-login-toggle';
      wrap.innerHTML =
        '<div class="woocommerce-info">Returning customer? <a href="#" class="showlogin">Click here to login</a></div>';
      form.parentElement.insertBefore(wrap, form);

      const notice = wrap.querySelector('.woocommerce-info');
      const link = wrap.querySelector('a.showlogin');
      const cs = getComputedStyle(link);
      return {
        backgroundColor: cs.backgroundColor,
        borderStyle: cs.borderStyle,
        textDecorationLine: cs.textDecorationLine,
        linkRight: Math.round(link.getBoundingClientRect().right),
        noticeRight: Math.round(notice.getBoundingClientRect().right),
      };
    });

    expect(measured.backgroundColor).not.toBe('rgba(0, 0, 0, 0)');
    expect(measured.borderStyle).toBe('solid');
    expect(measured.textDecorationLine).toBe('none');
    // Right-aligned inside the notice (the shared `.woocommerce-info a
    // {margin-left:auto}` rule), not left where the text left it.
    expect(measured.noticeRight - measured.linkRight).toBeLessThan(40);
  });
});

test.describe('checkout notices (K4)', () => {
  test('reinstates a real, differently-coloured icon for message, info, and error notices', async ({
    page,
  }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await resetCartWith(page, [{ id: fixtures.products.simple }]);
    await page.goto(CHECKOUT_URL, { waitUntil: 'domcontentloaded' });

    // MUTATION (confirmed red, then restored): changed `content: ''` to
    // `content: none` on the shared `::before` rule in checkout.css (i.e.
    // reverted K4 back to storefront's own suppression). `content` read
    // back as the literal string "none" for all three, failing the
    // `not.toBe('none')` guard.
    const measured = await page.evaluate(() => {
      const wrapper = document.querySelector('.woocommerce-notices-wrapper');
      const make = (tag, cls, text) => {
        const el = document.createElement(tag);
        el.className = cls;
        if (tag === 'ul') el.innerHTML = `<li>${text}</li>`;
        else el.textContent = text;
        wrapper.appendChild(el);
        return el;
      };
      const msg = make('div', 'woocommerce-message', 'ok');
      const info = make('div', 'woocommerce-info', 'fyi');
      const err = make('ul', 'woocommerce-error', 'nope');

      const read = (el) => {
        const cs = getComputedStyle(el, '::before');
        return { content: cs.content, backgroundColor: cs.backgroundColor, position: cs.position };
      };
      return { msg: read(msg), info: read(info), err: read(err) };
    });

    for (const notice of Object.values(measured)) {
      expect(notice.content).not.toBe('none');
      expect(notice.position).toBe('static');
    }
    // Three genuinely different tints, not one colour reused three times.
    expect(measured.msg.backgroundColor).not.toBe(measured.info.backgroundColor);
    expect(measured.info.backgroundColor).not.toBe(measured.err.backgroundColor);
    expect(measured.msg.backgroundColor).not.toBe(measured.err.backgroundColor);
  });
});

test.describe('checkout field grid (K5)', () => {
  test('maps form-row-first/-last onto a two-column grid and form-row-wide across both', async ({
    page,
  }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await resetCartWith(page, [{ id: fixtures.products.simple }]);
    await page.goto(CHECKOUT_URL, { waitUntil: 'domcontentloaded' });

    // MUTATION (confirmed red, then restored): commented out
    // `form .form-row-first, form .form-row-last { width: auto; float: none }`
    // in checkout.css. Woo's own `.woocommerce form .form-row-first,
    // .woocommerce form .form-row-last{width:47%}` then governed the width
    // again — inside a grid cell whose own track is already ~50% of the
    // wrapper, so the measured field width dropped to roughly a QUARTER of
    // the wrapper instead of close to half, failing the ratio assertion.
    const measured = await page.evaluate(() => {
      const wrapper = document.querySelector('.woocommerce-billing-fields__field-wrapper');
      const rows = [...wrapper.querySelectorAll('p.form-row')];
      const first = rows.find((r) => r.classList.contains('form-row-first'));
      const wide = rows.find((r) => r.classList.contains('form-row-wide'));
      return {
        display: getComputedStyle(wrapper).display,
        wrapperWidth: wrapper.getBoundingClientRect().width,
        firstWidth: first ? first.getBoundingClientRect().width : null,
        wideWidth: wide ? wide.getBoundingClientRect().width : null,
      };
    });

    expect(measured.display).toBe('grid');
    expect(measured.firstWidth, 'no p.form-row-first in the billing fields').not.toBeNull();
    // A first/last field takes roughly HALF the wrapper (its own column),
    // not Woo's 47%-of-something-else. A RATIO, not a fixed pixel bound —
    // holds regardless of the container's own absolute width.
    const halfRatio = measured.firstWidth / measured.wrapperWidth;
    expect(halfRatio).toBeGreaterThan(0.4);
    expect(halfRatio).toBeLessThan(0.6);
    // A wide field spans (close to) the FULL wrapper width.
    expect(measured.wideWidth, 'no p.form-row-wide in the billing fields').not.toBeNull();
    expect(measured.wideWidth / measured.wrapperWidth).toBeGreaterThan(0.9);
  });
});

test.describe('checkout shipping methods (K7)', () => {
  test('gives each shipping-method radio the payment-method card treatment, with an accent on the checked one', async ({
    page,
  }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await resetCartWith(page, [{ id: fixtures.products.simple }]);
    await page.goto(CHECKOUT_URL, { waitUntil: 'domcontentloaded' });

    const list = page.locator('#shipping_method > li');
    await expect(list.first()).toBeVisible();
    const count = await list.count();
    expect(count).toBeGreaterThanOrEqual(2);

    // MUTATION (confirmed red, then restored): commented out
    // `#shipping_method > li:has(input:checked) { border-color: … }` in
    // checkout.css. The checked and unchecked `<li>`s then measured the
    // SAME border colour, failing the `not.toBe` guard.
    const measured = await page.evaluate(() => {
      const items = [...document.querySelectorAll('#shipping_method > li')];
      const checked = items.find((li) => li.querySelector('input:checked'));
      const unchecked = items.find((li) => !li.querySelector('input:checked'));
      return {
        checkedBorder: getComputedStyle(checked).borderColor,
        uncheckedBorder: getComputedStyle(unchecked).borderColor,
        checkedRadius: getComputedStyle(checked).borderRadius,
        display: getComputedStyle(checked).display,
      };
    });

    expect(measured.display).toBe('flex');
    expect(measured.checkedRadius).not.toBe('0px');
    expect(measured.checkedBorder).not.toBe(measured.uncheckedBorder);
  });
});

test.describe('checkout review-order line items (K8, K10)', () => {
  test('prepends a 40px thumbnail and renders the quantity in the monospace font', async ({
    page,
  }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await resetCartWith(page, [{ id: fixtures.products.simple, qty: 2 }]);
    await page.goto(CHECKOUT_URL, { waitUntil: 'domcontentloaded' });

    // MUTATION (confirmed red, then restored): commented out
    // `#order_review .wtb-review-thumb__img { width: 40px; height: 40px; … }`.
    // The image fell back to whatever intrinsic/attribute size Woo's
    // `get_image()` markup carries, and the `toBeGreaterThan(30)` /
    // `toBeLessThan(60)` bounds below failed against it.
    const measured = await page.evaluate(() => {
      const img = document.querySelector('#order_review .wtb-review-thumb__img');
      const qty = document.querySelector('#order_review .product-quantity');
      const name = document.querySelector('#order_review td.product-name');
      return {
        imgWidth: img ? img.getBoundingClientRect().width : null,
        qtyFont: qty ? getComputedStyle(qty).fontFamily : null,
        nameFont: getComputedStyle(name).fontFamily,
      };
    });

    expect(measured.imgWidth, 'no .wtb-review-thumb__img in the review order table').not.toBeNull();
    expect(measured.imgWidth).toBeGreaterThan(30);
    expect(measured.imgWidth).toBeLessThan(60);
    expect(measured.qtyFont, 'no .product-quantity in the review order table').not.toBeNull();
    expect(measured.qtyFont).not.toBe(measured.nameFont);
    expect(measured.qtyFont.toLowerCase()).toContain('mono');
  });

  test('K10 — the review-order table stays a plain table at mobile width instead of stacking', async ({
    page,
  }) => {
    // review-order.php's table carries `shop_table` only, never
    // `shop_table_responsive`, and its `<td>`s carry no `data-title` — the
    // contrast with the cart's OWN table (which does stack, per C11 above)
    // is the actual claim K10 makes.
    await page.setViewportSize({ width: 390, height: 900 });
    await resetCartWith(page, [{ id: fixtures.products.simple }]);
    await page.goto(CHECKOUT_URL, { waitUntil: 'domcontentloaded' });

    const rowDisplay = await page.evaluate(
      () =>
        getComputedStyle(document.querySelector('table.woocommerce-checkout-review-order-table tr'))
          .display,
    );
    expect(rowDisplay).toBe('table-row');
  });

  test('K6 — the payment section sits on the panel surface, not on Woo own slab', async ({
    page,
  }) => {
    // Found by looking at the rendered checkout, not by any assertion: the
    // payment methods sat on a lavender-grey slab and the chosen method's
    // description rendered as a lavender tooltip with a pointer triangle.
    // Both are WooCommerce's own hardcoded colours on ID-carrying selectors
    // (`.woocommerce-checkout #payment`, `… #payment div.payment_box`,
    // (1,1,0)-(1,2,0)), which the pre-existing `ul.wc_payment_methods` /
    // `li.wc_payment_method .payment_box` rules at (0,2,0) lost to outright —
    // so those rules' padding, colour and border-top had never applied on a
    // checkout at all.
    //
    // MUTATION (confirmed red, then restored): commented out the whole
    // `#payment { background-color: transparent }` /
    // `#payment div.payment_box { background-color: transparent }` block in
    // src/css/woo/checkout.css. Both comparisons below went red.
    //
    // Measurement against MEASUREMENT, never against a token string: the
    // payment wrapper and the description box must resolve to the SAME
    // background the panel around them already resolves to. Comparing either
    // against `var(--card)`'s source text is the vacuous shape s18 shipped.
    //
    // `resetCartWith()` logs in itself — no separate `login()` call here. An
    // earlier version had both and that is what exposed the non-idempotent
    // helper (see `login()`'s own comment).
    await resetCartWith(page, [{ id: fixtures.products.simple }]);
    await page.goto(CHECKOUT_URL, { waitUntil: 'domcontentloaded' });

    const measured = await page.evaluate(() => {
      const panel = document.querySelector('#order_review');
      const payment = document.querySelector('#payment');
      const box = document.querySelector('#payment div.payment_box');
      const pointer = box && getComputedStyle(box, '::before');
      return {
        panelBg: getComputedStyle(panel).backgroundColor,
        paymentBg: payment ? getComputedStyle(payment).backgroundColor : null,
        boxBg: box ? getComputedStyle(box).backgroundColor : null,
        pointerDisplay: pointer ? pointer.display : null,
        methods: document.querySelectorAll('#payment li.wc_payment_method').length,
      };
    });

    // Guards the test itself: with no gateway enabled Woo renders a notice
    // instead of a method list, and every assertion below would pass against
    // an absent `#payment` subtree. The fixture enables `cod` + `bacs`.
    expect(measured.methods, 'no payment methods rendered — is a gateway enabled?').toBeGreaterThan(
      1,
    );

    // `transparent` computes to `rgba(0, 0, 0, 0)`, which is what "shows the
    // panel through" means; asserting equality with the panel's own resolved
    // colour would be wrong, since a transparent box does not COPY it.
    expect(measured.paymentBg).toBe('rgba(0, 0, 0, 0)');
    expect(measured.paymentBg).not.toBe(measured.panelBg);
    expect(
      measured.boxBg,
      'no .payment_box — is a method with a description selected?',
    ).not.toBeNull();
    expect(measured.boxBg).toBe('rgba(0, 0, 0, 0)');
    expect(measured.pointerDisplay).toBe('none');
  });
});
