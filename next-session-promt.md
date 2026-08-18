# Next Session Handoff — Woodev Base (s23, 19.08.2026)

You are continuing Woodev Base, a universal WordPress theme built with Basecoat
UI, Tailwind CSS v4, Alpine.js, classic templates plus `theme.json`, and an
optional WooCommerce layer.

## Read first

1. `CLAUDE.md` — the project entry point.
2. `AGENTS.md` — engineering canon, language and test rules.
3. `docs/CURRENT-STATE.md` — authoritative status.
4. `docs/plans/2026-07-26-m3-release-prep.md` — remaining release work.
5. `tests/e2e-woo/global-setup.mjs` and the #48 notes in
   `docs/CURRENT-STATE.md` before changing Woo e2e seeding.

Talk to Maksim in Russian, informal «ты». Keep code, comments, docs and commits
in English. Serena is preferred when available; it was unavailable in s21.

## Working tree

- `main` started at `2f6530f`. s23 is saved as a local WIP commit on `main`; push it before
  beginning more work. There is no branch to merge.
- #40, #43, M3 tags/languages, and #49 are already merged. Do not redo them.

## What happened in s23

- `npm run seed:showcase` seeds a clean, human-readable Woo demo on `:8891` through one
  mapped `wp eval-file` call. It activates the theme/Woo, disables Woo Coming Soon, creates
  pages/menu/posts/categories/products/images/theme mods and removes `wtb-*` e2e leftovers.
- `npm run probe:showcase` browser-checks and stores PNGs for home, catalogue, product,
  Journal, search, 404 and sidebar. All real routes passed after the Coming Soon fix.
- `npm run screenshot:wporg` produced a visually checked `woodev-base-theme/screenshot.png`
  at 1200×900 using the production build.
- #48 is NOT fixed. The new `scripts/wp-cli-batch-server.php` plus edits to
  `tests/e2e-woo/global-setup.mjs` are an unfinished persistent `wp eval-file` IPC experiment.
  Fresh `e2e:woo` runs never reached specs: two transport result-shape/race defects were fixed,
  then the sixth run timed out in setup. The server lacks reliable process teardown and stale
  processes can lock the shared request file on Windows.

## What shipped in s22

- `1ba4440`: #40 repair set and #43 content-template polish, both re-criticked.
- `6b2083d`: wp.org tags aligned in `style.css` and `readme.txt`.
- `cc424d3`: package ships `languages/index.php`, pinned by integration test.
- `5849510`: isolated WP 6.8 + PHP 8.1 integration CI job, version assertion,
  and production/dev suites; #49 closed.

## Verified in s23

- `seed:showcase`: succeeded in one `wp-env run cli` call (about 38 seconds).
- Browser/Playwright visual pass: home, catalogue, product, Journal, search, 404, sidebar;
  no horizontal overflow; screenshot inspected manually.
- `e2e:woo`: explicitly NOT verified. Every fresh run was red or timed out before specs.

## Verified in s22

- WP 6.8 / PHP 8.1: integration 83/286 (1 expected skip), dev integration 4/8.
- PHPCS: 127 files; Prettier clean.
- #43 gates: PHPCS, PHPStan L8, ESLint, Prettier, unit 518, integration 82,
  build and diff check passed. Browser/e2e remains unverified.

## Gate caveat

Do not claim full `e2e:woo` green. Its global setup is slow because it makes 39
separate `npx wp-env run cli` calls. All four `wp widget add` commands have
been independently proven to work (~14–16s each); a two-command `sh -c` batch
took 52s, so do not use that approach. Design a portable batch seed instead;
never depend on Docker-generated container names. Run wp-env and Playwright one
at a time.

## Next priorities

1. Finish or replace the #48 IPC batch experiment. Do not use Docker names. Add explicit
   server teardown and avoid shared fixed IPC paths; run full `e2e:woo` to completion.
2. Run QA/review for the showcase changes, then commit/push the verified screenshot.
3. Resolve R2 font provenance (#17), choose a release version, and run M3's
   full release battery.

## Key files

- `tests/e2e-woo/global-setup.mjs`
- `.wp-env.woo.json`, `playwright.woo.config.mjs`
- `.wp-env.wp68.json`, `.github/workflows/ci.yml`
- `docs/CURRENT-STATE.md`
- `docs/SESSION-LOG.md`
