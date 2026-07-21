# Current State — Woodev Base

> Updated: 20.07.2026 (s4)

## Phase status

| Milestone | Status | Notes |
|---|---|---|
| Design & decisions | ✅ Done | Spec approved, ADR-001…006 recorded |
| M0 — Bootstrap | ✅ Done | PR [#1](https://github.com/kalbac/woodev-base-theme/pull/1) merged s3 after both Codex P2s were fixed and re-reviewed |
| M1 — Core theme | 🟡 In progress | Split into 5 plans (see below). Harness (PR #2), icon helper (PR #3, `96df1db`) and **templates M1-02 (PR [#4](https://github.com/kalbac/woodev-base-theme/pull/4), `f3f5f0a`, s4)** done. M1-03/04/05 not started |
| M2 — WooCommerce layer | ⬜ Not started | |
| M3 — Public release prep | ⬜ Not started | |

## Known bugs

**None open.**

s4 caught and fixed two during M1-02 verification (both browser-verified, both now gotchas): `number_format_i18n()` mangled the copyright **year** into `© 2,026` (`docs/gotchas/number-format-i18n-mangles-years.md`), and a dark-mode e2e read a false light value — the theme's dark mode was fine, the test used `browser.newPage()` which skips the config (`docs/gotchas/playwright-browser-newpage-skips-config.md`). The M1-01 icon helper also had a real Codex-found P1 fixed before merge: `DOMDocument::loadXML('')` throws `ValueError` on an empty SVG, breaking the fail-closed contract (guarded + `try/finally`, mutation-pinned).

Earlier (s3): the html5-support coverage hole is fixed (`c6f3bb3`, `76b6c58`) — see `docs/gotchas/wp-test-suite-removes-html5-support.md`.

## Deferred, tracked

- **`Layout::has_sidebar()` is `! is_page()`, broader than spec §7's "blog/archive/single"** — Codex P1 on M1-02, reviewed and **kept for v1 with Maksim's sign-off** (he said merge). It also allows the sidebar on search (a results list — standard WordPress) and attachment views. The behaviour is unit-tested; tightening to a positive allow-list (`is_home() || is_archive() || is_search() || is_single()`) would rewrite Task 2's mocks. **Narrow in M1-04** if the Customizer work wants it — trivial one-liner + test update.
- **Dev mode has no integration/e2e coverage** — Codex P2 from the s3 re-review, accepted as real, **deferred to M1** (not waved away). The dev path is pinned only by a unit test with a mocked `wp_enqueue_script_module()`; canon wants WP-API behavior covered at integration level and "renders with styles" proven in a browser. It needs a second wp-env environment carrying `WOODEV_BASE_DEV` — the same `.wp-env.<variant>.json` mechanism M1's integration harness is building and validating, so doing it earlier would build that mechanism twice. **Do it as the first follow-up once the harness lands.** Manual recipe meanwhile: `docs/gotchas/wp-env-config-constants-persist.md`.

## Open items

- Codex tooling: the plugin's job and `-c 'mcp_servers={}'` both fail here (the s2 note that the flag works is **wrong** — MCP still loads and the run dies on supermemory's HTTP 403). Working recipe, found s3: a clean `CODEX_HOME` holding only `auth.json` + a minimal `config.toml`, run in the **foreground**, prompt inline and under ~15 KB. See `docs/gotchas/codex-cli-dies-silently.md` — every failure mode exits 0 and looks like success.
- ~~wp-env deprecation / config shape~~ — resolved s3 by research (`.wp-env.<variant>.json` + `--config`); see the M1 harness plan. **`tests-cli` goes away**: the test site becomes the `development` env of a second config file, and that file is now the isolation boundary — the core suite drops and reinstalls the DB of whatever env you point it at.
- ~~**PHPUnit 10.5 cannot run the WP core test suite**~~ — resolved s3, harness shipped. WP 6.8–7.0 are PHPUnit-9-only (core requires `yoast/phpunit-polyfills ^1.1` = PHPUnit ≤9; polyfills 3.x/4.x deliberately skip 10), and our root pin can't move up — PHPUnit 11 needs PHP ≥8.2 vs our 8.1 floor (ADR-003). **A separate Composer root at `tests/integration/`** with its own `vendor/`: additive, keeps the unit suite on 10.5 and its `failOnNotice` (absent from the 9.6 schema). Note the incompatibility itself is **documentation-backed, not empirically reproduced** — the one attempt to demonstrate it produced a `TestSuite::empty()` error that was really a 9.6-schema-XML-vs-10.5-parser mismatch, i.e. confounded. The fix works (green 9.6 suite); the impossibility proof was never cleanly run, and doesn't need to be.
- **Line endings**: `.gitattributes` now pins `eol=lf` in the working tree (`a557d36`). Without it, `core.autocrlf=true` on Windows made `composer phpcs` fail all 8 files on EOL alone while CI stayed green.
- ~~Pin concrete WP floor~~ — resolved s2: **6.8** (`Requires at least`), tested up to **7.0**, computed from the real release list per ADR-003 (latest 3 majors = 7.0, 6.9, 6.8). Re-check each release; note the plan's `min-2` one-liner is broken now that WP is 7.0.
- ~~Basecoat pin~~ — resolved s1: exact `1.0.2`. ~~M1 inventory~~ — resolved s1: spec §7 (incl. 8 Basecoat style packs as Customizer option, optional right sidebar, scheme switcher in header).
- ~~Fonts/icons selection~~ — resolved s1: system font stack, Lucide icons (ISC).
- ~~Decide on installing a vetted WordPress skill pack~~ — done in s1: 8 skills from jorgerosal/wordpress-skills installed to `.claude/skills/` with project-override patches.

## Next actions

**M1 is five plans, not one.** Spec §7's M1 is six independent subsystems; a single plan would bury task 60's dependency on task 5. Each plan leaves the theme working:

| # | Plan | State |
|---|---|---|
| M1-01 | Lucide icon helper | ✅ merged `96df1db` (s4); Codex critic passed 2 rounds |
| M1-02 | Templates & parts (§7: 7 templates, content parts, pagination, sidebar + widget areas, 2+2 header/footer variants) | ✅ merged `f3f5f0a` (s4); Codex critic 3 reviews + re-critic, all findings fixed |
| M1-03 | 8 Basecoat style-pack bundles + adapter | not written — **next** |
| M1-04 | Customizer v1 (§6) — writes the header/footer/sidebar `theme_mod`s M1-02 already reads; consider narrowing `has_sidebar` here | not written |
| M1-05 | Scheme switcher + no-FOUC head script — insert into the documented slot in both header variants (`template-parts/header/*.php`) | not written |

i18n is cross-cutting — a requirement in every task, with `.pot` generation deferred to M3.

1. **Write and execute M1-03** (8 Basecoat style-pack bundles + adapter) — next plan, written now that M1-02 has landed so it can build on the adapter/CSS patterns it introduced.
2. **Dev-mode integration coverage** — deferred item above, unblocked since s3: the `.wp-env.<variant>.json` mechanism it needs exists and is proven. Good small follow-up.
3. M1-04 (Customizer) and M1-05 (scheme switcher) follow; each written after the previous lands. M1-05 drops into the documented switcher slot already left in both header variants.

## Last session

s4 (20.07.2026): merged M1-01 icons (PR #3, after the mandatory Codex critic it had never had — one real P1 fixed) and all of M1-02 templates (PR #4, subagent-driven: 8 tasks, 3 Codex reviews + re-critic). Gate green: phpcs 40/40, phpstan L8, unit 80, integration 13, vitest 4, e2e 21. Two bugs caught in-browser during verification (year, dark-test). `has_sidebar` breadth kept for v1 with sign-off. See SESSION-LOG for detail.
