# Current State — Woodev Base

> Updated: 19.07.2026 (s3)

## Phase status

| Milestone | Status | Notes |
|---|---|---|
| Design & decisions | ✅ Done | Spec approved, ADR-001…006 recorded |
| M0 — Bootstrap | ✅ Done | PR [#1](https://github.com/kalbac/woodev-base-theme/pull/1) merged s3 after both Codex P2s were fixed and re-reviewed |
| M1 — Core theme | 🟡 In progress | Integration harness landed s3 (`1020ca8`) on `feat/m1-integration-harness`. Templates/components per spec §7 not started |
| M2 — WooCommerce layer | ⬜ Not started | |
| M3 — Public release prep | ⬜ Not started | |

## Known bugs

**None open.** The s3 harness review surfaced a real one and it is fixed (`c6f3bb3`, `76b6c58`): `add_theme_support()` was asserted with a bare `->times( 4 )`, so the html5 feature list was covered **nowhere** — and the code comment claiming it was covered in the unit suite is what kept anyone from checking. Both the comment and the gotcha stated it as "mutation-verified in s2"; the phrase was inherited from a session summary and never re-run. Lesson recorded in `docs/gotchas/wp-test-suite-removes-html5-support.md` ("The second trap").

Both Codex findings on PR #1 were fixed in s3 (`e175958`, `9b0341f`), each reproduced against a real WP first rather than taken on the review's word, and each guarded by a test proven red before the fix. The re-review (mandatory: never self-certify fixes made in response to a review) passed with no P1/P2/P3 and an explicit merge approval.

## Deferred, tracked

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

1. ~~M1 kickoff: WP integration-test harness~~ — done s3. `npm run wp:test:start` → `test:integration:install` → `test:integration`; CI job `php-integration`. Mutation-verified (drop a support, rename the menu slug, point the bootstrap at another theme → red).
2. **Dev-mode integration coverage** — the deferred item above, now unblocked: the `.wp-env.<variant>.json` mechanism it needed exists and is proven.
3. M1 planning: component/template inventory and Customizer v1 scope per spec §7.

## Last session

s3 (19.07.2026): PR #1's two P2s fixed, re-reviewed, merged. M1 integration harness built and mutation-verified. Its own review found a real coverage hole hidden behind a false comment — see Known bugs. Also fixed: `composer phpcs` was unrunnable on Windows (EOL).
