// playwright.woo.config.mjs
//
// The WooCommerce e2e run. Separate from playwright.config.mjs on purpose:
//   - it targets the isolated Woo wp-env environment on :8891, not :8888;
//   - it exists so the base e2e (playwright.config.mjs / :8888) stays Woo-free —
//     the base theme must remain fully useful with WooCommerce absent (spec §8),
//     and testing the two together in one env would hide any regression that
//     depends on Woo being active or not;
//   - its globalSetup activates the theme + Woo, runs Woo's install, and seeds
//     the demo store. tests/e2e/global-setup.mjs seeds through the DEFAULT
//     config, i.e. :8888 — reusing it here would seed the wrong site.
//
// Uses the `{ page }` fixture only — no `browser.newPage()` in any Woo spec.
// See docs/gotchas/playwright-browser-newpage-skips-config.md: a raw
// browser.newPage() ignores the project `use` config (baseURL, viewport, …)
// and is a silent source of "works alone, fails in suite" flakes.
//
// No `webServer` here: production assets, not dev. If a Vite build is stale,
// run `npm run build` before `npm run e2e:woo`.
import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: 'tests/e2e-woo',
  globalSetup: './tests/e2e-woo/global-setup.mjs',
  use: {
    baseURL: 'http://localhost:8891',
  },
  reporter: [['list']],
});
