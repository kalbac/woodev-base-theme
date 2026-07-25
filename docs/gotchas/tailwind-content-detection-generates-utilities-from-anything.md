# Tailwind's automatic content detection generated a utility that collided with WooCommerce's own class

**Area:** Build / CSS · **Found:** s12 (Codex re-critic on the Woo layer, then verified in the built bundle)

## What happened

`src/css/app.css` declared its sources explicitly:

```css
@source "../../woodev-base-theme/**/*.php";
@import 'tailwindcss';
```

and the shipped bundle nevertheless contained:

```css
.col-1{grid-column:1}
.col-2{grid-column:2}
```

`.col-1` / `.col-2` are **WooCommerce's own class names** for the checkout billing/shipping columns (`woocommerce-layout.css`: `.woocommerce .col2-set .col-1 { float: left; width: 48% }`). So the theme was injecting `grid-column` onto Woo's markup on every checkout, cart-address and my-account-address screen — from a utility nobody asked for.

The class names came from `docs/design/v2-mockup/woodev-base-identity.html`, the approved design mockup, which uses `col-1`/`col-2` as ordinary layout classes. That file is documentation. It is not shipped, not scanned deliberately, and not part of the theme.

## Why

An explicit `@source` rule **adds** a path to Tailwind v4's automatic content detection; it does not replace it. Automatic detection walks the project root (minus `.gitignore`d paths and binaries) and treats any class-like string it finds as a candidate. Every markdown file, JSON config, design mockup, test fixture and archived HTML export in the repository is therefore a potential source of shipped CSS.

That is harmless right up to the moment a candidate string happens to be a real Tailwind utility name **and** a class some third-party CSS already relies on. `col-2` is both.

## Why grepping the source could not find it

Two greps looked clean and both were misleading:

- Grepping `src/css/**` for the selector found nothing — the rule is generated, not written.
- Grepping `woo.css` for unscoped rules "proved" every rule was nested under `.woocommerce` — while `woo.css` began with `@import 'tailwindcss'`, which expands at build time into a full un-scoped preflight and utility set. The built `woo-*.css` was 45,895 bytes, of which the storefront skin was about 30,000.

**Rule: for anything a build generates, assert against `assets/dist`, not against the source.** A source grep cannot see what an `@import` adds.

## The fix

Turn detection off and name the sources:

```css
@import 'tailwindcss' source(none);

@source "../../woodev-base-theme/**/*.php";
@source "../../src/js/**/*.js";
```

Result: 48 generated utilities disappeared, none were added, and the bundle shrank 147.7 → 142.9 kB. Verified that the utilities the theme genuinely uses (`mt-4` in `404.php`, `sr-only`) survived, and that the theme ships no other class-carrying file type — `theme.json` has no `className` strings and there are no block-template `.html` files.

Separately, `@import 'tailwindcss'` was removed from `src/css/woo.css` entirely: that file uses no Tailwind feature at all, and `app.css` already scans the same PHP glob, so its copy of preflight+utilities was pure duplication shipped to every storefront page.

## How to check it stayed fixed

```bash
S=$(ls woodev-base-theme/assets/dist/assets/style-*.css)
grep -o '\.[a-z0-9\\:_-]*{' "$S" | sort -u   # the complete generated-selector list
```

Diff that list before and after touching `@source` rules. Anything appearing that no theme PHP or JS file mentions came from a file that should not be scanned.

## Related

- [[tailwind-v4-layer-precedence]] — utilities beat `@layer adapter`, which is why a leaked utility wins over the skin
- [[basecoat-tokens-are-un-layered]] — the other case where an import brought more than its name suggested
- [[qa-gates-cover-less-than-they-claim]] — same family: a green check that never looked at the artifact
