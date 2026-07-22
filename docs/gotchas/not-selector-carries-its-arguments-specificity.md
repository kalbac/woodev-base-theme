# `:not()` carries its argument's specificity — `:where()` is the zero-cost wrapper

**Area:** CSS · **Found:** s6 (M1-05), by adversarial review · **Severity when missed:** a Customizer setting silently does nothing

## The trap

`:root:not(.light):not(.dark)` looks like "the root element, filtered". It is
specificity **(0,3,0)** — one for the element-ish `:root`, plus one for each
class inside `:not()`, because `:not()` contributes the specificity of its
argument (Selectors Level 4 §17). `:is()` behaves the same way.

That put the generated `prefers-color-scheme` fallback block above **every**
override path in the theme, all of which are (0,1,0):

- `Customizer\InlineStyles` emits `:root{--primary:…}` — whose own docblock
  promises the cascade is decided by source order alone;
- a site owner's Additional CSS, which the same docblock promises still wins.

**Symptom:** with the shipped defaults (`color_scheme_default = system`) and a
visitor on a dark OS, choosing an accent colour in the Customizer did nothing at
all. The single commonest configuration there is.

## The fix

Wrap the filtering half in `:where()`, which contributes **zero**:

```css
:root:where(:not(.light):not(.dark)) { … }   /* (0,1,0) */
```

That is deliberately (0,1,0) and not (0,0,0): it must still beat Basecoat's own
un-layered `:root` on source order, while losing to the overrides that come
later. Zero would have lost to Basecoat and broken the fallback in the other
direction.

## Why it survived review until it didn't

Nothing failed. The e2e suite pinned the fallback (a `system` visitor gets dark
tokens) and pinned the accent preset (`--primary` changes) — but never both at
once, and `playwright.config.mjs` sets no `colorScheme`, so the accent test ran
in light mode and never entered the media block. Two green tests, one dead
feature between them.

The regression test strips comments before asserting, because the generator's
own explanation of this trap contains the very selector it forbids.

## Related

- [[basecoat-tokens-are-un-layered]] — why these declarations are un-layered at all
- [[tailwind-v4-layer-precedence]] — the layer half of the same cascade story
- [[qa-gates-cover-less-than-they-claim]] — the shape of "two green tests, one dead feature"
