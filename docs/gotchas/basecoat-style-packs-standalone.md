# Basecoat style packs are standalone — never combine them

> Discovered s1 (17.07.2026) while designing the `style_preset` Customizer option, verified against Basecoat 1.0.2 docs.

## The traps

1. **Style packs cannot be combined.** Basecoat ships 8 packs (`vega` default, `nova`, `maia`, `lyra`, `mira`, `luma`, `sera`, `rhea`); upstream explicitly forbids importing more than one — they are standalone full token sets, and stacking them produces cascade garbage.
2. **Runtime pack switching therefore requires N bundles, not one.** The build must emit one standalone CSS bundle per pack (8 Vite entries); PHP enqueues only the admin-chosen bundle. Do not try to ship one CSS with all packs scoped under classes — that fights upstream architecture.
3. **Dark mode is the `.dark` class on `<html>`** — Basecoat convention. Not `data-theme` (which the old woodev-theme used). All packs ship paired light/dark values keyed off `.dark`.
4. **Version is pinned exact (`1.0.2`, no caret).** Packs are visual contracts; a silent minor bump can restyle the whole site. Upgrade = changelog review + visual e2e across all packs.

## How to apply here

- `style_preset` setting (spec §6): 8 bundles at build time, one enqueued at runtime.
- Scheme switching and `primary_preset` overrides apply on top of whichever pack is active.
- `theme.json` editor palette is generated from `vega` only — known v1 limitation.

## Related

- `docs/specs/2026-07-17-woodev-base-v1-design.md` §5–6
- [[tailwind-v4-layer-precedence]]
- ADR-004 (Basecoat npm + adapter)
