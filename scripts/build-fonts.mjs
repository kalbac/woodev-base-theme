// scripts/build-fonts.mjs
/**
 * Self-hosted webfonts (ADR-007). Derives the theme's font subset from the
 * `fonts.googleapis.com` CSS response vendored at
 * docs/design/v2-mockup/assets/fonts/fonts.css (read-only — the approved
 * design artifact, never written to):
 *
 *   1. Parses every `@font-face` block in the source CSS.
 *   2. Keeps only the latin / latin-ext / cyrillic / cyrillic-ext subsets
 *      (drops greek, greek-ext, vietnamese — ADR-007).
 *   3. Keeps only the font-weights the design actually uses (see
 *      USED_WEIGHTS below — audited by hand against the mockup's inline
 *      `<style>` block, docs/design/v2-mockup/woodev-base-identity.html
 *      lines 18-1090, plus every `font-weight` in src/css/**; methodology
 *      and evidence are in the T3 session report, not reproduced here).
 *   4. Copies the matching woff2 files into woodev-base-theme/assets/fonts/
 *      with readable, deterministic names.
 *   5. Writes src/css/fonts.css: one `@font-face` per (family, subset),
 *      `font-display: swap`, the source's `unicode-range` copied verbatim.
 *   6. Writes the two OFL 1.1 license files.
 *
 * Golos Text and IBM Plex Sans are shipped by Google as VARIABLE fonts: every
 * weight block for a given (family, subset) in the source CSS points at the
 * SAME physical woff2 (confirmed by comparing the `src: url(...)` strings —
 * not an assumption). Copying that file once per weight would quadruple the
 * shipped bytes for no reason, so this script groups blocks by their actual
 * source url and emits ONE @font-face with a `font-weight: <min> <max>`
 * range per physical file — one file serves every weight in that range, and
 * the browser interpolates. IBM Plex Mono is NOT variable here — the vendored
 * response has a distinct static file per weight — so Mono keeps one
 * @font-face per weight.
 *
 * Path choice (verified empirically against a real `vite build` of this
 * project, not assumed — see the T3 report for the reproduction steps):
 * src/css/fonts.css references fonts as `url('../../fonts/<file>.woff2')`.
 * This resolves against `src/css/`, i.e. `src/../fonts/` = `fonts/` at the
 * repo root, which does not exist — so Vite's CSS asset pipeline cannot
 * statically resolve it, prints a non-fatal
 * "didn't resolve at build time, it will remain unchanged to be resolved at
 * runtime" warning, and leaves the literal string untouched in the compiled
 * output. That is exactly what we want: with Vite's default
 * `build.assetsDir` ('assets'), the compiled CSS lands at
 * woodev-base-theme/assets/dist/assets/*.css (confirmed by running the
 * project's own `vite build` once app.css imported this file — two levels
 * down from woodev-base-theme/assets/, not one), and the fonts live at the
 * sibling woodev-base-theme/assets/fonts/. `../../fonts/...` is what
 * actually walks assets/dist/assets/ -> assets/dist/ -> assets/ -> fonts/,
 * so the browser resolves it correctly against the *compiled* CSS's own
 * URL, regardless of the theme's install path (domain root, subdirectory,
 * custom WP_CONTENT_URL — Assets.php never has to know fonts exist). A
 * `/`-rooted absolute path would dodge the Vite warning but hardcodes a
 * domain-root-relative URL, which breaks under a subdirectory WP install and
 * is a wp.org Theme Review portability flag — rejected for that reason.
 *
 * Idempotent: woodev-base-theme/assets/fonts/ is fully cleared and
 * regenerated every run, so re-running produces byte-identical output.
 *
 * Run: npm run fonts
 */
import { copyFile, mkdir, readdir, readFile, rm, stat, writeFile } from 'node:fs/promises';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { format, resolveConfig } from 'prettier';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');

const SRC_CSS_PATH = join(ROOT, 'docs/design/v2-mockup/assets/fonts/fonts.css');
const SRC_FONTS_DIR = join(ROOT, 'docs/design/v2-mockup/assets/fonts');
const DEST_FONTS_DIR = join(ROOT, 'woodev-base-theme/assets/fonts');
const DEST_CSS_PATH = join(ROOT, 'src/css/fonts.css');

/** ADR-007: only these four subsets ship. Greek and Vietnamese are dropped. */
const KEEP_SUBSETS = ['cyrillic-ext', 'cyrillic', 'latin-ext', 'latin'];

/**
 * Weights the design actually uses, per family — see the file header and the
 * T3 report for the audit. Golos Text 400 and IBM Plex Mono 700 are
 * deliberately absent:
 *  - Golos Text is never used at 400 anywhere in the audited CSS.
 *  - IBM Plex Mono 700 IS requested once (`.totals .row.grand .amount`,
 *    woodev-base-identity.html) but the vendored Google response never
 *    fetched a 700 static instance for Mono (it ships 400/500/600 only,
 *    confirmed against the source file list). No file exists to keep;
 *    fabricating one is out of scope for a script that must stay
 *    deterministic and offline (ADR-007). Browsers fall back to the nearest
 *    registered weight (600) for that one rule. Re-subsetting from upstream
 *    OFL releases (ADR-007, M3) is the real fix.
 */
const USED_WEIGHTS = {
  'Golos Text': [500, 600, 700, 800],
  'IBM Plex Sans': [400, 500, 600, 700],
  'IBM Plex Mono': [400, 500, 600],
};

const FAMILY_SLUGS = {
  'Golos Text': 'golos-text',
  'IBM Plex Sans': 'ibm-plex-sans',
  'IBM Plex Mono': 'ibm-plex-mono',
};

/** Emit order in the generated CSS: display, body, mono. */
const FAMILY_ORDER = ['Golos Text', 'IBM Plex Sans', 'IBM Plex Mono'];

const OFL_PREAMBLE_AND_BODY = `This Font Software is licensed under the SIL Open Font License, Version 1.1.
This license is copied below, and is also available with a FAQ at:
https://openfontlicense.org

-----------------------------------------------------------
SIL OPEN FONT LICENSE Version 1.1 - 26 February 2007
-----------------------------------------------------------

PREAMBLE
The goals of the Open Font License (OFL) are to stimulate worldwide
development of collaborative font projects, to support the font creation
efforts of academic and linguistic communities, and to provide a free and
open framework in which fonts may be shared and improved in partnership
with others.

The OFL allows the licensed fonts to be used, studied, modified and
redistributed freely as long as they are not sold by themselves. The
fonts, including any derivative works, can be bundled, embedded,
redistributed and/or sold with any software provided that any reserved
names are not used by derivative works. The fonts and derivatives,
however, cannot be released under any other type of license. The
requirement for fonts to remain under this license does not apply
to any document created using the fonts or their derivatives.

DEFINITIONS
"Font Software" refers to the set of files released by the Copyright
Holder(s) under this license and clearly marked as such. This may
include source files, build scripts and documentation.

"Reserved Font Name" refers to any names specified as such after the
copyright statement(s).

"Original Version" refers to the collection of Font Software components as
distributed by the Copyright Holder(s).

"Modified Version" refers to any derivative made by adding to, deleting,
or substituting -- in part or in whole -- any of the components of the
Original Version, by changing formats or by porting the Font Software to a
new environment.

"Author" refers to any designer, engineer, programmer, technical
writer or other person who contributed to the Font Software.

PERMISSION & CONDITIONS
Permission is hereby granted, free of charge, to any person obtaining
a copy of the Font Software, to use, study, copy, merge, embed, modify,
redistribute, and sell modified and unmodified copies of the Font
Software, subject to the following conditions:

1) Neither the Font Software nor any of its individual components,
in Original or Modified Versions, may be sold by itself.

2) Original or Modified Versions of the Font Software may be bundled,
redistributed and/or sold with any software, provided that each copy
contains the above copyright notice and this license. These can be
included either as stand-alone text files, human-readable headers or
in the appropriate machine-readable metadata fields within text or
binary files as long as those fields can be easily viewed by the user.

3) No Modified Version of the Font Software may use the Reserved Font
Name(s) unless explicit written permission is granted by the corresponding
Copyright Holder. This restriction only applies to the primary font name as
presented to the users.

4) The name(s) of the Copyright Holder(s) or the Author(s) of the Font
Software shall not be used to promote, endorse or advertise any
Modified Version, except to acknowledge the contribution(s) of the
Copyright Holder(s) and the Author(s) or with their explicit written
permission.

5) The Font Software, modified or unmodified, in part or in whole,
must be distributed entirely under this license, and must not be
distributed under any other license. The requirement for fonts to
remain under this license does not apply to any document created
using the Font Software.

TERMINATION
This license becomes null and void if any of the above conditions are
not met.

DISCLAIMER
THE FONT SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO ANY WARRANTIES OF
MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT
OF COPYRIGHT, PATENT, TRADEMARK, OR OTHER RIGHT. IN NO EVENT SHALL THE
COPYRIGHT HOLDER BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
INCLUDING ANY GENERAL, SPECIAL, INDIRECT, INCIDENTAL, OR CONSEQUENTIAL
DAMAGES, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
FROM, OUT OF THE USE OR INABILITY TO USE THE FONT SOFTWARE OR FROM
OTHER DEALINGS IN THE FONT SOFTWARE.
`;

/**
 * Reproduced from memory of the canonical, unchanging OFL 1.1 body (not
 * fetched from an upstream URL this run) — see the T3 report.
 *
 * @param {string} copyrightLine
 * @param {string} reservedFontName
 */
function oflLicenseText(copyrightLine, reservedFontName) {
  return `Copyright ${copyrightLine}, with Reserved Font Name "${reservedFontName}".\n\n${OFL_PREAMBLE_AND_BODY}`;
}

/**
 * Parses every `@font-face { ... }` block in the vendored Google Fonts CSS,
 * reading the subset from the preceding `/* subset *\/` comment Google emits.
 *
 * @param {string} cssText
 * @returns {Array<{subset: string, family: string, style: string, weight: number, stretch: string | null, url: string, unicodeRange: string}>}
 */
function parseSourceFontFaces(cssText) {
  const blockRe = /\/\*\s*([a-z0-9-]+)\s*\*\/\s*@font-face\s*\{([\s\S]*?)\}/gi;
  const faces = [];
  let match;

  while ((match = blockRe.exec(cssText)) !== null) {
    const [, subset, body] = match;
    const family = /font-family:\s*'([^']+)'/.exec(body)?.[1];
    const style = /font-style:\s*([a-z]+)/i.exec(body)?.[1];
    const weightRaw = /font-weight:\s*(\d+)/.exec(body)?.[1];
    const stretch = /font-stretch:\s*([^;]+);/.exec(body)?.[1]?.trim() ?? null;
    const url = /src:\s*url\("([^"]+)"\)/.exec(body)?.[1];
    const unicodeRange = /unicode-range:\s*([^;]+);/.exec(body)?.[1]?.trim();

    if (!family || !style || !weightRaw || !url || !unicodeRange) {
      throw new Error(
        `build-fonts: failed to parse an @font-face block (subset "${subset}") in ${SRC_CSS_PATH} — source drifted from the expected Google Fonts CSS shape.\n${body}`,
      );
    }

    faces.push({ subset, family, style, weight: Number(weightRaw), stretch, url, unicodeRange });
  }

  if (faces.length === 0) {
    throw new Error(
      `build-fonts: parsed zero @font-face blocks from ${SRC_CSS_PATH} — parser or source drifted.`,
    );
  }

  return faces;
}

/**
 * Groups the filtered faces by (family, subset), then by their source url —
 * the url grouping is what detects "this is one variable-font file serving
 * several nominal weights" versus "these are genuinely distinct static
 * files".
 *
 * @param {ReturnType<typeof parseSourceFontFaces>} faces
 */
function groupIntoOutputFaces(faces) {
  /** @type {Map<string, typeof faces>} */
  const byFamilySubset = new Map();
  for (const face of faces) {
    const key = `${face.family}::${face.subset}`;
    if (!byFamilySubset.has(key)) byFamilySubset.set(key, []);
    byFamilySubset.get(key).push(face);
  }

  const outputFaces = [];

  for (const group of byFamilySubset.values()) {
    /** @type {Map<string, typeof faces>} */
    const byUrl = new Map();
    for (const face of group) {
      if (!byUrl.has(face.url)) byUrl.set(face.url, []);
      byUrl.get(face.url).push(face);
    }

    for (const facesForUrl of byUrl.values()) {
      const weights = facesForUrl.map((f) => f.weight).sort((a, b) => a - b);
      const unicodeRanges = new Set(facesForUrl.map((f) => f.unicodeRange));
      if (unicodeRanges.size !== 1) {
        throw new Error(
          `build-fonts: ${facesForUrl[0].family} ${facesForUrl[0].subset} has inconsistent unicode-range values sharing one file — refusing to guess which is right.`,
        );
      }

      const { family, subset, style, stretch, unicodeRange } = facesForUrl[0];
      outputFaces.push({
        family,
        subset,
        style,
        stretch,
        unicodeRange,
        weightMin: weights[0],
        weightMax: weights[weights.length - 1],
        sourceUrl: facesForUrl[0].url,
      });
    }
  }

  return outputFaces;
}

/** @param {ReturnType<typeof groupIntoOutputFaces>[number]} face */
function destFileName(face) {
  const slug = FAMILY_SLUGS[face.family];
  const weightPart =
    face.weightMin === face.weightMax ? `${face.weightMin}` : `${face.weightMin}-${face.weightMax}`;
  const italicPart = face.style === 'italic' ? '-italic' : '';
  return `${slug}-${weightPart}${italicPart}-${face.subset}.woff2`;
}

/** @param {ReturnType<typeof groupIntoOutputFaces>[number]} face */
function toFontFaceRule(face) {
  const weightDecl =
    face.weightMin === face.weightMax ? `${face.weightMin}` : `${face.weightMin} ${face.weightMax}`;
  const lines = [
    '@font-face {',
    `  font-family: '${face.family}';`,
    `  font-style: ${face.style};`,
    `  font-weight: ${weightDecl};`,
  ];
  if (face.stretch) {
    lines.push(`  font-stretch: ${face.stretch};`);
  }
  lines.push(
    '  font-display: swap;',
    `  src: url('../../fonts/${destFileName(face)}') format('woff2');`,
    `  unicode-range: ${face.unicodeRange};`,
    '}',
  );
  return lines.join('\n');
}

async function main() {
  const sourceCss = await readFile(SRC_CSS_PATH, 'utf8');
  const parsed = parseSourceFontFaces(sourceCss);

  const filtered = parsed.filter(
    (face) =>
      KEEP_SUBSETS.includes(face.subset) && (USED_WEIGHTS[face.family] ?? []).includes(face.weight),
  );

  if (filtered.length === 0) {
    throw new Error(
      'build-fonts: zero faces survived subset/weight filtering — check KEEP_SUBSETS and USED_WEIGHTS.',
    );
  }

  // Fail loudly if an audited weight has no source file for one of the four
  // kept subsets, instead of silently shipping a family with a hole in it.
  for (const [family, weights] of Object.entries(USED_WEIGHTS)) {
    for (const weight of weights) {
      for (const subset of KEEP_SUBSETS) {
        const has = filtered.some(
          (f) => f.family === family && f.weight === weight && f.subset === subset,
        );
        if (!has) {
          throw new Error(
            `build-fonts: ${family} weight ${weight} has no source file for subset "${subset}" in ${SRC_CSS_PATH} — USED_WEIGHTS claims a weight the vendored asset does not provide.`,
          );
        }
      }
    }
  }

  const outputFaces = groupIntoOutputFaces(filtered);

  // Fail loudly on a missing source file rather than copying nothing and
  // leaving fonts.css pointing at a hole.
  for (const face of outputFaces) {
    const sourcePath = join(SRC_FONTS_DIR, face.sourceUrl.replace(/^\.\//, ''));
    try {
      await stat(sourcePath);
    } catch {
      throw new Error(`build-fonts: referenced source file is missing: ${sourcePath}`);
    }
  }

  // Idempotency: start from a clean slate every run so a weight or subset
  // dropped from USED_WEIGHTS/KEEP_SUBSETS can never leave a stale file behind.
  await rm(DEST_FONTS_DIR, { recursive: true, force: true });
  await mkdir(DEST_FONTS_DIR, { recursive: true });

  for (const face of outputFaces) {
    const sourcePath = join(SRC_FONTS_DIR, face.sourceUrl.replace(/^\.\//, ''));
    await copyFile(sourcePath, join(DEST_FONTS_DIR, destFileName(face)));
  }

  await writeFile(
    join(DEST_FONTS_DIR, 'LICENSE-golos-text.txt'),
    oflLicenseText('2020 The Golos Text Project Authors', 'Golos Text'),
  );
  await writeFile(
    join(DEST_FONTS_DIR, 'LICENSE-ibm-plex.txt'),
    oflLicenseText('2017 IBM Corp.', 'IBM Plex'),
  );

  const orderedFaces = [...outputFaces].sort((a, b) => {
    const familyDiff = FAMILY_ORDER.indexOf(a.family) - FAMILY_ORDER.indexOf(b.family);
    if (familyDiff !== 0) return familyDiff;
    const weightDiff = a.weightMin - b.weightMin;
    if (weightDiff !== 0) return weightDiff;
    return KEEP_SUBSETS.indexOf(a.subset) - KEEP_SUBSETS.indexOf(b.subset);
  });

  const cssHeader = `/* AUTO-GENERATED by scripts/build-fonts.mjs — do not edit. Run \`npm run fonts\`.
 *
 * Self-hosted Golos Text + IBM Plex Sans + IBM Plex Mono (ADR-007). Derived
 * from docs/design/v2-mockup/assets/fonts/fonts.css (vendored Google Fonts
 * response), latin + latin-ext + cyrillic + cyrillic-ext only, weights the
 * design actually uses only. Golos Text and IBM Plex Sans are variable fonts
 * here — one file per subset serves a whole font-weight range. IBM Plex Mono
 * ships one static file per weight. See the script header for the full
 * audit trail and the Vite path-resolution reasoning.
 *
 * No external requests: every src: is theme-relative, no fonts.g* host.
 */

`;

  const rawCss = cssHeader + orderedFaces.map(toFontFaceRule).join('\n\n') + '\n';
  // Run through the project's own Prettier config (long unicode-range lists
  // exceed printWidth and need wrapping) so `npm run format` stays meaningful
  // for this generated file too, same as tokens.generated.css.
  const prettierOptions = (await resolveConfig(DEST_CSS_PATH)) ?? {};
  const css = await format(rawCss, { ...prettierOptions, filepath: DEST_CSS_PATH });
  await writeFile(DEST_CSS_PATH, css);

  const writtenFonts = (await readdir(DEST_FONTS_DIR)).filter((f) => f.endsWith('.woff2'));
  if (writtenFonts.length !== outputFaces.length) {
    throw new Error(
      `build-fonts: expected ${outputFaces.length} woff2 files, found ${writtenFonts.length} in ${DEST_FONTS_DIR}.`,
    );
  }

  let totalBytes = 0;
  const byFamily = new Map();
  for (const face of outputFaces) {
    const { size } = await stat(join(DEST_FONTS_DIR, destFileName(face)));
    totalBytes += size;
    byFamily.set(face.family, (byFamily.get(face.family) ?? 0) + size);
  }

  console.log(
    `Generated src/css/fonts.css and ${writtenFonts.length} font files in ${DEST_FONTS_DIR}:`,
  );
  for (const family of FAMILY_ORDER) {
    const bytes = byFamily.get(family) ?? 0;
    console.log(
      `  ${family}: weights ${USED_WEIGHTS[family].join(', ')} — ${(bytes / 1024).toFixed(1)} KB`,
    );
  }
  console.log(`  Total: ${(totalBytes / 1024).toFixed(1)} KB`);
  if (totalBytes > 120 * 1024) {
    console.warn(
      `build-fonts: total ${(totalBytes / 1024).toFixed(1)} KB exceeds the ADR-007 ~120 KB target. Not dropping weights to hide it — see the T3 report for the breakdown and root cause.`,
    );
  }
}

await main();
