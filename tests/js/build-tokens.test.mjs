import { describe, expect, it } from 'vitest';
import {
  assertAccessiblePalettes,
  buildPalettesPhp,
  buildThemeJson,
  buildTokensCss,
  contrastFailures,
  contrastRatio,
  formatColor,
  resolveColor,
  varsFor,
} from '../../scripts/lib/build-tokens-lib.mjs';
import { tokens } from '../../src/tokens/tokens.mjs';

const light = (palette = 'warm-clay') => varsFor(tokens, palette, 'light');
const resolved = (name, vars) => resolveColor(vars[name], vars);

describe('resolveColor', () => {
  it('follows var() references to a literal', () => {
    const vars = light();

    // --background is var(--n-0), which is oklch(99% 0.002 var(--n-h)), and
    // --n-h is the palette's neutral temperature. Three hops, all of which the
    // browser makes too.
    expect(resolved('background', vars)).toEqual({ l: 0.99, c: 0.002, h: 68, a: 1 });
  });

  it('reads both oklch spellings and the optional alpha', () => {
    expect(resolveColor('oklch(47% 0.088 40)', {})).toEqual({ l: 0.47, c: 0.088, h: 40, a: 1 });
    expect(resolveColor('oklch(0.47 0.088 40)', {})).toEqual({ l: 0.47, c: 0.088, h: 40, a: 1 });
    expect(resolveColor('oklch(15% 0.01 68 / .5)', {}).a).toBe(0.5);
  });

  it('resolves an alpha given as a custom property', () => {
    const vars = light();

    // --ring-halo spells its alpha as var(--focus-ring-alpha) so the focus halo
    // has exactly one knob. Resolving it proves the knob is real.
    expect(resolved('ring-halo', vars).a).toBe(0.18);
  });

  // The dark scheme desaturates the accent slightly. This is the only
  // arithmetic in the whole token layer, and it must be evaluated rather than
  // approximated: the resolved chroma is what the contrast gate measures.
  it('evaluates calc(var(--x) * n)', () => {
    const vars = varsFor(tokens, 'night-indigo', 'dark');

    expect(resolved('primary', vars).c).toBeCloseTo(0.13 * 0.95, 10);
  });

  // A resolver that shrugged and returned a default would hand the gate a
  // colour nobody ships. Every one of these must be loud.
  it('throws rather than guessing', () => {
    expect(() => resolveColor('var(--nope)', {})).toThrow(/Unknown custom property/);
    expect(() => resolveColor('#3b82f6', {})).toThrow(/Cannot resolve to an oklch colour/);
    expect(() => resolveColor('color-mix(in oklch, red, blue)', {})).toThrow(/Cannot resolve/);
    // All digits and dots, but not a number — it would reach the browser as an
    // invalid colour and be dropped at computed-value time.
    expect(() => resolveColor('oklch(0.5.6 0 0)', {})).toThrow(/Cannot resolve/);
    // Valid CSS, but a lax pattern reads this as 6.3 rather than 0.63 and would
    // measure contrast off the wrong side of the comparison.
    expect(() => resolveColor('oklch(6.3e-1 0 0)', {})).toThrow(/Cannot resolve/);
  });

  it('refuses a cycle instead of recursing forever', () => {
    expect(() => resolveColor('var(--a)', { a: 'var(--b)', b: 'var(--a)' })).toThrow(
      /Custom property cycle/,
    );
  });

  it('refuses a component that is not finite', () => {
    // 309 digits is still all digits, so the pattern admits it and Number()
    // returns Infinity. The colour maths then yields NaN, and `NaN < 4.5` is
    // false — an unmeasurable colour would pass the contrast gate.
    expect(() => resolveColor(`oklch(${'9'.repeat(309)} 0 0)`, {})).toThrow(/Not a finite number/);
  });

  // `Number()` is far more permissive than CSS: `0x10`, `0b110`, `''` and
  // `Infinity` all resolve to plausible numbers in JavaScript and are INVALID
  // in a browser, which drops the declaration at computed-value time — the gate
  // would be measuring a colour no visitor ever sees.
  //
  // `1e3` is the odd one out and is listed here deliberately: it IS valid CSS,
  // and this generator rejects it anyway as a project subset. Keeping it in the
  // same list without saying so is how a test starts asserting something untrue
  // about the platform — which is exactly what the first version of this test
  // did, and what the re-critic caught.
  it('accepts only plain decimals, rejecting both invalid CSS and unused-but-valid exponents', () => {
    for (const spelling of ['0x10', '0b110', '1e3', '', ' ', 'Infinity']) {
      // Through a component slot, which is how a scalar token actually reaches
      // the maths: oklch(…, …, var(--n-h)).
      expect(
        () => resolveColor('oklch(50% 0.1 var(--n-h))', { 'n-h': spelling }),
        spelling,
      ).toThrow(/Not a plain decimal number|Not a finite number/);
    }
  });

  // Chroma may legitimately sit outside sRGB — that is what the gamut mapping
  // is for. Lightness may not: a browser clamps it, so measuring an unclamped
  // 101% computes a ratio nothing on screen has. A typo in the token source
  // must fail the build, not be silently corrected into a colour nobody chose.
  // Both operands finite does not make the product finite. Unchecked, this
  // reaches formatColor() and lands in theme.json as `oklch(50% Infinity 0)` —
  // emitted by a build that reported success.
  it('refuses a calc() whose result overflows even though its operands did not', () => {
    expect(() =>
      resolveColor('oklch(50% calc(var(--c) * 10) 0)', { c: `1${'0'.repeat(308)}` }),
    ).toThrow(/Not a finite number/);
  });

  it('refuses an out-of-range lightness', () => {
    expect(() => resolveColor('oklch(101% 0.06 40)', {})).toThrow(/lightness out of the/);
    expect(() => resolveColor('oklch(1.5 0.06 40)', {})).toThrow(/lightness out of the/);
    expect(resolveColor('oklch(100% 0 0)', {}).l).toBe(1);
    expect(resolveColor('oklch(0% 0 0)', {}).l).toBe(0);
  });
});

describe('varsFor', () => {
  it('lets a palette override the three values and nothing else', () => {
    const warm = light('warm-clay');
    const cold = light('cold-petrol');

    expect([warm['n-h'], warm['accent-h'], warm['accent-c']]).toEqual(['68', '40', '0.088']);
    expect([cold['n-h'], cold['accent-h'], cold['accent-c']]).toEqual(['264', '214', '0.105']);
    // The semantic layer is identical — only its resolved values move.
    expect(warm.background).toBe(cold.background);
    expect(resolved('background', warm).h).not.toBe(resolved('background', cold).h);
  });

  it('layers the dark scheme over the light one, as the cascade does', () => {
    const vars = varsFor(tokens, 'warm-clay', 'dark');

    expect(vars.background).toBe(tokens.colors.dark.background);
    // --warning is declared only in :root; dark inherits it rather than
    // redeclaring, and the merged map has to model that or the gate would
    // measure a token the browser never sees.
    expect(vars.warning).toBe(tokens.colors.light.warning);
  });

  it('refuses an unknown palette', () => {
    expect(() => varsFor(tokens, 'chartreuse', 'light')).toThrow(/Unknown palette/);
  });
});

describe('contrastRatio', () => {
  // The design's accents reach past sRGB, and how a UA brings them back in
  // changes the answer by a quarter of a ratio point — CSS Color 4 §14 permits
  // several algorithms. The gate keeps the WORSE of the naive per-channel clamp
  // and chroma reduction, so it can only ever be stricter than what ships.
  // Two cases, deliberately pulling in OPPOSITE directions, because one case
  // proves nothing: the earlier single assertion here would still have passed
  // with mappedLuminance deleted entirely.
  it('takes the pessimistic of the two out-of-gamut readings', () => {
    const nearWhite = { l: 0.985, c: 0, h: 0, a: 1 };

    // rose-600: 4.53:1 under chroma reduction alone — a pass. The CLAMPED
    // reading says 4.32:1, and that is the one that counts. Delete the clamped
    // half and this goes green when it should not.
    expect(contrastRatio({ l: 0.586, c: 0.253, h: 17.585, a: 1 }, nearWhite)).toBeLessThan(4.5);

    // The mirror image, measured rather than guessed: at oklch(58% .4 265) the
    // clamped reading is 8.10:1 and the CHROMA-REDUCED one 4.33:1. Delete the
    // mapped half and this colour passes AA on a reading no browser using
    // MINDE-style mapping would agree with.
    const ratio = contrastRatio({ l: 0.58, c: 0.4, h: 265, a: 1 }, nearWhite);

    expect(ratio).toBeCloseTo(4.33, 1);
    expect(ratio).toBeLessThan(4.5);
  });

  // A ratio against a colour whose appearance depends on what is behind it is a
  // number that means nothing. Refusing beats reporting fiction.
  it('refuses to measure a translucent colour', () => {
    const vars = light();

    expect(() => contrastRatio(resolved('ring-halo', vars), resolved('card', vars))).toThrow(
      /translucent/,
    );
  });

  it('is symmetric', () => {
    const vars = light();
    const fg = resolved('foreground', vars);
    const bg = resolved('background', vars);

    expect(contrastRatio(fg, bg)).toBeCloseTo(contrastRatio(bg, fg), 12);
  });
});

describe('contrastFailures', () => {
  it('passes every shipped palette in both schemes', () => {
    expect(contrastFailures(tokens)).toEqual([]);
  });

  // The gate measuring nothing would also report zero failures. Prove it
  // measures: every palette and both schemes must be reachable, and a
  // deliberately broken value must surface with its palette, scheme and pair
  // named.
  it('actually measures — a broken palette fails loudly and specifically', () => {
    const broken = {
      ...tokens,
      colors: {
        ...tokens.colors,
        light: {
          ...tokens.colors.light,
          // Mid grey on near-white: 2.16:1 measured. No hue luck rescues it.
          'muted-foreground': 'oklch(75% 0 0)',
        },
      },
    };

    const failures = contrastFailures(broken);

    // One per palette (7) times the four surfaces --muted-foreground is
    // asserted against. Dark redeclares --muted-foreground, so dark is clean.
    expect(failures).toHaveLength(28);
    // Asserted by membership, not by position: the pair table's ORDER is not a
    // contract, and pinning failures[0] to it makes an unrelated reordering
    // look like a regression.
    expect(failures).toContain(
      'warm-clay/light: --muted-foreground on --background is 2.16:1, below AA (4.5:1)',
    );
    expect(failures.some((line) => line.startsWith('night-indigo/light:'))).toBe(true);
  });

  it('catches a dark-scheme-only regression', () => {
    const broken = {
      ...tokens,
      colors: {
        ...tokens.colors,
        dark: { ...tokens.colors.dark, 'primary-foreground': 'oklch(70% 0.02 var(--accent-h))' },
      },
    };

    const failures = contrastFailures(broken);

    expect(failures.every((line) => line.includes('/dark:'))).toBe(true);
    expect(failures).toHaveLength(Object.keys(tokens.palettes).length);
  });

  // "No failures" and "measured nothing" are the same output, and the second
  // one is how a gate quietly stops guarding. An empty palette map is the
  // cheapest way to reach it — a bad merge, a renamed key, a generator run
  // against a half-written source.
  it('refuses to report success when it measured nothing', () => {
    const empty = { ...tokens, palettes: {} };

    expect(() => contrastFailures(empty)).toThrow(/measured 0 pairs/);
    expect(() => buildTokensCss(empty)).toThrow(/measured 0 pairs/);
  });

  it('measures every palette in both schemes, and says so in the count', () => {
    const oneLess = {
      ...tokens,
      palettes: Object.fromEntries(Object.entries(tokens.palettes).slice(0, 3)),
    };

    // 3 palettes × 2 schemes × the pair table. If a future edit makes the loop
    // skip a scheme or short-circuit a palette, the count stops matching and
    // this throws rather than silently narrowing the gate.
    expect(() => contrastFailures(oneLess)).not.toThrow();
  });

  it('throws on build when a palette is inaccessible', () => {
    const broken = {
      ...tokens,
      colors: {
        ...tokens.colors,
        light: { ...tokens.colors.light, foreground: 'oklch(90% 0 0)' },
      },
    };

    expect(() => assertAccessiblePalettes(broken)).toThrow(/fail WCAG AA/);
    expect(() => buildTokensCss(broken)).toThrow(/fail WCAG AA/);
  });
});

describe('buildTokensCss', () => {
  it('emits the palette knobs, the neutral scale and both schemes', () => {
    const css = buildTokensCss(tokens);

    expect(css).toContain('--n-h: 68;');
    expect(css).toContain(`--n-0: ${tokens.neutrals['n-0']};`);
    expect(css).toContain(':root {');
    expect(css).toContain(`--background: ${tokens.colors.light.background};`);
    expect(css).toContain('.dark {');
    expect(css).toContain(`--background: ${tokens.colors.dark.background};`);
    expect(css).toContain(`--radius: ${tokens.radius.radius};`);
    expect(css).toContain(`--font-display: ${tokens.fontRoles['font-display']};`);
    expect(css).toContain('--font-sans: var(--font-body);');
    expect(css).toContain(`--space-md: ${tokens.spacing['space-md']};`);
    expect(css).toContain(`--dur: ${tokens.motion.dur};`);
  });

  // Basecoat declares its own :root/.dark token defaults UN-LAYERED. Layered CSS
  // always loses to un-layered CSS, so tokens wrapped in `@layer theme` would be
  // silently overridden by Basecoat and the Customizer could never move them.
  // Ours must stay un-layered and be imported after Basecoat, where equal
  // specificity makes source order decide.
  // See docs/gotchas/basecoat-tokens-are-un-layered.md
  it('emits tokens un-layered so they can beat Basecoat defaults', () => {
    const withoutComments = buildTokensCss(tokens).replace(/\/\*[\s\S]*?\*\//g, '');

    expect(withoutComments).not.toContain('@layer');
    expect(withoutComments).toContain(':root {');
  });

  // A `system` visitor with JS disabled never gets a .light/.dark class, so
  // without this block they would be stuck on the :root light values no matter
  // what their OS says (M1-05 Task 2).
  it('falls back to the dark values under prefers-color-scheme for a class-less system visitor', () => {
    const css = buildTokensCss(tokens);

    expect(css).toContain('@media (prefers-color-scheme: dark)');
    expect(css).toContain(':root:where(:not(.light):not(.dark)) {');

    // The :where() wrapper is the whole point, not cosmetic. Unwrapped,
    // :root:not(.light):not(.dark) is (0,3,0) and outranks the Customizer's
    // inline :root{--accent-h:…} AND a site owner's Additional CSS — so the
    // palette choice would silently die for `system` + a dark OS, the single
    // commonest configuration. Adversarial review found exactly that.
    // Strip comments first: the generator's own explanation of this trap
    // contains the very selector we are forbidding.
    expect(css.replace(/\/\*[\s\S]*?\*\//g, '')).not.toMatch(/:root:not\(/);

    const mediaBlock = css.slice(css.indexOf('@media (prefers-color-scheme: dark)'));

    for (const [slug, value] of Object.entries(tokens.colors.dark)) {
      expect(mediaBlock, `--${slug} missing from the fallback block`).toContain(
        `--${slug}: ${value};`,
      );
    }

    // Elevation differs by scheme too — dark cannot lean on drop shadows. An
    // earlier generation of this file emitted colours in the fallback but left
    // the light shadows in place, which reads as a flat, wrong-looking dark UI.
    for (const [slug, value] of Object.entries(tokens.shadows.dark)) {
      expect(mediaBlock, `--${slug} missing from the fallback block`).toContain(
        `--${slug}: ${value};`,
      );
    }
  });
});

describe('buildThemeJson', () => {
  it('emits theme.json v3 whose palette references the live tokens (ADR-010)', () => {
    const result = buildThemeJson(tokens);

    expect(result.version).toBe(3);
    expect(result.$schema).toBe('https://schemas.wp.org/trunk/theme.json');

    const palette = result.settings.color.palette;
    const primary = palette.find((entry) => entry.slug === 'primary');

    expect(primary.name).toBe('Primary');

    // This assertion used to be its exact inverse — "must be a literal", on the
    // stated grounds that theme.json "cannot follow a custom property". Measured
    // false in s15: WordPress serialises var() into --wp--preset--color--* verbatim,
    // and add_editor_style() makes it resolve inside the editor canvas too. The
    // literals were #25: a frozen light-scheme snapshot that followed neither the
    // Customizer nor .dark.
    for (const entry of palette) {
      expect(entry.color, `${entry.slug} must reference its token`).toBe(`var(--${entry.slug})`);
    }

    // No fallback: a `var(--x, <literal>)` reintroduces the second copy this change
    // exists to delete, and it drifts silently.
    expect(primary.color).toBe('var(--primary)');
    expect(primary.color).not.toContain(',');
  });

  it('disables core’s default palette and displaces core’s button styling', () => {
    const result = buildThemeJson(tokens);

    expect(result.settings.color.defaultPalette).toBe(false);
    expect(result.styles.elements.button.color).toEqual({
      background: 'var(--primary)',
      text: 'var(--primary-foreground)',
    });

    // ADR-009 decision 5: styles.blocks stays empty. This is styles.elements.
    expect(result.styles.blocks).toBeUndefined();
  });

  it('emits the three type roles as font families', () => {
    const families = buildThemeJson(tokens).settings.typography.fontFamilies;

    expect(families.map((family) => family.slug)).toEqual(['display', 'body', 'mono']);
    expect(families.find((family) => family.slug === 'body').fontFamily).toBe(
      tokens.fontRoles['font-body'],
    );
  });
});

describe('buildPalettesPhp', () => {
  it('emits a strict-typed PHP file returning the seven palettes', () => {
    const php = buildPalettesPhp(tokens);

    expect(php.startsWith('<?php\n')).toBe(true);
    expect(php).toContain('declare(strict_types=1);');
    expect(php).toContain('AUTO-GENERATED');
    expect(php.endsWith('];\n')).toBe(true);

    for (const slug of Object.keys(tokens.palettes)) {
      expect(php).toContain(`'${slug}'`);
    }

    // Spacing is not one space everywhere: WPCS's
    // WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned sniff
    // requires every `=>` in a contiguous block to align on the widest key in
    // that block — and only that run. Slugs align on 'night-indigo'; the three
    // properties align on 'accent-h' within their own palette, NOT on some
    // wider key from elsewhere in the file (over-padding is a violation too).
    expect(php).toContain("'warm-clay'    => [");
    expect(php).toContain("'n-h'      => '68',");
    expect(php).toContain("'accent-c' => '0.088',");
  });

  // The emitted strings are interpolated straight into single-quoted PHP and
  // then into a <style> block. A value carrying a quote, backslash or angle
  // bracket would break out of both. The generator is where that is stopped,
  // once, rather than at every consumer.
  it('refuses to emit a slug that is not a plain identifier', () => {
    const hostile = { ...tokens, palettes: { "o'neil": { 'n-h': '68' } } };

    expect(() => buildPalettesPhp(hostile)).toThrow(/not a plain identifier/);
  });

  it('refuses to emit a value that is not a bare number', () => {
    const hostile = {
      ...tokens,
      // All three required keys present, so this reaches the VALUE guard rather
      // than being stopped earlier by the key allowlist.
      palettes: {
        evil: { 'n-h': "68'; system('rm -rf /'); '", 'accent-h': '40', 'accent-c': '0.088' },
      },
    };

    expect(() => buildPalettesPhp(hostile)).toThrow(/non-numeric palette value/);
  });

  // Validating the value while trusting the KEY is validating neither: both are
  // interpolated into single-quoted PHP in the same expression. A key carrying
  // `' => '0']; …; return ['` closes the array literal and appends statements to
  // a file the theme require()s on every single request. The allowlist is what
  // stops it — there is deliberately no separate key-shape check, because the
  // allowlist makes one unreachable.
  it('refuses a key that would break out of the generated PHP', () => {
    const hostile = {
      ...tokens,
      palettes: { safe: { "x' => '0']; phpinfo(); return ['y": '1' } },
    };

    expect(() => buildPalettesPhp(hostile)).toThrow(/must define exactly/);
  });

  // "A palette is three custom properties" is the architecture (ADR-008), and
  // the PHP consumer reads exactly those three. A fourth key with an innocent
  // name passes every syntactic check, gets emitted, is silently ignored at
  // runtime, and reads to the next person as a supported knob.
  it('refuses a palette that is not exactly the three architectural properties', () => {
    const extra = {
      ...tokens,
      palettes: { safe: { 'n-h': '68', 'accent-h': '40', 'accent-c': '0.088', background: '0' } },
    };
    const missing = { ...tokens, palettes: { safe: { 'n-h': '68', 'accent-h': '40' } } };

    expect(() => buildPalettesPhp(extra)).toThrow(/must define exactly/);
    expect(() => buildPalettesPhp(missing)).toThrow(/must define exactly/);
  });
});
