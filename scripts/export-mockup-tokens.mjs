/**
 * Re-export docs/design/v2-mockup/tokens.css from the approved mockup HTML.
 *
 * The mockup artifact carries its tokens INLINE; the sibling tokens.css is a
 * convenience export that has already drifted once (s10 shipped an export with
 * eight accent-only [data-palette] packs while the artifact had moved on to
 * seven [data-preset] palettes that also set the neutral temperature — anyone
 * porting from the export would have implemented a design nobody approved).
 *
 * So: never hand-edit the export. Run this.
 *
 *   node scripts/export-mockup-tokens.mjs
 */
import { readFileSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const mockup = resolve(root, 'docs/design/v2-mockup/woodev-base-identity.html');
const output = resolve(root, 'docs/design/v2-mockup/tokens.css');

// The token section of the artifact's single <style> block: from its first
// `:root{` up to the section-2 banner comment. Matched on landmarks rather than
// line numbers so an edit above or below the section does not silently shift
// the slice.
const SECTION_START = /^:root\{$/m;
const SECTION_END = /^\/\* =+\n\s+2\. BASE \/ RESET$/m;

const HEADER = `/* ============================================================================
   Woodev Base — design tokens, EXPORTED from the approved mockup.

   THIS FILE IS AN EXPORT, NOT AN INPUT. The source of truth is the inline
   <style> block of woodev-base-identity.html (the token section). If the two
   ever disagree, the HTML wins — re-export rather than hand-edit.
   Regenerate: node scripts/export-mockup-tokens.mjs

   The theme's real token layer is generated from src/tokens/tokens.mjs; this
   file is the design-side reference that port is checked against.

   Selector note: the mockup switches schemes with [data-scheme="dark"]; the
   theme uses .dark on <html> (M1-05 + Basecoat's dark: variant). The port
   rewrites the selector, never the values.
   See docs/plans/2026-07-25-visual-identity.md.

   Fonts: Golos Text / IBM Plex Sans / IBM Plex Mono — all SIL OFL 1.1, all
   shipping full Cyrillic, all self-hosted (ADR-007).
   ========================================================================== */

`;

const html = readFileSync(mockup, 'utf8');

const start = SECTION_START.exec(html);

if (null === start) {
  throw new Error(`No ':root{' token block found in ${mockup}`);
}

const end = SECTION_END.exec(html);

if (null === end) {
  throw new Error(`No '2. BASE / RESET' banner found in ${mockup} — cannot bound the slice`);
}

if (end.index <= start.index) {
  throw new Error('The BASE/RESET banner precedes the token block — the artifact changed shape');
}

const tokens = html.slice(start.index, end.index).trimEnd();

// Cheap shape assertions. Each one has already been wrong in a shipped export.
const expect = (condition, message) => {
  if (!condition) {
    throw new Error(`Refusing to write a suspicious export: ${message}`);
  }
};

expect(/--n-h:/.test(tokens), 'no --n-h: the neutral temperature is the spine of this design');
expect(
  7 === (tokens.match(/\[data-preset="/g) ?? []).length,
  'expected exactly 7 [data-preset] palettes',
);
expect(!tokens.includes('[data-palette'), '[data-palette] is the stale s10 spelling');
expect(/\[data-scheme="dark"\]\{/.test(tokens), 'no dark scheme block');

writeFileSync(output, `${HEADER}${tokens}\n`);

console.log(`Exported ${tokens.split('\n').length} lines of tokens to ${output}`);
