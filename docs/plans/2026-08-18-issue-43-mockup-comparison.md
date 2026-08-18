# Issue #43 — Journal, long-form and utility-screen comparison

## Scope and method

Compared the shipped templates and adapter styles with the approved mockup:

- `docs/design/v2-mockup/woodev-base-identity.html#s-blog`
- `docs/design/v2-mockup/woodev-base-identity.html#s-sidebar`
- `docs/design/v2-mockup/woodev-base-identity.html#s-misc`

The purpose of this document is to record the differences **before** changing
the implementation. A mockup example is not automatically a theme feature: a
theme must not hard-code a specific store's claims, editorial taxonomy, or
content. Items below therefore distinguish visual/structural gaps from
intentional, reusable WordPress behaviour.

## Shipped coverage

The following mockup contracts are already implemented and should be preserved:

- The journal/archive cards are responsive (one, two, then three columns; two
  columns when a sidebar is visible), use real featured images and category
  badges, and retain a keyboard-accessible read-more link.
- Long-form content has the mockup's measure, typography, heading rhythm,
  links, blockquotes, lists and inline-code treatment.
- Standard WordPress widgets are styled as the mockup's sidebar widgets without
  inventing parallel widget markup.
- Comments use WordPress's comment walker and form submission contract rather
  than a custom client-side form.
- Search and 404 already have an accessible form and an SSR no-results state.

## Differences requiring implementation

| Priority | Surface | Difference from the mockup | Proposed theme-level outcome |
| --- | --- | --- | --- |
| P1 | Sidebar layouts | The mockup puts the archive sidebar on the right and the single-post sidebar on the left. `Layout` only permits `none` and `right`, so a single post can never use the approved left layout. | Add `left` as a sanitized Customizer position; render the same sidebar partial after content in the DOM and use grid ordering only at the desktop breakpoint. This retains the mobile content-first order and adds no duplicate markup. |
| P1 | Journal cards | `content.css` crops featured images at `16 / 9`; the approved cards use `16 / 10`. | Change the card image ratio to `16 / 10` and add an e2e geometry assertion. |
| P1 | Single post | The mockup has a journal context line (date + category) and a left sidebar. The current post has date + author, no category context, and always renders its thumbnail above the article. | Add category context without removing the useful author byline; make the featured-image treatment an explicit, tested article decision rather than an accidental carry-over from the generic page part. |
| P1 | Comments | The current core-compatible form is sound, but its submit button has only Basecoat's neutral `btn` class while the mockup uses the primary action treatment. | Make the submit control `btn btn--primary`; preserve the WordPress URL field and other core semantics. |
| P1 | Search results | The mockup has a search field followed by compact result rows. `search.php` currently shows only a heading and reuses the journal card grid. | Add a search-results part that keeps the normal WordPress query and pagination, but renders compact, typed result rows; keep the search form above the list. |
| P1 | 404 | The current alert-style no-results block is structurally valid but visibly different from the centred mockup: it lacks the short lede and explicit primary/secondary recovery links. | Retain the server-rendered search form and add centred recovery actions using safe theme URLs. |
| P2 | Journal index header | The full-width mockup includes a short journal description and category links; `index.php` only prints the posts-page title. | Add an optional, admin-authored description. Do not hard-code mockup copy or a fixed category list; category navigation must be derived from existing taxonomy and remain absent when there is nothing useful to show. |
| P2 | Breadcrumbs | The mockup draws breadcrumbs on archive, post and static text-page examples. The theme has no breadcrumb subsystem. | Decide and implement one small, SSR-only theme breadcrumb helper for the scoped templates, with correct current-page semantics and no SEO-plugin dependency. |

## Intentional differences — no direct port

- The mockup's reading-time label is editorial content, not a WordPress core
  field. It will not be fabricated until a separately specified, localisable
  calculation and display contract exists.
- Widget examples show a store-specific search placeholder, recent-post image
  thumbnails and numbered category badges. Core widgets do not all expose that
  markup, so existing real-widget styling is the correct reusable behaviour.
- The service-page example includes concrete delivery prices, payment methods
  and return promises. Those belong to the site's page content or WooCommerce
  configuration, never to theme defaults.
- The mockup's illustrative search results mix products and articles. The
  theme must not expand a site's search query to WooCommerce products unless
  WooCommerce and the site's own query configuration already do so.
- The mockup's comment form omits the optional Website field. The theme keeps
  WordPress's core field instead of silently changing a site-wide discussion
  setting.

## Verification plan

1. Extend unit tests for the expanded sidebar enum and its safe fallback.
2. Add integration coverage for markup decisions (article context, page
   breadcrumb and search-result part) without booting a browser.
3. Extend `tests/e2e/` with desktop and narrow-width sidebar ordering,
   card aspect ratio, search/404 recovery controls and keyboard-visible links.
4. Run the normal PHP, JS, build and non-Woo e2e gates; independently
   re-review the resulting diff before it is considered ready.

## Related

- `docs/design/v2-mockup/woodev-base-identity.html`
- `docs/adr/ADR-008-single-visual-identity.md`
- `docs/05-IMPLEMENTATION-ROADMAP.md`
