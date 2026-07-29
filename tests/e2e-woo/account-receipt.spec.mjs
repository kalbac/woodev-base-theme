// tests/e2e-woo/account-receipt.spec.mjs
//
// My Account (#42, plan rows M1, M2-CSS, M4-CSS, M5-CSS, M6-CSS, M7-CSS, M8,
// M9, M10) and the order-received "thank you" screen (R1-CSS, R2, R3, R4,
// R5-CSS). CSS-only scope: src/css/woo/account.css and src/css/woo/receipt.css
// — this file guards THOSE rules, never the PHP/template contract, which is
// already merged.
//
// Every assertion reads COMPUTED STYLE or GEOMETRY, never markup — the whole
// point per docs/gotchas/source-order-only-wins-the-properties-you-redeclare.md
// — and every comparison is measurement-against-measurement, never against a
// raw token/selector string, per
// docs/gotchas/qa-gates-cover-less-than-they-claim.md. Conventions follow
// catalogue.spec.mjs / storefront.spec.mjs: the { page } fixture only (never
// browser.newPage()), products/orders located through the seeded fixtures.
//
// Three defects were found by *looking at the screenshots* while writing this
// file, none visible from the markup, all now fixed in src/css/woo/{account,
// receipt}.css and re-verified here:
//
//   1. M1 — WooCommerce's own `.woocommerce::after, .woocommerce-account
//      .woocommerce::before { content:" "; display:table }` turns BOTH
//      pseudo-elements into grid items the moment `.woocommerce` becomes a
//      grid container, pushing the real nav/content children apart (nav top
//      right, content bottom left) instead of side by side.
//   2. M1 — the float/width reset for the nav/content columns targeted the
//      wrong (unscoped) selector; Woo's real rule is `.woocommerce-account
//      .woocommerce-MyAccount-navigation/-content`, two classes, and the
//      unscoped reset lost to it on specificity regardless of source order.
//   3. R2 — the exact same clearfix trap a THIRD time: `.woocommerce
//      ul.order_details::before/::after` (the order-overview <ul> also
//      carries the `order_details` class), splitting a 5-item auto-fit grid
//      into 4-in-a-row plus one orphaned on its own line.
//   4. M8 — the address card's heading+edit-link row had no `flex-wrap` and
//      not enough room at realistic card widths (~190px), squeezing both the
//      heading and the button down to a sliver and wrapping their text one
//      word (sometimes one syllable) per line.
//   5. M1 — the account-layout grid had no explicit `grid-template-columns`
//      below the 64rem breakpoint, so its one implicit column sized to the
//      MAX-CONTENT of its widest descendant (the edit-account form's
//      `max-width: 40rem`) rather than clamping to the container — the whole
//      page overflowed horizontally on a phone-width viewport.
//
// Every assertion group below states which mutation was used to confirm it
// fails red before the fix, and is restored (not left as a comment guess).
import { expect, test } from '@playwright/test';

import { loadFixtures } from './fixtures.mjs';

// Playwright's 30s default is not enough for this file, and the reason is
// latency rather than anything under test. Every test here logs in through the
// real classic form (two navigations) before it can measure anything, and the
// layout tests then navigate again — on this wp-env container a page load costs
// 8-12s once it has been up a while (a documented degradation; see
// next-session-promt.md's environment notes). Measured on a clean run: the
// passing tests land between 13s and 29s, i.e. every one of them was one slow
// load away from a red that says "timeout" and means nothing, and the
// four-navigation M1 test failed reproducibly at 30s while being entirely
// correct. Raised here rather than in `playwright.woo.config.mjs` so the other
// suites keep the tighter default — a slow spec should be the exception it is
// named as, not a relaxation everything inherits.
test.describe.configure({ timeout: 90_000 });

const CUSTOMER = { username: 'wtb-e2e-customer', password: 'WtbE2eCustomer!1' };

/** Log in as the seeded customer through the real classic login form. */
async function login(page) {
  await page.goto('/my-account/');

  // Return early when the session is already live. Nothing in THIS file calls
  // `login()` twice today, but the identical helper in cart-checkout.spec.mjs
  // did (once directly, once through a cart-reset helper) and it cost a
  // 16-minute run: on the second call `/my-account/` renders the dashboard,
  // which has no `#username` at all, so the helper waited out its whole
  // timeout for a field that cannot appear while the state it was asked for
  // already held. Kept in step here so the next test added to this file does
  // not rediscover it.
  if ((await page.locator('.woocommerce-MyAccount-navigation').count()) > 0) {
    return;
  }

  await page.fill('#username', CUSTOMER.username);
  await page.fill('#password', CUSTOMER.password);
  await page.click('button[name="login"]');
  await page.waitForLoadState('domcontentloaded');
  // Real proof the session took, not an assumption the click "worked".
  //
  // Explicit 15s rather than Playwright's 5s `expect` default, for the same
  // measured reason as this file's raised test timeout: a page load on this
  // container costs 8-12s once it has been up a while. On a full 32-test run
  // two tests failed here — not on anything they assert, but on the login
  // itself timing out at 5s while the dashboard was still on its way. That is
  // a red that reads like a product defect and is not one, which is the worst
  // kind. cart-checkout.spec.mjs's own login already used an explicit timeout;
  // this brings the two in step.
  await expect(page.locator('.woocommerce-MyAccount-navigation')).toBeVisible({
    timeout: 15_000,
  });
}

test.describe('My Account layout (M1)', () => {
  test('the nav and content columns sit side by side at desktop width, each filling its own track', async ({
    page,
  }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await login(page);

    // MUTATION 1 (confirmed red, then restored): commented out the
    // `&:has(...)::before/::after { display: none }` rule in account.css.
    // Woo's own `.woocommerce::after`/`.woocommerce-account
    // .woocommerce::before` clearfix then re-entered the grid as two extra
    // items, and this assertion failed: `content.top` came back ~345px
    // greater than `nav.top` (stacked, not side by side) instead of within
    // 1px of it.
    const measured = await page.evaluate(() => {
      const nav = document.querySelector('nav.woocommerce-MyAccount-navigation');
      const content = document.querySelector('.woocommerce-MyAccount-content');
      const navBox = nav.getBoundingClientRect();
      const contentBox = content.getBoundingClientRect();
      return {
        navTop: navBox.top,
        contentTop: contentBox.top,
        navLeft: navBox.left,
        contentLeft: contentBox.left,
        navWidth: navBox.width,
        contentWidth: contentBox.width,
      };
    });

    // Same row: top edges within a rounding pixel of each other.
    expect(Math.abs(measured.navTop - measured.contentTop)).toBeLessThan(2);
    // Side by side: content starts well to the right of where nav ends.
    expect(measured.contentLeft).toBeGreaterThan(measured.navLeft + measured.navWidth - 2);

    // MUTATION 2 (confirmed red, then restored): changed the bottom
    // `.woocommerce-account .woocommerce-MyAccount-navigation, … -content`
    // reset to the unscoped `.woocommerce-MyAccount-navigation, …-content`
    // (one class short of Woo's real selector). `navWidth` came back
    // 124.67px against a 230px track (float:left/width:30% survived) —
    // this assertion is the one that would catch it: a correctly-reset
    // nav fills essentially the whole 230px track.
    expect(measured.navWidth).toBeGreaterThan(200);
    // The content column gets whatever is left of the row — a real
    // fraction of the row, not a shrunk sliver either.
    expect(measured.contentWidth).toBeGreaterThan(300);
  });

  test('collapses to one column below 64rem, with the nav scrolling horizontally, and never overflows the viewport', async ({
    page,
  }) => {
    await page.setViewportSize({ width: 390, height: 900 });
    await login(page);

    // MUTATION 3 (confirmed red, then restored): the account-layout grid's
    // base rule had no explicit `grid-template-columns` (relying on "no
    // columns declared ⇒ one column at container width", which is false —
    // the implicit auto column sizes to descendant MAX-CONTENT instead).
    // Reached via /my-account/edit-account/, whose form carries
    // `max-width: 40rem` (640px).
    //
    // Measured on the FORM'S OWN WIDTH, not `document.documentElement.
    // scrollWidth` — the first version of this assertion used scrollWidth
    // and it is VACUOUS on this theme: `html`/`body` both compute
    // `overflow-x: clip` (a base reset), so an overflowing descendant gets
    // silently clipped rather than becoming scrollable, and `scrollWidth`
    // reported 390 (no overflow) even while the form was 640px wide and
    // visibly cut off in the screenshot. `getBoundingClientRect().width`
    // on the actual element is a direct measurement of the real bug
    // regardless of how the browser reacts to the overflow.
    await page.goto('/my-account/edit-account/');
    const widths = await page.evaluate(() => ({
      wrap: document.querySelector('.woocommerce').getBoundingClientRect().width,
      form: document.querySelector('.woocommerce-EditAccountForm').getBoundingClientRect().width,
    }));
    expect(widths.form).toBeLessThanOrEqual(widths.wrap + 1);

    await page.goto('/my-account/');
    const stacked = await page.evaluate(() => {
      const nav = document.querySelector('nav.woocommerce-MyAccount-navigation');
      const content = document.querySelector('.woocommerce-MyAccount-content');
      return {
        navBottom: nav.getBoundingClientRect().bottom,
        contentTop: content.getBoundingClientRect().top,
      };
    });
    // One column: content starts at or after the bottom of the nav that
    // precedes it, not beside it.
    expect(stacked.contentTop).toBeGreaterThanOrEqual(stacked.navBottom - 2);

    // The nav's own list goes horizontal and scrollable (mockup line 924),
    // not a vertical column squeezed onto a phone screen.
    const navList = await page.evaluate(() => {
      const ul = document.querySelector('.woocommerce-MyAccount-navigation ul');
      const cs = getComputedStyle(ul);
      return {
        flexDirection: cs.flexDirection,
        overflowX: cs.overflowX,
        scrollWidth: ul.scrollWidth,
        clientWidth: ul.clientWidth,
      };
    });
    expect(navList.flexDirection).toBe('row');
    expect(navList.overflowX).toBe('auto');

    // `flex-direction: row` + `overflow-x: auto` only prove the list COULD
    // scroll. If the seven items shrank to fit a 390px viewport instead, both
    // assertions stay green while "scrolling horizontally" — the thing this
    // test's own name claims — does not happen. Measuring the scroll container
    // itself is the honest probe, and it is NOT the trap the M1 desktop test
    // documents: that one was `document.documentElement.scrollWidth` on a root
    // whose `overflow-x` computes to `clip`, which cannot report overflow. This
    // is the element that actually owns the scrollport, and `auto` (asserted
    // above) is what makes its `scrollWidth` meaningful.
    expect(navList.scrollWidth).toBeGreaterThan(navList.clientWidth);
  });
});

test.describe('Account nav icon (M2)', () => {
  test('is sized to 18px and dims until its own item is the active one', async ({ page }) => {
    await login(page);

    // An `<li>` existing does not mean its icon does — `Account::nav_icon()`
    // returns '' for an endpoint it has no mapping for. Asserting the icon
    // COUNT against the item count first turns "the icons stopped rendering"
    // into that sentence, instead of a browser-side TypeError on
    // `getComputedStyle(null)` that says nothing about what broke.
    const navItems = page.locator('.woocommerce-MyAccount-navigation li');
    const navIcons = page.locator('.woocommerce-MyAccount-navigation .wtb-account-nav__icon');
    const navItemCount = await navItems.count();
    expect(navItemCount).toBeGreaterThan(1);
    await expect(navIcons).toHaveCount(navItemCount);

    const items = await page.evaluate(() => {
      const lis = [...document.querySelectorAll('.woocommerce-MyAccount-navigation li')];
      return lis.map((li) => {
        const icon = li.querySelector('.wtb-account-nav__icon');
        const cs = getComputedStyle(icon);
        return {
          active: li.classList.contains('is-active'),
          opacity: cs.opacity,
          width: cs.width,
        };
      });
    });

    expect(items.length).toBeGreaterThan(1);
    const active = items.find((i) => i.active);
    const inactive = items.filter((i) => !i.active);
    expect(active, 'no li.is-active on the dashboard endpoint').toBeTruthy();
    expect(inactive.length).toBeGreaterThan(0);

    for (const item of items) {
      expect(item.width).toBe('18px');
    }

    // MUTATION (confirmed red, then restored): deleted the
    // `li.is-active .wtb-account-nav__icon { opacity: 1 }` rule. Every icon
    // then reported the same 0.7 opacity and this comparison — active
    // opacity vs an inactive one, both READ from the page, never against a
    // literal — failed.
    expect(active.opacity).not.toBe(inactive[0].opacity);
    expect(Number(active.opacity)).toBeGreaterThan(Number(inactive[0].opacity));
  });
});

test.describe('Dashboard metric cards and section heading (M4, M5)', () => {
  test('the metric cards render as a bordered grid, not a stack of full-width blocks', async ({
    page,
  }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await login(page);

    const cards = page.locator('.wtb-dash-card');
    await expect(cards).toHaveCount(3);

    // MUTATION (confirmed red, then restored): changed `.wtb-dash-cards`'s
    // `display: grid` to `display: block`. The three cards then stacked
    // full width and shared no row — `top` differed by each card's full
    // height instead of being within a rounding pixel.
    const boxes = await page.evaluate(() =>
      [...document.querySelectorAll('.wtb-dash-card')].map((el) =>
        el.getBoundingClientRect().toJSON(),
      ),
    );
    expect(Math.abs(boxes[0].top - boxes[1].top)).toBeLessThan(2);
    // And each is visibly a card: a real border, not the page background
    // bleeding through.
    const cardStyle = await cards.first().evaluate((el) => getComputedStyle(el).borderWidth);
    expect(cardStyle).not.toBe('0px');
  });

  test('the "Recent orders" heading is not styled like a plain, unclassed core heading', async ({
    page,
  }) => {
    await login(page);

    // A plain, unclassed <h2> as the measurement-vs-measurement baseline.
    // `order/order-downloads.php`'s own optional title (the first version
    // of this test targeted "Available downloads" there) turned out to be
    // dead weight: `woocommerce_order_downloads_table()` never passes
    // `show_title`, so that heading never actually renders on
    // /my-account/downloads/ — checked by reading the real DOM, not
    // assumed from the template source a second time. No page under this
    // theme ships a genuinely bare <h2> to point at, so one is mounted
    // directly, the same fallback the M6 neutral-tone badge above uses.
    //
    // `margin`, not `fontSize`: the theme's own base typography already
    // gives every <h2> the same 24px/Golos-Text/-0.02em treatment this
    // rule also states (checked live — a synthetic plain <h2> matched
    // `.wtb-account-section-title` on all three), so this rule's real,
    // non-redundant contribution is the SPACING around the heading
    // (`var(--space-2xl) 0 var(--space-md)` vs the base rule's own
    // margin), which is what the mockup actually asks for here (a
    // section break above "Recent orders", not a font change).
    //
    // MUTATION (confirmed red, then restored): emptied out the
    // `.wtb-account-section-title` rule body. `sectionMargin` then equalled
    // `plainMargin` and this comparison failed.
    const sectionMargin = await page
      .locator('.wtb-account-section-title')
      .evaluate((el) => getComputedStyle(el).margin);

    const plainMargin = await page.evaluate(() => {
      const h2 = document.createElement('h2');
      h2.textContent = 'Plain heading, no class';
      document.querySelector('.woocommerce-MyAccount-content').append(h2);
      return getComputedStyle(h2).margin;
    });

    expect(sectionMargin).not.toBe(plainMargin);
  });
});

test.describe('Order status badges (M6)', () => {
  test('the three tones are visually distinct from each other', async ({ page }) => {
    await login(page);

    // The seeded customer's two real orders give two real tones
    // (processing → accent, completed → success — Woo\Account::
    // STATUS_TONES, not re-verified here, that mapping is PHP's job).
    const badges = page.locator('.wtb-recent-orders .wtb-status-badge');
    await expect(badges).toHaveCount(2);
    // Visibility first (docs/gotchas/qa-gates-cover-less-than-they-claim.md
    // — a hidden badge would make every colour comparison vacuously true).
    await expect(badges.first()).toBeVisible();
    await expect(badges.last()).toBeVisible();

    const [tone1, tone2] = await Promise.all([
      badges.first().evaluate((el) => getComputedStyle(el).backgroundColor),
      badges.last().evaluate((el) => getComputedStyle(el).backgroundColor),
    ]);
    expect(tone1).not.toBe(tone2);

    // The third tone (`neutral`) has no seeded order to carry it — none of
    // `pending`/`on-hold`/`cancelled`/`refunded`/`failed` are in the
    // fixture — so it is mounted directly, the same fallback
    // storefront.spec.mjs's stacked-notices test uses for a shape a normal
    // user flow cannot reliably produce.
    const neutralBg = await page.evaluate(() => {
      const span = document.createElement('span');
      span.className = 'wtb-status-badge wtb-status-badge--neutral';
      span.textContent = 'Neutral';
      document.querySelector('.wtb-recent-orders').before(span);
      return getComputedStyle(span).backgroundColor;
    });

    // MUTATION (confirmed red, then restored): set all three
    // `.wtb-status-badge--{tone}` rules' `background-color` to the same
    // `var(--secondary)`. `tone1`/`tone2` collapsed to the same string and
    // `neutralBg` matched `tone1` too — every assertion in this block
    // failed together.
    expect(neutralBg).not.toBe(tone1);
    expect(neutralBg).not.toBe(tone2);
    expect(new Set([tone1, tone2, neutralBg]).size).toBe(3);
  });
});

test.describe('View-order head and meta grid (M7)', () => {
  test('the head lays the title and badge on one row, and the meta grid is a real 4-up card', async ({
    page,
  }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await login(page);
    const { orders } = loadFixtures();
    await page.goto(`/my-account/view-order/${orders.processing.id}/`);

    const head = page.locator('.wtb-order-head');
    await expect(head).toBeVisible();

    // MUTATION (confirmed red, then restored): changed `.wtb-order-head`'s
    // `display: flex` to `display: block`. `title.top`/`badge.top` then
    // differed by the title's full line height instead of sharing a row.
    // Both children asserted present first: `.wtb-order-head` being visible
    // says nothing about what is inside it, and a missing title or badge would
    // otherwise surface as a TypeError on `getBoundingClientRect()` of null
    // rather than as "the badge is gone".
    await expect(head.locator('.wtb-order-head__title')).toBeVisible();
    await expect(head.locator('.wtb-status-badge')).toBeVisible();

    const rowMeasured = await head.evaluate((el) => {
      const title = el.querySelector('.wtb-order-head__title');
      const badge = el.querySelector('.wtb-status-badge');
      return {
        titleTop: title.getBoundingClientRect().top,
        badgeTop: badge.getBoundingClientRect().top,
      };
    });
    expect(Math.abs(rowMeasured.titleTop - rowMeasured.badgeTop)).toBeLessThan(4);

    // The meta grid: shared with the receipt's order-overview (R2) — see
    // that test for the auto-fit/clearfix regression coverage. Here, only
    // that it IS a multi-column grid and not the `display: block` stack
    // core ships by default.
    const meta = page.locator('.wtb-order-meta');
    await expect(meta).toBeVisible();
    const metaDisplay = await meta.evaluate((el) => getComputedStyle(el).display);
    expect(metaDisplay).toBe('grid');
    const metaItems = meta.locator('> div');
    await expect(metaItems).toHaveCount(4);

    // `display: grid` plus four children does NOT prove a 4-UP row: a
    // single-column grid (`grid-template-columns: 1fr`) with four items
    // satisfies both, while the meta fields stack vertically — which is the
    // exact regression this test names. Measuring that the four boxes share a
    // row is the claim; comparing their `top` coordinates is measurement
    // against measurement, so it cannot be satisfied vacuously.
    const tops = await meta.evaluate((el) =>
      [...el.children].map((child) => Math.round(child.getBoundingClientRect().top)),
    );
    expect(tops).toHaveLength(4);
    for (const top of tops) {
      expect(top).toBe(tops[0]);
    }
  });
});

test.describe('Addresses (M8)', () => {
  test('the card heading and edit link never squeeze into a letter-per-line wrap', async ({
    page,
  }) => {
    // The narrowest realistic width for a `.col2-set` card at this account
    // layout's 230px+content split — the defect this guards was found at
    // exactly this viewport, not an extreme one.
    await page.setViewportSize({ width: 1280, height: 900 });
    await login(page);
    await page.goto('/my-account/edit-address/');

    const title = page.locator('.woocommerce-Address-title').first();
    await expect(title).toBeVisible();

    // MUTATION (confirmed red, then restored): reverted `.woocommerce-
    // Address-title` to a ROW (`display: flex; flex-direction: row;
    // justify-content: space-between`) — the version that shipped first.
    // `h2.getBoundingClientRect().width` measured ~10px (a single glyph's
    // width) against ~13px font, and the rendered TEXT wrapped one
    // character to a line — this line-count assertion is what catches it:
    // a heading whose own width comfortably exceeds a single glyph renders
    // its short text ("Billing address") on very few lines, not a dozen.
    const measured = await title.evaluate((el) => {
      const h2 = el.querySelector('h2');
      const box = h2.getBoundingClientRect();
      const lineHeight = Number.parseFloat(getComputedStyle(h2).lineHeight);
      return { width: box.width, lines: Math.round(box.height / lineHeight) };
    });
    expect(measured.width).toBeGreaterThan(40);
    expect(measured.lines).toBeLessThanOrEqual(2);

    // And the heading sits ABOVE the edit link, not squeezed beside it —
    // the actual layout change (column instead of row).
    const stacked = await title.evaluate((el) => {
      const h2 = el.querySelector('h2');
      const edit = el.querySelector('a.edit');
      return {
        h2Bottom: h2.getBoundingClientRect().bottom,
        editTop: edit.getBoundingClientRect().top,
      };
    });
    expect(stacked.editTop).toBeGreaterThanOrEqual(stacked.h2Bottom - 2);
  });
});

test.describe('Downloads table (M9)', () => {
  test('the numeric columns are monospaced and the file cell aligns right', async ({ page }) => {
    await login(page);
    await page.goto('/my-account/downloads/');

    const remaining = page.locator('.download-remaining').first();
    const product = page.locator('.download-product').first();
    await expect(remaining).toBeVisible();

    // MUTATION (confirmed red, then restored): removed the `font-family`/
    // `font-variant-numeric` declarations from the `.download-remaining,
    // .download-expires` rule. Both columns then reported the SAME font
    // stack as the product name column, and this comparison failed.
    const [remainingFont, productFont] = await Promise.all([
      remaining.evaluate((el) => getComputedStyle(el).fontFamily),
      product.evaluate((el) => getComputedStyle(el).fontFamily),
    ]);
    expect(remainingFont).not.toBe(productFont);
    expect(remainingFont).toContain('Mono');

    const fileCellAlign = await page
      .locator('.download-file')
      .first()
      .evaluate((el) => getComputedStyle(el).textAlign);
    expect(fileCellAlign).toBe('right');
  });
});

test.describe('Edit-account form (M10)', () => {
  test('lays out first/last name side by side and hides the clearfix spacers', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await login(page);
    await page.goto('/my-account/edit-account/');

    const form = page.locator('form.woocommerce-EditAccountForm');
    await expect(form).toBeVisible();

    const formDisplay = await form.evaluate((el) => getComputedStyle(el).display);
    expect(formDisplay).toBe('grid');

    // First/last name share a row (2-column grid), display name is full
    // width (`--wide`).
    const rows = await page.evaluate(() => {
      const first = document.querySelector('p.woocommerce-form-row--first');
      const last = document.querySelector('p.woocommerce-form-row--last');
      const wide = document.querySelector('p.woocommerce-form-row--wide');
      return {
        firstTop: first.getBoundingClientRect().top,
        lastTop: last.getBoundingClientRect().top,
        firstLeft: first.getBoundingClientRect().left,
        lastLeft: last.getBoundingClientRect().left,
        wideWidth: wide.getBoundingClientRect().width,
        formWidth: document
          .querySelector('form.woocommerce-EditAccountForm')
          .getBoundingClientRect().width,
      };
    });
    expect(Math.abs(rows.firstTop - rows.lastTop)).toBeLessThan(2);
    expect(rows.lastLeft).toBeGreaterThan(rows.firstLeft);
    // The wide row spans (close to) the form's full width, not one column.
    expect(rows.wideWidth).toBeGreaterThan(rows.formWidth * 0.8);

    // MUTATION (confirmed red, then restored): removed `.woocommerce-
    // EditAccountForm .clear { display: none }`. `getBoundingClientRect
    // ().height` on the first `div.clear` then reported a non-zero box
    // (an empty grid cell, ~a few px tall) and the fields after it shifted
    // down a row — this presence-of-a-visible-box assertion is what
    // catches it.
    const clearBoxes = await page.evaluate(() =>
      [...document.querySelectorAll('.woocommerce-EditAccountForm .clear')].map(
        (el) => getComputedStyle(el).display,
      ),
    );
    expect(clearBoxes.length).toBeGreaterThan(0);
    for (const display of clearBoxes) {
      expect(display).toBe('none');
    }

    const fieldsetGrid = await page
      .locator('form.woocommerce-EditAccountForm fieldset')
      .evaluate((el) => getComputedStyle(el).gridColumn);
    expect(fieldsetGrid).toContain('-1');
  });
});

test.describe('Order received hero and lede (R1)', () => {
  test('the check mark is a real 64px circle, and the lede is centred under it', async ({
    page,
  }) => {
    await login(page);
    const { orders } = loadFixtures();
    await page.goto(`/checkout/order-received/${orders.completed.id}/?key=${orders.completed.key}`);

    // Exactly one <h1> — page.php suppresses the entry header on this
    // screen (Receipt.php's own docblock; not re-derived here, checked as
    // a fact about THIS rendered page).
    await expect(page.locator('h1')).toHaveCount(1);

    const check = page.locator('.wtb-receipt-hero__check');
    await expect(check).toBeVisible();

    // MUTATION (confirmed red, then restored): removed `width`/`height`
    // from `.wtb-receipt-hero__check`. The box collapsed to the size of
    // its 32px SVG child and this size/shape assertion failed.
    const box = await check.evaluate((el) => el.getBoundingClientRect().toJSON());
    expect(Math.round(box.width)).toBe(64);
    expect(Math.round(box.height)).toBe(64);
    const radius = await check.evaluate((el) => getComputedStyle(el).borderRadius);
    // A circle: radius at least half the box's own size (999px token also
    // satisfies this, but this holds even if the token value changes).
    expect(Number.parseFloat(radius)).toBeGreaterThanOrEqual(box.width / 2 - 1);

    // Woo's own, previously-unstyled lede — centred, not a left-aligned
    // orphan under a centred hero.
    const lede = page.locator('.woocommerce-thankyou-order-received');
    await expect(lede).toBeVisible();
    const ledeAlign = await lede.evaluate((el) => getComputedStyle(el).textAlign);
    expect(ledeAlign).toBe('center');
  });
});

test.describe('Order overview grid (R2, shared with M7)', () => {
  test('all 5 overview items share one row, not 4-plus-an-orphan', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await login(page);
    // orders.completed is the seeded customer's own order, with a billing
    // email and a payment method title — the two CONDITIONAL <li>s
    // (checkout/thankyou.php) that push the item count to 5.
    const { orders } = loadFixtures();
    await page.goto(`/checkout/order-received/${orders.completed.id}/?key=${orders.completed.key}`);

    const items = page.locator('ul.woocommerce-order-overview li');
    await expect(items).toHaveCount(5);
    for (let i = 0; i < 5; i += 1) {
      await expect(items.nth(i)).toBeVisible();
    }

    // MUTATION A (confirmed red, then restored): reverted
    // `grid-template-columns` on the shared rule from `repeat(auto-fit,
    // minmax(6.5rem, 1fr))` back to storefront.css's `repeat(4, 1fr)`. The
    // 5th item (`payment-method`) wrapped to its own row, alone, at
    // roughly a quarter of the row's width — `tops` below had two distinct
    // values instead of one, and the 5th item's width was far short of
    // the other four's.
    //
    // MUTATION B (confirmed red, then restored): removed `ul.woocommerce-
    // order-overview::before, ::after { display: none }`. Woo's OWN
    // `.woocommerce ul.order_details::before/::after` clearfix (this <ul>
    // also carries the `order_details` class) then re-entered the grid as
    // two extra items, bumping the 5th real item to a second row exactly
    // as mutation A did, from an entirely different cause — this is the
    // regression that is actually live without the fix, found by reading
    // computed `::before` content, not assumed.
    const boxes = await page.evaluate(() =>
      [...document.querySelectorAll('ul.woocommerce-order-overview li')].map((el) =>
        el.getBoundingClientRect().toJSON(),
      ),
    );
    const tops = boxes.map((b) => Math.round(b.top));
    expect(new Set(tops).size, 'all 5 items must share one row').toBe(1);

    const widths = boxes.map((b) => b.width);
    const maxWidth = Math.max(...widths);
    const minWidth = Math.min(...widths);
    // Roughly EQUAL columns — an orphaned item at a quarter of the row's
    // width would fail this by a wide margin.
    expect(maxWidth - minWidth).toBeLessThan(5);
  });
});

test.describe('Order details table (R3, shared with M7)', () => {
  test('the total column right-aligns even though the table carries no data-title', async ({
    page,
  }) => {
    await login(page);
    const { orders } = loadFixtures();
    await page.goto(`/checkout/order-received/${orders.completed.id}/?key=${orders.completed.key}`);

    const total = page.locator('table.order_details .product-total').first();
    await expect(total).toBeVisible();

    // MUTATION (confirmed red, then restored): removed `text-align: right`
    // from `.order_details .product-total, .order_details tfoot td`.
    const align = await total.evaluate((el) => getComputedStyle(el).textAlign);
    expect(align).toBe('right');

    // Confirms the row's own claim: no `data-title` on these cells, so a
    // markup-driven responsive label can never have worked here anyway —
    // this is a fact about the CURRENT page, not an assumption.
    const hasDataTitle = await total.evaluate((el) => el.hasAttribute('data-title'));
    expect(hasDataTitle).toBe(false);

    // The quantity marker ("× 1") reads as a quieter, monospaced note next
    // to the product name — not the same weight/font as the name itself.
    const productName = page.locator('table.order_details .product-name').first();
    const [qtyFont, nameFont] = await Promise.all([
      page
        .locator('table.order_details .product-quantity')
        .first()
        .evaluate((el) => getComputedStyle(el).fontFamily),
      productName.evaluate((el) => getComputedStyle(el).fontFamily),
    ]);
    expect(qtyFont).not.toBe(nameFont);
  });
});

test.describe('Address cards on the receipt (R4, shared with M7/view-order)', () => {
  test('the address heading is a small uppercase label, not the display-font h2', async ({
    page,
  }) => {
    await login(page);
    const { orders } = loadFixtures();
    await page.goto(`/checkout/order-received/${orders.completed.id}/?key=${orders.completed.key}`);

    const heading = page.locator('.woocommerce-column--billing-address h2').first();
    await expect(heading).toBeVisible();

    // MUTATION (confirmed red, then restored): emptied the `.woocommerce-
    // column--billing-address h2, .woocommerce-column--shipping-address h2`
    // rule body in receipt.css. storefront.css's own (0,2,1) rule then won
    // outright — `textTransform` read back `'none'` and `fontSize` jumped
    // to the display heading's larger size, both asserted below.
    const style = await heading.evaluate((el) => {
      const cs = getComputedStyle(el);
      return { textTransform: cs.textTransform, fontSize: Number.parseFloat(cs.fontSize) };
    });
    expect(style.textTransform).toBe('uppercase');

    // Measurement against measurement: smaller than the page's own <h1>,
    // not against a literal pixel value that could drift with the type
    // scale.
    const h1Size = await page
      .locator('h1')
      .first()
      .evaluate((el) => Number.parseFloat(getComputedStyle(el).fontSize));
    expect(style.fontSize).toBeLessThan(h1Size);
  });
});

test.describe('Receipt action buttons (R5)', () => {
  test('the cluster centres two buttons, and the outline variant is visually distinct from the default', async ({
    page,
  }) => {
    await login(page);
    const { orders } = loadFixtures();
    await page.goto(`/checkout/order-received/${orders.completed.id}/?key=${orders.completed.key}`);

    const actions = page.locator('.wtb-receipt-actions');
    await expect(actions).toBeVisible();

    const display = await actions.evaluate((el) => getComputedStyle(el).display);
    expect(display).toBe('flex');
    const justify = await actions.evaluate((el) => getComputedStyle(el).justifyContent);
    expect(justify).toBe('center');

    const track = actions.locator('a.button:not(.wtb-button--outline)');
    const outline = actions.locator('a.wtb-button--outline');
    await expect(outline).toBeVisible();

    // MUTATION (confirmed red, then restored): removed the `background-
    // color: transparent` (and its `:hover` pair) from `a.button.wtb-
    // button--outline` in receipt.css. The outline button then fell back
    // to storefront.css's default secondary `.button` background and this
    // comparison — against the OTHER button actually rendered on the same
    // page, not against a literal — failed.
    const outlineBg = await outline.evaluate((el) => getComputedStyle(el).backgroundColor);
    expect(outlineBg).toBe('rgba(0, 0, 0, 0)');

    // UNCONDITIONAL, deliberately. This was `if ((await track.count()) > 0)`,
    // which made the whole "the outline variant is visually distinct from the
    // default" half of this test's name optional: with the track button absent
    // the comparison simply did not run and the test stayed green. And the
    // button cannot legitimately be absent here — `Woo\Receipt::actions()`
    // renders it whenever the current user owns the order, and this spec logs
    // in as that exact customer before loading the receipt, so an absent track
    // button IS the regression, not a valid state to skip over.
    await expect(track).toHaveCount(1);
    await expect(track).toBeVisible();
    const trackBg = await track.evaluate((el) => getComputedStyle(el).backgroundColor);
    expect(trackBg).not.toBe(outlineBg);
  });
});
