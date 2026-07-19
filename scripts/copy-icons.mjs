// scripts/copy-icons.mjs
/**
 * Copies the icons the theme actually uses out of lucide-static.
 * Spec §9: only the icons used ship in the markup — no icon font, no full set.
 * Run: npm run icons
 */
import { copyFile, mkdir, readdir, rm } from 'node:fs/promises';
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

await rm(DEST, { recursive: true, force: true });
await mkdir(DEST, { recursive: true });

for (const name of ICONS) {
  await copyFile(join(SRC, `${name}.svg`), join(DEST, `${name}.svg`));
}

const written = (await readdir(DEST)).filter((f) => f.endsWith('.svg'));
if (written.length !== ICONS.length) {
  throw new Error(`Expected ${ICONS.length} icons, wrote ${written.length}`);
}
console.log(`Copied ${written.length} icons to ${DEST}`);
