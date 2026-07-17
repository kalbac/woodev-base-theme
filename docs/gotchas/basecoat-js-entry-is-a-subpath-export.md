# Basecoat's JS entry is a subpath export — `import 'basecoat-css'` gives you CSS

> Discovered s2 (17.07.2026) while writing the JS entry in M0 Task 4, verified by reading basecoat-css@1.0.2's `package.json` exports and `dist/js/*.js` contents.

## The traps

1. **`import 'basecoat-css'` from JS imports a stylesheet, not code.** The package's `.` (and `./css`) export maps to `./dist/basecoat.css`. A bundler will happily resolve it, so there is no error to alert you — you just silently get zero component behavior, plus a duplicate copy of the CSS that `app.css` already imports.
2. **`basecoat-css/basecoat` is the registry only — it registers nothing.** `dist/js/basecoat.js` ships the core `window.basecoat` API (`register`, `init`, `initAll`, `refresh`, `theme.get/set/toggle`), the MutationObserver lifecycle and a `DOMContentLoaded` hook, but **0** `register()` calls. Importing it looks right and initializes nothing.
3. **`basecoat-css/all` is the correct auto-init entry.** `dist/js/all.js` is that same core plus every component's self-registering IIFE (**12** `register()` calls at module-eval time, before `DOMContentLoaded`).
4. **The npm CSS is source, not compiled.** `dist/basecoat.css` → `basecoat-vega.css` → `basecoat-base.css` + `styles/vega.css`, and those use `@apply`, `@custom-variant dark`, and `@theme`. Tailwind must compile them; you cannot ship the npm CSS as-is. (The `*.cdn.css` variants are the pre-compiled ones.)

## How to apply here

- JS entry is `import 'basecoat-css/all';` (`src/js/app.js`). Per-component subpath exports (`basecoat-css/tabs`, `/dropdown-menu`, …) exist if bundle size ever forces a narrower import — revisit only with evidence, not preemptively.
- Verify the import by asserting component behavior in e2e, never by reading the bundle: a wrong import fails silently.
- Basecoat's `@theme { --color-background: var(--background); … }` maps its tokens onto Tailwind color utilities. It is **not** `@theme inline`, so the values stay runtime CSS custom properties and Customizer overrides work — see [[tailwind-v4-layer-precedence]] trap 4.

## Related

- [[tailwind-v4-layer-precedence]] — the layer contract Basecoat is imported into
- [[basecoat-style-packs-standalone]] — why the version is pinned exact and packs never combine
- ADR-004 (Basecoat npm + adapter)
