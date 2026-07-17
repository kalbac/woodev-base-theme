# ADR-006: i18n — English source strings, ru_RU shipped

- **Status:** Accepted (17.07.2026)
- **Deciders:** Maksim + Claude (brainstorm s1)

## Decision

- All user-facing source strings are English, text domain `woodev-base-theme`.
- A complete `ru_RU` translation ships with the theme (primary demo audience is Russian).
- Russian plural rule: avoid `_n()` for count-sensitive copy (Russian needs 3 plural forms vs WP's gettext handling of source English's 2); prefer count-agnostic phrasing with `number_format_i18n()` + `printf`. Never pass variables into i18n functions (WP-CLI `i18n make-pot` compatibility).

## Consequences

- wp.org-ready and internationally usable from day one.
- Translation completeness for ru_RU is a release-gate item (M3).
