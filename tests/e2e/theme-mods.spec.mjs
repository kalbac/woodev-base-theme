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
import { tokens } from '../../src/tokens/tokens.mjs';
import { isInteger, isToggleValue, readThemeMod, restoreThemeMod, wp } from './lib/theme-mod.mjs';

const PRESETS = ['neutral', 'blue', 'green', 'red', 'rose', 'orange', 'yellow', 'violet'];
const RADII = ['none', 'sm', 'md', 'lg'];
const SCHEMES = ['system', 'light', 'dark'];

const SIDEBAR_POSITIONS = ['none', 'right'];

/** theme_mod name -> guard, for everything this file touches. */
const TOUCHED = {
  style_preset: (value) => PACKS.includes(value),
  primary_preset: (value) => PRESETS.includes(value),
  container_width: isInteger,
  radius_scale: (value) => RADII.includes(value),
  base_font_size: isInteger,
  color_scheme_default: (value) => SCHEMES.includes(value),
  color_scheme_toggle: isToggleValue,
  sidebar_position: (value) => SIDEBAR_POSITIONS.includes(value),
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

/**
 * Canonicalize a CSS color through the browser's own parser/serializer, so a
 * comparison is semantic (same resolved color) rather than textual — the
 * build re-serializes `oklch()` (smoke.spec.mjs documents the same need for
 * `--font-sans`/`--background`), so a raw string compare would be brittle.
 */
function canonicalColor(page, raw) {
  return page.evaluate((value) => {
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = value;
    return ctx.fillStyle;
  }, raw);
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

  // §7 sidebar column cap: Layout::has_sidebar() requires BOTH
  // sidebar_position=right AND an active sidebar-1 widget. global-setup.mjs
  // seeds that widget idempotently so this test only has to toggle the
  // theme_mod, but the precondition is still asserted explicitly below — a
  // cap test that silently ran on a sidebar-less page would prove nothing.
  test('a visible sidebar caps the post grid at 2 tracks, not 3', async ({ page }) => {
    wp('theme mod set sidebar_position right');
    await page.setViewportSize({ width: 1400, height: 900 });
    await page.goto('/');

    await expect(
      page.locator('.wtb-layout--has-sidebar'),
      'expected .wtb-layout--has-sidebar on the page — is sidebar-1 empty? ' +
        'global-setup.mjs should have seeded a widget there.',
    ).toHaveCount(1);

    const trackCount = await page.evaluate(
      () =>
        getComputedStyle(document.querySelector('.wtb-post-grid')).gridTemplateColumns.split(' ')
          .length,
    );
    expect(trackCount).toBe(2);
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

  // Colour-scheme switcher (M1-05, spec §6): the two settings, the no-FOUC
  // head script, and the sun/moon control.
  test.describe('colour-scheme switcher', () => {
    test('the toggle flips the scheme, persists in localStorage, and sticks across a navigation', async ({
      page,
    }) => {
      wp('theme mod set color_scheme_toggle 1');
      wp('theme mod set color_scheme_default light');

      await page.goto('/');
      const button = page.locator('.wtb-scheme-toggle');
      await expect(button).toBeVisible();
      await expect(page.locator('html')).toHaveClass(/light/);
      await expect(page.locator('html')).not.toHaveClass(/dark/);

      await button.click();
      await expect(page.locator('html')).toHaveClass(/dark/);
      expect(await page.evaluate(() => localStorage.getItem('wtb-scheme'))).toBe('dark');

      // Sticks across a navigation to a DIFFERENT page: the stored choice is
      // read by the head script before Alpine even runs, and Alpine's own
      // init() must not clobber it back to the admin default.
      await page.goto('/about/');
      await expect(page.locator('html')).toHaveClass(/dark/);
      await expect(page.locator('html')).not.toHaveClass(/light/);
    });

    /**
     * The class must be present at FIRST PAINT, not added later — that is the
     * entire point of the Task 3 head script. `addInitScript` runs before any
     * of the page's own scripts on every subsequent navigation, so recording
     * the class at `DOMContentLoaded` proves the synchronous, wp_head-priority-1
     * head script already ran by the time the DOM finished parsing — before
     * any deferred asset (Vite's module script, async-loaded CSS) gets a
     * chance to paint something else first.
     */
    /**
     * The head script, isolated.
     *
     * The obvious version of this test (admin default `dark`, assert `dark` at
     * DOMContentLoaded) is VACUOUS: with an explicit admin default the SERVER
     * already renders class="dark" on <html>, so the assertion holds even if
     * the head script is deleted outright. Adversarial review caught exactly
     * that.
     *
     * So set the server and the stored choice to DISAGREE. The server renders
     * `light`; only the script can turn that into `dark` before first paint,
     * and it must have done so by DOMContentLoaded — after that, any change is
     * the flash this feature exists to prevent.
     */
    test('no flash: the head script resolves the stored choice before first paint', async ({
      page,
    }) => {
      wp('theme mod set color_scheme_toggle 1');
      wp('theme mod set color_scheme_default light');

      await page.addInitScript(() => {
        try {
          localStorage.setItem('wtb-scheme', 'dark');
        } catch {
          /* Storage blocked; the assertion below will catch the consequence. */
        }

        document.addEventListener('DOMContentLoaded', () => {
          window.__wtbClassAtDCL = document.documentElement.className;
        });
      });

      await page.goto('/');

      const classAtDCL = await page.evaluate(() => window.__wtbClassAtDCL);

      expect(classAtDCL, 'the stored choice must win before DOMContentLoaded').toContain('dark');
      expect(classAtDCL, 'the server-rendered class must be gone, not merely joined').not.toContain(
        'light',
      );
    });

    /**
     * `system` follows the OS. Emulating `colorScheme` as a context option
     * (via `test.use`, never `browser.newPage()`) applies BEFORE the initial
     * navigation, so this is read by the browser's native
     * `prefers-color-scheme` media query in the generated token CSS — the
     * mechanism that resolves `system`'s colours, independent of any JS.
     *
     * This deliberately does NOT attempt to prove LIVE re-following (flipping
     * the OS preference after load and expecting the button to react):
     * `page.emulateMedia()` updates `matchMedia().matches` but does not
     * dispatch a `change` event to already-registered listeners in this
     * Chromium/CDP combination, so that half of spec §6 cannot be pinned
     * through Playwright's media emulation. Recorded here rather than papered
     * over with a test that would pass for the wrong reason.
     */
    test.describe('system follows the OS: dark', () => {
      test.use({ colorScheme: 'dark' });

      test('a dark OS preference resolves the dark tokens under the system default', async ({
        page,
      }) => {
        wp('theme mod set color_scheme_default system');
        wp('theme mod set color_scheme_toggle 1');

        await page.goto('/');

        await expect(page.locator('html')).not.toHaveClass(/light/);
        await expect(page.locator('html')).not.toHaveClass(/dark/);

        const background = await canonicalColor(page, await rootVar(page, '--background'));
        expect(background).toBe(await canonicalColor(page, tokens.colors.dark.background));
      });
    });

    test.describe('system follows the OS: light', () => {
      test.use({ colorScheme: 'light' });

      test('a light OS preference resolves the light tokens under the system default', async ({
        page,
      }) => {
        wp('theme mod set color_scheme_default system');
        wp('theme mod set color_scheme_toggle 1');

        await page.goto('/');

        await expect(page.locator('html')).not.toHaveClass(/light/);
        await expect(page.locator('html')).not.toHaveClass(/dark/);

        const background = await canonicalColor(page, await rootVar(page, '--background'));
        expect(background).toBe(await canonicalColor(page, tokens.colors.light.background));
      });
    });

    test('the toggle off renders no control, and a stored visitor choice is not honoured', async ({
      page,
    }) => {
      wp('theme mod set color_scheme_toggle 0');
      wp('theme mod set color_scheme_default light');

      // A visitor who chose dark before the admin turned the switcher off.
      await page.addInitScript(() => {
        try {
          localStorage.setItem('wtb-scheme', 'dark');
        } catch {
          // Nothing to simulate if storage is unavailable in this browser.
        }
      });

      await page.goto('/');

      await expect(page.locator('.wtb-scheme-toggle')).toHaveCount(0);
      // The admin default wins outright: with the toggle off,
      // Scheme::build_head_script() never even reads localStorage, so the
      // stored 'dark' cannot surface no matter what it holds.
      await expect(page.locator('html')).toHaveClass(/light/);
      await expect(page.locator('html')).not.toHaveClass(/dark/);
    });

    /**
     * `localStorage.getItem` THROWS (not returns null) in Safari private mode
     * and whenever storage/cookies are blocked. Scheme::build_head_script()
     * wraps the read in try/catch for exactly this. Task 3's Step 5 mutation
     * (removing that try/catch) has no unit-test-visible effect — a caught
     * throw always falls back to a value the server already rendered, so
     * removing the catch is only observable as an UNCAUGHT exception, never
     * as a different `<html>` class. That is what this test actually checks.
     */
    test('a throwing localStorage.getItem does not break scheme resolution', async ({ page }) => {
      wp('theme mod set color_scheme_toggle 1');
      wp('theme mod set color_scheme_default light');

      const pageErrors = [];
      page.on('pageerror', (error) => pageErrors.push(error));

      await page.addInitScript(() => {
        window.Storage.prototype.getItem = function () {
          throw new Error('blocked');
        };
      });

      await page.goto('/');

      await expect(page.locator('html')).toHaveClass(/light/);
      expect(pageErrors).toEqual([]);
    });
  });
});
