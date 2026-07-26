# Current State — Woodev Base

> Updated: 26.07.2026 (s14)

## Phase status

| Milestone | Status | Notes |
|---|---|---|
| Design & decisions | ✅ Done | Spec approved, ADR-001…006 recorded |
| M0 — Bootstrap | ✅ Done | PR [#1](https://github.com/kalbac/woodev-base-theme/pull/1) merged s3 |
| M1 — Core theme | ✅ Done | 5 plans, all merged: icons `96df1db`, templates `f3f5f0a`, style packs `1fd9dd8`, Customizer `e480b3a`, scheme switcher `11ce459` |
| Dev-mode coverage | ✅ Done | s7, PR [#10](https://github.com/kalbac/woodev-base-theme/pull/10) `e1cf31b` — the s3 debt, closed |
| §7 component tail | ✅ Done | s7, PR [#11](https://github.com/kalbac/woodev-base-theme/pull/11) `6dfac28` — card/badge/alert/comment-form. tabs+accordion deferred to M2 |
| Design — whole-theme visual identity | ✅ Approved (s10) | Refined V2 «Обиход» in `docs/design/v2-mockup/`. Golos Text + IBM Plex (OFL, Cyrillic), token-driven, 7 palette presets, hover-reveal CTA, all pages. Source of truth for implementation |
| Identity implementation (T0–T8) | ✅ T0–T8 done | ADR-007 (fonts) + ADR-008 (identity replaces the 8 style packs) recorded. Plan: `docs/plans/2026-07-25-visual-identity.md`. All four areas criticked and re-criticked (s12); test debt paid s13. **PR [#24](https://github.com/kalbac/woodev-base-theme/pull/24) open, 27 commits. Merge is Maksim's call** |
| M2a — Woo storefront | ✅ Done, in PR #24 | Classic storefront on the approved identity. The block cart/checkout gap is M2b, see below |
| M2b — Woo block cart & checkout | ✅ Done (s14) | [ADR-009](adr/ADR-009-block-cart-checkout-styling.md) implemented B0–B6 on `feat/m2b-block-cart-checkout`, 7 commits. Criticked **and** re-criticked. Not merged, no PR yet — sits on top of the still-open PR #24 |
| M3 — Public release prep | ⬜ Not started | |

## Known bugs

**None open.** `main` is green, verified on the MERGED commit `6dfac28` and not just per-branch: phpcs 0 · phpstan L8 · unit **146** · vitest 25 · integration **35** · integration-dev 4 · e2e **44** · e2e-dev 2 · build OK.

**Branch `feat/m2a-woo-storefront` (PR [#24](https://github.com/kalbac/woodev-base-theme/pull/24)) — every suite run on the current tree, s13, one at a time:** phpcs **0** · phpstan L8 **0** · eslint **0** · prettier (on the files we own) · unit **204** (1 skip) · vitest **56** · integration **37** (1 skip) · integration-dev **4** · base e2e **57/57** in one pass · **e2e:woo 16/16 in one pass** · e2e-dev **2/2** · build OK.

**Branch `feat/m2b-block-cart-checkout` (M2b, 7 commits on top of the above) — every suite run by the orchestrator, s14:** phpcs **0** · phpstan L8 **0** · eslint **0** · prettier clean on the files we own · unit **208** (1 skip) · vitest **56** · integration **40** (1 skip) · **e2e:woo 23/23** (16 storefront + 7 new block-surface tests) · build OK, `wooBlocks` bundle **11.91 KB**, zero Tailwind preflight, zero `!important`, zero rules outside the two block scopes.

**The critic gate is CLOSED for s13 and for M2b.** Eight passes ran in s14 — s13's code, B0, B1 CSS, B1 PHP, B2, B3–B5, B6, plus a re-critic of the fixes. Six clean, two with findings (2 on B1 PHP, 2 on B6), all four resolved: two fixed, one rejected with an AGENTS.md carve-out, one an ADR-009 amendment. The re-critic came back clean — **the first round on this project that did not find a defect inside the fixes**, which is worth watching rather than trusting. One caveat recorded honestly: s13's own diff was criticked at the DEFAULT reasoning effort, not `high`, because the quota ran out mid-re-run. Everything M2b ran at `high`.

**The gate was broken for the wrong reason all morning, and it is worth not re-learning.** `/codex:adversarial-review` returns `verdict: approve` whose body admits it read nothing — the sandbox denies Codex's shell, and the plugin asks Codex to go and read the diff. The working route is inline-on-stdin with a NO-TOOLS preamble and an explicit `model_reasoning_effort="high"`; see [`codex-critic-needs-inline-stdin-and-explicit-effort`](gotchas/codex-critic-needs-inline-stdin-and-explicit-effort.md). A `CODEX_OK` smoke test distinguishes "sandbox" from "quota" in one command — do that before concluding anything about the account.

**Base e2e (:8888) was NOT run in s14.** Nothing in the base theme changed, but it has not been re-verified on this branch and should run before merge.

**Suites still contend for Docker.** Run them ONE AT A TIME even on different wp-env configs — s12 lost a run to a stall, and s13 watched wp-cli calls slow to a crawl with 15 containers up. Stop the environments you are not using.

**`npm run format` is green as of s15, and it was never optional.** Recorded for two sessions as "red on 5 files, not in the documented gate battery" — but CI's `js-qa` job runs `prettier --check .`, so it was a gate, it was failing, and because the `e2e` job declares `needs: js-qa`, **CI has never run the base e2e on PR #24 at all**. Fixed in `dc6f889`: the three `docs/design/v2-mockup/*` exports are `.prettierignore`d (approved artifacts, ADR-008 — not ours to reformat) and `scripts/lib/build-tokens-lib.mjs` is formatted, line-wrapping only.

Two measurement traps came out of that, both worth keeping. **Prettier 3 reads `.gitignore` as well as `.prettierignore`** — so once `opencode.json` was git-ignored, `prettier --check opencode.json` printed "All matched files use Prettier code style!" while matching zero files. That message means "nothing failed", never "your file is clean", and it is indistinguishable from a pass. And **a docs line saying a check is "not in the gate battery" is a claim about CI that only CI can settle** — read `.github/workflows/ci.yml`, not the note.

**What the critic gate produced (all four areas now criticked AND re-criticked):**
- **Woo layer** — 4 real defects, then 3 more inside the fixes: the shortcode/block product loop shipping without CSS or `data-cta`; a priority window swallowing third-party buttons; the same repair then wrapping the Product Button BLOCK's markup in an inline span; a placeholder sprite unreachable from REST-rendered blocks.
- **Asset/build wiring** — the licence written from memory (then a circular test for it), a WOFF2 check that a 4-byte file passed, a missing preload, and that preload ignoring the `font=system` setting.
- **adapter CSS** — 12 defects, 2 of them P0, and **both P0s were re-critic findings inside the first repair**: an invalid field's focus indicator vanishing in forced-colors mode, and a mobile-menu height cap computed from a `padding-top` the header bar does not have.
- **`woo.css`** — 7 cascade-race defects plus the Tailwind import that made the bundle 45,895 bytes of un-scoped preflight. **One repair existed only as a comment** — the prose claimed (0,4,3), the selector stayed (0,3,0) — caught by an e2e assertion on computed `display`, not by either critic.

**M2b researched and decided (s13), against the installed WooCommerce 10.9.4 and the live `:8891` store — [ADR-009](adr/ADR-009-block-cart-checkout-styling.md).** The scope finding is worse than s12 recorded and the fix is smaller than feared:

- The Cart/Checkout block trees declare **no design supports at all** — no `color`, `typography`, `spacing` or border, across all 40+ inner blocks. There is no block-supports route.
- `theme.json` → `styles.blocks["woocommerce/checkout"]` **does** emit and apply, but only to the wrapper, at specificity **(0,1,0)**. Verified by patching `theme.json` and reading computed style: the wrapper changed colour and padding; the place-order button and the text input did not.
- **Not one byte of `woo.css` can reach those pages.** All 184 top-level rules in the BUILT bundle require a `.woocommerce` ancestor, and the block checkout page carries no element with that class. Block surfaces need a new top-level scope on `.wp-block-woocommerce-*` — see the gotcha.
- Our stylesheets already load **last** in `<head>` on both `/cart/` and `/checkout/`, so the un-layered specificity-mirroring approach carries over unchanged.
- The blocks lean on `currentColor`/`inherit`, so type, colour and our Golos Text already reach them in both schemes. The broken set is small and specific — inputs, selects, checkboxes, buttons, notices, radii — and **only one of them is a real defect rather than an off-brand look: a white input with near-black text on a dark page.**
- The classic path stays first-party supported (shortcodes plus the `woocommerce/classic-shortcode` block, `enum: ["cart","checkout"]`), so the classic CSS is not dead code. Two branches, both maintained.
- `.wc-block-*` churn measured 9.4.0 → 10.9.4: **94%** of checkout classes and **85%** of cart classes survived.
- **Progressive enhancement cannot hold on the block checkout** — it ships zero `<input>`s server-side. That is WooCommerce's architecture; the classic branch is the PE-friendly option.

Plan: `docs/plans/2026-07-25-block-cart-checkout.md`. Implementation is the next branch.

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

- **The `/codex:*` plugin does NOT work as the critic gate here — corrected s14.** It returns `verdict: approve` having read nothing, because it asks Codex to open the diff and the Windows sandbox denies every child process (`CreateProcessAsUserW failed: 5`). Run the critic directly instead: whole diff on **stdin**, a NO-TOOLS preamble, foreground, and an explicit `model_reasoning_effort="high"` — the default does ~1/3 the work and its verdict is indistinguishable. Chunks of 18–26 KB are fine; name the out-of-chunk guards in every chunk. Full recipe and the smoke test that tells "sandbox" from "quota" apart: `docs/gotchas/codex-critic-needs-inline-stdin-and-explicit-effort.md`.
- **Bash needs a separate safety classifier in auto mode.** When `claude-sonnet-5[1m]` is unavailable, every Bash call is refused with "auto mode cannot determine the safety" — nothing to do with the session model or with Codex. Read-only tools keep working. Wait it out, switch permission mode, or have Maksim run the command with `! …`.
- **Codex history (still true when going direct): use the DEFAULT profile with MCP disabled.** `codex exec -c 'mcp_servers={}' "…"`. The s3 recipe's clean `CODEX_HOME=~/.codex-review-clean` has its **own** `auth.json`, which goes stale independently — s6 lost an hour to "refresh token already used" there while the default profile was freshly authorised. The 403s that appear alongside come from an **MCP worker**, not the model, which is what `mcp_servers={}` silences. Everything else from the s3 recipe still holds: foreground, prompt inline and **under ~15 KB**, stdin closed, smoke-test with `"Reply with exactly: CODEX_OK"` first (every failure mode exits 0 — `codex-cli-dies-silently.md`), and name the out-of-chunk guards in every chunk prompt (`codex-split-diff-false-positives.md`).
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
| T2 | Retire the pack machinery (`src/css/packs/`, `scripts/build-pack-entries.mjs`, `scripts/lib/packs-lib.mjs`, `style_preset`) → one `src/css/app.css` entry on `basecoat-css/base` | ✅ Done |
| T3 | Fonts (ADR-007): `scripts/build-fonts.mjs`, self-hosted Golos Text + IBM Plex Sans/Mono, `src/css/fonts.css`, OFL licenses. 20 woff2, **352 KB shipped / ~132 KB fetched** by a Russian page — over the ADR's original estimate, which is now restated with measured numbers + an M3 `pyftsubset` plan. Idempotency and all 20 built-CSS `url()`s verified by hand | ✅ Done |
| T4 | Base + layout surfaces → `adapter/{base,header,hero,blocks,content,footer}.css` + template pass. Hero/category-tiles/promo CSS is ported but **not wired to any template** — no front-page merchandising markup exists yet | ✅ Done |
| T5 | Component kit → `adapter/{buttons,forms,feedback,components}.css`, incl. tabs + accordion (the M1 §7 deferral). Basecoat's leftover tokens ruled on: nothing overridden, reasons in the plan | ✅ Done |
| — | **Integration (orchestrator):** `adapter/index.css` rewired — 10 imports, superseded blocks deleted (skip-link, nav, scheme-toggle, entry-card bits, comment bits), container/layout/post-grid deliberately kept below the imports. Verified: no selector collides across the 10 files; build green; the ported CSS is in the bundle and inside `@layer adapter` | ✅ Done |
| T6 | WooCommerce surfaces — shop/product/cart/checkout/account, hover-reveal CTA (static under `@media (hover: none)`), placeholder sprite. `woo.css` rewritten un-layered; Woo form controls + store notices live there, not in the adapter | ✅ Done |
| T7 | Customizer — 5 settings: `palette` (7), `accent` (hex→oklch), `radius` (px, renamed from `radius_scale`), `font`, `cta_reveal`. Each sanitised and resolved by one function | ✅ Done |
| T8 | Gate. **Critic complete for s10–s12** (10 Codex chunks, every fix re-criticked). s13 ran the whole battery on the current tree — all green, numbers above — strengthened the over-claiming assertions with mutation verification, and opened **PR [#24](https://github.com/kalbac/woodev-base-theme/pull/24)**. Remaining before merge: `/codex:review` on s13's own three commits | ✅ Done, PR open |

T0→T1→T2 sequential; T3 parallel with T1/T2; T4–T6 parallelisable once T1–T3 land; T7 after T1; T8 last. Then M2b + M3 (remaining Woo flow polish + release prep). i18n cross-cutting; `.pot` deferred to M3.

## Last session

s14 (26.07.2026): built M2b end to end (B0–B6, 7 commits on
`feat/m2b-block-cart-checkout`), closed the critic gate for both s13 and M2b through a
working direct-`codex exec` route, and corrected ADR-009's button finding.

**Next session starts here:**
1. **Merge decisions are Maksim's, and there are two.** PR
   [#24](https://github.com/kalbac/woodev-base-theme/pull/24) (M1 identity + M2a) is still
   open; `feat/m2b-block-cart-checkout` sits on top of it with no PR yet. Before either
   merges: run the base e2e (`npm run e2e`, :8888) — it was not run in s14 — and, if you
   want the belt-and-braces version, re-critic s13's own diff at `high` effort, since its
   only pass ran at the default.
2. **Then M3, release prep** — the last milestone. `pyftsubset` re-instancing (ADR-007,
   closes [#17](https://github.com/kalbac/woodev-base-theme/issues/17)), the `.pot` file,
   wp.org Theme Review compliance sweep.
3. **Backlog worth pulling in:** [#26](https://github.com/kalbac/woodev-base-theme/issues/26)
   (core's theme.json paints every `.wp-element-button` — already in Бэклог),
   [#25](https://github.com/kalbac/woodev-base-theme/issues/25) (theme.json presets follow
   neither the Customizer nor the dark scheme — solve with #26 in one pass),
   [#23](https://github.com/kalbac/woodev-base-theme/issues/23) (e2e setup breaks on POSIX),
   [#27](https://github.com/kalbac/woodev-base-theme/issues/27),
   [#28](https://github.com/kalbac/woodev-base-theme/issues/28),
   [#18](https://github.com/kalbac/woodev-base-theme/issues/18),
   [#13](https://github.com/kalbac/woodev-base-theme/issues/13).

See `next-session-promt.md` for the full handoff, including the traps this session paid for.
