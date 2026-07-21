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
