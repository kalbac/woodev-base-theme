// tests/e2e-woo/global-setup.mjs
//
// Prepares the isolated Woo wp-env environment (http://localhost:8891,
// .wp-env.woo.json) before any tests/e2e-woo spec runs.
//
// WHY THIS EXISTS: wp-env's `themes` AND `plugins` keys INSTALL — they do NOT
// activate. Right after `wp-env start --config=.wp-env.woo.json`, `wp theme
// list` shows `woodev-base-theme inactive` and `wp plugin list` shows
// `woocommerce inactive`. Every downstream assertion would otherwise run
// against the wrong theme, with Woo turned off entirely. See
// docs/gotchas/wp-env-installs-themes-without-activating-them.md.
//
// Also seeds the storefront: three simple products with known slugs so the
// specs can target them deterministically:
//   - wtb-product-simple  — plain simple product, regular_price only
//   - wtb-product-sale    — regular_price + sale_price strictly below it
//   - wtb-product-oos     — flagged out of stock via --in_stock=false
//
// NOTE ON THE OOS FIELD: `wp wc product create` in the Woo shipped by
// wordpress.org (verified 24.07.2026, Woo 11.0.0-beta.2) does NOT expose a
// --stock_status parameter — the schema only carries --in_stock (boolean),
// which Woo's product setter internally maps to stock_status=outofstock when
// false. Confirmed against `wp wc product create --help` in the container.
//
// The Woo container persists state across restarts, so every create is
// idempotent (delete-by-slug first) — same pattern tests/e2e/global-setup.mjs
// uses for pages/posts.
//
// All wp-cli goes through `npx wp-env run cli --config=.wp-env.woo.json wp …`.
// The `--config` flag is what pins commands to :8891 rather than the default
// :8888; omitting it silently talks to the wrong container.

import { execSync } from 'node:child_process';

/** wp-env config file for the isolated Woo environment (port 8891). */
const CONFIG = '.wp-env.woo.json';
/** Theme slug — must match woodev-base-theme/style.css. */
const THEME_SLUG = 'woodev-base-theme';
/** Plugin slug — WooCommerce. */
const WOO_SLUG = 'woocommerce';

/** Run a wp-cli command against the Woo environment, return trimmed stdout. */
function wp(command) {
  const full = `npx wp-env run cli --config=${CONFIG} wp ${command}`;
  return execSync(full, { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] }).trim();
}

/** Same, but swallow a non-zero exit (e.g. deleting something that isn't there). */
function wpTry(command) {
  try {
    return wp(command);
  } catch {
    return '';
  }
}

/**
 * Delete every product with this slug, then create a fresh one from `args`.
 *
 * Woo stores products as a CPT with slug/name — `wp wc product list --slug=…`
 * returns matches. Returns the new product ID.
 *
 * IMPORTANT: uses `--format=json` and parses in JS rather than
 * `--field=id --format=ids`. Two reasons found in this Woo:
 *  - `--field=id` is rejected as "Invalid field: id." (case/name mismatch in
 *    the WC-CLI schema); the previous approach failed silently through wpTry,
 *    the delete was skipped, and Woo re-suffixed the recreated product's slug
 *    to `<slug>-2` — leaving duplicates rather than reseeding cleanly;
 *  - `--fields=id --format=ids` DOES print the id, but also emits a
 *    `foreach() argument must be of type array|object, int given` warning
 *    from `class-wc-cli-rest-command.php:444`, which is a live bug in the
 *    plugin's CLI wrapper when reducing a scalar field. JSON avoids both.
 */
function reseedProduct(slug, args) {
  const listJson = wpTry(`wc product list --user=admin --slug=${slug} --format=json`);
  if (listJson) {
    const products = JSON.parse(listJson);
    for (const { id } of products) {
      wp(`wc product delete ${id} --user=admin --force=true`);
    }
  }
  return wp(`wc product create --user=admin --slug=${slug} --porcelain ${args}`);
}

export default function globalSetup() {
  const log = (...a) => console.log('[e2e-woo:setup]', ...a);

  // ── 1. Activate the theme, assert it took ────────────────────────────────
  log(`activating ${THEME_SLUG} on http://localhost:8891 …`);
  wp(`theme activate ${THEME_SLUG}`);
  const activeTheme = wp('theme list --status=active --field=name');
  if (activeTheme !== THEME_SLUG) {
    throw new Error(
      `[e2e-woo:setup] expected "${THEME_SLUG}" to be the active theme on :8891, got "${activeTheme}"`,
    );
  }
  log(`confirmed active theme: ${activeTheme}`);

  // ── 2. Activate WooCommerce, assert it took ──────────────────────────────
  log(`activating ${WOO_SLUG} …`);
  wp(`plugin activate ${WOO_SLUG}`);
  const activePlugins = wp('plugin list --status=active --field=name').split(/\r?\n/);
  if (!activePlugins.includes(WOO_SLUG)) {
    throw new Error(
      `[e2e-woo:setup] expected "${WOO_SLUG}" to be active on :8891, got: ${activePlugins.join(', ')}`,
    );
  }
  log(`confirmed active plugins include: ${WOO_SLUG}`);

  // ── 3. Ensure Woo pages exist (shop/cart/checkout/my-account) ────────────
  //
  // The `wp wc tool run install_pages` command is documented under the
  // `wp wc tool` namespace in WooCommerce's CLI (`wp wc tool list` shows it).
  // Verified against the installed Woo 10.9.x by running `wp wc tool list`
  // first and picking the tool id that installs the default pages.
  log('running Woo install_pages tool to ensure /shop/, /cart/, /checkout/, /my-account/ exist …');
  wp('wc tool run install_pages --user=admin');
  log('install_pages done.');

  // ── 4. Seed three simple products, idempotently ──────────────────────────
  //
  // Field names come from the WC REST API product schema (which the wc-cli
  // forwards): regular_price, sale_price, stock_status, manage_stock, type,
  // name, description, short_description.
  const simpleId = reseedProduct(
    'wtb-product-simple',
    [
      '--type=simple',
      '--name="WTB Simple Product"',
      '--regular_price=19.99',
      '--description="A plain simple product seeded by the Woo e2e global-setup."',
      '--short_description="Plain simple product for e2e."',
    ].join(' '),
  );
  log(`simple product wtb-product-simple → id ${simpleId}`);

  const saleId = reseedProduct(
    'wtb-product-sale',
    [
      '--type=simple',
      '--name="WTB Sale Product"',
      '--regular_price=29.99',
      '--sale_price=19.99',
      '--description="A simple product on sale, seeded by the Woo e2e global-setup."',
      '--short_description="On-sale simple product for e2e."',
    ].join(' '),
  );
  log(`sale product wtb-product-sale → id ${saleId}`);

  const oosId = reseedProduct(
    'wtb-product-oos',
    [
      '--type=simple',
      '--name="WTB Out of Stock Product"',
      '--regular_price=24.99',
      '--in_stock=false',
      '--description="A simple product marked out of stock, seeded by the Woo e2e global-setup."',
      '--short_description="Out-of-stock simple product for e2e."',
    ].join(' '),
  );
  log(`out-of-stock product wtb-product-oos → id ${oosId}`);

  log('done.');
}
