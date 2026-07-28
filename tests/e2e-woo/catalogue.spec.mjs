// tests/e2e-woo/catalogue.spec.mjs
//
// The catalogue and product-page nodes added in s18 (#41,
// docs/plans/2026-07-28-catalogue-and-product.md), plus the two defects that
// walk-through found already live on `main`.
//
// EVERY assertion here that guards a defect reads COMPUTED STYLE, never
// markup, and that is the whole point of the file. Both defects it pins had
// perfect markup and passed phpcs, phpstan, unit, integration and the existing
// e2e; each was a property this theme never declared, left to be decided by a
// stylesheet we do not own:
//
//   - `ul.tabs` also matches BASECOAT's `.tabs` component, which sets
//     `flex-direction: column`. woo.css re-declared `display: flex` (a no-op
//     that masked the collision) and never the direction, so the product tabs
//     rendered as a vertical stack of full-width rows.
//   - Woo's `.woocommerce ul.products li.product .onsale` sets `right: 0` at
//     the same specificity as ours. Ours won on source order for `top` and
//     `left` only, so the badge had BOTH insets set with `width: auto` and
//     stretched into a full-width red bar across every card.
//
// A markup assertion sees neither. A width/direction assertion sees both.
//
// Conventions follow storefront.spec.mjs: the { page } fixture only (never
// browser.newPage() — docs/gotchas/playwright-browser-newpage-skips-config.md),
// and products located by name rather than by permalink shape.
import { expect, test } from '@playwright/test';

const SALE_PRODUCT_SLUG = 'wtb-product-sale';

test.describe('sale badge', () => {
  test('is a pill over the card, not a full-width bar', async ({ page }) => {
    await page.setViewportSize({ width: 1400, height: 900 });
    await page.goto('/shop/');

    const measured = await page.evaluate(() => {
      const badge = document.querySelector('ul.products li.product .onsale');
      if (!badge) {
        return null;
      }

      const card = badge.closest('li.product');

      const badgeBox = badge.getBoundingClientRect();
      const cardBox = card.getBoundingClientRect();

      return {
        badgeWidth: badgeBox.width,
        cardWidth: cardBox.width,
        // How far the badge's right edge stops short of the card's. Zero means
        // the box was stretched across the whole card, which is the defect.
        rightGap: cardBox.right - badgeBox.right,
        text: badge.textContent.trim(),
      };
    });

    expect(
      measured,
      'no on-sale product rendered on /shop/ — check the seeded store',
    ).not.toBeNull();

    // Asserted on GEOMETRY, not on `getComputedStyle(badge).right`. That was
    // the first version of this test and it was wrong in a way worth
    // recording: for a positioned element Chrome reports the USED value of an
    // `auto` inset, not the keyword — the fixed page returns something like
    // "281.469px", so `toBe('auto')` fails against correct CSS while `0px`
    // (the broken state) would also merely be "some string". The distance the
    // badge's right edge stops short of the card's is the thing that actually
    // differs: zero when Woo's `right: 0` stretched it across the card, a
    // couple of hundred pixels when it is the pill the mockup draws.
    expect(measured.rightGap).toBeGreaterThan(measured.cardWidth / 2);
    expect(measured.badgeWidth).toBeLessThan(measured.cardWidth / 2);
  });

  test('reads as a discount percentage', async ({ page }) => {
    await page.goto('/shop/');

    const text = await page.locator('ul.products li.product .onsale').first().innerText();

    // U+2212 MINUS SIGN, not a hyphen — inc/Woo/Catalogue.php's format string.
    expect(text.trim()).toMatch(/^−\d+%$/);
  });
});

test.describe('product tabs', () => {
  test('lay out in a row, not a column', async ({ page }) => {
    await page.setViewportSize({ width: 1400, height: 900 });
    // Straight to the product by its seeded slug, rather than clicking through
    // from /shop/. storefront.spec.mjs clicks through on purpose — it is
    // asserting the card link works — but this test is about the tab list, and
    // routing it through the catalogue made it depend on which page of results
    // the product happens to land on. `?product=<slug>` is a query var, so it
    // is as permalink-independent as the click-through was.
    await page.goto(`/?product=${SALE_PRODUCT_SLUG}`);

    const measured = await page.evaluate(() => {
      const list = document.querySelector('.woocommerce-tabs ul.tabs');
      if (!list) {
        return null;
      }

      const items = [...list.querySelectorAll('li')].map((li) => li.getBoundingClientRect());

      return {
        flexDirection: getComputedStyle(list).flexDirection,
        tops: items.map((rect) => Math.round(rect.top)),
        widths: items.map((rect) => Math.round(rect.width)),
        listWidth: Math.round(list.getBoundingClientRect().width),
      };
    });

    expect(
      measured,
      'the product has no tab list — Woo prints one whenever a tab exists',
    ).not.toBeNull();
    expect(measured.tops.length).toBeGreaterThan(1);

    expect(measured.flexDirection).toBe('row');

    // The observable consequence, independent of how the direction was set:
    // tabs on one line share a top edge, and none of them spans the list.
    expect(new Set(measured.tops).size).toBe(1);
    for (const width of measured.widths) {
      expect(width).toBeLessThan(measured.listWidth);
    }
  });
});

test.describe('catalogue chrome', () => {
  test('cards carry a category eyebrow above the title', async ({ page }) => {
    await page.goto('/shop/');

    const eyebrow = page.locator('ul.products li.product .wtb-product-card__cat').first();
    await expect(eyebrow).toBeVisible();
    await expect(eyebrow).not.toBeEmpty();

    // Positioned above the title rather than merely present somewhere.
    const ordered = await page.evaluate(() => {
      const card = document.querySelector('ul.products li.product');
      const cat = card.querySelector('.wtb-product-card__cat');
      const title = card.querySelector('.woocommerce-loop-product__title');

      // The numeric literal rather than `Node.DOCUMENT_POSITION_FOLLOWING`:
      // this function is serialised and run in the PAGE, where `Node` exists,
      // but ESLint lints it in the Node.js config for this directory, where it
      // does not. 4 is the constant's value, fixed by the DOM spec.
      return cat.compareDocumentPosition(title) & 4;
    });

    expect(ordered).toBeTruthy();
  });

  test('the archive header carries the subcategory chip row', async ({ page }) => {
    await page.setViewportSize({ width: 1400, height: 900 });
    await page.goto('/shop/');

    const chips = page.locator('.woocommerce-products-header .wtb-subcats .wtb-subcat');
    expect(await chips.count()).toBeGreaterThan(0);

    // The row sits against the header's right edge (mockup line 1911), which
    // is a layout claim the markup alone cannot make.
    const aligned = await page.evaluate(() => {
      const header = document.querySelector('.woocommerce-products-header');
      const row = header.querySelector('.wtb-subcats');

      return {
        headerRight: Math.round(header.getBoundingClientRect().right),
        rowRight: Math.round(row.getBoundingClientRect().right),
      };
    });

    expect(Math.abs(aligned.headerRight - aligned.rowRight)).toBeLessThanOrEqual(1);
  });

  test('the breadcrumb separator is quieter than the crumbs either side of it', async ({
    page,
  }) => {
    await page.goto('/shop/');

    const colours = await page.evaluate(() => {
      const nav = document.querySelector('nav.woocommerce-breadcrumb');
      if (!nav) {
        return null;
      }

      const separator = nav.querySelector('.wtb-breadcrumb__sep');
      const link = nav.querySelector('a');

      return separator && link
        ? {
            separator: getComputedStyle(separator).color,
            link: getComputedStyle(link).color,
            nav: getComputedStyle(nav).color,
          }
        : null;
    });

    expect(
      colours,
      'no breadcrumb separator span — the delimiter filter did not apply',
    ).not.toBeNull();
    expect(colours.separator).not.toBe(colours.link);
    expect(colours.link).not.toBe(colours.nav);
  });
});

test.describe('filter rail', () => {
  // Every test here depends on tests/e2e-woo/global-setup.mjs having put
  // WooCommerce's filter widgets into `sidebar-shop`. With that area empty the
  // theme renders the plain full-width shell and `.wtb-filter-rail` does not
  // exist at all — so these would be asserting on nothing. seedShopFilterWidgets()
  // throws rather than letting that state through.

  test('renders as a column beside the results on a wide viewport', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await page.goto('/shop/');

    const measured = await page.evaluate(() => {
      const layout = document.querySelector('.wtb-shop-layout');
      const rail = document.querySelector('.wtb-filter-rail');
      const group = rail && rail.querySelector('.wtb-filter-group');

      return layout && rail && group
        ? {
            columns: getComputedStyle(layout).gridTemplateColumns.split(' ').length,
            railLeft: Math.round(rail.getBoundingClientRect().left),
            contentLeft: Math.round(
              document.querySelector('.wtb-shop-layout__content').getBoundingClientRect().left,
            ),
            // `innerText` rather than a height: a closed <details> still lays
            // its children out with real boxes while painting none of them, so
            // geometry alone cannot tell "visible" from "suppressed". This is
            // the assertion that would have caught the first version of this
            // rail, which measured 248x349 and showed nothing.
            groupPainted: group.innerText.trim().length > 0,
          }
        : null;
    });

    expect(measured, 'no filter rail on /shop/ — is `sidebar-shop` seeded?').not.toBeNull();
    expect(measured.columns).toBe(2);
    expect(measured.railLeft).toBeLessThan(measured.contentLeft);
    expect(measured.groupPainted).toBe(true);
  });

  test('collapses behind its summary on a narrow viewport', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 900 });
    await page.goto('/shop/');

    const toggle = page.locator('.wtb-filter-rail__toggle');
    await expect(toggle).toBeVisible();

    // The toggle only exists once src/js/woo.js has run — PHP renders a plain
    // `<span>` title — so its presence above already proves the module
    // executed, and no polling for a racy attribute is needed.
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
    await expect(page.locator('.wtb-filter-rail__panel')).toBeHidden();

    // And the rail takes no more room than its own head. This is the assertion
    // the `<details>` version could not pass: a closed `<details>` left the
    // grid row sized for the open panel, so the aside measured 790px tall
    // around a 45px control — a screen-height gap under the button that no
    // markup assertion could see.
    const measured = await page.evaluate(() => {
      const rail = document.querySelector('.wtb-filter-rail');
      const head = rail.querySelector('.wtb-filter-rail__head');

      return {
        railHeight: Math.round(rail.getBoundingClientRect().height),
        headHeight: Math.round(head.getBoundingClientRect().height),
      };
    });

    expect(measured.railHeight).toBeLessThanOrEqual(measured.headHeight + 4);
  });

  test('reopens when the viewport crosses back to the wide layout', async ({ page }) => {
    // The failure this guards: closing the rail once on a narrow viewport and
    // never reopening it leaves a visitor who rotates a tablet — or drags a
    // window wider — looking at a 248px column with a "Filters" label and
    // nothing under it, because the desktop CSS cannot undo a closed
    // <details>. src/js/woo.js re-syncs on the media query, not just at load.
    await page.setViewportSize({ width: 390, height: 900 });
    await page.goto('/shop/');
    await page.setViewportSize({ width: 1280, height: 900 });

    await expect(page.locator('.wtb-filter-rail__toggle')).toHaveAttribute('aria-expanded', 'true');
    await expect(page.locator('.wtb-filter-rail__panel')).toBeVisible();
  });

  test('shows the reset link only while a filter is active', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });

    await page.goto('/shop/');
    await expect(page.locator('.wtb-filter-rail__reset')).toHaveCount(0);

    await page.goto('/shop/?filter_wtb-colour=forest');
    const reset = page.locator('.wtb-filter-rail__reset');
    await expect(reset).toBeVisible();

    // It must go somewhere that actually clears the filter, not just anywhere.
    const href = await reset.getAttribute('href');
    expect(href).not.toContain('filter_wtb-colour');

    // And it must be the quiet ghost button, not the primary one. This is a
    // computed-style assertion because the failure it guards produced correct
    // markup: the class was ported from the mockup as `btn--ghost btn--sm`,
    // which this theme's attribute-based button contract ignores entirely, so
    // the link fell through to `.btn:not([data-variant])` and rendered as a
    // solid primary block above the filters.
    const background = await reset.evaluate((el) => getComputedStyle(el).backgroundColor);
    const primary = await page.evaluate(() =>
      getComputedStyle(document.documentElement).getPropertyValue('--primary').trim(),
    );
    expect(background).not.toBe(primary);
  });
});

test.describe('pagination', () => {
  test('renders a pager whose next link carries an icon and an accessible name', async ({
    page,
  }) => {
    await page.goto('/shop/');

    const next = page.locator('.woocommerce-pagination a.next');
    await expect(next).toBeVisible();

    // Woo's default is the bare entity `&rarr;`; inc/Woo/Catalogue.php swaps in
    // the theme's chevron plus a screen-reader-only name, because an anchor
    // whose only content is an aria-hidden SVG has no accessible name at all.
    await expect(next.locator('svg')).toHaveCount(1);
    await expect(next.locator('.sr-only')).not.toBeEmpty();
  });
});
