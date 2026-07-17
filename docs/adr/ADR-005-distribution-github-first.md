# ADR-005: Distribution — GitHub first, wp.org later

- **Status:** Accepted (17.07.2026)
- **Deciders:** Maksim + Claude (brainstorm s1)

## Decision

- v1 ships via GitHub Releases; production sites self-update through the `Update URI` header in `style.css` (pattern proven on woodev-theme).
- The code is written to wp.org Theme Review requirements from day one (escaping, prefixes, no plugin-territory features, GPL-compatible assets).
- wp.org submission happens when the theme matures — without rework.

## Consequences

- Fast iteration for demo sites now; the public channel stays open later.
- Every feature must pass the "would Theme Review accept this?" filter even before submission.
- Release ZIP is built by GitHub Actions (build artifacts are not committed to git).
