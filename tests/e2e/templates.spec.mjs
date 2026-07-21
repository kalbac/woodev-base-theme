// tests/e2e/templates.spec.mjs
//
// M1-02 Task 7: e2e smoke across the classic template hierarchy, in both the
// light and dark colour schemes. Real Playwright against the wp-env dev site;
// fixtures (pages, posts, category) are seeded by
// tests/e2e/global-setup.mjs.
//
// 404, archive and search all render `h1.wtb-archive-title`, so views are
// told apart by TEXT/other markers, not by that shared class — see the
// per-test assertions below.
//
// Dark mode is the `.dark` class on `<html>` (Basecoat convention); the
// scheme switcher UI does not exist yet (M1-05), so each `dark` test injects
// the class itself via `page.addInitScript`, which runs before the document's
// own scripts/first paint.
import { expect, test } from '@playwright/test';
import { SEEDED_CATEGORY_SLUG } from './global-setup.mjs';

/** Fail a test on any console error, mirroring smoke.spec.mjs / navigation.spec.mjs. */
function trackConsoleErrors(page) {
  const errors = [];
  page.on('console', (message) => {
    if (message.type() === 'error') errors.push(message.text());
  });
  return errors;
}

const NOT_FOUND_TEXT = 'Page not found';

/** { describe-block name: whether to force the .dark class before paint } */
const SCHEMES = { light: false, dark: true };

for (const [schemeName, isDark] of Object.entries(SCHEMES)) {
  test.describe(`${schemeName} scheme`, () => {
    test.beforeEach(async ({ page }) => {
      if (isDark) {
        await page.addInitScript(() => {
          document.documentElement.classList.add('dark');
        });
      }
    });

    test('home renders the excerpt-card list, not the 404 fallback', async ({ page }) => {
      const errors = trackConsoleErrors(page);

      const response = await page.goto('/');
      expect(response.status()).toBe(200);

      await expect(page.locator('article.wtb-entry--excerpt').first()).toBeVisible();
      await expect(page.getByText(NOT_FOUND_TEXT)).toHaveCount(0);

      // Exactly one h1 — the blog index carries its own page heading (the excerpt
      // cards are h2), so the document is not left headingless.
      await expect(page.locator('h1')).toHaveCount(1);

      expect(errors).toEqual([]);
    });

    test('single post renders its own template', async ({ page }) => {
      const errors = trackConsoleErrors(page);

      await page.goto('/');
      const href = await page
        .locator('article.wtb-entry--excerpt h2 a')
        .first()
        .getAttribute('href');
      expect(href).toBeTruthy();

      const response = await page.goto(href);
      expect(response.status()).toBe(200);

      await expect(page.locator('article.wtb-entry')).toBeVisible();
      await expect(page.locator('h1.wtb-entry-title')).toBeVisible();
      await expect(page.getByText(NOT_FOUND_TEXT)).toHaveCount(0);

      expect(errors).toEqual([]);
    });

    test('page renders its own template', async ({ page }) => {
      const errors = trackConsoleErrors(page);

      const response = await page.goto('/about/');
      expect(response.status()).toBe(200);

      await expect(page.locator('article.wtb-entry h1.wtb-entry-title')).toHaveText('About');
      await expect(page.getByText(NOT_FOUND_TEXT)).toHaveCount(0);

      expect(errors).toEqual([]);
    });

    test('category archive renders its own template', async ({ page }) => {
      const errors = trackConsoleErrors(page);

      const response = await page.goto(`/category/${SEEDED_CATEGORY_SLUG}/`);
      expect(response.status()).toBe(200);

      await expect(page.locator('h1.wtb-archive-title')).toBeVisible();
      await expect(page.locator('article.wtb-entry--excerpt').first()).toBeVisible();
      await expect(page.getByText(NOT_FOUND_TEXT)).toHaveCount(0);

      expect(errors).toEqual([]);
    });

    test('search renders its own template', async ({ page }) => {
      const errors = trackConsoleErrors(page);

      const response = await page.goto('/?s=WTB+Post');
      expect(response.status()).toBe(200);

      await expect(page.locator('h1.wtb-archive-title')).toContainText('Search results for:');
      await expect(page.getByText(NOT_FOUND_TEXT)).toHaveCount(0);

      expect(errors).toEqual([]);
    });

    test('404 renders the not-found template', async ({ page }) => {
      const errors = trackConsoleErrors(page);

      const response = await page.goto('/no-such-thing-xyz/');
      expect(response.status()).toBe(404);

      await expect(page.locator('h1')).toHaveText(NOT_FOUND_TEXT);

      // Investigated (not blanket-suppressed): Chromium logs "Failed to load
      // resource: the server responded with a status of 404" to the console
      // for the MAIN-FRAME navigation itself whenever the HTTP response is
      // >= 400 — confirmed in a real browser via the network panel, where the
      // only 404 response on this view is the intended `/no-such-thing-xyz/`
      // request already asserted above. It is not a subresource failure (no
      // favicon/asset 404s here) and not a theme bug; it is the unavoidable
      // side effect of deliberately visiting a URL this test expects to 404.
      const unexpected = errors.filter(
        (message) => !message.includes('the server responded with a status of 404'),
      );
      expect(unexpected).toEqual([]);
    });
  });
}

test('dark scheme actually changes the applied tokens on the home view', async ({ page }) => {
  // Not just "dark doesn't error" — proves the .dark tokens actually win at
  // runtime, the "visual check light + dark" the plan asks for, done as a
  // computed-style assertion rather than a brittle screenshot.
  //
  // One page, toggled at runtime: read the light background, add `.dark` to
  // <html> (Basecoat's convention), read it again. Uses the `{ page }` fixture
  // (which carries the project baseURL) rather than `browser.newPage()` (which
  // does not inherit the config), and a runtime class toggle rather than
  // addInitScript — both were sources of a false light reading when the whole
  // suite ran. The token cascade is a pure CSS custom-property switch, so
  // toggling after load reflects the dark values immediately.
  await page.goto('/');

  const lightBackground = await page.evaluate(
    () => getComputedStyle(document.body).backgroundColor,
  );

  await page.evaluate(() => document.documentElement.classList.add('dark'));

  const darkBackground = await page.evaluate(() => getComputedStyle(document.body).backgroundColor);

  expect(darkBackground).not.toBe(lightBackground);
});
