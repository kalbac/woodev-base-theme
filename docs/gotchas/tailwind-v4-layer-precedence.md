# Tailwind v4 layer precedence traps

> Inherited from woodev-theme production experience (2026), verified there; directly shapes this theme's CSS architecture.

## The traps

1. **`@layer components` cannot override utilities.** Utilities are declared later in the layer order, so a component class loses to any utility on the same element regardless of specificity.
2. **Un-layered CSS beats ALL layered CSS**, regardless of specificity or order. Any stray un-layered rule (third-party plugin CSS, inline critical CSS) silently wins over the entire Tailwind cascade.
3. **Critical/inline CSS injected at `wp_head`** must declare the same layer order (`@layer theme, base, components, adapter, utilities;`) or its rules become un-layered and win unexpectedly.
4. **`@theme inline`** freezes token values at build time — avoid it; keep tokens as runtime CSS custom properties so Customizer overrides and `data-theme` switching work.

## How to apply here

- Layer order is declared once in `src/css/app.css`: `theme, base, components, adapter, utilities`.
- Basecoat lives in `components` — but it puts itself there; do **not** wrap the import in `layer(components)`. See [[basecoat-tokens-are-un-layered]] (verified s2: the wrapper fails the build outright, and Basecoat's own tokens are un-layered, so ours must be un-layered too).
- All our overrides live in `adapter` (still loses to utilities — that is correct and intended: utilities are the escape hatch).
- Interactive state rules that must beat utilities (`:disabled`, `.is-loading`, forced states) are declared **outside all layers**, deliberately and documented, in one dedicated file.
- Dark mode via the `.dark` class on `<html>` (Basecoat convention) + CSS custom properties, not build-time values.

## Related

- [[basecoat-tokens-are-un-layered]] — how traps 2 and 4 actually played out against Basecoat 1.0.2
- [[basecoat-js-entry-is-a-subpath-export]]
- ADR-004 (Basecoat npm + adapter)
- `docs/specs/2026-07-17-woodev-base-v1-design.md` §5
