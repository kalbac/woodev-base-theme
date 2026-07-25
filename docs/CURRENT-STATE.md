# Current State — Woodev Base

> Updated: 25.07.2026 (s11)

## Phase status

| Milestone | Status | Notes |
|---|---|---|
| Design & decisions | ✅ Done | Spec approved, ADR-001…006 recorded |
| M0 — Bootstrap | ✅ Done | PR [#1](https://github.com/kalbac/woodev-base-theme/pull/1) merged s3 |
| M1 — Core theme | ✅ Done | 5 plans, all merged: icons `96df1db`, templates `f3f5f0a`, style packs `1fd9dd8`, Customizer `e480b3a`, scheme switcher `11ce459` |
| Dev-mode coverage | ✅ Done | s7, PR [#10](https://github.com/kalbac/woodev-base-theme/pull/10) `e1cf31b` — the s3 debt, closed |
| §7 component tail | ✅ Done | s7, PR [#11](https://github.com/kalbac/woodev-base-theme/pull/11) `6dfac28` — card/badge/alert/comment-form. tabs+accordion deferred to M2 |
| Design — whole-theme visual identity | ✅ Approved (s10) | Refined V2 «Обиход» in `docs/design/v2-mockup/`. Golos Text + IBM Plex (OFL, Cyrillic), token-driven, 7 palette presets, hover-reveal CTA, all pages. Source of truth for implementation |
| Identity implementation (T0–T8) | 🟡 In progress (s11) | ADR-007 (fonts) + ADR-008 (single identity replaces the 8 style packs) recorded. Plan: `docs/plans/2026-07-25-visual-identity.md`. **T0 (fix `tokens.css` export) done.** T1 (token layer) in progress. T3 (fonts, `scripts/fetch-fonts.mjs`) in progress. T2/T4–T8 not started |
| M2a — Woo storefront | 🟡 CSS done on branch (`faf7801`), superseded by the approved design; NOT merged | s10: `woo.css` rewritten un-layered + Woo sidebar removed (grid/cascade/sidebar fixes carry over). Visual skin will be **replaced** by the approved design during implementation. Folded into T6 of the identity plan; Task 7 (full gate + Codex + PR) is now T8 |
| M2b — Woo checkout flow | ⬜ Not started | cart/checkout/account/store-notices + Woo Customizer section |
| M3 — Public release prep | ⬜ Not started | |

## Known bugs

**None open.** `main` is green, verified on the MERGED commit `6dfac28` and not just per-branch: phpcs 0 · phpstan L8 · unit **146** · vitest 25 · integration **35** · integration-dev 4 · e2e **44** · e2e-dev 2 · build OK.

**M2a branch caveat (`feat/m2a-woo-storefront`):** each M2a task was self-verified at its own level (s9 end: unit **156** · phpcs 72/72 · integration **36** (Woo `BootstrapTest` skips w/o Woo) · e2e-woo **9** green, grid guard mutation-verified · build OK), but the branch has **not** had the full merged-tree battery (no phpstan / vitest / base-isolation e2e / e2e-dev this session) and **no Codex critic** — that is Task 7, deliberately deferred until after the UI redesign. Treat the branch as unproven at the merge bar until Task 7 runs.

s7's near-miss is worth carrying: the new `ScriptModuleGuard` reflected on `WP_Script_Modules::$done`, which **exists only from WP 6.9** while the theme declares `Requires at least: 6.8`. Every test using it would have died with `ReflectionException` on the floor we claim to support. Local runs cannot see this — wp-env uses `core: null`, i.e. latest — and neither can CI, which does not matrix the floor. **Nothing in this project currently tests the declared WP floor**; that is now the most valuable untested claim we make.

s5 found and fixed one real defect after merging — the mobile-drawer focus-trap e2e was red on merged `main` while green on each branch alone. Not a product regression: `x-trap` moves focus asynchronously and a premature `Tab` lands on the skip link, outside the nav (`docs/gotchas/x-trap-focus-move-is-async.md`, PR #7 `9dc2f3b`). Codex also caught a would-be **fatal on every front-end request** before merge — `(string) get_theme_mod()` throws `Error` for an object; now fails closed.

## Deferred, tracked

- ~~**Dev mode has no integration/e2e coverage**~~ — resolved s7, closing a Codex P2 open since s3. Integration: `tests/integration/Integration/DevMode/AssetsDevModeTest.php` via `npm run test:integration:dev` (a second PHPUnit config whose bootstrap defines the constant — never wp-env's `config` key, which leaks into both environments and persists), mirrored by `Integration/AssetsProductionTest.php`. e2e: `tests/e2e-dev/dev-mode.spec.mjs` via `npm run e2e:dev`, against `.wp-env.dev-mode.json` on :8892 with Playwright owning a live Vite dev server. The e2e asserts **computed style**, since the defect class it guards has the script tag present and the styles absent.
- **Customizer overrides do nothing in dev mode.** Vite serves the pack CSS as a JS module that injects its `<style>` when the module EXECUTES — after `InlineStyles`' block was parsed — so `tokens.generated.css` wins on source order. Production is unaffected and an e2e mutation pins it (moving the block to `wp_head` 5 turns the accent assertion red). Raising selector specificity would fix dev at the cost of every real site's override path (Additional CSS), which is the wrong trade — see `InlineStyles`' docblock.
- **Self-hosted fonts 404 in dev mode** (s11, same root cause as the entry above). Vite dev injects the CSS through a JS-created `<style>`, which has no URL of its own, so `fonts.css`'s relative `url('../../fonts/…')` resolves against the *page* and misses at a depth-dependent path. `font-display: swap` hides it — the page renders in the fallback stack and merely looks slightly off, so **typography must be judged in a production build, never in dev**. Not fixed with a `/`-rooted path: that breaks subdirectory installs and is a wp.org review flag. Production verified (all 20 built `url()`s resolve). See `docs/gotchas/dev-mode-css-injection-breaks-relative-urls.md`.
- **IBM Plex Mono 700 does not exist in the vendored subsets**, so the design's one `.totals .row.grand .amount` rule falls back to 600. Closes with the M3 `pyftsubset` re-instancing (ADR-007).
- **Live OS-following is not pinned by a test.** Spec §6 says `system` keeps following `prefers-color-scheme` after load. `page.emulateMedia()` updates `matchMedia().matches` but does NOT dispatch `change` to registered listeners in this Chromium/CDP build, so the behaviour was verified by invoking the handler directly and the spec file says so rather than faking it.
- **No-JS + `system` misses Basecoat's `dark:` utilities.** Such a visitor gets our dark *tokens* via the generated `prefers-color-scheme` block, but Basecoat's dark variant keys off a literal `html.dark`, which only exists once JS or an explicit admin default sets it.
- **Reset-to-defaults (spec §6) not built.** Core has no reset primitive; a real one is a JS control plus a nonce'd handler, i.e. plugin territory for a v1 theme. Clearing a value in the Customizer already returns the setting to its documented default.
- ~~`has_sidebar()` too broad~~ — resolved M1-04: `is_home() || is_archive() || is_search() || is_singular( 'post' )`. Note `is_single()` was wrong: core sets it for attachments and every public CPT.
- ~~Container width hard-coded~~ — resolved M1-04: a Customizer setting, 960–1920px.
- ~~e2e style-packs isolation~~ — resolved M1-04: `style-packs.spec.mjs` was absorbed into a single serial `theme-mods.spec.mjs` that owns every theme_mod mutation and restores after each test.

## Open items

- **Codex: use the DEFAULT profile with MCP disabled.** `codex exec -c 'mcp_servers={}' "…"`. The s3 recipe's clean `CODEX_HOME=~/.codex-review-clean` has its **own** `auth.json`, which goes stale independently — s6 lost an hour to "refresh token already used" there while the default profile was freshly authorised. The 403s that appear alongside come from an **MCP worker**, not the model, which is what `mcp_servers={}` silences. Everything else from the s3 recipe still holds: foreground, prompt inline and **under ~15 KB**, stdin closed, smoke-test with `"Reply with exactly: CODEX_OK"` first (every failure mode exits 0 — `codex-cli-dies-silently.md`), and name the out-of-chunk guards in every chunk prompt (`codex-split-diff-false-positives.md`).
- **Re-critic the fixes, always.** s6's two re-critic passes each found defects *inside* the fixes written for the previous round — including one in a fix for a finding the critic had just made. See `three-rounds-of-fixes-means-change-the-approach.md`.
- **Codex reads project files during review.** Tell it explicitly not to read `.claude/skills/**` — one run returned 186 KB.
- **Line endings, three routes into the same trap**: `.gitattributes` pins `eol=lf`; a Python helper in text mode emits CRLF (s5, twice). **Serena writes CRLF regardless — `line_ending: "lf"` does NOT work** (s6 said it did; measured false in s7 for both `create_text_file` and `replace_symbol_body`, the latter converting the whole file while `git diff` stays clean). Strip CRs after every Serena write and check `git ls-files --eol`. All three end in PHPCS failing on line 1.
- **Nothing tests the declared WP floor (6.8).** wp-env runs `core: null` and CI does not matrix versions, so a 6.9+ API used anywhere passes every gate we have. s7 nearly shipped exactly that. Cheap fix when someone wants it: one CI job with `core: "WordPress/WordPress#6.8"`.
- **Serena is required for codebase work** (AGENTS.md). Index scoped to `./woodev-base-theme`, so `find_referencing_symbols` does not see `tests/` — use `search_for_pattern` for test usages.
- ~~Pin concrete WP floor~~ — resolved s2: **6.8** (`Requires at least`), tested up to **7.0**. Re-check each release.
- ~~Basecoat pin~~ — resolved s1: exact `1.0.2`. ~~M1 inventory~~ — resolved s1: spec §7. ~~Fonts/icons~~ — s1: system stack, Lucide (ISC). ~~wp-env config shape~~ / ~~PHPUnit 10.5 vs core suite~~ — resolved s3.

## Next actions

**M1 complete, plus two s7 follow-ups.** All merged:

| # | Plan | State |
|---|---|---|
| M1-01 | Lucide icon helper | ✅ `96df1db` (s4) |
| M1-02 | Templates & parts | ✅ `f3f5f0a` (s4) |
| M1-03 | 8 Basecoat style-pack bundles + adapter | ✅ `1fd9dd8` (s5) |
| M1-04 | Customizer v1 (§6) | ✅ `e480b3a` (s6), PR #8 |
| M1-05 | Scheme switcher + no-FOUC head script | ✅ `11ce459` (s6), PR #9 |
| Dev-mode coverage | ✅ `e1cf31b` (s7), PR #10 |
| §7 component tail | ✅ `6dfac28` (s7), PR #11 |

**s10 approved the whole-theme VISUAL IDENTITY; s11 is implementing it** per `docs/plans/2026-07-25-visual-identity.md` (tasks T0–T8, ADR-007 + ADR-008 recorded). The storefront-only redesign (#12) and M2a are subsumed by it (T6). Nothing merged; `main` untouched at `27edbd6`. Status by task:

| Task | What | Status |
|---|---|---|
| T0 | Fix `docs/design/v2-mockup/tokens.css` (regenerate from the mockup HTML's inline `<style>`, not the stale 8-`[data-palette]` version) | ✅ Done |
| T1 | Token layer — `src/tokens/tokens.mjs` + `scripts/lib/build-tokens-lib.mjs` emit `--n-h`, the 7 palettes, accent/radius/type roles. Contrast gate **resolves `var()`/`calc()` numerically** and measures 7 palettes × 2 schemes × 24 pairs; below AA the build throws. Mutation-verified (a broken `--muted-foreground` yields exactly 21 named failures). vitest 25/25. Verified separately: all 82 tokens the design's CSS consumes are emitted | ✅ Done |
| T2 | Retire the pack machinery (`src/css/packs/`, `scripts/build-pack-entries.mjs`, `scripts/lib/packs-lib.mjs`, `style_preset`) → one `src/css/app.css` entry on `basecoat-css/base` | 🟡 In progress |
| T3 | Fonts (ADR-007): `scripts/build-fonts.mjs`, self-hosted Golos Text + IBM Plex Sans/Mono, `src/css/fonts.css`, OFL licenses. 20 woff2, **352 KB shipped / ~132 KB fetched** by a Russian page — over the ADR's original estimate, which is now restated with measured numbers + an M3 `pyftsubset` plan. Idempotency and all 20 built-CSS `url()`s verified by hand | ✅ Done |
| T4 | Base + layout surfaces → `adapter/{base,header,hero,blocks,content,footer}.css` + template pass. Hero/category-tiles/promo CSS is ported but **not wired to any template** — no front-page merchandising markup exists yet | ✅ Done |
| T5 | Component kit → `adapter/{buttons,forms,feedback,components}.css`, incl. tabs + accordion (the M1 §7 deferral). Basecoat's leftover tokens ruled on: nothing overridden, reasons in the plan | ✅ Done |
| — | **Integration (orchestrator):** `adapter/index.css` rewired — 10 imports, superseded blocks deleted (skip-link, nav, scheme-toggle, entry-card bits, comment bits), container/layout/post-grid deliberately kept below the imports. Verified: no selector collides across the 10 files; build green; the ported CSS is in the bundle and inside `@layer adapter` | ✅ Done |
| T6 | WooCommerce surfaces — shop/product/cart/checkout/account; reconciles with `woo.css` (`faf7801`) | ⬜ Not started |
| T7 | Customizer — 5 settings: `palette` (7), `accent`, `radius`, `font`, `cta_reveal` | ⬜ Not started |
| T8 | Full gate (phpcs · phpstan L8 · unit · vitest · build · integration · integration-dev · base-isolation e2e · e2e-dev · e2e-woo) → Codex critic + re-critic → push + PR. Merge is Maksim's call | ⬜ Not started |

T0→T1→T2 sequential; T3 parallel with T1/T2; T4–T6 parallelisable once T1–T3 land; T7 after T1; T8 last. Then M2b + M3 (remaining Woo flow polish + release prep). i18n cross-cutting; `.pot` deferred to M3.

## Last session

s10 (25.07.2026): pivoted from patching the storefront CSS to designing the **whole-theme visual identity** via Open Design. Rewrote `woo.css` un-layered (beats Woo's un-layered CSS) + removed Woo sidebar (`faf7801`); wrote `DESIGN.md` (`fff851f`). Ran 3 OD mockups (v1/v2/v3) — **Maksim chose v2 «Обиход» and refined it** (warm palette + 7 colour-palette presets, plates fixed, hover-reveal over price, Customizer-ready tokens). **Approved mockup lives in `docs/design/v2-mockup/`** — the design source of truth. Next = implement it into the theme. Nothing merged. See SESSION-LOG.
