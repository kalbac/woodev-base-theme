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

## Amendment — the theme's public name is «Woodev Base Theme» (27.07.2026, s16)

Theme Check found the promise above about to be broken: wp.org derives a theme's slug
from `Theme Name`, and `Woodev Base` yields `woodev-base` — matching neither the
`woodev-base-theme` directory nor the `woodev-base-theme` text domain. Left alone, that
is exactly the rework this ADR said submission would not need, and it gets more expensive
the moment a live site installs the theme: changing the text domain strands existing
translations, and renaming the directory breaks updates.

**Decision (Maksim, [#35](https://github.com/kalbac/woodev-base-theme/issues/35)):** rename
the theme rather than the directory. `Theme Name: Woodev Base Theme`, so the derived slug,
the directory and the text domain are all `woodev-base-theme`. The alternative — renaming
the directory and the domain to `woodev-base` — was rejected as the more expensive half of
the same fix: it touches every i18n call site, the wp-env configs, the test paths and a
constant `CLAUDE.md` fixes.

The **project** keeps its short name, Woodev Base. Only the theme header, and the
`readme.txt` title and copyright block that must mirror it, carry the longer form.

Verified with Theme Check run headlessly against the theme: the directory/slug WARNING is
gone, and it still fires when the check is handed the old `woodev-base` slug — so its
silence is a measurement, not an absence. The two REQUIREDs left standing are the missing
`screenshot.png` ([#36](https://github.com/kalbac/woodev-base-theme/issues/36), deferred)
and `Update URI`, which this ADR makes correct for v1 and which comes out at submission,
not before.
