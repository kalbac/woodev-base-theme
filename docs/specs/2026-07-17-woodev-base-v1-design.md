# Woodev Base — v1 Design Specification

> Status: **Approved draft** (brainstorm session s1, 17.07.2026).
> Supersedes nothing; refines `PROJECT.md`. Architecture decisions are recorded as ADRs in `docs/adr/`.

## 1. Identity

| Field | Value |
|---|---|
| Product name | Woodev Base |
| Theme slug / text domain | `woodev-base-theme` |
| PHP namespace | `Woodev\Theme\Base` |
| Hook/function prefix | `woodev_base_` (filters/actions/functions) |
| Short prefix | `wtb` — CSS custom properties (`--wtb-*`), data attributes, and other places where brevity matters |
| Type | Universal WordPress theme with optional WooCommerce support |
| License | GPL-2.0-or-later (wp.org compatible) |

## 2. Goals and non-goals

**Goals**

- A clean, accessible, customizable universal WordPress theme.
- First consumer: demo sites for Woodev WooCommerce plugins; then public free distribution.
- WooCommerce support as a well-bounded integration layer, not the theme identity.

**Non-goals (v1)**

- No page-builder integrations (Elementor etc.).
- No block-theme / FSE architecture.
- No React or client-rendered application behavior.
- No child themes shipped by us (child-theme *compatibility* is required).

## 3. Fixed decisions (see ADRs)

| # | Decision | ADR |
|---|---|---|
| 1 | Hybrid architecture: classic PHP templates + `theme.json` | ADR-001 |
| 2 | Customizer is the user-facing settings mechanism; storage in `theme_mods` | ADR-002 |
| 3 | Floors: PHP ≥ 8.1; WordPress: latest 3 majors (floating); WooCommerce: latest 3 majors | ADR-003 |
| 4 | Basecoat as pinned npm dependency + own adapter layer | ADR-004 |
| 5 | Distribution: GitHub Releases + `Update URI` first; wp.org-compliant from day one, submit later | ADR-005 |
| 6 | i18n: English source strings, complete `ru_RU` translation shipped | ADR-006 |

## 4. Theme architecture

Classic template hierarchy (`index.php`, `single.php`, `page.php`, `archive.php`, `search.php`, `404.php`, …) with `get_template_part()`-based partials. `theme.json` provides:

- design tokens exposed to the block editor (palette, typography, spacing presets) kept in sync with the front-end CSS custom properties;
- editor styles so Gutenberg content matches the front end;
- `appearance-tools` / block supports as appropriate for a hybrid theme.

PHP code layout:

```text
woodev-base-theme/
├── style.css                 # theme header (incl. Update URI, Requires PHP/WP)
├── functions.php             # bootstrap only: autoloader + Theme::instance()
├── theme.json
├── inc/                      # PHP classes (Woodev\Theme\Base\*)
│   ├── Theme.php             # composition root
│   ├── Setup.php             # supports, menus, image sizes
│   ├── Assets.php            # Vite manifest → enqueue, conditional loading
│   ├── Customizer/           # sections, controls, sanitizers, CSS-var renderer
│   ├── Templates/            # template helpers/controllers
│   └── Woo/                  # WooCommerce layer, loaded only when Woo is active
├── template-parts/           # header/, footer/, content/, components/
├── src/
│   ├── css/                  # Tailwind entry, tokens, basecoat adapter
│   └── js/                   # Alpine modules, theme JS
├── assets/
│   ├── dist/                 # build output (not in git; part of release ZIP)
│   └── static/               # images, icons committed as-is
├── languages/                # .pot + ru_RU.po/.mo
└── tests/                    # unit/, integration/, e2e/
```

- Lightweight `spl_autoload_register` classmap-style autoloader; **no Composer autoloader in production** (Composer is a dev-only tool for QA tooling).
- `Theme.php` is the composition root; features are small classes with single responsibilities registered from there. No god-objects, no static-everything.

## 5. Frontend stack

### Build: Vite

- Entries: `src/css/app.css`, `src/js/app.js` (+ separate `woo.css`/`woo.js` bundle).
- **Design-token single source:** tokens (colors incl. light/dark values, typography, spacing, radius) are defined once in `src/tokens/` (JS/JSON module); a small build step generates both the CSS custom properties file and `theme.json`. Neither CSS token values nor `theme.json` presets are ever edited by hand — zero front-end/editor drift.
- Production: hashed filenames + `manifest.json`; `Assets.php` resolves the manifest for `wp_enqueue_*`.
- Dev: Vite dev server with HMR against a local WordPress (wp-env); a `WOODEV_BASE_DEV` flag switches enqueues to the dev server.

### CSS: Tailwind v4 + Basecoat + adapter

Layer order (explicit, single source of truth in `app.css`):

```css
@layer theme, base, components, adapter, utilities;
```

- `components` — Basecoat imports (npm, version pinned).
- `adapter` — **the only place** where Basecoat is overridden/extended and where project components live.
- Interactive state overrides that must beat utilities (`:disabled`, `.is-loading`, …) are declared **outside all layers** — inherited rule from woodev-theme (see `docs/gotchas/tailwind-v4-layer-precedence.md`).
- Design tokens are CSS custom properties on `:root`, dark mode via the `.dark` class on `<html>` (Basecoat's upstream convention; class toggling, not media-query-only); no `@theme inline` so the runtime cascade stays active.
- Utility usage in PHP templates is allowed but repeated patterns (3+ occurrences) must be promoted to an adapter component class.

### JS: Basecoat JS + Alpine.js split

- **Basecoat's own vanilla JS** drives Basecoat components (dialog, dropdown, tabs, select, popover, toast): its markup contract and a11y behavior are already correct — do not rewrite in Alpine.
- **Alpine.js** owns theme-level behavior: mobile navigation state, disclosure patterns not covered by Basecoat, filters, async UI states, WooCommerce interface behavior.
- Progressive enhancement is mandatory: pages must be meaningful with JS disabled; Alpine adds behavior, never renders primary content.
- Conditional loading: the Woo bundle loads only on Woo pages; heavy modules load only where used.

## 6. Customization model

- WordPress Customizer, settings stored as `theme_mods`. Every control has a sanitize callback; capability and nonce handling per WP core conventions.
- Sections (v1): Colors (presets + key tokens, light/dark values), Typography, Layout (container width, radius scale), Header variants, Footer variants, and a WooCommerce section that registers only when Woo is active.
- Rendering: settings compile to CSS custom properties emitted in a single inline `<style>` after the main stylesheet, overriding token defaults. The theme is fully functional with zero settings touched.
- Presets first, few high-value controls; no raw token dump into the UI. Reset-to-defaults supported.

## 7. WooCommerce layer (Milestone 2)

- Namespace `Woodev\Theme\Base\Woo`, bootstrapped only when WooCommerce is active; base theme degrades gracefully without it.
- Declared support: `woocommerce` (+ gallery features as designed), `WC requires at least` / `WC tested up to` kept current.
- Override policy: **hooks and CSS first, template overrides last resort.** Every override file documents the source template version; overrides are audited on each supported Woo major release.
- Native flows (cart AJAX, checkout, fragments) are styled and hooked, never replaced. No fragile dependencies on Woo internal markup — style via our own wrappers and body classes where possible.
- Covered areas: shop/archive, single product, cart, checkout, account, store notices.

## 8. Quality baseline

- **Accessibility:** WCAG 2.1 AA target; keyboard navigation, visible focus, reduced-motion support, correct semantics/labels/states per component. Verified per component, not "at the end".
- **Browsers:** evergreen — last 2 versions of Chrome/Firefox/Safari/Edge; no IE.
- **Fonts (v1):** system font stack (`system-ui` based) — zero payload, zero licensing, good Cyrillic. A bundled OFL font (served locally, never from Google CDN) may be added as a Customizer option in M1+. Icons: decided at M1 (Lucide is the likely candidate, ISC license).
- **Testing (mandatory, all three levels):**
  - *Unit* — PHPUnit + Brain\Monkey for PHP (no WP bootstrap), Vitest for JS modules.
  - *Integration* — WordPress test suite (`WP_UnitTestCase`) under wp-env; Woo integration tests in M2.
  - *e2e* — Playwright against a wp-env site: smoke flows, key user journeys, visual checks of core templates (light + dark).
- **Static analysis / lint:** PHPStan level 8, PHPCS with WPCS + Theme Review sniffs and documented modern-syntax deviations (`phpcs.xml.dist` is the source of truth), ESLint + Prettier for JS/CSS.
- **Release gate:** Theme Check plugin pass + full test suite green + build reproducible via CI (GitHub Actions).

## 9. Engineering conventions

See `AGENTS.md` (authoritative for coding agents). Highlights:

- PHP 8.1+ modern syntax everywhere it is possible: `[]`, arrow functions, constructor promotion, `readonly`, enums, `match`, typed everything, `declare(strict_types=1)`.
- SOLID, DRY (refactor at 3+ occurrences), YAGNI, KISS.
- WordPress canon over invented conventions: escaping on output, i18n on every user-facing string, prefixed hooks, proper enqueueing.
- Russian plural rule: avoid `_n()` for count-sensitive copy; use count-agnostic phrasing with `number_format_i18n()`.

## 10. Milestones

| Milestone | Scope | Exit criteria |
|---|---|---|
| **M0 — Bootstrap** | Repo, docs, tooling skeleton (Vite, wp-env, CI, lint/test harness), theme boots with empty index | CI green on scaffold; theme activates cleanly |
| **M1 — Core theme** | Tokens + theme.json, Basecoat adapter, base templates, header/footer variants, navigation, Customizer v1, i18n | Demo content site fully usable; a11y pass; all tests green |
| **M2 — WooCommerce layer** | Woo templates/hooks/CSS, Woo Customizer section, Woo e2e | Demo store usable end-to-end; override audit doc complete |
| **M3 — Public release prep** | Theme Check, wp.org compliance audit, docs, ru_RU completion, release automation | Release ZIP via GitHub Actions; Update URI self-update verified |

## 11. Open items (deferred, tracked in CURRENT-STATE)

- Concrete WP floor number to print in `style.css` at M0 (per floating "latest 3 majors" policy).
- Basecoat version pin + upstream watch process.
- Full component/template inventory for M1 (planned at M1 kickoff).
- Icons selection and licensing audit (M1); fonts resolved: system stack (see §8).
