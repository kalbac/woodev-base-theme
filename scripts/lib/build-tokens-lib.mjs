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
