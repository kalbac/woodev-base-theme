// tests/e2e/components.spec.mjs
//
// §7 component tail (M1 tail): the post-card grid, category badges, the
// alert treatment for the 404/empty-search states, and the comment-form
// controls — asserted against real rendered pages.
//
// READ-ONLY. This file must never mutate a site-global theme_mod:
// tests/e2e/theme-mods.spec.mjs is the one file allowed to do that, because
// Playwright parallelises by file and a second mutating spec would race it
// (see that file's own header comment). The one case here that genuinely
// needs a theme_mod — the sidebar column cap — lives in theme-mods.spec.mjs
// instead. The single wp-cli call below is a READ (a post URL lookup), not a
// state change.
import { expect, test } from '@playwright/test';
import { wp } from './lib/theme-mod.mjs';

/** Number of CSS grid tracks `.wtb-post-grid` resolves to at a given width. */
async function gridTrackCount(page) {
  return page.evaluate(
    () =>
      getComputedStyle(document.querySelector('.wtb-post-grid')).gridTemplateColumns.split(' ')
        .length,
  );
}

const GRID_BREAKPOINTS = [
  { width: 375, tracks: 1 },
  { width: 800, tracks: 2 },
  { width: 1400, tracks: 3 },
];

for (const { width, tracks } of GRID_BREAKPOINTS) {
  test(`the post grid resolves ${tracks} track(s) at ${width}px`, async ({ page }) => {
    // grid-template-columns resolves to concrete pixel tracks at the given
    // width, so the number of tracks IS the column count. Counting visible
    // cards instead would pass silently with too few posts in the row.
    await page.setViewportSize({ width, height: 800 });
    await page.goto('/');

    // These counts are the NO-sidebar contract; the sidebar cap is a separate
    // case in theme-mods.spec.mjs. Assert the precondition rather than assume
    // it: a leftover sidebar_position=right (a killed run in that file never
    // restored it) would legitimately cap 1400px at 2 tracks, and without this
    // guard the failure would read as a broken breakpoint rather than a dirty
    // theme_mod.
    await expect(page.locator('.wtb-layout--has-sidebar')).toHaveCount(0);

    expect(await gridTrackCount(page)).toBe(tracks);
  });
}

test('post excerpts render as cards with header, section and footer', async ({ page }) => {
  await page.goto('/');

  const cards = page.locator('.wtb-entry-card.card');
  const count = await cards.count();
  expect(count).toBeGreaterThan(1);

  for (let i = 0; i < count; i += 1) {
    const card = cards.nth(i);
    await expect(card.locator('> header')).toHaveCount(1);
    await expect(card.locator('> section')).toHaveCount(1);
    await expect(card.locator('> footer')).toHaveCount(1);
  }
});

test('a category badge in a card header links to a live category archive', async ({ page }) => {
  await page.goto('/');

  const badge = page
    .locator('.wtb-entry-card.card > header .badge[data-variant="secondary"]')
    .first();
  await expect(badge).toBeVisible();

  const href = await badge.getAttribute('href');
  expect(href).toBeTruthy();

  const response = await page.request.get(href);
  expect(response.status()).toBe(200);
});

test('a non-existent page renders one alert containing the page h1', async ({ page }) => {
  const response = await page.goto('/no-such-page/');
  expect(response.status()).toBe(404);

  const alert = page.locator('.alert');
  await expect(alert).toHaveCount(1);
  await expect(alert.locator('> h1[data-title]')).toHaveCount(1);
});

test('an empty search renders an alert and no post grid', async ({ page }) => {
  const response = await page.goto('/?s=zzzzzznothing');
  expect(response.status()).toBe(200);

  await expect(page.locator('.alert')).toHaveCount(1);
  await expect(page.locator('.wtb-post-grid')).toHaveCount(0);
});

test('a single post exposes the styled comment form controls', async ({ page }) => {
  // A read, not a mutation: fetches an existing post's URL, writes nothing.
  const url = wp('post list --post_type=post --posts_per_page=1 --field=url');
  expect(url).toBeTruthy();

  await page.goto(url);

  await expect(page.locator('textarea.textarea')).toBeVisible();
  await expect(page.locator('#submit.btn')).toBeVisible();
});

test('the card actually restyles under the dark scheme', async ({ page }) => {
  // Same runtime-toggle approach templates.spec.mjs established for a read-only
  // dark-mode check: toggle `.dark` on <html> via page.evaluate AFTER
  // navigation, rather than depending on the color_scheme_* theme_mods (which
  // theme-mods.spec.mjs may be mutating in another worker) or on
  // browser.newPage() (skips project config —
  // docs/gotchas/playwright-browser-newpage-skips-config.md).
  //
  // Assert a computed colour that MUST change, not DOM structure: the card's
  // element tree is identical in both schemes, so counting header/section/
  // footer would stay green with the dark tokens completely broken — the same
  // vacuous-assertion trap smoke.spec.mjs documents. `.card` paints `bg-card`
  // (--card), which is near-white in light and near-black in dark, so its
  // computed background-color is the cheapest value that proves the dark
  // tokens reached the component.
  await page.goto('/');

  const card = page.locator('.wtb-entry-card.card').first();
  await expect(card).toBeVisible();

  const readBg = () => card.evaluate((el) => getComputedStyle(el).backgroundColor);

  const light = await readBg();
  await page.evaluate(() => document.documentElement.classList.add('dark'));
  const dark = await readBg();

  expect(light).not.toBe(dark);
});
