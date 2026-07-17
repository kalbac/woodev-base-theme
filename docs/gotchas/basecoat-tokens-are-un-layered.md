# Basecoat's tokens are un-layered — `@layer theme` tokens lose, and `layer(components)` won't build

> Discovered s2 (17.07.2026) executing M0 Task 5, proven by sentinel builds against basecoat-css 1.0.2 + Tailwind 4.3.3 + Vite 8.1.5. This corrected two assumptions baked into the M0 plan.

## The traps

1. **`@import "basecoat-css" layer(components);` is a hard build failure.** It sweeps Basecoat's whole import graph — including the top-level `@custom-variant dark (&:is(html.dark *));` in `dist/base/base.css` — inside a layer, and Tailwind v4 rejects that outright:
   `[plugin @tailwindcss/vite:generate:build] Error: '@custom-variant' cannot be nested.`
   No output is emitted at all.
2. **The wrapper is unnecessary anyway.** Basecoat already self-declares `@layer components` in 38 of its 39 component files and in every style pack. A plain `@import "basecoat-css";` lands its components in `components` exactly as the spec's layer order requires.
3. **Basecoat declares its own `:root`/`.dark` token defaults UN-LAYERED**, and un-layered CSS beats every layer. So design tokens wrapped in `@layer theme` are silently overridden by Basecoat — the build succeeds, the values look right (both sides ship identical shadcn defaults), and nothing appears broken until the Customizer tries to move a token in M1 and can't.

## Proof

Sentinel builds, reading the effective `--background` out of the compiled bundle:

| Our tokens declared as | Where they landed | Winner |
|---|---|---|
| `@layer theme { :root { … } }` | `[theme]`, hoisted above Basecoat | **Basecoat** — our tokens dead |
| un-layered `:root { … }`, imported after Basecoat | un-layered, last in source order | **ours** ✅ |

In the real bundle both blocks are un-layered `:root`; Basecoat's is identifiable by `--card`/`--popover` (tokens we don't ship) and ours follows it.

## How to apply here

- `src/css/app.css`: `@import "basecoat-css";` with **no** `layer()` wrapper, and `@import "./tokens.generated.css";` **after** it. That import order is load-bearing — moving the tokens line above Basecoat silently discards every token.
- `buildTokensCss()` emits un-layered `:root`/`.dark`. A unit test asserts no `@layer` rule survives in the generated CSS; e2e asserts the computed `--background` equals our token value, because only a runtime check catches a silent cascade regression.
- The declared order `@layer theme, base, components, adapter, utilities;` still stands and is still correct: Tailwind's and Basecoat's `@theme` blocks compile into `theme`, Basecoat's components into `components`, ours into `adapter`.
- Basecoat's `@theme { --color-background: var(--background); … }` is **not** `@theme inline` — the mapping keeps `var()` indirection, so overriding `--background` at runtime propagates to `bg-background` utilities. Spec §5's "no `@theme inline`" requirement is satisfied by upstream.

## Related

- [[tailwind-v4-layer-precedence]] — trap 2 (un-layered beats all) is exactly what bites here
- [[basecoat-js-entry-is-a-subpath-export]] — the sibling trap on the JS side
- ADR-004 (Basecoat npm + adapter) — unaffected: npm + adapter still hold
- `docs/specs/2026-07-17-woodev-base-v1-design.md` §5
