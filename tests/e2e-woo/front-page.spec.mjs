// tests/e2e-woo/front-page.spec.mjs
//
// The three #40 front-page sections are sourced from the live store, so this
// suite belongs beside the Woo e2e fixtures rather than the Woo-free base suite.
// Assertions use rendered structure plus geometry: the product grid's computed
// track count catches a front-only override that accidentally changes archives,
// while the card counts catch a query that silently returns the wrong source.
import { expect, test } from '@playwright/test';

test.describe('front-page sections', () => {
  test('renders four popularity-sourced product picks in a four-column home grid', async ({
    page,
  }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await page.goto('/');

    const section = page.locator('.wtb-front-products');
    await expect(section).toBeVisible();

    const cards = section.locator('ul.products > li.wtb-product-card');
    await expect(cards).toHaveCount(4);

    const columns = await section
      .locator('ul.products')
      .evaluate(
        (element) => getComputedStyle(element).gridTemplateColumns.trim().split(/\s+/).length,
      );
    expect(columns).toBe(4);
  });

  test('renders three newest posts as editorial cards with links and artwork', async ({ page }) => {
    await page.goto('/');

    const section = page.locator('.wtb-front-journal');
    await expect(section).toBeVisible();
    await expect(section.locator('.wtb-front-editorial__card')).toHaveCount(3);
    await expect(section.locator('.wtb-front-editorial__card h3 a')).toHaveCount(3);
    await expect(
      section.locator('.wtb-front-editorial__thumb img, .wtb-front-editorial__thumb svg'),
    ).toHaveCount(3);
  });

  test('does not print a newsletter shell or raw shortcode without a plugin setting', async ({
    page,
  }) => {
    await page.goto('/');

    await expect(page.locator('.wtb-front-newsletter')).toHaveCount(0);
    await expect(page.locator('body')).not.toContainText('newsletter_form');
  });

  test('collapses the front-page grids to one column on a phone viewport', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/');

    const tracks = await page.evaluate(() =>
      ['.wtb-front-products ul.products', '.wtb-front-editorial'].map((selector) => {
        const element = document.querySelector(selector);
        return element
          ? getComputedStyle(element).gridTemplateColumns.trim().split(/\s+/).length
          : 0;
      }),
    );

    expect(tracks).toEqual([1, 1]);
  });
});
