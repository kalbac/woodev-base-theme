# Plan — implement the approved visual identity («Обиход») into the theme

> Written s11, 25.07.2026. Branch: `feat/m2a-woo-storefront` (continues; the M2a work and
> this design land together, Task 7 gates both).
> Design source of truth: `docs/design/v2-mockup/woodev-base-identity.html` — **the inline
> `<style>` block, lines 18–1090**, not the sibling `tokens.css` (see T0).
> Decisions: [ADR-007](../adr/ADR-007-self-hosted-fonts.md) ·
> [ADR-008](../adr/ADR-008-single-visual-identity.md).

## What we are building

One visual identity, admin-tunable through five single-point controls. The token layer is
the whole architecture: every surface reads tokens, so a palette swap is two numbers and a
radius swap is one.

```
--n-h            neutral temperature (68 warm … 274 cold) → the --n-0…--n-950 scale
--accent-h/-c    the accent, two numbers → --primary, --ring, washes, glow
--radius         one base → sm/md/lg/xl by calc()
--font-display   Golos Text      \
--font-body      IBM Plex Sans    } three roles, ADR-007
--font-mono      IBM Plex Mono   /
[data-cta]       add-to-cart reveal: hover (default) | always
```

## Architecture decisions taken here (mine, recorded rather than asked)

1. **Scheme selector stays `.light` / `.dark` on `<html>`**, not the mockup's
   `[data-scheme]`. M1-05 (no-FOUC head script, switcher, `prefers-color-scheme` fallback,
   34+ passing e2e) is built on the class, and Basecoat's own `dark:` variant keys off
   `html.dark` (CURRENT-STATE "Deferred": a `[data-scheme]` rename would break it silently).
   The port rewrites `[data-scheme="dark"]` → `.dark` plus the existing
   `:root:where(:not(.light):not(.dark))` fallback block, which the generator already emits.
2. **Palette preset and accent are written as inline `:root` custom properties by the
   Customizer** (the existing `InlineStyles` path), not as a `[data-preset]` attribute.
   They are a site-wide admin choice, not a runtime toggle; this reuses a tested path and
   ships no per-preset CSS. `[data-cta]` *is* an attribute — it is a behaviour switch on
   `<body>`, and CSS must select on it.
3. **Basecoat entry: `basecoat-css/base`.** The mockup *is* the skin; loading a Basecoat
   pack underneath it means every adapter rule fights 42 KB of `@layer components` doing
   the same job differently. What Basecoat keeps supplying is real and unchanged (ADR-004):
   component structure, its vanilla JS for dropdown/drawer/dialog/tabs/toast, and the class
   contract the templates already emit.

   **Corrected during T2 (25.07.2026):** an earlier draft of this line called that entry
   "structure only, no skin". It is not. `basecoat-css/base` ships a full un-layered
   `:root`/`.dark` token baseline (shadcn greyscale, `--radius: 0.625rem`, Geist Sans,
   `--chart-*`, `--sidebar-*`, `--scrollbar-*`, `--chevron-down-icon`, `--check-icon`).
   Ours override it on source order; the ones we never declare keep a foreign grey default.
   **T5 owns that list:** decide per token whether to emit an identity value or restyle the
   component that reads it. `--chevron-down-icon` / `--check-icon` in particular collide
   conceptually with the design's own `--icon-chevron` — pick one spelling and make the
   select and checkbox use it. Reversible in one import line. 🟡
4. **`--radius` in px, as the design has it** (10px base, `calc()` derivatives). The old
   `radius_scale` Customizer setting was rem-based; it becomes the `--radius` driver with
   a px scale (0 = square … 16 = round). One setting, one property.
5. **Type scale ships as tokens, not as Tailwind utility overrides.** Templates use the
   adapter's semantic classes; utilities stay for one-offs.

## T0 — Fix the design source of truth (blocker, do first)

`docs/design/v2-mockup/tokens.css` is a **stale export**: 8 `[data-palette]` packs that
change only the accent, and a hard-coded `--n-h: 68`. The approved artifact HTML carries 7
`[data-preset]` blocks that set `--n-h` *and* the accent. Anyone porting from `tokens.css`
implements the wrong design.

- Regenerate `tokens.css` from the HTML's inline `<style>` token block (lines 18–232).
- Add a header line stating it is an export of the artifact, not an input to it.
- **Verify:** every custom property present in the HTML block exists in `tokens.css` with
  the same value; the 7 presets match; no `[data-palette]` remains.

## T1 — Token layer

Rewrite `src/tokens/tokens.mjs` + `scripts/lib/build-tokens-lib.mjs` to emit the identity.

**Contract:**
- `tokens.generated.css` stays **un-layered** and keeps its three blocks: `:root`, `.dark`,
  and the `@media (prefers-color-scheme: dark)` + `:root:where(:not(.light):not(.dark))`
  fallback (comment and `:where()` reasoning preserved verbatim — that comment is a scar).
- Emits: `--n-h`, the `--n-*` scale, accent pair + derived accent surfaces, semantic
  surfaces, status colours (incl. the `-text` steps), footer surface, elevation, radius
  scale, spacing, type roles + scale, line heights, measure/container, motion, `--rule`,
  `--icon-chevron`.
- Keeps `--font-sans` / `--font-mono` as aliases of the new roles so nothing that already
  reads them breaks.
- `theme.json` colour palette is generated from the **resolved** default-palette values
  (warm-clay, light) — the editor palette can now match the front end (ADR-008).
- `inc/generated/primary-presets.php` becomes `inc/generated/palettes.php`: the 7 palettes
  as `[ slug => [ 'n-h' => …, 'accent-h' => …, 'accent-c' => … ] ]` plus their translated
  labels' slugs (labels live in PHP, not in generated code).

**The contrast gate survives, and gets harder.** Today `presetTuple()` measures literal
oklch colours. Now the values are `var()` expressions, so the generator must **resolve**
each palette numerically (substitute `--n-h`, `--accent-h`, `--accent-c` into the formulas)
and then measure, for every one of the 7 palettes × light/dark:
- `--primary-foreground` on `--primary` ≥ 4.5
- `--foreground` on `--background`, on `--card`, on `--surface-2` ≥ 4.5
- `--muted-foreground` on `--background`, on `--card`, on `--surface-2` ≥ 4.5 (the
  design's own comment claims exactly this — assert it, do not trust it)
- `--footer-fg` and `--footer-muted` on `--footer-bg` ≥ 4.5
- `--sale`, `--warning-text`, `--success-text`, `--destructive` as text on `--background`
Below AA ⇒ the build throws, as it does today. Keep the pessimistic
`min(chroma-reduction, per-channel-clamp)` reading — that machinery is correct and stays.

**Tests:** vitest against the lib — one case per assertion class, plus a mutation check
(perturb a palette to a failing value, expect the throw). Unit-test the resolver against
values computed by hand from the formulas.

## T2 — Retire the pack machinery, one bundle

- Delete `src/css/packs/`, `scripts/build-pack-entries.mjs`, `scripts/lib/packs-lib.mjs`,
  the `packs` npm script, the 8 Vite inputs.
- New single entry `src/css/app.css`: layer order, `tailwindcss`, `basecoat-css`,
  `tokens.generated.css` (un-layered, after Basecoat — the source-order contract),
  `adapter/index.css` in `layer(adapter)`, `states.css`, `fonts.css`.
- `Assets.php`: enqueue the one bundle; drop the `style_preset` lookup.
- `Settings.php`: remove `style_preset` + `presets()`; keep everything else working.
- `Customizer.php`: drop the style-pack control.
- Fix every test that asserts pack behaviour (`e2e/theme-mods.spec.mjs`, integration,
  unit). **Do not delete a test to make it pass** — rewrite it against the new contract or
  state why it no longer describes anything.

## T3 — Fonts (ADR-007)

- `scripts/fetch-fonts.mjs`: download the upstream OFL releases, subset to
  latin+latin-ext+cyrillic+cyrillic-ext, emit `woff2` with readable names into
  `woodev-base-theme/assets/fonts/`, write `LICENSE-*.txt`. Committed output, documented
  regeneration — the mockup's hash-named Google-CDN files do **not** ship.
- Weights: only those the design uses (audit the mockup's `font-weight` declarations).
- `src/css/fonts.css`: `@font-face` × N, `font-display: swap`, `unicode-range` per subset.
- Preload the display face used above the fold; nothing else.
- **Verify:** no request leaves the origin (e2e asserts zero `fonts.g*` requests); the
  faces actually load (computed `font-family` on `h1` resolves to Golos Text).

## The mockup's CSS, mapped to files (settled — do not re-derive)

The artifact's `<style>` block is already sectioned, and it marks its own demo
scaffolding. Sections 3 and 19 are **demo-only and must not ship** — the mockup says so
itself. Everything else maps:

| Mockup section | Goes to | Task |
|---|---|---|
| 2. Base / reset | `src/css/adapter/base.css` | T4 |
| 3. Showcase frame | — **demo only, do not port** | — |
| 4. Buttons | `adapter/buttons.css` | T5 |
| 5. Form controls | `adapter/forms.css` | T5 |
| 6. Badges · tags · alerts · breadcrumbs · pagination | `adapter/feedback.css` | T5 |
| 7. Header + nav | `adapter/header.css` | T4 |
| 8. Hero | `adapter/hero.css` | T4 |
| 9. Product card (native Woo `li.product`) | `src/css/woo.css` | T6 |
| 10. Category tiles / promo / value band | `adapter/blocks.css` | T4 |
| 11. Shop layout (filter rail + grid) | `src/css/woo.css` | T6 |
| 12. Single product | `src/css/woo.css` | T6 |
| 13. Commerce tables (cart/checkout/account/receipt) | `src/css/woo.css` | T6 |
| 14. Sidebar layouts + blog + article | `adapter/content.css` | T4 |
| 15. Footer + newsletter + 404 + search | `adapter/footer.css` | T4 |
| 16. Utility swatches / kit | **do not port** — audited, demo only (see below) | — |
| 17. Rationale | — comment block, port the reasoning into the CSS it explains | — |
| 18. Responsive | split into the file each rule belongs to, next to what it modifies | T4–T6 |

**Why §9 and §11–13 go to `woo.css` and not the adapter:** WooCommerce ships un-layered
CSS, which beats every `@layer` regardless of specificity. `woo.css` is un-layered and
mirrors Woo's own selector shapes for exactly that reason (s10, `faf7801`). Putting a
product-card rule in `@layer adapter` is the s9 mistake that made the storefront look
broken. The mockup's §9 was written against real Woo markup (`li.product > a`,
`span.add_to_cart`), so it ports nearly verbatim.

**Token coverage is verified, once, so nobody re-checks it per task:** every custom
property the shipped sections consume (82 of them) is emitted by the generator. The single
exception is `--sw`, which §16 defines inline on its own `.sw--*` classes — a demo swatch
strip, and one that still lists the retired eight accents rather than the seven palettes.
That is what settles §16 as demo-only.

**Class-name mapping is part of the port, not an afterthought:** the mockup invents its
own class names; the theme's templates already emit Basecoat's. For each ported rule,
either retarget the selector at the class the template emits, or change the template — and
say which, per component, in the task report. Do not ship two vocabularies.

## T4 — Base + layout surfaces

Port from the mockup: reset/base, container, header (+ mobile drawer), footer (the inverted
surface), skip link, focus ring, breadcrumbs, pagination, sidebar/widgets, blog list +
single post, 404/search/empty states, comment form.

Keep: existing template PHP structure, `Layout.php` variants, i18n, escaping. This is a
**CSS + class-name pass**, not a template rewrite — where the mockup's structure genuinely
differs, change the template, but do not invent new template files.

## T5 — Component kit

Buttons (all variants/sizes), inputs/selects/textarea/checkbox/radio, badges, alerts,
cards, tables, tabs, accordion, dropdown, tooltip, dialog/drawer — restyled in
`@layer adapter` against Basecoat's classes. **tabs + accordion were deferred from M1 to
here** (CURRENT-STATE §7 tail) — they land in this task, with the single-product page as
their real home.

### The Basecoat leftover-token ruling (settled 25.07.2026, T5)

Of the tokens `basecoat-css/base` declares and we do not, **none are overridden**:

- `--chart-*`, `--sidebar-*`, `--scrollbar-track/width/radius` — consumed only by Basecoat
  components this theme never renders. Verified by grepping `dist/components/`.
- `--scrollbar-thumb` — already `var(--border)` upstream, so it follows our identity free.
- `--chevron-down-icon` / `--chevron-down-icon-50` — consumed only by `sidebar.css`, which
  we do not render. Our own `--icon-chevron` drives the one chevron that is actually
  painted (the `<select>`), so there is no collision to resolve.
- `--check-icon` — **deliberately left at Basecoat's default**, reversing T5's
  recommendation to emit our own. Two reasons, both checked against the real files rather
  than the token's name: `checkbox.css` consumes it through `mask-[image:…]` with
  `bg-current`, so the literal `stroke="oklch(…)"` baked into the data URI is never
  rendered — the colour is already ours. And the default glyph *is* Lucide's check, which
  is this theme's own icon set (spec §9); replacing it with a hand-authored path would
  make the checkbox less consistent with the rest of the UI, not more.

The general rule this establishes: before overriding a vendor token, read its consumer.
A token whose value is used as a **mask** carries no colour, and one whose glyph already
comes from our icon set carries no style debt.

## T6 — WooCommerce surfaces

Shop archive + toolbar, product card (incl. the **hover-reveal add-to-cart over the
price**: absolute, zero layout space, `:hover`/`:focus-within`, `[data-cta="always"]`
escape hatch, `prefers-reduced-motion` honoured), single product + gallery + tabs, cart,
checkout, account, order-received, store notices.

**Reconcile with `woo.css` (`faf7801`):** its structural fixes carry over verbatim — the
un-layered placement, mirroring Woo's selector specificity, the `li.product` float/width
reset, the `ul.products::before` clearfix, the removed sidebar. The visual skin is
replaced. The SVG `<use>` plate sprites are the swap point for real product images and
must be themed via custom properties (shadow-boundary gotcha).

## T7 — Customizer

Five controls, each a single-point change:

| Setting | Values | Writes |
|---|---|---|
| `palette` | 7 slugs, default `warm-clay` | `--n-h`, `--accent-h`, `--accent-c` |
| `accent` | colour picker (overrides the palette's accent) | `--accent-h`, `--accent-c` |
| `radius` | 0…16px | `--radius` |
| `font` | `identity` (default) \| `system` | the three `--font-*` roles |
| `cta_reveal` | `hover` (default) \| `always` | `data-cta` on `<body>` |

Every setting: a sanitize callback that is **the same function** the front end resolves
with (the M1-04 rule — the two can never disagree). Accent picker input is hex; the
converter to `--accent-h/-c` is unit-tested including out-of-gamut and greyscale input.

## T8 — Gate + merge (the deferred M2a Task 7)

Full battery: phpcs · phpstan L8 · unit · vitest · build · integration · integration-dev ·
**base-isolation `npm run e2e` on :8888** · e2e-dev · e2e-woo. Then Codex critic + re-critic
(default profile, `-c 'mcp_servers={}'`, chunked, tell it not to read `.claude/skills/**`).
Then push + PR. **Merge is Maksim's call.**

## Order and parallelism

T0 → T1 → T2 must be sequential (each depends on the previous). T3 can run in parallel with
T1/T2. T4, T5, T6 are parallelisable once T1–T3 land. T7 after T1. T8 last.

## Definition of done per task

AGENTS.md §"Definition of done" applies to each task: lint green, tests at the right level,
i18n + escaping, a11y for changed components, docs updated. UI claims need browser
evidence — a screenshot or an e2e assertion on computed style, never "it renders correctly".

## Related

- [[ADR-007-self-hosted-fonts]] · [[ADR-008-single-visual-identity]]
- `docs/design/v2-mockup/` — approved design
- `docs/gotchas/basecoat-tokens-are-un-layered.md` ·
  `docs/gotchas/tailwind-v4-layer-precedence.md` ·
  `docs/gotchas/svg-use-shadow-boundary-needs-custom-props.md` ·
  `docs/gotchas/not-selector-carries-its-arguments-specificity.md`
