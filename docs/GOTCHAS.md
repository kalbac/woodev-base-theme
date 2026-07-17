# Gotchas Index — Woodev Base

> 2 entries. Each gotcha is a separate file in `docs/gotchas/`.

| Gotcha | Area | Summary |
|---|---|---|
| [tailwind-v4-layer-precedence](gotchas/tailwind-v4-layer-precedence.md) | CSS | `@layer components` loses to utilities; un-layered CSS beats all layers — plan overrides accordingly |
| [basecoat-style-packs-standalone](gotchas/basecoat-style-packs-standalone.md) | CSS/Basecoat | Style packs can't be combined — one standalone bundle per pack, enqueue only the chosen one; dark mode is `.dark` class; version pinned exact |
