# Front-page sections (#40)

> Written 18.08.2026. Continues the approved identity implementation after s19.

## Scope

Implement the three sections that remain in mockup §05 (`id="s-home"`):

1. `Выбор недели` — a WooCommerce product loop sourced from published, visible
   products ordered by WooCommerce popularity. The label is editorial; the theme
   does not pretend to calculate a calendar-week report or introduce a reporting
   cache. The section is absent when WooCommerce is unavailable or the query is
   empty.
2. `Журнал` — the three newest published `post` entries, with a real permalink,
   date/category metadata, excerpt, and a featured image or token plate fallback.
   The section is absent when there are no posts.
3. `Письмо раз в месяц` — a Customizer textarea containing a third-party form
   shortcode. The theme owns only the visual wrapper. It renders nothing when
   the setting is empty or its shortcode tag is not registered; it never stores
   email addresses or implements a submit handler.

The old `2026-07-28-front-page-completion.md` deliberately excluded these
sections because their data sources had not been decided. This plan supersedes
that out-of-scope boundary for #40 without changing the earlier decisions for
the already-shipped hero, value band, categories, or promo.

## Implementation

### P0 — Surface probe

- Keep `scripts/surface-probe.mjs` as a committed diagnostic command.
- Capture nine real surfaces at one explicit viewport, including full-page PNGs,
  h1 count, selected computed-style values, and element geometry.
- Store output under ignored `surface-probe-out/`.

### P1 — Front-page templates

- Add `template-parts/front/product-picks.php`.
- Guard on `function_exists( 'wc_get_products' )` and use `wc_get_products()` with
  `limit => 4`, `status => 'publish'`, `visibility => 'visible'`,
  `orderby => 'popularity'`, `order => 'DESC'`, `return => 'objects'`.
- Render the standard Woo loop start/end and `content-product` template so the
  existing hooks, card override, sale/out-of-stock badges, rating, price, and
  add-to-cart behavior remain the single product-card contract.
- Save and restore the previous `$product` global and post loop state.
- Add `template-parts/front/journal.php` with a bounded `WP_Query` for three
  published posts and a dedicated editorial-card markup. Use the existing post
  escaping/category patterns; use `Plate::render()` for posts without a featured
  image, with a deterministic `post_id % 3` variant.
- Add both parts to `front-page.php` after promo, matching mockup order.

### P2 — Newsletter setting

- Add `front_newsletter_shortcode` to `Settings` and the Front page Customizer
  section, defaulting to an empty string.
- Sanitize as plain textarea text; at render time extract the first shortcode tag,
  require `shortcode_exists()`, then call `do_shortcode()`.
- Wrap only registered plugin output in the mockup's newsletter structure. Do not
  print a raw unregistered shortcode and do not add mail, AJAX, REST, storage,
  nonce, consent, or provider logic to the theme.

### P3 — Visual layer and tests

- Add front-specific wrapper/card/newsletter CSS in the existing adapter layer.
- Add the four-column desktop override only to the front-page product grid; retain
  the existing Woo archive grid contract elsewhere.
- Add unit coverage for shortcode sanitization and registration defaults.
- Add integration coverage for product/journal/newsletter suppression and render
  contracts, including no-Woo behavior.
- Add Woo e2e coverage for four product cards, journal cards, shortcode output,
  and the mobile/desktop grid geometry. Assertions must use real measurements or
  accessibility-tree names, not raw CSS tokens.

## Verification

Run one Docker-backed suite at a time:

1. `npm run build`
2. PHPCS, PHPStan, ESLint, Prettier, token check
3. unit and integration tests
4. base e2e
5. full `e2e:woo` after a completed global setup
6. inspect surface-probe PNGs and JSON
7. run the Codex critic on the complete diff, then re-critic fixes

Do not call the Woo e2e gate green if global setup times out or if the fixture
file was not rewritten by that setup.
