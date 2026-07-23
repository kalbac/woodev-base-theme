# Session Log — Woodev Base

## s7 — 22–23.07.2026 — dev-mode coverage and the §7 component tail merged

**Done:** two features, both designed → planned → subagent-driven → Codex-critic → merged. [#10](https://github.com/kalbac/woodev-base-theme/pull/10) dev-mode coverage (`e1cf31b`) and [#11](https://github.com/kalbac/woodev-base-theme/pull/11) the §7 component tail (`6dfac28`). Order this session: dev-mode → §7 → (M2 next), agreed with Maksim.

**Gate on merged `main` (`6dfac28`):** phpcs 0 · phpstan L8 · unit **146** · vitest 25 · integration **35** · integration-dev **4** · e2e **44** · e2e-dev **2** · build OK.

**AGENTS.md** gained an explicit **Autonomy** section: Maksim is interrupted only for UI/UX calls and architectural forks that cannot be settled from the docs; everything else is the agent's call, recorded in the report. Verification gates unchanged.

**Dev-mode coverage (PR #10)** — closed a Codex P2 open since s3. A second PHPUnit config whose bootstrap **defines** `WOODEV_BASE_DEV` (never wp-env's `config` key — it leaks into both environments and persists), asserting the real `wp_head`+`wp_footer` output; mirrored by a production test so neither passes vacuously. Plus one browser spec on a third permanently-dev wp-env (:8892) asserting **computed style**, because the defect class it guards has the script tag present and the styles absent.

- **The near-miss worth remembering.** The `ScriptModuleGuard` reflected on `WP_Script_Modules::$done` — a property that **exists only from WP 6.9**, while the theme declares `Requires at least: 6.8`. On 6.8 every test using it dies with `ReflectionException`. Invisible locally and in CI: both run `core: null` = latest. Caught by Codex reading the real 6.8/6.9 core, verified against the wordpress-develop tags, fixed with `property_exists()`. **Nothing in this project tests the declared WP floor** — logged as the most valuable untested claim we make; cheap fix is one CI job pinned to 6.8.
- **`AssetMarkup` hit the three-rounds rule a second time** (after s6's `add_html_class`). Round 1 a lookahead regex accepted `data-type=`; round 2 a DOM query matched any `assets/dist` URL; round 3 a dual "exact or substring" URL parameter had silently downgraded the dev assertion to `str_contains`. The fix was again to **delete the requirement** — the URL is always exact now, the production test reading the hashed name from the manifest it asserts about. Gotcha updated with the second occurrence.
- **Three false explanatory comments**, this project's recurring defect class: `loadHTML()` promised to fail on malformed markup (it recovers; measured on PHP 8.1.34 in-container, where `''` throws `ValueError` — the s4 Icons trap); a comment credited Vite's `strictPort` for a loud port failure that Playwright actually raises itself before starting Vite (measured with a foreign listener). Each corrected against the real thing.
- **Corrected the s6 Serena gotcha** — `line_ending: "lf"` does NOT stop the CRLF writes; measured false for both write paths, the symbol edit converting the whole file while `git diff` stays clean. New gotcha: wp-env's `themes` key installs without activating.

**§7 component tail (PR #11)** — card, badge, alert and the comment-form controls wired into real templates; the inventory now renders rather than merely builds (and the 8 packs are visibly distinguishable at last — cards are the first place their geometry shows). **tabs and accordion deferred to M2**, where the single-product page is their real home rather than a page invented to display a component. Post excerpts became Basecoat cards in a `.wtb-post-grid` (1→2→3 cols, capped at 2 with a sidebar); categories are secondary badges; empty/404/password states are alerts; the comment form is styled through `comment_form()`'s own args.

- **The escaping hole phpcs could not see.** The comment-form labels were built with bare `__()`/`_x()`, assigned to a variable, and handed to `comment_form()`, which echoes them. `WordPress.Security.EscapeOutput` flags `echo __()` but not a translation that travels through a variable into a WP function — so phpcs was green while a tampered translation could reach the page. Now `esc_html__()`/`esc_html_x()`. New gotcha `phpcs-misses-unescaped-output-through-a-variable`.
- **A vacuous dark-mode e2e**, again: it asserted DOM structure identical in both schemes, so the dark tokens could break green. Fixed to read the card's computed `background-color` (`oklch(1 0 0)` vs `oklch(0.205 0 0)`) — the same lesson `smoke.spec.mjs` carries.
- **A grid breakpoint test with no precondition** — a leftover `sidebar_position=right` would cap 1400px at 2 tracks and read as a broken breakpoint; now asserts `.wtb-layout--has-sidebar` absent first, mirroring how the cap test asserts it present.
- Own finding while verifying T2: the badge helper's `esc_url` guard was un-testable (its stub was `returnArg`), so removing `esc_url` stayed green. Added a stub with an observable identity.

**Process notes:** a worker terminated mid-task on an API 403 (fixes for the re-critic) and another returned while "waiting for a background task"; both times I took over in the main loop rather than re-dispatching, since the context was small and mine. One e2e timeout flake (the scheme-switcher toggle-off test) under heavy concurrent local load passed in isolation and clean on CI (5 min vs my 23) — confirmed load-only.

**Gotchas:** +3 (`wp-env-installs-themes-without-activating-them`, `phpcs-misses-unescaped-output-through-a-variable`, plus the s6 Serena and vite-css corrections), index now **19**.

**Next:** M2 — the WooCommerce layer, with a design pass before a plan.

## s6 — 22.07.2026 — M1-04 Customizer and M1-05 scheme switcher merged; **M1 complete**

**Done:** two PRs merged to `main` — [#8](https://github.com/kalbac/woodev-base-theme/pull/8) M1-04 Customizer v1 (`e480b3a`) and [#9](https://github.com/kalbac/woodev-base-theme/pull/9) M1-05 colour-scheme switcher (`11ce459`). Both planned first, executed subagent-driven (Sonnet workers, Opus orchestration and verification), Codex `gpt-5.6-sol` critic in focused chunks plus re-critic passes.

**Gate on merged `main`:** phpcs 0 · phpstan L8 · unit **141** · vitest **25** · integration **28** · e2e **34** · build OK.

**M1-04** — 8 settings, one validator each used BOTH as the Customizer `sanitize_callback` and as the front-end resolver, so the two can never disagree. Closed two deferred items (`has_sidebar()` narrowed, container width configurable). Found on the way in: `Layout::header_variant()`/`footer_variant()` still carried the `(string) get_theme_mod()` cast that Codex had flagged in `StylePreset` during s5 — a fatal on every front-end request for an object value.

**M1-05** — two scheme settings, a no-FOUC `<head>` script at `wp_head` 1, the sun/moon switcher, and a generated `prefers-color-scheme` fallback for JS-disabled visitors. Closes M1.

**The finding worth remembering (M1-04, accessibility).** The accent presets are derived from Tailwind's palette, and the first version picked `--primary-foreground` by a lightness threshold. Codex called `rose/light` below AA; measuring properly (oklch → oklab → linear sRGB → WCAG luminance) confirmed 4.32:1 — and revealed that **11 of the 16 palette values sit outside sRGB**, so how out-of-gamut colours are handled decides the answer. Per-channel clamping and chroma reduction disagree by ~0.25 of a ratio point, and CSS Color 4 §14 lets a UA pick either. The generator now measures BOTH and keeps the **worse**, throwing below 4.5:1 — so an inaccessible palette value fails the build. rose moved to `-700`.

**The finding worth remembering (M1-05, cascade).** The `prefers-color-scheme` fallback was scoped `:root:not(.light):not(.dark)` — specificity **(0,3,0)**, because `:not()` contributes its argument's. That outranked the Customizer's own inline `:root` and Additional CSS, so on the shipped default (`system`) with a dark OS the accent preset silently did nothing. Two green e2e tests straddled the hole: one pinned the fallback, one pinned the accent, neither ran both at once. `:where()` fixes it. New gotcha `not-selector-carries-its-arguments-specificity`.

**The process lesson.** `add_html_class()` took three review rounds, each finding a *narrower* defect than the last (word boundary matching `data-class=`; unquoted/spaced/uppercase forms missed; `str_replace` rewriting other attributes; a match inside a quoted value winning; a newline falling through to an empty match). Converging bug reports in one function mean the **approach** is wrong. Stopped parsing entirely — the attribute exists only for the no-JS visitor, so declining to touch a string that already mentions a class is a bounded cost, while corrupting a plugin's attribute is not. New gotcha `three-rounds-of-fixes-means-change-the-approach`.

**Comments that lied, four times.** A phpcs deviation claimed WP core uses `wp_strip_all_tags()` for inline CSS (it does not — it checks for a literal `</style>`); an `InlineStyles` comment claimed a child theme loads after our block (it does not — enqueued styles print at `wp_head` 8, ours at 20; only Additional CSS at 101 comes later); a comment promised the matchMedia listener had "a real teardown path" before `destroy()` existed; a `Layout` docblock described an `is_string()` guard that PHPStan proved redundant. Each was settled in one command against the real source. **If a comment asserts what WP core, a browser or PHP does, verify it before writing it.**

**Codex tooling, corrected.** The s3 recipe pins `CODEX_HOME=~/.codex-review-clean`, which has its OWN `auth.json` — five days stale, so every run failed with "refresh token already used" while the default profile was freshly authorised. The 403s alongside it came from an **MCP worker**, not the model. Working invocation: default profile plus `-c 'mcp_servers={}'` (the s2 flag, unused until now).

**Tooling adopted:** Serena, scoped to `./woodev-base-theme`, pinned to `line_ending: "lf"` (unset it wrote CRLF and PHPCS died on line 1), with `.gitattributes -text` and `.prettierignore` keeping other tools out of `.serena/`. `AGENTS.md` now requires Serena for codebase work. New gotcha `serena-writes-native-line-endings`.

**Also:** `WordPress.Security.EscapeOutput.OutputNotEscaped` is no longer weakened anywhere in the ruleset — both legitimate non-HTML echoes carry a line-scoped `phpcs:ignore` with a reason, after a global `customEscapingFunctions` entry and then per-file exclusions were each shown to be too broad. `tests/e2e/style-packs.spec.mjs` was absorbed into a single serial `theme-mods.spec.mjs` that owns every theme_mod mutation, retiring its ISOLATION CAVEAT.

**Gotchas:** +3, index now **17**.

**Next:** M2 (WooCommerce layer), with the dev-mode integration coverage tail (deferred since s3) as a small unblocked side task.

## s5 — 21–22.07.2026 — M1-03 style packs merged, plus two follow-up fixes

**Done:** three PRs merged to `main` — [#5](https://github.com/kalbac/woodev-base-theme/pull/5) M1-03 (`1fd9dd8`), [#6](https://github.com/kalbac/woodev-base-theme/pull/6) container width (`3fafddc`), [#7](https://github.com/kalbac/woodev-base-theme/pull/7) e2e race fix (`9dc2f3b`). Plan written first (`docs/plans/2026-07-21-m1-03-style-packs.md`), executed subagent-driven (Sonnet workers, Opus orchestration/verification), Codex `gpt-5.6-sol` critic in 3 chunks + re-critic.

**The finding that shaped the whole plan.** Read the shipped `basecoat-css@1.0.2` instead of trusting the s1 gotcha: `basecoat-css/<pack>` = `basecoat-base.css` (colour tokens + component structure) + `styles/<pack>.css`. **All 8 packs share one colour palette**; `styles/<pack>.css` is a shape *skin* (`@apply` radius/height/density) with **zero** colour tokens (verified for all 8). So packs differ in geometry, not colour — e.g. `.btn` is 36px in vega, 32px in nova. Consequence: **a pack switch is invisible on a page rendering no Basecoat component classes**, and M1-02's templates rendered none. Without surfacing a `.btn`, the 8 bundles would have built byte-different and looked identical, and e2e could only have asserted filenames. Scope decision (Maksim): engine + one real button, not the full §7 component set.

**Shipped:** `scripts/lib/packs-lib.mjs` is the single source for the 8 pack names, feeding both a CSS-entry generator (`src/css/packs/<pack>.css`, generated + gitignored) and Vite's 8 Rollup inputs; `src/css/app.css` retired. `StylePreset` backed enum resolves the `style_preset` theme_mod → manifest key; `Assets` enqueues that one bundle (prod + dev). `searchform.php` + read-more carry `.btn`/`.input`.

**Gate:** phpcs 0 · phpstan L8 · unit **92** · integration **15** · vitest **10** · e2e **23** · build OK.

**Codex critic — real findings, all fixed and mutation-pinned:**
1. **P1** `(string) get_theme_mod()` not fail-safe — an object without `__toString()` throws `Error`, i.e. **a fatal on every front-end request** (`wp_enqueue_scripts`). Now `is_string()` fails closed; mutation reproduced the exact fatal.
2. **P2** the vitest pinned only `pack → tokens`; reordering Tailwind, wrapping Basecoat in `layer()`, or breaking the `../` paths stayed green. Now pins the full contract.
3. **P2** `searchform.php` dropped core's supported `aria_label` arg (verified fixed against real WP).
4. **P2** ambiguous `.btn` e2e locator → `a.wtb-entry-more.btn`.
5. **P1** the e2e blindly deleted the theme_mod. Re-critic then found **two new defects in my own fix**: the restore interpolated a DB value into a shell (injection) and swallowed read errors. Both fixed; proven by attacking it with `nova; touch /tmp/pwned` — refused loudly, value survived, no command executed.

**The bug the gate caught after merging.** Ran the full gate on merged `main` and `navigation.spec.mjs › … focus is trapped` was red, though green on both branches separately. Not a regression: `x-trap` moves focus **asynchronously** (still `<body>` synchronously after the click and through the next microtask; inside the nav by 50 ms), and the document's first focusable is the skip link, *outside* `.wtb-nav`. A `Tab` fired in that window fails an assertion that blames the trap. Latent since M1-02; #6's one-line CSS change (which cannot affect a 375px viewport) merely perturbed timing. **Bisect pointed at #6 and would have sent me hunting a phantom layout regression** — instrumenting `activeElement` over time is what settled it. Fixed by polling the precondition; mutation (stripping `x-trap`) confirms the guard still bites.

**Process notes:** a worker resolved a WPCS/camelCase conflict by **relaxing `phpcs.xml.dist`** — reverted, renamed to snake_case instead (the whole codebase is snake_case; the camelCase was my design error in the plan). Another worker backgrounded its e2e run and never reported; finished it myself (run, mutation, commit).

**Gotchas:** +1 new `x-trap-focus-move-is-async`; **2 corrected** — `basecoat-style-packs-standalone` (the "standalone full *token* sets" wording was wrong) and `basecoat-tokens-are-un-layered` (`app.css` no longer exists). Index now **14**.

**Next:** M1-04 Customizer (the `style_preset` engine is built and waiting for a control; also the natural home for narrowing `has_sidebar()` and making container width a setting), then M1-05 scheme switcher, plus the dev-mode integration coverage tail.

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
