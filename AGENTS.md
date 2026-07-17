# AGENTS.md — Woodev Base

Entry point for all development agents. Read this first, then `docs/CURRENT-STATE.md`.

## What this project is

Woodev Base — a universal WordPress theme (optional WooCommerce layer) built on Basecoat UI + Tailwind CSS v4 + Alpine.js, classic templates + `theme.json` (hybrid). Full brief: `PROJECT.md`. Approved design: `docs/specs/2026-07-17-woodev-base-v1-design.md`. Architecture decisions: `docs/adr/`.

## Roles and models

- **Orchestrator:** Opus 4.8 drives implementation sessions using subagent-driven development (`superpowers:subagent-driven-development`): it dispatches plan tasks to workers, verifies each task's tests/lints itself, and never lets a worker mark its own work done.
- **Workers:** Sonnet 5 subagents for routine implementation tasks; Opus for tasks needing deeper reasoning. Workers follow TDD and this document.
- **Critic/reviewer:** Codex — every substantial change gets a Codex review pass before merge. Never self-certify fixes made in response to a review.
- Architecture-shaping decisions must be surfaced **before** coding (🔴 irreversible vs 🟡 graftable). If a task conflicts with an ADR — stop, surface the conflict, propose alternatives. Never silently override an ADR.

## Project skills (`.claude/skills/`)

Eight vetted WordPress review skills are installed project-locally (source: [jorgerosal/wordpress-skills](https://github.com/jorgerosal/wordpress-skills), vetted and patched s1): `wp-theme-development`, `wp-woocommerce-dev`, `wp-security-review`, `wp-accessibility-review`, `wp-test-strategy`, `wp-phpstan-review`, `wp-ci-cd-and-release-engineering`, `wp-performance-review`.

- They are **review-oriented**: use them when reviewing/auditing code and as pattern references. Writing canon stays in this document.
- Each carries a **PROJECT OVERRIDE** preamble (modern PHP 8.1+ syntax, hybrid-classic scope); the preamble wins over the skill body, and this document wins over both.
- **Codex critic:** when composing Codex review prompts, instruct Codex to read the matching `.claude/skills/wp-*/SKILL.md` (and only the `references/` files it needs) — single source of truth, no `~/.codex` copies.
- Upstream sync is manual: re-vet and re-apply override preambles when updating from the source repo.

## Language rules

- Code, comments, commits, all docs: **English**.
- User-facing theme strings: English source + `ru_RU` translation (ADR-006).
- Discussions with Maksim: **Russian only, always informal «ты»** — never «вы», never English. We're on bro terms; write like a teammate, not a support desk. This applies to every message, including short status updates and questions.

## Coding standards — PHP

This is a well-trodden domain. **Follow WordPress/WooCommerce canon and established best practices; do not invent project-specific conventions where a WordPress convention exists.** Consult official docs (WordPress, WooCommerce, Basecoat, Tailwind, Alpine) when unsure — do not guess APIs.

**Modern PHP 8.1+ syntax is mandatory wherever possible:**

- `declare(strict_types=1)` in every PHP file under `inc/` and `tests/`.
- `[]` — never `array()`.
- Arrow functions `fn() =>` for single-expression closures; first-class callable syntax (`$obj->method(...)`) over `[$obj, 'method']`.
- Constructor property promotion; `readonly` properties where state must not change.
- Backed `enum`s for closed value sets — not class constants.
- `match` over `switch` where it fits; null coalescing / nullsafe operators over `isset()` chains.
- Types everywhere: parameters, returns, properties. `mixed` only when WP APIs force it.
- `str_contains()` / `str_starts_with()` / `str_ends_with()` over `strpos()` comparisons.
- No `extract()`, no `compact()` for template data, no dynamic property creation.

**WPCS deviations are codified, not ad-hoc:** `phpcs.xml.dist` is the single source of truth. We keep WPCS core style (tabs, spacing, escaping/security/i18n sniffs) but disable sniffs that conflict with modern syntax (e.g. long array syntax). If a sniff blocks a justified modern construct — update the ruleset in a dedicated commit, don't sprinkle `phpcs:ignore`.

**WordPress canon (non-negotiable):**

- Escape on output (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`), sanitize on input.
- Every user-facing string is translatable, text domain `woodev-base-theme`, no variables inside i18n functions.
- Russian plural rule: avoid `_n()` for count-sensitive copy; use count-agnostic phrasing + `number_format_i18n()`.
- Hooks/functions prefixed `woodev_base_`; CSS custom properties `--wtb-*`.
- Assets only via `wp_enqueue_*` (through the Vite manifest resolver in `Assets.php`).
- Nonces + capability checks for anything that writes state.
- Customizer settings: sanitize callback required for every control.

## Coding standards — CSS/JS

- Tailwind v4 layer order: `theme, base, components (Basecoat), adapter (ours), utilities`. Basecoat overrides live **only** in the adapter layer. State overrides that must beat utilities go **outside** all layers — see `docs/gotchas/tailwind-v4-layer-precedence.md`.
- Basecoat components use Basecoat's JS; Alpine handles theme-level behavior only. Do not rewrite Basecoat components in Alpine.
- Progressive enhancement: pages must work as server-rendered HTML; JS enhances, never renders primary content.
- Utility classes repeated in 3+ places → promote to an adapter component class.

## Principles

- **SOLID** — small classes, single responsibility, composition root in `Theme.php`.
- **DRY** — refactor at 3+ occurrences; 2 occurrences is not a pattern yet.
- **YAGNI** — build what the current milestone needs. No speculative abstractions, options, or "future-proof" layers.
- **KISS** — the boring, canonical WordPress way beats a clever custom one.

## Testing — mandatory, three levels

No feature merges without tests at the appropriate levels; no merge with red tests.

| Level | Tooling | Scope |
|---|---|---|
| Unit | PHPUnit + Brain\Monkey (PHP, no WP bootstrap); Vitest (JS) | Pure logic, sanitizers, helpers, Alpine modules |
| Integration | WP test suite (`WP_UnitTestCase`) under wp-env | Anything touching WP/Woo APIs: hooks, Customizer, template loading |
| e2e | Playwright against wp-env | User-visible flows, smoke on key templates, visual checks (light + dark) |

TDD is the default workflow (test first, watch it fail, implement). UI claims ("it renders correctly") require browser/e2e evidence, not assertion.

## Definition of done

1. Code follows this document; lint (PHPCS, PHPStan, ESLint) green.
2. Tests written and green at the required levels.
3. i18n + escaping verified for any new user-facing surface.
4. A11y checked for new/changed components (keyboard, focus, labels, reduced motion).
5. Codex review passed; findings addressed or explicitly deferred with Maksim's sign-off.
6. Docs updated when behavior or contracts changed (`docs/`, ADR if architectural).

## Related docs

- `docs/CURRENT-STATE.md` — phase status, next actions (always read at session start)
- `docs/SESSION-LOG.md` — session history
- `docs/GOTCHAS.md` — index of known traps
- `docs/adr/` — architecture decisions
