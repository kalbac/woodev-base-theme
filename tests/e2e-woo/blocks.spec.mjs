// tests/e2e-woo/blocks.spec.mjs
//
// The block-based Cart and Checkout, asserted against src/css/woo-blocks.css
// (docs/plans/2026-07-25-block-cart-checkout.md, task B6) and
// docs/adr/ADR-009-block-cart-checkout-styling.md. Both schemes, computed
// style, after real hydration — never the SSR skeleton (ADR-009 finding 8),
// never the source stylesheet, only assets/dist (plan ground rule 1).
//
// Conventions mirrored from storefront.spec.mjs (that file's own header):
//   - computed style over DOM-shape counting;
//   - the { page } fixture only, never browser.newPage();
//   - the runtime `.dark` class toggle for the dark-scheme check, not the
//     color_scheme theme_mod;
//   - where triggering a real state would be flaky, mount Woo's OWN verified
//     markup shape directly into the real wrapper and assert the real cascade
//     against it (storefront.spec.mjs's two-stacked-notices test uses the
//     identical technique for the classic notices; the notice test below
//     does the same for the block notice-banner's four roles).
//
// MUTATION-VERIFICATION METHOD: every assertion below was checked by
// commenting out the CSS rule it guards in src/css/woo-blocks.css, running
// `npm run build`, re-running the affected test against the built bundle, and
// confirming it goes red before restoring the rule and rebuilding. Per-
// assertion results are recorded in the task report, not here — a comment
// claiming "mutation-verified" without the run behind it is exactly the s12
// failure mode (a repair that existed only in a comment) applied to a test.
//
// TWO OPEN QUESTIONS THIS SPEC WAS ALSO ASKED TO SETTLE (B6 brief), answered
// by live DOM measurement against the seeded :8891 store on 26.07.2026 —
// recorded here so a later reader does not have to re-derive them, and NOT
// used to silently widen or narrow src/css/woo-blocks.css's scope:
//
// 1. Does the Cart page render a select-like control? NO, not on this store,
//    under any shipping configuration tried. With the store's original zero
//    shipping methods, the cart shows no shipping row, no "Calculate
//    shipping" prompt, and zero `<select>` elements anywhere in the page (a
//    full-page grep for "postcode"/"calculat" in the rendered DOM/JS state
//    turned up nothing user-visible). Temporarily adding a Flat rate method,
//    then a second (Local pickup) method, to the default shipping zone (both
//    removed again afterward, store restored to its original zero-method
//    state) still produced no `<select>`, no "Calculate shipping" link, and
//    no `.wc-blocks-components-select` anywhere on the cart page — WooCommerce
//    Blocks' cart summary shows the resolved shipping line as plain text
//    ("Shipping … via Flat rate"), never an interactive picker, regardless of
//    method count. This is independent confirmation of what B1's own
//    file-header comment already established from the vendor CSS alone (the
//    class compiles only into checkout.css, never cart.css/packages-style.css)
//    — B1's checkout-only scope for `.wc-blocks-components-select` is correct
//    on both counts: the CSS could not reach a cart-rendered select, and no
//    such select is ever rendered there in the first place.
// 2. Do `.wc-block-components-address-card` and the highlight-checked radio
//    group render on the CART page? The address card: NO — confirmed absent
//    (`page.locator(...).count() === 0`) on `/cart/` in every configuration
//    tried (0, 1 and 2 shipping methods); confirmed PRESENT and visible on
//    `/checkout/` (a real "CA, United States (US)" estimated-address summary
//    card, unprompted, on the default guest flow). The highlight-checked
//    radio group: NOT observed rendering on EITHER page in this session, even
//    with two shipping methods configured on the zone — WooCommerce Blocks
//    picked and displayed the cheapest method as plain text on the cart
//    summary rather than a selectable list, and the checkout block's own
//    `checkout-shipping-method-block` stayed empty throughout (it may need a
//    fully validated postcode entered through real user interaction to
//    compute multiple applicable rates, which this session did not reproduce
//    in the time available). Net effect: B5's `.wc-block-components-address-
//    card` scoping to BOTH wrapper classes is WRONG for the address card by
//    live evidence — it never renders on Cart — though harmless, since an
//    unmatched selector in the built CSS costs nothing at runtime; it is
//    flagged here rather than silently narrowed, per the brief's own
//    instruction, for the orchestrator to decide whether to tighten the CSS
//    scope. The highlight-checked radio group's cart-vs-checkout split could
//    not be settled with actual DOM evidence in this session either way — its
//    scoping is UNPROVEN in both directions, not confirmed correct, and is
//    therefore not asserted anywhere in this file (a test that always finds
//    zero matches would not be a real check).
import { expect, test } from '@playwright/test';

import { gotoCartHydrated, gotoCheckoutHydrated } from './helpers.mjs';

/**
 * Resolve `var(cssVarExpr)` for CSS property `cssProp`, in the PAGE'S CURRENT
 * colour scheme, via an invisible probe element appended to <body>. This is
 * what lets an assertion prove "this element's computed X equals var(--foo)"
 * rather than merely "this element's computed X is not the old vendor
 * literal" — the weaker form would also pass if the override read the WRONG
 * token, which is not what any of these rules are meant to prove.
 */
async function resolveToken(page, cssProp, cssVarExpr) {
  return page.evaluate(
    ({ cssProp, cssVarExpr }) => {
      const probe = document.createElement('div');
      probe.style.setProperty(cssProp, cssVarExpr);
      probe.style.position = 'absolute';
      probe.style.visibility = 'hidden';
      probe.style.pointerEvents = 'none';
      document.body.appendChild(probe);
      const value = getComputedStyle(probe).getPropertyValue(cssProp);
      probe.remove();
      return value;
    },
    { cssProp, cssVarExpr },
  );
}

/** Read one computed CSS property of a Playwright locator's element. */
async function computed(locator, cssProp) {
  return locator.evaluate((el, prop) => getComputedStyle(el).getPropertyValue(prop), cssProp);
}

/** Toggle the runtime dark scheme the same way storefront.spec.mjs does. */
async function setDark(page, on) {
  await page.evaluate((value) => document.documentElement.classList.toggle('dark', value), on);
}

/**
 * Mount one of WooCommerce Blocks' four real notice-banner role variants
 * inside a live wrapper. Triggering all four roles through a real user flow
 * on demand is not reliably deterministic (the store only ever shows the
 * "no payment methods" error notice unprompted, because no gateway is
 * enabled) — this instead reproduces the exact class shape and child
 * structure DevTools showed live on the seeded :8891 checkout
 * (`.wc-block-components-notice-banner.is-error > svg + content`,
 * 26.07.2026), the same "inject Woo's own verified markup into the real
 * wrapper" fallback storefront.spec.mjs's two-stacked-notices test already
 * uses for the classic notices, for the identical reason.
 */
async function mountNoticeBanner(page, wrapperSelector, role) {
  await page.evaluate(
    ({ wrapperSelector, role }) => {
      const svgNs = 'http://www.w3.org/2000/svg';
      const wrapper = document.querySelector(wrapperSelector);

      const div = document.createElement('div');
      div.className = `wc-block-components-notice-banner is-${role}`;
      // Disambiguates a mounted banner from the checkout's own real
      // "no payment methods" `.is-error` notice, which is genuinely present
      // on this store (no gateway is enabled) and would otherwise strict-mode
      // collide with the mounted `.is-error` banner.
      div.dataset.wtbTestNotice = role;

      const svg = document.createElementNS(svgNs, 'svg');
      svg.setAttribute('viewBox', '0 0 24 24');
      svg.setAttribute('width', '24');
      svg.setAttribute('height', '24');
      svg.setAttribute('aria-hidden', 'true');
      svg.setAttribute('focusable', 'false');
      const path = document.createElementNS(svgNs, 'path');
      path.setAttribute('d', 'M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20z');
      svg.appendChild(path);
      div.appendChild(svg);

      const content = document.createElement('div');
      content.className = 'wc-block-components-notice-banner__content';
      content.textContent = `Test ${role} notice.`;
      div.appendChild(content);

      wrapper.prepend(div);
    },
    { wrapperSelector, role },
  );
}

// Vendor's own hardcoded literals (ADR-009 finding 4 / this file's own
// header), asserted absent below alongside the positive "equals the token"
// checks — a rule that merely changed to SOME non-white colour would still
// pass an `.not.toBe('#fff')`-only check without actually reading our token.
const VENDOR_INPUT_WHITE = 'rgb(255, 255, 255)';
const VENDOR_BUTTON_GREY = 'rgb(50, 55, 60)';
const VENDOR_NOTICE_ERROR_BG = 'rgb(255, 240, 240)';
const VENDOR_NOTICE_ERROR_BORDER = 'rgb(204, 24, 24)';

test.describe('block Checkout', () => {
  test("a text input's background/border/text colour follow the theme tokens in both schemes, and dark is never #fff", async ({
    page,
  }) => {
    // Guards src/css/woo-blocks.css's B1 text-input rule (file lines ~120-136):
    // the one genuine defect ADR-009 found — white bg / near-black text on a
    // dark page. `#email` is real, hydrated markup (helpers.mjs), not mounted.
    await gotoCheckoutHydrated(page);
    const input = page.locator('#email');
    await expect(input).toBeVisible();

    const read = async () => ({
      bg: await computed(input, 'background-color'),
      border: await computed(input, 'border-color'),
      color: await computed(input, 'color'),
    });

    const light = await read();
    const lightBgExpected = await resolveToken(page, 'background-color', 'var(--background)');
    const lightBorderExpected = await resolveToken(page, 'border-color', 'var(--border)');
    const lightColorExpected = await resolveToken(page, 'color', 'var(--foreground)');

    await setDark(page, true);
    const dark = await read();
    const darkBgExpected = await resolveToken(page, 'background-color', 'var(--background)');
    const darkBorderExpected = await resolveToken(page, 'border-color', 'var(--border)');
    const darkColorExpected = await resolveToken(page, 'color', 'var(--foreground)');

    expect(dark.bg, 'dark input background must never be the vendor #fff literal').not.toBe(
      VENDOR_INPUT_WHITE,
    );
    expect(dark.bg, 'scheme must actually change the computed background').not.toBe(light.bg);
    expect(light.bg, 'light background follows --background').toBe(lightBgExpected);
    expect(light.border, 'light border follows --border').toBe(lightBorderExpected);
    expect(light.color, 'light text colour follows --foreground').toBe(lightColorExpected);
    expect(dark.bg, 'dark background follows --background').toBe(darkBgExpected);
    expect(dark.border, 'dark border follows --border').toBe(darkBorderExpected);
    expect(dark.color, 'dark text colour follows --foreground').toBe(darkColorExpected);
  });

  test('the country/region select follows the theme tokens in both schemes', async ({ page }) => {
    // Guards src/css/woo-blocks.css's B1 select rule (file lines ~161-172).
    // checkout.css-only per B1's own header (confirmed absent from cart.css /
    // packages-style.css), so this is a checkout-only test on purpose.
    await gotoCheckoutHydrated(page);
    const container = page.locator('.wc-blocks-components-select__container').first();
    const select = page.locator('.wc-blocks-components-select__select').first();
    await expect(container).toBeVisible();

    const read = async () => ({
      containerBg: await computed(container, 'background-color'),
      selectBorder: await computed(select, 'border-color'),
      selectColor: await computed(select, 'color'),
    });

    const light = await read();
    const lightBorderExpected = await resolveToken(page, 'border-color', 'var(--border)');
    const lightColorExpected = await resolveToken(page, 'color', 'var(--foreground)');

    await setDark(page, true);
    const dark = await read();

    const bgExpected = await resolveToken(page, 'background-color', 'var(--background)');
    const borderExpected = await resolveToken(page, 'border-color', 'var(--border)');
    const colorExpected = await resolveToken(page, 'color', 'var(--foreground)');

    expect(dark.containerBg, 'dark select container background never #fff').not.toBe(
      VENDOR_INPUT_WHITE,
    );
    expect(dark.containerBg, 'scheme must actually change the computed background').not.toBe(
      light.containerBg,
    );
    expect(light.selectBorder, 'light select border follows --border').toBe(lightBorderExpected);
    expect(light.selectColor, 'light select text colour follows --foreground').toBe(
      lightColorExpected,
    );
    expect(dark.containerBg, 'container background follows --background').toBe(bgExpected);
    expect(dark.selectBorder, 'select border follows --border').toBe(borderExpected);
    expect(dark.selectColor, 'select text colour follows --foreground').toBe(colorExpected);
  });

  test('the place-order button follows --primary / --primary-foreground in both schemes', async ({
    page,
  }) => {
    // Guards src/css/woo-blocks.css's B3 resting-colour rule (file lines
    // ~625-631) — the same shared `.wp-element-button:not(...)` selector the
    // cart's "proceed to checkout" button uses (tested separately below).
    await gotoCheckoutHydrated(page);
    const button = page.locator('.wc-block-components-checkout-place-order-button');
    await expect(button).toBeVisible();

    const read = async () => ({
      bg: await computed(button, 'background-color'),
      fg: await computed(button, 'color'),
    });

    const light = await read();
    await setDark(page, true);
    const dark = await read();

    const bgExpected = await resolveToken(page, 'background-color', 'var(--primary)');
    const fgExpected = await resolveToken(page, 'color', 'var(--primary-foreground)');

    expect(dark.bg, 'place-order button never the WP-core global-styles grey').not.toBe(
      VENDOR_BUTTON_GREY,
    );
    expect(dark.bg, 'scheme must actually change the computed background').not.toBe(light.bg);
    expect(dark.bg, 'button background follows --primary').toBe(bgExpected);
    expect(dark.fg, 'button text colour follows --primary-foreground').toBe(fgExpected);
  });

  test('radii on the checkout form controls and panels follow --radius', async ({ page }) => {
    // Guards three independent B1/B5 radius declarations at once — each
    // target below is mutation-verified individually (task report), so a
    // single reverted rule fails exactly its own row here, not the others.
    await gotoCheckoutHydrated(page);

    const expected = await resolveToken(page, 'border-top-left-radius', 'var(--radius)');
    // Sanity: the token itself must resolve to a real radius, or every
    // row below would trivially pass against an empty/zero expectation.
    expect(expected).not.toBe('0px');

    const targets = [
      ['text input (#email)', page.locator('#email')],
      ['select container', page.locator('.wc-blocks-components-select__container').first()],
      ['address card', page.locator('.wc-block-components-address-card')],
    ];

    for (const [label, locator] of targets) {
      await expect(locator, `${label} should exist on checkout`).toHaveCount(1);
      const radius = await computed(locator, 'border-top-left-radius');
      expect(radius, `${label} border-radius should follow --radius`).toBe(expected);
    }
  });

  test('notice banners use the destructive/success/warning/info roles, never the vendor literals, and the four roles stay visually distinct, in both schemes', async ({
    page,
  }) => {
    // Guards src/css/woo-blocks.css's B4 rules: the shared base rule (file
    // lines ~754-760) plus all four `.is-*` role overrides (lines ~774-830).
    // Covers all four roles on purpose — s12's own notice test mounted only
    // `.woocommerce-error` and passed with its fix reverted (this file's own
    // B4 header cites the same lesson). Checked in BOTH schemes: mount once,
    // read in light, toggle the runtime `.dark` class, read the SAME elements
    // again — proving the cascade keeps following its tokens across the
    // switch, not merely that it happened to be right in whichever scheme the
    // test ran in.
    await gotoCheckoutHydrated(page);

    const roles = ['error', 'warning', 'success', 'info'];
    const bannerLocator = (role) =>
      page.locator(`.wp-block-woocommerce-checkout [data-wtb-test-notice="${role}"]`);

    for (const role of roles) {
      await mountNoticeBanner(page, '.wp-block-woocommerce-checkout', role);
      await expect(bannerLocator(role)).toBeVisible();
    }

    const readAll = async () => {
      const out = {};
      for (const role of roles) {
        const banner = bannerLocator(role);
        out[role] = {
          bg: await computed(banner, 'background-color'),
          border: await computed(banner, 'border-color'),
          color: await computed(banner, 'color'),
          radius: await computed(banner, 'border-top-left-radius'),
        };
      }
      return out;
    };

    // Each role's background/border tie the EXACT `color-mix()` expression
    // src/css/woo-blocks.css declares for it (file lines ~774-830), not a
    // bare `var(--role)` — the latter would still pass if the mix percentage
    // or base colour drifted from what the stylesheet actually resolves.
    const roleTokens = {
      error: {
        border: 'var(--destructive)',
        bg: 'color-mix(in oklab, var(--destructive) 9%, var(--card))',
      },
      warning: {
        border: 'var(--warning)',
        bg: 'color-mix(in oklab, var(--warning) 9%, var(--card))',
      },
      success: {
        border: 'var(--success)',
        bg: 'color-mix(in oklab, var(--success) 9%, var(--card))',
      },
      info: {
        border: 'var(--primary)',
        bg: 'color-mix(in oklab, var(--primary) 8%, var(--card))',
      },
    };

    // Shared base rule (file lines ~754-760): text colour and radius are set
    // ONCE on the un-suffixed selector, not per role — checked via a single
    // role, in each scheme, rather than duplicated four times per scheme.
    async function assertScheme(results, scheme) {
      const foregroundExpected = await resolveToken(page, 'color', 'var(--foreground)');
      const radiusExpected = await resolveToken(page, 'border-top-left-radius', 'var(--radius)');
      expect(results.error.color, `${scheme}: notice text colour follows --foreground`).toBe(
        foregroundExpected,
      );
      expect(results.error.radius, `${scheme}: notice radius follows --radius`).toBe(
        radiusExpected,
      );

      for (const role of roles) {
        const { border: borderExpr, bg: bgExpr } = roleTokens[role];
        const borderExpected = await resolveToken(page, 'border-color', borderExpr);
        const bgExpected = await resolveToken(page, 'background-color', bgExpr);
        expect(results[role].border, `${scheme}: ${role} border follows its role token`).toBe(
          borderExpected,
        );
        expect(results[role].bg, `${scheme}: ${role} background follows its role token`).toBe(
          bgExpected,
        );
      }

      // Four roles, four distinct backgrounds — not one shared default that
      // happens to satisfy every per-role assertion above (same shape as
      // storefront.spec.mjs's classic-notice test's own distinctness check).
      const distinctBackgrounds = new Set(Object.values(results).map((r) => r.bg));
      expect(distinctBackgrounds.size, `${scheme}: four distinct backgrounds`).toBe(4);
    }

    const light = await readAll();
    // The task brief's explicit ask: the destructive role, checked against
    // the vendor's own hardcoded literals in the scheme actually rendered —
    // a token-mix result could coincidentally match a vendor literal in one
    // scheme without this check catching it there.
    expect(light.error.border, 'light: error role never the vendor #cc1818 literal').not.toBe(
      VENDOR_NOTICE_ERROR_BORDER,
    );
    expect(light.error.bg, 'light: error role never the vendor #fff0f0 literal').not.toBe(
      VENDOR_NOTICE_ERROR_BG,
    );
    await assertScheme(light, 'light');

    await setDark(page, true);
    const dark = await readAll();
    expect(dark.error.border, 'dark: error role never the vendor #cc1818 literal').not.toBe(
      VENDOR_NOTICE_ERROR_BORDER,
    );
    expect(dark.error.bg, 'dark: error role never the vendor #fff0f0 literal').not.toBe(
      VENDOR_NOTICE_ERROR_BG,
    );
    await assertScheme(dark, 'dark');

    expect(dark.error.bg, 'scheme must actually change the computed background').not.toBe(
      light.error.bg,
    );
  });
});

test.describe('block Cart', () => {
  // "The cart page's totals and buttons match" (B6 brief). Buttons are
  // asserted below. Totals rows are DELIBERATELY not asserted here: ADR-009
  // finding 4, measured before any of this stylesheet was written, is that
  // the totals rows already inherit our tokens/fonts via `currentColor` and
  // `inherit` in BOTH schemes, so src/css/woo-blocks.css carries no rule for
  // them at all (decision 4's own "style the hardcoded set only"). There is
  // therefore no CSS to mutate for a "totals follow the theme" assertion —
  // writing one anyway would look like coverage of a fix that does not
  // exist, exactly what the brief's "say so rather than assert something
  // weaker" line warns against. Said so here instead.

  test('the quantity-selector radius follows --radius (a cart-only rule)', async ({ page }) => {
    // Guards src/css/woo-blocks.css's B5 quantity-selector rule (file lines
    // ~869-871) — real markup only in cart.css per that rule's own comment,
    // so this is deliberately a cart-only test, unlike every rule above.
    await gotoCartHydrated(page);
    const selector = page.locator('.wc-block-components-quantity-selector').first();
    await expect(selector).toBeVisible();

    const expected = await resolveToken(page, 'border-top-left-radius', 'var(--radius)');
    const radius = await computed(selector, 'border-top-left-radius');
    expect(radius).toBe(expected);
  });

  test("the proceed-to-checkout button matches the checkout place-order button's --primary / --primary-foreground treatment", async ({
    page,
  }) => {
    // Same shared B3 rule as the checkout place-order test above — the cart
    // and checkout wrappers are both listed in that single selector, so this
    // is the "buttons match" half of the B6 brief's cart bullet, and a single
    // reverted rule fails BOTH this test and the checkout one.
    await gotoCartHydrated(page);
    const button = page.locator('.wc-block-cart__submit-button');
    await expect(button).toBeVisible();

    const read = async () => ({
      bg: await computed(button, 'background-color'),
      fg: await computed(button, 'color'),
    });

    const light = await read();
    await setDark(page, true);
    const dark = await read();

    const bgExpected = await resolveToken(page, 'background-color', 'var(--primary)');
    const fgExpected = await resolveToken(page, 'color', 'var(--primary-foreground)');

    expect(dark.bg, 'cart button never the WP-core global-styles grey').not.toBe(
      VENDOR_BUTTON_GREY,
    );
    expect(dark.bg, 'scheme must actually change the computed background').not.toBe(light.bg);
    expect(dark.bg, 'cart button background follows --primary').toBe(bgExpected);
    expect(dark.fg, 'cart button text colour follows --primary-foreground').toBe(fgExpected);
  });
});
