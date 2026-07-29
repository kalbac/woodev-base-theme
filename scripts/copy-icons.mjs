// scripts/copy-icons.mjs
/**
 * Copies the icons the theme actually uses out of lucide-static.
 * Spec §9: only the icons used ship in the markup — no icon font, no full set.
 * Run: npm run icons
 */
import { copyFile, mkdir, readdir, unlink } from 'node:fs/promises';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');
const SRC = join(ROOT, 'node_modules', 'lucide-static', 'icons');
const DEST = join(ROOT, 'woodev-base-theme', 'assets', 'static', 'icons');

// Every icon the theme references, and where. Keep the comments accurate —
// an icon with no listed consumer should be deleted, not left to rot.
const ICONS = [
  'sun', // scheme switcher, light state (M1-05)
  'moon', // scheme switcher, dark state (M1-05)
  'menu', // mobile nav toggle (M1-02)
  'x', // mobile nav close (M1-02)
  'chevron-down', // dropdown nav (M1-02)
  'chevron-left', // pagination, previous (M1-02)
  'chevron-right', // pagination, next (M1-02)
  'search', // search form (M1-02)
  'check', // front-page value band / hero trust badges: fallback icon (s17 #18)
  'truck', // front-page value band / hero trust badges: shipping perk (s17 #18)
  'shield-check', // front-page value band / hero trust badges: warranty perk (s17 #18)
  'refresh-cw', // front-page value band / hero trust badges: returns perk (s17 #18)
  'leaf', // front-page value band / hero trust badges: eco/sustainability perk (s17 #18)
  'package', // front-page value band / hero trust badges: packaging perk (s17 #18)
  'credit-card', // front-page value band / hero trust badges: payment perk (s17 #18)
  'headphones', // front-page value band / hero trust badges: support perk (s17 #18)
  'sliders-horizontal', // catalogue filter rail head, and its mobile disclosure (s18 #41)
  'minus', // single-product quantity stepper, decrement (s18 #41)
  'plus', // single-product quantity stepper, increment (s18 #41)
  'shopping-bag', // empty-cart state (s19 #42, plan row C12)
  'lock', // secure-payment note on the cart and checkout panels (s19 #42, C10/K9)
  'user', // checkout login notice, and My Account nav → Account details (s19 #42)
  'info', // store notice, info variant (s19 #42, plan row K4)
  'circle-check', // store notice, success variant (s19 #42, plan row K4)
  'triangle-alert', // store notice, error variant (s19 #42, plan row K4)
  'house', // My Account nav → Dashboard (s19 #42, plan row M2)
  'file-text', // My Account nav → Orders (s19 #42, plan row M2)
  'download', // My Account nav → Downloads, and the downloads table button (s19 #42)
  'map-pin', // My Account nav → Addresses (s19 #42, plan row M2)
  'log-out', // My Account nav → Log out (s19 #42, plan row M2)
];

await mkdir(DEST, { recursive: true });

// Clear the previous SVGs so a name dropped from ICONS stops shipping — but
// only the SVGs. Removing the whole directory would take README.md with it,
// which is exactly what the README tells you to trigger ("run npm run icons
// after changing the ICONS list").
for (const stale of (await readdir(DEST)).filter((f) => f.endsWith('.svg'))) {
  await unlink(join(DEST, stale));
}

for (const name of ICONS) {
  await copyFile(join(SRC, `${name}.svg`), join(DEST, `${name}.svg`));
}

const written = (await readdir(DEST)).filter((f) => f.endsWith('.svg'));
if (written.length !== ICONS.length) {
  throw new Error(`Expected ${ICONS.length} icons, wrote ${written.length}`);
}
console.log(`Copied ${written.length} icons to ${DEST}`);
