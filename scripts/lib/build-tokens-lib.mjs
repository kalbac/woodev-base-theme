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
