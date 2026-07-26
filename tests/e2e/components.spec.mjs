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

// ---------------------------------------------------------------------------
// Critic findings (forms.css/feedback.css/content.css/blocks.css) — each test
// below pins one finding's fix as a COMPUTED-STYLE regression guard, per the
// task brief. See those files' own inline comments for the reasoning; this
// header only notes the e2e-specific choices.

test('an invalid input keeps a focus indicator distinct from its unfocused error state', async ({
  page,
}) => {
  // Finding 1 (P0). `#author` is comments.php's real live `.input` (no
  // `.field` wrapper — see forms.css's own header note), so this test runs
  // against real markup, not a mount.
  const url = wp('post list --post_type=post --posts_per_page=1 --field=url');
  expect(url).toBeTruthy();

  await page.goto(url);

  const input = page.locator('#author.input');
  await expect(input).toBeVisible();

  // `.is-error` is forms.css's own pure CSS state class, toggled here purely
  // at runtime (a DOM class, not a theme_mod or template change) — the same
  // established pattern as the dark-scheme test above.
  await input.evaluate((el) => el.classList.add('is-error'));

  const readIndicator = () =>
    input.evaluate((el) => {
      const cs = getComputedStyle(el);
      return { borderColor: cs.borderColor, boxShadow: cs.boxShadow };
    });

  const unfocusedInvalid = await readIndicator();

  // `.focus()` is Playwright's documented way to give an element keyboard
  // focus (no pointer event involved); forms.css reacts to plain `:focus`
  // (not `:focus-visible`) throughout, so this matches the CSS under test.
  await input.focus();
  const focusedInvalid = await readIndicator();

  expect(focusedInvalid.boxShadow).not.toBe(unfocusedInvalid.boxShadow);
});

test('a focused field never disables its own outline shape', async ({ page }) => {
  // Finding P0-2. `outline: none` removes the outline SHAPE outright, not
  // just its visibility — that is exactly what makes it unrecoverable in
  // forced-colors mode (see the next test), because forced-colors mode only
  // repaints a COLOUR, it cannot resurrect a shape that isn't there. This
  // pins the general rule in normal rendering, independent of any
  // forced-colors emulation: `outline-style` must never compute to `none`
  // on a focused field.
  const url = wp('post list --post_type=post --posts_per_page=1 --field=url');
  expect(url).toBeTruthy();

  await page.goto(url);

  const input = page.locator('#author.input');
  await input.focus();

  const outlineStyle = await input.evaluate((el) => getComputedStyle(el).outlineStyle);
  expect(outlineStyle).not.toBe('none');
});

test('a focused invalid field paints a real outline under forced-colors mode', async ({ page }) => {
  // Finding P0-2. forms.css draws its focus halo entirely via `box-shadow`,
  // which forced-colors mode (Windows High Contrast) drops outright — so
  // this outline is the ONLY focus indication a forced-colors keyboard user
  // gets, worst on an already-destructive-coloured invalid field.
  // `page.emulateMedia({ forcedColors: 'active' })` is Playwright's
  // documented way to drive the real `forced-colors` media feature (backed
  // by Chromium's own forced-colors implementation, not a simulation), so
  // this exercises the actual mechanism a High-Contrast user hits.
  const url = wp('post list --post_type=post --posts_per_page=1 --field=url');
  expect(url).toBeTruthy();

  await page.emulateMedia({ forcedColors: 'active' });
  await page.goto(url);

  const input = page.locator('#author.input');
  await input.evaluate((el) => el.classList.add('is-error'));
  await input.focus();

  const outline = await input.evaluate((el) => {
    const cs = getComputedStyle(el);
    return { style: cs.outlineStyle, width: cs.outlineWidth, color: cs.outlineColor };
  });

  expect(outline.style).not.toBe('none');
  expect(parseFloat(outline.width)).toBeGreaterThan(0);
  // Not literally invisible: either the browser's own forced-colors handling
  // repainted the declared `transparent` outline-colour (the standard
  // behaviour once outline-style isn't `none`), or the explicit
  // `@media (forced-colors: active)` block in forms.css did it directly —
  // either way the field must not still resolve to a fully transparent
  // colour here.
  expect(outline.color).not.toBe('rgba(0, 0, 0, 0)');
  expect(outline.color).not.toBe('transparent');
});

test('the 404 alert stacks its heading above its body at 320px', async ({ page }) => {
  // Finding 2 (P1).
  await page.setViewportSize({ width: 320, height: 800 });
  const response = await page.goto('/no-such-page/');
  expect(response.status()).toBe(404);

  const alert = page.locator('.alert');
  await expect(alert).toHaveCount(1);

  const title = alert.locator('> h1[data-title]');
  const body = alert.locator('> section');

  const titleBox = await title.boundingBox();
  const bodyBox = await body.boundingBox();
  expect(titleBox).not.toBeNull();
  expect(bodyBox).not.toBeNull();

  // Stacked, not side-by-side: the body's top edge is at/after the title's
  // bottom edge. A 1px tolerance absorbs subpixel rounding, nothing more.
  expect(bodyBox.y).toBeGreaterThanOrEqual(titleBox.y + titleBox.height - 1);
});

test('pagination wraps instead of overflowing at 320px', async ({ page }) => {
  // Finding 3 (P1). The seeded fixture (global-setup.mjs) creates 12 posts =
  // 2 pages at the default 10/page, so the real page never renders the long
  // prev+first+…neighbours…+last+next row the finding describes (that needs
  // far more pages than this suite seeds). scrollWidth<=clientWidth would
  // therefore pass trivially either way here, so the real regression guard
  // is the computed `flex-wrap`, which is exactly what the CSS fix changed;
  // the overflow check is kept as a cheap secondary sanity check.
  await page.setViewportSize({ width: 320, height: 800 });
  await page.goto('/');

  const navLinks = page.locator('.pagination .nav-links');
  await expect(navLinks).toBeVisible();

  const flexWrap = await navLinks.evaluate((el) => getComputedStyle(el).flexWrap);
  expect(flexWrap).toBe('wrap');

  const fits = await navLinks.evaluate((el) => el.scrollWidth <= el.clientWidth);
  expect(fits).toBe(true);
});

test('threaded comments stay legible at the deepest depth WordPress allows, at 320px', async ({
  page,
}) => {
  // Finding 4 (P1). No comment thread exists in the seeded fixtures
  // (global-setup.mjs seeds posts, not comments), and creating real nested
  // comments here would be a content mutation outside this file's
  // READ-ONLY/no-global-state contract (see file header). Instead, mount
  // WordPress core's own default HTML5 comment-list contract directly on a
  // real single-post page, at WordPress's default 5-level
  // `thread_comments_depth`, so content.css's real selectors and cascade
  // apply against real computed layout. `wp_list_comments()` is called with
  // `'style' => 'ol'` in comments.php (its default), so core's real reply
  // wrapper is `<ol class="children">`, not a `<ul>` or a bare `<div>` — this
  // mounts that exact tag, not a stand-in.
  const url = wp('post list --post_type=post --posts_per_page=1 --field=url');
  expect(url).toBeTruthy();

  await page.setViewportSize({ width: 320, height: 800 });
  await page.goto(url);

  const DEPTH = 5;

  await page.evaluate((depth) => {
    let html = '<div class="comment-content">Leaf reply text that must stay readable.</div>';
    for (let i = 0; i < depth; i += 1) {
      html = `<li class="comment"><article class="comment-body"><div class="comment-content">Reply text at this depth.</div></article><ol class="children">${html}</ol></li>`;
    }
    const list = document.createElement('ol');
    list.className = 'wtb-comment-list';
    list.innerHTML = html;
    document.body.prepend(list);
  }, DEPTH);

  const childrenLists = page.locator('.wtb-comment-list .children');
  await expect(childrenLists).toHaveCount(DEPTH);

  const marginLeftAt = (level) =>
    childrenLists.nth(level - 1).evaluate((el) => getComputedStyle(el).marginLeft);

  const depth1 = await marginLeftAt(1);
  const depth2 = await marginLeftAt(2);
  const depth3 = await marginLeftAt(3);
  const depth4 = await marginLeftAt(4);
  const depth5 = await marginLeftAt(5);

  // Finding 8 (P2), fixed here: the previous version asserted
  // `depth3 === depth2`, which is FALSE against the very CSS it claims to
  // guard — at this 320px viewport, content.css's `@media (max-width: 30rem)`
  // block gives depth 1 and depth 2 two DIFFERENT non-zero indents
  // (`--space-md`, `--space-sm`), and only depth 3 onward collapses to a
  // shared cap. The actual guarantee is: shallow depths still indent
  // (legible nesting), and the indent stops GROWING once the cap kicks in —
  // not that depth 3 matches depth 2.
  expect(depth1).not.toBe('0px');
  expect(depth2).not.toBe('0px');
  expect(depth2).not.toBe(depth1);

  // The cap: every depth from 3 on shares the SAME value (production CSS
  // resolves it to 0 at this viewport, but the guarantee under test is the
  // capping, not that specific literal).
  expect(depth3).toBe(depth4);
  expect(depth4).toBe(depth5);
  // And the cap is a REAL cap, distinct from depth 2 — this is what the old,
  // inverted assertion got backwards.
  expect(depth3).not.toBe(depth2);

  // And the leaf text itself still has real width to render into at 320px.
  const leaf = page.locator('.wtb-comment-list .comment-content').last();
  const box = await leaf.boundingBox();
  expect(box).not.toBeNull();
  expect(box.width).toBeGreaterThan(120);
});

test('a .field > select renders a chevron; a bare select.select keeps a native appearance', async ({
  page,
}) => {
  // Finding 6 (P1) and finding 9 (P2). Neither shape exists in any template
  // yet — the theme emits no `.field > select` markup at all today (see
  // forms.css's own file header); this mounts an ideal, forward-groundwork
  // DOM directly on a live page so the real CSS cascade (adapter layer,
  // tokens) applies, and pins the contract for whichever future template
  // (T6 checkout/account) is the first real consumer.
  await page.goto('/');

  await page.evaluate(() => {
    const wrap = document.createElement('div');
    wrap.innerHTML = `
      <div class="field" id="e2e-field-select">
        <label>Sort</label>
        <select class="select"><option>A</option><option>B</option></select>
      </div>
      <select class="select" id="e2e-bare-select"><option>A</option><option>B</option></select>
    `;
    document.body.prepend(wrap);
  });

  const field = page.locator('#e2e-field-select');
  const wrapped = page.locator('#e2e-field-select > select');
  const bare = page.locator('#e2e-bare-select');

  const wrappedAppearance = await wrapped.evaluate((el) => getComputedStyle(el).appearance);
  const bareAppearance = await bare.evaluate((el) => getComputedStyle(el).appearance);

  expect(wrappedAppearance).toBe('none');
  expect(bareAppearance).toBe('auto');

  // Finding 9 (P2), fixed here: the old assertion only checked that the
  // `::after` pseudo-element's `content` was not `none` — that stays green
  // even if the arrow is positioned outside the control, fully transparent,
  // or has no mask at all. Compute its actual box (via the field's own
  // `right`/`bottom` offsets, since a pseudo-element has no `boundingBox()`
  // of its own) and confirm it sits INSIDE the select's box, not merely
  // somewhere on the page, plus that it has a real mask image and a
  // non-transparent fill colour that would actually paint.
  const fieldBox = await field.boundingBox();
  const selectBox = await wrapped.boundingBox();
  expect(fieldBox).not.toBeNull();
  expect(selectBox).not.toBeNull();

  const arrow = await field.evaluate((el) => {
    const cs = getComputedStyle(el, '::after');
    return {
      content: cs.content,
      width: parseFloat(cs.width),
      height: parseFloat(cs.height),
      right: parseFloat(cs.right),
      bottom: parseFloat(cs.bottom),
      backgroundColor: cs.backgroundColor,
      maskImage: cs.maskImage && cs.maskImage !== 'none' ? cs.maskImage : cs.webkitMaskImage,
    };
  });
  expect(arrow.content).not.toBe('none');

  const arrowRect = {
    left: fieldBox.x + fieldBox.width - arrow.right - arrow.width,
    top: fieldBox.y + fieldBox.height - arrow.bottom - arrow.height,
    right: fieldBox.x + fieldBox.width - arrow.right,
    bottom: fieldBox.y + fieldBox.height - arrow.bottom,
  };

  // A 1px tolerance absorbs subpixel rounding, nothing more — this is the
  // geometry check the old test never made: `bottom: 14px` anchored to the
  // FIELD (finding 5, P2) rather than the select, so a field with a trailing
  // `.hint` would push this arrow below the select entirely without this
  // failing.
  expect(arrowRect.left).toBeGreaterThanOrEqual(selectBox.x - 1);
  expect(arrowRect.right).toBeLessThanOrEqual(selectBox.x + selectBox.width + 1);
  expect(arrowRect.top).toBeGreaterThanOrEqual(selectBox.y - 1);
  expect(arrowRect.bottom).toBeLessThanOrEqual(selectBox.y + selectBox.height + 1);

  // Paintable, not just present: a mask with no image, or a fully
  // transparent fill colour, would satisfy every check above while
  // rendering nothing.
  expect(arrow.maskImage).not.toBe('none');
  expect(arrow.backgroundColor).not.toBe('rgba(0, 0, 0, 0)');
  expect(arrow.backgroundColor).not.toBe('transparent');
});

test('a .field > select[multiple] keeps its native appearance and draws no chevron', async ({
  page,
}) => {
  // Finding 5 (P2). `.field:has(> select)` used to match ANY select,
  // including `[multiple]`/`[size]` listboxes that have no single closed
  // dropdown to decorate — `appearance: none` would have stripped their
  // native listbox chrome, and the chevron would have drawn over content
  // that isn't a dropdown at all. Pins the `:not([multiple]):not([size])`
  // exclusion.
  await page.goto('/');

  await page.evaluate(() => {
    const wrap = document.createElement('div');
    wrap.innerHTML = `
      <div class="field" id="e2e-field-multiselect">
        <label>Options</label>
        <select class="select" multiple><option>A</option><option>B</option></select>
      </div>
    `;
    document.body.prepend(wrap);
  });

  const multiSelect = page.locator('#e2e-field-multiselect > select');
  const appearance = await multiSelect.evaluate((el) => getComputedStyle(el).appearance);
  expect(appearance).toBe('auto');

  const afterContent = await page
    .locator('#e2e-field-multiselect')
    .evaluate((el) => getComputedStyle(el, '::after').content);
  expect(afterContent).toBe('none');
});
