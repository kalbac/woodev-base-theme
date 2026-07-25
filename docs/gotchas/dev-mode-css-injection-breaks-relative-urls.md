# Dev mode serves CSS as a JS-injected `<style>`, so every relative `url()` in it resolves against the PAGE, not the stylesheet

> Discovered s11 (25.07.2026) implementing T3 (self-hosted fonts, ADR-007), verified in a
> browser against the running dev server. Production is unaffected and was verified
> separately — this trap exists only under `npm run dev`.

## The trap

`src/css/fonts.css` declares its faces theme-relatively:

```css
src: url('../../fonts/golos-text-500-800-cyrillic.woff2') format('woff2');
```

That path is written for where the **compiled** CSS lands —
`woodev-base-theme/assets/dist/assets/style-<hash>.css` — from which `../../fonts/`
is `woodev-base-theme/assets/fonts/`. In a production build it is correct: all 20 font
references resolve to real files on disk (checked by walking the built CSS, not by
assuming).

In dev mode there is no compiled CSS file. Vite serves the CSS entry as a **JavaScript
module** that injects the stylesheet text into a `<style>` element at runtime — the same
mechanism `vite-css-entry-is-not-imported-by-the-js-entry.md` describes and the same one
that makes the Customizer's inline overrides lose in dev.

A `<style>` element has no URL of its own, so relative URLs inside it resolve against the
**document's base URL**. On `http://localhost:8888/shop/product/kettle/`, `../../fonts/…`
resolves to `http://localhost:8888/shop/fonts/…` — a 404. On a different page depth it
resolves somewhere else again, also a 404. The fonts silently fall back to the system
stack, and the failure moves as you navigate.

## Why it looks like nothing is wrong

`font-display: swap` means a failed font never blanks the text — the page renders in the
fallback stack and simply looks *slightly off*. Nobody sees an error unless they open the
network panel and notice the 404s, or compare the dev page's computed `font-family`
against production. A visual check in dev will happily "confirm" a typographic design that
is not actually loading.

## How to apply here

- **Do not chase the font 404s in dev by making the path absolute.** A `/`-rooted path
  (`/wp-content/themes/woodev-base-theme/assets/fonts/…`) would fix dev and break every
  WordPress install in a subdirectory — and it is a wp.org Theme Review portability flag.
  The relative path is correct; the dev delivery mechanism is what differs.
- **Judge typography in a production build**, i.e. `npm run build` against a normal
  environment, never against `npm run dev`. Same rule the Customizer-overrides gap already
  imposes.
- If dev-mode fonts ever become worth fixing, the fix belongs in `Assets.php`'s dev branch
  (emit a small dev-only `@font-face` block whose `src` is built from
  `get_theme_file_uri()`), not in the generated `fonts.css`.
- More generally: **any relative `url()` in our CSS — fonts, background images, masks —
  has this property in dev.** The token layer's `--icon-chevron` is a `data:` URI and so is
  immune; that is a reason to prefer inline data URIs for small assets, not an accident.

## Related

- [[vite-css-entry-is-not-imported-by-the-js-entry]] — the same JS-module delivery, found
  from the other end
- [[basecoat-tokens-are-un-layered]] — why source order, not layering, decides our tokens
- `docs/adr/ADR-007-self-hosted-fonts.md` — the fonts this was found with
- `docs/CURRENT-STATE.md` → "Customizer overrides do nothing in dev mode" — the sibling
  symptom of the same dev-mode mechanism
