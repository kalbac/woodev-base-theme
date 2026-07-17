# ADR-003: Version support floors

- **Status:** Accepted (17.07.2026)
- **Deciders:** Maksim + Claude (brainstorm s1)

## Decision

- **PHP:** ≥ 8.1 (enums, readonly, first-class callables; everything older is EOL in 2026).
- **WordPress:** floating floor — latest 3 major versions; the concrete number is pinned in `style.css` and docs at each release.
- **WooCommerce:** latest 3 major versions (matches Woo's own ecosystem policy); declared via `WC requires at least` / `WC tested up to`.

## Consequences

- Code may freely use PHP 8.1 syntax (see AGENTS.md coding standards).
- Each release must re-check the floating WP/Woo floors and update headers/docs.
- Test matrix in CI targets the supported floors, not older versions.
