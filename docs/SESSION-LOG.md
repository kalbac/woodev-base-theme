# Session Log — Woodev Base

## s1 — 17.07.2026 — Brainstorm + project bootstrap

**Done:**
- Brainstormed all open decisions from `PROJECT.md`; recorded ADR-001…006:
  hybrid architecture, Customizer, PHP ≥ 8.1 / WP & Woo latest-3-majors, Basecoat via pinned npm + adapter layer, GitHub-first distribution (wp.org-compliant from day one), English source strings + ru_RU.
- Wrote approved v1 design spec: `docs/specs/2026-07-17-woodev-base-v1-design.md` (architecture, directory layout, CSS layer order, Basecoat-JS/Alpine split, Customizer model, Woo boundaries, quality baseline, milestones M0–M3).
- Scaffolded project canon: `AGENTS.md` (coding standards: mandatory PHP 8.1+ modern syntax, SOLID/DRY/YAGNI/KISS, mandatory unit/integration/e2e tests, worker Sonnet 5/Opus + Codex critic), lean `CLAUDE.md`, `docs/` structure, seeded Tailwind v4 layer gotcha from woodev-theme experience.
- `git init` (branch `main`), initial commit.

**Decisions:** see `docs/adr/ADR-001…006`.

**Skills installed:** 8 review skills from jorgerosal/wordpress-skills → `.claude/skills/` (theme-development, woocommerce-dev, security, a11y, test-strategy, phpstan, ci-cd, performance). Vetted (security screen clean), patched: PROJECT OVERRIDE preamble in each (modern PHP 8.1+ / `[]` not `array()`; hybrid-classic scope note in theme-development), normative WPCS-syntax lines corrected. Codex critic reads the same files — no `~/.codex` duplication. Alternatives rejected: elvismdev (performance-only), Jeffallan wordpress-pro (single generic skill).

**Next:** M0 implementation plan + tooling skeleton (see CURRENT-STATE).
