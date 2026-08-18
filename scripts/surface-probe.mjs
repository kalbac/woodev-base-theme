// scripts/surface-probe.mjs
/**
 * Capture rendered WordPress/WooCommerce surfaces with their computed geometry.
 *
 * This is a diagnostic tool, not an assertion suite. It exists because several
 * shipped layout defects were visible in screenshots while every markup and
 * track-list assertion stayed green. Keep the output outside git and inspect
 * the PNGs before changing CSS.
 *
 * Requires the isolated Woo environment on :8891 to be running and seeded:
 * `npm run wp:woo:start`, followed by `npm run e2e:woo` at least once.
 * Use `WTB_SURFACE_BASE_URL` and `WTB_SURFACE_OUT` to target another site or
 * output directory.
 */
import { chromium } from '@playwright/test';
import { mkdir, writeFile } from 'node:fs/promises';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

import { CUSTOMER_PASSWORD, CUSTOMER_USERNAME } from '../tests/e2e-woo/global-setup.mjs';
import { loadFixtures } from '../tests/e2e-woo/fixtures.mjs';

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const BASE_URL = process.env.WTB_SURFACE_BASE_URL ?? 'http://localhost:8891';
const OUTPUT_DIR = resolve(ROOT, process.env.WTB_SURFACE_OUT ?? 'surface-probe-out');
const VIEWPORT = { width: 1280, height: 900 };

const SURFACE_SELECTORS = {
  shell: 'main, .wtb-layout, .woocommerce',
  headings: 'h1, h2, h3',
  primaryLayout:
    '.wtb-cart-layout, .wtb-checkout-layout, .woocommerce-MyAccount-content, .woocommerce',
  productGrid: 'ul.products, .wtb-product-grid',
  notices: '.woocommerce-message, .woocommerce-info, .woocommerce-error',
  orderPanel: '.cart_totals, #order_review, .woocommerce-order-overview, .wtb-order-meta',
};

const STYLE_PROPERTIES = [
  'display',
  'position',
  'gridTemplateColumns',
  'flexDirection',
  'maxWidth',
  'width',
  'height',
  'backgroundColor',
  'color',
  'borderRadius',
  'overflowX',
];

function surfacePath(name, path) {
  return { name, path };
}

function readViewport() {
  const raw = process.env.WTB_SURFACE_VIEWPORT;
  if (!raw) return VIEWPORT;

  const match = /^(\d+)x(\d+)$/.exec(raw);
  if (!match) {
    throw new Error(`WTB_SURFACE_VIEWPORT must look like WIDTHxHEIGHT, got ${JSON.stringify(raw)}`);
  }

  return { width: Number(match[1]), height: Number(match[2]) };
}

async function login(page) {
  await page.goto('/my-account/', { waitUntil: 'domcontentloaded' });

  if ((await page.locator('.woocommerce-MyAccount-navigation').count()) > 0) return;

  await page.locator('#username').fill(CUSTOMER_USERNAME);
  await page.locator('#password').fill(CUSTOMER_PASSWORD);
  await page.locator('button[name="login"]').click();
  await page.locator('.woocommerce-MyAccount-navigation').waitFor({ state: 'visible' });
}

async function addSimpleProduct(page, productId) {
  await page.goto(`/?add-to-cart=${productId}`, { waitUntil: 'domcontentloaded' });
}

async function readSurface(page, surface) {
  const values = await page.evaluate(
    ({ selectors, styleProperties }) => {
      const inspect = (selector) => {
        const elements = [...document.querySelectorAll(selector)];
        const first = elements[0];
        if (!first) return { count: 0 };

        const style = getComputedStyle(first);
        const computed = Object.fromEntries(
          styleProperties.map((property) => [property, style[property]]),
        );
        return {
          count: elements.length,
          computed,
          rect: first.getBoundingClientRect().toJSON(),
        };
      };

      return {
        url: window.location.href,
        title: document.title,
        h1Count: document.querySelectorAll('h1').length,
        selectors: Object.fromEntries(
          Object.entries(selectors).map(([name, selector]) => [name, inspect(selector)]),
        ),
      };
    },
    { selectors: SURFACE_SELECTORS, styleProperties: STYLE_PROPERTIES },
  );

  await page.screenshot({ path: resolve(OUTPUT_DIR, `${surface.name}.png`), fullPage: true });
  return { ...surface, ...values };
}

async function visitAndCapture(page, surface, beforeCapture) {
  await page.goto(surface.path, { waitUntil: 'domcontentloaded' });
  if (beforeCapture) await beforeCapture();
  await page.evaluate(() => document.fonts.ready);
  const result = await readSurface(page, surface);
  console.log(`${surface.name}: ${result.url} (h1=${result.h1Count})`);
  return result;
}

const fixtures = loadFixtures();
const viewport = readViewport();
await mkdir(OUTPUT_DIR, { recursive: true });

const browser = await chromium.launch();
const context = await browser.newContext({ baseURL: BASE_URL, viewport });
const page = await context.newPage();

try {
  const surfaces = [
    surfacePath('front-page', '/'),
    surfacePath('shop', '/shop/'),
    surfacePath('product', ''),
    surfacePath('cart', '/cart/'),
    surfacePath('checkout', '/checkout/'),
    surfacePath('account', '/my-account/'),
    surfacePath('account-edit-address', '/my-account/edit-address/'),
    surfacePath('account-view-order', `/my-account/view-order/${fixtures.orders.processing.id}/`),
    surfacePath(
      'order-received',
      `/checkout/order-received/${fixtures.orders.processing.id}/?key=${encodeURIComponent(fixtures.orders.processing.key)}`,
    ),
  ];

  const results = [];
  results.push(await visitAndCapture(page, surfaces[0]));
  results.push(await visitAndCapture(page, surfaces[1]));
  surfaces[2].path = await page
    .locator('ul.products li.product a.woocommerce-LoopProduct-link')
    .first()
    .getAttribute('href');
  if (!surfaces[2].path) throw new Error('shop surface has no product link to probe');
  results.push(await visitAndCapture(page, surfaces[2]));

  // The cart/checkout/account/receipt surfaces need the same authenticated and
  // populated browser state that a visitor would use, not a guessed URL alone.
  await addSimpleProduct(page, fixtures.products.simple);
  results.push(
    await visitAndCapture(page, surfaces[3], async () => {
      await page
        .locator('.wc-block-components-quantity-selector__input, .quantity input.qty')
        .first()
        .waitFor({ state: 'visible' });
    }),
  );

  await addSimpleProduct(page, fixtures.products.simple);
  results.push(
    await visitAndCapture(page, surfaces[4], async () => {
      const pathname = new URL(page.url()).pathname;
      if (!pathname.startsWith('/checkout/')) {
        throw new Error(
          `checkout surface redirected to ${pathname}; the cart is empty or checkout is unavailable`,
        );
      }
      await page
        .locator('#email, .woocommerce-checkout #billing_first_name')
        .first()
        .waitFor({ state: 'visible' });
    }),
  );

  await login(page);
  results.push(await visitAndCapture(page, surfaces[5]));
  results.push(await visitAndCapture(page, surfaces[6]));
  results.push(await visitAndCapture(page, surfaces[7]));
  results.push(await visitAndCapture(page, surfaces[8]));

  await writeFile(
    resolve(OUTPUT_DIR, 'surface-data.json'),
    `${JSON.stringify({ baseURL: BASE_URL, viewport, results }, null, 2)}\n`,
  );
  console.log(`Wrote ${results.length} surfaces to ${OUTPUT_DIR}`);
} finally {
  await browser.close();
}
