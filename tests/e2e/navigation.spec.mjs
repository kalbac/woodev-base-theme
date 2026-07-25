// tests/e2e/navigation.spec.mjs
//
// Accessible navigation (M1-02 Task 4). Real Playwright against the wp-env dev
// site; fixtures are seeded by tests/e2e/global-setup.mjs (About > Team, Contact).
import { expect, test } from '@playwright/test';

/** Fail the test on any console error, mirroring smoke.spec.mjs. */
function trackConsoleErrors(page) {
  const errors = [];
  page.on('console', (message) => {
    if (message.type() === 'error') errors.push(message.text());
  });
  return errors;
}

test.describe('skip link', () => {
  // Deferred from Task 3: this is the AUTHORITATIVE on-focus-visibility check.
  // Playwright drives real OS focus (the in-app browser pane cannot), so the
  // skip link's reveal-on-focus behaviour gets pinned here.
  test('becomes visible when focused via the keyboard', async ({ page }) => {
    const errors = trackConsoleErrors(page);
    await page.goto('/');

    const skip = page.locator('.wtb-skip-link');
    // Off-screen before focus: its box top is negative (top: -100%).
    const before = await skip.boundingBox();
    expect(before.y).toBeLessThan(0);

    // First Tab lands on the skip link (it is the first focusable element).
    await page.keyboard.press('Tab');
    await expect(skip).toBeFocused();

    // On focus it moves fully into view (top: 0.5rem >= 0). Poll past the
    // `transition: top` animation rather than sampling a single mid-flight frame.
    await expect.poll(async () => (await skip.boundingBox()).y).toBeGreaterThanOrEqual(0);

    expect(errors).toEqual([]);
  });

  // Regression guard: the skip link's target (`#wtb-content`) previously had no
  // `tabindex="-1"`, so fragment navigation scrolled the page but left focus on
  // the skip link itself (Safari/VoiceOver and other keyboard paths don't move
  // focus to a non-focusable fragment target) — the next Tab walked the header
  // again instead of landing inside the content.
  test('activating it moves focus into the main content', async ({ page }) => {
    await page.goto('/');

    await page.keyboard.press('Tab');
    await expect(page.locator('.wtb-skip-link')).toBeFocused();

    await page.keyboard.press('Enter');

    const focusIsInMain = await page.evaluate(() => {
      const main = document.getElementById('wtb-content');
      return document.activeElement === main || main.contains(document.activeElement);
    });
    expect(focusIsInMain).toBe(true);
  });
});

test.describe('mobile drawer', () => {
  test.use({ viewport: { width: 375, height: 800 } });

  test('toggle opens/closes the drawer, Escape restores focus, focus is trapped', async ({
    page,
  }) => {
    const errors = trackConsoleErrors(page);
    await page.goto('/');

    const toggle = page.locator('.wtb-nav__toggle');
    const menu = page.locator('#wtb-primary-menu');

    // Enhanced + narrow: toggle is revealed by Alpine, menu collapsed.
    await expect(toggle).toBeVisible();
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
    await expect(menu).toBeHidden();

    // Open.
    await toggle.click();
    await expect(toggle).toHaveAttribute('aria-expanded', 'true');
    await expect(menu).toBeVisible();

    // x-trap moves focus into the drawer ASYNCHRONOUSLY — it is still on <body>
    // synchronously after the click and through the next microtask. A Tab fired in
    // that window walks from <body> to the document's first focusable, which is
    // the skip link OUTSIDE .wtb-nav, and the loop below then fails on i=0 for a
    // reason that is not a trap failure. Wait for the precondition rather than
    // racing it.
    await expect
      .poll(async () =>
        page.evaluate(() => document.querySelector('.wtb-nav').contains(document.activeElement)),
      )
      .toBe(true);

    // Focus trap: x-trap moves focus into the drawer; tabbing never escapes it.
    for (let i = 0; i < 5; i += 1) {
      await page.keyboard.press('Tab');
      const insideNav = await page.evaluate(() => {
        const nav = document.querySelector('.wtb-nav');
        return nav.contains(document.activeElement);
      });
      expect(insideNav).toBe(true);
    }

    // Escape closes AND returns focus to the toggle (x-trap teardown).
    await page.keyboard.press('Escape');
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
    await expect(menu).toBeHidden();
    await expect(toggle).toBeFocused();

    expect(errors).toEqual([]);
  });

  test('widening to desktop while open releases the focus trap', async ({ page }) => {
    // Regression guard: an open drawer left `open = true` and x-trap active when
    // the viewport grew to desktop, where the toggle is display:none — Escape
    // then tried to focus a hidden button and keyboard focus stayed trapped in
    // the now-inline menu. Widening must drop `open` and free focus.
    await page.goto('/');

    const toggle = page.locator('.wtb-nav__toggle');
    await toggle.click();
    await expect(toggle).toHaveAttribute('aria-expanded', 'true');

    await page.setViewportSize({ width: 1280, height: 800 });

    // open is cleared, so nothing is trapped: focus can reach the footer.
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
    const footerLink = page.locator('.wtb-footer a').first();
    await footerLink.focus();
    await expect(footerLink).toBeFocused();
  });
});

test.describe('mobile menu height cap', () => {
  test.use({ viewport: { width: 375, height: 800 } });

  test('the open menu is capped to a viewport fraction, not a hand-computed subtraction', async ({
    page,
  }) => {
    // P0 fix (header.css). The cap used to be `calc(100dvh - 4.5rem)`, a
    // subtrahend that assumed a `padding-top` the bar never had. `60dvh`
    // resolves to a fixed fraction of the viewport height regardless of any
    // other declaration — pin the actual computed value so a reintroduced
    // hand-computed `calc()` (which resolves to a materially different px
    // number at this viewport: 60% of 800px is 480px, the old formula
    // resolved to 800 - 72px = 728px) fails this test.
    await page.goto('/');

    const toggle = page.locator('.wtb-nav__toggle');
    await toggle.click();

    const menu = page.locator('#wtb-primary-menu');
    await expect(menu).toBeVisible();

    const { maxHeightPx, viewportHeight } = await menu.evaluate((el) => ({
      maxHeightPx: parseFloat(getComputedStyle(el).maxHeight),
      viewportHeight: window.innerHeight,
    }));

    expect(maxHeightPx).toBeCloseTo(viewportHeight * 0.6, 0);
  });

  test('the open menu stays inside the viewport under the centered header variant too', async ({
    page,
  }) => {
    // P0 fix (header.css). The bug this fixes was worst on
    // `.wtb-header--centered`: that variant stacks wordmark + nav + actions
    // in a column, so the menu starts much lower than the `inline` variant
    // while the OLD cap subtracted the same fixed amount regardless of
    // variant. Simulate the variant class client-side — a DOM mutation, not
    // a theme_mod: this file does not own theme_mod state (see
    // components.spec.mjs's file header for why that boundary matters) —
    // and check the open menu's own box never extends past the viewport
    // bottom.
    await page.goto('/');

    await page.evaluate(() => {
      document.querySelector('.wtb-header').classList.add('wtb-header--centered');
    });

    const toggle = page.locator('.wtb-nav__toggle');
    await toggle.click();

    const menu = page.locator('#wtb-primary-menu');
    await expect(menu).toBeVisible();

    const box = await menu.boundingBox();
    const viewport = page.viewportSize();
    expect(box).not.toBeNull();
    expect(viewport).not.toBeNull();
    expect(box.y + box.height).toBeLessThanOrEqual(viewport.height + 1);
  });
});

test.describe('mobile menu overflow', () => {
  // Short + wide ("phone in landscape"): still under the 48rem collapse
  // breakpoint (width 700 < 768), but only 400px tall — not enough room for a
  // menu with a submenu to fit without scrolling.
  test.use({ viewport: { width: 700, height: 400 } });

  test('a long open menu scrolls internally and its last item is reachable', async ({ page }) => {
    await page.goto('/');

    // The wp-env fixture menu (global-setup.mjs, owned by another workstream)
    // is only 3 items — About > Team, Contact — nowhere near enough to
    // overflow even this short viewport. Rather than extend that fixture,
    // inject extra <li>s into the already-rendered, real menu client-side:
    // this still exercises the real CSS (max-height/overflow-y) against real
    // layout, it just doesn't route the extra items through wp-cli to get
    // there.
    await page.evaluate(() => {
      const menu = document.getElementById('wtb-primary-menu');
      const template = menu.querySelector('li');
      for (let i = 0; i < 20; i += 1) {
        const clone = template.cloneNode(true);
        clone.removeAttribute('id');
        clone.className = 'wtb-e2e-injected-item';
        const link = clone.querySelector('a');
        link.textContent = `Injected item ${i}`;
        link.setAttribute('href', '#');
        menu.appendChild(clone);
      }
    });

    const toggle = page.locator('.wtb-nav__toggle');
    const menu = page.locator('#wtb-primary-menu');

    await toggle.click();
    await expect(menu).toBeVisible();

    // The menu's content is now taller than its own box: it overflows and
    // therefore needs (and, per the fix, has) internal scrolling.
    const { scrollHeight, clientHeight } = await menu.evaluate((el) => ({
      scrollHeight: el.scrollHeight,
      clientHeight: el.clientHeight,
    }));
    expect(scrollHeight).toBeGreaterThan(clientHeight);

    // The last injected item is reachable: scrolling it into view lands it
    // inside the (locked-body) viewport, not below it.
    const lastItem = page.locator('.wtb-e2e-injected-item a').last();
    await lastItem.scrollIntoViewIfNeeded();
    const box = await lastItem.boundingBox();
    expect(box).not.toBeNull();
    expect(box.y).toBeGreaterThanOrEqual(0);
    expect(box.y).toBeLessThan(400);
  });
});

test.describe('desktop submenu', () => {
  test.use({ viewport: { width: 1280, height: 800 } });

  test('submenu is hidden until the parent item receives focus', async ({ page }) => {
    const errors = trackConsoleErrors(page);
    await page.goto('/');

    const menu = page.locator('#wtb-primary-menu');
    const toggle = page.locator('.wtb-nav__toggle');
    // Desktop: menu inline, toggle hidden.
    await expect(menu).toBeVisible();
    await expect(toggle).toBeHidden();

    const teamLink = menu.locator('.sub-menu a', { hasText: 'Team' });
    await expect(teamLink).toBeHidden();

    // Focusing the parent link triggers :focus-within, revealing the submenu.
    const parentLink = menu.locator('.menu-item-has-children > a').first();
    await parentLink.focus();
    await expect(teamLink).toBeVisible();

    expect(errors).toEqual([]);
  });

  // Regression guard: the submenu used to hide/show via `display`, which a CSS
  // transition cannot run from (no @starting-style / transition-behavior:
  // allow-discrete in use here) — opacity/transform snapped instead of
  // animating.
  //
  // Asserted by LISTENING for the transition rather than polling computed
  // opacity for an intermediate value: the listener is attached before the
  // transition is triggered, so the assertion does not depend on a round trip
  // landing inside the ~200ms window. `transitionrun` fires when a transition
  // is created — which is exactly the thing the old `display`-driven rule
  // never did.
  test('the submenu opacity actually transitions rather than snapping', async ({ page }) => {
    // Diagnosed root cause of the prior failure on a real wp-env run (P2
    // 10): `subMenu.evaluate(fn)` where `fn` returns a Promise requires a CDP
    // round trip before the in-page `addEventListener` call actually runs —
    // and that call was never AWAITED before `parentLink.focus()` fired its
    // OWN round trip. Nothing serialised the two, so the transition could
    // start and finish before the listener existed to observe it, and the
    // test failed on the 2s fallback timeout regardless of whether the CSS
    // was correct. The fix: register the listener with a plain, SYNCHRONOUS
    // callback (writes onto `window`, does not return a pending Promise from
    // inside evaluate()), so `await subMenu.evaluate(...)` genuinely
    // completes — and the listener is provably attached — before `focus()`
    // is ever called. Reading the result back is then a separate, decoupled
    // step via `expect.poll`.
    await page.goto('/');

    const menu = page.locator('#wtb-primary-menu');
    const subMenu = menu.locator('.sub-menu').first();
    const parentLink = menu.locator('.menu-item-has-children > a').first();

    await subMenu.evaluate((el) => {
      window.__wtbTransitionRun = null;
      el.addEventListener('transitionrun', (event) => {
        if (event.propertyName === 'opacity' && window.__wtbTransitionRun === null) {
          window.__wtbTransitionRun = event.propertyName;
        }
      });
    });

    await parentLink.focus();

    await expect.poll(() => page.evaluate(() => window.__wtbTransitionRun)).toBe('opacity');

    // It still settles at the fully-open end state.
    await expect(subMenu).toHaveCSS('opacity', '1');
    await expect(subMenu).toHaveCSS('visibility', 'visible');
  });

  test('closing the submenu also transitions opacity, not just opening it', async ({ page }) => {
    // Finding 10 (P2): nothing in this suite exercised the CLOSE half of the
    // same allow-discrete transition — only opening it. Uses the same
    // synchronous-listener-registration pattern as the open test above, for
    // the same race-avoidance reason.
    await page.goto('/');

    const menu = page.locator('#wtb-primary-menu');
    const subMenu = menu.locator('.sub-menu').first();
    const parentLink = menu.locator('.menu-item-has-children > a').first();

    await parentLink.focus();
    await expect(subMenu).toHaveCSS('opacity', '1');

    await subMenu.evaluate((el) => {
      window.__wtbTransitionRun = null;
      el.addEventListener('transitionrun', (event) => {
        if (event.propertyName === 'opacity' && window.__wtbTransitionRun === null) {
          window.__wtbTransitionRun = event.propertyName;
        }
      });
    });

    // Move focus off the item entirely so `:focus-within` stops matching and
    // the submenu closes — the skip link is a real, unrelated focusable
    // target outside `.wtb-nav`, not a synthetic blur.
    await page.locator('.wtb-skip-link').focus();

    await expect.poll(() => page.evaluate(() => window.__wtbTransitionRun)).toBe('opacity');
    await expect(subMenu).toHaveCSS('opacity', '0');
  });

  test('the submenu keeps allow-discrete and @starting-style in the built CSS', async ({
    page,
  }) => {
    // Finding 10 (P2): a build-pipeline regression (a minifier/PostCSS step
    // that doesn't understand these newer at-rules/values) could silently
    // drop them without erroring the build — at which point the open/close
    // transition tests above would start failing for a completely different
    // reason (dead CSS, not a race or a real behavioural regression). Scans
    // the actually-loaded stylesheets rather than trusting the source file,
    // so it catches exactly that class of failure.
    await page.goto('/');

    const found = await page.evaluate(() => {
      let hasAllowDiscrete = false;
      let hasStartingStyle = false;
      const scan = (ruleList) => {
        for (const rule of ruleList) {
          const text = rule.cssText || '';
          if (text.includes('allow-discrete')) hasAllowDiscrete = true;
          if (/@starting-style/i.test(text)) hasStartingStyle = true;
          if (rule.cssRules) scan(rule.cssRules);
        }
      };
      for (const sheet of document.styleSheets) {
        try {
          scan(sheet.cssRules);
        } catch {
          // Cross-origin stylesheet; not one of ours, skip it.
        }
      }
      return { hasAllowDiscrete, hasStartingStyle };
    });

    expect(found.hasAllowDiscrete).toBe(true);
    expect(found.hasStartingStyle).toBe(true);
  });

  // Finding 10 (P2): stated plainly — this test is GREEN today regardless of
  // whether the allow-discrete transition fix above is present or fully
  // reverted, because BOTH the current code and the pre-fix code hide the
  // closed submenu with `display: none`. It is not a regression guard for
  // the transition change; it guards the mechanism that change deliberately
  // did NOT use, so a FUTURE rewrite to `visibility`-based hiding gets
  // caught here instead of shipping silently.
  //
  // `visibility: hidden` transitions and is more widely supported than
  // `allow-discrete`, so it is the tempting simplification — but it leaves the
  // closed submenu with a real box, and an absolutely positioned box still
  // contributes to its containing block's scrollable overflow. With
  // `min-width: 12rem` anchored at the parent item's `left: 0`, any item near
  // the right edge then pushes past the viewport — which is where the `inline`
  // header variant puts the entire menu. `display: none` produces no box, so
  // it cannot. This assertion is what turns that reasoning into something the
  // suite enforces rather than something a comment claims.
  test('the closed submenu does not make the document scroll horizontally', async ({ page }) => {
    await page.goto('/');

    const overflow = await page.evaluate(() => ({
      scrollWidth: document.documentElement.scrollWidth,
      clientWidth: document.documentElement.clientWidth,
    }));

    expect(overflow.scrollWidth).toBeLessThanOrEqual(overflow.clientWidth);
  });
});

test.describe('progressive enhancement (JS disabled)', () => {
  test.use({ javaScriptEnabled: false });

  test('menu is reachable and the toggle stays hidden without JS', async ({ page }) => {
    await page.goto('/');

    // The menu links are server-rendered and visible with no script at all.
    await expect(page.locator('#wtb-primary-menu a').first()).toBeVisible();

    // The toggle never becomes a dead control: it ships `hidden` and Alpine —
    // which would remove it — never runs.
    await expect(page.locator('.wtb-nav__toggle')).toBeHidden();
  });
});
