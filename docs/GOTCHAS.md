# Gotchas Index — Woodev Base

> 3 entries. Each gotcha is a separate file in `docs/gotchas/`.

| Gotcha | Area | Summary |
|---|---|---|
| [tailwind-v4-layer-precedence](gotchas/tailwind-v4-layer-precedence.md) | CSS | `@layer components` loses to utilities; un-layered CSS beats all layers — plan overrides accordingly |
| [basecoat-style-packs-standalone](gotchas/basecoat-style-packs-standalone.md) | CSS/Basecoat | Style packs can't be combined — one standalone bundle per pack, enqueue only the chosen one; dark mode is `.dark` class; version pinned exact |
| [basecoat-js-entry-is-a-subpath-export](gotchas/basecoat-js-entry-is-a-subpath-export.md) | JS/Basecoat | `import 'basecoat-css'` silently imports CSS, not JS; `/basecoat` registers 0 components — only `/all` auto-inits. npm CSS is source, needs Tailwind |
