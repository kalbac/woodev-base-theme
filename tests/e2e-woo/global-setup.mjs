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

/**
 * Product category slugs seeded for the catalogue e2e specs (filter rail,
 * subcategory chips, active-filter chips, grid, pagination). `kettles`,
 * `tableware` and `storage` are children of `kitchen`; `textile` is a
 * SIBLING top-level category with no parent — the subcategory chip row
 * needs at least one category the "WTB Kitchen" chip set does not include,
 * to prove the chips scope to the current parent rather than just listing
 * every category on the site.
 */
export const CATEGORY_SLUGS = Object.freeze({
  kitchen: 'wtb-kitchen',
  kettles: 'wtb-kettles',
  tableware: 'wtb-tableware',
  storage: 'wtb-storage',
  textile: 'wtb-textile',
});

/**
 * Global attribute slug passed to `wc product_attribute create`. WooCommerce
 * stores the resulting taxonomy as `pa_${ATTRIBUTE_SLUG_COLOUR}` — see
 * reseedAttribute()'s docblock for where this was confirmed.
 */
export const ATTRIBUTE_SLUG_COLOUR = 'wtb-colour';

/** Colour attribute term slugs, created in this order. */
export const COLOUR_TERM_SLUGS = Object.freeze([
  'graphite',
  'forest',
  'terracotta',
  'sand',
  'blue',
]);

/** Published posts used by the front-page Journal section. */
const JOURNAL_POSTS = Object.freeze([
  {
    slug: 'wtb-journal-kitchen-rhythm',
    title: 'Kitchen rhythm',
    content: 'A seeded journal entry for the front-page editorial surface.',
  },
  {
    slug: 'wtb-journal-quiet-light',
    title: 'Quiet light',
    content: 'A second seeded journal entry for the front-page editorial surface.',
  },
  {
    slug: 'wtb-journal-things-kept',
    title: 'Things kept',
    content: 'A third seeded journal entry for the front-page editorial surface.',
  },
]);

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
 * Delete every product category with this slug, then create a fresh one —
 * same delete-then-create discipline as reseedProduct(), for the same
 * reason: nothing here has been proven to expose a `--field=id` shortcut on
 * this Woo, so this reads the existing match back via `--format=json` too
 * rather than assume the schema behaves like a different WC-CLI namespace.
 *
 * @param {string} slug
 * @param {string} name
 * @param {number} [parentId] Parent category id; 0 (the default) is top-level.
 * @returns {number} the new category id.
 */
function reseedCategory(slug, name, parentId = 0) {
  const listJson = wpTry(`wc product_cat list --user=admin --slug=${slug} --format=json`);
  if (listJson) {
    for (const { id } of JSON.parse(listJson)) {
      wp(`wc product_cat delete ${id} --user=admin --force=true`);
    }
  }
  return Number(
    wp(
      `wc product_cat create --user=admin --slug=${slug} --name="${name}" --parent=${parentId} --porcelain`,
    ),
  );
}

/**
 * Delete the global attribute with this slug, then create it fresh together
 * with all of its terms. Deleting the attribute leaves nothing on the term
 * side to clean up separately — a freshly created attribute id starts with
 * zero terms regardless of what the previous attribute (a different id) had.
 *
 * WHY MATCH ON `pa_${slug}`, NOT THE RAW SLUG: WooCommerce prefixes the slug
 * it stores internally with `pa_` — confirmed against this container by
 * creating a throwaway attribute with `--slug=wtb-colour-test` and reading
 * it back via `wc product_attribute list --format=json`, which reported
 * `"slug":"pa_wtb-colour-test"`. Matching on the raw slug would never find
 * the attribute created by a previous run, and every run would pile up a new
 * duplicate "WTB Colour" attribute instead of reseeding cleanly — the exact
 * failure mode reseedProduct()'s own docblock describes for a different
 * field-name mismatch.
 *
 * @param {string} name e.g. "WTB Colour".
 * @param {string} slug e.g. "wtb-colour" (unprefixed).
 * @param {readonly string[]} termSlugs Term slugs to create; each is
 *   Title-Cased for the term's own --name (e.g. "graphite" → "Graphite").
 * @returns {{ attributeId: number, taxonomy: string, termIds: Record<string, number> }}
 */
function reseedAttribute(name, slug, termSlugs) {
  const taxonomy = `pa_${slug}`;
  const listJson = wpTry('wc product_attribute list --user=admin --format=json');
  if (listJson) {
    for (const attr of JSON.parse(listJson)) {
      if (attr.slug === taxonomy) {
        wp(`wc product_attribute delete ${attr.id} --user=admin --force=true`);
      }
    }
  }
  const attributeId = Number(
    wp(
      `wc product_attribute create --user=admin --name="${name}" --slug=${slug} --type=select ` +
        '--has_archives=true --porcelain',
    ),
  );

  const termIds = {};
  for (const termSlug of termSlugs) {
    const termName = termSlug.charAt(0).toUpperCase() + termSlug.slice(1);
    termIds[termSlug] = Number(
      wp(
        `wc product_attribute_term create ${attributeId} --user=admin --name="${termName}" ` +
          `--slug=${termSlug} --porcelain`,
      ),
    );
  }
  return { attributeId, taxonomy, termIds };
}

/**
 * Replace a product's product_cat terms with the given category ids.
 *
 * `wp post term set` is core wp-cli, not wc-cli — but product_cat is a plain
 * taxonomy registered on the `product` post type, so no Woo-specific command
 * is needed. `--by=id` takes bare numeric ids with no delimiter the shell
 * could mangle, so this carries none of the quoting risk the file's other
 * helpers work around.
 *
 * @param {number} productId
 * @param {number[]} categoryIds
 */
function assignCategories(productId, categoryIds) {
  wp(`post term set ${productId} product_cat ${categoryIds.join(' ')} --by=id`);
}

/**
 * Attach the global "WTB Colour" attribute to a product with one term value,
 * marked visible on the product page and NOT used for variations — this
 * store seeds no variable products, but a real product's `_product_attributes`
 * entry always carries an explicit variation flag rather than leaving one to
 * an implicit default, so the layered-nav filter rail has a normal attribute
 * to count against.
 *
 * WHY `wp eval` INSTEAD OF `wc product update --attributes=<json>`: the
 * --attributes value the WC REST schema expects is a JSON array of objects
 * (id, options, visible, variation) — both double quotes and spaces, exactly
 * the combination seedShortcodePage()'s and seedGalleryImages()'s docblocks
 * already document as unsafe once wp-env re-splits the command string a
 * second time inside the container. A short single-line PHP snippet through
 * `wp eval` — the same technique seedGalleryImages() uses — sidesteps the
 * problem entirely: it builds one WC_Product_Attribute object in-process and
 * calls WC_Product::save(), which is also what performs the
 * wp_set_object_terms() call that links the pa_wtb-colour term to the
 * product. No separate `wp post term set` call is needed for the attribute
 * itself, unlike assignCategories() above.
 *
 * @param {number} productId
 * @param {number} attributeId Numeric id from reseedAttribute().
 * @param {string} taxonomy    e.g. "pa_wtb-colour", from reseedAttribute().
 * @param {number} termId      Numeric term id from reseedAttribute().
 */
function assignColourAttribute(productId, attributeId, taxonomy, termId) {
  const php = [
    `$product = wc_get_product(${productId});`,
    '$attr = new WC_Product_Attribute();',
    `$attr->set_id(${attributeId});`,
    `$attr->set_name('${taxonomy}');`,
    `$attr->set_options([${termId}]);`,
    '$attr->set_visible(true);',
    '$attr->set_variation(false);',
    '$product->set_attributes([$attr]);',
    '$product->save();',
  ].join(' ');
  wp(`eval "${php}"`);
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
 * Slugs of the pages carrying the CLASSIC cart / checkout shortcodes (#42, F1).
 *
 * WHY SEPARATE PAGES rather than rewriting the ones `install_pages` creates:
 * WooCommerce 10.9.4's `install_pages` builds the Cart and Checkout pages out
 * of the `woocommerce/cart` and `woocommerce/checkout` BLOCKS, and
 * `assertBlockPageExists()` above exists precisely to fail the run if those
 * pages ever stop carrying that block markup — `tests/e2e-woo/blocks.spec.mjs`
 * (ADR-009 / B6) asserts against them. Overwriting `post_content` with a
 * shortcode would satisfy the classic specs by breaking the block ones.
 *
 * These two pages are therefore ADDITIONAL, with their own slugs, and the
 * `woocommerce_cart_page_id` / `woocommerce_checkout_page_id` options keep
 * pointing at the block pages. Consequence worth knowing before writing a
 * spec against them: because the options are unchanged, `is_cart()` and
 * `is_checkout()` are FALSE on these pages. The shortcodes still render — the
 * `[woocommerce_cart]` / `[woocommerce_checkout]` handlers call
 * `wc_get_template()` directly rather than gating on those conditionals — but
 * any theme code that branches on `is_cart()` / `is_checkout()` will not fire
 * here, which is a property of the FIXTURE, not of a real store. Theme code
 * that needs to run on the classic branch must therefore key off something the
 * template itself provides (a hook inside `cart.php`/`form-checkout.php`), not
 * off the page conditional — that is a design constraint this fixture makes
 * visible, and it is the shape the plan's C1/K-row hooks already use.
 */
export const CLASSIC_CART_PAGE_SLUG = 'wtb-cart-classic';
/** @see CLASSIC_CART_PAGE_SLUG */
export const CLASSIC_CHECKOUT_PAGE_SLUG = 'wtb-checkout-classic';

/** Slug of the downloadable product seeded for `/my-account/downloads/`. */
export const DOWNLOADABLE_PRODUCT_SLUG = 'wtb-product-downloadable';

/** Code of the percent coupon seeded so an order carries a discount row. */
export const ORDER_COUPON_CODE = 'wtbe2e10';

/**
 * Seed one ordinary page whose entire content is a single shortcode.
 *
 * Kept separate from `seedShortcodePage()` rather than generalising it: that
 * function's docblock documents a `[products]`-specific reason for existing
 * (a NON-Woo page that still renders our `content-product.php` override), and
 * folding two different intents into one helper would lose it.
 *
 * The shortcode string carries no attributes and no spaces on purpose — see
 * `seedShortcodePage()` for why an inner-quoted attribute does not survive
 * `npx wp-env run cli`'s second round of argument splitting.
 *
 * @param {string} slug      Page slug (also the idempotency key).
 * @param {string} title     Page title.
 * @param {string} shortcode e.g. "[woocommerce_cart]".
 * @returns {number} the new page id.
 */
function seedSingleShortcodePage(slug, title, shortcode) {
  const existing = wpTry(`post list --post_type=page --name=${slug} --field=ID`);
  for (const id of existing.split(/\r?\n/).filter(Boolean)) {
    wp(`post delete ${id} --force`);
  }
  return Number(
    wp(
      `post create --post_type=page --post_status=publish --post_title="${title}" ` +
        `--post_name=${slug} --post_content="${shortcode}" --porcelain`,
    ),
  );
}

/**
 * Reseed a downloadable, virtual product with one real file in uploads.
 *
 * `/my-account/downloads/` lists rows from `wc_get_customer_available_downloads()`,
 * which reads the `woocommerce_downloadable_product_permissions` table — and
 * those rows are written by `wc_downloadable_product_permissions()`, which runs
 * on order COMPLETION. So a downloadable product alone puts nothing on that
 * page; `seedCustomerOrders()` below has to complete an order containing it.
 *
 * The file is generated inside the container (nothing on the host is mounted
 * where WordPress can read it) and written into the uploads dir so the download
 * URL resolves. `--downloads=<json>` is not used for the same reason
 * `assignColourAttribute()` avoids `--attributes=<json>`: the value needs both
 * quotes and spaces, which do not survive `wp-env run cli`.
 *
 * WHY THE APPROVED DIRECTORY IS ADDED EXPLICITLY. This store runs WooCommerce's
 * Approved Download Directories feature in `enabled` mode, and
 * `WC_Product::set_downloads()` throws `WC_Data_Exception` for a file outside the
 * approved list. It does try to self-heal — `WC_Product_Download::approved_directory_checks()`
 * calls `add_approved_directory( parent_url, $is_site_administrator )` — but
 * `$is_site_administrator` is `current_user_can( 'manage_options' )`, and a
 * `wp eval` runs with NO current user, so the directory is added **disabled**,
 * `is_valid_path()` stays false, and the save fatals with "does not exist on the
 * server, or is not located within an approved directory" — a message that sends
 * you looking for a missing file that is in fact present (measured: the file
 * existed on disk while the save failed). Adding the uploads URL as ENABLED
 * before the save removes the dependency on the acting user, and on the mode
 * being off. Passing `--user=admin` to the eval would also work, but it would
 * make a fixture silently depend on a capability check three classes deep.
 *
 * @returns {number} the product id.
 */
function seedDownloadableProduct() {
  const id = reseedProduct(
    DOWNLOADABLE_PRODUCT_SLUG,
    [
      '--type=simple',
      '--name="WTB Care Guide PDF"',
      '--regular_price=4.90',
      '--virtual=true',
      '--downloadable=true',
      '--description="A downloadable product seeded so /my-account/downloads/ has a real row."',
      '--short_description="Downloadable product for e2e."',
    ].join(' '),
  );

  const php = [
    `$product = wc_get_product(${id});`,
    '$dir = wp_upload_dir();',
    "$file = $dir['path'] . '/wtb-care-guide.txt';",
    "$url = $dir['url'] . '/wtb-care-guide.txt';",
    "file_put_contents($file, 'WTB e2e downloadable fixture.');",
    // Approve the uploads directory (ENABLED) before the save — see the
    // docblock above for why the auto-add inside set_downloads() is not enough
    // under `wp eval`. Wrapped: add_approved_directory() throws on a malformed
    // URL, and the is_valid_path() assertion below is what actually decides
    // whether to continue, so a throw here must not mask it.
    "$dirs = wc_get_container()->get('Automattic\\\\WooCommerce\\\\Internal\\\\ProductDownloads\\\\ApprovedDirectories\\\\Register');",
    // add_approved_directory() returns the EXISTING row's id without touching
    // its `enabled` flag when the URL is already listed, so on its own it is a
    // no-op against a directory a previous run added DISABLED — which is
    // exactly what set_downloads()'s own auto-add leaves behind under
    // `wp eval` (no current user → not enabled). is_valid_path() only matches
    // rows with `enabled = 1`, so the add has to be followed by an explicit
    // update. Passing the id add_approved_directory() returned makes this
    // correct in both branches: a fresh insert and a stale disabled row.
    "try { $id = $dirs->add_approved_directory($dir['url'], true); $dirs->update_approved_directory($id, $dir['url'], true); } catch (Exception $e) {}",
    "if (!$dirs->is_valid_path($url)) { throw new Exception('uploads dir is still not an approved download directory: ' . $dir['url']); }",
    '$download = new WC_Product_Download();',
    "$download->set_name('WTB Care Guide');",
    "$download->set_id('wtb-care-guide');",
    '$download->set_file($url);',
    '$product->set_downloads([$download]);',
    '$product->set_download_limit(3);',
    '$product->save();',
    // Read the download back off a FRESHLY loaded product rather than echoing
    // the id: set_downloads() collects per-file errors and, for a file it
    // cannot validate, silently DISABLES the download instead of throwing when
    // the product was not yet read. A save that "worked" while shipping zero
    // usable downloads is the exact shape of fixture that makes a downloads
    // spec pass against an empty table.
    'echo count(wc_get_product($product->get_id())->get_downloads());',
  ].join(' ');

  const downloads = Number(wp(`eval "${php}"`));
  if (1 !== downloads) {
    throw new Error(
      `[e2e-woo:setup] expected the downloadable product to carry exactly 1 download, got ${downloads}`,
    );
  }

  return Number(id);
}

/**
 * Enable two payment gateways.
 *
 * WHY: measured on the seeded store before this existed, the classic checkout's
 * payment section rendered nothing but "Sorry, it seems that there are no
 * available payment methods" and a dead Place-order button. A fresh WooCommerce
 * install enables no gateway at all, so `ul.wc_payment_methods` — the element
 * plan row K6 styles as the mockup's payment cards, and the one that carries
 * the `:has(input:checked)` accent — did not exist, and the whole checkout was
 * not a checkout. Same class of gap as the missing shipping zone below.
 *
 * TWO gateways, not one: with a single available method WooCommerce still
 * renders the `<li>` but there is nothing to choose between, so the selected-
 * state styling and the radio behaviour are untestable. `cod` (Cash on
 * delivery) and `bacs` (Direct bank transfer) are both core, both offline, and
 * neither needs credentials or a network call.
 *
 * `wp wc payment_gateway update` is the documented CLI route; the settings are
 * stored per-gateway as `woocommerce_{id}_settings`, which is what the
 * assertion below reads back rather than trusting the write.
 */
function seedPaymentGateways() {
  for (const id of ['cod', 'bacs']) {
    wp(`wc payment_gateway update ${id} --user=admin --enabled=true`);
  }

  const enabled = wp(
    'eval "$ids = []; foreach (WC()->payment_gateways()->get_available_payment_gateways() as $g) { $ids[] = $g->id; } echo implode(\',\', $ids);"',
  );

  for (const id of ['cod', 'bacs']) {
    if (!enabled.split(',').includes(id)) {
      throw new Error(
        `[e2e-woo:setup] expected payment gateway "${id}" to be available, got: ${enabled || '(none)'}`,
      );
    }
  }

  return enabled.split(',');
}

/**
 * Give the store one shipping zone with two flat-rate methods.
 *
 * WHY THIS IS A FIXTURE AND NOT AN AFTERTHOUGHT: measured on the seeded store
 * before this existed, the classic checkout's review table had a `tfoot` of
 * exactly two rows — `cart-subtotal` and `order-total`. No `tr.shipping`, no
 * `ul#shipping_method`, because a store with no shipping zone offers no methods
 * at all. That makes two things unverifiable rather than merely untested: the
 * mockup's shipping-method cards (plan row K7 styles `ul#shipping_method li`,
 * which did not exist) and the "Delivery" line the mockup draws in both totals
 * panels (C8/R3). TWO methods, not one — Woo renders a single available method
 * as plain text with no radio at all, so one method would style nothing.
 *
 * Idempotent: every zone this created before is deleted first, matched by name.
 * Zone 0 ("Locations not covered by your other zones") cannot be deleted and is
 * not touched — the methods go on a real zone so the `zone_id` lookup below has
 * something stable to return.
 *
 * @returns {number} the zone id.
 */
function seedShippingZone() {
  const php = [
    '$zones = WC_Shipping_Zones::get_zones();',
    "foreach ($zones as $z) { if ('WTB E2E Zone' === $z['zone_name']) { $old = new WC_Shipping_Zone($z['zone_id']); $old->delete(true); } }",
    '$zone = new WC_Shipping_Zone();',
    "$zone->set_zone_name('WTB E2E Zone');",
    "$zone->add_location('US', 'country');",
    '$zone->save();',
    "$flat = $zone->add_shipping_method('flat_rate');",
    "$local = $zone->add_shipping_method('local_pickup');",
    '$zone->save();',
    // add_shipping_method() creates the instance with default settings, which
    // for flat_rate means a cost of '' — i.e. free, and indistinguishable from
    // local pickup in the rendered list. Set a real cost so the mockup's
    // "method name + price" row has a price in it.
    '$m = WC_Shipping_Zones::get_shipping_method($flat);',
    "if ($m) { $m->init_instance_settings(); $s = $m->instance_settings; $s['cost'] = '3.50'; $s['title'] = 'WTB Courier'; update_option($m->get_instance_option_key(), $s); }",
    '$p = WC_Shipping_Zones::get_shipping_method($local);',
    "if ($p) { $p->init_instance_settings(); $ps = $p->instance_settings; $ps['title'] = 'WTB Pickup Point'; update_option($p->get_instance_option_key(), $ps); }",
    'echo $zone->get_id();',
  ].join(' ');

  const zoneId = Number(wp(`eval "${php}"`));

  // Assert the methods actually landed: add_shipping_method() returns an
  // instance id without validating that the method is registered, so a typo in
  // a method id would leave a zone with zero methods and a checkout that still
  // shows no shipping — the exact silent state this function exists to end.
  const methods = Number(
    wp(`eval "$z = new WC_Shipping_Zone(${zoneId}); echo count($z->get_shipping_methods());"`),
  );
  if (2 !== methods) {
    throw new Error(
      `[e2e-woo:setup] expected the shipping zone to carry 2 methods, got ${methods}`,
    );
  }

  return zoneId;
}

/**
 * Reseed a fixed-percent coupon, so an order (and a classic cart) can carry a
 * discount row — the mockup draws one in `--sale` red on the cart, the checkout
 * panel and the receipt (`woodev-base-identity.html` lines 2460, 2615, 2869),
 * and nothing in the store produced one before.
 */
function seedOrderCoupon() {
  const php = [
    `$existing = get_posts(['post_type' => 'shop_coupon', 'post_status' => 'any', 'title' => '${ORDER_COUPON_CODE}', 'posts_per_page' => -1, 'fields' => 'ids']);`,
    'foreach ($existing as $cid) { wp_delete_post($cid, true); }',
    '$coupon = new WC_Coupon();',
    `$coupon->set_code('${ORDER_COUPON_CODE}');`,
    "$coupon->set_discount_type('percent');",
    '$coupon->set_amount(10);',
    '$coupon->save();',
    'echo $coupon->get_id();',
  ].join(' ');
  return Number(wp(`eval "${php}"`));
}

/**
 * Reseed two orders for the seeded customer: one `processing`, one `completed`.
 *
 * WHY TWO, IN THOSE STATUSES: the mockup's account section draws three status
 * tones (`processing` / `completed` / `pending`, lines 2714-2728) and the plan's
 * M6 renders them as pills; two real orders in two different statuses are what
 * make that mapping measurable rather than asserted. The `completed` one is also
 * what grants the download permission M9/`/my-account/downloads/` needs, and it
 * is the one the order-received screen (D-block, R1-R5) is checked against —
 * `is_order_received_page()` does not care about the status, but a completed
 * order is the realistic case for a receipt that shows a paid total.
 *
 * Both carry a shipping line and the percent coupon, so the totals `tfoot` has
 * a Доставка row and a Скидка row rather than just a subtotal and a total.
 *
 * IDEMPOTENCY: every order belonging to the seeded customer is deleted first.
 * That is deliberately broader than a marker-meta query — this store's Woo runs
 * HPOS, where a `meta_key` lookup goes through a different code path than the
 * post-meta one `seedGalleryImages()` uses, and the customer is ours anyway, so
 * "delete every order this customer has" is both simpler and impossible to miss
 * a stale row with. (`seedGalleryImages()`'s docblock records what a
 * marker query that silently matches nothing costs.)
 *
 * @param {number[]} productIds Products to put on the processing order.
 * @param {number}   downloadableId Product for the completed order.
 * @returns {{ processing: {id: number, key: string}, completed: {id: number, key: string} }}
 */
function seedCustomerOrders(productIds, downloadableId) {
  const address = [
    "$address = ['first_name' => 'Anna', 'last_name' => 'Sokolova', 'company' => '', " +
      "'email' => 'wtb-e2e-customer@example.com', 'phone' => '+7 921 000-00-00', " +
      "'address_1' => '12 Mill Race Road', 'address_2' => 'Apt. 34', 'city' => 'Brooklyn', " +
      "'state' => 'NY', 'postcode' => '11201', 'country' => 'US'];",
  ].join('');

  const php = [
    `$user = get_user_by('login', '${CUSTOMER_USERNAME}');`,
    "if (!$user) { throw new Exception('seeded customer is missing'); }",
    "$old = wc_get_orders(['limit' => -1, 'customer_id' => $user->ID, 'return' => 'ids', 'status' => 'any']);",
    'foreach ($old as $oid) { $o = wc_get_order($oid); if ($o) { $o->delete(true); } }',
    address,
    '$made = [];',
    `$plan = [['processing', [${productIds.join(', ')}]], ['completed', [${downloadableId}]]];`,
    'foreach ($plan as $entry) {',
    '  list($status, $items) = $entry;',
    "  $order = wc_create_order(['customer_id' => $user->ID]);",
    '  foreach ($items as $i => $pid) { $order->add_product(wc_get_product($pid), $i + 1); }',
    "  $order->set_address($address, 'billing');",
    "  $order->set_address($address, 'shipping');",
    '  $shipping = new WC_Order_Item_Shipping();',
    "  $shipping->set_method_title('WTB Courier');",
    "  $shipping->set_method_id('flat_rate');",
    '  $shipping->set_total(3.50);',
    '  $order->add_item($shipping);',
    `  $order->apply_coupon('${ORDER_COUPON_CODE}');`,
    '  $order->calculate_totals();',
    "  $order->set_payment_method('bacs');",
    "  $order->set_payment_method_title('Direct bank transfer');",
    "  $order->update_status($status, 'Seeded by the e2e-woo global setup.');",
    '  $order->save();',
    "  $made[] = $order->get_id() . ':' . $order->get_order_key();",
    '}',
    "echo implode('|', $made);",
  ].join(' ');

  const raw = wp(`eval "${php}"`);
  const parts = raw.trim().split('|');
  if (parts.length !== 2) {
    throw new Error(
      `[e2e-woo:setup] expected two "id:key" pairs from the order seeder, got ${JSON.stringify(raw)}`,
    );
  }
  const parse = (pair) => {
    const [id, key] = pair.split(':');
    if (!/^\d+$/.test(id) || !key) {
      throw new Error(`[e2e-woo:setup] unparseable order pair ${JSON.stringify(pair)}`);
    }
    return { id: Number(id), key };
  };
  return { processing: parse(parts[0]), completed: parse(parts[1]) };
}

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

/**
 * Seed the three ordinary posts the front-page Journal section reads. The
 * posts are test data, not theme copy: they make the Woo e2e environment able
 * to exercise a real post query while the Woo-free integration suite keeps its
 * own no-post/no-Woo contract.
 */
export function seedJournalPosts() {
  for (const { slug, title, content } of JOURNAL_POSTS) {
    const existing = wpTry(`post list --post_type=post --name=${slug} --field=ID`);
    for (const id of existing.split(/\r?\n/).filter(Boolean)) {
      wp(`post delete ${id} --force`);
    }

    wp(
      `post create --post_type=post --post_status=publish --post_title="${title}" ` +
        `--post_name=${slug} --post_content="${content}" --porcelain`,
    );
  }
}

/**
 * Widget area the theme renders as the catalogue filter rail
 * (woodev-base-theme/inc/Woo/FilterRail.php — `FilterRail::SIDEBAR_ID`).
 */
export const SHOP_SIDEBAR_ID = 'sidebar-shop';

/**
 * The WooCommerce filter widgets seeded into that area, in render order.
 *
 * All four are WooCommerce's OWN widgets — the theme builds no filtering of
 * its own, it only supplies the rail and the styling (#41 rows A5–A9). Two of
 * them deliberately render nothing until the request warrants it, and that is
 * the point of seeding them: `woocommerce_layered_nav` needs the colour
 * attribute this file also seeds, and `woocommerce_layered_nav_filters` (the
 * active-filter chips) only prints once a filter is in the URL — so a spec can
 * assert the chips appear on `/shop/?filter_wtb-colour=forest` and not on
 * `/shop/`.
 */
const SHOP_FILTER_WIDGETS = [
  'woocommerce_product_categories',
  'woocommerce_layered_nav',
  'woocommerce_price_filter',
  'woocommerce_layered_nav_filters',
];

/**
 * Put WooCommerce's filter widgets into the theme's shop widget area.
 *
 * Idempotent by delete-then-add, same discipline as `reseedProduct()`: the Woo
 * container persists across restarts, and `wp widget add` appends rather than
 * replacing, so without the delete every run would stack another four copies
 * of the rail onto the previous run's.
 *
 * Worth stating plainly, because it is the difference between a real
 * assertion and a vacuous one: with this area EMPTY, `FilterRail::is_active()`
 * is false, `Support::open_wrapper()` emits the plain full-width shell, and no
 * `.wtb-filter-rail` exists anywhere in the document. A rail spec written
 * against an unseeded store would therefore be asserting on `null` — passing
 * or failing for reasons unrelated to the rail.
 */
function seedShopFilterWidgets() {
  const listed = wpTry(`widget list ${SHOP_SIDEBAR_ID} --format=json`);

  if (listed) {
    for (const { id } of JSON.parse(listed)) {
      wp(`widget delete ${id}`);
    }
  }

  for (const widget of SHOP_FILTER_WIDGETS) {
    wp(`widget add ${widget} ${SHOP_SIDEBAR_ID}`);
  }

  const seeded = JSON.parse(wp(`widget list ${SHOP_SIDEBAR_ID} --format=json`));

  if (seeded.length !== SHOP_FILTER_WIDGETS.length) {
    throw new Error(
      `[e2e-woo:setup] expected ${SHOP_FILTER_WIDGETS.length} widgets in "${SHOP_SIDEBAR_ID}", ` +
        `got ${seeded.length} — the theme's widget area may not be registered, in which case ` +
        '`wp widget add` reports success and the widget lands nowhere.',
    );
  }
}

/**
 * Everything #42 (cart / checkout / account / receipt) needs on top of the
 * catalogue fixtures: the classic shortcode pages, a downloadable product, a
 * percent coupon, and two orders for the seeded customer.
 *
 * Exported and self-contained so it can be run on its own against an
 * already-seeded container — the full `globalSetup()` takes ~12 minutes
 * (~50 sequential `wp-env run cli` calls at 10-15s each), and re-paying that to
 * add four fixtures would be the whole reason a session stalls. `globalSetup()`
 * calls this as its step 5a-b; a standalone runner calls it directly and merges
 * the result into the existing `.fixtures.json`.
 *
 * @param {number[]} orderProductIds Products to put on the `processing` order.
 * @returns {object} the fixture fragment to merge into `.fixtures.json`.
 */
export function seedIssue42Fixtures(orderProductIds) {
  const log = (...a) => console.log('[e2e-woo:setup]', ...a);

  // Additional pages, NOT a rewrite of the block ones — see
  // CLASSIC_CART_PAGE_SLUG's docblock for why, and for the `is_cart()` /
  // `is_checkout()` consequence any spec written against them has to respect.
  const classicCart = seedSingleShortcodePage(
    CLASSIC_CART_PAGE_SLUG,
    'WTB Classic Cart',
    '[woocommerce_cart]',
  );
  const classicCheckout = seedSingleShortcodePage(
    CLASSIC_CHECKOUT_PAGE_SLUG,
    'WTB Classic Checkout',
    '[woocommerce_checkout]',
  );
  log(`classic shortcode pages: cart → ${classicCart}, checkout → ${classicCheckout}`);

  const downloadable = seedDownloadableProduct();
  log(`downloadable product ${DOWNLOADABLE_PRODUCT_SLUG} → id ${downloadable}`);

  const shippingZone = seedShippingZone();
  log(`shipping zone "WTB E2E Zone" → id ${shippingZone} (flat rate + local pickup)`);

  const gateways = seedPaymentGateways();
  log(`payment gateways available: ${gateways.join(', ')}`);

  const couponId = seedOrderCoupon();
  log(`percent coupon ${ORDER_COUPON_CODE} → id ${couponId}`);

  const orders = seedCustomerOrders(orderProductIds, downloadable);
  log(
    `orders for ${CUSTOMER_USERNAME}: processing → ${orders.processing.id}, ` +
      `completed → ${orders.completed.id}`,
  );

  // `orders.*.key` is the `order_key`. A logged-in owner reaching
  // /checkout/order-received/{id}/ does not need it; a spec that checks the
  // receipt WITHOUT logging in passes it as the `key` query arg.
  return {
    classicPages: { cart: classicCart, checkout: classicCheckout },
    downloadable,
    shippingZone,
    gateways,
    coupon: { code: ORDER_COUPON_CODE, id: couponId },
    orders,
  };
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

  // ── 3a-bis. Catalogue page size comes from the THEME, not from here ─────
  //
  // Nothing is set here on purpose, and the absence is load-bearing enough to
  // write down. `inc/Woo/Support.php` declares `product_grid` with 3 rows and
  // 3 columns, and `wc_reset_product_grid_settings()` writes those into
  // `woocommerce_catalog_rows`/`_columns` on `after_switch_theme` — which the
  // `wp theme activate` above fires on every run. Nine per page against ten
  // seeded products is what gives the catalogue a second page for the
  // pagination assertions (#41 row A13) while keeping all three fixed-slug
  // products on page one, where the existing storefront specs click through to
  // them from `/shop/`.
  //
  // Two measurements behind that, both made on this container rather than
  // reasoned about: `posts_per_page` is NOT the lever (setting it to 6 changed
  // nothing — `WC_Query::product_query()` reaches `apply_filters(
  // 'loop_shop_per_page', … )` with no `posts_per_page` on the query and
  // overrides the Reading setting), and forcing 2 rows x 3 columns here DID
  // paginate but pushed those three products onto page two, timing out six
  // specs that had been green for sessions.

  // ── 3a-ter. Populate the shop filter rail ────────────────────────────────
  seedShopFilterWidgets();

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

  seedJournalPosts();
  log(`seeded ${JOURNAL_POSTS.length} journal posts`);

  // ── 3d. Seed the category tree and the colour attribute ──────────────────
  //
  // Parent-then-children order matters here: each child category is created
  // WITH the freshly (re)created parent's id, so a run never leaves a child
  // pointed at a parent id from a previous run that reseedCategory() just
  // deleted.
  const kitchenCatId = reseedCategory(CATEGORY_SLUGS.kitchen, 'WTB Kitchen');
  const kettlesCatId = reseedCategory(CATEGORY_SLUGS.kettles, 'WTB Kettles', kitchenCatId);
  const tablewareCatId = reseedCategory(CATEGORY_SLUGS.tableware, 'WTB Tableware', kitchenCatId);
  const storageCatId = reseedCategory(CATEGORY_SLUGS.storage, 'WTB Storage', kitchenCatId);
  const textileCatId = reseedCategory(CATEGORY_SLUGS.textile, 'WTB Textile');
  log(
    `category tree: kitchen=${kitchenCatId} (kettles=${kettlesCatId}, ` +
      `tableware=${tablewareCatId}, storage=${storageCatId}), textile=${textileCatId}`,
  );

  const {
    attributeId: colourAttributeId,
    taxonomy: colourTaxonomy,
    termIds: colourTermIds,
  } = reseedAttribute('WTB Colour', ATTRIBUTE_SLUG_COLOUR, COLOUR_TERM_SLUGS);
  log(
    `colour attribute ${ATTRIBUTE_SLUG_COLOUR} → id ${colourAttributeId}, terms: ` +
      Object.entries(colourTermIds)
        .map(([termSlug, id]) => `${termSlug}=${id}`)
        .join(', '),
  );

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
  assignCategories(simpleId, [kettlesCatId]);
  assignColourAttribute(simpleId, colourAttributeId, colourTaxonomy, colourTermIds.graphite);

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
  assignCategories(saleId, [tablewareCatId]);
  assignColourAttribute(saleId, colourAttributeId, colourTaxonomy, colourTermIds.forest);

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
  assignCategories(oosId, [storageCatId]);
  // The out-of-stock product gets a colour term as well, and that is not
  // symmetry for its own sake: without it the layered-nav colour filter counts
  // nine of the ten products, and `sand` has no product at all — so a spec
  // asserting a count, or one filtering by colour and expecting the
  // out-of-stock CARD treatment, would be measuring a store that does not
  // match the invariant three comments in this file state. Raised by the s18
  // critic pass.
  assignColourAttribute(oosId, colourAttributeId, colourTaxonomy, colourTermIds.sand);

  // ── 4b. Seven more simple products spread across the four categories ────
  //
  // Prices span 6.90-49.90 — this store's currency uses two decimal places,
  // so these are plain numbers, not cents — to give the price-range side of
  // the filter rail something to bracket. Two of these are on sale and one
  // is out of stock, on top of wtb-product-sale and wtb-product-oos above:
  // three products on sale and two out of stock, store-wide. Every entry
  // carries a colour term so the layered-nav filter has something to count
  // for all ten products, not just the three fixed-slug ones.
  const CATALOGUE_PRODUCTS = [
    {
      slug: 'wtb-alder-kettle',
      name: 'WTB Alder Kettle 1.7 L',
      regularPrice: '34.90',
      categoryId: kettlesCatId,
      colourTermSlug: 'graphite',
    },
    {
      slug: 'wtb-birch-whistling-kettle',
      name: 'WTB Birch Whistling Kettle',
      regularPrice: '24.90',
      salePrice: '19.90',
      categoryId: kettlesCatId,
      colourTermSlug: 'blue',
    },
    {
      slug: 'wtb-cedar-teapot-set',
      name: 'WTB Cedar Teapot Set',
      regularPrice: '29.90',
      categoryId: tablewareCatId,
      colourTermSlug: 'terracotta',
    },
    {
      slug: 'wtb-linen-napkin-set',
      name: 'WTB Linen Napkin Set',
      regularPrice: '12.90',
      categoryId: tablewareCatId,
      colourTermSlug: 'sand',
      inStock: false,
    },
    {
      slug: 'wtb-oak-pantry-jar',
      name: 'WTB Oak Pantry Storage Jar',
      regularPrice: '6.90',
      categoryId: storageCatId,
      colourTermSlug: 'forest',
    },
    {
      slug: 'wtb-maple-utensil-crock',
      name: 'WTB Maple Utensil Crock',
      regularPrice: '44.90',
      salePrice: '34.90',
      categoryId: storageCatId,
      colourTermSlug: 'graphite',
    },
    {
      slug: 'wtb-woven-table-runner',
      name: 'WTB Woven Table Runner',
      regularPrice: '49.90',
      categoryId: textileCatId,
      colourTermSlug: 'terracotta',
    },
  ];

  const catalogueProductIds = {};
  for (const p of CATALOGUE_PRODUCTS) {
    const args = [
      '--type=simple',
      `--name="${p.name}"`,
      `--regular_price=${p.regularPrice}`,
      `--description="${p.name}, seeded by the Woo e2e global-setup for catalogue coverage."`,
      `--short_description="${p.name} for e2e."`,
    ];
    if (p.salePrice) {
      args.push(`--sale_price=${p.salePrice}`);
    }
    if (p.inStock === false) {
      args.push('--in_stock=false');
    }

    const id = reseedProduct(p.slug, args.join(' '));
    assignCategories(id, [p.categoryId]);
    assignColourAttribute(id, colourAttributeId, colourTaxonomy, colourTermIds[p.colourTermSlug]);
    catalogueProductIds[p.slug] = Number(id);
    log(`catalogue product ${p.slug} → id ${id}`);
  }

  // ── 5. Seed a NON-Woo page that renders a Woo product loop ───────────────
  const shortcodePageId = seedShortcodePage();
  log(`[products] shortcode page ${SHORTCODE_PAGE_SLUG} → id ${shortcodePageId}`);

  // ── 5a-b. The #42 fixtures: classic cart/checkout, downloads, orders ─────
  const issue42 = seedIssue42Fixtures([Number(simpleId), Number(saleId)]);

  // ── 6. Export the seeded ids for specs/helpers.mjs ───────────────────────
  //
  // (The pagination question is settled at step 3a-bis above: the theme's own
  // `product_grid` support is what sets the catalogue's page size, so nine of
  // these ten products land on page one and the pager is real. An earlier NOTE
  // here said the opposite — that no catalogue-specific lever existed and this
  // fixture could not manufacture a second page — which was true of
  // `posts_per_page` and wrong about `woocommerce_catalog_rows`/`_columns`.
  // Two contradictory explanations of the same behaviour in one file is worse
  // than either alone, so the stale one is gone rather than annotated.)
  writeFixtures({
    products: {
      simple: Number(simpleId),
      sale: Number(saleId),
      oos: Number(oosId),
      catalogue: catalogueProductIds,
    },
    categories: {
      kitchen: kitchenCatId,
      kettles: kettlesCatId,
      tableware: tablewareCatId,
      storage: storageCatId,
      textile: textileCatId,
    },
    attributes: {
      colour: {
        id: colourAttributeId,
        taxonomy: colourTaxonomy,
        terms: colourTermIds,
      },
    },
    productsShortcodePage: Number(shortcodePageId),
    ...issue42,
  });
  log(`wrote fixture ids to ${FIXTURES_PATH}`);

  log('done.');
}
