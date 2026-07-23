# Current State — Woodev Base

> Updated: 22.07.2026 (s7)

## Phase status

| Milestone | Status | Notes |
|---|---|---|
| Design & decisions | ✅ Done | Spec approved, ADR-001…006 recorded |
| M0 — Bootstrap | ✅ Done | PR [#1](https://github.com/kalbac/woodev-base-theme/pull/1) merged s3 |
| M1 — Core theme | ✅ Done | 5 plans, all merged: icons `96df1db`, templates `f3f5f0a`, style packs `1fd9dd8`, Customizer `e480b3a`, scheme switcher `11ce459` |
| Dev-mode coverage | ✅ Done | s7, PR [#10](https://github.com/kalbac/woodev-base-theme/pull/10) `e1cf31b` — the s3 debt, closed |
| §7 component tail | ✅ Done | s7, PR [#11](https://github.com/kalbac/woodev-base-theme/pull/11) `6dfac28` — card/badge/alert/comment-form. tabs+accordion deferred to M2 |
| M2a — Woo storefront | 🟡 Design+plan done, unbuilt | s7: [spec](specs/2026-07-23-m2a-woo-storefront-design.md) + [plan](plans/2026-07-23-m2a-woo-storefront.md) on `main` (`bbe67e0`,`a586049`). **Next session executes** — create a fresh branch, start at plan Task 1 |
| M2b — Woo checkout flow | ⬜ Not started | cart/checkout/account/store-notices + Woo Customizer section |
| M3 — Public release prep | ⬜ Not started | |

## Known bugs

**None open.** `main` is green, verified on the MERGED commit `6dfac28` and not just per-branch: phpcs 0 · phpstan L8 · unit **146** · vitest 25 · integration **35** · integration-dev 4 · e2e **44** · e2e-dev 2 · build OK.

s7's near-miss is worth carrying: the new `ScriptModuleGuard` reflected on `WP_Script_Modules::$done`, which **exists only from WP 6.9** while the theme declares `Requires at least: 6.8`. Every test using it would have died with `ReflectionException` on the floor we claim to support. Local runs cannot see this — wp-env uses `core: null`, i.e. latest — and neither can CI, which does not matrix the floor. **Nothing in this project currently tests the declared WP floor**; that is now the most valuable untested claim we make.

s5 found and fixed one real defect after merging — the mobile-drawer focus-trap e2e was red on merged `main` while green on each branch alone. Not a product regression: `x-trap` moves focus asynchronously and a premature `Tab` lands on the skip link, outside the nav (`docs/gotchas/x-trap-focus-move-is-async.md`, PR #7 `9dc2f3b`). Codex also caught a would-be **fatal on every front-end request** before merge — `(string) get_theme_mod()` throws `Error` for an object; now fails closed.

## Deferred, tracked

- ~~**Dev mode has no integration/e2e coverage**~~ — resolved s7, closing a Codex P2 open since s3. Integration: `tests/integration/Integration/DevMode/AssetsDevModeTest.php` via `npm run test:integration:dev` (a second PHPUnit config whose bootstrap defines the constant — never wp-env's `config` key, which leaks into both environments and persists), mirrored by `Integration/AssetsProductionTest.php`. e2e: `tests/e2e-dev/dev-mode.spec.mjs` via `npm run e2e:dev`, against `.wp-env.dev-mode.json` on :8892 with Playwright owning a live Vite dev server. The e2e asserts **computed style**, since the defect class it guards has the script tag present and the styles absent.
- **Customizer overrides do nothing in dev mode.** Vite serves the pack CSS as a JS module that injects its `<style>` when the module EXECUTES — after `InlineStyles`' block was parsed — so `tokens.generated.css` wins on source order. Production is unaffected and an e2e mutation pins it (moving the block to `wp_head` 5 turns the accent assertion red). Raising selector specificity would fix dev at the cost of every real site's override path (Additional CSS), which is the wrong trade — see `InlineStyles`' docblock.
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

Dev-mode coverage, the §7 component tail, and the M2a design+plan all landed in s7.

1. **Execute M2a** — the plan is written and on `main` (`docs/plans/2026-07-23-m2a-woo-storefront.md`), built on WooCommerce 10.9.4 contracts already read from the installed plugin. Start at Task 1 (stand up `.wp-env.woo.json`, seed the demo store). Subagent-driven. The storefront: shop grid of cards + single product with gallery/summary/tabs; one template override (the product card); Woo bundle on Woo contexts only; separate Woo e2e so the base stays Woo-free.
2. **Then M2b** — cart/checkout/account/store-notices + the Woo Customizer section (spec §8). **tabs/accordion** land in M2a as restyled native Woo tabs, not a separate component.

i18n is cross-cutting — required in every task, `.pot` generation deferred to M3.

## Last session

s7 (22–23.07.2026): two features designed, planned, executed subagent-driven and merged — dev-mode coverage (PR #10, `e1cf31b`, the s3 debt gone) and the §7 component tail (PR #11, `6dfac28`). Things to carry: a guard used a WP **6.9+** API under a **6.8** floor and no gate could see it; the three-rounds rule fired a second time (on `AssetMarkup`) and again the answer was to delete a requirement; the s6 Serena line-ending gotcha was simply wrong; and phpcs missed unescaped `__()` output because the strings passed through a variable into `comment_form()` rather than being echoed. See SESSION-LOG.
