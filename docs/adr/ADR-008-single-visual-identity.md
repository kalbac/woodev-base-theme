# ADR-008: One visual identity replaces the eight Basecoat style packs

- **Status:** Accepted (25.07.2026)
- **Deciders:** Maksim (decision) + Claude (s11)
- **Amends:** `docs/specs/2026-07-17-woodev-base-v1-design.md` §6 "Customization model" —
  the *Basecoat style packs* and *Primary color presets* subsections (`style_preset` and
  `primary_preset`)

## Context

Spec §6 shipped Basecoat's eight style packs (`luma`, `lyra`, `maia`, `mira`, `nova`,
`rhea`, `sera`, `vega`) as an admin choice: eight standalone Vite bundles, a generator
(`scripts/build-pack-entries.mjs`), a `style_preset` Customizer control, and a test matrix
that multiplies by eight. Upstream forbids combining packs, hence one bundle each.

s10 produced and Maksim approved a **whole-theme visual identity** (refined V2 «Обиход»),
which defines its own complete token layer: neutral scale derived from a temperature
variable `--n-h`, seven colour-palette presets, an accent expressed as `--accent-h` /
`--accent-c`, one `--radius` base with everything else `calc()`-derived, three type roles,
its own elevation and motion scales.

A Basecoat pack overrides exactly those things. Keeping both means the admin can pick a
combination in which the approved design does not exist. The two are not composable
features; they are two answers to the same question.

## Decision

**The identity replaces the packs.** The theme ships one visual identity and one CSS
bundle.

What the admin picks instead (all single-point token changes by design):

| Control | What it writes |
|---|---|
| Colour palette preset (7) | `--n-h`, `--accent-h`, `--accent-c` |
| Accent colour | `--accent-h`, `--accent-c` |
| Border radius (rounded → zero) | `--radius` |
| Font | the three `--font-*` roles |
| Add-to-cart reveal | `always` / `hover` (a data attribute) |
| Colour scheme | unchanged from M1-05 (`light` / `dark` / `system`) |

Removed: the `style_preset` setting and its eight bundles, `scripts/build-pack-entries.mjs`
and `scripts/lib/packs-lib.mjs`, `src/css/packs/`, the eight Vite inputs, and the
Tailwind-palette `primary_preset` presets — superseded by the seven design palettes.

Basecoat itself stays (ADR-004 unchanged): the theme keeps using its component classes and
its vanilla JS. Only the *skin* is ours now, in `@layer adapter`, driven by the identity's
tokens.

## Consequences

- The block-editor palette (`theme.json`) can finally match the front end, because there
  is one answer to "what colour is `--primary`" instead of eight (spec §6's documented v1
  limitation goes away).
- The e2e matrix collapses from 8 packs to 7 palettes × 2 schemes, and the palettes differ
  only in two or three custom properties — far cheaper to assert.
- M1-03's pack machinery is retired. Its durable half — the un-layered token architecture,
  the AA contrast gate in the token generator, the Basecoat-beats-layers knowledge — is
  kept and carried into the new token layer.
- **The Basecoat entry is `basecoat-css/base`.** Loading a pack underneath the identity
  would mean every adapter rule fighting 42 KB of `@layer components` doing the same job
  differently.

  **Corrected 25.07.2026, after reading the shipped files rather than trusting the name:**
  `basecoat-css/base` is *not* skin-free. Its `base/base.css` declares a full un-layered
  `:root`/`.dark` token baseline — shadcn greyscale, `--radius: 0.625rem`,
  `--font-sans: "Geist Sans"`, plus `--chart-*`, `--sidebar-*`, `--scrollbar-*`,
  `--chevron-down-icon` and `--check-icon`. Only the *component* files (`button.css` and
  friends) are genuinely structure-only `@apply` rules with no colour or geometry.

  This does not change the decision, but it changes what we own:
  - Every token we emit overrides Basecoat's, because ours is un-layered and imported
    after it — verified in the built bundle, and the same source-order contract the token
    layer already depended on.
  - Every token we do **not** emit silently keeps a foreign greyscale default. Today that
    is `--chart-*`, `--sidebar-*` and the two icon tokens. `--scrollbar-thumb` is the
    happy exception: it is defined as `var(--border)`, so it follows our identity for
    free.
  - Components the mockup does not draw (toast, command palette, combobox) therefore
    render in shadcn grey rather than not at all — which is *worse* than bare, because it
    looks deliberate. They must be styled in the identity's language as they are first
    used.

  Reversible in one import line (🟡).
- Sites that wanted a *different* look are served by the palette/radius/font controls, not
  by a foreign design system.

## Related

- [[ADR-004-basecoat-npm-adapter]]
- [[ADR-002-customizer-for-user-settings]]
- [[ADR-007-self-hosted-fonts]]
- `docs/design/v2-mockup/` — the approved design (source of truth)
- `docs/gotchas/basecoat-tokens-are-un-layered.md`
