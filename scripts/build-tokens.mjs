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

// buildTokensCss runs the WCAG gate over all seven palettes in both schemes and
// throws on a failure, so nothing below writes if a palette is inaccessible.
const css = buildTokensCss(tokens);

writeFileSync(themeJsonPath, `${JSON.stringify(buildThemeJson(tokens), null, '\t')}\n`);
writeFileSync(tokensCssPath, css);
writeFileSync(palettesPhpPath, buildPalettesPhp(tokens));

console.log('Generated theme.json, tokens.generated.css and inc/generated/palettes.php');
