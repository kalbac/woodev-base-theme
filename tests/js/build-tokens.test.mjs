import { describe, expect, it } from 'vitest';
import { buildThemeJson, buildTokensCss } from '../../scripts/lib/build-tokens-lib.mjs';
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
});

import { buildPrimaryPresets, lightnessOf } from '../../scripts/lib/build-tokens-lib.mjs';

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

  // A preset is a coherent tuple, never a single hex (spec §6). The pairing has
  // to stay readable in BOTH schemes, and the failure mode is silent — a yellow
  // button with near-white text still renders, it is just unusable.
  it('keeps every primary/foreground pair far apart in lightness', () => {
    const presets = buildPrimaryPresets(tokens);

    for (const [slug, schemes] of Object.entries(presets)) {
      for (const scheme of ['light', 'dark']) {
        const gap = Math.abs(
          lightnessOf(schemes[scheme]['--primary']) -
            lightnessOf(schemes[scheme]['--primary-foreground']),
        );
        expect(gap, `${slug}/${scheme}`).toBeGreaterThanOrEqual(0.38);
      }
    }
  });

  it('picks the dark foreground for light primaries', () => {
    // yellow-600 sits at L 0.681 — above the threshold, so near-white text
    // would leave a 0.31 gap and an unreadable button.
    expect(buildPrimaryPresets(tokens).yellow.light['--primary-foreground']).toBe(
      'oklch(0.145 0 0)',
    );
  });
});

import { buildPrimaryPresetsPhp } from '../../scripts/lib/build-tokens-lib.mjs';

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
  it('refuses to emit a value that is not a plain oklch() literal', () => {
    const hostile = {
      ...tokens,
      primaryPalette: { evil: { light: "oklch(1 0 0)'; system('rm -rf /'); '", dark: 'oklch(0 0 0)' } },
    };

    expect(() => buildPrimaryPresetsPhp(hostile)).toThrow(/Refusing to emit/);
  });
});
