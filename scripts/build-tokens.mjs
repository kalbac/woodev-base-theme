import { mkdirSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { buildThemeJson, buildTokensCss } from './lib/build-tokens-lib.mjs';
import { tokens } from '../src/tokens/tokens.mjs';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');

const themeJsonPath = resolve(root, 'woodev-base-theme/theme.json');
const tokensCssPath = resolve(root, 'src/css/tokens.generated.css');

mkdirSync(dirname(themeJsonPath), { recursive: true });
mkdirSync(dirname(tokensCssPath), { recursive: true });

writeFileSync(themeJsonPath, `${JSON.stringify(buildThemeJson(tokens), null, '\t')}\n`);
writeFileSync(tokensCssPath, buildTokensCss(tokens));

console.log('Generated theme.json and tokens.generated.css');
