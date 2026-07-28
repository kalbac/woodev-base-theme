// tests/e2e-woo/fixtures.mjs
//
// Loader for the product ids tests/e2e-woo/global-setup.mjs seeds on every
// run, written to the gitignored tests/e2e-woo/.fixtures.json. See
// writeFixtures()'s docblock in global-setup.mjs for why this is a file
// rather than a process.env mutation: the short version is that this
// project's own verification workflow runs Playwright a second time with
// globalSetup skipped entirely (a temporary config reusing whatever the last
// full run seeded, to keep the probe loop cheap), and only a file on disk
// survives across that kind of separate invocation.
import { readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const FIXTURES_PATH = path.join(__dirname, '.fixtures.json');

/**
 * The product/category/attribute ids global-setup.mjs seeded for the current
 * run of the Woo e2e store. Throws a clear error rather than returning
 * undefined ids if global-setup.mjs has never run against this container.
 *
 * `products.catalogue` holds the seven extra catalogue products keyed by
 * slug (see CATALOGUE_PRODUCTS in global-setup.mjs for the full list);
 * `products.simple/sale/oos` are unchanged from before this fixture grew a
 * category tree and a colour attribute — specs depending only on those keys
 * do not need to change.
 *
 * @returns {{
 *   products: {
 *     simple: number,
 *     sale: number,
 *     oos: number,
 *     catalogue: Record<string, number>,
 *   },
 *   categories: {
 *     kitchen: number,
 *     kettles: number,
 *     tableware: number,
 *     storage: number,
 *     textile: number,
 *   },
 *   attributes: {
 *     colour: { id: number, taxonomy: string, terms: Record<string, number> },
 *   },
 * }}
 */
export function loadFixtures() {
  let raw;
  try {
    raw = readFileSync(FIXTURES_PATH, 'utf8');
  } catch {
    throw new Error(
      `[e2e-woo] ${FIXTURES_PATH} does not exist. tests/e2e-woo/global-setup.mjs writes it on ` +
        'every run — either the full suite (npm run e2e:woo) has never run against this ' +
        'container, or a temporary config that skips globalSetup is being used before any ' +
        'full run has seeded once.',
    );
  }

  return JSON.parse(raw);
}
