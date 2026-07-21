import { mkdirSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { buildPrimaryPresetsPhp, buildThemeJson, buildTokensCss } from './lib/build-tokens-lib.mjs';
import { tokens } from '../src/tokens/tokens.mjs';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');

const themeJsonPath = resolve(root, 'woodev-base-theme/theme.json');
const tokensCssPath = resolve(root, 'src/css/tokens.generated.css');
const presetsPhpPath = resolve(root, 'woodev-base-theme/inc/generated/primary-presets.php');

mkdirSync(dirname(themeJsonPath), { recursive: true });
mkdirSync(dirname(tokensCssPath), { recursive: true });
mkdirSync(dirname(presetsPhpPath), { recursive: true });

writeFileSync(themeJsonPath, `${JSON.stringify(buildThemeJson(tokens), null, '\t')}\n`);
writeFileSync(tokensCssPath, buildTokensCss(tokens));
writeFileSync(presetsPhpPath, buildPrimaryPresetsPhp(tokens));

console.log('Generated theme.json, tokens.generated.css and primary-presets.php');
