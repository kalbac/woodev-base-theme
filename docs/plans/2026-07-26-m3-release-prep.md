# Plan — M3, release preparation

> **For agentic workers:** use `superpowers:subagent-driven-development`. Steps use
> checkbox (`- [ ]`) syntax; the orchestrator verifies every claim itself and never lets a
> worker mark its own work done (AGENTS.md).

**Goal:** take the theme from "feature-complete on `main`" to "submittable to wp.org" —
the identity reaching core's own blocks, the font payload honest, the strings extractable,
and the review checklist actually run rather than assumed.

**Not in scope, deliberately:** the translation files. `.pot`, `.po` and `.mo` are Maksim's,
produced in Poedit once the strings are final. See R3.

**Architecture:** four independent tracks. R1 changes how the identity is delivered to
WordPress — **done**. R2 was to replace the font derivation pipeline; measurement has since
cut it down to a provenance question. R3 is translation-READINESS only, the files are
Maksim's. R4 is the compliance sweep and must run **last**, on the tree the others produce.

**Tech stack:** unchanged. R2 would have added a Python font toolchain; whether it is worth
adding at all is now an open question — see R2-2.

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

## R1 — the identity reaches core's blocks (#26 + #25) — ✅ DONE

> Shipped in PR [#30](https://github.com/kalbac/woodev-base-theme/pull/30), CI green.
> [ADR-010](../adr/ADR-010-theme-json-identity.md) accepted; three critic rounds, every fix
> mutation-verified. Two things the plan did not foresee, both recorded in the ADR: theme.json
> was ALREADY a generated artifact, and the editor is two documents, not one.

These are one problem seen twice and must be solved in one pass: both turn on *whether a
`theme.json` value may be a `var()` reference*, and that depends on whether our tokens
exist inside the editor. Nothing here may be coded before R1-1 answers it.

### R1-1 — Measure the editor, then decide 🔴

- [x] **Step 1: measure.** With `wp:start` up, open the block editor for a page and read,
      inside the editor canvas (which is an iframe in WP 6.8+), whether
      `getComputedStyle(document.documentElement).getPropertyValue('--primary')` and
      `--wtb-*` resolve to anything. Do the same after adding a throwaway
      `add_editor_style()` pointing at the built token CSS, to learn whether that route
      reaches the iframe at all. Record both results verbatim.
- [x] **Step 2: measure the second half.** Patch `theme.json` by hand so one preset is
      `"color": "var(--primary)"`, and read `--wp--preset--color--primary` on the front end
      **and** in the editor. WordPress may serialise, sanitise, or reject a `var()` there —
      find out which, do not reason about it.
- [x] **Step 3: write `ADR-010-theme-json-identity.md`** with the measurements in it. The
      live options, to be settled by what steps 1–2 return:
      **(a)** literal values regenerated from `src/tokens/tokens.mjs` at build time —
      single source of truth, still a static snapshot, still schema-blind;
      **(b)** `var()` references plus an editor stylesheet carrying the tokens — follows
      the Customizer and the dark scheme everywhere, if WordPress permits it;
      **(c)** `styles.elements.button` only, leaving presets frozen — closes #26, leaves
      #25 open, and is the honest fallback if (b) proves impossible.
      Also decide `settings.color.defaultPalette` (core's `black`/`vivid-red`/`pale-pink`
      currently sit beside our 15).
- [x] **Step 4: surface it to Maksim before implementing.** This changes how the identity
      is delivered and touches ADR-008. Show the concrete diff, not a description of it.

**Do not proceed past this task without the ADR recorded.**

### R1-2 — `styles.elements.button` displaces core's default

- [x] **Step 1: the failing test.** Integration test asserting that the CSS WordPress emits
      for `.wp-element-button` does **not** contain `#32373c`, and does contain our
      primary. Render through `wp_get_global_stylesheet()` rather than scraping a page, so
      the assertion names the mechanism.
- [x] **Step 2: run it, watch it fail** with core's grey present.
- [x] **Step 3: implement** `styles.elements.button` in `theme.json` per ADR-010's choice.
- [x] **Step 4: run it, watch it pass. Then mutate:** revert the `theme.json` hunk, confirm
      red, restore.
- [x] **Step 5: e2e.** A core Button block on a plain page computes our primary background
      in **both** schemes — light and dark, both asserted. (M2b shipped a "both schemes"
      spec that only checked dark; do not repeat that.)
- [x] **Step 6: commit.**

### R1-3 — presets follow the identity

- [x] Implement whichever of (a)/(b) ADR-010 chose; if it chose (c), close #25 as
      "not planned" with the reason in Russian and skip to R2.
- [x] **Test:** the desync guard #25 asks for — a test that fails when a preset and its
      live token disagree. Mutation-verify by changing one palette value in the generator
      alone.
- [x] If `theme.json` becomes a generated artifact, wire it into `npm run tokens`, add it
      to the build, and make CI fail when the committed file differs from a fresh
      generation. A generated file that can drift silently is worse than a hand-written one.
- [x] Commit; `Closes #26` and `#25` as applicable.

---

## R2 — the fonts (#17, ADR-007) — 🟡 measured, and the task inverted

Independent of R1. The current pipeline derives from a `fonts.googleapis.com` response
vendored on 25.07.2026 — deterministic and offline, but pinned to whatever Google served
that day.

**Read R2-2 first: it ran, and it removed most of this track's reason to exist.**
R2-1 and R2-3 below are conditional on a decision that has not been taken.

### R2-1 — the toolchain, and the decision that it stays optional (only if R2-2's decision is yes)

- [ ] Add `scripts/fonts/requirements.txt` (`fonttools[woff]`, `brotli`) and document the
      one-command setup in `docs/` — **not** in the npm install path. The `woff2` outputs
      stay committed to `assets/fonts/`, exactly as today, so a contributor who is not
      regenerating fonts needs no Python at all. Record this in ADR-007 as an amendment.
- [ ] Vendor the upstream OFL sources (Golos Text, IBM Plex Sans, IBM Plex Mono) from their
      **release tags**, with the tag recorded, replacing "whatever Google served".

### R2-2 — DONE, and it inverted the task 🔴

Measured 27.07.2026 in a browser, across the base site and the store, both schemes. Full
table in [ADR-007](../adr/ADR-007-self-hosted-fonts.md)'s amendment and in
[#17](https://github.com/kalbac/woodev-base-theme/issues/17):

- IBM Plex Sans uses its **whole** 400–700 axis. Nothing to trim.
- Golos Text renders at 600, **650**, 700, 800 — an intermediate value only a variable
  font produces, so no static replacement is possible. Only the unused 500–600 range is
  trimmable, which is a small saving.
- IBM Plex Mono uses **all three** shipped weights. The single biggest hoped-for saving,
  131 KB, does not exist.
- `@ibm/plex-mono` has **no variable release** (checked, 2.5.0: 96 static `woff2`).
- Glyph-subsetting to observed content is **unusable by construction** for a theme that
  renders arbitrary user text.

**So the ~120 KB target is struck, not pursued.** What remains worth doing is a provenance
change, not a size one — and it is Maksim's call whether it is worth a session at all:

- [ ] Decide with Maksim whether to re-instance from upstream OFL **release tags** purely
      for a citable version at wp.org review. It costs a Python toolchain the repo does
      not have and saves no meaningful bytes. Doing nothing is defensible: the current
      files are correctly subset OFL `woff2` derived by a deterministic script.
- [ ] If yes: `scripts/fonts/requirements.txt` (`fonttools[woff]`, `brotli`), outputs stay
      committed so no contributor needs Python, record the release tag, and re-verify all
      20 built `url()`s resolve in a PRODUCTION build (dev mode 404s fonts by design).
- [ ] Either way: do NOT add Mono 700. Measured absent from every rendered page — it would
      add bytes for a rule nothing reaches.

## R3 — translation-READINESS only. The files themselves are Maksim's.

**Scope corrected 27.07.2026, on Maksim's call.** An earlier draft of this plan had me
generate the `.pot` and translate `ru_RU`. That was wrong twice over: the string set is not
final until R2 and R4 land, so any `.pot` produced now gets regenerated anyway, and wp.org
does not require a shipped `.pot` at all — GlotPress builds its own from the source. Maksim
produces `.pot`, `.po` and `.mo` himself in **Poedit**, at the end. Do not generate them,
and do not "save him time" by pre-filling them.

What IS ours is that the code is extractable and correct when he runs Poedit over it:

- [ ] **Audit every i18n call site** (85 at the time of writing) for the defects that make a
      string un-extractable or mis-extractable: a variable inside an i18n function, a
      missing or wrong text domain, a concatenation where a placeholder belongs, a
      translator comment missing on an ambiguous string.
- [ ] **Confirm the plural rule holds.** `_n()` is banned here because Russian has three
      forms; count-sensitive copy must be count-agnostic plus `number_format_i18n()`.
      `comments.php:94` documents the one place this came up. Verify nothing new violates it.
- [ ] **Test what is testable without the files.** `load_theme_textdomain()` is called with
      the right domain and path (`Setup.php:38`); every user-facing string carries
      `woodev-base-theme`. Do NOT assert that a `.pot` exists — that is his artefact, and a
      test demanding it would fail the suite for a reason that is not a defect.
- [ ] **Create `languages/` with an index.php stub** if wp.org's checklist wants the
      directory present, and leave it otherwise empty.
- [ ] **Report the audit to Maksim** so he knows the source is clean before opening Poedit.

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
