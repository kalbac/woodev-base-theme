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

  const light = await read();

  // The defect, stated as the assertion: core's grey must not be what renders.
  expect(light.bg).not.toBe(CORE_DEFAULT_BG);

  // …and it must be a real colour, not a var() that resolved to nothing. An
  // unresolvable custom property computes to `rgba(0, 0, 0, 0)`, which would also
  // satisfy the assertion above while looking completely broken on screen.
  expect(light.bg).not.toBe('rgba(0, 0, 0, 0)');
  expect(light.fg).not.toBe('rgba(0, 0, 0, 0)');

  // Same runtime `.dark` toggle the rest of this suite uses, rather than mutating
  // theme_mods another worker may own.
  await page.evaluate(() => document.documentElement.classList.add('dark'));
  const dark = await read();

  expect(dark.bg).not.toBe(CORE_DEFAULT_BG);
  expect(dark.bg).not.toBe('rgba(0, 0, 0, 0)');
  expect(dark.fg).not.toBe('rgba(0, 0, 0, 0)');

  // The point of the var() references: the button FOLLOWS the scheme. If theme.json
  // still carried a literal, these would be identical in both schemes — which is the
  // whole of #25 expressed as one comparison.
  expect(dark.bg).not.toBe(light.bg);
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
