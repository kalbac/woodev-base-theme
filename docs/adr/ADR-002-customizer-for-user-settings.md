# ADR-002: WordPress Customizer as the user settings mechanism

- **Status:** Accepted (17.07.2026)
- **Deciders:** Maksim + Claude (brainstorm s1)

## Context

The theme needs user-facing customization (colors, typography, layout, header/footer variants, presets) without editing theme files. Options: Customizer, a dedicated settings page, or both.

## Decision

WordPress Customizer only (v1). Settings are stored as `theme_mods` and rendered as CSS custom properties in an inline style block overriding token defaults.

## Consequences

- Live preview, familiar UX, wp.org-friendly; fully supported for classic/hybrid themes.
- `theme_mods` storage is the long-lived contract; changing it later requires migration.
- Presets + few high-value controls; no raw token dump into the UI.
- A dedicated settings page can be grafted later if the Customizer hits a ceiling (🟡 reversible extension, not a rework).

## Related

- [[ADR-001-hybrid-theme-architecture]]
