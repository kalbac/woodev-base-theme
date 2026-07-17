# Session Log — Woodev Base

## s2 — 17.07.2026 — M0 bootstrap executed (PR #1)

**Done:** all 16 plan tasks, subagent-driven (Sonnet workers, Opus verification). Toolchain (Vite 8, Tailwind v4, Basecoat 1.0.2 exact, Alpine), design-token single source → `theme.json` + CSS vars, theme skeleton, hand-rolled autoloader + `Theme`/`Setup`/`Assets`, PHPCS + PHPStan L8 + PHPUnit 10.5/Brain\Monkey + Vitest + Playwright, wp-env, CI. Verified on wp-env: WP 7.0.1 on **PHP 8.1.34** (the declared floor), theme activates, front page 200 with dist assets, zero PHP notices. Tests: 10 PHP unit + 4 JS unit + 3 e2e.

**Plan deviations (reality contradicted the plan — all four verified, not assumed):**

1. `import 'basecoat-css'` → **`basecoat-css/all`**. The bare specifier resolves to the package's `.` export, which is CSS; it would have registered zero components **silently**. `/basecoat` is the registry alone (0 `register()` calls); `/all` has 12.
2. Dropped **`layer(components)`** from the Basecoat import: it fails the build outright (`@custom-variant cannot be nested`) and is redundant — Basecoat self-declares `@layer components` in 38/39 component files.
3. Design tokens now emit **un-layered, imported after Basecoat**. Basecoat declares its own `:root` defaults un-layered, so our `@layer theme` tokens were silently losing. Invisible because both ship identical shadcn colours; would have surfaced in M1 as "the Customizer can't move a token". Proven with sentinel builds.
4. **PHPUnit pinned `^10.5` + `config.platform.php = 8.1`**. PHPUnit 11 needs PHP ≥ 8.2 while ADR-003 fixes the floor at 8.1 and requires CI to target it — the plan asked for both. Local PHP is 8.5, so composer had resolved a lock CI could not install.

Deviations 2 and 3 were **silent failures**: the build/site would look fine and break in M1.

**Other corrections:** WP floor computed as **6.8 / tested 7.0** (plan's `6.7` was a stale placeholder; its `min-2` one-liner breaks now that WP is 7.0). Dev-server CORS narrowed from `cors: true` to the wp-env origins. `wp_json_file_decode()` replaces `file_get_contents`+`json_decode` (WP canon). Vitest scoped to `tests/js` — it was collecting the Playwright spec and failing `npm run test:js`. Prettier scoped to code, not prose (it had realigned every table in the approved spec and in the plan itself). Mockery expectations now count as PHPUnit assertions (tests were "risky", i.e. reporting zero assertions).

**Verification approach:** worker claims were never trusted. PHP setup tests checked by **mutation** (dropping an `add_theme_support`, swapping the text domain — both caught); PHPStan L8 sanity-checked by injecting a type error; the token-cascade e2e guard checked by **simulating the regression** — which revealed its comment was wrong about why it works (Basecoat *does* define `--font-sans`, as Geist; that difference is the only thing that can observe the regression, since every colour is identical).

**Gotchas added:** `basecoat-js-entry-is-a-subpath-export`, `basecoat-tokens-are-un-layered` (both new, both silent-failure traps). `tailwind-v4-layer-precedence` updated with how its traps actually played out.

**Codex critic (mandatory gate):** ran, **2 × P2 open — nothing auto-fixed, awaiting Maksim** (verbatim in the [PR comment](https://github.com/kalbac/woodev-base-theme/pull/1#issuecomment-4998876196)). Both verified real:
1. **Dev mode ships no CSS** (`Assets.php:60-61`). `enqueue_dev()` enqueues only the Vite client + JS entry, and `app.js` never imports `app.css` because Vite declares CSS as a separate Rollup entry — so `WOODEV_BASE_DEV` renders with no Tailwind/Basecoat/tokens. e2e only covers the production path, which is why it got through.
2. **Missing manifest emits a warning** (`Assets.php:73-76`) — **a regression introduced in this session** (`c8f440b`). WP core's `wp_json_file_decode()` calls `wp_trigger_error()` before returning null; dropping the old `is_file()` guard traded a PHPCS warning for a real behaviour change, and the docblock's "enqueue nothing, not a fatal" claim is currently false. Reachable on any fresh checkout before `npm run build` (`assets/dist` is gitignored). Fix: restore the `is_file()` guard ahead of the decode.

Tooling note: the Codex plugin's review job hung 15 min on a `supermemory/recall` MCP call (log dead after the call). Re-ran `codex review --base main -c 'mcp_servers={}'` directly, which completed — worth knowing before trusting a "running" Codex job.

**Build/commits:** 23 commits on `feat/m0-bootstrap`; PR [#1](https://github.com/kalbac/woodev-base-theme/pull/1), CI green (both runs, all three jobs). Language rule codified in `AGENTS.md` (Russian only, informal «ты»); `AGENTS.md` made mandatory session-start reading in `CLAUDE.md`.

**Next:** triage the 2 Codex findings → fix → re-run the critic on the fixes (never self-certify) → merge PR #1. Then M1 kickoff: WP integration-test harness (research current wp-env docs first), then M1 per spec §7 inventory.

## s1 — 17.07.2026 — Brainstorm, decisions, full project bootstrap

**Done:**
- Brainstormed all open decisions from `PROJECT.md`; recorded ADR-001…006: hybrid architecture (classic + theme.json), Customizer (`theme_mods`), PHP ≥ 8.1 / WP & Woo latest-3-majors, Basecoat via pinned npm + adapter layer, GitHub-first distribution (wp.org-compliant from day one), English source strings + ru_RU.
- Wrote v1 design spec `docs/specs/2026-07-17-woodev-base-v1-design.md`; scaffolded canon: `AGENTS.md` (modern PHP 8.1+ mandatory, SOLID/DRY/YAGNI/KISS, unit+integration+e2e mandatory, Opus 4.8 orchestrator + Sonnet 5 workers + Codex critic), lean `CLAUDE.md`, docs structure.
- Installed 8 vetted review skills from jorgerosal/wordpress-skills → `.claude/skills/` with PROJECT OVERRIDE preambles (`[]` not `array()`, hybrid-classic scope); Codex critic reads the same files.
- Created public repo **kalbac/woodev-base-theme**, pushed `main`.
- Verified Basecoat reality (context7): npm `basecoat-css` **1.0.2** (pinned exact), granular imports, dark mode = `.dark` class, 8 standalone style packs.
- Customizer contracts fixed in spec: `color_scheme_default` (system/light/dark, default system) + `color_scheme_toggle` (visitor switcher, header icon button, no-FOUC inline script); `primary_preset` (default = inherit pack, + 8 curated colors); `style_preset` (8 Basecoat packs → 8 build bundles, one enqueued).
- M1 inventory fixed in spec §7 (templates, parts, 2+2 header/footer variants, optional right sidebar, components, Lucide inline-SVG icons, system font stack).
- Wrote M0 implementation plan `docs/plans/2026-07-17-m0-bootstrap.md` (16 tasks, full code, TDD; WP integration harness deliberately deferred to M1 kickoff).
- Handoff prompt for the next session: `next-session-promt.md` (gitignored).

**Decisions:** ADR-001…006 + spec §6–7 Customizer/inventory contracts.

**Gotchas added:** `tailwind-v4-layer-precedence` (inherited), `basecoat-style-packs-standalone` (new).

**Build/commits:** docs-only session; 8 commits on `main`, pushed. No code yet — M0 starts next session.

**Next:** execute M0 plan in a fresh session (subagent-driven, autonomous; see `next-session-promt.md`).
