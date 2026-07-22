// tests/e2e-dev/dev-mode.spec.mjs
//
// Proves that a dev-mode page (WOODEV_BASE_DEV true, assets pulled live from
// the Vite dev server on :5173) is ACTUALLY styled, not merely served.
//
// Why this file asserts computed style, never markup: PR #1 shipped an
// enqueue_dev() that asked the dev server only for `@vite/client` and
// `app.js`. The pack CSS entry is a separate Rollup input that app.js never
// imports, so the page was a 200 with working JavaScript and correct script
// tags — every PHP/markup test stayed green — while NO Tailwind, Basecoat or
// design tokens ever reached the page. See
// docs/gotchas/vite-css-entry-is-not-imported-by-the-js-entry.md. Markup
// assertions (tag presence, counts) are already covered by the integration
// tests; this spec exists specifically to catch what those cannot.
import { expect, test } from '@playwright/test';
import { tokens } from '../../src/tokens/tokens.mjs';

test('the dev-mode site really is in dev mode', async ({ page }) => {
  // A harness guard, not a product assertion. Without it, a misconfigured
  // environment (wrong port, WOODEV_BASE_DEV not actually set, wp-env
  // pointed at the wrong config) would silently run test 2's real
  // assertions against an ordinary PRODUCTION page instead — and they would
  // mostly still pass there too, because the built CSS produces the same
  // computed values as the dev-server CSS. This test is what tells them
  // apart before that can happen.
  const response = await page.goto('/');
  expect(response.status()).toBe(200);

  // Production enqueues a hashed stylesheet from assets/dist; dev mode must not.
  const distStylesheet = page.locator('link[rel="stylesheet"][href*="assets/dist"]');
  await expect(distStylesheet).toHaveCount(0);

  // Dev mode enqueues the selected style pack's CSS entry as a script module
  // straight from the Vite dev server (see enqueue_dev()'s docblock).
  const devPackModule = page.locator('script[src*="localhost:5173/src/css/packs/"]');
  await expect(devPackModule).toHaveCount(1);
});

test('the dev server actually styles the page', async ({ page }) => {
  await page.goto('/');

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

  const result = await page.evaluate((expectedFontSansRaw) => {
    // --font-sans is the only cheap token probe that can tell "our CSS won"
    // from "Basecoat's did": every colour token we ship is byte-identical to
    // Basecoat's shadcn default (see smoke.spec.mjs), so a colour check would
    // pass even if our stylesheet never loaded at all. Basecoat's vega pack
    // sets --font-sans to Geist Sans; ours is the system stack from
    // tokens.mjs. Only this property distinguishes the two.
    const canonicalizeFontFamily = (fontFamilyString) => {
      const probe = document.createElement('div');
      probe.style.fontFamily = fontFamilyString;
      return probe.style.fontFamily;
    };

    const actualFontSans = canonicalizeFontFamily(
      getComputedStyle(document.documentElement).getPropertyValue('--font-sans').trim(),
    );
    const expectedFontSans = canonicalizeFontFamily(expectedFontSansRaw);

    // vega's `.btn:not([data-size])` sets height via `h-9` = 36px
    // (node_modules/basecoat-css/dist/styles/vega.css). `.btn` is a component
    // rule the pack ships, not a Tailwind utility generated from page markup,
    // so it applies to an element created here at runtime and does not depend
    // on anything already present in the page — an independent confirmation
    // that the pack's rules, not just its custom properties, reached the page.
    const btn = document.createElement('button');
    btn.className = 'btn';
    document.body.appendChild(btn);
    const btnHeight = getComputedStyle(btn).height;
    btn.remove();

    return { actualFontSans, expectedFontSans, btnHeight };
  }, tokens.fonts.sans);

  expect(result.actualFontSans).toBe(result.expectedFontSans);
  expect(result.btnHeight).toBe('36px');
});
