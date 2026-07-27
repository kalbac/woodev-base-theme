// ADR-010 / #26 — WordPress core's own Button block wears the theme identity.
//
// The integration suite already proves what `wp_get_global_stylesheet()` emits. This
// proves the only thing that actually matters to a visitor: what the browser computes
// after every stylesheet in the page has had its say. Those are different claims — a
// correct rule that loses a cascade race computes as the loser, which is exactly how
// s12's woo.css defects survived a passing PHP suite.
//
// Both schemes are asserted. M2b shipped a spec that called itself both-scheme and read
// only dark values; a light-only regression would have passed it.
import { expect, test } from '@playwright/test';

/** Core's default button background, the thing #26 was about. */
const CORE_DEFAULT_BG = 'rgb(50, 55, 60)'; // #32373c

/**
 * Alpha of a computed colour string. Compared numerically rather than against the literal
 * `rgba(0, 0, 0, 0)`: `rgba(255, 0, 0, 0)` is equally invisible and equally wrong, and a
 * string comparison waves it through. Raised in the third critic round.
 */
const alphaOf = (computed) => {
  const match = /^rgba?\([^)]*?(?:,\s*|\/\s*)([\d.]+)\s*\)$/.exec(computed);
  return match ? Number(match[1]) : 1;
};

/**
 * Resolve a token to the value the browser computes for it, in whatever scheme the
 * document is currently in. Comparing against this — rather than against "not core's
 * grey" — is what makes the assertion about the TOKEN rather than about some colour.
 */
const resolveToken = (button, token) =>
  button.evaluate((el, name) => {
    const raw = getComputedStyle(document.documentElement).getPropertyValue(name).trim();

    // The probe goes in the BUTTON'S OWN PARENT, not in <body>. A value that resolves
    // against inherited context — `currentColor` is the obvious one — computes
    // differently in the two places, and the earlier version measured the wrong one.
    // No token spells itself that way today (the generator resolves every token to an
    // oklch literal and throws on anything else, so the trigger is impossible by
    // construction) but a probe measuring in the wrong context is fragile regardless of
    // whether it is currently wrong. Third critic round.
    const probe = document.createElement('div');
    probe.style.backgroundColor = raw;
    el.parentElement.append(probe);
    const value = getComputedStyle(probe).backgroundColor;
    probe.remove();

    return { raw, computed: value };
  }, token);

test('a core Button block is painted by the theme, not by core, in both schemes', async ({
  page,
}) => {
  await page.goto('/core-button/');

  const button = page.locator('a.wp-element-button').first();
  await expect(button).toBeVisible();

  const read = () =>
    button.evaluate((el) => {
      const cs = getComputedStyle(el);
      return { bg: cs.backgroundColor, fg: cs.color };
    });

  // Both schemes are checked against the LIVE TOKEN, not against a list of colours the
  // button must avoid. A "not core's grey, not transparent, differs from light" trio
  // passes happily against a hardcoded `.dark .wp-element-button{background:#123456}`,
  // which is a second copy of the identity and exactly what #25 is about.
  for (const scheme of ['light', 'dark']) {
    if (scheme === 'dark') {
      // Runtime toggle rather than a theme_mod another worker may own.
      await page.evaluate(() => document.documentElement.classList.add('dark'));
    }

    const computed = await read();
    const primary = await resolveToken(button, '--primary');
    const primaryForeground = await resolveToken(button, '--primary-foreground');

    expect(primary.raw, `--primary is undefined in ${scheme}`).not.toBe('');

    // Following the token is necessary but not sufficient: a button faithfully
    // following `--primary: transparent` computes rgba(0, 0, 0, 0), matches its token
    // exactly, and is invisible on screen. The first version of this fix dropped this
    // guard while adding the token comparison — caught by the re-critic. Both claims
    // have to hold at once.
    expect(alphaOf(computed.bg), `background is invisible in ${scheme}`).toBeGreaterThan(0);
    expect(alphaOf(computed.fg), `text is invisible in ${scheme}`).toBeGreaterThan(0);

    // Both guards above are satisfied by an opaque button whose text is the same colour
    // as its background — visible, and unreadable. The real defence is the build-time
    // contrast gate (assertAccessiblePalettes, every palette in both schemes, throws
    // below AA); this is the cheap sanity check that the pair did not collapse here.
    expect(computed.fg, `text is the background colour in ${scheme}`).not.toBe(computed.bg);

    expect(computed.bg, `background in ${scheme}`).toBe(primary.computed);
    expect(computed.fg, `text colour in ${scheme}`).toBe(primaryForeground.computed);

    // Belt to those braces: the defect this change exists to fix, stated directly.
    expect(computed.bg, `core's default survived in ${scheme}`).not.toBe(CORE_DEFAULT_BG);
  }
});

test('the two schemes really do resolve to different colours', async ({ page }) => {
  // Guards the loop above against a degenerate pass: if `.dark` did nothing, both
  // iterations would compare the same value against the same token and agree.
  await page.goto('/core-button/');

  const button = page.locator('a.wp-element-button').first();
  await expect(button).toBeVisible();

  const bg = () => button.evaluate((el) => getComputedStyle(el).backgroundColor);

  const light = await bg();
  await page.evaluate(() => document.documentElement.classList.add('dark'));
  const dark = await bg();

  expect(dark).not.toBe(light);
});

test('the button resolves its colours from the identity tokens, not from a copy', async ({
  page,
}) => {
  await page.goto('/core-button/');

  const button = page.locator('a.wp-element-button').first();
  await expect(button).toBeVisible();

  // Read the live token and the computed background in the same frame, so this cannot
  // pass by both happening to be some fixed value measured at different times.
  const { tokenPrimary, buttonBg } = await button.evaluate((el) => ({
    tokenPrimary: getComputedStyle(document.documentElement).getPropertyValue('--primary').trim(),
    buttonBg: getComputedStyle(el).backgroundColor,
  }));

  expect(tokenPrimary).not.toBe('');

  // Both describe the same colour; the browser normalises the computed value, so compare
  // by resolving the token through a throwaway element rather than by string equality.
  const resolvedToken = await page.evaluate((token) => {
    const probe = document.createElement('div');
    probe.style.backgroundColor = token;
    document.body.append(probe);
    const value = getComputedStyle(probe).backgroundColor;
    probe.remove();
    return value;
  }, tokenPrimary);

  expect(buttonBg).toBe(resolvedToken);
});
