import { describe, expect, it } from 'vitest';
import {
  buildPrimaryPresets,
  contrastRatio,
  buildPrimaryPresetsPhp,
  buildThemeJson,
  buildTokensCss,
  lightnessOf,
} from '../../scripts/lib/build-tokens-lib.mjs';
import { tokens } from '../../src/tokens/tokens.mjs';

describe('buildThemeJson', () => {
  it('emits theme.json v3 with a palette entry per light color', () => {
    const result = buildThemeJson(tokens);
    expect(result.version).toBe(3);
    expect(result.$schema).toBe('https://schemas.wp.org/trunk/theme.json');
    const palette = result.settings.color.palette;
    expect(palette).toHaveLength(Object.keys(tokens.colors.light).length);
    const primary = palette.find((entry) => entry.slug === 'primary');
    expect(primary).toEqual({
      slug: 'primary',
      name: 'Primary',
      color: tokens.colors.light.primary,
    });
  });

  it('emits font families', () => {
    const families = buildThemeJson(tokens).settings.typography.fontFamilies;
    expect(families.find((f) => f.slug === 'sans').fontFamily).toBe(tokens.fonts.sans);
  });
});

describe('buildTokensCss', () => {
  it('emits :root light values and .dark overrides', () => {
    const css = buildTokensCss(tokens);
    expect(css).toContain(':root {');
    expect(css).toContain(`--background: ${tokens.colors.light.background};`);
    expect(css).toContain('.dark {');
    expect(css).toContain(`--background: ${tokens.colors.dark.background};`);
    expect(css).toContain(`--radius: ${tokens.radius};`);
    expect(css).toContain(`--font-sans: ${tokens.fonts.sans};`);
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
  // without this block they would be stuck on the :root light values no
  // matter what their OS says (M1-05 Task 2). The block must stay un-layered
  // like the rest of this file (docs/gotchas/basecoat-tokens-are-un-layered.md)
  // and must be excluded the moment an explicit class is present, so it never
  // fights a decision (admin default or stored visitor choice) that has
  // already been made.
  it('falls back to the dark values under prefers-color-scheme for a class-less system visitor', () => {
    const css = buildTokensCss(tokens);

    expect(css).toContain('@media (prefers-color-scheme: dark)');
    expect(css).toContain(':root:not(.light):not(.dark) {');

    for (const [slug, value] of Object.entries(tokens.colors.dark)) {
      expect(css).toContain(`--${slug}: ${value};`);
    }

    const mediaBlock = css.slice(css.indexOf('@media (prefers-color-scheme: dark)'));

    for (const [slug, value] of Object.entries(tokens.colors.dark)) {
      expect(mediaBlock).toContain(`--${slug}: ${value};`);
    }
  });

  it('still emits no @layer anywhere, including the prefers-color-scheme block', () => {
    const withoutComments = buildTokensCss(tokens).replace(/\/\*[\s\S]*?\*\//g, '');
    expect(withoutComments).not.toContain('@layer');
  });
});

describe('buildPrimaryPresets', () => {
  it('reads the lightness of both oklch spellings', () => {
    expect(lightnessOf('oklch(54.6% 0.245 262.881)')).toBeCloseTo(0.546, 3);
    expect(lightnessOf('oklch(0.145 0 0)')).toBeCloseTo(0.145, 3);
    expect(() => lightnessOf('#3b82f6')).toThrow(/Not an oklch colour/);
  });

  it('emits the 8 curated presets, each a light+dark tuple of 3 vars', () => {
    const presets = buildPrimaryPresets(tokens);

    expect(Object.keys(presets)).toEqual([
      'neutral',
      'blue',
      'green',
      'red',
      'rose',
      'orange',
      'yellow',
      'violet',
    ]);

    expect(presets.blue.light).toEqual({
      '--primary': tokens.primaryPalette.blue.light,
      '--primary-foreground': 'oklch(0.985 0 0)',
      '--ring': tokens.primaryPalette.blue.light,
    });
  });

  // A preset is a coherent tuple, never a single hex (spec §6), and the pair
  // has to stay READABLE in both schemes. An earlier version of this test
  // compared oklch lightness and passed while rose-600 sat at 4.32:1 against
  // near-white: lightness alone ignores how hue and chroma move relative
  // luminance. Measure the thing the standard actually specifies.
  it('keeps every primary/foreground pair at WCAG AA or better', () => {
    const presets = buildPrimaryPresets(tokens);

    for (const [slug, schemes] of Object.entries(presets)) {
      for (const scheme of ['light', 'dark']) {
        const ratio = contrastRatio(
          schemes[scheme]['--primary'],
          schemes[scheme]['--primary-foreground'],
        );
        expect(ratio, `${slug}/${scheme} contrast`).toBeGreaterThanOrEqual(4.5);
      }
    }
  });

  // Every scheme of every preset is a full triple. Asserting only blue.light
  // (as an earlier version did) let --ring rot in the other 15.
  it('gives every scheme all three custom properties', () => {
    for (const [slug, schemes] of Object.entries(buildPrimaryPresets(tokens))) {
      for (const scheme of ['light', 'dark']) {
        expect(Object.keys(schemes[scheme]), `${slug}/${scheme}`).toEqual([
          '--primary',
          '--primary-foreground',
          '--ring',
        ]);
        expect(schemes[scheme]['--ring'], `${slug}/${scheme} ring`).toBe(
          schemes[scheme]['--primary'],
        );
      }
    }
  });

  it('refuses a palette value that cannot carry readable text', () => {
    const unreadable = {
      ...tokens,
      // A mid grey is the hardest case there is: 4.28:1 on near-white and
      // 4.43:1 on near-black, so no choice of foreground reaches AA.
      primaryPalette: { grey: { light: 'oklch(0.57 0 0)', dark: 'oklch(0.2 0 0)' } },
    };

    expect(() => buildPrimaryPresets(unreadable)).toThrow(/cannot reach WCAG AA/);
  });

  it('refuses a component that is not finite', () => {
    // 309 digits is still all digits, so the pattern admits it and Number()
    // returns Infinity. The colour maths then yields NaN, and `NaN < 4.5` is
    // false — an unmeasurable colour would pass the contrast gate.
    const huge = `oklch(${'9'.repeat(309)} 0 0)`;

    expect(() => lightnessOf(huge)).toThrow(/Not a finite oklch colour/);
  });

  // 11 of the 16 palette values fall outside sRGB, so how the gate handles
  // them decides its answers — and CSS Color 4 §14 lets a UA choose among
  // several mappings. The gate keeps the WORSE of the naive clamp (5.80 for
  // rose-700) and chroma reduction (6.05), so it can only be stricter than
  // what ships.
  it('takes the pessimistic of the two out-of-gamut readings', () => {
    expect(contrastRatio('oklch(51.4% 0.222 16.935)', 'oklch(0.985 0 0)')).toBeCloseTo(5.8, 1);

    // rose-600 measures 4.53:1 under chroma reduction alone — a pass. The
    // clamped reading says 4.32:1, and that is the one that counts.
    expect(contrastRatio('oklch(58.6% 0.253 17.585)', 'oklch(0.985 0 0)')).toBeLessThan(4.5);
  });

  // A finite but absurd chroma used to leave the bisection bracket
  // astronomically wide, so 32 halvings never reached the gamut boundary and
  // the colour was measured as a neutral grey.
  it('refuses a colour whose contrast cannot be measured', () => {
    const absurd = {
      ...tokens,
      primaryPalette: {
        wild: { light: `oklch(0.541 ${'9'.repeat(298)} 142)`, dark: 'oklch(0.2 0 0)' },
      },
    };

    expect(() => buildPrimaryPresets(absurd)).toThrow(/cannot reach WCAG AA/);
  });

  it('rejects malformed and exotic oklch spellings', () => {
    // All digits and dots, but not a number — it would reach the browser as an
    // invalid colour and be dropped at computed-value time.
    expect(() => lightnessOf('oklch(0.5.6 0 0)')).toThrow(/Not an oklch colour/);
    // Valid CSS, but a lax pattern reads this as 6.3 rather than 0.63.
    expect(() => lightnessOf('oklch(6.3e-1 0 0)')).toThrow(/Not an oklch colour/);
    expect(() => lightnessOf('#3b82f6')).toThrow(/Not an oklch colour/);
  });

  it('picks the dark foreground for light primaries', () => {
    // yellow-600 measures 6.75:1 against near-black and only 2.81:1 against
    // near-white, so the measurement picks the dark foreground.
    expect(buildPrimaryPresets(tokens).yellow.light['--primary-foreground']).toBe(
      'oklch(0.145 0 0)',
    );
  });
});

describe('buildPrimaryPresetsPhp', () => {
  it('emits a strict-typed PHP file returning the preset map', () => {
    const php = buildPrimaryPresetsPhp(tokens);

    expect(php.startsWith('<?php\n')).toBe(true);
    expect(php).toContain('declare(strict_types=1);');
    expect(php).toContain('AUTO-GENERATED');
    // Spacing is not one space everywhere: WPCS's
    // WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned sniff
    // requires every `=>` in a contiguous block to align on the widest key in
    // that block ('neutral' among slugs, '--primary-foreground' among the
    // three preset vars). The generator computes that padding so the
    // committed file is PHPCS-clean without a phpcs:ignore.
    expect(php).toContain("'blue'    => [");
    expect(php).toContain(`'--primary'            => '${tokens.primaryPalette.blue.light}',`);
    expect(php).toContain("'dark'  => [");
    expect(php.endsWith('];\n')).toBe(true);
  });

  // The emitted strings are interpolated straight into single-quoted PHP and
  // then into a <style> block. A value carrying a quote, backslash or angle
  // bracket would break out of both. The generator is where that is stopped,
  // once, rather than at every consumer.
  it('refuses to emit a slug that is not a plain identifier', () => {
    const hostile = {
      ...tokens,
      // A quote closes the PHP array key and hands the parser a fatal.
      primaryPalette: { "o'neil": { light: 'oklch(0.3 0 0)', dark: 'oklch(0.8 0 0)' } },
    };

    expect(() => buildPrimaryPresetsPhp(hostile)).toThrow(/not a plain identifier/);
  });

  it('refuses to emit a value that is not a plain oklch() literal', () => {
    const hostile = {
      ...tokens,
      primaryPalette: {
        evil: { light: "oklch(1 0 0)'; system('rm -rf /'); '", dark: 'oklch(0 0 0)' },
      },
    };

    // Two guards now stand between this value and the file: the strict oklch
    // parser rejects it while the tuple is being built, and the emitter checks
    // again immediately before interpolating into single-quoted PHP. The first
    // one fires today; the emitter's is the last line of defence at the point
    // of entry into the string, and is kept for that reason.
    expect(() => buildPrimaryPresetsPhp(hostile)).toThrow(/Not an oklch colour|Refusing to emit/);
  });
});
