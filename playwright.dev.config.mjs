// playwright.dev.config.mjs
//
// The dev-mode e2e run. Separate from playwright.config.mjs on purpose:
//   - it targets the permanently-dev wp-env environment on :8892, not :8888;
//   - it owns a live Vite dev server, which the main gate must not depend on;
//   - its globalSetup only activates the theme. tests/e2e/global-setup.mjs seeds
//     through `npx wp-env run cli` with the DEFAULT config, i.e. :8888 — reusing
//     it here would seed the wrong site, and this spec needs no fixtures: it
//     asserts computed style on the front page.
import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: 'tests/e2e-dev',
  globalSetup: './tests/e2e-dev/global-setup.mjs',
  use: {
    baseURL: 'http://localhost:8892',
  },
  reporter: [['list']],
  webServer: {
    command: 'npm run dev',
    // @vite/client is served by the dev server itself and needs no build.
    url: 'http://localhost:5173/@vite/client',
    reuseExistingServer: !process.env.CI,
    timeout: 120_000,
  },
});
