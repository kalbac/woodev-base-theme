// playwright.config.mjs
import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: 'tests/e2e',
  globalSetup: './tests/e2e/global-setup.mjs',
  use: {
    baseURL: 'http://localhost:8888',
  },
  /*
   * Two projects, and the second waits for the first.
   *
   * `theme-mods.spec.mjs` mutates SITE-GLOBAL state — theme_mods, and since
   * #37 the `show_on_front`/`page_on_front` options too. Its own header rule
   * ("one file owns every mutation, restore after each test") only buys
   * isolation from OTHER TESTS IN THAT FILE: Playwright's unit of parallelism
   * is the FILE, and with no `workers` setting it runs up to ceil(cores/2) of
   * them at once. So while that file switches the site to a static front
   * page, a worker running smoke/templates/components/navigation can be
   * loading `/` and asserting on the posts loop that is no longer there.
   *
   * Nothing had gone red from it yet, which is luck rather than isolation —
   * the theme_mod mutations were always exposed the same way, and the s5
   * focus-trap failure that only appeared on merged `main` is what this class
   * of race looks like when it finally lands. Switching the front page makes
   * it materially worse, so it is fixed here rather than left to timing.
   *
   * `dependencies` is what serialises the two groups; `workers: 1` would too,
   * but it would also serialise the 51 tests that have no reason to wait for
   * each other, and the suite is already ~12 minutes. Everything inside each
   * group still runs in parallel.
   *
   * Note the consequence, because a skipped job reads like a passing one
   * (docs/gotchas/qa-gates-cover-less-than-they-claim.md): if `parallel`
   * fails, `site-global` does not run at all. Read the counts, not the exit
   * code.
   */
  projects: [
    {
      name: 'parallel',
      testIgnore: '**/theme-mods.spec.mjs',
    },
    {
      name: 'site-global',
      testMatch: '**/theme-mods.spec.mjs',
      dependencies: ['parallel'],
    },
  ],
  reporter: [['list']],
});
