# Current State — Woodev Base

> Updated: 17.07.2026 (s2)

## Phase status

| Milestone | Status | Notes |
|---|---|---|
| Design & decisions | ✅ Done | Spec approved, ADR-001…006 recorded |
| M0 — Bootstrap | 🟡 In review | All 16 tasks done; PR [#1](https://github.com/kalbac/woodev-base-theme/pull/1) open (s2). Awaiting Maksim's review/merge |
| M1 — Core theme | ⬜ Not started | |
| M2 — WooCommerce layer | ⬜ Not started | |
| M3 — Public release prep | ⬜ Not started | |

## Known bugs

**None open.** Both Codex findings on PR #1 were fixed in s3 (`e175958`, `9b0341f`), each reproduced against a real WP first rather than taken on the review's word, and each guarded by a test proven red before the fix. The re-review (mandatory: never self-certify fixes made in response to a review) passed with no P1/P2/P3 and an explicit merge approval.

## Deferred, tracked

- **Dev mode has no integration/e2e coverage** — Codex P2 from the s3 re-review, accepted as real, **deferred to M1** (not waved away). The dev path is pinned only by a unit test with a mocked `wp_enqueue_script_module()`; canon wants WP-API behavior covered at integration level and "renders with styles" proven in a browser. It needs a second wp-env environment carrying `WOODEV_BASE_DEV` — the same `.wp-env.<variant>.json` mechanism M1's integration harness is building and validating, so doing it earlier would build that mechanism twice. **Do it as the first follow-up once the harness lands.** Manual recipe meanwhile: `docs/gotchas/wp-env-config-constants-persist.md`.

## Open items

- Codex tooling: the plugin's job and `-c 'mcp_servers={}'` both fail here (the s2 note that the flag works is **wrong** — MCP still loads and the run dies on supermemory's HTTP 403). Working recipe, found s3: a clean `CODEX_HOME` holding only `auth.json` + a minimal `config.toml`, run in the **foreground**, prompt inline and under ~15 KB. See `docs/gotchas/codex-cli-dies-silently.md` — every failure mode exits 0 and looks like success.
- ~~wp-env deprecation / config shape~~ — resolved s3 by research (`.wp-env.<variant>.json` + `--config`); see the M1 harness plan. **`tests-cli` goes away**: the test site becomes the `development` env of a second config file, and that file is now the isolation boundary — the core suite drops and reinstalls the DB of whatever env you point it at.
- **🔴 PHPUnit 10.5 cannot run the WP core test suite** (research s3). WP 6.8–7.0 are PHPUnit-9-only (core requires `yoast/phpunit-polyfills ^1.1` = PHPUnit ≤9; polyfills 3.x/4.x deliberately skip 10). Our root pin is `^10.5` and can't move up — PHPUnit 11 needs PHP ≥8.2 vs our 8.1 floor (ADR-003). **Decision (s3): a separate Composer root at `tests/integration/`** with its own `vendor/` — additive, keeps the unit suite on 10.5 and its `failOnNotice` (absent from the 9.6 schema). Downgrading the root to 9.6 would regress passing code.
- ~~Pin concrete WP floor~~ — resolved s2: **6.8** (`Requires at least`), tested up to **7.0**, computed from the real release list per ADR-003 (latest 3 majors = 7.0, 6.9, 6.8). Re-check each release; note the plan's `min-2` one-liner is broken now that WP is 7.0.
- ~~Basecoat pin~~ — resolved s1: exact `1.0.2`. ~~M1 inventory~~ — resolved s1: spec §7 (incl. 8 Basecoat style packs as Customizer option, optional right sidebar, scheme switcher in header).
- ~~Fonts/icons selection~~ — resolved s1: system font stack, Lucide icons (ISC).
- ~~Decide on installing a vetted WordPress skill pack~~ — done in s1: 8 skills from jorgerosal/wordpress-skills installed to `.claude/skills/` with project-override patches.

## Next actions

1. **M1 kickoff: WP integration-test harness** (`WP_UnitTestCase` via wp-env/wp-phpunit) — deliberately deferred from M0; research current wp-env docs first rather than guessing the wiring.
2. M1 planning: component/template inventory and Customizer v1 scope per spec §7.

## Last session

s2 (17.07.2026): M0 executed end-to-end; PR #1 open with CI green. Four plan deviations forced by reality — two of them silent failures (Basecoat's JS entry is a CSS export; Basecoat's un-layered tokens beat our layered ones). See SESSION-LOG and the two new gotchas.
