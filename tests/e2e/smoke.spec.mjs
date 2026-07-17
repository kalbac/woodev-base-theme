// tests/e2e/smoke.spec.mjs
import { expect, test } from '@playwright/test';
import { tokens } from '../../src/tokens/tokens.mjs';

test('front page renders with theme assets and no console errors', async ({ page }) => {
  const errors = [];
  page.on('console', (message) => {
    if (message.type() === 'error') errors.push(message.text());
  });

  const response = await page.goto('/');
  expect(response.status()).toBe(200);

  // Theme stylesheet from the Vite dist is loaded.
  const themeCss = page.locator('link[rel="stylesheet"][href*="assets/dist"]');
  await expect(themeCss).toHaveCount(1);

  // Header renders the site name (header.php).
  await expect(page.locator('header a').first()).toBeVisible();

  expect(errors).toEqual([]);
});

test('our design tokens win the cascade at runtime', async ({ page }) => {
  // Guards docs/gotchas/basecoat-tokens-are-un-layered.md: Basecoat declares
  // its own :root/.dark token defaults UN-LAYERED, and our generated tokens
  // are also un-layered but imported AFTER Basecoat, so source order makes
  // ours win. Both currently ship identical shadcn values, so a cascade
  // regression would be invisible in the rendered page — only a
  // computed-style assertion catches it.
  await page.goto('/');

  const result = await page.evaluate((expectedBackgroundRaw) => {
    const root = document.documentElement;
    const actualBackground = getComputedStyle(root).getPropertyValue('--background').trim();

    // Custom-property values on :root come back exactly as the build
    // pipeline shipped them, which is NOT necessarily the raw string in
    // tokens.mjs — Tailwind's build (Lightning CSS) itself re-serializes
    // color functions (proven below: `oklch(1 0 0)` ships as
    // `oklch(100% 0 0)` in the compiled bundle). A raw string comparison
    // would be brittle against that transform, and setting the raw token
    // on a throwaway element's custom property does NOT reproduce it either
    // (custom properties are unparsed token streams — no color parsing
    // happens). So we canonicalize BOTH sides through a real CSS color
    // parser/serializer that neither side's raw string authored: canvas
    // 2D's `fillStyle` accepts any valid CSS color (including oklch) and
    // always returns its own canonical serialization. That makes the
    // comparison semantic (same resolved color), not textual.
    const canonicalizeColor = (colorString) => {
      const canvas = document.createElement('canvas');
      const ctx = canvas.getContext('2d');
      ctx.fillStyle = colorString;
      return ctx.fillStyle;
    };

    // Same story for the font stack: the build re-quotes font-family idents
    // (`'Segoe UI'` -> `"Segoe UI"`), so compare through the browser's own
    // `font-family` serialization (a real CSS property, unlike a custom
    // property) rather than a raw string.
    const canonicalizeFontFamily = (fontFamilyString) => {
      const probe = document.createElement('div');
      probe.style.fontFamily = fontFamilyString;
      return probe.style.fontFamily;
    };

    return {
      actualBackground: canonicalizeColor(actualBackground),
      expectedBackground: canonicalizeColor(expectedBackgroundRaw),
      actualFontSans: canonicalizeFontFamily(
        getComputedStyle(root).getPropertyValue('--font-sans').trim(),
      ),
    };
  }, tokens.colors.light.background);

  expect(result.actualBackground).toBe(result.expectedBackground);

  // --font-sans is a token Basecoat does NOT define, so a correct value here
  // proves our tokens.generated.css is actually loaded and applied — not
  // just that some --background happens to match by coincidence.
  const expectedFontSans = await page.evaluate((fontFamilyString) => {
    const probe = document.createElement('div');
    probe.style.fontFamily = fontFamilyString;
    return probe.style.fontFamily;
  }, tokens.fonts.sans);
  expect(result.actualFontSans).toBe(expectedFontSans);
});

test('basecoat JS initializes components and Alpine starts', async ({ page }) => {
  // Guards docs/gotchas/basecoat-js-entry-is-a-subpath-export.md: app.js must
  // import 'basecoat-css/all' (which self-registers 12 components), not the
  // bare 'basecoat-css' specifier (which resolves to CSS and registers
  // nothing) nor 'basecoat-css/basecoat' (the registry alone, still nothing
  // registered). A wrong import fails silently — only a runtime check catches
  // it.
  await page.goto('/');

  const basecoatType = await page.evaluate(() => typeof window.basecoat);
  expect(basecoatType).toBe('object');

  // window.basecoat's component registry is a private closure variable —
  // there is no public API to list registered component names, and
  // asserting against it would be an implementation-detail (brittle) check.
  // Instead assert the documented, public CONTRACT: initAll() walks the
  // registry and initializes any matching element in the DOM. We inject a
  // real Basecoat component root (`.accordion`, registered by
  // dist/js/accordion.js) and confirm Basecoat's own init function actually
  // ran against it — proving a component really is registered, via an
  // observable behavior rather than a private field.
  const accordionInitialized = await page.evaluate(() => {
    const el = document.createElement('div');
    el.className = 'accordion';
    document.body.appendChild(el);
    window.basecoat.initAll();
    const initialized = el.dataset.accordionInitialized === 'true';
    el.remove();
    return initialized;
  });
  expect(accordionInitialized).toBe(true);

  // app.js does `window.Alpine = Alpine; Alpine.start();`.
  const hasAlpine = await page.evaluate(() => typeof window.Alpine !== 'undefined');
  expect(hasAlpine).toBe(true);
});
