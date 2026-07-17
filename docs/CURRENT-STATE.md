# Current State — Woodev Base

> Updated: 17.07.2026 (s1)

## Phase status

| Milestone | Status | Notes |
|---|---|---|
| Design & decisions | ✅ Done | Spec approved, ADR-001…006 recorded |
| M0 — Bootstrap | ⬜ Not started | Repo/docs scaffold done in s1; tooling (Vite, wp-env, CI, lint/test harness) pending |
| M1 — Core theme | ⬜ Not started | |
| M2 — WooCommerce layer | ⬜ Not started | |
| M3 — Public release prep | ⬜ Not started | |

## Known bugs

None — no code yet.

## Open items

- Pin concrete WP floor number in `style.css` at M0 (floating "latest 3 majors" policy, ADR-003; plan has the compute step).
- ~~Basecoat pin~~ — resolved s1: exact `1.0.2`. ~~M1 inventory~~ — resolved s1: spec §7 (incl. 8 Basecoat style packs as Customizer option, optional right sidebar, scheme switcher in header).
- ~~Fonts/icons selection~~ — resolved s1: system font stack, Lucide icons (ISC).
- ~~Decide on installing a vetted WordPress skill pack~~ — done in s1: 8 skills from jorgerosal/wordpress-skills installed to `.claude/skills/` with project-override patches.

## Next actions

1. M0: implementation plan (superpowers:writing-plans) → tooling skeleton: theme headers, autoloader, Vite config, wp-env, phpcs.xml.dist, PHPStan, PHPUnit/Brain Monkey, Vitest, Playwright, GitHub Actions CI.
2. Verify theme activates cleanly on wp-env with empty index.

## Last session

s1 (17.07.2026): brainstorm, decisions fixed, project scaffolding created. See SESSION-LOG.
