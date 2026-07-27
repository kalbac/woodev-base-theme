// scripts/make-screenshot.mjs
/**
 * Produce woodev-base-theme/screenshot.png — the image wp.org shows in the theme
 * directory and in Appearance → Themes.
 *
 * It is a real capture of the running theme at exactly 1200x900, not an illustration:
 * wp.org rejects screenshots that show something the theme does not render, and a
 * hand-made mock would drift from the identity the moment a token changed.
 *
 * Requires the base wp-env on :8888, seeded (`tests/e2e/global-setup.mjs`) and built
 * (`npm run build`) — a dev build would capture the fallback font stack, since dev mode
 * serves CSS as a JS module and the self-hosted fonts 404 there.
 *
 * Usage: node scripts/make-screenshot.mjs [url]
 */
import { chromium } from '@playwright/test';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const OUT = resolve(ROOT, 'woodev-base-theme/screenshot.png');
const URL = process.argv[2] ?? 'http://localhost:8888/';

// wp.org's required dimensions. Not a preference: the directory crops or rejects
// anything else, and 1200x900 is what it displays at 2x.
const WIDTH = 1200;
const HEIGHT = 900;

const browser = await chromium.launch();
const page = await browser.newPage({
  viewport: { width: WIDTH, height: HEIGHT },
  deviceScaleFactor: 1,
});

await page.goto(URL, { waitUntil: 'networkidle' });

// The self-hosted faces must be resolved before the capture, or the screenshot shows
// the fallback stack — `font-display: swap` makes that failure look like a design
// choice rather than a missing file.
await page.evaluate(() => document.fonts.ready);

await page.screenshot({ path: OUT, fullPage: false });
await browser.close();

console.log(`Wrote ${OUT} (${WIDTH}x${HEIGHT}) from ${URL}`);
