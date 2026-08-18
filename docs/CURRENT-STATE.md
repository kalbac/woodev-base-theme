# Current State — Woodev Base

> Updated: 18.08.2026 (s20)

## Phase status

| Milestone | Status | Notes |
|---|---|---|
| Design & decisions | ✅ Done | Spec approved, ADR-001…006 recorded |
| M0 — Bootstrap | ✅ Done | PR [#1](https://github.com/kalbac/woodev-base-theme/pull/1) merged s3 |
| M1 — Core theme | ✅ Done | 5 plans, all merged: icons `96df1db`, templates `f3f5f0a`, style packs `1fd9dd8`, Customizer `e480b3a`, scheme switcher `11ce459` |
| Dev-mode coverage | ✅ Done | s7, PR [#10](https://github.com/kalbac/woodev-base-theme/pull/10) `e1cf31b` — the s3 debt, closed |
| §7 component tail | ✅ Done | s7, PR [#11](https://github.com/kalbac/woodev-base-theme/pull/11) `6dfac28` — card/badge/alert/comment-form. tabs+accordion deferred to M2 |
| Design — whole-theme visual identity | ✅ Approved (s10) | Refined V2 «Обиход» in `docs/design/v2-mockup/`. Golos Text + IBM Plex (OFL, Cyrillic), token-driven, 7 palette presets, hover-reveal CTA, all pages. Source of truth for implementation |
| Identity implementation (T0–T8) | ✅ **Merged s15** | ADR-007 (fonts) + ADR-008 (identity replaces the 8 style packs). Plan: `docs/plans/2026-07-25-visual-identity.md`. All four areas criticked and re-criticked (s12); test debt paid s13. PR [#24](https://github.com/kalbac/woodev-base-theme/pull/24) squashed onto `main` as `f040eaa` |
| M2a — Woo storefront | ✅ **Merged s15**, in `f040eaa` | Classic storefront on the approved identity. The block cart/checkout gap was M2b |
| M2b — Woo block cart & checkout | ✅ **Merged s15** | [ADR-009](adr/ADR-009-block-cart-checkout-styling.md) implemented B0–B6, criticked **and** re-criticked. PR [#29](https://github.com/kalbac/woodev-base-theme/pull/29) squashed onto `main` as `1d769ae` |
| M3 — Public release prep | 🟡 In flight | Plan: `docs/plans/2026-07-26-m3-release-prep.md`. **R1 done and merged** ([ADR-010](adr/ADR-010-theme-json-identity.md), closes #26 + #25). **R4 largely done** — `readme.txt`, direct-access guards, Theme Check run, `comment-reply`, the eight core CSS classes; screenshot deferred to [#36](https://github.com/kalbac/woodev-base-theme/issues/36). **R2 measured and cut down** to a provenance question ([#17](https://github.com/kalbac/woodev-base-theme/issues/17)). **R3 done s16** — audit + a guard test; the `.pot`/`.po`/`.mo` stay Maksim's, in Poedit. Remaining: version bump off `0.1.0`, the `Tags:` list against wp.org's current allowed set, and the screenshot |
| Front page (#18) | ✅ **Done s17**, merged `b4c592c` | Hero (eyebrow, lede, trust badges, art column), value band, promo, category tiles with plate art. Ten Customizer settings defaulting to EMPTY, every section self-suppressing. `Templates\Plate` ports the mockup's eight plates byte-identically. Coverage (#37) in the same PR [#39](https://github.com/kalbac/woodev-base-theme/pull/39). |
| Front page sections (#40) | ✅ **Done s20**, branch `feat/front-page-sections` | Popularity-sourced Woo product picks, three-post Journal with deterministic Plate fallbacks, and an optional registered third-party newsletter shortcode. Surface probe, Woo-free integration, and four new Woo e2e checks added. |
| Catalogue + product page (#41) | ✅ **Done s18**, merged `042c1a1` | PR [#44](https://github.com/kalbac/woodev-base-theme/pull/44). Filter rail (`sidebar-shop`, holding WooCommerce's own filter widgets — the theme builds no filtering), subcategory chips, `−24%` sale badge, breadcrumb separator, pagination chevrons with accessible names, card category eyebrow. Product page: breadcrumb + sale badge into the buy box, SKU by the rating, savings badge, quantity stepper, `<dl>` meta, 64px thumbnail column, two default-empty trust badges. Two template overrides only. **Three defects that had already shipped were fixed on the way** — see Known bugs |
| Cart, checkout, account, receipt (#42) | ✅ **Done s19**, merged `e800e09` | PR [#50](https://github.com/kalbac/woodev-base-theme/pull/50) squashed onto `main`. Plan: `docs/plans/2026-07-28-cart-checkout-account.md` — 38 nodes walked, verdict CSS/hook/override each. **Classic (shortcode) branch only** — a default Woo 10.9.4 install serves the cart and checkout as BLOCKS, where [ADR-009](adr/ADR-009-block-cart-checkout-styling.md) already bounds what is reachable and `woo-blocks.css` owns it. Account and order-received are classic regardless. `woo.css` split into an index + five partials (verified a byte-identical bundle). Three template overrides added (`myaccount/{navigation,dashboard,view-order}.php`), taking the theme to five. 32/32 new e2e green |
| **Pages vs the approved mockup** | 🟡 **One gap left** | Operator verdict s17 was **4/10, still a skeleton**; s18 closed the catalogue and product page (#41), s19 closed cart/checkout/account/receipt (#42), s20 closed the final front-page sections (#40). Remaining: blog/text pages never compared against the mockup → [#43](https://github.com/kalbac/woodev-base-theme/issues/43). All designed in detail in `docs/design/v2-mockup/` |

## Known bugs

**None open.** `main` is at **`e800e09`** (s19, the classic commerce surfaces —
PR [#50](https://github.com/kalbac/woodev-base-theme/pull/50) squashed, #42 closed).

### s20 measurements — front-page sections and gate battery

- **Implementation branch** `feat/front-page-sections`: build, PHPCS **122 files**, PHPStan L8, ESLint, Prettier, `tokens:check`, Vitest **64**, PHP unit **516** (1704 assertions, 1 skip), and integration **74** (255 assertions, 1 skip) all green.
- **Base e2e** **63/63** passed against the Woo-free environment.
- **New Woo front-page e2e** **4/4** passed: four popularity picks, three Journal cards, newsletter suppression, and phone-width one-column geometry.
- **Surface probe** captured all nine commerce/front surfaces at 1280×900. The browser caught the front-loop scope defect: the unscoped product list was block layout and made the page 7421px tall; after the standard `woocommerce` wrapper it became a four-track grid and the page measured 2498px.
- The full Woo run without a fresh global setup reached **65 passed** but had seven stale-fixture failures because `global-setup` stalled at `wp widget add` before product reseeding. Treat [#48](https://github.com/kalbac/woodev-base-theme/issues/48) and the existing wp-env CLI gotcha as the gate caveat; the new front-page suite itself is green.

### s19 measurements — CI green on all four jobs of PR #50, read by COUNTS

- **CI** (branch head `84e2614`, the commit that was squashed) — `php-qa` unit **508** (1664
  assertions) · `js-qa` vitest **64** in 3 files ·
  `php-integration` **69** (235 assertions, 1 skip) · `e2e` **63 passed** in 2.9m — it ran, which is
  worth stating each time, since that job declares `needs: js-qa` and has silently skipped on a PR
  before. phpcs / phpstan L8 / eslint / prettier / `tokens:check` all 0.
- **Local on the same tree** — unit **508** (1662 assertions; the 2-assertion difference is the one
  test that skips on Windows, not a failure) · **new e2e 32/32**: `cart-checkout.spec.mjs` **17**
  (13.2m) and `account-receipt.spec.mjs` **15** (5.9m), against the reseeded `:8891` store.
- **CI still does not run `e2e:woo` at all** ([#48](https://github.com/kalbac/woodev-base-theme/issues/48)),
  so those 32 exist only as a local measurement — the same caveat s18 recorded for its 37. Between
  them that backlog item is now worth **69** Woo e2e tests and the only guards on eight shipped
  defects.

**Five defects that were already live on `main` were found and fixed on the way, none of them visible
to any gate:**

- **The commerce screens were 694px wide inside a 1248px article.** They are ordinary PAGES carrying
  a shortcode, so they render through `.wtb-entry-content` and inherited its `max-width: var(--measure)`.
  The cart table's six columns overflowed *under* the totals card. The mockup only measures `.article`
  and explicit `.prose` blocks — see [`commerce-pages-inherit-the-prose-reading-measure`](gotchas/commerce-pages-inherit-the-prose-reading-measure.md).
- **A publication date and an empty byline printed on the cart and checkout** ("WTB Classic Cart /
  JULY 28, 2026 BY") — `content.php` printed post meta on every page. Now `'post' === get_post_type()`.
- **`woo.js` was never enqueued on the cart**, so C4's stepper buttons rendered with `hidden` and
  nothing removed it. `wp_enqueue_scripts` cannot answer "will the cart render" — the shortcode has
  not run yet, so `is_cart()` falls back to a page-id comparison. Enqueued from `woocommerce_before_cart`
  instead.
- **The payment section sat on WooCommerce's lavender slab**, with the chosen method's description as
  a lavender tooltip. Woo scopes those on an **ID**, so this theme's class-only rules lost on
  specificity and had *never applied on a checkout* — a second mechanism now recorded in
  [`source-order-only-wins-the-properties-you-redeclare`](gotchas/source-order-only-wins-the-properties-you-redeclare.md).
- **Woo's clearfix `::before`/`::after` became grid items** on `.woocommerce` and on `ul.order_details`
  the moment those were gridded — the account nav rendered top-right and the content bottom-left while
  `gridTemplateColumns` measured correct. **Third occurrence** of this in the project →
  [`woo-clearfix-pseudo-elements-become-grid-items`](gotchas/woo-clearfix-pseudo-elements-become-grid-items.md).

**The critic gate ran 6 chunks plus a re-critic, all at `high`.** It found one real product defect —
`Receipt::actions()` had no failed-order guard, and `woocommerce_thankyou` fires AFTER `thankyou.php`'s
failed/success `endif`, so a declined payment would have been offered "Track order" beneath Woo's own
"payment declined" notice ([gotcha](gotchas/woocommerce-thankyou-fires-for-failed-orders-too.md)) — one
real media-query gap (`max-width: 63.9375rem` is not the complement of `min-width: 64rem`; a 1023.5px
viewport matched neither), **three more vacuous assertions**, and six false factual claims in comments.
**The re-critic found a defect inside the fixes, as every round in this project has.** Five findings
were rejected with reasons, one of them caused by an incomplete token list in the orchestrator's own
preamble rather than by the code — record that as a cost of the prompt, not of the review.

### s18, kept for the trend: CI green on all four jobs of PR [#44](https://github.com/kalbac/woodev-base-theme/pull/44), read by COUNTS rather than by the tick:

- **CI** — `e2e` **63 passed** in 4m44s (it ran; this is the job behind `needs: js-qa` that has silently
  skipped on a PR before) · `php-integration` **69** (235 assertions, 1 skip) · `php-qa` unit **396** (1256
  assertions) · `js-qa` · phpcs/phpstan L8/eslint/prettier/`tokens:check` all 0.
- **Local on the same tree** — **e2e:woo 37/37**, against a store reseeded end to end by the edited fixture.
  CI still does not run this suite → [#48](https://github.com/kalbac/woodev-base-theme/issues/48).

Unit assertions differ by platform (1254 locally on Windows, 1256 in CI) because one test skips on Windows.
Neither is a failure; the same test COUNT means slightly different work on each.

**s18 fixed three defects that had already shipped on `main`, and all three were invisible to every green
suite.** They are the reason the new gotcha exists:

- **Product tabs rendered as a vertical stack.** WooCommerce names its tab list `class="tabs wc-tabs"`, and
  `.tabs` is **also Basecoat's tabs component**, contributing `flex-direction: column`. `woo.css` re-declared
  `display: flex` — which changed nothing and masked the collision — and never touched the direction.
- **The sale badge was a full-width red bar on every catalogue card.** Woo's
  `.woocommerce ul.products li.product .onsale` sets `right: 0` at our own specificity. Ours won on source
  order for the three properties it re-declared; `right` was not one of them, and an absolutely positioned box
  with both insets set and `width: auto` stretches between them.
- **The rail's reset link rendered as a solid primary block.** Ported from the mockup as `btn--ghost btn--sm`;
  this theme keys button variants off `data-variant`, so the class did nothing.

**And four e2e/unit guards were found to measure nothing** — by giving the critic the TESTS, not the code. The
worst compared a computed `backgroundColor` (`rgb(…)`) against the raw `--primary` token text: two strings that
can never be equal, so the assertion would have passed the exact defect it was written to catch. Full account
in [`qa-gates-cover-less-than-they-claim`](gotchas/qa-gates-cover-less-than-they-claim.md).

### Earlier measurements, kept for the trend

- **CI on `50bdbf4`** (all four jobs, `e2e` among them and actually running, 4m09s) — phpcs 0 · phpstan L8 0 · eslint 0 · prettier 0 · `tokens:check` 0 · unit **214** (628 assertions) · vitest **57** · integration **50** · base e2e **60** · build OK.
- **Local on the same tree** — e2e:woo **23/23**, integration-dev **4/4**, e2e-dev **2/2**. CI runs none of these three, so they only ever exist as a local measurement.

**s16's distribution of defects is the finding worth carrying, not the counts.** Four layers found six real defects and almost none overlapped: the Codex critic found 7 (including a PHP 8 fatal — `get_term_link()`'s `WP_Error` cast to string on the front page); the **re-critic found 1 inside the critic's own fix** (routing the static front page through the full content partial brought the page title back as a second `<h1>`); the **local e2e** found a regression three review passes had read past (`front-page.php` rendered neither the layout wrapper nor the sidebar, so a posts front page silently lost the sidebar it had always shown); and **CI** found a precondition that had been asserting nothing since s5. No layer caught another layer's defect. Treat none of them as the layer.

**Branch `feat/m3-release-prep` (M3: R1 + R4, PR [#32](https://github.com/kalbac/woodev-base-theme/pull/32)) — s15:** phpcs **0** · phpstan L8 **0** · eslint **0** · prettier **0** · `tokens:check` **0** · unit **210** (1 skip) · vitest **57** · integration **50** (1 skip) · integration-dev **4** · base e2e **60** (run split: 49 + 11, the serial suite exceeds a 10-minute foreground) · **e2e:woo 23/23** · build OK.

Note the skip counts differ by platform: unit skips 1 locally (Windows) and 0 in CI. Neither is a failure, but the same test count means slightly different work on each.

**CI now covers more than it did.** `php-integration` never ran `npm run build`, so four asset tests were silently `markTestSkipped()`; the job now builds and integration went *Skipped: 4* → *Skipped: 1*, 122 → 130 assertions.

**The critic gate is CLOSED for s13 and for M2b.** Eight passes ran in s14 — s13's code, B0, B1 CSS, B1 PHP, B2, B3–B5, B6, plus a re-critic of the fixes. Six clean, two with findings (2 on B1 PHP, 2 on B6), all four resolved: two fixed, one rejected with an AGENTS.md carve-out, one an ADR-009 amendment. The re-critic came back clean — **the first round on this project that did not find a defect inside the fixes**, which is worth watching rather than trusting. One caveat recorded honestly: s13's own diff was criticked at the DEFAULT reasoning effort, not `high`, because the quota ran out mid-re-run. Everything M2b ran at `high`.

**The gate was broken for the wrong reason all morning, and it is worth not re-learning.** `/codex:adversarial-review` returns `verdict: approve` whose body admits it read nothing — the sandbox denies Codex's shell, and the plugin asks Codex to go and read the diff. The working route is inline-on-stdin with a NO-TOOLS preamble and an explicit `model_reasoning_effort="high"`; see [`codex-critic-needs-inline-stdin-and-explicit-effort`](gotchas/codex-critic-needs-inline-stdin-and-explicit-effort.md). A `CODEX_OK` smoke test distinguishes "sandbox" from "quota" in one command — do that before concluding anything about the account.

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

**Branch `feat/m3-r1-theme-json-identity` (PR [#30](https://github.com/kalbac/woodev-base-theme/pull/30)) — CI green on head `30b1090`, and CI now covers more than it did:** php-qa · js-qa · php-integration · e2e all pass. unit **208** (613 assertions) · integration **46** (130 assertions, **Skipped: 1**, down from 4) · base e2e **60 passed**. Locally, on the same tree: e2e:woo **23/23** · integration-dev 4/4 · e2e-dev 2/2 · `tokens:check` 0.

Two gate defects were fixed to get there, and both are the same shape as the prettier one above. **`php-integration` never ran `npm run build`**, so four asset tests were silently `markTestSkipped()` — a green job covering less than it looked like. And **`theme.json` is a generated artifact** (`scripts/build-tokens.mjs`), which nothing in the gate could see: a hand edit passed the whole PHP suite, which never builds, and was erased by the next build. `npm run tokens:check` now fails CI when a committed generated file drifts from its source.

**The critic ran three rounds on R1** — 3 findings, then 2 *inside* those fixes, then 3 more that were all about the test's precision rather than the product. Every fix mutation-verified, several with the critic's own stated trigger. The product code has been clean since round 1.

## Deferred, tracked

- ~~**Dev mode has no integration/e2e coverage**~~ — resolved s7, closing a Codex P2 open since s3. Integration: `tests/integration/Integration/DevMode/AssetsDevModeTest.php` via `npm run test:integration:dev` (a second PHPUnit config whose bootstrap defines the constant — never wp-env's `config` key, which leaks into both environments and persists), mirrored by `Integration/AssetsProductionTest.php`. e2e: `tests/e2e-dev/dev-mode.spec.mjs` via `npm run e2e:dev`, against `.wp-env.dev-mode.json` on :8892 with Playwright owning a live Vite dev server. The e2e asserts **computed style**, since the defect class it guards has the script tag present and the styles absent.
- **Customizer overrides do nothing in dev mode.** Vite serves the pack CSS as a JS module that injects its `<style>` when the module EXECUTES — after `InlineStyles`' block was parsed — so `tokens.generated.css` wins on source order. Production is unaffected and an e2e mutation pins it (moving the block to `wp_head` 5 turns the accent assertion red). Raising selector specificity would fix dev at the cost of every real site's override path (Additional CSS), which is the wrong trade — see `InlineStyles`' docblock.
- **The block editor has no tokens in dev mode** (s15, ADR-010, same family as the two entries around it). `theme.json` colours are now `var()` references, and both editor hooks bail in dev mode, so swatches render transparent and the canvas is unstyled there. Not fixable the obvious way: Vite dev serves CSS as a JS module and `add_editor_style()` takes a stylesheet URL. Production is covered by integration + e2e. Raised by the Codex critic and accepted rather than fixed — **judge the editor in a production build**.
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
- **Nothing tests the declared WP floor (6.8).** wp-env runs `core: null` and CI does not matrix versions, so a 6.9+ API used anywhere passes every gate we have. s7 nearly shipped exactly that. Cheap fix when someone wants it: one CI job with `core: "WordPress/WordPress#6.8"`. Carded s18 → [#49](https://github.com/kalbac/woodev-base-theme/issues/49).
- **`e2e:woo` is not run by CI** (s18). 37 tests, including the only guards on the three defects s18 found, exist solely as a local measurement → [#48](https://github.com/kalbac/woodev-base-theme/issues/48).
- **The integration harness loads no plugins** (s18, measured). `tests/integration/bootstrap.php` boots the WP test suite itself, so `class_exists( 'WooCommerce' )` is false there no matter what `.wp-env.test.json` installs — adding Woo to that config changed 10 skips into 10 skips. 13 unrunnable rail tests were deleted rather than left reading as coverage → [#47](https://github.com/kalbac/woodev-base-theme/issues/47).
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

s20 (18.08.2026): **#40 completed on `feat/front-page-sections`**. Added popularity-sourced Woo product
picks, three-post Journal with deterministic SVG fallback plates, and an optional registered third-party
newsletter shortcode setting. Added the committed nine-surface probe and a fresh plan at
`docs/plans/2026-08-18-front-page-sections.md`.

The browser caught one real defect before completion: Woo storefront CSS is scoped under `.woocommerce`,
so a front-page product loop without that class rendered as a block list and stretched the page to 7421px.
The wrapper now carries the standard scope; the final probe measured 2498px and four tracks. The finding is
recorded in `docs/gotchas/woo-front-loop-needs-woocommerce-scope.md`.

The full global Woo setup remains a gate caveat: this machine stalled at `wp widget add` before the product
reseed. The targeted new suite is clean (`4/4`), base e2e is `63/63`, integration is `74`, and the no-setup
Woo battery reached `65 passed` with seven stale-fixture failures. Do not call the full Woo gate green until
`global-setup` completes after the existing [#48](https://github.com/kalbac/woodev-base-theme/issues/48) / wp-env
CLI degradation is resolved.

**Next session starts here:**

1. Review and merge/commit the `feat/front-page-sections` implementation after the critic gate.
2. Implement [#43](https://github.com/kalbac/woodev-base-theme/issues/43): compare blog, text and service pages against the approved mockup.
3. Then return to M3 release mechanics: version, wp.org tags, and the deferred screenshot.
