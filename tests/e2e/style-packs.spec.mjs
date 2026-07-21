// tests/e2e/style-packs.spec.mjs
//
// Guards M1-03: Assets enqueues exactly the bundle the style_preset theme_mod
// selects, and the 8 packs are genuinely different. Basecoat's packs share one
// colour palette and differ only in component SHAPE, so the proof has to be a
// component's geometry — a colour assertion literally cannot fail here.
//
// ISOLATION CAVEAT — read this before adding specs. This file mutates a
// SITE-GLOBAL theme_mod on the shared wp-env dev site while other spec files run
// in parallel workers. That is safe today only because no other spec asserts
// anything pack-specific. If you add a spec that pins a bundle name or a Basecoat
// component's geometry, it must not run against this site concurrently: give it
// its own Playwright project, or run this file with --workers=1.
import { execSync } from 'node:child_process';
import { expect, test } from '@playwright/test';
import { PACKS } from '../../scripts/lib/packs-lib.mjs';

/** Run a wp-cli command in the cli container, return trimmed stdout. */
function wp(command) {
  return execSync(`npx wp-env run cli wp ${command}`, {
    encoding: 'utf8',
    stdio: ['ignore', 'pipe', 'pipe'],
  }).trim();
}

/**
 * The stored style_preset, or '' when genuinely unset.
 *
 * Errors are deliberately NOT swallowed: a wp-cli failure or unparseable output
 * dressed up as "unset" would make afterAll() delete the very setting it exists
 * to restore. Let it throw loudly instead.
 *
 * The value is also validated against the known pack list before it can ever be
 * interpolated back into a shell command — it comes out of the database, and
 * style_preset is a closed set, so anything else is corrupt or hostile.
 */
function currentPreset() {
  const [row] = JSON.parse(wp('theme mod get style_preset --format=json'));
  const value = row?.value ?? '';

  if ('' !== value && !PACKS.includes(value)) {
    throw new Error(
      `Refusing to round-trip an unrecognised style_preset: ${JSON.stringify(value)}`,
    );
  }

  return value;
}

/**
 * Whatever style_preset held before this file ran, so it can be put back.
 * Stays null until it has actually been read.
 */
let previousPreset = null;

/**
 * Height (px, rounded) of the blog index's read-more button.
 *
 * The locator is deliberately specific. A bare `.btn` would also match the search
 * form's submit, and would keep passing if `btn` were ever dropped from the
 * read-more link — measuring some other element instead of failing.
 */
async function readMoreButtonHeight(page) {
  const button = page.locator('a.wtb-entry-more.btn').first();
  await expect(button).toBeVisible();

  return Math.round((await button.boundingBox()).height);
}

test.describe.serial('Basecoat style packs', () => {
  test.beforeAll(() => {
    previousPreset = currentPreset();
  });

  test.afterAll(() => {
    // beforeAll never got a readable value, so the prior state is unknown —
    // touching it now would destroy exactly what we failed to read.
    if (null === previousPreset) {
      return;
    }

    // Restore rather than remove: blindly deleting would discard a deliberate
    // setting on whatever site the suite was pointed at.
    if ('' === previousPreset) {
      wp('theme mod remove style_preset');
    } else {
      wp(`theme mod set style_preset ${previousPreset}`);
    }
  });

  test('default pack (vega): vega bundle loads and .btn is 36px tall', async ({ page }) => {
    wp('theme mod remove style_preset');
    await page.goto('/');

    await expect(page.locator('link[rel="stylesheet"][href*="style-vega-"]')).toHaveCount(1);
    await expect(page.locator('link[rel="stylesheet"][href*="style-nova-"]')).toHaveCount(0);

    expect(await readMoreButtonHeight(page)).toBe(36);
  });

  test('switching to nova loads the nova bundle and shrinks the .btn to 32px', async ({ page }) => {
    wp('theme mod set style_preset nova');
    await page.goto('/');

    await expect(page.locator('link[rel="stylesheet"][href*="style-nova-"]')).toHaveCount(1);
    await expect(page.locator('link[rel="stylesheet"][href*="style-vega-"]')).toHaveCount(0);

    expect(await readMoreButtonHeight(page)).toBe(32);
  });
});
