# Basecoat style packs are standalone — never combine them, and they differ by SHAPE not colour

> Discovered s1 (17.07.2026) while designing the `style_preset` Customizer option, from Basecoat 1.0.2 docs. **Corrected s5 (21.07.2026)** during M1-03 by reading the shipped package: the s1 wording ("standalone full *token* sets") was wrong about *what* differs, and that mistake would have made the whole feature untestable.

## The traps

1. **Style packs cannot be combined.** Basecoat ships 8 packs (`vega` default, `nova`, `maia`, `lyra`, `mira`, `luma`, `sera`, `rhea`); upstream forbids importing more than one. Each `styles/<pack>.css` re-declares **every** component, so stacking two doubles every component rule and the later one silently wins piecemeal.
2. **Runtime pack switching therefore requires N bundles, not one.** The build emits one standalone CSS bundle per pack (8 Vite entries); PHP enqueues only the admin-chosen bundle. Do not ship one CSS with all packs scoped under classes — that fights upstream architecture.
3. **Packs differ in component SHAPE, not colour — all 8 share one palette.** This is the s1 correction and the one that bites. Verified on disk against basecoat-css 1.0.2:
   - `basecoat-css/<pack>` → `basecoat-base.css` + `styles/<pack>.css`
   - `basecoat-base.css` → `base/base.css` (the `:root`/`.dark` colour tokens — the shadcn palette, **identical for all 8 packs**) + `basecoat-components.css` (component structure)
   - `styles/<pack>.css` (~1386 lines) → the pack's **skin**: `@apply` rules setting radius, height, density. **Zero colour tokens** — `grep -c ':root|--background:|--primary:'` returns 0 for all 8.

   Example delta, `.btn:not([data-size])`: `vega` is `rounded-md h-9` (36 px), `nova` is `rounded-lg h-8` (32 px).
4. **A pack switch is invisible on a page that renders no Basecoat component classes.** Direct consequence of trap 3, and the reason M1-02's templates showed no difference across packs: they used only Tailwind utilities and our own adapter classes. The 8 bundles built byte-different and looked identical. **Any test asserting "the pack changed" must assert a component's geometry (button height/radius), never a colour** — colour cannot move between packs.
5. **Dark mode is the `.dark` class on `<html>`** — Basecoat convention. Not `data-theme` (which the old woodev-theme used). Light/dark values live in the shared base, not per pack.
6. **Version is pinned exact (`1.0.2`, no caret).** Packs are visual contracts; a silent minor bump can restyle the whole site. Upgrade = changelog review + visual e2e across packs. The e2e's exact `36`/`32` px assertions are the intended tripwire.

## How to apply here

- The 8 pack names live once, in `scripts/lib/packs-lib.mjs` (`PACKS`/`DEFAULT_PACK`), consumed by the entry generator and by `vite.config.mjs`'s Rollup inputs.
- `scripts/build-pack-entries.mjs` emits `src/css/packs/<pack>.css` (generated, gitignored) — one standalone entry per pack, byte-identical except the `@import 'basecoat-css/<pack>'` line.
- `StylePreset::from_theme_mod()->css_entry()` maps the `style_preset` theme_mod to the manifest key `src/css/packs/<pack>.css`; `Assets` enqueues exactly that bundle (default `vega`).
- Because of trap 4, the theme must render at least one real Basecoat component for the setting to mean anything — currently the search form (`searchform.php`, `.input` + `.btn`) and the blog read-more link (`.btn`).
- Scheme switching and `primary_preset` overrides apply on top of whichever pack is active.
- `theme.json` editor palette is generated from our tokens, not from a pack — and since packs carry no colour, that is a smaller limitation than s1 assumed.

## Related

- `docs/specs/2026-07-17-woodev-base-v1-design.md` §5–6 (§6's "every pack ships its own light+dark values" is imprecise for the same reason — the values are shared)
- [[basecoat-tokens-are-un-layered]] — the import-order rule each generated entry must preserve
- [[tailwind-v4-layer-precedence]]
- ADR-004 (Basecoat npm + adapter)
