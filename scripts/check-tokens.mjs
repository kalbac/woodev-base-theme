// scripts/check-tokens.mjs
/**
 * Fail if a COMMITTED generated artefact differs from what the generator produces.
 *
 * Why this exists, concretely: in s15 theme.json was edited by hand, the integration
 * suite went green on the edit, and the next `npm run build` silently overwrote it —
 * because theme.json is generated, which the editor did not know. The suite kept
 * passing (it never builds) while the shipped file said something else. Nothing in the
 * gate could see the divergence.
 *
 * Only the artefacts that are actually committed are checked. `src/css/tokens.generated.css`
 * is gitignored and regenerated on every build, so it cannot drift in the repository.
 *
 * Read-only by construction: it never writes, so a red run leaves the tree untouched
 * and `npm run tokens` is always the fix.
 */
import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { buildPalettesPhp, buildThemeJson } from './lib/build-tokens-lib.mjs';
import { tokens } from '../src/tokens/tokens.mjs';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');

const artefacts = [
  {
    path: 'woodev-base-theme/theme.json',
    expected: `${JSON.stringify(buildThemeJson(tokens), null, '\t')}\n`,
  },
  {
    path: 'woodev-base-theme/inc/generated/palettes.php',
    expected: buildPalettesPhp(tokens),
  },
];

const stale = [];

for (const { path, expected } of artefacts) {
  // Compare with the file's line endings normalised: .gitattributes pins eol=lf, but a
  // checkout on a misconfigured machine can still present CRLF, and that is a line-ending
  // problem, not a drift one. Reporting it here would send the reader to the wrong fix.
  const actual = readFileSync(resolve(root, path), 'utf8').replace(/\r\n/g, '\n');

  if (actual !== expected.replace(/\r\n/g, '\n')) {
    stale.push(path);
  }
}

if (stale.length > 0) {
  console.error(
    `These generated files differ from what the generator produces:\n` +
      stale.map((path) => `  - ${path}`).join('\n') +
      `\n\nThey are generated from src/tokens/tokens.mjs — edit that, not the output.\n` +
      `Run \`npm run tokens\` and commit the result.`,
  );
  process.exit(1);
}

console.log(`Generated artefacts match their source (${artefacts.length} checked).`);
