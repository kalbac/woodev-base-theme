# Front page — completing the merchandising surface (#18) and covering it (#37)

> Written s17, 28.07.2026. Branch `feat/front-page-completion`, off `main` at `3f798a7`.

s16 wired the first slice: `front-page.php`, `template-parts/front/{hero,category-tiles}.php`.
What is left on [#18](https://github.com/kalbac/woodev-base-theme/issues/18) is the part where
the design's remaining sections have **no data source in the site** — and the answer to that,
per AGENTS.md, is a Customizer setting with an empty default, never an invented string.
[#37](https://github.com/kalbac/woodev-base-theme/issues/37) — no test touches any of it — is
done in the same branch, not after it.

## Decisions taken up front (no operator round-trip; AGENTS.md "decide and proceed")

1. **The empty hero plate becomes a real token-driven SVG, not a grey box.** The mockup already
   contains the artwork (`#p-hero`, `#p-promo`, and six object plates for the tiles). Port them
   as inline SVG through one helper, with the shapes inlined rather than referenced through
   `<symbol>`/`<use>` — the exact lesson `inc/Woo/ProductPlaceholder.php` records, and the same
   reason applies here (a plate can be rendered into a document that never received our sprite).
2. **The hero art column is a setting, not a question.** `front_hero_art`: `auto` (featured
   image if the front page has one, else the token plate) or `off` (no art column at all, the
   hero goes single-column). Default `auto`. Two workable options → a control, per AGENTS.md.
3. **Copy-bearing surfaces are Customizer text, defaulting to empty, and every section
   self-suppresses when its text is empty.** A theme that renders "Доставка за день" out of the
   box is shipping someone else's product copy.
4. **Repeated items are one textarea, one item per line, pipe-delimited** — not N controls and
   not a JS repeater (plugin territory for a v1 theme, spec §6's own reasoning about the reset
   control). Value band: `Title | Text | icon`. Hero trust badges: `Text | icon`. The icon is
   optional, validated against a closed set of shipped slugs, and falls back to `check`.
   Item caps are the CSS grid's: 4 value items, 3 trust badges.
5. **A tile with no category thumbnail gets a plate too**, picked deterministically from the six
   object plates by `term_id % 6` — variety without randomness, so a page's look is stable
   across renders and across a test run.

## Tasks

### F0 — the icons the new surfaces name

`scripts/copy-icons.mjs` ships only icons with a listed consumer. Add, with comments:
`check`, `truck`, `shield-check`, `refresh-cw`, `leaf`, `package`, `credit-card`, `headphones`.
Run `npm run icons`, commit the SVGs. `tests/php/Unit/IconAssetsTest.php` already guards that
every listed icon exists on disk — extend it if it enumerates a hardcoded list.

### F1 — `Templates\Plate`

New `woodev-base-theme/inc/Templates/Plate.php`, `final class Plate`:

- `public static function render( string $variant ): string` — returns one self-contained
  `<svg class="wtb-plate wtb-plate--{variant}" viewBox=… aria-hidden="true" focusable="false">`
  with the mockup's shapes inlined. Unknown variant → `''` (fail closed, never a fatal).
- Variants: `hero`, `promo` (480×400), and `mug`, `lamp`, `box`, `plaid`, `vase`, `towel`
  (400×400). Shapes copied verbatim from `docs/design/v2-mockup/woodev-base-identity.html`
  `<symbol>` definitions — that file is the approved artefact (ADR-008), so port, do not redraw.
- `public static function tile_variant( int $term_id ): string` — the six object plates indexed
  by `term_id % 6`.
- Fills stay presentation attributes reading `var(--c-bg)`, `var(--c-obj)`, `var(--c-obj2)`,
  `var(--c-obj3)`, `var(--c-ln)`, exactly as the mockup and `ProductPlaceholder` do.

CSS: a new `.wtb-plate` block in `src/css/adapter/blocks.css` defining those five custom
properties from identity tokens (`--surface-2`, `--muted-foreground`, `--primary`, `--border`),
plus `.wtb-hero__art .wtb-plate, .wtb-promo__art .wtb-plate { position: absolute; inset: 0; }`
and `object-fit`-equivalent sizing (`width/height: 100%`), mirroring the mockup's
`.hero__art .plate,.promo__art .plate` rule. `woo.css` keeps owning the placeholder's own
`--c-bg`/`--c-obj` — do not move it.

`ProductPlaceholder::render()` delegates to `Plate` **only if** it can keep its exact current
class list (`plate wtb-plate--placeholder`) and its current two shapes; if that turns into a
contortion, leave it alone and say so — DRY at two occurrences is not a rule (AGENTS.md).

Unit tests: every variant returns non-empty markup containing the expected shape count; an
unknown variant returns `''`; `tile_variant()` is total over any int, including negatives
(`-1 % 6` is `-1` in PHP — the modulo must not index out of range).

### F2 — Settings and the "Front page" Customizer section

`inc/Customizer/Settings.php` gains, each with a `sanitize_*` callback used both as the
Customizer callback and as the front-end resolver (the existing contract in that file):

| theme_mod | Shape | Default |
|---|---|---|
| `front_hero_eyebrow` | one line of text | `''` |
| `front_hero_lede` | text, overrides the site tagline when non-empty | `''` |
| `front_hero_trust` | ≤3 lines, `Text \| icon` | `''` |
| `front_hero_art` | `auto` \| `off` | `auto` |
| `front_value_items` | ≤4 lines, `Title \| Text \| icon` | `''` |
| `front_promo_title` | one line | `''` |
| `front_promo_text` | text | `''` |
| `front_promo_cta_label` | one line | `''` |
| `front_promo_cta_url` | URL | `''` |
| `front_promo_image` | attachment ID | `0` |

- Resolvers return structured data, not raw strings: `front_hero_trust(): array<int, array{label: string, icon: string}>`,
  `front_value_items(): array<int, array{title: string, text: string, icon: string}>`.
- Line parsing: split on newlines, trim, drop empties, cap, then split on `|` and trim each
  field. An item with an empty **title** (value band) or empty **label** (trust) is dropped —
  a badge that is only an icon is noise.
- Icons are validated against `Settings::FRONT_ICONS` (the F0 set); anything else → `check`.
- Text is `sanitize_text_field()`; the promo body is `wp_kses_post()`-free plain text
  (`sanitize_textarea_field()`), URLs `esc_url_raw()`, the image an absint that must resolve to
  an attachment at render time.
- `front_promo_cta_url` sanitisation must reject a `javascript:` URL — `esc_url_raw()` already
  does, but pin it with a test; it is the one setting here that lands in an `href`.

`inc/Customizer/Customizer.php`: a new section `woodev_base_front` titled "Front page",
priority 45 (between Colors/Typography/Layout and Header). Reuse the existing private
`add_*` helpers; add `add_textarea()` and `add_media()` (media = `WP_Customize_Media_Control`,
`mime_type => image`, guarded by `class_exists()` exactly as `add_color()` is, and for the same
reason). Every control gets a `description` that explains the line format and names the allowed
icon slugs.

Unit tests extend `tests/php/Unit/Customizer/` — sanitizers (valid, junk, non-string, over-cap,
empty fields, hostile URL) and the registration assertions the existing `CustomizerTest` makes.

### F3 — the templates

- `template-parts/front/hero.php`: eyebrow (`<p class="wtb-hero__eyebrow eyebrow"><span class="dot"></span>…`),
  lede = setting or tagline, trust badges, and the art column per `front_hero_art` — image, else
  `Plate::render('hero')`, and **no art element at all** when `off` (the CSS's second column
  must not be left empty; add the single-column modifier class `wtb-hero__inner--single` and a
  rule for it).
- `template-parts/front/value-band.php`: new, self-suppressing, `.wtb-value-band` /
  `.wtb-value` / `.ico` / `h4` / `p` per the mockup.
- `template-parts/front/promo.php`: new, self-suppressing on an empty title. Art side: the
  chosen image, else `Plate::render('promo')`. CTA renders only with both a label and a URL.
- `template-parts/front/category-tiles.php`: `.bg` falls back to `Plate::render( Plate::tile_variant( $term_id ) )`
  when the category has no `thumbnail_id`.
- `front-page.php` order, from the mockup's §05: hero → value band → categories → promo → the
  existing content/loop block. **No `.wtb-container` on any of them** — `header.php` already
  opens `<main id="wtb-content" class="wtb-container">`.
- Escaping: every string through `esc_html()`/`esc_url()`/`esc_attr()`; `Plate::render()` is our
  own generated markup and is echoed with a `phpcs:ignore` and a reason, like the two existing
  cases in these files.

### F4 — coverage (#37)

- **Integration** (`tests/integration/Integration/FrontPage*Test.php`): the four render modes
  from #37 — static front page with and without a featured image, posts front page, no
  WooCommerce — plus each new section's self-suppression and its rendered form when configured.
  Assert exactly one `<h1>`; assert the value band renders exactly the configured item count.
- **Unit**: F1 and F2 above.
- **e2e**: posts front page on :8888 (one `h1`, no tiles); the static front page inside
  `theme-mods.spec.mjs` (it is the only spec allowed to mutate options and it cleans up);
  tiles on :8891 in `tests/e2e-woo/` — tile count equals the non-empty top-level category count
  and each tile links to its archive.
- Where a test guards a defect class, **mutate the source and watch it go red** before believing
  it (`docs/gotchas/qa-gates-cover-less-than-they-claim.md`), and print the post-mutation state.

### F5 — the gate

Full battery in this order, one suite at a time (Docker contention):
`npm run build` · `composer phpcs` · `composer phpstan` · `npm run lint` · `npm run format` ·
`npm run tokens:check` · `composer test:unit` · `npm run test:js` · integration · base e2e
(background, ~12 min) · `e2e:woo`. Then the Codex critic on the whole diff — inline on stdin,
NO-TOOLS preamble, `model_reasoning_effort="high"` — then a re-critic of the fixes. PR last.

## Out of scope

The newsletter block, the journal/post grid on the front page, and the mockup's float-cards over
the hero art (they carry a product name and a price — invented content by definition).
