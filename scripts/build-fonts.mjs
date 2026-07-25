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
 *   4. Validates and writes the matching woff2 files into
 *      woodev-base-theme/assets/fonts/ with readable, deterministic names.
 *   5. Writes src/css/fonts.css: one `@font-face` per (family, subset),
 *      `font-display: swap`, the source's `unicode-range` copied verbatim.
 *   6. Validates (shape AND identity, see PINNED below) and writes the two
 *      vendored OFL 1.1 license files verbatim.
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
 * LICENSE PROVENANCE (Codex critic finding, 25.07.2026 — see the s11 session
 * log): earlier revisions of this script embedded the OFL 1.1 body from
 * memory and synthesised the copyright/Reserved-Font-Name line per family.
 * Both were factually wrong (wrong year and a fabricated Reserved Font Name
 * for Golos Text; a Reserved Font Name of "IBM Plex" instead of upstream's
 * "Plex"; the wrong FAQ URL). A license file attached to someone else's font
 * must be their exact text. The fix vendors the real upstream files as
 * build INPUTS, fetched verbatim on 25.07.2026:
 *
 *   - docs/design/v2-mockup/assets/fonts/OFL-golos-text.txt
 *     <- https://raw.githubusercontent.com/google/fonts/main/ofl/golostext/OFL.txt
 *     4394 bytes. First line: `Copyright 2019 The Golos Text Project Authors
 *     (https://github.com/googlefonts/golos-text)` — no Reserved Font Name.
 *   - docs/design/v2-mockup/assets/fonts/OFL-ibm-plex.txt
 *     <- https://raw.githubusercontent.com/google/fonts/main/ofl/ibmplexsans/OFL.txt
 *     4456 bytes. First line: `Copyright © 2017 IBM Corp. with Reserved Font
 *     Name "Plex"`.
 *
 * They are vendored rather than fetched at build time because the
 * `fonts.googleapis.com` CSS/woff2 response the font payload itself is
 * derived from (docs/design/v2-mockup/assets/fonts/fonts.css) never included
 * license text, and ADR-007 requires this build to stay offline and
 * deterministic — no network access at `npm run fonts` time.
 *
 * `LICENSE-ibm-plex.txt` (from OFL-ibm-plex.txt above) is shipped for BOTH
 * IBM Plex Sans and IBM Plex Mono. This is correct, not a shortcut: upstream
 * `ofl/ibmplexsans/OFL.txt` and `ofl/ibmplexmono/OFL.txt` are byte-identical
 * (verified 25.07.2026 — same 4456-byte length, same sha256 checksum
 * `7e6b2818edbd8f6a01ae80641cc8f16a51080d08fb4e532be3a0b6f74adb07da`), so one
 * vendored file legitimately covers both families.
 *
 * PINNED, NOT JUST SHAPE-CHECKED (re-critic finding, 25.07.2026): the shape
 * check in assertVendoredLicenseText() below — "starts with Copyright",
 * "contains the OFL 1.1 heading" — is circular against the defect it exists
 * to catch. It only proves the vendored file LOOKS like an OFL 1.1 body; it
 * says nothing about whether it IS the real upstream text, because it never
 * compares against anything outside itself. A vendored file with its whole
 * middle body silently deleted, leaving only the first line and the OFL
 * heading intact, satisfies both shape checks and would still ship — an OFL
 * §2 violation the old test could not have caught either, since it compared
 * the shipped file against this SAME vendored input. VENDORED_LICENSE_PINS
 * below closes that hole by pinning each vendored input's SHA-256 and exact
 * byte length, computed by hand against the upstream files fetched on
 * 25.07.2026 (URLs and byte counts above) and re-verified against the files
 * on disk before this fix was written. Updating a vendored license file on
 * purpose (an upstream refresh) means updating its pin in the SAME commit —
 * an unpinned change is treated as corruption, never silently accepted.
 *
 * Idempotent: woodev-base-theme/assets/fonts/ is fully cleared and
 * regenerated every run, so re-running produces byte-identical output.
 *
 * Run: npm run fonts
 */
import { Buffer } from 'node:buffer';
import { createHash } from 'node:crypto';
import { mkdir, readFile, readdir, rename, rm, stat, writeFile } from 'node:fs/promises';
import { basename, dirname, join } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { format, resolveConfig } from 'prettier';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');

const SRC_CSS_PATH = join(ROOT, 'docs/design/v2-mockup/assets/fonts/fonts.css');
const SRC_FONTS_DIR = join(ROOT, 'docs/design/v2-mockup/assets/fonts');
const SRC_LICENSE_DIR = join(ROOT, 'docs/design/v2-mockup/assets/fonts');
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

/**
 * Vendored upstream OFL.txt bodies (see the file header for provenance,
 * dates and hashes). Each entry copies `src`, byte-for-byte, to `dest` inside
 * woodev-base-theme/assets/fonts/. `ibm-plex` covers both IBM Plex Sans and
 * IBM Plex Mono — upstream ships identical text for both (see header).
 */
const LICENSES = [
  { src: 'OFL-golos-text.txt', dest: 'LICENSE-golos-text.txt' },
  { src: 'OFL-ibm-plex.txt', dest: 'LICENSE-ibm-plex.txt' },
];

/** The four bytes every valid WOFF2 file starts with: ASCII "wOF2". */
export const WOFF2_SIGNATURE = Buffer.from([0x77, 0x4f, 0x46, 0x32]);

/**
 * Throws unless `bytes` begins with the WOFF2 magic number. This proves only
 * that the first FOUR BYTES look like a WOFF2 file — it does not, on its
 * own, prove anything about the rest of the file. A buffer that is literally
 * just the four bytes "wOF2" and nothing else passes this check (re-critic
 * finding, 25.07.2026: the previous docblock claimed this "guards against
 * shipping a truncated download", which is false — truncation past byte 4 is
 * invisible here). Real truncation/corruption detection is
 * assertWoff2Length() below, which checks the format's own self-reported
 * total length. Keep both: this one gives a fast, specific error message
 * ("not even trying to be a WOFF2") before the header-length check runs.
 *
 * @param {Buffer} bytes
 * @param {string} path
 */
export function assertWoff2Signature(bytes, path) {
  if (bytes.length < 4 || !bytes.subarray(0, 4).equals(WOFF2_SIGNATURE)) {
    throw new Error(
      `build-fonts: ${path} does not start with the WOFF2 signature (bytes 77 4F 46 32 / "wOF2") — refusing to ship a corrupt or non-font source file.`,
    );
  }
}

/**
 * Throws unless `bytes` carries a self-consistent WOFF2 header: the format
 * puts a `length` field — a uint32, big-endian, the total size of the file
 * in bytes — at byte offset 8 (after the 4-byte signature and 4-byte
 * `flavor` field). A genuine WOFF2 file's own header says how big the whole
 * file is, so comparing that declared length against `bytes.length` is the
 * format's own self-check, and it is what actually catches a download cut
 * off mid-transfer or a font substituted for a shorter/longer one — which
 * assertWoff2Signature() above cannot (see its docblock). This still does
 * NOT prove `bytes` is a valid, well-formed font, let alone the specific
 * face it claims to be (that would need a full WOFF2/sfnt parser, out of
 * scope for a build script) — only that the header's own length claim is
 * honest.
 *
 * @param {Buffer} bytes
 * @param {string} path
 */
export function assertWoff2Length(bytes, path) {
  if (bytes.length < 12) {
    throw new Error(
      `build-fonts: ${path} is only ${bytes.length} bytes — too short to contain a WOFF2 header (the length field lives at offset 8, needs 4 more bytes to read).`,
    );
  }
  const declaredLength = bytes.readUInt32BE(8);
  if (declaredLength !== bytes.length) {
    throw new Error(
      `build-fonts: ${path} declares a WOFF2 header length of ${declaredLength} bytes (offset 8 of the header) but the file on disk is ${bytes.length} bytes — truncated or corrupt WOFF2.`,
    );
  }
}

/**
 * Throws unless a write produced a destination the same size as the buffer
 * that was written. The signature/header checks alone would not catch a
 * write truncated partway through by, say, a disk filling up.
 *
 * @param {number} sourceBytes
 * @param {number} destBytes
 * @param {string} destPath
 */
export function assertCopyComplete(sourceBytes, destBytes, destPath) {
  if (sourceBytes !== destBytes) {
    throw new Error(
      `build-fonts: ${destPath} is ${destBytes} bytes after copying, source was ${sourceBytes} bytes — truncated copy.`,
    );
  }
}

/**
 * Throws unless `bytes` is plausibly an OFL 1.1 body: a real copyright line,
 * and the canonical license heading. This is a SHAPE check only — it cannot
 * prove the text is byte-identical to upstream, or even that nothing was
 * removed from its middle, because it never compares against anything
 * outside the buffer itself. A vendored file with its entire body deleted
 * except the first line and the OFL heading satisfies both conditions here
 * (re-critic finding, 25.07.2026 — this is exactly what made the old test
 * suite's guarantee circular: it also compared the shipped file only against
 * this same vendored input, so a truncated-but-shaped-right vendored file
 * would sail through everything). assertVendoredLicensePin() below is what
 * actually proves identity, by pinning the vendored input's SHA-256 and byte
 * length against values verified against upstream. Keep this shape check
 * too — a hash mismatch alone (assertVendoredLicensePin()) is diagnostically
 * useless ("hash is wrong"), whereas this one names what specifically looks
 * broken ("not a Copyright line" / "no OFL heading").
 *
 * @param {Buffer} bytes
 * @param {string} path
 */
export function assertVendoredLicenseText(bytes, path) {
  const text = bytes.toString('utf8');
  if (!text.startsWith('Copyright')) {
    throw new Error(
      `build-fonts: vendored license ${path} does not start with "Copyright" — re-vendor the exact upstream OFL.txt verbatim, do not retype or paraphrase it.`,
    );
  }
  if (!text.includes('SIL OPEN FONT LICENSE Version 1.1')) {
    throw new Error(
      `build-fonts: vendored license ${path} does not contain "SIL OPEN FONT LICENSE Version 1.1" — this does not look like an OFL 1.1 body.`,
    );
  }
}

/**
 * Pins for each vendored license input, keyed by basename: SHA-256 and exact
 * byte length. Verified by hand against the upstream `google/fonts` OFL.txt
 * files (URLs in the file header) on 25.07.2026, and re-confirmed against
 * the files actually on disk at fix time — not copied blind from a report.
 * This is what makes the license guarantee non-circular (see
 * assertVendoredLicenseText() above): the shape check alone cannot detect a
 * vendored file whose middle was silently removed while its first line and
 * OFL heading survive, but a pinned hash catches it immediately.
 *
 * Updating a vendored license file on purpose (an upstream refresh) means
 * updating its pin HERE in the same commit. An unpinned change to a vendored
 * `OFL-*.txt` file is treated as corruption by this script, on purpose —
 * never silently accepted.
 *
 * @type {Record<string, {bytes: number, sha256: string}>}
 */
const VENDORED_LICENSE_PINS = {
  'OFL-golos-text.txt': {
    bytes: 4394,
    sha256: 'ff532f9e8789f09a9fdffc3c0954eedfb0a48be77b2e2eb90f5f82e4f347f50c',
  },
  'OFL-ibm-plex.txt': {
    bytes: 4456,
    sha256: '7e6b2818edbd8f6a01ae80641cc8f16a51080d08fb4e532be3a0b6f74adb07da',
  },
};

/**
 * Throws unless `bytes` matches the pinned SHA-256 and byte length for the
 * vendored license file at `path` (looked up by basename in
 * VENDORED_LICENSE_PINS). See that constant's docblock for why this exists
 * and how to update it deliberately.
 *
 * @param {Buffer} bytes
 * @param {string} path
 */
export function assertVendoredLicensePin(bytes, path) {
  const pin = VENDORED_LICENSE_PINS[basename(path)];
  if (!pin) {
    throw new Error(
      `build-fonts: no entry in VENDORED_LICENSE_PINS for ${basename(path)} (${path}) — every vendored OFL-*.txt input must be pinned by SHA-256 and byte length, see that constant's docblock.`,
    );
  }
  if (bytes.length !== pin.bytes) {
    throw new Error(
      `build-fonts: vendored license ${path} is ${bytes.length} bytes, pinned length is ${pin.bytes} bytes — this no longer matches the verified upstream file. If this is a deliberate upstream refresh, update VENDORED_LICENSE_PINS in the same commit; otherwise the vendored file was altered or corrupted.`,
    );
  }
  const actualHash = createHash('sha256').update(bytes).digest('hex');
  if (actualHash !== pin.sha256) {
    throw new Error(
      `build-fonts: vendored license ${path} has sha256 ${actualHash}, pinned value is ${pin.sha256} — this no longer matches the verified upstream file. If this is a deliberate upstream refresh, update VENDORED_LICENSE_PINS in the same commit; otherwise the vendored file was altered or corrupted.`,
    );
  }
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

  // Ordering, precisely (re-critic finding, 25.07.2026: "all validation
  // happens before the destination is cleared" was never an accurate
  // description of this script, since output validation necessarily runs
  // after a write exists to check). What actually holds:
  //
  //   - Every INPUT is validated before anything is cleared: a missing
  //     file, a corrupt (non-WOFF2) font, a WOFF2 whose header-declared
  //     length disagrees with its real size, a license that fails the
  //     shape check, or a license that fails its identity pin all throw
  //     HERE, before rm(DEST_FONTS_DIR) runs below — so a failed run at
  //     this stage never touches assets/fonts/ at all.
  //   - OUTPUT validation (assertCopyComplete, further down) runs after
  //     each write, by necessity — a write cannot be checked before it
  //     exists.
  //   - A run that fails partway through the write loop leaves
  //     assets/fonts/ partially regenerated, not corrupted: this script is
  //     idempotent (see the file header) and fully clears+rebuilds the
  //     directory every run, so simply re-running `npm run fonts` repairs
  //     a partial output. The generated src/css/fonts.css write is the one
  //     output that additionally needs its own atomicity (a mid-write
  //     failure there would leave a stylesheet importing files that may or
  //     may not match), so that write goes through a temp-file-then-rename
  //     below instead.
  //
  // Font sources: existence + WOFF2 signature + WOFF2 header length.
  // Buffers are kept so the write step below writes the EXACT bytes
  // validated here, instead of re-reading (and not re-validating) the
  // source from disk — see assertVendoredLicensePin()'s docblock and the
  // A3 note in the file header for why re-reading was the bug.
  const sourceBuffers = new Map();
  for (const face of outputFaces) {
    const sourcePath = join(SRC_FONTS_DIR, face.sourceUrl.replace(/^\.\//, ''));
    let buffer;
    try {
      buffer = await readFile(sourcePath);
    } catch {
      throw new Error(`build-fonts: referenced source file is missing: ${sourcePath}`);
    }
    assertWoff2Signature(buffer, sourcePath);
    assertWoff2Length(buffer, sourcePath);
    sourceBuffers.set(sourcePath, buffer);
  }

  // License sources: existence + plausible OFL 1.1 shape + pinned identity
  // (see the file header and assertVendoredLicensePin()'s docblock for why
  // the shape check alone is not enough).
  const licenseBuffers = new Map();
  for (const { src } of LICENSES) {
    const sourcePath = join(SRC_LICENSE_DIR, src);
    let buffer;
    try {
      buffer = await readFile(sourcePath);
    } catch {
      throw new Error(`build-fonts: vendored license source file is missing: ${sourcePath}`);
    }
    assertVendoredLicenseText(buffer, sourcePath);
    assertVendoredLicensePin(buffer, sourcePath);
    licenseBuffers.set(sourcePath, buffer);
  }

  // Idempotency: start from a clean slate every run so a weight or subset
  // dropped from USED_WEIGHTS/KEEP_SUBSETS can never leave a stale file behind.
  await rm(DEST_FONTS_DIR, { recursive: true, force: true });
  await mkdir(DEST_FONTS_DIR, { recursive: true });

  for (const face of outputFaces) {
    const sourcePath = join(SRC_FONTS_DIR, face.sourceUrl.replace(/^\.\//, ''));
    const destPath = join(DEST_FONTS_DIR, destFileName(face));
    const sourceBuffer = sourceBuffers.get(sourcePath);
    // Write the buffer already validated above, instead of the old
    // copyFile-based approach's re-read from disk: copyFile would re-read
    // the SOURCE path, so anything replacing that file between validation
    // and this point would land in the destination unvalidated (re-critic
    // finding, A3).
    await writeFile(destPath, sourceBuffer);
    const { size: destSize } = await stat(destPath);
    assertCopyComplete(sourceBuffer.length, destSize, destPath);
  }

  for (const { src, dest } of LICENSES) {
    const sourcePath = join(SRC_LICENSE_DIR, src);
    const destPath = join(DEST_FONTS_DIR, dest);
    const sourceBuffer = licenseBuffers.get(sourcePath);
    // Same reasoning as the font-write loop above: write the buffer already
    // validated (shape + pin), don't re-read the source via the old
    // copyFile-based approach.
    await writeFile(destPath, sourceBuffer);
    const { size: destSize } = await stat(destPath);
    assertCopyComplete(sourceBuffer.length, destSize, destPath);
  }

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
  // Atomic write (re-critic finding, A4): write to a temp file NEXT TO the
  // real destination, then rename() over it. rename() within the same
  // directory is a single filesystem operation on both POSIX and Windows —
  // readers of src/css/fonts.css always see either the complete previous
  // version or the complete new version, never a partial write from a
  // process that died mid-write (out of disk space, killed build, etc).
  // The font/license files above don't need this: this script always fully
  // clears and regenerates assets/fonts/ (see "Idempotent" in the file
  // header), so a partial fonts/ directory self-heals on the next run;
  // src/css/fonts.css is edited in place and does not get that same
  // clear-first treatment, so it is the one output where a torn write can
  // persist until someone notices.
  const tmpCssPath = `${DEST_CSS_PATH}.tmp`;
  await writeFile(tmpCssPath, css);
  await rename(tmpCssPath, DEST_CSS_PATH);

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

// Guard so the test suite can import the pure helpers above (WOFF2_SIGNATURE,
// assertWoff2Signature, assertCopyComplete, assertVendoredLicenseText)
// without triggering a real build as a side effect of the import.
// pathToFileURL (not string concatenation) so this also matches on Windows,
// where import.meta.url is `file:///D:/...` (three slashes, forward slashes)
// but process.argv[1] is `D:\...` (drive letter, backslashes).
const isMainModule = process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href;
if (isMainModule) {
  await main();
}
