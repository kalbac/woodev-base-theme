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
