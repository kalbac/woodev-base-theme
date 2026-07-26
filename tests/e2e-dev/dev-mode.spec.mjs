// tests/e2e-dev/dev-mode.spec.mjs
//
// Proves that a dev-mode page (WOODEV_BASE_DEV true, assets pulled live from
// the Vite dev server on :5173) is ACTUALLY styled, not merely served.
//
// Why this file asserts computed style, never markup: PR #1 shipped an
// enqueue_dev() that asked the dev server only for `@vite/client` and
// `app.js`. The CSS entry is a separate Rollup input that app.js never
// imports, so the page was a 200 with working JavaScript and correct script
// tags — every PHP/markup test stayed green — while NO Tailwind, Basecoat or
// design tokens ever reached the page. See
// docs/gotchas/vite-css-entry-is-not-imported-by-the-js-entry.md. Markup
// assertions (tag presence, counts) are already covered by the integration
// tests; this spec exists specifically to catch what those cannot.
import { expect, test } from '@playwright/test';
import { tokens } from '../../src/tokens/tokens.mjs';

// A harness guard, not a product assertion, run before EVERY test in this
// file — including via a focused `--grep` run of a single test. Without it,
// a misconfigured environment (wrong port, WOODEV_BASE_DEV not actually set,
// wp-env pointed at the wrong config) would silently let a test's real
// assertions run against an ordinary PRODUCTION page instead — and they
// would mostly still pass there too, because the built CSS produces the same
// computed values as the dev-server CSS. This is what tells them apart
// before that can happen. It used to live only inside the first test, so
// `npx playwright test --grep "dev server actually styles"` skipped it
// entirely and would have passed against a production page.
test.beforeEach(async ({ page }) => {
  const response = await page.goto('/');
  expect(response.status()).toBe(200);

  // Production enqueues a hashed stylesheet from assets/dist; dev mode must not.
  const distStylesheet = page.locator('link[rel="stylesheet"][href*="assets/dist"]');
  await expect(distStylesheet).toHaveCount(0);

  // Dev mode enqueues the theme's one CSS entry as a script module straight
  // from the Vite dev server (see enqueue_dev()'s docblock). ADR-008: no
  // per-pack resolution, so this is a fixed path, not a selection.
  const devStyleModule = page.locator('script[src*="localhost:5173/src/css/app.css"]');
  await expect(devStyleModule).toHaveCount(1);
});

test('the dev-mode site really is in dev mode', async ({ page }) => {
  // The guard itself runs in beforeEach above, for every test in this file.
  // This test restates it explicitly, under its own name, so the guard
  // reads as a real, documented assertion rather than implicit setup that
  // happens to run before something else.
  const distStylesheet = page.locator('link[rel="stylesheet"][href*="assets/dist"]');
  await expect(distStylesheet).toHaveCount(0);

  const devStyleModule = page.locator('script[src*="localhost:5173/src/css/app.css"]');
  await expect(devStyleModule).toHaveCount(1);
});

test('the dev server actually styles the page', async ({ page }) => {
  // In dev mode, Vite's CSS-as-a-JS-module injects its <style> tag when the
  // module EXECUTES, not at first paint — so --font-sans is absent until that
  // script has run. Poll instead of asserting immediately after goto().
  await expect
    .poll(async () =>
      page.evaluate(() =>
        getComputedStyle(document.documentElement).getPropertyValue('--font-sans').trim(),
      ),
    )
    .not.toBe('');

  // --font-sans is the only cheap token probe that can tell "our CSS won"
  // from "Basecoat's did": every colour token we ship is byte-identical to
  // Basecoat's shadcn default (see smoke.spec.mjs), so a colour check would
  // pass even if our stylesheet never loaded at all. Basecoat's base entry
  // (`basecoat-css/base` -> base/base.css) sets --font-sans to "Geist Sans"
  // in its @theme block; ours is the system stack from tokens.mjs, aliased
  // from the body role (--font-sans: var(--font-body)). Only this property
  // distinguishes the two — see the surprising-finding note in src/css/app.css
  // about basecoat-css/base NOT actually being skin-free.
  //
  // A component-geometry probe (a `.btn`'s height/radius) used to sit here
  // too, pinned to the vega pack's `h-9`/`rounded-md`. ADR-008 retires the
  // packs, and `basecoat-css/base` styles no component geometry at all — that
  // is now T5's job (component kit, adapter layer). Until it lands there is
  // nothing meaningful to assert about `.btn`'s rendered size, so the probe
  // is dropped here rather than pinned to an accidental UA default.
  const result = await page.evaluate((expectedFontSansRaw) => {
    const canonicalizeFontFamily = (fontFamilyString) => {
      const probe = document.createElement('div');
      probe.style.fontFamily = fontFamilyString;
      return probe.style.fontFamily;
    };

    const actualFontSans = canonicalizeFontFamily(
      getComputedStyle(document.documentElement).getPropertyValue('--font-sans').trim(),
    );
    const expectedFontSans = canonicalizeFontFamily(expectedFontSansRaw);

    return { actualFontSans, expectedFontSans };
  }, tokens.fontRoles['font-body']);

  expect(result.actualFontSans).toBe(result.expectedFontSans);
});
