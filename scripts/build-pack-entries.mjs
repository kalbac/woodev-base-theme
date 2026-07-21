import { mkdirSync, rmSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { PACKS, packEntryCss } from './lib/packs-lib.mjs';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const outDir = resolve(root, 'src/css/packs');

// Wipe and recreate so a pack removed from PACKS never leaves a stale entry that
// Vite would still try to build.
rmSync(outDir, { recursive: true, force: true });
mkdirSync(outDir, { recursive: true });

for (const pack of PACKS) {
  writeFileSync(resolve(outDir, `${pack}.css`), packEntryCss(pack));
}

console.log(`Generated ${PACKS.length} style-pack entries in src/css/packs/`);
