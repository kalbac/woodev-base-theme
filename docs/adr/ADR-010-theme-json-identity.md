# ADR-010: `theme.json` carries `var()` references, and the editor gets the tokens

- **Status:** **Accepted** (26.07.2026, s15) — Maksim approved option (b) on the measured
  diff. 🔴: it changes how the identity is delivered to WordPress itself.
- **Deciders:** Maksim + Claude
- **Closes:** [#26](https://github.com/kalbac/woodev-base-theme/issues/26),
  [#25](https://github.com/kalbac/woodev-base-theme/issues/25)
- **Relates to:** [ADR-008](ADR-008-single-visual-identity.md) — this is how "one identity"
  reaches core's own blocks rather than stopping at our stylesheets.

## Context

`theme.json` currently declares `settings` only: 15 colours and 3 font families, written as
**literal light-scheme values**. There is no `styles` key at all. Two consequences, both
measured on `main` at `db2f8dc` against WordPress 7.0.2 in wp-env — every number below is a
measurement, not a reading of the source.

**1. Core paints every button on the site.** With `styles` absent, `WP_Theme_JSON`
contributes core's own default, and `wp_get_global_stylesheet()` emits:

```css
:root :where(.wp-element-button, .wp-block-button__link){background-color: #32373c;color: #fff; …}
```

`:where()` contributes zero specificity, so that rule is **(0,1,0)** — trivially beatable,
but it is what an untouched core Button block renders as, site-wide, in both schemes.

**2. The presets are a frozen snapshot.** `--wp--preset--color--primary` serialises to
`oklch(47% .088 40)` and follows neither the 7 Customizer palettes, nor an accent override,
nor `.dark`. Also measured: **27 colour presets total**, because core's own default palette
(`black`, `vivid-red`, `pale-pink`, `cyan-bluish-gray`, `luminous-vivid-orange`) ships
beside our 15 — `settings.color.defaultPalette` was never disabled.

**3. The editor has none of our tokens.** Measured inside the WP 6.8+ editor canvas iframe:
`--primary`, `--primary-foreground` and `--background` all resolve to **empty**. The iframe
loads five core stylesheets and nothing of ours; `get_editor_stylesheets()` returns `[]` and
there is no `add_editor_style` or `enqueue_block_assets` anywhere in `inc/`. What the iframe
*does* have is `--wp--preset--color--primary`, as a literal.

Fact 3 is why this is one decision and not two: it is the constraint that decides whether a
`theme.json` value may be a `var()` reference at all.

## What was measured before deciding

| Question | Method | Answer |
|---|---|---|
| Does `var()` survive into a colour preset? | filtered `wp_theme_json_data_theme`, cleared the cache, read `wp_get_global_stylesheet()` | **Yes**, verbatim — including the fallback form `var(--wtb-probe, hsl(200 50% 50%))` |
| Does `var()` survive into `styles.elements.button`? | same | **Yes** — `background-color: var(--primary)` |
| Does declaring `styles.elements.button` *add to* core's default or *replace* it? | same, then grepped the output | **Replaces.** `#32373c` disappears from the stylesheet entirely |
| Does `add_editor_style()` deliver our tokens into the canvas iframe? | added it temporarily, read `getComputedStyle` inside the iframe via Playwright | **Yes** — `--primary` resolved to `oklch(47% .088 40)` inside the canvas, where it had been empty |

The `theme.json` file was never edited to measure any of this; a filter was used, so the
committed file stayed clean throughout.

## Decision

**Take option (b): `theme.json` values become `var()` references, and the editor is given
the tokens that resolve them.** Four changes:

1. `styles.elements.button` declared as `var(--primary)` / `var(--primary-foreground)`,
   which displaces core's `#32373c` site-wide.
2. The colour presets become `var(--…)` references to the same semantic tokens the rest of
   the theme reads, so they follow the Customizer palette, the accent override and `.dark`
   for free, instead of being a second copy of the answer.
3. `add_editor_style()` with the built stylesheet, resolved **through the existing Vite
   manifest resolver in `Assets.php`** — never a hardcoded hashed filename.
4. `settings.color.defaultPalette: false`, dropping core's five stray colours.

`styles.blocks` stays empty, as [ADR-009](ADR-009-block-cart-checkout-styling.md) decision 5
requires; that ruling is about `styles.blocks`, and this is `styles.elements`.

## Consequences

- **One identity, finally including core's blocks.** A core Button block, in any post, in
  either scheme, renders in the theme's primary.
- **The presets stop being a second source of truth.** #25's desync cannot occur, because
  there is only one value.
- **The editor becomes a consumer of our CSS.** That is new surface: the canvas will now
  inherit whatever the built stylesheet says, so a rule written for the front end can leak
  into the editing experience. Scope the editor stylesheet if that proves noisy.
- **Two honest limits.** The editor has no `.dark` class, so the canvas follows the light
  scheme regardless — unchanged from today, but now visible rather than hidden. And the
  Customizer's per-site overrides are injected by `InlineStyles` on the front end only; the
  editor will show the *default* palette until that block is emitted there too. Neither is a
  regression; both should be written down rather than discovered later.
- **Reversible in the sense that matters (🟡 in practice, 🔴 in reach):** every change here
  is a `theme.json` hunk plus one `add_editor_style` call. What is not cheap to undo is the
  expectation it sets — once the editor matches the site, going back is a visible downgrade.

## Corrections made during implementation

Two things this ADR got wrong, both found by running the change rather than reading it.
Recorded here rather than quietly edited away, because each was a plausible belief.

1. **`theme.json` was ALREADY a generated artifact.** `scripts/build-tokens.mjs` rewrites
   it from `src/tokens/tokens.mjs` on every `npm run build`, and `buildThemeJson()` carried
   a docblock asserting that theme.json "is static JSON that cannot follow a custom
   property" — with a vitest case pinning `must be a literal` on those grounds. So the
   first implementation, a hand edit, passed the whole PHP suite (which never builds) and
   was then erased by the next build. The change belongs in the generator; the file it now
   produces is byte-identical to the hand-written one. `npm run tokens:check` fails CI if a
   committed generated file ever diverges again.
2. **The editor is two documents, and only one of them was covered.** `add_editor_style()`
   reaches the canvas iframe. The colour picker lives in the wp-admin document, where
   Gutenberg paints each swatch from the RAW preset value — so with `var()` presets and no
   tokens there, every swatch computed `rgba(0, 0, 0, 0)`. Measured, then fixed with a
   tokens-only Vite entry (`src/css/editor-tokens.css`) enqueued on
   `enqueue_block_editor_assets`. The full bundle must never go to wp-admin: it carries
   Basecoat's base layer and the site chrome.

The old docblock was half-right, which is why it survived: a `var()` preset IS broken in
the editor — in the sidebar, not the canvas, and fixable rather than fatal.

## Rejected alternatives

- **(a) Emit literal values resolved from `src/tokens/tokens.mjs`.** This was the status
  quo, not an alternative — see correction 1. It fixes nothing: the result is a static
  snapshot of one palette in one scheme, so #25 survives in full.
- **(c) Declare `styles.elements.button` only, leave the presets frozen.** Closes #26,
  leaves #25 open, and needs no editor work. Kept as the fallback **only** if the editor
  surface in the first consequence above turns out to be a real problem — measured, not
  feared.

## Related

- [[ADR-008-single-visual-identity]] · [[ADR-009-block-cart-checkout-styling]]
- `docs/plans/2026-07-26-m3-release-prep.md` — R1
