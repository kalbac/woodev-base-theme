// tests/e2e-woo/storefront.spec.mjs
//
// The WooCommerce storefront, asserted against the seeded demo store on :8891
// (tests/e2e-woo/global-setup.mjs seeds three products with known NAMES:
// "WTB Simple Product", "WTB Sale Product", "WTB Out of Stock Product").
//
// Mirrors tests/e2e/components.spec.mjs conventions:
//   - computed-style assertions over DOM-shape counting (a track count is the
//     column count; counting cards would pass with too few in a row);
//   - the { page } fixture only — never browser.newPage()
//     (docs/gotchas/playwright-browser-newpage-skips-config.md);
//   - the runtime `.dark` toggle for the dark-scheme check rather than the
//     color_scheme theme_mods.
//
// Cards are located by product NAME and single products are reached by CLICKING
// through from /shop/, so the specs do not depend on the permalink structure
// (pretty vs plain) of the wp-env install.
import { expect, test } from '@playwright/test';

const NAMES = {
  simple: 'WTB Simple Product',
  sale: 'WTB Sale Product',
  oos: 'WTB Out of Stock Product',
};

/** Number of CSS grid tracks `ul.products` resolves to at the current width. */
async function productGridTracks(page) {
  return page.evaluate(
    () =>
      getComputedStyle(document.querySelector('ul.products')).gridTemplateColumns.split(' ').length,
  );
}

const GRID_BREAKPOINTS = [
  { width: 375, tracks: 1 },
  { width: 800, tracks: 2 },
  { width: 1400, tracks: 3 },
];

for (const { width, tracks } of GRID_BREAKPOINTS) {
  test(`the shop grid resolves ${tracks} track(s) at ${width}px`, async ({ page }) => {
    await page.setViewportSize({ width, height: 900 });
    await page.goto('/shop/');
    expect(await productGridTracks(page)).toBe(tracks);
  });
}

test('the shop renders product cards in the theme card vocabulary', async ({ page }) => {
  await page.goto('/shop/');

  const cards = page.locator('ul.products li.wtb-product-card.card');
  const count = await cards.count();
  expect(count).toBeGreaterThan(1);

  // Every card carries the body wrapper the override introduces. There is
  // deliberately no <header>/<footer>: the Woo loop-product anchor spans the
  // upper card, and a wrapper crossing that boundary is invalid HTML — see the
  // content-product.php override for the full reasoning.
  for (let i = 0; i < count; i += 1) {
    await expect(cards.nth(i).locator('.wtb-product-card__body')).toHaveCount(1);
  }
});

test('the on-sale product shows a sale flash and the out-of-stock product its badge', async ({
  page,
}) => {
  await page.goto('/shop/');

  const saleCard = page.locator('.wtb-product-card', { hasText: NAMES.sale });
  await expect(saleCard.locator('.onsale')).toBeVisible();

  const oosCard = page.locator('.wtb-product-card', { hasText: NAMES.oos });
  await expect(oosCard.locator('.wtb-stock-badge')).toBeVisible();
});

test('a single product shows the gallery, a styled add-to-cart, and the tabs', async ({ page }) => {
  await page.goto('/shop/');
  await page.locator('.wtb-product-card', { hasText: NAMES.simple }).locator('a').first().click();

  await expect(page.locator('.woocommerce-product-gallery')).toBeVisible();
  await expect(page.locator('.single_add_to_cart_button')).toBeVisible();

  const tabs = page.locator('.woocommerce-tabs');
  await expect(tabs).toBeVisible();
  await expect(tabs.locator('[role="tablist"]')).toHaveCount(1);
});

test('adding a product to the cart works', async ({ page }) => {
  await page.goto('/shop/');
  await page.locator('.wtb-product-card', { hasText: NAMES.simple }).locator('a').first().click();

  await page.locator('.single_add_to_cart_button').click();

  // Default Woo (redirect-after-add off) reloads the product with a success
  // notice + "View cart" link. The cart PAGE itself is M2b; here we only prove
  // the button adds an item.
  await expect(page.locator('.woocommerce-message')).toBeVisible();
});

test('the product card restyles under the dark scheme', async ({ page }) => {
  // Assert a computed colour that MUST change, not DOM structure: the card's
  // element tree is identical in both schemes. `.card` paints --card, near-white
  // in light and near-black in dark, so its computed background-color is the
  // cheapest value that proves the dark tokens reached the storefront bundle.
  await page.goto('/shop/');

  const card = page.locator('.wtb-product-card.card').first();
  await expect(card).toBeVisible();

  const readBg = () => card.evaluate((el) => getComputedStyle(el).backgroundColor);

  const light = await readBg();
  await page.evaluate(() => document.documentElement.classList.add('dark'));
  const dark = await readBg();

  expect(light).not.toBe(dark);
});

// ---------------------------------------------------------------------------
// Cascade-race regression coverage (woo.css defects P1-253, -478/503, -872,
// -1032, -1342, -1610) — each rule below was losing to a same-scope Woo rule
// of equal or higher specificity; see the corresponding comment in
// src/css/woo.css for the exact selector math. Computed style / geometry is
// asserted throughout per this file's own header rule, never DOM shape alone.
// ---------------------------------------------------------------------------

test('the card link computes display: flex and the body stretches evenly across differing title lengths', async ({
  page,
}) => {
  // P1-253: `.wtb-product-card .woocommerce-loop-product__link` was (0,3,0)
  // against Woo's own `.woocommerce ul.products li.product
  // a.woocommerce-loop-product__link { display: block }` at (0,4,3) — Woo
  // won, so the flex column (gap, `.wtb-product-card__body` stretch) never
  // applied. "Prices align to the bottom" is verified here as EQUAL
  // `.wtb-product-card__body` heights for the sale/out-of-stock cards,
  // which sit in the same grid row and carry genuinely different title text
  // ("WTB Sale Product" vs "WTB Out of Stock Product", confirmed to wrap to
  // a different number of lines at this width) — the literal, direct proof
  // the stretch chain runs. Their `.price` elements themselves are NOT
  // asserted to land at an identical Y: neither this file nor the approved
  // mockup gives `.price` a `margin-top: auto`, so a taller title still
  // pushes its own price a little lower than a shorter one in the same
  // row — a real, separate gap from what this defect fixes, not claimed
  // here (see the task report for detail).
  await page.setViewportSize({ width: 1400, height: 1000 });
  await page.goto('/shop/');

  const saleCard = page.locator('.wtb-product-card', { hasText: NAMES.sale });
  const oosCard = page.locator('.wtb-product-card', { hasText: NAMES.oos });

  await expect(saleCard.locator('.woocommerce-loop-product__link')).toHaveCSS('display', 'flex');
  await expect(oosCard.locator('.woocommerce-loop-product__link')).toHaveCSS('display', 'flex');

  const [saleHeight, oosHeight] = await Promise.all([
    saleCard.locator('.wtb-product-card__body').evaluate((el) => el.getBoundingClientRect().height),
    oosCard.locator('.wtb-product-card__body').evaluate((el) => el.getBoundingClientRect().height),
  ]);
  expect(Math.abs(saleHeight - oosHeight)).toBeLessThan(1);
});

test('a gallery with 4+ images leaves the last thumbnail reachable, and clicking it moves the active border', async ({
  page,
}) => {
  // P1-478/503: the thumbnail strip's own `li { width: 64px }` was losing
  // to Woo's `{ width: 25%; float: left }` while Woo's `overflow: hidden`
  // on the container stayed live — with 5 fixed-width thumbnails
  // (global-setup seeds wtb-product-simple with 1 featured + 4 gallery
  // images) in a non-wrapping row, anything past the 3rd/4th was clipped
  // and unreachable. Separately, the active-thumbnail selector expected a
  // nested `.flex-active img`, but Woo marks the `<img>` itself
  // `img.flex-active`, so the border never rendered at all.
  await page.goto('/shop/');
  await page.locator('.wtb-product-card', { hasText: NAMES.simple }).locator('a').first().click();

  const thumbImgs = page.locator('.flex-control-thumbs img');
  await expect(thumbImgs).toHaveCount(5);

  const lastImg = thumbImgs.last();
  await expect(lastImg).toBeVisible();
  const box = await lastImg.boundingBox();
  expect(box.width).toBeGreaterThan(0);
  expect(box.height).toBeGreaterThan(0);

  const firstImg = thumbImgs.first();
  await expect(firstImg).toHaveClass(/flex-active/);
  const inactiveBorderColor = await lastImg.evaluate((el) => getComputedStyle(el).borderColor);

  await lastImg.click();

  await expect(lastImg).toHaveClass(/flex-active/);
  await expect(firstImg).not.toHaveClass(/flex-active/);
  const activeBorderColor = await lastImg.evaluate((el) => getComputedStyle(el).borderColor);
  expect(activeBorderColor).not.toBe(inactiveBorderColor);
});

test('.col-1 fills its own grid track at desktop width', async ({ page }) => {
  // P1-1342: turning `.col2-set` into a grid does not stop Woo's own
  // `.col2-set .col-1 { float: left; width: 48% }` — a grid item ignores
  // `float` but not an explicit `width`, and that percentage resolves
  // against the item's own grid AREA (its track), not the row, so `.col-1`
  // rendered at roughly half of its own track. Reached via `/my-account/`'s
  // login/register split (global-setup enables registration) since the
  // seeded store's checkout page renders the Checkout BLOCK, not this
  // classic `.col2-set` markup.
  await page.setViewportSize({ width: 1400, height: 1000 });
  await page.goto('/my-account/');

  const col2Set = page.locator('#customer_login.col2-set');
  const col1 = col2Set.locator('.col-1');
  const col2 = col2Set.locator('.col-2');

  const [setBox, col1Box, col2Box] = await Promise.all([
    col2Set.boundingBox(),
    col1.boundingBox(),
    col2.boundingBox(),
  ]);

  // Side by side, not stacked.
  expect(Math.abs(col1Box.y - col2Box.y)).toBeLessThan(1);
  // A correctly-filled `.col-1` covers comfortably more than a THIRD of the
  // container width; the pre-fix bug rendered it at roughly a QUARTER (48%
  // of a ~50%-wide track).
  expect(col1Box.width).toBeGreaterThan(setBox.width * 0.4);
});

test('two stacked notices do not widen the page at 375px', async ({ page }) => {
  // P1-872: making `.woocommerce-error` (shared with -message/-info) a flex
  // ROW packs Woo's real error markup — a single `<ul class="woocommerce-
  // error">` with one `<li>` per validation error, verified against the
  // installed 10.9.4 `templates/notices/error.php` — onto one line,
  // overflowing a narrow screen with 2+ errors.
  //
  // FIXTURE NOTE: triggering 2+ native validation errors from one real Woo
  // form submission was not reliably deterministic — traced into
  // `includes/class-wc-form-handler.php::process_registration()`, which
  // only ever accumulates multiple messages when something hooks
  // `woocommerce_process_registration_errors` (nothing does by default in
  // core), so every empty/invalid registration attempt produced exactly
  // one error. Rather than depend on that undocumented internal, this
  // injects Woo's OWN verified template shape directly into the real
  // `.woocommerce` wrapper on a live page and asserts the real CSS cascade
  // against it — the fallback this suite's header comment and the task
  // brief both call for when a fixture can't be seeded through a normal
  // user flow at reasonable cost.
  await page.setViewportSize({ width: 375, height: 800 });
  await page.goto('/shop/');

  await page.evaluate(() => {
    const wrap = document.querySelector('.woocommerce');
    const ul = document.createElement('ul');
    ul.className = 'woocommerce-error';
    ul.setAttribute('role', 'alert');
    for (const message of [
      'Error: Billing first name is a required field.',
      'Error: Billing last name is a required field.',
    ]) {
      const li = document.createElement('li');
      li.textContent = message;
      ul.appendChild(li);
    }
    wrap.prepend(ul);
  });

  const items = page.locator('.woocommerce-error li');
  await expect(items).toHaveCount(2);

  const [firstBox, secondBox] = await Promise.all([
    items.nth(0).boundingBox(),
    items.nth(1).boundingBox(),
  ]);
  expect(secondBox.y).toBeGreaterThanOrEqual(firstBox.y + firstBox.height - 1);

  const overflowX = await page.evaluate(
    () => document.documentElement.scrollWidth - document.documentElement.clientWidth,
  );
  expect(overflowX).toBeLessThanOrEqual(0);
});

test('the shop ordering select is legible under the dark scheme', async ({ page }) => {
  // Covers the SHOP ORDERING select (`.woocommerce-ordering select.orderby`),
  // which woo.css styles in its own block. It does NOT cover the `.form-row
  // select` specificity repair (P1-1032) — read this before assuming it does:
  //
  // 1. `#billing_country` (the field that repair is about) does not exist on
  //    this store. WooCommerce 10.x's `install_pages` creates a BLOCK-based
  //    checkout (`<!-- wp:woocommerce/checkout -->`, verified on the seeded
  //    :8891 install), whose markup is `.wc-block-*` throughout — none of the
  //    classic `.form-row` / `input.input-text` / `#billing_country` shapes
  //    a large part of woo.css targets. Two earlier versions of this test
  //    timed out looking for that element, once via the checkout and once via
  //    a logged-in address page.
  // 2. That is a SCOPE finding, not a test problem, and it belongs to M2b:
  //    on a default Woo install the classic cart/checkout form styling is
  //    inert, and only applies to stores that swap the shortcode back in.
  //    `/my-account/` IS classic, which is why the `.col2-set` test above
  //    works. Recorded in docs/CURRENT-STATE.md.
  // 3. The password-toggle half of the original defect IS real, contrary to an
  //    earlier note here: WooCommerce's frontend JS creates the button
  //    (`assets/js/frontend/woocommerce.js:126`) and `woocommerce-layout.css`
  //    gives its `::before` a data-URI icon with `fill="%23111111"` baked in.
  //    Searching the TEMPLATES finds nothing, which is what produced the
  //    wrong conclusion. woo.css inverts that glyph in both dark paths;
  //    asserting it needs a rendered login form and is not covered here.
  await page.goto('/shop/');
  const select = page.locator('.woocommerce-ordering select.orderby');
  await expect(select).toHaveCount(1);

  const readStyle = () =>
    page.evaluate(() => {
      const el = document.querySelector('.woocommerce-ordering select.orderby');
      const s = getComputedStyle(el);
      // The chevron is OUR pseudo-element on the wrapper, not a background
      // image on the control: woo.css strips the native arrow with
      // `appearance: none` and paints `.woocommerce-ordering::after` as a
      // masked shape coloured by `--muted-foreground`. Read it there, or the
      // assertion measures the wrong element.
      const arrow = getComputedStyle(el.closest('.woocommerce-ordering'), '::after');
      return {
        backgroundColor: s.backgroundColor,
        appearance: s.appearance,
        arrowColor: arrow.backgroundColor,
        arrowMask: arrow.maskImage || arrow.webkitMaskImage,
      };
    });

  const light = await readStyle();
  await page.evaluate(() => document.documentElement.classList.add('dark'));
  const dark = await readStyle();

  // Woo's own `.woocommerce form .form-row select` ships a literal
  // `var(--wc-form-color-background,#fff)`. Ours must be the token instead,
  // and the two schemes must actually differ — that difference is what
  // proves a token drives it rather than a static fallback.
  expect(dark.backgroundColor).not.toBe('rgb(255, 255, 255)');
  expect(dark.backgroundColor).not.toBe(light.backgroundColor);

  // Native arrow stripped, ours actually painted, and its colour follows the
  // scheme — a chevron that stayed a fixed near-black would be invisible on
  // the dark surface asserted above.
  expect(dark.appearance).toBe('none');
  expect(dark.arrowMask).not.toBe('none');
  expect(dark.arrowColor).not.toBe(light.arrowColor);
});

test('under prefers-reduced-motion: reduce, the Woo spinner and gallery slide transition are stopped', async ({
  page,
}) => {
  // P1-1610: the reduced-motion block only ever covered motion THIS FILE
  // declares. Woo's own `animation: spin 2s linear infinite` on
  // `.button.loading::after` and `transition: all cubic-bezier(...) .5s`
  // on `.woocommerce-product-gallery__wrapper` kept running regardless.
  await page.emulateMedia({ reducedMotion: 'reduce' });
  await page.goto('/shop/');
  await page.locator('.wtb-product-card', { hasText: NAMES.simple }).locator('a').first().click();

  const wrapper = page.locator('.woocommerce-product-gallery__wrapper');
  await expect(wrapper).toBeVisible();
  await expect(wrapper).toHaveCSS('transition-property', 'none');

  // Woo's own class, applied directly to a real add-to-cart button already
  // on the page — no separate fixture needed, and this is exactly the
  // element/class Woo's JS itself toggles while an add-to-cart request is
  // in flight.
  const btn = page.locator('.single_add_to_cart_button');
  await btn.evaluate((el) => el.classList.add('loading'));
  const afterAnimationName = await btn.evaluate(
    (el) => getComputedStyle(el, '::after').animationName,
  );
  expect(afterAnimationName).toBe('none');
});
