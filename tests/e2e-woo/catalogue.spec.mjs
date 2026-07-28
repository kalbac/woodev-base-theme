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

    // Visibility FIRST, and not as ceremony: a regression that hid the badge
    // (`display: none`, or a zero-size box) would give it a 0x0 rect, which
    // satisfies both geometry assertions below — "narrower than half the card"
    // and "stops well short of its right edge" are both trivially true of
    // nothing at all. Raised by the s18 critic pass against the guards
    // themselves.
    await expect(page.locator('ul.products li.product .onsale').first()).toBeVisible();

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

  test('the archive header keeps its title readable at 320px', async ({ page }) => {
    // The failure this guards, found by the s18 critic pass and confirmed in
    // the browser: with the header's two-column grid applied at every width,
    // the chip track is sized to its max-content — every chip on one line, and
    // `flex-wrap` does not constrain a track while it is being sized. At 320px
    // the chips took 249px and the title's track was left at 0px, so the <h1>
    // wrapped at every character and ran down the left edge one letter per
    // line. The page did NOT overflow, so a scrollWidth assertion would have
    // stayed green through all of it.
    await page.setViewportSize({ width: 320, height: 800 });
    await page.goto('/product-category/wtb-kitchen/');

    const measured = await page.evaluate(() => {
      const header = document.querySelector('.woocommerce-products-header');
      const title = header.querySelector('.woocommerce-products-header__title');
      const box = title.getBoundingClientRect();
      const lineHeight = Number.parseFloat(getComputedStyle(title).lineHeight);

      return {
        titleWidth: Math.round(box.width),
        headerWidth: Math.round(header.getBoundingClientRect().width),
        lines: Math.round(box.height / lineHeight),
        chipCount: header.querySelectorAll('.wtb-subcat').length,
      };
    });

    expect(
      measured.chipCount,
      'no subcategory chips — the fixture tree is missing',
    ).toBeGreaterThan(1);
    // The title owns the full content width, not a sliver of it.
    expect(measured.titleWidth).toBe(measured.headerWidth);
    // And it sets on one or two lines, not one line per character.
    expect(measured.lines).toBeLessThanOrEqual(2);
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

    // It must be the quiet ghost button, not the primary one. The failure this
    // guards produced perfectly correct markup: the class was ported from the
    // mockup as `btn--ghost btn--sm`, which this theme's attribute-based button
    // contract ignores entirely, so the link fell through to
    // `.btn:not([data-variant])` and rendered as a solid primary block above
    // the filters.
    //
    // Asserted as "transparent", not as "different from --primary". The latter
    // was the first version and it was VACUOUS: `backgroundColor` computes to
    // an `rgb(…)`/`oklch(…)` string while `--primary` is the raw token text, so
    // the two can never be equal and a solid primary button would have passed.
    // Caught by the s18 critic pass reviewing the guards themselves.
    await expect(reset).toHaveCSS('background-color', 'rgba(0, 0, 0, 0)');

    // And it must actually clear the filtering — FOLLOWED, not just inspected.
    // Asserting the href merely lacks `filter_wtb-colour` would pass for a link
    // that swapped one active filter for another.
    await reset.click();
    const landed = new URL(page.url());
    const stillFiltering = [...landed.searchParams.keys()].filter(
      (key) =>
        key.startsWith('filter_') ||
        key.startsWith('query_type_') ||
        ['min_price', 'max_price', 'rating_filter'].includes(key),
    );
    expect(stillFiltering).toEqual([]);
    await expect(page.locator('.wtb-filter-rail__reset')).toHaveCount(0);
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

    // The NAME is asserted against the accessibility tree, not against the
    // presence of a non-empty `.sr-only` element. Those are different claims:
    // an `.sr-only` span that picked up `display: none`, or `aria-hidden`, is
    // still non-empty in the DOM and contributes nothing to the name. The
    // whole point of this markup is the name, so that is what is measured.
    await expect(next).toHaveAccessibleName(/next page/i);
  });
});
