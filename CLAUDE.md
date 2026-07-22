# CLAUDE.md — Woodev Base

Universal WordPress theme (hybrid: classic templates + theme.json) on Basecoat UI + Tailwind v4 + Alpine.js, with an optional WooCommerce layer. Free, publicly distributable.

## Session start

**Both files are MANDATORY reading before any work — no exceptions, including for "quick" tasks.**

1. **`AGENTS.md`** — authoritative engineering rules (coding standards, testing, roles, language rules). Always read it in full first; it wins over this file on engineering matters.
2. **`docs/CURRENT-STATE.md`** — current phase, bugs, next actions.

## Key facts

- Theme dir: `woodev-base-theme/` (repo root is `woodev_base_theme/`).
- Namespace `Woodev\Theme\Base`; hooks prefix `woodev_base_`; CSS vars `--wtb-*`; text domain `woodev-base-theme`.
- PHP ≥ 8.1, WP/Woo: latest 3 majors (ADR-003).
- Build: Vite → `assets/dist/` (not in git); local env: wp-env.
- Milestones: M0 bootstrap → M1 core theme → M2 Woo layer → M3 release prep.

## Hard rules

- **Work the codebase through Serena** (`find_symbol`, `replace_symbol_body`, …). Built-in `Read`/`Edit`/`Write` and shell rewrites are the fallback for when Serena is unavailable — details and scope in AGENTS.md "Code navigation and editing".
- Modern PHP 8.1+ syntax mandatory (`[]`, `fn()`, promotion, enums, `match`) — details in AGENTS.md.
- Tests mandatory at unit/integration/e2e levels; TDD by default.
- Never silently override an ADR — surface conflicts first.
- wp.org Theme Review compliance from day one (escaping, prefixes, no plugin territory).
- Docs/commits in English; discussions with Maksim in Russian only, informal «ты» (AGENTS.md "Language rules").

## Docs map

- `PROJECT.md` — project brief
- `docs/specs/2026-07-17-woodev-base-v1-design.md` — approved v1 design
- `docs/adr/` — decisions · `docs/GOTCHAS.md` — traps index
- `docs/CURRENT-STATE.md` — status · `docs/SESSION-LOG.md` — history

"Сохрани сессию" triggers the session-end protocol from the global CLAUDE.md.
