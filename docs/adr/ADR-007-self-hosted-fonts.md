# ADR-007: Self-hosted Golos Text + IBM Plex replace the system font stack

- **Status:** Accepted (25.07.2026)
- **Deciders:** Maksim + Claude (s10 design approval, s11 implementation)
- **Supersedes:** `docs/specs/2026-07-17-woodev-base-v1-design.md` §9 "Quality baseline" → *Fonts (v1)* ("system font stack … a bundled OFL font may be added as a Customizer option in M1+")

## Context

The v1 spec chose a system font stack: zero payload, zero licensing questions, zero
wp.org review risk. That decision was made before the theme had a visual identity.

The approved whole-theme design (refined V2 «Обиход», `docs/design/v2-mockup/`) is built
on three type roles that the system stack cannot supply:

- `--font-display` — **Golos Text** (headings, prices, the whole editorial voice)
- `--font-body` — **IBM Plex Sans**
- `--font-mono` — **IBM Plex Mono** (order numbers, SKUs, tabular figures)

A system stack renders the design as a different design: on Windows it becomes Segoe UI,
on macOS SF Pro, and the vertical rhythm, cap heights and the display/body contrast the
mockup is composed around all collapse. Typography here is not decoration, it is the
identity.

Both families are **SIL OFL 1.1** (GPL-compatible, redistributable inside a wp.org theme)
and both ship **full Cyrillic** — which the design's Russian-language target audience
needs and which most "safe" webfont choices do not have.

## Decision

Ship Golos Text, IBM Plex Sans and IBM Plex Mono **self-hosted** inside the theme.

- Files live in `woodev-base-theme/assets/fonts/`, `woff2` only, subset to
  **latin + latin-ext + cyrillic + cyrillic-ext**; other subsets (greek, vietnamese) are
  dropped.
- `@font-face` is declared by the theme's own CSS, `font-display: swap`, with the system
  stack as the fallback in every `--font-*` token — so an unloaded font degrades to the
  v1 behaviour rather than to invisible text.
- **No external requests, ever.** No Google Fonts CDN, no `fonts.gstatic.com` — that is a
  wp.org review requirement and a GDPR one, not a preference.
- License texts ship alongside the files (`assets/fonts/LICENSE-*.txt`) and are credited
  in `readme.txt`.
- The Customizer exposes a **font** choice; the shipped families are the default, and the
  control's other option is the system stack (no download at all).

## Consequences

- **Payload, measured 25.07.2026** (the ≤120 KB figure this ADR first carried was an
  estimate written before anything was built; these are the real numbers):

  | | shipped on disk | fetched by a Russian page (cyrillic + latin) |
  |---|---|---|
  | Golos Text (variable, 500–800) | 94.4 KB | 58.5 KB |
  | IBM Plex Sans (variable, 400–700) | 126.7 KB | 73.5 KB |
  | IBM Plex Mono (static, 400/500/600) | 130.9 KB | 69.7 KB |
  | **Total** | **352.0 KB** | **~132 KB**, or ~202 KB where mono renders |

  Shipped bytes and downloaded bytes are different numbers and only the second one is a
  user cost: `unicode-range` means a browser fetches a subset file only when a character
  in its range is actually rendered in that family, so greek/vietnamese were dropped and
  the `-ext` subsets rarely load at all. Mono is the outlier — 131 KB on disk for order
  numbers and SKUs — because Google served it as three static instances while the other
  two families came as single variable files per subset.
- **M3 reduction, concrete:** re-instance with `pyftsubset` (drop unused weight-axis range
  on the variable faces; cut Mono to the one or two weights the shipped templates truly
  need). Not done at v1 because it needs a font toolchain this repo does not have, and
  because cutting weights now would deviate from the approved design before anything has
  been seen in a browser.
- One known gap from the vendored source: the design uses IBM Plex **Mono 700** once
  (`.totals .row.grand .amount`) and no 700 static instance exists in the Google response
  we derive from, so that rule falls back to 600. The M3 re-subset closes it.
- The theme now carries third-party font binaries. For v1 they are **derived by a
  documented script** from the subsets already vendored in `docs/design/v2-mockup/assets/`
  (a `fonts.googleapis.com` response: correctly subset OFL `woff2`, hash-named): the
  script keeps only the four subsets and the weights the design uses, renames the files
  readably, and rewrites the `@font-face` CSS. Deterministic and offline — but it inherits
  whatever font version Google served on 25.07.2026. Regenerating from upstream OFL
  releases with `pyftsubset` is an **M3** item, not a v1 blocker.
- wp.org review surface: license files + credits are mandatory, and are part of M3.
- Reversible (🟡): every consumer reads `--font-display` / `--font-body` / `--font-mono`,
  so dropping back to the system stack is a token change plus deleting the files.

## Related

- [[ADR-001-hybrid-theme-architecture]]
- [[ADR-008-single-visual-identity]]
- `docs/design/v2-mockup/` — the approved design
