# Current State — Woodev Base

> Updated: 22.07.2026 (s5)

## Phase status

| Milestone | Status | Notes |
|---|---|---|
| Design & decisions | ✅ Done | Spec approved, ADR-001…006 recorded |
| M0 — Bootstrap | ✅ Done | PR [#1](https://github.com/kalbac/woodev-base-theme/pull/1) merged s3 |
| M1 — Core theme | 🟡 In progress | 5 plans. Harness (PR #2), icons M1-01 (`96df1db`), templates M1-02 (`f3f5f0a`) and **style packs M1-03 (PR [#5](https://github.com/kalbac/woodev-base-theme/pull/5), `1fd9dd8`, s5)** done. M1-04/05 not started |
| M2 — WooCommerce layer | ⬜ Not started | |
| M3 — Public release prep | ⬜ Not started | |

## Known bugs

**None open.** `main` is green: phpcs 0, phpstan L8, unit 92, integration 15, vitest 10, e2e 23, build OK.

s5 found and fixed one real defect after merging — the mobile-drawer focus-trap e2e was red on merged `main` while green on each branch alone. Not a product regression: `x-trap` moves focus asynchronously and a premature `Tab` lands on the skip link, outside the nav (`docs/gotchas/x-trap-focus-move-is-async.md`, PR #7 `9dc2f3b`). Codex also caught a would-be **fatal on every front-end request** before merge — `(string) get_theme_mod()` throws `Error` for an object; now fails closed.

## Deferred, tracked

- **`Layout::has_sidebar()` is `! is_page()`, broader than spec §7's "blog/archive/single"** — Codex P1 on M1-02, kept for v1 with Maksim's sign-off. Unit-tested. **Narrow in M1-04** if the Customizer work wants it — one-liner + test-mock update.
- **Dev mode has no integration/e2e coverage** — Codex P2 from s3, accepted as real. The dev path is pinned only by unit tests with a mocked `wp_enqueue_script_module()`. Needs a second wp-env environment carrying `WOODEV_BASE_DEV`; the `.wp-env.<variant>.json` mechanism it needs now exists and is proven, so this is **unblocked**. Manual recipe: `docs/gotchas/wp-env-config-constants-persist.md`.
- **Container width is a hard-coded default** — `.wtb-container` max-width is `90rem`/1440px in `src/css/adapter/index.css` (raised from 1024px in s5, PR #6). Spec §6 lists container width as a Customizer setting (Layout section) — **make it configurable in M1-04**. It lives in exactly one place; `theme.json` declares no `contentSize`/`wideSize`, so there is nothing to keep in sync yet.
- **e2e style-packs isolation** — `tests/e2e/style-packs.spec.mjs` mutates a site-global `theme_mod` while other spec files run in parallel workers. Safe today only because no other spec asserts anything pack-specific; the file carries an explicit ISOLATION CAVEAT. Re-critic accepted the YAGNI call. **If a future spec pins a bundle name or Basecoat geometry, it needs its own Playwright project or `--workers=1`.**

## Open items

- **Codex tooling works, with the s3 recipe.** Clean `CODEX_HOME=/c/Users/maksi/.codex-review-clean` (`model = "gpt-5.6-sol"`), foreground, prompt inline and **under ~15 KB**, stdin closed. s5 ran 4 reviews this way with zero failures. Smoke-test with `"Reply with exactly: CODEX_OK"` first — every failure mode exits 0 (`docs/gotchas/codex-cli-dies-silently.md`). A 26 KB code diff had to be **split into 3 chunks**; name the out-of-chunk guards in every prompt or you get false positives (`codex-split-diff-false-positives.md`) — s5 got none by doing this.
- **Codex reads project files during review.** It pulled `docs/CURRENT-STATE.md` and `.claude/skills/*/SKILL.md` on its own, which bloats output (one run returned 186 KB). Tell it explicitly not to read skills when you don't want that.
- **Line endings**: `.gitattributes` pins `eol=lf`. Beware: a Python helper writing files in text mode on Windows silently emits CRLF and PHPCS fails on line 1 (hit twice in s5 — write bytes, or set `newline=''`).
- ~~Pin concrete WP floor~~ — resolved s2: **6.8** (`Requires at least`), tested up to **7.0**. Re-check each release.
- ~~Basecoat pin~~ — resolved s1: exact `1.0.2`. ~~M1 inventory~~ — resolved s1: spec §7. ~~Fonts/icons~~ — s1: system stack, Lucide (ISC). ~~wp-env config shape~~ / ~~PHPUnit 10.5 vs core suite~~ — resolved s3.

## Next actions

**M1 is five plans.** Each leaves the theme working:

| # | Plan | State |
|---|---|---|
| M1-01 | Lucide icon helper | ✅ merged `96df1db` (s4) |
| M1-02 | Templates & parts | ✅ merged `f3f5f0a` (s4) |
| M1-03 | 8 Basecoat style-pack bundles + adapter | ✅ merged `1fd9dd8` (s5); Codex 3 chunks + re-critic |
| M1-04 | Customizer v1 (§6) | not written — **next** |
| M1-05 | Scheme switcher + no-FOUC head script | not written |

1. **Write and execute M1-04 (Customizer v1).** It has the most leverage now: the `style_preset` engine is built and only lacks a control, and M1-02's header/footer/sidebar `theme_mod`s are already read. Fold in the three deferred items above that belong here — narrow `has_sidebar()`, make container width a setting, and add `primary_preset` (§6) which layers on top of the active pack.
2. **Dev-mode integration coverage** — small unblocked follow-up; can slot before or after M1-04.
3. **M1-05** (scheme switcher) last — it drops into the documented slot already left in both header variants (`template-parts/header/{inline,centered}.php`, grep `M1-05 inserts`).

i18n is cross-cutting — required in every task, `.pot` generation deferred to M3.

## Last session

s5 (21–22.07.2026): M1-03 planned and executed subagent-driven, merged with two follow-ups (container width 1440px, e2e focus-trap race). Key correction: Basecoat's 8 packs share one palette and differ only in component **shape**, so a pack switch is invisible without Basecoat classes on the page — hence the `.btn` surfaced in this PR. See SESSION-LOG for the Codex findings and the two process notes.
