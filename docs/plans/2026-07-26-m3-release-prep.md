# Plan — M3, release preparation

> **For agentic workers:** use `superpowers:subagent-driven-development`. Steps use
> checkbox (`- [ ]`) syntax; the orchestrator verifies every claim itself and never lets a
> worker mark its own work done (AGENTS.md).

**Goal:** take the theme from "feature-complete on `main`" to "submittable to wp.org" —
the identity reaching core's own blocks, the font payload honest, the strings extractable,
and the review checklist actually run rather than assumed.

**Architecture:** four independent tracks. R1 changes how the identity is delivered to
WordPress (needs a decision recorded first). R2 replaces the font derivation pipeline. R3
adds the translation artifacts. R4 is the compliance sweep and must run **last**, on the
tree the other three produce.

**Tech stack:** unchanged, plus one new build-time dependency in R2 (`fonttools` +
`brotli`, Python) which is **optional for contributors** — its outputs are committed.

---

## Where this starts

`main` at `db2f8dc`: M1 identity + M2a + M2b merged, whole battery green on the merge
commit. Measured state of the four gaps, on that tree:

| Gap | Measured, not recalled |
|---|---|
| `theme.json` `styles` | The key is **absent entirely**. `WP_Theme_JSON` therefore contributes core's own default, `styles.elements.button.color.background = #32373c`, to the un-layered `global-styles` block — so every `.wp-element-button` on the site renders dark grey, in both schemes (#26) |
| `theme.json` presets | 15 colours + 3 families, written as **literal light-scheme values**. They follow neither the 7 Customizer palettes, nor an accent override, nor `.dark` (#25) |
| Editor tokens | **No `add_editor_style` and no `enqueue_block_assets` anywhere in `inc/`.** The block editor receives none of our `--wtb-*` or semantic tokens. This is the fact R1's decision turns on |
| Fonts | 22 files in `assets/fonts/` (20 woff2 + 2 OFL licences), **352.0 KB on disk**, ~132 KB fetched by a Russian page. Mono is 130.9 KB of it, as three static instances, and **has no 700** — the design asks for it once and silently gets 600 |
| i18n | `Setup.php:38` calls `load_theme_textdomain( 'woodev-base-theme', … . '/languages' )` against a directory that **does not exist**. No `.pot`, no `ru_RU`. 85 i18n call sites in the theme |
| wp.org | **No `readme.txt`. No `screenshot.png`.** `style.css` header is present and well-formed; `Tags:` currently lists three |

Two things that look like debt and are not: the lone `_n(` grep hit in `comments.php:94` is
a **comment explaining why `_n()` is not used** — the AGENTS.md Russian plural rule is
already followed. And the font-licence files already ship.

## Ground rules for every task here

- TDD. Tests first, watched to fail, at the level the change lives at (AGENTS.md).
- Every assertion added here gets **mutation-verified**: break the thing it guards, watch
  it go red, restore. An assertion that never fails is not coverage — this project has
  shipped two of those already.
- Serena for source edits; strip CRs after every Serena write.
- Never run a suite while anything edits the theme — wp-env bind-mounts it.
- One wp-env environment at a time.
- Codex critic gate before merge, then re-critic the fixes. Inline on stdin, NO-TOOLS
  preamble, explicit `model_reasoning_effort="high"`.

---

## R1 — the identity reaches core's blocks (#26 + #25)

These are one problem seen twice and must be solved in one pass: both turn on *whether a
`theme.json` value may be a `var()` reference*, and that depends on whether our tokens
exist inside the editor. Nothing here may be coded before R1-1 answers it.

### R1-1 — Measure the editor, then decide 🔴

- [ ] **Step 1: measure.** With `wp:start` up, open the block editor for a page and read,
      inside the editor canvas (which is an iframe in WP 6.8+), whether
      `getComputedStyle(document.documentElement).getPropertyValue('--primary')` and
      `--wtb-*` resolve to anything. Do the same after adding a throwaway
      `add_editor_style()` pointing at the built token CSS, to learn whether that route
      reaches the iframe at all. Record both results verbatim.
- [ ] **Step 2: measure the second half.** Patch `theme.json` by hand so one preset is
      `"color": "var(--primary)"`, and read `--wp--preset--color--primary` on the front end
      **and** in the editor. WordPress may serialise, sanitise, or reject a `var()` there —
      find out which, do not reason about it.
- [ ] **Step 3: write `ADR-010-theme-json-identity.md`** with the measurements in it. The
      live options, to be settled by what steps 1–2 return:
      **(a)** literal values regenerated from `src/tokens/tokens.mjs` at build time —
      single source of truth, still a static snapshot, still schema-blind;
      **(b)** `var()` references plus an editor stylesheet carrying the tokens — follows
      the Customizer and the dark scheme everywhere, if WordPress permits it;
      **(c)** `styles.elements.button` only, leaving presets frozen — closes #26, leaves
      #25 open, and is the honest fallback if (b) proves impossible.
      Also decide `settings.color.defaultPalette` (core's `black`/`vivid-red`/`pale-pink`
      currently sit beside our 15).
- [ ] **Step 4: surface it to Maksim before implementing.** This changes how the identity
      is delivered and touches ADR-008. Show the concrete diff, not a description of it.

**Do not proceed past this task without the ADR recorded.**

### R1-2 — `styles.elements.button` displaces core's default

- [ ] **Step 1: the failing test.** Integration test asserting that the CSS WordPress emits
      for `.wp-element-button` does **not** contain `#32373c`, and does contain our
      primary. Render through `wp_get_global_stylesheet()` rather than scraping a page, so
      the assertion names the mechanism.
- [ ] **Step 2: run it, watch it fail** with core's grey present.
- [ ] **Step 3: implement** `styles.elements.button` in `theme.json` per ADR-010's choice.
- [ ] **Step 4: run it, watch it pass. Then mutate:** revert the `theme.json` hunk, confirm
      red, restore.
- [ ] **Step 5: e2e.** A core Button block on a plain page computes our primary background
      in **both** schemes — light and dark, both asserted. (M2b shipped a "both schemes"
      spec that only checked dark; do not repeat that.)
- [ ] **Step 6: commit.**

### R1-3 — presets follow the identity

- [ ] Implement whichever of (a)/(b) ADR-010 chose; if it chose (c), close #25 as
      "not planned" with the reason in Russian and skip to R2.
- [ ] **Test:** the desync guard #25 asks for — a test that fails when a preset and its
      live token disagree. Mutation-verify by changing one palette value in the generator
      alone.
- [ ] If `theme.json` becomes a generated artifact, wire it into `npm run tokens`, add it
      to the build, and make CI fail when the committed file differs from a fresh
      generation. A generated file that can drift silently is worse than a hand-written one.
- [ ] Commit; `Closes #26` and `#25` as applicable.

---

## R2 — re-instance the fonts with `pyftsubset` (#17, ADR-007)

Independent of R1. The current pipeline derives from a `fonts.googleapis.com` response
vendored on 25.07.2026 — deterministic and offline, but pinned to whatever Google served
that day, and missing Mono 700.

### R2-1 — the toolchain, and the decision that it stays optional

- [ ] Add `scripts/fonts/requirements.txt` (`fonttools[woff]`, `brotli`) and document the
      one-command setup in `docs/` — **not** in the npm install path. The `woff2` outputs
      stay committed to `assets/fonts/`, exactly as today, so a contributor who is not
      regenerating fonts needs no Python at all. Record this in ADR-007 as an amendment.
- [ ] Vendor the upstream OFL sources (Golos Text, IBM Plex Sans, IBM Plex Mono) from their
      **release tags**, with the tag recorded, replacing "whatever Google served".

### R2-2 — decide the weight set from the shipped CSS, not from taste

- [ ] Enumerate every `font-weight` actually used, across `src/css/**` **and** the built
      bundle in `assets/dist` — the built artifact is the authority here, since Tailwind
      contributes rules no source grep can see.
- [ ] Mono's set is the live question: the design asks for 400/500/600 **and** 700 once.
      Ship the weights the built CSS requests and no others; if 700 is requested exactly
      once, say so in the commit rather than quietly dropping it.

### R2-3 — build, measure, gate

- [ ] **Step 1: the failing test.** A vitest assertion that every family/subset the CSS
      declares has a matching file on disk, that each is a valid woff2 (magic bytes **and**
      a plausible size floor — a 4-byte file passed this check once), and that Mono 700
      exists.
- [ ] **Step 2: run it, watch it fail** on the missing Mono 700.
- [ ] **Step 3: implement** `scripts/fonts/build.py` (or extend `build-fonts.mjs` to shell
      out to it): subset to latin + latin-ext + cyrillic + cyrillic-ext, cut the weight axis
      on the variable faces to the used range, emit one file per (family, subset).
- [ ] **Step 4: run it, watch it pass. Mutate:** truncate one woff2 to 4 bytes, confirm the
      size floor catches it, restore.
- [ ] **Step 5: measure and record** shipped-on-disk and fetched-by-a-Russian-page, the
      same two numbers ADR-007 carries, and update that table. If the ~120 KB target is not
      reached, **write the real number** — ADR-007 already had to be corrected once for
      carrying an estimate as if it were a measurement.
- [ ] **Step 6:** confirm all built `url()`s resolve in a **production build** (dev mode
      404s fonts by design), and that the licence files still match their pinned identity.
- [ ] **Step 7: commit.** `Closes #17`.

---

## R3 — the `.pot`, and a real `ru_RU` (ADR-006)

Independent of R1 and R2.

- [ ] **Step 1: the failing test.** Integration assertion that
      `get_template_directory() . '/languages/woodev-base-theme.pot'` exists and that
      `load_theme_textdomain()` returns true for `ru_RU`. It currently points at a directory
      that does not exist, so this fails honestly today.
- [ ] **Step 2: run it, watch it fail.**
- [ ] **Step 3: generate** via wp-cli inside wp-env:
      `wp i18n make-pot wp-content/themes/woodev-base-theme wp-content/themes/woodev-base-theme/languages/woodev-base-theme.pot --domain=woodev-base-theme`.
      Add an npm script so it is reproducible, not a remembered command.
- [ ] **Step 4: audit the extraction.** Compare the `.pot` entry count against the 85 call
      sites. A string that uses a variable inside an i18n function does not extract and is
      a defect, not a discrepancy — fix any at the source.
- [ ] **Step 5: translate `ru_RU`** (`.po` + compiled `.mo`), informal register to match the
      product's voice. Every count-sensitive string must stay count-agnostic — the Russian
      3-form plural rule is why `_n()` is banned here (`comments.php:94` documents it).
- [ ] **Step 6: e2e.** With `WPLANG=ru_RU`, a known front-end string renders in Russian.
      Mutation-verify by removing the `.mo`.
- [ ] **Step 7: commit.**

---

## R4 — the wp.org Theme Review sweep (runs last)

Depends on R1–R3 being merged: the readme credits the fonts R2 produces, and Theme Check
must run on the final tree.

- [ ] **`readme.txt`** — does not exist yet. wp.org format: theme name, contributors,
      requires/tested/stable tag, licence, description, changelog, and a **Credits/Resources
      section naming every bundled third-party asset with its licence and source URL** —
      Golos Text (OFL), IBM Plex Sans/Mono (OFL), Basecoat UI, Lucide (ISC), Alpine.js,
      Tailwind. A missing credit is a standard rejection reason.
- [ ] **`screenshot.png`** — does not exist yet. 1200×900, showing the actual theme, no
      text that is not in the theme, no mockup chrome.
- [ ] **Run Theme Check** (the plugin) against the built theme inside wp-env and fix every
      ERROR; record each WARNING with a decision rather than leaving it unread.
- [ ] **`Tags:`** — currently three. Validate against wp.org's *current* allowed list, which
      is versioned and drops tags over time; do not copy them from another theme.
- [ ] **Escaping/prefix audit** on the whole tree, not on the diff: every echo escaped,
      every public function and hook prefixed `woodev_base_`, every Customizer setting with
      a sanitize callback. PHPCS covers most of this — read what it *scanned*, not just its
      exit code (`docs/gotchas/qa-gates-cover-less-than-they-claim.md`).
- [ ] **No plugin territory:** confirm nothing registers a CPT, taxonomy, shortcode, or
      admin page that would belong in a plugin.
- [ ] **Version and floors:** bump `Version:` off `0.1.0`; re-verify `Requires at least: 6.8`
      and `Tested up to: 7.0` against the current releases. Note the standing blind spot —
      **nothing in this project tests the declared 6.8 floor**; wp-env runs `core: null` and
      CI does not matrix. One CI job with `core: "WordPress/WordPress#6.8"` closes it and is
      cheap. If it is not done, say so in the release notes rather than implying coverage.
- [ ] **Full battery on the merge commit**, not per-branch, including the three suites CI
      never runs (`e2e:woo`, `integration-dev`, `e2e-dev`).
- [ ] Commit; tag the release.

---

## Order

R1-1 first and alone — it is the only 🔴 here and it blocks R1-2/R1-3. R2 and R3 are
independent of R1 and of each other and can run in parallel with it. R4 last, on the merged
tree.

Not in M3, deliberately: #13 (thumbnail ratio Customizer control), #18 (front-page
merchandising markup has no template), #23 (e2e setup breaks on POSIX), #27, #28. Each is
tracked and none blocks a release.

## Related

- [[ADR-007-self-hosted-fonts]] — R2 amends its payload table and its toolchain note
- [[ADR-008-single-visual-identity]] — R1 touches how that identity is delivered
- [[ADR-006-i18n-english-source]] — R3
- `docs/gotchas/qa-gates-cover-less-than-they-claim.md` — read before trusting any gate here
- `docs/gotchas/dev-mode-css-injection-breaks-relative-urls.md` — why R2 must be judged in a
  production build
