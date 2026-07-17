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

Two open, both found by the Codex critic on PR #1 and both verified real. **Nothing auto-fixed — awaiting Maksim's triage.** Neither affects the production path; verified on wp-env: WP 7.0.1 / PHP 8.1.34, theme activates, front page 200 with dist assets, zero PHP notices.

1. **Dev mode ships no CSS** (`inc/Assets.php:60-61`). `enqueue_dev()` enqueues only the Vite client + JS entry, and `app.js` never imports `app.css` (Vite declares CSS as a separate Rollup entry), so `WOODEV_BASE_DEV` renders with no Tailwind/Basecoat/tokens. Nothing tests dev mode — decide whether it gets e2e coverage or stays a documented manual path.
2. **Missing/corrupt manifest emits a PHP warning** (`inc/Assets.php:73-76`) — a regression from `c8f440b`. WP core's `wp_json_file_decode()` calls `wp_trigger_error()` before returning null, so the "enqueue nothing, not a fatal" contract is currently false. Reachable on a fresh checkout before `npm run build`. Fix: restore the `is_file()` guard ahead of the decode.

## Open items

- **PR #1 needs Maksim's triage + merge** — Codex findings are verbatim in the [PR comment](https://github.com/kalbac/woodev-base-theme/pull/1#issuecomment-4998876196); nothing was auto-fixed. Fixes must be re-reviewed by Codex (AGENTS.md: never self-certify fixes made in response to a review).
- Codex plugin tooling: its review job hung 15 min on a `supermemory/recall` MCP call. `codex review --base main -c 'mcp_servers={}'` works. Don't trust a "running" Codex job without checking its log.
- wp-env emits a deprecation warning: it starts dev **and** tests environments by default, and `testsEnvironment`/`env`/`testsPort` are deprecated in favour of a separate config file. M0 doesn't use the tests env; M1's integration harness will — decide the config shape then.
- ~~Pin concrete WP floor~~ — resolved s2: **6.8** (`Requires at least`), tested up to **7.0**, computed from the real release list per ADR-003 (latest 3 majors = 7.0, 6.9, 6.8). Re-check each release; note the plan's `min-2` one-liner is broken now that WP is 7.0.
- ~~Basecoat pin~~ — resolved s1: exact `1.0.2`. ~~M1 inventory~~ — resolved s1: spec §7 (incl. 8 Basecoat style packs as Customizer option, optional right sidebar, scheme switcher in header).
- ~~Fonts/icons selection~~ — resolved s1: system font stack, Lucide icons (ISC).
- ~~Decide on installing a vetted WordPress skill pack~~ — done in s1: 8 skills from jorgerosal/wordpress-skills installed to `.claude/skills/` with project-override patches.

## Next actions

1. **M1 kickoff: WP integration-test harness** (`WP_UnitTestCase` via wp-env/wp-phpunit) — deliberately deferred from M0; research current wp-env docs first rather than guessing the wiring.
2. M1 planning: component/template inventory and Customizer v1 scope per spec §7.

## Last session

s2 (17.07.2026): M0 executed end-to-end; PR #1 open with CI green. Four plan deviations forced by reality — two of them silent failures (Basecoat's JS entry is a CSS export; Basecoat's un-layered tokens beat our layered ones). See SESSION-LOG and the two new gotchas.
