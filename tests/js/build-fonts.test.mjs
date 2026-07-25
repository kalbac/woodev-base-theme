import { Buffer } from 'node:buffer';
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';
import {
  WOFF2_SIGNATURE,
  assertCopyComplete,
  assertVendoredLicensePin,
  assertVendoredLicenseText,
  assertWoff2Length,
  assertWoff2Signature,
} from '../../scripts/build-fonts.mjs';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '../..');

const LICENSES = [
  {
    family: 'Golos Text',
    vendored: join(ROOT, 'docs/design/v2-mockup/assets/fonts/OFL-golos-text.txt'),
    shipped: join(ROOT, 'woodev-base-theme/assets/fonts/LICENSE-golos-text.txt'),
  },
  {
    family: 'IBM Plex (Sans + Mono)',
    vendored: join(ROOT, 'docs/design/v2-mockup/assets/fonts/OFL-ibm-plex.txt'),
    shipped: join(ROOT, 'woodev-base-theme/assets/fonts/LICENSE-ibm-plex.txt'),
  },
];

describe('shipped font licenses are the real upstream text, not a paraphrase', () => {
  // Defect (Codex critic, 25.07.2026): the script used to embed the OFL 1.1
  // body "from memory" and synthesise a copyright line per family — with a
  // fabricated year and a Reserved Font Name the authors never reserved
  // (Golos Text), and the wrong Reserved Font Name for IBM Plex ("IBM Plex"
  // instead of upstream's "Plex"). A license attached to someone else's font
  // must be their exact text, not a reconstruction of it.
  for (const { family, vendored, shipped } of LICENSES) {
    it(`${family}: shipped license is byte-identical to its vendored input`, () => {
      // Buffers, not strings: decoding to UTF-8 and comparing strings — or
      // worse, trimming — cannot see a dropped trailing newline, a
      // re-encoded character, or a line-ending change, all of which change
      // the legal document being shipped even though they "look the same"
      // in an editor. This is exactly the class of defect a normalising
      // comparison would miss.
      const vendoredBytes = readFileSync(vendored);
      const shippedBytes = readFileSync(shipped);

      expect(shippedBytes.equals(vendoredBytes)).toBe(true);
    });
  }

  it('Golos Text license does not claim a Reserved Font Name (upstream reserves none)', () => {
    const text = readFileSync(
      join(ROOT, 'woodev-base-theme/assets/fonts/LICENSE-golos-text.txt'),
    ).toString('utf8');
    const firstLine = text.split(/\r?\n/, 1)[0];

    expect(firstLine).toBe(
      'Copyright 2019 The Golos Text Project Authors (https://github.com/googlefonts/golos-text)',
    );
    expect(firstLine).not.toContain('Reserved Font Name');
  });

  it('IBM Plex license reserves exactly "Plex", not "IBM Plex"', () => {
    const text = readFileSync(
      join(ROOT, 'woodev-base-theme/assets/fonts/LICENSE-ibm-plex.txt'),
    ).toString('utf8');
    const firstLine = text.split(/\r?\n/, 1)[0];

    expect(firstLine).toBe('Copyright © 2017 IBM Corp. with Reserved Font Name "Plex"');
    expect(firstLine).not.toContain('"IBM Plex"');
  });

  // Regression guard for the defect itself, not just its symptom: no
  // embedded or synthesised license text may remain in the script's source.
  // If any of these strings reappear, someone reintroduced an in-memory
  // reproduction of the OFL body instead of copying the vendored file.
  it('the build script embeds no license text or copyright-line synthesis of its own', () => {
    const scriptSource = readFileSync(join(ROOT, 'scripts/build-fonts.mjs'), 'utf8');

    expect(scriptSource).not.toContain('PERMISSION & CONDITIONS');
    expect(scriptSource).not.toContain('oflLicenseText');
    expect(scriptSource).not.toContain('OFL_PREAMBLE_AND_BODY');
  });
});

describe('assertWoff2Signature', () => {
  it('accepts a buffer starting with the WOFF2 magic number', () => {
    expect(() =>
      assertWoff2Signature(Buffer.from(`wOF2${'x'.repeat(20)}`), 'fixture.woff2'),
    ).not.toThrow();
  });

  // The exact failure mode Defect 2 leaves open: a vendored woff2 replaced by
  // an HTML error page (e.g. a failed CDN fetch) has a real file at the
  // expected path, a plausible size, and passes every check the old script
  // ran — right up until a browser tries to parse it as a font.
  it('rejects a fixture that is not a woff2, such as an HTML error page', () => {
    const htmlBody = Buffer.from('<html><body>404 Not Found</body></html>');

    expect(() => assertWoff2Signature(htmlBody, 'fixture.woff2')).toThrow(/WOFF2 signature/);
  });

  it('rejects a buffer too short to even contain a 4-byte signature', () => {
    expect(() => assertWoff2Signature(Buffer.from('wO'), 'fixture.woff2')).toThrow(
      /WOFF2 signature/,
    );
  });

  it('exports the exact 4-byte signature it checks against ("wOF2")', () => {
    expect(WOFF2_SIGNATURE).toEqual(Buffer.from([0x77, 0x4f, 0x46, 0x32]));
    expect(WOFF2_SIGNATURE.toString('ascii')).toBe('wOF2');
  });
});

describe('assertWoff2Length', () => {
  // Builds a buffer with a real WOFF2 signature at offset 0 and a chosen
  // `length` field (uint32 BE) at offset 8, then truncates/pads it to
  // `actualLength` bytes total — letting a test independently control what
  // the header CLAIMS versus what is REALLY there.
  function woff2Header(actualLength, declaredLength) {
    const buf = Buffer.alloc(Math.max(actualLength, 12));
    WOFF2_SIGNATURE.copy(buf, 0);
    buf.writeUInt32BE(declaredLength, 8);
    return buf.subarray(0, actualLength);
  }

  it('accepts a header whose declared length matches the real file size', () => {
    const buf = woff2Header(100, 100);

    expect(() => assertWoff2Length(buf, 'fixture.woff2')).not.toThrow();
  });

  // Re-critic finding (A2, 25.07.2026): the exact failure mode
  // assertWoff2Signature() cannot catch — a file consisting of exactly the
  // 4-byte "wOF2" signature (or the signature plus arbitrary padding) passes
  // the signature check and, under the OLD assertCopyComplete comparison,
  // the byte-length check too (destination size trivially equals source
  // size when both are the same short/corrupt file). The header's own
  // length field is what actually exposes the corruption.
  it('rejects a file whose header-declared length disagrees with its real size', () => {
    const truncated = woff2Header(50, 100); // header claims 100 bytes, only 50 are present

    expect(() => assertWoff2Length(truncated, 'fixture.woff2')).toThrow(
      /declares a WOFF2 header length of 100 bytes.*is 50 bytes/,
    );
  });

  it('rejects a buffer too short to even contain the length field', () => {
    expect(() => assertWoff2Length(Buffer.from(`wOF2`), 'fixture.woff2')).toThrow(/too short/);
  });
});

describe('assertCopyComplete', () => {
  it('accepts a copy whose byte length matches its source', () => {
    expect(() => assertCopyComplete(1234, 1234, 'dest.woff2')).not.toThrow();
  });

  // A signature check alone would not catch this: the first four bytes can
  // be intact while the rest of the file was cut off mid-write.
  it('rejects a truncated copy', () => {
    expect(() => assertCopyComplete(1234, 900, 'dest.woff2')).toThrow(/truncated copy/);
  });

  it('rejects a copy that is unexpectedly larger than its source too', () => {
    expect(() => assertCopyComplete(1234, 2000, 'dest.woff2')).toThrow(/truncated copy/);
  });
});

describe('assertVendoredLicenseText', () => {
  const validOfl = Buffer.from(
    [
      'Copyright 2019 The Golos Text Project Authors',
      '',
      'This Font Software is licensed under the SIL Open Font License, Version 1.1.',
      '',
      '-----------------------------------------------------------',
      'SIL OPEN FONT LICENSE Version 1.1 - 26 February 2007',
      '-----------------------------------------------------------',
    ].join('\n'),
  );

  it('accepts text that starts with "Copyright" and contains the OFL 1.1 heading', () => {
    expect(() => assertVendoredLicenseText(validOfl, 'OFL-fixture.txt')).not.toThrow();
  });

  it('rejects text that does not start with "Copyright"', () => {
    const missingCopyright = Buffer.from(
      'This font is free to use.\n\nSIL OPEN FONT LICENSE Version 1.1 - 26 February 2007',
    );

    expect(() => assertVendoredLicenseText(missingCopyright, 'OFL-fixture.txt')).toThrow(
      /does not start with "Copyright"/,
    );
  });

  // This is what actually stops the original defect from recurring: a
  // from-memory paraphrase can easily start with "Copyright" and still not
  // be the OFL 1.1 body.
  it('rejects text missing the OFL 1.1 heading — a paraphrase is not the license', () => {
    const paraphrase = Buffer.from(
      'Copyright 2019 The Golos Text Project Authors\n\nThis is licensed under a permissive open license.',
    );

    expect(() => assertVendoredLicenseText(paraphrase, 'OFL-fixture.txt')).toThrow(
      /does not contain "SIL OPEN FONT LICENSE Version 1.1"/,
    );
  });
});

describe('assertVendoredLicensePin', () => {
  // Re-critic finding (A1, 25.07.2026): assertVendoredLicenseText() alone is
  // circular against the defect it exists to catch — it only checks that a
  // vendored file LOOKS like an OFL 1.1 body (starts with "Copyright",
  // contains the OFL heading), and the old test compared the shipped file
  // only against this same vendored input. A vendored file with its whole
  // middle silently deleted, leaving just the first line and the heading,
  // satisfies the shape check and would still ship — an OFL §2 violation.
  // Pinning SHA-256 + byte length against values verified against the real
  // upstream files closes that hole.

  it('accepts the real vendored Golos Text license, matching its pinned sha256 and length', () => {
    const path = join(ROOT, 'docs/design/v2-mockup/assets/fonts/OFL-golos-text.txt');
    const bytes = readFileSync(path);

    expect(() => assertVendoredLicensePin(bytes, path)).not.toThrow();
  });

  it('accepts the real vendored IBM Plex license, matching its pinned sha256 and length', () => {
    const path = join(ROOT, 'docs/design/v2-mockup/assets/fonts/OFL-ibm-plex.txt');
    const bytes = readFileSync(path);

    expect(() => assertVendoredLicensePin(bytes, path)).not.toThrow();
  });

  // The mutation that matters: this buffer keeps the exact first line and
  // the exact OFL 1.1 heading of the real Golos Text license — so it still
  // passes assertVendoredLicenseText()'s shape check below — but everything
  // between them (the whole license body) has been deleted. Only the pin
  // can catch this.
  it('rejects a vendored license truncated to just its first line and the OFL heading', () => {
    const truncated = Buffer.from(
      [
        'Copyright 2019 The Golos Text Project Authors (https://github.com/googlefonts/golos-text)',
        '',
        '-----------------------------------------------------------',
        'SIL OPEN FONT LICENSE Version 1.1 - 26 February 2007',
        '-----------------------------------------------------------',
      ].join('\n'),
    );
    const fakePath = join(ROOT, 'docs/design/v2-mockup/assets/fonts/OFL-golos-text.txt');

    // Sanity check that this is really the failure mode being guarded
    // against: the shape check alone must NOT catch it.
    expect(() => assertVendoredLicenseText(truncated, fakePath)).not.toThrow();
    expect(() => assertVendoredLicensePin(truncated, fakePath)).toThrow(
      /no longer matches the verified upstream file/,
    );
  });

  it('rejects a path with no registered pin', () => {
    expect(() =>
      assertVendoredLicensePin(Buffer.from('Copyright x'), 'unknown-license.txt'),
    ).toThrow(/no entry in VENDORED_LICENSE_PINS/);
  });
});

describe('build-fonts.mjs writes validated buffers directly instead of re-reading via copyFile()', () => {
  // Re-critic finding (A3, 25.07.2026): the script used to read + validate a
  // buffer, then call copyFile(source, dest) — which re-reads `source` from
  // disk. Anything that replaced the source file between validation and the
  // copyFile() call would land in the destination completely unvalidated.
  // There is no practical way to assert this behaviourally in a unit test
  // (it needs a real race on the filesystem mid-build), so — same idiom as
  // the license-synthesis guard above — this is a regression guard on the
  // source itself: copyFile() must not be called anywhere in the script.
  it('never calls copyFile() — every write uses the buffer already validated in memory', () => {
    const scriptSource = readFileSync(join(ROOT, 'scripts/build-fonts.mjs'), 'utf8');

    expect(scriptSource).not.toMatch(/\bcopyFile\s*\(/);
  });
});

describe('build-fonts.mjs writes src/css/fonts.css atomically', () => {
  // Re-critic finding (A4, 25.07.2026): a process that dies mid-write to
  // src/css/fonts.css (disk full, killed build) could leave a truncated
  // generated stylesheet in place, since that file is edited in place and —
  // unlike woodev-base-theme/assets/fonts/ — is not cleared and rebuilt from
  // scratch every run. The fix writes to a temp file next to the real
  // destination, then rename()s over it — a single filesystem operation, so
  // readers always see either the whole old file or the whole new one.
  // Same reasoning as the copyFile() guard above: this is a regression
  // guard on the source, because the failure mode it prevents (a process
  // dying mid-write) cannot be triggered deterministically in a unit test.
  it('writes to a temp file and renames over the destination — never writes DEST_CSS_PATH directly', () => {
    const scriptSource = readFileSync(join(ROOT, 'scripts/build-fonts.mjs'), 'utf8');

    expect(scriptSource).toMatch(/await writeFile\(tmpCssPath, css\)/);
    expect(scriptSource).toMatch(/await rename\(tmpCssPath, DEST_CSS_PATH\)/);
    expect(scriptSource).not.toMatch(/await writeFile\(DEST_CSS_PATH, css\)/);
  });
});
