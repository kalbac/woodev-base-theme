# Session Log — Woodev Base

## s4 — 20.07.2026 — M1-01 icons merged, M1-02 templates built and merged

**Done:** Two PRs merged to `main`.

- **PR #3 (M1-01 Lucide icons)** — carried over from s3 unmerged, and it had never been through the mandatory Codex critic. Ran it (3 focused reviews weren't needed here — one pass on the ~12 KB code diff). One real **P1**: `DOMDocument::loadXML('')` throws `ValueError` on PHP 8 for a zero-byte SVG, breaking `inner_markup()`'s documented "return '' on missing/malformed" contract and surfacing as a fatal. Fixed with an empty-file guard + throw-safe `try/finally`; both mutation-pinned (guard removal → ValueError; restore removal → libxml test red). Re-critic found 2 P3s (parse the untrimmed bytes so a NUL-wrapped doc stays rejected; make the libxml test cold-cached so it isn't vacuous) — both fixed. CI green, squash-merged `96df1db`.
- **PR #4 (M1-02 templates & parts)** — the whole plan, subagent-driven (Sonnet workers + Opus orchestration/verification, one Opus worker for the nav). 8 tasks: widget areas + footer menu; `Layout` resolver; header/footer variants; accessible navigation; content parts + pagination; template hierarchy; e2e smoke; gate + critic + PR. Squash-merged `f3f5f0a`.

**Gate (all green):** phpcs 40/40 (grew 27→40 with the new templates), phpstan L8, unit 80, integration 13, vitest 4, **e2e 21** (incl. a resize focus-trap regression test and a one-h1 pin), build ok.

**Two bugs caught in-browser during verification (worker code passed lint + its own tests):**
1. `number_format_i18n()` on the copyright **year** → `© 2,026`. The count rule (AGENTS.md) was misapplied to a year by the plan; the worker followed it faithfully. Fixed to plain `wp_date('Y')`. Gotcha: `number-format-i18n-mangles-years`.
2. A dark-tokens e2e read a false light value **only under the full suite**. The theme's dark mode was proven correct in-browser first; the test used `browser.newPage()` (skips the project config) + `addInitScript` timing. Rewritten to a runtime toggle on the `{ page }` fixture. Gotcha: `playwright-browser-newpage-skips-config`.

**Codex critic on M1-02** — the ~34 KB diff exceeds the CLI's safe prompt size, so it went out as 3 focused reviews (templates+resolver / parts / nav+CSS) + a re-critic on the fixes. Three real findings, each fixed and test-pinned: no `<h1>` on the blog index; pagination prev/next had no accessible name (lone decorative chevron); the mobile drawer left focus trapped when widened to desktop. The re-critic added an `esc_html( single_post_title() )` tightening. **Three false positives** were artefacts of the split diff (a guard living in another chunk) — recorded as its own gotcha `codex-split-diff-false-positives`.

**Decisions:** no custom nav walker — the default walker markup is correct, submenus revealed by CSS `:focus-within` (works with JS off); mobile drawer is an Alpine disclosure with `@alpinejs/focus` x-trap and a `.wtb-nav--enhanced` PE marker (JS off ⇒ menu visible, toggle hidden). `has_sidebar()` kept as `! is_page()` for v1 with Maksim's sign-off (Codex wanted it narrowed to blog/archive/single; deferred to M1-04).

**Gotchas added:** `number-format-i18n-mangles-years`, `playwright-browser-newpage-skips-config`, `codex-split-diff-false-positives` (index now 13).

**Next:** write and execute M1-03 (8 Basecoat style-pack bundles + adapter). Then the dev-mode integration coverage follow-up, M1-04 (Customizer — narrow `has_sidebar` here if wanted), M1-05 (scheme switcher into the slot already left in both header variants).

## s3 — 19.07.2026 — PR #1 merged, M1 integration harness (reconstructed)

> This entry was never written at the time; reconstructed s4 from `docs/CURRENT-STATE.md` and git history to close the gap. Treat detail as best-effort.

**Done:** Fixed PR #1's two Codex P2s (dev-mode ships no CSS; missing-manifest warning) — each reproduced against a real WP first and guarded by a test proven red before the fix (`e175958`, `9b0341f`); re-reviewed (no P1/P2/P3) and merged. Built the **M1 WP integration-test harness**: a separate Composer root at `tests/integration/` on PHPUnit 9.6 (WP core is PHPUnit-9-only; our unit root stays on 10.5), driven by a second `.wp-env.test.json` config; `npm run wp:test:start` → `test:integration:install` → `test:integration`; CI job `php-integration` green (PR #2). Its own review found a real coverage hole hidden behind a false "mutation-verified in s2" comment — the html5 feature list was asserted nowhere (`->times(4)`); fixed (`c6f3bb3`, `76b6c58`). Also fixed `composer phpcs` being unrunnable on Windows (CRLF) via `.gitattributes eol=lf` (`a557d36`).

**Gotchas added (s3):** `codex-cli-dies-silently`, `wp-env-config-constants-persist`, `wp-json-file-decode-warns-on-missing-file`, `qa-gates-cover-less-than-they-claim`, `vite-css-entry-is-not-imported-by-the-js-entry`; `wp-test-suite-removes-html5-support` updated ("second trap").

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
