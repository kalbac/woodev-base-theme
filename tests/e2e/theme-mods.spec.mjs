// tests/e2e/theme-mods.spec.mjs
//
// THE ONE FILE THAT MUTATES SITE-GLOBAL theme_mods.
//
// Playwright parallelises by FILE, so keeping every theme_mod mutation in a
// single serial file is what guarantees no other spec observes a half-applied
// setting. Do not add a theme_mod mutation to any other spec — put it here.
// Each test restores what it touched before the next one runs.
import { expect, test } from '@playwright/test';
import { PACKS } from '../../scripts/lib/packs-lib.mjs';
import { isInteger, readThemeMod, restoreThemeMod, wp } from './lib/theme-mod.mjs';

const PRESETS = ['neutral', 'blue', 'green', 'red', 'rose', 'orange', 'yellow', 'violet'];
const RADII = ['none', 'sm', 'md', 'lg'];

/** theme_mod name -> guard, for everything this file touches. */
const TOUCHED = {
  style_preset: (value) => PACKS.includes(value),
  primary_preset: (value) => PRESETS.includes(value),
  container_width: isInteger,
  radius_scale: (value) => RADII.includes(value),
  base_font_size: isInteger,
};

/** @type {Record<string, string|null>} */
const previous = Object.fromEntries(Object.keys(TOUCHED).map((name) => [name, null]));

/** Read a CSS custom property off :root as the browser resolved it. */
function rootVar(page, property) {
  return page.evaluate(
    (name) => getComputedStyle(document.documentElement).getPropertyValue(name).trim(),
    property,
  );
}

/**
 * Height (px, rounded) of the blog index's read-more button.
 *
 * The locator is deliberately specific. A bare `.btn` would also match the
 * search form's submit and would keep passing if `btn` were ever dropped from
 * the read-more link — measuring some other element instead of failing.
 */
async function readMoreButton(page) {
  const button = page.locator('a.wtb-entry-more.btn').first();
  await expect(button).toBeVisible();

  return button;
}

test.describe.serial('site-global theme_mods', () => {
  test.beforeAll(() => {
    for (const [name, isValid] of Object.entries(TOUCHED)) {
      previous[name] = readThemeMod(name, isValid);
    }
  });

  test.afterEach(() => {
    // Restore after EVERY test, not just at the end: the window in which a
    // parallel spec could observe a mutated site is what we are minimising.
    for (const name of Object.keys(TOUCHED)) {
      restoreThemeMod(name, previous[name]);
    }
  });

  test('default pack (vega): vega bundle loads and .btn is 36px tall', async ({ page }) => {
    wp('theme mod remove style_preset');
    await page.goto('/');

    await expect(page.locator('link[rel="stylesheet"][href*="style-vega-"]')).toHaveCount(1);
    await expect(page.locator('link[rel="stylesheet"][href*="style-nova-"]')).toHaveCount(0);

    const box = await (await readMoreButton(page)).boundingBox();
    expect(Math.round(box.height)).toBe(36);
  });

  test('switching to nova loads the nova bundle and shrinks the .btn to 32px', async ({ page }) => {
    wp('theme mod set style_preset nova');
    await page.goto('/');

    await expect(page.locator('link[rel="stylesheet"][href*="style-nova-"]')).toHaveCount(1);
    await expect(page.locator('link[rel="stylesheet"][href*="style-vega-"]')).toHaveCount(0);

    const box = await (await readMoreButton(page)).boundingBox();
    expect(Math.round(box.height)).toBe(32);
  });

  // An untouched site must ship no inline block at all — that contract is what
  // keeps the default install free of per-page CSS.
  test('an untouched site emits no inline style block', async ({ page }) => {
    await page.goto('/');

    await expect(page.locator('style#woodev-base-inline')).toHaveCount(0);
  });

  // Colour is the one axis packs cannot move (all 8 share one palette), so this
  // assertion is immune to whatever pack happens to be active.
  test('the blue accent preset repaints --primary and the button', async ({ page }) => {
    await page.goto('/');
    const before = await (
      await readMoreButton(page)
    ).evaluate((node) => getComputedStyle(node).backgroundColor);

    wp('theme mod set primary_preset blue');
    await page.goto('/');

    await expect(page.locator('style#woodev-base-inline')).toHaveCount(1);
    expect(await rootVar(page, '--primary')).toContain('54.6%');

    const after = await (
      await readMoreButton(page)
    ).evaluate((node) => getComputedStyle(node).backgroundColor);
    expect(after).not.toBe(before);
  });

  test('the container width setting caps the layout', async ({ page }) => {
    await page.setViewportSize({ width: 1600, height: 900 });

    wp('theme mod set container_width 1000');
    await page.goto('/');

    const width = await page
      .locator('.wtb-container')
      .first()
      .evaluate((node) => node.getBoundingClientRect().width);

    expect(Math.round(width)).toBe(1000);
  });

  // --radius drives Basecoat's --radius-md/-lg/-xl through calc(), so one
  // setting reshapes every component. Asserting 0 vs > 0 keeps the check true
  // under any pack, which differ in WHICH radius step a .btn uses.
  test('the radius setting squares off and rounds the button', async ({ page }) => {
    wp('theme mod set radius_scale none');
    await page.goto('/');

    expect(await rootVar(page, '--radius')).toBe('0rem');
    const squared = await (
      await readMoreButton(page)
    ).evaluate((node) => parseFloat(getComputedStyle(node).borderTopLeftRadius));
    expect(squared).toBe(0);

    wp('theme mod set radius_scale lg');
    await page.goto('/');

    const rounded = await (
      await readMoreButton(page)
    ).evaluate((node) => parseFloat(getComputedStyle(node).borderTopLeftRadius));
    expect(rounded).toBeGreaterThan(squared);
  });

  test('the base font size setting moves the root size', async ({ page }) => {
    wp('theme mod set base_font_size 20');
    await page.goto('/');

    const rootSize = await page.evaluate(() => getComputedStyle(document.documentElement).fontSize);

    expect(rootSize).toBe('20px');
  });
});
