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
    // Always false, even locally: reuseExistingServer only checks that SOME
    // server answers the probe URL, not that it is THIS checkout's server —
    // a Vite left running from before a vite.config.mjs change (e.g. the CORS
    // fix), or from another worktree, answers the same @vite/client URL, so
    // `npm run dev` would never start and the spec would fail against a
    // foreign module graph while this checkout is perfectly fine.
    // vite.config.mjs sets strictPort: true, so with reuseExistingServer
    // false a foreign server already on :5173 now makes the run fail loudly
    // with a port conflict instead of silently reusing the wrong assets —
    // the desired outcome. If you see that conflict, stop whatever `npm run
    // dev` you have running elsewhere before `npm run e2e:dev`.
    reuseExistingServer: false,
    timeout: 120_000,
  },
});
