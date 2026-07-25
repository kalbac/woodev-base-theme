import { mkdirSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { buildPalettesPhp, buildThemeJson, buildTokensCss } from './lib/build-tokens-lib.mjs';
import { tokens } from '../src/tokens/tokens.mjs';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');

const themeJsonPath = resolve(root, 'woodev-base-theme/theme.json');
const tokensCssPath = resolve(root, 'src/css/tokens.generated.css');
const palettesPhpPath = resolve(root, 'woodev-base-theme/inc/generated/palettes.php');

mkdirSync(dirname(themeJsonPath), { recursive: true });
mkdirSync(dirname(tokensCssPath), { recursive: true });
mkdirSync(dirname(palettesPhpPath), { recursive: true });

// Build ALL THREE artefacts before writing ANY of them.
//
// Each builder validates as it goes — buildTokensCss runs the WCAG gate over
// every palette in both schemes, buildPalettesPhp refuses anything that could
// break or inject into the generated PHP — and any of them can throw. Writing
// as we went would leave the tree in a state no source produces: a fresh
// theme.json and CSS beside a stale palettes.php, which then disagree about
// what the accent is. The build exits non-zero either way; this makes it exit
// non-zero having changed nothing.
const themeJson = `${JSON.stringify(buildThemeJson(tokens), null, '\t')}\n`;
const css = buildTokensCss(tokens);
const palettesPhp = buildPalettesPhp(tokens);

writeFileSync(themeJsonPath, themeJson);
writeFileSync(tokensCssPath, css);
writeFileSync(palettesPhpPath, palettesPhp);

console.log('Generated theme.json, tokens.generated.css and inc/generated/palettes.php');
