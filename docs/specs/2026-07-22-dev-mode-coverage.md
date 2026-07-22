# Dev-mode coverage — design

> Written s7, 22.07.2026. Closes the "dev mode has no integration/e2e coverage" item
> deferred since s3.

## Problem

`Assets::enqueue()` branches on the `WOODEV_BASE_DEV` constant. The production
branch resolves the Vite manifest and enqueues hashed files from `assets/dist`;
the dev branch enqueues three script modules straight from the Vite dev server
at `http://localhost:5173`.

Only the second branch is unverified in anything but a mock. `tests/php/Unit/AssetsTest.php`
pins URL construction with `wp_enqueue_script_module()` mocked away, so it proves
the strings we would pass to WordPress and nothing about what WordPress then does
with them.

Two defect classes therefore have no guard:

1. **The branch is never exercised in a real WordPress.** Nothing proves the dev
   branch is actually taken when the constant is set, that WordPress prints the
   three modules, or that no `assets/dist` URL leaks into the same page.
2. **Styles can fail to reach the browser while every PHP test stays green.**
   This is not hypothetical: PR #1 shipped an `enqueue_dev()` that asked the dev
   server only for `@vite/client` and `app.js`. The CSS entry is a separate
   Rollup input that `app.js` never imports, so the page was a 200 with working
   JavaScript and no Tailwind, Basecoat or tokens at all
   (`docs/gotchas/vite-css-entry-is-not-imported-by-the-js-entry.md`, s3). It was
   caught by the Codex critic and confirmed by hand. Nothing would catch its
   recurrence today.

That gotcha closes with "dev mode has no e2e coverage **by decision** (s3)" —
the cost of a second environment plus a live dev server was judged out of
proportion at the time. This spec reverses that call for one spec's worth of
coverage, and the gotcha is updated as part of the work rather than left to
contradict the tree.

M1-04 added a known dev-mode limitation of its own (Customizer overrides lose to
the Vite-injected pack CSS on source order), which raises the value of having a
dev path that is exercised rather than assumed.

## Scope

Two additions, one per defect class above.

**A — integration.** Prove the dev branch produces the right markup in a real
WordPress.

**B — one e2e spec.** Prove a dev-mode page is actually styled in a browser.

Deliberately out of scope, with reasons:

- **Pinning the Customizer-override limitation in dev.** Documented in
  `InlineStyles`' docblock and in `docs/CURRENT-STATE.md`; production is
  unaffected and is already mutation-pinned. A test would pin a known-wrong
  behaviour we intend to leave alone.
- **A dev-mode run of the existing e2e suite.** Dev mode is a developer tool, not
  a shipped path. The maintenance cost of a parallel suite is not proportional to
  the risk.
- **Dev e2e in the default `npm run e2e` gate.** It needs a live Vite dev server
  and a third wp-env environment; making the main gate depend on both trades a
  real flakiness surface for coverage of a non-production path.

## Part A — integration

### Why a second PHPUnit config and not a wp-env constant

`WOODEV_BASE_DEV` cannot be undefined once defined, so a single PHPUnit process
cannot cover both branches. The constant must therefore be set for a whole
process, and there are two ways to do it.

Setting it through wp-env's `config` key is the wrong one, twice over: wp-env
writes `config` constants into **both** the dev and the tests environment, and it
appends them to `wp-config.php` without ever removing them again — deleting the
config file and restarting leaves the constant in place, and `--update` does not
help either (`docs/gotchas/wp-env-config-constants-persist.md`). The integration
environment would silently stay in dev mode for every later run.

A bootstrap that defines the constant scopes it to exactly one PHPUnit process
and leaves no residue.

### Shape

- `tests/integration/bootstrap-dev.php` — defines `WOODEV_BASE_DEV` as `true`,
  then requires the existing `bootstrap.php`. The define must precede the WP
  bootstrap so the theme sees it at `wp_enqueue_scripts` time.
- `tests/integration/phpunit.dev.xml.dist` — same 9.6 schema and flags as
  `phpunit.xml.dist`, `bootstrap="bootstrap-dev.php"`, and a testsuite pointing
  at a directory that holds only the dev-mode tests.
- `tests/integration/Integration/DevMode/AssetsDevModeTest.php` — the dev
  assertions.
- An npm script `test:integration:dev` mirroring `test:integration`, differing
  only in `-c phpunit.dev.xml.dist`.

Both configs run against the same `.wp-env.test.json` environment; nothing about
the environment changes.

### What the tests assert

Dev suite, on a real front-end request with the theme active — capture
`do_action( 'wp_head' )` output and assert:

- all three dev-server URLs are present as script modules: `/@vite/client`,
  `/src/css/packs/vega.css`, `/src/js/app.js`;
- **no** `assets/dist` URL appears anywhere in the same output.

Production suite (the existing `phpunit.xml.dist` run) gets the mirror-image
test: `assets/dist` present, `localhost:5173` absent. The pair is the point —
either assertion alone passes vacuously in the wrong environment, and having both
proves the configs really do boot different modes rather than the same one twice.

The production side needs a built `assets/dist` to assert against. If the
manifest is absent the theme correctly enqueues nothing, so that test must skip
with an explicit message rather than fail — a fresh checkout has no `dist`.

## Part B — the dev e2e spec

### Environment

`.wp-env.dev-mode.json`: a third environment, own port `8892`,
`"testsEnvironment": false`, and `"config": { "WOODEV_BASE_DEV": true }`. Here the
constant's persistence is the desired behaviour, not a trap — the environment
exists to be permanently in dev mode, and `:8888` and `:8890` stay clean.

### Runner

`playwright.dev.config.mjs`: `testDir: tests/e2e-dev`, `baseURL: http://localhost:8892`,
and a `webServer` entry that runs `npm run dev` and waits on `http://localhost:5173`.
Playwright owns the dev server's lifecycle so a killed run cannot leave one
behind. The wp-env environment is started separately (`npm run wp:dev-mode:start`),
matching how the existing e2e suite treats `:8888`.

`npm run e2e:dev` invokes it. `npm run e2e` is untouched.

### The spec

One file, one meaningful assertion: load `/` and read **computed** style, not
markup. Two probes, because they fail independently:

- a pack token resolves on `:root` (e.g. `getComputedStyle(document.documentElement).getPropertyValue('--primary')`
  is non-empty) — proves the pack CSS module executed and injected its styles;
- a Basecoat component has its pack geometry (the `.btn` in `searchform.php`,
  whose vega height is a known concrete value) — proves the component layer
  arrived too, not just the tokens.

Asserting the presence of a `<script>` tag would be worthless here: Part A
already does that, and the s5 defect had the tag present and the styles missing.

## Testing and verification

Every guard is mutation-tested before it counts as written, per AGENTS.md:

| Guard | Mutation | Expected |
|---|---|---|
| Dev integration test | remove the `WOODEV_BASE_DEV` branch from `Assets::enqueue()` | red |
| Production integration test | force the dev branch unconditionally | red |
| Dev e2e spec | drop the pack-CSS enqueue from `enqueue_dev()` | red |

TDD order per part: write the test, watch it fail for the right reason, then make
it pass. Parts A and B are independent and can be built in either order.

## Risks

- **Vite dev server startup latency** under Playwright's `webServer`. Mitigated by
  a generous `timeout` and by `reuseExistingServer` locally.
- **Cross-origin module loading** from `:5173` into a page served from `:8892`.
  Vite's dev server sends permissive CORS headers by default and the same
  arrangement is already proven by hand at `:8888`; if it turns out otherwise,
  Vite's `server.cors` is the lever.
- **A third wp-env environment costs disk and start time.** Accepted: it is
  started only for `e2e:dev`, and isolation is what keeps the constant out of the
  other two environments.

## Docs to update when this lands

- `docs/gotchas/vite-css-entry-is-not-imported-by-the-js-entry.md` — its "no e2e
  coverage by decision" paragraph becomes false.
- `docs/CURRENT-STATE.md` — the deferred item is closed.

## Related

- `docs/gotchas/wp-env-config-constants-persist.md` — why the constant is not set through wp-env
- `docs/gotchas/vite-css-entry-is-not-imported-by-the-js-entry.md` — the defect Part B exists to catch
- `docs/gotchas/playwright-browser-newpage-skips-config.md` — `{ page }` fixture only, also in the new spec
- `docs/CURRENT-STATE.md` — the deferred item this closes
