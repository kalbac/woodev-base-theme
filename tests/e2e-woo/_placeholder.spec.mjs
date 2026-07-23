// Placeholder so Playwright can discover this testDir before Task 6 lands the
// real specs. Delete when tests/e2e-woo/storefront.spec.mjs is added.
import { test, expect } from '@playwright/test';

test('placeholder', async ({ page }) => {
  await page.goto('/');
  await expect(page).toHaveTitle(/.*/);
});
