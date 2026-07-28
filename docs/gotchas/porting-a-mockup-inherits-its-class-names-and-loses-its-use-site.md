# Porting a mockup inherits its class names and loses its use site

> Found s17 (28.07.2026) — two defects in one port, neither visible to any gate that had already passed.

## The trap

A design mockup is a **standalone document**. It owns the whole class namespace and it writes attributes at
the point of use. A theme is neither of those things, so two categories of information go wrong in a port and
both survive every lint, every unit test and every markup assertion.

## 1. The mockup's class names are not free

`docs/design/v2-mockup/woodev-base-identity.html` names the category tile's text block `.label`. Ported
verbatim, that markup shipped — and this theme also ships **Basecoat**, where `.label` is the form-label
component:

```css
.field>label,.field>section>label,.label{user-select:none;align-items:center;display:flex}
.field>label,.field>section>label,.label{font-size:var(--text-sm);color:var(--foreground);font-weight:600}
```

So the tile's label silently inherited `align-items: center` — the category name centred itself instead of
sitting against the tile's left padding, and drifted over the artwork behind it — plus `user-select: none`,
which makes a category name unselectable for no reason anyone chose.

Nothing could see it. The markup was correct. `phpcs` has no opinion. The integration test asserted the label
existed, which it did. It became visible only in a browser screenshot, and was only *confirmed* by reading
computed style:

```js
getComputedStyle(label).alignItems  // "center" — and nothing in our CSS says so
```

- **Before porting a class name, check it against what the theme already ships.** One grep of the BUILT
  bundle answers it: `grep -o "[^}{,]*\.NAME\b[^{}]*{[^}]*}" assets/dist/assets/style-*.css`. In s17 the tile's
  other ported names — `.bg`, `.arrow`, `.count`, `.ico`, `.dot`, `.plate` — were all checked the same way and
  all were free; only `.label` collided.
- The fix is a rename into our own namespace (`wtb-tile-label`), not a counter-declaration. Fighting a
  component class with `align-items: flex-start` leaves the collision live for every property the component
  adds later.
- **Pin it on computed style**, not on markup. A class rename is exactly the repair a later "port fidelity"
  edit undoes by accident, and the markup looks identical either way.

## 2. Attributes at the use site are not in the `<symbol>`

The mockup draws its illustration plates as `<symbol>` definitions referenced by `<use>`:

```html
<symbol id="p-promo" viewBox="0 0 480 400"> … shapes … </symbol>
…
<svg class="plate" viewBox="0 0 480 400" preserveAspectRatio="xMidYMid slice"><use href="#p-promo"/></svg>
```

`preserveAspectRatio` is on the **`<svg>` at the use site**, not on the symbol. A port that copies the symbols
— which is the right thing to copy, and was verified byte-identical against the source — takes the shapes and
leaves that attribute behind. The default is `meet`, so the artwork letterboxes: the promo's 480×400 viewBox
inside a 623×280 column drew 336px wide and centred, and the plate's own background rect stopped short of the
column edges. A lighter rectangle inside a darker one — precisely the "broken image" look the plate exists to
remove.

- **When porting from `<symbol>`/`<use>`, diff the USE SITE too.** The symbol carries geometry; the use site
  carries `preserveAspectRatio`, `width`/`height`, `class`, and any `aria-*`. In this mockup the tile plates
  deliberately differ from the panel plates — `plate plate--bare` with no `preserveAspectRatio` — so the
  answer was not one global attribute either.
- Verifying the shapes byte-for-byte is worth doing and is **not sufficient**. Both checks passed
  simultaneously: shapes identical, picture wrong.

## The shared lesson

Both defects are the same shape: **information that lives outside the fragment being copied.** A port
faithful to its fragment can still be wrong about the document it lands in. Look at a rendered page in a
browser before believing a port is done — in s17 both were found in the first screenshot taken, after the
suites were already green.

## s18: the collision does not only come from the mockup

The theme shares its class namespace with Basecoat — and, on storefront pages, with **WooCommerce**,
whose markup this theme does not write and cannot rename. Woo's product tab list is
`class="tabs wc-tabs"`, and `.tabs` is Basecoat's tabs component: it set `flex-direction: column` on
every product page's tab list, which therefore rendered as a vertical stack. Nothing in this theme
declared that property, so nothing in this theme could be blamed by reading its own CSS.

The check is the same one that caught `.label` — grep the built bundle for the name — but the
*trigger* is broader than "before porting a class name from the mockup". It is: **before assuming a
rule of ours governs an element, list every stylesheet on the page that matches it.** Full write-up,
with the second s18 defect of the same family:
[[source-order-only-wins-the-properties-you-redeclare]].

## Related

- [[source-order-only-wins-the-properties-you-redeclare]] — the s18 generalisation: a rule wins on source order only for the properties it re-declares, and the colliding name can come from a dependency rather than the mockup
- [[qa-gates-cover-less-than-they-claim]] — the parent pattern: green results that measured something other than what was claimed
- [[svg-use-shadow-boundary-needs-custom-props]] — the other reason this project stopped using `<use>` at all
- [[tailwind-v4-layer-precedence]] — the other way an outside declaration wins over ours
