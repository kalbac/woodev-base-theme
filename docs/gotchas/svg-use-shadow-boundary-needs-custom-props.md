# SVG `<use>` clones into a shadow tree — document class selectors don't reach it

**Area:** CSS / SVG · **Found:** s10 (OD v2 mockup, plate illustrations rendered solid black)

## What happened

The mockup drew product "plates" as an SVG sprite: `<symbol id="p-kettle"><rect class="bgf"/><path class="obj"/><path class="ln"/></symbol>`, referenced by `<svg class="plate"><use href="#p-kettle"/></svg>`. The fills were set from the document stylesheet:

```css
.plate .bgf{ fill:var(--surface-3); }
.plate .obj{ fill:var(--primary); opacity:.86; }
.plate .ln { stroke:var(--foreground); fill:none; }
```

Every plate rendered as a **solid black box**.

## Why

`<use>` clones the referenced `<symbol>` into a **shadow tree**. A document CSS **selector** (`.plate .obj`) cannot match an element inside that shadow tree — the shadow boundary blocks selector matching. So the cloned `<rect class="bgf">` etc. never received a fill and fell back to the SVG default, which is **`fill:black`**. The `bgf` rect fills the whole viewBox black and hides everything behind it.

## The fix — CSS custom properties, which DO inherit across the boundary

Inherited properties (and **custom properties**) cross the `<use>` shadow boundary. So drive the fills with variables referenced as **presentation attributes on the shapes**, and set the variables on the host:

```css
.plate{ --c-bg:var(--surface-3); --c-obj:var(--primary); --c-ln:var(--foreground); }
```
```html
<rect fill="var(--c-bg)"/>
<path fill="var(--c-obj)" opacity=".86"/>
<path fill="none" stroke="var(--c-ln)" stroke-width="6"/>
```

The `fill="var(--c-bg)"` presentation attribute is part of the cloned content and resolves the inherited custom property — the illustration renders, stays token-driven, and follows a palette/scheme swap.

## Takeaways

- Theming `<use>`d sprite content: **never style it by class from the document**. Use custom properties + presentation attributes (or `currentColor` for a single colour).
- A "solid black" SVG is the tell-tale of unstyled shape defaults reaching through a `<use>`.
- Inline `width`/`height` attributes on the `<svg class="plate">` are **overridden by `.plate{width:100%}`** — a related trap: order/cart line-item thumbnails blew up to fill their flex cell until a higher-specificity `.shop_table .plate{width:48px}` pinned them.

## Related
- [[not-selector-carries-its-arguments-specificity]] — another CSS specificity/scope surprise
