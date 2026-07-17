# ADR-004: Basecoat via pinned npm dependency + adapter layer

- **Status:** Accepted (17.07.2026)
- **Deciders:** Maksim + Claude (brainstorm s1)

## Context

Basecoat (shadcn-inspired vanilla HTML/CSS/JS components) is the UI foundation. Options: npm dependency, vendored copy, or full fork.

## Decision

Pinned npm dependency imported at build time. All customization lives in our own `adapter` CSS layer and project components. Upgrades are deliberate (review changelog, bump pin, run visual e2e).

Basecoat's own vanilla JS drives Basecoat components (dialogs, dropdowns, tabs, …) — it is not rewritten in Alpine. Alpine owns theme-level behavior only.

## Consequences

- Upstream fixes (a11y, bugs) arrive via version bumps; markup contract churn is absorbed by the adapter layer.
- The compiled CSS ships in the theme dist — no runtime dependency for users.
- Fallback: if upstream breaks trust, switch to a vendored copy (🟡 reversible; the adapter layer makes the swap cheap).

## Related

- [[ADR-001-hybrid-theme-architecture]]
- docs/gotchas/tailwind-v4-layer-precedence.md
