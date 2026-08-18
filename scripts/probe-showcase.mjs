/**
 * Capture the release showcase's user-visible routes after seed-showcase.php.
 *
 * Kept separate from e2e assertions: this is a visual-review tool that records
 * all pages needed for a release screenshot decision, including recovery states.
 */
import { chromium } from '@playwright/test';
import { mkdirSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const output = resolve(root, 'surface-probe-out', 'showcase');
const baseUrl = process.env.SHOWCASE_URL ?? 'http://localhost:8891';
const surfaces = [
  ['home', '/', 200],
  ['catalogue', '/shop/', 200],
  ['product', '/product/stoneware-pour-over-set/', 200],
  ['journal', '/journal/', 200],
  ['search', '/?s=tray', 200],
  ['not-found', '/nothing-here/', 404],
  ['sidebar', '/showcase-quieter-kitchen/', 200],
];

mkdirSync(output, { recursive: true });

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1200, height: 900 }, deviceScaleFactor: 1 });
const consoleErrors = [];
page.on('pageerror', (error) => consoleErrors.push(error.message));
page.on('console', (message) => {
  if (message.type() === 'error') {
    consoleErrors.push(message.text());
  }
});

const report = [];
for (const [name, path, expectedStatus] of surfaces) {
  consoleErrors.length = 0;
  const response = await page.goto(`${baseUrl}${path}`, { waitUntil: 'networkidle' });
  await page.evaluate(() => document.fonts.ready);

  const metrics = await page.evaluate(() => ({
    title: document.title,
    h1: document.querySelector('h1')?.textContent?.trim() ?? '',
    width: document.documentElement.scrollWidth,
    viewport: window.innerWidth,
  }));

  if (response == null || response.status() !== expectedStatus || metrics.width > metrics.viewport) {
    throw new Error(`${name}: status=${response?.status() ?? 'none'}, width=${metrics.width}/${metrics.viewport}`);
  }

  await page.screenshot({ path: resolve(output, `${name}.png`), fullPage: true });
  report.push({ name, url: page.url(), status: response.status(), ...metrics, consoleErrors: [...consoleErrors] });
}

await browser.close();
writeFileSync(resolve(output, 'report.json'), `${JSON.stringify(report, null, 2)}\n`);
console.log(JSON.stringify(report, null, 2));
