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

@media (prefers-color-scheme: dark) {
  /* JS-disabled \`system\` visitors only. An explicit admin default or a stored
   * visitor choice puts .light/.dark on <html>, and either one excludes this
   * block — so it never fights a decision that has already been made. */
  :root:not(.light):not(.dark) {
${varsBlock(tokens.colors.dark, '    ')}
  }
}
`;
}

// The two neutral extremes every preset's foreground is drawn from — the same
// values tokens.colors.light.foreground / .background already use.
const FOREGROUND_ON_LIGHT = 'oklch(0.145 0 0)';
const FOREGROUND_ON_DARK = 'oklch(0.985 0 0)';

// WCAG 2.1 AA for normal-size text. A preset's --primary is a button
// background and --primary-foreground is the label on it, so this is the bar.
const MIN_CONTRAST = 4.5;

// One oklch component: an integer or decimal with at most one dot. Deliberately
// NOT `[\d.]+`, which admits nonsense like `0.5.6` — that parses to NaN and
// reaches the browser as an invalid colour, i.e. a declaration silently dropped
// at computed-value time. Scientific notation is valid CSS but is rejected on
// purpose: nothing generates it here, and a lax pattern would read `6.3e-1` as
// 6.3 and pick the foreground off the wrong side of the contrast comparison.
const OKLCH_COLOUR = /^oklch\(\s*(\d+(?:\.\d+)?)(%?)\s+(\d+(?:\.\d+)?)\s+(\d+(?:\.\d+)?)\s*\)$/;

/**
 * The L, C and H of an oklch() colour, with L normalised to 0…1.
 *
 * Accepts both spellings in play here: Tailwind writes `oklch(54.6% …)`, our
 * own tokens write `oklch(0.145 …)`.
 */
function parseOklch(color) {
  const match = OKLCH_COLOUR.exec(color);

  if (null === match) {
    throw new Error(`Not an oklch colour: ${color}`);
  }

  const parsed = {
    l: '%' === match[2] ? Number(match[1]) / 100 : Number(match[1]),
    c: Number(match[3]),
    h: Number(match[4]),
  };

  // The pattern bounds the SHAPE, not the magnitude: 309 digits is still all
  // digits, and Number() turns it into Infinity. That would poison the colour
  // maths into NaN, and `NaN < MIN_CONTRAST` is false — so an unmeasurable
  // colour would sail through the contrast gate as if it had passed.
  if (!Object.values(parsed).every(Number.isFinite)) {
    throw new Error(`Not a finite oklch colour: ${color}`);
  }

  return parsed;
}

// Channel tolerance for "inside sRGB" — floating-point slack, not a real gamut
// allowance.
const GAMUT_EPSILON = 0.0001;

/**
 * Linear sRGB channels for an oklch colour, without gamut correction.
 */
function linearChannels({ l, c, h }) {
  const radians = (h * Math.PI) / 180;
  const a = c * Math.cos(radians);
  const b = c * Math.sin(radians);

  const long = (l + 0.3963377774 * a + 0.2158037573 * b) ** 3;
  const medium = (l - 0.1055613458 * a - 0.0638541728 * b) ** 3;
  const short = (l - 0.0894841775 * a - 1.291485548 * b) ** 3;

  return [
    4.0767416621 * long - 3.3077115913 * medium + 0.2309699292 * short,
    -1.2684380046 * long + 2.6097574011 * medium - 0.3413193965 * short,
    -0.0041960863 * long - 0.7034186147 * medium + 1.707614701 * short,
  ];
}

const insideSrgb = (channels) =>
  channels.every((channel) => channel >= -GAMUT_EPSILON && channel <= 1 + GAMUT_EPSILON);

/**
 * Lightness of an oklch() colour, normalised to 0…1.
 */
export function lightnessOf(color) {
  return parseOklch(color).l;
}

/**
 * WCAG relative luminance of an oklch() colour.
 *
 * oklch -> oklab -> LMS -> linear sRGB (Björn Ottosson's published matrices),
 * then the sRGB luminance coefficients.
 *
 * Out-of-gamut handling is load-bearing here: 11 of the 16 palette values fall
 * outside sRGB (Tailwind's oklch palette reaches past it), and how they are
 * brought back in changes the answer by a quarter of a ratio point. There is no
 * single right answer to reproduce — CSS Color 4 §14 lets a UA pick among
 * several algorithms, and its own Binary Search with Local MINDE is not what
 * this bisection computes.
 *
 * So do not pretend to know: contrastRatio() measures BOTH the naive
 * per-channel clamp and this chroma reduction, and keeps the WORSE of the two.
 * On rose-600 that is 4.32:1 rather than the 4.53:1 chroma reduction alone
 * reports — the same call MINDE makes, reached without implementing it.
 *
 * This function is the chroma-reduction half: hold lightness and hue, bisect
 * chroma down to the gamut boundary.
 */
function mappedLuminance(color) {
  const { l, c, h } = parseOklch(color);
  let channels = linearChannels({ l, c, h });

  if (!insideSrgb(channels)) {
    let low = 0;
    // Cap the bracket: no sRGB colour has chroma anywhere near 1, and a
    // parseable-but-absurd chroma (10^298 is finite) would leave 32 halvings
    // still astronomically above the boundary, so `low` would stay 0 and the
    // colour would be measured as a neutral grey — passing the gate on a
    // luminance it never had.
    let high = Math.min(c, 1);

    for (let step = 0; step < 32; step += 1) {
      const middle = (low + high) / 2;

      if (insideSrgb(linearChannels({ l, c: middle, h }))) {
        low = middle;
      } else {
        high = middle;
      }
    }

    channels = linearChannels({ l, c: low, h });
  }

  const [red, green, blue] = channels.map((channel) => Math.min(1, Math.max(0, channel)));

  return 0.2126 * red + 0.7152 * green + 0.0722 * blue;
}

/**
 * WCAG relative luminance with out-of-gamut channels simply clamped.
 *
 * The naive reading. Kept precisely because it disagrees with the chroma
 * reduction above, and the disagreement is the uncertainty we refuse to
 * gamble on.
 */
function clampedLuminance(color) {
  const [red, green, blue] = linearChannels(parseOklch(color)).map((channel) =>
    Math.min(1, Math.max(0, channel)),
  );

  return 0.2126 * red + 0.7152 * green + 0.0722 * blue;
}

const ratioFrom = (luminance, one, other) => {
  const [lighter, darker] = [luminance(one), luminance(other)].sort((x, y) => y - x);

  return (lighter + 0.05) / (darker + 0.05);
};

/**
 * WCAG contrast ratio between two oklch() colours, 1…21 — the PESSIMISTIC of
 * the two out-of-gamut readings.
 *
 * Which one a given browser matches depends on the gamut-mapping algorithm it
 * chose, and CSS Color 4 §14 permits several. Taking the lower value means the
 * gate can only ever be stricter than what ships, never laxer.
 */
export function contrastRatio(one, other) {
  return Math.min(ratioFrom(mappedLuminance, one, other), ratioFrom(clampedLuminance, one, other));
}

/**
 * One preset: the primary, the readable neutral to put on it, and the ring.
 *
 * The foreground is CHOSEN BY MEASUREMENT, not by a lightness threshold. An
 * earlier draft switched at L 0.62, which passed a lightness-gap test while
 * pairing rose-600 with near-white at 4.32:1 — below AA, because lightness
 * alone ignores how hue and chroma move relative luminance. Measuring both
 * candidates and refusing anything under AA is what makes "the presets are
 * accessible" a fact rather than a hope: a palette value that cannot carry
 * readable text fails the build instead of shipping.
 */
const presetTuple = (primary) => {
  const onLight = contrastRatio(primary, FOREGROUND_ON_LIGHT);
  const onDark = contrastRatio(primary, FOREGROUND_ON_DARK);
  const best = Math.max(onLight, onDark);

  if (!Number.isFinite(best) || best < MIN_CONTRAST) {
    throw new Error(
      `Primary ${primary} cannot reach WCAG AA (${MIN_CONTRAST}:1) against either neutral — ` +
        `best is ${best.toFixed(2)}:1. Pick a darker or lighter shade in tokens.mjs.`,
    );
  }

  return {
    '--primary': primary,
    '--primary-foreground': onLight > onDark ? FOREGROUND_ON_LIGHT : FOREGROUND_ON_DARK,
    '--ring': primary,
  };
};

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
// string and later into a <style> block. Reuse the strict component pattern
// rather than a charset check: `oklch(0.5.6 0 0)` is all-digits-and-dots and
// would sail through a charset check into the browser as an invalid colour.
const OKLCH_LITERAL = OKLCH_COLOUR;

// A slug becomes a single-quoted PHP array key. Quoting protects dashes and the
// like, but not a quote or a backslash — those would close the string and hand
// PHP a parse error, i.e. a fatal on every request once the file is required.
// Slugs come from our own token source, so this is a build-time assertion about
// what may enter the generated file, not a runtime defence.
const PRESET_SLUG = /^[a-z][a-z0-9-]*$/;

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
    .map(([slug, schemes]) => {
      if (!PRESET_SLUG.test(slug)) {
        throw new Error(`Refusing to emit a preset slug that is not a plain identifier: ${slug}`);
      }

      return (
        `\t${alignedArrow(slug, slugWidth)} [\n` +
        `\t\t${alignedArrow('light', schemeLabelWidth)} [\n${scheme(schemes.light)}\n\t\t],\n` +
        `\t\t${alignedArrow('dark', schemeLabelWidth)} [\n${scheme(schemes.dark)}\n\t\t],\n` +
        `\t],`
      );
    })
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
