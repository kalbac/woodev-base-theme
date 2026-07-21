// tests/e2e/style-packs.spec.mjs
//
// Guards M1-03: Assets enqueues exactly the bundle the style_preset theme_mod
// selects, and the 8 packs are genuinely different (they differ in component
// SHAPE, not colour — so we assert a .btn's height, not a colour). The blog
// index renders a "Read more" .btn per post (seeded by global-setup).
//
// Runs serially and resets the mod in finally: the mod is global to the shared
// dev site, and other specs assume the default (vega).
import { execSync } from 'node:child_process';
import { expect, test } from '@playwright/test';

/** Run a wp-cli command in the cli container, return trimmed stdout. */
function wp(command) {
  return execSync(`npx wp-env run cli wp ${command}`, {
    encoding: 'utf8',
    stdio: ['ignore', 'pipe', 'pipe'],
  }).trim();
}

/** Height (px, rounded) of the first Basecoat .btn on the current page. */
async function firstButtonHeight(page) {
  const btn = page.locator('.btn').first();
  await expect(btn).toBeVisible();
  return Math.round((await btn.boundingBox()).height);
}

test.describe.serial('Basecoat style packs', () => {
  test.afterAll(() => {
    wp('theme mod remove style_preset');
  });

  test('default pack (vega): vega bundle loads and .btn is 36px tall', async ({ page }) => {
    wp('theme mod remove style_preset');
    await page.goto('/');

    await expect(page.locator('link[rel="stylesheet"][href*="style-vega-"]')).toHaveCount(1);
    await expect(page.locator('link[rel="stylesheet"][href*="style-nova-"]')).toHaveCount(0);

    expect(await firstButtonHeight(page)).toBe(36);
  });

  test('switching to nova loads the nova bundle and shrinks the .btn to 32px', async ({ page }) => {
    wp('theme mod set style_preset nova');
    await page.goto('/');

    await expect(page.locator('link[rel="stylesheet"][href*="style-nova-"]')).toHaveCount(1);
    await expect(page.locator('link[rel="stylesheet"][href*="style-vega-"]')).toHaveCount(0);

    expect(await firstButtonHeight(page)).toBe(32);
  });
});
