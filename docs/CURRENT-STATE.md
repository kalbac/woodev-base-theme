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

1. Execute `docs/plans/2026-07-17-m0-bootstrap.md` via superpowers:subagent-driven-development (kickoff prompt: `next-session-promt.md`).
2. After M0 merge: M1 kickoff — WP integration-test harness (research current wp-env docs first), then M1 plan per spec §7 inventory.

## Last session

s1 (17.07.2026): all decisions fixed (ADRs, Customizer contracts, M1 inventory), skills installed, repo published, M0 plan ready. See SESSION-LOG.
