// tests/e2e/theme-mods.spec.mjs
//
// THE ONE FILE THAT MUTATES SITE-GLOBAL STATE — theme_mods AND, since #37,
// the options that decide the static front page (show_on_front/page_on_front).
//
// Playwright parallelises by FILE, so keeping every site-global mutation in a
// single serial file is what guarantees no other spec observes a half-applied
// setting. Do not add a theme_mod or option mutation to any other spec — put
// it here. Each test restores what it touched before the next one runs.
import { expect, test } from '@playwright/test';
import { formatColor, resolveColor, varsFor } from '../../scripts/lib/build-tokens-lib.mjs';
import { tokens } from '../../src/tokens/tokens.mjs';
import { readOption, restoreOption } from './lib/option.mjs';
import { isInteger, isToggleValue, readThemeMod, restoreThemeMod, wp } from './lib/theme-mod.mjs';

const SCHEMES = ['system', 'light', 'dark'];

const SIDEBAR_POSITIONS = ['none', 'left', 'right'];

/**
 * theme_mod name -> guard, for everything this file touches.
 *
 * ADR-008 retired `style_preset` (the 8 Basecoat packs) and `primary_preset`
 * (the Tailwind-palette accent presets) outright, and T7
 * (`docs/plans/2026-07-25-visual-identity.md`) has since built their
 * replacements — `palette` (7 slugs), `accent` (hex), `font`, `cta_reveal` —
 * but no test in THIS file mutates any of them yet (deferred to the T8 e2e
 * gate), so there is still nothing here for TOUCHED to restore for them.
 *
 * `radius_scale` (four rem STEPS) was retired by T7 too, replaced by
 * `radius` (a PX INTEGER 0-16, Settings::sanitize_radius()'s docblock has
 * the full migration rationale) — updated below rather than left to rot,
 * since this file's own radius test exercises the exact contract that moved.
 */
const TOUCHED = {
  container_width: isInteger,
  radius: isInteger,
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

  // An untouched site must ship no inline block at all — that contract is what
  // keeps the default install free of per-page CSS.
  test('an untouched site emits no inline style block', async ({ page }) => {
    await page.goto('/');

    await expect(page.locator('style#woodev-base-inline')).toHaveCount(0);
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

  test('a left sidebar moves visually before content only on desktop', async ({ page }) => {
    wp('theme mod set sidebar_position left');
    await page.setViewportSize({ width: 1400, height: 900 });
    await page.goto('/');

    const sidebar = page.locator('.wtb-sidebar');
    const content = page.locator('.wtb-layout__content');
    await expect(sidebar).toBeVisible();
    await expect(content).toBeVisible();

    const [sidebarBox, contentBox] = await Promise.all([
      sidebar.boundingBox(),
      content.boundingBox(),
    ]);
    expect(sidebarBox).not.toBeNull();
    expect(contentBox).not.toBeNull();
    expect(sidebarBox.x).toBeLessThan(contentBox.x);

    // The DOM deliberately remains content first. Narrow layouts collapse to
    // that reading order instead of duplicating the sidebar or moving it with JS.
    await page.setViewportSize({ width: 375, height: 800 });
    await page.reload();

    const [narrowSidebar, narrowContent] = await Promise.all([
      sidebar.boundingBox(),
      content.boundingBox(),
    ]);
    expect(narrowSidebar).not.toBeNull();
    expect(narrowContent).not.toBeNull();
    expect(narrowContent.y).toBeLessThan(narrowSidebar.y);
  });

  // --radius drives Basecoat's --radius-md/-lg/-xl through calc(), so one
  // setting reshapes every component that reads them.
  //
  // This used to also assert the read-more button's rendered
  // border-top-left-radius, which came from the active style pack's own
  // `rounded-*` utility on `.btn`. ADR-008 retires the packs, and
  // `basecoat-css/base` styles no component geometry at all (see the
  // surprising-finding note in src/css/app.css) — `.btn`'s radius is not
  // driven by anything yet, that is T5's job (component kit, adapter layer).
  // Until T5 lands there is nothing for a `.btn`-geometry assertion to prove;
  // the token-level assertion below is what T2's Settings/InlineStyles
  // machinery actually guarantees today.
  test('the radius setting moves the --radius token', async ({ page }) => {
    wp('theme mod set radius 0');
    await page.goto('/');

    expect(await rootVar(page, '--radius')).toBe('0px');

    wp('theme mod set radius 16');
    await page.goto('/');

    expect(await rootVar(page, '--radius')).toBe('16px');
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

        // tokens.colors.dark.background is `var(--n-950)` since the identity
        // landed (ADR-008), not a literal oklch() string — canvas 2D's
        // fillStyle does NOT resolve custom properties, so feeding it the raw
        // token would silently keep canvas's untouched default (black)
        // instead of a real colour. Resolve through the same substitution the
        // generator and the browser both perform (this file never sets the
        // T7 `palette`/`accent` theme_mods, so it is always warm-clay, the
        // default palette).
        const darkVars = varsFor(tokens, 'warm-clay', 'dark');
        const expectedBackground = formatColor(resolveColor(darkVars.background, darkVars));

        const background = await canonicalColor(page, await rootVar(page, '--background'));
        expect(background).toBe(await canonicalColor(page, expectedBackground));
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

        // See the sibling "dark" test above for why this resolves through
        // varsFor()/resolveColor() rather than feeding the raw var()-valued
        // token to canvas's fillStyle.
        const lightVars = varsFor(tokens, 'warm-clay', 'light');
        const expectedBackground = formatColor(resolveColor(lightVars.background, lightVars));

        const background = await canonicalColor(page, await rootVar(page, '--background'));
        expect(background).toBe(await canonicalColor(page, expectedBackground));
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

  // #37: the static front page is the one front-page.php render mode that
  // mutates site-global STATE rather than a theme_mod — show_on_front and
  // page_on_front are OPTIONS, so this lives here per this file's own header
  // rule, not alongside the posts-front-page test in templates.spec.mjs.
  test.describe('static front page (#37)', () => {
    const FRONT_PAGE_OPTIONS = {
      show_on_front: (value) => 'posts' === value || 'page' === value,
      page_on_front: isInteger,
    };

    /**
     * Prior state per option: `{ exists, value }`, or null when never read.
     * "Absent" is a state an option can legitimately be in and `wp option
     * update` cannot express it — see lib/option.mjs.
     *
     * @type {Record<string, {exists: boolean, value: string}|null>}
     */
    const previousOptions = Object.fromEntries(
      Object.keys(FRONT_PAGE_OPTIONS).map((name) => [name, null]),
    );

    /**
     * ID of the per-test fixture page, so afterEach can clean it up (and its
     * featured-image attachment) even when the test itself failed partway
     * through — the same "always restore" discipline TOUCHED/previous apply
     * to options, extended to the throwaway post/attachment fixtures, which
     * are not site-global state but would otherwise leak into a later run.
     */
    let fixturePageId = null;

    test.beforeAll(() => {
      for (const [name, isValid] of Object.entries(FRONT_PAGE_OPTIONS)) {
        previousOptions[name] = readOption(name, isValid);
      }
    });

    test.afterEach(() => {
      for (const name of Object.keys(FRONT_PAGE_OPTIONS)) {
        restoreOption(name, previousOptions[name]);
      }

      if (null !== fixturePageId) {
        const attachmentIds = wp(
          `post list --post_parent=${fixturePageId} --post_type=attachment --field=ID --format=ids`,
        )
          .split(/\s+/)
          .filter(Boolean);

        for (const id of attachmentIds) {
          wp(`post delete ${id} --force`);
        }
        wp(`post delete ${fixturePageId} --force`);
        fixturePageId = null;
      }
    });

    // A minimal valid 1x1 transparent PNG, base64 — a fixture CONSTANT, not
    // a database value, so embedding it directly does not run into the
    // "never interpolate an unvalidated DB value" rule this file's header
    // states for theme_mods/options.
    const FIXTURE_PNG_BASE64 =
      'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

    test('a static front page with a featured image renders one h1 and no duplicate entry markup', async ({
      page,
    }) => {
      // This fixture makes ~7 sequential `wp-env run cli` calls before the
      // first navigation — each one is a real `docker exec`, measured at
      // 4-8s apiece on this machine (see `wp option get` above) — so the
      // default 30s test timeout was measured to fail on setup alone, never
      // reaching the assertions. Not a retry-until-it-passes workaround: the
      // page snapshot at the 30s mark already showed the correct render.
      test.setTimeout(120_000);

      fixturePageId = wp(
        'post create --post_type=page --post_title="E2E Static Front" --post_name=e2e-static-front --post_status=publish --porcelain',
      );

      // wp media import needs a real file inside the CONTAINER's filesystem
      // — sideloading over HTTP or reaching for a host path would both add
      // a network/mount dependency this fixture does not need.
      wp(
        `eval "file_put_contents( '/tmp/wtb-e2e-front.png', base64_decode( '${FIXTURE_PNG_BASE64}' ) );"`,
      );
      wp(
        `media import /tmp/wtb-e2e-front.png --post_id=${fixturePageId} --featured_image --title="E2E Featured Image" --porcelain`,
      );

      wp('option update show_on_front page');
      wp(`option update page_on_front ${fixturePageId}`);

      await page.goto('/');

      // #37's exact ask: one <h1>, and neither of content.php's own
      // (hide_entry_head-suppressed) entry-head elements — the featured
      // image and the page title already render once, inside the hero.
      await expect(page.locator('h1')).toHaveCount(1);
      await expect(page.locator('.wtb-entry-thumbnail')).toHaveCount(0);
      await expect(page.locator('.wtb-entry-title')).toHaveCount(0);
    });
  });
});
