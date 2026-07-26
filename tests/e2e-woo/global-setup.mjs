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
import { writeFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

/** wp-env config file for the isolated Woo environment (port 8891). */
const CONFIG = '.wp-env.woo.json';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

// Where the seeded product ids are exported for specs/helpers.mjs to read —
// see the docblock on writeFixtures() below for why a file, not process.env.
const FIXTURES_PATH = path.join(__dirname, '.fixtures.json');
/** Theme slug — must match woodev-base-theme/style.css. */
const THEME_SLUG = 'woodev-base-theme';
/** Plugin slug — WooCommerce. */
const WOO_SLUG = 'woocommerce';

// Known credentials for a seeded customer account. Some `.form-row select`
// legibility assertions (storefront.spec.mjs) need a page that only exists
// for a logged-in shopper — WooCommerce's country/state `<select>` lives on
// `/my-account/edit-address/billing/`, which requires an authenticated
// session (the unauthenticated login/register forms carry no `<select>` at
// all). Exported so the spec can log in through the real classic login form
// rather than duplicating credentials.
export const CUSTOMER_USERNAME = 'wtb-e2e-customer';
export const CUSTOMER_PASSWORD = 'WtbE2eCustomer!1';

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

/**
 * Assert that a WooCommerce page option points at a page that ACTUALLY carries
 * the block markup it is supposed to (B0, ADR-009).
 *
 * `wp wc tool run install_pages` is documented to create the Cart/Checkout
 * pages, but the plan explicitly says not to assume it worked: it is a no-op
 * when the option already points at an existing page (by design, so it does
 * not clobber a store owner's edits), and nothing stops that existing page
 * from having been hand-edited back to the classic shortcode. Every e2e spec
 * for the block surfaces (this task and B6) is worthless against a page that
 * silently reverted to classic markup, so this reads the option
 * `install_pages` itself writes, fetches that page's real `post_content`, and
 * greps for the block comment delimiter WordPress serializes for a block
 * (`<!-- wp:woocommerce/cart -->` / `<!-- wp:woocommerce/checkout -->`) —
 * verified against the live :8891 install via `wp post get --field=post_content`.
 *
 * @param {string} optionName WordPress option holding the page id.
 * @param {string} blockName  Fully-qualified block name, e.g. "woocommerce/cart".
 * @returns {number} the page id, once verified.
 */
function assertBlockPageExists(optionName, blockName) {
  const pageId = wpTry(`option get ${optionName}`);
  if (!pageId || !/^\d+$/.test(pageId)) {
    throw new Error(
      `[e2e-woo:setup] expected option "${optionName}" to hold a numeric page id, got ` +
        `${JSON.stringify(pageId)} — did "wp wc tool run install_pages" actually run?`,
    );
  }

  const content = wp(`post get ${pageId} --field=post_content`);
  const delimiter = `<!-- wp:${blockName} -->`;
  if (!content.includes(delimiter)) {
    throw new Error(
      `[e2e-woo:setup] page ${pageId} (option "${optionName}") does not contain "${delimiter}" — ` +
        `expected the block-based page install_pages creates, but got content starting: ` +
        `${content.slice(0, 200)}`,
    );
  }

  return Number(pageId);
}

/**
 * Write the ids this run seeded to a JSON file so specs/helpers.mjs can reach
 * a real product without depending on the id being stable across runs
 * (reseedProduct() deletes and recreates on every run, so the id changes).
 *
 * WHY A FILE, NOT process.env: Playwright's docs list `process.env` mutation
 * from globalSetup as the alternative, but that value only reaches worker
 * PROCESSES Playwright itself forks for the run that just executed
 * globalSetup. It does NOT persist to a later, separate `npx playwright test`
 * invocation — which this project's own verification workflow relies on (a
 * temporary config that omits globalSetup entirely and reuses whatever the
 * last full run seeded, to turn a multi-minute cycle into a ~15s one). A file
 * on disk survives that gap, is inspectable with a plain `cat` mid-debugging,
 * and carries no assumption about worker-fork timing relative to globalSetup
 * returning. Gitignored (`tests/e2e-woo/.fixtures.json`) since it is
 * regenerated every run and only meaningful against the container's current
 * state.
 *
 * Idempotent by construction: this OVERWRITES the file every run rather than
 * appending, so it never accumulates stale ids from a previous seeding pass.
 */
function writeFixtures(fixtures) {
  writeFileSync(FIXTURES_PATH, `${JSON.stringify(fixtures, null, 2)}\n`, 'utf8');
}

/** Slug of the page carrying a `[products]` shortcode loop. */
export const SHORTCODE_PAGE_SLUG = 'wtb-products-shortcode';

/**
 * Seed an ordinary PAGE whose content is a `[products]` shortcode.
 *
 * This is not a Woo page: `is_woocommerce()` is `is_shop() ||
 * is_product_taxonomy() || is_product()` and is FALSE here, yet the shortcode
 * renders `woocommerce/content-product.php` — our override, carrying every
 * `wtb-*` class — inside Woo's own `<div class="woocommerce columns-N">`
 * wrapper. That combination is exactly the case `inc/Woo/Assets.php` was
 * changed to serve (a conditional enqueue used to ship that markup with no
 * stylesheet at all) and `inc/Woo/CtaAttribute.php` alongside it, and nothing
 * asserted it end to end until s13.
 *
 * Idempotent by delete-then-create on the slug, same as `reseedProduct()`.
 */
function seedShortcodePage() {
  const existing = wpTry(`post list --post_type=page --name=${SHORTCODE_PAGE_SLUG} --field=ID`);
  for (const id of existing.split(/\r?\n/).filter(Boolean)) {
    wp(`post delete ${id} --force`);
  }
  // The shortcode is written WITHOUT attributes on purpose: this command goes
  // through `npx wp-env run cli`, which re-splits the command string, so inner
  // quotes are stripped and `[products limit="3"]` arrives as two positional
  // arguments and fails. Only three products are seeded anyway, so the default
  // limit is not doing any work here.
  return wp(
    `post create --post_type=page --post_status=publish --post_title="WTB Products Shortcode" ` +
      `--post_name=${SHORTCODE_PAGE_SLUG} --post_content="[products]" --porcelain`,
  );
}

/**
 * Attach `count` freshly generated placeholder images to a product — one
 * set as the featured image, the rest as the gallery — so
 * storefront.spec.mjs has a real product whose `.flex-control-thumbs` strip
 * carries 4+ thumbnails to test wrap/reachability against. WooCommerce's
 * flexslider gallery prints one thumbnail per image, featured image
 * included (verified against the installed 10.9.4
 * `templates/single-product/product-image.php`).
 *
 * Generated with GD directly through `wp eval` (confirmed available in the
 * container) rather than `wp media import`, which needs a file already
 * reachable from inside the container — nothing is mounted there except
 * the theme directory, and this repo carries no fixture images to upload.
 *
 * Idempotent: every attachment this creates carries a
 * `_wtb_e2e_gallery_marker` meta, and EVERY attachment carrying that meta is
 * force-deleted first — whatever value it holds. That "whatever value" is
 * load-bearing, and an earlier version got it wrong: it deleted only the
 * attachments whose marker equalled the CURRENT product id, while
 * `reseedProduct()` deletes and recreates the product before this runs, so the
 * product's id is new every time. The previous run's attachments were marked
 * with the OLD id, matched nothing, and survived — five orphaned media rows per
 * run, in a container that persists state across restarts. The marker still
 * records the product id, which is useful when inspecting the container by
 * hand; it is simply no longer what the cleanup query filters on.
 *
 * `post_status => 'any'` is explicitness, NOT part of the fix — measured on the
 * container, the marker query returns the same five attachments with `'any'`,
 * with `'inherit'`, and with the parameter omitted entirely (WP_Query special-
 * cases `post_type => 'attachment'`, and `inherit` is registered with
 * `exclude_from_search => false`, so `'any'` includes it). Recorded because an
 * earlier draft of this comment's commit message blamed the default status for
 * the orphans, which the measurement disproves.
 *
 * The PHP runs as a single `wp eval` line — no literal newlines — because
 * embedding a multi-line PHP string in this project's `wp-env run cli`
 * invocation was observed to truncate the command silently.
 *
 * @returns {number} total images attached (gallery + 1 featured).
 */
function seedGalleryImages(productId, count) {
  const php = [
    `$pid = ${productId};`,
    `$old = get_posts(['post_type' => 'attachment', 'post_status' => 'any', 'meta_key' => '_wtb_e2e_gallery_marker', 'posts_per_page' => -1, 'fields' => 'ids']);`,
    `foreach ($old as $aid) { wp_delete_attachment($aid, true); }`,
    `require_once ABSPATH . 'wp-admin/includes/image.php';`,
    `$upload_dir = wp_upload_dir();`,
    `$ids = [];`,
    `for ($i = 0; $i < ${count}; $i++) {`,
    `  $im = imagecreatetruecolor(400, 400);`,
    `  $color = imagecolorallocate($im, 40 + $i * 40, 120, 200 - $i * 30);`,
    `  imagefill($im, 0, 0, $color);`,
    `  $filename = 'wtb-gallery-' . $pid . '-' . $i . '.png';`,
    `  $filepath = $upload_dir['path'] . '/' . $filename;`,
    `  imagepng($im, $filepath);`,
    `  imagedestroy($im);`,
    `  $attach_id = wp_insert_attachment(['post_mime_type' => 'image/png', 'post_title' => $filename, 'post_content' => '', 'post_status' => 'inherit'], $filepath, $pid);`,
    `  wp_update_attachment_metadata($attach_id, wp_generate_attachment_metadata($attach_id, $filepath));`,
    `  update_post_meta($attach_id, '_wtb_e2e_gallery_marker', $pid);`,
    `  $ids[] = $attach_id;`,
    `}`,
    `$thumbnail_id = array_shift($ids);`,
    `set_post_thumbnail($pid, $thumbnail_id);`,
    `update_post_meta($pid, '_product_image_gallery', implode(',', $ids));`,
    `echo count($ids) + 1;`,
  ].join(' ');
  return Number(wp(`eval "${php}"`));
}

/**
 * Ensure a customer account exists with the fixed CUSTOMER_USERNAME /
 * CUSTOMER_PASSWORD credentials, so the spec can log in through the real
 * classic login form to reach a page only a logged-in shopper sees
 * (`/my-account/edit-address/billing/`, needed for the `<select>`
 * legibility check — see the constants' own comment). `wp user create`
 * fails loudly on a duplicate login, so check first; the password is reset
 * on every run regardless, so a stale password from an interrupted
 * previous run never blocks login.
 */
function seedCustomer() {
  const existingId = wpTry(`user get ${CUSTOMER_USERNAME} --field=ID`);
  if (!existingId) {
    wp(
      `user create ${CUSTOMER_USERNAME} wtb-e2e-customer@example.com --role=customer --user_pass="${CUSTOMER_PASSWORD}"`,
    );
  } else {
    wp(`user update ${existingId} --user_pass="${CUSTOMER_PASSWORD}"`);
  }
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

  // ── 3a. Assert the block Cart and Checkout pages actually carry block
  // markup — do not assume install_pages worked (B0, ADR-009). ─────────────
  const cartPageId = assertBlockPageExists('woocommerce_cart_page_id', 'woocommerce/cart');
  const checkoutPageId = assertBlockPageExists(
    'woocommerce_checkout_page_id',
    'woocommerce/checkout',
  );
  log(
    `confirmed block Cart (page ${cartPageId}) and block Checkout (page ${checkoutPageId}) ` +
      'carry the expected block markup.',
  );

  // ── 3b. Enable my-account registration ────────────────────────────────────
  //
  // Off by default. The register form's `.col2-set` split (login left,
  // register right, `templates/myaccount/form-login.php`) is the only
  // unauthenticated classic-rendered `.col2-set`/`.col-1`/`.col-2` surface
  // Woo ships (checkout's own `#customer_details` uses the same class
  // shape but the seeded store's checkout page renders the Checkout BLOCK,
  // not this classic markup) — storefront.spec.mjs needs it on to reach
  // that layout.
  log('enabling my-account registration (for the .col2-set login/register layout) …');
  wp('option update woocommerce_enable_myaccount_registration yes');

  // ── 3c. Seed a customer account for login-gated assertions ───────────────
  seedCustomer();
  log(`seeded customer account: ${CUSTOMER_USERNAME}`);

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

  const galleryImageCount = seedGalleryImages(simpleId, 5);
  log(
    `attached ${galleryImageCount} placeholder images (1 featured + 4 gallery) to wtb-product-simple`,
  );

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

  // ── 5. Seed a NON-Woo page that renders a Woo product loop ───────────────
  const shortcodePageId = seedShortcodePage();
  log(`[products] shortcode page ${SHORTCODE_PAGE_SLUG} → id ${shortcodePageId}`);

  // ── 6. Export the seeded product ids for specs/helpers.mjs ──────────────
  writeFixtures({
    products: {
      simple: Number(simpleId),
      sale: Number(saleId),
      oos: Number(oosId),
    },
  });
  log(`wrote fixture ids to ${FIXTURES_PATH}`);

  log('done.');
}
