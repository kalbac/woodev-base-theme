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
    .map(([slug, value]) => `    --font-${slug}: ${value};`)
    .join('\n');

  return `/* AUTO-GENERATED from src/tokens/tokens.mjs — do not edit. Run \`npm run tokens\`. */
@layer theme {
  :root {
${varsBlock(tokens.colors.light, '    ')}
    --radius: ${tokens.radius};
${fontVars}
  }

  .dark {
${varsBlock(tokens.colors.dark, '    ')}
  }
}
`;
}
