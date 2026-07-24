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
