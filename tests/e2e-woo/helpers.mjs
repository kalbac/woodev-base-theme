// tests/e2e-woo/helpers.mjs
//
// Reach a HYDRATED block Cart / Checkout. Both block trees ship zero
// server-rendered <input> elements (ADR-009 finding 8: the SSR response
// carries only a `wc-block-checkout is-loading` skeleton) — a plain
// `page.goto()` alone lands on that skeleton, and `page.waitForLoadState
// ('networkidle')` alone is not a reliable substitute either: the initial
// data fetch (cart contents, shipping packages, country list, …) can settle
// on the network while the React tree is still mid-render, so "network is
// idle" and "the app has mounted" are different moments. Each helper instead
// waits for a REAL element proven, by probing the live :8891 store, to exist
// only once the client-side app has actually mounted:
//
//   - checkout: `#email` — the contact-information email <input>. Verified
//     absent from `post_content`/the SSR skeleton and present in the
//     hydrated DOM (`wc-block-components-address-form__email > input#email`).
//   - cart: `.wc-block-components-quantity-selector__input` — the per-line-
//     item quantity <input>, same absent/present split, and it only exists
//     once there is a real line item to render.
//
// `/checkout/` 302s to `/cart/` when the cart is empty — verified live: a
// fresh (cookie-cleared) session navigating straight to /checkout/ ends on
// http://localhost:8891/cart/. A spec that skips the final-URL check would
// silently assert against the CART page while believing it tested checkout;
// that check is the entire reason gotoCheckoutHydrated() exists rather than
// specs calling `page.goto('/checkout/')` directly.
import { loadFixtures } from './fixtures.mjs';

/**
 * Add the seeded simple product to the cart via the no-JS `add-to-cart` GET
 * endpoint. ADR-009 finding 8 rules out a JS-driven add-to-cart for reaching
 * checkout: the block checkout has no server-rendered "add to cart" control
 * of its own, and driving one through the /shop/ or single-product page
 * would tie this fixture helper to markup another task (B2-B5) owns and is
 * free to change mid-plan.
 *
 * Woo's default "redirect to the cart page after successful addition" setting
 * is OFF on this seeded store, so this GET responds on whatever page it was
 * requested from; callers always navigate on to the page they actually want.
 */
async function addSimpleProductToCart(page) {
  const { products } = loadFixtures();
  await page.goto(`/?add-to-cart=${products.simple}`);
  await page.waitForLoadState('networkidle');
}

/**
 * Navigate to a hydrated /cart/ with one seeded item already in it.
 *
 * @param {import('@playwright/test').Page} page
 * @returns {Promise<import('@playwright/test').Page>} the same page, for chaining.
 */
export async function gotoCartHydrated(page) {
  await addSimpleProductToCart(page);
  await page.goto('/cart/');

  // Real hydration marker, not networkidle — see file header.
  await page.locator('.wc-block-components-quantity-selector__input').first().waitFor({
    state: 'visible',
  });

  return page;
}

/**
 * Navigate to a hydrated /checkout/ with one seeded item already in the cart.
 *
 * Asserts the final URL is still under /checkout/ BEFORE waiting on the
 * hydration marker: if the cart failed to receive an item, WooCommerce
 * redirects to /cart/ and #email would never appear, turning a clear "wrong
 * page" failure into an opaque hydration timeout. Checking the URL first,
 * right after `goto()` (which resolves only after redirects are followed),
 * keeps the failure message pointing at the actual cause.
 *
 * @param {import('@playwright/test').Page} page
 * @returns {Promise<import('@playwright/test').Page>} the same page, for chaining.
 */
export async function gotoCheckoutHydrated(page) {
  await addSimpleProductToCart(page);
  await page.goto('/checkout/');

  const { pathname } = new URL(page.url());
  if (!pathname.startsWith('/checkout/')) {
    throw new Error(
      `[e2e-woo] gotoCheckoutHydrated() landed on "${pathname}", not /checkout/ — ` +
        'WooCommerce redirects an empty cart to /cart/, so this almost certainly means the ' +
        'add-to-cart step above did not actually add an item to the cart.',
    );
  }

  // Real hydration marker, not networkidle — see file header.
  await page.locator('#email').waitFor({ state: 'visible' });

  return page;
}
