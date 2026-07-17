# ADR-001: Hybrid theme architecture (classic templates + theme.json)

- **Status:** Accepted (17.07.2026)
- **Deciders:** Maksim + Claude (brainstorm s1)

## Context

Woodev Base must use server-rendered PHP markup (Basecoat + Tailwind + Alpine) without React. Options: classic theme, block/FSE theme, or hybrid.

## Decision

Hybrid: classic PHP template hierarchy and template parts, plus `theme.json` for design tokens, editor palette/typography presets, and editor styles.

## Consequences

- Full control over front-end markup — required by the Basecoat/Tailwind/Alpine stack.
- Gutenberg content stays visually consistent with the front end via theme.json/editor styles; tokens must be kept in sync between `theme.json` and CSS custom properties (single generation source preferred).
- Site Editor (FSE) is not available to users; the Customizer covers customization (ADR-002).
- FSE migration, if ever needed, is a separate major effort — accepted risk.

## Related

- [[ADR-002-customizer-for-user-settings]]
