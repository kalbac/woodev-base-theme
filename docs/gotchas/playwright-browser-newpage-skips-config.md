# A Playwright test read a false light value in dark mode

> Discovered s4 (20.07.2026). A dark-scheme e2e assertion passed in isolation but failed under the full suite — the theme's dark mode was fine; the test was wrong.

## The symptom

`templates.spec.mjs`'s "dark scheme actually changes the applied tokens" test read the same background (`oklch(1 0 0)`, light) for both light and dark, so `expect(dark).not.toBe(light)` failed — but **only when the whole suite ran**; `--grep`'d alone it passed. The theme was verified dark-correct in a browser first (`.dark` on `<html>` → body background `oklch(0.145 0 0)`), so the defect was in the test.

## Two causes, both in how the test forced dark

The failing test used:

```js
const darkPage = await browser.newPage();
await darkPage.addInitScript(() => document.documentElement.classList.add('dark'));
await darkPage.goto('/');
```

1. **`browser.newPage()` does NOT inherit the project `use` config** (baseURL, viewport, colorScheme, …). Every other spec here uses the `{ page }` fixture, which does. A page created off the raw `browser` is a different, unconfigured context.
2. **`addInitScript` + first-paint timing** made the class application race the computed-style read under load.

## The fix

Use the `{ page }` fixture and toggle at runtime — the token cascade is a pure CSS custom-property switch, so flipping the class after load reflects immediately:

```js
test('…', async ({ page }) => {
  await page.goto('/');
  const light = await page.evaluate(() => getComputedStyle(document.body).backgroundColor);
  await page.evaluate(() => document.documentElement.classList.add('dark'));
  const dark = await page.evaluate(() => getComputedStyle(document.body).backgroundColor);
  expect(dark).not.toBe(light);
});
```

## The rules

- In `@playwright/test`, prefer the **`{ page }` / `{ context }` fixtures**. `browser.newPage()` / `browser.newContext()` skip the config and are a silent source of "works alone, fails in suite".
- When a test asserts a *visual* state (dark tokens, focus styles), a **runtime toggle read on one page** is more reliable than `addInitScript`, and it matches what you can reproduce by hand in the browser.
- "Passes in isolation, fails in the full run" ⇒ suspect test isolation/config, not the code under test. Confirm the feature by hand before touching it. (This session: the dark mode was proven correct in-browser before the test was rewritten.)

## Related

- [[qa-gates-cover-less-than-they-claim]] — a green-in-isolation test is not a covered behaviour.
