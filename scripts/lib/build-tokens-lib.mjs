const titleCase = (slug) =>
  slug
    .split('-')
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ');

export function buildThemeJson(tokens) {
  return {
    $schema: 'https://schemas.wp.org/trunk/theme.json',
    version: 3,
    settings: {
      color: {
        palette: Object.entries(tokens.colors.light).map(([slug, color]) => ({
          slug,
          name: titleCase(slug),
          color,
        })),
      },
      typography: {
        fontFamilies: Object.entries(tokens.fonts).map(([slug, fontFamily]) => ({
          slug,
          name: titleCase(slug),
          fontFamily,
        })),
      },
    },
  };
}

const varsBlock = (colors, indent) =>
  Object.entries(colors)
    .map(([slug, value]) => `${indent}--${slug}: ${value};`)
    .join('\n');

export function buildTokensCss(tokens) {
  const fontVars = Object.entries(tokens.fonts)
    .map(([slug, value]) => `  --font-${slug}: ${value};`)
    .join('\n');

  return `/* AUTO-GENERATED from src/tokens/tokens.mjs — do not edit. Run \`npm run tokens\`. */

/* Deliberately UN-LAYERED: Basecoat declares its own :root/.dark token defaults
 * un-layered too, and un-layered CSS beats every layer. Wrapped in @layer theme
 * these would lose to Basecoat and the Customizer could never move them.
 * Import order in app.css (after Basecoat) is what makes ours win.
 * See docs/gotchas/basecoat-tokens-are-un-layered.md */
:root {
${varsBlock(tokens.colors.light, '  ')}
  --radius: ${tokens.radius};
${fontVars}
}

.dark {
${varsBlock(tokens.colors.dark, '  ')}
}
`;
}

// The two neutral extremes every preset's foreground is drawn from — the same
// values tokens.colors.light.foreground / .background already use.
const FOREGROUND_ON_LIGHT = 'oklch(0.145 0 0)';
const FOREGROUND_ON_DARK = 'oklch(0.985 0 0)';

// Above this lightness a primary needs dark text on it, below it needs light.
// Empirical: it is what moves yellow-600 (L 0.681) off the near-white
// foreground that would leave a 0.31 lightness gap.
const FOREGROUND_THRESHOLD = 0.62;

/**
 * Lightness of an oklch() colour, normalised to 0…1.
 *
 * Accepts both spellings in play here: Tailwind writes `oklch(54.6% …)`, our
 * own tokens write `oklch(0.145 …)`.
 */
export function lightnessOf(color) {
  const match = /^oklch\(\s*([\d.]+)(%?)/.exec(color);

  if (null === match) {
    throw new Error(`Not an oklch colour: ${color}`);
  }

  return '%' === match[2] ? Number(match[1]) / 100 : Number(match[1]);
}

const presetTuple = (primary) => ({
  '--primary': primary,
  '--primary-foreground':
    lightnessOf(primary) > FOREGROUND_THRESHOLD ? FOREGROUND_ON_LIGHT : FOREGROUND_ON_DARK,
  '--ring': primary,
});

/**
 * The curated accent presets, each a light+dark tuple of --primary,
 * --primary-foreground and --ring (spec §6).
 */
export function buildPrimaryPresets(tokens) {
  return Object.fromEntries(
    Object.entries(tokens.primaryPalette).map(([slug, pair]) => [
      slug,
      { light: presetTuple(pair.light), dark: presetTuple(pair.dark) },
    ]),
  );
}

// A colour that reaches the generated PHP is interpolated into a single-quoted
// string and later into a <style> block. Pin the shape here so neither consumer
// has to defend itself: digits, dots, spaces, percent signs, nothing else.
const OKLCH_LITERAL = /^oklch\([\d.% ]+\)$/;

// PHPCS's WordPress.Arrays.MultipleStatementAlignment sniff requires every
// `=>` in a contiguous block of array-item assignments to land in the same
// column, padded to the widest key in that block. Doing this by hand in the
// template (as an earlier draft did) drifts the moment a preset is added or
// renamed; computing it keeps the generator PHPCS-clean without ever hand
// -editing the generated file or reaching for phpcs:ignore.
const quoteKey = (key) => `'${key}'`;

const widestKey = (keys) => Math.max(...keys.map((key) => quoteKey(key).length));

const alignedArrow = (key, width) => {
  const label = quoteKey(key);
  return `${label}${' '.repeat(width - label.length + 1)}=>`;
};

/**
 * The preset map as a committed PHP file (spec §6: one source, two consumers).
 */
export function buildPrimaryPresetsPhp(tokens) {
  const presets = buildPrimaryPresets(tokens);

  const slugWidth = widestKey(Object.keys(presets));
  const schemeLabelWidth = widestKey(['light', 'dark']);

  const scheme = (vars) => {
    const propertyWidth = widestKey(Object.keys(vars));

    return Object.entries(vars)
      .map(([property, value]) => {
        if (!OKLCH_LITERAL.test(value)) {
          throw new Error(`Refusing to emit a non-oklch preset value: ${value}`);
        }

        return `\t\t\t${alignedArrow(property, propertyWidth)} '${value}',`;
      })
      .join('\n');
  };

  const entries = Object.entries(presets)
    .map(
      ([slug, schemes]) =>
        `\t${alignedArrow(slug, slugWidth)} [\n` +
        `\t\t${alignedArrow('light', schemeLabelWidth)} [\n${scheme(schemes.light)}\n\t\t],\n` +
        `\t\t${alignedArrow('dark', schemeLabelWidth)} [\n${scheme(schemes.dark)}\n\t\t],\n` +
        `\t],`,
    )
    .join('\n');

  return `<?php
/**
 * AUTO-GENERATED from src/tokens/tokens.mjs — do not edit. Run \`npm run tokens\`.
 *
 * The curated primary presets (spec §6). Each is a coherent light+dark tuple of
 * --primary, --primary-foreground and --ring; \`default\` is not listed because
 * it means "inherit the active style pack" and emits no override at all.
 *
 * @package Woodev\\Theme\\Base
 */

declare(strict_types=1);

return [
${entries}
];
`;
}
