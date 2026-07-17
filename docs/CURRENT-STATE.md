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

None known. Verified on wp-env: WP 7.0.1 / PHP 8.1.34, theme activates, front page 200 with dist assets, zero PHP notices.

## Open items

- **PR #1 needs Maksim's review + merge** — Codex findings are presented in the PR/session log; nothing was auto-fixed.
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
